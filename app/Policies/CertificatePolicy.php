<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CertificatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view certificates
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Certificate $certificate): bool
    {
        // Users can view their own certificates
        if ($certificate->user_id === $user->id) {
            return true;
        }

        // Instructors and admins can view all certificates
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create certificates
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Certificate $certificate): bool
    {
        // Only admins can update certificates (certificates should be immutable)
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Certificate $certificate): bool
    {
        // Only admins can delete certificates
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Certificate $certificate): bool
    {
        // Only admins can restore certificates
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Certificate $certificate): bool
    {
        // Only admins can permanently delete certificates
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can generate certificates for a specific learning path.
     */
    public function generate(User $user, $certifiable = null): bool
    {
        // Only instructors and admins can generate certificates
        if (!$user->hasInstructorPermissions()) {
            return false;
        }

        // If a specific certifiable (track/course) is provided, check permissions
        if ($certifiable) {
            // For tracks, check if user owns the track
            if ($certifiable instanceof \App\Models\Track) {
                return $user->hasAdminPermissions() || $certifiable->instructor_id === $user->id;
            }

            // For courses, instructors can generate certificates
            if ($certifiable instanceof \App\Models\Course) {
                return true;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can download the certificate.
     */
    public function download(User $user, Certificate $certificate): bool
    {
        // Users can download their own certificates
        if ($certificate->user_id === $user->id) {
            return true;
        }

        // Instructors and admins can download all certificates
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can verify the certificate.
     */
    public function verify(User $user, Certificate $certificate): bool
    {
        // All authenticated users can verify certificates
        return true;
    }

    /**
     * Determine whether the user can revoke the certificate.
     */
    public function revoke(User $user, Certificate $certificate): bool
    {
        // Only admins can revoke certificates
        return $user->hasAdminPermissions();
    }
}
