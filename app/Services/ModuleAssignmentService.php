<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Level;
use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModuleAssignmentService
{
    /**
     * Assign a module to a level with duplicate prevention.
     *
     * @param Module $module
     * @param Level $level
     * @param array $options
     * @return array
     * @throws ValidationException
     */
    public function assignModuleToLevel(Module $module, Level $level, array $options = []): array
    {
        // Validate assignment constraints
        $this->validateLevelAssignment($module, $level);

        // Check for duplicate assignment
        if ($this->isDuplicateLevelAssignment($module, $level)) {
            throw ValidationException::withMessages([
                'assignment' => "Module '{$module->title}' is already assigned to level '{$level->title}'.",
            ]);
        }

        $assignmentData = [
            'order' => $options['order'] ?? $this->getNextLevelOrder($level),
            'is_required' => $options['is_required'] ?? true,
        ];

        return DB::transaction(function () use ($module, $level, $assignmentData) {
            // Create the assignment relationship using the new flexible system
            $level->assignedModules()->attach($module->id, $assignmentData);

            return [
                'success' => true,
                'message' => "Module '{$module->title}' successfully assigned to level '{$level->title}'.",
                'assignment_data' => $assignmentData,
            ];
        });
    }

    /**
     * Assign a module to a course with validation.
     *
     * @param Module $module
     * @param Course $course
     * @param array $options
     * @return array
     * @throws ValidationException
     */
    public function assignModuleToCourse(Module $module, Course $course, array $options = []): array
    {
        // Validate assignment constraints
        $this->validateCourseAssignment($module, $course);

        // Check for duplicate assignment
        if ($this->isDuplicateCourseAssignment($module, $course)) {
            throw ValidationException::withMessages([
                'assignment' => "Module '{$module->title}' is already assigned to course '{$course->title}'.",
            ]);
        }

        $assignmentData = [
            'order' => $options['order'] ?? $this->getNextCourseOrder($course),
            'is_required' => $options['is_required'] ?? true,
        ];

        return DB::transaction(function () use ($module, $course, $assignmentData) {
            // Create the assignment relationship
            $course->modules()->attach($module->id, $assignmentData);

            return [
                'success' => true,
                'message' => "Module '{$module->title}' successfully assigned to course '{$course->title}'.",
                'assignment_data' => $assignmentData,
            ];
        });
    }

    /**
     * Remove a module assignment from a level.
     *
     * @param Module $module
     * @param Level $level
     * @return array
     * @throws ValidationException
     */
    public function removeModuleFromLevel(Module $module, Level $level): array
    {
        if (!$this->isDuplicateLevelAssignment($module, $level)) {
            throw ValidationException::withMessages([
                'assignment' => "Module '{$module->title}' is not assigned to level '{$level->title}'.",
            ]);
        }

        return DB::transaction(function () use ($module, $level) {
            $level->modules()->detach($module->id);

            return [
                'success' => true,
                'message' => "Module '{$module->title}' successfully removed from level '{$level->title}'.",
            ];
        });
    }

    /**
     * Remove a module assignment from a course.
     *
     * @param Module $module
     * @param Course $course
     * @return array
     * @throws ValidationException
     */
    public function removeModuleFromCourse(Module $module, Course $course): array
    {
        if (!$this->isDuplicateCourseAssignment($module, $course)) {
            throw ValidationException::withMessages([
                'assignment' => "Module '{$module->title}' is not assigned to course '{$course->title}'.",
            ]);
        }

        return DB::transaction(function () use ($module, $course) {
            $course->modules()->detach($module->id);

            return [
                'success' => true,
                'message' => "Module '{$module->title}' successfully removed from course '{$course->title}'.",
            ];
        });
    }

    /**
     * Perform bulk assignment operations with comprehensive validation.
     *
     * @param array $assignments
     * @return array
     * @throws ValidationException
     */
    public function bulkAssignModules(array $assignments): array
    {
        $results = [];
        $errors = [];

        // Validate all assignments first
        foreach ($assignments as $index => $assignment) {
            try {
                $this->validateBulkAssignmentData($assignment, $index);
            } catch (ValidationException $e) {
                $errors[] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'bulk_assignment' => 'Validation failed for bulk assignment.',
                'assignment_errors' => $errors,
            ]);
        }

        return DB::transaction(function () use ($assignments, &$results) {
            foreach ($assignments as $index => $assignment) {
                try {
                    $module = Module::findOrFail($assignment['module_id']);
                    $options = $assignment['options'] ?? [];

                    if ($assignment['type'] === 'level') {
                        $level = Level::findOrFail($assignment['target_id']);
                        $result = $this->assignModuleToLevel($module, $level, $options);
                    } else {
                        $course = Course::findOrFail($assignment['target_id']);
                        $result = $this->assignModuleToCourse($module, $course, $options);
                    }

                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'result' => $result,
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Bulk assignment completed.',
                'results' => $results,
                'total_processed' => count($assignments),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            ];
        });
    }

    /**
     * Get all modules assigned to a level.
     *
     * @param Level $level
     * @return Collection
     */
    public function getLevelModules(Level $level): Collection
    {
        return $level->modules()
            ->withPivot(['order', 'is_required', 'created_at'])
            ->orderBy('order')
            ->get();
    }

    /**
     * Get all modules assigned to a course.
     *
     * @param Course $course
     * @return Collection
     */
    public function getCourseModules(Course $course): Collection
    {
        return $course->modules()
            ->withPivot(['order', 'is_required', 'created_at'])
            ->orderBy('order')
            ->get();
    }

    /**
     * Update module assignment order within a level.
     *
     * @param Level $level
     * @param array $moduleOrders
     * @return array
     */
    public function updateLevelModuleOrder(Level $level, array $moduleOrders): array
    {
        return DB::transaction(function () use ($level, $moduleOrders) {
            foreach ($moduleOrders as $moduleId => $order) {
                $level->modules()->updateExistingPivot($moduleId, ['order' => $order]);
            }

            return [
                'success' => true,
                'message' => 'Module order updated successfully.',
            ];
        });
    }

    /**
     * Update module assignment order within a course.
     *
     * @param Course $course
     * @param array $moduleOrders
     * @return array
     */
    public function updateCourseModuleOrder(Course $course, array $moduleOrders): array
    {
        return DB::transaction(function () use ($course, $moduleOrders) {
            foreach ($moduleOrders as $moduleId => $order) {
                $course->modules()->updateExistingPivot($moduleId, ['order' => $order]);
            }

            return [
                'success' => true,
                'message' => 'Module order updated successfully.',
            ];
        });
    }

    /**
     * Validate assignment constraints for level assignment.
     *
     * @param Module $module
     * @param Level $level
     * @throws ValidationException
     */
    private function validateLevelAssignment(Module $module, Level $level): void
    {
        if (!$module->is_published) {
            throw ValidationException::withMessages([
                'module' => 'Cannot assign unpublished module to level.',
            ]);
        }

        if (!$level->is_published) {
            throw ValidationException::withMessages([
                'level' => 'Cannot assign module to unpublished level.',
            ]);
        }
    }

    /**
     * Validate assignment constraints for course assignment.
     *
     * @param Module $module
     * @param Course $course
     * @throws ValidationException
     */
    private function validateCourseAssignment(Module $module, Course $course): void
    {
        if (!$module->is_published) {
            throw ValidationException::withMessages([
                'module' => 'Cannot assign unpublished module to course.',
            ]);
        }

        if (!$course->is_active) {
            throw ValidationException::withMessages([
                'course' => 'Cannot assign module to inactive course.',
            ]);
        }
    }

    /**
     * Check if module is already assigned to level.
     *
     * @param Module $module
     * @param Level $level
     * @return bool
     */
    private function isDuplicateLevelAssignment(Module $module, Level $level): bool
    {
        // Check both old direct assignment and new flexible assignment
        return $level->modules()->where('modules.id', $module->id)->exists() ||
               $level->assignedModules()->where('modules.id', $module->id)->exists();
    }

    /**
     * Check if module is already assigned to course.
     *
     * @param Module $module
     * @param Course $course
     * @return bool
     */
    private function isDuplicateCourseAssignment(Module $module, Course $course): bool
    {
        return $course->modules()->where('modules.id', $module->id)->exists();
    }

    /**
     * Get the next order number for a level's modules.
     *
     * @param Level $level
     * @return int
     */
    private function getNextLevelOrder(Level $level): int
    {
        $maxOrder = $level->modules()->max('level_modules.order') ?? 0;
        return $maxOrder + 1;
    }

    /**
     * Get the next order number for a course's modules.
     *
     * @param Course $course
     * @return int
     */
    private function getNextCourseOrder(Course $course): int
    {
        $maxOrder = $course->modules()->max('course_modules.order') ?? 0;
        return $maxOrder + 1;
    }

    /**
     * Validate bulk assignment data structure.
     *
     * @param array $assignment
     * @param int $index
     * @throws ValidationException
     */
    private function validateBulkAssignmentData(array $assignment, int $index): void
    {
        $requiredFields = ['module_id', 'type', 'target_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($assignment[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw ValidationException::withMessages([
                "assignment_{$index}" => "Missing required fields: " . implode(', ', $missingFields),
            ]);
        }

        if (!in_array($assignment['type'], ['level', 'course'])) {
            throw ValidationException::withMessages([
                "assignment_{$index}" => "Invalid assignment type. Must be 'level' or 'course'.",
            ]);
        }

        // Validate that the entities exist
        if (!Module::where('id', $assignment['module_id'])->exists()) {
            throw ValidationException::withMessages([
                "assignment_{$index}" => "Module with ID {$assignment['module_id']} does not exist.",
            ]);
        }

        if ($assignment['type'] === 'level') {
            if (!Level::where('id', $assignment['target_id'])->exists()) {
                throw ValidationException::withMessages([
                    "assignment_{$index}" => "Level with ID {$assignment['target_id']} does not exist.",
                ]);
            }
        } else {
            if (!Course::where('id', $assignment['target_id'])->exists()) {
                throw ValidationException::withMessages([
                    "assignment_{$index}" => "Course with ID {$assignment['target_id']} does not exist.",
                ]);
            }
        }
    }
}
