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
import type { BreadcrumbItem, Lesson, Module } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle,
    ChevronRight,
    Clock,
    FileText,
    Play,
    Target,
    Video,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    module: Module;
    breadcrumbs: BreadcrumbItem[];
    nextModule?: Module;
    previousModule?: Module;
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
    if (!props.module.lessons || props.module.lessons.length === 0) return 0;

    const completedLessons = props.module.lessons.filter(
        (lesson) => lesson.is_completed,
    ).length;
    return (completedLessons / props.module.lessons.length) * 100;
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
        router.visit(`/classroom/lessons/${nextLesson.id}`);
    }
};

const completedLessonsCount = computed(() => {
    return sortedLessons.value.filter((lesson) => lesson.is_completed).length;
});
</script>

<template>
    <Head :title="`${module.title} - Classroom`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Module Header -->
        <section class="w-full py-8 border-b">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Module Info -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 flex items-center gap-3">
                            <Badge variant="outline" class="text-sm">
                                {{ sortedLessons.length }} lessons
                            </Badge>
                            <div
                                class="flex items-center gap-1 text-sm text-muted-foreground"
                            >
                                <Clock class="h-4 w-4" />
                                <span>{{
                                    formatDuration(module.estimated_duration)
                                }}</span>
                            </div>
                        </div>

                        <h1 class="mb-4 text-3xl font-bold md:text-4xl">
                            {{ module.title }}
                        </h1>

                        <p class="mb-6 text-lg text-muted-foreground">
                            {{ module.description }}
                        </p>

                        <!-- Navigation -->
                        <div class="flex flex-wrap gap-4">
                            <Button
                                v-if="previousModule"
                                :as="Link"
                                :href="`/classroom/modules/${previousModule.id}`"
                                variant="outline"
                            >
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Previous Module
                            </Button>

                            <Button
                                v-if="nextModule"
                                :as="Link"
                                :href="`/classroom/modules/${nextModule.id}`"
                                variant="outline"
                            >
                                Next Module
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Progress Card -->
                    <div class="lg:col-span-1">
                        <Card class="sticky top-4">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Target class="h-5 w-5" />
                                    Module Progress
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div
                                    class="mb-2 flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Overall Progress</span
                                    >
                                    <span class="font-medium"
                                        >{{ Math.round(moduleProgress) }}%</span
                                    >
                                </div>
                                <Progress
                                    :value="moduleProgress"
                                    class="mb-4"
                                />

                                <Button
                                    @click="startNextLesson"
                                    :disabled="!getNextLesson()"
                                    class="w-full"
                                    size="lg"
                                >
                                    <Play class="mr-2 h-4 w-4" />
                                    {{
                                        getNextLesson()
                                            ? 'Continue Learning'
                                            : 'Module Complete'
                                    }}
                                </Button>

                                <div
                                    class="text-center text-xs text-muted-foreground"
                                >
                                    {{ completedLessonsCount }} of
                                    {{ sortedLessons.length }} lessons completed
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lessons Content -->
        <section class="py-8">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2">
                        <h2 class="mb-6 text-2xl font-bold">Lessons</h2>

                        <div v-if="sortedLessons.length > 0" class="space-y-4">
                            <Card
                                v-for="(lesson, index) in sortedLessons"
                                :key="lesson.id"
                                class="group cursor-pointer transition-all duration-200 hover:shadow-md"
                                @click="
                                    router.visit(
                                        `/classroom/lessons/${lesson.id}`,
                                    )
                                "
                            >
                                <CardHeader class="pb-3">
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <div class="flex-1">
                                            <div
                                                class="mb-2 flex items-center gap-3"
                                            >
                                                <Badge
                                                    variant="outline"
                                                    class="text-xs"
                                                >
                                                    Lesson {{ index + 1 }}
                                                </Badge>
                                                <Badge
                                                    :class="
                                                        getLessonTypeColor(
                                                            lesson.lesson_type,
                                                        )
                                                    "
                                                    class="text-xs"
                                                >
                                                    <component
                                                        :is="
                                                            getLessonIcon(
                                                                lesson.lesson_type,
                                                            )
                                                        "
                                                        class="mr-1 h-3 w-3"
                                                    />
                                                    {{
                                                        lesson.lesson_type
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                        lesson.lesson_type.slice(
                                                            1,
                                                        )
                                                    }}
                                                </Badge>
                                                <div
                                                    v-if="
                                                        lesson.estimated_duration
                                                    "
                                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    <span>{{
                                                        formatDuration(
                                                            lesson.estimated_duration,
                                                        )
                                                    }}</span>
                                                </div>
                                            </div>
                                            <CardTitle
                                                class="flex items-center gap-2 text-lg transition-colors group-hover:text-primary"
                                            >
                                                {{ lesson.title }}
                                                <CheckCircle
                                                    v-if="lesson.is_completed"
                                                    class="h-5 w-5 text-green-500"
                                                />
                                            </CardTitle>
                                            <CardDescription
                                                class="mt-1 line-clamp-2"
                                            >
                                                {{
                                                    lesson.content
                                                        ? lesson.content.substring(
                                                              0,
                                                              150,
                                                          ) + '...'
                                                        : 'No description available'
                                                }}
                                            </CardDescription>
                                        </div>

                                        <div
                                            class="ml-4 flex items-center gap-2"
                                        >
                                            <ChevronRight
                                                class="h-5 w-5 text-muted-foreground transition-colors group-hover:text-primary"
                                            />
                                        </div>
                                    </div>
                                </CardHeader>
                            </Card>
                        </div>

                        <div v-else class="py-12 text-center">
                            <BookOpen
                                class="mx-auto mb-4 h-16 w-16 text-muted-foreground"
                            />
                            <h3 class="mb-2 text-lg font-semibold">
                                No lessons available
                            </h3>
                            <p class="text-muted-foreground">
                                This module doesn't have any lessons yet.
                            </p>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-6">
                            <!-- Module Stats -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Module Overview</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Total Lessons</span
                                        >
                                        <span class="font-medium">{{
                                            sortedLessons.length
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Completed</span
                                        >
                                        <span class="font-medium">{{
                                            completedLessonsCount
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Remaining</span
                                        >
                                        <span class="font-medium">{{
                                            sortedLessons.length -
                                            completedLessonsCount
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Duration</span
                                        >
                                        <span class="font-medium">{{
                                            formatDuration(
                                                module.estimated_duration,
                                            )
                                        }}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Lesson Types Breakdown -->
                            <Card v-if="sortedLessons.length > 0">
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Lesson Types</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div
                                        v-for="type in [
                                            'text',
                                            'video',
                                            'interactive',
                                        ]"
                                        :key="type"
                                        class="flex justify-between text-sm"
                                    >
                                        <div class="flex items-center gap-2">
                                            <component
                                                :is="getLessonIcon(type)"
                                                class="h-4 w-4"
                                            />
                                            <span
                                                class="text-muted-foreground"
                                                >{{
                                                    type
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    type.slice(1)
                                                }}</span
                                            >
                                        </div>
                                        <span class="font-medium">
                                            {{
                                                sortedLessons.filter(
                                                    (l) =>
                                                        l.lesson_type === type,
                                                ).length
                                            }}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Quick Navigation -->
                            <Card v-if="previousModule || nextModule">
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Quick Navigation</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Button
                                        v-if="previousModule"
                                        :as="Link"
                                        :href="`/classroom/modules/${previousModule.id}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        {{ previousModule.title }}
                                    </Button>

                                    <Button
                                        v-if="nextModule"
                                        :as="Link"
                                        :href="`/classroom/modules/${nextModule.id}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <ArrowRight class="mr-2 h-4 w-4" />
                                        {{ nextModule.title }}
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
