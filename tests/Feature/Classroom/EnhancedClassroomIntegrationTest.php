<?php

namespace Tests\Feature\Classroom;

use App\Models\User;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrackEnrollment;
use App\Models\LearningProgress;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\AssessmentAttempt;
use App\Services\ModuleAssignmentService;
use App\Services\ProgressTrackingService;
use App\Services\CertificateService;
use App\Services\CourseEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnhancedClassroomIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ModuleAssignmentService $moduleAssignmentService;
    private ProgressTrackingService $progressTrackingService;
    private CertificateService $certificateService;
    private CourseEnrollmentService $courseEnrollmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleAssignmentService = app(ModuleAssignmentService::class);
        $this->progressTrackingService = app(ProgressTrackingService::class);
        $this->certificateService = app(CertificateService::class);
        $this->courseEnrollmentService = app(CourseEnrollmentService::class);

        // Create default certificate templates for testing
        CertificateTemplate::factory()->create([
            'name' => 'Default Track Certificate',
            'template_type' => 'track',
            'is_default' => true,
        ]);

        CertificateTemplate::factory()->create([
            'name' => 'Default Course Certificate',
            'template_type' => 'course',
            'is_default' => true,
        ]);
    }

    /**
     * Test complete track-based learning workflow
     * Requirements: All requirements integration
     */
    public function test_complete_track_based_learning_workflow()
    {
        // Create users
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);

        // Create track structure
        $track = Track::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published' => true,
            'is_premium' => false,
        ]);

        $level = $track->levels()->create([
            'title' => 'Beginner Level',
            'difficulty' => 'beginner',
            'order_index' => 1,
            'is_published' => true,
        ]);

        // Create modules and assign to level
        $module1 = Module::factory()->create([
            'title' => 'Introduction Module',
            'level_id' => $level->id, // Backward compatibility
        ]);

        $module2 = Module::factory()->make([
            'title' => 'Advanced Module',
            'is_published' => true,
        ]);
        $module2->level_id = null; // New flexible module
        $module2->save();

        // Use new assignment service
        $this->moduleAssignmentService->assignModuleToLevel($module2, $level, ['order' => 2]);

        // Create lessons
        $lesson1 = $module1->lessons()->create([
            'title' => 'Lesson 1',
            'content' => 'Introduction content',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'text',
        ]);

        $lesson2 = $module2->lessons()->create([
            'title' => 'Lesson 2',
            'content' => 'Advanced content',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'video',
        ]);

        // Create assessment
        $assessment = $module1->assessments()->create([
            'title' => 'Module 1 Quiz',
            'description' => 'Test your knowledge',
            'assessable_type' => Module::class,
            'assessable_id' => $module1->id,
            'max_attempts' => 3,
            'passing_score' => 70,
            'time_limit_minutes' => 30,
        ]);

        $question = $assessment->questions()->create([
            'question_text' => 'What is the main topic?',
            'question_type' => 'multiple_choice',
            'points' => 10,
            'order_index' => 1,
        ]);

        $question->options()->createMany([
            ['option_text' => 'Option A', 'is_correct' => true, 'order_index' => 1],
            ['option_text' => 'Option B', 'is_correct' => false, 'order_index' => 2],
        ]);

        // Learner enrolls in track
        $enrollment = $track->enrollments()->create([
            'user_id' => $learner->id,
            'enrolled_at' => now(),
            'progress_percentage' => 0,
        ]);

        $this->assertDatabaseHas('track_enrollments', [
            'user_id' => $learner->id,
            'track_id' => $track->id,
        ]);

        // Learner progresses through lessons
        $this->progressTrackingService->updateProgress($learner, $lesson1, [
            'completion_percentage' => 100,
            'time_spent_minutes' => 15,
            'engagement_score' => 0.85,
        ]);

        $this->progressTrackingService->updateProgress($learner, $lesson2, [
            'completion_percentage' => 100,
            'time_spent_minutes' => 20,
            'engagement_score' => 0.90,
        ]);

        // Check granular progress tracking
        $lesson1Progress = LearningProgress::where([
            'user_id' => $learner->id,
            'progressable_type' => Lesson::class,
            'progressable_id' => $lesson1->id,
        ])->first();

        $this->assertNotNull($lesson1Progress);
        $this->assertEquals(100, $lesson1Progress->completion_percentage);
        $this->assertEquals(15, $lesson1Progress->time_spent_minutes);
        $this->assertEquals(0.85, $lesson1Progress->engagement_score);

        // Learner takes assessment
        $attempt = $assessment->attempts()->create([
            'user_id' => $learner->id,
            'started_at' => now(),
            'completed_at' => now()->addMinutes(10),
            'score' => 80,
            'max_score' => 100,
            'passed' => true,
        ]);

        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_id' => $question->options()->where('is_correct', true)->first()->id,
            'is_correct' => true,
            'points_earned' => 10,
        ]);

        // Calculate aggregate progress
        $trackProgress = $this->progressTrackingService->calculateAggregateProgress($learner, $track);

        $this->assertArrayHasKey('completion_percentage', $trackProgress);
        $this->assertArrayHasKey('time_spent_minutes', $trackProgress);
        $this->assertArrayHasKey('engagement_score', $trackProgress);

        // Complete track and generate certificate
        $this->progressTrackingService->updateProgress($learner, $track, [
            'completion_percentage' => 100,
        ]);

        // Mark enrollment as completed for certificate eligibility
        $enrollment->update([
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);

        $certificate = $this->certificateService->generateCertificate($learner, $track);

        $this->assertNotNull($certificate);
        $this->assertEquals($learner->id, $certificate->user_id);
        $this->assertEquals(Track::class, $certificate->certifiable_type);
        $this->assertEquals($track->id, $certificate->certifiable_id);

        // Verify cross-context data consistency
        $this->assertEquals(2, $level->allModules()->count()); // Both modules assigned
        $this->assertTrue($module2->levels()->where('levels.id', $level->id)->exists());
    }

    /**
     * Test complete course-based learning workflow
     * Requirements: All requirements integration
     */
    public function test_complete_course_based_learning_workflow()
    {
        // Create users
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);

        // Create certificate template for courses
        $courseTemplate = CertificateTemplate::factory()->create([
            'name' => 'Course Completion Certificate',
            'template_type' => 'course',
        ]);

        // Create course
        $course = Course::factory()->create([
            'title' => 'Full Stack Development',
            'description' => 'Complete course on full stack development',
            'is_active' => true,
            'certificate_template_id' => $courseTemplate->id,
            'estimated_duration' => 120, // 2 hours
        ]);

        // Create reusable modules (not tied to specific levels)
        $module1 = Module::factory()->make(['title' => 'Frontend Basics', 'is_published' => true]);
        $module1->level_id = null;
        $module1->save();

        $module2 = Module::factory()->make(['title' => 'Backend Fundamentals', 'is_published' => true]);
        $module2->level_id = null;
        $module2->save();

        $module3 = Module::factory()->make(['title' => 'Database Design', 'is_published' => true]);
        $module3->level_id = null;
        $module3->save();

        // Assign modules to course with specific ordering
        $this->moduleAssignmentService->assignModuleToCourse($module1, $course, ['order' => 1]);
        $this->moduleAssignmentService->assignModuleToCourse($module2, $course, ['order' => 2]);
        $this->moduleAssignmentService->assignModuleToCourse($module3, $course, ['order' => 3]);

        // Create lessons for each module
        $lesson1 = $module1->lessons()->create([
            'title' => 'HTML Basics',
            'content' => 'Learn HTML fundamentals',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'text',
        ]);

        $lesson2 = $module2->lessons()->create([
            'title' => 'Node.js Introduction',
            'content' => 'Getting started with Node.js',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'video',
        ]);

        $lesson3 = $module3->lessons()->create([
            'title' => 'SQL Fundamentals',
            'content' => 'Database design principles',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'interactive',
        ]);

        // Create course-level assessment
        $courseAssessment = $course->assessments()->create([
            'title' => 'Final Project',
            'description' => 'Complete full stack application',
            'assessable_type' => Course::class,
            'assessable_id' => $course->id,
            'max_attempts' => 1,
            'passing_score' => 80,
            'time_limit_minutes' => 180,
        ]);

        // Learner enrolls in course
        $enrollment = $this->courseEnrollmentService->enrollUser($learner, $course);

        $this->assertInstanceOf(CourseEnrollment::class, $enrollment);
        $this->assertEquals($learner->id, $enrollment->user_id);
        $this->assertEquals($course->id, $enrollment->course_id);

        // Verify modules are in correct order
        $courseModules = $course->modules()->orderBy('order')->get();
        $this->assertEquals('Frontend Basics', $courseModules[0]->title);
        $this->assertEquals('Backend Fundamentals', $courseModules[1]->title);
        $this->assertEquals('Database Design', $courseModules[2]->title);

        // Learner progresses through course modules
        foreach ([$lesson1, $lesson2, $lesson3] as $index => $lesson) {
            $this->progressTrackingService->updateProgress($learner, $lesson, [
                'completion_percentage' => 100,
                'time_spent_minutes' => 25 + ($index * 5),
                'engagement_score' => 0.80 + ($index * 0.05),
            ]);

            // Update module progress
            $this->progressTrackingService->updateProgress($learner, $lesson->module, [
                'completion_percentage' => 100,
                'time_spent_minutes' => 25 + ($index * 5),
            ]);
        }

        // Calculate course progress aggregation
        $courseProgress = $this->progressTrackingService->calculateAggregateProgress($learner, $course);

        $this->assertArrayHasKey('completion_percentage', $courseProgress);
        $this->assertArrayHasKey('module_details', $courseProgress);
        $this->assertArrayHasKey('time_spent_minutes', $courseProgress);

        // Complete course assessment
        $attempt = $courseAssessment->attempts()->create([
            'user_id' => $learner->id,
            'started_at' => now(),
            'completed_at' => now()->addHours(2),
            'score' => 85,
            'max_score' => 100,
            'passed' => true,
        ]);

        // Mark course as completed
        $this->progressTrackingService->updateProgress($learner, $course, [
            'completion_percentage' => 100,
        ]);

        // Mark enrollment as completed for certificate eligibility
        $enrollment->update([
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);

        // Generate course certificate with dynamic template selection
        $certificate = $this->certificateService->generateCertificate($learner, $course);

        $this->assertNotNull($certificate);
        $this->assertEquals($learner->id, $certificate->user_id);
        $this->assertEquals(Course::class, $certificate->certifiable_type);
        $this->assertEquals($course->id, $certificate->certifiable_id);
        $this->assertEquals($courseTemplate->id, $certificate->template_id);

        // Verify module reusability - assign same modules to another course
        $course2 = Course::factory()->create([
            'title' => 'Advanced Web Development',
            'is_active' => true,
        ]);

        // Should be able to reuse modules
        $this->moduleAssignmentService->assignModuleToCourse($module2, $course2, ['order' => 1]);
        $this->moduleAssignmentService->assignModuleToCourse($module3, $course2, ['order' => 2]);

        $this->assertEquals(2, $course2->modules()->count());
        $this->assertTrue($module2->courses()->where('courses.id', $course2->id)->exists());
        $this->assertTrue($module3->courses()->where('courses.id', $course2->id)->exists());
    }

    /**
     * Test cross-context data consistency between tracks and courses
     * Requirements: All requirements integration
     */
    public function test_cross_context_data_consistency()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);

        // Create track with level
        $track = Track::factory()->create(['instructor_id' => $instructor->id]);
        $level = $track->levels()->create([
            'title' => 'Intermediate Level',
            'difficulty' => 'intermediate',
            'order_index' => 1,
            'is_published' => true,
        ]);

        // Create course
        $course = Course::factory()->create(['title' => 'Shared Content Course', 'is_active' => true]);

        // Create shared module
        $sharedModule = Module::factory()->make(['title' => 'Shared Programming Module', 'is_published' => true]);
        $sharedModule->level_id = null;
        $sharedModule->save();

        // Assign same module to both track level and course
        $this->moduleAssignmentService->assignModuleToLevel($sharedModule, $level, ['order' => 1]);
        $this->moduleAssignmentService->assignModuleToCourse($sharedModule, $course, ['order' => 1]);

        // Create lesson in shared module
        $lesson = $sharedModule->lessons()->create([
            'title' => 'Programming Fundamentals',
            'content' => 'Basic programming concepts',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'text',
        ]);

        // Enroll learner in both track and course
        $trackEnrollment = $track->enrollments()->create([
            'user_id' => $learner->id,
            'enrolled_at' => now(),
        ]);

        $courseEnrollment = $this->courseEnrollmentService->enrollUser($learner, $course);

        // Update module content
        $sharedModule->update(['title' => 'Updated Programming Module']);

        // Verify consistency across contexts
        $trackModule = $level->assignedModules()->where('modules.id', $sharedModule->id)->first();
        $courseModule = $course->modules()->where('modules.id', $sharedModule->id)->first();

        $this->assertEquals('Updated Programming Module', $trackModule->title);
        $this->assertEquals('Updated Programming Module', $courseModule->title);
        $this->assertEquals($trackModule->id, $courseModule->id); // Same module instance

        // Progress tracking should work in both contexts
        $this->progressTrackingService->updateProgress($learner, $lesson, [
            'completion_percentage' => 100,
            'time_spent_minutes' => 30,
        ]);

        // Also update module progress to ensure aggregation works
        $this->progressTrackingService->updateProgress($learner, $sharedModule, [
            'completion_percentage' => 100,
            'time_spent_minutes' => 30,
        ]);

        // Verify progress is recorded for the lesson regardless of access context
        $progressRecords = LearningProgress::where([
            'user_id' => $learner->id,
            'progressable_type' => Lesson::class,
            'progressable_id' => $lesson->id,
        ])->get();

        $this->assertEquals(1, $progressRecords->count()); // Only one progress record
        $this->assertEquals(100, $progressRecords->first()->completion_percentage);

        // Both track and course should reflect module completion
        $trackProgress = $this->progressTrackingService->calculateAggregateProgress($learner, $track);
        $courseProgress = $this->progressTrackingService->calculateAggregateProgress($learner, $course);

        $this->assertGreaterThan(0, $trackProgress['completion_percentage']);
        $this->assertGreaterThan(0, $courseProgress['completion_percentage']);
    }

    /**
     * Test performance with large datasets
     * Requirements: System performance and scalability
     */
    public function test_performance_with_large_datasets()
    {
        $startTime = microtime(true);

        // Create large dataset
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learners = User::factory()->count(50)->create(['role' => 'learner']);

        // Create multiple tracks and courses
        $tracks = Track::factory()->count(10)->create(['instructor_id' => $instructor->id, 'is_published' => true]);
        $courses = Course::factory()->count(10)->create(['is_active' => true]);

        // Create modules and lessons
        $modules = collect();
        for ($i = 0; $i < 20; $i++) {
            $module = new Module([
                'title' => "Module {$i}",
                'description' => "Description for module {$i}",
                'order_index' => $i,
                'estimated_duration' => 60,
                'is_published' => true,
                'level_id' => null,
            ]);
            $module->save();
            $modules->push($module);
        }

        foreach ($tracks as $track) {
            $level = $track->levels()->create([
                'track_id' => $track->id, // Explicitly set to prevent factory from creating new track
                'title' => "Level for {$track->title}",
                'difficulty' => 'beginner',
                'order_index' => 1,
                'is_published' => true,
            ]);

            // Assign 2 modules per level
            $trackModules = $modules->random(2);
            foreach ($trackModules as $index => $module) {
                $this->moduleAssignmentService->assignModuleToLevel($module, $level, ['order' => $index + 1]);
            }
        }

        foreach ($courses as $course) {
            // Assign 3 modules per course
            $courseModules = $modules->random(3);
            foreach ($courseModules as $index => $module) {
                $this->moduleAssignmentService->assignModuleToCourse($module, $course, ['order' => $index + 1]);
            }
        }

        // Create lessons for modules
        foreach ($modules as $module) {
            $module->lessons()->createMany([
                [
                    'title' => "Lesson 1 for {$module->title}",
                    'content' => 'Content 1',
                    'order_index' => 1,
                    'is_published' => true,
                    'lesson_type' => 'text',
                ],
                [
                    'title' => "Lesson 2 for {$module->title}",
                    'content' => 'Content 2',
                    'order_index' => 2,
                    'is_published' => true,
                    'lesson_type' => 'video',
                ],
            ]);
        }

        // Bulk enrollments
        foreach ($learners as $learner) {
            // Enroll in 2 random tracks
            $learnerTracks = $tracks->random(2);
            foreach ($learnerTracks as $track) {
                $track->enrollments()->create([
                    'user_id' => $learner->id,
                    'enrolled_at' => now(),
                ]);
            }

            // Enroll in 2 random courses
            $learnerCourses = $courses->random(2);
            foreach ($learnerCourses as $course) {
                $this->courseEnrollmentService->enrollUser($learner, $course);
            }
        }

        $setupTime = microtime(true) - $startTime;

        // Test query performance
        $queryStartTime = microtime(true);

        // Complex queries that should be optimized
        $trackWithModules = Track::with(['levels.modules.lessons'])->first();
        $courseWithModules = Course::with(['modules.lessons'])->first();

        // Progress aggregation queries
        $sampleLearner = $learners->first();
        $trackProgress = $this->progressTrackingService->calculateAggregateProgress($sampleLearner, $trackWithModules);
        $courseProgress = $this->progressTrackingService->calculateAggregateProgress($sampleLearner, $courseWithModules);

        // Cross-context queries
        $sharedModules = Module::whereHas('levels')
                              ->whereHas('courses')
                              ->with(['levels', 'courses'])
                              ->get();

        $queryTime = microtime(true) - $queryStartTime;

        // Performance assertions
        $this->assertLessThan(30, $setupTime, 'Setup should complete within 30 seconds');
        $this->assertLessThan(5, $queryTime, 'Complex queries should complete within 5 seconds');

        // Data integrity assertions
        $this->assertEquals(50, User::where('role', 'learner')->count());
        $this->assertEquals(10, Track::count());
        $this->assertEquals(10, Course::count());
        $this->assertEquals(20, Module::count());

        // Verify enrollments
        $totalTrackEnrollments = TrackEnrollment::count();
        $totalCourseEnrollments = CourseEnrollment::count();

        $this->assertEquals(100, $totalTrackEnrollments); // 50 learners × 2 tracks
        $this->assertEquals(100, $totalCourseEnrollments); // 50 learners × 2 courses

        // Verify module assignments
        $this->assertGreaterThan(0, DB::table('level_modules')->count());
        $this->assertGreaterThan(0, DB::table('course_modules')->count());

        echo "\nPerformance Results:\n";
        echo "Setup Time: " . round($setupTime, 2) . " seconds\n";
        echo "Query Time: " . round($queryTime, 2) . " seconds\n";
        echo "Total Track Enrollments: {$totalTrackEnrollments}\n";
        echo "Total Course Enrollments: {$totalCourseEnrollments}\n";
        echo "Shared Modules: " . $sharedModules->count() . "\n";
    }

    /**
     * Test backward compatibility with existing track system
     * Requirements: 6.1, 6.2, 6.3, 6.4
     */
    public function test_backward_compatibility_with_existing_system()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);

        // Create track with old-style module assignment
        $track = Track::factory()->create(['instructor_id' => $instructor->id, 'is_published' => true]);
        $level = $track->levels()->create([
            'title' => 'Legacy Level',
            'difficulty' => 'beginner',
            'order_index' => 1,
            'is_published' => true,
        ]);

        // Create module with direct level_id (old way)
        $legacyModule = Module::factory()->create([
            'title' => 'Legacy Module',
            'level_id' => $level->id, // Old direct foreign key
        ]);

        // Create lesson
        $lesson = $legacyModule->lessons()->create([
            'title' => 'Legacy Lesson',
            'content' => 'Legacy content',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'text',
        ]);

        // Old enrollment method should still work
        $enrollment = $track->enrollments()->create([
            'user_id' => $learner->id,
            'enrolled_at' => now(),
        ]);

        // Old progress tracking should still work
        $lessonProgress = $lesson->progress()->create([
            'user_id' => $learner->id,
            'completed_at' => now(),
            'completion_percentage' => 100,
        ]);

        // Mark enrollment as completed for certificate eligibility
        $enrollment->update([
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);

        // Progress calculation should work with both old and new data
        $trackProgress = $this->progressTrackingService->calculateAggregateProgress($learner, $track);
        $this->assertArrayHasKey('completion_percentage', $trackProgress);

        // Certificate generation should work with legacy tracks
        $certificate = $this->certificateService->generateCertificate($learner, $track);
        $this->assertNotNull($certificate);
        $this->assertEquals(Track::class, $certificate->certifiable_type);

        // Verify backward compatibility
        $this->assertEquals($level->id, $legacyModule->level_id);
        $this->assertEquals($level->id, $legacyModule->level->id);
        $this->assertTrue($level->modules->contains($legacyModule));

        // New system should also work with legacy data
        $this->assertTrue($level->modules()->where('id', $legacyModule->id)->exists());
    }

    /**
     * Test API consistency between tracks and courses
     * Requirements: 10.1, 10.2
     */
    public function test_api_consistency_between_tracks_and_courses()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $learner = User::factory()->create(['role' => 'learner']);

        // Create track and course
        $track = Track::factory()->create(['instructor_id' => $instructor->id, 'is_published' => true]);
        $course = Course::factory()->create(['is_active' => true]);

        // Test that both have similar data structures
        $trackData = $track->toArray();
        $courseData = $course->toArray();

        // Verify consistent data structure
        $this->assertArrayHasKey('id', $trackData);
        $this->assertArrayHasKey('title', $trackData);
        $this->assertArrayHasKey('slug', $trackData);

        $this->assertArrayHasKey('id', $courseData);
        $this->assertArrayHasKey('title', $courseData);
        $this->assertArrayHasKey('slug', $courseData);

        // Test enrollment consistency
        $trackEnrollment = $track->enrollments()->create([
            'user_id' => $learner->id,
            'enrolled_at' => now(),
        ]);

        $courseEnrollment = $this->courseEnrollmentService->enrollUser($learner, $course);

        // Both should have similar enrollment structures
        $this->assertEquals($learner->id, $trackEnrollment->user_id);
        $this->assertEquals($learner->id, $courseEnrollment->user_id);
        $this->assertNotNull($trackEnrollment->enrolled_at);
        $this->assertNotNull($courseEnrollment->enrolled_at);
    }
}
