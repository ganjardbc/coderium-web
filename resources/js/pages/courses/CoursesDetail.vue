<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CourseCard from '@/components/CourseCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BookOpen,
    Clock,
    Users,
    GraduationCap,
    Award,
    PlayCircle,
    CheckCircle,
    Calendar,
    Target,
    Play,
    Lock,
    Layers,
    BarChart3,
    Star,
    TrendingUp,
    FileText,
    TimerIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

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
const progressPercentage = computed(() => props.enrollment?.progress_percentage || 0);
const canEnroll = computed(() => !isEnrolled.value && courseDetail.value.is_active);

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
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
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

const startCourse = () => {
    if (courseDetail.value.modules.length > 0) {
        const firstModule = courseDetail.value.modules[0];
        window.location.href = `/courses/${courseDetail.value.slug}/modules/${firstModule.id}`;
    }
};

const continueCourse = () => {
    // Logic to continue from where user left off
    // For now, just go to first module
    startCourse();
};

const sortedModules = computed(() => {
    return courseDetail.value.modules
        ?.slice()
        .sort((a: any, b: any) => a.order - b.order) || [];
});

const requiredModules = computed(() => {
    return sortedModules.value.filter((module: any) => module.is_required).length;
});

const optionalModules = computed(() => {
    return sortedModules.value.filter((module: any) => !module.is_required).length;
});
</script>

