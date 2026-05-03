<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
import DiscoverMode from '@/components/DiscoverMode.vue';
import PostCard from '@/components/PostCard.vue';
import PostCarousel from '@/components/PostCarousel.vue';
import PostVideo from '@/components/PostVideo.vue';
import PostGallery from '@/components/PostGallery.vue';
import { Button } from '@/components/ui/button';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Clock,
    Eye,
    Heart,
    Share2,
    Tag,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Media {
    id: number;
    url: string;
    type: 'image' | 'video';
}

interface Post {
    id: number;
    slug: string;
    title: string;
    subtitle: string;
    content: string;
    cover: string;
    type: 'article' | 'carousel' | 'video' | 'stack_gallery';
    tags: string[];
    views_count: number;
    likes_count: number;
    published_at: string;
    media: Media[];
    user: {
        name: string;
    };
}

interface Props {
    post: Post;
    isLiked: boolean;
    relatedPosts: Post[];
}

const PostType = {
    ARTICLE: 'article',
    CAROUSEL: 'carousel',
    VIDEO: 'video',
    STACK_GALLERY: 'stack_gallery',
};

const ENABLE_DISCOVER_MODE = false;

const props = defineProps<Props>();

const isLiked = ref(props.isLiked);
const likesCount = ref(props.post.likes_count);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
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

const toggleLike = async () => {
    try {
        const response = await axios.post(
            `/api/v1/posts/${props.post.slug}/like`,
        );
        isLiked.value = response.data.is_liked;
        likesCount.value = response.data.likes_count;
    } catch (error) {
        console.error('Failed to toggle like:', error);
    }
};

