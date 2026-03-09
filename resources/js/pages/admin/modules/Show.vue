<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import {
    Edit,
    Plus,
    BookOpen,
    Clock,
    CheckCircle,
    XCircle,
    EditIcon,
    TrashIcon,
    FileCheck,
    Users,
    Target,
    EyeIcon,
} from 'lucide-vue-next';

interface Lesson {
    id: number;
    title: string;
    description: string;
    order_index: number;
    estimated_duration: number | null;
    is_published: boolean;
    lesson_type: string;
    created_at: string;
}

interface Assessment {
    id: number;
    title: string;
    description: string;
    passing_score: number;
    max_attempts: number;
    time_limit: number | null;
    is_required: boolean;
    questions_count: number;
    attempts_count: number;
    created_at: string;
}

interface Module {
    id: number;
    title: string;
    description: string;
    estimated_duration: number | null;
    is_published: boolean;
    lessons_count: number;
    assessments_count: number;
    lessons: Lesson[];
    assessments: Assessment[];
    created_at: string;
    updated_at: string;
}

interface Props {
    module: Module;
}

const props = defineProps<Props>();

// Tab state management with query parameters
const activeTab = ref<string>('overview');

// Get initial tab from URL query parameter
const getInitialTab = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const validTabs = ['overview', 'lessons', 'assessments'];
    return validTabs.includes(tabParam || '') ? tabParam! : 'overview';
};

// Update URL when tab changes
const updateUrlTab = (tab: string) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
};

// Handle tab change
const handleTabChange = (tab: string) => {
    activeTab.value = tab;
    updateUrlTab(tab);
};

