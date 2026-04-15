<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrackEnrollment;
use App\Models\LearningProgress;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDataIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classroom:verify-integrity
                            {--fix : Attempt to fix integrity issues}
                            {--detailed : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify data integrity for the enhanced classroom system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Enhanced Classroom System Data Integrity Verification...');
        $this->newLine();

        $issues = [];
        $fixes = [];

        // Run all integrity checks
        $issues = array_merge($issues, $this->checkReferentialIntegrity());
        $issues = array_merge($issues, $this->checkDataConsistency());
        $issues = array_merge($issues, $this->checkConstraintCompliance());
        $issues = array_merge($issues, $this->checkBusinessRuleCompliance());
        $issues = array_merge($issues, $this->checkMigrationIntegrity());

        // Report results
        if (empty($issues)) {
            $this->info('✅ All data integrity checks passed!');
        } else {
            $this->error('❌ Found ' . count($issues) . ' data integrity issues:');
            $this->newLine();

            foreach ($issues as $issue) {
                $this->warn("• {$issue['message']}");
                if ($this->option('detailed') && isset($issue['details'])) {
                    $this->line("  Details: {$issue['details']}");
                }

                // Attempt fixes if requested
                if ($this->option('fix') && isset($issue['fix'])) {
                    try {
                        $issue['fix']();
                        $fixes[] = $issue['message'];
                        $this->info("  ✅ Fixed");
                    } catch (\Exception $e) {
                        $this->error("  ❌ Fix failed: " . $e->getMessage());
                    }
                }
            }

            if (!empty($fixes)) {
                $this->newLine();
                $this->info('Fixed ' . count($fixes) . ' issues.');
            }
        }

        $this->newLine();
        $this->info('Data integrity verification completed.');

        return empty($issues) ? 0 : 1;
    }

    /**
     * Check referential integrity
     */
    private function checkReferentialIntegrity(): array
    {
        $issues = [];

        $this->info('Checking referential integrity...');

        // Check modules with invalid level_id
        $orphanedModules = DB::table('modules')
            ->leftJoin('levels', 'modules.level_id', '=', 'levels.id')
            ->whereNotNull('modules.level_id')
            ->whereNull('levels.id')
            ->get(['modules.id', 'modules.title', 'modules.level_id']);

        if ($orphanedModules->count() > 0) {
            $issues[] = [
                'message' => "Found {$orphanedModules->count()} modules with invalid level_id references",
                'details' => "Module IDs: " . $orphanedModules->pluck('id')->implode(', '),
                'fix' => function () use ($orphanedModules) {
                    DB::table('modules')
                        ->whereIn('id', $orphanedModules->pluck('id'))
                        ->update(['level_id' => null]);
                }
            ];
        }

        // Check lessons with invalid module_id
        $orphanedLessons = DB::table('lessons')
            ->leftJoin('modules', 'lessons.module_id', '=', 'modules.id')
            ->whereNull('modules.id')
            ->get(['lessons.id', 'lessons.title', 'lessons.module_id']);

        if ($orphanedLessons->count() > 0) {
            $issues[] = [
                'message' => "Found {$orphanedLessons->count()} lessons with invalid module_id references",
                'details' => "Lesson IDs: " . $orphanedLessons->pluck('id')->implode(', '),
            ];
        }

        // Check track enrollments with invalid references
        $orphanedTrackEnrollments = DB::table('track_enrollments')
            ->leftJoin('users', 'track_enrollments.user_id', '=', 'users.id')
            ->leftJoin('tracks', 'track_enrollments.track_id', '=', 'tracks.id')
            ->where(function ($query) {
                $query->whereNull('users.id')->orWhereNull('tracks.id');
            })
            ->get(['track_enrollments.id', 'track_enrollments.user_id', 'track_enrollments.track_id']);

        if ($orphanedTrackEnrollments->count() > 0) {
            $issues[] = [
                'message' => "Found {$orphanedTrackEnrollments->count()} track enrollments with invalid references",
                'details' => "Enrollment IDs: " . $orphanedTrackEnrollments->pluck('id')->implode(', '),
            ];
        }

        // Check course enrollments with invalid references
        if (DB::getSchemaBuilder()->hasTable('course_enrollments')) {
            $orphanedCourseEnrollments = DB::table('course_enrollments')
                ->leftJoin('users', 'course_enrollments.user_id', '=', 'users.id')
                ->leftJoin('courses', 'course_enrollments.course_id', '=', 'courses.id')
                ->where(function ($query) {
                    $query->whereNull('users.id')->orWhereNull('courses.id');
                })
                ->get(['course_enrollments.id', 'course_enrollments.user_id', 'course_enrollments.course_id']);

            if ($orphanedCourseEnrollments->count() > 0) {
                $issues[] = [
                    'message' => "Found {$orphanedCourseEnrollments->count()} course enrollments with invalid references",
                    'details' => "Enrollment IDs: " . $orphanedCourseEnrollments->pluck('id')->implode(', '),
                ];
            }
        }

        // Check learning progress with invalid references
        if (DB::getSchemaBuilder()->hasTable('learning_progress')) {
            $orphanedProgress = DB::table('learning_progress')
                ->leftJoin('users', 'learning_progress.user_id', '=', 'users.id')
                ->whereNull('users.id')
                ->get(['learning_progress.id', 'learning_progress.user_id']);

            if ($orphanedProgress->count() > 0) {
                $issues[] = [
                    'message' => "Found {$orphanedProgress->count()} learning progress records with invalid user references",
                    'details' => "Progress IDs: " . $orphanedProgress->pluck('id')->implode(', '),
                ];
            }
        }

        return $issues;
    }

    /**
     * Check data consistency
     */
    private function checkDataConsistency(): array
    {
        $issues = [];

        $this->info('Checking data consistency...');

        // Check progress percentages
        $invalidTrackProgress = DB::table('track_enrollments')
            ->where('progress_percentage', '<', 0)
            ->orWhere('progress_percentage', '>', 100)
            ->count();

        if ($invalidTrackProgress > 0) {
            $issues[] = [
                'message' => "Found {$invalidTrackProgress} track enrollments with invalid progress percentages",
                'fix' => function () {
                    DB::table('track_enrollments')
                        ->where('progress_percentage', '<', 0)
                        ->update(['progress_percentage' => 0]);
                    DB::table('track_enrollments')
                        ->where('progress_percentage', '>', 100)
                        ->update(['progress_percentage' => 100]);
                }
            ];
        }

        if (DB::getSchemaBuilder()->hasTable('course_enrollments')) {
            $invalidCourseProgress = DB::table('course_enrollments')
                ->where('progress_percentage', '<', 0)
                ->orWhere('progress_percentage', '>', 100)
                ->count();

            if ($invalidCourseProgress > 0) {
                $issues[] = [
                    'message' => "Found {$invalidCourseProgress} course enrollments with invalid progress percentages",
                    'fix' => function () {
                        DB::table('course_enrollments')
                            ->where('progress_percentage', '<', 0)
                            ->update(['progress_percentage' => 0]);
                        DB::table('course_enrollments')
                            ->where('progress_percentage', '>', 100)
                            ->update(['progress_percentage' => 100]);
                    }
                ];
            }
        }

        // Check order indexes
        $invalidOrders = DB::table('modules')
            ->where('order_index', '<', 0)
            ->count();

        if ($invalidOrders > 0) {
            $issues[] = [
                'message' => "Found {$invalidOrders} modules with negative order indexes",
                'fix' => function () {
                    DB::table('modules')
                        ->where('order_index', '<', 0)
                        ->update(['order_index' => 0]);
                }
            ];
        }

        // Check required fields for published content
        $invalidPublishedModules = DB::table('modules')
            ->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('title')->orWhere('title', '');
            })
            ->count();

        if ($invalidPublishedModules > 0) {
            $issues[] = [
                'message' => "Found {$invalidPublishedModules} published modules without titles",
            ];
        }

        $invalidPublishedLessons = DB::table('lessons')
            ->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('title')->orWhere('title', '')
                      ->orWhereNull('content')->orWhere('content', '');
            })
            ->count();

        if ($invalidPublishedLessons > 0) {
            $issues[] = [
                'message' => "Found {$invalidPublishedLessons} published lessons without titles or content",
            ];
        }

        return $issues;
    }

    /**
     * Check constraint compliance
     */
    private function checkConstraintCompliance(): array
    {
        $issues = [];

        $this->info('Checking constraint compliance...');

        // Check unique constraints
        $duplicateTrackEnrollments = DB::table('track_enrollments')
            ->select('user_id', 'track_id')
            ->groupBy('user_id', 'track_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateTrackEnrollments->count() > 0) {
            $issues[] = [
                'message' => "Found {$duplicateTrackEnrollments->count()} duplicate track enrollments",
                'details' => "Affected user-track pairs: " . $duplicateTrackEnrollments->count(),
            ];
        }

        if (DB::getSchemaBuilder()->hasTable('course_enrollments')) {
            $duplicateCourseEnrollments = DB::table('course_enrollments')
                ->select('user_id', 'course_id')
                ->groupBy('user_id', 'course_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            if ($duplicateCourseEnrollments->count() > 0) {
                $issues[] = [
                    'message' => "Found {$duplicateCourseEnrollments->count()} duplicate course enrollments",
                    'details' => "Affected user-course pairs: " . $duplicateCourseEnrollments->count(),
                ];
            }
        }

        // Check required fields
        $nullTrackTitles = DB::table('tracks')->whereNull('title')->count();
        if ($nullTrackTitles > 0) {
            $issues[] = [
                'message' => "Found {$nullTrackTitles} tracks with null titles",
            ];
        }

        $nullTrackSlugs = DB::table('tracks')->whereNull('slug')->count();
        if ($nullTrackSlugs > 0) {
            $issues[] = [
                'message' => "Found {$nullTrackSlugs} tracks with null slugs",
            ];
        }

        if (DB::getSchemaBuilder()->hasTable('courses')) {
            $nullCourseTitles = DB::table('courses')->whereNull('title')->count();
            if ($nullCourseTitles > 0) {
                $issues[] = [
                    'message' => "Found {$nullCourseTitles} courses with null titles",
                ];
            }

            $nullCourseSlugs = DB::table('courses')->whereNull('slug')->count();
            if ($nullCourseSlugs > 0) {
                $issues[] = [
                    'message' => "Found {$nullCourseSlugs} courses with null slugs",
                ];
            }
        }

        return $issues;
    }

    /**
     * Check business rule compliance
     */
    private function checkBusinessRuleCompliance(): array
    {
        $issues = [];

        $this->info('Checking business rule compliance...');

        // Check that completed enrollments have 100% progress
        $inconsistentCompletions = DB::table('track_enrollments')
            ->whereNotNull('completed_at')
            ->where('progress_percentage', '<', 100)
            ->count();

        if ($inconsistentCompletions > 0) {
            $issues[] = [
                'message' => "Found {$inconsistentCompletions} completed track enrollments with progress < 100%",
                'fix' => function () {
                    DB::table('track_enrollments')
                        ->whereNotNull('completed_at')
                        ->where('progress_percentage', '<', 100)
                        ->update(['progress_percentage' => 100]);
                }
            ];
        }

        if (DB::getSchemaBuilder()->hasTable('course_enrollments')) {
            $inconsistentCourseCompletions = DB::table('course_enrollments')
                ->whereNotNull('completed_at')
                ->where('progress_percentage', '<', 100)
                ->count();

            if ($inconsistentCourseCompletions > 0) {
                $issues[] = [
                    'message' => "Found {$inconsistentCourseCompletions} completed course enrollments with progress < 100%",
                    'fix' => function () {
                        DB::table('course_enrollments')
                            ->whereNotNull('completed_at')
                            ->where('progress_percentage', '<', 100)
                            ->update(['progress_percentage' => 100]);
                    }
                ];
            }
        }

        // Check that certificates are only issued for completed learning paths
        $invalidCertificates = DB::table('certificates')
            ->leftJoin('track_enrollments', function ($join) {
                $join->on('certificates.user_id', '=', 'track_enrollments.user_id')
                     ->on('certificates.track_id', '=', 'track_enrollments.track_id');
            })
            ->whereNotNull('certificates.track_id')
            ->whereNull('track_enrollments.completed_at')
            ->count();

        if ($invalidCertificates > 0) {
            $issues[] = [
                'message' => "Found {$invalidCertificates} certificates issued for incomplete tracks",
            ];
        }

        return $issues;
    }

    /**
     * Check migration-specific integrity
     */
    private function checkMigrationIntegrity(): array
    {
        $issues = [];

        $this->info('Checking migration integrity...');

        // Check that level_modules pivot table is consistent with module.level_id
        if (DB::getSchemaBuilder()->hasTable('level_modules')) {
            $missingPivotRecords = DB::table('modules')
                ->whereNotNull('modules.level_id')
                ->leftJoin('level_modules', function ($join) {
                    $join->on('modules.id', '=', 'level_modules.module_id')
                         ->on('modules.level_id', '=', 'level_modules.level_id');
                })
                ->whereNull('level_modules.id')
                ->count();

            if ($missingPivotRecords > 0) {
                $issues[] = [
                    'message' => "Found {$missingPivotRecords} modules with level_id but no corresponding level_modules record",
                    'fix' => function () {
                        $modules = DB::table('modules')
                            ->whereNotNull('modules.level_id')
                            ->leftJoin('level_modules', function ($join) {
                                $join->on('modules.id', '=', 'level_modules.module_id')
                                     ->on('modules.level_id', '=', 'level_modules.level_id');
                            })
                            ->whereNull('level_modules.id')
                            ->select('modules.id', 'modules.level_id', 'modules.order_index')
                            ->get();

                        foreach ($modules as $module) {
                            DB::table('level_modules')->insert([
                                'level_id' => $module->level_id,
                                'module_id' => $module->id,
                                'order' => $module->order_index ?? 0,
                                'is_required' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                ];
            }
        }

        // Check polymorphic certificate relationships
        if (DB::getSchemaBuilder()->hasColumn('certificates', 'certifiable_type')) {
            $inconsistentCertificates = DB::table('certificates')
                ->whereNotNull('track_id')
                ->where(function ($query) {
                    $query->whereNull('certifiable_type')
                          ->orWhereNull('certifiable_id')
                          ->orWhere('certifiable_type', '!=', 'App\\Models\\Track')
                          ->orWhereRaw('certifiable_id != track_id');
                })
                ->count();

            if ($inconsistentCertificates > 0) {
                $issues[] = [
                    'message' => "Found {$inconsistentCertificates} certificates with inconsistent polymorphic relationships",
                    'fix' => function () {
                        DB::table('certificates')
                            ->whereNotNull('track_id')
                            ->where(function ($query) {
                                $query->whereNull('certifiable_type')
                                      ->orWhereNull('certifiable_id');
                            })
                            ->update([
                                'certifiable_type' => 'App\\Models\\Track',
                                'certifiable_id' => DB::raw('track_id')
                            ]);
                    }
                ];
            }
        }

        return $issues;
    }
}
