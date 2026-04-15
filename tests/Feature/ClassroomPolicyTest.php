<?php

use App\Models\User;
use App\Models\Track;
use App\Models\Course;
use App\Models\Certificate;
use App\Models\LearningProgress;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('track policy permissions work correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    // Test create permissions
    expect($learner->can('create', Track::class))->toBeFalse();
    expect($instructor->can('create', Track::class))->toBeTrue();
    expect($admin->can('create', Track::class))->toBeTrue();
});

test('gates work correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    // Test manage-classroom gate
    expect($learner->can('manage-classroom'))->toBeFalse();
    expect($instructor->can('manage-classroom'))->toBeTrue();
    expect($admin->can('manage-classroom'))->toBeTrue();

    // Test access-classroom gate
    expect($learner->can('access-classroom'))->toBeTrue();
    expect($instructor->can('access-classroom'))->toBeTrue();
    expect($admin->can('access-classroom'))->toBeTrue();

    // Test enroll-in-tracks gate
    expect($learner->can('enroll-in-tracks'))->toBeTrue();
    expect($instructor->can('enroll-in-tracks'))->toBeTrue();
    expect($admin->can('enroll-in-tracks'))->toBeTrue();

    // Test enroll-in-courses gate
    expect($learner->can('enroll-in-courses'))->toBeTrue();
    expect($instructor->can('enroll-in-courses'))->toBeTrue();
    expect($admin->can('enroll-in-courses'))->toBeTrue();

    // Test manage-module-assignments gate
    expect($learner->can('manage-module-assignments'))->toBeFalse();
    expect($instructor->can('manage-module-assignments'))->toBeTrue();
    expect($admin->can('manage-module-assignments'))->toBeTrue();

    // Test view-granular-progress gate
    expect($learner->can('view-granular-progress'))->toBeFalse();
    expect($instructor->can('view-granular-progress'))->toBeTrue();
    expect($admin->can('view-granular-progress'))->toBeTrue();
});

test('course policy permissions work correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    // Test create permissions
    expect($learner->can('create', Course::class))->toBeFalse();
    expect($instructor->can('create', Course::class))->toBeTrue();
    expect($admin->can('create', Course::class))->toBeTrue();
});

test('certificate policy permissions work correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    // Test create permissions
    expect($learner->can('create', Certificate::class))->toBeFalse();
    expect($instructor->can('create', Certificate::class))->toBeTrue();
    expect($admin->can('create', Certificate::class))->toBeTrue();
});

test('progress policy permissions work correctly', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    // Test create permissions (all users can create their own progress)
    expect($learner->can('create', LearningProgress::class))->toBeTrue();
    expect($instructor->can('create', LearningProgress::class))->toBeTrue();
    expect($admin->can('create', LearningProgress::class))->toBeTrue();
});
