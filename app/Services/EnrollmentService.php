<?php

namespace App\Services;

use App\Models\Track;
use App\Models\User;
use App\Models\TrackEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    protected ConstraintEnforcementService $constraintService;

    public function __construct(ConstraintEnforcementService $constraintService)
    {
        $this->constraintService = $constraintService;
    }

    /**
     * Enroll a user in a track.
     *
     * @param User $user
     * @param Track $track
     * @param bool $isAdminOverride
     * @return TrackEnrollment
     * @throws ValidationException
     */
    public function enrollUser(User $user, Track $track, bool $isAdminOverride = false): TrackEnrollment
    {
        // Enforce enrollment constraints
        $this->constraintService->enforceTrackEnrollmentConstraints($user, $track, $isAdminOverride);

        // Check if user is already enrolled
        $existingEnrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        if ($existingEnrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'User is already enrolled in this track.',
            ]);
        }

        return DB::transaction(function () use ($user, $track) {
            $enrollment = TrackEnrollment::create([
                'user_id' => $user->id,
                'track_id' => $track->id,
                'enrolled_at' => now(),
                'progress_percentage' => 0.00,
            ]);

            // Check for achievements after enrollment
            $this->checkAchievementsAfterEnrollment($user);

            return $enrollment;
        });
    }

    /**
     * Check if a user has enrollment access to a track.
     *
     * @param User $user
     * @param Track $track
     * @return bool
     */
    public function checkEnrollmentAccess(User $user, Track $track): bool
    {
        // Check if user can access classroom
        if (!$user->canAccessClassroom()) {
            return false;
        }

        // Check if track is published
        if (!$track->is_published) {
            // Only instructors and admins can access unpublished tracks
            return $user->hasInstructorPermissions();
        }

        // Check if user is enrolled
        $enrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        if (!$enrollment) {
            // For free tracks, users can access without enrollment for preview
            return $track->isFree();
        }

        // For premium tracks, verify payment status
        if ($track->is_premium && !$track->isFree()) {
            return $this->verifyPaymentStatus($user, $track);
        }

        return true;
    }

    /**
     * Get all enrolled tracks for a user.
     *
     * @param User $user
     * @return Collection
     */
    public function getEnrolledTracks(User $user): Collection
    {
        return Track::whereHas('enrollments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->with(['instructor', 'levels.modules.lessons'])
        ->orderBy('created_at', 'desc')
        ->get();
    }

    /**
     * Get enrollment details for a user and track.
     *
     * @param User $user
     * @param Track $track
     * @return TrackEnrollment|null
     */
    public function getEnrollment(User $user, Track $track): ?TrackEnrollment
    {
        return TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();
    }

    /**
     * Unenroll a user from a track.
     *
     * @param User $user
     * @param Track $track
     * @return bool
     * @throws ValidationException
     */
    public function unenrollUser(User $user, Track $track): bool
    {
        $enrollment = $this->getEnrollment($user, $track);

        if (!$enrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'User is not enrolled in this track.',
            ]);
        }

        // Check if track allows unenrollment (business rule)
        if ($enrollment->progress_percentage > 50) {
            throw ValidationException::withMessages([
                'enrollment' => 'Cannot unenroll from track with more than 50% progress.',
            ]);
        }

        return DB::transaction(function () use ($enrollment) {
            return $enrollment->delete();
        });
    }

    /**
     * Get enrollment statistics for a track.
     *
     * @param Track $track
     * @return array
     */
    public function getEnrollmentStatistics(Track $track): array
    {
        $totalEnrollments = $track->enrollments()->count();
        $activeEnrollments = $track->enrollments()
            ->whereNull('completed_at')
            ->count();
        $completedEnrollments = $track->enrollments()
            ->whereNotNull('completed_at')
            ->count();

        $averageProgress = $track->enrollments()
            ->avg('progress_percentage') ?? 0;

        return [
            'total_enrollments' => $totalEnrollments,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'completion_rate' => $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 2) : 0,
            'average_progress' => round($averageProgress, 2),
        ];
    }

    /**
     * Verify payment for premium track.
     *
     * @param User $user
     * @param Track $track
     * @throws ValidationException
     */
    private function verifyPaymentForPremiumTrack(User $user, Track $track): void
    {
        // For now, we'll implement a simple check
        // In a real application, this would integrate with a payment processor

        // Check if user has a valid payment method or subscription
        // This is a placeholder implementation
        $hasValidPayment = $this->checkUserPaymentStatus($user, $track);

        if (!$hasValidPayment) {
            throw ValidationException::withMessages([
                'payment' => 'Payment verification required for premium track.',
            ]);
        }
    }

    /**
     * Check enrollment capacity for a track.
     *
     * @param Track $track
     * @throws ValidationException
     */
    private function checkEnrollmentCapacity(Track $track): void
    {
        // For now, we'll implement a simple capacity check
        // In a real application, this would be configurable per track

        $maxCapacity = 1000; // Default capacity
        $currentEnrollments = $track->enrollments()->count();

        if ($currentEnrollments >= $maxCapacity) {
            throw ValidationException::withMessages([
                'capacity' => 'Track has reached maximum enrollment capacity.',
            ]);
        }
    }

    /**
     * Verify payment status for a user and track.
     *
     * @param User $user
     * @param Track $track
     * @return bool
     */
    private function verifyPaymentStatus(User $user, Track $track): bool
    {
        // Placeholder implementation for payment verification
        // In a real application, this would check with payment processor

        return $this->checkUserPaymentStatus($user, $track);
    }

    /**
     * Check user payment status.
     *
     * @param User $user
     * @param Track $track
     * @return bool
     */
    private function checkUserPaymentStatus(User $user, Track $track): bool
    {
        // Placeholder implementation
        // In a real application, this would:
        // 1. Check if user has active subscription
        // 2. Check if user has purchased this specific track
        // 3. Verify payment with payment processor

        // For now, return true for admin/instructor users, false for others
        return $user->hasInstructorPermissions();
    }

    /**
     * Bulk enroll users in tracks with comprehensive validation.
     *
     * @param array $enrollments
     * @return array
     * @throws ValidationException
     */
    public function bulkEnrollUsers(array $enrollments): array
    {
        $results = [];
        $errors = [];

        // Validate all enrollments first
        foreach ($enrollments as $index => $enrollment) {
            try {
                $this->validateBulkEnrollmentData($enrollment, $index);
            } catch (ValidationException $e) {
                $errors[] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'bulk_enrollment' => 'Validation failed for bulk enrollment.',
                'enrollment_errors' => $errors,
            ]);
        }

        return DB::transaction(function () use ($enrollments, &$results) {
            foreach ($enrollments as $index => $enrollment) {
                try {
                    $user = User::findOrFail($enrollment['user_id']);
                    $track = Track::findOrFail($enrollment['track_id']);

                    $result = $this->enrollUser($user, $track);

                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'enrollment_id' => $result->id,
                        'user_id' => $user->id,
                        'track_id' => $track->id,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'user_id' => $enrollment['user_id'] ?? null,
                        'track_id' => $enrollment['track_id'] ?? null,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Bulk enrollment completed.',
                'results' => $results,
                'total_processed' => count($enrollments),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            ];
        });
    }

    /**
     * Bulk unenroll users from tracks.
     *
     * @param array $unenrollments
     * @return array
     * @throws ValidationException
     */
    public function bulkUnenrollUsers(array $unenrollments): array
    {
        $results = [];
        $errors = [];

        // Validate all unenrollments first
        foreach ($unenrollments as $index => $unenrollment) {
            try {
                $this->validateBulkUnenrollmentData($unenrollment, $index);
            } catch (ValidationException $e) {
                $errors[] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'bulk_unenrollment' => 'Validation failed for bulk unenrollment.',
                'unenrollment_errors' => $errors,
            ]);
        }

        return DB::transaction(function () use ($unenrollments, &$results) {
            foreach ($unenrollments as $index => $unenrollment) {
                try {
                    $user = User::findOrFail($unenrollment['user_id']);
                    $track = Track::findOrFail($unenrollment['track_id']);

                    $success = $this->unenrollUser($user, $track);

                    $results[] = [
                        'index' => $index,
                        'success' => $success,
                        'user_id' => $user->id,
                        'track_id' => $track->id,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'user_id' => $unenrollment['user_id'] ?? null,
                        'track_id' => $unenrollment['track_id'] ?? null,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Bulk unenrollment completed.',
                'results' => $results,
                'total_processed' => count($unenrollments),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            ];
        });
    }

    /**
     * Validate bulk enrollment data structure.
     *
     * @param array $enrollment
     * @param int $index
     * @throws ValidationException
     */
    private function validateBulkEnrollmentData(array $enrollment, int $index): void
    {
        $requiredFields = ['user_id', 'track_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($enrollment[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw ValidationException::withMessages([
                "enrollment_{$index}" => "Missing required fields: " . implode(', ', $missingFields),
            ]);
        }

        // Validate that the entities exist
        if (!User::where('id', $enrollment['user_id'])->exists()) {
            throw ValidationException::withMessages([
                "enrollment_{$index}" => "User with ID {$enrollment['user_id']} does not exist.",
            ]);
        }

        if (!Track::where('id', $enrollment['track_id'])->exists()) {
            throw ValidationException::withMessages([
                "enrollment_{$index}" => "Track with ID {$enrollment['track_id']} does not exist.",
            ]);
        }
    }

    /**
     * Validate bulk unenrollment data structure.
     *
     * @param array $unenrollment
     * @param int $index
     * @throws ValidationException
     */
    private function validateBulkUnenrollmentData(array $unenrollment, int $index): void
    {
        $requiredFields = ['user_id', 'track_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($unenrollment[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw ValidationException::withMessages([
                "unenrollment_{$index}" => "Missing required fields: " . implode(', ', $missingFields),
            ]);
        }

        // Validate that the entities exist
        if (!User::where('id', $unenrollment['user_id'])->exists()) {
            throw ValidationException::withMessages([
                "unenrollment_{$index}" => "User with ID {$unenrollment['user_id']} does not exist.",
            ]);
        }

        if (!Track::where('id', $unenrollment['track_id'])->exists()) {
            throw ValidationException::withMessages([
                "unenrollment_{$index}" => "Track with ID {$unenrollment['track_id']} does not exist.",
            ]);
        }
    }

    /**
     * Check for achievements after enrollment.
     *
     * @param User $user
     */
    private function checkAchievementsAfterEnrollment(User $user): void
    {
        try {
            // Avoid circular dependency by resolving AchievementService here
            $achievementService = app(AchievementService::class);
            $achievementService->checkEnrollmentAchievements($user);
        } catch (\Exception $e) {
            // Log the error but don't fail the enrollment
            \Log::error('Failed to check achievements after enrollment', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
