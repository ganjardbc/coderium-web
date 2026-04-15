<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, BookOpen, Clock, Users, GraduationCap, Eye, Edit, Settings, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Course {
    id: number;
    title: string;
    description: string;
    slug: string;
    is_active: boolean;
    estimated_duration: number | null;
    modules_count: number;
    enrollments_count: number;
    certificate_template?: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    courses: {
        data: Course[];
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
    { title: 'Courses', href: '/admin/courses' },
];

const deleteCourse = (course: Course) => {
    if (confirm(`Are you sure you want to delete "${course.title}"?`)) {
        router.delete(`/admin/courses/${course.id}`, {
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

const columns: Column<Course>[] = [
    { key: 'title', label: 'Course', align: 'left' },
    { key: 'modules_count', label: 'Modules', align: 'left' },
    { key: 'enrollments_count', label: 'Enrollments', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'is_active', label: 'Status', align: 'left' },
];

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/admin/courses', {
        search: query,
        status: props.filters?.status,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleClearSearch = () => {
    router.get('/admin/courses', {
        status: props.filters?.status,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Courses - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Course Management</h1>
                    <p class="text-muted-foreground">
                        Create and manage courses with modular content
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/courses/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Course
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search courses by title or description..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.courses.data || []"
                :columns="columns"
                :pagination="props.courses"
                empty-message="No courses found"
                empty-description="Create your first course to get started with structured learning paths."
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[200px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description }}
                        </div>
                        <div v-if="row.certificate_template" class="flex items-center gap-1 mt-1">
                            <GraduationCap class="h-3 w-3 text-muted-foreground" />
                            <span class="text-xs text-muted-foreground">{{ row.certificate_template.name }}</span>
                        </div>
                    </div>
                </template>

                <template #cell-modules_count="{ row }">
                    <div class="flex items-center gap-1">
                        <BookOpen class="h-4 w-4" />
                        {{ row.modules_count }}
                    </div>
                </template>

                <template #cell-enrollments_count="{ row }">
                    <div class="flex items-center gap-1">
                        <Users class="h-4 w-4" />
                        {{ row.enrollments_count }}
                    </div>
                </template>

                <template #cell-estimated_duration="{ row }">
                    <div class="flex items-center gap-1">
                        <Clock class="h-4 w-4" />
                        {{ formatDuration(row.estimated_duration) }}
                    </div>
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        :class="row.is_active
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                    >
                        {{ row.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </template>

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex gap-2">
                        <Link :href="`/admin/courses/${row.id}`">
                            <Button variant="outline">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/courses/${row.id}/edit`">
                            <Button variant="outline">
                                <Edit class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/courses/${row.id}/modules`">
                            <Button variant="outline">
                                <Settings class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="outline" @click="deleteCourse(row)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
