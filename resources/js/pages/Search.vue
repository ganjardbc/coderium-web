<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { XIcon, Search as SearchIcon, FileText, Image as ImageIcon, Video, SlidersHorizontal } from 'lucide-vue-next';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PostCard from '@/components/PostCard.vue';
import Pagination from '@/components/Pagination.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Button } from '@/components/ui/button';
import { useDebounceFn } from '@/lib/utils';
import BackButton from '@/components/BackButton.vue';

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
    { value: 'all', label: 'All Posts', icon: FileText, count: props.counts.all },
    { value: 'article', label: 'Articles', icon: FileText, count: props.counts.article },
    { value: 'carousel', label: 'Carousels', icon: ImageIcon, count: props.counts.carousel },
    { value: 'video', label: 'Videos', icon: Video, count: props.counts.video },
]);

const sortOptions = [
    { value: 'recent', label: 'Most Recent' },
    { value: 'popular', label: 'Most Popular' },
    { value: 'likes', label: 'Most Liked' },
    { value: 'oldest', label: 'Oldest First' },
];

const performSearch = () => {
    router.get('/search', {
        q: searchQuery.value,
        type: selectedType.value,
        sort: selectedSort.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const updateType = (type: string) => {
    selectedType.value = type;
    performSearch();
};

const updateSort = (sort: string) => {
    selectedSort.value = sort;
    performSearch();
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
        <section class="border-b bg-gradient-to-b from-card/50 to-background py-8">
            <div class="container mx-auto px-4">
                <h1 class="text-2xl md:text-3xl font-bold text-center">Explore Posts</h1>
                <p class="text-md md:text-lg text-muted-foreground text-center mt-2">
                    Discover articles, carousels, and videos shared by our community of code enthusiasts
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
                        class="w-full md:hidden mt-4"
                    >
                        <SlidersHorizontal class="h-4 w-4" />
                        {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                    </Button>
                </div>

                <div class="grid gap-8 lg:grid-cols-[310px_1fr]">
                    <!-- Sidebar Filters -->
                    <aside
                        class="flex-1 border rounded-lg p-4 h-fit"
                        :class="['space-y-4', showFilters ? 'block' : 'hidden md:block']"
                    >
                        <div class="flex justify-between items-center border-b pb-4">
                            <div class="text-md font-semibold">
                                Filters
                            </div>

                            <Button
                                v-if="filters.query || filters.type !== 'all' || filters.sortBy !== 'recent'"
                                @click="clearSearch"
                                variant="outline"
                                size="sm"
                                class="rounded-full"
                            >
                                <XIcon class="h-4 w-4" />
                                Clear
                            </Button>
                        </div>

                        <!-- Type Filter -->
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-muted-foreground">Type</h3>
                            <div class="space-y-2">
                                <Button
                                    v-for="option in typeOptions"
                                    :key="option.value"
                                    @click="updateType(option.value)"
                                    :variant="selectedType === option.value ? 'default' : 'ghost'"
                                    class="w-full justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <component :is="option.icon" class="h-4 w-4" />
                                        <span>{{ option.label }}</span>
                                    </div>
                                    <span class="text-xs opacity-75">{{ option.count }}</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Sort Filter -->
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-muted-foreground">Sort By</h3>
                            <div class="space-y-2">
                                <Button
                                    v-for="option in sortOptions"
                                    :key="option.value"
                                    @click="updateSort(option.value)"
                                    :variant="selectedSort === option.value ? 'default' : 'ghost'"
                                    class="w-full justify-start"
                                >
                                    {{ option.label }}
                                </Button>
                            </div>
                        </div>
                    </aside>

                    <!-- Results -->
                    <div class="flex-1">
                        <!-- Posts Grid -->
                        <div v-if="posts.data.length > 0" class="space-y-6">
                            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
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
                        <div v-else class="rounded-lg border border-dashed py-16 text-center">
                            <SearchIcon class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 class="mb-2 text-lg font-semibold">No posts found</h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Try adjusting your search or filters to find what you're looking for.
                            </p>
                            <Button
                                @click="clearSearch"
                            >
                                Clear Filters
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
