<?php

namespace App\Services;

use App\Models\User;
use App\Models\Track;
use App\Models\Course;
use App\Models\TrackEnrollment;
use App\Models\CourseEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ConstraintEnforcementService
{
    /**
     * Default enrollment limits.
     */
    private const DEFAULT_TRACK_CAPACITY = 1000;
    private const DEFAULT_COURSE_CAPACITY = 500;
    private const DEFAULT_USER_TRACK_LIMIT = 10;
    private const DEFAULT_USER_COURSE_LIMIT = 15;

    /**
     * Enforce enrollment constraints for tracks.
     *
     * @param User $user
     * @param Track $track
     * @param bool $isAdminOverride
     * @throws ValidationException
     */
    public function enforceTrackEnrollmentConstraints(User $user, Track $track, bool $isAdminOverride = false): void
    {
        // Skip constraints for admin override
        if ($isAdminOverride && $user->hasAdminPermissions()) {
            return;
        }

        // Check track capacity
        $this->checkTrackCapacity($track);

        // Check user enrollment limits
        $this->checkUserTrackEnrollmentLimit($user);

        // Check track prerequisites
        $this->checkTrackPrerequisites($user, $track);

        // Check track availability window
        $this->checkTrackAvailabilityWindow($track);

        // Check user role permissions
        $this->checkTrackRolePermissions($user, $track);
    }

    /**
     * Enforce enrollment constraints for courses.
     *
     * @param User $user
     * @param Course $course
     * @param bool $isAdminOverride
     * @throws ValidationException
     */
    public function enforceCourseEnrollmentConstraints(User $user, Course $course, bool $isAdminOverride = false): void
    {
        // Skip constraints for admin override
        if ($isAdminOverride && $user->hasAdminPermissions()) {
            return;
        }

        // Check course capacity
        $this->checkCourseCapacity($course);

        // Check user enrollment limits
        $this->checkUserCourseEnrollmentLimit($user);

        // Check course prerequisites
        $this->checkCoursePrerequisites($user, $course);

        // Check course availability window
        $this->checkCourseAvailabilityWindow($course);

        // Check user role permissions
        $this->checkCourseRolePermissions($user, $course);
    }

    /**
     * Validate consistent constraints across learning paths.
     *
     * @param User $user
     * @param array $enrollmentRequests
     * @param bool $isAdminOverride
     * @return array
     * @throws ValidationException
     */
    public function validateConsistentConstraints(User $user, array $enrollmentRequests, bool $isAdminOverride = false): array
    {
        $validationResults = [];
        $errors = [];

        foreach ($enrollmentRequests as $index => $request) {
            try {
                $this->validateSingleEnrollmentRequest($user, $request, $isAdminOverride);
                $validationResults[] = [
                    'index' => $index,
                    'valid' => true,
                    'request' => $request,
                ];
            } catch (ValidationException $e) {
                $validationResults[] = [
                    'index' => $index,
                    'valid' => false,
                    'request' => $request,
                    'errors' => $e->errors(),
                ];
                $errors[] = $e->errors();
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'constraint_validation' => 'One or more enrollment requests failed constraint validation.',
                'validation_errors' => $errors,
            ]);
        }

        return $validationResults;
    }

    /**
     * Get enrollment capacity information for a learning path.
     *
     * @param Model $learningPath
     * @return array
     */
    public function getEnrollmentCapacityInfo(Model $learningPath): array
    {
        if ($learningPath instanceof Track) {
            return $this->getTrackCapacityInfo($learningPath);
        } elseif ($learningPath instanceof Course) {
            return $this->getCourseCapacityInfo($learningPath);
        }

        throw new \InvalidArgumentException('Learning path must be a Track or Course instance.');
    }

    /**
     * Check if user can enroll in additional learning paths.
     *
     * @param User $user
     * @param string $learningPathType
     * @return array
     */
    public function checkUserEnrollmentCapacity(User $user, string $learningPathType): array
    {
        if ($learningPathType === 'track') {
            return $this->checkUserTrackCapacity($user);
        } elseif ($learningPathType === 'course') {
            return $this->checkUserCourseCapacity($user);
        }

        throw new \InvalidArgumentException('Learning path type must be "track" or "course".');
    }

    /**
     * Override constraints for administrators.
     *
     * @param User $admin
     * @param User $targetUser
     * @param Model $learningPath
     * @param string $overrideReason
     * @return array
     * @throws ValidationException
     */
    public function overrideConstraints(User $admin, User $targetUser, Model $learningPath, string $overrideReason): array
    {
        if (!$admin->hasAdminPermissions()) {
            throw ValidationException::withMessages([
                'authorization' => 'Only administrators can override enrollment constraints.',
            ]);
        }

        $overrideData = [
            'admin_id' => $admin->id,
            'target_user_id' => $targetUser->id,
            'learning_path_type' => get_class($learningPath),
            'learning_path_id' => $learningPath->id,
            'override_reason' => $overrideReason,
            'overridden_at' => now(),
        ];

        // Log the override for audit purposes
        $this->logConstraintOverride($overrideData);

        return [
            'success' => true,
            'message' => 'Constraints overridden successfully.',
            'override_data' => $overrideData,
        ];
    }

    /**
     * Get constraint enforcement statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getConstraintEnforcementStatistics(array $filters = []): array
    {
        $dateRange = $this->parseDateRange($filters);

        return [
            'track_constraints' => $this->getTrackConstraintStats($dateRange),
            'course_constraints' => $this->getCourseConstraintStats($dateRange),
            'user_limit_violations' => $this->getUserLimitViolationStats($dateRange),
            'capacity_utilization' => $this->getCapacityUtilizationStats(),
            'override_statistics' => $this->getOverrideStatistics($dateRange),
        ];
    }

    /**
     * Check track capacity constraints.
     *
     * @param Track $track
     * @throws ValidationException
     */
    private function checkTrackCapacity(Track $track): void
    {
        $maxCapacity = $track->max_enrollments ?? self::DEFAULT_TRACK_CAPACITY;
        $currentEnrollments = $this->getTrackEnrollmentCount($track);

        if ($currentEnrollments >= $maxCapacity) {
            throw ValidationException::withMessages([
                'capacity' => "Track '{$track->title}' has reached maximum enrollment capacity ({$maxCapacity}).",
            ]);
        }
    }

    /**
     * Check course capacity constraints.
     *
     * @param Course $course
     * @throws ValidationException
     */
    private function checkCourseCapacity(Course $course): void
    {
        $maxCapacity = $course->max_enrollments ?? self::DEFAULT_COURSE_CAPACITY;
        $currentEnrollments = $this->getCourseEnrollmentCount($course);

        if ($currentEnrollments >= $maxCapacity) {
            throw ValidationException::withMessages([
                'capacity' => "Course '{$course->title}' has reached maximum enrollment capacity ({$maxCapacity}).",
            ]);
        }
    }

    /**
     * Check user track enrollment limits.
     *
     * @param User $user
     * @throws ValidationException
     */
    private function checkUserTrackEnrollmentLimit(User $user): void
    {
        $maxTracks = $user->max_track_enrollments ?? self::DEFAULT_USER_TRACK_LIMIT;
        $currentEnrollments = TrackEnrollment::where('user_id', $user->id)
            ->whereNull('completed_at')
            ->count();

        if ($currentEnrollments >= $maxTracks) {
            throw ValidationException::withMessages([
                'user_limit' => "User has reached maximum active track enrollment limit ({$maxTracks}).",
            ]);
        }
    }

    /**
     * Check user course enrollment limits.
     *
     * @param User $user
     * @throws ValidationException
     */
    private function checkUserCourseEnrollmentLimit(User $user): void
    {
        $maxCourses = $user->max_course_enrollments ?? self::DEFAULT_USER_COURSE_LIMIT;
        $currentEnrollments = CourseEnrollment::where('user_id', $user->id)
            ->whereNull('completed_at')
            ->count();

        if ($currentEnrollments >= $maxCourses) {
            throw ValidationException::withMessages([
                'user_limit' => "User has reached maximum active course enrollment limit ({$maxCourses}).",
            ]);
        }
    }

    /**
     * Check track prerequisites.
     *
     * @param User $user
     * @param Track $track
     * @throws ValidationException
     */
    private function checkTrackPrerequisites(User $user, Track $track): void
    {
        // Check if track has prerequisites defined
        if (!$track->prerequisite_track_ids) {
            return;
        }

        $prerequisiteIds = is_string($track->prerequisite_track_ids)
            ? json_decode($track->prerequisite_track_ids, true)
            : $track->prerequisite_track_ids;

        if (empty($prerequisiteIds)) {
            return;
        }

        $completedPrerequisites = TrackEnrollment::where('user_id', $user->id)
            ->whereIn('track_id', $prerequisiteIds)
            ->whereNotNull('completed_at')
            ->pluck('track_id')
            ->toArray();

        $missingPrerequisites = array_diff($prerequisiteIds, $completedPrerequisites);

        if (!empty($missingPrerequisites)) {
            $prerequisiteNames = Track::whereIn('id', $missingPrerequisites)->pluck('title')->toArray();
            throw ValidationException::withMessages([
                'prerequisites' => 'Missing required prerequisites: ' . implode(', ', $prerequisiteNames),
            ]);
        }
    }

    /**
     * Check course prerequisites.
     *
     * @param User $user
     * @param Course $course
     * @throws ValidationException
     */
    private function checkCoursePrerequisites(User $user, Course $course): void
    {
        // Check if course has prerequisites defined
        if (!$course->prerequisite_course_ids) {
            return;
        }

        $prerequisiteIds = is_string($course->prerequisite_course_ids)
            ? json_decode($course->prerequisite_course_ids, true)
            : $course->prerequisite_course_ids;

        if (empty($prerequisiteIds)) {
            return;
        }

        $completedPrerequisites = CourseEnrollment::where('user_id', $user->id)
            ->whereIn('course_id', $prerequisiteIds)
            ->whereNotNull('completed_at')
            ->pluck('course_id')
            ->toArray();

        $missingPrerequisites = array_diff($prerequisiteIds, $completedPrerequisites);

        if (!empty($missingPrerequisites)) {
            $prerequisiteNames = Course::whereIn('id', $missingPrerequisites)->pluck('title')->toArray();
            throw ValidationException::withMessages([
                'prerequisites' => 'Missing required prerequisites: ' . implode(', ', $prerequisiteNames),
            ]);
        }
    }

    /**
     * Check track availability window.
     *
     * @param Track $track
     * @throws ValidationException
     */
    private function checkTrackAvailabilityWindow(Track $track): void
    {
        $now = now();

        if ($track->enrollment_start_date && $now->lt($track->enrollment_start_date)) {
            throw ValidationException::withMessages([
                'availability' => "Track enrollment opens on {$track->enrollment_start_date->format('Y-m-d H:i')}.",
            ]);
        }

        if ($track->enrollment_end_date && $now->gt($track->enrollment_end_date)) {
            throw ValidationException::withMessages([
                'availability' => "Track enrollment closed on {$track->enrollment_end_date->format('Y-m-d H:i')}.",
            ]);
        }
    }

    /**
     * Check course availability window.
     *
     * @param Course $course
     * @throws ValidationException
     */
    private function checkCourseAvailabilityWindow(Course $course): void
    {
        $now = now();

        if ($course->enrollment_start_date && $now->lt($course->enrollment_start_date)) {
            throw ValidationException::withMessages([
                'availability' => "Course enrollment opens on {$course->enrollment_start_date->format('Y-m-d H:i')}.",
            ]);
        }

        if ($course->enrollment_end_date && $now->gt($course->enrollment_end_date)) {
            throw ValidationException::withMessages([
                'availability' => "Course enrollment closed on {$course->enrollment_end_date->format('Y-m-d H:i')}.",
            ]);
        }
    }

    /**
     * Check track role permissions.
     *
     * @param User $user
     * @param Track $track
     * @throws ValidationException
     */
    private function checkTrackRolePermissions(User $user, Track $track): void
    {
        // Check if track has role restrictions
        if (!$track->allowed_roles) {
            return;
        }

        $allowedRoles = is_string($track->allowed_roles)
            ? json_decode($track->allowed_roles, true)
            : $track->allowed_roles;

        if (!empty($allowedRoles) && !in_array($user->role, $allowedRoles)) {
            throw ValidationException::withMessages([
                'role_permission' => "User role '{$user->role}' is not allowed to enroll in this track.",
            ]);
        }
    }

    /**
     * Check course role permissions.
     *
     * @param User $user
     * @param Course $course
     * @throws ValidationException
     */
    private function checkCourseRolePermissions(User $user, Course $course): void
    {
        // Check if course has role restrictions
        if (!$course->allowed_roles) {
            return;
        }

        $allowedRoles = is_string($course->allowed_roles)
            ? json_decode($course->allowed_roles, true)
            : $course->allowed_roles;

        if (!empty($allowedRoles) && !in_array($user->role, $allowedRoles)) {
            throw ValidationException::withMessages([
                'role_permission' => "User role '{$user->role}' is not allowed to enroll in this course.",
            ]);
        }
    }

    /**
     * Validate a single enrollment request.
     *
     * @param User $user
     * @param array $request
     * @param bool $isAdminOverride
     * @throws ValidationException
     */
    private function validateSingleEnrollmentRequest(User $user, array $request, bool $isAdminOverride): void
    {
        $type = $request['type'] ?? null;
        $id = $request['id'] ?? null;

        if (!$type || !$id) {
            throw ValidationException::withMessages([
                'request_format' => 'Enrollment request must include type and id.',
            ]);
        }

        if ($type === 'track') {
            $track = Track::findOrFail($id);
            $this->enforceTrackEnrollmentConstraints($user, $track, $isAdminOverride);
        } elseif ($type === 'course') {
            $course = Course::findOrFail($id);
            $this->enforceCourseEnrollmentConstraints($user, $course, $isAdminOverride);
        } else {
            throw ValidationException::withMessages([
                'request_type' => 'Invalid enrollment request type. Must be "track" or "course".',
            ]);
        }
    }

    /**
     * Get track capacity information.
     *
     * @param Track $track
     * @return array
     */
    private function getTrackCapacityInfo(Track $track): array
    {
        $maxCapacity = $track->max_enrollments ?? self::DEFAULT_TRACK_CAPACITY;
        $currentEnrollments = $this->getTrackEnrollmentCount($track);

        return [
            'learning_path_type' => 'track',
            'learning_path_id' => $track->id,
            'learning_path_title' => $track->title,
            'max_capacity' => $maxCapacity,
            'current_enrollments' => $currentEnrollments,
            'available_spots' => max(0, $maxCapacity - $currentEnrollments),
            'utilization_percentage' => round(($currentEnrollments / $maxCapacity) * 100, 2),
            'is_full' => $currentEnrollments >= $maxCapacity,
        ];
    }

    /**
     * Get course capacity information.
     *
     * @param Course $course
     * @return array
     */
    private function getCourseCapacityInfo(Course $course): array
    {
        $maxCapacity = $course->max_enrollments ?? self::DEFAULT_COURSE_CAPACITY;
        $currentEnrollments = $this->getCourseEnrollmentCount($course);

        return [
            'learning_path_type' => 'course',
            'learning_path_id' => $course->id,
            'learning_path_title' => $course->title,
            'max_capacity' => $maxCapacity,
            'current_enrollments' => $currentEnrollments,
            'available_spots' => max(0, $maxCapacity - $currentEnrollments),
            'utilization_percentage' => round(($currentEnrollments / $maxCapacity) * 100, 2),
            'is_full' => $currentEnrollments >= $maxCapacity,
        ];
    }

    /**
     * Check user track capacity.
     *
     * @param User $user
     * @return array
     */
    private function checkUserTrackCapacity(User $user): array
    {
        $maxTracks = $user->max_track_enrollments ?? self::DEFAULT_USER_TRACK_LIMIT;
        $currentEnrollments = TrackEnrollment::where('user_id', $user->id)
            ->whereNull('completed_at')
            ->count();

        return [
            'user_id' => $user->id,
            'learning_path_type' => 'track',
            'max_enrollments' => $maxTracks,
            'current_enrollments' => $currentEnrollments,
            'available_enrollments' => max(0, $maxTracks - $currentEnrollments),
            'can_enroll' => $currentEnrollments < $maxTracks,
        ];
    }

    /**
     * Check user course capacity.
     *
     * @param User $user
     * @return array
     */
    private function checkUserCourseCapacity(User $user): array
    {
        $maxCourses = $user->max_course_enrollments ?? self::DEFAULT_USER_COURSE_LIMIT;
        $currentEnrollments = CourseEnrollment::where('user_id', $user->id)
            ->whereNull('completed_at')
            ->count();

        return [
            'user_id' => $user->id,
            'learning_path_type' => 'course',
            'max_enrollments' => $maxCourses,
            'current_enrollments' => $currentEnrollments,
            'available_enrollments' => max(0, $maxCourses - $currentEnrollments),
            'can_enroll' => $currentEnrollments < $maxCourses,
        ];
    }

    /**
     * Get track enrollment count with caching.
     *
     * @param Track $track
     * @return int
     */
    private function getTrackEnrollmentCount(Track $track): int
    {
        $cacheKey = "track_enrollment_count_{$track->id}";

        return Cache::remember($cacheKey, 60, function () use ($track) {
            return TrackEnrollment::where('track_id', $track->id)->count();
        });
    }

    /**
     * Get course enrollment count with caching.
     *
     * @param Course $course
     * @return int
     */
    private function getCourseEnrollmentCount(Course $course): int
    {
        $cacheKey = "course_enrollment_count_{$course->id}";

        return Cache::remember($cacheKey, 60, function () use ($course) {
            return CourseEnrollment::where('course_id', $course->id)->count();
        });
    }

    /**
     * Log constraint override for audit purposes.
     *
     * @param array $overrideData
     */
    private function logConstraintOverride(array $overrideData): void
    {
        // In a real application, this would log to a dedicated audit table
        \Log::info('Constraint override applied', $overrideData);
    }

    /**
     * Parse date range from filters.
     *
     * @param array $filters
     * @return array
     */
    private function parseDateRange(array $filters): array
    {
        return [
            'start_date' => $filters['start_date'] ?? now()->subDays(30),
            'end_date' => $filters['end_date'] ?? now(),
        ];
    }

    /**
     * Get track constraint statistics.
     *
     * @param array $dateRange
     * @return array
     */
    private function getTrackConstraintStats(array $dateRange): array
    {
        return [
            'total_tracks' => Track::count(),
            'tracks_at_capacity' => Track::whereRaw('
                (SELECT COUNT(*) FROM track_enrollments WHERE track_id = tracks.id) >=
                COALESCE(tracks.max_enrollments, ?)
            ', [self::DEFAULT_TRACK_CAPACITY])->count(),
            'average_utilization' => $this->calculateAverageTrackUtilization(),
        ];
    }

    /**
     * Get course constraint statistics.
     *
     * @param array $dateRange
     * @return array
     */
    private function getCourseConstraintStats(array $dateRange): array
    {
        return [
            'total_courses' => Course::count(),
            'courses_at_capacity' => Course::whereRaw('
                (SELECT COUNT(*) FROM course_enrollments WHERE course_id = courses.id) >=
                COALESCE(courses.max_enrollments, ?)
            ', [self::DEFAULT_COURSE_CAPACITY])->count(),
            'average_utilization' => $this->calculateAverageCourseUtilization(),
        ];
    }

    /**
     * Get user limit violation statistics.
     *
     * @param array $dateRange
     * @return array
     */
    private function getUserLimitViolationStats(array $dateRange): array
    {
        return [
            'users_at_track_limit' => User::whereRaw('
                (SELECT COUNT(*) FROM track_enrollments WHERE user_id = users.id AND completed_at IS NULL) >=
                COALESCE(users.max_track_enrollments, ?)
            ', [self::DEFAULT_USER_TRACK_LIMIT])->count(),
            'users_at_course_limit' => User::whereRaw('
                (SELECT COUNT(*) FROM course_enrollments WHERE user_id = users.id AND completed_at IS NULL) >=
                COALESCE(users.max_course_enrollments, ?)
            ', [self::DEFAULT_USER_COURSE_LIMIT])->count(),
        ];
    }

    /**
     * Get capacity utilization statistics.
     *
     * @return array
     */
    private function getCapacityUtilizationStats(): array
    {
        return [
            'track_utilization' => $this->calculateAverageTrackUtilization(),
            'course_utilization' => $this->calculateAverageCourseUtilization(),
        ];
    }

    /**
     * Get override statistics.
     *
     * @param array $dateRange
     * @return array
     */
    private function getOverrideStatistics(array $dateRange): array
    {
        // This would query an audit log table in a real implementation
        return [
            'total_overrides' => 0, // Placeholder
            'override_reasons' => [], // Placeholder
        ];
    }

    /**
     * Calculate average track utilization.
     *
     * @return float
     */
    private function calculateAverageTrackUtilization(): float
    {
        $tracks = Track::all();
        $totalUtilization = 0;

        foreach ($tracks as $track) {
            $maxCapacity = $track->max_enrollments ?? self::DEFAULT_TRACK_CAPACITY;
            $currentEnrollments = $this->getTrackEnrollmentCount($track);
            $utilization = ($currentEnrollments / $maxCapacity) * 100;
            $totalUtilization += $utilization;
        }

        return $tracks->count() > 0 ? round($totalUtilization / $tracks->count(), 2) : 0;
    }

    /**
     * Calculate average course utilization.
     *
     * @return float
     */
    private function calculateAverageCourseUtilization(): float
    {
        $courses = Course::all();
        $totalUtilization = 0;

        foreach ($courses as $course) {
            $maxCapacity = $course->max_enrollments ?? self::DEFAULT_COURSE_CAPACITY;
            $currentEnrollments = $this->getCourseEnrollmentCount($course);
            $utilization = ($currentEnrollments / $maxCapacity) * 100;
            $totalUtilization += $utilization;
        }

        return $courses->count() > 0 ? round($totalUtilization / $courses->count(), 2) : 0;
    }
}
