<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Action, type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Layers } from 'lucide-vue-next';
import { ref } from 'vue';

interface Level {
    id: number;
    title: string;
    description: string;
    difficulty: 'beginner' | 'intermediate' | 'advanced';
    order_index: number;
    is_published: boolean;
    modules_count: number;
    track: {
        id: number;
        title: string;
        slug: string;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    levels: {
        data?: Level[];
        current_page?: number;
        last_page?: number;
        per_page?: number;
        total?: number;
        links?: any[];
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Levels', href: '/admin/classroom/levels' },
];

const deleteLevel = (level: Level) => {
    if (confirm(`Are you sure you want to delete "${level.title}"?`)) {
        router.delete(`/admin/classroom/levels/${level.id}`, {
            preserveScroll: true,
        });
    }
};

const getDifficultyColor = (difficulty: string) => {
    switch (difficulty) {
        case 'beginner': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'intermediate': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'advanced': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

const columns: Column<Level>[] = [
    { key: 'title', label: 'Level', align: 'left' },
    { key: 'track', label: 'Track', align: 'left' },
    { key: 'difficulty', label: 'Difficulty', align: 'left' },
    { key: 'order_index', label: 'Position', align: 'left' },
    { key: 'modules_count', label: 'Modules', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const actions: Action<Level>[] = [
    {
        href: (level) => `/admin/classroom/levels/${level.id}/edit`,
        variant: 'outline',
    },
    {
        onClick: (level) => deleteLevel(level),
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
    <Head title="Levels - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Levels</h1>
                    <p class="text-muted-foreground">
                        Manage learning levels across all tracks
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/classroom/levels/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Level
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search levels..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.levels.data || []"
                :columns="columns"
                :actions="actions"
                :pagination="props.levels"
                empty-message="No levels found"
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description }}
                        </div>
                    </div>
                </template>

                <template #cell-track="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.track.title }}</div>
                    </div>
                </template>

                <template #cell-difficulty="{ row }">
                    <span
                        :class="getDifficultyColor(row.difficulty)"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                    >
                        {{ row.difficulty }}
                    </span>
                </template>

                <template #cell-order_index="{ row }">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                        #{{ row.order_index }}
                    </span>
                </template>

                <template #cell-modules_count="{ row }">
                    <div class="flex items-center gap-1">
                        <Layers class="h-4 w-4" />
                        {{ row.modules_count }}
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
