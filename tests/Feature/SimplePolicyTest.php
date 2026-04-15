<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('classroom gates work correctly', function () {
    $learner = new User(['role' => 'learner']);
    $instructor = new User(['role' => 'instructor']);
    $admin = new User(['role' => 'admin']);

    // Test manage-classroom gate
    expect(Gate::forUser($learner)->allows('manage-classroom'))->toBeFalse();
    expect(Gate::forUser($instructor)->allows('manage-classroom'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('manage-classroom'))->toBeTrue();

    // Test access-classroom gate
    expect(Gate::forUser($learner)->allows('access-classroom'))->toBeTrue();
    expect(Gate::forUser($instructor)->allows('access-classroom'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('access-classroom'))->toBeTrue();

    // Test enroll-in-tracks gate
    expect(Gate::forUser($learner)->allows('enroll-in-tracks'))->toBeTrue();
    expect(Gate::forUser($instructor)->allows('enroll-in-tracks'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('enroll-in-tracks'))->toBeTrue();
});
