<?php

namespace Tests\Feature\Classroom;

use App\Models\User;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_classroom_management()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get('/admin/dashboard');

        $response->assertStatus(200);
        // Admin dashboard should be accessible
    }

    public function test_instructor_can_manage_own_tracks()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create(['instructor_id' => $instructor->id]);

        // Instructor can update their own track
        $response = $this->actingAs($instructor)
            ->put("/api/v1/classroom/tracks/{$track->slug}", [
                'title' => 'Updated Track Title',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tracks', [
            'id' => $track->id,
            'title' => 'Updated Track Title',
        ]);
    }

    public function test_instructor_cannot_manage_other_tracks()
    {
        $instructor1 = User::factory()->create(['role' => 'instructor']);
        $instructor2 = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create(['instructor_id' => $instructor2->id]);

        // Instructor cannot update another instructor's track
        $response = $this->actingAs($instructor1)
            ->put("/api/v1/classroom/tracks/{$track->slug}", [
                'title' => 'Unauthorized Update',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_manage_all_tracks()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create(['instructor_id' => $instructor->id]);

        // Admin can update any track
        $response = $this->actingAs($admin)
            ->put("/api/v1/classroom/tracks/{$track->slug}", [
                'title' => 'Admin Updated Title',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tracks', [
            'id' => $track->id,
            'title' => 'Admin Updated Title',
        ]);
    }

    public function test_track_enrollment_statistics_for_instructors()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create(['instructor_id' => $instructor->id]);

        // Create some enrollments
        $learners = User::factory()->count(3)->create(['role' => 'learner']);
        foreach ($learners as $learner) {
            $track->enrollments()->create([
                'user_id' => $learner->id,
                'enrolled_at' => now(),
                'progress_percentage' => rand(0, 100),
            ]);
        }

        $response = $this->actingAs($instructor)
            ->get("/api/v1/classroom/tracks/{$track->slug}/enrollment-stats");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'track' => ['id', 'title', 'slug'],
            'statistics' => [
                'total_enrollments',
                'active_enrollments',
                'completed_enrollments',
                'completion_rate',
                'average_progress',
            ],
        ]);
    }

    public function test_media_integration_with_classroom_content()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create(['instructor_id' => $instructor->id]);

        $level = $track->levels()->create([
            'title' => 'Test Level',
            'difficulty' => 'beginner',
            'order_index' => 1,
            'is_published' => true,
        ]);

        $module = $level->modules()->create([
            'title' => 'Test Module',
            'order_index' => 1,
            'is_published' => true,
        ]);

        $lesson = $module->lessons()->create([
            'title' => 'Test Lesson',
            'content' => 'Test content',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'text',
        ]);

        // Test that lesson can have media attached
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphToMany::class, $lesson->media());

        // Test that track can have media attached
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphToMany::class, $track->media());

        // Test that module can have media attached
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphToMany::class, $module->media());
    }

    public function test_existing_user_model_compatibility()
    {
        $user = User::factory()->create(['role' => 'learner']);

        // Test that user has classroom-related methods
        $this->assertTrue(method_exists($user, 'isLearner'));
        $this->assertTrue(method_exists($user, 'isInstructor'));
        $this->assertTrue(method_exists($user, 'hasInstructorPermissions'));
        $this->assertTrue(method_exists($user, 'canManageClassroomContent'));

        // Test that user has classroom relationships
        $this->assertTrue(method_exists($user, 'trackEnrollments'));
        $this->assertTrue(method_exists($user, 'lessonProgress'));
        $this->assertTrue(method_exists($user, 'achievements'));
        $this->assertTrue(method_exists($user, 'certificates'));

        // Test role-based permissions
        $this->assertTrue($user->isLearner());
        $this->assertFalse($user->isInstructor());
        $this->assertFalse($user->hasInstructorPermissions());
        $this->assertFalse($user->canManageClassroomContent());
    }

    public function test_content_hierarchy_integrity()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create(['instructor_id' => $instructor->id]);

        $level = $track->levels()->create([
            'title' => 'Test Level',
            'difficulty' => 'beginner',
            'order_index' => 1,
            'is_published' => true,
        ]);

        $module = $level->modules()->create([
            'title' => 'Test Module',
            'order_index' => 1,
            'is_published' => true,
        ]);

        $lesson = $module->lessons()->create([
            'title' => 'Test Lesson',
            'content' => 'Test content',
            'order_index' => 1,
            'is_published' => true,
            'lesson_type' => 'text',
        ]);

        // Test relationships work correctly
        $this->assertEquals($track->id, $level->track->id);
        $this->assertEquals($level->id, $module->level->id);
        $this->assertEquals($module->id, $lesson->module->id);

        // Test reverse relationships
        $this->assertTrue($track->levels->contains($level));
        $this->assertTrue($level->modules->contains($module));
        $this->assertTrue($module->lessons->contains($lesson));
    }
}
