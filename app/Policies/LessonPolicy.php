<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LessonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view lessons
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        // Users can view published lessons or if they have access to the track
        if ($lesson->is_published &&
            $lesson->module->is_published &&
            $lesson->module->level->is_published &&
            $lesson->module->level->track->is_published) {
            return true;
        }

        // Instructors and admins can view unpublished lessons if they own the track
        return $user->hasInstructorPermissions() && $lesson->module->level->track->instructor_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create lessons
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lesson $lesson): bool
    {
        // Only the track owner or admins can update lessons
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $lesson->module->level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        // Only the track owner or admins can delete lessons
        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $lesson->module->level->track->instructor_id === $user->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lesson $lesson): bool
    {
        // Only admins can restore lessons
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lesson $lesson): bool
    {
        // Only admins can permanently delete lessons
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can access lesson content.
     */
    public function access(User $user, Lesson $lesson): bool
    {
        // Users must be enrolled in the track to access lesson content
        if (!$lesson->is_published ||
            !$lesson->module->is_published ||
            !$lesson->module->level->is_published ||
            !$lesson->module->level->track->is_published) {
            return $user->hasInstructorPermissions() && $lesson->module->level->track->instructor_id === $user->id;
        }

        // Check if user is enrolled in the track
        return $lesson->module->level->track->enrollments()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can complete the lesson.
     */
    public function complete(User $user, Lesson $lesson): bool
    {
        // Users can complete lessons they have access to
        return $this->access($user, $lesson);
    }
}