const sharePost = () => {
    if (navigator.share) {
        navigator.share({
            title: props.post.title,
            text: props.post.subtitle,
            url: window.location.href,
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href);
        alert('Link copied to clipboard!');
    }
};

const getTags = () => {
    try {
        return JSON.parse(props.post.tags as unknown as string) as string[];
    } catch {
        return [];
    }
};

const showPostCover = computed(() => {
    const listOfTypes = [PostType.ARTICLE];
    return listOfTypes.includes(props.post.type) && !!props.post.cover;
});

const carouselPostMedia = computed(() => {
    let listOfMedia = props.post.media || [];

    if (props.post.cover) {
        const mediaCover: Media = {
            id: 0,
            url: props.post.cover,
            type: 'image',
        };
        listOfMedia = [mediaCover, ...listOfMedia];
    }

    return listOfMedia;
});
</script>

<template>
    <Head>
        <title>{{ post.title }}</title>
        <meta name="description" :content="post.subtitle || post.title" />
        <meta property="og:title" :content="post.title" />
        <meta
            property="og:description"
            :content="post.subtitle || post.title"
        />
        <meta property="og:image" :content="post.cover" />
        <meta property="og:type" :content="post.type" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>

    <FrontLayout>
        <!-- Back Button -->
        <BackButton>
            <template #append>
                <!-- Type Badge -->
                <div class="flex items-center justify-end gap-2">
                    <Button
                        @click="toggleLike"
                        size="lg"
                        :variant="isLiked ? 'default' : 'outline'"
                        class="rounded-full !px-3"
                    >
                        <Heart
                            :class="['h-5 w-5', isLiked ? 'fill-current' : '']"
                        />
                        <span class="hidden md:block">
                            {{ isLiked ? 'Liked' : 'Like' }}
                        </span>
                    </Button>
                    <Button
                        @click="sharePost"
                        size="lg"
                        variant="outline"
                        class="rounded-full !px-3"
                    >
                        <Share2 class="h-5 w-5" />
                        <span class="hidden md:block"> Share </span>
                    </Button>
                </div>
            </template>
        </BackButton>

        <!-- Article Content -->
        <article class="container mx-auto max-w-3xl px-0 lg:px-4">
            <!-- Article: Single Cover Image -->
            <div v-if="showPostCover" class="aspect-video overflow-hidden">
                <img
                    :src="post.cover"
                    :alt="post.title"
                    class="aspect-video w-full object-cover"
                />
            </div>

            <!-- Video: Post Video -->
            <PostVideo
                v-if="post.type === PostType.VIDEO && post.media.length > 0"
                :url="post.media[0].url"
            />

            <!-- Carousel: Multiple Images -->
            <PostCarousel
                v-if="post.type === PostType.CAROUSEL && carouselPostMedia.length > 0"
                :medias="carouselPostMedia"
            />

            <div class="px-4 py-8 lg:px-0">
                <div class="mx-auto max-w-3xl">
                    <div class="flex flex-col gap-4">
                        <!-- Title -->
                        <h1
                            class="text-xl font-bold tracking-tight lg:text-3xl"
                        >
                            {{ post.title }}
                        </h1>

                        <!-- Subtitle -->
                        <p
                            v-if="post.subtitle"
                            class="text-sm text-muted-foreground lg:text-lg"
                        >
                            {{ post.subtitle }}
                        </p>

                        <!-- Stacked Gallery -->
                        <PostGallery
                            v-if="
                                post.type === PostType.STACK_GALLERY &&
                                carouselPostMedia.length > 0
                            "
                            :medias="carouselPostMedia"
                        />

                        <!-- Content -->
                        <div
                            class="prose prose-lg dark:prose-invert content-html max-w-none"
                            v-html="post.content"
                        ></div>

                        <!-- Tags -->
                        <div
                            v-if="getTags().length > 0"
                            class="flex flex-wrap items-center gap-2 border-t pt-4"
                        >
                            <Link
                                v-for="tag in getTags()"
                                :key="tag"
                                :href="`/explore?q=${encodeURIComponent(tag)}&sort=recent&type=all`"
                                class="inline-flex items-center rounded-full border px-3 py-1 text-sm transition-colors hover:border-primary hover:bg-accent"
                            >
                                <Tag
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                {{ tag }}
                            </Link>
                        </div>

                        <!-- Meta Info -->
                        <div class="grid grid-cols-2 gap-4 border-t pt-4">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-1">
                                    <User
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <span class="text-sm text-muted-foreground">
                                        {{ post.user.name }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Clock
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <span class="text-sm text-muted-foreground">
                                        {{ formatDate(post.published_at) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-1">
                                    <Eye
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <span class="text-sm text-muted-foreground">
                                        {{
                                            formatNumber(post.views_count)
                                        }}
                                        views
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Heart
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <span class="text-sm text-muted-foreground">
                                        {{ formatNumber(likesCount) }} likes
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <!-- Related Posts -->
        <section
            v-if="props.relatedPosts && props.relatedPosts.length > 0"
            class="border-t py-8"
        >
            <div class="container mx-auto px-4">
                <div class="mx-auto w-full">
                    <h2 class="mb-4 text-2xl font-bold">Related Posts</h2>
                    <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-4">
                        <PostCard
                            v-for="relatedPost in props.relatedPosts"
                            :key="relatedPost.id"
                            :post="relatedPost"
                            :show-tags="true"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Related Actions -->
        <DiscoverMode v-if="ENABLE_DISCOVER_MODE" />
    </FrontLayout>
</template>

<style>
.prose {
    color: hsl(var(--foreground));
}

.prose :deep(h1),
.prose :deep(h2),
.prose :deep(h3),
.prose :deep(h4),
.prose :deep(h5),
.prose :deep(h6) {
    font-weight: 700;
    color: hsl(var(--foreground));
}

.prose :deep(a) {
    color: hsl(var(--primary));
}

.prose :deep(a:hover) {
    text-decoration: underline;
}

.prose :deep(code) {
    border-radius: 0.25rem;
    background-color: hsl(var(--muted));
    padding: 0.125rem 0.375rem;
    font-size: 0.875rem;
}

.prose :deep(pre) {
    border-radius: 0.5rem;
    background-color: hsl(var(--muted));
    padding: 1rem;
    overflow-x: auto;
}

.prose :deep(pre code) {
    background-color: transparent;
    padding: 0;
}

.prose :deep(blockquote) {
    border-left: 4px solid hsl(var(--primary));
    padding-left: 1rem;
    font-style: italic;
}

.prose :deep(img) {
    border-radius: 0.5rem;
}

.prose :deep(table) {
    width: 100%;
    border-collapse: collapse;
}

.prose :deep(th),
.prose :deep(td) {
    border: 1px solid hsl(var(--border));
    padding: 0.5rem 1rem;
}

.prose :deep(th) {
    background-color: hsl(var(--muted));
    font-weight: 600;
}
</style>
