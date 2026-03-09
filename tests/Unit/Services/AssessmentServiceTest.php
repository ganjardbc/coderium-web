<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
use App\Services\AssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentService $assessmentService;
    private User $instructor;
    private User $learner;
    private Track $track;
    private Lesson $lesson;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assessmentService = new AssessmentService();

        // Create test users
        $this->instructor = User::factory()->create(['role' => 'instructor']);
        $this->learner = User::factory()->create(['role' => 'learner']);

        // Create test content hierarchy
        $this->track = Track::factory()->create(['instructor_id' => $this->instructor->id]);
        $level = $this->track->levels()->create([
            'title' => 'Test Level',
            'difficulty' => 'beginner',
            'is_published' => true,
        ]);
        $this->module = $level->modules()->create([
            'title' => 'Test Module',
            'is_published' => true,
        ]);
        $this->lesson = $this->module->lessons()->create([
            'title' => 'Test Lesson',
            'content' => 'Test content',
            'is_published' => true,
        ]);

        // Enroll learner in track
        TrackEnrollment::create([
            'user_id' => $this->learner->id,
            'track_id' => $this->track->id,
        ]);
    }

    public function test_create_assessment_with_multiple_choice_questions()
    {
        $assessmentData = [
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'title' => 'Test Assessment',
            'description' => 'A test assessment',
            'passing_score' => 70.0,
            'max_attempts' => 3,
            'time_limit' => 60,
            'is_required' => true,
            'questions' => [
                [
                    'question_text' => 'What is 2 + 2?',
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'explanation' => 'Basic arithmetic',
                    'options' => [
                        ['option_text' => '3', 'is_correct' => false],
                        ['option_text' => '4', 'is_correct' => true],
                        ['option_text' => '5', 'is_correct' => false],
                    ],
                ],
            ],
        ];

        $assessment = $this->assessmentService->createAssessment($assessmentData);

        $this->assertInstanceOf(Assessment::class, $assessment);
        $this->assertEquals('Test Assessment', $assessment->title);
        $this->assertEquals(70.0, $assessment->passing_score);
        $this->assertEquals(3, $assessment->max_attempts);
        $this->assertTrue($assessment->is_required);

        // Check questions were created
        $this->assertCount(1, $assessment->questions);
        $question = $assessment->questions->first();
        $this->assertEquals('What is 2 + 2?', $question->question_text);
        $this->assertEquals('multiple_choice', $question->question_type);
        $this->assertEquals(1, $question->points);

        // Check options were created
        $this->assertCount(3, $question->options);
        $correctOption = $question->options->where('is_correct', true)->first();
        $this->assertEquals('4', $correctOption->option_text);
    }

    public function test_create_assessment_with_true_false_questions()
    {
        $assessmentData = [
            'assessable_type' => 'App\Models\Module',
            'assessable_id' => $this->module->id,
            'title' => 'True/False Assessment',
            'passing_score' => 80.0,
            'max_attempts' => 2,
            'questions' => [
                [
                    'question_text' => 'PHP is a programming language.',
                    'question_type' => 'true_false',
                    'points' => 2,
                    'options' => [
                        ['option_text' => 'True', 'is_correct' => true],
                        ['option_text' => 'False', 'is_correct' => false],
                    ],
                ],
            ],
        ];

        $assessment = $this->assessmentService->createAssessment($assessmentData);

        $this->assertInstanceOf(Assessment::class, $assessment);
        $question = $assessment->questions->first();
        $this->assertEquals('true_false', $question->question_type);
        $this->assertCount(2, $question->options);
    }

    public function test_create_assessment_validation_fails_with_invalid_data()
    {
        $this->expectException(ValidationException::class);

        $this->assessmentService->createAssessment([
            'title' => '', // Invalid: empty title
            'passing_score' => 150, // Invalid: over 100
            'max_attempts' => 0, // Invalid: must be at least 1
        ]);
    }

    public function test_create_assessment_validation_fails_without_correct_answer()
    {
        $this->expectException(ValidationException::class);

        $assessmentData = [
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'title' => 'Test Assessment',
            'passing_score' => 70.0,
            'max_attempts' => 3,
            'questions' => [
                [
                    'question_text' => 'What is 2 + 2?',
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [
                        ['option_text' => '3', 'is_correct' => false],
                        ['option_text' => '4', 'is_correct' => false], // No correct answer
                        ['option_text' => '5', 'is_correct' => false],
                    ],
                ],
            ],
        ];

        $this->assessmentService->createAssessment($assessmentData);
    }

    public function test_submit_assessment_with_correct_answers()
    {
        // Create assessment
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'passing_score' => 70.0,
            'max_attempts' => 3,
        ]);

        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'multiple_choice',
            'points' => 10,
        ]);

        $correctOption = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => true,
        ]);

        QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => false,
        ]);

        // Submit assessment
        $answers = [
            $question->id => [
                'selected_options' => [$correctOption->id],
            ],
        ];

        $attempt = $this->assessmentService->submitAssessment($this->learner, $assessment, $answers);

        $this->assertInstanceOf(AssessmentAttempt::class, $attempt);
        $this->assertEquals(100.0, $attempt->score);
        $this->assertTrue($attempt->passed);
        $this->assertNotNull($attempt->completed_at);
    }

    public function test_submit_assessment_with_incorrect_answers()
    {
        // Create assessment
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'passing_score' => 70.0,
            'max_attempts' => 3,
        ]);

        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'multiple_choice',
            'points' => 10,
        ]);

        $correctOption = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => true,
        ]);

        $incorrectOption = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => false,
        ]);

        // Submit with incorrect answer
        $answers = [
            $question->id => [
                'selected_options' => [$incorrectOption->id],
            ],
        ];

        $attempt = $this->assessmentService->submitAssessment($this->learner, $assessment, $answers);

        $this->assertEquals(0.0, $attempt->score);
        $this->assertFalse($attempt->passed);
    }

    public function test_cannot_submit_assessment_without_enrollment()
    {
        $unenrolledUser = User::factory()->create(['role' => 'learner']);

        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->assessmentService->submitAssessment($unenrolledUser, $assessment, []);
    }

    public function test_cannot_exceed_max_attempts()
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'max_attempts' => 1,
        ]);

        // Create existing attempt
        AssessmentAttempt::factory()->create([
            'user_id' => $this->learner->id,
            'assessment_id' => $assessment->id,
            'completed_at' => now(),
        ]);

        $this->assertFalse($this->assessmentService->canUserTakeAssessment($this->learner, $assessment));
    }

    public function test_get_assessment_results()
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
        ]);

        $attempt = AssessmentAttempt::factory()->create([
            'user_id' => $this->learner->id,
            'assessment_id' => $assessment->id,
            'score' => 85.0,
            'passed' => true,
            'completed_at' => now(),
        ]);

        $results = $this->assessmentService->getAssessmentResults($this->learner, $assessment);

        $this->assertArrayHasKey('assessment', $results);
        $this->assertArrayHasKey('attempts', $results);
        $this->assertArrayHasKey('best_attempt', $results);
        $this->assertArrayHasKey('has_passed', $results);
        $this->assertTrue($results['has_passed']);
        $this->assertEquals($attempt->id, $results['best_attempt']->id);
    }

    public function test_get_assessment_for_taking_hides_correct_answers()
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
        ]);

        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
        ]);

        QuestionOption::factory()->create([
            'question_id' => $question->id,
            'is_correct' => true,
        ]);

        $assessmentData = $this->assessmentService->getAssessmentForTaking($this->learner, $assessment);

        $this->assertArrayHasKey('questions', $assessmentData);
        $questionData = $assessmentData['questions'][0];
        $this->assertArrayNotHasKey('is_correct', $questionData['options'][0]);
    }

    public function test_instructor_has_access_to_unpublished_assessments()
    {
        $unpublishedLesson = $this->module->lessons()->create([
            'title' => 'Unpublished Lesson',
            'content' => 'This is unpublished lesson content.',
            'is_published' => false,
        ]);

        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $unpublishedLesson->id,
        ]);

        $this->assertTrue($this->assessmentService->canUserTakeAssessment($this->instructor, $assessment));
        $this->assertFalse($this->assessmentService->canUserTakeAssessment($this->learner, $assessment));
    }

    public function test_grade_true_false_question_correctly()
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
        ]);

        $question = Question::factory()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'true_false',
            'points' => 5,
        ]);

        $trueOption = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'option_text' => 'True',
            'is_correct' => true,
        ]);

        $falseOption = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'option_text' => 'False',
            'is_correct' => false,
        ]);

        // Submit correct answer
        $answers = [
            $question->id => [
                'selected_options' => [$trueOption->id],
            ],
        ];

        $attempt = $this->assessmentService->submitAssessment($this->learner, $assessment, $answers);

        $this->assertEquals(100.0, $attempt->score);
        $this->assertTrue($attempt->passed);
    }

    public function test_can_user_progress_past_required_assessment()
    {
        $requiredAssessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => true,
        ]);

        $optionalAssessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => false,
        ]);

        // User can always progress past optional assessments
        $this->assertTrue($this->assessmentService->canUserProgressPastAssessment($this->learner, $optionalAssessment));

        // User cannot progress past required assessment without passing
        $this->assertFalse($this->assessmentService->canUserProgressPastAssessment($this->learner, $requiredAssessment));

        // Create a passing attempt
        AssessmentAttempt::factory()->create([
            'user_id' => $this->learner->id,
            'assessment_id' => $requiredAssessment->id,
            'passed' => true,
        ]);

        // Now user can progress
        $this->assertTrue($this->assessmentService->canUserProgressPastAssessment($this->learner, $requiredAssessment));
    }

    public function test_get_blocking_assessments()
    {
        $assessment1 = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => true,
        ]);

        $assessment2 = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => false,
        ]);

        $blockingAssessments = $this->assessmentService->getBlockingAssessments(
            $this->learner,
            'App\Models\Lesson',
            $this->lesson->id
        );

        // Should only return required assessments that haven't been passed
        $this->assertCount(1, $blockingAssessments);
        $this->assertEquals($assessment1->id, $blockingAssessments->first()->id);
    }

    public function test_check_content_access_with_blocking_assessments()
    {
        $requiredAssessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => true,
        ]);

        $accessCheck = $this->assessmentService->checkContentAccess($this->learner, 'lesson', $this->lesson->id);

        $this->assertFalse($accessCheck['can_access']);
        $this->assertEquals('Required assessments must be completed first', $accessCheck['reason']);
        $this->assertCount(1, $accessCheck['blocking_assessments']);
    }

    public function test_check_content_access_without_blocking_assessments()
    {
        $accessCheck = $this->assessmentService->checkContentAccess($this->learner, 'lesson', $this->lesson->id);

        $this->assertTrue($accessCheck['can_access']);
        $this->assertEmpty($accessCheck['blocking_assessments']);
    }

    public function test_get_immediate_feedback_for_passed_required_assessment()
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => true,
            'passing_score' => 70.0,
        ]);

        $attempt = AssessmentAttempt::factory()->create([
            'user_id' => $this->learner->id,
            'assessment_id' => $assessment->id,
            'score' => 85.0,
            'passed' => true,
            'completed_at' => now(),
        ]);

        $feedback = $this->assessmentService->getImmediateFeedback($attempt);

        $this->assertTrue($feedback['passed']);
        $this->assertTrue($feedback['progression']['is_required']);
        $this->assertTrue($feedback['progression']['can_progress']);
        $this->assertStringContainsString('Congratulations', $feedback['progression']['message']);
        $this->assertArrayHasKey('next_steps', $feedback);
    }

    public function test_get_immediate_feedback_for_failed_required_assessment()
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => true,
            'passing_score' => 70.0,
            'max_attempts' => 3,
        ]);

        $attempt = AssessmentAttempt::factory()->create([
            'user_id' => $this->learner->id,
            'assessment_id' => $assessment->id,
            'score' => 45.0,
            'passed' => false,
            'completed_at' => now(),
            'attempt_number' => 1,
        ]);

        $feedback = $this->assessmentService->getImmediateFeedback($attempt);

        $this->assertFalse($feedback['passed']);
        $this->assertTrue($feedback['progression']['is_required']);
        $this->assertFalse($feedback['progression']['can_progress']);
        $this->assertStringContainsString('need to pass', $feedback['progression']['message']);
        $this->assertTrue($feedback['can_retake']);
        $this->assertEquals(2, $feedback['remaining_attempts']);
    }

    public function test_get_immediate_feedback_for_optional_assessment()
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $this->lesson->id,
            'is_required' => false,
        ]);

        $attempt = AssessmentAttempt::factory()->create([
            'user_id' => $this->learner->id,
            'assessment_id' => $assessment->id,
            'score' => 45.0,
            'passed' => false,
            'completed_at' => now(),
        ]);

        $feedback = $this->assessmentService->getImmediateFeedback($attempt);

        $this->assertFalse($feedback['progression']['is_required']);
        $this->assertTrue($feedback['progression']['can_progress']);
        $this->assertStringContainsString('optional', $feedback['progression']['message']);
    }
}
