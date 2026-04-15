<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LevelResource;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\LessonResource;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Services\ContentService;
use App\Services\ProgressService;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    public function __construct(
        private ContentService $contentService,
        private ProgressService $progressService,
        private EnrollmentService $enrollmentService
    ) {}

    /**
     * Get levels for a track.
     */
    public function trackLevels(Request $request, string $trackSlug): AnonymousResourceCollection
    {
        $track = Track::where('slug', $trackSlug)->firstOrFail();

        // Check access permissions
        $this->checkTrackAccess($request, $track);

        $query = $track->levels();

        // For non-instructors, only show published levels
        if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
            $query->where('is_published', true);
        }

        $levels = $query->with(['modules' => function ($moduleQuery) use ($request) {
            if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                $moduleQuery->where('is_published', true);
            }
            $moduleQuery->withCount('lessons');
        }])
        ->withCount('modules')
        ->orderBy('order_index')
        ->get();

        // Add progress data for authenticated users
        if ($request->user()) {
            $levels->each(function ($level) use ($request) {
                $level->progress_percentage = $this->progressService->calculateLevelProgress($request->user(), $level);
            });
        }

        return LevelResource::collection($levels);
    }

    /**
     * Get modules for a level.
     */
    public function levelModules(Request $request, int $levelId): AnonymousResourceCollection
    {
        $level = Level::with('track')->findOrFail($levelId);

        // Check access permissions
        $this->checkTrackAccess($request, $level->track);

        $query = $level->modules();

        // For non-instructors, only show published modules
        if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
            $query->where('is_published', true);
        }

        $modules = $query->with(['lessons' => function ($lessonQuery) use ($request) {
            if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
                $lessonQuery->where('is_published', true);
            }
        }, 'assessments', 'assignments'])
        ->withCount(['lessons', 'assessments', 'assignments'])
        ->orderBy('order_index')
        ->get();

        // Add progress data for authenticated users
        if ($request->user()) {
            $modules->each(function ($module) use ($request) {
                $module->progress_percentage = $this->progressService->calculateModuleProgress($request->user(), $module);
            });
        }

        return ModuleResource::collection($modules);
    }

    /**
     * Get lessons for a module.
     */
    public function moduleLessons(Request $request, int $moduleId): AnonymousResourceCollection
    {
        $module = Module::with('level.track')->findOrFail($moduleId);

        // Check access permissions
        $this->checkTrackAccess($request, $module->level->track);

        $query = $module->lessons();

        // For non-instructors, only show published lessons
        if (!$request->user() || !$request->user()->hasInstructorPermissions()) {
            $query->where('is_published', true);
        }

        $lessons = $query->with(['assessments', 'discussions', 'media'])
            ->orderBy('order_index')
            ->get();

        // Add progress data for authenticated users
        if ($request->user()) {
            $lessons->each(function ($lesson) use ($request) {
                $progress = $this->progressService->getLessonProgress($request->user(), $lesson);
                $lesson->is_completed = $progress ? $progress->isCompleted() : false;
                $lesson->progress = $progress;
            });
        }

        return LessonResource::collection($lessons);
    }

    /**
     * Get a specific lesson with content.
     */
    public function showLesson(Request $request, int $lessonId): LessonResource
    {
        $lesson = Lesson::with(['module.level.track', 'assessments', 'discussions', 'media'])
            ->findOrFail($lessonId);

        // Check access permissions
        $this->checkTrackAccess($request, $lesson->module->level->track);

        // Check if lesson is published for non-instructors
        if (!$lesson->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }

        // Add progress data for authenticated users
        if ($request->user()) {
            $progress = $this->progressService->getLessonProgress($request->user(), $lesson);
            $lesson->is_completed = $progress ? $progress->isCompleted() : false;
            $lesson->progress = $progress;
        }

        return new LessonResource($lesson);
    }

    /**
     * Mark a lesson as complete.
     */
    public function completeLesson(Request $request, int $lessonId): \Illuminate\Http\JsonResponse
    {
        $lesson = Lesson::with('module.level.track')->findOrFail($lessonId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkTrackAccess($request, $lesson->module->level->track);

        try {
            $progress = $this->progressService->markLessonComplete($user, $lesson);

            // Calculate updated progress percentages
            $moduleProgress = $this->progressService->calculateModuleProgress($user, $lesson->module);
            $levelProgress = $this->progressService->calculateLevelProgress($user, $lesson->module->level);
            $trackProgress = $this->progressService->calculateTrackProgress($user, $lesson->module->level->track);

            return response()->json([
                'message' => 'Lesson marked as complete.',
                'progress' => [
                    'lesson_completed_at' => $progress->completed_at,
                    'module_progress' => $moduleProgress,
                    'level_progress' => $levelProgress,
                    'track_progress' => $trackProgress,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Failed to mark lesson as complete.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Update lesson time spent.
     */
    public function updateLessonTime(Request $request, int $lessonId): \Illuminate\Http\JsonResponse
    {
        $lesson = Lesson::with('module.level.track')->findOrFail($lessonId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkTrackAccess($request, $lesson->module->level->track);

        $validated = $request->validate([
            'time_spent' => 'required|integer|min:1|max:7200', // Max 2 hours per session
        ]);

        $progress = $this->progressService->updateLessonTimeSpent($user, $lesson, $validated['time_spent']);

        return response()->json([
            'message' => 'Lesson time updated.',
            'progress' => [
                'total_time_spent' => $progress->time_spent,
                'session_time' => $validated['time_spent'],
            ],
        ], 200);
    }

    /**
     * Get content hierarchy for a track.
     */
    public function trackHierarchy(Request $request, string $trackSlug): \Illuminate\Http\JsonResponse
    {
        $track = Track::where('slug', $trackSlug)->firstOrFail();

        // Check access permissions
        $this->checkTrackAccess($request, $track);

        $publishedOnly = !$request->user() || !$request->user()->hasInstructorPermissions();
        $hierarchy = $this->contentService->getContentHierarchy($track->id, $publishedOnly);

        // Add progress data for authenticated users
        if ($request->user()) {
            $hierarchy->each(function ($level) use ($request) {
                $level->progress_percentage = $this->progressService->calculateLevelProgress($request->user(), $level);

                $level->modules->each(function ($module) use ($request) {
                    $module->progress_percentage = $this->progressService->calculateModuleProgress($request->user(), $module);

                    $module->lessons->each(function ($lesson) use ($request) {
                        $progress = $this->progressService->getLessonProgress($request->user(), $lesson);
                        $lesson->is_completed = $progress ? $progress->isCompleted() : false;
                    });
                });
            });
        }

        return response()->json([
            'track' => [
                'id' => $track->id,
                'title' => $track->title,
                'slug' => $track->slug,
            ],
            'hierarchy' => LevelResource::collection($hierarchy),
        ], 200);
    }

    /**
     * Get navigation data for a lesson (previous/next).
     */
    public function lessonNavigation(Request $request, int $lessonId): \Illuminate\Http\JsonResponse
    {
        $lesson = Lesson::with('module.level.track')->findOrFail($lessonId);

        // Check access permissions
        $this->checkTrackAccess($request, $lesson->module->level->track);

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
            $previousLesson = $prevInModule;
        } else {
            // Look in previous module
            $prevModule = $module->level->modules()
                ->when($publishedOnly, fn($q) => $q->where('is_published', true))
                ->where('order_index', '<', $module->order_index)
                ->orderBy('order_index', 'desc')
                ->first();

            if ($prevModule) {
                $previousLesson = $prevModule->lessons()
                    ->when($publishedOnly, fn($q) => $q->where('is_published', true))
                    ->orderBy('order_index', 'desc')
                    ->first();
            }
        }

        // Get next lesson
        $nextLesson = null;
        $nextInModule = $module->lessons()
            ->when($publishedOnly, fn($q) => $q->where('is_published', true))
            ->where('order_index', '>', $lesson->order_index)
            ->orderBy('order_index', 'asc')
            ->first();

        if ($nextInModule) {
            $nextLesson = $nextInModule;
        } else {
            // Look in next module
            $nextModule = $module->level->modules()
                ->when($publishedOnly, fn($q) => $q->where('is_published', true))
                ->where('order_index', '>', $module->order_index)
                ->orderBy('order_index', 'asc')
                ->first();

            if ($nextModule) {
                $nextLesson = $nextModule->lessons()
                    ->when($publishedOnly, fn($q) => $q->where('is_published', true))
                    ->orderBy('order_index', 'asc')
                    ->first();
            }
        }

        return response()->json([
            'current' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'module' => $module->title,
                'level' => $module->level->title,
            ],
            'previous' => $previousLesson ? [
                'id' => $previousLesson->id,
                'title' => $previousLesson->title,
                'module' => $previousLesson->module->title,
            ] : null,
            'next' => $nextLesson ? [
                'id' => $nextLesson->id,
                'title' => $nextLesson->title,
                'module' => $nextLesson->module->title,
            ] : null,
        ], 200);
    }

    /**
     * Get user progress for a track.
     */
    public function trackProgress(Request $request, string $trackSlug): \Illuminate\Http\JsonResponse
    {
        $track = Track::where('slug', $trackSlug)->firstOrFail();
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkTrackAccess($request, $track);

        $progressReport = $this->progressService->getDetailedProgressReport($user, $track);

        return response()->json($progressReport, 200);
    }

    /**
     * Check if user has access to track content.
     */
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
}
