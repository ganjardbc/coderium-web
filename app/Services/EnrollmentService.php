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
    /**
     * Enroll a user in a track.
     *
     * @param User $user
     * @param Track $track
     * @return TrackEnrollment
     * @throws ValidationException
     */
    public function enrollUser(User $user, Track $track): TrackEnrollment
    {
        // Check if user can enroll in tracks
        if (!$user->canEnrollInTracks()) {
            throw ValidationException::withMessages([
                'user' => 'User does not have permission to enroll in tracks.',
            ]);
        }

        // Check if track is published
        if (!$track->is_published) {
            throw ValidationException::withMessages([
                'track' => 'Cannot enroll in unpublished track.',
            ]);
        }

        // Check if user is already enrolled
        $existingEnrollment = TrackEnrollment::where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->first();

        if ($existingEnrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'User is already enrolled in this track.',
            ]);
        }

        // Check payment verification for premium tracks
        if ($track->is_premium && !$track->isFree()) {
            $this->verifyPaymentForPremiumTrack($user, $track);
        }

        // Check enrollment capacity
        $this->checkEnrollmentCapacity($track);

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
