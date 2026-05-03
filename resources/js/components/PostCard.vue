<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, Heart, Clock, PlaySquare } from 'lucide-vue-next';
import PostBadge from '@/components/PostBadge.vue';

interface Post {
    id: number;
    slug: string;
    title: string;
    subtitle: string;
    cover: string;
    type: 'article'  | 'carousel' | 'video' | 'stack_gallery';
    tags?: string[];
    views_count: number;
    likes_count: number;
    published_at: string;
}

interface Props {
    post: Post;
    showTags?: boolean;
}

withDefaults(defineProps<Props>(), {
    showTags: false,
});

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatNumber = (num: number) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
};

const getPostTags = (post: Post): string[] => {
    try {
        return JSON.parse(post.tags as unknown as string) as string[];
    } catch {
        return [];
    }
};
</script>

<template>
    <Link
        :href="`/posts/${post.slug}`"
        class="group overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-xl"
    >
        <div class="relative aspect-video w-full overflow-hidden bg-muted">
            <img
                v-if="post.cover"
                :src="post.cover"
                :alt="post.title"
                class="h-full w-full object-cover transition-transform group-hover:scale-105"
            />
            <div v-else class="flex h-full items-center justify-center">
                <PlaySquare class="h-16 w-16 text-muted-foreground" />
            </div>

            <!-- Type Badge -->
            <PostBadge
                :post-type="post.type"
                class="absolute top-3 left-3"
            />
        </div>

        <div class="p-5 flex flex-col justify-between">
            <div class="flex flex-col min-h-[124px]">
                <h3 class="mb-2 text-md font-semibold truncate group-hover:text-primary transition-colors">
                    {{ post.title }}
                </h3>
                <p v-if="post.subtitle" class="mb-4 line-clamp-1 text-sm text-muted-foreground">
                    {{ post.subtitle }}
                </p>

                <!-- Tags -->
                <div
                    v-if="showTags && getPostTags(post) && getPostTags(post).length > 0"
                    class="mb-3 flex flex-wrap gap-1"
                >
                    <span
                        v-for="tag in getPostTags(post).slice(0, 1)"
                        :key="tag"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                    >
                        {{ tag }}
                    </span>
                    <span
                        v-if="getPostTags(post).length > 1"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                    >
                        +{{ getPostTags(post).length - 1 }} more
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs text-muted-foreground">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1">
                        <Eye class="h-3.5 w-3.5" />
                        <span>{{ formatNumber(post.views_count) }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Heart class="h-3.5 w-3.5" />
                        <span>{{ formatNumber(post.likes_count) }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <Clock class="h-3.5 w-3.5" />
                    <span>{{ formatDate(post.published_at) }}</span>
                </div>
            </div>
        </div>
    </Link>
</template>
