<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Module;
use App\Models\CourseEnrollment;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseOnlySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates only courses and basic relationships,
     * without the full complexity of the main CourseSeeder.
     */
    public function run(): void
    {
        $this->command->info('Creating basic course data...');

        // Ensure we have certificate templates
        $this->ensureCertificateTemplates();

        // Get available modules
        $modules = Module::where('is_published', true)->get();

        if ($modules->isEmpty()) {
            $this->command->info('No published modules found. Creating some basic modules...');
            $modules = $this->createBasicModules();
        }

        // Create courses
        $courses = $this->createBasicCourses($modules);

        // Create some enrollments
        $this->createBasicEnrollments($courses);

        $this->command->info('Basic course data created successfully!');
    }

    private function ensureCertificateTemplates(): void
    {
        if (CertificateTemplate::count() === 0) {
            CertificateTemplate::factory()->create([
                'name' => 'Course Completion Certificate',
                'description' => 'Standard certificate for course completion',
            ]);

            CertificateTemplate::factory()->create([
                'name' => 'Professional Certificate',
                'description' => 'Professional development certificate',
            ]);
        }
    }

    private function createBasicModules()
    {
        return collect([
            Module::factory()->create([
                'level_id' => null,
                'title' => 'Web Development Basics',
                'description' => 'Introduction to HTML, CSS, and JavaScript',
                'estimated_duration' => 120,
                'is_published' => true,
            ]),
            Module::factory()->create([
                'level_id' => null,
                'title' => 'Database Fundamentals',
                'description' => 'Learn SQL and database design',
                'estimated_duration' => 90,
                'is_published' => true,
            ]),
            Module::factory()->create([
                'level_id' => null,
                'title' => 'API Development',
                'description' => 'Build REST APIs',
                'estimated_duration' => 150,
                'is_published' => true,
            ]),
        ]);
    }

    private function createBasicCourses($modules)
    {
        $certificateTemplate = CertificateTemplate::first();

        $courses = collect([
            Course::factory()->create([
                'title' => 'Complete Web Development',
                'description' => 'Learn full-stack web development from scratch',
                'slug' => 'complete-web-development',
                'is_active' => true,
                'estimated_duration' => 480,
                'certificate_template_id' => $certificateTemplate->id,
            ]),
            Course::factory()->create([
                'title' => 'Frontend Development Mastery',
                'description' => 'Master modern frontend technologies',
                'slug' => 'frontend-development-mastery',
                'is_active' => true,
                'estimated_duration' => 360,
                'certificate_template_id' => $certificateTemplate->id,
            ]),
            Course::factory()->create([
                'title' => 'Backend API Development',
                'description' => 'Build scalable backend APIs',
                'slug' => 'backend-api-development-basic',
                'is_active' => true,
                'estimated_duration' => 300,
                'certificate_template_id' => $certificateTemplate->id,
            ]),
        ]);

        // Assign modules to courses
        foreach ($courses as $index => $course) {
            $moduleCount = min(3, $modules->count());
            $courseModules = $modules->take($moduleCount);

            foreach ($courseModules as $moduleIndex => $module) {
                $course->modules()->attach($module->id, [
                    'order' => $moduleIndex + 1,
                    'is_required' => $moduleIndex < 2, // First 2 are required
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $courses;
    }

    private function createBasicEnrollments($courses)
    {
        $students = User::where('role', 'learner')->limit(5)->get();

        if ($students->isEmpty()) {
            $students = User::factory(5)->create([
                'role' => 'learner',
                'password' => 'password',
            ]);
        }

        foreach ($students as $student) {
            $enrollmentCount = rand(1, 2);
            $selectedCourses = $courses->random($enrollmentCount);

            foreach ($selectedCourses as $course) {
                CourseEnrollment::factory()->create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'enrolled_at' => now()->subDays(rand(1, 30)),
                    'progress_percentage' => rand(0, 100),
                ]);
            }
        }
    }
}
