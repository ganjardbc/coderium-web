<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use App\Models\CourseEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseEnrollmentService
{
    protected ConstraintEnforcementService $constraintService;

    public function __construct(ConstraintEnforcementService $constraintService)
    {
        $this->constraintService = $constraintService;
    }

    /**
     * Enroll a user in a course.
     *
     * @param User $user
     * @param Course $course
     * @param bool $isAdminOverride
     * @return CourseEnrollment
     * @throws ValidationException
     */
    public function enrollUser(User $user, Course $course, bool $isAdminOverride = false): CourseEnrollment
    {
        // Enforce enrollment constraints
        $this->constraintService->enforceCourseEnrollmentConstraints($user, $course, $isAdminOverride);

        // Check if user is already enrolled
        $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existingEnrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'User is already enrolled in this course.',
            ]);
        }

        return DB::transaction(function () use ($user, $course) {
            $enrollment = CourseEnrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'enrolled_at' => now(),
                'progress_percentage' => 0.00,
            ]);

            // Check for achievements after enrollment
            $this->checkAchievementsAfterEnrollment($user);

            return $enrollment;
        });
    }

    /**
     * Check if a user has enrollment access to a course.
     *
     * @param User $user
     * @param Course $course
     * @return bool
     */
    public function checkEnrollmentAccess(User $user, Course $course): bool
    {
        // Check if user can access classroom
        if (!$user->canAccessClassroom()) {
            return false;
        }

        // Check if course is active
        if (!$course->is_active) {
            // Only instructors and admins can access inactive courses
            return $user->hasInstructorPermissions();
        }

        // Check if user is enrolled
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        return (bool) $enrollment;
    }

    /**
     * Get all enrolled courses for a user.
     *
     * @param User $user
     * @return Collection
     */
    public function getEnrolledCourses(User $user): Collection
    {
        return Course::whereHas('enrollments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->with(['modules.lessons'])
        ->orderBy('created_at', 'desc')
        ->get();
    }

    /**
     * Get enrollment details for a user and course.
     *
     * @param User $user
     * @param Course $course
     * @return CourseEnrollment|null
     */
    public function getEnrollment(User $user, Course $course): ?CourseEnrollment
    {
        return CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
    }

    /**
     * Unenroll a user from a course.
     *
     * @param User $user
     * @param Course $course
     * @return bool
     * @throws ValidationException
     */
    public function unenrollUser(User $user, Course $course): bool
    {
        $enrollment = $this->getEnrollment($user, $course);

        if (!$enrollment) {
            throw ValidationException::withMessages([
                'enrollment' => 'User is not enrolled in this course.',
            ]);
        }

        // Check if course allows unenrollment (business rule)
        if ($enrollment->progress_percentage > 50) {
            throw ValidationException::withMessages([
                'enrollment' => 'Cannot unenroll from course with more than 50% progress.',
            ]);
        }

        return DB::transaction(function () use ($enrollment) {
            return $enrollment->delete();
        });
    }

    /**
     * Get enrollment statistics for a course.
     *
     * @param Course $course
     * @return array
     */
    public function getEnrollmentStatistics(Course $course): array
    {
        $totalEnrollments = $course->enrollments()->count();
        $activeEnrollments = $course->enrollments()
            ->whereNull('completed_at')
            ->count();
        $completedEnrollments = $course->enrollments()
            ->whereNotNull('completed_at')
            ->count();

        $averageProgress = $course->enrollments()
            ->avg('progress_percentage') ?? 0;

        return [
            'total_enrollments' => $totalEnrollments,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'completion_rate' => $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 2) : 0,
            'average_progress' => round($averageProgress, 2),
        ];
    }

    /**
     * Bulk enroll users in courses with comprehensive validation.
     *
     * @param array $enrollments
     * @return array
     * @throws ValidationException
     */
    public function bulkEnrollUsers(array $enrollments): array
    {
        $results = [];
        $errors = [];

        // Validate all enrollments first
        foreach ($enrollments as $index => $enrollment) {
            try {
                $this->validateBulkEnrollmentData($enrollment, $index);
            } catch (ValidationException $e) {
                $errors[] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'bulk_enrollment' => 'Validation failed for bulk enrollment.',
                'enrollment_errors' => $errors,
            ]);
        }

        return DB::transaction(function () use ($enrollments, &$results) {
            foreach ($enrollments as $index => $enrollment) {
                try {
                    $user = User::findOrFail($enrollment['user_id']);
                    $course = Course::findOrFail($enrollment['course_id']);

                    $result = $this->enrollUser($user, $course);

                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'enrollment_id' => $result->id,
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'user_id' => $enrollment['user_id'] ?? null,
                        'course_id' => $enrollment['course_id'] ?? null,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Bulk course enrollment completed.',
                'results' => $results,
                'total_processed' => count($enrollments),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            ];
        });
    }

    /**
     * Bulk unenroll users from courses.
     *
     * @param array $unenrollments
     * @return array
     * @throws ValidationException
     */
    public function bulkUnenrollUsers(array $unenrollments): array
    {
        $results = [];
        $errors = [];

        // Validate all unenrollments first
        foreach ($unenrollments as $index => $unenrollment) {
            try {
                $this->validateBulkUnenrollmentData($unenrollment, $index);
            } catch (ValidationException $e) {
                $errors[] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'bulk_unenrollment' => 'Validation failed for bulk unenrollment.',
                'unenrollment_errors' => $errors,
            ]);
        }

        return DB::transaction(function () use ($unenrollments, &$results) {
            foreach ($unenrollments as $index => $unenrollment) {
                try {
                    $user = User::findOrFail($unenrollment['user_id']);
                    $course = Course::findOrFail($unenrollment['course_id']);

                    $success = $this->unenrollUser($user, $course);

                    $results[] = [
                        'index' => $index,
                        'success' => $success,
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'user_id' => $unenrollment['user_id'] ?? null,
                        'course_id' => $unenrollment['course_id'] ?? null,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Bulk course unenrollment completed.',
                'results' => $results,
                'total_processed' => count($unenrollments),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            ];
        });
    }

    /**
     * Check enrollment capacity for a course.
     *
     * @param Course $course
     * @throws ValidationException
     */
    private function checkEnrollmentCapacity(Course $course): void
    {
        // For now, we'll implement a simple capacity check
        // In a real application, this would be configurable per course

        $maxCapacity = 500; // Default capacity for courses
        $currentEnrollments = $course->enrollments()->count();

        if ($currentEnrollments >= $maxCapacity) {
            throw ValidationException::withMessages([
                'capacity' => 'Course has reached maximum enrollment capacity.',
            ]);
        }
    }

    /**
     * Validate bulk enrollment data structure.
     *
     * @param array $enrollment
     * @param int $index
     * @throws ValidationException
     */
    private function validateBulkEnrollmentData(array $enrollment, int $index): void
    {
        $requiredFields = ['user_id', 'course_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($enrollment[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw ValidationException::withMessages([
                "enrollment_{$index}" => "Missing required fields: " . implode(', ', $missingFields),
            ]);
        }

        // Validate that the entities exist
        if (!User::where('id', $enrollment['user_id'])->exists()) {
            throw ValidationException::withMessages([
                "enrollment_{$index}" => "User with ID {$enrollment['user_id']} does not exist.",
            ]);
        }

        if (!Course::where('id', $enrollment['course_id'])->exists()) {
            throw ValidationException::withMessages([
                "enrollment_{$index}" => "Course with ID {$enrollment['course_id']} does not exist.",
            ]);
        }
    }

    /**
     * Validate bulk unenrollment data structure.
     *
     * @param array $unenrollment
     * @param int $index
     * @throws ValidationException
     */
    private function validateBulkUnenrollmentData(array $unenrollment, int $index): void
    {
        $requiredFields = ['user_id', 'course_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($unenrollment[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw ValidationException::withMessages([
                "unenrollment_{$index}" => "Missing required fields: " . implode(', ', $missingFields),
            ]);
        }

        // Validate that the entities exist
        if (!User::where('id', $unenrollment['user_id'])->exists()) {
            throw ValidationException::withMessages([
                "unenrollment_{$index}" => "User with ID {$unenrollment['user_id']} does not exist.",
            ]);
        }

        if (!Course::where('id', $unenrollment['course_id'])->exists()) {
            throw ValidationException::withMessages([
                "unenrollment_{$index}" => "Course with ID {$unenrollment['course_id']} does not exist.",
            ]);
        }
    }

    /**
     * Check for achievements after enrollment.
     *
     * @param User $user
     */
    private function checkAchievementsAfterEnrollment(User $user): void
    {
        try {
            // Avoid circular dependency by resolving AchievementService here
            $achievementService = app(AchievementService::class);
            $achievementService->checkEnrollmentAchievements($user);
        } catch (\Exception $e) {
            // Log the error but don't fail the enrollment
            \Log::error('Failed to check achievements after enrollment', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
