<script setup lang="ts">
import DataTable, {
    type Action,
    type Column,
} from '@/components/DataTable.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import CustomSelect from '@/components/CustomSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Award, BookOpen, Download, TrendingUp, Users } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface TrackProgress {
    id: number;
    title: string;
    slug: string;
    total_enrollments: number;
    active_learners: number;
    completion_rate: number;
    average_progress: number;
    certificates_issued: number;
}

interface LearnerProgress {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
        avatar?: string;
    };
    track: {
        id: number;
        title: string;
        slug: string;
    };
    enrolled_at: string;
    progress_percentage: number;
    completed_at?: string;
    last_activity: string;
    lessons_completed: number;
    total_lessons: number;
    assessments_passed: number;
    total_assessments: number;
}

interface AnalyticsData {
    total_tracks: number;
    total_enrollments: number;
    total_completions: number;
    average_completion_rate: number;
    certificates_issued: number;
    active_learners_this_month: number;
}

interface Props {
    tracks: {
        data: TrackProgress[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    learners: {
        data: LearnerProgress[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    analytics: AnalyticsData;
    filters?: {
        search?: string;
        track_id?: number;
        status?: string;
    };
    availableTracks: Array<{ id: number; title: string }>;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');
const selectedTrack = ref(props.filters?.track_id || null);
const selectedStatus = ref(props.filters?.status || '');
const activeTab = ref<'overview' | 'tracks' | 'learners'>('overview');

const trackOptions = computed(() => [
    { value: null, label: 'All tracks' },
    ...(props.availableTracks || []).map(track => ({
        value: track.id,
        label: track.title
    }))
]);

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'not_started', label: 'Not Started' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
];

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Progress Dashboard', href: '/admin/classroom/progress' },
];

const handleSearch = (query: string) => {
    router.get(
        '/admin/classroom/progress',
        {
            search: query || undefined,
            track_id: selectedTrack.value || undefined,
            status: selectedStatus.value || undefined,
            tab: activeTab.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const handleFilterChange = () => {
    router.get(
        '/admin/classroom/progress',
        {
            search: searchQuery.value || undefined,
            track_id: selectedTrack.value || undefined,
            status: selectedStatus.value || undefined,
            tab: activeTab.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedTrack.value = null;
    selectedStatus.value = '';
    router.get(
        '/admin/classroom/progress',
        { tab: activeTab.value },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const exportData = (type: 'tracks' | 'learners') => {
    const params = new URLSearchParams({
        export: type,
        search: searchQuery.value || '',
        track_id: selectedTrack.value?.toString() || '',
        status: selectedStatus.value || '',
    });

    window.open(
        `/admin/classroom/progress/export?${params.toString()}`,
        '_blank',
    );
};

// Track columns
const trackColumns: Column<TrackProgress>[] = [
    { key: 'title', label: 'Track', align: 'left' },
    { key: 'enrollments', label: 'Enrollments', align: 'center' },
    { key: 'progress', label: 'Avg Progress', align: 'center' },
    { key: 'completion_rate', label: 'Completion Rate', align: 'center' },
    { key: 'certificates', label: 'Certificates', align: 'center' },
];

const trackActions: Action<TrackProgress>[] = [
    {
        label: 'View Details',
        href: (track) => `/admin/classroom/tracks/${track.id}/analytics`,
        variant: 'outline',
    },
];

// Learner columns
const learnerColumns: Column<LearnerProgress>[] = [
    { key: 'user', label: 'Learner', align: 'left' },
    { key: 'track', label: 'Track', align: 'left' },
    { key: 'progress', label: 'Progress', align: 'center' },
    { key: 'status', label: 'Status', align: 'center' },
    { key: 'last_activity', label: 'Last Activity', align: 'center' },
];

const learnerActions: Action<LearnerProgress>[] = [
    {
        label: 'View Profile',
        href: (learner) => `/admin/users/${learner.user.id}`,
        variant: 'outline',
    },
    {
        label: 'Generate Certificate',
        onClick: (learner) => generateCertificate(learner),
        variant: 'outline',
        show: (learner) =>
            learner.progress_percentage === 100 && !learner.completed_at,
    },
];

const generateCertificate = (learner: LearnerProgress) => {
    if (confirm(`Generate certificate for ${learner.user.name}?`)) {
        router.post(
            `/admin/classroom/certificates/generate`,
            {
                user_id: learner.user.id,
                track_id: learner.track.id,
            },
            {
                preserveScroll: true,
            },
        );
    }
};

const getStatusBadge = (learner: LearnerProgress) => {
    if (learner.completed_at) {
        return { text: 'Completed', variant: 'default' as const };
    } else if (learner.progress_percentage > 0) {
        return { text: 'In Progress', variant: 'secondary' as const };
    } else {
        return { text: 'Not Started', variant: 'outline' as const };
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const formatPercentage = (value: number) => {
    return `${Math.round(value)}%`;
};
</script>

<template>
    <Head title="Progress Dashboard - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Progress Dashboard</h1>
                <p class="text-muted-foreground">
                    Monitor learner progress and track performance across all
                    classroom content
                </p>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab">
                <TabsList>
                    <TabsTrigger value="overview">
                        Overview
                    </TabsTrigger>
                    <TabsTrigger value="tracks">
                        Track Performance
                    </TabsTrigger>
                    <TabsTrigger value="learners">
                        Learner Progress
                    </TabsTrigger>
                </TabsList>

                <!-- Overview Tab -->
                <TabsContent value="overview" class="space-y-6">
                    <!-- Analytics Cards -->
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card>
                            <CardHeader
                                class="flex flex-row items-center justify-between space-y-0 pb-2"
                            >
                                <CardTitle class="text-sm font-medium"
                                    >Total Tracks</CardTitle
                                >
                                <BookOpen class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">
                                    {{ analytics?.total_tracks }}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader
                                class="flex flex-row items-center justify-between space-y-0 pb-2"
                            >
                                <CardTitle class="text-sm font-medium"
                                    >Total Enrollments</CardTitle
                                >
                                <Users class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">
                                    {{ analytics?.total_enrollments }}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader
                                class="flex flex-row items-center justify-between space-y-0 pb-2"
                            >
                                <CardTitle class="text-sm font-medium"
                                    >Completions</CardTitle
                                >
                                <Award class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">
                                    {{ analytics?.total_completions }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        formatPercentage(
                                            analytics?.average_completion_rate,
                                        )
                                    }}
                                    completion rate
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader
                                class="flex flex-row items-center justify-between space-y-0 pb-2"
                            >
                                <CardTitle class="text-sm font-medium"
                                    >Certificates Issued</CardTitle
                                >
                                <Award class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">
                                    {{ analytics?.certificates_issued }}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader
                                class="flex flex-row items-center justify-between space-y-0 pb-2"
                            >
                                <CardTitle class="text-sm font-medium"
                                    >Active This Month</CardTitle
                                >
                                <TrendingUp class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">
                                    {{ analytics?.active_learners_this_month }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Learners with activity
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Quick Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Actions</CardTitle>
                            <CardDescription>
                                Common administrative tasks
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-4">
                                <Button
                                    @click="exportData('tracks')"
                                    variant="outline"
                                >
                                    <Download class="mr-2 h-4 w-4" />
                                    Export Track Data
                                </Button>
                                <Button
                                    @click="exportData('learners')"
                                    variant="outline"
                                >
                                    <Download class="mr-2 h-4 w-4" />
                                    Export Learner Data
                                </Button>
                                <Button
                                    @click="
                                        router.visit(
                                            '/admin/classroom/certificates',
                                        )
                                    "
                                    variant="outline"
                                >
                                    <Award class="mr-2 h-4 w-4" />
                                    Manage Certificates
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Track Performance Tab -->
                <TabsContent value="tracks" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold">Track Performance</h2>
                            <p class="text-muted-foreground">
                                Monitor how each track is performing
                            </p>
                        </div>
                        <Button @click="exportData('tracks')" variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Export Data
                        </Button>
                    </div>

                    <DataTable
                        :data="tracks?.data || []"
                        :columns="trackColumns"
                        :actions="trackActions"
                        :pagination="tracks"
                        empty-message="No tracks found"
                    >
                        <!-- Track Title Cell -->
                        <template #cell-title="{ row }">
                            <div>
                                <p class="font-medium">{{ row.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ row.slug }}
                                </p>
                            </div>
                        </template>

                        <!-- Enrollments Cell -->
                        <template #cell-enrollments="{ row }">
                            <div class="text-center">
                                <p class="font-medium">
                                    {{ row.total_enrollments }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ row.active_learners }} active
                                </p>
                            </div>
                        </template>

                        <!-- Progress Cell -->
                        <template #cell-progress="{ row }">
                            <div class="w-24">
                                <Progress
                                    :value="row.average_progress"
                                    class="mb-1"
                                />
                                <p class="text-center text-xs">
                                    {{ formatPercentage(row.average_progress) }}
                                </p>
                            </div>
                        </template>

                        <!-- Completion Rate Cell -->
                        <template #cell-completion_rate="{ row }">
                            <div class="text-center">
                                <p class="font-medium">
                                    {{ formatPercentage(row.completion_rate) }}
                                </p>
                            </div>
                        </template>

                        <!-- Certificates Cell -->
                        <template #cell-certificates="{ row }">
                            <div class="text-center">
                                <Badge variant="secondary">{{
                                    row.certificates_issued
                                }}</Badge>
                            </div>
                        </template>
                    </DataTable>
                </TabsContent>

                <!-- Learner Progress Tab -->
                <TabsContent value="learners" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold">Learner Progress</h2>
                            <p class="text-muted-foreground">
                                Track individual learner progress
                            </p>
                        </div>
                        <Button @click="exportData('learners')" variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Export Data
                        </Button>
                    </div>

                    <!-- Filters -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="space-y-2">
                                    <Label>Search</Label>
                                    <Searchbar
                                        v-model="searchQuery"
                                        placeholder="Search learners?..."
                                        @search="handleSearch"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <CustomSelect
                                        label="Track"
                                        v-model="selectedTrack"
                                        :options="trackOptions"
                                        placeholder="All tracks"
                                        @update:modelValue="handleFilterChange"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <CustomSelect
                                        label="Status"
                                        v-model="selectedStatus"
                                        :options="statusOptions"
                                        placeholder="All statuses"
                                        @update:modelValue="handleFilterChange"
                                    />
                                </div>

                                <div class="flex items-end">
                                    <Button
                                        variant="outline"
                                        @click="clearFilters"
                                        class="w-full"
                                    >
                                        Clear Filters
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <DataTable
                        :data="learners?.data || []"
                        :columns="learnerColumns"
                        :actions="learnerActions"
                        :pagination="learners"
                        empty-message="No learners found"
                    >
                        <!-- User Cell -->
                        <template #cell-user="{ row }">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-muted"
                                >
                                    <span class="text-sm font-medium">
                                        {{ row.user.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-medium">{{ row.user.name }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ row.user.email }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Track Cell -->
                        <template #cell-track="{ row }">
                            <div>
                                <p class="font-medium">{{ row.track.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    Enrolled {{ formatDate(row.enrolled_at) }}
                                </p>
                            </div>
                        </template>

                        <!-- Progress Cell -->
                        <template #cell-progress="{ row }">
                            <div class="w-32">
                                <Progress
                                    :value="row.progress_percentage"
                                    class="mb-1"
                                />
                                <div
                                    class="flex justify-between text-xs text-muted-foreground"
                                >
                                    <span>{{
                                        formatPercentage(row.progress_percentage)
                                    }}</span>
                                    <span
                                        >{{ row.lessons_completed }}/{{
                                            row.total_lessons
                                        }}</span
                                    >
                                </div>
                            </div>
                        </template>

                        <!-- Status Cell -->
                        <template #cell-status="{ row }">
                            <Badge :variant="getStatusBadge(row).variant">
                                {{ getStatusBadge(row).text }}
                            </Badge>
                        </template>

                        <!-- Last Activity Cell -->
                        <template #cell-last_activity="{ row }">
                            <span class="text-sm">{{
                                formatDate(row.last_activity)
                            }}</span>
                        </template>
                    </DataTable>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
