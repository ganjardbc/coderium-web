<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Services\ProgressTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CourseEnrollmentController extends Controller
{
    public function __construct(
        private ProgressTrackingService $progressTrackingService
    ) {}

    /**
     * Display a listing of course enrollments.
     */
    public function index(Request $request)
    {
        $query = CourseEnrollment::with(['user', 'course']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('course', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->get('course_id'));
        }

        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'completed') {
                $query->completed();
            }
        }

        if ($request->filled('progress_range')) {
            $range = $request->get('progress_range');
            if ($range === 'low') {
                $query->where('progress_percentage', '<', 25);
            } elseif ($range === 'medium') {
                $query->whereBetween('progress_percentage', [25, 75]);
            } elseif ($range === 'high') {
                $query->where('progress_percentage', '>', 75);
            }
        }

        $enrollments = $query->latest('enrolled_at')->paginate(20);

        // Get filter options
        $courses = Course::select('id', 'title')->where('is_active', true)->orderBy('title')->get();

        return Inertia::render('admin/classroom/CourseEnrollmentIndex', [
            'enrollments' => $enrollments,
            'courses' => $courses,
            'filters' => $request->only(['search', 'course_id', 'status', 'progress_range']),
        ]);
    }

    /**
     * Show the form for creating a new enrollment.
     */
    public function create()
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        $courses = Course::select('id', 'title')->where('is_active', true)->orderBy('title')->get();

        return Inertia::render('admin/classroom/CourseEnrollmentCreate', [
            'users' => $users,
            'courses' => $courses,
        ]);
    }

    /**
     * Store a newly created enrollment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'enrolled_at' => 'nullable|date',
        ]);

        try {
            // Check if enrollment already exists
            $existingEnrollment = CourseEnrollment::where('user_id', $validated['user_id'])
                ->where('course_id', $validated['course_id'])
                ->first();

            if ($existingEnrollment) {
                return redirect()->back()
                    ->withErrors(['enrollment' => 'User is already enrolled in this course.']);
            }

            $validated['enrolled_at'] = $validated['enrolled_at'] ?? now();
            $validated['progress_percentage'] = 0.00;

            $enrollment = CourseEnrollment::create($validated);

            return redirect()->route('admin.classroom.course-enrollments.index')
                ->with('success', 'Course enrollment created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['enrollment' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified enrollment.
     */
    public function show(CourseEnrollment $courseEnrollment)
    {
        $courseEnrollment->load(['user', 'course.modules.lessons']);

        // Get detailed progress data
        $progressData = $this->progressTrackingService->getProgressSummary(
            $courseEnrollment->user,
            $courseEnrollment->course
        );

        return Inertia::render('admin/classroom/CourseEnrollmentShow', [
            'enrollment' => $courseEnrollment,
            'progressData' => $progressData,
        ]);
    }

    /**
     * Update the specified enrollment.
     */
    public function update(Request $request, CourseEnrollment $courseEnrollment)
    {
        $validated = $request->validate([
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'completed_at' => 'nullable|date',
        ]);

        // If progress is 100% and no completion date, set it
        if (isset($validated['progress_percentage']) &&
            $validated['progress_percentage'] >= 100 &&
            !$courseEnrollment->completed_at &&
            !isset($validated['completed_at'])) {
            $validated['completed_at'] = now();
        }

        // If completion date is being removed, ensure progress is less than 100%
        if (isset($validated['completed_at']) &&
            is_null($validated['completed_at']) &&
            $courseEnrollment->progress_percentage >= 100) {
            $validated['progress_percentage'] = 99.99;
        }

        $courseEnrollment->update($validated);

        return redirect()->route('admin.classroom.course-enrollments.index')
            ->with('success', 'Course enrollment updated successfully.');
    }

    /**
     * Remove the specified enrollment.
     */
    public function destroy(CourseEnrollment $courseEnrollment)
    {
        $courseEnrollment->delete();

        return redirect()->route('admin.classroom.course-enrollments.index')
            ->with('success', 'Course enrollment deleted successfully.');
    }

    /**
     * Bulk enroll users in a course.
     */
    public function bulkEnroll(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'enrolled_at' => 'nullable|date',
        ]);

        try {
            $course = Course::findOrFail($validated['course_id']);
            $enrolledAt = $validated['enrolled_at'] ?? now();

            $enrolled = 0;
            $skipped = 0;
            $errors = [];

            DB::transaction(function () use ($validated, $enrolledAt, &$enrolled, &$skipped, &$errors) {
                foreach ($validated['user_ids'] as $userId) {
                    try {
                        // Check if enrollment already exists
                        $existingEnrollment = CourseEnrollment::where('user_id', $userId)
                            ->where('course_id', $validated['course_id'])
                            ->first();

                        if ($existingEnrollment) {
                            $skipped++;
                            continue;
                        }

                        CourseEnrollment::create([
                            'user_id' => $userId,
                            'course_id' => $validated['course_id'],
                            'enrolled_at' => $enrolledAt,
                            'progress_percentage' => 0.00,
                        ]);
                        $enrolled++;
                    } catch (\Exception $e) {
                        $user = User::find($userId);
                        $errors[] = "Failed to enroll {$user->name}: {$e->getMessage()}";
                    }
                }
            });

            $message = "Bulk enrollment completed. Enrolled: {$enrolled}, Skipped: {$skipped}";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }

            return redirect()->route('admin.classroom.course-enrollments.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['bulk_enroll' => $e->getMessage()]);
        }
    }

    /**
     * Bulk update enrollment progress.
     */
    public function bulkUpdateProgress(Request $request)
    {
        $validated = $request->validate([
            'enrollment_ids' => 'required|array',
            'enrollment_ids.*' => 'exists:course_enrollments,id',
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'mark_completed' => 'boolean',
        ]);

        try {
            $updated = 0;
            $errors = [];

            DB::transaction(function () use ($validated, &$updated, &$errors) {
                foreach ($validated['enrollment_ids'] as $enrollmentId) {
                    try {
                        $enrollment = CourseEnrollment::findOrFail($enrollmentId);

                        $updateData = [
                            'progress_percentage' => $validated['progress_percentage'],
                        ];

                        if ($validated['mark_completed'] ?? false) {
                            $updateData['completed_at'] = now();
                            $updateData['progress_percentage'] = 100.00;
                        } elseif ($validated['progress_percentage'] >= 100) {
                            $updateData['completed_at'] = now();
                        }

                        $enrollment->update($updateData);
                        $updated++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to update enrollment {$enrollmentId}: {$e->getMessage()}";
                    }
                }
            });

            $message = "Bulk update completed. Updated: {$updated}";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }

            return redirect()->route('admin.classroom.course-enrollments.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['bulk_update' => $e->getMessage()]);
        }
    }

    /**
     * Get enrollment statistics for a course.
     */
    public function courseStatistics(Course $course)
    {
        $enrollments = $course->enrollments();

        $stats = [
            'total_enrollments' => $enrollments->count(),
            'active_enrollments' => $enrollments->active()->count(),
            'completed_enrollments' => $enrollments->completed()->count(),
            'average_progress' => $enrollments->avg('progress_percentage'),
            'completion_rate' => $enrollments->count() > 0
                ? ($enrollments->completed()->count() / $enrollments->count()) * 100
                : 0,
        ];

        // Progress distribution
        $progressDistribution = [
            'low' => $enrollments->where('progress_percentage', '<', 25)->count(),
            'medium' => $enrollments->whereBetween('progress_percentage', [25, 75])->count(),
            'high' => $enrollments->where('progress_percentage', '>', 75)->count(),
        ];

        // Recent enrollments
        $recentEnrollments = $enrollments->with('user')
            ->latest('enrolled_at')
            ->take(10)
            ->get();

        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'stats' => $stats,
            'progress_distribution' => $progressDistribution,
            'recent_enrollments' => $recentEnrollments,
        ]);
    }

    /**
     * Export enrollment data.
     */
    public function exportEnrollments(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'status' => 'nullable|in:active,completed',
            'format' => 'required|in:csv,json',
        ]);

        try {
            // This would implement actual export functionality
            // For now, return a success message
            return response()->json([
                'success' => true,
                'message' => 'Export functionality will be implemented.',
                'download_url' => '#', // Would be actual download URL
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
