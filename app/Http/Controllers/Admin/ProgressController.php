<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Track;
use App\Models\Course;
use App\Models\LearningProgress;
use App\Services\ProgressTrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgressController extends Controller
{
    public function __construct(
        private ProgressTrackingService $progressTrackingService
    ) {}

    /**
     * Display progress dashboard.
     */
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'active_learners' => LearningProgress::distinct('user_id')->count(),
            'total_progress_records' => LearningProgress::count(),
            'completed_tracks' => LearningProgress::where('progressable_type', 'App\\Models\\Track')
                ->whereNotNull('completed_at')
                ->count(),
            'completed_courses' => LearningProgress::where('progressable_type', 'App\\Models\\Course')
                ->whereNotNull('completed_at')
                ->count(),
        ];

        // Recent progress updates
        $recentProgress = LearningProgress::with(['user', 'progressable'])
            ->latest('updated_at')
            ->take(10)
            ->get();

        return Inertia::render('admin/classroom/ProgressDashboard', [
            'stats' => $stats,
            'recentProgress' => $recentProgress,
        ]);
    }

    /**
     * Show detailed progress for a specific user.
     */
    public function userProgress(User $user)
    {
        // Get track progress
        $trackProgress = $user->trackEnrollments()
            ->with('track')
            ->get()
            ->map(function ($enrollment) use ($user) {
                $progress = $this->progressTrackingService->calculateAggregateProgress($user, $enrollment->track);
                return [
                    'enrollment' => $enrollment,
                    'track' => $enrollment->track,
                    'progress' => $progress,
                ];
            });

        // Get course progress
        $courseProgress = $user->courseEnrollments()
            ->with('course')
            ->get()
            ->map(function ($enrollment) use ($user) {
                $progress = $this->progressTrackingService->calculateAggregateProgress($user, $enrollment->course);
                return [
                    'enrollment' => $enrollment,
                    'course' => $enrollment->course,
                    'progress' => $progress,
                ];
            });

        // Get detailed learning progress records
        $learningProgress = LearningProgress::where('user_id', $user->id)
            ->with('progressable')
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return Inertia::render('admin/classroom/UserProgress', [
            'user' => $user,
            'trackProgress' => $trackProgress,
            'courseProgress' => $courseProgress,
            'learningProgress' => $learningProgress,
        ]);
    }

    /**
     * Show progress analytics for a track.
     */
    public function trackAnalytics(Track $track)
    {
        $enrollments = $track->enrollments()->with('user')->get();

        $analytics = [
            'total_enrollments' => $enrollments->count(),
            'completed_enrollments' => $enrollments->where('completed_at', '!=', null)->count(),
            'average_progress' => $enrollments->avg('progress_percentage'),
            'completion_rate' => $enrollments->count() > 0
                ? ($enrollments->where('completed_at', '!=', null)->count() / $enrollments->count()) * 100
                : 0,
        ];

        // Get detailed progress for each user
        $userProgress = $enrollments->map(function ($enrollment) {
            $progress = $this->progressTrackingService->calculateAggregateProgress($enrollment->user, $enrollment->track);
            return [
                'user' => $enrollment->user,
                'enrollment' => $enrollment,
                'progress' => $progress,
            ];
        });

        return Inertia::render('admin/classroom/TrackAnalytics', [
            'track' => $track,
            'analytics' => $analytics,
            'userProgress' => $userProgress,
        ]);
    }

    /**
     * Show progress analytics for a course.
     */
    public function courseAnalytics(Course $course)
    {
        $enrollments = $course->enrollments()->with('user')->get();

        $analytics = [
            'total_enrollments' => $enrollments->count(),
            'completed_enrollments' => $enrollments->where('completed_at', '!=', null)->count(),
            'average_progress' => $enrollments->avg('progress_percentage'),
            'completion_rate' => $enrollments->count() > 0
                ? ($enrollments->where('completed_at', '!=', null)->count() / $enrollments->count()) * 100
                : 0,
        ];

        // Get detailed progress for each user
        $userProgress = $enrollments->map(function ($enrollment) {
            $progress = $this->progressTrackingService->calculateAggregateProgress($enrollment->user, $enrollment->course);
            return [
                'user' => $enrollment->user,
                'enrollment' => $enrollment,
                'progress' => $progress,
            ];
        });

        return Inertia::render('admin/classroom/CourseAnalytics', [
            'course' => $course,
            'analytics' => $analytics,
            'userProgress' => $userProgress,
        ]);
    }

    /**
     * Update progress for a user manually.
     */
    public function updateProgress(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'progressable_type' => 'required|in:App\\Models\\Track,App\\Models\\Course,App\\Models\\Module,App\\Models\\Lesson',
            'progressable_id' => 'required|integer',
            'completion_percentage' => 'required|numeric|min:0|max:100',
            'time_spent_minutes' => 'nullable|integer|min:0',
            'engagement_score' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $progressableClass = $validated['progressable_type'];
            $progressable = $progressableClass::findOrFail($validated['progressable_id']);

            $metrics = [
                'completion_percentage' => $validated['completion_percentage'],
                'time_spent_minutes' => $validated['time_spent_minutes'] ?? 0,
                'engagement_score' => $validated['engagement_score'] ?? null,
            ];

            $result = $this->progressTrackingService->updateProgress($user, $progressable, $metrics);

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully.',
                'progress' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get progress summary for a learning path.
     */
    public function getProgressSummary(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'progressable_type' => 'required|in:App\\Models\\Track,App\\Models\\Course',
            'progressable_id' => 'required|integer',
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $progressableClass = $validated['progressable_type'];
            $progressable = $progressableClass::findOrFail($validated['progressable_id']);

            $summary = $this->progressTrackingService->getProgressSummary($user, $progressable);

            return response()->json([
                'success' => true,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Export progress data.
     */
    public function exportProgress(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:track,course,user',
            'id' => 'required|integer',
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
