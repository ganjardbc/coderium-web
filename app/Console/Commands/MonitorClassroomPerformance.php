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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MonitorClassroomPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classroom:monitor-performance
                            {--cache : Test cache performance}
                            {--queries : Show slow queries}
                            {--memory : Show memory usage}
                            {--detailed : Show detailed metrics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor performance metrics for the enhanced classroom system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Enhanced Classroom System Performance Monitor');
        $this->newLine();

        $metrics = [];

        // Database performance metrics
        $metrics['database'] = $this->measureDatabasePerformance();

        // Cache performance metrics
        if ($this->option('cache')) {
            $metrics['cache'] = $this->measureCachePerformance();
        }

        // Memory usage metrics
        if ($this->option('memory')) {
            $metrics['memory'] = $this->measureMemoryUsage();
        }

        // Query performance metrics
        if ($this->option('queries')) {
            $metrics['queries'] = $this->measureQueryPerformance();
        }

        // System metrics
        $metrics['system'] = $this->getSystemMetrics();

        // Display results
        $this->displayMetrics($metrics);

        // Performance recommendations
        $this->showRecommendations($metrics);

        return 0;
    }

    /**
     * Measure database performance
     */
    private function measureDatabasePerformance(): array
    {
        $this->info('Measuring database performance...');

        $metrics = [];
        $startTime = microtime(true);

        // Test basic queries
        $metrics['track_count'] = Track::count();
        $metrics['course_count'] = Course::count();
        $metrics['module_count'] = Module::count();
        $metrics['lesson_count'] = Lesson::count();
        $metrics['enrollment_count'] = TrackEnrollment::count() + CourseEnrollment::count();

        $basicQueryTime = microtime(true) - $startTime;
        $metrics['basic_query_time'] = round($basicQueryTime * 1000, 2); // ms

        // Test complex queries
        $startTime = microtime(true);

        // Complex track query with relationships
        $trackWithContent = Track::with(['levels.modules.lessons'])
            ->where('is_published', true)
            ->first();

        // Complex course query with relationships
        $courseWithContent = Course::with(['modules.lessons'])
            ->where('is_active', true)
            ->first();

        // Progress aggregation query
        if ($trackWithContent) {
            $progressQuery = TrackEnrollment::where('track_id', $trackWithContent->id)
                ->with('user')
                ->get();
        }

        $complexQueryTime = microtime(true) - $startTime;
        $metrics['complex_query_time'] = round($complexQueryTime * 1000, 2); // ms

        // Test join performance
        $startTime = microtime(true);

        $joinQuery = DB::table('modules')
            ->leftJoin('levels', 'modules.level_id', '=', 'levels.id')
            ->leftJoin('level_modules', 'modules.id', '=', 'level_modules.module_id')
            ->leftJoin('course_modules', 'modules.id', '=', 'course_modules.module_id')
            ->select('modules.*', 'levels.title as level_title')
            ->limit(100)
            ->get();

        $joinQueryTime = microtime(true) - $startTime;
        $metrics['join_query_time'] = round($joinQueryTime * 1000, 2); // ms

        return $metrics;
    }

    /**
     * Measure cache performance
     */
    private function measureCachePerformance(): array
    {
        $this->info('Measuring cache performance...');

        $metrics = [];

        // Test cache write performance
        $startTime = microtime(true);
        $testData = ['test' => 'data', 'timestamp' => now()];

        for ($i = 0; $i < 100; $i++) {
            Cache::put("test_key_{$i}", $testData, 60);
        }

        $writeTime = microtime(true) - $startTime;
        $metrics['cache_write_time'] = round($writeTime * 1000, 2); // ms

        // Test cache read performance
        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            Cache::get("test_key_{$i}");
        }

        $readTime = microtime(true) - $startTime;
        $metrics['cache_read_time'] = round($readTime * 1000, 2); // ms

        // Test cache hit ratio
        $hits = 0;
        for ($i = 0; $i < 100; $i++) {
            if (Cache::has("test_key_{$i}")) {
                $hits++;
            }
        }

        $metrics['cache_hit_ratio'] = round(($hits / 100) * 100, 2); // percentage

        // Clean up test cache
        for ($i = 0; $i < 100; $i++) {
            Cache::forget("test_key_{$i}");
        }

        return $metrics;
    }

    /**
     * Measure memory usage
     */
    private function measureMemoryUsage(): array
    {
        $this->info('Measuring memory usage...');

        $metrics = [];

        $metrics['current_memory'] = round(memory_get_usage() / 1024 / 1024, 2); // MB
        $metrics['peak_memory'] = round(memory_get_peak_usage() / 1024 / 1024, 2); // MB
        $metrics['memory_limit'] = ini_get('memory_limit');

        // Test memory usage with large dataset
        $startMemory = memory_get_usage();

        // Load large dataset
        $tracks = Track::with(['levels.modules.lessons'])->get();
        $courses = Course::with(['modules.lessons'])->get();

        $afterLoadMemory = memory_get_usage();
        $metrics['dataset_memory_usage'] = round(($afterLoadMemory - $startMemory) / 1024 / 1024, 2); // MB

        // Test memory usage with progress calculations
        $startMemory = memory_get_usage();

        if ($tracks->count() > 0 && $tracks->first()->enrollments()->count() > 0) {
            $enrollment = $tracks->first()->enrollments()->first();
            if ($enrollment) {
                // Simulate progress calculation
                $progressData = [];
                foreach ($tracks->first()->levels as $level) {
                    foreach ($level->modules as $module) {
                        $progressData[] = [
                            'module_id' => $module->id,
                            'completion' => rand(0, 100),
                            'time_spent' => rand(10, 120),
                        ];
                    }
                }
            }
        }

        $afterCalculationMemory = memory_get_usage();
        $metrics['calculation_memory_usage'] = round(($afterCalculationMemory - $startMemory) / 1024 / 1024, 2); // MB

        return $metrics;
    }

    /**
     * Measure query performance
     */
    private function measureQueryPerformance(): array
    {
        $this->info('Measuring query performance...');

        $metrics = [];
        $slowQueries = [];

        // Enable query logging
        DB::enableQueryLog();

        // Run various queries and measure performance
        $queries = [
            'Track with levels' => function () {
                return Track::with('levels')->get();
            },
            'Course with modules' => function () {
                return Course::with('modules')->get();
            },
            'Module assignments' => function () {
                return DB::table('level_modules')
                    ->join('modules', 'level_modules.module_id', '=', 'modules.id')
                    ->join('levels', 'level_modules.level_id', '=', 'levels.id')
                    ->select('modules.title', 'levels.title as level_title')
                    ->get();
            },
            'Progress aggregation' => function () {
                return DB::table('track_enrollments')
                    ->join('tracks', 'track_enrollments.track_id', '=', 'tracks.id')
                    ->join('users', 'track_enrollments.user_id', '=', 'users.id')
                    ->select('tracks.title', 'users.name', 'track_enrollments.progress_percentage')
                    ->get();
            },
        ];

        foreach ($queries as $name => $query) {
            $startTime = microtime(true);
            $result = $query();
            $endTime = microtime(true);

            $executionTime = round(($endTime - $startTime) * 1000, 2);
            $metrics[$name] = $executionTime;

            if ($executionTime > 100) { // Queries taking more than 100ms
                $slowQueries[] = [
                    'name' => $name,
                    'time' => $executionTime,
                    'count' => is_countable($result) ? count($result) : 'N/A',
                ];
            }
        }

        $queryLog = DB::getQueryLog();
        $metrics['total_queries'] = count($queryLog);
        $metrics['slow_queries'] = $slowQueries;

        DB::disableQueryLog();

        return $metrics;
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        $metrics = [];

        // Database connection info
        $metrics['database_driver'] = config('database.default');
        $metrics['database_name'] = config('database.connections.' . config('database.default') . '.database');

        // Cache driver info
        $metrics['cache_driver'] = config('cache.default');

        // PHP version and settings
        $metrics['php_version'] = PHP_VERSION;
        $metrics['max_execution_time'] = ini_get('max_execution_time');
        $metrics['upload_max_filesize'] = ini_get('upload_max_filesize');

        // Laravel version
        $metrics['laravel_version'] = app()->version();

        // Environment
        $metrics['environment'] = app()->environment();

        return $metrics;
    }

    /**
     * Display performance metrics
     */
    private function displayMetrics(array $metrics): void
    {
        $this->newLine();
        $this->info('=== PERFORMANCE METRICS ===');
        $this->newLine();

        // Database metrics
        if (isset($metrics['database'])) {
            $this->line('<fg=cyan>Database Performance:</fg=cyan>');
            $db = $metrics['database'];

            $this->line("  Tracks: {$db['track_count']}");
            $this->line("  Courses: {$db['course_count']}");
            $this->line("  Modules: {$db['module_count']}");
            $this->line("  Lessons: {$db['lesson_count']}");
            $this->line("  Enrollments: {$db['enrollment_count']}");
            $this->line("  Basic Query Time: {$db['basic_query_time']}ms");
            $this->line("  Complex Query Time: {$db['complex_query_time']}ms");
            $this->line("  Join Query Time: {$db['join_query_time']}ms");
            $this->newLine();
        }

        // Cache metrics
        if (isset($metrics['cache'])) {
            $this->line('<fg=cyan>Cache Performance:</fg=cyan>');
            $cache = $metrics['cache'];

            $this->line("  Write Time (100 ops): {$cache['cache_write_time']}ms");
            $this->line("  Read Time (100 ops): {$cache['cache_read_time']}ms");
            $this->line("  Hit Ratio: {$cache['cache_hit_ratio']}%");
            $this->newLine();
        }

        // Memory metrics
        if (isset($metrics['memory'])) {
            $this->line('<fg=cyan>Memory Usage:</fg=cyan>');
            $memory = $metrics['memory'];

            $this->line("  Current Memory: {$memory['current_memory']}MB");
            $this->line("  Peak Memory: {$memory['peak_memory']}MB");
            $this->line("  Memory Limit: {$memory['memory_limit']}");
            $this->line("  Dataset Memory: {$memory['dataset_memory_usage']}MB");
            $this->line("  Calculation Memory: {$memory['calculation_memory_usage']}MB");
            $this->newLine();
        }

        // Query metrics
        if (isset($metrics['queries'])) {
            $this->line('<fg=cyan>Query Performance:</fg=cyan>');
            $queries = $metrics['queries'];

            foreach ($queries as $name => $time) {
                if ($name !== 'total_queries' && $name !== 'slow_queries') {
                    $color = $time > 100 ? 'red' : ($time > 50 ? 'yellow' : 'green');
                    $this->line("  <fg={$color}>{$name}: {$time}ms</fg={$color}>");
                }
            }

            $this->line("  Total Queries: {$queries['total_queries']}");

            if (!empty($queries['slow_queries'])) {
                $this->line("  <fg=red>Slow Queries:</fg=red>");
                foreach ($queries['slow_queries'] as $slow) {
                    $this->line("    - {$slow['name']}: {$slow['time']}ms ({$slow['count']} records)");
                }
            }
            $this->newLine();
        }

        // System metrics
        if (isset($metrics['system'])) {
            $this->line('<fg=cyan>System Information:</fg=cyan>');
            $system = $metrics['system'];

            $this->line("  PHP Version: {$system['php_version']}");
            $this->line("  Laravel Version: {$system['laravel_version']}");
            $this->line("  Environment: {$system['environment']}");
            $this->line("  Database: {$system['database_driver']} ({$system['database_name']})");
            $this->line("  Cache Driver: {$system['cache_driver']}");
            $this->line("  Max Execution Time: {$system['max_execution_time']}s");
            $this->newLine();
        }
    }

    /**
     * Show performance recommendations
     */
    private function showRecommendations(array $metrics): void
    {
        $this->info('=== PERFORMANCE RECOMMENDATIONS ===');
        $this->newLine();

        $recommendations = [];

        // Database recommendations
        if (isset($metrics['database'])) {
            $db = $metrics['database'];

            if ($db['basic_query_time'] > 50) {
                $recommendations[] = "Consider adding database indexes - basic queries taking {$db['basic_query_time']}ms";
            }

            if ($db['complex_query_time'] > 200) {
                $recommendations[] = "Optimize complex queries with eager loading - taking {$db['complex_query_time']}ms";
            }

            if ($db['join_query_time'] > 100) {
                $recommendations[] = "Consider denormalizing data or adding indexes for join queries - taking {$db['join_query_time']}ms";
            }
        }

        // Cache recommendations
        if (isset($metrics['cache'])) {
            $cache = $metrics['cache'];

            if ($cache['cache_hit_ratio'] < 80) {
                $recommendations[] = "Improve cache hit ratio - currently {$cache['cache_hit_ratio']}%";
            }

            if ($cache['cache_read_time'] > 50) {
                $recommendations[] = "Consider faster cache driver - read time {$cache['cache_read_time']}ms";
            }
        }

        // Memory recommendations
        if (isset($metrics['memory'])) {
            $memory = $metrics['memory'];

            if ($memory['dataset_memory_usage'] > 50) {
                $recommendations[] = "Consider pagination for large datasets - using {$memory['dataset_memory_usage']}MB";
            }

            if ($memory['peak_memory'] > 100) {
                $recommendations[] = "Monitor memory usage - peak at {$memory['peak_memory']}MB";
            }
        }

        // Query recommendations
        if (isset($metrics['queries']) && !empty($metrics['queries']['slow_queries'])) {
            $recommendations[] = "Optimize slow queries - " . count($metrics['queries']['slow_queries']) . " queries taking >100ms";
        }

        // General recommendations
        $recommendations[] = "Implement caching for frequently accessed data";
        $recommendations[] = "Add database indexes for commonly queried columns";
        $recommendations[] = "Use eager loading to reduce N+1 query problems";
        $recommendations[] = "Consider implementing pagination for large result sets";
        $recommendations[] = "Monitor query performance in production";

        if (empty($recommendations)) {
            $this->info('✅ No performance issues detected!');
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line("• {$recommendation}");
            }
        }

        $this->newLine();
    }
}
