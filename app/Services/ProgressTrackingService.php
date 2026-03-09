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
use Illuminate\Validation\ValidationException;

class ProgressTrackingService
{
    /**
     * Update progress with granular metrics.
     *
     * @param User $user
     * @param mixed $progressable
     * @param array $metrics
     * @return LearningProgress
     * @throws ValidationException
     */
    public function updateProgress(User $user, $progressable, array $metrics): LearningProgress
    {
        $this->validateProgressMetrics($metrics);
        $this->validateProgressAccess($user, $progressable);

        return DB::transaction(function () use ($user, $progressable, $metrics) {
            $progress = LearningProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'progressable_type' => get_class($progressable),
                    'progressable_id' => $progressable->id,
                ],
                [
                    'completion_percentage' => $metrics['completion_percentage'] ?? 0.00,
                    'time_spent_minutes' => ($metrics['time_spent_minutes'] ?? 0) + ($progress->time_spent_minutes ?? 0),
                    'engagement_score' => $metrics['engagement_score'] ?? null,
                    'last_accessed_at' => now(),
                ]
            );

            // Mark as completed if 100% progress
            if ($progress->completion_percentage >= 100.00 && !$progress->completed_at) {
                $progress->update(['completed_at' => now()]);
            }

            // Update parent progress if applicable
            $this->updateParentProgress($user, $progressable);

            return $progress;
        });
    }

    /**
     * Calculate aggregate progress for tracks.
     *
     * @param User $user
     * @param Track $track
     * @return array
     */
    public function calculateAggregateProgress(User $user, $learningPath): array
    {
        if ($learningPath instanceof Track) {
            return $this->calculateTrackProgress($user, $learningPath);
        } elseif ($learningPath instanceof Course) {
            return $this->calculateCourseProgress($user, $learningPath);
        }

        throw new \InvalidArgumentException('Learning path must be a Track or Course instance.');
    }

    /**
     * Calculate track progress aggregation.
     *
     * @param User $user
     * @param Track $track
     * @return array
     */
    private function calculateTrackProgress(User $user, Track $track): array
    {
        $levels = $track->levels()->where('is_published', true)->get();

        if ($levels->isEmpty()) {
            return $this->getEmptyProgressData();
        }

        $totalProgress = 0.0;
        $totalTimeSpent = 0;
        $totalEngagement = 0.0;
        $engagementCount = 0;
        $levelDetails = [];

        foreach ($levels as $level) {
            $levelProgress = $this->calculateLevelProgress($user, $level);
            $totalProgress += $levelProgress['completion_percentage'];
            $totalTimeSpent += $levelProgress['time_spent_minutes'];

            if ($levelProgress['engagement_score'] !== null) {
                $totalEngagement += $levelProgress['engagement_score'];
                $engagementCount++;
            }

            $levelDetails[] = $levelProgress;
        }

        $overallProgress = round($totalProgress / $levels->count(), 2);
        $averageEngagement = $engagementCount > 0 ? round($totalEngagement / $engagementCount, 2) : null;

        return [
            'learning_path_type' => 'track',
            'learning_path_id' => $track->id,
            'completion_percentage' => $overallProgress,
            'time_spent_minutes' => $totalTimeSpent,
            'engagement_score' => $averageEngagement,
            'is_completed' => $overallProgress >= 100.00,
            'level_details' => $levelDetails,
            'last_updated' => now(),
        ];
    }

    /**
     * Calculate course progress aggregation.
     *
     * @param User $user
     * @param Course $course
     * @return array
     */
    private function calculateCourseProgress(User $user, Course $course): array
    {
        $modules = $course->modules()->get();

        if ($modules->isEmpty()) {
            return $this->getEmptyProgressData();
        }

        $totalProgress = 0.0;
        $totalTimeSpent = 0;
        $totalEngagement = 0.0;
        $engagementCount = 0;
        $moduleDetails = [];

        foreach ($modules as $module) {
            $moduleProgress = $this->calculateModuleProgress($user, $module);
            $totalProgress += $moduleProgress['completion_percentage'];
            $totalTimeSpent += $moduleProgress['time_spent_minutes'];

            if ($moduleProgress['engagement_score'] !== null) {
                $totalEngagement += $moduleProgress['engagement_score'];
                $engagementCount++;
            }

            $moduleDetails[] = $moduleProgress;
        }

        $overallProgress = round($totalProgress / $modules->count(), 2);
        $averageEngagement = $engagementCount > 0 ? round($totalEngagement / $engagementCount, 2) : null;

        return [
            'learning_path_type' => 'course',
            'learning_path_id' => $course->id,
            'completion_percentage' => $overallProgress,
            'time_spent_minutes' => $totalTimeSpent,
            'engagement_score' => $averageEngagement,
            'is_completed' => $overallProgress >= 100.00,
            'module_details' => $moduleDetails,
            'last_updated' => now(),
        ];
    }

    /**
     * Calculate level progress.
     *
     * @param User $user
     * @param Level $level
     * @return array
     */
    private function calculateLevelProgress(User $user, $level): array
    {
        // Use allModules() to get both direct and pivot assigned modules
        $modules = $level->allModules()->where('is_published', true);

        if ($modules->isEmpty()) {
            return $this->getEmptyProgressData(['level_id' => $level->id]);
        }

        $totalProgress = 0.0;
        $totalTimeSpent = 0;
        $totalEngagement = 0.0;
        $engagementCount = 0;

        foreach ($modules as $module) {
            $moduleProgress = $this->calculateModuleProgress($user, $module);
            $totalProgress += $moduleProgress['completion_percentage'];
            $totalTimeSpent += $moduleProgress['time_spent_minutes'];

            if ($moduleProgress['engagement_score'] !== null) {
                $totalEngagement += $moduleProgress['engagement_score'];
                $engagementCount++;
            }
        }

        $overallProgress = round($totalProgress / $modules->count(), 2);
        $averageEngagement = $engagementCount > 0 ? round($totalEngagement / $engagementCount, 2) : null;

        return [
            'level_id' => $level->id,
            'completion_percentage' => $overallProgress,
            'time_spent_minutes' => $totalTimeSpent,
            'engagement_score' => $averageEngagement,
            'is_completed' => $overallProgress >= 100.00,
        ];
    }

    /**
     * Calculate module progress.
     *
     * @param User $user
     * @param Module $module
     * @return array
     */
    private function calculateModuleProgress(User $user, Module $module): array
    {
        $lessons = $module->lessons()->where('is_published', true)->get();

        if ($lessons->isEmpty()) {
            return $this->getEmptyProgressData(['module_id' => $module->id]);
        }

        $totalProgress = 0.0;
        $totalTimeSpent = 0;
        $totalEngagement = 0.0;
        $engagementCount = 0;

        foreach ($lessons as $lesson) {
            $lessonProgress = $this->getLessonProgress($user, $lesson);
            if ($lessonProgress) {
                $totalProgress += $lessonProgress->completion_percentage;
                $totalTimeSpent += $lessonProgress->time_spent_minutes;

                if ($lessonProgress->engagement_score !== null) {
                    $totalEngagement += $lessonProgress->engagement_score;
                    $engagementCount++;
                }
            }
        }

        $overallProgress = round($totalProgress / $lessons->count(), 2);
        $averageEngagement = $engagementCount > 0 ? round($totalEngagement / $engagementCount, 2) : null;

        return [
            'module_id' => $module->id,
            'completion_percentage' => $overallProgress,
            'time_spent_minutes' => $totalTimeSpent,
            'engagement_score' => $averageEngagement,
            'is_completed' => $overallProgress >= 100.00,
        ];
    }

    /**
     * Get progress summary with backward compatibility.
     *
     * @param User $user
     * @param mixed $learningPath
     * @return array
     */
    public function getProgressSummary(User $user, $learningPath): array
    {
        $aggregateProgress = $this->calculateAggregateProgress($user, $learningPath);

        // Add backward compatibility data
        $summary = [
            'aggregate_progress' => $aggregateProgress,
            'binary_completion' => $aggregateProgress['is_completed'],
            'completion_percentage' => $aggregateProgress['completion_percentage'],
            'detailed_metrics' => [
                'time_spent_minutes' => $aggregateProgress['time_spent_minutes'],
                'engagement_score' => $aggregateProgress['engagement_score'],
                'last_updated' => $aggregateProgress['last_updated'],
            ],
        ];

        // Add learning path specific data
        if ($learningPath instanceof Track) {
            $enrollment = TrackEnrollment::where('user_id', $user->id)
                ->where('track_id', $learningPath->id)
                ->first();

            $summary['enrollment_data'] = [
                'enrolled_at' => $enrollment?->enrolled_at,
                'completed_at' => $enrollment?->completed_at,
                'enrollment_progress' => $enrollment?->progress_percentage ?? 0.00,
            ];
        } elseif ($learningPath instanceof Course) {
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $learningPath->id)
                ->first();

            $summary['enrollment_data'] = [
                'enrolled_at' => $enrollment?->enrolled_at,
                'completed_at' => $enrollment?->completed_at,
                'enrollment_progress' => $enrollment?->progress_percentage ?? 0.00,
            ];
        }

        return $summary;
    }

    /**
     * Get lesson progress for a user.
     *
     * @param User $user
     * @param Lesson $lesson
     * @return LearningProgress|null
     */
    public function getLessonProgress(User $user, Lesson $lesson): ?LearningProgress
    {
        return LearningProgress::where('user_id', $user->id)
            ->where('progressable_type', Lesson::class)
            ->where('progressable_id', $lesson->id)
            ->first();
    }

    /**
     * Get all progress records for a user.
     *
     * @param User $user
     * @param string|null $progressableType
     * @return Collection
     */
    public function getUserProgress(User $user, ?string $progressableType = null): Collection
    {
        $query = LearningProgress::where('user_id', $user->id);

        if ($progressableType) {
            $query->where('progressable_type', $progressableType);
        }

        return $query->with('progressable')->get();
    }

    /**
     * Reset progress for a user and learning path.
     *
     * @param User $user
     * @param mixed $learningPath
     * @return bool
     */
    public function resetProgress(User $user, $learningPath): bool
    {
        return DB::transaction(function () use ($user, $learningPath) {
            if ($learningPath instanceof Track) {
                return $this->resetTrackProgress($user, $learningPath);
            } elseif ($learningPath instanceof Course) {
                return $this->resetCourseProgress($user, $learningPath);
            }

            return false;
        });
    }

    /**
     * Validate progress metrics.
     *
     * @param array $metrics
     * @throws ValidationException
     */
    private function validateProgressMetrics(array $metrics): void
    {
        if (isset($metrics['completion_percentage'])) {
            $percentage = $metrics['completion_percentage'];
            if (!is_numeric($percentage) || $percentage < 0 || $percentage > 100) {
                throw ValidationException::withMessages([
                    'completion_percentage' => 'Completion percentage must be between 0 and 100.',
                ]);
            }
        }

        if (isset($metrics['time_spent_minutes'])) {
            $timeSpent = $metrics['time_spent_minutes'];
            if (!is_numeric($timeSpent) || $timeSpent < 0) {
                throw ValidationException::withMessages([
                    'time_spent_minutes' => 'Time spent must be a non-negative number.',
                ]);
            }
        }

        if (isset($metrics['engagement_score'])) {
            $engagement = $metrics['engagement_score'];
            if ($engagement !== null && (!is_numeric($engagement) || $engagement < 0 || $engagement > 1)) {
                throw ValidationException::withMessages([
                    'engagement_score' => 'Engagement score must be between 0.00 and 1.00.',
                ]);
            }
        }
    }

    /**
     * Validate user access to progressable entity.
     *
     * @param User $user
     * @param mixed $progressable
     * @throws ValidationException
     */
    private function validateProgressAccess(User $user, $progressable): void
    {
        // Basic validation - can be extended based on business rules
        if (!$progressable || !$progressable->exists) {
            throw ValidationException::withMessages([
                'progressable' => 'Invalid progressable entity.',
            ]);
        }

        // Add specific access validation based on entity type
        if ($progressable instanceof Lesson) {
            if (!$progressable->is_published && !$user->hasInstructorPermissions()) {
                throw ValidationException::withMessages([
                    'access' => 'Cannot update progress for unpublished lesson.',
                ]);
            }
        }
    }

    /**
     * Update parent progress when child progress changes.
     *
     * @param User $user
     * @param mixed $progressable
     */
    private function updateParentProgress(User $user, $progressable): void
    {
        if ($progressable instanceof Lesson) {
            // Update module progress
            $module = $progressable->module;
            if ($module) {
                $moduleProgress = $this->calculateModuleProgress($user, $module);
                $this->updateProgress($user, $module, $moduleProgress);
            }
        }
    }

    /**
     * Reset track progress.
     *
     * @param User $user
     * @param Track $track
     * @return bool
     */
    private function resetTrackProgress(User $user, Track $track): bool
    {
        // Delete all learning progress for this track
        $lessonIds = DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('levels', 'modules.level_id', '=', 'levels.id')
            ->where('levels.track_id', $track->id)
            ->pluck('lessons.id');

        LearningProgress::where('user_id', $user->id)
            ->where('progressable_type', Lesson::class)
            ->whereIn('progressable_id', $lessonIds)
            ->delete();

        // Reset track enrollment
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
    }

    /**
     * Reset course progress.
     *
     * @param User $user
     * @param Course $course
     * @return bool
     */
    private function resetCourseProgress(User $user, Course $course): bool
    {
        // Delete all learning progress for this course
        $lessonIds = DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->join('course_modules', 'modules.id', '=', 'course_modules.module_id')
            ->where('course_modules.course_id', $course->id)
            ->pluck('lessons.id');

        LearningProgress::where('user_id', $user->id)
            ->where('progressable_type', Lesson::class)
            ->whereIn('progressable_id', $lessonIds)
            ->delete();

        // Reset course enrollment
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($enrollment) {
            $enrollment->update([
                'progress_percentage' => 0.00,
                'completed_at' => null,
            ]);
        }

        return true;
    }

    /**
     * Bulk update progress for multiple users and progressables.
     *
     * @param array $progressUpdates
     * @return array
     * @throws ValidationException
     */
    public function bulkUpdateProgress(array $progressUpdates): array
    {
        $results = [];
        $errors = [];

        // Validate all progress updates first
        foreach ($progressUpdates as $index => $update) {
            try {
                $this->validateBulkProgressData($update, $index);
            } catch (ValidationException $e) {
                $errors[] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'bulk_progress_update' => 'Validation failed for bulk progress update.',
                'progress_errors' => $errors,
            ]);
        }

        return DB::transaction(function () use ($progressUpdates, &$results) {
            foreach ($progressUpdates as $index => $update) {
                try {
                    $user = User::findOrFail($update['user_id']);
                    $progressableClass = $update['progressable_type'];
                    $progressable = $progressableClass::findOrFail($update['progressable_id']);
                    $metrics = $update['metrics'];

                    $result = $this->updateProgress($user, $progressable, $metrics);

                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'progress_id' => $result->id,
                        'user_id' => $user->id,
                        'progressable_type' => get_class($progressable),
                        'progressable_id' => $progressable->id,
                        'completion_percentage' => $result->completion_percentage,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'user_id' => $update['user_id'] ?? null,
                        'progressable_type' => $update['progressable_type'] ?? null,
                        'progressable_id' => $update['progressable_id'] ?? null,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Bulk progress update completed.',
                'results' => $results,
                'total_processed' => count($progressUpdates),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            ];
        });
    }

    /**
     * Generate bulk progress reports for multiple users and learning paths.
     *
     * @param array $reportRequests
     * @return array
     * @throws ValidationException
     */
    public function bulkGenerateProgressReports(array $reportRequests): array
    {
        $results = [];
        $errors = [];

        // Validate all report requests first
        foreach ($reportRequests as $index => $request) {
            try {
                $this->validateBulkReportData($request, $index);
            } catch (ValidationException $e) {
                $errors[] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'bulk_report_generation' => 'Validation failed for bulk report generation.',
                'report_errors' => $errors,
            ]);
        }

        foreach ($reportRequests as $index => $request) {
            try {
                $user = User::findOrFail($request['user_id']);
                $learningPathClass = $request['learning_path_type'];
                $learningPath = $learningPathClass::findOrFail($request['learning_path_id']);

                $progressSummary = $this->getProgressSummary($user, $learningPath);

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'user_id' => $user->id,
                    'learning_path_type' => get_class($learningPath),
                    'learning_path_id' => $learningPath->id,
                    'progress_summary' => $progressSummary,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'user_id' => $request['user_id'] ?? null,
                    'learning_path_type' => $request['learning_path_type'] ?? null,
                    'learning_path_id' => $request['learning_path_id'] ?? null,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Bulk progress report generation completed.',
            'results' => $results,
            'total_processed' => count($reportRequests),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
        ];
    }

    /**
     * Validate bulk progress update data structure.
     *
     * @param array $update
     * @param int $index
     * @throws ValidationException
     */
    private function validateBulkProgressData(array $update, int $index): void
    {
        $requiredFields = ['user_id', 'progressable_type', 'progressable_id', 'metrics'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($update[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw ValidationException::withMessages([
                "progress_update_{$index}" => "Missing required fields: " . implode(', ', $missingFields),
            ]);
        }

        // Validate progressable type
        $validProgressableTypes = [
            \App\Models\Track::class,
            \App\Models\Course::class,
            \App\Models\Module::class,
            \App\Models\Lesson::class,
        ];

        if (!in_array($update['progressable_type'], $validProgressableTypes)) {
            throw ValidationException::withMessages([
                "progress_update_{$index}" => "Invalid progressable type: {$update['progressable_type']}",
            ]);
        }

        // Validate that entities exist
        if (!User::where('id', $update['user_id'])->exists()) {
            throw ValidationException::withMessages([
                "progress_update_{$index}" => "User with ID {$update['user_id']} does not exist.",
            ]);
        }

        $progressableClass = $update['progressable_type'];
        if (!$progressableClass::where('id', $update['progressable_id'])->exists()) {
            throw ValidationException::withMessages([
                "progress_update_{$index}" => "Progressable entity with ID {$update['progressable_id']} does not exist.",
            ]);
        }

        // Validate metrics
        $this->validateProgressMetrics($update['metrics']);
    }

    /**
     * Validate bulk report generation data structure.
     *
     * @param array $request
     * @param int $index
     * @throws ValidationException
     */
    private function validateBulkReportData(array $request, int $index): void
    {
        $requiredFields = ['user_id', 'learning_path_type', 'learning_path_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($request[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw ValidationException::withMessages([
                "report_request_{$index}" => "Missing required fields: " . implode(', ', $missingFields),
            ]);
        }

        // Validate learning path type
        $validLearningPathTypes = [
            \App\Models\Track::class,
            \App\Models\Course::class,
        ];

        if (!in_array($request['learning_path_type'], $validLearningPathTypes)) {
            throw ValidationException::withMessages([
                "report_request_{$index}" => "Invalid learning path type: {$request['learning_path_type']}",
            ]);
        }

        // Validate that entities exist
        if (!User::where('id', $request['user_id'])->exists()) {
            throw ValidationException::withMessages([
                "report_request_{$index}" => "User with ID {$request['user_id']} does not exist.",
            ]);
        }

        $learningPathClass = $request['learning_path_type'];
        if (!$learningPathClass::where('id', $request['learning_path_id'])->exists()) {
            throw ValidationException::withMessages([
                "report_request_{$index}" => "Learning path with ID {$request['learning_path_id']} does not exist.",
            ]);
        }
    }

    /**
     * Get empty progress data structure.
     *
     * @param array $additional
     * @return array
     */
    private function getEmptyProgressData(array $additional = []): array
    {
        return array_merge([
            'completion_percentage' => 0.00,
            'time_spent_minutes' => 0,
            'engagement_score' => null,
            'is_completed' => false,
        ], $additional);
    }
}
