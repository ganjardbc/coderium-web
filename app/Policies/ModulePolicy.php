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
        // Instructors and admins can always access modules they have permission for
        if ($user->hasInstructorPermissions()) {
            // Check if module is in a track they own
            if ($module->level && $module->level->track && $module->level->track->instructor_id === $user->id) {
                return true;
            }

            // Admins can access any module
            if ($user->hasAdminPermissions()) {
                return true;
            }
        }

        // For published modules, check enrollment in any associated learning path
        if ($module->is_published) {
            // Check track enrollment (backward compatibility)
            if ($module->level && $module->level->is_published && $module->level->track->is_published) {
                if ($module->level->track->enrollments()->where('user_id', $user->id)->exists()) {
                    return true;
                }
            }

            // Check course enrollment (new functionality)
            foreach ($module->courses as $course) {
                if ($course->is_active && $course->enrollments()->where('user_id', $user->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can assign this module to levels.
     */
    public function assignToLevel(User $user, Module $module): bool
    {
        // Only instructors and admins can assign modules to levels
        if (!$user->hasInstructorPermissions()) {
            return false;
        }

        // Check if user has permission to manage this module
        if ($module->level && $module->level->track) {
            return $user->hasAdminPermissions() || $module->level->track->instructor_id === $user->id;
        }

        // For modules not yet assigned to tracks, instructors can assign them
        return true;
    }

    /**
     * Determine whether the user can assign this module to courses.
     */
    public function assignToCourse(User $user, Module $module): bool
    {
        // Only instructors and admins can assign modules to courses
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can remove this module from levels.
     */
    public function removeFromLevel(User $user, Module $module): bool
    {
        // Only instructors and admins can remove modules from levels
        if (!$user->hasInstructorPermissions()) {
            return false;
        }

        // Check if user has permission to manage the associated track
        if ($module->level && $module->level->track) {
            return $user->hasAdminPermissions() || $module->level->track->instructor_id === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can remove this module from courses.
     */
    public function removeFromCourse(User $user, Module $module): bool
    {
        // Only instructors and admins can remove modules from courses
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can manage module assignments.
     */
    public function manageAssignments(User $user, Module $module): bool
    {
        // Only instructors and admins can manage module assignments
        return $user->hasInstructorPermissions();
    }
}
