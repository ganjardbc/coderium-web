<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Action, type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Clock } from 'lucide-vue-next';
import { ref } from 'vue';

interface Lesson {
    id: number;
    title: string;
    content: string;
    lesson_type: 'text' | 'video' | 'interactive';
    order_index: number;
    estimated_duration: number | null;
    is_published: boolean;
    module: {
        id: number;
        title: string;
        level: {
            id: number;
            title: string;
            track: {
                id: number;
                title: string;
                slug: string;
            };
        };
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    lessons: {
        data?: Lesson[];
        links?: any[];
        current_page?: number;
        last_page?: number;
        per_page?: number;
        total?: number;
        from?: number | null;
        to?: number | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Lessons', href: '/admin/classroom/lessons' },
];

const deleteLesson = (lesson: Lesson) => {
    if (confirm(`Are you sure you want to delete "${lesson.title}"?`)) {
        router.delete(`/admin/classroom/lessons/${lesson.id}`, {
            preserveScroll: true,
        });
    }
};

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'Not set';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const getLessonTypeColor = (type: string) => {
    switch (type) {
        case 'video': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'interactive': return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 'text': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

const getLessonTypeIcon = (type: string) => {
    switch (type) {
        case 'video': return '🎥';
        case 'interactive': return '🎮';
        case 'text': return '📝';
        default: return '📄';
    }
};

const columns: Column<Lesson>[] = [
    { key: 'title', label: 'Lesson', align: 'left' },
    { key: 'module_info', label: 'Module / Level / Track', align: 'left' },
    { key: 'lesson_type', label: 'Type', align: 'left' },
    { key: 'order_index', label: 'Position', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const actions: Action<Lesson>[] = [
    {
        href: (lesson) => `/admin/classroom/lessons/${lesson.id}/edit`,
        variant: 'outline',
    },
    {
        onClick: (lesson) => deleteLesson(lesson),
        variant: 'outline',
    },
];

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    console.log('handleSearch', query);
};

const handleClearSearch = () => {
    console.log('handleClearSearch');
};
</script>

<template>
    <Head title="Lessons - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Lessons</h1>
                    <p class="text-muted-foreground">
                        Manage learning lessons across all modules
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/classroom/lessons/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Lesson
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search lessons..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.lessons.data || []"
                :columns="columns"
                :actions="actions"
                :pagination="props.lessons"
                empty-message="No lessons found"
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium flex items-center gap-2">
                            <span>{{ getLessonTypeIcon(row.lesson_type) }}</span>
                            {{ row.title }}
                        </div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.content }}
                        </div>
                    </div>
                </template>

                <template #cell-module_info="{ row }">
                    <div class="min-w-[228px] text-sm">
                        <div class="font-medium">{{ row.module.title }}</div>
                        <div class="text-muted-foreground">{{ row.module.level.title }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.module.level.track.title }}</div>
                    </div>
                </template>

                <template #cell-lesson_type="{ row }">
                    <span
                        :class="getLessonTypeColor(row.lesson_type)"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                    >
                        {{ row.lesson_type }}
                    </span>
                </template>

                <template #cell-order_index="{ row }">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                        #{{ row.order_index }}
                    </span>
                </template>

                <template #cell-estimated_duration="{ row }">
                    <div class="flex items-center gap-1">
                        <Clock class="h-4 w-4" />
                        {{ formatDuration(row.estimated_duration) }}
                    </div>
                </template>

                <template #cell-is_published="{ row }">
                    <span
                        :class="row.is_published
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                    >
                        {{ row.is_published ? 'Published' : 'Draft' }}
                    </span>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
