<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Action, type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, BookOpen } from 'lucide-vue-next';
import { ref } from 'vue';

interface Module {
    id: number;
    title: string;
    description: string;
    order_index: number;
    estimated_duration: number | null;
    is_published: boolean;
    lessons_count: number;
    level: {
        id: number;
        title: string;
        track: {
            id: number;
            title: string;
            slug: string;
        };
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    modules: {
        data?: Module[];
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
    { title: 'Modules', href: '/admin/classroom/modules' },
];

const deleteModule = (module: Module) => {
    if (confirm(`Are you sure you want to delete "${module.title}"?`)) {
        router.delete(`/admin/classroom/modules/${module.id}`, {
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

const columns: Column<Module>[] = [
    { key: 'title', label: 'Module', align: 'left' },
    { key: 'track_level', label: 'Track / Level', align: 'left' },
    { key: 'order_index', label: 'Position', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'lessons_count', label: 'Lessons', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const actions: Action<Module>[] = [
    {
        href: (module) => `/admin/classroom/modules/${module.id}/edit`,
        variant: 'outline',
    },
    {
        onClick: (module) => deleteModule(module),
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
    <Head title="Modules - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Modules</h1>
                    <p class="text-muted-foreground">
                        Manage learning modules across all tracks
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/classroom/modules/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Module
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search modules..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.modules.data || []"
                :columns="columns"
                :actions="actions"
                :pagination="props.modules"
                empty-message="No modules found"
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description }}
                        </div>
                    </div>
                </template>

                <template #cell-track_level="{ row }">
                    <div class="min-w-[228px] text-sm">
                        <div class="font-medium">{{ row.level.track.title }}</div>
                        <div class="text-muted-foreground">{{ row.level.title }}</div>
                    </div>
                </template>

                <template #cell-order_index="{ row }">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                        #{{ row.order_index }}
                    </span>
                </template>

                <template #cell-estimated_duration="{ row }">
                    {{ formatDuration(row.estimated_duration) }}
                </template>

                <template #cell-lessons_count="{ row }">
                    <div class="flex items-center gap-1">
                        <BookOpen class="h-4 w-4" />
                        {{ row.lessons_count }}
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
