<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view courses
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Course $course): bool
    {
        // Users can view active courses
        if ($course->is_active) {
            return true;
        }

        // Instructors and admins can view inactive courses they created
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create courses
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Course $course): bool
    {
        // Only instructors and admins can update courses
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Course $course): bool
    {
        // Only instructors and admins can delete courses
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Course $course): bool
    {
        // Only admins can restore courses
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Course $course): bool
    {
        // Only admins can permanently delete courses
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can enroll in the course.
     */
    public function enroll(User $user, Course $course): bool
    {
        // All users can enroll in active courses
        return $course->is_active && $user->canEnrollInCourses();
    }

    /**
     * Determine whether the user can publish the course.
     */
    public function publish(User $user, Course $course): bool
    {
        // Only instructors and admins can publish courses
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can manage course content.
     */
    public function manageContent(User $user, Course $course): bool
    {
        // Only instructors and admins can manage content
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can assign modules to the course.
     */
    public function assignModule(User $user, Course $course, Module $module = null): bool
    {
        // Only instructors and admins can assign modules to courses
        if (!$user->hasInstructorPermissions()) {
            return false;
        }

        // If a specific module is provided, check if user can manage that module
        if ($module) {
            // Check if user has permission to use this module
            // For now, instructors can assign any module to their courses
            return true;
        }

        return true;
    }

    /**
     * Determine whether the user can remove modules from the course.
     */
    public function removeModule(User $user, Course $course, Module $module = null): bool
    {
        // Only instructors and admins can remove modules from courses
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can manage course enrollments.
     */
    public function manageEnrollments(User $user, Course $course): bool
    {
        // Only instructors and admins can manage enrollments
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can access course content.
     */
    public function access(User $user, Course $course): bool
    {
        // Users must be enrolled in the course to access content
        if (!$course->is_active) {
            return $user->hasInstructorPermissions();
        }

        // Check if user is enrolled in the course
        return $course->enrollments()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can view course progress.
     */
    public function viewProgress(User $user, Course $course): bool
    {
        // Instructors and admins can view all progress
        if ($user->hasInstructorPermissions()) {
            return true;
        }

        // Users can view their own progress if enrolled
        return $course->enrollments()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can generate certificates for the course.
     */
    public function generateCertificate(User $user, Course $course): bool
    {
        // Only instructors and admins can generate certificates
        return $user->hasInstructorPermissions();
    }
}
