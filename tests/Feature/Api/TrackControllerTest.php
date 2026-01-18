<?php

namespace Tests\Feature\Api;

use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_published_tracks()
    {
        // Create a published track
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create([
            'is_published' => true,
            'instructor_id' => $instructor->id,
        ]);

        // Create an unpublished track (should not appear)
        Track::factory()->create([
            'is_published' => false,
            'instructor_id' => $instructor->id,
        ]);

        $response = $this->getJson('/api/v1/classroom/tracks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'is_published',
                        'difficulty_level',
                        'instructor',
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_can_show_published_track()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $track = Track::factory()->create([
            'is_published' => true,
            'instructor_id' => $instructor->id,
        ]);

        $response = $this->getJson("/api/v1/classroom/tracks/{$track->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'is_published',
                    'difficulty_level',
                    'instructor',
                ]
            ]);
    }

    public function test_instructor_can_create_track()
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $trackData = [
            'title' => 'Test Track',
            'description' => 'A test track for learning',
            'difficulty_level' => 'beginner',
            'is_premium' => false,
        ];

        $response = $this->actingAs($instructor)
            ->postJson('/api/v1/classroom/tracks', $trackData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'difficulty_level',
                    'instructor',
                ]
            ]);

        $this->assertDatabaseHas('tracks', [
            'title' => 'Test Track',
            'instructor_id' => $instructor->id,
        ]);
    }

    public function test_learner_cannot_create_track()
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $trackData = [
            'title' => 'Test Track',
            'description' => 'A test track for learning',
            'difficulty_level' => 'beginner',
        ];

        $response = $this->actingAs($learner)
            ->postJson('/api/v1/classroom/tracks', $trackData);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_create_track()
    {
        $trackData = [
            'title' => 'Test Track',
            'description' => 'A test track for learning',
            'difficulty_level' => 'beginner',
        ];

        $response = $this->postJson('/api/v1/classroom/tracks', $trackData);

        $response->assertStatus(401);
    }
}
