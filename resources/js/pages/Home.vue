<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PlaySquare, Tag } from 'lucide-vue-next';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PostListCard from '@/components/PostListCard.vue';
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
const ENABLE_POST_SEARCH = false;
const ENABLE_POST_PAGINATION = true;
const ENABLE_POPULAR_TAGS = true;
const ENABLE_DISCOVER_MODE = false;

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/', {
        q: query || undefined,
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
</script>

<template>
    <Head title="The Code Heroes Journey" />

    <FrontLayout>
        <!-- Main Content: Two Column Layout (Medium-style) -->
        <section class="py-4 lg:py-8">
            <div class="w-full px-4">
                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-6">
                    <!-- Main Column: Posts List (Second on mobile, first on desktop) -->
                    <div class="flex-1 space-y-6">
                        <!-- Hero Section -->
                        <section class="border-b bg-[#004aad] bg-gradient-to-r to-[#cb6ce6] py-12 rounded-lg">
                            <div class="container mx-auto px-4 text-center">
                                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white">
                                    The <span class="font-extrabold text-white">Code Heroes</span> Journey
                                </h1>
                                <p class="mx-auto max-w-3xl text-md md:text-lg text-white mt-2">
                                    Discover tutorials, code snippets, and development insights.
                                </p>
                            </div>
                        </section>

                        <div class="flex-1 max-w-xl mx-auto space-y-6">
                            <!-- Search Bar -->
                            <Searchbar
                                v-if="ENABLE_POST_SEARCH"
                                v-model="searchQuery"
                                placeholder="Search posts..."
                                @search="handleSearch"
                                @clear="handleClearSearch"
                            />

                            <div
                                v-if="recentPosts.data.length > 0"
                                class="grid grid-cols-1 gap-6"
                            >
                                <PostListCard
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
                            <Pagination
                                v-if="recentPosts.last_page > 1 && ENABLE_POST_PAGINATION"
                                :current-page="recentPosts.current_page"
                                :last-page="recentPosts.last_page"
                                :links="recentPosts.links"
                            />
                        </div>
                    </div>

                    <!-- Sidebar Column: Sticky Content (First on mobile, second on desktop) -->
                    <aside class="flex-1 max-w-xl mx-auto hidden xl:block">
                        <div class="xl:sticky xl:top-8 space-y-6">
                            <!-- Featured Playlists Card -->
                            <div
                                v-if="ENABLE_PLAYLIST && playlists.length > 0"
                                class="w-full"
                            >
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-semibold">Featured Playlists</h3>
                                    <Button
                                        :as="Link"
                                        href="/playlists"
                                        variant="outline"
                                        size="sm"
                                        class="text-xs"
                                    >
                                        View All
                                    </Button>
                                </div>
                                <div class="space-y-4">
                                    <PlaylistCard
                                        v-for="playlist in playlists"
                                        :key="playlist.id"
                                        :playlist="playlist"
                                    />
                                </div>
                            </div>

                            <!-- Popular Tags Card -->
                            <div
                                v-if="ENABLE_POPULAR_TAGS && popularTags.length > 0"
                                class="w-full"
                            >
                                <h3 class="mb-4 text-lg font-semibold">Popular Tags</h3>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="tag in popularTags.slice(0, 12)"
                                        :key="tag.name"
                                        :as="Link"
                                        :href="`/explore?q=${encodeURIComponent(tag.name)}&sort=recent&type=all`"
                                        variant="outline"
                                        size="sm"
                                        class="group rounded-full text-xs"
                                    >
                                        <Tag class="h-3 w-3 mr-1" />
                                        {{ tag.name }}
                                        <span class="ml-1 text-muted-foreground">
                                            {{ tag.count }}
                                        </span>
                                    </Button>
                                     <Button
                                        v-if="popularTags.length > 4"
                                        :as="Link"
                                        href="/explore"
                                        variant="outline"
                                        size="sm"
                                        class="group rounded-full text-xs"
                                    >
                                        View All
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <!-- Section Mode -->
        <DiscoverMode v-if="ENABLE_DISCOVER_MODE" />
    </FrontLayout>
</template>
