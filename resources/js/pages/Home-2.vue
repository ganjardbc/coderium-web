<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import DiscoverMode from '@/components/DiscoverMode.vue';
import HomePlaylists from '@/components/HomePlaylists.vue';
import HomeTags from '@/components/HomeTags.vue';
import HomePosts from '@/components/HomePosts.vue';

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
    popularPosts: PaginatedPosts;
    oldestPosts: PaginatedPosts;
    popularTags: PopularTag[];
    filters?: {
        search?: string;
    };
}

const ENABLE_PLAYLIST = true;
const ENABLE_RECENT_POSTS = true;
const ENABLE_POPULAR_POSTS = true;
const ENABLE_OLDEST_POSTS = true;
const ENABLE_POPULAR_TAGS = true;
const ENABLE_DISCOVER_MODE = false;

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');
</script>

<template>
    <Head title="The Code Heroes Journey" />

    <FrontLayout>
        <!-- Hero Section -->
        <template #front-prepend>
            <section class="border-b bg-[#004aad] bg-gradient-to-r to-[#cb6ce6] py-8">
                <div class="container mx-auto px-4 text-center">
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white">
                        The <span class="font-extrabold text-white">Code Heroes</span> Journey
                    </h1>
                    <p class="mx-auto max-w-3xl text-md md:text-lg text-white mt-2">
                        Discover tutorials, code snippets, and development insights shared by the community.
                    </p>
                    <div class="flex items-center justify-center gap-4 mt-8">
                        <Button
                            :as="Link"
                            href="/search"
                            size="lg"
                            variant="default"
                            class="p-4 text-sm md:text-md rounded-full"
                        >
                            Explore Posts
                        </Button>
                        <Button
                            :as="Link"
                            href="/playlists"
                            size="lg"
                            variant="outline"
                            class="p-4 text-sm md:text-md rounded-full"
                        >
                            Browse Playlists
                        </Button>
                    </div>
                </div>
            </section>
        </template>

        <!-- Playlists Section -->
        <HomePlaylists
            v-if="ENABLE_PLAYLIST && playlists.length > 0"
            :playlists="playlists"
        />

        <!-- Recent Posts Section -->
        <HomePosts
            v-if="ENABLE_RECENT_POSTS && recentPosts.data.length > 0"
            title="Recent Posts"
            :posts="recentPosts.data"
            :filters="{ search: searchQuery, sort: 'recent' }"
            :pagination="{
                current_page: recentPosts.current_page,
                last_page: recentPosts.last_page,
                links: recentPosts.links
            }"
        />

        <!-- Popular Posts Section -->
        <HomePosts
            v-if="ENABLE_POPULAR_POSTS && popularPosts.data.length > 0"
            title="Popular Posts"
            :posts="popularPosts.data"
            :filters="{ search: searchQuery, sort: 'popular' }"
            :pagination="{
                current_page: popularPosts.current_page,
                last_page: popularPosts.last_page,
                links: popularPosts.links
            }"
        />

        <!-- Oldest Posts Section -->
        <HomePosts
            v-if="ENABLE_OLDEST_POSTS && oldestPosts.data.length > 0"
            title="Oldest Posts"
            :posts="oldestPosts.data"
            :filters="{ search: searchQuery, sort: 'oldest' }"
            :pagination="{
                current_page: oldestPosts.current_page,
                last_page: oldestPosts.last_page,
                links: oldestPosts.links
            }"
        />

        <!-- Popular Tags Section -->
        <HomeTags
            v-if="ENABLE_POPULAR_TAGS && popularTags.length > 0"
            :popularTags="popularTags"
        />

        <!-- Related Actions -->
        <DiscoverMode v-if="ENABLE_DISCOVER_MODE" />
    </FrontLayout>
</template>
