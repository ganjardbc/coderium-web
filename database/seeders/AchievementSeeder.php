<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Services\AchievementService;
use App\Services\ProgressService;

class AchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $progressService = app(ProgressService::class);
        $achievementService = new AchievementService($progressService);

        $achievementService->initializeDefaultAchievements();

        $this->command->info('Default achievements have been created successfully.');
    }
}
