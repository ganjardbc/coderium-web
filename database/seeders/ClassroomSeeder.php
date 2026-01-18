<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Discussion;
use App\Models\DiscussionPost;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Level;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Track;
use App\Models\TrackEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating classroom sample data...');

        // Create instructors
        $instructors = $this->createInstructors();

        // Create students
        $students = $this->createStudents();

        // Create tracks with complete structure
        $tracks = $this->createTracksWithContent($instructors);

        // Create enrollments and progress
        $this->createEnrollmentsAndProgress($tracks, $students);

        $this->command->info('Classroom sample data created successfully!');
    }

    private function createInstructors(): array
    {
        $this->command->info('Creating instructors...');

        $instructors = [];

        // Create or find instructors
        $instructorData = [
            [
                'name' => 'Dr. Sarah Johnson',
                'email' => 'sarah.johnson@coderium.id',
                'role' => 'instructor',
                'password' => 'password',
            ],
            [
                'name' => 'Prof. Michael Chen',
                'email' => 'michael.chen@coderium.id',
                'role' => 'instructor',
                'password' => 'password',
            ],
            [
                'name' => 'Dr. Emily Rodriguez',
                'email' => 'emily.rodriguez@coderium.id',
                'role' => 'instructor',
                'password' => 'password',
            ],
        ];

        foreach ($instructorData as $data) {
            $instructor = User::where('email', $data['email'])->first();
            if (!$instructor) {
                $instructor = User::factory()->create($data);
                $this->command->info("Created instructor: {$data['name']}");
            } else {
                $this->command->info("Found existing instructor: {$instructor->name}");
            }
            $instructors[] = $instructor;
        }

        return $instructors;
    }

    private function createStudents(): array
    {
        $this->command->info('Creating students...');

        $students = [];

        // Create specific students
        $specificStudents = [
            [
                'name' => 'Alex Thompson',
                'email' => 'alex.thompson@student.coderium.id',
                'role' => 'learner',
                'password' => 'password',
            ],
            [
                'name' => 'Maria Garcia',
                'email' => 'maria.garcia@student.coderium.id',
                'role' => 'learner',
                'password' => 'password',
            ],
        ];

        foreach ($specificStudents as $data) {
            $student = User::where('email', $data['email'])->first();
            if (!$student) {
                $student = User::factory()->create($data);
                $this->command->info("Created student: {$data['name']}");
            } else {
                $this->command->info("Found existing student: {$student->name}");
            }
            $students[] = $student;
        }

        // Create additional random students
        $existingLearnerCount = User::where('role', 'learner')->count();
        $targetLearnerCount = 17; // 2 specific + 15 random
        $neededLearners = max(0, $targetLearnerCount - $existingLearnerCount);

        if ($neededLearners > 0) {
            $additionalStudents = User::factory($neededLearners)->create([
                'role' => 'learner',
                'password' => 'password',
            ]);
            $this->command->info("Created {$neededLearners} additional random students");
            $students = array_merge($students, $additionalStudents->toArray());
        } else {
            // Get existing learners to fill the array
            $existingLearners = User::where('role', 'learner')
                ->whereNotIn('email', ['alex.thompson@student.coderium.id', 'maria.garcia@student.coderium.id'])
                ->limit(15)
                ->get();
            $students = array_merge($students, $existingLearners->toArray());
        }

        return $students;
    }

    private function createTracksWithContent(array $instructors): array
    {
        $this->command->info('Creating tracks with complete content...');

        $tracks = [];

        // Track 1: Web Development Fundamentals
        $track1 = Track::where('slug', 'web-development-fundamentals')->first();
        if (!$track1) {
            $track1 = Track::factory()->create([
                'title' => 'Web Development Fundamentals',
                'description' => 'Learn the basics of web development including HTML, CSS, and JavaScript. Perfect for beginners who want to start their journey in web development.',
                'slug' => 'web-development-fundamentals',
                'difficulty_level' => 'beginner',
                'is_premium' => false,
                'is_published' => true,
                'estimated_duration' => 480, // 8 hours
                'instructor_id' => $instructors[0]->id,
            ]);
            $this->createTrackContent($track1, 'web-development');
            $this->command->info('Created track: Web Development Fundamentals');
        } else {
            $this->command->info('Found existing track: Web Development Fundamentals');
        }
        $tracks[] = $track1;

        // Track 2: Advanced JavaScript & React
        $track2 = Track::where('slug', 'advanced-javascript-react')->first();
        if (!$track2) {
            $track2 = Track::factory()->create([
                'title' => 'Advanced JavaScript & React',
                'description' => 'Master advanced JavaScript concepts and learn React.js to build modern web applications. Includes hooks, state management, and best practices.',
                'slug' => 'advanced-javascript-react',
                'difficulty_level' => 'intermediate',
                'is_premium' => true,
                'price' => 99.99,
                'is_published' => true,
                'estimated_duration' => 720, // 12 hours
                'instructor_id' => $instructors[1]->id,
            ]);
            $this->createTrackContent($track2, 'javascript-react');
            $this->command->info('Created track: Advanced JavaScript & React');
        } else {
            $this->command->info('Found existing track: Advanced JavaScript & React');
        }
        $tracks[] = $track2;

        // Track 3: Python for Data Science
        $track3 = Track::where('slug', 'python-data-science')->first();
        if (!$track3) {
            $track3 = Track::factory()->create([
                'title' => 'Python for Data Science',
                'description' => 'Comprehensive course covering Python programming for data analysis, visualization, and machine learning using pandas, matplotlib, and scikit-learn.',
                'slug' => 'python-data-science',
                'difficulty_level' => 'intermediate',
                'is_premium' => true,
                'price' => 149.99,
                'is_published' => true,
                'estimated_duration' => 960, // 16 hours
                'instructor_id' => $instructors[2]->id,
            ]);
            $this->createTrackContent($track3, 'python-data-science');
            $this->command->info('Created track: Python for Data Science');
        } else {
            $this->command->info('Found existing track: Python for Data Science');
        }
        $tracks[] = $track3;

        // Track 4: Mobile App Development (Draft)
        $track4 = Track::where('slug', 'mobile-app-flutter')->first();
        if (!$track4) {
            $track4 = Track::factory()->create([
                'title' => 'Mobile App Development with Flutter',
                'description' => 'Learn to build cross-platform mobile applications using Flutter and Dart. From basics to advanced concepts.',
                'slug' => 'mobile-app-flutter',
                'difficulty_level' => 'advanced',
                'is_premium' => true,
                'price' => 199.99,
                'is_published' => false, // Draft track
                'estimated_duration' => 1200, // 20 hours
                'instructor_id' => $instructors[0]->id,
            ]);
            $this->createTrackContent($track4, 'flutter-mobile');
            $this->command->info('Created track: Mobile App Development with Flutter');
        } else {
            $this->command->info('Found existing track: Mobile App Development with Flutter');
        }
        $tracks[] = $track4;

        return $tracks;
    }

    private function createTrackContent(Track $track, string $type): void
    {
        $contentMap = [
            'web-development' => $this->getWebDevelopmentContent(),
            'javascript-react' => $this->getJavaScriptReactContent(),
            'python-data-science' => $this->getPythonDataScienceContent(),
            'flutter-mobile' => $this->getFlutterMobileContent(),
        ];

        $content = $contentMap[$type];

        foreach ($content['levels'] as $levelIndex => $levelData) {
            $level = Level::factory()->create([
                'track_id' => $track->id,
                'title' => $levelData['title'],
                'description' => $levelData['description'],
                'difficulty' => $levelData['difficulty'],
                'order_index' => $levelIndex,
                'is_published' => true,
            ]);

            foreach ($levelData['modules'] as $moduleIndex => $moduleData) {
                $module = Module::factory()->create([
                    'level_id' => $level->id,
                    'title' => $moduleData['title'],
                    'description' => $moduleData['description'],
                    'order_index' => $moduleIndex,
                    'estimated_duration' => $moduleData['duration'],
                    'is_published' => true,
                ]);

                // Create lessons for the module
                foreach ($moduleData['lessons'] as $lessonIndex => $lessonData) {
                    $lesson = Lesson::factory()->create([
                        'module_id' => $module->id,
                        'title' => $lessonData['title'],
                        'content' => $lessonData['content'],
                        'lesson_type' => $lessonData['type'],
                        'order_index' => $lessonIndex,
                        'estimated_duration' => $lessonData['duration'],
                        'is_published' => true,
                    ]);

                    // Create assessment for some lessons
                    if (isset($lessonData['assessment'])) {
                        $this->createAssessmentForLesson($lesson, $lessonData['assessment']);
                    }
                }

                // Create module assessment
                if (isset($moduleData['assessment'])) {
                    $this->createAssessmentForModule($module, $moduleData['assessment']);
                }

                // Create assignments
                if (isset($moduleData['assignments'])) {
                    foreach ($moduleData['assignments'] as $assignmentData) {
                        Assignment::factory()->create([
                            'module_id' => $module->id,
                            'title' => $assignmentData['title'],
                            'description' => $assignmentData['description'],
                            'instructions' => $assignmentData['instructions'],
                            'evaluation_checklist' => $assignmentData['checklist'],
                            'due_date' => now()->addDays(rand(7, 21)),
                            'is_published' => true,
                        ]);
                    }
                }

                // Create discussions
                if (isset($moduleData['discussions'])) {
                    foreach ($moduleData['discussions'] as $discussionData) {
                        $discussion = Discussion::factory()->create([
                            'discussable_type' => 'App\Models\Module',
                            'discussable_id' => $module->id,
                            'title' => $discussionData['title'],
                            'description' => $discussionData['description'],
                            'is_active' => true,
                        ]);

                        // Create some discussion posts
                        DiscussionPost::factory(rand(2, 8))->create([
                            'discussion_id' => $discussion->id,
                            'user_id' => User::where('role', 'learner')->inRandomOrder()->first()->id,
                        ]);
                    }
                }
            }
        }
    }
    private function createAssessmentForLesson(Lesson $lesson, array $assessmentData): void
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Lesson',
            'assessable_id' => $lesson->id,
            'title' => $assessmentData['title'],
            'description' => $assessmentData['description'],
            'passing_score' => $assessmentData['passing_score'],
            'max_attempts' => $assessmentData['max_attempts'],
            'time_limit' => $assessmentData['time_limit'] ?? null,
            'is_required' => $assessmentData['required'],
        ]);

        foreach ($assessmentData['questions'] as $questionIndex => $questionData) {
            $question = Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_text' => $questionData['text'],
                'question_type' => $questionData['type'],
                'points' => $questionData['points'],
                'order_index' => $questionIndex,
                'explanation' => $questionData['explanation'] ?? null,
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
    }

    private function createAssessmentForModule(Module $module, array $assessmentData): void
    {
        $assessment = Assessment::factory()->create([
            'assessable_type' => 'App\Models\Module',
            'assessable_id' => $module->id,
            'title' => $assessmentData['title'],
            'description' => $assessmentData['description'],
            'passing_score' => $assessmentData['passing_score'],
            'max_attempts' => $assessmentData['max_attempts'],
            'time_limit' => $assessmentData['time_limit'] ?? null,
            'is_required' => $assessmentData['required'],
        ]);

        foreach ($assessmentData['questions'] as $questionIndex => $questionData) {
            $question = Question::factory()->create([
                'assessment_id' => $assessment->id,
                'question_text' => $questionData['text'],
                'question_type' => $questionData['type'],
                'points' => $questionData['points'],
                'order_index' => $questionIndex,
                'explanation' => $questionData['explanation'] ?? null,
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
    }

    private function createEnrollmentsAndProgress(array $tracks, array $students): void
    {
        $this->command->info('Creating enrollments and progress...');

        foreach ($students as $student) {
            // Enroll student in 1-3 random tracks
            $enrollmentCount = rand(1, 3);
            $selectedTracks = collect($tracks)->random($enrollmentCount);

            foreach ($selectedTracks as $track) {
                // Skip unpublished tracks for most students
                if (!$track->is_published && rand(1, 10) > 2) {
                    continue;
                }

                // Check if enrollment already exists
                $existingEnrollment = TrackEnrollment::where('user_id', $student['id'])
                    ->where('track_id', $track->id)
                    ->first();

                if ($existingEnrollment) {
                    $this->command->info("Enrollment already exists for user {$student['id']} in track {$track->id}");
                    continue;
                }

                $enrollment = TrackEnrollment::factory()->create([
                    'user_id' => $student['id'],
                    'track_id' => $track->id,
                    'enrolled_at' => now()->subDays(rand(1, 30)),
                    'progress_percentage' => rand(0, 100),
                ]);

                // Create lesson progress for enrolled students
                $this->createLessonProgress($track, $student['id'], $enrollment->progress_percentage);

                // Create some assessment attempts
                $this->createAssessmentAttempts($track, $student['id']);

                // Create assignment submissions
                $this->createAssignmentSubmissions($track, $student['id']);
            }
        }
    }

    private function createLessonProgress(Track $track, int $userId, int $overallProgress): void
    {
        $lessons = Lesson::whereHas('module.level', function ($query) use ($track) {
            $query->where('track_id', $track->id);
        })->get();

        $completedLessons = $lessons->take(intval($lessons->count() * $overallProgress / 100));

        foreach ($completedLessons as $lesson) {
            LessonProgress::factory()->create([
                'user_id' => $userId,
                'lesson_id' => $lesson->id,
                'completed_at' => now()->subDays(rand(1, 20)),
                'time_spent' => rand(300, 3600), // 5 minutes to 1 hour
            ]);
        }
    }

    private function createAssessmentAttempts(Track $track, int $userId): void
    {
        // Get all lessons and modules for this track
        $lessons = Lesson::whereHas('module.level', function ($query) use ($track) {
            $query->where('track_id', $track->id);
        })->pluck('id');

        $modules = Module::whereHas('level', function ($query) use ($track) {
            $query->where('track_id', $track->id);
        })->pluck('id');

        // Get assessments for these lessons and modules
        $assessments = Assessment::where(function ($query) use ($lessons, $modules) {
            $query->where(function ($q) use ($lessons) {
                $q->where('assessable_type', 'App\Models\Lesson')
                  ->whereIn('assessable_id', $lessons);
            })->orWhere(function ($q) use ($modules) {
                $q->where('assessable_type', 'App\Models\Module')
                  ->whereIn('assessable_id', $modules);
            });
        })->get();

        if ($assessments->isEmpty()) {
            return;
        }

        foreach ($assessments->random(min(3, $assessments->count())) as $assessment) {
            $attemptCount = rand(1, min(3, $assessment->max_attempts));

            for ($i = 0; $i < $attemptCount; $i++) {
                $score = rand(40, 100);
                $isPassed = $score >= $assessment->passing_score;

                $attempt = AssessmentAttempt::factory()->create([
                    'assessment_id' => $assessment->id,
                    'user_id' => $userId,
                    'score' => $score,
                    'max_score' => $assessment->questions->sum('points'),
                    'passed' => $isPassed,
                    'started_at' => now()->subDays(rand(1, 15)),
                    'completed_at' => now()->subDays(rand(1, 15))->addMinutes(rand(10, 60)),
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

    private function createAssignmentSubmissions(Track $track, int $userId): void
    {
        $assignments = Assignment::whereHas('module.level', function ($query) use ($track) {
            $query->where('track_id', $track->id);
        })->get();

        foreach ($assignments->random(min(2, $assignments->count())) as $assignment) {
            AssignmentSubmission::factory()->create([
                'assignment_id' => $assignment->id,
                'user_id' => $userId,
                'submission_notes' => $this->generateSampleSubmission($assignment->title),
                'submitted_at' => now()->subDays(rand(1, 10)),
                'grade' => rand(70, 100),
                'feedback' => $this->generateSampleFeedback(),
                'graded_at' => now()->subDays(rand(0, 5)),
            ]);
        }
    }

    private function generateSampleSubmission(string $assignmentTitle): string
    {
        $submissions = [
            "I have completed the {$assignmentTitle} assignment. Here's my approach:\n\n1. First, I analyzed the requirements\n2. Then I implemented the solution step by step\n3. Finally, I tested the functionality\n\nThe code is working as expected and meets all the specified criteria.",
            "For this {$assignmentTitle} assignment, I focused on:\n\n- Clean code structure\n- Proper error handling\n- Comprehensive testing\n- Documentation\n\nI believe this solution demonstrates a good understanding of the concepts covered in the module.",
            "My submission for {$assignmentTitle}:\n\nI approached this problem by breaking it down into smaller components. Each component was implemented and tested individually before integration. The final solution is robust and follows best practices.",
        ];

        return $submissions[array_rand($submissions)];
    }

    private function generateSampleFeedback(): string
    {
        $feedbacks = [
            "Great work! Your solution demonstrates a solid understanding of the concepts. The code is clean and well-structured. Consider adding more comments for better readability.",
            "Good effort! The implementation is correct and functional. For future assignments, try to include more edge case testing.",
            "Excellent submission! Your approach is innovative and the code quality is high. Well done on the comprehensive testing.",
            "Nice work! The solution meets all requirements. Consider optimizing the performance for larger datasets in future projects.",
            "Well done! Your code is clean and follows best practices. The documentation is particularly good.",
        ];

        return $feedbacks[array_rand($feedbacks)];
    }
    private function getWebDevelopmentContent(): array
    {
        return [
            'levels' => [
                [
                    'title' => 'HTML Fundamentals',
                    'description' => 'Learn the building blocks of web pages with HTML',
                    'difficulty' => 'beginner',
                    'modules' => [
                        [
                            'title' => 'Introduction to HTML',
                            'description' => 'Understanding HTML structure and basic elements',
                            'duration' => 60,
                            'lessons' => [
                                [
                                    'title' => 'What is HTML?',
                                    'content' => 'HTML (HyperText Markup Language) is the standard markup language for creating web pages. It describes the structure of a web page using elements and tags.',
                                    'type' => 'text',
                                    'duration' => 15,
                                ],
                                [
                                    'title' => 'HTML Document Structure',
                                    'content' => 'Every HTML document has a basic structure including DOCTYPE, html, head, and body elements. Let\'s explore each of these components.',
                                    'type' => 'text',
                                    'duration' => 20,
                                    'assessment' => [
                                        'title' => 'HTML Structure Quiz',
                                        'description' => 'Test your understanding of HTML document structure',
                                        'passing_score' => 70,
                                        'max_attempts' => 3,
                                        'time_limit' => 10,
                                        'required' => true,
                                        'questions' => [
                                            [
                                                'text' => 'Which element contains the visible content of a web page?',
                                                'type' => 'multiple_choice',
                                                'points' => 1,
                                                'options' => [
                                                    ['text' => '<head>', 'correct' => false],
                                                    ['text' => '<body>', 'correct' => true],
                                                    ['text' => '<html>', 'correct' => false],
                                                    ['text' => '<title>', 'correct' => false],
                                                ],
                                                'explanation' => 'The <body> element contains all the visible content of a web page.'
                                            ],
                                            [
                                                'text' => 'What does DOCTYPE declaration do?',
                                                'type' => 'multiple_choice',
                                                'points' => 1,
                                                'options' => [
                                                    ['text' => 'Defines the document type and version', 'correct' => true],
                                                    ['text' => 'Creates a new document', 'correct' => false],
                                                    ['text' => 'Styles the document', 'correct' => false],
                                                    ['text' => 'Links external files', 'correct' => false],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'title' => 'Common HTML Elements',
                                    'content' => 'Learn about headings, paragraphs, links, images, and other essential HTML elements that form the foundation of web content.',
                                    'type' => 'interactive',
                                    'duration' => 25,
                                ],
                            ],
                            'assignments' => [
                                [
                                    'title' => 'Create Your First HTML Page',
                                    'description' => 'Build a simple HTML page about yourself',
                                    'instructions' => 'Create an HTML page that includes:\n1. A proper HTML5 document structure\n2. A title in the head section\n3. At least 3 different heading levels\n4. Several paragraphs of text\n5. At least one image\n6. At least one link to an external website',
                                    'checklist' => [
                                        ['item' => 'Proper HTML5 document structure', 'points' => 2],
                                        ['item' => 'Title element in head', 'points' => 1],
                                        ['item' => 'Multiple heading levels used', 'points' => 2],
                                        ['item' => 'Well-structured content', 'points' => 2],
                                        ['item' => 'Image with proper alt text', 'points' => 2],
                                        ['item' => 'External link with target="_blank"', 'points' => 1],
                                    ],
                                ],
                            ],
                            'discussions' => [
                                [
                                    'title' => 'HTML Best Practices',
                                    'description' => 'Share and discuss HTML coding best practices',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'CSS Styling',
                    'description' => 'Master the art of styling web pages with CSS',
                    'difficulty' => 'beginner',
                    'modules' => [
                        [
                            'title' => 'CSS Fundamentals',
                            'description' => 'Learn CSS syntax, selectors, and basic styling properties',
                            'duration' => 90,
                            'lessons' => [
                                [
                                    'title' => 'Introduction to CSS',
                                    'content' => 'CSS (Cascading Style Sheets) is used to style and layout web pages. It controls colors, fonts, spacing, and positioning of HTML elements.',
                                    'type' => 'text',
                                    'duration' => 20,
                                ],
                                [
                                    'title' => 'CSS Selectors',
                                    'content' => 'Learn about different types of CSS selectors: element, class, ID, and attribute selectors. Understanding selectors is crucial for effective styling.',
                                    'type' => 'interactive',
                                    'duration' => 30,
                                ],
                                [
                                    'title' => 'Box Model',
                                    'content' => 'The CSS box model describes how elements are structured with content, padding, border, and margin. This is fundamental to CSS layout.',
                                    'type' => 'video',
                                    'duration' => 25,
                                ],
                                [
                                    'title' => 'Colors and Typography',
                                    'content' => 'Explore CSS properties for colors, fonts, text styling, and creating visually appealing typography on your web pages.',
                                    'type' => 'text',
                                    'duration' => 15,
                                ],
                            ],
                            'assessment' => [
                                'title' => 'CSS Fundamentals Test',
                                'description' => 'Comprehensive test covering CSS basics',
                                'passing_score' => 75,
                                'max_attempts' => 2,
                                'time_limit' => 20,
                                'required' => true,
                                'questions' => [
                                    [
                                        'text' => 'Which CSS property is used to change the text color?',
                                        'type' => 'multiple_choice',
                                        'points' => 1,
                                        'options' => [
                                            ['text' => 'color', 'correct' => true],
                                            ['text' => 'text-color', 'correct' => false],
                                            ['text' => 'font-color', 'correct' => false],
                                            ['text' => 'background-color', 'correct' => false],
                                        ],
                                    ],
                                    [
                                        'text' => 'The CSS box model consists of content, padding, border, and margin.',
                                        'type' => 'true_false',
                                        'points' => 1,
                                        'options' => [
                                            ['text' => 'True', 'correct' => true],
                                            ['text' => 'False', 'correct' => false],
                                        ],
                                    ],
                                    [
                                        'text' => 'Which selector has the highest specificity?',
                                        'type' => 'multiple_choice',
                                        'points' => 2,
                                        'options' => [
                                            ['text' => 'Element selector', 'correct' => false],
                                            ['text' => 'Class selector', 'correct' => false],
                                            ['text' => 'ID selector', 'correct' => true],
                                            ['text' => 'Universal selector', 'correct' => false],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'JavaScript Basics',
                    'description' => 'Add interactivity to your web pages with JavaScript',
                    'difficulty' => 'intermediate',
                    'modules' => [
                        [
                            'title' => 'JavaScript Fundamentals',
                            'description' => 'Learn JavaScript syntax, variables, and basic programming concepts',
                            'duration' => 120,
                            'lessons' => [
                                [
                                    'title' => 'Introduction to JavaScript',
                                    'content' => 'JavaScript is a programming language that enables interactive web pages. It\'s an essential part of web applications alongside HTML and CSS.',
                                    'type' => 'text',
                                    'duration' => 20,
                                ],
                                [
                                    'title' => 'Variables and Data Types',
                                    'content' => 'Learn about JavaScript variables (var, let, const) and data types (string, number, boolean, object, array, null, undefined).',
                                    'type' => 'interactive',
                                    'duration' => 35,
                                ],
                                [
                                    'title' => 'Functions and Control Flow',
                                    'content' => 'Understand JavaScript functions, conditional statements (if/else), and loops (for, while) to control program execution.',
                                    'type' => 'video',
                                    'duration' => 40,
                                ],
                                [
                                    'title' => 'DOM Manipulation',
                                    'content' => 'Learn how to interact with HTML elements using JavaScript. Select elements, modify content, and respond to user events.',
                                    'type' => 'interactive',
                                    'duration' => 25,
                                ],
                            ],
                            'assignments' => [
                                [
                                    'title' => 'Interactive Web Page',
                                    'description' => 'Create an interactive web page using HTML, CSS, and JavaScript',
                                    'instructions' => 'Build a web page that includes:\n1. HTML structure with multiple elements\n2. CSS styling for visual appeal\n3. JavaScript functionality for user interaction\n4. At least one form with validation\n5. Dynamic content updates based on user input',
                                    'checklist' => [
                                        ['item' => 'Clean HTML structure', 'points' => 2],
                                        ['item' => 'Attractive CSS styling', 'points' => 2],
                                        ['item' => 'Working JavaScript functionality', 'points' => 3],
                                        ['item' => 'Form validation', 'points' => 2],
                                        ['item' => 'Dynamic content updates', 'points' => 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getJavaScriptReactContent(): array
    {
        return [
            'levels' => [
                [
                    'title' => 'Advanced JavaScript',
                    'description' => 'Master advanced JavaScript concepts and ES6+ features',
                    'difficulty' => 'intermediate',
                    'modules' => [
                        [
                            'title' => 'ES6+ Features',
                            'description' => 'Learn modern JavaScript features including arrow functions, destructuring, and modules',
                            'duration' => 150,
                            'lessons' => [
                                [
                                    'title' => 'Arrow Functions and Template Literals',
                                    'content' => 'Explore arrow function syntax, lexical this binding, and template literals for string interpolation.',
                                    'type' => 'interactive',
                                    'duration' => 30,
                                ],
                                [
                                    'title' => 'Destructuring and Spread Operator',
                                    'content' => 'Learn destructuring assignment for arrays and objects, and the spread operator for copying and merging data.',
                                    'type' => 'video',
                                    'duration' => 35,
                                ],
                                [
                                    'title' => 'Promises and Async/Await',
                                    'content' => 'Master asynchronous JavaScript with Promises and async/await syntax for handling asynchronous operations.',
                                    'type' => 'interactive',
                                    'duration' => 45,
                                ],
                                [
                                    'title' => 'Modules and Classes',
                                    'content' => 'Understand ES6 modules for code organization and ES6 classes for object-oriented programming in JavaScript.',
                                    'type' => 'text',
                                    'duration' => 40,
                                ],
                            ],
                            'assessment' => [
                                'title' => 'Advanced JavaScript Quiz',
                                'description' => 'Test your knowledge of modern JavaScript features',
                                'passing_score' => 80,
                                'max_attempts' => 2,
                                'time_limit' => 25,
                                'required' => true,
                                'questions' => [
                                    [
                                        'text' => 'What is the main benefit of arrow functions?',
                                        'type' => 'multiple_choice',
                                        'points' => 2,
                                        'options' => [
                                            ['text' => 'Shorter syntax', 'correct' => false],
                                            ['text' => 'Lexical this binding', 'correct' => true],
                                            ['text' => 'Better performance', 'correct' => false],
                                            ['text' => 'Automatic return', 'correct' => false],
                                        ],
                                    ],
                                    [
                                        'text' => 'Which keyword is used to handle Promise rejections?',
                                        'type' => 'multiple_choice',
                                        'points' => 1,
                                        'options' => [
                                            ['text' => 'try', 'correct' => false],
                                            ['text' => 'catch', 'correct' => true],
                                            ['text' => 'finally', 'correct' => false],
                                            ['text' => 'reject', 'correct' => false],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'React Fundamentals',
                    'description' => 'Learn React.js for building modern user interfaces',
                    'difficulty' => 'intermediate',
                    'modules' => [
                        [
                            'title' => 'React Components and JSX',
                            'description' => 'Understanding React components, JSX syntax, and component composition',
                            'duration' => 180,
                            'lessons' => [
                                [
                                    'title' => 'Introduction to React',
                                    'content' => 'React is a JavaScript library for building user interfaces. Learn about the virtual DOM, component-based architecture, and React\'s philosophy.',
                                    'type' => 'text',
                                    'duration' => 25,
                                ],
                                [
                                    'title' => 'JSX and Components',
                                    'content' => 'Learn JSX syntax for writing React components and understand the difference between functional and class components.',
                                    'type' => 'interactive',
                                    'duration' => 40,
                                ],
                                [
                                    'title' => 'Props and State',
                                    'content' => 'Understand how to pass data between components using props and manage component state for dynamic UIs.',
                                    'type' => 'video',
                                    'duration' => 50,
                                ],
                                [
                                    'title' => 'Event Handling',
                                    'content' => 'Learn how to handle user interactions in React components and understand synthetic events.',
                                    'type' => 'interactive',
                                    'duration' => 35,
                                ],
                                [
                                    'title' => 'Conditional Rendering and Lists',
                                    'content' => 'Master conditional rendering techniques and learn how to render lists of data efficiently in React.',
                                    'type' => 'text',
                                    'duration' => 30,
                                ],
                            ],
                            'assignments' => [
                                [
                                    'title' => 'React Todo App',
                                    'description' => 'Build a fully functional todo application using React',
                                    'instructions' => 'Create a React todo application with the following features:\n1. Add new todos\n2. Mark todos as complete/incomplete\n3. Delete todos\n4. Filter todos (all, active, completed)\n5. Local storage persistence\n6. Responsive design',
                                    'checklist' => [
                                        ['item' => 'Component structure and organization', 'points' => 3],
                                        ['item' => 'State management', 'points' => 3],
                                        ['item' => 'Event handling', 'points' => 2],
                                        ['item' => 'Conditional rendering', 'points' => 2],
                                        ['item' => 'Local storage integration', 'points' => 2],
                                        ['item' => 'Responsive design', 'points' => 1],
                                        ['item' => 'Code quality and comments', 'points' => 2],
                                    ],
                                ],
                            ],
                            'discussions' => [
                                [
                                    'title' => 'React Best Practices',
                                    'description' => 'Discuss React coding best practices and common patterns',
                                ],
                                [
                                    'title' => 'Component Design Patterns',
                                    'description' => 'Share and discuss different React component design patterns',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    private function getPythonDataScienceContent(): array
    {
        return [
            'levels' => [
                [
                    'title' => 'Python Programming Basics',
                    'description' => 'Learn Python fundamentals for data science applications',
                    'difficulty' => 'beginner',
                    'modules' => [
                        [
                            'title' => 'Python Fundamentals',
                            'description' => 'Master Python syntax, data types, and control structures',
                            'duration' => 120,
                            'lessons' => [
                                [
                                    'title' => 'Python Syntax and Variables',
                                    'content' => 'Learn Python syntax, variable assignment, and basic data types including integers, floats, strings, and booleans.',
                                    'type' => 'interactive',
                                    'duration' => 30,
                                ],
                                [
                                    'title' => 'Data Structures',
                                    'content' => 'Explore Python data structures: lists, tuples, dictionaries, and sets. Understand when and how to use each type.',
                                    'type' => 'video',
                                    'duration' => 40,
                                ],
                                [
                                    'title' => 'Control Flow and Functions',
                                    'content' => 'Master if statements, loops, and function definition. Learn about function parameters, return values, and scope.',
                                    'type' => 'interactive',
                                    'duration' => 35,
                                ],
                                [
                                    'title' => 'File Handling and Modules',
                                    'content' => 'Learn to read and write files in Python, and understand how to import and use modules and packages.',
                                    'type' => 'text',
                                    'duration' => 15,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Data Analysis with Pandas',
                    'description' => 'Master data manipulation and analysis using pandas',
                    'difficulty' => 'intermediate',
                    'modules' => [
                        [
                            'title' => 'Pandas Fundamentals',
                            'description' => 'Learn pandas DataFrames and Series for data manipulation',
                            'duration' => 200,
                            'lessons' => [
                                [
                                    'title' => 'Introduction to Pandas',
                                    'content' => 'Pandas is a powerful data manipulation library. Learn about DataFrames, Series, and basic data operations.',
                                    'type' => 'text',
                                    'duration' => 25,
                                ],
                                [
                                    'title' => 'Data Loading and Inspection',
                                    'content' => 'Learn to load data from various sources (CSV, Excel, JSON) and inspect data using pandas methods.',
                                    'type' => 'interactive',
                                    'duration' => 45,
                                ],
                                [
                                    'title' => 'Data Cleaning and Transformation',
                                    'content' => 'Master data cleaning techniques: handling missing values, duplicates, and data type conversions.',
                                    'type' => 'video',
                                    'duration' => 60,
                                ],
                                [
                                    'title' => 'Data Filtering and Grouping',
                                    'content' => 'Learn advanced data filtering, grouping operations, and aggregation functions in pandas.',
                                    'type' => 'interactive',
                                    'duration' => 50,
                                ],
                                [
                                    'title' => 'Merging and Joining Data',
                                    'content' => 'Understand how to combine multiple datasets using merge, join, and concatenate operations.',
                                    'type' => 'text',
                                    'duration' => 20,
                                ],
                            ],
                            'assessment' => [
                                'title' => 'Pandas Mastery Test',
                                'description' => 'Comprehensive assessment of pandas skills',
                                'passing_score' => 75,
                                'max_attempts' => 3,
                                'time_limit' => 30,
                                'required' => true,
                                'questions' => [
                                    [
                                        'text' => 'Which method is used to load a CSV file in pandas?',
                                        'type' => 'multiple_choice',
                                        'points' => 1,
                                        'options' => [
                                            ['text' => 'pd.read_csv()', 'correct' => true],
                                            ['text' => 'pd.load_csv()', 'correct' => false],
                                            ['text' => 'pd.import_csv()', 'correct' => false],
                                            ['text' => 'pd.open_csv()', 'correct' => false],
                                        ],
                                    ],
                                    [
                                        'text' => 'What does the dropna() method do?',
                                        'type' => 'multiple_choice',
                                        'points' => 2,
                                        'options' => [
                                            ['text' => 'Removes duplicate rows', 'correct' => false],
                                            ['text' => 'Removes rows with missing values', 'correct' => true],
                                            ['text' => 'Removes empty columns', 'correct' => false],
                                            ['text' => 'Removes the last row', 'correct' => false],
                                        ],
                                    ],
                                ],
                            ],
                            'assignments' => [
                                [
                                    'title' => 'Data Analysis Project',
                                    'description' => 'Analyze a real-world dataset using pandas',
                                    'instructions' => 'Complete a comprehensive data analysis project:\n1. Load and inspect the provided dataset\n2. Clean the data (handle missing values, duplicates)\n3. Perform exploratory data analysis\n4. Create meaningful visualizations\n5. Draw insights and conclusions\n6. Document your process and findings',
                                    'checklist' => [
                                        ['item' => 'Data loading and initial inspection', 'points' => 2],
                                        ['item' => 'Data cleaning and preprocessing', 'points' => 3],
                                        ['item' => 'Exploratory data analysis', 'points' => 3],
                                        ['item' => 'Data visualizations', 'points' => 2],
                                        ['item' => 'Insights and conclusions', 'points' => 2],
                                        ['item' => 'Code documentation', 'points' => 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Data Visualization',
                    'description' => 'Create compelling visualizations with matplotlib and seaborn',
                    'difficulty' => 'intermediate',
                    'modules' => [
                        [
                            'title' => 'Matplotlib and Seaborn',
                            'description' => 'Master data visualization libraries for creating charts and plots',
                            'duration' => 150,
                            'lessons' => [
                                [
                                    'title' => 'Introduction to Matplotlib',
                                    'content' => 'Learn the basics of matplotlib for creating static, animated, and interactive visualizations in Python.',
                                    'type' => 'video',
                                    'duration' => 35,
                                ],
                                [
                                    'title' => 'Basic Plot Types',
                                    'content' => 'Create line plots, bar charts, histograms, and scatter plots. Understand when to use each visualization type.',
                                    'type' => 'interactive',
                                    'duration' => 45,
                                ],
                                [
                                    'title' => 'Advanced Visualizations with Seaborn',
                                    'content' => 'Use seaborn for statistical visualizations: heatmaps, pair plots, violin plots, and regression plots.',
                                    'type' => 'interactive',
                                    'duration' => 50,
                                ],
                                [
                                    'title' => 'Customizing Plots',
                                    'content' => 'Learn to customize colors, styles, labels, and legends to create professional-looking visualizations.',
                                    'type' => 'text',
                                    'duration' => 20,
                                ],
                            ],
                            'discussions' => [
                                [
                                    'title' => 'Visualization Best Practices',
                                    'description' => 'Share tips and best practices for effective data visualization',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getFlutterMobileContent(): array
    {
        return [
            'levels' => [
                [
                    'title' => 'Flutter Basics',
                    'description' => 'Get started with Flutter and Dart programming',
                    'difficulty' => 'beginner',
                    'modules' => [
                        [
                            'title' => 'Introduction to Flutter',
                            'description' => 'Learn Flutter framework basics and development environment setup',
                            'duration' => 120,
                            'lessons' => [
                                [
                                    'title' => 'What is Flutter?',
                                    'content' => 'Flutter is Google\'s UI toolkit for building natively compiled applications for mobile, web, and desktop from a single codebase.',
                                    'type' => 'text',
                                    'duration' => 20,
                                ],
                                [
                                    'title' => 'Development Environment Setup',
                                    'content' => 'Learn how to install Flutter SDK, set up your IDE, and configure Android/iOS development environments.',
                                    'type' => 'video',
                                    'duration' => 40,
                                ],
                                [
                                    'title' => 'Dart Programming Basics',
                                    'content' => 'Master Dart language fundamentals: variables, functions, classes, and object-oriented programming concepts.',
                                    'type' => 'interactive',
                                    'duration' => 45,
                                ],
                                [
                                    'title' => 'Your First Flutter App',
                                    'content' => 'Create your first Flutter application and understand the basic project structure and widget tree.',
                                    'type' => 'interactive',
                                    'duration' => 15,
                                ],
                            ],
                            'assignments' => [
                                [
                                    'title' => 'Personal Profile App',
                                    'description' => 'Create a simple personal profile mobile app',
                                    'instructions' => 'Build a Flutter app that displays:\n1. Your profile picture\n2. Personal information (name, bio, contact)\n3. List of skills or hobbies\n4. Navigation between different screens\n5. Responsive design for different screen sizes',
                                    'checklist' => [
                                        ['item' => 'App structure and organization', 'points' => 2],
                                        ['item' => 'Widget usage and layout', 'points' => 3],
                                        ['item' => 'Navigation implementation', 'points' => 2],
                                        ['item' => 'Responsive design', 'points' => 2],
                                        ['item' => 'Code quality and comments', 'points' => 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Advanced Flutter Development',
                    'description' => 'Master advanced Flutter concepts and state management',
                    'difficulty' => 'advanced',
                    'modules' => [
                        [
                            'title' => 'State Management',
                            'description' => 'Learn different state management approaches in Flutter',
                            'duration' => 180,
                            'lessons' => [
                                [
                                    'title' => 'StatefulWidget vs StatelessWidget',
                                    'content' => 'Understand the difference between stateful and stateless widgets and when to use each type.',
                                    'type' => 'text',
                                    'duration' => 25,
                                ],
                                [
                                    'title' => 'Provider Pattern',
                                    'content' => 'Learn the Provider pattern for state management and dependency injection in Flutter applications.',
                                    'type' => 'video',
                                    'duration' => 50,
                                ],
                                [
                                    'title' => 'BLoC Pattern',
                                    'content' => 'Master the Business Logic Component (BLoC) pattern for complex state management scenarios.',
                                    'type' => 'interactive',
                                    'duration' => 60,
                                ],
                                [
                                    'title' => 'Riverpod and Other Solutions',
                                    'content' => 'Explore alternative state management solutions like Riverpod, GetX, and MobX.',
                                    'type' => 'text',
                                    'duration' => 45,
                                ],
                            ],
                            'assessment' => [
                                'title' => 'State Management Quiz',
                                'description' => 'Test your understanding of Flutter state management',
                                'passing_score' => 80,
                                'max_attempts' => 2,
                                'time_limit' => 20,
                                'required' => true,
                                'questions' => [
                                    [
                                        'text' => 'When should you use a StatefulWidget?',
                                        'type' => 'multiple_choice',
                                        'points' => 2,
                                        'options' => [
                                            ['text' => 'When the widget never changes', 'correct' => false],
                                            ['text' => 'When the widget needs to maintain state', 'correct' => true],
                                            ['text' => 'For better performance', 'correct' => false],
                                            ['text' => 'Only for root widgets', 'correct' => false],
                                        ],
                                    ],
                                    [
                                        'text' => 'What is the main benefit of the BLoC pattern?',
                                        'type' => 'multiple_choice',
                                        'points' => 2,
                                        'options' => [
                                            ['text' => 'Faster development', 'correct' => false],
                                            ['text' => 'Separation of business logic from UI', 'correct' => true],
                                            ['text' => 'Smaller app size', 'correct' => false],
                                            ['text' => 'Better animations', 'correct' => false],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
