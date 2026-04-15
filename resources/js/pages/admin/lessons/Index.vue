<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Clock, Eye, Edit, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Lesson {
    id: number;
    title: string;
    content: string;
    lesson_type: string;
    order_index: number;
    estimated_duration: number;
    is_published: boolean;
    module?: {
        id: number;
        title: string;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    lessons: {
        data?: Lesson[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total?: number;
        from?: number | null;
        to?: number | null;
    };
    filters?: {
        search?: string;
        type?: string;
        status?: string;
        module?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Lessons', href: '/admin/lessons' },
];

const deleteLesson = (lesson: Lesson) => {
    if (confirm(`Are you sure you want to delete "${lesson.title}"?`)) {
        router.delete(`/admin/lessons/${lesson.id}`, {
            preserveScroll: true,
        });
    }
};

const formatDuration = (minutes: number) => {
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const columns: Column<Lesson>[] = [
    { key: 'title', label: 'Lesson', align: 'left' },
    { key: 'module', label: 'Module', align: 'left' },
    { key: 'lesson_type', label: 'Type', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/admin/lessons', {
        search: query,
        type: props.filters?.type,
        status: props.filters?.status,
        module: props.filters?.module,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleClearSearch = () => {
    router.get('/admin/lessons', {
        type: props.filters?.type,
        status: props.filters?.status,
        module: props.filters?.module,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Lessons - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Lesson Management</h1>
                    <p class="text-muted-foreground">
                        Manage lessons across all modules
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/lessons/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Lesson
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search lessons by title or content..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.lessons.data || []"
                :columns="columns"
                :pagination="props.lessons.current_page ? {
                    current_page: props.lessons.current_page,
                    last_page: props.lessons.last_page || 1,
                    per_page: props.lessons.per_page || 15,
                    links: props.lessons.links || []
                } : undefined"
                empty-message="No lessons found"
                empty-description="Create your first lesson to get started with content creation."
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.content.substring(0, 100) }}{{ row.content.length > 100 ? '...' : '' }}
                        </div>
                    </div>
                </template>

                <template #cell-module="{ row }">
                    <div v-if="row.module" class="min-w-[228px] text-sm">
                        <Link
                            :href="`/admin/modules/${row.module.id}`"
                            class="font-medium text-primary hover:underline"
                        >
                            {{ row.module.title }}
                        </Link>
                    </div>
                    <div v-else class="min-w-[228px] text-sm text-muted-foreground">
                        No module assigned
                    </div>
                </template>

                <template #cell-lesson_type="{ row }">
                    <span
                        :class="{
                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': row.lesson_type === 'text',
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': row.lesson_type === 'video',
                            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200': row.lesson_type === 'interactive'
                        }"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                    >
                        {{ row.lesson_type }}
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

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex gap-2">
                        <Link :href="`/admin/lessons/${row.id}`">
                            <Button variant="outline">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/lessons/${row.id}/edit`">
                            <Button variant="outline">
                                <Edit class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="outline" @click="deleteLesson(row)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