<template>
    <Head>
        <title>{{ courseDetail.title }}</title>
        <meta name="description" :content="courseDetail.description" />
        <meta property="og:title" :content="courseDetail.title" />
        <meta property="og:description" :content="courseDetail.description" />
        <meta property="og:type" content="article" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>

    <FrontLayout>
        <!-- Course Header -->
        <template #front-prepend>
            <!-- Breadcrumbs -->
            <div class="border-b bg-gray-50/50 py-4 dark:bg-gray-900/20">
                <div class="container mx-auto px-4">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" :is-back="true" />
                </div>
            </div>

            <section class="w-full py-12 border-b bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20">
                <div class="container mx-auto px-4">
                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        <!-- Course Info -->
                        <div class="lg:col-span-2">
                            <div class="mb-6 flex items-center gap-3">
                                <Badge variant="outline">
                                    <BookOpen class="mr-1 h-3 w-3" />
                                    Course
                                </Badge>
                                <Badge v-if="courseDetail.certificate_template" variant="secondary" class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                    <Award class="mr-1 h-3 w-3" />
                                    Certificate Available
                                </Badge>
                                <Badge v-if="courseDetail.is_active" variant="default">
                                    <TrendingUp class="mr-1 h-3 w-3" />
                                    Active
                                </Badge>
                            </div>

                            <h1 class="mb-6 text-3xl font-bold leading-tight md:text-4xl">
                                {{ courseDetail.title }}
                            </h1>

                            <div class="flex flex-wrap gap-8 text-sm">
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                        <Clock class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <span class="font-medium">{{ formatDuration(courseDetail.estimated_duration) }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                        <Users class="h-4 w-4 text-green-600 dark:text-green-400" />
                                    </div>
                                    <span class="font-medium">{{ formatNumber(courseDetail.enrollments_count) }} students</span>
                                </div>
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                        <Layers class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <span class="font-medium">{{ courseDetail.modules_count }} modules</span>
                                </div>
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30">
                                        <Calendar class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                    </div>
                                    <span class="font-medium">{{ formatDate(courseDetail.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Enrollment Card -->
                        <div class="lg:col-span-1">
                            <Card class="sticky top-4 shadow-lg border-0 bg-white/80 backdrop-blur-sm dark:bg-gray-900/80">
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2 text-xl">
                                        <Target class="h-6 w-6 text-blue-600" />
                                        Course Progress
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-6">
                                    <!-- Progress for enrolled users -->
                                    <div v-if="isEnrolled">
                                        <div class="mb-3 flex items-center justify-between text-sm">
                                            <span class="text-muted-foreground font-medium">Overall Progress</span>
                                            <span class="font-bold text-lg">{{ Math.round(progressPercentage) }}%</span>
                                        </div>
                                        <Progress :value="progressPercentage" class="mb-6 h-3" />

                                        <div v-if="isCompleted" class="mb-6 flex items-center gap-3 rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                                            <CheckCircle class="h-6 w-6 text-green-600 dark:text-green-400" />
                                            <div>
                                                <span class="font-semibold text-green-800 dark:text-green-200">Course Completed!</span>
                                                <p class="text-sm text-green-600 dark:text-green-300">Congratulations on finishing this course</p>
                                            </div>
                                        </div>

                                        <Button
                                            @click="continueCourse"
                                            class="w-full"
                                            size="lg"
                                        >
                                            <Play class="mr-2 h-5 w-5" />
                                            {{ isCompleted ? 'Review Course' : 'Continue Learning' }}
                                        </Button>
                                    </div>

                                    <!-- Enrollment for non-enrolled users -->
                                    <div v-else-if="canEnroll">
                                        <div class="mb-6 text-center">
                                            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 mx-auto dark:bg-blue-900/30">
                                                <GraduationCap class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                                            </div>
                                            <h3 class="font-semibold text-lg mb-2">Ready to Start Learning?</h3>
                                            <p class="text-sm text-muted-foreground">Join thousands of students in this course</p>
                                        </div>

                                        <Button
                                            @click="enrollInCourse"
                                            class="w-full"
                                            size="lg"
                                        >
                                            Enroll Now
                                        </Button>

                                        <div class="text-center text-sm text-gray-500 mt-4">
                                            This Course is FREE
                                        </div>
                                    </div>

                                    <!-- Not available -->
                                    <div v-else class="text-center py-6">
                                        <Lock class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                                        <h3 class="font-semibold mb-2">Course Unavailable</h3>
                                        <p class="text-sm text-muted-foreground">
                                            This course is not available for enrollment at the moment.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <!-- Course Content -->
        <section class="py-12">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-10 lg:col-span-2">
                        <!-- Course Details -->
                         <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-bold">Course Details</h2>
                            </div>

                            <p class="text-md text-muted-foreground leading-relaxed">
                                {{ courseDetail.description }}
                            </p>
                        </div>

                        <!-- Course Details -->
                        <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-bold">What You'll Learn</h2>
                            </div>

                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                    <span class="text-sm">Master the fundamentals through hands-on practice and real-world examples</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                    <span class="text-sm">Build practical projects that demonstrate your skills and knowledge</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                    <span class="text-sm">Complete assessments to validate and reinforce your learning</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                    <span class="text-sm">{{ courseDetail.certificate_template ? 'Earn a professional certificate of completion' : 'Track your learning progress and achievements' }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Course Modules -->
                        <div class="w-full">
                            <div class="mb-8 flex items-center justify-between">
                                <h2 class="text-xl font-bold">Course Curriculum</h2>
                                <Badge variant="outline" class="text-sm">
                                    {{ sortedModules.length }} {{ sortedModules.length === 1 ? 'Module' : 'Modules' }}
                                </Badge>
                            </div>

                            <div v-if="sortedModules.length > 0" class="space-y-4">
                                <Card
                                    v-for="(module, index) in sortedModules"
                                    :key="module.id"
                                    class="group transition-all duration-300 hover:shadow-lg hover:border-blue-200 dark:hover:border-blue-800"
                                >
                                    <CardHeader>
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3">
                                                <Badge variant="outline" class="text-xs font-medium">
                                                    Module {{ index + 1 }}
                                                </Badge>
                                                <Badge
                                                    v-if="module.is_required"
                                                    variant="destructive"
                                                    class="text-xs"
                                                >
                                                    Required
                                                </Badge>
                                                <Badge
                                                    v-else
                                                    variant="secondary"
                                                    class="text-xs"
                                                >
                                                    Optional
                                                </Badge>
                                                <Badge variant="secondary" class="text-xs">
                                                    <Layers class="mr-1 h-3 w-3" />
                                                    {{ module.lessons_count }} {{ module.lessons_count === 1 ? 'lesson' : 'lessons' }}
                                                </Badge>
                                                <Badge variant="secondary" class="text-xs">
                                                    <TimerIcon class="mr-1 h-3 w-3" />
                                                    {{ formatDuration(module.estimated_duration) }}
                                                </Badge>
                                            </div>
                                            <div class="flex flex-col items-end gap-3">
                                                <Button
                                                    v-if="isEnrolled"
                                                    :as="Link"
                                                    :href="`/courses/${courseDetail.slug}/modules/${module.id}`"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Play class="mr-2 h-4 w-4" />
                                                    Start Module
                                                </Button>
                                                <div v-else class="flex items-center gap-2 text-muted-foreground">
                                                    <Lock class="h-4 w-4" />
                                                    <span class="text-sm">Enroll to access</span>
                                                </div>
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent>
                                        <div class="w-full">
                                            <CardTitle class="text-lg transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                                {{ module.title }}
                                            </CardTitle>
                                            <CardDescription class="text-sm leading-relaxed mb-4">
                                                {{ module.description }}
                                            </CardDescription>

                                            <!-- Lessons List -->
                                            <div v-if="module.lessons && module.lessons.length > 0" class="mt-4 space-y-2">
                                                <div
                                                    v-for="lesson in module.lessons"
                                                    :key="lesson.id"
                                                    class="flex items-center gap-3 text-sm p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                                >
                                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                        <PlayCircle v-if="lesson.lesson_type === 'video'" class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                        <FileText v-else-if="lesson.lesson_type === 'article'" class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                        <BookOpen v-else class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ lesson.title }}</div>
                                                        <div class="text-xs text-muted-foreground">{{ formatDuration(lesson.estimated_duration) }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Assessments List -->
                                            <div v-if="module.assessments && module.assessments.length > 0" class="mt-4 space-y-2">
                                                 <div
                                                    v-for="assessment in module.assessments"
                                                    :key="assessment.id"
                                                    class="flex items-center gap-3 text-sm p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                                >
                                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                                        <Target class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ assessment.title }}</div>
                                                        <div class="text-xs text-muted-foreground">Assessment</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div v-else class="py-16 text-center">
                                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                    <BookOpen class="h-10 w-10 text-muted-foreground" />
                                </div>
                                <h3 class="mb-2 text-xl font-semibold">No Modules Available</h3>
                                <p class="text-muted-foreground">This course doesn't have any modules yet. Check back later!</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-8">
                            <!-- Course Analytics -->
                            <Card v-if="sortedModules.length > 0" class="border-0 shadow-lg bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20">
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2 text-xl">
                                        <BarChart3 class="h-6 w-6 text-blue-600" />
                                        Course Overview
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-6">
                                        <!-- Module Overview -->
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="text-center">
                                                <div class="text-3xl font-bold text-blue-600 mb-1">
                                                    {{ courseDetail.modules_count || 0 }}
                                                </div>
                                                <div class="text-sm text-muted-foreground font-medium">
                                                    Total Modules
                                                </div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-3xl font-bold text-green-600 mb-1">
                                                    {{ formatNumber(courseDetail.enrollments_count) }}
                                                </div>
                                                <div class="text-sm text-muted-foreground font-medium">
                                                    Students
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Progress Bar for enrolled users -->
                                        <div v-if="isEnrolled && progressPercentage > 0" class="rounded-lg bg-white/60 p-4 dark:bg-gray-900/60">
                                            <div class="mb-3 flex items-center justify-between text-sm">
                                                <span class="text-muted-foreground font-medium">Your Progress</span>
                                                <span class="font-bold text-lg">{{ Math.round(progressPercentage) }}%</span>
                                            </div>
                                            <Progress :value="progressPercentage" class="h-3" />
                                        </div>

                                        <!-- Module Breakdown -->
                                        <div class="space-y-3">
                                            <h4 class="font-semibold text-sm tracking-wide text-muted-foreground">Module Breakdown</h4>
                                            <div class="space-y-3 text-sm">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-muted-foreground">Required Modules</span>
                                                    <Badge variant="destructive" class="text-xs">{{ requiredModules }}</Badge>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-muted-foreground">Optional Modules</span>
                                                    <Badge variant="secondary" class="text-xs">{{ optionalModules }}</Badge>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-muted-foreground">Total Duration</span>
                                                    <span class="font-semibold">{{ formatDuration(courseDetail.estimated_duration) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Certificate Information -->
                            <Card v-if="courseDetail.certificate_template" class="border-0 shadow-lg bg-gradient-to-br from-green-50/50 to-emerald-50/50 dark:from-green-950/20 dark:to-emerald-950/20">
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2 text-xl">
                                        <Award class="h-6 w-6 text-green-600" />
                                        Certificate Program
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-4">
                                        <div class="rounded-lg bg-white/60 p-4 dark:bg-gray-900/60">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                                    <GraduationCap class="h-5 w-5 text-green-600 dark:text-green-400" />
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold text-green-900 dark:text-green-100 mb-1">
                                                        {{ courseDetail.certificate_template.name }}
                                                    </h4>
                                                    <p class="text-sm text-green-700 dark:text-green-300">
                                                        {{ courseDetail.certificate_template.description }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-sm text-muted-foreground">
                                            Complete all required modules to earn your certificate of completion.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Related Courses -->
        <section
            v-if="props.relatedCourses && props.relatedCourses.length > 0"
            class="border-t bg-gray-50/50 py-16 dark:bg-gray-900/20"
        >
            <div class="container mx-auto px-4">
                <div class="mx-auto w-full">
                    <div class="mb-8 text-center">
                        <h2 class="text-3xl font-bold mb-4">Continue Your Learning Journey</h2>
                        <p class="text-lg text-muted-foreground">Explore more courses to expand your skills</p>
                    </div>
                    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <CourseCard
                            v-for="relatedCourse in props.relatedCourses"
                            :key="relatedCourse.id"
                            :course="relatedCourse"
                        />
                    </div>
                </div>
            </div>
        </section>

    </FrontLayout>
</template>
