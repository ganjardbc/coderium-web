<script setup lang="ts">
import CourseCard from '@/components/CourseCard.vue';
import FilterSidebar from '@/components/FilterSidebar.vue';
import Pagination from '@/components/Pagination.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Button } from '@/components/ui/button';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { useDebounceFn } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import {
    BookOpen,
    GraduationCap,
    Search as SearchIcon,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Course {
    id: number;
    slug: string;
    title: string;
    description: string;
    estimated_duration: number;
    is_active: boolean;
    modules_count: number;
    enrollments_count: number;
    certificate_template?: {
        id: number;
        name: string;
        description: string;
    };
    url: string;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    data: Course[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Counts {
    all: number;
    with_certificate: number;
    beginner: number;
    intermediate: number;
    advanced: number;
}

interface Filters {
    search: string;
    certificate: string;
    difficulty: string;
    sortBy: string;
}

interface Props {
    courses: Pagination;
    counts: Counts;
    filters: Filters;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);
const selectedCertificate = ref(props.filters.certificate);
const selectedDifficulty = ref(props.filters.difficulty);
const selectedSort = ref(props.filters.sortBy);
const showFilters = ref(false);

const certificateOptions = computed(() => [
    {
        value: 'all',
        label: 'All Courses',
        icon: BookOpen,
        count: props.counts.all,
    },
    {
        value: 'with_certificate',
        label: 'With Certificate',
        icon: GraduationCap,
        count: props.counts.with_certificate,
    },
]);

const difficultyOptions = computed(() => [
    {
        value: 'all',
        label: 'All Levels',
        icon: BookOpen,
        count: props.counts.all,
    },
    {
        value: 'beginner',
        label: 'Beginner',
        icon: BookOpen,
        count: props.counts.beginner,
    },
    {
        value: 'intermediate',
        label: 'Intermediate',
        icon: BookOpen,
        count: props.counts.intermediate,
    },
    {
        value: 'advanced',
        label: 'Advanced',
        icon: BookOpen,
        count: props.counts.advanced,
    },
]);

const sortOptions = [
    { value: 'recent', label: 'Most Recent' },
    { value: 'popular', label: 'Most Popular' },
    { value: 'duration_asc', label: 'Shortest First' },
    { value: 'duration_desc', label: 'Longest First' },
    { value: 'alphabetical', label: 'A-Z' },
];

const filterSections = computed(() => [
    {
        key: 'certificate',
        title: 'Certificate',
        options: certificateOptions.value,
        selectedValue: selectedCertificate.value,
    },
    {
        key: 'difficulty',
        title: 'Difficulty',
        options: difficultyOptions.value,
        selectedValue: selectedDifficulty.value,
    },
    {
        key: 'sort',
        title: 'Sort By',
        options: sortOptions.map((option) => ({ ...option, icon: undefined })),
        selectedValue: selectedSort.value,
    },
]);

const hasActiveFilters = computed(() => {
    return (props.filters.search ||
        props.filters.certificate !== 'all' ||
        props.filters.difficulty !== 'all' ||
        props.filters.sortBy !== 'recent') as boolean;
});

const performSearch = () => {
    router.get(
        '/courses',
        {
            search: searchQuery.value,
            certificate: selectedCertificate.value,
            difficulty: selectedDifficulty.value,
            sort: selectedSort.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const updateCertificate = (certificate: string) => {
    selectedCertificate.value = certificate;
    performSearch();
};

const updateDifficulty = (difficulty: string) => {
    selectedDifficulty.value = difficulty;
    performSearch();
};

const updateSort = (sort: string) => {
    selectedSort.value = sort;
    performSearch();
};

const handleFilterUpdate = (key: string, value: string) => {
    if (key === 'certificate') {
        updateCertificate(value);
    } else if (key === 'difficulty') {
        updateDifficulty(value);
    } else if (key === 'sort') {
        updateSort(value);
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    selectedCertificate.value = 'all';
    selectedDifficulty.value = 'all';
    selectedSort.value = 'recent';
    performSearch();
};

const debouncedHandleSearch = useDebounceFn(() => {
    performSearch();
}, 500);

const handleSearch = () => {
    debouncedHandleSearch();
};
</script>

<template>
    <Head title="Explore Courses" />

    <FrontLayout>
        <!-- Header -->
        <section
            class="border-b bg-gradient-to-b from-card/50 to-background py-8"
        >
            <div class="container mx-auto px-4">
                <h1 class="text-center text-2xl font-bold md:text-3xl">
                    Explore Courses
                </h1>
                <p
                    class="text-md mt-2 text-center text-muted-foreground md:text-lg"
                >
                    Discover structured learning paths designed to help you
                    master new skills and advance your career
                </p>
            </div>
        </section>

        <!-- Filters & Results -->
        <section class="py-8">
            <div class="mx-auto max-w-6xl space-y-8 px-4">
                <!-- Search Courses -->
                <div class="flex gap-2">
                    <Searchbar
                        v-model="searchQuery"
                        placeholder="Search courses..."
                        class="flex-1"
                        @search="handleSearch"
                        @clear="clearSearch"
                    />
                    <Button
                        @click="showFilters = !showFilters"
                        variant="outline"
                        class="w-24"
                    >
                        <SlidersHorizontal class="h-4 w-4" />
                        {{ showFilters ? 'Hide' : 'Filters' }}
                    </Button>
                </div>

                <!-- Sidebar Filters -->
                <FilterSidebar
                    :sections="filterSections"
                    :show-filters="showFilters"
                    :has-active-filters="hasActiveFilters"
                    @update-filter="handleFilterUpdate"
                    @clear-filters="clearSearch"
                />

                <!-- Results -->
                <div class="flex-1">
                    <!-- Courses Grid -->
                    <div v-if="courses.data.length > 0" class="space-y-6">
                        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            <CourseCard
                                v-for="course in courses.data"
                                :key="course.id"
                                :course="course"
                            />
                        </div>

                        <!-- Pagination -->
                        <Pagination
                            :current-page="courses.current_page"
                            :last-page="courses.last_page"
                            :links="courses.links"
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
                            No courses found
                        </h3>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Try adjusting your search or filters to find what
                            you're looking for.
                        </p>
                        <Button @click="clearSearch"> Clear Filters </Button>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
