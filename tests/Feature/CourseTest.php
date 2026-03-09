<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_course_list_page()
    {
        // Create some test courses
        Course::factory()->count(3)->create(['is_active' => true]);

        $response = $this->get('/courses');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('courses/CoursesList')
                ->has('courses.data', 3)
        );
    }

    public function test_can_view_course_detail_page()
    {
        $course = Course::factory()->create(['is_active' => true]);

        // Debug: Check if course exists and has correct slug
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'slug' => $course->slug,
            'is_active' => true,
        ]);

        // Try to find the course by slug to ensure route model binding works
        $foundCourse = Course::where('slug', $course->slug)->first();
        $this->assertNotNull($foundCourse, "Course with slug {$course->slug} not found");

        $response = $this->get("/courses/{$course->slug}");

        // If it's still 404, there's likely an issue with the controller
        if ($response->status() === 404) {
            $this->fail("Course detail page returned 404. Course slug: {$course->slug}");
        }

        $response->assertStatus(200);
    }

    public function test_can_enroll_in_course_via_api()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/courses/{$course->slug}/enroll");

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Successfully enrolled in course.',
        ]);

        $this->assertDatabaseHas('course_enrollments', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_cannot_enroll_in_inactive_course()
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/courses/{$course->slug}/enroll");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Course is not available for enrollment.',
        ]);
    }

    public function test_home_page_shows_featured_courses()
    {
        Course::factory()->count(3)->create(['is_active' => true]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Home-2')
                ->has('courses')
        );
    }
}
