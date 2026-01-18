<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
import FilterSidebar from '@/components/FilterSidebar.vue';
import Pagination from '@/components/Pagination.vue';
import Searchbar from '@/components/Searchbar.vue';
import TrackCard from '@/components/TrackCard.vue';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApi';
import { useDebounceFn } from '@/lib/utils';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { Track } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    Search as SearchIcon,
    SlidersHorizontal,
    BookOpen,
    Star,
    DollarSign
} from 'lucide-vue-next';
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

interface Counts {
    all: number;
    beginner: number;
    intermediate: number;
    advanced: number;
    free: number;
    premium: number;
}

interface Filters {
    search?: string;
    difficulty?: string;
    is_premium?: boolean;
    sort?: string;
}

interface Props {
    tracks: PaginatedTracks;
    counts?: Counts;
    filters?: Filters;
}

const props = defineProps<Props>();

const { get } = useApi();

const searchQuery = ref(props.filters?.search || '');
const selectedDifficulty = ref(
    props.filters?.difficulty && props.filters.difficulty !== ''
        ? props.filters.difficulty
        : 'all'
);
const selectedPremium = ref(
    props.filters?.is_premium !== null && props.filters?.is_premium !== undefined
        ? props.filters.is_premium.toString()
        : 'all'
);
const selectedSort = ref(props.filters?.sort || 'created_at');
const showFilters = ref(false);

const difficultyOptions = computed(() => [
    {
        value: 'all',
        label: 'All Levels',
        icon: BookOpen,
        count: props.counts?.all || 0,
    },
    {
        value: 'beginner',
        label: 'Beginner',
        icon: Star,
        count: props.counts?.beginner || 0,
    },
    {
        value: 'intermediate',
        label: 'Intermediate',
        icon: Star,
        count: props.counts?.intermediate || 0,
    },
    {
        value: 'advanced',
        label: 'Advanced',
        icon: Star,
        count: props.counts?.advanced || 0,
    },
]);

const typeOptions = computed(() => [
    {
        value: 'all',
        label: 'All Types',
        icon: BookOpen,
        count: props.counts?.all || 0,
    },
    {
        value: 'false',
        label: 'Free',
        icon: BookOpen,
        count: props.counts?.free || 0,
    },
    {
        value: 'true',
        label: 'Premium',
        icon: DollarSign,
        count: props.counts?.premium || 0,
    },
]);

const sortOptions = [
    { value: 'created_at', label: 'Most Recent' },
    { value: 'title', label: 'Title A-Z' },
    { value: 'difficulty_level', label: 'By Difficulty' },
    { value: 'estimated_duration', label: 'By Duration' },
];

const filterSections = computed(() => [
    {
        key: 'difficulty',
        title: 'Difficulty',
        options: difficultyOptions.value,
        selectedValue: selectedDifficulty.value,
    },
    {
        key: 'type',
        title: 'Type',
        options: typeOptions.value,
        selectedValue: selectedPremium.value,
    },
    {
        key: 'sort',
        title: 'Sort By',
        options: sortOptions.map(option => ({ ...option, icon: undefined })),
        selectedValue: selectedSort.value,
    },
]);

const performSearch = () => {
    get(
        '/classroom',
        {
            search: searchQuery.value || undefined,
            difficulty: selectedDifficulty.value !== 'all' ? selectedDifficulty.value : undefined,
            is_premium: selectedPremium.value !== 'all' ? selectedPremium.value : undefined,
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

const updateDifficulty = (difficulty: string) => {
    selectedDifficulty.value = difficulty;
    performSearch();
};

const updateType = (type: string) => {
    selectedPremium.value = type;
    performSearch();
};

const updateSort = (sort: string) => {
    selectedSort.value = sort;
    performSearch();
};

const handleFilterUpdate = (key: string, value: string) => {
    if (key === 'difficulty') {
        updateDifficulty(value);
    } else if (key === 'type') {
        updateType(value);
    } else if (key === 'sort') {
        updateSort(value);
    }
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedDifficulty.value = 'all';
    selectedPremium.value = 'all';
    selectedSort.value = 'created_at';
    performSearch();
};

const debouncedHandleSearch = useDebounceFn(() => {
    performSearch();
}, 500);

const handleSearch = () => {
    debouncedHandleSearch();
};

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value ||
        selectedDifficulty.value !== 'all' ||
        selectedPremium.value !== 'all' ||
        selectedSort.value !== 'created_at'
    );
});
</script>

<template>
    <Head title="Explore Classroom" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Header -->
        <section
            class="border-b bg-gradient-to-b from-card/50 to-background py-8"
        >
            <div class="container mx-auto px-4">
                <h1 class="text-center text-2xl font-bold md:text-3xl">
                    Explore Classroom
                </h1>
                <p
                    class="text-md mt-2 text-center text-muted-foreground md:text-lg"
                >
                    Structured learning paths to master coding skills
                    through hands-on projects and assessments.
                </p>
            </div>
        </section>

        <!-- Filters & Results -->
        <section class="py-8">
            <div class="container mx-auto px-4">
                <!-- Search Tracks -->
                <div class="mb-8">
                    <Searchbar
                        v-model="searchQuery"
                        placeholder="Search tracks..."
                        @search="handleSearch"
                        @clear="clearFilters"
                    />
                    <Button
                        @click="showFilters = !showFilters"
                        variant="outline"
                        class="mt-4 w-full md:hidden"
                    >
                        <SlidersHorizontal class="h-4 w-4" />
                        {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                    </Button>
                </div>

                <div class="grid gap-8 lg:grid-cols-[310px_1fr]">
                    <!-- Sidebar Filters -->
                    <FilterSidebar
                        :sections="filterSections"
                        :show-filters="showFilters"
                        :has-active-filters="hasActiveFilters"
                        :show-counts="!!counts"
                        @update-filter="handleFilterUpdate"
                        @clear-filters="clearFilters"
                    />

                    <!-- Results -->
                    <div class="flex-1">
                        <!-- Tracks Grid -->
                        <div v-if="tracks.data.length > 0" class="space-y-6">
                            <div
                                class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <TrackCard
                                    v-for="track in tracks.data"
                                    :key="track.id"
                                    :track="track"
                                />
                            </div>

                            <!-- Pagination -->
                            <Pagination
                                :current-page="tracks.current_page"
                                :last-page="tracks.last_page"
                                :links="tracks.links"
                            />
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else
                            class="rounded-lg border border-dashed py-16 text-center"
                        >
                            <SearchIcon
                                class="mx-auto mb-4 h-12 w-12 text-muted-foreground"
                            />
                            <h3 class="mb-2 text-lg font-semibold">
                                No tracks found
                            </h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Try adjusting your search or filters to find
                                what you're looking for.
                            </p>
                            <Button @click="clearFilters">
                                Clear Filters
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
