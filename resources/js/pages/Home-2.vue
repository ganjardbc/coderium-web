<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PlaySquare, ChevronLeft, ChevronRight, Tag } from 'lucide-vue-next';
import emblaCarouselVue from 'embla-carousel-vue';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PostCard from '@/components/PostCard.vue';
import PlaylistCard from '@/components/PlaylistCard.vue';
import Pagination from '@/components/Pagination.vue';
import DiscoverMode from '@/components/DiscoverMode.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Button } from '@/components/ui/button';

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

interface Playlist {
    id: number;
    slug: string;
    title: string;
    description: string;
    cover: string;
    posts_count: number;
}

interface PaginatedPosts {
    data: Post[];
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

interface PopularTag {
    name: string;
    count: number;
}

interface Props {
    playlists: Playlist[];
    recentPosts: PaginatedPosts;
    popularTags: PopularTag[];
    filters?: {
        search?: string;
    };
}

const ENABLE_PLAYLIST = true;
const ENABLE_POST_SEARCH = true;
const ENABLE_POST_PAGINATION = true;
const ENABLE_POPULAR_TAGS = true;
const ENABLE_DISCOVER_MODE = false;

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');

const [emblaRef, emblaApi] = emblaCarouselVue({
    align: 'start',
    containScroll: 'trimSnaps',
    dragFree: true,
});

const canScrollLeft = ref(false);
const canScrollRight = ref(true);

// Update scroll buttons based on embla state
watchEffect(() => {
    if (emblaApi.value) {
        const updateScrollButtons = () => {
            canScrollLeft.value = emblaApi.value?.canScrollPrev() || false;
            canScrollRight.value = emblaApi.value?.canScrollNext() || false;
        };

        emblaApi.value.on('select', updateScrollButtons);
        emblaApi.value.on('reInit', updateScrollButtons);

        // Initialize button states
        updateScrollButtons();
    }
});

const handleSearch = (query: string) => {
    router.get('/search', {
        q: query || undefined,
        sort: 'recent',
        type: 'all',
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleClearSearch = () => {
    router.get('/', {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const scrollPlaylists = (direction: 'left' | 'right') => {
    if (!emblaApi.value) return;

    if (direction === 'left') {
        emblaApi.value.scrollPrev();
    } else {
        emblaApi.value.scrollNext();
    }
};
</script>

<template>
    <Head title="The Code Heroes Journey" />

    <FrontLayout>
        <!-- Hero Section -->
        <template #front-prepend>
            <section class="border-b bg-[#004aad] bg-gradient-to-r to-[#cb6ce6] py-8">
                <div class="container mx-auto px-4 text-center">
                    <h1 class="mb-4 text-4xl font-bold tracking-tight lg:text-5xl text-white">
                        The <span class="font-extrabold text-white">Code Heroes</span> Journey
                    </h1>
                    <p class="mx-auto mb-8 max-w-3xl text-md lg:text-lg text-white">
                        Discover tutorials, code snippets, and development insights shared by the community.<br />
                        Learn, create, and grow together.
                    </p>
                    <div class="flex items-center justify-center gap-4">
                        <Button
                            :as="Link"
                            href="/search"
                            size="lg"
                            variant="default"
                            class="p-4 md:p-6 text-sm md:text-md rounded-full"
                        >
                            Explore Posts
                        </Button>
                        <Button
                            :as="Link"
                            href="/playlists"
                            size="lg"
                            variant="outline"
                            class="p-4 md:p-6 text-sm md:text-md rounded-full"
                        >
                            Browse Playlists
                        </Button>
                    </div>
                </div>
            </section>
        </template>

        <!-- Playlists Section -->
        <section
            v-if="ENABLE_PLAYLIST && playlists.length > 0"
            id="playlists"
            class="border-b py-8"
        >
            <div class="container mx-auto px-4">
                <div class="mb-4 flex flex-col md:flex-row items-center justify-between gap-2">
                    <h2 class="text-xl font-bold">Featured Playlists</h2>

                    <!-- Scroll Navigation Buttons -->
                    <div class="flex gap-2">
                        <Button
                            size="lg"
                            variant="outline"
                            class="px-0 w-[40px] rounded-full"
                            :disabled="!canScrollLeft"
                            @click="scrollPlaylists('left')"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            class="px-0 w-[40px] rounded-full"
                            :disabled="!canScrollRight"
                            @click="scrollPlaylists('right')"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </Button>
                    </div>
                </div>

                <div
                    v-if="playlists.length > 0"
                    class="relative"
                >
                    <div ref="emblaRef" class="overflow-hidden">
                        <div class="flex gap-6 touch-pan-y">
                            <div
                                v-for="playlist in playlists"
                                :key="playlist.id"
                                class="flex-[0_0_520px] min-w-0"
                            >
                                <PlaylistCard :playlist="playlist" />
                            </div>
                            <div class="flex-[0_0_auto] min-w-0">
                                <Button
                                    :as="Link"
                                    href="/playlists"
                                    size="lg"
                                    variant="outline"
                                    class="flex flex-col w-auto min-h-[156px] !px-10"
                                >
                                    Browse Playlists
                                    <div class="w-[40px] h-[40px] flex justify-center items-center border rounded-full bg-primary text-background">
                                        <ChevronRight class="h-10 w-10" />
                                    </div>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="py-12 text-center text-muted-foreground">
                    <PlaySquare class="mx-auto mb-4 h-12 w-12" />
                    <p>No playlists available yet</p>
                </div>
            </div>
        </section>

        <!-- Recent Posts Section -->
        <section class="py-8 border-t">
            <div class="container mx-auto px-4">
                <div class="flex flex-col md:flex-row justify-between items-center gap-2 mb-4">
                    <h2 class="text-xl font-bold">Recent Posts</h2>
                    <Button
                        :as="Link"
                        href="/search"
                        size="lg"
                        variant="outline"
                        class="w-full md:w-auto"
                    >
                        Explore Posts
                        <ChevronRight class="h-5 w-5" />
                    </Button>
                </div>

                <!-- Search Posts -->
                <div
                    v-if="ENABLE_POST_SEARCH"
                    class="mb-8"
                >
                    <Searchbar
                        v-model="searchQuery"
                        placeholder="Search posts..."
                        @search="handleSearch"
                        @clear="handleClearSearch"
                    />
                </div>

                <div
                    v-if="recentPosts.data.length > 0"
                    class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <PostCard
                        v-for="post in recentPosts.data"
                        :key="post.id"
                        :post="post"
                        :show-tags="true"
                    />
                </div>

                <div
                    v-else
                    class="py-12 text-center text-muted-foreground"
                >
                    <PlaySquare class="mx-auto mb-4 h-12 w-12" />
                    <p>No posts available yet</p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="recentPosts.last_page > 1 && ENABLE_POST_PAGINATION"
                    class="mt-8"
                >
                    <Pagination
                        :current-page="recentPosts.current_page"
                        :last-page="recentPosts.last_page"
                        :links="recentPosts.links"
                    />
                </div>
            </div>
        </section>

        <!-- Popular Tags Section -->
        <section
            v-if="ENABLE_POPULAR_TAGS && popularTags.length > 0"
            class="py-8 border-t"
        >
            <div class="container max-w-4xl mx-auto px-4">
                <h2 class="text-xl font-bold text-center mb-4">
                    Popular Tags
                </h2>

                <div class="flex flex-wrap justify-center gap-3">
                    <Button
                        v-for="tag in popularTags"
                        :key="tag.name"
                        :as="Link"
                        :href="`/search?q=${encodeURIComponent(tag.name)}&sort=recent&type=all`"
                        variant="outline"
                        size="lg"
                        class="group relative overflow-hidden rounded-full transition-all hover:scale-105"
                    >
                        <Tag class="h-4 w-4 mr-2" />
                        <span class="font-medium">{{ tag.name }}</span>
                        <span class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground transition-colors group-hover:bg-primary/10">
                            {{ tag.count }}
                        </span>
                    </Button>
                </div>
            </div>
        </section>

        <!-- Related Actions -->
        <DiscoverMode v-if="ENABLE_DISCOVER_MODE" />
    </FrontLayout>
</template>
