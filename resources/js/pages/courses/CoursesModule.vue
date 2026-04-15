<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
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
import { Head, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Clock,
    CheckCircle,
    PlayCircle,
    FileText,
    Target,
    Play,
    ChevronRight,
    BarChart3,
    Layers,
    Star,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Lesson {
    id: number;
    title: string;
    order_index: number;
    estimated_duration: number;
    lesson_type: string;
    is_completed?: boolean;
}

interface Assessment {
    id: number;
    title: string;
    is_required: boolean;
    passing_score: number;
}

interface Assignment {
    id: number;
    title: string;
    due_date?: string;
}

interface Course {
    id: number;
    title: string;
    slug: string;
}

interface Module {
    id: number;
    title: string;
    description: string;
    order_index: number;
    is_required: boolean;
    estimated_duration: number;
    progress_percentage?: number;
    course: Course;
    lessons: Lesson[];
    assessments: Assessment[];
    assignments: Assignment[];
}

interface BreadcrumbItem {
    title: string;
    url?: string;
}

interface Props {
    module: Module;
    breadcrumbs: BreadcrumbItem[];
}

const props = defineProps<Props>();

const formatDuration = (minutes: number): string => {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const sortedLessons = computed(() => {
    return props.module.lessons
        ?.slice()
        .sort((a, b) => a.order_index - b.order_index) || [];
});

const completedLessons = computed(() => {
    return sortedLessons.value.filter(lesson => lesson.is_completed).length;
});

const totalLessons = computed(() => {
    return sortedLessons.value.length;
});

const progressPercentage = computed(() => {
    return props.module.progress_percentage || 0;
});

const nextLesson = computed(() => {
    return sortedLessons.value.find(lesson => !lesson.is_completed) || sortedLessons.value[0];
});

const getLessonTypeIcon = (type: string) => {
    switch (type) {
        case 'video':
            return PlayCircle;
        case 'text':
            return FileText;
        case 'quiz':
            return Target;
        default:
            return BookOpen;
    }
};

const getLessonTypeColor = (type: string) => {
    switch (type) {
        case 'video':
            return 'text-blue-600 dark:text-blue-400';
        case 'text':
            return 'text-green-600 dark:text-green-400';
        case 'quiz':
            return 'text-purple-600 dark:text-purple-400';
        default:
            return 'text-gray-600 dark:text-gray-400';
    }
};

const startNextLesson = () => {
    if (nextLesson.value) {
        router.visit(`/courses/${props.module.course.slug}/modules/${props.module.id}/lessons/${nextLesson.value.id}`);
    }
};
</script>

<template>
    <Head>
        <title>{{ module.title }} - {{ module.course.title }}</title>
        <meta name="description" :content="module.description" />
    </Head>

    <FrontLayout>
        <template #front-prepend>
            <!-- Breadcrumbs -->
            <div class="border-b bg-gray-50/50 py-4 dark:bg-gray-900/20">
                <div class="mx-auto px-4">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" :is-back="true" />
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="min-h-screen bg-background">
                <div class="grid grid-cols-[1fr__420px]">
                    <!-- Main Content (Left Side) -->
                    <div class="flex-1 border-r">
                        <!-- Module Header -->
                        <div class="border-b bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20 p-6 lg:p-8">
                            <div class="space-y-6">
                                <!-- Module Info -->
                                <div>
                                    <div class="mb-4 flex items-center gap-3">
                                        <Badge variant="outline">
                                            <Layers class="mr-1 h-3 w-3" />
                                            Module {{ module.order_index }}
                                        </Badge>
                                        <Badge
                                            v-if="module.is_required"
                                            variant="destructive"
                                            class="text-xs"
                                        >
                                            <Star class="mr-1 h-3 w-3 fill-current" />
                                            Required
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="secondary"
                                            class="text-xs"
                                        >
                                            Optional
                                        </Badge>
                                    </div>

                                    <h1 class="mb-4 text-3xl font-bold leading-tight lg:text-4xl">
                                        {{ module.title }}
                                    </h1>

                                    <p class="text-lg text-muted-foreground leading-relaxed">
                                        {{ module.description }}
                                    </p>
                                </div>

                                <!-- Module Stats -->
                                <div class="flex flex-wrap gap-6 text-sm">
                                    <div class="flex items-center gap-2 text-muted-foreground">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                            <Clock class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <span class="font-medium">{{ formatDuration(module.estimated_duration) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-muted-foreground">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                            <BookOpen class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        </div>
                                        <span class="font-medium">{{ totalLessons }} {{ totalLessons === 1 ? 'lesson' : 'lessons' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-muted-foreground">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                            <Target class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                        </div>
                                        <span class="font-medium">{{ module.assessments.length }} {{ module.assessments.length === 1 ? 'assessment' : 'assessments' }}</span>
                                    </div>
                                    <div v-if="module.assignments.length > 0" class="flex items-center gap-2 text-muted-foreground">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30">
                                            <FileText class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                        </div>
                                        <span class="font-medium">{{ module.assignments.length }} {{ module.assignments.length === 1 ? 'assignment' : 'assignments' }}</span>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div v-if="progressPercentage > 0" class="rounded-lg bg-white/60 p-4 dark:bg-gray-900/60">
                                    <div class="mb-3 flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground font-medium">Module Progress</span>
                                        <span class="font-bold text-lg">{{ Math.round(progressPercentage) }}%</span>
                                    </div>
                                    <Progress :value="progressPercentage" class="h-3" />
                                    <div class="mt-2 text-xs text-muted-foreground">
                                        {{ completedLessons }} of {{ totalLessons }} lessons completed
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <div>
                                    <Button
                                        v-if="nextLesson"
                                        @click="startNextLesson"
                                        class="h-12 text-lg font-semibold bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                                        size="lg"
                                    >
                                        <Play class="mr-2 h-5 w-5" />
                                        {{ completedLessons === 0 ? 'Start Module' : 'Continue Learning' }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Module Content -->
                        <div class="p-6 lg:p-8">
                            <div class="space-y-8">
                                <!-- Learning Objectives -->
                                <Card>
                                    <CardHeader>
                                        <CardTitle class="flex items-center gap-2">
                                            <Target class="h-5 w-5 text-blue-600" />
                                            Learning Objectives
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul class="space-y-3">
                                            <li class="flex items-start gap-3">
                                                <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                                <span class="text-sm">Master the core concepts covered in this module</span>
                                            </li>
                                            <li class="flex items-start gap-3">
                                                <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                                <span class="text-sm">Apply knowledge through practical exercises and examples</span>
                                            </li>
                                            <li class="flex items-start gap-3">
                                                <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                                <span class="text-sm">Complete assessments to validate your understanding</span>
                                            </li>
                                            <li v-if="module.assignments.length > 0" class="flex items-start gap-3">
                                                <CheckCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                                <span class="text-sm">Submit assignments to demonstrate practical skills</span>
                                            </li>
                                        </ul>
                                    </CardContent>
                                </Card>

                                <!-- Assignments (if any) -->
                                <Card v-if="module.assignments.length > 0">
                                    <CardHeader>
                                        <CardTitle class="flex items-center gap-2">
                                            <FileText class="h-5 w-5 text-orange-600" />
                                            Assignments
                                        </CardTitle>
                                        <CardDescription>
                                            Complete these assignments to demonstrate your understanding
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="space-y-4">
                                            <div
                                                v-for="assignment in module.assignments"
                                                :key="assignment.id"
                                                class="flex items-center justify-between rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-900/50"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30">
                                                        <FileText class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                                    </div>
                                                    <div>
                                                        <h4 class="font-semibold">{{ assignment.title }}</h4>
                                                        <p v-if="assignment.due_date" class="text-sm text-muted-foreground">
                                                            Due: {{ formatDate(assignment.due_date) }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <Button variant="outline" size="sm">
                                                    View Assignment
                                                    <ChevronRight class="ml-1 h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <!-- Assessments -->
                                <Card v-if="module.assessments.length > 0">
                                    <CardHeader>
                                        <CardTitle class="flex items-center gap-2">
                                            <Target class="h-5 w-5 text-purple-600" />
                                            Assessments
                                        </CardTitle>
                                        <CardDescription>
                                            Test your knowledge with these assessments
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="space-y-4">
                                            <div
                                                v-for="assessment in module.assessments"
                                                :key="assessment.id"
                                                class="flex items-center justify-between rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-900/50"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                                        <Target class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                                    </div>
                                                    <div>
                                                        <h4 class="font-semibold">{{ assessment.title }}</h4>
                                                        <div class="flex items-center gap-4 text-sm text-muted-foreground">
                                                            <span>Passing Score: {{ assessment.passing_score }}%</span>
                                                            <Badge
                                                                v-if="assessment.is_required"
                                                                variant="destructive"
                                                                class="text-xs"
                                                            >
                                                                Required
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                </div>
                                                <Button variant="outline" size="sm">
                                                    Take Assessment
                                                    <ChevronRight class="ml-1 h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar (Right Side) -->
                    <div class="flex-1 bg-gray-50/50 dark:bg-gray-900/20">
                        <div class="sticky top-0 h-screen overflow-y-auto">
                            <!-- Module Navigation Header -->
                            <div class="border-b bg-white/80 backdrop-blur-sm p-6 dark:bg-gray-900/80">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-semibold text-lg">Module Content</h3>
                                    <Badge variant="outline" class="text-xs">
                                        {{ completedLessons }}/{{ totalLessons }}
                                    </Badge>
                                </div>
                                <div v-if="totalLessons > 0" class="space-y-2">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">Progress</span>
                                        <span class="font-semibold">{{ Math.round((completedLessons / totalLessons) * 100) }}%</span>
                                    </div>
                                    <Progress :value="(completedLessons / totalLessons) * 100" class="h-2" />
                                </div>
                            </div>

                            <!-- Lessons List -->
                            <div class="p-6">
                                <div class="space-y-3">
                                    <div
                                        v-for="(lesson, index) in sortedLessons"
                                        :key="lesson.id"
                                        class="group cursor-pointer rounded-lg border bg-white p-4 transition-all hover:shadow-md hover:border-blue-200 dark:bg-gray-900 dark:hover:border-blue-800"
                                        @click="() => router.visit(`/courses/${module.course.slug}/modules/${module.id}/lessons/${lesson.id}`)"
                                    >
                                        <div class="flex items-start gap-3">
                                            <!-- Lesson Status Icon -->
                                            <div class="flex-shrink-0 mt-1">
                                                <div
                                                    v-if="lesson.is_completed"
                                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
                                                >
                                                    <CheckCircle class="h-4 w-4 text-green-600 dark:text-green-400" />
                                                </div>
                                                <div
                                                    v-else
                                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"
                                                >
                                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ index + 1 }}</span>
                                                </div>
                                            </div>

                                            <!-- Lesson Content -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="flex-1">
                                                        <h4 class="font-medium text-sm leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                                            {{ lesson.title }}
                                                        </h4>
                                                        <div class="flex items-center gap-3 mt-2 text-xs text-muted-foreground">
                                                            <div class="flex items-center gap-1">
                                                                <component
                                                                    :is="getLessonTypeIcon(lesson.lesson_type)"
                                                                    class="h-3 w-3"
                                                                    :class="getLessonTypeColor(lesson.lesson_type)"
                                                                />
                                                                <span class="capitalize">{{ lesson.lesson_type }}</span>
                                                            </div>
                                                            <div class="flex items-center gap-1">
                                                                <Clock class="h-3 w-3" />
                                                                <span>{{ formatDuration(lesson.estimated_duration) }}</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Play Button -->
                                                    <div class="flex-shrink-0">
                                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <Play class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- No lessons message -->
                                    <div v-if="sortedLessons.length === 0" class="py-8 text-center">
                                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                            <BookOpen class="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <h4 class="font-semibold mb-2">No Lessons Available</h4>
                                        <p class="text-sm text-muted-foreground">This module doesn't have any lessons yet.</p>
                                    </div>
                                </div>

                                <!-- Module Stats -->
                                <div v-if="totalLessons > 0" class="mt-8 pt-6 border-t">
                                    <Card class="border-0 shadow-sm bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20">
                                        <CardHeader class="pb-3">
                                            <CardTitle class="flex items-center gap-2 text-base">
                                                <BarChart3 class="h-4 w-4 text-blue-600" />
                                                Module Stats
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent class="pt-0">
                                            <div class="space-y-3 text-sm">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-muted-foreground">Total Lessons</span>
                                                    <span class="font-semibold">{{ totalLessons }}</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-muted-foreground">Completed</span>
                                                    <span class="font-semibold text-green-600">{{ completedLessons }}</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-muted-foreground">Remaining</span>
                                                    <span class="font-semibold text-blue-600">{{ totalLessons - completedLessons }}</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-muted-foreground">Duration</span>
                                                    <span class="font-semibold">{{ formatDuration(module.estimated_duration) }}</span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </template>
    </FrontLayout>
</template>
