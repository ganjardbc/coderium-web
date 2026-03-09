<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Populate level_modules from existing module.level_id relationships
        $this->migrateModuleLevelRelationships();

        // Step 2: Convert existing lesson_progress to new learning_progress format
        $this->migrateLessonProgressData();

        // Step 3: Populate polymorphic certificate fields from existing track_id
        $this->migrateCertificatePolymorphicFields();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear migrated data (but preserve original data)
        DB::table('level_modules')->truncate();
        DB::table('learning_progress')->truncate();

        // Reset polymorphic certificate fields
        DB::table('certificates')->update([
            'certifiable_type' => null,
            'certifiable_id' => null,
            'template_id' => null
        ]);
    }

    /**
     * Migrate existing module.level_id relationships to level_modules pivot table
     */
    private function migrateModuleLevelRelationships(): void
    {
        // Get all modules with their current level_id relationships
        $modules = DB::table('modules')
            ->whereNotNull('level_id')
            ->select('id', 'level_id', 'order_index')
            ->get();

        $levelModulesData = [];

        foreach ($modules as $module) {
            $levelModulesData[] = [
                'level_id' => $module->level_id,
                'module_id' => $module->id,
                'order' => $module->order_index ?? 0,
                'is_required' => true, // Default to required for existing modules
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Batch insert to level_modules table
        if (!empty($levelModulesData)) {
            // Use chunks to handle large datasets
            $chunks = array_chunk($levelModulesData, 1000);
            foreach ($chunks as $chunk) {
                DB::table('level_modules')->insert($chunk);
            }
        }

        // Only output info if running in console context
        if (app()->runningInConsole()) {
            echo "Migrated " . count($levelModulesData) . " module-level relationships to level_modules table.\n";
        }
    }

    /**
     * Convert existing lesson_progress to new learning_progress format
     */
    private function migrateLessonProgressData(): void
    {
        // Get all existing lesson progress records
        $lessonProgressRecords = DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->select(
                'lesson_progress.user_id',
                'lesson_progress.lesson_id',
                'lesson_progress.completed_at',
                'lesson_progress.time_spent',
                'lesson_progress.created_at',
                'lesson_progress.updated_at'
            )
            ->get();

        $learningProgressData = [];

        foreach ($lessonProgressRecords as $record) {
            // Calculate completion percentage (100% if completed, 0% if not)
            $completionPercentage = $record->completed_at ? 100.00 : 0.00;

            // Convert time_spent from seconds to minutes
            $timeSpentMinutes = intval($record->time_spent / 60);

            $learningProgressData[] = [
                'user_id' => $record->user_id,
                'progressable_type' => 'App\\Models\\Lesson',
                'progressable_id' => $record->lesson_id,
                'completion_percentage' => $completionPercentage,
                'time_spent_minutes' => $timeSpentMinutes,
                'engagement_score' => null, // No existing engagement data
                'last_accessed_at' => $record->updated_at,
                'completed_at' => $record->completed_at,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ];
        }

        // Batch insert to learning_progress table
        if (!empty($learningProgressData)) {
            // Use chunks to handle large datasets
            $chunks = array_chunk($learningProgressData, 1000);
            foreach ($chunks as $chunk) {
                DB::table('learning_progress')->insert($chunk);
            }
        }

        // Only output info if running in console context
        if (app()->runningInConsole()) {
            echo "Migrated " . count($learningProgressData) . " lesson progress records to learning_progress table.\n";
        }
    }

    /**
     * Populate polymorphic certificate fields from existing track_id relationships
     */
    private function migrateCertificatePolymorphicFields(): void
    {
        // Update existing certificates to use polymorphic relationships
        $updatedCount = DB::table('certificates')
            ->whereNotNull('track_id')
            ->whereNull('certifiable_type') // Only update records that haven't been migrated yet
            ->update([
                'certifiable_type' => 'App\\Models\\Track',
                'certifiable_id' => DB::raw('track_id'),
                'updated_at' => now(),
            ]);

        // Only output info if running in console context
        if (app()->runningInConsole()) {
            echo "Updated $updatedCount certificate records with polymorphic relationships.\n";
        }

        // Set template_id based on track's certificate template (if available)
        // This assumes tracks have a certificate_template_id field
        if (Schema::hasColumn('tracks', 'certificate_template_id')) {
            DB::table('certificates')
                ->join('tracks', 'certificates.track_id', '=', 'tracks.id')
                ->whereNotNull('tracks.certificate_template_id')
                ->whereNull('certificates.template_id')
                ->update([
                    'certificates.template_id' => DB::raw('tracks.certificate_template_id'),
                    'certificates.updated_at' => now(),
                ]);
        }
    }
};
