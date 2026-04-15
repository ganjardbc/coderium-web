<?php

namespace App\Services;

use App\Models\User;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrackEnrollment;
use App\Models\LearningProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class ClassroomCacheService
{
    /**
     * Cache duration in minutes
     */
    private const CACHE_DURATION = 60; // 1 hour
    private const LONG_CACHE_DURATION = 1440; // 24 hours
    private const SHORT_CACHE_DURATION = 15; // 15 minutes

    /**
     * Cache key prefixes
     */
    private const PREFIX_TRACK = 'classroom:track:';
    private const PREFIX_COURSE = 'classroom:course:';
    private const PREFIX_MODULE = 'classroom:module:';
    private const PREFIX_PROGRESS = 'classroom:progress:';
    private const PREFIX_ENROLLMENT = 'classroom:enrollment:';
    private const PREFIX_STATS = 'classroom:stats:';

    /**
     * Get cached track with relationships
     */
    public function getTrackWithContent(string $slug): ?Track
    {
        $cacheKey = self::PREFIX_TRACK . "content:{$slug}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($slug) {
            return Track::with(['levels.modules.lessons'])
                ->where('slug', $slug)
                ->where('is_published', true)
                ->first();
        });
    }

    /**
     * Get cached course with relationships
     */
    public function getCourseWithContent(string $slug): ?Course
    {
        $cacheKey = self::PREFIX_COURSE . "content:{$slug}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($slug) {
            return Course::with(['modules.lessons'])
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Get cached module with lessons
     */
    public function getModuleWithLessons(int $moduleId): ?Module
    {
        $cacheKey = self::PREFIX_MODULE . "lessons:{$moduleId}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($moduleId) {
            return Module::with(['lessons' => function ($query) {
                $query->where('is_published', true)->orderBy('order_index');
            }])
                ->where('id', $moduleId)
                ->where('is_published', true)
                ->first();
        });
    }

    /**
     * Get cached user progress for a learning path
     */
    public function getUserProgress(int $userId, string $learningPathType, int $learningPathId): ?array
    {
        $cacheKey = self::PREFIX_PROGRESS . "user:{$userId}:{$learningPathType}:{$learningPathId}";

        return Cache::remember($cacheKey, self::SHORT_CACHE_DURATION, function () use ($userId, $learningPathType, $learningPathId) {
            $progressTrackingService = app(ProgressTrackingService::class);
            $user = User::find($userId);

            if (!$user) {
                return null;
            }

            if ($learningPathType === 'track') {
                $track = Track::find($learningPathId);
                return $track ? $progressTrackingService->calculateAggregateProgress($user, $track) : null;
            } elseif ($learningPathType === 'course') {
                $course = Course::find($learningPathId);
                return $course ? $progressTrackingService->calculateAggregateProgress($user, $course) : null;
            }

            return null;
        });
    }

    /**
     * Get cached user enrollments
     */
    public function getUserEnrollments(int $userId): array
    {
        $cacheKey = self::PREFIX_ENROLLMENT . "user:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($userId) {
            $trackEnrollments = TrackEnrollment::with('track')
                ->where('user_id', $userId)
                ->get()
                ->map(function ($enrollment) {
                    return [
                        'type' => 'track',
                        'id' => $enrollment->track->id,
                        'title' => $enrollment->track->title,
                        'slug' => $enrollment->track->slug,
                        'progress_percentage' => $enrollment->progress_percentage,
                        'enrolled_at' => $enrollment->enrolled_at,
                        'completed_at' => $enrollment->completed_at,
                    ];
                });

            $courseEnrollments = CourseEnrollment::with('course')
                ->where('user_id', $userId)
                ->get()
                ->map(function ($enrollment) {
                    return [
                        'type' => 'course',
                        'id' => $enrollment->course->id,
                        'title' => $enrollment->course->title,
                        'slug' => $enrollment->course->slug,
                        'progress_percentage' => $enrollment->progress_percentage,
                        'enrolled_at' => $enrollment->enrolled_at,
                        'completed_at' => $enrollment->completed_at,
                    ];
                });

            return [
                'tracks' => $trackEnrollments->toArray(),
                'courses' => $courseEnrollments->toArray(),
                'total' => $trackEnrollments->count() + $courseEnrollments->count(),
            ];
        });
    }

    /**
     * Get cached learning path statistics
     */
    public function getLearningPathStats(string $type, int $id): array
    {
        $cacheKey = self::PREFIX_STATS . "{$type}:{$id}";

        return Cache::remember($cacheKey, self::LONG_CACHE_DURATION, function () use ($type, $id) {
            if ($type === 'track') {
                return $this->calculateTrackStats($id);
            } elseif ($type === 'course') {
                return $this->calculateCourseStats($id);
            }

            return [];
        });
    }

    /**
     * Get cached popular tracks
     */
    public function getPopularTracks(int $limit = 10): Collection
    {
        $cacheKey = self::PREFIX_TRACK . "popular:{$limit}";

        return Cache::remember($cacheKey, self::LONG_CACHE_DURATION, function () use ($limit) {
            return Track::withCount('enrollments')
                ->where('is_published', true)
                ->orderBy('enrollments_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get cached popular courses
     */
    public function getPopularCourses(int $limit = 10): Collection
    {
        $cacheKey = self::PREFIX_COURSE . "popular:{$limit}";

        return Cache::remember($cacheKey, self::LONG_CACHE_DURATION, function () use ($limit) {
            return Course::withCount('enrollments')
                ->where('is_active', true)
                ->orderBy('enrollments_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get cached instructor content
     */
    public function getInstructorContent(int $instructorId): array
    {
        $cacheKey = self::PREFIX_STATS . "instructor:{$instructorId}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($instructorId) {
            $tracks = Track::where('instructor_id', $instructorId)
                ->withCount(['enrollments', 'levels'])
                ->get();

            $totalEnrollments = $tracks->sum('enrollments_count');
            $totalLevels = $tracks->sum('levels_count');

            return [
                'tracks' => $tracks,
                'total_tracks' => $tracks->count(),
                'total_enrollments' => $totalEnrollments,
                'total_levels' => $totalLevels,
                'average_enrollments_per_track' => $tracks->count() > 0 ? round($totalEnrollments / $tracks->count(), 2) : 0,
            ];
        });
    }

    /**
     * Invalidate cache for a specific track
     */
    public function invalidateTrackCache(string $slug): void
    {
        $patterns = [
            self::PREFIX_TRACK . "content:{$slug}",
            self::PREFIX_TRACK . "popular:*",
            self::PREFIX_STATS . "track:*",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->forgetByPattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Invalidate cache for a specific course
     */
    public function invalidateCourseCache(string $slug): void
    {
        $patterns = [
            self::PREFIX_COURSE . "content:{$slug}",
            self::PREFIX_COURSE . "popular:*",
            self::PREFIX_STATS . "course:*",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->forgetByPattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Invalidate cache for user progress
     */
    public function invalidateUserProgressCache(int $userId): void
    {
        $patterns = [
            self::PREFIX_PROGRESS . "user:{$userId}:*",
            self::PREFIX_ENROLLMENT . "user:{$userId}",
        ];

        foreach ($patterns as $pattern) {
            $this->forgetByPattern($pattern);
        }
    }

    /**
     * Invalidate cache for a specific module
     */
    public function invalidateModuleCache(int $moduleId): void
    {
        $cacheKey = self::PREFIX_MODULE . "lessons:{$moduleId}";
        Cache::forget($cacheKey);
    }

    /**
     * Warm up cache for popular content
     */
    public function warmUpCache(): void
    {
        // Warm up popular tracks and courses
        $this->getPopularTracks();
        $this->getPopularCourses();

        // Warm up recently accessed tracks
        $recentTracks = Track::where('is_published', true)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTracks as $track) {
            $this->getTrackWithContent($track->slug);
        }

        // Warm up recently accessed courses
        $recentCourses = Course::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentCourses as $course) {
            $this->getCourseWithContent($course->slug);
        }
    }

    /**
     * Clear all classroom cache
     */
    public function clearAllCache(): void
    {
        $prefixes = [
            self::PREFIX_TRACK,
            self::PREFIX_COURSE,
            self::PREFIX_MODULE,
            self::PREFIX_PROGRESS,
            self::PREFIX_ENROLLMENT,
            self::PREFIX_STATS,
        ];

        foreach ($prefixes as $prefix) {
            $this->forgetByPattern($prefix . '*');
        }
    }

    /**
     * Calculate track statistics
     */
    private function calculateTrackStats(int $trackId): array
    {
        $track = Track::withCount(['enrollments', 'levels'])->find($trackId);

        if (!$track) {
            return [];
        }

        $completedEnrollments = TrackEnrollment::where('track_id', $trackId)
            ->whereNotNull('completed_at')
            ->count();

        $averageProgress = TrackEnrollment::where('track_id', $trackId)
            ->avg('progress_percentage') ?? 0;

        return [
            'total_enrollments' => $track->enrollments_count,
            'completed_enrollments' => $completedEnrollments,
            'completion_rate' => $track->enrollments_count > 0 ? round(($completedEnrollments / $track->enrollments_count) * 100, 2) : 0,
            'average_progress' => round($averageProgress, 2),
            'total_levels' => $track->levels_count,
        ];
    }

    /**
     * Calculate course statistics
     */
    private function calculateCourseStats(int $courseId): array
    {
        $course = Course::withCount(['enrollments', 'modules'])->find($courseId);

        if (!$course) {
            return [];
        }

        $completedEnrollments = CourseEnrollment::where('course_id', $courseId)
            ->whereNotNull('completed_at')
            ->count();

        $averageProgress = CourseEnrollment::where('course_id', $courseId)
            ->avg('progress_percentage') ?? 0;

        return [
            'total_enrollments' => $course->enrollments_count,
            'completed_enrollments' => $completedEnrollments,
            'completion_rate' => $course->enrollments_count > 0 ? round(($completedEnrollments / $course->enrollments_count) * 100, 2) : 0,
            'average_progress' => round($averageProgress, 2),
            'total_modules' => $course->modules_count,
        ];
    }

    /**
     * Forget cache keys by pattern
     */
    private function forgetByPattern(string $pattern): void
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or similar for better performance

        $cacheDriver = Cache::getStore();

        if (method_exists($cacheDriver, 'flush')) {
            // For array/file cache, we can't easily pattern match, so we clear all
            // In production with Redis, you would use SCAN with the pattern
            return;
        }

        // For Redis or other advanced cache drivers, implement pattern-based deletion
        // This is a placeholder for the actual implementation
    }
}
