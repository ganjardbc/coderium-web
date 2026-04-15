<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Module;
use App\Models\CourseEnrollment;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Discussion;
use App\Models\DiscussionPost;
use App\Models\LearningProgress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating comprehensive course data...');

        // Get or create certificate templates
        $certificateTemplates = $this->ensureCertificateTemplates();

        // Get existing modules or create some if none exist
        $modules = $this->getOrCreateModules();

        // Create courses with complete structure
        $courses = $this->createCoursesWithContent($certificateTemplates, $modules);

        // Create enrollments and progress
        $this->createEnrollmentsAndProgress($courses);

        $this->command->info('Course data created successfully!');
    }

    private function ensureCertificateTemplates(): array
    {
        $templates = CertificateTemplate::all();

        if ($templates->count() < 3) {
            $this->command->info('Creating certificate templates...');

            // Create missing templates
            $existingCount = $templates->count();
            $templatesData = [
                [
                    'name' => 'Course Completion Certificate',
                    'description' => 'Standard certificate for course completion',
                    'template_content' => '<html><body><h1>Certificate of Completion</h1><p>This certifies that {student_name} has successfully completed {course_name}.</p></body></html>',
                    'is_default' => false,
                ],
                [
                    'name' => 'Advanced Achievement Certificate',
                    'description' => 'Certificate for advanced course completion with high scores',
                    'template_content' => '<html><body><h1>Advanced Achievement Certificate</h1><p>This certifies that {student_name} has achieved excellence in {course_name}.</p></body></html>',
                    'is_default' => false,
                ],
                [
                    'name' => 'Professional Development Certificate',
                    'description' => 'Certificate for professional development courses',
                    'template_content' => '<html><body><h1>Professional Development Certificate</h1><p>This certifies that {student_name} has completed professional development in {course_name}.</p></body></html>',
                    'is_default' => false,
                ],
            ];

            for ($i = $existingCount; $i < 3; $i++) {
                $templates->push(CertificateTemplate::create($templatesData[$i]));
            }
        }

        return $templates->toArray();
    }

    private function getOrCreateModules(): array
    {
        $modules = Module::with('level.track')->get();

        if ($modules->count() < 10) {
            $this->command->info('Not enough modules found. Please run ClassroomSeeder first to create tracks, levels, and modules.');
            $this->command->info('Creating some standalone modules for courses...');

            // Create some standalone modules (without level_id)
            $standaloneModules = collect([
                Module::factory()->create([
                    'level_id' => null,
                    'title' => 'Introduction to Web Development',
                    'description' => 'Learn the basics of HTML, CSS, and JavaScript',
                    'estimated_duration' => 120,
                    'is_published' => true,
                ]),
                Module::factory()->create([
                    'level_id' => null,
                    'title' => 'Database Design Fundamentals',
                    'description' => 'Master database design principles and SQL',
                    'estimated_duration' => 180,
                    'is_published' => true,
                ]),
                Module::factory()->create([
                    'level_id' => null,
                    'title' => 'API Development with REST',
                    'description' => 'Build RESTful APIs using modern frameworks',
                    'estimated_duration' => 150,
                    'is_published' => true,
                ]),
                Module::factory()->create([
                    'level_id' => null,
                    'title' => 'Frontend Frameworks Overview',
                    'description' => 'Compare and contrast popular frontend frameworks',
                    'estimated_duration' => 90,
                    'is_published' => true,
                ]),
                Module::factory()->create([
                    'level_id' => null,
                    'title' => 'Version Control with Git',
                    'description' => 'Master Git for version control and collaboration',
                    'estimated_duration' => 60,
                    'is_published' => true,
                ]),
            ]);

            $modules = $modules->concat($standaloneModules);
        }

        return $modules->toArray();
    }

    private function createCoursesWithContent(array $certificateTemplates, array $modules): array
    {
        $this->command->info('Creating courses with modules...');

        $courses = [];

        // Course 1: Full Stack Web Development
        $course1 = Course::where('slug', 'full-stack-web-development')->first();
        if (!$course1) {
            $course1 = Course::factory()->create([
                'title' => 'Full Stack Web Development',
                'description' => 'Complete course covering frontend and backend development. Learn HTML, CSS, JavaScript, Node.js, databases, and deployment strategies. Perfect for aspiring full-stack developers.',
                'slug' => 'full-stack-web-development',
                'is_active' => true,
                'estimated_duration' => 720, // 12 hours
                'certificate_template_id' => $certificateTemplates[0]['id'],
            ]);
            $this->assignModulesToCourse($course1, $modules, [0, 1, 2, 4]); // Assign 4 modules
            $this->createCourseAssessments($course1);
            $this->command->info('Created course: Full Stack Web Development');
        }
        $courses[] = $course1;

        // Course 2: Modern Frontend Development
        $course2 = Course::where('slug', 'modern-frontend-development')->first();
        if (!$course2) {
            $course2 = Course::factory()->create([
                'title' => 'Modern Frontend Development',
                'description' => 'Master modern frontend technologies including React, Vue.js, and advanced CSS. Learn responsive design, state management, and performance optimization.',
                'slug' => 'modern-frontend-development',
                'is_active' => true,
                'estimated_duration' => 480, // 8 hours
                'certificate_template_id' => $certificateTemplates[1]['id'],
            ]);
            $this->assignModulesToCourse($course2, $modules, [0, 3, 4]); // Assign 3 modules
            $this->createCourseAssessments($course2);
            $this->command->info('Created course: Modern Frontend Development');
        }
        $courses[] = $course2;

        // Course 3: Backend API Development
        $course3 = Course::where('slug', 'backend-api-development')->first();
        if (!$course3) {
            $course3 = Course::factory()->create([
                'title' => 'Backend API Development',
                'description' => 'Comprehensive backend development course. Learn to build scalable APIs, work with databases, implement authentication, and deploy applications.',
                'slug' => 'backend-api-development',
                'is_active' => true,
                'estimated_duration' => 600, // 10 hours
                'certificate_template_id' => $certificateTemplates[2]['id'],
            ]);
            $this->assignModulesToCourse($course3, $modules, [1, 2, 4]); // Assign 3 modules
            $this->createCourseAssessments($course3);
            $this->command->info('Created course: Backend API Development');
        }
        $courses[] = $course3;

        // Course 4: Web Development Fundamentals (Beginner)
        $course4 = Course::where('slug', 'web-dev-fundamentals')->first();
        if (!$course4) {
            $course4 = Course::factory()->create([
                'title' => 'Web Development Fundamentals',
                'description' => 'Perfect for beginners! Start your web development journey with HTML, CSS, and basic JavaScript. No prior experience required.',
                'slug' => 'web-dev-fundamentals',
                'is_active' => true,
                'estimated_duration' => 300, // 5 hours
                'certificate_template_id' => $certificateTemplates[0]['id'],
            ]);
            $this->assignModulesToCourse($course4, $modules, [0, 4]); // Assign 2 modules
            $this->createCourseAssessments($course4);
            $this->command->info('Created course: Web Development Fundamentals');
        }
        $courses[] = $course4;

        // Course 5: Advanced Web Technologies (Draft)
        $course5 = Course::where('slug', 'advanced-web-technologies')->first();
        if (!$course5) {
            $course5 = Course::factory()->create([
                'title' => 'Advanced Web Technologies',
                'description' => 'Explore cutting-edge web technologies including WebAssembly, Progressive Web Apps, GraphQL, and microservices architecture.',
                'slug' => 'advanced-web-technologies',
                'is_active' => false, // Draft course
                'estimated_duration' => 900, // 15 hours
                'certificate_template_id' => $certificateTemplates[1]['id'],
            ]);
            $this->assignModulesToCourse($course5, $modules, [2, 3]); // Assign 2 modules
            $this->createCourseAssessments($course5);
            $this->command->info('Created course: Advanced Web Technologies (Draft)');
        }
        $courses[] = $course5;

        return $courses;
    }

    private function assignModulesToCourse(Course $course, array $modules, array $moduleIndexes): void
    {
        foreach ($moduleIndexes as $order => $moduleIndex) {
            if (isset($modules[$moduleIndex])) {
                $module = $modules[$moduleIndex];

                // Check if already assigned
                if (!$course->modules()->where('module_id', $module['id'])->exists()) {
                    $course->modules()->attach($module['id'], [
                        'order' => $order + 1,
                        'is_required' => $order < 2, // First 2 modules are required
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function createCourseAssessments(Course $course): void
    {
        // Create a final course assessment
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Course',
            'assessable_id' => $course->id,
            'title' => "Final Assessment: {$course->title}",
            'description' => "Comprehensive assessment covering all topics in {$course->title}",
            'passing_score' => 75,
            'max_attempts' => 3,
            'time_limit' => 45,
            'is_required' => true,
        ]);

        // Create questions for the assessment
        $questions = [
            [
                'text' => 'What is the primary purpose of this course?',
                'type' => 'multiple_choice',
                'points' => 2,
                'options' => [
                    ['text' => 'To learn web development concepts', 'correct' => true],
                    ['text' => 'To learn mobile development', 'correct' => false],
                    ['text' => 'To learn desktop applications', 'correct' => false],
                    ['text' => 'To learn game development', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which technology is fundamental to web development?',
                'type' => 'multiple_choice',
                'points' => 1,
                'options' => [
                    ['text' => 'HTML', 'correct' => true],
                    ['text' => 'Python', 'correct' => false],
                    ['text' => 'Java', 'correct' => false],
                    ['text' => 'C++', 'correct' => false],
                ],
            ],
            [
                'text' => 'Version control is important for collaborative development.',
                'type' => 'true_false',
                'points' => 1,
                'options' => [
                    ['text' => 'True', 'correct' => true],
                    ['text' => 'False', 'correct' => false],
                ],
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_text' => $questionData['text'],
                'question_type' => $questionData['type'],
                'points' => $questionData['points'],
                'order_index' => $index,
            ]);

            foreach ($questionData['options'] as $optionIndex => $optionData) {
                QuestionOption::factory()->create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['text'],
                    'is_correct' => $optionData['correct'],
                    'order_index' => $optionIndex,
                ]);
            }
        }

        // Create course assignments (assign to course modules)
        $courseModules = $course->modules;
        if ($courseModules->isNotEmpty()) {
            $selectedModule = $courseModules->first();
            Assignment::factory()->create([
                'module_id' => $selectedModule->id,
                'title' => "Final Project: {$course->title}",
                'description' => "Capstone project demonstrating skills learned in {$course->title}",
                'instructions' => "Create a comprehensive project that demonstrates your understanding of the concepts covered in this course. Your project should include:\n\n1. Clean, well-structured code\n2. Proper documentation\n3. Testing (where applicable)\n4. Deployment instructions\n5. A README file explaining your project\n\nSubmit your project as a GitHub repository link along with a brief explanation of your approach and any challenges you faced.",
                'evaluation_checklist' => [
                    ['item' => 'Code quality and structure', 'points' => 25],
                    ['item' => 'Functionality and features', 'points' => 30],
                    ['item' => 'Documentation and README', 'points' => 15],
                    ['item' => 'Testing implementation', 'points' => 15],
                    ['item' => 'Deployment and accessibility', 'points' => 10],
                    ['item' => 'Creativity and innovation', 'points' => 5],
                ],
                'due_date' => now()->addDays(30),
                'is_published' => true,
            ]);
        }

        // Create course discussion
        $discussion = Discussion::factory()->create([
            'discussable_type' => 'App\Models\Course',
            'discussable_id' => $course->id,
            'title' => "General Discussion: {$course->title}",
            'description' => "General discussion forum for questions, tips, and sharing experiences related to {$course->title}",
            'is_active' => true,
        ]);

        // Add some initial discussion posts
        $users = User::where('role', 'learner')->limit(3)->get();
        if ($users->count() > 0) {
            foreach ($users as $user) {
                DiscussionPost::factory()->create([
                    'discussion_id' => $discussion->id,
                    'user_id' => $user->id,
                    'content' => $this->generateDiscussionContent($course->title),
                ]);
            }
        }
    }

    private function createEnrollmentsAndProgress(array $courses): void
    {
        $this->command->info('Creating course enrollments and progress...');

        $students = User::where('role', 'learner')->get();

        if ($students->isEmpty()) {
            $this->command->info('No students found. Creating some sample students...');
            $students = User::factory(10)->create([
                'role' => 'learner',
                'password' => 'password',
            ]);
        }

        foreach ($students as $student) {
            // Enroll student in 1-3 random courses
            $enrollmentCount = rand(1, min(3, count($courses)));
            $selectedCourses = collect($courses)->random($enrollmentCount);

            foreach ($selectedCourses as $course) {
                // Skip inactive courses for most students
                if (!$course->is_active && rand(1, 10) > 2) {
                    continue;
                }

                // Check if enrollment already exists
                $existingEnrollment = CourseEnrollment::where('user_id', $student->id)
                    ->where('course_id', $course->id)
                    ->first();

                if ($existingEnrollment) {
                    continue;
                }

                $progressPercentage = rand(0, 100);
                $enrollment = CourseEnrollment::factory()->create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'enrolled_at' => now()->subDays(rand(1, 60)),
                    'progress_percentage' => $progressPercentage,
                    'completed_at' => $progressPercentage >= 100 ? now()->subDays(rand(1, 10)) : null,
                ]);

                // Create learning progress
                LearningProgress::factory()->create([
                    'user_id' => $student->id,
                    'progressable_type' => 'App\Models\Course',
                    'progressable_id' => $course->id,
                    'completion_percentage' => $progressPercentage,
                    'time_spent_minutes' => rand(5, 120), // 5 minutes to 2 hours
                    'last_accessed_at' => now()->subDays(rand(0, 7)),
                ]);

                // Create assessment attempts for course assessments
                $this->createCourseAssessmentAttempts($course, $student->id);

                // Create assignment submissions
                $this->createCourseAssignmentSubmissions($course, $student->id);
            }
        }
    }

    private function createCourseAssessmentAttempts(Course $course, int $userId): void
    {
        $assessments = $course->assessments;

        foreach ($assessments as $assessment) {
            $attemptCount = rand(1, min(2, $assessment->max_attempts));

            for ($i = 0; $i < $attemptCount; $i++) {
                $score = rand(50, 100);
                $isPassed = $score >= $assessment->passing_score;

                $attempt = AssessmentAttempt::factory()->create([
                    'assessment_id' => $assessment->id,
                    'user_id' => $userId,
                    'score' => $score,
                    'max_score' => $assessment->questions->sum('points'),
                    'passed' => $isPassed,
                    'started_at' => now()->subDays(rand(1, 30)),
                    'completed_at' => now()->subDays(rand(1, 30))->addMinutes(rand(15, 45)),
                ]);

                // Create attempt answers
                foreach ($assessment->questions as $question) {
                    $selectedOption = $question->options->random();

                    AttemptAnswer::factory()->create([
                        'assessment_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'selected_options' => [$selectedOption->id],
                        'is_correct' => $selectedOption->is_correct,
                        'points_earned' => $selectedOption->is_correct ? $question->points : 0,
                    ]);
                }

                // If passed, don't create more attempts
                if ($isPassed) {
                    break;
                }
            }
        }
    }

    private function createCourseAssignmentSubmissions(Course $course, int $userId): void
    {
        // Get assignments from course modules
        $assignments = Assignment::whereHas('module', function ($query) use ($course) {
            $query->whereIn('id', $course->modules->pluck('id'));
        })->get();

        foreach ($assignments as $assignment) {
            // Check if submission already exists
            $existingSubmission = AssignmentSubmission::where('assignment_id', $assignment->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingSubmission) {
                continue; // Skip if submission already exists
            }

            // 70% chance of submission
            if (rand(1, 10) <= 7) {
                AssignmentSubmission::factory()->create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $userId,
                    'submission_notes' => $this->generateProjectSubmission($course->title),
                    'submitted_at' => now()->subDays(rand(1, 20)),
                    'grade' => rand(70, 100),
                    'feedback' => $this->generateProjectFeedback(),
                    'graded_at' => now()->subDays(rand(0, 10)),
                ]);
            }
        }
    }

    private function generateDiscussionContent(string $courseTitle): string
    {
        $contents = [
            "I'm really enjoying the {$courseTitle} course! The content is well-structured and easy to follow. Has anyone else tried implementing the concepts in a real project?",
            "Quick question about {$courseTitle} - I'm having trouble with one of the assignments. Could someone point me in the right direction?",
            "Just completed the {$courseTitle} course and wanted to share my project. It was challenging but very rewarding!",
            "The {$courseTitle} course has been incredibly helpful for my career development. The practical examples really make a difference.",
            "Looking for study partners for {$courseTitle}. Anyone interested in forming a study group?",
        ];

        return $contents[array_rand($contents)];
    }

    private function generateProjectSubmission(string $courseTitle): string
    {
        $submissions = [
            "I've completed my final project for {$courseTitle}. Here's my GitHub repository: https://github.com/student/project-name\n\nProject Overview:\nI built a comprehensive web application that demonstrates all the key concepts covered in the course. The application includes user authentication, database integration, responsive design, and deployment to a cloud platform.\n\nChallenges Faced:\n- Setting up the development environment initially took some time\n- Implementing user authentication was more complex than expected\n- Optimizing performance for mobile devices required additional research\n\nKey Features:\n- User registration and login system\n- CRUD operations with database\n- Responsive design that works on all devices\n- Clean, maintainable code structure\n- Comprehensive documentation\n\nI'm proud of what I've accomplished and feel confident applying these skills in real-world projects.",

            "Final Project Submission for {$courseTitle}\n\nRepository: https://github.com/student/course-project\nLive Demo: https://my-project.netlify.app\n\nThis project showcases my understanding of the course material through a practical application. I focused on creating clean, well-documented code that follows best practices.\n\nTechnical Implementation:\n- Used modern development tools and frameworks\n- Implemented proper error handling and validation\n- Added comprehensive testing suite\n- Followed accessibility guidelines\n- Optimized for performance\n\nLearning Outcomes:\nThis project helped me solidify my understanding of the concepts and gave me confidence to tackle more complex challenges. The hands-on experience was invaluable.",

            "Project Submission: {$courseTitle} Capstone\n\nI've created a full-featured application that incorporates all major topics from the course. The project demonstrates my ability to plan, develop, and deploy a complete solution.\n\nProject Highlights:\n- Modular, scalable architecture\n- Integration with external APIs\n- User-friendly interface design\n- Robust error handling\n- Deployment automation\n\nReflection:\nThis course has significantly improved my development skills. The project-based approach helped me understand how different concepts work together in real applications. I'm excited to continue building on this foundation.",
        ];

        return $submissions[array_rand($submissions)];
    }

    private function generateProjectFeedback(): string
    {
        $feedbacks = [
            "Excellent work! Your project demonstrates a strong understanding of the course concepts. The code is well-structured and follows best practices. Your documentation is particularly impressive. Consider exploring advanced optimization techniques for your next project.",

            "Great project! You've successfully implemented all the required features and your code quality is high. The user interface is intuitive and the functionality works smoothly. For future projects, consider adding more comprehensive error handling and edge case testing.",

            "Outstanding submission! Your project goes above and beyond the requirements. The attention to detail in both functionality and presentation is commendable. Your approach to problem-solving shows real growth throughout the course.",

            "Very good work! Your project meets all requirements and demonstrates solid understanding of the material. The code is clean and well-commented. Consider refactoring some of the larger functions into smaller, more focused ones for better maintainability.",

            "Impressive project! You've created a polished, professional-quality application. Your use of modern development practices and attention to user experience really stand out. This is portfolio-worthy work!",
        ];

        return $feedbacks[array_rand($feedbacks)];
    }
}