// Initialize tab from URL on mount
onMounted(() => {
    activeTab.value = getInitialTab();
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Modules', href: '/admin/modules' },
    { title: props.module.title, href: '#' },
];

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'Not set';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const deleteLesson = (lesson: Lesson) => {
    if (confirm(`Are you sure you want to delete "${lesson.title}"?`)) {
        router.delete(`/admin/lessons/${lesson.id}`, {
            preserveScroll: true,
        });
    }
};

const deleteAssessment = (assessment: Assessment) => {
    if (confirm(`Are you sure you want to delete "${assessment.title}"?`)) {
        router.delete(`/admin/assessments/${assessment.id}`, {
            preserveScroll: true,
        });
    }
};

const lessonColumns: Column<Lesson>[] = [
    { key: 'title', label: 'Lesson', align: 'left' },
    { key: 'lesson_type', label: 'Type', align: 'left' },
    { key: 'order_index', label: 'Position', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const assessmentColumns: Column<Assessment>[] = [
    { key: 'title', label: 'Assessment', align: 'left' },
    { key: 'questions_count', label: 'Questions', align: 'left' },
    { key: 'passing_score', label: 'Pass Score', align: 'left' },
    { key: 'time_limit', label: 'Time Limit', align: 'left' },
    { key: 'attempts_count', label: 'Attempts', align: 'left' },
    { key: 'is_required', label: 'Required', align: 'left' },
];

const totalDuration = (props.module.lessons || [])?.reduce((total, lesson) => {
    return total + (lesson.estimated_duration || 0);
}, 0);

const publishedLessons = (props.module.lessons || [])?.filter(lesson => lesson.is_published).length;

// Lesson type statistics
const lessonTypeStats = (props.module.lessons || [])?.reduce((stats, lesson) => {
    const type = lesson.lesson_type || 'Unknown';
    stats[type] = (stats[type] || 0) + 1;
    return stats;
}, {} as Record<string, number>);

// Average lesson duration
const averageLessonDuration = totalDuration > 0 && props.module.lessons_count > 0
    ? Math.round(totalDuration / props.module.lessons_count)
    : 0;
</script>

<template>
    <Head :title="`${module.title} - Modules - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ module.title }}</h1>
                        <Badge :variant="module.is_published ? 'default' : 'secondary'">
                            {{ module.is_published ? 'Published' : 'Draft' }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground">
                        Standalone learning module
                    </p>
                </div>
                <Button as-child>
                    <Link :href="`/admin/modules/${module.id}/edit`">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Module
                    </Link>
                </Button>
            </div>

            <Tabs :model-value="activeTab" @update:model-value="handleTabChange" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="lessons">Lessons</TabsTrigger>
                    <TabsTrigger value="assessments">Assessments</TabsTrigger>
                </TabsList>

                <!-- Modules Overview -->
                <TabsContent value="overview" class="space-y-4">
                    <!-- Overview Statistics -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <BookOpen class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Total Lessons</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ module.lessons_count }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ publishedLessons }} published
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <FileCheck class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Assessments</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ module.assessments_count || 0 }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ (module.assessments || []).filter(a => a.is_required).length }} required
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Total Duration</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ formatDuration(totalDuration) }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        Estimated time
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <CheckCircle class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Status</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ module.is_published ? 'Live' : 'Draft' }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ module.is_published ? 'Available to students' : 'Not published yet' }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Module Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Module Information</CardTitle>
                            <CardDescription>
                                Details about this learning module
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h3 class="font-medium mb-2">Description</h3>
                                <p class="text-muted-foreground">
                                    {{ module.description || 'No description provided.' }}
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <h3 class="font-medium mb-2">Created</h3>
                                    <p class="text-muted-foreground">
                                        {{ new Date(module.created_at).toLocaleDateString() }}
                                    </p>
                                </div>
                                <div>
                                    <h3 class="font-medium mb-2">Last Updated</h3>
                                    <p class="text-muted-foreground">
                                        {{ new Date(module.updated_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Content Breakdown -->
                    <div v-if="module.lessons && module.lessons.length > 0" class="grid gap-4 md:grid-cols-2">
                        <!-- Lesson Types -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Lesson Types</CardTitle>
                                <CardDescription>
                                    Content distribution by type
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div
                                        v-for="(count, type) in lessonTypeStats"
                                        :key="type"
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Badge variant="outline">{{ type }}</Badge>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold">{{ count }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ Math.round((count / module.lessons_count) * 100) }}%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Publication Status -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Publication Status</CardTitle>
                                <CardDescription>
                                    Content readiness overview
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle class="h-4 w-4 text-green-600" />
                                            <span class="text-sm">Published</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold">{{ publishedLessons }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ publishedLessons > 0 ? Math.round((publishedLessons / module.lessons_count) * 100) : 0 }}%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <XCircle class="h-4 w-4 text-gray-400" />
                                            <span class="text-sm">Draft</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold">{{ module.lessons_count - publishedLessons }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ module.lessons_count > 0 ? Math.round(((module.lessons_count - publishedLessons) / module.lessons_count) * 100) : 0 }}%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Modules Lessons -->
                <TabsContent value="lessons" class="space-y-4">
                    <!-- Lessons Statistics -->
                    <div v-if="module.lessons && module.lessons.length > 0" class="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <BookOpen class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Total Lessons</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ module.lessons_count }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        In this module
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <CheckCircle class="h-4 w-4 text-green-600" />
                                    <span class="text-sm font-medium truncate">Published</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ publishedLessons }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ publishedLessons > 0 ? Math.round((publishedLessons / module.lessons_count) * 100) : 0 }}% of total
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <XCircle class="h-4 w-4 text-gray-400" />
                                    <span class="text-sm font-medium truncate">Draft</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ module.lessons_count - publishedLessons }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ module.lessons_count > 0 ? Math.round(((module.lessons_count - publishedLessons) / module.lessons_count) * 100) : 0 }}% of total
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Avg Duration</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ formatDuration(averageLessonDuration) }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        Per lesson
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Lesson Type Breakdown -->
                    <Card v-if="module.lessons && module.lessons.length > 0 && Object.keys(lessonTypeStats).length > 0">
                        <CardHeader>
                            <CardTitle class="text-lg">Lesson Types</CardTitle>
                            <CardDescription>
                                Breakdown of lessons by type
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="(count, type) in lessonTypeStats"
                                    :key="type"
                                    class="flex items-center justify-between p-3 rounded-lg border"
                                >
                                    <div class="flex items-center gap-2">
                                        <Badge variant="outline">{{ type }}</Badge>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold">{{ count }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ Math.round((count / module.lessons_count) * 100) }}%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Lessons</CardTitle>
                                    <CardDescription>
                                        Manage lessons within this module
                                    </CardDescription>
                                </div>
                                <Button as-child size="sm">
                                    <Link :href="`/admin/lessons/create?module_id=${module.id}`">
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add Lesson
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                :data="module.lessons"
                                :columns="lessonColumns"
                                empty-message="No lessons found"
                                empty-description="Create your first lesson to add content to this module."
                            >
                                <template #cell-title="{ row }">
                                    <div class="min-w-[200px]">
                                        <div class="font-medium">{{ row.title }}</div>
                                        <div class="text-sm text-muted-foreground line-clamp-1">
                                            {{ row.description }}
                                        </div>
                                    </div>
                                </template>

                                <template #cell-lesson_type="{ row }">
                                    <Badge variant="outline">
                                        {{ row.lesson_type }}
                                    </Badge>
                                </template>

                                <template #cell-order_index="{ row }">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                        #{{ row.order_index }}
                                    </span>
                                </template>

                                <template #cell-estimated_duration="{ row }">
                                    {{ formatDuration(row.estimated_duration) }}
                                </template>

                                <template #cell-is_published="{ row }">
                                    <div class="flex items-center gap-1">
                                        <CheckCircle v-if="row.is_published" class="h-4 w-4 text-green-600" />
                                        <XCircle v-else class="h-4 w-4 text-gray-400" />
                                        <span class="text-sm">
                                            {{ row.is_published ? 'Published' : 'Draft' }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Actions -->
                                <template #actions="{ row }">
                                    <div class="flex gap-2 justify-end">
                                        <Link :href="`/admin/lessons/${row.id}`">
                                            <Button variant="outline">
                                                <EyeIcon class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Link :href="`/admin/lessons/${row.id}/edit`">
                                            <Button variant="outline">
                                                <EditIcon class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button variant="outline" @click="deleteLesson(row)">
                                            <TrashIcon class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </template>
                            </DataTable>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Modules Assessments -->
                <TabsContent value="assessments" class="space-y-4">
                    <!-- Assessment Statistics -->
                    <div v-if="module.assessments && module.assessments.length > 0" class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <FileCheck class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Total Questions</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">
                                        {{ module.assessments.reduce((sum, assessment) => sum + (assessment.questions_count || 0), 0) }}
                                    </div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        Across all assessments
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Total Attempts</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">
                                        {{ module.assessments.reduce((sum, assessment) => sum + (assessment.attempts_count || 0), 0) }}
                                    </div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        Student attempts
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <Target class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Average Pass Score</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">
                                        {{ module.assessments.length > 0 ? Math.round(module.assessments.reduce((sum, assessment) => sum + assessment.passing_score, 0) / module.assessments.length) : 0 }}%
                                    </div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        Required to pass
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Assessments</CardTitle>
                                    <CardDescription>
                                        Manage assessments for this module
                                    </CardDescription>
                                </div>
                                <Button as-child size="sm">
                                    <Link :href="`/admin/assessments/create?module_id=${module.id}`">
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add Assessment
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                :data="module.assessments || []"
                                :columns="assessmentColumns"
                                empty-message="No assessments found"
                                empty-description="Create your first assessment to test student knowledge in this module."
                            >
                                <template #cell-title="{ row }">
                                    <div class="min-w-[200px]">
                                        <div class="font-medium">{{ row.title }}</div>
                                        <div class="text-sm text-muted-foreground line-clamp-1">
                                            {{ row.description }}
                                        </div>
                                    </div>
                                </template>

                                <template #cell-questions_count="{ row }">
                                    <div class="flex items-center gap-1">
                                        <FileCheck class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ row.questions_count || 0 }}</span>
                                    </div>
                                </template>

                                <template #cell-passing_score="{ row }">
                                    <div class="flex items-center gap-1">
                                        <Target class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ row.passing_score }}%</span>
                                    </div>
                                </template>

                                <template #cell-time_limit="{ row }">
                                    <div class="min-w-[92px] flex items-center gap-1">
                                        <Clock class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ formatDuration(row.time_limit) }}</span>
                                    </div>
                                </template>

                                <template #cell-attempts_count="{ row }">
                                    <div class="flex items-center gap-1">
                                        <Users class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ row.attempts_count || 0 }}</span>
                                    </div>
                                </template>

                                <template #cell-is_required="{ row }">
                                    <div class="flex items-center gap-1">
                                        <CheckCircle v-if="row.is_required" class="h-4 w-4 text-red-600" />
                                        <XCircle v-else class="h-4 w-4 text-gray-400" />
                                        <span class="text-sm">
                                            {{ row.is_required ? 'Required' : 'Optional' }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Actions -->
                                <template #actions="{ row }">
                                    <div class="flex gap-2 justify-end">
                                        <Link :href="`/admin/assessments/${row.id}`">
                                            <Button variant="outline">
                                                <EyeIcon class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Link :href="`/admin/assessments/${row.id}/edit`">
                                            <Button variant="outline">
                                                <EditIcon class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button variant="outline" @click="deleteAssessment(row)">
                                            <TrashIcon class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </template>
                            </DataTable>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
