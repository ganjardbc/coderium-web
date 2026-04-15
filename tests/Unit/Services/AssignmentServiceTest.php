<?php

namespace Tests\Unit\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentService $assignmentService;
    private User $instructor;
    private User $learner;
    private Track $track;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignmentService = new AssignmentService();

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

        // Enroll learner in track
        TrackEnrollment::create([
            'user_id' => $this->learner->id,
            'track_id' => $this->track->id,
        ]);

        // Set up fake storage
        Storage::fake('private');
    }

    public function test_create_assignment_successfully()
    {
        $assignmentData = [
            'module_id' => $this->module->id,
            'title' => 'Test Assignment',
            'description' => 'This is a test assignment for the module.',
            'instructions' => 'Complete the following tasks: 1. Create a function 2. Write tests',
            'evaluation_checklist' => "- Function works correctly\n- Tests are comprehensive\n- Code is well documented",
            'due_date' => now()->addWeek(),
            'is_published' => true,
        ];

        $assignment = $this->assignmentService->createAssignment($assignmentData);

        $this->assertInstanceOf(Assignment::class, $assignment);
        $this->assertEquals('Test Assignment', $assignment->title);
        $this->assertEquals($this->module->id, $assignment->module_id);
        $this->assertTrue($assignment->is_published);
        $this->assertIsArray($assignment->evaluation_checklist);
        $this->assertCount(3, $assignment->evaluation_checklist);
    }

    public function test_create_assignment_validation_fails_with_invalid_data()
    {
        $this->expectException(ValidationException::class);

        $this->assignmentService->createAssignment([
            'module_id' => 999999, // Non-existent module
            'title' => '', // Empty title
            'description' => '', // Empty description
            'instructions' => '', // Empty instructions
            'due_date' => now()->subDay(), // Past date
        ]);
    }

    public function test_submit_assignment_with_repository_url()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        $submissionData = [
            'repository_url' => 'https://github.com/user/repo',
            'submission_notes' => 'This is my submission with additional notes.',
        ];

        $submission = $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            $submissionData
        );

        $this->assertInstanceOf(AssignmentSubmission::class, $submission);
        $this->assertEquals('https://github.com/user/repo', $submission->repository_url);
        $this->assertEquals('This is my submission with additional notes.', $submission->submission_notes);
        $this->assertEquals($this->learner->id, $submission->user_id);
        $this->assertEquals($assignment->id, $submission->assignment_id);
        $this->assertNotNull($submission->submitted_at);
        $this->assertNull($submission->graded_at);
    }

    public function test_submit_assignment_with_file_uploads()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        $file = UploadedFile::fake()->create('assignment.pdf', 1024, 'application/pdf');

        $submissionData = [
            'submission_notes' => 'Submitted with file attachment.',
        ];

        $submission = $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            $submissionData,
            [$file]
        );

        $this->assertInstanceOf(AssignmentSubmission::class, $submission);
        $this->assertIsArray($submission->file_attachments);
        $this->assertCount(1, $submission->file_attachments);
        $this->assertEquals('assignment.pdf', $submission->file_attachments[0]['original_name']);
        $this->assertEquals('application/pdf', $submission->file_attachments[0]['mime_type']);

        // Verify file was stored
        $this->assertTrue(Storage::disk('private')->exists($submission->file_attachments[0]['path']));
    }

    public function test_submit_assignment_validation_fails_without_content()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        $this->expectException(ValidationException::class);

        $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            [] // No repository URL or files
        );
    }

    public function test_submit_assignment_fails_after_due_date()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->subDay(), // Past due date
        ]);

        $this->expectException(ValidationException::class);

        $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            ['repository_url' => 'https://github.com/user/repo']
        );
    }

    public function test_submit_assignment_fails_without_enrollment()
    {
        $unenrolledUser = User::factory()->create(['role' => 'learner']);

        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        $this->expectException(ValidationException::class);

        $this->assignmentService->submitAssignment(
            $assignment,
            $unenrolledUser,
            ['repository_url' => 'https://github.com/user/repo']
        );
    }

    public function test_update_existing_submission_before_grading()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        // Create initial submission
        $initialSubmission = $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            ['repository_url' => 'https://github.com/user/repo-v1']
        );

        // Update submission
        $updatedSubmission = $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            ['repository_url' => 'https://github.com/user/repo-v2']
        );

        $this->assertEquals($initialSubmission->id, $updatedSubmission->id);
        $this->assertEquals('https://github.com/user/repo-v2', $updatedSubmission->repository_url);
    }

    public function test_cannot_resubmit_graded_assignment()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->learner->id,
            'grade' => 85.0,
            'graded_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            ['repository_url' => 'https://github.com/user/repo-new']
        );
    }

    public function test_grade_submission_successfully()
    {
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => Assignment::factory()->create(['module_id' => $this->module->id])->id,
            'user_id' => $this->learner->id,
            'graded_at' => null,
        ]);

        $gradingData = [
            'grade' => 92.5,
            'feedback' => 'Excellent work! The code is well-structured and documented.',
        ];

        $gradedSubmission = $this->assignmentService->gradeSubmission($submission, $gradingData);

        $this->assertEquals(92.5, $gradedSubmission->grade);
        $this->assertEquals('Excellent work! The code is well-structured and documented.', $gradedSubmission->feedback);
        $this->assertNotNull($gradedSubmission->graded_at);
        $this->assertTrue($gradedSubmission->isGraded());
    }

    public function test_grade_submission_validation_fails_with_invalid_grade()
    {
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => Assignment::factory()->create(['module_id' => $this->module->id])->id,
            'user_id' => $this->learner->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->assignmentService->gradeSubmission($submission, [
            'grade' => 150, // Invalid: over 100
            'feedback' => 'Test feedback',
        ]);
    }

    public function test_get_assignments_for_module()
    {
        $assignment1 = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'is_published' => true,
            'due_date' => now()->addDays(7),
        ]);

        $assignment2 = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'is_published' => false,
            'due_date' => now()->addDays(14),
        ]);

        $assignment3 = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'is_published' => true,
            'due_date' => now()->addDays(3),
        ]);

        $publishedAssignments = $this->assignmentService->getAssignmentsForModule($this->module, true);
        $allAssignments = $this->assignmentService->getAssignmentsForModule($this->module, false);

        $this->assertCount(2, $publishedAssignments);
        $this->assertCount(3, $allAssignments);

        // Check ordering by due date
        $this->assertEquals($assignment3->id, $publishedAssignments->first()->id); // Earlier due date first
    }

    public function test_get_assignment_with_user_status()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addDays(7)->startOfDay(), // Use start of day for precise calculation
        ]);

        // Test without submission
        $status = $this->assignmentService->getAssignmentWithUserStatus($assignment, $this->learner);

        $this->assertFalse($status['has_submitted']);
        $this->assertFalse($status['is_graded']);
        $this->assertFalse($status['is_overdue']);
        $this->assertTrue($status['can_submit']);
        $this->assertGreaterThanOrEqual(6, $status['days_until_due']);
        $this->assertLessThanOrEqual(7, $status['days_until_due']);

        // Test with submission
        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->learner->id,
            'grade' => 85.0,
            'graded_at' => now(),
        ]);

        $statusWithSubmission = $this->assignmentService->getAssignmentWithUserStatus($assignment, $this->learner);

        $this->assertTrue($statusWithSubmission['has_submitted']);
        $this->assertTrue($statusWithSubmission['is_graded']);
        $this->assertFalse($statusWithSubmission['can_submit']); // Cannot resubmit graded assignment
    }

    public function test_get_submissions_for_grading()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
        ]);

        $gradedSubmission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->learner->id,
            'grade' => 85.0,
            'graded_at' => now(),
        ]);

        $ungradedSubmission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => User::factory()->create(['role' => 'learner'])->id,
            'graded_at' => null,
        ]);

        // Get all submissions
        $allSubmissions = $this->assignmentService->getSubmissionsForGrading($assignment);
        $this->assertCount(2, $allSubmissions);

        // Get only ungraded submissions
        $ungradedSubmissions = $this->assignmentService->getSubmissionsForGrading($assignment, [
            'graded' => false,
        ]);
        $this->assertCount(1, $ungradedSubmissions);
        $this->assertEquals($ungradedSubmission->id, $ungradedSubmissions->first()->id);

        // Get only graded submissions
        $gradedSubmissions = $this->assignmentService->getSubmissionsForGrading($assignment, [
            'graded' => true,
        ]);
        $this->assertCount(1, $gradedSubmissions);
        $this->assertEquals($gradedSubmission->id, $gradedSubmissions->first()->id);
    }

    public function test_get_assignment_statistics()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        // Create additional enrolled users
        $learner2 = User::factory()->create(['role' => 'learner']);
        $learner3 = User::factory()->create(['role' => 'learner']);

        TrackEnrollment::create(['user_id' => $learner2->id, 'track_id' => $this->track->id]);
        TrackEnrollment::create(['user_id' => $learner3->id, 'track_id' => $this->track->id]);

        // Create submissions
        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->learner->id,
            'grade' => 85.0,
            'graded_at' => now(),
            'submitted_at' => now()->subDays(2),
        ]);

        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $learner2->id,
            'grade' => 92.0,
            'graded_at' => now(),
            'submitted_at' => now()->subDay(),
        ]);

        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $learner3->id,
            'graded_at' => null,
            'submitted_at' => now(),
        ]);

        $stats = $this->assignmentService->getAssignmentStatistics($assignment);

        $this->assertEquals(3, $stats['total_enrolled']);
        $this->assertEquals(3, $stats['total_submissions']);
        $this->assertEquals(100.0, $stats['submission_rate']);
        $this->assertEquals(2, $stats['graded_submissions']);
        $this->assertEquals(1, $stats['pending_grading']);
        $this->assertEquals(0, $stats['overdue_submissions']);
        $this->assertEquals(88.5, $stats['average_grade']); // (85 + 92) / 2
        $this->assertArrayHasKey('grade_distribution', $stats);
        $this->assertArrayHasKey('submission_timeline', $stats);
    }

    public function test_bulk_grade_submissions()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
        ]);

        $submission1 = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->learner->id,
        ]);

        $submission2 = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => User::factory()->create(['role' => 'learner'])->id,
        ]);

        $gradingData = [
            $submission1->id => [
                'grade' => 88.0,
                'feedback' => 'Good work on submission 1',
            ],
            $submission2->id => [
                'grade' => 95.0,
                'feedback' => 'Excellent work on submission 2',
            ],
        ];

        $results = $this->assignmentService->bulkGradeSubmissions($assignment, $gradingData);

        $this->assertArrayHasKey('success', $results);
        $this->assertCount(2, $results['success']);
        $this->assertContains($submission1->id, $results['success']);
        $this->assertContains($submission2->id, $results['success']);

        // Verify submissions were graded
        $submission1->refresh();
        $submission2->refresh();

        $this->assertEquals(88.0, $submission1->grade);
        $this->assertEquals(95.0, $submission2->grade);
        $this->assertNotNull($submission1->graded_at);
        $this->assertNotNull($submission2->graded_at);
    }

    public function test_export_submissions_to_csv()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
        ]);

        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->learner->id,
            'grade' => 85.0,
            'repository_url' => 'https://github.com/user/repo',
            'feedback' => 'Good work',
            'submitted_at' => now(),
        ]);

        $csv = $this->assignmentService->exportSubmissions($assignment, 'csv');

        $this->assertStringContainsString('Student Name,Email,Submitted At,Grade,Repository URL,Feedback', $csv);
        $this->assertStringContainsString($this->learner->name, $csv);
        $this->assertStringContainsString($this->learner->email, $csv);
        $this->assertStringContainsString('85', $csv);
        $this->assertStringContainsString('https://github.com/user/repo', $csv);
    }

    public function test_export_submissions_to_json()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
        ]);

        AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->learner->id,
            'grade' => 85.0,
            'repository_url' => 'https://github.com/user/repo',
        ]);

        $json = $this->assignmentService->exportSubmissions($assignment, 'json');
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($this->learner->name, $data[0]['student_name']);
        $this->assertEquals(85.0, $data[0]['grade']);
        $this->assertEquals('https://github.com/user/repo', $data[0]['repository_url']);
    }

    public function test_file_upload_validation_rejects_large_files()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        $largeFile = UploadedFile::fake()->create('large.pdf', 15000, 'application/pdf'); // 15MB

        $this->expectException(ValidationException::class);

        $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            ['submission_notes' => 'Test'],
            [$largeFile]
        );
    }

    public function test_file_upload_validation_rejects_invalid_extensions()
    {
        $assignment = Assignment::factory()->create([
            'module_id' => $this->module->id,
            'due_date' => now()->addWeek(),
        ]);

        $invalidFile = UploadedFile::fake()->create('malware.exe', 1024, 'application/octet-stream');

        $this->expectException(ValidationException::class);

        $this->assignmentService->submitAssignment(
            $assignment,
            $this->learner,
            ['submission_notes' => 'Test'],
            [$invalidFile]
        );
    }

    public function test_process_evaluation_checklist_from_string()
    {
        $assignmentData = [
            'module_id' => $this->module->id,
            'title' => 'Test Assignment',
            'description' => 'Test description',
            'instructions' => 'Test instructions',
            'evaluation_checklist' => "- [ ] Function works correctly\n* Code is well documented\n- Tests are comprehensive",
        ];

        $assignment = $this->assignmentService->createAssignment($assignmentData);

        $this->assertIsArray($assignment->evaluation_checklist);
        $this->assertCount(3, $assignment->evaluation_checklist);
        $this->assertEquals('Function works correctly', $assignment->evaluation_checklist[0]['item']);
        $this->assertEquals('Code is well documented', $assignment->evaluation_checklist[1]['item']);
        $this->assertEquals('Tests are comprehensive', $assignment->evaluation_checklist[2]['item']);
    }
}
