<?php

namespace App\Http\Controllers;

use App\Models\Track;
use App\Models\Level;
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

class ClassroomController extends Controller
{
    public function __construct(
        private TrackService $trackService,
        private ContentService $contentService,
        private ProgressService $progressService,
        private EnrollmentService $enrollmentService,
        private AssessmentService $assessmentService
    ) {}

    /**
     * Display tracks listing page.
     */
    public function tracks(Request $request): Response
    {
        $query = Track::query();

        // For non-instructors, only show published tracks
        if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
            $query->where('is_published', true);
        }

        // Apply filters
        if ($request->has('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        if ($request->has('is_premium')) {
            $query->where('is_premium', $request->boolean('is_premium'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Load relationships and counts
        $query->with(['instructor:id,name'])
              ->withCount(['enrollments', 'levels']);

        // Add enrollment data for authenticated users
        if ($request->user()) {
            $query->with(['enrollments' => function ($enrollmentQuery) use ($request) {
                $enrollmentQuery->where('user_id', $request->user()->id);
            }]);
        }

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        if (in_array($sortBy, ['created_at', 'title', 'difficulty_level', 'estimated_duration'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $tracks = $query->paginate($request->get('per_page', 12))
                       ->withQueryString();

        // Transform tracks data for frontend
        $tracks->getCollection()->transform(function ($track) {
            $trackData = [
                'id' => $track->id,
                'title' => $track->title,
                'description' => $track->description,
                'slug' => $track->slug,
                'difficulty_level' => $track->difficulty_level,
                'is_premium' => $track->is_premium,
                'price' => $track->price,
                'estimated_duration' => $track->estimated_duration,
                'enrollments_count' => $track->enrollments_count,
                'levels_count' => $track->levels_count,
            ];

            // Add instructor data if available
            if ($track->instructor) {
                $trackData['instructor'] = [
                    'id' => $track->instructor->id,
                    'name' => $track->instructor->name,
                ];
            } else {
                $trackData['instructor'] = null;
            }

            // Add enrollment data if user is enrolled
            if ($track->enrollments->isNotEmpty()) {
                $enrollment = $track->enrollments->first();
                $trackData['enrollment'] = [
                    'id' => $enrollment->id,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'progress_percentage' => $enrollment->progress_percentage,
                    'completed_at' => $enrollment->completed_at,
                ];
            }

            return $trackData;
        });

        return Inertia::render('classroom/TrackList', [
            'tracks' => $tracks,
            'filters' => [
                'search' => $request->search,
                'difficulty' => $request->difficulty,
                'is_premium' => $request->has('is_premium') ? $request->boolean('is_premium') : null,
                'sort' => $sortBy,
            ],
        ]);
    }

    /**
     * Display track detail page.
     */
    public function trackDetail(Request $request, string $slug): Response
    {
        $track = Track::where('slug', $slug)
            ->with(['instructor:id,name', 'levels' => function ($levelQuery) use ($request) {
                if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                    $levelQuery->where('is_published', true);
                }
                $levelQuery->withCount('modules')->orderBy('order_index');
            }])
            ->withCount(['enrollments', 'levels'])
            ->firstOrFail();

        // Check access permissions
        if (!$track->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        $trackData = [
            'id' => $track->id,
            'title' => $track->title,
            'description' => $track->description,
            'slug' => $track->slug,
            'difficulty_level' => $track->difficulty_level,
            'is_premium' => $track->is_premium,
            'is_free' => !$track->is_premium,
            'price' => $track->price,
            'estimated_duration' => $track->estimated_duration,
            'is_published' => $track->is_published,
            'enrollments_count' => $track->enrollments_count,
            'levels_count' => $track->levels_count,
            'levels' => $track->levels->map(function ($level) {
                return [
                    'id' => $level->id,
                    'title' => $level->title,
                    'description' => $level->description,
                    'difficulty' => $level->difficulty,
                    'order_index' => $level->order_index,
                    'modules_count' => $level->modules_count,
                ];
            }),
        ];

        // Add instructor data if available
        if ($track->instructor) {
            $trackData['instructor'] = [
                'id' => $track->instructor->id,
                'name' => $track->instructor->name,
            ];
        } else {
            $trackData['instructor'] = null;
        }

        // Add enrollment and progress data for authenticated users
        if ($request->user()) {
            $trackWithProgress = $this->trackService->getTrackWithProgress($track, $request->user());
            $trackData['enrollment'] = $trackWithProgress['enrollment'];
            $trackData['progress'] = $trackWithProgress['progress'];
        }

        return Inertia::render('classroom/TrackDetail', [
            'track' => $trackData,
        ]);
    }

    /**
     * Display level view page.
     */
    public function levelView(Request $request, int $levelId): Response
    {
        $level = Level::with(['track', 'modules' => function ($moduleQuery) use ($request) {
            if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                $moduleQuery->where('is_published', true);
            }
            $moduleQuery->withCount(['lessons', 'assessments', 'assignments'])
                        ->orderBy('order_index');
        }])
        ->findOrFail($levelId);

        // Check access permissions
        $this->checkTrackAccess($request, $level->track);

        $levelData = [
            'id' => $level->id,
            'title' => $level->title,
            'description' => $level->description,
            'difficulty' => $level->difficulty,
            'order_index' => $level->order_index,
            'track' => [
                'id' => $level->track->id,
                'title' => $level->track->title,
                'slug' => $level->track->slug,
            ],
            'modules' => $level->modules->map(function ($module) use ($request) {
                $moduleData = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'order_index' => $module->order_index,
                    'estimated_duration' => $module->estimated_duration,
                    'lessons_count' => $module->lessons_count,
                    'assessments_count' => $module->assessments_count,
                    'assignments_count' => $module->assignments_count,
                ];

                // Add progress data for authenticated users
                if ($request->user()) {
                    $moduleData['progress_percentage'] = $this->progressService->calculateModuleProgress($request->user(), $module);
                }

                return $moduleData;
            }),
        ];

        // Add progress data for authenticated users
        if ($request->user()) {
            $levelData['progress_percentage'] = $this->progressService->calculateLevelProgress($request->user(), $level);
        }

        // Generate breadcrumbs
        $breadcrumbs = [
            ['title' => 'Classroom', 'url' => route('classroom.tracks.index')],
            ['title' => $level->track->title, 'url' => route('classroom.tracks.show', $level->track->slug)],
            ['title' => $level->title, 'url' => null],
        ];

        return Inertia::render('classroom/LevelView', [
            'level' => $levelData,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display module view page.
     */
    public function moduleView(Request $request, int $moduleId): Response
    {
        $module = Module::with(['level.track', 'lessons' => function ($lessonQuery) use ($request) {
            if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                $lessonQuery->where('is_published', true);
            }
            $lessonQuery->orderBy('order_index');
        }, 'assessments', 'assignments'])
        ->findOrFail($moduleId);

        // Check access permissions
        $this->checkTrackAccess($request, $module->level->track);

        $moduleData = [
            'id' => $module->id,
            'title' => $module->title,
            'description' => $module->description,
            'order_index' => $module->order_index,
            'estimated_duration' => $module->estimated_duration,
            'level' => [
                'id' => $module->level->id,
                'title' => $module->level->title,
                'track' => [
                    'id' => $module->level->track->id,
                    'title' => $module->level->track->title,
                    'slug' => $module->level->track->slug,
                ],
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
            ['title' => 'Classroom', 'url' => route('classroom.tracks.index')],
            ['title' => $module->level->track->title, 'url' => route('classroom.tracks.show', $module->level->track->slug)],
            ['title' => $module->level->title, 'url' => route('classroom.levels.show', $module->level->id)],
            ['title' => $module->title, 'url' => null],
        ];

        return Inertia::render('classroom/ModuleView', [
            'module' => $moduleData,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display lesson view page.
     */
    public function lessonView(Request $request, int $lessonId): Response
    {
        $lesson = Lesson::with(['module.level.track', 'assessments', 'discussions', 'media'])
            ->findOrFail($lessonId);

        // Check access permissions
        $this->checkTrackAccess($request, $lesson->module->level->track);

        // Check if lesson is published for non-instructors
        if (!$lesson->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        $lessonData = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'content' => $lesson->content,
            'order_index' => $lesson->order_index,
            'estimated_duration' => $lesson->estimated_duration,
            'lesson_type' => $lesson->lesson_type,
            'module' => [
                'id' => $lesson->module->id,
                'title' => $lesson->module->title,
                'level' => [
                    'id' => $lesson->module->level->id,
                    'title' => $lesson->module->level->title,
                    'track' => [
                        'id' => $lesson->module->level->track->id,
                        'title' => $lesson->module->level->track->title,
                        'slug' => $lesson->module->level->track->slug,
                    ],
                ],
            ],
            'media' => $lesson->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'original_name' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'url' => $media->url,
                    'size' => $media->size,
                ];
            }),
        ];

        // Add progress data for authenticated users
        if ($request->user()) {
            $progress = $this->progressService->getLessonProgress($request->user(), $lesson);
            $lessonData['is_completed'] = $progress ? $progress->isCompleted() : false;
            $lessonData['progress'] = $progress;

            // Get module progress
            $moduleProgress = [
                'progress_percentage' => $this->progressService->calculateModuleProgress($request->user(), $lesson->module),
                'completed_lessons' => $lesson->module->lessons()
                    ->whereHas('progress', function ($query) use ($request) {
                        $query->where('user_id', $request->user()->id)
                              ->whereNotNull('completed_at');
                    })->count(),
                'total_lessons' => $lesson->module->lessons()->count(),
            ];
            $lessonData['moduleProgress'] = $moduleProgress;
        }

        // Get navigation (previous/next lessons)
        $navigation = $this->getNavigationData($request, $lesson);
        $lessonData['nextLesson'] = $navigation['next'];
        $lessonData['previousLesson'] = $navigation['previous'];

        // Generate breadcrumbs
        $breadcrumbs = [
            ['title' => 'Classroom', 'url' => route('classroom.tracks.index')],
            ['title' => $lesson->module->level->track->title, 'url' => route('classroom.tracks.show', $lesson->module->level->track->slug)],
            ['title' => $lesson->module->level->title, 'url' => route('classroom.levels.show', $lesson->module->level->id)],
            ['title' => $lesson->module->title, 'url' => route('classroom.modules.show', $lesson->module->id)],
            ['title' => $lesson->title, 'url' => null],
        ];

        return Inertia::render('classroom/LessonView', [
            'lesson' => $lessonData,
            'breadcrumbs' => $breadcrumbs,
            'nextLesson' => $navigation['next'],
            'previousLesson' => $navigation['previous'],
            'moduleProgress' => $lessonData['moduleProgress'] ?? null,
        ]);
    }

    /**
     * Display assessment view page.
     */
    public function assessmentView(Request $request, int $assessmentId): Response
    {
        $assessment = Assessment::with(['assessable', 'questions.options'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        try {
            $assessmentData = $this->assessmentService->getAssessmentForTaking($user, $assessment);
            $results = $this->assessmentService->getAssessmentResults($user, $assessment);

            // Generate breadcrumbs based on assessable type
            $breadcrumbs = $this->generateAssessmentBreadcrumbs($assessment);

            return Inertia::render('classroom/AssessmentView', [
                'assessment' => $assessmentData,
                'results' => $results,
                'breadcrumbs' => $breadcrumbs,
            ]);
        } catch (\Exception $e) {
            abort(403, 'Cannot access this assessment.');
        }
    }

    /**
     * Display assessment results page.
     */
    public function assessmentResults(Request $request, int $assessmentId): Response
    {
        $assessment = Assessment::with(['assessable'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        $results = $this->assessmentService->getAssessmentResults($user, $assessment);

        if ($results['attempts']->isEmpty()) {
            abort(404, 'No assessment attempts found.');
        }

        // Generate breadcrumbs based on assessable type
        $breadcrumbs = $this->generateAssessmentBreadcrumbs($assessment);
        $breadcrumbs[] = ['title' => 'Results', 'url' => null];

        return Inertia::render('classroom/AssessmentResults', [
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'description' => $assessment->description,
                'passing_score' => $assessment->passing_score,
                'max_attempts' => $assessment->max_attempts,
                'is_required' => $assessment->is_required,
            ],
            'results' => $results,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display course-specific module view page.
     */
    public function courseModuleView(Request $request, string $courseSlug, int $moduleId): Response
    {
        $course = \App\Models\Course::where('slug', $courseSlug)->firstOrFail();
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

        $module->load(['lessons' => function ($lessonQuery) use ($request) {
            if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                $lessonQuery->where('is_published', true);
            }
            $lessonQuery->orderBy('order_index');
        }, 'assessments', 'assignments']);

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
        $course = \App\Models\Course::where('slug', $courseSlug)->firstOrFail();
        $module = Module::findOrFail($moduleId);
        $lesson = Lesson::findOrFail($lessonId);

        // Check if user is enrolled in the course
        if ($request->user()) {
            $enrollment = $course->enrollments()->where('user_id', $request->user()->id)->first();
            if (!$enrollment) {
                abort(403, 'You must be enrolled in this course to access its content.');
            }
        } else {
            abort(401, 'Authentication required.');
        }

        // Check if module is part of this course and lesson is part of module
        $courseModule = $course->modules()->where('modules.id', $module->id)->first();
        if (!$courseModule) {
            abort(404, 'Module not found in this course.');
        }

        if ($lesson->module_id !== $module->id) {
            abort(404, 'Lesson not found in this module.');
        }

        // Check if lesson is published for non-instructors
        if (!$lesson->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        $lesson->load(['assessments', 'discussions', 'media']);

        $lessonData = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'content' => $lesson->content,
            'order_index' => $lesson->order_index,
            'estimated_duration' => $lesson->estimated_duration,
            'lesson_type' => $lesson->lesson_type,
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
            ],
            'media' => $lesson->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'original_name' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'url' => $media->url,
                    'size' => $media->size,
                ];
            }),
        ];

        // Add progress data for authenticated users
        if ($request->user()) {
            $progress = $this->progressService->getLessonProgress($request->user(), $lesson);
            $lessonData['is_completed'] = $progress ? $progress->isCompleted() : false;
            $lessonData['progress'] = $progress;

            // Get module progress
            $moduleProgress = [
                'progress_percentage' => $this->progressService->calculateModuleProgress($request->user(), $module),
                'completed_lessons' => $module->lessons()
                    ->whereHas('progress', function ($query) use ($request) {
                        $query->where('user_id', $request->user()->id)
                              ->whereNotNull('completed_at');
                    })->count(),
                'total_lessons' => $module->lessons()->count(),
            ];
            $lessonData['moduleProgress'] = $moduleProgress;
        }

        // Get navigation (previous/next lessons within the course context)
        $navigation = $this->getCourseNavigationData($request, $course, $module, $lesson);
        $lessonData['nextLesson'] = $navigation['next'];
        $lessonData['previousLesson'] = $navigation['previous'];

        // Generate breadcrumbs
        $breadcrumbs = [
            ['title' => 'Courses', 'url' => route('courses.index')],
            ['title' => $course->title, 'url' => route('courses.show', $course->slug)],
            ['title' => $module->title, 'url' => route('courses.modules.show', [$course->slug, $module->id])],
            ['title' => $lesson->title, 'url' => null],
        ];

        return Inertia::render('classroom/CourseLessonView', [
            'lesson' => $lessonData,
            'breadcrumbs' => $breadcrumbs,
            'nextLesson' => $navigation['next'],
            'previousLesson' => $navigation['previous'],
            'moduleProgress' => $lessonData['moduleProgress'] ?? null,
        ]);
    }

    /**
     * Display course-specific assessment view page.
     */
    public function courseAssessmentView(Request $request, string $courseSlug, int $moduleId, int $assessmentId): Response
    {
        $course = \App\Models\Course::where('slug', $courseSlug)->firstOrFail();
        $module = Module::findOrFail($moduleId);
        $assessment = Assessment::with(['assessable', 'questions.options'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check if user is enrolled in the course
        $enrollment = $course->enrollments()->where('user_id', $user->id)->first();
        if (!$enrollment) {
            abort(403, 'You must be enrolled in this course to access its content.');
        }

        // Check if module is part of this course
        $courseModule = $course->modules()->where('modules.id', $module->id)->first();
        if (!$courseModule) {
            abort(404, 'Module not found in this course.');
        }

        // Check if assessment belongs to this module
        if ($assessment->assessable_type !== Module::class || $assessment->assessable_id !== $module->id) {
            abort(404, 'Assessment not found in this module.');
        }

        try {
            $assessmentData = $this->assessmentService->getAssessmentForTaking($user, $assessment);
            $results = $this->assessmentService->getAssessmentResults($user, $assessment);

            // Generate breadcrumbs
            $breadcrumbs = [
                ['title' => 'Courses', 'url' => route('courses.index')],
                ['title' => $course->title, 'url' => route('courses.show', $course->slug)],
                ['title' => $module->title, 'url' => route('courses.modules.show', [$course->slug, $module->id])],
                ['title' => $assessment->title, 'url' => null],
            ];

            return Inertia::render('classroom/CourseAssessmentView', [
                'assessment' => $assessmentData,
                'results' => $results,
                'breadcrumbs' => $breadcrumbs,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                ],
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                ],
            ]);
        } catch (\Exception $e) {
            abort(403, 'Cannot access this assessment.');
        }
    }

    /**
     * Get navigation data for a lesson within course context (previous/next).
     */
    private function getCourseNavigationData(Request $request, \App\Models\Course $course, Module $module, Lesson $lesson): array
    {
        $publishedOnly = !$request->user() || !$request->user()->hasInstructorPermissions();

        // Get previous lesson
        $previousLesson = null;
        $prevInModule = $module->lessons()
            ->when($publishedOnly, fn($q) => $q->where('is_published', true))
            ->where('order_index', '<', $lesson->order_index)
            ->orderBy('order_index', 'desc')
            ->first();

        if ($prevInModule) {
            $previousLesson = [
                'id' => $prevInModule->id,
                'title' => $prevInModule->title,
                'module' => $module->title,
                'url' => route('courses.lessons.show', [$course->slug, $module->id, $prevInModule->id]),
            ];
        }

        // Get next lesson
        $nextLesson = null;
        $nextInModule = $module->lessons()
            ->when($publishedOnly, fn($q) => $q->where('is_published', true))
            ->where('order_index', '>', $lesson->order_index)
            ->orderBy('order_index', 'asc')
            ->first();

        if ($nextInModule) {
            $nextLesson = [
                'id' => $nextInModule->id,
                'title' => $nextInModule->title,
                'module' => $module->title,
                'url' => route('courses.lessons.show', [$course->slug, $module->id, $nextInModule->id]),
            ];
        }

        return [
            'previous' => $previousLesson,
            'next' => $nextLesson,
        ];
    }
    private function checkTrackAccess(Request $request, Track $track): void
    {
        // Check if track is published for non-instructors
        if (!$track->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        // Check enrollment access for authenticated users
        if ($request->user()) {
            $hasAccess = $this->enrollmentService->checkEnrollmentAccess($request->user(), $track);
            if (!$hasAccess) {
                abort(403, 'Access denied. Enrollment required.');
            }
        } else {
            // For unauthenticated users, only allow access to free published tracks
            if (!$track->isFree() || !$track->is_published) {
                abort(401, 'Authentication required.');
            }
        }
    }

    /**
     * Check if user has access to assessment.
     */
    private function checkAssessmentAccess(Request $request, Assessment $assessment): void
    {
        $assessable = $assessment->assessable;

        if (!$assessable) {
            abort(404, 'Assessment content not found.');
        }

        // Get the track from the assessable content
        if ($assessable instanceof Lesson) {
            $track = $assessable->module->level->track;
        } else { // Module
            $track = $assessable->level->track;
        }

        $this->checkTrackAccess($request, $track);

        // Check if assessable content is published for non-instructors
        if (!$assessable->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }
    }

    /**
     * Get navigation data for a lesson (previous/next).
     */
    private function getNavigationData(Request $request, Lesson $lesson): array
    {
        $module = $lesson->module;
        $publishedOnly = !$request->user() || !$request->user()->hasInstructorPermissions();

        // Get previous lesson
        $previousLesson = null;
        $prevInModule = $module->lessons()
            ->when($publishedOnly, fn($q) => $q->where('is_published', true))
            ->where('order_index', '<', $lesson->order_index)
            ->orderBy('order_index', 'desc')
            ->first();

        if ($prevInModule) {
            $previousLesson = [
                'id' => $prevInModule->id,
                'title' => $prevInModule->title,
                'module' => $module->title,
            ];
        }

        // Get next lesson
        $nextLesson = null;
        $nextInModule = $module->lessons()
            ->when($publishedOnly, fn($q) => $q->where('is_published', true))
            ->where('order_index', '>', $lesson->order_index)
            ->orderBy('order_index', 'asc')
            ->first();

        if ($nextInModule) {
            $nextLesson = [
                'id' => $nextInModule->id,
                'title' => $nextInModule->title,
                'module' => $module->title,
            ];
        }

        return [
            'previous' => $previousLesson,
            'next' => $nextLesson,
        ];
    }

    /**
     * Generate breadcrumbs for assessment pages.
     */
    private function generateAssessmentBreadcrumbs(Assessment $assessment): array
    {
        $assessable = $assessment->assessable;
        $breadcrumbs = [
            ['title' => 'Classroom', 'url' => route('classroom.tracks.index')],
        ];

        if ($assessable instanceof Lesson) {
            $track = $assessable->module->level->track;
            $breadcrumbs[] = ['title' => $track->title, 'url' => route('classroom.tracks.show', $track->slug)];
            $breadcrumbs[] = ['title' => $assessable->module->level->title, 'url' => route('classroom.levels.show', $assessable->module->level->id)];
            $breadcrumbs[] = ['title' => $assessable->module->title, 'url' => route('classroom.modules.show', $assessable->module->id)];
            $breadcrumbs[] = ['title' => $assessable->title, 'url' => route('classroom.lessons.show', $assessable->id)];
        } else { // Module
            $track = $assessable->level->track;
            $breadcrumbs[] = ['title' => $track->title, 'url' => route('classroom.tracks.show', $track->slug)];
            $breadcrumbs[] = ['title' => $assessable->level->title, 'url' => route('classroom.levels.show', $assessable->level->id)];
            $breadcrumbs[] = ['title' => $assessable->title, 'url' => route('classroom.modules.show', $assessable->id)];
        }

        $breadcrumbs[] = ['title' => $assessment->title, 'url' => null];

        return $breadcrumbs;
    }
}
