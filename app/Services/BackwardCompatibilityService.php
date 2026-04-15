<?php

namespace App\Services;

use App\Models\LessonProgress;
use App\Models\LearningProgress;
use App\Models\TrackEnrollment;
use App\Models\CourseEnrollment;
use App\Models\Certificate;
use Illuminate\Support\Collection;

class BackwardCompatibilityService
{
    /**
     * Get unified progress data for a user across both old and new systems.
     */
    public function getUserProgress($userId): Collection
    {
        $progress = collect();

        // Get old lesson progress records
        $lessonProgress = LessonProgress::where('user_id', $userId)->get();
        foreach ($lessonProgress as $record) {
            $progress->push([
                'id' => $record->id,
                'user_id' => $record->user_id,
                'type' => 'lesson',
                'entity_id' => $record->lesson_id,
                'completion_percentage' => $record->completion_percentage,
                'time_spent_minutes' => $record->time_spent_minutes,
                'completed_at' => $record->completed_at,
                'source' => 'legacy',
            ]);
        }

        // Get new learning progress records
        $learningProgress = LearningProgress::where('user_id', $userId)->get();
        foreach ($learningProgress as $record) {
            $progress->push([
                'id' => $record->id,
                'user_id' => $record->user_id,
                'type' => strtolower(class_basename($record->progressable_type)),
                'entity_id' => $record->progressable_id,
                'completion_percentage' => $record->completion_percentage,
                'time_spent_minutes' => $record->time_spent_minutes,
                'engagement_score' => $record->engagement_score,
                'last_accessed_at' => $record->last_accessed_at,
                'completed_at' => $record->completed_at,
                'source' => 'enhanced',
            ]);
        }

        return $progress;
    }

    /**
     * Get unified enrollment data for a user across tracks and courses.
     */
    public function getUserEnrollments($userId): Collection
    {
        $enrollments = collect();

        // Get track enrollments
        $trackEnrollments = TrackEnrollment::where('user_id', $userId)->get();
        foreach ($trackEnrollments as $enrollment) {
            $enrollments->push($enrollment->toUnifiedEnrollmentFormat());
        }

        // Get course enrollments
        $courseEnrollments = CourseEnrollment::where('user_id', $userId)->get();
        foreach ($courseEnrollments as $enrollment) {
            $enrollments->push($enrollment->toTrackEnrollmentFormat());
        }

        return $enrollments;
    }

    /**
     * Get unified certificate data for a user across tracks and courses.
     */
    public function getUserCertificates($userId): Collection
    {
        $certificates = Certificate::where('user_id', $userId)->get();

        return $certificates->map(function ($certificate) {
            return [
                'id' => $certificate->id,
                'user_id' => $certificate->user_id,
                'certificate_number' => $certificate->certificate_number,
                'title' => $certificate->title,
                'description' => $certificate->description,
                'issued_at' => $certificate->issued_at,
                'completed_at' => $certificate->completed_at,
                'verification_url' => $certificate->verification_url,
                'type' => $certificate->certificate_type,
                'entity_id' => $certificate->certifiable_id ?? $certificate->track_id,
                'entity_type' => $certificate->certifiable_type ?? 'App\\Models\\Track',
                'is_track_certificate' => $certificate->isTrackCertificate(),
                'is_course_certificate' => $certificate->isCourseCertificate(),
            ];
        });
    }

    /**
     * Convert lesson progress to learning progress format.
     */
    public function convertLessonProgressToLearningProgress(LessonProgress $lessonProgress): array
    {
        return [
            'user_id' => $lessonProgress->user_id,
            'progressable_type' => 'App\\Models\\Lesson',
            'progressable_id' => $lessonProgress->lesson_id,
            'completion_percentage' => $lessonProgress->completion_percentage,
            'time_spent_minutes' => $lessonProgress->time_spent_minutes,
            'engagement_score' => null,
            'last_accessed_at' => $lessonProgress->updated_at,
            'completed_at' => $lessonProgress->completed_at,
        ];
    }

    /**
     * Get progress data in the old format for backward compatibility.
     */
    public function getProgressInLegacyFormat($userId, $lessonId): ?array
    {
        // First check if there's a legacy record
        $lessonProgress = LessonProgress::where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->first();

        if ($lessonProgress) {
            return [
                'id' => $lessonProgress->id,
                'user_id' => $lessonProgress->user_id,
                'lesson_id' => $lessonProgress->lesson_id,
                'completed_at' => $lessonProgress->completed_at,
                'time_spent' => $lessonProgress->time_spent,
                'created_at' => $lessonProgress->created_at,
                'updated_at' => $lessonProgress->updated_at,
            ];
        }

        // Check the new learning progress system
        $learningProgress = LearningProgress::where('user_id', $userId)
            ->where('progressable_type', 'App\\Models\\Lesson')
            ->where('progressable_id', $lessonId)
            ->first();

        if ($learningProgress) {
            return [
                'id' => $learningProgress->id,
                'user_id' => $learningProgress->user_id,
                'lesson_id' => $learningProgress->progressable_id,
                'completed_at' => $learningProgress->completed_at,
                'time_spent' => $learningProgress->time_spent_minutes * 60, // Convert back to seconds
                'created_at' => $learningProgress->created_at,
                'updated_at' => $learningProgress->updated_at,
            ];
        }

        return null;
    }

    /**
     * Ensure progress exists in both old and new systems during transition.
     */
    public function syncProgressSystems($userId, $lessonId, array $progressData): void
    {
        // Update or create in legacy system
        LessonProgress::updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lessonId],
            [
                'completed_at' => $progressData['completed_at'] ?? null,
                'time_spent' => $progressData['time_spent'] ?? 0,
            ]
        );

        // Update or create in new system
        LearningProgress::updateOrCreate(
            [
                'user_id' => $userId,
                'progressable_type' => 'App\\Models\\Lesson',
                'progressable_id' => $lessonId,
            ],
            [
                'completion_percentage' => isset($progressData['completed_at']) ? 100.00 : 0.00,
                'time_spent_minutes' => intval(($progressData['time_spent'] ?? 0) / 60),
                'last_accessed_at' => now(),
                'completed_at' => $progressData['completed_at'] ?? null,
            ]
        );
    }
}
