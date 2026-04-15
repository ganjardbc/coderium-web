<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, TrashIcon, EditIcon, PlusIcon, EyeIcon } from 'lucide-vue-next';
import { ref } from 'vue';

interface Module {
    id: number;
    title: string;
    description: string;
    estimated_duration: number | null;
    is_published: boolean;
    lessons_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    modules: {
        data: Module[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters?: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Modules', href: '/admin/modules' },
];

const deleteModule = (module: Module) => {
    if (confirm(`Are you sure you want to delete "${module.title}"?`)) {
        router.delete(`/admin/modules/${module.id}`, {
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
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'lessons_count', label: 'Lessons', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/admin/modules', {
        search: query,
        status: props.filters?.status,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleClearSearch = () => {
    router.get('/admin/modules', {
        status: props.filters?.status,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Modules - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Module Management</h1>
                    <p class="text-muted-foreground">
                        Manage learning modules across all tracks and levels
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/modules/create">
                        <PlusIcon class="h-4 w-4" />
                        Create Module
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search modules by title or description..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <!-- Debug info - remove this after testing -->
            <DataTable
                :data="props.modules.data || []"
                :columns="columns"
                :pagination="props.modules"
                empty-message="No modules found"
                empty-description="Create your first module to get started with course content."
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description }}
                        </div>
                    </div>
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

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex gap-2">
                        <Link :href="`/admin/modules/${row.id}`">
                            <Button variant="outline">
                                <EyeIcon class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/modules/${row.id}/edit`">
                            <Button variant="outline">
                                <EditIcon class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="outline" @click="deleteModule(row)">
                            <TrashIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
