<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useApi } from '@/composables/useApi';
import { globalLoading } from '@/composables/useLoading';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { BreadcrumbItem, Lesson } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Award,
    BookOpen,
    CheckCircle,
    Clock,
    FileText,
    Play,
    Target,
    Video,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    lesson: Lesson;
    breadcrumbs: BreadcrumbItem[];
    nextLesson?: Lesson;
    previousLesson?: Lesson;
    moduleProgress?: {
        completed_lessons: number;
        total_lessons: number;
        progress_percentage: number;
    };
}

const props = defineProps<Props>();

const { api, get } = useApi();
const { isLoading } = globalLoading;

const isCompleting = isLoading('lesson-complete');

const markAsComplete = async () => {
    if (props.lesson.is_completed) return;

    try {
        await api.post(
            `/api/v1/classroom/lessons/${props.lesson.id}/complete`,
            {},
            {
                loadingKey: 'lesson-complete',
                successMessage: 'Lesson completed successfully!',
                showSuccessMessage: true,
            },
        );

        // Refresh the page to get updated progress data
        get(
            `/classroom/lessons/${props.lesson.id}`,
            {},
            {
                errorContext: 'Refresh lesson data',
            },
        );
    } catch {
        // Error is already handled by the API composable
    }
};

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

const goToNextLesson = () => {
    if (props.nextLesson) {
        get(
            `/classroom/lessons/${props.nextLesson.id}`,
            {},
            {
                errorContext: 'Load next lesson',
            },
        );
    }
};

const goToPreviousLesson = () => {
    if (props.previousLesson) {
        get(
            `/classroom/lessons/${props.previousLesson.id}`,
            {},
            {
                errorContext: 'Load previous lesson',
            },
        );
    }
};

