<?php

use App\Models\User;

test('user role methods work correctly', function () {
    // Test learner role
    $learner = new User(['role' => 'learner']);
    expect($learner->isLearner())->toBeTrue();
    expect($learner->isInstructor())->toBeFalse();
    expect($learner->isAdmin())->toBeFalse();
    expect($learner->hasInstructorPermissions())->toBeFalse();
    expect($learner->hasAdminPermissions())->toBeFalse();
    expect($learner->canManageClassroomContent())->toBeFalse();
    expect($learner->canEnrollInTracks())->toBeTrue();
    expect($learner->canAccessClassroom())->toBeTrue();

    // Test instructor role
    $instructor = new User(['role' => 'instructor']);
    expect($instructor->isLearner())->toBeFalse();
    expect($instructor->isInstructor())->toBeTrue();
    expect($instructor->isAdmin())->toBeFalse();
    expect($instructor->hasInstructorPermissions())->toBeTrue();
    expect($instructor->hasAdminPermissions())->toBeFalse();
    expect($instructor->canManageClassroomContent())->toBeTrue();
    expect($instructor->canEnrollInTracks())->toBeTrue();
    expect($instructor->canAccessClassroom())->toBeTrue();

    // Test admin role
    $admin = new User(['role' => 'admin']);
    expect($admin->isLearner())->toBeFalse();
    expect($admin->isInstructor())->toBeFalse();
    expect($admin->isAdmin())->toBeTrue();
    expect($admin->hasInstructorPermissions())->toBeTrue();
    expect($admin->hasAdminPermissions())->toBeTrue();
    expect($admin->canManageClassroomContent())->toBeTrue();
    expect($admin->canEnrollInTracks())->toBeTrue();
    expect($admin->canAccessClassroom())->toBeTrue();
});
