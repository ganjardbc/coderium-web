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

        $assessmentProgress = [];

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
                            ];
                        }
                    }
                }
            }

            $assessmentProgress[] = [
                'track_id' => $track->id,
                'track_title' => $track->title,
                'assessments' => $trackAssessments,
                'total_assessments' => count($trackAssessments),
                'passed_assessments' => collect($trackAssessments)->where('has_passed', true)->count(),
                'required_assessments' => collect($trackAssessments)->where('is_required', true)->count(),
                'passed_required' => collect($trackAssessments)->where('is_required', true)->where('has_passed', true)->count(),
            ];
        }

        return response()->json([
            'user_id' => $user->id,
            'tracks' => $assessmentProgress,
            'summary' => [
                'total_tracks' => count($assessmentProgress),
                'total_assessments' => collect($assessmentProgress)->sum('total_assessments'),
                'total_passed' => collect($assessmentProgress)->sum('passed_assessments'),
                'total_required' => collect($assessmentProgress)->sum('required_assessments'),
                'total_required_passed' => collect($assessmentProgress)->sum('passed_required'),
            ],
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
}
