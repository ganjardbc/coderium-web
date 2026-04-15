<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle,
    Clock,
    FileText,
    Play,
    Target,
    Video,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Media {
    id: number;
    original_name: string;
    mime_type: string;
    url: string;
    size: number;
}

interface Course {
    id: number;
    title: string;
    slug: string;
}

interface Module {
    id: number;
    title: string;
}

interface Lesson {
    id: number;
    title: string;
    content: string;
    order_index: number;
    estimated_duration: number;
    lesson_type: string;
    is_completed?: boolean;
    course: Course;
    module: Module;
    media: Media[];
    nextLesson?: {
        id: number;
        title: string;
        module: string;
        url: string;
    };
    previousLesson?: {
        id: number;
        title: string;
        module: string;
        url: string;
    };
}

interface Props {
    lesson: Lesson;
    breadcrumbs: BreadcrumbItem[];
    nextLesson?: {
        id: number;
        title: string;
        module: string;
        url: string;
    };
    previousLesson?: {
        id: number;
        title: string;
        module: string;
        url: string;
    };
    moduleProgress?: {
        completed_lessons: number;
        total_lessons: number;
        progress_percentage: number;
    };
}

const props = defineProps<Props>();

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

const moduleProgressPercentage = computed(() => {
    return props.moduleProgress?.progress_percentage || 0;
});
</script>

<template>
    <Head :title="`${lesson.title} - ${lesson.course.title}`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Lesson Header -->
        <section class="w-full py-8 border-b bg-gradient-to-br from-green-50/50 to-emerald-50/50 dark:from-green-950/20 dark:to-emerald-950/20">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Lesson Info -->
                    <div class="lg:col-span-2">
                        <div class="mb-6 flex items-center gap-3">
                            <Badge
                                :class="getLessonTypeColor(lesson.lesson_type)"
                                class="text-sm"
                            >
                                <component
                                    :is="getLessonIcon(lesson.lesson_type)"
                                    class="mr-1 h-3 w-3"
                                />
                                {{ lesson.lesson_type.charAt(0).toUpperCase() + lesson.lesson_type.slice(1) }} Lesson
                            </Badge>
                            <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                <Clock class="h-4 w-4" />
                                <span>{{ formatDuration(lesson.estimated_duration) }}</span>
                            </div>
                            <CheckCircle
                                v-if="lesson.is_completed"
                                class="h-5 w-5 text-green-500"
                            />
                        </div>

                        <div class="mb-4 space-y-2">
                            <Link
                                :href="`/courses/${lesson.course.slug}`"
                                class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                            >
                                {{ lesson.course.title }}
                            </Link>
                            <div class="text-sm text-muted-foreground">
                                {{ lesson.module.title }}
                            </div>
                        </div>

                        <h1 class="mb-6 text-3xl font-bold leading-tight md:text-4xl">
                            {{ lesson.title }}
                        </h1>

                        <!-- Navigation -->
                        <div class="flex flex-wrap gap-4">
                            <Button
                                v-if="previousLesson"
                                :as="Link"
                                :href="previousLesson.url"
                                variant="outline"
                            >
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Previous Lesson
                            </Button>

                            <Button
                                v-if="nextLesson"
                                :as="Link"
                                :href="nextLesson.url"
                                variant="outline"
                            >
                                Next Lesson
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Progress Card -->
                    <div class="lg:col-span-1">
                        <Card v-if="moduleProgress" class="sticky top-4 shadow-lg border-0 bg-white/80 backdrop-blur-sm dark:bg-gray-900/80">
                            <CardHeader class="pb-4">
                                <CardTitle class="flex items-center gap-2 text-lg">
                                    <Target class="h-5 w-5 text-green-600" />
                                    Module Progress
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="mb-2 flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Progress</span>
                                    <span class="font-bold">{{ Math.round(moduleProgressPercentage) }}%</span>
                                </div>
                                <Progress :value="moduleProgressPercentage" class="mb-4 h-2" />

                                <div class="text-center text-sm text-muted-foreground">
                                    {{ moduleProgress.completed_lessons }} of {{ moduleProgress.total_lessons }} lessons completed
                                </div>

                                <Button
                                    :as="Link"
                                    :href="`/courses/${lesson.course.slug}/modules/${lesson.module.id}`"
                                    variant="outline"
                                    class="w-full"
                                >
                                    <BookOpen class="mr-2 h-4 w-4" />
                                    Back to Module
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lesson Content -->
        <section class="py-12">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2">
                        <!-- Lesson Content -->
                        <div class="prose prose-lg max-w-none dark:prose-invert">
                            <div v-html="lesson.content"></div>
                        </div>

                        <!-- Media Files -->
                        <div v-if="lesson.media && lesson.media.length > 0" class="mt-12">
                            <h3 class="mb-6 text-2xl font-bold">Resources</h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <Card
                                    v-for="media in lesson.media"
                                    :key="media.id"
                                    class="group cursor-pointer transition-all hover:shadow-md"
                                >
                                    <CardContent class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                                <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium truncate">{{ media.original_name }}</p>
                                                <p class="text-sm text-muted-foreground">{{ media.mime_type }}</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="mt-12 flex items-center justify-between border-t pt-8">
                            <div>
                                <Button
                                    v-if="previousLesson"
                                    :as="Link"
                                    :href="previousLesson.url"
                                    variant="outline"
                                    class="flex items-center gap-2"
                                >
                                    <ArrowLeft class="h-4 w-4" />
                                    <div class="text-left">
                                        <div class="text-xs text-muted-foreground">Previous</div>
                                        <div class="font-medium">{{ previousLesson.title }}</div>
                                    </div>
                                </Button>
                            </div>

                            <div>
                                <Button
                                    v-if="nextLesson"
                                    :as="Link"
                                    :href="nextLesson.url"
                                    class="flex items-center gap-2"
                                >
                                    <div class="text-right">
                                        <div class="text-xs text-white/80">Next</div>
                                        <div class="font-medium">{{ nextLesson.title }}</div>
                                    </div>
                                    <ArrowRight class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-6">
                            <!-- Lesson Info -->
                            <Card class="border-0 shadow-lg">
                                <CardHeader>
                                    <CardTitle class="text-lg">Lesson Details</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Type</span>
                                        <Badge :class="getLessonTypeColor(lesson.lesson_type)" class="text-xs">
                                            {{ lesson.lesson_type.charAt(0).toUpperCase() + lesson.lesson_type.slice(1) }}
                                        </Badge>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Duration</span>
                                        <span class="font-medium">{{ formatDuration(lesson.estimated_duration) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Status</span>
                                        <Badge v-if="lesson.is_completed" variant="default" class="text-xs bg-green-600">
                                            <CheckCircle class="mr-1 h-3 w-3" />
                                            Completed
                                        </Badge>
                                        <Badge v-else variant="secondary" class="text-xs">
                                            In Progress
                                        </Badge>
                                    </div>
                                    <div v-if="lesson.media && lesson.media.length > 0" class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Resources</span>
                                        <span class="font-medium">{{ lesson.media.length }}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Quick Navigation -->
                            <Card class="border-0 shadow-lg">
                                <CardHeader>
                                    <CardTitle class="text-lg">Quick Navigation</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Button
                                        :as="Link"
                                        :href="`/courses/${lesson.course.slug}/modules/${lesson.module.id}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <BookOpen class="mr-2 h-4 w-4" />
                                        Back to Module
                                    </Button>

                                    <Button
                                        :as="Link"
                                        :href="`/courses/${lesson.course.slug}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        Course Overview
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
