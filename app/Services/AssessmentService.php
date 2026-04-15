<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentService
{
    /**
     * Create a new assessment with support for all question types.
     *
     * @param array $data
     * @return Assessment
     * @throws ValidationException
     */
    public function createAssessment(array $data): Assessment
    {
        $this->validateAssessmentData($data);

        return DB::transaction(function () use ($data) {
            // Extract questions data
            $questionsData = $data['questions'] ?? [];
            unset($data['questions']);

            // Create assessment
            $assessment = Assessment::create($data);

            // Create questions if provided
            if (!empty($questionsData)) {
                $this->createQuestionsForAssessment($assessment, $questionsData);
            }

            return $assessment->load(['questions.options']);
        });
    }

    /**
     * Update an existing assessment.
     *
     * @param Assessment $assessment
     * @param array $data
     * @return Assessment
     * @throws ValidationException
     */
    public function updateAssessment(Assessment $assessment, array $data): Assessment
    {
        $this->validateAssessmentData($data, $assessment->id);

        return DB::transaction(function () use ($assessment, $data) {
            // Extract questions data
            $questionsData = $data['questions'] ?? null;
            unset($data['questions']);

            // Update assessment
            $assessment->update($data);

            // Update questions if provided
            if ($questionsData !== null) {
                $this->updateQuestionsForAssessment($assessment, $questionsData);
            }

            return $assessment->fresh(['questions.options']);
        });
    }

    /**
     * Submit an assessment attempt.
     *
     * @param User $user
     * @param Assessment $assessment
     * @param array $answers
     * @return AssessmentAttempt
     * @throws ValidationException
     */
    public function submitAssessment(User $user, Assessment $assessment, array $answers): AssessmentAttempt
    {
        $this->validateAssessmentSubmission($user, $assessment, $answers);

        return DB::transaction(function () use ($user, $assessment, $answers) {
            // Get or create attempt
            $attempt = $this->getOrCreateAttempt($user, $assessment);

            // Process answers
            $this->processAnswers($attempt, $answers);

            // Grade the assessment
            $this->gradeAssessment($attempt);

            // Mark attempt as completed
            $attempt->markCompleted();

            // Check for achievements after assessment completion
            $this->checkAchievementsAfterAssessmentCompletion($user, $attempt);

            return $attempt->load(['answers.question', 'assessment']);
        });
    }

    /**
     * Grade an assessment attempt.
     *
     * @param AssessmentAttempt $attempt
     * @return float
     */
    public function gradeAssessment(AssessmentAttempt $attempt): float
    {
        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($attempt->assessment->questions as $question) {
            $totalPoints += $question->points;

            $answer = $attempt->answers()
                ->where('question_id', $question->id)
                ->first();

            if ($answer) {
                $pointsEarned = $this->gradeAnswer($question, $answer);
                $answer->update([
                    'points_earned' => $pointsEarned,
                    'is_correct' => $pointsEarned > 0,
                ]);
                $earnedPoints += $pointsEarned;
            }
        }

        // Update attempt with final score
        $score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $score >= $attempt->assessment->passing_score;

        $attempt->update([
            'score' => $score,
            'max_score' => $totalPoints,
            'passed' => $passed,
        ]);

        return $score;
    }

    /**
     * Check if a user can take an assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return bool
     */
    public function canUserTakeAssessment(User $user, Assessment $assessment): bool
    {
        // Check if user has access to the assessable content
        if (!$this->hasAccessToAssessable($user, $assessment)) {
            return false;
        }

        // Check if user has already passed
        if ($assessment->hasUserPassed($user)) {
            return false;
        }

        // Check attempt limits
        $attemptCount = $assessment->attempts()
            ->where('user_id', $user->id)
            ->count();

        return $attemptCount < $assessment->max_attempts;
    }

    /**
     * Get assessment results for a user.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return array
     */
    public function getAssessmentResults(User $user, Assessment $assessment): array
    {
        $attempts = $assessment->attempts()
            ->where('user_id', $user->id)
            ->with(['answers.question.options'])
            ->orderBy('attempt_number', 'desc')
            ->get();

        $bestAttempt = $assessment->getBestAttempt($user);
        $hasPassed = $assessment->hasUserPassed($user);

        return [
            'assessment' => $assessment->load(['questions.options']),
            'attempts' => $attempts,
            'best_attempt' => $bestAttempt,
            'has_passed' => $hasPassed,
            'can_retake' => $this->canUserTakeAssessment($user, $assessment),
            'remaining_attempts' => max(0, $assessment->max_attempts - $attempts->count()),
        ];
    }

    /**
     * Get assessment for taking (without answers).
     *
     * @param User $user
     * @param Assessment $assessment
     * @return array
     * @throws ValidationException
     */
    public function getAssessmentForTaking(User $user, Assessment $assessment): array
    {
        if (!$this->canUserTakeAssessment($user, $assessment)) {
            throw ValidationException::withMessages([
                'assessment' => 'You cannot take this assessment at this time.',
            ]);
        }

        // Load questions without revealing correct answers
        $questions = $assessment->questions()
            ->with(['options' => function ($query) {
                $query->select(['id', 'question_id', 'option_text', 'order_index']);
            }])
            ->get()
            ->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'points' => $question->points,
                    'options' => $question->options,
                ];
            });

        return [
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'description' => $assessment->description,
                'time_limit' => $assessment->time_limit,
                'passing_score' => $assessment->passing_score,
                'max_attempts' => $assessment->max_attempts,
            ],
            'questions' => $questions,
            'attempt_number' => $this->getNextAttemptNumber($user, $assessment),
        ];
    }

    /**
     * Validate assessment data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateAssessmentData(array $data, ?int $excludeId = null): void
    {
        $rules = [
            'assessable_type' => 'required|in:App\Models\Lesson,App\Models\Module',
            'assessable_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'passing_score' => 'required|numeric|min:0|max:100',
            'max_attempts' => 'required|integer|min:1|max:10',
            'time_limit' => 'nullable|integer|min:1|max:300', // Max 5 hours
            'is_required' => 'boolean',
            'questions' => 'nullable|array',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.question_type' => 'required_with:questions|in:multiple_choice,true_false,code_output,conceptual',
            'questions.*.points' => 'required_with:questions|integer|min:1|max:10',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,true_false|array',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string',
            'questions.*.options.*.is_correct' => 'required_with:questions.*.options|boolean',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate assessable exists
        if (isset($data['assessable_type']) && isset($data['assessable_id'])) {
            $this->validateAssessableExists($data['assessable_type'], $data['assessable_id']);
        }

        // Validate questions if provided
        if (isset($data['questions'])) {
            $this->validateQuestions($data['questions']);
        }
    }

    /**
     * Validate assessable exists.
     *
     * @param string $type
     * @param int $id
     * @throws ValidationException
     */
    private function validateAssessableExists(string $type, int $id): void
    {
        $model = $type === 'App\Models\Lesson' ? Lesson::class : Module::class;

        if (!$model::find($id)) {
            throw ValidationException::withMessages([
                'assessable_id' => 'The selected assessable content does not exist.',
            ]);
        }
    }

    /**
     * Validate questions data.
     *
     * @param array $questions
     * @throws ValidationException
     */
    private function validateQuestions(array $questions): void
    {
        foreach ($questions as $index => $questionData) {
            $questionType = $questionData['question_type'] ?? '';

            // Validate options for multiple choice and true/false
            if (in_array($questionType, ['multiple_choice', 'true_false'])) {
                $options = $questionData['options'] ?? [];

                if (empty($options)) {
                    throw ValidationException::withMessages([
                        "questions.{$index}.options" => 'Options are required for this question type.',
                    ]);
                }

                // Check for at least one correct answer
                $hasCorrectAnswer = collect($options)->contains('is_correct', true);
                if (!$hasCorrectAnswer) {
                    throw ValidationException::withMessages([
                        "questions.{$index}.options" => 'At least one option must be marked as correct.',
                    ]);
                }

                // For true/false, ensure exactly 2 options
                if ($questionType === 'true_false' && count($options) !== 2) {
                    throw ValidationException::withMessages([
                        "questions.{$index}.options" => 'True/false questions must have exactly 2 options.',
                    ]);
                }
            }
        }
    }

    /**
     * Create questions for an assessment.
     *
     * @param Assessment $assessment
     * @param array $questionsData
     */
    private function createQuestionsForAssessment(Assessment $assessment, array $questionsData): void
    {
        foreach ($questionsData as $index => $questionData) {
            $optionsData = $questionData['options'] ?? [];
            unset($questionData['options']);

            $questionData['assessment_id'] = $assessment->id;
            $questionData['order_index'] = $index;

            $question = Question::create($questionData);

            // Create options if provided
            if (!empty($optionsData)) {
                foreach ($optionsData as $optionIndex => $optionData) {
                    $optionData['question_id'] = $question->id;
                    $optionData['order_index'] = $optionIndex;
                    QuestionOption::create($optionData);
                }
            }
        }
    }

    /**
     * Update questions for an assessment.
     *
     * @param Assessment $assessment
     * @param array $questionsData
     */
    private function updateQuestionsForAssessment(Assessment $assessment, array $questionsData): void
    {
        // Delete existing questions and options
        $assessment->questions()->delete();

        // Create new questions
        $this->createQuestionsForAssessment($assessment, $questionsData);
    }

    /**
     * Validate assessment submission.
     *
     * @param User $user
     * @param Assessment $assessment
     * @param array $answers
     * @throws ValidationException
     */
    private function validateAssessmentSubmission(User $user, Assessment $assessment, array $answers): void
    {
        if (!$this->canUserTakeAssessment($user, $assessment)) {
            throw ValidationException::withMessages([
                'assessment' => 'You cannot submit this assessment at this time.',
            ]);
        }

        // Validate all questions are answered
        $questionIds = $assessment->questions()->pluck('id')->toArray();
        $answeredQuestionIds = array_keys($answers);

        $missingQuestions = array_diff($questionIds, $answeredQuestionIds);
        if (!empty($missingQuestions)) {
            throw ValidationException::withMessages([
                'answers' => 'All questions must be answered before submission.',
            ]);
        }
    }

    /**
     * Get or create assessment attempt.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return AssessmentAttempt
     */
    private function getOrCreateAttempt(User $user, Assessment $assessment): AssessmentAttempt
    {
        // Check for existing incomplete attempt
        $existingAttempt = $assessment->attempts()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->first();

        if ($existingAttempt) {
            return $existingAttempt;
        }

        // Create new attempt
        return AssessmentAttempt::create([
            'user_id' => $user->id,
            'assessment_id' => $assessment->id,
            'score' => 0,
            'max_score' => $assessment->getTotalPoints(),
            'passed' => false,
            'started_at' => now(),
            'attempt_number' => $this->getNextAttemptNumber($user, $assessment),
        ]);
    }

    /**
     * Process answers for an attempt.
     *
     * @param AssessmentAttempt $attempt
     * @param array $answers
     */
    private function processAnswers(AssessmentAttempt $attempt, array $answers): void
    {
        // Delete existing answers for this attempt
        $attempt->answers()->delete();

        foreach ($answers as $questionId => $answerData) {
            AttemptAnswer::create([
                'assessment_attempt_id' => $attempt->id,
                'question_id' => $questionId,
                'selected_options' => $answerData['selected_options'] ?? null,
                'answer_text' => $answerData['answer_text'] ?? null,
                'is_correct' => false, // Will be set during grading
                'points_earned' => 0, // Will be set during grading
            ]);
        }
    }

    /**
     * Grade a single answer.
     *
     * @param Question $question
     * @param AttemptAnswer $answer
     * @return float
     */
    private function gradeAnswer(Question $question, AttemptAnswer $answer): float
    {
        switch ($question->question_type) {
            case 'multiple_choice':
                return $this->gradeMultipleChoiceAnswer($question, $answer);

            case 'true_false':
                return $this->gradeTrueFalseAnswer($question, $answer);

            case 'code_output':
            case 'conceptual':
                // These require manual grading - return 0 for now
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Grade multiple choice answer.
     *
     * @param Question $question
     * @param AttemptAnswer $answer
     * @return float
     */
    private function gradeMultipleChoiceAnswer(Question $question, AttemptAnswer $answer): float
    {
        $selectedOptions = $answer->selected_options ?? [];
        $correctOptions = $question->correctOptions()->pluck('id')->toArray();

        // Check if selected options match correct options exactly
        sort($selectedOptions);
        sort($correctOptions);

        return $selectedOptions === $correctOptions ? $question->points : 0;
    }

    /**
     * Grade true/false answer.
     *
     * @param Question $question
     * @param AttemptAnswer $answer
     * @return float
     */
    private function gradeTrueFalseAnswer(Question $question, AttemptAnswer $answer): float
    {
        $selectedOptions = $answer->selected_options ?? [];
        $correctOptions = $question->correctOptions()->pluck('id')->toArray();

        // For true/false, should have exactly one selected option
        if (count($selectedOptions) !== 1) {
            return 0;
        }

        return in_array($selectedOptions[0], $correctOptions) ? $question->points : 0;
    }

    /**
     * Get next attempt number for user and assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return int
     */
    private function getNextAttemptNumber(User $user, Assessment $assessment): int
    {
        $maxAttempt = $assessment->attempts()
            ->where('user_id', $user->id)
            ->max('attempt_number');

        return ($maxAttempt ?? 0) + 1;
    }

    /**
     * Check if user can progress past a required assessment.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return bool
     */
    public function canUserProgressPastAssessment(User $user, Assessment $assessment): bool
    {
        // If assessment is not required, user can always progress
        if (!$assessment->is_required) {
            return true;
        }

        // Check if user has passed the assessment
        return $assessment->hasUserPassed($user);
    }

    /**
     * Get all required assessments that block progression for a user.
     *
     * @param User $user
     * @param string $assessableType
     * @param int $assessableId
     * @return Collection
     */
    public function getBlockingAssessments(User $user, string $assessableType, int $assessableId): Collection
    {
        return Assessment::where('assessable_type', $assessableType)
            ->where('assessable_id', $assessableId)
            ->where('is_required', true)
            ->get()
            ->filter(function ($assessment) use ($user) {
                return !$assessment->hasUserPassed($user);
            });
    }

    /**
     * Check if user can access content based on required assessments.
     *
     * @param User $user
     * @param string $contentType
     * @param int $contentId
     * @return array
     */
    public function checkContentAccess(User $user, string $contentType, int $contentId): array
    {
        // Get all required assessments for this content and its prerequisites
        $blockingAssessments = collect();

        if ($contentType === 'lesson') {
            $lesson = Lesson::find($contentId);
            if (!$lesson) {
                return ['can_access' => false, 'reason' => 'Content not found'];
            }

            // Check lesson-level assessments
            $lessonAssessments = $this->getBlockingAssessments($user, 'App\Models\Lesson', $lesson->id);
            $blockingAssessments = $blockingAssessments->merge($lessonAssessments);

            // Check module-level assessments
            $moduleAssessments = $this->getBlockingAssessments($user, 'App\Models\Module', $lesson->module_id);
            $blockingAssessments = $blockingAssessments->merge($moduleAssessments);

            // Check previous lessons in the same module
            $previousLessons = Lesson::where('module_id', $lesson->module_id)
                ->where('order_index', '<', $lesson->order_index)
                ->where('is_published', true)
                ->get();

            foreach ($previousLessons as $prevLesson) {
                $prevAssessments = $this->getBlockingAssessments($user, 'App\Models\Lesson', $prevLesson->id);
                $blockingAssessments = $blockingAssessments->merge($prevAssessments);
            }
        } elseif ($contentType === 'module') {
            $module = Module::find($contentId);
            if (!$module) {
                return ['can_access' => false, 'reason' => 'Content not found'];
            }

            // Check module-level assessments
            $moduleAssessments = $this->getBlockingAssessments($user, 'App\Models\Module', $module->id);
            $blockingAssessments = $blockingAssessments->merge($moduleAssessments);

            // Check previous modules in the same level
            $previousModules = Module::where('level_id', $module->level_id)
                ->where('order_index', '<', $module->order_index)
                ->where('is_published', true)
                ->get();

            foreach ($previousModules as $prevModule) {
                $prevAssessments = $this->getBlockingAssessments($user, 'App\Models\Module', $prevModule->id);
                $blockingAssessments = $blockingAssessments->merge($prevAssessments);
            }
        }

        if ($blockingAssessments->isEmpty()) {
            return [
                'can_access' => true,
                'blocking_assessments' => [],
            ];
        }

        return [
            'can_access' => false,
            'reason' => 'Required assessments must be completed first',
            'blocking_assessments' => $blockingAssessments->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'assessable_type' => $assessment->assessable_type,
                    'assessable_id' => $assessment->assessable_id,
                ];
            })->toArray(),
        ];
    }

    /**
     * Provide immediate feedback after assessment completion.
     *
     * @param AssessmentAttempt $attempt
     * @return array
     */
    public function getImmediateFeedback(AssessmentAttempt $attempt): array
    {
        $assessment = $attempt->assessment;
        $user = $attempt->user;

        $feedback = [
            'attempt_id' => $attempt->id,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'percentage' => $attempt->getPercentageScore(),
            'passed' => $attempt->passed,
            'passing_score' => $assessment->passing_score,
            'completed_at' => $attempt->completed_at,
            'time_taken' => $attempt->time_taken,
            'attempt_number' => $attempt->attempt_number,
            'can_retake' => $this->canUserTakeAssessment($user, $assessment),
            'remaining_attempts' => max(0, $assessment->max_attempts - $attempt->attempt_number),
        ];

        // Add progression information
        if ($assessment->is_required) {
            $feedback['progression'] = [
                'is_required' => true,
                'can_progress' => $attempt->passed,
                'message' => $attempt->passed
                    ? 'Congratulations! You can now proceed to the next content.'
                    : 'You need to pass this assessment to continue. Please try again.',
            ];
        } else {
            $feedback['progression'] = [
                'is_required' => false,
                'can_progress' => true,
                'message' => 'This assessment is optional. You can continue regardless of your score.',
            ];
        }

        // Add detailed question feedback for completed attempts
        if ($attempt->isCompleted()) {
            $feedback['question_feedback'] = $this->getQuestionFeedback($attempt);
        }

        // Add next steps
        $feedback['next_steps'] = $this->getNextSteps($attempt);

        return $feedback;
    }

    /**
     * Get detailed feedback for each question in an attempt.
     *
     * @param AssessmentAttempt $attempt
     * @return array
     */
    private function getQuestionFeedback(AssessmentAttempt $attempt): array
    {
        $questionFeedback = [];

        foreach ($attempt->assessment->questions as $question) {
            $answer = $attempt->answers()
                ->where('question_id', $question->id)
                ->first();

            $feedback = [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'points_earned' => $answer ? $answer->points_earned : 0,
                'is_correct' => $answer ? $answer->is_correct : false,
            ];

            // Add explanation if available
            if ($question->explanation) {
                $feedback['explanation'] = $question->explanation;
            }

            // Add correct answers for multiple choice and true/false
            if (in_array($question->question_type, ['multiple_choice', 'true_false'])) {
                $feedback['correct_options'] = $question->correctOptions()
                    ->pluck('option_text')
                    ->toArray();

                if ($answer && $answer->selected_options) {
                    $selectedOptions = $question->options()
                        ->whereIn('id', $answer->selected_options)
                        ->pluck('option_text')
                        ->toArray();
                    $feedback['selected_options'] = $selectedOptions;
                }
            }

            $questionFeedback[] = $feedback;
        }

        return $questionFeedback;
    }

    /**
     * Get next steps for the user after assessment completion.
     *
     * @param AssessmentAttempt $attempt
     * @return array
     */
    private function getNextSteps(AssessmentAttempt $attempt): array
    {
        $assessment = $attempt->assessment;
        $user = $attempt->user;

        $nextSteps = [];

        if ($attempt->passed) {
            if ($assessment->is_required) {
                $nextSteps[] = [
                    'type' => 'success',
                    'message' => 'You have successfully completed this required assessment.',
                    'action' => 'continue',
                ];
            } else {
                $nextSteps[] = [
                    'type' => 'success',
                    'message' => 'Great job on completing this assessment!',
                    'action' => 'continue',
                ];
            }
        } else {
            if ($this->canUserTakeAssessment($user, $assessment)) {
                $remainingAttempts = $assessment->max_attempts - $attempt->attempt_number;
                $nextSteps[] = [
                    'type' => 'retry',
                    'message' => "You can retry this assessment. You have {$remainingAttempts} attempts remaining.",
                    'action' => 'retry',
                ];
            } else {
                $nextSteps[] = [
                    'type' => 'blocked',
                    'message' => 'You have used all available attempts for this assessment.',
                    'action' => 'contact_instructor',
                ];
            }

            if ($assessment->is_required) {
                $nextSteps[] = [
                    'type' => 'warning',
                    'message' => 'This is a required assessment. You must pass it to continue.',
                    'action' => 'study_more',
                ];
            }
        }

        return $nextSteps;
    }

    /**
     * Check if user has access to assessable content.
     *
     * @param User $user
     * @param Assessment $assessment
     * @return bool
     */
    private function hasAccessToAssessable(User $user, Assessment $assessment): bool
    {
        $assessable = $assessment->assessable;

        if (!$assessable) {
            return false;
        }

        // If user is instructor, they have access
        if ($user->role === 'instructor') {
            return true;
        }

        // Check if content is published
        if (!$assessable->is_published) {
            return false;
        }

        // Check enrollment for the track
        if ($assessable instanceof Lesson) {
            $track = $assessable->module->level->track;
        } else { // Module
            $track = $assessable->level->track;
        }

        return $track->enrollments()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Check for achievements after assessment completion.
     *
     * @param User $user
     * @param AssessmentAttempt $attempt
     */
    private function checkAchievementsAfterAssessmentCompletion(User $user, AssessmentAttempt $attempt): void
    {
        try {
            // Avoid circular dependency by resolving AchievementService here
            $achievementService = app(AchievementService::class);
            $achievementService->checkAssessmentAchievements($user, $attempt);
        } catch (\Exception $e) {
            // Log the error but don't fail the assessment completion
            \Log::error('Failed to check achievements after assessment completion', [
                'user_id' => $user->id,
                'attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
