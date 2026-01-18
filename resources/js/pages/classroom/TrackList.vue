<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import CustomSelect from '@/components/CustomSelect.vue';
import { useApi } from '@/composables/useApi';
import { globalLoading } from '@/composables/useLoading';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { Track } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Clock, Filter, GraduationCap, Search, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaginatedTracks {
    data: Track[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    tracks: PaginatedTracks;
    filters?: {
        search?: string;
        difficulty?: string;
        is_premium?: boolean;
        sort?: string;
    };
}

const props = defineProps<Props>();

const { get } = useApi();
const { isLoading } = globalLoading;

const searchQuery = ref(props.filters?.search || '');
const selectedDifficulty = ref(props.filters?.difficulty || '');
const selectedPremium = ref(props.filters?.is_premium?.toString() || '');
const selectedSort = ref(props.filters?.sort || 'created_at');

const isSearching = isLoading('track-search');

const handleSearch = () => {
    get(
        '/classroom/tracks',
        {
            search: searchQuery.value || undefined,
            difficulty: selectedDifficulty.value || undefined,
            is_premium: selectedPremium.value || undefined,
            sort: selectedSort.value,
        },
        {
            loadingKey: 'track-search',
            errorContext: 'Search tracks',
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedDifficulty.value = '';
    selectedPremium.value = '';
    selectedSort.value = 'created_at';

    get(
        '/classroom/tracks',
        {},
        {
            loadingKey: 'track-search',
            errorContext: 'Clear filters',
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const formatDuration = (minutes: number | undefined): string => {
    if (!minutes) return 'N/A';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
};

const getDifficultyColor = (difficulty: string): string => {
    switch (difficulty) {
        case 'beginner':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'intermediate':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'advanced':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value ||
        selectedDifficulty.value ||
        selectedPremium.value ||
        selectedSort.value !== 'created_at'
    );
});

const navigateToTrack = (slug: string) => {
    get(
        `/classroom/tracks/${slug}`,
        {},
        {
            loadingKey: `track-${slug}`,
            errorContext: 'Load track details',
        },
    );
};

const difficultyOptions = [
    { value: '', label: 'All Levels' },
    { value: 'beginner', label: 'Beginner' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'advanced', label: 'Advanced' },
];

const typeOptions = [
    { value: '', label: 'All Types' },
    { value: 'false', label: 'Free' },
    { value: 'true', label: 'Premium' },
];

const sortOptions = [
    { value: 'created_at', label: 'Newest' },
    { value: 'title', label: 'Title' },
    { value: 'difficulty_level', label: 'Difficulty' },
    { value: 'estimated_duration', label: 'Duration' },
];
</script>

<template>
    <Head title="Learning Tracks - Classroom" />

    <FrontLayout>
        <!-- Hero Section -->
        <template #front-prepend>
            <section
                class="border-b bg-gradient-to-r from-blue-600 to-purple-600 py-8 sm:py-12"
            >
                <div class="container mx-auto px-4 text-center">
                    <h1
                        class="mb-4 text-2xl font-bold tracking-tight text-white sm:text-3xl md:text-4xl"
                    >
                        <GraduationCap
                            class="mr-2 inline-block h-6 w-6 sm:h-8 sm:w-8"
                        />
                        Learning Tracks
                    </h1>
                    <p
                        class="mx-auto max-w-3xl px-4 text-base text-white/90 sm:text-lg"
                    >
                        Structured learning paths to master coding skills
                        through hands-on projects and assessments.
                    </p>
                </div>
            </section>
        </template>

        <!-- Filters and Search -->
        <section class="border-b bg-card/50 py-4 sm:py-6">
            <div class="container mx-auto max-w-7xl px-4">
                <div class="flex flex-col gap-4">
                    <!-- Search -->
                    <div class="w-full">
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search tracks..."
                                class="h-12 pl-10 text-base"
                                @keyup.enter="handleSearch"
                            />
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <div class="grid flex-1 grid-cols-2 gap-3 sm:flex">
                            <CustomSelect
                                v-model="selectedDifficulty"
                                :options="difficultyOptions"
                                placeholder="Difficulty"
                            />

                            <CustomSelect
                                v-model="selectedPremium"
                                :options="typeOptions"
                                placeholder="Type"
                            />

                            <CustomSelect
                                v-model="selectedSort"
                                :options="sortOptions"
                                placeholder="Sort by"
                            />
                        </div>

                        <div class="flex gap-3">
                            <Button
                                @click="handleSearch"
                                variant="default"
                                class="h-12 flex-1 text-base sm:flex-none"
                                :disabled="isSearching.value"
                            >
                                <Filter class="mr-2 h-4 w-4" />
                                {{
                                    isSearching.value ? 'Searching...' : 'Apply'
                                }}
                            </Button>

                            <Button
                                v-if="hasActiveFilters"
                                @click="clearFilters"
                                variant="outline"
                                class="h-12 flex-1 text-base sm:flex-none"
                                :disabled="isSearching.value"
                            >
                                Clear
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tracks Grid -->
        <section class="py-6 sm:py-8">
            <div class="container mx-auto max-w-7xl px-4">
                <div
                    v-if="tracks.data.length > 0"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3"
                >
                    <Card
                        v-for="track in tracks.data"
                        :key="track.id"
                        class="group cursor-pointer touch-manipulation transition-all duration-200 hover:shadow-lg"
                        @click="navigateToTrack(track.slug)"
                    >
                        <CardHeader class="p-4 pb-3 sm:p-6">
                            <div class="mb-2 flex items-start justify-between">
                                <Badge
                                    :class="
                                        getDifficultyColor(
                                            track.difficulty_level,
                                        )
                                    "
                                    class="text-xs font-medium"
                                >
                                    {{
                                        track.difficulty_level
                                            .charAt(0)
                                            .toUpperCase() +
                                        track.difficulty_level.slice(1)
                                    }}
                                </Badge>
                                <Badge
                                    v-if="track.is_premium"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{
                                        track.price
                                            ? `${track.price}`
                                            : 'Premium'
                                    }}
                                </Badge>
                                <Badge v-else variant="outline" class="text-xs">
                                    Free
                                </Badge>
                            </div>
                            <CardTitle
                                class="line-clamp-2 text-base transition-colors group-hover:text-primary sm:text-lg"
                            >
                                {{ track.title }}
                            </CardTitle>
                            <CardDescription class="line-clamp-2 text-sm">
                                {{ track.description }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="p-4 pt-0 sm:p-6">
                            <div
                                class="mb-4 flex items-center justify-between text-sm text-muted-foreground"
                            >
                                <div class="flex items-center gap-1">
                                    <Clock class="h-4 w-4" />
                                    <span>{{
                                        formatDuration(track.estimated_duration)
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Users class="h-4 w-4" />
                                    <span class="hidden sm:inline"
                                        >{{
                                            track.enrollments_count || 0
                                        }}
                                        enrolled</span
                                    >
                                    <span class="sm:hidden">{{
                                        track.enrollments_count || 0
                                    }}</span>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <div
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    <span
                                        >{{
                                            track.levels_count || 0
                                        }}
                                        levels</span
                                    >
                                </div>
                                <div
                                    class="flex items-center gap-1 truncate text-muted-foreground"
                                >
                                    <span class="truncate"
                                        >by {{ track.instructor.name }}</span
                                    >
                                </div>
                            </div>

                            <!-- Progress bar if enrolled -->
                            <div v-if="track.enrollment" class="mt-4">
                                <div
                                    class="mb-1 flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Progress</span
                                    >
                                    <span class="font-medium"
                                        >{{
                                            Math.round(
                                                track.enrollment
                                                    .progress_percentage,
                                            )
                                        }}%</span
                                    >
                                </div>
                                <div class="h-2 w-full rounded-full bg-muted">
                                    <div
                                        class="h-2 rounded-full bg-primary transition-all duration-300"
                                        :style="{
                                            width: `${track.enrollment.progress_percentage}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Empty State -->
                <div v-else class="py-12 text-center">
                    <GraduationCap
                        class="mx-auto mb-4 h-12 w-12 text-muted-foreground sm:h-16 sm:w-16"
                    />
                    <h3 class="mb-2 text-base font-semibold sm:text-lg">
                        No tracks found
                    </h3>
                    <p
                        class="mb-4 px-4 text-sm text-muted-foreground sm:text-base"
                    >
                        {{
                            hasActiveFilters
                                ? 'Try adjusting your filters to find more tracks.'
                                : 'No learning tracks are available yet.'
                        }}
                    </p>
                    <Button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        variant="outline"
                        class="h-12 text-base"
                    >
                        Clear Filters
                    </Button>
                </div>

                <!-- Pagination -->
                <div v-if="tracks.last_page > 1" class="mt-8">
                    <Pagination
                        :current-page="tracks.current_page"
                        :last-page="tracks.last_page"
                        :links="tracks.links"
                    />
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
