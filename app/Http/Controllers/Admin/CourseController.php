<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\CertificateTemplate;
use App\Services\ModuleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function __construct(
        private ModuleAssignmentService $moduleAssignmentService
    ) {}

    /**
     * Display a listing of courses.
     */
    public function index()
    {
        $courses = Course::withCount(['modules', 'enrollments'])
            ->latest()
            ->paginate(5);

        return Inertia::render('admin/classroom/CourseIndex', [
            'courses' => $courses,
        ]);
    }

    /**
     * Show the form for creating a new course.
     */
    public function create()
    {
        $certificateTemplates = CertificateTemplate::orderBy('name')->get();

        return Inertia::render('admin/classroom/CourseEditor', [
            'certificateTemplates' => $certificateTemplates,
        ]);
    }

    /**
     * Store a newly created course in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'slug' => 'required|string|max:255|unique:courses,slug',
            'is_active' => 'boolean',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'estimated_duration' => 'nullable|integer|min:1',
        ]);

        // Ensure slug is properly formatted
        $validated['slug'] = Str::slug($validated['slug']);

        $course = Course::create($validated);

        return redirect()->route('admin.classroom.courses.index')
            ->with('success', 'Course created successfully.');
    }

    /**
     * Display the specified course.
     */
    public function show(Course $course)
    {
        $course->load(['modules' => function ($query) {
            $query->withPivot(['order', 'is_required'])
                  ->orderBy('order');
        }, 'certificateTemplate']);

        $course->loadCount(['enrollments', 'modules']);

        // Get available modules (not already assigned to this course)
        $assignedModuleIds = $course->modules->pluck('id')->toArray();
        $availableModules = Module::whereNotIn('id', $assignedModuleIds)
            ->where('is_published', true)
            ->with('level.track')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/courses/Show', [
            'course' => $course,
            'availableModules' => $availableModules,
        ]);
    }

    /**
     * Show the form for editing the specified course.
     */
    public function edit(Course $course)
    {
        $certificateTemplates = CertificateTemplate::orderBy('name')->get();

        return Inertia::render('admin/classroom/CourseEditor', [
            'course' => $course,
            'certificateTemplates' => $certificateTemplates,
        ]);
    }

    /**
     * Update the specified course in storage.
     */
    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'slug' => 'required|string|max:255|unique:courses,slug,' . $course->id,
            'is_active' => 'boolean',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'estimated_duration' => 'nullable|integer|min:1',
        ]);

        // Ensure slug is properly formatted
        $validated['slug'] = Str::slug($validated['slug']);

        $course->update($validated);

        return redirect()->route('admin.classroom.courses.index')
            ->with('success', 'Course updated successfully.');
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy(Course $course)
    {
        // Check if course has enrollments
        if ($course->enrollments()->count() > 0) {
            return redirect()->route('admin.classroom.courses.index')
                ->with('error', 'Cannot delete course with existing enrollments.');
        }

        $course->delete();

        return redirect()->route('admin.classroom.courses.index')
            ->with('success', 'Course deleted successfully.');
    }

    /**
     * Show the module assignment interface for a course.
     */
    public function moduleAssignment(Course $course)
    {
        $course->load(['modules' => function ($query) {
            $query->withPivot(['order', 'is_required'])
                  ->orderBy('order');
        }]);

        // Get available modules (not already assigned to this course)
        $assignedModuleIds = $course->modules->pluck('id')->toArray();
        $availableModules = Module::whereNotIn('id', $assignedModuleIds)
            ->where('is_published', true)
            ->with('level.track')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/classroom/CourseModuleAssignment', [
            'course' => $course,
            'availableModules' => $availableModules,
        ]);
    }

    /**
     * Assign a module to the course.
     */
    public function assignModule(Request $request, Course $course)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'order' => 'nullable|integer|min:0',
            'is_required' => 'boolean',
        ]);

        try {
            $module = Module::findOrFail($validated['module_id']);
            $options = [
                'order' => $validated['order'] ?? null,
                'is_required' => $validated['is_required'] ?? true,
            ];

            $result = $this->moduleAssignmentService->assignModuleToCourse($module, $course, $options);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove a module from the course.
     */
    public function removeModule(Request $request, Course $course)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
        ]);

        try {
            $module = Module::findOrFail($validated['module_id']);
            $result = $this->moduleAssignmentService->removeModuleFromCourse($module, $course);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update module order within the course.
     */
    public function updateModuleOrder(Request $request, Course $course)
    {
        $validated = $request->validate([
            'module_orders' => 'required|array',
            'module_orders.*' => 'integer|min:0',
        ]);

        try {
            $result = $this->moduleAssignmentService->updateCourseModuleOrder($course, $validated['module_orders']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk assign modules to the course.
     */
    public function bulkAssignModules(Request $request, Course $course)
    {
        $validated = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'exists:modules,id',
            'default_required' => 'boolean',
        ]);

        try {
            $assignments = [];
            foreach ($validated['module_ids'] as $index => $moduleId) {
                $assignments[] = [
                    'module_id' => $moduleId,
                    'type' => 'course',
                    'target_id' => $course->id,
                    'options' => [
                        'order' => $index + 1,
                        'is_required' => $validated['default_required'] ?? true,
                    ],
                ];
            }

            $result = $this->moduleAssignmentService->bulkAssignModules($assignments);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'results' => $result['results'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
