<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningProgress;
use App\Models\LessonProgress;
use App\Models\CourseEnrollment;
use App\Models\TrackEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->get('userId', auth()->id());

        $progress = [
            'user_id' => $userId,
            'overall_progress' => $this->calculateOverallProgress($userId),
            'course_progress' => $this->getCourseProgress($userId),
            'track_progress' => $this->getTrackProgress($userId),
            'recent_activity' => $this->getRecentActivity($userId),
        ];

        return response()->json($progress);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'lesson_id' => 'sometimes|exists:lessons,id',
            'course_id' => 'sometimes|exists:courses,id',
            'track_id' => 'sometimes|exists:tracks,id',
            'completed' => 'boolean',
            'time_spent' => 'sometimes|integer|min:0',
        ]);

        $userId = $validated['user_id'] ?? auth()->id();

        // Update lesson progress if lesson_id is provided
        if (isset($validated['lesson_id'])) {
            $updateData = [
                'time_spent' => $validated['time_spent'] ?? 0,
            ];

            // Set completed_at if completed is true
            if (isset($validated['completed']) && $validated['completed']) {
                $updateData['completed_at'] = now();
            }

            $lessonProgress = LessonProgress::updateOrCreate(
                [
                    'user_id' => $userId,
                    'lesson_id' => $validated['lesson_id'],
                ],
                $updateData
            );
        }

        // Update course enrollment progress if course_id is provided
        if (isset($validated['course_id'])) {
            $progressPercentage = $validated['progress_percentage'] ?? 0;
            CourseEnrollment::where('user_id', $userId)
                ->where('course_id', $validated['course_id'])
                ->update(['progress_percentage' => $progressPercentage]);
        }

        // Update track enrollment progress if track_id is provided
        if (isset($validated['track_id'])) {
            $progressPercentage = $validated['progress_percentage'] ?? 0;
            TrackEnrollment::where('user_id', $userId)
                ->where('track_id', $validated['track_id'])
                ->update(['progress_percentage' => $progressPercentage]);
        }

        return response()->json(['message' => 'Progress updated successfully']);
    }

    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.user_id' => 'sometimes|exists:users,id',
            'updates.*.lesson_id' => 'sometimes|exists:lessons,id',
            'updates.*.completed' => 'boolean',
            'updates.*.time_spent' => 'sometimes|integer|min:0',
        ]);

        $results = [];

        DB::transaction(function () use ($validated, &$results) {
            foreach ($validated['updates'] as $update) {
                try {
                    $userId = $update['user_id'] ?? auth()->id();

                    if (isset($update['lesson_id'])) {
                        $updateData = [
                            'time_spent' => $update['time_spent'] ?? 0,
                        ];

                        // Set completed_at if completed is true
                        if (isset($update['completed']) && $update['completed']) {
                            $updateData['completed_at'] = now();
                        }

                        LessonProgress::updateOrCreate(
                            [
                                'user_id' => $userId,
                                'lesson_id' => $update['lesson_id'],
                            ],
                            $updateData
                        );
                    }

                    $results[] = ['status' => 'success'];
                } catch (\Exception $e) {
                    $results[] = ['status' => 'error', 'message' => $e->getMessage()];
                }
            }
        });

        return response()->json(['results' => $results]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $timeRange = $request->get('timeRange', '30d');
        $userId = $request->get('userId', auth()->id());

        $metrics = [
            'total_lessons_completed' => $this->getTotalLessonsCompleted($userId, $timeRange),
            'total_time_spent' => $this->getTotalTimeSpent($userId, $timeRange),
            'completion_rate' => $this->getCompletionRate($userId, $timeRange),
            'streak_days' => $this->getStreakDays($userId),
            'achievements_earned' => $this->getAchievementsCount($userId, $timeRange),
        ];

        return response()->json($metrics);
    }

    public function export(Request $request)
    {
        $format = $request->get('format', 'json');
        $userId = $request->get('userId', auth()->id());

        $progress = $this->index($request)->getData();

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($progress);
            case 'pdf':
                return $this->exportToPdf($progress);
            default:
                return response()->json($progress);
        }
    }

    private function calculateOverallProgress($userId): float
    {
        $totalLessons = LessonProgress::where('user_id', $userId)->count();
        $completedLessons = LessonProgress::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->count();

        return $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;
    }

    private function getCourseProgress($userId): array
    {
        return CourseEnrollment::with('course')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'course_id' => $enrollment->course_id,
                    'title' => $enrollment->course->title,
                    'progress' => $enrollment->progress_percentage ?? 0,
                ];
            })
            ->toArray();
    }

    private function getTrackProgress($userId): array
    {
        return TrackEnrollment::with('track')
            ->where('user_id', $userId)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'track_id' => $enrollment->track_id,
                    'title' => $enrollment->track->title,
                    'progress' => $enrollment->progress_percentage ?? 0,
                ];
            })
            ->toArray();
    }

    private function getRecentActivity($userId): array
    {
        return LessonProgress::with(['lesson'])
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($progress) {
                return [
                    'lesson_id' => $progress->lesson_id,
                    'lesson_title' => $progress->lesson->title,
                    'completed' => !is_null($progress->completed_at),
                    'completed_at' => $progress->completed_at,
                    'time_spent' => $progress->time_spent,
                    'updated_at' => $progress->updated_at,
                ];
            })
            ->toArray();
    }

    private function getTotalLessonsCompleted($userId, $timeRange): int
    {
        $query = LessonProgress::where('user_id', $userId)
            ->whereNotNull('completed_at');

        if ($timeRange !== 'all') {
            $days = (int) str_replace('d', '', $timeRange);
            $query->where('completed_at', '>=', now()->subDays($days));
        }

        return $query->count();
    }

    private function getTotalTimeSpent($userId, $timeRange): int
    {
        $query = LessonProgress::where('user_id', $userId);

        if ($timeRange !== 'all') {
            $days = (int) str_replace('d', '', $timeRange);
            $query->where('updated_at', '>=', now()->subDays($days));
        }

        return $query->sum('time_spent') ?? 0;
    }

    private function getCompletionRate($userId, $timeRange): float
    {
        $query = LessonProgress::where('user_id', $userId);

        if ($timeRange !== 'all') {
            $days = (int) str_replace('d', '', $timeRange);
            $query->where('updated_at', '>=', now()->subDays($days));
        }

        $total = $query->count();
        $completed = $query->whereNotNull('completed_at')->count();

        return $total > 0 ? ($completed / $total) * 100 : 0;
    }

    private function getStreakDays($userId): int
    {
        // Simple implementation - you might want to make this more sophisticated
        $recentDays = LessonProgress::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(completed_at) as date')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->pluck('date')
            ->toArray();

        $streak = 0;
        $currentDate = now()->format('Y-m-d');

        foreach ($recentDays as $date) {
            if ($date === $currentDate) {
                $streak++;
                $currentDate = now()->subDay()->format('Y-m-d');
            } else {
                break;
            }
        }

        return $streak;
    }

    private function getAchievementsCount($userId, $timeRange): int
    {
        // Placeholder - implement based on your achievement system
        return 0;
    }

    private function exportToCsv($data)
    {
        // Implement CSV export
        return response()->json(['message' => 'CSV export not implemented yet']);
    }

    private function exportToPdf($data)
    {
        // Implement PDF export
        return response()->json(['message' => 'PDF export not implemented yet']);
    }
}
