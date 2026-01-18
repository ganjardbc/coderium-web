<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Achievement;
use App\Models\Certificate;
use App\Models\TrackEnrollment;
use App\Models\LessonProgress;
use App\Services\AchievementService;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AchievementService $achievementService;
    protected ProgressService $progressService;
    protected User $user;
    protected Track $track;

    protected function setUp(): void
    {
        parent::setUp();

        $this->progressService = app(ProgressService::class);
        $this->achievementService = new AchievementService($this->progressService);

        // Create test user
        $this->user = User::factory()->create(['role' => 'learner']);

        // Create test track structure with published content
        $instructor = User::factory()->create(['role' => 'instructor']);
        $this->track = Track::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published' => true
        ]);
        $level = Level::factory()->create([
            'track_id' => $this->track->id,
            'is_published' => true
        ]);
        $module = Module::factory()->create([
            'level_id' => $level->id,
            'is_published' => true
        ]);
        Lesson::factory()->create([
            'module_id' => $module->id,
            'is_published' => true
        ]);

        // Initialize default achievements
        $this->achievementService->initializeDefaultAchievements();
    }

    public function test_initialize_default_achievements_creates_achievements()
    {
        // Clear existing achievements using delete instead of truncate
        Achievement::query()->delete();

        $this->achievementService->initializeDefaultAchievements();

        $this->assertDatabaseHas('achievements', ['type' => 'first_lesson']);
        $this->assertDatabaseHas('achievements', ['type' => 'first_module']);
        $this->assertDatabaseHas('achievements', ['type' => 'track_completion']);

        $achievementCount = Achievement::count();
        $this->assertGreaterThan(0, $achievementCount);
    }

    public function test_check_first_lesson_achievement()
    {
        // Ensure achievements exist
        $this->achievementService->initializeDefaultAchievements();

        // Enroll user in track
        TrackEnrollment::create([
            'user_id' => $this->user->id,
            'track_id' => $this->track->id,
            'enrolled_at' => now(),
        ]);

        $lesson = $this->track->levels->first()->modules->first()->lessons->first();

        // Complete the lesson (this will automatically check for achievements)
        $this->progressService->markLessonComplete($this->user, $lesson);

        // Verify achievement was awarded
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $this->user->id,
            'achievement_id' => Achievement::where('type', 'first_lesson')->first()->id,
        ]);

        // Verify the user has the achievement
        $userAchievements = $this->user->achievements;
        $this->assertGreaterThan(0, $userAchievements->count());
        $this->assertTrue($userAchievements->contains('type', 'first_lesson'));
    }

    public function test_generate_track_completion_certificate()
    {
        // Enroll user in track
        $enrollment = TrackEnrollment::create([
            'user_id' => $this->user->id,
            'track_id' => $this->track->id,
            'enrolled_at' => now(),
            'completed_at' => now(),
            'progress_percentage' => 100.00,
        ]);

        $certificate = $this->achievementService->generateTrackCompletionCertificate($this->user, $this->track);

        $this->assertInstanceOf(Certificate::class, $certificate);
        $this->assertEquals($this->user->id, $certificate->user_id);
        $this->assertEquals($this->track->id, $certificate->track_id);
        $this->assertNotNull($certificate->certificate_number);
        $this->assertStringContainsString($this->track->title, $certificate->title);

        // Verify certificate was saved to database
        $this->assertDatabaseHas('certificates', [
            'user_id' => $this->user->id,
            'track_id' => $this->track->id,
        ]);
    }

    public function test_get_user_achievements()
    {
        // Award an achievement manually
        $achievement = Achievement::where('type', 'first_lesson')->first();
        $this->user->achievements()->attach($achievement->id, [
            'earned_at' => now(),
            'metadata' => json_encode([]),
        ]);

        $userAchievements = $this->achievementService->getUserAchievements($this->user);

        $this->assertCount(1, $userAchievements);
        $this->assertEquals($achievement->id, $userAchievements->first()->id);
    }

    public function test_get_user_certificates()
    {
        // Create a certificate
        $certificate = Certificate::create([
            'user_id' => $this->user->id,
            'track_id' => $this->track->id,
            'certificate_number' => Certificate::generateCertificateNumber(),
            'title' => "Test Certificate",
            'description' => "Test description",
            'issued_at' => now(),
            'completed_at' => now(),
        ]);

        $userCertificates = $this->achievementService->getUserCertificates($this->user);

        $this->assertCount(1, $userCertificates);
        $this->assertEquals($certificate->id, $userCertificates->first()->id);
    }

    public function test_get_user_achievement_stats()
    {
        // Award some achievements
        $achievement1 = Achievement::where('type', 'first_lesson')->first();
        $achievement2 = Achievement::where('type', 'first_module')->first();

        $this->user->achievements()->attach($achievement1->id, [
            'earned_at' => now(),
            'metadata' => json_encode([]),
        ]);

        $this->user->achievements()->attach($achievement2->id, [
            'earned_at' => now(),
            'metadata' => json_encode([]),
        ]);

        // Create a certificate
        Certificate::create([
            'user_id' => $this->user->id,
            'track_id' => $this->track->id,
            'certificate_number' => Certificate::generateCertificateNumber(),
            'title' => "Test Certificate",
            'description' => "Test description",
            'issued_at' => now(),
            'completed_at' => now(),
        ]);

        $stats = $this->achievementService->getUserAchievementStats($this->user);

        $this->assertArrayHasKey('total_achievements', $stats);
        $this->assertArrayHasKey('earned_achievements', $stats);
        $this->assertArrayHasKey('completion_percentage', $stats);
        $this->assertArrayHasKey('total_points', $stats);
        $this->assertArrayHasKey('certificates_earned', $stats);

        $this->assertEquals(2, $stats['earned_achievements']);
        $this->assertEquals(1, $stats['certificates_earned']);
        $this->assertGreaterThan(0, $stats['total_points']);
    }

    public function test_certificate_number_generation_is_unique()
    {
        $number1 = Certificate::generateCertificateNumber();
        $number2 = Certificate::generateCertificateNumber();

        $this->assertNotEquals($number1, $number2);
        $this->assertStringStartsWith('CERT-', $number1);
        $this->assertStringStartsWith('CERT-', $number2);
    }

    public function test_achievement_is_not_awarded_twice()
    {
        // Ensure achievements exist
        $this->achievementService->initializeDefaultAchievements();

        // Enroll user in track
        TrackEnrollment::create([
            'user_id' => $this->user->id,
            'track_id' => $this->track->id,
            'enrolled_at' => now(),
        ]);

        $lesson = $this->track->levels->first()->modules->first()->lessons->first();

        // Complete the lesson twice
        $this->progressService->markLessonComplete($this->user, $lesson);

        // Try to complete the same lesson again (should not award achievements again)
        $achievements2 = $this->achievementService->checkLessonCompletionAchievements($this->user, $lesson);

        // Second time should not award achievement again
        $this->assertEmpty($achievements2);

        // Verify that the first_lesson achievement exists only once
        $firstLessonAchievementCount = $this->user->achievements()
            ->where('type', 'first_lesson')
            ->count();
        $this->assertEquals(1, $firstLessonAchievementCount);
    }

    public function test_certificate_is_not_generated_twice()
    {
        // Enroll user in track
        TrackEnrollment::create([
            'user_id' => $this->user->id,
            'track_id' => $this->track->id,
            'enrolled_at' => now(),
            'completed_at' => now(),
            'progress_percentage' => 100.00,
        ]);

        // Generate certificate twice
        $certificate1 = $this->achievementService->generateTrackCompletionCertificate($this->user, $this->track);
        $certificate2 = $this->achievementService->generateTrackCompletionCertificate($this->user, $this->track);

        // Should return the same certificate
        $this->assertEquals($certificate1->id, $certificate2->id);

        // Verify only one certificate exists
        $certificateCount = Certificate::where('user_id', $this->user->id)
            ->where('track_id', $this->track->id)
            ->count();
        $this->assertEquals(1, $certificateCount);
    }
}