// Process lesson content to handle code blocks and formatting
const processedContent = computed(() => {
    if (!props.lesson.content) return '';

    // Basic processing - in a real app, you'd use a proper markdown parser
    return props.lesson.content
        .replace(
            /```(\w+)?\n([\s\S]*?)```/g,
            '<pre class="bg-muted p-4 rounded-lg overflow-x-auto"><code class="language-$1">$2</code></pre>',
        )
        .replace(
            /`([^`]+)`/g,
            '<code class="bg-muted px-1 py-0.5 rounded text-sm">$1</code>',
        )
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/\n\n/g, '</p><p class="mb-4">')
        .replace(/\n/g, '<br>');
});
</script>

<template>
    <Head :title="`${lesson.title} - Classroom`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <div class="container mx-auto max-w-7xl px-4 py-4">
            <Breadcrumbs :items="breadcrumbs" />
        </div>

        <!-- Lesson Content -->
        <section class="py-6 sm:py-8">
            <div class="container mx-auto max-w-7xl px-4">
                <div class="grid grid-cols-1 gap-6 sm:gap-8 lg:grid-cols-4">
                    <!-- Main Content -->
                    <div class="lg:col-span-3">
                        <!-- Lesson Header -->
                        <div class="mb-6 sm:mb-8">
                            <div
                                class="mb-4 flex flex-wrap items-center gap-2 sm:gap-3"
                            >
                                <Badge
                                    :class="
                                        getLessonTypeColor(lesson.lesson_type)
                                    "
                                    class="text-sm"
                                >
                                    <component
                                        :is="getLessonIcon(lesson.lesson_type)"
                                        class="mr-1 h-4 w-4"
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
                                    class="flex items-center gap-1 text-sm text-muted-foreground"
                                >
                                    <Clock class="h-4 w-4" />
                                    <span>{{
                                        formatDuration(
                                            lesson.estimated_duration,
                                        )
                                    }}</span>
                                </div>
                                <CheckCircle
                                    v-if="lesson.is_completed"
                                    class="h-5 w-5 text-green-500"
                                />
                            </div>

                            <h1
                                class="mb-4 text-2xl font-bold sm:text-3xl md:text-4xl"
                            >
                                {{ lesson.title }}
                            </h1>
                        </div>

                        <!-- Lesson Content -->
                        <Card class="mb-6 sm:mb-8">
                            <CardContent class="p-4 sm:p-6 lg:p-8">
                                <!-- Media Content -->
                                <div
                                    v-if="
                                        lesson.media && lesson.media.length > 0
                                    "
                                    class="mb-6 sm:mb-8"
                                >
                                    <div
                                        v-for="media in lesson.media"
                                        :key="media.id"
                                        class="mb-6"
                                    >
                                        <div
                                            v-if="
                                                media.mime_type.startsWith(
                                                    'video/',
                                                )
                                            "
                                            class="aspect-video overflow-hidden rounded-lg bg-black"
                                        >
                                            <video
                                                :src="media.url"
                                                controls
                                                class="h-full w-full"
                                                :poster="
                                                    media.url.replace(
                                                        /\.[^/.]+$/,
                                                        '_thumb.jpg',
                                                    )
                                                "
                                            >
                                                Your browser does not support
                                                the video tag.
                                            </video>
                                        </div>
                                        <div
                                            v-else-if="
                                                media.mime_type.startsWith(
                                                    'image/',
                                                )
                                            "
                                            class="overflow-hidden rounded-lg"
                                        >
                                            <img
                                                :src="media.url"
                                                :alt="media.original_name"
                                                class="h-auto w-full"
                                            />
                                        </div>
                                        <div
                                            v-else-if="
                                                media.mime_type ===
                                                'application/pdf'
                                            "
                                            class="rounded-lg border p-4"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <FileText
                                                    class="h-8 w-8 text-red-500"
                                                />
                                                <div class="flex-1">
                                                    <p class="font-medium">
                                                        {{
                                                            media.original_name
                                                        }}
                                                    </p>
                                                    <p
                                                        class="text-sm text-muted-foreground"
                                                    >
                                                        PDF Document
                                                    </p>
                                                </div>
                                                <Button
                                                    :as="Link"
                                                    :href="media.url"
                                                    target="_blank"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-10 text-sm"
                                                >
                                                    Download
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Text Content -->
                                <div
                                    v-if="lesson.content"
                                    class="prose prose-gray dark:prose-invert max-w-none text-sm sm:text-base"
                                    v-html="processedContent"
                                ></div>

                                <div
                                    v-else
                                    class="py-8 text-center text-muted-foreground"
                                >
                                    <BookOpen class="mx-auto mb-4 h-12 w-12" />
                                    <p>
                                        No content available for this lesson
                                        yet.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Lesson Actions -->
                        <div class="flex flex-col gap-4">
                            <!-- Navigation -->
                            <div
                                class="flex flex-col justify-between gap-3 sm:flex-row"
                            >
                                <div class="flex gap-3">
                                    <Button
                                        v-if="previousLesson"
                                        @click="goToPreviousLesson"
                                        variant="outline"
                                        class="h-12 flex-1 text-base sm:flex-none"
                                    >
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        Previous
                                    </Button>

                                    <Button
                                        v-if="nextLesson"
                                        @click="goToNextLesson"
                                        variant="outline"
                                        class="h-12 flex-1 text-base sm:flex-none"
                                    >
                                        Next
                                        <ArrowRight class="ml-2 h-4 w-4" />
                                    </Button>
                                </div>

                                <!-- Complete Lesson -->
                                <Button
                                    v-if="!lesson.is_completed"
                                    @click="markAsComplete"
                                    :disabled="isCompleting.value"
                                    size="lg"
                                    class="h-12 text-base"
                                >
                                    <CheckCircle class="mr-2 h-4 w-4" />
                                    {{
                                        isCompleting.value
                                            ? 'Completing...'
                                            : 'Mark as Complete'
                                    }}
                                </Button>

                                <div
                                    v-else
                                    class="flex items-center justify-center gap-2 py-3 text-green-600"
                                >
                                    <CheckCircle class="h-5 w-5" />
                                    <span class="font-medium"
                                        >Lesson Completed</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="order-first lg:order-last lg:col-span-1">
                        <div class="space-y-4 sm:space-y-6 lg:sticky lg:top-4">
                            <!-- Module Progress -->
                            <Card>
                                <CardHeader>
                                    <CardTitle
                                        class="flex items-center gap-2 text-base sm:text-lg"
                                    >
                                        <Target class="h-5 w-5" />
                                        Module Progress
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        class="mb-2 flex items-center justify-between text-sm"
                                    >
                                        <span class="text-muted-foreground"
                                            >Progress</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                Math.round(
                                                    moduleProgressPercentage,
                                                )
                                            }}%</span
                                        >
                                    </div>
                                    <Progress
                                        :value="moduleProgressPercentage"
                                        class="mb-4"
                                    />

                                    <div
                                        class="text-center text-xs text-muted-foreground"
                                    >
                                        {{
                                            moduleProgress?.completed_lessons ||
                                            0
                                        }}
                                        of
                                        {{
                                            moduleProgress?.total_lessons || 0
                                        }}
                                        lessons completed
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Lesson Info -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-base sm:text-lg"
                                        >Lesson Details</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Type</span
                                        >
                                        <span class="font-medium">{{
                                            lesson.lesson_type
                                                .charAt(0)
                                                .toUpperCase() +
                                            lesson.lesson_type.slice(1)
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Duration</span
                                        >
                                        <span class="font-medium">{{
                                            formatDuration(
                                                lesson.estimated_duration,
                                            )
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Status</span
                                        >
                                        <span class="font-medium">
                                            {{
                                                lesson.is_completed
                                                    ? 'Completed'
                                                    : 'In Progress'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="
                                            lesson.media &&
                                            lesson.media.length > 0
                                        "
                                        class="flex justify-between"
                                    >
                                        <span class="text-muted-foreground"
                                            >Resources</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                lesson.media.length
                                            }}
                                            files</span
                                        >
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Quick Navigation -->
                            <Card v-if="previousLesson || nextLesson">
                                <CardHeader>
                                    <CardTitle class="text-base sm:text-lg"
                                        >Quick Navigation</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Button
                                        v-if="previousLesson"
                                        @click="goToPreviousLesson"
                                        variant="outline"
                                        class="h-12 w-full justify-start text-sm"
                                    >
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        <span class="truncate">{{
                                            previousLesson.title
                                        }}</span>
                                    </Button>

                                    <Button
                                        v-if="nextLesson"
                                        @click="goToNextLesson"
                                        variant="outline"
                                        class="h-12 w-full justify-start text-sm"
                                    >
                                        <ArrowRight class="mr-2 h-4 w-4" />
                                        <span class="truncate">{{
                                            nextLesson.title
                                        }}</span>
                                    </Button>
                                </CardContent>
                            </Card>

                            <!-- Achievement -->
                            <Card v-if="lesson.is_completed">
                                <CardContent class="p-4 text-center sm:p-6">
                                    <Award
                                        class="mx-auto mb-3 h-12 w-12 text-yellow-500"
                                    />
                                    <h3 class="mb-2 font-semibold">
                                        Well Done!
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        You've completed this lesson. Keep up
                                        the great work!
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>

<style scoped>
/* Custom styles for processed content */
:deep(.prose) {
    color: hsl(var(--foreground));
}

:deep(.prose pre) {
    background-color: hsl(var(--muted));
    color: hsl(var(--foreground));
}

:deep(.prose code) {
    background-color: hsl(var(--muted));
    color: hsl(var(--foreground));
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

:deep(.prose strong) {
    font-weight: 600;
    color: hsl(var(--foreground));
}

:deep(.prose em) {
    font-style: italic;
    color: hsl(var(--foreground));
}
</style>
