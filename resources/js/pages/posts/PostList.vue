<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
import FilterSidebar from '@/components/FilterSidebar.vue';
import Pagination from '@/components/Pagination.vue';
import PostCard from '@/components/PostCard.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Button } from '@/components/ui/button';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { useDebounceFn } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import {
    FileText,
    Image as ImageIcon,
    Search as SearchIcon,
    SlidersHorizontal,
    Video,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Post {
    id: number;
    slug: string;
    title: string;
    subtitle: string;
    cover: string;
    type: 'article' | 'carousel' | 'video';
    tags: string[];
    views_count: number;
    likes_count: number;
    published_at: string;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    data: Post[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Counts {
    all: number;
    article: number;
    carousel: number;
    video: number;
}

interface Filters {
    query: string;
    type: string;
    sortBy: string;
}

interface Props {
    posts: Pagination;
    counts: Counts;
    filters: Filters;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.query);
const selectedType = ref(props.filters.type);
const selectedSort = ref(props.filters.sortBy);
const showFilters = ref(false);

const typeOptions = computed(() => [
    {
        value: 'all',
        label: 'All Posts',
        icon: FileText,
        count: props.counts.all,
    },
    {
        value: 'article',
        label: 'Articles',
        icon: FileText,
        count: props.counts.article,
    },
    {
        value: 'carousel',
        label: 'Carousels',
        icon: ImageIcon,
        count: props.counts.carousel,
    },
    { value: 'video', label: 'Videos', icon: Video, count: props.counts.video },
]);

const sortOptions = [
    { value: 'recent', label: 'Most Recent' },
    { value: 'popular', label: 'Most Popular' },
    { value: 'likes', label: 'Most Liked' },
    { value: 'oldest', label: 'Oldest First' },
];

const filterSections = computed(() => [
    {
        key: 'type',
        title: 'Type',
        options: typeOptions.value,
        selectedValue: selectedType.value,
    },
    {
        key: 'sort',
        title: 'Sort By',
        options: sortOptions.map(option => ({ ...option, icon: undefined })),
        selectedValue: selectedSort.value,
    },
]);

const hasActiveFilters = computed(() => {
    return (
        props.filters.query ||
        props.filters.type !== 'all' ||
        props.filters.sortBy !== 'recent'
    );
});

const performSearch = () => {
    router.get(
        '/search',
        {
            q: searchQuery.value,
            type: selectedType.value,
            sort: selectedSort.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const updateType = (type: string) => {
    selectedType.value = type;
    performSearch();
};

const updateSort = (sort: string) => {
    selectedSort.value = sort;
    performSearch();
};

const handleFilterUpdate = (key: string, value: string) => {
    if (key === 'type') {
        updateType(value);
    } else if (key === 'sort') {
        updateSort(value);
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    selectedType.value = 'all';
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
    <Head title="Explore Posts" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Header -->
        <section
            class="border-b bg-gradient-to-b from-card/50 to-background py-8"
        >
            <div class="container mx-auto px-4">
                <h1 class="text-center text-2xl font-bold md:text-3xl">
                    Explore Posts
                </h1>
                <p
                    class="text-md mt-2 text-center text-muted-foreground md:text-lg"
                >
                    Discover articles, carousels, and videos shared by our
                    community of code enthusiasts
                </p>
            </div>
        </section>

        <!-- Filters & Results -->
        <section class="py-8">
            <div class="container mx-auto px-4">
                <!-- Search Posts -->
                <div class="mb-8">
                    <Searchbar
                        v-model="searchQuery"
                        placeholder="Search posts..."
                        @search="handleSearch"
                        @clear="clearSearch"
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
                        @update-filter="handleFilterUpdate"
                        @clear-filters="clearSearch"
                    />

                    <!-- Results -->
                    <div class="flex-1">
                        <!-- Posts Grid -->
                        <div v-if="posts.data.length > 0" class="space-y-6">
                            <div
                                class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <PostCard
                                    v-for="post in posts.data"
                                    :key="post.id"
                                    :post="post"
                                    :show-tags="true"
                                />
                            </div>

                            <!-- Pagination -->
                            <Pagination
                                :current-page="posts.current_page"
                                :last-page="posts.last_page"
                                :links="posts.links"
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
                                No posts found
                            </h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Try adjusting your search or filters to find
                                what you're looking for.
                            </p>
                            <Button @click="clearSearch">
                                Clear Filters
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
