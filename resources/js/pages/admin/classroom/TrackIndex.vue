<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Action, type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Users, BookOpen } from 'lucide-vue-next';
import { ref } from 'vue';

interface Track {
    id: number;
    title: string;
    description: string;
    slug: string;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration: number | null;
    is_premium: boolean;
    price: number | null;
    is_published: boolean;
    instructor_id: number | null;
    levels_count: number;
    enrollments_count: number;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

interface Props {
    tracks: {
        data?: Track[];
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
    { title: 'Tracks', href: '/admin/classroom/tracks' },
];

const deleteTrack = (track: Track) => {
    if (confirm(`Are you sure you want to delete the track "${track.title}"? This action cannot be undone.`)) {
        router.delete(`/admin/classroom/tracks/${track.slug}`, {
            preserveScroll: true,
            onSuccess: () => {
                // Optional: Add success notification here
            },
            onError: (errors) => {
                console.error('Failed to delete track:', errors);
                alert('Failed to delete track. Please try again.');
            },
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

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'Not set';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const columns: Column<Track>[] = [
    { key: 'title', label: 'Track', align: 'left' },
    { key: 'difficulty_level', label: 'Difficulty', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'levels_count', label: 'Levels', align: 'left' },
    { key: 'enrollments_count', label: 'Enrollments', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const actions: Action<Track>[] = [
    {
        href: (track) => `/admin/classroom/tracks/${track.slug}/edit`,
        variant: 'outline',
    },
    {
        onClick: (track) => deleteTrack(track),
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
    <Head title="Tracks - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Tracks</h1>
                    <p class="text-muted-foreground">
                        Manage learning tracks and courses
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/classroom/tracks/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Track
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search tracks..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.tracks.data || []"
                :columns="columns"
                :actions="actions"
                :pagination="props.tracks"
                empty-message="No tracks found"
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[228px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description.substring(0, 100) }}{{ row.description.length > 100 ? '...' : '' }}
                        </div>
                        <div v-if="row.is_premium" class="mt-1">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                Premium ${{ row.price }}
                            </span>
                        </div>
                    </div>
                </template>

                <template #cell-difficulty_level="{ row }">
                    <span
                        :class="getDifficultyColor(row.difficulty_level)"
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                    >
                        {{ row.difficulty_level }}
                    </span>
                </template>

                <template #cell-estimated_duration="{ row }">
                    <div class="min-w-[92px]">
                        {{ formatDuration(row.estimated_duration) }}
                    </div>
                </template>

                <template #cell-levels_count="{ row }">
                    <div class="flex items-center gap-1">
                        <BookOpen class="h-4 w-4" />
                        {{ row.levels_count }}
                    </div>
                </template>

                <template #cell-enrollments_count="{ row }">
                    <div class="flex items-center gap-1">
                        <Users class="h-4 w-4" />
                        {{ row.enrollments_count }}
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
