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
    ChevronLeft,
    BarChart3,
    Layers,
    Download,
    ArrowLeft,
    ArrowRight,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Media {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    url: string;
}

interface ModuleLesson {
    id: number;
    title: string;
    order_index: number;
    estimated_duration: number;
    lesson_type: string;
    is_completed?: boolean;
}

interface Module {
    id: number;
    title: string;
    lessons?: ModuleLesson[];
}

interface Course {
    id: number;
    title: string;
    slug: string;
}

interface Lesson {
    id: number;
    title: string;
    content: string;
    lesson_type: string;
    order_index: number;
    estimated_duration: number;
    is_published: boolean;
    is_completed?: boolean;
    module: Module;
    course: Course;
    media: Media[];
}

interface BreadcrumbItem {
    title: string;
    url?: string;
}

interface Props {
    lesson: Lesson;
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

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

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

// Mock data for module lessons (in real app, this would come from the backend)
const moduleLessons = computed(() => {
    return props.lesson.module.lessons || [];
});

const sortedLessons = computed(() => {
    return moduleLessons.value
        .slice()
        .sort((a, b) => a.order_index - b.order_index);
});

const completedLessons = computed(() => {
    return sortedLessons.value.filter(lesson => lesson.is_completed).length;
});

const totalLessons = computed(() => {
    return sortedLessons.value.length;
});

const currentLessonIndex = computed(() => {
    return sortedLessons.value.findIndex(lesson => lesson.id === props.lesson.id);
});

const nextLesson = computed(() => {
    const currentIndex = currentLessonIndex.value;
    if (currentIndex >= 0 && currentIndex < sortedLessons.value.length - 1) {
        return sortedLessons.value[currentIndex + 1];
    }
    return null;
});

const previousLesson = computed(() => {
    const currentIndex = currentLessonIndex.value;
    if (currentIndex > 0) {
        return sortedLessons.value[currentIndex - 1];
    }
    return null;
});

const markAsComplete = () => {
    // TODO: Implement lesson completion logic
    console.log('Mark lesson as complete');
};

const goToNextLesson = () => {
    if (nextLesson.value) {
        router.visit(`/courses/${props.lesson.course.slug}/modules/${props.lesson.module.id}/lessons/${nextLesson.value.id}`);
    }
};

const goToPreviousLesson = () => {
    if (previousLesson.value) {
        router.visit(`/courses/${props.lesson.course.slug}/modules/${props.lesson.module.id}/lessons/${previousLesson.value.id}`);
    }
};
</script>

<template>
    <Head>
        <title>{{ lesson.title }} - {{ lesson.course.title }}</title>
        <meta name="description" :content="lesson.title" />
    </Head>

    <FrontLayout>
        <!-- Breadcrumbs -->
        <div class="border-b bg-gray-50/50 py-4 dark:bg-gray-900/20">
            <div class="container mx-auto px-4">
                <Breadcrumbs :breadcrumbs="breadcrumbs" :is-back="true" />
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="min-h-screen bg-background">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-0">
                <!-- Main Content (Left Side) -->
                <div class="lg:col-span-8 border-r">
                    <!-- Lesson Header -->
                    <div class="border-b bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20 p-6 lg:p-8">
                        <div class="space-y-6">
                            <!-- Lesson Info -->
                            <div>
                                <div class="mb-4 flex items-center gap-3">
                                    <Badge variant="outline">
                                        <component
                                            :is="getLessonTypeIcon(lesson.lesson_type)"
                                            class="mr-1 h-3 w-3"
                                            :class="getLessonTypeColor(lesson.lesson_type)"
                                        />
                                        Lesson {{ lesson.order_index }}
                                    </Badge>
                                    <Badge variant="outline">
                                        <Layers class="mr-1 h-3 w-3" />
                                        {{ lesson.lesson_type }}
                                    </Badge>
                                    <Badge v-if="lesson.is_completed" variant="default" class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        <CheckCircle class="mr-1 h-3 w-3" />
                                        Completed
                                    </Badge>
                                </div>

                                <h1 class="mb-4 text-3xl font-bold leading-tight lg:text-4xl">
                                    {{ lesson.title }}
                                </h1>
                            </div>

