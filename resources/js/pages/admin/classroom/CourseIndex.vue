<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import DataTable from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Course } from '@/types/enhanced-classroom';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Eye, Users, BookOpen, Clock, BarChart3 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    courses: Course[];
    stats: {
        total_courses: number;
        published_courses: number;
        draft_courses: number;
        total_enrollments: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Courses', href: '/admin/classroom/courses' },
];

const formatDuration = (minutes: number) => {
    if (!minutes) return '0m';
    if (minutes >= 60) {
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    }
    return `${minutes}m`;
};

const getDifficultyColor = (difficulty: string) => {
    switch (difficulty) {
        case 'beginner': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'intermediate': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'advanced': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const deleteCourse = (course: Course) => {
    if (confirm(`Are you sure you want to delete the course "${course.title}"? This action cannot be undone.`)) {
        router.delete(`/admin/classroom/courses/${course.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                // Handle success
            },
        });
    }
};

const tableColumns = [
    { key: 'title', label: 'Course Title', sortable: true },
    { key: 'category', label: 'Category', sortable: true },
    { key: 'difficulty', label: 'Difficulty', sortable: true },
    { key: 'moduleCount', label: 'Modules', sortable: true },
    { key: 'enrollmentCount', label: 'Enrollments', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'actions', label: 'Actions', sortable: false },
];

const tableData = computed(() => {
    return props.courses.map(course => ({
        id: course.id,
        title: course.title,
        category: course.category,
        difficulty: course.difficulty,
        moduleCount: course.moduleCount,
        enrollmentCount: course.enrollmentCount,
        status: course.isPublished ? 'Published' : 'Draft',
        estimatedDuration: course.estimatedDuration,
        rating: course.rating,
        course: course,
    }));
});

interface Action<T> {
    label: string;
    href?: (item: T) => string;
    onClick?: (item: T) => void;
    variant?: 'default' | 'outline' | 'destructive';
    icon?: any;
}

const actions: Action<any>[] = [
    {
        label: 'View',
        href: (course) => `/courses/${course.id}`,
        variant: 'outline',
        icon: Eye,
    },
    {
        label: 'Edit',
        href: (course) => `/admin/classroom/courses/${course.id}/edit`,
        variant: 'outline',
        icon: Edit,
    },
    {
        label: 'Delete',
        onClick: (course) => deleteCourse(course.course),
        variant: 'destructive',
        icon: Trash2,
    },
];
</script>

<template>
    <Head title="Courses - Classroom Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Course Management</h1>
                    <p class="text-muted-foreground">
                        Manage courses with flexible module assignments
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/classroom/courses/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Course
                    </Link>
                </Button>
            </div>

            <!-- Enhanced Stats Overview -->
            <div class="mb-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Courses</CardTitle>
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_courses }}</div>
                        <p class="text-xs text-muted-foreground">All courses</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Published</CardTitle>
                        <Eye class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ stats.published_courses }}</div>
                        <p class="text-xs text-muted-foreground">Live courses</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Drafts</CardTitle>
                        <Edit class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-yellow-600">{{ stats.draft_courses }}</div>
                        <p class="text-xs text-muted-foreground">In development</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Enrollments</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_enrollments }}</div>
                        <p class="text-xs text-muted-foreground">Across all courses</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Enhanced Course Table -->
            <Card>
                <CardHeader>
                    <CardTitle>All Courses</CardTitle>
                    <CardDescription>
                        Manage your course catalog with unified module assignments
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :columns="tableColumns"
                        :data="tableData"
                        :actions="actions"
                        searchable
                        :search-placeholder="'Search courses...'"
                    >
                        <template #cell-title="{ item }">
                            <div class="flex flex-col">
                                <span class="font-medium">{{ item.title }}</span>
                                <span class="text-sm text-muted-foreground">{{ item.course.description?.substring(0, 60) }}...</span>
                            </div>
                        </template>

                        <template #cell-difficulty="{ item }">
                            <Badge :class="getDifficultyColor(item.difficulty)" class="text-xs">
                                {{ item.difficulty.charAt(0).toUpperCase() + item.difficulty.slice(1) }}
                            </Badge>
                        </template>

                        <template #cell-moduleCount="{ item }">
                            <div class="flex items-center gap-1">
                                <BookOpen class="h-4 w-4 text-muted-foreground" />
                                <span>{{ item.moduleCount }}</span>
                            </div>
                        </template>

                        <template #cell-enrollmentCount="{ item }">
                            <div class="flex items-center gap-1">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                <span>{{ item.enrollmentCount }}</span>
                            </div>
                        </template>

                        <template #cell-status="{ item }">
                            <Badge :variant="item.status === 'Published' ? 'default' : 'secondary'">
                                {{ item.status }}
                            </Badge>
                        </template>

                        <template #expanded-row="{ item }">
                            <div class="p-4 bg-muted/50 rounded-lg">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <h4 class="font-medium mb-2">Course Details</h4>
                                        <div class="space-y-1 text-sm">
                                            <div class="flex items-center gap-2">
                                                <Clock class="h-4 w-4 text-muted-foreground" />
                                                <span>{{ formatDuration(item.estimatedDuration) }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <BarChart3 class="h-4 w-4 text-muted-foreground" />
                                                <span>{{ item.rating?.toFixed(1) || 'No rating' }} rating</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="font-medium mb-2">Module Assignments</h4>
                                        <div class="text-sm text-muted-foreground">
                                            <p>{{ item.course.moduleAssignments?.length || 0 }} modules assigned</p>
                                            <p>{{ item.course.moduleAssignments?.filter(a => a.isRequired).length || 0 }} required</p>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="font-medium mb-2">Quick Actions</h4>
                                        <div class="flex gap-2">
                                            <Button size="sm" variant="outline" as-child>
                                                <Link :href="`/admin/classroom/courses/${item.id}/assignments`">
                                                    Manage Assignments
                                                </Link>
                                            </Button>
                                            <Button size="sm" variant="outline" as-child>
                                                <Link :href="`/admin/classroom/courses/${item.id}/analytics`">
                                                    View Analytics
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </DataTable>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
