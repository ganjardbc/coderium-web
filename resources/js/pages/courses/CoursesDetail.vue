<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import CourseLayout from '@/layouts/CourseLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Award,
    BookOpen,
    Calendar,
    CheckCircle,
    Clock,
    GraduationCap,
    Layers,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const ENABLE_PRGRESS_ENROLLED = false;
const ENABLE_WHAT_YOULL_LEARN = true;
const ENABLE_CERTIFICATE_PROGRAM = false;

interface Lesson {
    id: number;
    title: string;
    estimated_duration: number;
    lesson_type: 'video' | 'article' | 'text';
}

interface Assessment {
    id: number;
    title: string;
}

interface Module {
    id: number;
    title: string;
    description: string;
    slug: string;
    order: number;
    is_required: boolean;
    lessons_count: number;
    estimated_duration: number;
    lessons?: Lesson[];
    assessments?: Assessment[];
}

interface Course {
    id: number;
    slug: string;
    title: string;
    description: string;
    estimated_duration: number;
    is_active: boolean;
    modules_count: number;
    enrollments_count: number;
    certificate_template?: {
        id: number;
        name: string;
        description: string;
    };
    modules: Module[];
    created_at: string;
    updated_at: string;
    url: string;
}

interface Enrollment {
    id: number;
    enrolled_at: string;
    completed_at?: string;
    progress_percentage: number;
}

interface BreadcrumbItem {
    title: string;
    url?: string;
}

interface Props {
    course: { data: Course };
    enrollment?: Enrollment;
    relatedCourses: Course[];
    breadcrumbs: BreadcrumbItem[];
}

const props = defineProps<Props>();

const courseDetail = computed(() => {
    return props.course.data;
});
const isEnrolled = computed(() => !!props.enrollment);
const isCompleted = computed(() => !!props.enrollment?.completed_at);
const progressPercentage = computed(
    () => props.enrollment?.progress_percentage || 0,
);
const canEnroll = computed(
    () => !isEnrolled.value && courseDetail.value.is_active,
);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatDuration = (minutes: number): string => {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0
        ? `${hours}h ${remainingMinutes}m`
        : `${hours}h`;
};

const formatNumber = (num: number) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num > 0 ? num.toString() : 0;
};

const enrollInCourse = async () => {
    try {
        await axios.post(`/api/v1/courses/${courseDetail.value.slug}/enroll`);
        // Reload the page to update enrollment status
        window.location.reload();
    } catch (error) {
        console.error('Failed to enroll in course:', error);
        alert('Failed to enroll in course. Please try again.');
    }
};

const continueCourse = () => {
    // Logic to continue from where user left off
    // For now, just go to first module
    if (courseDetail.value.modules.length > 0) {
        const firstModule = courseDetail.value.modules[0];
        const firstLesson = firstModule.lessons?.[0];
        if (firstLesson) {
            router.visit(
                `/courses/${courseDetail.value.slug}/modules/${firstModule.id}/lessons/${firstLesson.id}`,
            );
        } else {
            router.visit(
                `/courses/${courseDetail.value.slug}/modules/${firstModule.id}`,
            );
        }
    }
};

const sortedModules = computed(() => {
    return courseDetail.value.modules || [];
});

const requiredModules = computed(() => {
    return sortedModules.value.filter((module: any) => module.is_required)
        .length;
});

const optionalModules = computed(() => {
    return sortedModules.value.filter((module: any) => !module.is_required)
        .length;
});
</script>

