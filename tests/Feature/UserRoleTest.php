<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user defaults to learner role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe('learner');
    expect($user->isLearner())->toBeTrue();
    expect($user->isInstructor())->toBeFalse();
    expect($user->isAdmin())->toBeFalse();
});

test('user can be instructor', function () {
    $user = User::factory()->create(['role' => 'instructor']);

    expect($user->role)->toBe('instructor');
    expect($user->isLearner())->toBeFalse();
    expect($user->isInstructor())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();
    expect($user->hasInstructorPermissions())->toBeTrue();
    expect($user->canManageClassroomContent())->toBeTrue();
});

test('user can be admin', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->role)->toBe('admin');
    expect($user->isLearner())->toBeFalse();
    expect($user->isInstructor())->toBeFalse();
    expect($user->isAdmin())->toBeTrue();
    expect($user->hasInstructorPermissions())->toBeTrue();
    expect($user->hasAdminPermissions())->toBeTrue();
    expect($user->canManageClassroomContent())->toBeTrue();
});

test('all users can enroll in tracks', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    expect($learner->canEnrollInTracks())->toBeTrue();
    expect($instructor->canEnrollInTracks())->toBeTrue();
    expect($admin->canEnrollInTracks())->toBeTrue();
});

test('all users can access classroom', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    expect($learner->canAccessClassroom())->toBeTrue();
    expect($instructor->canAccessClassroom())->toBeTrue();
    expect($admin->canAccessClassroom())->toBeTrue();
});

test('only instructors and admins have instructor permissions', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    expect($learner->hasInstructorPermissions())->toBeFalse();
    expect($instructor->hasInstructorPermissions())->toBeTrue();
    expect($admin->hasInstructorPermissions())->toBeTrue();
});

test('only admins have admin permissions', function () {
    $learner = User::factory()->create(['role' => 'learner']);
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    expect($learner->hasAdminPermissions())->toBeFalse();
    expect($instructor->hasAdminPermissions())->toBeFalse();
    expect($admin->hasAdminPermissions())->toBeTrue();
});
