<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Module;
use App\Services\ModuleAssignmentService;
use App\Services\ProgressTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function __construct(
        private ModuleAssignmentService $moduleAssignmentService,
        private ProgressTrackingService $progressTrackingService
    ) {}

    /**
     * Display a listing of courses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Course::query();

        // For non-instructors, only show active courses
        if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
            $query->where('is_active', true);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Load relationships
        $query->with(['modules', 'certificateTemplate'])
              ->withCount(['enrollments', 'modules']);

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        if (in_array($sortBy, ['created_at', 'title', 'estimated_duration'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $courses = $query->paginate($request->get('per_page', 12));

        return CourseResource::collection($courses);
    }

    /**
     * Store a newly created course.
     */
    public function store(Request $request): CourseResource
    {
        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to create courses.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'slug' => 'nullable|string|max:255|unique:courses,slug',
            'is_active' => 'boolean',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'estimated_duration' => 'nullable|integer|min:1',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        // Ensure slug is unique
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Course::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        try {
            $course = Course::create($validated);
            return new CourseResource($course->load(['modules', 'certificateTemplate']));
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Display the specified course.
     */
    public function show(Request $request, string $slug): CourseResource
    {
        $course = Course::where('slug', $slug)
            ->with(['modules' => function ($query) {
                $query->withPivot(['order', 'is_required'])
                      ->orderBy('order')
                      ->with('lessons');
            }, 'certificateTemplate'])
            ->withCount(['enrollments', 'modules'])
            ->firstOrFail();

        // Check access permissions
        if (!$course->is_active && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        // If user is authenticated, get progress data
        if ($request->user()) {
            $enrollment = $course->enrollments()->where('user_id', $request->user()->id)->first();
            $course->enrollment = $enrollment;

            if ($enrollment) {
                $progress = $this->progressTrackingService->calculateAggregateProgress($request->user(), $course);
                $course->progress = $progress;
            }
        }

        return new CourseResource($course);
    }

    /**
     * Update the specified course.
     */
    public function update(Request $request, string $slug): CourseResource
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to update this course.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'slug' => 'nullable|string|max:255|unique:courses,slug,' . $course->id,
            'is_active' => 'boolean',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'estimated_duration' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        try {
            $course->update($validated);
            return new CourseResource($course->fresh()->load(['modules', 'certificateTemplate']));
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Remove the specified course.
     */
    public function destroy(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to delete this course.');
        }

        // Check if course has enrollments
        if ($course->enrollments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete course with existing enrollments.',
            ], 422);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ], 200);
    }

    /**
     * Get modules for a course.
     */
    public function modules(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // Check access permissions
        if (!$course->is_active && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        $modules = $this->moduleAssignmentService->getCourseModules($course);

        // Add progress data for authenticated users
        if ($request->user()) {
            $modules->each(function ($module) use ($request) {
                $progress = $this->progressTrackingService->calculateAggregateProgress($request->user(), $module);
                $module->progress = $progress;
            });
        }

        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'modules' => $modules,
        ], 200);
    }

    /**
     * Assign a module to the course.
     */
    public function assignModule(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to manage course modules.');
        }

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
                'assignment_data' => $result['assignment_data'],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Module assignment failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove a module from the course.
     */
    public function removeModule(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to manage course modules.');
        }

        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
        ]);

        try {
            $module = Module::findOrFail($validated['module_id']);
            $result = $this->moduleAssignmentService->removeModuleFromCourse($module, $course);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Module removal failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Update module order within the course.
     */
    public function updateModuleOrder(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // Authorization check
        if (!$request->user()->canManageClassroomContent()) {
            abort(403, 'Unauthorized to manage course modules.');
        }

        $validated = $request->validate([
            'module_orders' => 'required|array',
            'module_orders.*' => 'integer|min:0',
        ]);

        try {
            $result = $this->moduleAssignmentService->updateCourseModuleOrder($course, $validated['module_orders']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
