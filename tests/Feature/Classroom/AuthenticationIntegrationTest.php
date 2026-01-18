<?php

namespace Tests\Feature\Classroom;

use App\Models\User;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_can_view_public_tracks()
    {
        $track = Track::factory()->create([
            'is_published' => true,
            'is_premium' => false,
        ]);

        $response = $this->get('/classroom/tracks');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('classroom/TrackList')
                ->has('tracks.data', 1)
        );
    }

    public function test_unauthenticated_user_can_view_free_track_details()
    {
        $track = Track::factory()->create([
            'is_published' => true,
            'is_premium' => false,
        ]);

        $response = $this->get("/classroom/tracks/{$track->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('classroom/TrackDetail')
                ->where('track.id', $track->id)
        );
    }

    public function test_unauthenticated_user_cannot_access_premium_track_content()
    {
        $track = Track::factory()->create([
            'is_published' => true,
            'is_premium' => true,
            'price' => 99.99,
        ]);

        $level = $track->levels()->create([
            'title' => 'Test Level',
            'difficulty' => 'beginner',
            'order_index' => 1,
            'is_published' => true,
        ]);

        $response = $this->get("/classroom/levels/{$level->id}");

        // Laravel redirects unauthenticated users to login
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_enroll_in_free_track()
    {
        $user = User::factory()->create(['role' => 'learner']);
        $track = Track::factory()->create([
            'is_published' => true,
            'is_premium' => false,
        ]);

        $response = $this->actingAs($user)
            ->post("/api/v1/classroom/tracks/{$track->slug}/enroll");

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Successfully enrolled in track.',
        ]);

        $this->assertDatabaseHas('track_enrollments', [
            'user_id' => $user->id,
            'track_id' => $track->id,
        ]);
    }

    public function test_instructor_can_access_unpublished_content()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create([
            'is_published' => false,
            'instructor_id' => $instructor->id,
        ]);

        $response = $this->actingAs($instructor)
            ->get("/classroom/tracks/{$track->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('classroom/TrackDetail')
                ->where('track.id', $track->id)
        );
    }

    public function test_admin_can_access_all_content()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $track = Track::factory()->create([
            'is_published' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get("/classroom/tracks/{$track->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('classroom/TrackDetail')
                ->where('track.id', $track->id)
        );
    }

    public function test_fortify_two_factor_authentication_works_with_classroom()
    {
        $user = User::factory()->create(['role' => 'learner']);

        // Enable 2FA for the user
        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $track = Track::factory()->create([
            'is_published' => true,
            'is_premium' => false,
        ]);

        // User should still be able to access classroom after 2FA is enabled
        $response = $this->actingAs($user)
            ->post("/api/v1/classroom/tracks/{$track->slug}/enroll");

        $response->assertStatus(201);
    }

    public function test_user_role_permissions_are_enforced()
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $instructor = User::factory()->create(['role' => 'instructor']);

        // Learner cannot create tracks
        $response = $this->actingAs($learner)
            ->post('/api/v1/classroom/tracks', [
                'title' => 'Test Track',
                'difficulty_level' => 'beginner',
            ]);

        $response->assertStatus(403);

        // Instructor can create tracks
        $response = $this->actingAs($instructor)
            ->post('/api/v1/classroom/tracks', [
                'title' => 'Test Track',
                'difficulty_level' => 'beginner',
            ]);

        $response->assertStatus(201);
    }
}
