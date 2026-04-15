<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssessmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view assessments
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assessment $assessment): bool
    {
        // Users can view assessments if they have access to the associated content
        $assessable = $assessment->assessable;

        if (!$assessable) {
            return false;
        }

        // Check access based on the assessable type (lesson or module)
        if ($assessable instanceof \App\Models\Lesson) {
            return $user->can('access', $assessable);
        } elseif ($assessable instanceof \App\Models\Module) {
            return $user->can('access', $assessable);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only instructors and admins can create assessments
        return $user->hasInstructorPermissions();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assessment $assessment): bool
    {
        // Only instructors and admins who own the content can update assessments
        $assessable = $assessment->assessable;

        if (!$assessable) {
            return false;
        }

        // Get the track owner based on assessable type
        if ($assessable instanceof \App\Models\Lesson) {
            $trackInstructorId = $assessable->module->level->track->instructor_id;
        } elseif ($assessable instanceof \App\Models\Module) {
            $trackInstructorId = $assessable->level->track->instructor_id;
        } else {
            return false;
        }

        return $user->hasAdminPermissions() ||
               ($user->hasInstructorPermissions() && $trackInstructorId === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assessment $assessment): bool
    {
        // Same logic as update
        return $this->update($user, $assessment);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assessment $assessment): bool
    {
        // Only admins can restore assessments
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assessment $assessment): bool
    {
        // Only admins can permanently delete assessments
        return $user->hasAdminPermissions();
    }

    /**
     * Determine whether the user can take the assessment.
     */
    public function take(User $user, Assessment $assessment): bool
    {
        // Users can take assessments if they have access to the content
        return $this->view($user, $assessment);
    }

    /**
     * Determine whether the user can submit the assessment.
     */
    public function submit(User $user, Assessment $assessment): bool
    {
        // Users can submit assessments they can take
        return $this->take($user, $assessment);
    }

    /**
     * Determine whether the user can view assessment results.
     */
    public function viewResults(User $user, Assessment $assessment): bool
    {
        // Users can view their own results, instructors can view all results for their content
        $assessable = $assessment->assessable;

        if (!$assessable) {
            return false;
        }

        // Get the track owner based on assessable type
        if ($assessable instanceof \App\Models\Lesson) {
            $trackInstructorId = $assessable->module->level->track->instructor_id;
        } elseif ($assessable instanceof \App\Models\Module) {
            $trackInstructorId = $assessable->level->track->instructor_id;
        } else {
            return false;
        }

        // Instructors can view all results for their content
        if ($user->hasAdminPermissions() ||
            ($user->hasInstructorPermissions() && $trackInstructorId === $user->id)) {
            return true;
        }

        // Users can view their own results if they have access to the assessment
        return $this->view($user, $assessment);
    }
}
