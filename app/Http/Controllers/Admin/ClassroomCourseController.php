<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Assessment;
use App\Models\CertificateTemplate;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClassroomCourseController extends Controller
{
    /**
     * Display a listing of courses with classroom stats.
     */
    public function index(Request $request)
    {
        $query = Course::withCount(['modules', 'enrollments']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate(5);

        // Get classroom statistics
        $stats = [
            'courses' => Course::count(),
            'modules' => Module::count(),
            'lessons' => Lesson::count(),
            'assessments' => Assessment::count(),
            'enrollments' => CourseEnrollment::count(),
            'certificates' => \App\Models\Certificate::count(),
        ];

        return Inertia::render('admin/classroom/Index', [
            'courses' => CourseResource::collection($courses)->additional([
                'links' => $courses->links(),
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
                'from' => $courses->firstItem(),
                'to' => $courses->lastItem(),
            ]),
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new course.
     */
    public function create()
    {
        $certificateTemplates = CertificateTemplate::orderBy('name')->get();

        return Inertia::render('admin/classroom/courses/Create', [
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

        return redirect()->route('admin.classroom.courses.show', $course)
            ->with('success', 'Course created successfully.');
    }

    /**
     * Display the specified course.
     */
    public function show(Course $course)
    {
        $course->load([
            'modules' => function ($query) {
                $query->withPivot(['order', 'is_required'])
                      ->withCount('lessons')
                      ->orderBy('order');
            },
            'certificateTemplate'
        ]);

        $course->loadCount(['enrollments', 'modules']);

        return Inertia::render('admin/classroom/courses/Show', [
            'course' => new CourseResource($course),
        ]);
    }

    /**
     * Show the form for editing the specified course.
     */
    public function edit(Course $course)
    {
        $certificateTemplates = CertificateTemplate::orderBy('name')->get();

        return Inertia::render('admin/classroom/courses/Edit', [
            'course' => new CourseResource($course),
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

        return redirect()->route('admin.classroom.courses.show', $course)
            ->with('success', 'Course updated successfully.');
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy(Course $course)
    {
        // Check if course has enrollments
        if ($course->enrollments()->count() > 0) {
            return redirect()->route('admin.classroom.index')
                ->with('error', 'Cannot delete course with existing enrollments.');
        }

        $course->delete();

        return redirect()->route('admin.classroom.index')
            ->with('success', 'Course deleted successfully.');
    }

    /**
     * Show the module management interface for a course.
     */
    public function modules(Course $course)
    {
        $course->load([
            'modules' => function ($query) {
                $query->withPivot(['order', 'is_required'])
                      ->withCount('lessons')
                      ->orderBy('order');
            }
        ]);

        // Get available modules (not already assigned to this course)
        $assignedModuleIds = $course->modules->pluck('id')->toArray();
        $availableModules = Module::whereNotIn('id', $assignedModuleIds)
            ->where('is_published', true)
            ->with(['level.track'])
            ->withCount('lessons')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/classroom/courses/Modules', [
            'course' => new CourseResource($course),
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
            'order' => 'nullable|integer|min:1',
            'is_required' => 'boolean',
        ]);

        $module = Module::findOrFail($validated['module_id']);

        // Check if module is already assigned
        if ($course->modules()->where('module_id', $module->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Module is already assigned to this course.',
            ], 422);
        }

        // If no order specified, add to the end
        if (!isset($validated['order'])) {
            $maxOrder = $course->modules()->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        } else {
            // Shift existing modules if necessary
            $course->modules()
                ->where('order', '>=', $validated['order'])
                ->increment('order');
        }

        $course->modules()->attach($module->id, [
            'order' => $validated['order'],
            'is_required' => $validated['is_required'] ?? true,
        ]);

        return redirect()->route('admin.classroom.courses.modules', $course)
            ->with('success', 'Module assigned to course successfully.');
    }

    /**
     * Remove a module from the course.
     */
    public function removeModule(Course $course, Module $module)
    {
        if (!$course->modules()->where('module_id', $module->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Module is not assigned to this course.',
            ], 422);
        }

        // Get the order of the module being removed
        $moduleOrder = $course->modules()->where('module_id', $module->id)->first()->pivot->order;

        // Remove the module
        $course->modules()->detach($module->id);

        // Shift remaining modules down
        $course->modules()
            ->where('order', '>', $moduleOrder)
            ->decrement('order');

        return redirect()->route('admin.classroom.courses.modules', $course)
            ->with('success', 'Module removed from course successfully.');
    }

    /**
     * Update module order within the course.
     */
    public function updateModuleOrder(Request $request, Course $course, Module $module)
    {
        $validated = $request->validate([
            'order' => 'required|integer|min:1',
        ]);

        if (!$course->modules()->where('module_id', $module->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Module is not assigned to this course.',
            ], 422);
        }

        $currentOrder = $course->modules()->where('module_id', $module->id)->first()->pivot->order;
        $newOrder = $validated['order'];

        if ($currentOrder === $newOrder) {
            return response()->json([
                'success' => true,
                'message' => 'Module order unchanged.',
            ]);
        }

        DB::transaction(function () use ($course, $module, $currentOrder, $newOrder) {
            if ($newOrder > $currentOrder) {
                // Moving down: shift modules up
                $course->modules()
                    ->wherePivot('order', '>', $currentOrder)
                    ->wherePivot('order', '<=', $newOrder)
                    ->decrement('order');
            } else {
                // Moving up: shift modules down
                $course->modules()
                    ->wherePivot('order', '>=', $newOrder)
                    ->wherePivot('order', '<', $currentOrder)
                    ->increment('order');
            }

            // Update the module's order
            $course->modules()->updateExistingPivot($module->id, ['order' => $newOrder]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Module order updated successfully.',
        ]);
    }

    /**
     * Update module required status within the course.
     */
    public function updateModuleRequired(Request $request, Course $course, Module $module)
    {
        $validated = $request->validate([
            'is_required' => 'required|boolean',
        ]);

        if (!$course->modules()->where('module_id', $module->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Module is not assigned to this course.',
            ], 422);
        }

        $course->modules()->updateExistingPivot($module->id, [
            'is_required' => $validated['is_required'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module requirement status updated successfully.',
        ]);
    }
}
