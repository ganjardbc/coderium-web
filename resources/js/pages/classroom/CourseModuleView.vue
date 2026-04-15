<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
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
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    CheckCircle,
    ChevronRight,
    Clock,
    FileText,
    Play,
    Target,
    Video,
    Award,
    Layers,
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

interface Props {
    module: Module;
    breadcrumbs: BreadcrumbItem[];
}

const props = defineProps<Props>();

const sortedLessons = computed(() => {
    return (
        props.module.lessons
            ?.slice()
            .sort((a, b) => a.order_index - b.order_index) || []
    );
});

const moduleProgress = computed(() => {
    return props.module.progress_percentage || 0;
});

const formatDuration = (minutes: number | undefined): string => {
    if (!minutes) return 'N/A';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
};

const getLessonIcon = (lessonType: string) => {
    switch (lessonType) {
        case 'video':
            return Video;
        case 'interactive':
            return Play;
        default:
            return FileText;
    }
};

const getLessonTypeColor = (lessonType: string): string => {
    switch (lessonType) {
        case 'video':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'interactive':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getNextLesson = (): Lesson | null => {
    const incompleteLesson = sortedLessons.value.find(
        (lesson) => !lesson.is_completed,
    );
    return incompleteLesson || null;
};

const startNextLesson = () => {
    const nextLesson = getNextLesson();
    if (nextLesson) {
        router.visit(`/courses/${props.module.course.slug}/modules/${props.module.id}/lessons/${nextLesson.id}`);
    }
};

const completedLessonsCount = computed(() => {
    return sortedLessons.value.filter((lesson) => lesson.is_completed).length;
});

const goToLesson = (lesson: Lesson) => {
    router.visit(`/courses/${props.module.course.slug}/modules/${props.module.id}/lessons/${lesson.id}`);
};

const goToAssessment = (assessment: Assessment) => {
    router.visit(`/courses/${props.module.course.slug}/modules/${props.module.id}/assessments/${assessment.id}`);
};
</script>

<template>
    <Head :title="`${module.title} - ${module.course.title}`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Module Header -->
        <section class="w-full py-12 border-b bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Module Info -->
                    <div class="lg:col-span-2">
                        <div class="mb-6 flex items-center gap-3">
                            <Badge variant="outline" class="text-sm bg-white/80 backdrop-blur-sm">
                                <Layers class="mr-1 h-3 w-3" />
                                {{ sortedLessons.length }} {{ sortedLessons.length === 1 ? 'lesson' : 'lessons' }}
                            </Badge>
                            <Badge
                                v-if="module.is_required"
                                variant="destructive"
                                class="text-sm"
                            >
                                Required Module
                            </Badge>
                            <Badge
                                v-else
                                variant="secondary"
                                class="text-sm"
                            >
                                Optional Module
                            </Badge>
                            <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                <Clock class="h-4 w-4" />
                                <span>{{ formatDuration(module.estimated_duration) }}</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <Link
                                :href="`/courses/${module.course.slug}`"
                                class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                            >
                                ← Back to {{ module.course.title }}
                            </Link>
                        </div>

                        <h1 class="mb-6 text-4xl font-bold leading-tight md:text-5xl">
                            {{ module.title }}
                        </h1>

                        <p class="mb-8 text-xl text-muted-foreground leading-relaxed">
                            {{ module.description }}
                        </p>
                    </div>

                    <!-- Progress Card -->
                    <div class="lg:col-span-1">
                        <Card class="sticky top-4 shadow-lg border-0 bg-white/80 backdrop-blur-sm dark:bg-gray-900/80">
                            <CardHeader class="pb-4">
                                <CardTitle class="flex items-center gap-2 text-xl">
                                    <Target class="h-6 w-6 text-blue-600" />
                                    Module Progress
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div class="mb-3 flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground font-medium">Overall Progress</span>
                                    <span class="font-bold text-lg">{{ Math.round(moduleProgress) }}%</span>
                                </div>
                                <Progress :value="moduleProgress" class="mb-6 h-3" />

                                <Button
                                    @click="startNextLesson"
                                    :disabled="!getNextLesson()"
                                    class="w-full h-12 text-lg font-semibold"
                                    size="lg"
                                >
                                    <Play class="mr-2 h-5 w-5" />
                                    {{
                                        getNextLesson()
                                            ? 'Continue Learning'
                                            : 'Module Complete'
                                    }}
                                </Button>

                                <div class="text-center text-sm text-muted-foreground">
                                    {{ completedLessonsCount }} of {{ sortedLessons.length }} lessons completed
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- Module Content -->
        <section class="py-12">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2">
                        <!-- Lessons -->
                        <div class="mb-12">
                            <div class="mb-8 flex items-center justify-between">
                                <h2 class="text-3xl font-bold">Lessons</h2>
                                <Badge variant="outline" class="text-sm">
                                    {{ sortedLessons.length }} {{ sortedLessons.length === 1 ? 'Lesson' : 'Lessons' }}
                                </Badge>
                            </div>

                            <div v-if="sortedLessons.length > 0" class="space-y-4">
                                <Card
                                    v-for="(lesson, index) in sortedLessons"
                                    :key="lesson.id"
                                    class="group cursor-pointer transition-all duration-300 hover:shadow-lg hover:border-blue-200 dark:hover:border-blue-800"
                                    @click="goToLesson(lesson)"
                                >
                                    <CardHeader class="pb-4">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="mb-3 flex items-center gap-3">
                                                    <Badge variant="outline" class="text-xs font-medium">
                                                        Lesson {{ index + 1 }}
                                                    </Badge>
                                                    <Badge
                                                        :class="getLessonTypeColor(lesson.lesson_type)"
                                                        class="text-xs"
                                                    >
                                                        <component
                                                            :is="getLessonIcon(lesson.lesson_type)"
                                                            class="mr-1 h-3 w-3"
                                                        />
                                                        {{
                                                            lesson.lesson_type
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                            lesson.lesson_type.slice(1)
                                                        }}
                                                    </Badge>
                                                    <div
                                                        v-if="lesson.estimated_duration"
                                                        class="flex items-center gap-1 text-xs text-muted-foreground"
                                                    >
                                                        <Clock class="h-3 w-3" />
                                                        <span>{{ formatDuration(lesson.estimated_duration) }}</span>
                                                    </div>
                                                </div>
                                                <CardTitle class="flex items-center gap-3 text-xl mb-2 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                                    {{ lesson.title }}
                                                    <CheckCircle
                                                        v-if="lesson.is_completed"
                                                        class="h-6 w-6 text-green-500"
                                                    />
                                                </CardTitle>
                                            </div>

                                            <div class="ml-6 flex items-center gap-2">
                                                <ChevronRight class="h-6 w-6 text-muted-foreground transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400" />
                                            </div>
                                        </div>
                                    </CardHeader>
                                </Card>
                            </div>

                            <div v-else class="py-16 text-center">
                                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                    <BookOpen class="h-10 w-10 text-muted-foreground" />
                                </div>
                                <h3 class="mb-2 text-xl font-semibold">No Lessons Available</h3>
                                <p class="text-muted-foreground">This module doesn't have any lessons yet.</p>
                            </div>
                        </div>

                        <!-- Assessments -->
                        <div v-if="module.assessments && module.assessments.length > 0" class="mb-12">
                            <div class="mb-8 flex items-center justify-between">
                                <h2 class="text-3xl font-bold">Assessments</h2>
                                <Badge variant="outline" class="text-sm">
                                    {{ module.assessments.length }} {{ module.assessments.length === 1 ? 'Assessment' : 'Assessments' }}
                                </Badge>
                            </div>

                            <div class="space-y-4">
                                <Card
                                    v-for="assessment in module.assessments"
                                    :key="assessment.id"
                                    class="group cursor-pointer transition-all duration-300 hover:shadow-lg hover:border-orange-200 dark:hover:border-orange-800"
                                    @click="goToAssessment(assessment)"
                                >
                                    <CardHeader class="pb-4">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="mb-3 flex items-center gap-3">
                                                    <Badge variant="outline" class="text-xs font-medium">
                                                        <Award class="mr-1 h-3 w-3" />
                                                        Assessment
                                                    </Badge>
                                                    <Badge
                                                        v-if="assessment.is_required"
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
                                                </div>
                                                <CardTitle class="text-xl mb-2 transition-colors group-hover:text-orange-600 dark:group-hover:text-orange-400">
                                                    {{ assessment.title }}
                                                </CardTitle>
                                                <CardDescription class="text-base">
                                                    Passing score: {{ assessment.passing_score }}%
                                                </CardDescription>
                                            </div>

                                            <div class="ml-6 flex items-center gap-2">
                                                <ChevronRight class="h-6 w-6 text-muted-foreground transition-colors group-hover:text-orange-600 dark:group-hover:text-orange-400" />
                                            </div>
                                        </div>
                                    </CardHeader>
                                </Card>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-8">
                            <!-- Module Stats -->
                            <Card class="border-0 shadow-lg">
                                <CardHeader>
                                    <CardTitle class="text-xl">Module Overview</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Total Lessons</span>
                                        <span class="font-semibold">{{ sortedLessons.length }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Completed</span>
                                        <span class="font-semibold text-green-600">{{ completedLessonsCount }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Remaining</span>
                                        <span class="font-semibold">{{ sortedLessons.length - completedLessonsCount }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Duration</span>
                                        <span class="font-semibold">{{ formatDuration(module.estimated_duration) }}</span>
                                    </div>
                                    <div v-if="module.assessments && module.assessments.length > 0" class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Assessments</span>
                                        <span class="font-semibold">{{ module.assessments.length }}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Lesson Types Breakdown -->
                            <Card v-if="sortedLessons.length > 0" class="border-0 shadow-lg">
                                <CardHeader>
                                    <CardTitle class="text-xl">Lesson Types</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        v-for="type in ['text', 'video', 'interactive']"
                                        :key="type"
                                        class="flex justify-between text-sm"
                                    >
                                        <div class="flex items-center gap-2">
                                            <component
                                                :is="getLessonIcon(type)"
                                                class="h-4 w-4"
                                            />
                                            <span class="text-muted-foreground">
                                                {{ type.charAt(0).toUpperCase() + type.slice(1) }}
                                            </span>
                                        </div>
                                        <span class="font-semibold">
                                            {{ sortedLessons.filter((l) => l.lesson_type === type).length }}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Course Navigation -->
                            <Card class="border-0 shadow-lg">
                                <CardHeader>
                                    <CardTitle class="text-xl">Course Navigation</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Button
                                        :as="Link"
                                        :href="`/courses/${module.course.slug}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <BookOpen class="mr-2 h-4 w-4" />
                                        Back to Course Overview
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
