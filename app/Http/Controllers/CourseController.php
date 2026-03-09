<?php

namespace App\Http\Controllers;

use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Assessment;
use App\Services\TrackService;
use App\Services\ContentService;
use App\Services\ProgressService;
use App\Services\EnrollmentService;
use App\Services\AssessmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(
        private TrackService $trackService,
        private ContentService $contentService,
        private ProgressService $progressService,
        private EnrollmentService $enrollmentService,
        private AssessmentService $assessmentService
    ) {
    }

    /**
     * Display a listing of courses.
     */
    public function index(Request $request)
    {
        $query = Course::query()
            ->where('is_active', true)
            ->with(['certificateTemplate:id,name,description'])
            ->withCount(['modules', 'enrollments']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Certificate filter
        if ($request->filled('certificate') && $request->get('certificate') !== 'all') {
            if ($request->get('certificate') === 'with_certificate') {
                $query->whereNotNull('certificate_template_id');
            }
        }

        // Sort functionality
        $sortBy = $request->get('sort', 'recent');
        switch ($sortBy) {
            case 'popular':
                $query->orderBy('enrollments_count', 'desc');
                break;
            case 'duration_asc':
                $query->orderBy('estimated_duration', 'asc');
                break;
            case 'duration_desc':
                $query->orderBy('estimated_duration', 'desc');
                break;
            case 'alphabetical':
                $query->orderBy('title', 'asc');
                break;
            case 'recent':
            default:
                $query->latest();
                break;
        }

        $courses = $query->paginate(12);

        // Get counts for filters
        $allCoursesQuery = Course::where('is_active', true);
        $counts = [
            'all' => $allCoursesQuery->count(),
            'with_certificate' => $allCoursesQuery->whereNotNull('certificate_template_id')->count(),
            'beginner' => 0, // Courses don't have difficulty levels
            'intermediate' => 0,
            'advanced' => 0,
        ];

        return Inertia::render('courses/CoursesList', [
            'courses' => CourseResource::collection($courses),
            'counts' => $counts,
            'filters' => [
                'search' => $request->get('search', ''),
                'certificate' => $request->get('certificate', 'all'),
                'difficulty' => $request->get('difficulty', 'all'),
                'sortBy' => $sortBy,
            ],
        ]);
    }

    /**
     * Display the specified course.
     */
    public function show(Course $course)
    {
        // Only show active courses to public
        if (!$course->is_active) {
            abort(404);
        }

        $course->load([
            'modules' => function ($query) {
                $query->orderBy('course_modules.order')
                    ->with([
                        'lessons' => function ($q) {
                            $q->orderBy('order_index');
                        },
                        'assessments'
                    ]);
            },
            'certificateTemplate:id,name,description',
        ]);

        // Check if user is enrolled (if authenticated)
        $enrollment = null;
        if (auth()->check()) {
            $enrollment = $course->enrollments()
                ->where('user_id', auth()->id())
                ->first();
        }

        // Get related courses (same category or similar)
        $relatedCourses = Course::where('is_active', true)
            ->where('id', '!=', $course->id)
            ->with(['certificateTemplate:id,name,description'])
            ->withCount(['modules', 'enrollments'])
            ->limit(4)
            ->get();

        // Generate breadcrumbs
        $breadcrumbs = [
            ['title' => 'Home', 'url' => route('home')],
            ['title' => 'Courses', 'url' => route('courses.index')],
            ['title' => $course->title, 'url' => null],
        ];

        return Inertia::render('courses/CoursesDetail', [
            'course' => new CourseResource($course),
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'enrolled_at' => $enrollment->enrolled_at,
                'completed_at' => $enrollment->completed_at,
                'progress_percentage' => $enrollment->progress_percentage ?? 0,
            ] : null,
            'relatedCourses' => CourseResource::collection($relatedCourses),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display course-specific module view page.
     */
    public function courseModuleView(Request $request, string $courseSlug, int $moduleId): Response
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $module = Module::findOrFail($moduleId);

        // Check if user is enrolled in the course
        if ($request->user()) {
            $enrollment = $course->enrollments()->where('user_id', $request->user()->id)->first();
            \Log::info('Enrollment check', [
                'user_id' => $request->user()->id,
                'enrollment_exists' => $enrollment !== null,
                'enrollment_id' => $enrollment?->id
            ]);

            if (!$enrollment) {
                \Log::warning('User not enrolled in course', [
                    'user_id' => $request->user()->id,
                    'course_id' => $course->id
                ]);

                // Redirect to course page with enrollment message
                return redirect()->route('courses.show', $course->slug)
                    ->with('error', 'You must enroll in this course to access its content.');
            }
        } else {
            \Log::warning('User not authenticated');
            abort(401, 'Authentication required.');
        }

        // Check if module is part of this course
        $courseModule = $course->modules()->where('modules.id', $module->id)->first();
        \Log::info('Course module check', [
            'course_module_exists' => $courseModule !== null,
            'course_module_id' => $courseModule?->id
        ]);

        if (!$courseModule) {
            \Log::warning('Module not found in course', [
                'course_id' => $course->id,
                'module_id' => $module->id
            ]);
            abort(404, 'Module not found in this course.');
        }

        $module->load([
            'lessons' => function ($lessonQuery) use ($request) {
                if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                    $lessonQuery->where('is_published', true);
                }
                $lessonQuery->orderBy('order_index');
            },
            'assessments',
            'assignments'
        ]);

        $moduleData = [
            'id' => $module->id,
            'title' => $module->title,
            'description' => $module->description,
            'order_index' => $courseModule->pivot->order,
            'is_required' => $courseModule->pivot->is_required,
            'estimated_duration' => $module->estimated_duration,
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'lessons' => $module->lessons->map(function ($lesson) use ($request) {
                $lessonData = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'order_index' => $lesson->order_index,
                    'estimated_duration' => $lesson->estimated_duration,
                    'lesson_type' => $lesson->lesson_type,
                ];

                // Add progress data for authenticated users
                if ($request->user()) {
                    $progress = $this->progressService->getLessonProgress($request->user(), $lesson);
                    $lessonData['is_completed'] = $progress ? $progress->isCompleted() : false;
                }

                return $lessonData;
            }),
            'assessments' => $module->assessments->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'is_required' => $assessment->is_required,
                    'passing_score' => $assessment->passing_score,
                ];
            }),
            'assignments' => $module->assignments->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'due_date' => $assignment->due_date,
                ];
            }),
        ];

        // Add progress data for authenticated users
        if ($request->user()) {
            $moduleData['progress_percentage'] = $this->progressService->calculateModuleProgress($request->user(), $module);
        }

        // Generate breadcrumbs
        $breadcrumbs = [
            ['title' => 'Home', 'url' => route('home')],
            ['title' => 'Courses', 'url' => route('courses.index')],
            ['title' => $course->title, 'url' => route('courses.show', $course->slug)],
            ['title' => $module->title, 'url' => null],
        ];

        return Inertia::render('courses/CoursesModule', [
            'module' => $moduleData,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display course-specific lesson view page.
     */
    public function courseLessonView(Request $request, string $courseSlug, int $moduleId, int $lessonId): Response
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $module = Module::findOrFail($moduleId);
        $lesson = Lesson::findOrFail($lessonId);

        // Check if user is enrolled in the course
        if ($request->user()) {
            $enrollment = $course->enrollments()->where('user_id', $request->user()->id)->first();
            if (!$enrollment) {
                return redirect()->route('courses.show', $course->slug)
                    ->with('error', 'You must enroll in this course to access its content.');
            }
        } else {
            abort(401, 'Authentication required.');
        }

        // Check if module is part of this course
        $courseModule = $course->modules()->where('modules.id', $module->id)->first();
        if (!$courseModule) {
            abort(404, 'Module not found in this course.');
        }

        // Check if lesson belongs to this module
        if ($lesson->module_id !== $module->id) {
            abort(404, 'Lesson not found in this module.');
        }

        // Check if lesson is published (unless user has instructor permissions)
        if (!$lesson->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404, 'Lesson not available.');
        }

        $lesson->load(['media']);

        // Load module with all lessons for sidebar navigation
        $module->load([
            'lessons' => function ($lessonQuery) use ($request) {
                if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                    $lessonQuery->where('is_published', true);
                }
                $lessonQuery->orderBy('order_index');
            }
        ]);

        $lessonData = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'content' => $lesson->content,
            'lesson_type' => $lesson->lesson_type,
            'order_index' => $lesson->order_index,
            'estimated_duration' => $lesson->estimated_duration,
            'is_published' => $lesson->is_published,
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => $module->lessons->map(function ($moduleLesson) use ($request) {
                    $lessonData = [
                        'id' => $moduleLesson->id,
                        'title' => $moduleLesson->title,
                        'order_index' => $moduleLesson->order_index,
                        'estimated_duration' => $moduleLesson->estimated_duration,
                        'lesson_type' => $moduleLesson->lesson_type,
                    ];

                    // Add progress data for authenticated users
                    if ($request->user()) {
                        $progress = $this->progressService->getLessonProgress($request->user(), $moduleLesson);
                        $lessonData['is_completed'] = $progress ? $progress->isCompleted() : false;
                    }

                    return $lessonData;
                }),
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'media' => $lesson->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'url' => $media->getUrl(),
                ];
            }),
        ];

        // Add progress data for authenticated users
        if ($request->user()) {
            $progress = $this->progressService->getLessonProgress($request->user(), $lesson);
            $lessonData['is_completed'] = $progress ? $progress->isCompleted() : false;
        }

        // Generate breadcrumbs
        $breadcrumbs = [
            ['title' => 'Home', 'url' => route('home')],
            ['title' => 'Courses', 'url' => route('courses.index')],
            ['title' => $course->title, 'url' => route('courses.show', $course->slug)],
            ['title' => $module->title, 'url' => route('courses.modules.show', [$course->slug, $module->id])],
            ['title' => $lesson->title, 'url' => null],
        ];

        return Inertia::render('courses/CoursesLesson', [
            'lesson' => $lessonData,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display course-specific assessment view page.
     */
    public function courseAssessmentView(Request $request, string $courseSlug, int $moduleId, int $assessmentId): Response
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $module = Module::findOrFail($moduleId);
        $assessment = Assessment::findOrFail($assessmentId);

        // Check if user is enrolled in the course
        if ($request->user()) {
            $enrollment = $course->enrollments()->where('user_id', $request->user()->id)->first();
            if (!$enrollment) {
                return redirect()->route('courses.show', $course->slug)
                    ->with('error', 'You must enroll in this course to access its content.');
            }
        } else {
            abort(401, 'Authentication required.');
        }

        // Check if module is part of this course
        $courseModule = $course->modules()->where('modules.id', $module->id)->first();
        if (!$courseModule) {
            abort(404, 'Module not found in this course.');
        }

        // Check if assessment belongs to this module
        if ($assessment->assessable_type !== 'App\\Models\\Module' || $assessment->assessable_id !== $module->id) {
            abort(404, 'Assessment not found in this module.');
        }

        $assessment->load(['questions.options']);

        $assessmentData = [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'instructions' => $assessment->instructions,
            'time_limit' => $assessment->time_limit,
            'passing_score' => $assessment->passing_score,
            'max_attempts' => $assessment->max_attempts,
            'is_required' => $assessment->is_required,
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'questions' => $assessment->questions->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'points' => $question->points,
                    'order_index' => $question->order_index,
                    'options' => $question->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'option_text' => $option->option_text,
                            'order_index' => $option->order_index,
                            // Don't expose is_correct for security
                        ];
                    }),
                ];
            }),
        ];

        // Add attempt data for authenticated users
        if ($request->user()) {
            $attempts = $this->assessmentService->getUserAttempts($request->user(), $assessment);
            $assessmentData['attempts'] = $attempts->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'score' => $attempt->score,
                    'passed' => $attempt->passed,
                    'completed_at' => $attempt->completed_at,
                ];
            });
            $assessmentData['can_attempt'] = $this->assessmentService->canUserAttempt($request->user(), $assessment);
        }

        // Generate breadcrumbs
        $breadcrumbs = [
            ['title' => 'Home', 'url' => route('home')],
            ['title' => 'Courses', 'url' => route('courses.index')],
            ['title' => $course->title, 'url' => route('courses.show', $course->slug)],
            ['title' => $module->title, 'url' => route('courses.modules.show', [$course->slug, $module->id])],
            ['title' => $assessment->title, 'url' => null],
        ];

        return Inertia::render('courses/CoursesAssessment', [
            'assessment' => $assessmentData,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
