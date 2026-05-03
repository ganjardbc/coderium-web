<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Eye,
    Heart,
    Clock,
    PlayCircle,
} from 'lucide-vue-next';
import PostCarousel from '@/components/PostCarousel.vue';
import PostGallery from '@/components/PostGallery.vue';
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

const PostType = {
    ARTICLE: 'article',
    CAROUSEL: 'carousel',
    VIDEO: 'video',
    STACK_GALLERY: 'stack_gallery',
};

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

const showPostCover = (post: Post) => {
    const listOfTypes = [
        PostType.ARTICLE,
        PostType.VIDEO,
    ];
    return listOfTypes.includes(post.type) && !!post.cover;
};

const carouselPostMedia = (post: Post) => {
    let listOfMedia = post.media || [];

    if (post.cover) {
        const mediaCover: Media = {
            id: 0,
            url: post.cover,
            type: 'image',
        };
        listOfMedia = [mediaCover, ...listOfMedia];
    }

    // Limit gallery media to 3 images for stack_gallery type
    if (post.type === PostType.STACK_GALLERY) {
        listOfMedia = listOfMedia.slice(0, 2);
    }

    return listOfMedia;
};
</script>

<template>
    <div class="relative group overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-xl">
        <div
            v-if="post.type !== PostType.STACK_GALLERY"
            class="relative w-full overflow-hidden bg-muted"
            :class="{
                'aspect-video': showPostCover(post),
                'aspect-4/5': post.type === PostType.CAROUSEL,
            }"
        >
            <!-- Article: Single Cover Image -->
            <Link
                v-if="showPostCover(post)"
                :href="`/posts/${post.slug}`"
                class="aspect-video overflow-hidden"
            >
                <img
                    :src="post.cover"
                    :alt="post.title"
                    class="aspect-video w-full object-cover"
                />
            </Link>

            <!-- Video: Play Video -->
            <Link
                v-else-if="post.type === PostType.VIDEO"
                :href="`/posts/${post.slug}`"
                class="absolute top-0 flex w-full h-full items-center justify-center bg-black/50"
            >
                <PlayCircle class="h-12 w-12 text-primary" />
            </Link>

            <!-- Carousel: Multiple Images -->
            <PostCarousel
                v-else-if="post.type === PostType.CAROUSEL && carouselPostMedia(post).length > 0"
                :medias="carouselPostMedia(post)"
            />

            <PostBadge
                :post-type="post.type"
                class="absolute top-3 left-3"
            />
        </div>

        <Link
            :href="`/posts/${post.slug}`"
            class="p-5 flex flex-col gap-4">
            <div class="w-full space-y-4">
                <div class="w-full space-y-1">
                    <h3 class="mb-2 text-lg font-semibold truncate group-hover:text-primary transition-colors">
                        {{ post.title }}
                    </h3>
                    <p v-if="post.subtitle" class="line-clamp-1 text-sm text-muted-foreground">
                        {{ post.subtitle }}
                    </p>
                </div>

                <!-- Tags -->
                <div
                    v-if="showTags && getPostTags(post) && getPostTags(post).length > 0"
                    class="flex flex-wrap gap-1"
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

            <!-- Stack Gallery: Multiple Images -->
            <div
                v-if="post.type === PostType.STACK_GALLERY && carouselPostMedia(post).length > 0"
                class="relative w-full"
            >
                <PostGallery
                    class="grid grid-cols-2 bg-black"
                    :medias="carouselPostMedia(post)"
                />
                <PostBadge
                    :post-type="post.type"
                    class="absolute top-3 left-3"
                />
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
        </Link>
    </div>
</template>
