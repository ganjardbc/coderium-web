<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Create test routes for middleware testing
    Route::middleware(['web', 'auth', 'role:learner'])->get('/test-learner', function () {
        return 'learner access';
    });

    Route::middleware(['web', 'auth', 'role:instructor'])->get('/test-instructor', function () {
        return 'instructor access';
    });

    Route::middleware(['web', 'auth', 'role:admin'])->get('/test-admin', function () {
        return 'admin access';
    });

    Route::middleware(['web', 'auth', 'role:instructor_or_admin'])->get('/test-instructor-or-admin', function () {
        return 'instructor or admin access';
    });
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get('/test-learner');

    $response->assertRedirect('/login');
});

test('learner can access learner routes', function () {
    $user = User::factory()->create(['role' => 'learner']);

    $response = $this->actingAs($user)->get('/test-learner');

    $response->assertStatus(200);
    $response->assertSeeText('learner access');
});

test('learner cannot access instructor routes', function () {
    $user = User::factory()->create(['role' => 'learner']);

    $response = $this->actingAs($user)->get('/test-instructor');

    $response->assertStatus(403);
});

test('learner cannot access admin routes', function () {
    $user = User::factory()->create(['role' => 'learner']);

    $response = $this->actingAs($user)->get('/test-admin');

    $response->assertStatus(403);
});

test('instructor can access instructor routes', function () {
    $user = User::factory()->create(['role' => 'instructor']);

    $response = $this->actingAs($user)->get('/test-instructor');

    $response->assertStatus(200);
    $response->assertSeeText('instructor access');
});

test('instructor can access instructor or admin routes', function () {
    $user = User::factory()->create(['role' => 'instructor']);

    $response = $this->actingAs($user)->get('/test-instructor-or-admin');

    $response->assertStatus(200);
    $response->assertSeeText('instructor or admin access');
});

test('instructor cannot access admin only routes', function () {
    $user = User::factory()->create(['role' => 'instructor']);

    $response = $this->actingAs($user)->get('/test-admin');

    $response->assertStatus(403);
});

test('admin can access all routes', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Test each route individually to see which one fails
    $learnerResponse = $this->actingAs($user)->get('/test-learner');
    expect($learnerResponse->status())->toBe(200);

    $instructorResponse = $this->actingAs($user)->get('/test-instructor');
    expect($instructorResponse->status())->toBe(200);

    $adminResponse = $this->actingAs($user)->get('/test-admin');
    expect($adminResponse->status())->toBe(200);

    $instructorOrAdminResponse = $this->actingAs($user)->get('/test-instructor-or-admin');
    expect($instructorOrAdminResponse->status())->toBe(200);
});

test('middleware handles multiple roles correctly', function () {
    Route::middleware(['web', 'auth', 'role:instructor,admin'])->get('/test-multiple', function () {
        return 'multiple roles access';
    });

    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    // Learner should be denied
    $response = $this->actingAs($learner)->get('/test-multiple');
    $response->assertStatus(403);

    // Instructor should be allowed
    $response = $this->actingAs($instructor)->get('/test-multiple');
    $response->assertStatus(200);

    // Admin should be allowed
    $response = $this->actingAs($admin)->get('/test-multiple');
    $response->assertStatus(200);
});
