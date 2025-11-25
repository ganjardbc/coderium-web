<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PlaySquare, ChevronLeft, ChevronRight } from 'lucide-vue-next';
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

interface Props {
    playlists: Playlist[];
    recentPosts: PaginatedPosts;
    filters?: {
        search?: string;
    };
}

const ENABLE_PLAYLIST = true;
const ENABLE_POST_SEARCH = false;
const ENABLE_POST_PAGINATION = false;

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');

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

const playlistScroll = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(true);

const scrollPlaylists = (direction: 'left' | 'right') => {
    if (!playlistScroll.value) return;

    const scrollAmount = 400;
    const newScrollLeft = direction === 'left'
        ? playlistScroll.value.scrollLeft - scrollAmount
        : playlistScroll.value.scrollLeft + scrollAmount;

    playlistScroll.value.scrollTo({
        left: newScrollLeft,
        behavior: 'smooth'
    });

    // Update button states after scroll
    setTimeout(updateScrollButtons, 300);
};

const updateScrollButtons = () => {
    if (!playlistScroll.value) return;

    canScrollLeft.value = playlistScroll.value.scrollLeft > 0;
    canScrollRight.value =
        playlistScroll.value.scrollLeft <
        (playlistScroll.value.scrollWidth - playlistScroll.value.clientWidth - 10);
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
            v-if="ENABLE_PLAYLIST"
            id="playlists"
            class="border-b py-8"
        >
            <div class="container mx-auto px-4">
                <div class="mb-8 flex flex-col md:flex-row items-center justify-between gap-2">
                    <h2 class="text-2xl font-bold">Featured Playlists</h2>

                    <!-- Scroll Navigation Buttons -->
                    <div
                        v-if="playlists.length > 0"
                        class="flex gap-2"
                    >
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
                    ref="playlistScroll"
                    @scroll="updateScrollButtons"
                    class="flex gap-6 overflow-x-auto pb-4 scrollbar-hide snap-x snap-mandatory"
                    style="scroll-padding: 0 1rem;"
                >
                    <PlaylistCard
                        v-for="playlist in playlists"
                        :key="playlist.id"
                        :playlist="playlist"
                        style="width: 520px; min-width: 520px;"
                    />
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

                <div v-else class="py-12 text-center text-muted-foreground">
                    <PlaySquare class="mx-auto mb-4 h-12 w-12" />
                    <p>No playlists available yet</p>
                </div>
            </div>
        </section>

        <!-- Recent Posts Section -->
        <section class="py-8">
            <div class="container mx-auto px-4">
                <div class="flex flex-col md:flex-row justify-between items-center gap-2 mb-8">
                    <h2 class="text-2xl font-bold">Recent Posts</h2>
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

                <div v-if="recentPosts.data.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <PostCard
                        v-for="post in recentPosts.data"
                        :key="post.id"
                        :post="post"
                        :show-tags="true"
                    />
                </div>

                <div v-else class="py-12 text-center text-muted-foreground">
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

        <!-- Related Actions -->
        <DiscoverMode />
    </FrontLayout>
</template>
