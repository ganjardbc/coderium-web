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
        // Add performance indexes for enhanced classroom system

        // Modules table indexes
        Schema::table('modules', function (Blueprint $table) {
            $this->addIndexIfNotExists('modules', ['is_published', 'level_id'], 'idx_modules_published_level');
            $this->addIndexIfNotExists('modules', ['is_published', 'order_index'], 'idx_modules_published_order');
            $this->addIndexIfNotExists('modules', 'estimated_duration', 'idx_modules_duration');
        });

        // Lessons table indexes
        Schema::table('lessons', function (Blueprint $table) {
            $this->addIndexIfNotExists('lessons', ['module_id', 'is_published'], 'idx_lessons_module_published');
            $this->addIndexIfNotExists('lessons', ['module_id', 'order_index'], 'idx_lessons_module_order');
            $this->addIndexIfNotExists('lessons', ['is_published', 'lesson_type'], 'idx_lessons_published_type');
        });

        // Track enrollments indexes
        Schema::table('track_enrollments', function (Blueprint $table) {
            $this->addIndexIfNotExists('track_enrollments', ['user_id', 'progress_percentage'], 'idx_track_enrollments_user_progress');
            $this->addIndexIfNotExists('track_enrollments', ['track_id', 'progress_percentage'], 'idx_track_enrollments_track_progress');
            $this->addIndexIfNotExists('track_enrollments', ['enrolled_at', 'completed_at'], 'idx_track_enrollments_dates');
            $this->addIndexIfNotExists('track_enrollments', 'progress_percentage', 'idx_track_enrollments_progress');
        });

        // Course enrollments indexes (if table exists)
        if (Schema::hasTable('course_enrollments')) {
            Schema::table('course_enrollments', function (Blueprint $table) {
                $this->addIndexIfNotExists('course_enrollments', ['user_id', 'progress_percentage'], 'idx_course_enrollments_user_progress');
                $this->addIndexIfNotExists('course_enrollments', ['course_id', 'progress_percentage'], 'idx_course_enrollments_course_progress');
                $this->addIndexIfNotExists('course_enrollments', ['enrolled_at', 'completed_at'], 'idx_course_enrollments_dates');
                $this->addIndexIfNotExists('course_enrollments', 'progress_percentage', 'idx_course_enrollments_progress');
            });
        }

        // Learning progress indexes (if table exists)
        if (Schema::hasTable('learning_progress')) {
            Schema::table('learning_progress', function (Blueprint $table) {
                $this->addIndexIfNotExists('learning_progress', ['user_id', 'completion_percentage'], 'idx_learning_progress_user_completion');
                $this->addIndexIfNotExists('learning_progress', ['progressable_type', 'completion_percentage'], 'idx_learning_progress_type_completion');
                $this->addIndexIfNotExists('learning_progress', ['last_accessed_at', 'completed_at'], 'idx_learning_progress_dates');
                $this->addIndexIfNotExists('learning_progress', 'time_spent_minutes', 'idx_learning_progress_time');
                $this->addIndexIfNotExists('learning_progress', 'engagement_score', 'idx_learning_progress_engagement');
            });
        }

        // Level modules pivot table indexes (if table exists)
        if (Schema::hasTable('level_modules')) {
            Schema::table('level_modules', function (Blueprint $table) {
                $this->addIndexIfNotExists('level_modules', ['level_id', 'order'], 'idx_level_modules_level_order');
                $this->addIndexIfNotExists('level_modules', ['module_id', 'is_required'], 'idx_level_modules_module_required');
                $this->addIndexIfNotExists('level_modules', 'is_required', 'idx_level_modules_required');
            });
        }

        // Course modules pivot table indexes (if table exists)
        if (Schema::hasTable('course_modules')) {
            Schema::table('course_modules', function (Blueprint $table) {
                $this->addIndexIfNotExists('course_modules', ['course_id', 'order'], 'idx_course_modules_course_order');
                $this->addIndexIfNotExists('course_modules', ['module_id', 'is_required'], 'idx_course_modules_module_required');
                $this->addIndexIfNotExists('course_modules', 'is_required', 'idx_course_modules_required');
            });
        }

        // Certificates table indexes
        Schema::table('certificates', function (Blueprint $table) {
            $this->addIndexIfNotExists('certificates', ['user_id', 'issued_at'], 'idx_certificates_user_issued');
            $this->addIndexIfNotExists('certificates', ['certifiable_type', 'issued_at'], 'idx_certificates_type_issued');
            $this->addIndexIfNotExists('certificates', 'issued_at', 'idx_certificates_issued');

            // Only add is_valid index if column exists
            if (Schema::hasColumn('certificates', 'is_valid')) {
                $this->addIndexIfNotExists('certificates', 'is_valid', 'idx_certificates_valid');
            }
        });

        // Tracks table indexes
        Schema::table('tracks', function (Blueprint $table) {
            $this->addIndexIfNotExists('tracks', ['is_published', 'is_premium'], 'idx_tracks_published_premium');
            $this->addIndexIfNotExists('tracks', ['instructor_id', 'is_published'], 'idx_tracks_instructor_published');
            $this->addIndexIfNotExists('tracks', 'difficulty_level', 'idx_tracks_difficulty');
        });

        // Levels table indexes
        Schema::table('levels', function (Blueprint $table) {
            $this->addIndexIfNotExists('levels', ['track_id', 'is_published'], 'idx_levels_track_published');
            $this->addIndexIfNotExists('levels', ['track_id', 'order_index'], 'idx_levels_track_order');
            $this->addIndexIfNotExists('levels', ['is_published', 'difficulty'], 'idx_levels_published_difficulty');
        });

        // Courses table indexes (if table exists)
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                $this->addIndexIfNotExists('courses', ['is_active', 'estimated_duration'], 'idx_courses_active_duration');
                $this->addIndexIfNotExists('courses', 'estimated_duration', 'idx_courses_duration');
            });
        }

        // Users table additional indexes for classroom functionality
        Schema::table('users', function (Blueprint $table) {
            $this->addIndexIfNotExists('users', ['role', 'created_at'], 'idx_users_role_created');
            $this->addIndexIfNotExists('users', 'role', 'idx_users_role');
        });

        // Assessment related indexes
        if (Schema::hasTable('assessments')) {
            Schema::table('assessments', function (Blueprint $table) {
                $this->addIndexIfNotExists('assessments', ['assessable_type', 'assessable_id'], 'idx_assessments_assessable');
                $this->addIndexIfNotExists('assessments', ['max_attempts', 'passing_score'], 'idx_assessments_attempts_score');
            });
        }

        if (Schema::hasTable('assessment_attempts')) {
            Schema::table('assessment_attempts', function (Blueprint $table) {
                $this->addIndexIfNotExists('assessment_attempts', ['user_id', 'assessment_id'], 'idx_assessment_attempts_user_assessment');
                $this->addIndexIfNotExists('assessment_attempts', ['assessment_id', 'passed'], 'idx_assessment_attempts_assessment_passed');
                $this->addIndexIfNotExists('assessment_attempts', ['started_at', 'completed_at'], 'idx_assessment_attempts_dates');
                $this->addIndexIfNotExists('assessment_attempts', 'passed', 'idx_assessment_attempts_passed');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove performance indexes

        // Modules table indexes
        Schema::table('modules', function (Blueprint $table) {
            $this->dropIndexIfExists('modules', 'idx_modules_published_level');
            $this->dropIndexIfExists('modules', 'idx_modules_published_order');
            $this->dropIndexIfExists('modules', 'idx_modules_duration');
        });

        // Lessons table indexes
        Schema::table('lessons', function (Blueprint $table) {
            $this->dropIndexIfExists('lessons', 'idx_lessons_module_published');
            $this->dropIndexIfExists('lessons', 'idx_lessons_module_order');
            $this->dropIndexIfExists('lessons', 'idx_lessons_published_type');
        });

        // Track enrollments indexes
        Schema::table('track_enrollments', function (Blueprint $table) {
            $this->dropIndexIfExists('track_enrollments', 'idx_track_enrollments_user_progress');
            $this->dropIndexIfExists('track_enrollments', 'idx_track_enrollments_track_progress');
            $this->dropIndexIfExists('track_enrollments', 'idx_track_enrollments_dates');
            $this->dropIndexIfExists('track_enrollments', 'idx_track_enrollments_progress');
        });

        // Course enrollments indexes
        if (Schema::hasTable('course_enrollments')) {
            Schema::table('course_enrollments', function (Blueprint $table) {
                $this->dropIndexIfExists('course_enrollments', 'idx_course_enrollments_user_progress');
                $this->dropIndexIfExists('course_enrollments', 'idx_course_enrollments_course_progress');
                $this->dropIndexIfExists('course_enrollments', 'idx_course_enrollments_dates');
                $this->dropIndexIfExists('course_enrollments', 'idx_course_enrollments_progress');
            });
        }

        // Learning progress indexes
        if (Schema::hasTable('learning_progress')) {
            Schema::table('learning_progress', function (Blueprint $table) {
                $this->dropIndexIfExists('learning_progress', 'idx_learning_progress_user_completion');
                $this->dropIndexIfExists('learning_progress', 'idx_learning_progress_type_completion');
                $this->dropIndexIfExists('learning_progress', 'idx_learning_progress_dates');
                $this->dropIndexIfExists('learning_progress', 'idx_learning_progress_time');
                $this->dropIndexIfExists('learning_progress', 'idx_learning_progress_engagement');
            });
        }

        // Level modules indexes
        if (Schema::hasTable('level_modules')) {
            Schema::table('level_modules', function (Blueprint $table) {
                $this->dropIndexIfExists('level_modules', 'idx_level_modules_level_order');
                $this->dropIndexIfExists('level_modules', 'idx_level_modules_module_required');
                $this->dropIndexIfExists('level_modules', 'idx_level_modules_required');
            });
        }

        // Course modules indexes
        if (Schema::hasTable('course_modules')) {
            Schema::table('course_modules', function (Blueprint $table) {
                $this->dropIndexIfExists('course_modules', 'idx_course_modules_course_order');
                $this->dropIndexIfExists('course_modules', 'idx_course_modules_module_required');
                $this->dropIndexIfExists('course_modules', 'idx_course_modules_required');
            });
        }

        // Certificates indexes
        Schema::table('certificates', function (Blueprint $table) {
            $this->dropIndexIfExists('certificates', 'idx_certificates_user_issued');
            $this->dropIndexIfExists('certificates', 'idx_certificates_type_issued');
            $this->dropIndexIfExists('certificates', 'idx_certificates_issued');

            // Only drop is_valid index if it exists
            if (Schema::hasColumn('certificates', 'is_valid')) {
                $this->dropIndexIfExists('certificates', 'idx_certificates_valid');
            }
        });

        // Tracks indexes
        Schema::table('tracks', function (Blueprint $table) {
            $this->dropIndexIfExists('tracks', 'idx_tracks_published_premium');
            $this->dropIndexIfExists('tracks', 'idx_tracks_instructor_published');
            $this->dropIndexIfExists('tracks', 'idx_tracks_difficulty');
        });

        // Levels indexes
        Schema::table('levels', function (Blueprint $table) {
            $this->dropIndexIfExists('levels', 'idx_levels_track_published');
            $this->dropIndexIfExists('levels', 'idx_levels_track_order');
            $this->dropIndexIfExists('levels', 'idx_levels_published_difficulty');
        });

        // Courses indexes
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                $this->dropIndexIfExists('courses', 'idx_courses_active_duration');
                $this->dropIndexIfExists('courses', 'idx_courses_duration');
            });
        }

        // Users indexes
        Schema::table('users', function (Blueprint $table) {
            $this->dropIndexIfExists('users', 'idx_users_role_created');
            $this->dropIndexIfExists('users', 'idx_users_role');
        });

        // Assessment indexes
        if (Schema::hasTable('assessments')) {
            Schema::table('assessments', function (Blueprint $table) {
                $this->dropIndexIfExists('assessments', 'idx_assessments_assessable');
                $this->dropIndexIfExists('assessments', 'idx_assessments_attempts_score');
            });
        }

        if (Schema::hasTable('assessment_attempts')) {
            Schema::table('assessment_attempts', function (Blueprint $table) {
                $this->dropIndexIfExists('assessment_attempts', 'idx_assessment_attempts_user_assessment');
                $this->dropIndexIfExists('assessment_attempts', 'idx_assessment_attempts_assessment_passed');
                $this->dropIndexIfExists('assessment_attempts', 'idx_assessment_attempts_dates');
                $this->dropIndexIfExists('assessment_attempts', 'idx_assessment_attempts_passed');
            });
        }
    }

    /**
     * Add index if it doesn't already exist
     */
    private function addIndexIfNotExists(string $table, $columns, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($columns, $indexName) {
                $tableBlueprint->index($columns, $indexName);
            });
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName) {
                $tableBlueprint->dropIndex($indexName);
            });
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        // For MySQL
        if ($connection->getDriverName() === 'mysql') {
            $indexes = DB::select("
                SELECT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
            ", [$databaseName, $table, $indexName]);

            return count($indexes) > 0;
        }

        // For SQLite
        if ($connection->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list({$table})");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        // For PostgreSQL
        if ($connection->getDriverName() === 'pgsql') {
            $indexes = DB::select("
                SELECT indexname
                FROM pg_indexes
                WHERE tablename = ? AND indexname = ?
            ", [$table, $indexName]);

            return count($indexes) > 0;
        }

        // Default: assume index doesn't exist
        return false;
    }
};
