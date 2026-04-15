<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view assignments
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assignment $assignment): bool
    {
        // Users can view assignments if they have access to the module
        return $user->can('access', $assignment->module);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create assignments
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        // Only the track owner or admins can update assignments
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $assignment->module->level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        // Only the track owner or admins can delete assignments
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $assignment->module->level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assignment $assignment): bool
    {
        // Only admins can restore assignments
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assignment $assignment): bool
    {
        // Only admins can permanently delete assignments
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can submit to the assignment.
     */
    public function submit(User $user, Assignment $assignment): bool
    {
        // Users can submit assignments if they have access to the module
        return $this->view($user, $assignment);
    }

    /**
     * Determine whether the user can grade the assignment.
     */
    public function grade(User $user, Assignment $assignment): bool
    {
        // Only the track owner or admins can grade assignments
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $assignment->module->level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can view submissions.
     */
    public function viewSubmissions(User $user, Assignment $assignment): bool
    {
        // Instructors can view all submissions for their assignments
        if ($user->hasAdminPermissions() ||
            ($user->hasInstructorPermissions() && $assignment->module->level->track->instructor_id === $user->id)) {
            return true;
        }

        // Users can view their own submissions if they have access to the assignment
        return $this->view($user, $assignment);
    }

    /**
     * Determine whether the user can provide feedback.
     */
    public function provideFeedback(User $user, Assignment $assignment): bool
    {
        // Only the track owner or admins can provide feedback
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $assignment->module->level->track->instructor_id === $user->id);
    }
}
