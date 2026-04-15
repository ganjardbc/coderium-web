<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Button } from '@/components/ui/button';
import {
    BookOpen,
    ChevronDown,
    Circle,
    FileText,
    PanelRightOpen,
    PanelRightClose,
    PlayCircle,
    X,
    BookOpenCheck,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Lesson {
    id: number;
    title: string;
    estimated_duration: number;
    lesson_type: 'video' | 'article' | 'text';
}

interface Assessment {
    id: number;
    title: string;
    passing_score: string;
    max_attempts: number;
    time_limit: number;
    is_required: boolean;
}

interface Module {
    id: number;
    title: string;
    description?: string;
    slug?: string;
    order?: number;
    is_required?: boolean;
    lessons_count?: number;
    estimated_duration?: number;
    lessons?: Lesson[];
    assessments?: Assessment[];
}

interface CertificateTemplate {
    id: number;
    name: string;
    description: string;
}

interface Course {
    id: number;
    slug: string;
    title: string;
    description?: string;
    estimated_duration?: number;
    is_active?: boolean;
    modules_count?: number;
    enrollments_count?: number;
    certificate_template?: CertificateTemplate;
    modules?: Module[];
    created_at?: string;
    updated_at?: string;
    url?: string;
}

interface Lesson {
    id: number;
    title: string;
}

interface Props {
    lesson?: Lesson;
    course?: Course;
    modules?: Module[];
}

const props = defineProps<Props>();
const showSidebar = ref(false);

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

const sortedModules = computed(() => {
    return (
        props.modules?.slice().sort((a: any, b: any) => a.order - b.order) || []
    );
});
</script>

<template>
    <FrontLayout>
        <!-- Course Header -->
        <div
            class="container mx-auto grid grid-cols-1 xl:grid-cols-[1fr_380px]"
        >
            <div class="flex-1 transition-all">
                <!-- Back Button -->
                <BackButton>
                    <!-- Hide/Show Sidebar -->
                    <template #append>
                        <div class="flex gap-4 items-center justify-end">
                            <slot name="header-prepend" />
                            <Button
                                size="lg"
                                variant="outline"
                                class="w-[44px] h-[44px] xl:hidden rounded-full"
                                @click="showSidebar = !showSidebar"
                            >
                                <PanelRightOpen v-if="!showSidebar" class="w-5 h-5" />
                                <PanelRightClose v-else-if="showSidebar" class="w-5 h-5" />
                            </Button>
                            <slot name="header-append" />
                        </div>
                    </template>
                </BackButton>

                <!-- Course Info -->
                <slot />
            </div>

            <!-- Course Curicullum -->
            <div
                class="fixed z-100 top-0 w-full -right-full bg-black/50 xl:bg-transparent xl:right-0 xl:z-0 xl:sticky flex justify-end transition-all"
                :class="{
                    'right-0': showSidebar,
                    '-right-full': !showSidebar,
                }"
            >
                <div
                    class="sticky top-0 right-0 w-full md:w-[380px] h-screen overflow-y-auto border-l-none sm:border-l bg-white dark:bg-black"
                >
                    <div
                        class="sticky top-0 z-10 h-16 border-b bg-white p-4 dark:bg-black flex justify-between items-center"
                    >
                        <h1 class="lg flex items-center gap-2 font-bold">
                            Curicullums
                        </h1>
                        <Button
                            size="lg"
                            variant="ghost"
                            class="xl:hidden rounded-full !px-3"
                            @click="showSidebar = !showSidebar"
                        >
                            <X class="w-5 h-5" />
                        </Button>
                    </div>

                    <!-- Modules -->
                    <div class="w-full">
                        <div v-for="(module, i) in sortedModules" :key="module?.id || i">
                            <div
                                class="flex items-center justify-between gap-2 border-b p-4"
                            >
                                <div class="flex items-center gap-2">
                                    <h1 class="text-sm font-semibold">
                                        {{ module?.title }}
                                    </h1>
                                </div>
                                <ChevronDown
                                    class="h-4 w-4 cursor-pointer text-orange-600 dark:text-orange-400"
                                />
                            </div>

                            <!-- Module Lessons -->
                            <div
                                class="w-full"
                                :class="{
                                    'border-b': i !== (sortedModules.length - 1)
                                }"
                            >
                                <a
                                    v-for="moduleLesson in module?.lessons"
                                    :key="moduleLesson?.id"
                                    :href="`/courses/${course?.slug}/modules/${module?.id}/lessons/${moduleLesson?.id}`"
                                    class="block cursor-pointer border-l-4 border-transparent px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-900"
                                    :class="{
                                        '!border-primary': moduleLesson?.id === lesson?.id,
                                    }"
                                >
                                    <div class="flex gap-4">
                                        <Circle
                                            class="mt-1 h-4 w-4 text-blue-500"
                                        />
                                        <div class="flex-1 space-y-0.5">
                                            <h2 class="text-sm font-semibold">
                                                {{ moduleLesson?.title }}
                                            </h2>
                                            <span
                                                class="text-sm font-medium text-gray-400"
                                                >{{
                                                    formatDuration(
                                                        moduleLesson?.estimated_duration,
                                                    )
                                                }}
                                                {{ moduleLesson?.lesson_type }}</span
                                            >
                                        </div>
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                                        >
                                            <PlayCircle
                                                v-if="
                                                    moduleLesson?.lesson_type ===
                                                    'video'
                                                "
                                                class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                            />
                                            <FileText
                                                v-else-if="
                                                    moduleLesson?.lesson_type ===
                                                    'article'
                                                "
                                                class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                            />
                                            <BookOpen
                                                v-else
                                                class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                            />
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Module Assessments -->
                            <div
                                class="w-full"
                                :class="{
                                    'border-b': i !== (sortedModules.length - 1)
                                }"
                            >
                                <a
                                    v-for="moduleAssessment in module?.assessments"
                                    :key="moduleAssessment?.id"
                                    :href="`/courses/${course?.slug}/modules/${module?.id}/assessments/${moduleAssessment?.id}`"
                                    class="block cursor-pointer border-l-4 border-transparent px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-900"
                                    :class="{
                                        '!border-primary': moduleAssessment?.id === lesson?.id,
                                    }"
                                >
                                    <div class="flex gap-4">
                                        <Circle
                                            class="mt-1 h-4 w-4 text-red-500"
                                        />
                                        <div class="flex-1 space-y-0.5">
                                            <h2 class="text-sm font-semibold">
                                                {{ moduleAssessment?.title }}
                                            </h2>
                                            <span
                                                class="text-sm font-medium text-gray-400"
                                                >{{
                                                    formatDuration(
                                                        moduleAssessment?.time_limit,
                                                    )
                                                }} assessment</span
                                            >
                                        </div>
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                                        >
                                            <BookOpenCheck
                                                class="h-4 w-4 text-red-600 dark:text-red-400"
                                            />
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </FrontLayout>
</template>