<template>
    <Head>
        <title>{{ courseDetail.title }}</title>
        <meta name="description" :content="courseDetail.description" />
        <meta property="og:title" :content="courseDetail.title" />
        <meta property="og:description" :content="courseDetail.description" />
        <meta property="og:type" content="article" />
        <meta name="twitter:div" content="summary_large_image" />
    </Head>

    <CourseLayout :course="courseDetail" :modules="sortedModules">
        <div class="flex-1">
            <!-- Course Info -->
            <div class="w-full space-y-4 border-b p-4">
                <div class="flex items-center gap-3">
                    <Badge variant="outline">
                        <BookOpen class="mr-1 h-3 w-3" />
                        Course
                    </Badge>
                    <Badge
                        v-if="courseDetail.certificate_template"
                        variant="secondary"
                        class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                    >
                        <Award class="mr-1 h-3 w-3" />
                        Certificate Available
                    </Badge>
                    <Badge v-if="courseDetail.is_active" variant="default">
                        <TrendingUp class="mr-1 h-3 w-3" />
                        Active
                    </Badge>
                </div>

                <h1 class="text-xl leading-tight font-bold md:text-2xl">
                    {{ courseDetail.title }}
                </h1>

                <div class="flex flex-wrap gap-8 text-sm">
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                        >
                            <Clock
                                class="h-4 w-4 text-blue-600 dark:text-blue-400"
                            />
                        </div>
                        <span class="font-medium">{{
                            formatDuration(courseDetail.estimated_duration)
                        }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
                        >
                            <Users
                                class="h-4 w-4 text-green-600 dark:text-green-400"
                            />
                        </div>
                        <span class="font-medium"
                            >{{
                                formatNumber(courseDetail.enrollments_count)
                            }}
                            students</span
                        >
                    </div>
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30"
                        >
                            <Layers
                                class="h-4 w-4 text-purple-600 dark:text-purple-400"
                            />
                        </div>
                        <span class="font-medium"
                            >{{ courseDetail.modules_count || 0 }} modules</span
                        >
                    </div>
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30"
                        >
                            <Calendar
                                class="h-4 w-4 text-orange-600 dark:text-orange-400"
                            />
                        </div>
                        <span class="font-medium">{{
                            formatDate(courseDetail.created_at)
                        }}</span>
                    </div>
                </div>

                <div
                    v-if="ENABLE_PRGRESS_ENROLLED"
                    class="space-y-4 overflow-hidden rounded-lg bg-white/80 px-2 py-2 shadow-lg backdrop-blur-sm dark:bg-gray-900/80"
                >
                    <!-- Progress for enrolled users -->
                    <div v-if="isEnrolled">
                        <div
                            class="mb-3 flex items-center justify-between text-sm"
                        >
                            <span class="font-medium text-muted-foreground"
                                >Overall Progress</span
                            >
                            <span class="text-base font-semibold"
                                >{{ Math.round(progressPercentage) }}%</span
                            >
                        </div>

                        <Progress
                            :value="progressPercentage"
                            class="mb-6 h-3"
                        />

                        <div
                            v-if="isCompleted"
                            class="mb-6 flex items-center gap-3 rounded-lg bg-green-50 p-4 dark:bg-green-900/20"
                        >
                            <CheckCircle
                                class="h-6 w-6 text-green-600 dark:text-green-400"
                            />
                            <div>
                                <span
                                    class="font-semibold text-green-800 dark:text-green-200"
                                    >Course Completed!</span
                                >
                                <p
                                    class="text-sm text-green-600 dark:text-green-300"
                                >
                                    Congratulations on finishing this course
                                </p>
                            </div>
                        </div>

                        <Button
                            @click="continueCourse"
                            class="w-full"
                            size="lg"
                        >
                            <Play class="mr-2 h-5 w-5" />
                            {{
                                isCompleted
                                    ? 'Review Course'
                                    : 'Continue Learning'
                            }}
                        </Button>
                    </div>

                    <!-- Enrollment for non-enrolled users -->
                    <div v-else-if="canEnroll" class="flex items-center gap-4">
                        <div class="flex flex-1 items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                            >
                                <GraduationCap
                                    class="h-8 w-8 text-blue-600 dark:text-blue-400"
                                />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold">
                                    Ready to Start Learning?
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    Join thousands of students in this course
                                </p>
                            </div>
                        </div>

                        <Button size="lg" @click="enrollInCourse">
                            Enroll Now
                        </Button>
                    </div>

                    <!-- Not available -->
                    <div v-else class="py-6 text-center">
                        <Lock
                            class="mx-auto mb-4 h-12 w-12 text-muted-foreground"
                        />
                        <h3 class="mb-2 font-semibold">Course Unavailable</h3>
                        <p class="text-sm text-muted-foreground">
                            This course is not available for enrollment at the
                            moment.
                        </p>
                    </div>
                </div>
            </div>

            <div class="w-full border-b p-4">
                <!-- Course Details -->
                <div class="space-y-4">
                    <h2 class="text-base font-bold">Course Details</h2>

                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ courseDetail.description }}
                    </p>
                </div>
            </div>

            <div v-if="ENABLE_WHAT_YOULL_LEARN" class="w-full border-b p-4">
                <!-- What You'll Learn -->
                <div class="space-y-4">
                    <h2 class="text-base font-bold">What You'll Learn</h2>

                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <CheckCircle
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                            />
                            <span class="text-sm"
                                >Master the fundamentals through hands-on
                                practice and real-world examples</span
                            >
                        </li>
                        <li class="flex items-start gap-3">
                            <CheckCircle
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                            />
                            <span class="text-sm"
                                >Build practical projects that demonstrate your
                                skills and knowledge</span
                            >
                        </li>
                        <li class="flex items-start gap-3">
                            <CheckCircle
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                            />
                            <span class="text-sm"
                                >Complete assessments to validate and reinforce
                                your learning</span
                            >
                        </li>
                        <li class="flex items-start gap-3">
                            <CheckCircle
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                            />
                            <span class="text-sm">{{
                                courseDetail.certificate_template
                                    ? 'Earn a professional certificate of completion'
                                    : 'Track your learning progress and achievements'
                            }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="w-full border-b p-4">
                <!-- Course Analytics -->
                <div v-if="sortedModules.length > 0" class="space-y-4">
                    <h2 class="text-base font-bold">Course Overview</h2>

                    <div class="space-y-6">
                        <!-- Module Overview -->
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                class="rounded-lg bg-gray-100 p-4 text-center dark:bg-gray-900/60"
                            >
                                <div
                                    class="mb-1 text-3xl font-bold text-blue-600"
                                >
                                    {{ courseDetail.modules_count || 0 }}
                                </div>
                                <div
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Total Modules
                                </div>
                            </div>
                            <div
                                class="rounded-lg bg-gray-100 p-4 text-center dark:bg-gray-900/60"
                            >
                                <div
                                    class="mb-1 text-3xl font-bold text-green-600"
                                >
                                    {{
                                        formatNumber(
                                            courseDetail.enrollments_count,
                                        )
                                    }}
                                </div>
                                <div
                                    class="text-sm font-medium text-muted-foreground"
                                >
                                    Students
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar for enrolled users -->
                        <div
                            v-if="isEnrolled && progressPercentage > 0"
                            class="rounded-lg bg-white/60 p-4 dark:bg-gray-900/60"
                        >
                            <div
                                class="mb-3 flex items-center justify-between text-sm"
                            >
                                <span class="font-medium text-muted-foreground"
                                    >Your Progress</span
                                >
                                <span class="text-lg font-bold"
                                    >{{ Math.round(progressPercentage) }}%</span
                                >
                            </div>
                            <Progress :value="progressPercentage" class="h-3" />
                        </div>

                        <!-- Module Breakdown -->
                        <div class="space-y-3">
                            <h4
                                class="text-sm font-semibold tracking-wide text-muted-foreground"
                            >
                                Module Breakdown
                            </h4>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground"
                                        >Required Modules</span
                                    >
                                    <Badge
                                        variant="destructive"
                                        class="text-xs"
                                        >{{ requiredModules }}</Badge
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground"
                                        >Optional Modules</span
                                    >
                                    <Badge
                                        variant="secondary"
                                        class="text-xs"
                                        >{{ optionalModules }}</Badge
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground"
                                        >Total Duration</span
                                    >
                                    <span class="font-semibold">{{
                                        formatDuration(
                                            courseDetail.estimated_duration,
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certificate Information -->
            <div
                v-if="ENABLE_CERTIFICATE_PROGRAM && courseDetail.certificate_template"
                class="w-full space-y-4 p-4"
            >
                <h2 class="text-base font-bold">Certificate Program</h2>

                <div class="space-y-4">
                    <div class="rounded-lg bg-white/60 p-4 dark:bg-gray-900/60">
                        <div class="flex items-start gap-3">
                            <div class="flex-1">
                                <h4
                                    class="mb-1 font-semibold text-green-900 dark:text-green-100"
                                >
                                    {{ courseDetail.certificate_template.name }}
                                </h4>
                                <p
                                    class="text-sm text-green-700 dark:text-green-300"
                                >
                                    {{
                                        courseDetail.certificate_template
                                            .description
                                    }}
                                </p>
                            </div>
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
                            >
                                <GraduationCap
                                    class="h-5 w-5 text-green-600 dark:text-green-400"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-sm text-muted-foreground">
                    Complete all required modules to earn your certificate of
                    completion.
                </div>
            </div>
        </div>
    </CourseLayout>
</template>
