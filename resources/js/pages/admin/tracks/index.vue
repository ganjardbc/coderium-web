<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Searchbar from '@/components/Searchbar.vue';
import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, BookOpen, Clock, Users, DollarSign, Star, GraduationCap, Eye, Edit, Settings, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Track {
    id: number;
    title: string;
    description: string;
    slug: string;
    is_premium: boolean;
    price: number | null;
    is_published: boolean;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration: number | null;
    levels_count: number;
    enrollments_count: number;
    instructor?: {
        id: number;
        name: string;
        email: string;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    tracks: {
        data?: Track[];
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
        status?: string;
        difficulty?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Tracks', href: '/admin/tracks' },
];

const deleteTrack = (track: Track) => {
    if (confirm(`Are you sure you want to delete "${track.title}"?`)) {
        router.delete(`/admin/tracks/${track.slug}`, {
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

const formatPrice = (price: number | null, isPremium: boolean) => {
    if (!isPremium || !price) return 'Free';
    return `${price || 0}`;
};

const getDifficultyColor = (level: string) => {
    switch (level) {
        case 'beginner':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'intermediate':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'advanced':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

const columns: Column<Track>[] = [
    { key: 'title', label: 'Track', align: 'left' },
    { key: 'difficulty_level', label: 'Difficulty', align: 'left' },
    { key: 'levels_count', label: 'Levels', align: 'left' },
    { key: 'enrollments_count', label: 'Enrollments', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'price', label: 'Price', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/admin/tracks', {
        search: query,
        status: props.filters?.status,
        difficulty: props.filters?.difficulty,
    }, {
        preserveState: true,
        replace: true,
    });
};

const handleClearSearch = () => {
    router.get('/admin/tracks', {
        status: props.filters?.status,
        difficulty: props.filters?.difficulty,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Tracks - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Track Management</h1>
                    <p class="text-muted-foreground">
                        Create and manage learning tracks with structured levels and content
                    </p>
                </div>
                <Button as-child>
                    <Link href="/admin/tracks/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Track
                    </Link>
                </Button>
            </div>

            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search tracks by title or description..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <DataTable
                :data="props.tracks.data || []"
                :columns="columns"
                :pagination="props.tracks"
                empty-message="No tracks found"
                empty-description="Create your first track to get started with structured learning paths."
            >
                <template #cell-title="{ row }">
                    <div class="min-w-[200px]">
                        <div class="font-medium">{{ row.title }}</div>
                        <div class="text-sm text-muted-foreground line-clamp-2">
                            {{ row.description }}
                        </div>
                        <div v-if="row.instructor" class="flex items-center gap-1 mt-1">
                            <GraduationCap class="h-3 w-3 text-muted-foreground" />
                            <span class="text-xs text-muted-foreground">{{ row.instructor.name }}</span>
                        </div>
                    </div>
                </template>

                <template #cell-difficulty_level="{ row }">
                    <span
                        :class="getDifficultyColor(row.difficulty_level)"
                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold capitalize"
                    >
                        <Star class="mr-1 h-3 w-3" />
                        {{ row.difficulty_level }}
                    </span>
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

                <template #cell-estimated_duration="{ row }">
                    <div class="flex items-center gap-1">
                        <Clock class="h-4 w-4" />
                        {{ formatDuration(row.estimated_duration) }}
                    </div>
                </template>

                <template #cell-price="{ row }">
                    <div class="flex items-center gap-1">
                        <DollarSign class="h-4 w-4" />
                        <span :class="row.is_premium ? 'font-semibold' : 'text-muted-foreground'">
                            {{ formatPrice(row.price, row.is_premium) }}
                        </span>
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
                        <Link :href="`/admin/tracks/${row.slug}`">
                            <Button variant="outline">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/tracks/${row.slug}/edit`">
                            <Button variant="outline">
                                <Edit class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/tracks/${row.slug}/levels`">
                            <Button variant="outline">
                                <Settings class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="outline" @click="deleteTrack(row)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
