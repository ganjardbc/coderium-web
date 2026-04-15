<script setup lang="ts">
import CourseLayout from '@/layouts/CourseLayout.vue';
import PostCarousel from '@/components/PostCarousel.vue';
import PostVideo from '@/components/PostVideo.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    BookOpen,
    ChevronLeft,
    ChevronRight,
    FileText,
    PlayCircle,
    Target,
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

interface Course {
    id: number;
    slug: string;
    title: string;
}

interface CourseLayoutLesson {
    id: number;
    title: string;
    estimated_duration: number;
    lesson_type: 'video' | 'article' | 'text';
    order_index?: number;
    is_completed?: boolean;
}

interface CourseLayoutModule {
    id: number;
    title: string;
    description: string;
    slug: string;
    order: number;
    is_required: boolean;
    lessons_count: number;
    estimated_duration: number;
    lessons?: CourseLayoutLesson[];
}

interface CourseLayoutCourse {
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
    modules: CourseLayoutModule[];
    created_at: string;
    updated_at: string;
    url: string;
}

interface CourseModule {
    id: number;
    title: string;
    lessons?: Array<{
        id: number;
        title: string;
        order_index: number;
        estimated_duration: number;
        lesson_type: string;
        is_completed?: boolean;
    }>;
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
    module: CourseModule;
    course: Course;
    media: Media[];
}

interface BreadcrumbItem {
    title: string;
    url?: string;
}

interface Props {
    courseDetail: CourseLayoutCourse;
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
    return remainingMinutes > 0
        ? `${hours}h ${remainingMinutes}m`
        : `${hours}h`;
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

const courseDetail = computed(() => {
    return props.courseDetail || {};
});

const courseDetailModules = computed(() => {
    return courseDetail.value.modules || [];
});

// Mock data for module lessons (in real app, this would come from the backend)
const moduleLessons = computed(() => {
    return props.lesson?.module.lessons || [];
});

const sortedLessons = computed(() => {
    return moduleLessons.value
        .slice()
        .sort((a, b) => a.order_index - b.order_index);
});

const currentLessonIndex = computed(() => {
    return sortedLessons.value.findIndex(
        (lesson) => lesson?.id === props.lesson?.id,
    );
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

const goToNextLesson = () => {
    if (nextLesson?.value) {
        router.visit(
            `/courses/${props.lesson?.course.slug}/modules/${props.lesson?.module.id}/lessons/${nextLesson?.value.id}`,
        );
    }
};

const goToPreviousLesson = () => {
    if (previousLesson?.value) {
        router.visit(
            `/courses/${props.lesson?.course.slug}/modules/${props.lesson?.module.id}/lessons/${previousLesson?.value.id}`,
        );
    }
};
</script>

<template>
    <Head>
        <title>{{ lesson?.title }} - {{ lesson?.course.title }}</title>
        <meta name="description" :content="lesson?.title" />
    </Head>

    <CourseLayout
        :lesson="lesson"
        :course="courseDetail"
        :modules="courseDetailModules"
    >
        <template #header-prepend>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    class="w-[44px] h-[40px] sm:w-[118px] rounded-full"
                    :disabled="!previousLesson"
                    @click="goToPreviousLesson"
                >
                    <ChevronLeft class="h-5 w-5" />
                    <span class="hidden sm:inline">Previous</span>
                </Button>
                <Button
                    class="w-[44px] h-[40px] sm:w-[118px] rounded-full"
                    :disabled="!nextLesson"
                    @click="goToNextLesson"
                >
                    <span class="hidden sm:inline">Next</span>
                    <ChevronRight class="h-5 w-5" />
                </Button>
            </div>
        </template>

        <div class="flex-1">
            <!-- Lesson Content -->
            <!-- Video Player -->
            <div
                v-if="lesson?.lesson_type === 'video' && lesson?.media.length > 0"
                class="w-full border-b"
            >
                <PostVideo
                    :url="lesson?.media[0].url || ''"
                />
            </div>

            <!-- Media Gallery (if multiple media files) -->
            <div
                v-if="lesson?.media.length > 1"
                class="w-full border-b p-4"
            >
                <PostCarousel
                    :medias="lesson?.media"
                />
            </div>

            <!-- Lesson Information -->
            <div class="space-y-4 border-b p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="mb-2 flex items-center gap-2">
                            <component
                                :is="getLessonTypeIcon(lesson?.lesson_type)"
                                :class="[
                                    'h-5 w-5',
                                    getLessonTypeColor(lesson?.lesson_type),
                                ]"
                            />
                            <span
                                class="text-sm font-medium capitalize text-muted-foreground"
                            >
                                {{ lesson?.lesson_type }}
                            </span>
                            <span class="text-sm text-muted-foreground">•</span>
                            <span class="text-sm text-muted-foreground">
                                {{ formatDuration(lesson?.estimated_duration) }}
                            </span>
                        </div>
                        <h1 class="text-lg font-bold md:text-xl">
                            {{ lesson?.title }}
                        </h1>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ lesson?.module.title }}
                        </p>
                    </div>
                </div>
                <!-- Text Content -->
                <div
                    v-if="lesson?.content"
                    class="prose prose-lg dark:prose-invert max-w-none"
                    v-html="lesson?.content"
                ></div>
            </div>
        </div>
    </CourseLayout>
</template>
