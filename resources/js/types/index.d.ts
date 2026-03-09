import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    items?: NavItem[];
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

// Classroom Types
export interface Track {
    id: number;
    title: string;
    description: string;
    slug: string;
    is_premium: boolean;
    price?: number;
    is_published: boolean;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration?: number;
    created_at: string;
    updated_at: string;
    instructor: User;
    levels?: Level[];
    media?: Media[];
    is_free: boolean;
    levels_count?: number;
    enrollments_count?: number;
    enrollment?: TrackEnrollment;
    progress?: TrackProgress;
}

export interface Level {
    id: number;
    track_id: number;
    title: string;
    description: string;
    difficulty: 'beginner' | 'intermediate' | 'advanced';
    order_index: number;
    is_published: boolean;
    created_at: string;
    updated_at: string;
    track?: Track;
    modules?: Module[];
    modules_count?: number;
}

export interface Module {
    id: number;
    level_id: number;
    title: string;
    description: string;
    order_index: number;
    estimated_duration?: number;
    is_published: boolean;
    created_at: string;
    updated_at: string;
    level?: Level;
    lessons?: Lesson[];
    lessons_count?: number;
    progress?: ModuleProgress;
}

export interface Lesson {
    id: number;
    module_id: number;
    title: string;
    content: string;
    order_index: number;
    estimated_duration?: number;
    is_published: boolean;
    lesson_type: 'text' | 'video' | 'interactive';
    created_at: string;
    updated_at: string;
    module?: Module;
    media?: Media[];
    progress?: LessonProgress;
    is_completed?: boolean;
}

export interface TrackEnrollment {
    id: number;
    user_id: number;
    track_id: number;
    enrolled_at: string;
    completed_at?: string;
    progress_percentage: number;
    created_at: string;
    updated_at: string;
}

export interface TrackProgress {
    track_id: number;
    progress_percentage: number;
    completed_lessons: number;
    total_lessons: number;
    current_level?: Level;
    current_module?: Module;
    current_lesson?: Lesson;
}

export interface ModuleProgress {
    module_id: number;
    progress_percentage: number;
    completed_lessons: number;
    total_lessons: number;
}

export interface LessonProgress {
    id: number;
    user_id: number;
    lesson_id: number;
    completed_at?: string;
    time_spent: number;
    created_at: string;
    updated_at: string;
}

export interface Media {
    id: number;
    filename: string;
    original_name: string;
    mime_type: string;
    size: number;
    path: string;
    url: string;
    created_at: string;
    updated_at: string;
}

export interface Assessment {
    id: number;
    assessable_type: string;
    assessable_id: number;
    title: string;
    description?: string;
    passing_score: number;
    max_attempts: number;
    time_limit?: number;
    is_required: boolean;
    created_at: string;
    updated_at: string;
    questions?: Question[];
    questions_count?: number;
    attempts_count?: number;
    user_attempts?: number;
    best_score?: number;
    has_passed?: boolean;
}

export interface Question {
    id: number;
    assessment_id: number;
    question_text: string;
    question_type:
        | 'multiple_choice'
        | 'true_false'
        | 'code_output'
        | 'conceptual';
    points: number;
    order_index: number;
    explanation?: string;
    created_at: string;
    updated_at: string;
    options?: QuestionOption[];
}

export interface QuestionOption {
    id: number;
    question_id: number;
    option_text: string;
    is_correct: boolean;
    order_index: number;
    created_at: string;
    updated_at: string;
}

export interface AssessmentAttempt {
    id: number;
    user_id: number;
    assessment_id: number;
    score: number;
    max_score: number;
    passed: boolean;
    started_at: string;
    completed_at?: string;
    time_taken?: number;
    attempt_number: number;
    created_at: string;
    updated_at: string;
    answers?: AttemptAnswer[];
}

export interface AttemptAnswer {
    id: number;
    attempt_id: number;
    question_id: number;
    selected_options?: number[];
    answer_text?: string;
    is_correct: boolean;
    points_earned: number;
    created_at: string;
    updated_at: string;
    question?: Question;
}

export interface AssessmentFeedback {
    score: number;
    max_score: number;
    percentage: number;
    passed: boolean;
    passing_score: number;
    time_taken?: number;
    questions_feedback: QuestionFeedback[];
}

export interface QuestionFeedback {
    question_id: number;
    question_text: string;
    question_type: string;
    points: number;
    points_earned: number;
    is_correct: boolean;
    selected_options?: number[];
    answer_text?: string;
    correct_options?: number[];
    explanation?: string;
}

// Re-export enhanced classroom types
export * from './enhanced-classroom';
