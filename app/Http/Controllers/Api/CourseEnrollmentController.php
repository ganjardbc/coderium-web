<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Services\ProgressTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseEnrollmentController extends Controller
{
    public function __construct(
        private ProgressTrackingService $progressTrackingService
    ) {}

    /**
     * Enroll the authenticated user in a course.
     */
    public function enroll(Request $request, string $courseSlug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required for enrollment.');
        }

        // Check if course is active
        if (!$course->is_active) {
            return response()->json([
                'message' => 'Course is not available for enrollment.',
            ], 422);
        }

        try {
            // Check if already enrolled
            $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existingEnrollment) {
                return response()->json([
                    'message' => 'Already enrolled in this course.',
                    'enrollment' => [
                        'id' => $existingEnrollment->id,
                        'enrolled_at' => $existingEnrollment->enrolled_at,
                        'progress_percentage' => $existingEnrollment->progress_percentage,
                        'completed_at' => $existingEnrollment->completed_at,
                    ],
                ], 200);
            }

            $enrollment = CourseEnrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'enrolled_at' => now(),
                'progress_percentage' => 0.00,
            ]);

            return response()->json([
                'message' => 'Successfully enrolled in course.',
                'enrollment' => [
                    'id' => $enrollment->id,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'progress_percentage' => $enrollment->progress_percentage,
                    'completed_at' => $enrollment->completed_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Enrollment failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unenroll the authenticated user from a course.
     */
    public function unenroll(Request $request, string $courseSlug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        try {
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'message' => 'Not enrolled in this course.',
                ], 422);
            }

            // Check if course is completed (might want to prevent unenrollment)
            if ($enrollment->completed_at) {
                return response()->json([
                    'message' => 'Cannot unenroll from completed course.',
                ], 422);
            }

            $enrollment->delete();

            return response()->json([
                'message' => 'Successfully unenrolled from course.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unenrollment failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get enrollment status for the authenticated user.
     */
    public function status(Request $request, string $courseSlug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'enrolled' => false,
                'enrollment' => null,
                'progress' => null,
            ]);
        }

        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'enrolled' => false,
                'enrollment' => null,
                'progress' => null,
            ]);
        }

        // Get detailed progress data
        $progressData = $this->progressTrackingService->getProgressSummary($user, $course);

        return response()->json([
            'enrolled' => true,
            'enrollment' => [
                'id' => $enrollment->id,
                'enrolled_at' => $enrollment->enrolled_at,
                'completed_at' => $enrollment->completed_at,
                'progress_percentage' => $enrollment->progress_percentage,
            ],
            'progress' => $progressData,
        ]);
    }

    /**
     * Update enrollment progress.
     */
    public function updateProgress(Request $request, string $courseSlug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $validated = $request->validate([
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'time_spent_minutes' => 'nullable|integer|min:0',
            'engagement_score' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'message' => 'Not enrolled in this course.',
                ], 422);
            }

            // Update enrollment progress
            $updateData = [];
            if (isset($validated['progress_percentage'])) {
                $updateData['progress_percentage'] = $validated['progress_percentage'];

                // Mark as completed if progress reaches 100%
                if ($validated['progress_percentage'] >= 100 && !$enrollment->completed_at) {
                    $updateData['completed_at'] = now();
                }
            }

            if (!empty($updateData)) {
                $enrollment->update($updateData);
            }

            // Update detailed progress tracking
            $metrics = array_filter([
                'completion_percentage' => $validated['progress_percentage'] ?? null,
                'time_spent_minutes' => $validated['time_spent_minutes'] ?? null,
                'engagement_score' => $validated['engagement_score'] ?? null,
            ]);

            if (!empty($metrics)) {
                $this->progressTrackingService->updateProgress($user, $course, $metrics);
            }

            // Get updated progress data
            $progressData = $this->progressTrackingService->getProgressSummary($user, $course);

            return response()->json([
                'message' => 'Progress updated successfully.',
                'enrollment' => [
                    'id' => $enrollment->id,
                    'progress_percentage' => $enrollment->fresh()->progress_percentage,
                    'completed_at' => $enrollment->fresh()->completed_at,
                ],
                'progress' => $progressData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Progress update failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get user's course enrollments.
     */
    public function userEnrollments(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $query = CourseEnrollment::where('user_id', $user->id)
            ->with(['course']);

        // Apply filters
        if ($request->has('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'completed') {
                $query->completed();
            }
        }

        $enrollments = $query->latest('enrolled_at')->get();

        // Add progress data for each enrollment
        $enrollmentsWithProgress = $enrollments->map(function ($enrollment) use ($user) {
            $progressData = $this->progressTrackingService->getProgressSummary($user, $enrollment->course);

            return [
                'id' => $enrollment->id,
                'course' => [
                    'id' => $enrollment->course->id,
                    'title' => $enrollment->course->title,
                    'slug' => $enrollment->course->slug,
                    'description' => $enrollment->course->description,
                    'estimated_duration' => $enrollment->course->estimated_duration,
                ],
                'enrolled_at' => $enrollment->enrolled_at,
                'completed_at' => $enrollment->completed_at,
                'progress_percentage' => $enrollment->progress_percentage,
                'progress' => $progressData,
            ];
        });

        return response()->json([
            'enrollments' => $enrollmentsWithProgress,
            'stats' => [
                'total' => $enrollments->count(),
                'active' => $enrollments->where('completed_at', null)->count(),
                'completed' => $enrollments->where('completed_at', '!=', null)->count(),
            ],
        ]);
    }

    /**
     * Bulk enroll users in courses (admin only).
     */
    public function bulkEnroll(Request $request): \Illuminate\Http\JsonResponse
    {
        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to perform bulk enrollment.');
        }

        $validated = $request->validate([
            'enrollments' => 'required|array',
            'enrollments.*.user_id' => 'required|exists:users,id',
            'enrollments.*.course_id' => 'required|exists:courses,id',
        ]);

        try {
            $enrolled = 0;
            $skipped = 0;
            $errors = [];

            DB::transaction(function () use ($validated, &$enrolled, &$skipped, &$errors) {
                foreach ($validated['enrollments'] as $enrollmentData) {
                    try {
                        // Check if enrollment already exists
                        $existingEnrollment = CourseEnrollment::where('user_id', $enrollmentData['user_id'])
                            ->where('course_id', $enrollmentData['course_id'])
                            ->first();

                        if ($existingEnrollment) {
                            $skipped++;
                            continue;
                        }

                        CourseEnrollment::create([
                            'user_id' => $enrollmentData['user_id'],
                            'course_id' => $enrollmentData['course_id'],
                            'enrolled_at' => now(),
                            'progress_percentage' => 0.00,
                        ]);
                        $enrolled++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to enroll user {$enrollmentData['user_id']} in course {$enrollmentData['course_id']}: {$e->getMessage()}";
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Bulk enrollment completed.',
                'results' => [
                    'enrolled' => $enrolled,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk enrollment failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get enrollment statistics (admin only).
     */
    public function statistics(Request $request): \Illuminate\Http\JsonResponse
    {
        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to view enrollment statistics.');
        }

        $stats = [
            'total_enrollments' => CourseEnrollment::count(),
            'active_enrollments' => CourseEnrollment::active()->count(),
            'completed_enrollments' => CourseEnrollment::completed()->count(),
            'average_progress' => CourseEnrollment::avg('progress_percentage'),
        ];

        // Course-wise statistics
        $courseStats = Course::withCount([
            'enrollments',
            'enrollments as active_enrollments_count' => function ($query) {
                $query->active();
            },
            'enrollments as completed_enrollments_count' => function ($query) {
                $query->completed();
            }
        ])->get()->map(function ($course) {
            return [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'total_enrollments' => $course->enrollments_count,
                'active_enrollments' => $course->active_enrollments_count,
                'completed_enrollments' => $course->completed_enrollments_count,
                'completion_rate' => $course->enrollments_count > 0
                    ? ($course->completed_enrollments_count / $course->enrollments_count) * 100
                    : 0,
            ];
        });

        return response()->json([
            'overall_stats' => $stats,
            'course_stats' => $courseStats,
        ]);
    }
}
