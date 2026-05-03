<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, Heart, PlaySquare } from 'lucide-vue-next';
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
</script>

<template>
    <Link
        :href="`/posts/${post.slug}`"
        class="relative group overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-xl"
    >
        <div class="relative aspect-square w-full overflow-hidden bg-muted">
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

        <div class="absolute bottom-0 w-full p-2 hidden md:block">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 backdrop-blur-lg bg-black/60 shadow-lg px-2 py-1 rounded-full">
                    <div class="flex items-center gap-1 text-xs text-white">
                        <Eye class="h-3.5 w-3.5" />
                        <span>{{ formatNumber(post.views_count) }}</span>
                    </div>
                    <div class="flex items-center gap-1 text-xs text-white">
                        <Heart class="h-3.5 w-3.5" />
                        <span>{{ formatNumber(post.likes_count) }}</span>
                    </div>
                </div>
                <div class="backdrop-blur-lg bg-black/60 shadow-lg px-2 py-1 rounded-full">
                    <div class="flex items-center gap-1 text-xs text-white">
                        <span>{{ formatDate(post.published_at) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </Link>
</template>
