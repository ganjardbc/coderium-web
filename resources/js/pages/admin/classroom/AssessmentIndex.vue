<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Action, type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, FileQuestion, Clock } from 'lucide-vue-next';
import { ref } from 'vue';

interface Assessment {
    id: number;
    title: string;
    description: string | null;
    assessable_type: string;
    assessable_id: number;
    passing_score: number;
    max_attempts: number;
    time_limit: number | null;
    is_required: boolean;
    questions_count: number;
    assessable?: {
        id: number;
        title: string;
        [key: string]: any;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    assessments: {
        data?: Assessment[];
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
    { title: 'Assessments', href: '/admin/classroom/assessments' },
];

const deleteAssessment = (assessment: Assessment) => {
    if (confirm(`Are you sure you want to delete "${assessment.title}"?`)) {
        router.delete(`/admin/classroom/assessments/${assessment.id}`, {
            preserveScroll: true,
        });
    }
};

const formatTimeLimit = (minutes: number | null) => {
    if (!minutes) return 'No limit';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const getAssessableTypeLabel = (type: string) => {
    switch (type) {
        case 'App\\Models\\Module': return 'Module';
        case 'App\\Models\\Lesson': return 'Lesson';
        default: return type;
    }
};

const columns: Column<Assessment>[] = [
    { key: 'title', label: 'Assessment', align: 'left' },
    { key: 'assessable', label: 'Attached To', align: 'left' },
    { key: 'passing_score', label: 'Passing Score', align: 'left' },
    { key: 'time_limit', label: 'Time Limit', align: 'left' },
    { key: 'questions_count', label: 'Questions', align: 'left' },
    { key: 'is_required', label: 'Required', align: 'left' },
];

const actions: Action<Assessment>[] = [
    {
        href: (assessment) => `/admin/classroom/assessments/${assessment.id}/edit`,
        variant: 'outline',
    },
    {
        onClick: (assessment) => deleteAssessment(assessment),
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
    <Head title="Assessments - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Assessments</h1>
                    <p class="text-muted-foreground">
                        Manage assessments and quizzes across all content
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/classroom/assessments/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Assessment
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search assessments..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.assessments.data || []"
                :columns="columns"
                :actions="actions"
                :pagination="props.assessments"
                empty-message="No assessments found"
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div v-if="row.description" class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description }}
                        </div>
                    </div>
                </template>

                <template #cell-assessable="{ row }">
                    <div class="min-w-[228px] text-sm">
                        <div class="font-medium">{{ getAssessableTypeLabel(row.assessable_type) }}</div>
                        <div v-if="row.assessable" class="text-muted-foreground">
                            {{ row.assessable.title }}
                        </div>
                    </div>
                </template>

                <template #cell-passing_score="{ row }">
                    <div class="min-w-[128px]">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ row.passing_score }}%
                        </span>
                    </div>
                </template>

                <template #cell-time_limit="{ row }">
                    <div class="min-w-[128px] flex items-center gap-1">
                        <Clock class="h-4 w-4" />
                        {{ formatTimeLimit(row.time_limit) }}
                    </div>
                </template>

                <template #cell-questions_count="{ row }">
                    <div class="flex items-center gap-1">
                        <FileQuestion class="h-4 w-4" />
                        {{ row.questions_count }}
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
            </DataTable>
        </div>
    </AppLayout>
</template>
