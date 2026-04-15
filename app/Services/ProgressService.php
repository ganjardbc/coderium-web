<?php

namespace App\Services;

use App\Models\User;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\TrackEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgressService
{
    /**
     * Mark a lesson as complete for a user.
     *
     * @param User $user
     * @param Lesson $lesson
     * @return LessonProgress
     * @throws ValidationException
     */
    public function markLessonComplete(User $user, Lesson $lesson): LessonProgress
    {
        // Validate user has access to the lesson
        $this->validateLessonAccess($user, $lesson);

        // Check if lesson is already completed
        $existingProgress = LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($existingProgress && $existingProgress->isCompleted()) {
            return $existingProgress;
        }

        return DB::transaction(function () use ($user, $lesson, $existingProgress) {
            if ($existingProgress) {
                // Update existing progress
                $existingProgress->update([
                    'completed_at' => now(),
                ]);
                $progress = $existingProgress;
            } else {
                // Create new progress record
                $progress = LessonProgress::create([
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                    'completed_at' => now(),
                    'time_spent' => 0,
                ]);
            }

            // Update track enrollment progress
            $this->updateTrackEnrollmentProgress($user, $lesson);

            // Check for achievements after lesson completion
            $this->checkAchievementsAfterLessonCompletion($user, $lesson);

            return $progress;
        });
    }

    /**
     * Calculate module progress for a user.
     *
     * @param User $user
     * @param Module $module
     * @return float
     */
    public function calculateModuleProgress(User $user, Module $module): float
    {
        $totalLessons = $module->lessons()
            ->where('is_published', true)
            ->count();

        if ($totalLessons === 0) {
            return 0.0;
        }

        $completedLessons = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->where('lessons.module_id', $module->id)
            ->where('lessons.is_published', true)
            ->where('lesson_progress.user_id', $user->id)
            ->whereNotNull('lesson_progress.completed_at')
            ->count();

        return round(($completedLessons / $totalLessons) * 100, 2);
    }

    /**
     * Calculate level progress for a user.
     *
     * @param User $user
     * @param Level $level
     * @return float
     */
    public function calculateLevelProgress(User $user, Level $level): float
    {
        $modules = $level->modules()
            ->where('is_published', true)
            ->get();

        if ($modules->isEmpty()) {
            return 0.0;
        }

        $totalProgress = 0.0;
        foreach ($modules as $module) {
            $totalProgress += $this->calculateModuleProgress($user, $module);
        }

        return round($totalProgress / $modules->count(), 2);
    }

    /**
     * Calculate track progress for a user.
     *
     * @param User $user
     * @param Track $track
     * @return float
     */
    public function calculateTrackProgress(User $user, Track $track): float
    {
        $levels = $track->levels()
            ->where('is_published', true)
            ->get();

        if ($levels->isEmpty()) {
            return 0.0;
        }

        $totalProgress = 0.0;
        foreach ($levels as $level) {
            $totalProgress += $this->calculateLevelProgress($user, $level);
        }

        return round($totalProgress / $levels->count(), 2);
    }

    /**
     * Get detailed progress report for a user and track.
     *
     * @param User $user
     * @param Track $track
     * @return array
     */
    public function getDetailedProgressReport(User $user, Track $track): array
    {
        $trackProgress = $this->calculateTrackProgress($user, $track);

        $levels = [];
        foreach ($track->levels()->where('is_published', true)->get() as $level) {
            $levelProgress = $this->calculateLevelProgress($user, $level);

            $modules = [];
            foreach ($level->modules()->where('is_published', true)->get() as $module) {
                $moduleProgress = $this->calculateModuleProgress($user, $module);

                $lessons = [];
                foreach ($module->lessons()->where('is_published', true)->get() as $lesson) {
                    $lessonProgress = $this->getLessonProgress($user, $lesson);
                    $lessons[] = [
                        'lesson_id' => $lesson->id,
                        'title' => $lesson->title,
                        'is_completed' => $lessonProgress ? $lessonProgress->isCompleted() : false,
                        'completed_at' => $lessonProgress ? $lessonProgress->completed_at : null,
                        'time_spent' => $lessonProgress ? $lessonProgress->time_spent : 0,
                    ];
                }

                $modules[] = [
                    'module_id' => $module->id,
                    'title' => $module->title,
                    'progress_percentage' => $moduleProgress,
                    'lessons' => $lessons,
                ];
            }

            $levels[] = [
                'level_id' => $level->id,
                'title' => $level->title,
                'difficulty' => $level->difficulty,
                'progress_percentage' => $levelProgress,
                'modules' => $modules,
            ];
        }

        return [
            'track_id' => $track->id,
            'track_title' => $track->title,
            'overall_progress' => $trackProgress,
            'levels' => $levels,
            'statistics' => $this->getProgressStatistics($user, $track),
        ];
    }

    /**
     * Get progress statistics for a user and track.
     *
     * @param User $user
     * @param Track $track
     * @return array
     */
    public function getProgressStatistics(User $user, Track $track): array
    {
        $totalLessons = DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('levels', 'modules.level_id', '=', 'levels.id')
            ->where('levels.track_id', $track->id)
            ->where('lessons.is_published', true)
            ->where('modules.is_published', true)
            ->where('levels.is_published', true)
            ->count();

        $completedLessons = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('levels', 'modules.level_id', '=', 'levels.id')
            ->where('levels.track_id', $track->id)
            ->where('lesson_progress.user_id', $user->id)
            ->whereNotNull('lesson_progress.completed_at')
            ->where('lessons.is_published', true)
            ->where('modules.is_published', true)
            ->where('levels.is_published', true)
            ->count();

        $totalTimeSpent = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('levels', 'modules.level_id', '=', 'levels.id')
            ->where('levels.track_id', $track->id)
            ->where('lesson_progress.user_id', $user->id)
            ->where('lessons.is_published', true)
            ->where('modules.is_published', true)
            ->where('levels.is_published', true)
            ->sum('lesson_progress.time_spent');

        $enrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        return [
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'remaining_lessons' => $totalLessons - $completedLessons,
            'completion_percentage' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0,
            'total_time_spent' => $totalTimeSpent, // in seconds
            'enrolled_at' => $enrollment ? $enrollment->enrolled_at : null,
            'is_completed' => $enrollment ? $enrollment->isCompleted() : false,
        ];
    }

    /**
     * Update lesson time spent.
     *
     * @param User $user
     * @param Lesson $lesson
     * @param int $timeSpent (in seconds)
     * @return LessonProgress
     */
    public function updateLessonTimeSpent(User $user, Lesson $lesson, int $timeSpent): LessonProgress
    {
        $progress = LessonProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'time_spent' => 0,
            ]
        );

        $progress->increment('time_spent', $timeSpent);

        return $progress;
    }

    /**
     * Get lesson progress for a user.
     *
     * @param User $user
     * @param Lesson $lesson
     * @return LessonProgress|null
     */
    public function getLessonProgress(User $user, Lesson $lesson): ?LessonProgress
    {
        return LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();
    }

    /**
     * Get all progress for a user across all tracks.
     *
     * @param User $user
     * @return Collection
     */
    public function getUserProgressSummary(User $user): Collection
    {
        return TrackEnrollment::where('user_id', $user->id)
            ->with(['track.levels.modules.lessons'])
            ->get()
            ->map(function ($enrollment) use ($user) {
                $track = $enrollment->track;
                $progress = $this->calculateTrackProgress($user, $track);
                $statistics = $this->getProgressStatistics($user, $track);

                return [
                    'enrollment' => $enrollment,
                    'track' => $track,
                    'progress_percentage' => $progress,
                    'statistics' => $statistics,
                ];
            });
    }

    /**
     * Reset progress for a user and track (admin function).
     *
     * @param User $user
     * @param Track $track
     * @return bool
     */
    public function resetTrackProgress(User $user, Track $track): bool
    {
        return DB::transaction(function () use ($user, $track) {
            // Delete all lesson progress for this track
            DB::table('lesson_progress')
                ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
                ->join('modules', 'lessons.module_id', '=', 'modules.id')
                ->join('levels', 'modules.level_id', '=', 'levels.id')
                ->where('levels.track_id', $track->id)
                ->where('lesson_progress.user_id', $user->id)
                ->delete();

            // Reset track enrollment progress
            $enrollment = TrackEnrollment::where('user_id', $user->id)
                ->where('track_id', $track->id)
                ->first();

            if ($enrollment) {
                $enrollment->update([
                    'progress_percentage' => 0.00,
                    'completed_at' => null,
                ]);
            }

            return true;
        });
    }

    /**
     * Validate user has access to a lesson.
     *
     * @param User $user
     * @param Lesson $lesson
     * @throws ValidationException
     */
    private function validateLessonAccess(User $user, Lesson $lesson): void
    {
        // Check if lesson is published
        if (!$lesson->is_published && !$user->hasInstructorPermissions()) {
            throw ValidationException::withMessages([
                'lesson' => 'Cannot access unpublished lesson.',
            ]);
        }

        // Check if user is enrolled in the track
        $track = $lesson->module->level->track;
        $enrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        if (!$enrollment && !$user->hasInstructorPermissions()) {
            throw ValidationException::withMessages([
                'enrollment' => 'User must be enrolled in track to access lesson.',
            ]);
        }
    }

    /**
     * Update track enrollment progress.
     *
     * @param User $user
     * @param Lesson $lesson
     */
    private function updateTrackEnrollmentProgress(User $user, Lesson $lesson): void
    {
        $track = $lesson->module->level->track;
        $enrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        if ($enrollment) {
            $newProgress = $this->calculateTrackProgress($user, $track);

            $updateData = ['progress_percentage' => $newProgress];

            // Mark as completed if 100% progress
            if ($newProgress >= 100.0 && !$enrollment->isCompleted()) {
                $updateData['completed_at'] = now();
            }

            $enrollment->update($updateData);
        }
    }

    /**
     * Check for achievements after lesson completion.
     *
     * @param User $user
     * @param Lesson $lesson
     */
    private function checkAchievementsAfterLessonCompletion(User $user, Lesson $lesson): void
    {
        try {
            // Avoid circular dependency by resolving AchievementService here
            $achievementService = app(AchievementService::class);
            $achievementService->checkLessonCompletionAchievements($user, $lesson);
        } catch (\Exception $e) {
            // Log the error but don't fail the lesson completion
            \Log::error('Failed to check achievements after lesson completion', [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