                            <!-- Lesson Stats -->
                            <div class="flex flex-wrap gap-6 text-sm">
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                        <Clock class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <span class="font-medium">{{ formatDuration(lesson.estimated_duration) }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                        <component
                                            :is="getLessonTypeIcon(lesson.lesson_type)"
                                            class="h-4 w-4 text-green-600 dark:text-green-400"
                                        />
                                    </div>
                                    <span class="font-medium capitalize">{{ lesson.lesson_type }} lesson</span>
                                </div>
                                <div v-if="lesson.media.length > 0" class="flex items-center gap-2 text-muted-foreground">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                        <Download class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <span class="font-medium">{{ lesson.media.length }} {{ lesson.media.length === 1 ? 'resource' : 'resources' }}</span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-4">
                                <Button
                                    v-if="!lesson.is_completed"
                                    @click="markAsComplete"
                                    class="h-12 text-lg font-semibold bg-green-600 hover:bg-green-700"
                                    size="lg"
                                >
                                    <CheckCircle class="mr-2 h-5 w-5" />
                                    Mark as Complete
                                </Button>
                                <Button
                                    v-if="previousLesson"
                                    @click="goToPreviousLesson"
                                    variant="outline"
                                    size="lg"
                                >
                                    <ChevronLeft class="mr-2 h-5 w-5" />
                                    Previous
                                </Button>
                                <Button
                                    v-if="nextLesson"
                                    @click="goToNextLesson"
                                    class="h-12 text-lg font-semibold bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                                    size="lg"
                                >
                                    Next Lesson
                                    <ChevronRight class="ml-2 h-5 w-5" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Lesson Content -->
                    <div class="p-6 lg:p-8">
                        <div class="space-y-8">
                            <!-- Main Lesson Content -->
                            <Card>
                                <CardContent class="p-8">
                                    <div class="prose prose-lg max-w-none dark:prose-invert" v-html="lesson.content"></div>
                                </CardContent>
                            </Card>

                            <!-- Media Resources -->
                            <Card v-if="lesson.media.length > 0">
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2">
                                        <Download class="h-5 w-5 text-blue-600" />
                                        Resources & Downloads
                                    </CardTitle>
                                    <CardDescription>
                                        Additional materials for this lesson
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-4">
                                        <div
                                            v-for="media in lesson.media"
                                            :key="media.id"
                                            class="flex items-center justify-between rounded-lg border p-4 hover:bg-gray-50 dark:hover:bg-gray-900/50"
                                        >
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                    <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold">{{ media.name }}</h4>
                                                    <p class="text-sm text-muted-foreground">
                                                        {{ media.mime_type }} • {{ formatFileSize(media.size) }}
                                                    </p>
                                                </div>
                                            </div>
                                            <Button variant="outline" size="sm" :as="'a'" :href="media.url" target="_blank">
                                                <Download class="mr-1 h-4 w-4" />
                                                Download
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Navigation -->
                            <div class="flex items-center justify-between pt-6 border-t">
                                <Button
                                    v-if="previousLesson"
                                    @click="goToPreviousLesson"
                                    variant="outline"
                                    size="lg"
                                >
                                    <ArrowLeft class="mr-2 h-4 w-4" />
                                    {{ previousLesson.title }}
                                </Button>
                                <div v-else></div>

                                <Button
                                    v-if="nextLesson"
                                    @click="goToNextLesson"
                                    size="lg"
                                >
                                    {{ nextLesson.title }}
                                    <ArrowRight class="ml-2 h-4 w-4" />
                                </Button>
                                <div v-else></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar (Right Side) -->
                <div class="lg:col-span-4 bg-gray-50/50 dark:bg-gray-900/20">
                    <div class="sticky top-0 h-screen overflow-y-auto">
                        <!-- Module Navigation Header -->
                        <div class="border-b bg-white/80 backdrop-blur-sm p-6 dark:bg-gray-900/80">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-lg">{{ lesson.module.title }}</h3>
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
                                    v-for="(moduleLesson, index) in sortedLessons"
                                    :key="moduleLesson.id"
                                    class="group cursor-pointer rounded-lg border p-4 transition-all hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800"
                                    :class="{
                                        'bg-blue-50 border-blue-200 dark:bg-blue-950/30 dark:border-blue-800': moduleLesson.id === lesson.id,
                                        'bg-white dark:bg-gray-900': moduleLesson.id !== lesson.id
                                    }"
                                    @click="() => router.visit(`/courses/${lesson.course.slug}/modules/${lesson.module.id}/lessons/${moduleLesson.id}`)"
                                >
                                    <div class="flex items-start gap-3">
                                        <!-- Lesson Status Icon -->
                                        <div class="flex-shrink-0 mt-1">
                                            <div
                                                v-if="moduleLesson.is_completed"
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
                                            >
                                                <CheckCircle class="h-4 w-4 text-green-600 dark:text-green-400" />
                                            </div>
                                            <div
                                                v-else-if="moduleLesson.id === lesson.id"
                                                class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                                            >
                                                <Play class="h-4 w-4 text-blue-600 dark:text-blue-400" />
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
                                                    <h4 class="font-medium text-sm leading-tight transition-colors"
                                                        :class="{
                                                            'text-blue-600 dark:text-blue-400': moduleLesson.id === lesson.id,
                                                            'group-hover:text-blue-600 dark:group-hover:text-blue-400': moduleLesson.id !== lesson.id
                                                        }"
                                                    >
                                                        {{ moduleLesson.title }}
                                                    </h4>
                                                    <div class="flex items-center gap-3 mt-2 text-xs text-muted-foreground">
                                                        <div class="flex items-center gap-1">
                                                            <component
                                                                :is="getLessonTypeIcon(moduleLesson.lesson_type)"
                                                                class="h-3 w-3"
                                                                :class="getLessonTypeColor(moduleLesson.lesson_type)"
                                                            />
                                                            <span class="capitalize">{{ moduleLesson.lesson_type }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <Clock class="h-3 w-3" />
                                                            <span>{{ formatDuration(moduleLesson.estimated_duration) }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Play Button -->
                                                <div class="flex-shrink-0">
                                                    <div v-if="moduleLesson.id !== lesson.id" class="opacity-0 group-hover:opacity-100 transition-opacity">
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

                            <!-- Lesson Stats -->
                            <div v-if="totalLessons > 0" class="mt-8 pt-6 border-t">
                                <Card class="border-0 shadow-sm bg-gradient-to-br from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20">
                                    <CardHeader class="pb-3">
                                        <CardTitle class="flex items-center gap-2 text-base">
                                            <BarChart3 class="h-4 w-4 text-blue-600" />
                                            Lesson Progress
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <div class="space-y-3 text-sm">
                                            <div class="flex justify-between items-center">
                                                <span class="text-muted-foreground">Current Lesson</span>
                                                <span class="font-semibold">{{ currentLessonIndex + 1 }} of {{ totalLessons }}</span>
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
                                                <span class="font-semibold">{{ formatDuration(lesson.estimated_duration) }}</span>
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
    </FrontLayout>
</template>
