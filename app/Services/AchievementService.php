<?php

namespace App\Services;

use App\Models\User;
use App\Models\Track;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\Certificate;
use App\Models\TrackEnrollment;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\Notification as NotificationContract;
use Carbon\Carbon;

class AchievementService
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Check and award achievements for a user after lesson completion.
     *
     * @param User $user
     * @param \App\Models\Lesson $lesson
     * @return array
     */
    public function checkLessonCompletionAchievements(User $user, $lesson): array
    {
        $awardedAchievements = [];

        // Check for first lesson completion
        $awardedAchievements = array_merge(
            $awardedAchievements,
            $this->checkFirstLessonAchievement($user)
        );

        // Check for module completion
        $module = $lesson->module;
        if ($this->isModuleCompleted($user, $module)) {
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->checkFirstModuleAchievement($user)
            );
        }

        // Check for level completion
        $level = $module->level;
        if ($this->isLevelCompleted($user, $level)) {
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->checkFirstLevelAchievement($user)
            );
        }

        // Check for track completion
        $track = $level->track;
        if ($this->isTrackCompleted($user, $track)) {
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->checkTrackCompletionAchievement($user, $track)
            );

            // Generate certificate for track completion
            $this->generateTrackCompletionCertificate($user, $track);
        }

        // Check for learning streaks
        $awardedAchievements = array_merge(
            $awardedAchievements,
            $this->checkLearningStreakAchievements($user)
        );

        // Send notifications for new achievements
        if (!empty($awardedAchievements)) {
            $this->sendAchievementNotifications($user, $awardedAchievements);
        }

        return $awardedAchievements;
    }

    /**
     * Check and award achievements for assessment completion.
     *
     * @param User $user
     * @param \App\Models\AssessmentAttempt $attempt
     * @return array
     */
    public function checkAssessmentAchievements(User $user, $attempt): array
    {
        $awardedAchievements = [];

        // Check for perfect score achievement
        if ($attempt->score >= $attempt->max_score) {
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->checkPerfectionistAchievement($user)
            );
        }

        // Check for fast learner achievement (completed quickly)
        if ($attempt->time_taken && $attempt->time_taken < 300) { // Less than 5 minutes
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->checkFastLearnerAchievement($user)
            );
        }

        // Send notifications for new achievements
        if (!empty($awardedAchievements)) {
            $this->sendAchievementNotifications($user, $awardedAchievements);
        }

        return $awardedAchievements;
    }

    /**
     * Check and award achievements for track enrollment.
     *
     * @param User $user
     * @return array
     */
    public function checkEnrollmentAchievements(User $user): array
    {
        $awardedAchievements = [];

        // Check for explorer achievement (multiple tracks enrolled)
        $enrollmentCount = TrackEnrollment::where('user_id', $user->id)->count();
        if ($enrollmentCount >= 3) {
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->checkExplorerAchievement($user)
            );
        }

        // Send notifications for new achievements
        if (!empty($awardedAchievements)) {
            $this->sendAchievementNotifications($user, $awardedAchievements);
        }

        return $awardedAchievements;
    }

    /**
     * Generate a digital certificate for track completion.
     *
     * @param User $user
     * @param Track $track
     * @return Certificate
     */
    public function generateTrackCompletionCertificate(User $user, Track $track): Certificate
    {
        // Check if certificate already exists
        $existingCertificate = Certificate::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        if ($existingCertificate) {
            return $existingCertificate;
        }

        $enrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        $statistics = $this->progressService->getProgressStatistics($user, $track);

        return Certificate::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'certificate_number' => Certificate::generateCertificateNumber(),
            'title' => "Certificate of Completion - {$track->title}",
            'description' => "This certifies that {$user->name} has successfully completed the {$track->title} learning track.",
            'issued_at' => now(),
            'completed_at' => $enrollment->completed_at ?? now(),
            'metadata' => [
                'total_lessons' => $statistics['total_lessons'],
                'completion_percentage' => $statistics['completion_percentage'],
                'total_time_spent' => $statistics['total_time_spent'],
                'enrolled_at' => $statistics['enrolled_at'],
                'track_difficulty' => $track->difficulty_level,
            ],
        ]);
    }

    /**
     * Get all achievements for a user.
     *
     * @param User $user
     * @return Collection
     */
    public function getUserAchievements(User $user): Collection
    {
        return $user->achievements()
            ->orderBy('user_achievements.earned_at', 'desc')
            ->get();
    }

    /**
     * Get all certificates for a user.
     *
     * @param User $user
     * @return Collection
     */
    public function getUserCertificates(User $user): Collection
    {
        return $user->certificates()
            ->with('track')
            ->orderBy('issued_at', 'desc')
            ->get();
    }

    /**
     * Get achievement statistics for a user.
     *
     * @param User $user
     * @return array
     */
    public function getUserAchievementStats(User $user): array
    {
        $totalAchievements = Achievement::where('is_active', true)->count();
        $earnedAchievements = $user->achievements()->count();
        $totalPoints = $user->achievements()->sum('points');
        $certificates = $user->certificates()->count();

        return [
            'total_achievements' => $totalAchievements,
            'earned_achievements' => $earnedAchievements,
            'completion_percentage' => $totalAchievements > 0 ? round(($earnedAchievements / $totalAchievements) * 100, 2) : 0,
            'total_points' => $totalPoints,
            'certificates_earned' => $certificates,
        ];
    }

    /**
     * Initialize default achievements.
     *
     * @return void
     */
    public function initializeDefaultAchievements(): void
    {
        $achievements = [
            [
                'name' => 'First Steps',
                'description' => 'Complete your first lesson',
                'type' => 'first_lesson',
                'icon' => 'play-circle',
                'color' => '#10B981',
                'criteria' => ['type' => 'lesson_count', 'value' => 1],
                'points' => 10,
            ],
            [
                'name' => 'Module Master',
                'description' => 'Complete your first module',
                'type' => 'first_module',
                'icon' => 'check-circle',
                'color' => '#3B82F6',
                'criteria' => ['type' => 'module_count', 'value' => 1],
                'points' => 25,
            ],
            [
                'name' => 'Level Up',
                'description' => 'Complete your first level',
                'type' => 'first_level',
                'icon' => 'trending-up',
                'color' => '#8B5CF6',
                'criteria' => ['type' => 'level_count', 'value' => 1],
                'points' => 50,
            ],
            [
                'name' => 'Track Champion',
                'description' => 'Complete an entire learning track',
                'type' => 'track_completion',
                'icon' => 'trophy',
                'color' => '#F59E0B',
                'criteria' => ['type' => 'track_count', 'value' => 1],
                'points' => 100,
            ],
            [
                'name' => 'Week Warrior',
                'description' => 'Learn for 7 consecutive days',
                'type' => 'streak_7',
                'icon' => 'fire',
                'color' => '#EF4444',
                'criteria' => ['type' => 'streak_days', 'value' => 7],
                'points' => 30,
            ],
            [
                'name' => 'Monthly Master',
                'description' => 'Learn for 30 consecutive days',
                'type' => 'streak_30',
                'icon' => 'fire',
                'color' => '#DC2626',
                'criteria' => ['type' => 'streak_days', 'value' => 30],
                'points' => 100,
            ],
            [
                'name' => 'Speed Demon',
                'description' => 'Complete assessments quickly',
                'type' => 'fast_learner',
                'icon' => 'zap',
                'color' => '#FBBF24',
                'criteria' => ['type' => 'fast_assessments', 'value' => 5],
                'points' => 40,
            ],
            [
                'name' => 'Perfectionist',
                'description' => 'Score 100% on 5 assessments',
                'type' => 'perfectionist',
                'icon' => 'star',
                'color' => '#F472B6',
                'criteria' => ['type' => 'perfect_scores', 'value' => 5],
                'points' => 75,
            ],
            [
                'name' => 'Explorer',
                'description' => 'Enroll in 3 different tracks',
                'type' => 'explorer',
                'icon' => 'compass',
                'color' => '#06B6D4',
                'criteria' => ['type' => 'track_enrollments', 'value' => 3],
                'points' => 60,
            ],
        ];

        foreach ($achievements as $achievementData) {
            Achievement::firstOrCreate(
                ['type' => $achievementData['type']],
                $achievementData
            );
        }
    }

    /**
     * Check for first lesson achievement.
     */
    private function checkFirstLessonAchievement(User $user): array
    {
        $achievement = Achievement::where('type', 'first_lesson')->first();
        if (!$achievement || $achievement->isEarnedBy($user)) {
            return [];
        }

        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();

        if ($completedLessons >= 1) {
            return $this->awardAchievement($user, $achievement);
        }

        return [];
    }

    /**
     * Check for first module achievement.
     */
    private function checkFirstModuleAchievement(User $user): array
    {
        $achievement = Achievement::where('type', 'first_module')->first();
        if (!$achievement || $achievement->isEarnedBy($user)) {
            return [];
        }

        // Count completed modules by checking if all lessons in a module are completed
        $completedModules = DB::table('modules')
            ->join('lessons', 'modules.id', '=', 'lessons.module_id')
            ->leftJoin('lesson_progress', function ($join) use ($user) {
                $join->on('lessons.id', '=', 'lesson_progress.lesson_id')
                    ->where('lesson_progress.user_id', $user->id)
                    ->whereNotNull('lesson_progress.completed_at');
            })
            ->where('lessons.is_published', true)
            ->select('modules.id')
            ->groupBy('modules.id')
            ->havingRaw('COUNT(lessons.id) = COUNT(lesson_progress.id)')
            ->get()
            ->count();

        if ($completedModules >= 1) {
            return $this->awardAchievement($user, $achievement);
        }

        return [];
    }

    /**
     * Check for first level achievement.
     */
    private function checkFirstLevelAchievement(User $user): array
    {
        $achievement = Achievement::where('type', 'first_level')->first();
        if (!$achievement || $achievement->isEarnedBy($user)) {
            return [];
        }

        // Count completed levels by checking if all modules in a level are completed
        $completedLevels = DB::table('levels')
            ->join('modules', 'levels.id', '=', 'modules.level_id')
            ->join('lessons', 'modules.id', '=', 'lessons.module_id')
            ->leftJoin('lesson_progress', function ($join) use ($user) {
                $join->on('lessons.id', '=', 'lesson_progress.lesson_id')
                    ->where('lesson_progress.user_id', $user->id)
                    ->whereNotNull('lesson_progress.completed_at');
            })
            ->where('lessons.is_published', true)
            ->where('modules.is_published', true)
            ->select('levels.id')
            ->groupBy('levels.id')
            ->havingRaw('COUNT(lessons.id) = COUNT(lesson_progress.id)')
            ->get()
            ->count();

        if ($completedLevels >= 1) {
            return $this->awardAchievement($user, $achievement);
        }

        return [];
    }

    /**
     * Check for track completion achievement.
     */
    private function checkTrackCompletionAchievement(User $user, Track $track): array
    {
        $achievement = Achievement::where('type', 'track_completion')->first();
        if (!$achievement || $achievement->isEarnedBy($user)) {
            return [];
        }

        return $this->awardAchievement($user, $achievement, [
            'track_id' => $track->id,
            'track_title' => $track->title,
        ]);
    }

    /**
     * Check for learning streak achievements.
     */
    private function checkLearningStreakAchievements(User $user): array
    {
        $awardedAchievements = [];
        $currentStreak = $this->calculateCurrentStreak($user);

        // Check for 7-day streak
        $streak7Achievement = Achievement::where('type', 'streak_7')->first();
        if ($streak7Achievement && !$streak7Achievement->isEarnedBy($user) && $currentStreak >= 7) {
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->awardAchievement($user, $streak7Achievement, ['streak_days' => $currentStreak])
            );
        }

        // Check for 30-day streak
        $streak30Achievement = Achievement::where('type', 'streak_30')->first();
        if ($streak30Achievement && !$streak30Achievement->isEarnedBy($user) && $currentStreak >= 30) {
            $awardedAchievements = array_merge(
                $awardedAchievements,
                $this->awardAchievement($user, $streak30Achievement, ['streak_days' => $currentStreak])
            );
        }

        return $awardedAchievements;
    }

    /**
     * Check for perfectionist achievement.
     */
    private function checkPerfectionistAchievement(User $user): array
    {
        $achievement = Achievement::where('type', 'perfectionist')->first();
        if (!$achievement || $achievement->isEarnedBy($user)) {
            return [];
        }

        $perfectScores = DB::table('assessment_attempts')
            ->where('user_id', $user->id)
            ->whereColumn('score', 'max_score')
            ->count();

        if ($perfectScores >= 5) {
            return $this->awardAchievement($user, $achievement, ['perfect_scores' => $perfectScores]);
        }

        return [];
    }

    /**
     * Check for fast learner achievement.
     */
    private function checkFastLearnerAchievement(User $user): array
    {
        $achievement = Achievement::where('type', 'fast_learner')->first();
        if (!$achievement || $achievement->isEarnedBy($user)) {
            return [];
        }

        $fastAssessments = DB::table('assessment_attempts')
            ->where('user_id', $user->id)
            ->where('time_taken', '<', 300) // Less than 5 minutes
            ->count();

        if ($fastAssessments >= 5) {
            return $this->awardAchievement($user, $achievement, ['fast_assessments' => $fastAssessments]);
        }

        return [];
    }

    /**
     * Check for explorer achievement.
     */
    private function checkExplorerAchievement(User $user): array
    {
        $achievement = Achievement::where('type', 'explorer')->first();
        if (!$achievement || $achievement->isEarnedBy($user)) {
            return [];
        }

        $enrollmentCount = TrackEnrollment::where('user_id', $user->id)->count();

        if ($enrollmentCount >= 3) {
            return $this->awardAchievement($user, $achievement, ['track_enrollments' => $enrollmentCount]);
        }

        return [];
    }

    /**
     * Award an achievement to a user.
     */
    private function awardAchievement(User $user, Achievement $achievement, array $metadata = []): array
    {
        UserAchievement::create([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'earned_at' => now(),
            'metadata' => $metadata,
        ]);

        return [$achievement];
    }

    /**
     * Check if a module is completed by a user.
     */
    private function isModuleCompleted(User $user, $module): bool
    {
        $totalLessons = $module->lessons()->where('is_published', true)->count();
        if ($totalLessons === 0) {
            return false;
        }

        $completedLessons = LessonProgress::whereIn('lesson_id',
            $module->lessons()->where('is_published', true)->pluck('id')
        )
        ->where('user_id', $user->id)
        ->whereNotNull('completed_at')
        ->count();

        return $completedLessons === $totalLessons;
    }

    /**
     * Check if a level is completed by a user.
     */
    private function isLevelCompleted(User $user, $level): bool
    {
        foreach ($level->modules()->where('is_published', true)->get() as $module) {
            if (!$this->isModuleCompleted($user, $module)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a track is completed by a user.
     */
    private function isTrackCompleted(User $user, Track $track): bool
    {
        $enrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        return $enrollment && $enrollment->isCompleted();
    }

    /**
     * Calculate current learning streak for a user.
     */
    private function calculateCurrentStreak(User $user): int
    {
        $streak = 0;
        $currentDate = Carbon::today();

        // Get lesson completion dates for the user
        $completionDates = LessonProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->selectRaw('DATE(completed_at) as completion_date')
            ->groupBy('completion_date')
            ->orderBy('completion_date', 'desc')
            ->pluck('completion_date')
            ->map(function ($date) {
                return Carbon::parse($date);
            });

        if ($completionDates->isEmpty()) {
            return 0;
        }

        // Check if user learned today or yesterday (to account for timezone differences)
        $lastCompletionDate = $completionDates->first();
        if ($lastCompletionDate->diffInDays($currentDate) > 1) {
            return 0;
        }

        // Calculate consecutive days
        $expectedDate = $currentDate;
        foreach ($completionDates as $completionDate) {
            if ($completionDate->equalTo($expectedDate) ||
                ($expectedDate->equalTo($currentDate) && $completionDate->equalTo($currentDate->subDay()))) {
                $streak++;
                $expectedDate = $completionDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Send achievement notifications to a user.
     */
    private function sendAchievementNotifications(User $user, array $achievements): void
    {
        // This would integrate with Laravel's notification system
        // For now, we'll just log the achievements
        foreach ($achievements as $achievement) {
            \Log::info("Achievement earned", [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'achievement_name' => $achievement->name,
            ]);
        }

        // TODO: Implement actual notification sending
        // Notification::send($user, new AchievementEarnedNotification($achievements));
    }
}
