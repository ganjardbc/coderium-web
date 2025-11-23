<script setup lang="ts">
import { computed } from 'vue';

interface Analytics {
    overview: {
        total_posts: number;
        total_playlists: number;
        total_views: number;
        total_likes: number;
    };
    posts_by_type: {
        article?: number;
        carousel?: number;
        video?: number;
    };
    top_viewed_posts: Array<{
        id: number;
        title: string;
        slug: string;
        views_count: number;
        type: string;
    }>;
    top_liked_posts: Array<{
        id: number;
        title: string;
        slug: string;
        likes_count: number;
        type: string;
    }>;
}

interface Props {
    analytics: Analytics;
}

const props = defineProps<Props>();

const postTypePercentages = computed(() => {
    const total = props.analytics.overview.total_posts || 1;
    return {
        article: ((props.analytics.posts_by_type.article || 0) / total) * 100,
        carousel: ((props.analytics.posts_by_type.carousel || 0) / total) * 100,
        video: ((props.analytics.posts_by_type.video || 0) / total) * 100,
    };
});
</script>

<template>
    <div class="space-y-6">
        <!-- Stats Overview -->
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <!-- Total Posts -->
            <div class="rounded-lg border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Posts</p>
                        <h3 class="text-2xl font-bold">{{ analytics.overview.total_posts }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                        <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Playlists -->
            <div class="rounded-lg border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Playlists</p>
                        <h3 class="text-2xl font-bold">{{ analytics.overview.total_playlists }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-500/10">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Views -->
            <div class="rounded-lg border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Views</p>
                        <h3 class="text-2xl font-bold">{{ analytics.overview.total_views.toLocaleString() }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-500/10">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Likes -->
            <div class="rounded-lg border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Likes</p>
                        <h3 class="text-2xl font-bold">{{ analytics.overview.total_likes.toLocaleString() }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-500/10">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Post Types Distribution -->
            <div class="rounded-lg border bg-card p-6">
                <h3 class="mb-4 text-lg font-semibold">Posts by Type</h3>
                <div class="space-y-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Articles</span>
                            <span class="font-medium">{{ analytics.posts_by_type.article || 0 }}</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                            <div class="h-full bg-primary transition-all" :style="{ width: `${postTypePercentages.article}%` }"></div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Carousels</span>
                            <span class="font-medium">{{ analytics.posts_by_type.carousel || 0 }}</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                            <div class="h-full bg-blue-500 transition-all" :style="{ width: `${postTypePercentages.carousel}%` }"></div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Videos</span>
                            <span class="font-medium">{{ analytics.posts_by_type.video || 0 }}</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                            <div class="h-full bg-green-500 transition-all" :style="{ width: `${postTypePercentages.video}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Viewed Posts -->
            <div class="rounded-lg border bg-card p-6">
                <h3 class="mb-4 text-lg font-semibold">Top Viewed Posts</h3>
                <div class="w-full space-y-3">
                    <div
                        v-for="post in analytics.top_viewed_posts"
                        :key="post.id"
                        class="flex-1 flex flex-col md:flex-row md:items-center justify-between rounded-md border p-3 gap-2"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium">{{ post.title }}</p>
                            <p class="text-xs text-muted-foreground capitalize">{{ post.type }}</p>
                        </div>
                        <div class="flex items-center gap-1 text-sm font-medium text-muted-foreground">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            {{ post.views_count.toLocaleString() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Liked Posts -->
        <div class="rounded-lg border bg-card p-6">
            <h3 class="mb-4 text-lg font-semibold">Top Liked Posts</h3>
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="post in analytics.top_liked_posts"
                    :key="post.id"
                    class="flex-1 flex flex-col md:flex-row md:items-center justify-between rounded-md border p-3 gap-2"
                >
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-sm font-medium">{{ post.title }}</p>
                        <p class="text-xs text-muted-foreground capitalize">{{ post.type }}</p>
                    </div>
                    <div class="flex items-center gap-1 text-sm font-medium text-red-600">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        {{ post.likes_count }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
