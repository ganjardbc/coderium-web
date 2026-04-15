<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\AssessmentAttemptResource;
use App\Models\Assessment;
use App\Services\AssessmentService;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function __construct(
        private AssessmentService $assessmentService,
        private EnrollmentService $enrollmentService
    ) {}

    /**
     * Get assessment for taking (without revealing answers).
     */
    public function show(Request $request, int $assessmentId): \Illuminate\Http\JsonResponse
    {
        $assessment = Assessment::with(['assessable'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        try {
            $assessmentData = $this->assessmentService->getAssessmentForTaking($user, $assessment);

            return response()->json($assessmentData, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Cannot access assessment.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Submit assessment answers.
     */
    public function submit(Request $request, int $assessmentId): \Illuminate\Http\JsonResponse
    {
        $assessment = Assessment::with(['assessable', 'questions.options'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'array',
            'answers.*.selected_options' => 'nullable|array',
            'answers.*.selected_options.*' => 'integer|exists:question_options,id',
            'answers.*.answer_text' => 'nullable|string|max:1000',
            'time_taken' => 'nullable|integer|min:1',
        ]);

        try {
            $attempt = $this->assessmentService->submitAssessment($user, $assessment, $validated['answers']);

            // Update time taken if provided
            if (isset($validated['time_taken'])) {
                $attempt->update(['time_taken' => $validated['time_taken']]);
            }

            // Get immediate feedback
            $feedback = $this->assessmentService->getImmediateFeedback($attempt);

            return response()->json([
                'message' => 'Assessment submitted successfully.',
                'attempt' => new AssessmentAttemptResource($attempt),
                'feedback' => $feedback,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Assessment submission failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get assessment results for the authenticated user.
     */
    public function results(Request $request, int $assessmentId): \Illuminate\Http\JsonResponse
    {
        $assessment = Assessment::with(['assessable'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        $results = $this->assessmentService->getAssessmentResults($user, $assessment);

        return response()->json($results, 200);
    }

    /**
     * Get specific attempt details with feedback.
     */
    public function attemptDetails(Request $request, int $assessmentId, int $attemptId): \Illuminate\Http\JsonResponse
    {
        $assessment = Assessment::with(['assessable'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        $attempt = $assessment->attempts()
            ->where('id', $attemptId)
            ->where('user_id', $user->id)
            ->with(['answers.question.options'])
            ->firstOrFail();

        $feedback = $this->assessmentService->getImmediateFeedback($attempt);

        return response()->json([
            'attempt' => new AssessmentAttemptResource($attempt),
            'feedback' => $feedback,
        ], 200);
    }

    /**
     * Check if user can take an assessment.
     */
    public function canTake(Request $request, int $assessmentId): \Illuminate\Http\JsonResponse
    {
        $assessment = Assessment::with(['assessable'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        $canTake = $this->assessmentService->canUserTakeAssessment($user, $assessment);
        $results = $this->assessmentService->getAssessmentResults($user, $assessment);

        return response()->json([
            'can_take' => $canTake,
            'has_passed' => $results['has_passed'],
            'remaining_attempts' => $results['remaining_attempts'],
            'best_score' => $results['best_attempt'] ? $results['best_attempt']->score : null,
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'is_required' => $assessment->is_required,
                'passing_score' => $assessment->passing_score,
                'max_attempts' => $assessment->max_attempts,
                'time_limit' => $assessment->time_limit,
            ],
        ], 200);
    }

    /**
     * Get assessments for a specific content (lesson or module).
     */
    public function contentAssessments(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'assessable_type' => 'required|in:App\Models\Lesson,App\Models\Module',
            'assessable_id' => 'required|integer|exists:' . ($request->assessable_type === 'App\Models\Lesson' ? 'lessons' : 'modules') . ',id',
        ]);

        $assessments = Assessment::where('assessable_type', $validated['assessable_type'])
            ->where('assessable_id', $validated['assessable_id'])
            ->with(['questions'])
            ->withCount(['questions', 'attempts'])
            ->get();

        // Add user-specific data for authenticated users
        if ($request->user()) {
            $assessments->each(function ($assessment) use ($request) {
                $results = $this->assessmentService->getAssessmentResults($request->user(), $assessment);
                $assessment->user_attempts = $results['attempts']->count();
                $assessment->best_score = $results['best_attempt'] ? $results['best_attempt']->score : null;
                $assessment->has_passed = $results['has_passed'];
            });
        }

        return AssessmentResource::collection($assessments);
    }

    /**
     * Check content access based on required assessments.
     */
    public function checkContentAccess(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $validated = $request->validate([
            'content_type' => 'required|in:lesson,module',
            'content_id' => 'required|integer',
        ]);

        $accessCheck = $this->assessmentService->checkContentAccess(
            $user,
            $validated['content_type'],
            $validated['content_id']
        );

        return response()->json($accessCheck, 200);
    }

    /**
     * Get user's assessment progress summary.
     */
    public function progressSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Get all assessments the user has access to
        $enrolledTracks = $this->enrollmentService->getEnrolledTracks($user);
        $enrolledCourses = $this->getEnrolledCourses($user);

        $assessmentProgress = [];

        // Process track assessments
        foreach ($enrolledTracks as $track) {
            $trackAssessments = [];

            foreach ($track->levels as $level) {
                foreach ($level->modules as $module) {
                    // Module assessments
                    $moduleAssessments = Assessment::where('assessable_type', 'App\Models\Module')
                        ->where('assessable_id', $module->id)
                        ->get();

                    foreach ($moduleAssessments as $assessment) {
                        $results = $this->assessmentService->getAssessmentResults($user, $assessment);
                        $trackAssessments[] = [
                            'assessment_id' => $assessment->id,
                            'title' => $assessment->title,
                            'type' => 'module',
                            'content_title' => $module->title,
                            'is_required' => $assessment->is_required,
                            'has_passed' => $results['has_passed'],
                            'best_score' => $results['best_attempt'] ? $results['best_attempt']->score : null,
                            'attempts_count' => $results['attempts']->count(),
                            'can_retake' => $results['can_retake'],
                            'progress_contribution' => $assessment->getProgressContribution(),
                            'completion_percentage' => $assessment->getCompletionPercentage($user),
                        ];
                    }

                    // Lesson assessments
                    foreach ($module->lessons as $lesson) {
                        $lessonAssessments = Assessment::where('assessable_type', 'App\Models\Lesson')
                            ->where('assessable_id', $lesson->id)
                            ->get();

                        foreach ($lessonAssessments as $assessment) {
                            $results = $this->assessmentService->getAssessmentResults($user, $assessment);
                            $trackAssessments[] = [
                                'assessment_id' => $assessment->id,
                                'title' => $assessment->title,
                                'type' => 'lesson',
                                'content_title' => $lesson->title,
                                'is_required' => $assessment->is_required,
                                'has_passed' => $results['has_passed'],
                                'best_score' => $results['best_attempt'] ? $results['best_attempt']->score : null,
                                'attempts_count' => $results['attempts']->count(),
                                'can_retake' => $results['can_retake'],
                                'progress_contribution' => $assessment->getProgressContribution(),
                                'completion_percentage' => $assessment->getCompletionPercentage($user),
                            ];
                        }
                    }
                }
            }

            $assessmentProgress[] = [
                'learning_path_type' => 'track',
                'learning_path_id' => $track->id,
                'learning_path_title' => $track->title,
                'assessments' => $trackAssessments,
                'total_assessments' => count($trackAssessments),
                'passed_assessments' => collect($trackAssessments)->where('has_passed', true)->count(),
                'required_assessments' => collect($trackAssessments)->where('is_required', true)->count(),
                'passed_required' => collect($trackAssessments)->where('is_required', true)->where('has_passed', true)->count(),
                'total_progress_contribution' => collect($trackAssessments)->sum('progress_contribution'),
                'completed_progress_contribution' => collect($trackAssessments)->where('has_passed', true)->sum('progress_contribution'),
            ];
        }

        // Process course assessments
        foreach ($enrolledCourses as $course) {
            $courseAssessments = [];

            // Direct course assessments
            $directAssessments = Assessment::where('assessable_type', 'App\Models\Course')
                ->where('assessable_id', $course->id)
                ->get();

            foreach ($directAssessments as $assessment) {
                $results = $this->assessmentService->getAssessmentResults($user, $assessment);
                $courseAssessments[] = [
                    'assessment_id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => 'course',
                    'content_title' => $course->title,
                    'is_required' => $assessment->is_required,
                    'has_passed' => $results['has_passed'],
                    'best_score' => $results['best_attempt'] ? $results['best_attempt']->score : null,
                    'attempts_count' => $results['attempts']->count(),
                    'can_retake' => $results['can_retake'],
                    'progress_contribution' => $assessment->getProgressContribution(),
                    'completion_percentage' => $assessment->getCompletionPercentage($user),
                ];
            }

            // Module assessments within the course
            foreach ($course->modules as $module) {
                $moduleAssessments = Assessment::where('assessable_type', 'App\Models\Module')
                    ->where('assessable_id', $module->id)
                    ->get();

                foreach ($moduleAssessments as $assessment) {
                    $results = $this->assessmentService->getAssessmentResults($user, $assessment);
                    $courseAssessments[] = [
                        'assessment_id' => $assessment->id,
                        'title' => $assessment->title,
                        'type' => 'module',
                        'content_title' => $module->title,
                        'is_required' => $assessment->is_required,
                        'has_passed' => $results['has_passed'],
                        'best_score' => $results['best_attempt'] ? $results['best_attempt']->score : null,
                        'attempts_count' => $results['attempts']->count(),
                        'can_retake' => $results['can_retake'],
                        'progress_contribution' => $assessment->getProgressContribution(),
                        'completion_percentage' => $assessment->getCompletionPercentage($user),
                    ];
                }

                // Lesson assessments within course modules
                foreach ($module->lessons as $lesson) {
                    $lessonAssessments = Assessment::where('assessable_type', 'App\Models\Lesson')
                        ->where('assessable_id', $lesson->id)
                        ->get();

                    foreach ($lessonAssessments as $assessment) {
                        $results = $this->assessmentService->getAssessmentResults($user, $assessment);
                        $courseAssessments[] = [
                            'assessment_id' => $assessment->id,
                            'title' => $assessment->title,
                            'type' => 'lesson',
                            'content_title' => $lesson->title,
                            'is_required' => $assessment->is_required,
                            'has_passed' => $results['has_passed'],
                            'best_score' => $results['best_attempt'] ? $results['best_attempt']->score : null,
                            'attempts_count' => $results['attempts']->count(),
                            'can_retake' => $results['can_retake'],
                            'progress_contribution' => $assessment->getProgressContribution(),
                            'completion_percentage' => $assessment->getCompletionPercentage($user),
                        ];
                    }
                }
            }

            $assessmentProgress[] = [
                'learning_path_type' => 'course',
                'learning_path_id' => $course->id,
                'learning_path_title' => $course->title,
                'assessments' => $courseAssessments,
                'total_assessments' => count($courseAssessments),
                'passed_assessments' => collect($courseAssessments)->where('has_passed', true)->count(),
                'required_assessments' => collect($courseAssessments)->where('is_required', true)->count(),
                'passed_required' => collect($courseAssessments)->where('is_required', true)->where('has_passed', true)->count(),
                'total_progress_contribution' => collect($courseAssessments)->sum('progress_contribution'),
                'completed_progress_contribution' => collect($courseAssessments)->where('has_passed', true)->sum('progress_contribution'),
            ];
        }

        return response()->json([
            'user_id' => $user->id,
            'learning_paths' => $assessmentProgress,
            'summary' => [
                'total_learning_paths' => count($assessmentProgress),
                'total_tracks' => collect($assessmentProgress)->where('learning_path_type', 'track')->count(),
                'total_courses' => collect($assessmentProgress)->where('learning_path_type', 'course')->count(),
                'total_assessments' => collect($assessmentProgress)->sum('total_assessments'),
                'total_passed' => collect($assessmentProgress)->sum('passed_assessments'),
                'total_required' => collect($assessmentProgress)->sum('required_assessments'),
                'total_required_passed' => collect($assessmentProgress)->sum('passed_required'),
                'overall_progress_percentage' => $this->calculateOverallProgressPercentage($assessmentProgress),
            ],
        ], 200);
    }

    /**
     * Get course assessments for a specific course.
     */
    public function courseAssessments(Request $request, int $courseId): AnonymousResourceCollection
    {
        $course = \App\Models\Course::with(['modules.lessons'])->findOrFail($courseId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check course access
        $this->checkCourseAccess($request, $course);

        $assessments = collect();

        // Direct course assessments
        $directAssessments = Assessment::where('assessable_type', 'App\Models\Course')
            ->where('assessable_id', $course->id)
            ->with(['questions'])
            ->withCount(['questions', 'attempts'])
            ->get();

        $assessments = $assessments->merge($directAssessments);

        // Module assessments within the course
        foreach ($course->modules as $module) {
            $moduleAssessments = Assessment::where('assessable_type', 'App\Models\Module')
                ->where('assessable_id', $module->id)
                ->with(['questions'])
                ->withCount(['questions', 'attempts'])
                ->get();

            $assessments = $assessments->merge($moduleAssessments);

            // Lesson assessments within course modules
            foreach ($module->lessons as $lesson) {
                $lessonAssessments = Assessment::where('assessable_type', 'App\Models\Lesson')
                    ->where('assessable_id', $lesson->id)
                    ->with(['questions'])
                    ->withCount(['questions', 'attempts'])
                    ->get();

                $assessments = $assessments->merge($lessonAssessments);
            }
        }

        // Add user-specific data
        $assessments->each(function ($assessment) use ($user) {
            $results = $this->assessmentService->getAssessmentResults($user, $assessment);
            $assessment->user_attempts = $results['attempts']->count();
            $assessment->best_score = $results['best_attempt'] ? $results['best_attempt']->score : null;
            $assessment->has_passed = $results['has_passed'];
            $assessment->progress_contribution = $assessment->getProgressContribution();
            $assessment->completion_percentage = $assessment->getCompletionPercentage($user);
            $assessment->learning_path_context = $assessment->getLearningPathContext();
        });

        return AssessmentResource::collection($assessments);
    }

    /**
     * Get unified assessment report across all learning paths.
     */
    public function unifiedReport(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $validated = $request->validate([
            'learning_path_type' => 'nullable|in:track,course,all',
            'assessment_type' => 'nullable|in:lesson,module,course,all',
            'status' => 'nullable|in:passed,failed,not_attempted,all',
            'required_only' => 'nullable|boolean',
        ]);

        $learningPathType = $validated['learning_path_type'] ?? 'all';
        $assessmentType = $validated['assessment_type'] ?? 'all';
        $status = $validated['status'] ?? 'all';
        $requiredOnly = $validated['required_only'] ?? false;

        $report = [];

        // Get track assessments if requested
        if ($learningPathType === 'all' || $learningPathType === 'track') {
            $trackAssessments = $this->getTrackAssessmentsForReport($user, $assessmentType, $status, $requiredOnly);
            $report = array_merge($report, $trackAssessments);
        }

        // Get course assessments if requested
        if ($learningPathType === 'all' || $learningPathType === 'course') {
            $courseAssessments = $this->getCourseAssessmentsForReport($user, $assessmentType, $status, $requiredOnly);
            $report = array_merge($report, $courseAssessments);
        }

        // Calculate summary statistics
        $summary = $this->calculateReportSummary($report);

        return response()->json([
            'user_id' => $user->id,
            'filters' => [
                'learning_path_type' => $learningPathType,
                'assessment_type' => $assessmentType,
                'status' => $status,
                'required_only' => $requiredOnly,
            ],
            'assessments' => $report,
            'summary' => $summary,
        ], 200);
    }

    /**
     * Start an assessment attempt (for timed assessments).
     */
    public function startAttempt(Request $request, int $assessmentId): \Illuminate\Http\JsonResponse
    {
        $assessment = Assessment::with(['assessable'])->findOrFail($assessmentId);
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required.');
        }

        // Check access permissions
        $this->checkAssessmentAccess($request, $assessment);

        if (!$this->assessmentService->canUserTakeAssessment($user, $assessment)) {
            return response()->json([
                'message' => 'Cannot start assessment attempt.',
                'error' => 'You have reached the maximum number of attempts or already passed this assessment.',
            ], 422);
        }

        // For timed assessments, create an attempt record to track start time
        if ($assessment->time_limit) {
            $attempt = $assessment->attempts()
                ->where('user_id', $user->id)
                ->whereNull('completed_at')
                ->first();

            if (!$attempt) {
                $attempt = \App\Models\AssessmentAttempt::create([
                    'user_id' => $user->id,
                    'assessment_id' => $assessment->id,
                    'score' => 0,
                    'max_score' => $assessment->getTotalPoints(),
                    'passed' => false,
                    'started_at' => now(),
                    'attempt_number' => $this->getNextAttemptNumber($user, $assessment),
                ]);
            }

            $timeRemaining = $assessment->time_limit * 60; // Convert minutes to seconds
            $elapsed = now()->diffInSeconds($attempt->started_at);
            $timeRemaining = max(0, $timeRemaining - $elapsed);

            return response()->json([
                'message' => 'Assessment attempt started.',
                'attempt_id' => $attempt->id,
                'started_at' => $attempt->started_at,
                'time_limit' => $assessment->time_limit,
                'time_remaining' => $timeRemaining,
            ], 200);
        }

        return response()->json([
            'message' => 'Assessment ready to take.',
            'time_limit' => null,
        ], 200);
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
        if ($assessable instanceof \App\Models\Lesson) {
            $track = $assessable->module->level->track;
        } else { // Module
            $track = $assessable->level->track;
        }

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

        // Check if assessable content is published for non-instructors
        if (!$assessable->is_published && (!$request->user() || !$request->user()->hasInstructorPermissions())) {
            abort(404);
        }
    }

    /**
     * Get next attempt number for user and assessment.
     */
    private function getNextAttemptNumber(\App\Models\User $user, Assessment $assessment): int
    {
        $maxAttempt = $assessment->attempts()
            ->where('user_id', $user->id)
            ->max('attempt_number');

        return ($maxAttempt ?? 0) + 1;
    }

    /**
     * Get enrolled courses for a user.
     */
    private function getEnrolledCourses(\App\Models\User $user): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\Course::whereHas('enrollments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->with(['modules.lessons'])
        ->orderBy('created_at', 'desc')
        ->get();
    }

    /**
     * Check if user has access to course.
     */
    private function checkCourseAccess(Request $request, \App\Models\Course $course): void
    {
        $user = $request->user();

        // Check if course is active for non-instructors
        if (!$course->is_active && (!$user || !$user->hasInstructorPermissions())) {
            abort(404);
        }

        // Check enrollment access for authenticated users
        if ($user) {
            $hasAccess = \App\Models\CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if (!$hasAccess && !$user->hasInstructorPermissions()) {
                abort(403, 'Access denied. Course enrollment required.');
            }
        } else {
            // For unauthenticated users, deny access for now
            // In a real application, you might allow preview access for free courses
            abort(401, 'Authentication required.');
        }
    }

    /**
     * Calculate overall progress percentage across all learning paths.
     */
    private function calculateOverallProgressPercentage(array $assessmentProgress): float
    {
        $totalContribution = collect($assessmentProgress)->sum('total_progress_contribution');
        $completedContribution = collect($assessmentProgress)->sum('completed_progress_contribution');

        if ($totalContribution <= 0) {
            return 0.0;
        }

        return round(($completedContribution / $totalContribution) * 100, 2);
    }

    /**
     * Get track assessments for unified report.
     */
    private function getTrackAssessmentsForReport(\App\Models\User $user, string $assessmentType, string $status, bool $requiredOnly): array
    {
        $enrolledTracks = $this->enrollmentService->getEnrolledTracks($user);
        $assessments = [];

        foreach ($enrolledTracks as $track) {
            foreach ($track->levels as $level) {
                foreach ($level->modules as $module) {
                    // Module assessments
                    if ($assessmentType === 'all' || $assessmentType === 'module') {
                        $moduleAssessments = Assessment::where('assessable_type', 'App\Models\Module')
                            ->where('assessable_id', $module->id)
                            ->when($requiredOnly, function ($query) {
                                return $query->where('is_required', true);
                            })
                            ->get();

                        foreach ($moduleAssessments as $assessment) {
                            $assessmentData = $this->formatAssessmentForReport($user, $assessment, 'track', $track);
                            if ($this->matchesStatusFilter($assessmentData, $status)) {
                                $assessments[] = $assessmentData;
                            }
                        }
                    }

                    // Lesson assessments
                    if ($assessmentType === 'all' || $assessmentType === 'lesson') {
                        foreach ($module->lessons as $lesson) {
                            $lessonAssessments = Assessment::where('assessable_type', 'App\Models\Lesson')
                                ->where('assessable_id', $lesson->id)
                                ->when($requiredOnly, function ($query) {
                                    return $query->where('is_required', true);
                                })
                                ->get();

                            foreach ($lessonAssessments as $assessment) {
                                $assessmentData = $this->formatAssessmentForReport($user, $assessment, 'track', $track);
                                if ($this->matchesStatusFilter($assessmentData, $status)) {
                                    $assessments[] = $assessmentData;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $assessments;
    }

    /**
     * Get course assessments for unified report.
     */
    private function getCourseAssessmentsForReport(\App\Models\User $user, string $assessmentType, string $status, bool $requiredOnly): array
    {
        $enrolledCourses = $this->getEnrolledCourses($user);
        $assessments = [];

        foreach ($enrolledCourses as $course) {
            // Direct course assessments
            if ($assessmentType === 'all' || $assessmentType === 'course') {
                $courseAssessments = Assessment::where('assessable_type', 'App\Models\Course')
                    ->where('assessable_id', $course->id)
                    ->when($requiredOnly, function ($query) {
                        return $query->where('is_required', true);
                    })
                    ->get();

                foreach ($courseAssessments as $assessment) {
                    $assessmentData = $this->formatAssessmentForReport($user, $assessment, 'course', $course);
                    if ($this->matchesStatusFilter($assessmentData, $status)) {
                        $assessments[] = $assessmentData;
                    }
                }
            }

            // Module assessments within courses
            if ($assessmentType === 'all' || $assessmentType === 'module') {
                foreach ($course->modules as $module) {
                    $moduleAssessments = Assessment::where('assessable_type', 'App\Models\Module')
                        ->where('assessable_id', $module->id)
                        ->when($requiredOnly, function ($query) {
                            return $query->where('is_required', true);
                        })
                        ->get();

                    foreach ($moduleAssessments as $assessment) {
                        $assessmentData = $this->formatAssessmentForReport($user, $assessment, 'course', $course);
                        if ($this->matchesStatusFilter($assessmentData, $status)) {
                            $assessments[] = $assessmentData;
                        }
                    }
                }
            }

            // Lesson assessments within course modules
            if ($assessmentType === 'all' || $assessmentType === 'lesson') {
                foreach ($course->modules as $module) {
                    foreach ($module->lessons as $lesson) {
                        $lessonAssessments = Assessment::where('assessable_type', 'App\Models\Lesson')
                            ->where('assessable_id', $lesson->id)
                            ->when($requiredOnly, function ($query) {
                                return $query->where('is_required', true);
                            })
                            ->get();

                        foreach ($lessonAssessments as $assessment) {
                            $assessmentData = $this->formatAssessmentForReport($user, $assessment, 'course', $course);
                            if ($this->matchesStatusFilter($assessmentData, $status)) {
                                $assessments[] = $assessmentData;
                            }
                        }
                    }
                }
            }
        }

        return $assessments;
    }

    /**
     * Format assessment data for unified report.
     */
    private function formatAssessmentForReport(\App\Models\User $user, Assessment $assessment, string $learningPathType, $learningPath): array
    {
        $results = $this->assessmentService->getAssessmentResults($user, $assessment);
        $assessable = $assessment->assessable;

        return [
            'assessment_id' => $assessment->id,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'assessable_type' => class_basename($assessment->assessable_type),
            'assessable_title' => $assessable ? $assessable->title : 'Unknown',
            'learning_path_type' => $learningPathType,
            'learning_path_id' => $learningPath->id,
            'learning_path_title' => $learningPath->title,
            'is_required' => $assessment->is_required,
            'passing_score' => $assessment->passing_score,
            'max_attempts' => $assessment->max_attempts,
            'time_limit' => $assessment->time_limit,
            'has_passed' => $results['has_passed'],
            'best_score' => $results['best_attempt'] ? $results['best_attempt']->score : null,
            'max_score' => $results['best_attempt'] ? $results['best_attempt']->max_score : $assessment->getTotalPoints(),
            'attempts_count' => $results['attempts']->count(),
            'can_retake' => $results['can_retake'],
            'progress_contribution' => $assessment->getProgressContribution(),
            'completion_percentage' => $assessment->getCompletionPercentage($user),
            'last_attempt_date' => $results['best_attempt'] ? $results['best_attempt']->completed_at : null,
        ];
    }

    /**
     * Check if assessment matches status filter.
     */
    private function matchesStatusFilter(array $assessmentData, string $status): bool
    {
        if ($status === 'all') {
            return true;
        }

        switch ($status) {
            case 'passed':
                return $assessmentData['has_passed'];
            case 'failed':
                return $assessmentData['attempts_count'] > 0 && !$assessmentData['has_passed'];
            case 'not_attempted':
                return $assessmentData['attempts_count'] === 0;
            default:
                return true;
        }
    }

    /**
     * Calculate summary statistics for unified report.
     */
    private function calculateReportSummary(array $assessments): array
    {
        $total = count($assessments);
        $passed = collect($assessments)->where('has_passed', true)->count();
        $failed = collect($assessments)->where('attempts_count', '>', 0)->where('has_passed', false)->count();
        $notAttempted = collect($assessments)->where('attempts_count', 0)->count();
        $required = collect($assessments)->where('is_required', true)->count();
        $requiredPassed = collect($assessments)->where('is_required', true)->where('has_passed', true)->count();

        $totalContribution = collect($assessments)->sum('progress_contribution');
        $completedContribution = collect($assessments)->where('has_passed', true)->sum('progress_contribution');

        return [
            'total_assessments' => $total,
            'passed_assessments' => $passed,
            'failed_assessments' => $failed,
            'not_attempted_assessments' => $notAttempted,
            'required_assessments' => $required,
            'required_passed' => $requiredPassed,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'required_completion_rate' => $required > 0 ? round(($requiredPassed / $required) * 100, 2) : 0,
            'total_progress_contribution' => round($totalContribution, 2),
            'completed_progress_contribution' => round($completedContribution, 2),
            'progress_completion_rate' => $totalContribution > 0 ? round(($completedContribution / $totalContribution) * 100, 2) : 0,
            'average_score' => $this->calculateAverageScore($assessments),
            'assessment_types' => $this->getAssessmentTypeBreakdown($assessments),
            'learning_path_breakdown' => $this->getLearningPathBreakdown($assessments),
        ];
    }

    /**
     * Calculate average score across all assessments.
     */
    private function calculateAverageScore(array $assessments): float
    {
        $scoresWithMax = collect($assessments)
            ->filter(function ($assessment) {
                return $assessment['best_score'] !== null && $assessment['max_score'] > 0;
            })
            ->map(function ($assessment) {
                return ($assessment['best_score'] / $assessment['max_score']) * 100;
            });

        return $scoresWithMax->count() > 0 ? round($scoresWithMax->avg(), 2) : 0.0;
    }

    /**
     * Get breakdown by assessment type.
     */
    private function getAssessmentTypeBreakdown(array $assessments): array
    {
        $breakdown = collect($assessments)->groupBy('assessable_type');

        return $breakdown->map(function ($group, $type) {
            return [
                'total' => $group->count(),
                'passed' => $group->where('has_passed', true)->count(),
                'pass_rate' => $group->count() > 0 ? round(($group->where('has_passed', true)->count() / $group->count()) * 100, 2) : 0,
            ];
        })->toArray();
    }

    /**
     * Get breakdown by learning path type.
     */
    private function getLearningPathBreakdown(array $assessments): array
    {
        $breakdown = collect($assessments)->groupBy('learning_path_type');

        return $breakdown->map(function ($group, $type) {
            return [
                'total' => $group->count(),
                'passed' => $group->where('has_passed', true)->count(),
                'pass_rate' => $group->count() > 0 ? round(($group->where('has_passed', true)->count() / $group->count()) * 100, 2) : 0,
            ];
        })->toArray();
    }
}
