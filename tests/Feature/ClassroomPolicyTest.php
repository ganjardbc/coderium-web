<?php

use App\Models\User;
use App\Models\Track;

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
});
