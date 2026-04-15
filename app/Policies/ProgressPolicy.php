<?php

namespace App\Policies;

use App\Models\LearningProgress;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProgressPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view progress data
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LearningProgress $progress): bool
    {
        // Users can view their own progress
        if ($progress->user_id === $user->id) {
            return true;
        }

        // Instructors and admins can view all progress
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create progress records (for themselves)
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LearningProgress $progress): bool
    {
        // Users can update their own progress
        if ($progress->user_id === $user->id) {
            return true;
        }

        // Instructors and admins can update any progress
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LearningProgress $progress): bool
    {
        // Only admins can delete progress records
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LearningProgress $progress): bool
    {
        // Only admins can restore progress records
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LearningProgress $progress): bool
    {
        // Only admins can permanently delete progress records
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can view granular progress metrics.
     */
    public function viewGranularMetrics(User $user, LearningProgress $progress): bool
    {
        // Users can view their own granular metrics
        if ($progress->user_id === $user->id) {
            return true;
        }

        // Instructors and admins can view all granular metrics
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update granular progress metrics.
     */
    public function updateGranularMetrics(User $user, LearningProgress $progress): bool
    {
        // Users can update their own granular metrics
        if ($progress->user_id === $user->id) {
            return true;
        }

        // Instructors and admins can update any granular metrics
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can view progress reports.
     */
    public function viewReports(User $user, $progressable = null): bool
    {
        // Instructors and admins can view all progress reports
        if ($user->hasInstructorPermissions()) {
            return true;
        }

        // Users can view their own progress reports
        if ($progressable) {
            // Check if user has access to the progressable entity
            if ($progressable instanceof \App\Models\Track) {
                return $progressable->enrollments()->where('user_id', $user->id)->exists();
            }

            if ($progressable instanceof \App\Models\Course) {
                return $progressable->enrollments()->where('user_id', $user->id)->exists();
            }
        }

        return false;
    }

    /**
     * Determine whether the user can export progress data.
     */
    public function export(User $user, $progressable = null): bool
    {
        // Only instructors and admins can export progress data
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can reset progress.
     */
    public function reset(User $user, LearningProgress $progress): bool
    {
        // Only admins can reset progress
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can view aggregate progress across learning paths.
     */
    public function viewAggregateProgress(User $user, User $targetUser = null): bool
    {
        // Users can view their own aggregate progress
        if ($targetUser && $targetUser->id === $user->id) {
            return true;
        }

        // If no target user specified, assume viewing own progress
        if (!$targetUser) {
            return true;
        }

        // Instructors and admins can view aggregate progress for all users
        return $user->hasInstructorPermissions();
    }
}
