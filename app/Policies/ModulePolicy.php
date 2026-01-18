<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ModulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view modules
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Module $module): bool
    {
        // Users can view published modules or if they have access to the track
        if ($module->is_published && $module->level->is_published && $module->level->track->is_published) {
            return true;
        }

        // Instructors and admins can view unpublished modules if they own the track
        return $user->hasInstructorPermissions() && $module->level->track->instructor_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create modules
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Module $module): bool
    {
        // Only the track owner or admins can update modules
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $module->level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Module $module): bool
    {
        // Only the track owner or admins can delete modules
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $module->level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Module $module): bool
    {
        // Only admins can restore modules
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Module $module): bool
    {
        // Only admins can permanently delete modules
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can access module content.
     */
    public function access(User $user, Module $module): bool
    {
        // Users must be enrolled in the track to access module content
        if (!$module->is_published || !$module->level->is_published || !$module->level->track->is_published) {
            return $user->hasInstructorPermissions() && $module->level->track->instructor_id === $user->id;
        }

        // Check if user is enrolled in the track
        return $module->level->track->enrollments()->where('user_id', $user->id)->exists();
    }
}
