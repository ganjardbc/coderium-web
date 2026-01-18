<?php

namespace App\Policies;

use App\Models\Level;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LevelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view levels
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Level $level): bool
    {
        // Users can view published levels or if they have access to the track
        if ($level->is_published && $level->track->is_published) {
            return true;
        }

        // Instructors and admins can view unpublished levels if they own the track
        return $user->hasInstructorPermissions() && $level->track->instructor_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create levels
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Level $level): bool
    {
        // Only the track owner or admins can update levels
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Level $level): bool
    {
        // Only the track owner or admins can delete levels
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Level $level): bool
    {
        // Only admins can restore levels
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Level $level): bool
    {
        // Only admins can permanently delete levels
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can access level content.
     */
    public function access(User $user, Level $level): bool
    {
        // Users must be enrolled in the track to access level content
        if (!$level->is_published || !$level->track->is_published) {
            return $user->hasInstructorPermissions() && $level->track->instructor_id === $user->id;
        }

        // Check if user is enrolled in the track
        return $level->track->enrollments()->where('user_id', $user->id)->exists();
    }
}
