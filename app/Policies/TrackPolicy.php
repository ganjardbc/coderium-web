<?php

namespace App\Policies;

use App\Models\Track;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TrackPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view published tracks
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Track $track): bool
    {
        // Users can view published tracks or their own tracks
        if ($track->is_published) {
            return true;
        }

        // Instructors and admins can view unpublished tracks they created
        return $user->hasInstructorPermissions() && $track->instructor_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create tracks
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Track $track): bool
    {
        // Only the track creator or admins can update tracks
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Track $track): bool
    {
        // Only the track creator or admins can delete tracks
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Track $track): bool
    {
        // Only admins can restore tracks
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Track $track): bool
    {
        // Only admins can permanently delete tracks
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can enroll in the track.
     */
    public function enroll(User $user, Track $track): bool
    {
        // All users can enroll in published tracks
        return $track->is_published && $user->canEnrollInTracks();
    }

    /**
     * Determine whether the user can publish the track.
     */
    public function publish(User $user, Track $track): bool
    {
        // Only the track creator or admins can publish tracks
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can manage track content.
     */
    public function manageContent(User $user, Track $track): bool
    {
        // Only the track creator or admins can manage content
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $track->instructor_id === $user->id);
    }
}
