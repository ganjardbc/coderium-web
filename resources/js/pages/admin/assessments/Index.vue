<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, FileCheck, Clock, Users, Eye, Edit, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Assessment {
    id: number;
    title: string;
    description: string;
    assessable_type: string;
    assessable?: {
        id: number;
        title: string;
    };
    time_limit: number | null;
    passing_score: number;
    max_attempts: number;
    is_required: boolean;
    questions_count: number;
    attempts_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    assessments: {
        data?: Assessment[];
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
        required?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Assessments', href: '/admin/assessments' },
];

const deleteAssessment = (assessment: Assessment) => {
    if (confirm(`Are you sure you want to delete "${assessment.title}"?`)) {
        router.delete(`/admin/assessments/${assessment.id}`, {
            preserveScroll: true,
        });
    }
};

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'No limit';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const columns: Column<Assessment>[] = [
    { key: 'title', label: 'Assessment', align: 'left' },
    { key: 'assessable', label: 'Attached To', align: 'left' },
    { key: 'questions_count', label: 'Questions', align: 'left' },
    { key: 'time_limit', label: 'Time Limit', align: 'left' },
    { key: 'passing_score', label: 'Pass Score', align: 'left' },
    { key: 'attempts_count', label: 'Attempts', align: 'left' },
    { key: 'is_required', label: 'Required', align: 'left' },
];

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/admin/assessments', {
        search: query,
        type: props.filters?.type,
        required: props.filters?.required,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleClearSearch = () => {
    router.get('/admin/assessments', {
        type: props.filters?.type,
        required: props.filters?.required,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Assessments - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Assessment Management</h1>
                    <p class="text-muted-foreground">
                        Manage assessments and quizzes for modules
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/assessments/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Assessment
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search assessments by title or description..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.assessments.data || []"
                :columns="columns"
                :pagination="props.assessments.current_page ? {
                    current_page: props.assessments.current_page,
                    last_page: props.assessments.last_page || 1,
                    per_page: props.assessments.per_page || 15,
                    links: props.assessments.links || []
                } : undefined"
                empty-message="No assessments found"
                empty-description="Create your first assessment to test student knowledge."
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description }}
                        </div>
                    </div>
                </template>

                <template #cell-assessable="{ row }">
                    <div v-if="row.assessable" class="min-w-[228px] text-sm">
                        <div class="font-medium">{{ row.assessable.title }}</div>
                        <div class="text-muted-foreground capitalize">
                            {{ row.assessable_type.replace('App\\Models\\', '').toLowerCase() }}
                        </div>
                    </div>
                    <div v-else class="min-w-[228px] text-sm text-muted-foreground">
                        Standalone assessment
                    </div>
                </template>

                <template #cell-questions_count="{ row }">
                    <div class="flex items-center gap-1">
                        <FileCheck class="h-4 w-4" />
                        {{ row.questions_count }}
                    </div>
                </template>

                <template #cell-time_limit="{ row }">
                    <div class="min-w-[128px] flex items-center gap-1">
                        <Clock class="h-4 w-4" />
                        {{ formatDuration(row.time_limit) }}
                    </div>
                </template>

                <template #cell-passing_score="{ row }">
                    <div class="min-w-[128px]">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ row.passing_score }}%
                        </span>
                    </div>
                </template>

                <template #cell-attempts_count="{ row }">
                    <div class="flex items-center gap-1">
                        <Users class="h-4 w-4" />
                        {{ row.attempts_count }}
                    </div>
                </template>

                <template #cell-is_required="{ row }">
                    <span
                        :class="row.is_required
                            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                    >
                        {{ row.is_required ? 'Required' : 'Optional' }}
                    </span>
                </template>

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex gap-2">
                        <Link :href="`/admin/assessments/${row.id}`">
                            <Button variant="outline">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/assessments/${row.id}/edit`">
                            <Button variant="outline">
                                <Edit class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="outline" @click="deleteAssessment(row)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
