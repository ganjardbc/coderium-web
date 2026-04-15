<?php

namespace Tests\Unit\Services;

use App\Models\Track;
use App\Models\User;
use App\Models\TrackEnrollment;
use App\Services\EnrollmentService;
use App\Services\ConstraintEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentService $enrollmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $constraintService = $this->app->make(ConstraintEnforcementService::class);
        $this->enrollmentService = new EnrollmentService($constraintService);
    }

    public function test_enrollment_service_methods_exist(): void
    {
        $methods = get_class_methods($this->enrollmentService);

        $this->assertContains('enrollUser', $methods);
        $this->assertContains('checkEnrollmentAccess', $methods);
        $this->assertContains('getEnrolledTracks', $methods);
        $this->assertContains('getEnrollment', $methods);
        $this->assertContains('unenrollUser', $methods);
        $this->assertContains('getEnrollmentStatistics', $methods);
    }

    public function test_enrollment_service_instantiation(): void
    {
        $this->assertInstanceOf(EnrollmentService::class, $this->enrollmentService);
    }
}
