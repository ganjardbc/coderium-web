<?php

namespace Tests\Unit\Services;

use App\Services\ProgressService;
use Tests\TestCase;

class ProgressServiceTest extends TestCase
{
    private ProgressService $progressService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressService = new ProgressService();
    }

    public function test_progress_service_instantiation(): void
    {
        $this->assertInstanceOf(ProgressService::class, $this->progressService);
    }

    public function test_progress_service_methods_exist(): void
    {
        $methods = get_class_methods($this->progressService);

        $this->assertContains('markLessonComplete', $methods);
        $this->assertContains('calculateModuleProgress', $methods);
        $this->assertContains('calculateLevelProgress', $methods);
        $this->assertContains('calculateTrackProgress', $methods);
        $this->assertContains('getDetailedProgressReport', $methods);
        $this->assertContains('getProgressStatistics', $methods);
    }
}
