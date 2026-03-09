<?php

namespace App\Services;

use App\Models\User;
use App\Models\Track;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\LearningProgress;
use App\Models\TrackEnrollment;
use App\Models\CourseEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UnifiedProgressReportingService
{
    protected ProgressTrackingService $progressTrackingService;

    public function __construct(ProgressTrackingService $progressTrackingService)
    {
        $this->progressTrackingService = $progressTrackingService;
    }

    /**
     * Get unified progress view for a user across all learning paths.
     *
     * @param User $user
     * @param array $options
     * @return array
     */
    public function getUnifiedProgressView(User $user, array $options = []): array
    {
        $cacheKey = "unified_progress_view_{$user->id}_" . md5(serialize($options));

        return Cache::remember($cacheKey, 300, function () use ($user, $options) {
            $includeDetails = $options['include_details'] ?? false;
            $learningPathTypes = $options['learning_path_types'] ?? ['tracks', 'courses'];

            $progressData = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'overall_statistics' => $this->calculateOverallStatistics($user),
                'learning_paths' => [],
                'comparative_analysis' => [],
                'generated_at' => now(),
            ];

            // Get track progress if requested
            if (in_array('tracks', $learningPathTypes)) {
                $trackProgress = $this->getTrackProgressData($user, $includeDetails);
                $progressData['learning_paths']['tracks'] = $trackProgress;
            }

            // Get course progress if requested
            if (in_array('courses', $learningPathTypes)) {
                $courseProgress = $this->getCourseProgressData($user, $includeDetails);
                $progressData['learning_paths']['courses'] = $courseProgress;
            }

            // Generate comparative analysis if both types are included
            if (count($learningPathTypes) > 1) {
                $progressData['comparative_analysis'] = $this->generateComparativeAnalysis($user, $progressData['learning_paths']);
            }

            return $progressData;
        });
    }

    /**
     * Compare progress between tracks and courses for a user.
     *
     * @param User $user
     * @return array
     */
    public function compareProgressBetweenLearningPaths(User $user): array
    {
        $trackEnrollments = TrackEnrollment::where('user_id', $user->id)->get();
        $courseEnrollments = CourseEnrollment::where('user_id', $user->id)->get();

        $trackStats = $this->calculateLearningPathTypeStatistics($user, 'tracks');
        $courseStats = $this->calculateLearningPathTypeStatistics($user, 'courses');

        return [
            'user_id' => $user->id,
            'comparison_summary' => [
                'tracks' => [
                    'total_enrolled' => $trackEnrollments->count(),
                    'completed' => $trackEnrollments->whereNotNull('completed_at')->count(),
                    'average_progress' => $trackStats['average_progress'],
                    'total_time_spent' => $trackStats['total_time_spent'],
                    'average_engagement' => $trackStats['average_engagement'],
                ],
                'courses' => [
                    'total_enrolled' => $courseEnrollments->count(),
                    'completed' => $courseEnrollments->whereNotNull('completed_at')->count(),
                    'average_progress' => $courseStats['average_progress'],
                    'total_time_spent' => $courseStats['total_time_spent'],
                    'average_engagement' => $courseStats['average_engagement'],
                ],
            ],
            'performance_insights' => $this->generatePerformanceInsights($trackStats, $courseStats),
            'recommendations' => $this->generateLearningRecommendations($user, $trackStats, $courseStats),
        ];
    }

    /**
     * Generate detailed analytics and reporting for administrators.
     *
     * @param array $filters
     * @return array
     */
    public function generateDetailedAnalytics(array $filters = []): array
    {
        $dateRange = $this->parseDateRange($filters);
        $userIds = $filters['user_ids'] ?? null;
        $learningPathTypes = $filters['learning_path_types'] ?? ['tracks', 'courses'];

        $analytics = [
            'report_metadata' => [
                'generated_at' => now(),
                'date_range' => $dateRange,
                'filters_applied' => $filters,
            ],
            'system_overview' => $this->getSystemOverview($dateRange, $userIds),
            'learning_path_analytics' => [],
            'user_engagement_metrics' => $this->getUserEngagementMetrics($dateRange, $userIds),
            'completion_trends' => $this->getCompletionTrends($dateRange, $userIds),
        ];

        // Generate analytics for each learning path type
        foreach ($learningPathTypes as $type) {
            $analytics['learning_path_analytics'][$type] = $this->getLearningPathAnalytics($type, $dateRange, $userIds);
        }

        return $analytics;
    }

    /**
     * Get cross-context progress views for multiple users.
     *
     * @param Collection $users
     * @param array $options
     * @return array
     */
    public function getCrossContextProgressViews(Collection $users, array $options = []): array
    {
        $results = [];
        $includeComparison = $options['include_comparison'] ?? false;

        foreach ($users as $user) {
            $userProgress = $this->getUnifiedProgressView($user, $options);

            if ($includeComparison) {
                $userProgress['comparison_data'] = $this->compareProgressBetweenLearningPaths($user);
            }

            $results[] = $userProgress;
        }

        // Add aggregate statistics if multiple users
        if ($users->count() > 1) {
            $results['aggregate_statistics'] = $this->calculateAggregateStatistics($results);
        }

        return $results;
    }

    /**
     * Calculate overall statistics for a user.
     *
     * @param User $user
     * @return array
     */
    private function calculateOverallStatistics(User $user): array
    {
        $trackEnrollments = TrackEnrollment::where('user_id', $user->id)->get();
        $courseEnrollments = CourseEnrollment::where('user_id', $user->id)->get();

        $totalEnrollments = $trackEnrollments->count() + $courseEnrollments->count();
        $totalCompleted = $trackEnrollments->whereNotNull('completed_at')->count() +
                         $courseEnrollments->whereNotNull('completed_at')->count();

        $allProgress = LearningProgress::where('user_id', $user->id)->get();
        $totalTimeSpent = $allProgress->sum('time_spent_minutes');
        $averageEngagement = $allProgress->whereNotNull('engagement_score')->avg('engagement_score');

        return [
            'total_enrollments' => $totalEnrollments,
            'total_completed' => $totalCompleted,
            'completion_rate' => $totalEnrollments > 0 ? round(($totalCompleted / $totalEnrollments) * 100, 2) : 0,
            'total_time_spent_minutes' => $totalTimeSpent,
            'average_engagement_score' => $averageEngagement ? round($averageEngagement, 2) : null,
            'certificates_earned' => $user->certificates()->count(),
            'achievements_earned' => $user->achievements()->count(),
        ];
    }

    /**
     * Get track progress data for a user.
     *
     * @param User $user
     * @param bool $includeDetails
     * @return array
     */
    private function getTrackProgressData(User $user, bool $includeDetails = false): array
    {
        $trackEnrollments = TrackEnrollment::where('user_id', $user->id)
            ->with('track')
            ->get();

        $trackData = [
            'total_enrolled' => $trackEnrollments->count(),
            'completed' => $trackEnrollments->whereNotNull('completed_at')->count(),
            'in_progress' => $trackEnrollments->whereNull('completed_at')->count(),
            'tracks' => [],
        ];

        foreach ($trackEnrollments as $enrollment) {
            $track = $enrollment->track;
            $progressSummary = $this->progressTrackingService->getProgressSummary($user, $track);

            $trackInfo = [
                'track_id' => $track->id,
                'track_title' => $track->title,
                'enrollment_date' => $enrollment->enrolled_at,
                'completion_date' => $enrollment->completed_at,
                'progress_percentage' => $progressSummary['completion_percentage'],
                'time_spent_minutes' => $progressSummary['detailed_metrics']['time_spent_minutes'],
                'engagement_score' => $progressSummary['detailed_metrics']['engagement_score'],
            ];

            if ($includeDetails) {
                $trackInfo['detailed_progress'] = $progressSummary['aggregate_progress'];
            }

            $trackData['tracks'][] = $trackInfo;
        }

        return $trackData;
    }

    /**
     * Get course progress data for a user.
     *
     * @param User $user
     * @param bool $includeDetails
     * @return array
     */
    private function getCourseProgressData(User $user, bool $includeDetails = false): array
    {
        $courseEnrollments = CourseEnrollment::where('user_id', $user->id)
            ->with('course')
            ->get();

        $courseData = [
            'total_enrolled' => $courseEnrollments->count(),
            'completed' => $courseEnrollments->whereNotNull('completed_at')->count(),
            'in_progress' => $courseEnrollments->whereNull('completed_at')->count(),
            'courses' => [],
        ];

        foreach ($courseEnrollments as $enrollment) {
            $course = $enrollment->course;
            $progressSummary = $this->progressTrackingService->getProgressSummary($user, $course);

            $courseInfo = [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'enrollment_date' => $enrollment->enrolled_at,
                'completion_date' => $enrollment->completed_at,
                'progress_percentage' => $progressSummary['completion_percentage'],
                'time_spent_minutes' => $progressSummary['detailed_metrics']['time_spent_minutes'],
                'engagement_score' => $progressSummary['detailed_metrics']['engagement_score'],
            ];

            if ($includeDetails) {
                $courseInfo['detailed_progress'] = $progressSummary['aggregate_progress'];
            }

            $courseData['courses'][] = $courseInfo;
        }

        return $courseData;
    }

    /**
     * Generate comparative analysis between learning path types.
     *
     * @param User $user
     * @param array $learningPaths
     * @return array
     */
    private function generateComparativeAnalysis(User $user, array $learningPaths): array
    {
        $analysis = [
            'preference_indicators' => [],
            'performance_comparison' => [],
            'engagement_patterns' => [],
        ];

        if (isset($learningPaths['tracks']) && isset($learningPaths['courses'])) {
            $trackData = $learningPaths['tracks'];
            $courseData = $learningPaths['courses'];

            // Calculate preference indicators
            $analysis['preference_indicators'] = [
                'track_preference_score' => $this->calculatePreferenceScore($trackData),
                'course_preference_score' => $this->calculatePreferenceScore($courseData),
                'preferred_learning_path' => $this->determinePreferredLearningPath($trackData, $courseData),
            ];

            // Performance comparison
            $analysis['performance_comparison'] = [
                'tracks_completion_rate' => $trackData['total_enrolled'] > 0 ?
                    round(($trackData['completed'] / $trackData['total_enrolled']) * 100, 2) : 0,
                'courses_completion_rate' => $courseData['total_enrolled'] > 0 ?
                    round(($courseData['completed'] / $courseData['total_enrolled']) * 100, 2) : 0,
                'better_performance_in' => $this->determineBetterPerformance($trackData, $courseData),
            ];

            // Engagement patterns
            $analysis['engagement_patterns'] = $this->analyzeEngagementPatterns($trackData, $courseData);
        }

        return $analysis;
    }

    /**
     * Calculate learning path type statistics.
     *
     * @param User $user
     * @param string $type
     * @return array
     */
    private function calculateLearningPathTypeStatistics(User $user, string $type): array
    {
        if ($type === 'tracks') {
            $enrollments = TrackEnrollment::where('user_id', $user->id)->get();
            $progressQuery = LearningProgress::where('user_id', $user->id)
                ->whereIn('progressable_type', [Track::class, Module::class, Lesson::class]);
        } else {
            $enrollments = CourseEnrollment::where('user_id', $user->id)->get();
            $progressQuery = LearningProgress::where('user_id', $user->id)
                ->whereIn('progressable_type', [Course::class, Module::class, Lesson::class]);
        }

        $progressRecords = $progressQuery->get();

        return [
            'total_enrollments' => $enrollments->count(),
            'completed_enrollments' => $enrollments->whereNotNull('completed_at')->count(),
            'average_progress' => $enrollments->avg('progress_percentage') ?? 0,
            'total_time_spent' => $progressRecords->sum('time_spent_minutes'),
            'average_engagement' => $progressRecords->whereNotNull('engagement_score')->avg('engagement_score'),
        ];
    }

    /**
     * Generate performance insights based on statistics.
     *
     * @param array $trackStats
     * @param array $courseStats
     * @return array
     */
    private function generatePerformanceInsights(array $trackStats, array $courseStats): array
    {
        $insights = [];

        // Completion rate insights
        if ($trackStats['average_progress'] > $courseStats['average_progress']) {
            $insights[] = 'User shows better completion rates in track-based learning paths.';
        } elseif ($courseStats['average_progress'] > $trackStats['average_progress']) {
            $insights[] = 'User shows better completion rates in course-based learning paths.';
        }

        // Engagement insights
        if ($trackStats['average_engagement'] && $courseStats['average_engagement']) {
            if ($trackStats['average_engagement'] > $courseStats['average_engagement']) {
                $insights[] = 'User demonstrates higher engagement in track-based learning.';
            } else {
                $insights[] = 'User demonstrates higher engagement in course-based learning.';
            }
        }

        // Time investment insights
        if ($trackStats['total_time_spent'] > $courseStats['total_time_spent']) {
            $insights[] = 'User invests more time in track-based learning paths.';
        } elseif ($courseStats['total_time_spent'] > $trackStats['total_time_spent']) {
            $insights[] = 'User invests more time in course-based learning paths.';
        }

        return $insights;
    }

    /**
     * Generate learning recommendations based on user performance.
     *
     * @param User $user
     * @param array $trackStats
     * @param array $courseStats
     * @return array
     */
    private function generateLearningRecommendations(User $user, array $trackStats, array $courseStats): array
    {
        $recommendations = [];

        // Recommend based on completion rates
        if ($trackStats['average_progress'] > $courseStats['average_progress'] + 10) {
            $recommendations[] = 'Consider focusing on track-based learning paths for better completion rates.';
        } elseif ($courseStats['average_progress'] > $trackStats['average_progress'] + 10) {
            $recommendations[] = 'Consider focusing on course-based learning paths for better completion rates.';
        }

        // Recommend based on engagement
        if ($trackStats['average_engagement'] && $courseStats['average_engagement']) {
            if ($trackStats['average_engagement'] > $courseStats['average_engagement'] + 0.1) {
                $recommendations[] = 'Track-based learning appears to be more engaging for you.';
            } elseif ($courseStats['average_engagement'] > $trackStats['average_engagement'] + 0.1) {
                $recommendations[] = 'Course-based learning appears to be more engaging for you.';
            }
        }

        // General recommendations
        if (empty($recommendations)) {
            $recommendations[] = 'Both learning path types show similar performance. Continue with your preferred style.';
        }

        return $recommendations;
    }

    /**
     * Parse date range from filters.
     *
     * @param array $filters
     * @return array
     */
    private function parseDateRange(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Get system overview statistics.
     *
     * @param array $dateRange
     * @param array|null $userIds
     * @return array
     */
    private function getSystemOverview(array $dateRange, ?array $userIds): array
    {
        $userQuery = User::query();
        if ($userIds) {
            $userQuery->whereIn('id', $userIds);
        }

        $totalUsers = $userQuery->count();
        $activeUsers = $userQuery->whereHas('trackEnrollments', function ($query) use ($dateRange) {
            $query->whereBetween('enrolled_at', [$dateRange['start_date'], $dateRange['end_date']]);
        })->orWhereHas('courseEnrollments', function ($query) use ($dateRange) {
            $query->whereBetween('enrolled_at', [$dateRange['start_date'], $dateRange['end_date']]);
        })->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_tracks' => Track::where('is_published', true)->count(),
            'total_courses' => Course::where('is_active', true)->count(),
            'total_enrollments' => TrackEnrollment::count() + CourseEnrollment::count(),
        ];
    }

    /**
     * Get user engagement metrics.
     *
     * @param array $dateRange
     * @param array|null $userIds
     * @return array
     */
    private function getUserEngagementMetrics(array $dateRange, ?array $userIds): array
    {
        $progressQuery = LearningProgress::whereBetween('last_accessed_at', [$dateRange['start_date'], $dateRange['end_date']]);

        if ($userIds) {
            $progressQuery->whereIn('user_id', $userIds);
        }

        $progressRecords = $progressQuery->get();

        return [
            'total_learning_sessions' => $progressRecords->count(),
            'average_session_time' => $progressRecords->avg('time_spent_minutes'),
            'average_engagement_score' => $progressRecords->whereNotNull('engagement_score')->avg('engagement_score'),
            'total_time_spent' => $progressRecords->sum('time_spent_minutes'),
        ];
    }

    /**
     * Get completion trends over time.
     *
     * @param array $dateRange
     * @param array|null $userIds
     * @return array
     */
    private function getCompletionTrends(array $dateRange, ?array $userIds): array
    {
        $trackQuery = TrackEnrollment::whereBetween('completed_at', [$dateRange['start_date'], $dateRange['end_date']]);
        $courseQuery = CourseEnrollment::whereBetween('completed_at', [$dateRange['start_date'], $dateRange['end_date']]);

        if ($userIds) {
            $trackQuery->whereIn('user_id', $userIds);
            $courseQuery->whereIn('user_id', $userIds);
        }

        return [
            'track_completions' => $trackQuery->count(),
            'course_completions' => $courseQuery->count(),
            'total_completions' => $trackQuery->count() + $courseQuery->count(),
        ];
    }

    /**
     * Get learning path analytics for a specific type.
     *
     * @param string $type
     * @param array $dateRange
     * @param array|null $userIds
     * @return array
     */
    private function getLearningPathAnalytics(string $type, array $dateRange, ?array $userIds): array
    {
        if ($type === 'tracks') {
            $enrollmentQuery = TrackEnrollment::whereBetween('enrolled_at', [$dateRange['start_date'], $dateRange['end_date']]);
            $completionQuery = TrackEnrollment::whereBetween('completed_at', [$dateRange['start_date'], $dateRange['end_date']]);
        } else {
            $enrollmentQuery = CourseEnrollment::whereBetween('enrolled_at', [$dateRange['start_date'], $dateRange['end_date']]);
            $completionQuery = CourseEnrollment::whereBetween('completed_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        if ($userIds) {
            $enrollmentQuery->whereIn('user_id', $userIds);
            $completionQuery->whereIn('user_id', $userIds);
        }

        $enrollments = $enrollmentQuery->get();
        $completions = $completionQuery->get();

        return [
            'new_enrollments' => $enrollments->count(),
            'completions' => $completions->count(),
            'average_progress' => $enrollments->avg('progress_percentage'),
            'completion_rate' => $enrollments->count() > 0 ?
                round(($completions->count() / $enrollments->count()) * 100, 2) : 0,
        ];
    }

    /**
     * Calculate preference score for a learning path type.
     *
     * @param array $pathData
     * @return float
     */
    private function calculatePreferenceScore(array $pathData): float
    {
        if ($pathData['total_enrolled'] === 0) {
            return 0.0;
        }

        $completionRate = ($pathData['completed'] / $pathData['total_enrolled']) * 100;
        $engagementBonus = 0;

        // Add engagement bonus if available
        foreach ($pathData['tracks'] ?? $pathData['courses'] ?? [] as $item) {
            if ($item['engagement_score']) {
                $engagementBonus += $item['engagement_score'] * 10;
            }
        }

        return round($completionRate + $engagementBonus, 2);
    }

    /**
     * Determine preferred learning path based on data.
     *
     * @param array $trackData
     * @param array $courseData
     * @return string
     */
    private function determinePreferredLearningPath(array $trackData, array $courseData): string
    {
        $trackScore = $this->calculatePreferenceScore($trackData);
        $courseScore = $this->calculatePreferenceScore($courseData);

        if ($trackScore > $courseScore) {
            return 'tracks';
        } elseif ($courseScore > $trackScore) {
            return 'courses';
        }

        return 'no_preference';
    }

    /**
     * Determine which learning path type shows better performance.
     *
     * @param array $trackData
     * @param array $courseData
     * @return string
     */
    private function determineBetterPerformance(array $trackData, array $courseData): string
    {
        $trackCompletionRate = $trackData['total_enrolled'] > 0 ?
            ($trackData['completed'] / $trackData['total_enrolled']) * 100 : 0;
        $courseCompletionRate = $courseData['total_enrolled'] > 0 ?
            ($courseData['completed'] / $courseData['total_enrolled']) * 100 : 0;

        if ($trackCompletionRate > $courseCompletionRate) {
            return 'tracks';
        } elseif ($courseCompletionRate > $trackCompletionRate) {
            return 'courses';
        }

        return 'equal';
    }

    /**
     * Analyze engagement patterns between learning path types.
     *
     * @param array $trackData
     * @param array $courseData
     * @return array
     */
    private function analyzeEngagementPatterns(array $trackData, array $courseData): array
    {
        $trackEngagement = [];
        $courseEngagement = [];

        foreach ($trackData['tracks'] ?? [] as $track) {
            if ($track['engagement_score']) {
                $trackEngagement[] = $track['engagement_score'];
            }
        }

        foreach ($courseData['courses'] ?? [] as $course) {
            if ($course['engagement_score']) {
                $courseEngagement[] = $course['engagement_score'];
            }
        }

        return [
            'track_average_engagement' => !empty($trackEngagement) ? round(array_sum($trackEngagement) / count($trackEngagement), 2) : null,
            'course_average_engagement' => !empty($courseEngagement) ? round(array_sum($courseEngagement) / count($courseEngagement), 2) : null,
            'engagement_consistency' => [
                'tracks' => $this->calculateEngagementConsistency($trackEngagement),
                'courses' => $this->calculateEngagementConsistency($courseEngagement),
            ],
        ];
    }

    /**
     * Calculate engagement consistency (standard deviation).
     *
     * @param array $engagementScores
     * @return float|null
     */
    private function calculateEngagementConsistency(array $engagementScores): ?float
    {
        if (count($engagementScores) < 2) {
            return null;
        }

        $mean = array_sum($engagementScores) / count($engagementScores);
        $variance = array_sum(array_map(function ($score) use ($mean) {
            return pow($score - $mean, 2);
        }, $engagementScores)) / count($engagementScores);

        return round(sqrt($variance), 3);
    }

    /**
     * Calculate aggregate statistics for multiple users.
     *
     * @param array $userProgressData
     * @return array
     */
    private function calculateAggregateStatistics(array $userProgressData): array
    {
        $totalUsers = count($userProgressData);
        $totalEnrollments = 0;
        $totalCompletions = 0;
        $totalTimeSpent = 0;

        foreach ($userProgressData as $userData) {
            if (isset($userData['overall_statistics'])) {
                $stats = $userData['overall_statistics'];
                $totalEnrollments += $stats['total_enrollments'];
                $totalCompletions += $stats['total_completed'];
                $totalTimeSpent += $stats['total_time_spent_minutes'];
            }
        }

        return [
            'total_users_analyzed' => $totalUsers,
            'total_enrollments' => $totalEnrollments,
            'total_completions' => $totalCompletions,
            'overall_completion_rate' => $totalEnrollments > 0 ? round(($totalCompletions / $totalEnrollments) * 100, 2) : 0,
            'total_time_spent_minutes' => $totalTimeSpent,
            'average_enrollments_per_user' => $totalUsers > 0 ? round($totalEnrollments / $totalUsers, 2) : 0,
        ];
    }
}
