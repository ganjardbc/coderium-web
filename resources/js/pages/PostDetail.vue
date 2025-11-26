<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Eye, Heart, Tag, Share2, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import emblaCarouselVue from 'embla-carousel-vue';
import FrontLayout from '@/layouts/FrontLayout.vue';
import BackButton from '@/components/BackButton.vue';
import DiscoverMode from '@/components/DiscoverMode.vue';
import PostCard from '@/components/PostCard.vue';
import { Button } from '@/components/ui/button';
import axios from 'axios';

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
    type: 'article' | 'carousel' | 'video';
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

const props = defineProps<Props>();

const [emblaRef, emblaApi] = emblaCarouselVue();
const currentImageIndex = ref(0);
const isLiked = ref(props.isLiked);
const likesCount = ref(props.post.likes_count);

// Update current index when carousel scrolls
watchEffect(() => {
    if (emblaApi.value) {
        const onSelect = () => {
            currentImageIndex.value = emblaApi.value?.selectedScrollSnap() || 0;
        };

        emblaApi.value.on('select', onSelect);
        emblaApi.value.on('reInit', onSelect);

        // Initialize the index
        onSelect();
    }
});

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

const nextImage = () => {
    if (emblaApi.value) {
        emblaApi.value.scrollNext();
    }
};

const prevImage = () => {
    if (emblaApi.value) {
        emblaApi.value.scrollPrev();
    }
};

const goToImage = (index: number) => {
    if (emblaApi.value) {
        emblaApi.value.scrollTo(index);
    }
};

const toggleLike = async () => {
    try {
        const response = await axios.post(`/api/v1/posts/${props.post.slug}/like`);
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
</script>

<template>
    <Head>
        <title>{{ post.title }}</title>
        <meta name="description" :content="post.subtitle || post.title" />
        <meta property="og:title" :content="post.title" />
        <meta property="og:description" :content="post.subtitle || post.title" />
        <meta property="og:image" :content="post.cover" />
        <meta property="og:type" content="article" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>

    <FrontLayout>
        <!-- Back Button -->
        <BackButton />

        <!-- Article Content -->
        <article class="py-8">
            <div class="container mx-auto px-4">
                <div class="mx-auto max-w-4xl">
                    <!-- Type Badge -->
                    <div class="mb-4">
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-4 py-1.5 text-sm font-semibold',
                                post.type === 'article' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '',
                                post.type === 'carousel' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '',
                                post.type === 'video' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : '',
                            ]"
                        >
                            {{ post.type.charAt(0).toUpperCase() + post.type.slice(1) }}
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="mb-4 text-4xl font-bold tracking-tight lg:text-5xl">
                        {{ post.title }}
                    </h1>

                    <!-- Subtitle -->
                    <p v-if="post.subtitle" class="mb-6 text-xl text-muted-foreground">
                        {{ post.subtitle }}
                    </p>

                    <!-- Meta Info -->
                    <div class="mb-8 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">By {{ post.user.name }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Clock class="h-4 w-4" />
                            <span>{{ formatDate(post.published_at) }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Eye class="h-4 w-4" />
                            <span>{{ formatNumber(post.views_count) }} views</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Heart class="h-4 w-4" />
                            <span>{{ formatNumber(likesCount) }} likes</span>
                        </div>
                    </div>

                    <!-- Cover/Media Section -->
                    <div class="mb-8">
                        <!-- Article: Single Cover Image -->
                        <div v-if="post.type === 'article' && post.cover" class="overflow-hidden rounded-lg">
                            <img
                                :src="post.cover"
                                :alt="post.title"
                                class="w-full object-cover"
                            />
                        </div>

                        <!-- Carousel: Multiple Images -->
                        <div
                            v-else-if="post.type === 'carousel' && post.media.length > 0"
                            class="relative border border-gray-300 rounded-lg overflow-hidden h-[430px] md:h-[630px]"
                        >
                            <div ref="emblaRef" class="overflow-hidden rounded-lg h-full">
                                <div class="flex h-full touch-pan-y">
                                    <div
                                        v-for="(media, index) in post.media"
                                        :key="index"
                                        class="flex-[0_0_100%] min-w-0 bg-muted flex items-center justify-center"
                                    >
                                        <img
                                            :src="media.url"
                                            :alt="`${post.title} - Image ${index + 1}`"
                                            class="max-w-full h-full object-contain"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Navigation Arrows -->
                            <Button
                                v-if="post.media.length > 1"
                                @click="prevImage"
                                :disabled="currentImageIndex === 0"
                                variant="default"
                                class="absolute left-4 bottom-0 lg:bottom-1/2 -translate-y-1/2 rounded-full w-[32px] h-[32px]"
                            >
                                <ChevronLeft class="h-5 w-5" />
                            </Button>
                            <Button
                                v-if="post.media.length > 1"
                                @click="nextImage"
                                :disabled="currentImageIndex === post.media.length - 1"
                                variant="default"
                                class="absolute right-4 bottom-0 lg:bottom-1/2 -translate-y-1/2 rounded-full w-[32px] h-[32px]"
                            >
                                <ChevronRight class="h-5 w-5" />
                            </Button>

                            <!-- Image Counter -->
                            <div class="absolute bottom-10 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1.5 text-sm text-white backdrop-blur-sm">
                                {{ currentImageIndex + 1 }} / {{ post.media.length }}
                            </div>

                            <!-- Dot Indicators -->
                            <div v-if="post.media.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex justify-center gap-2">
                                <button
                                    v-for="(_, index) in post.media"
                                    :key="index"
                                    @click="goToImage(index)"
                                    :class="[
                                        'h-2 rounded-full transition-all',
                                        index === currentImageIndex
                                            ? 'w-8 bg-primary'
                                            : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/50'
                                    ]"
                                />
                            </div>
                        </div>

                        <!-- Video: Video Player -->
                        <div v-else-if="post.type === 'video' && post.media.length > 0" class="overflow-hidden rounded-lg bg-muted">
                            <video
                                :src="post.media[0].url"
                                controls
                                class="w-full h-auto aspect-video"
                            >
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mb-8 flex items-center gap-3">
                        <Button
                            @click="toggleLike"
                            :variant="isLiked ? 'default' : 'outline'"
                            class="rounded-full"
                        >
                            <Heart :class="['h-5 w-5', isLiked ? 'fill-current' : '']" />
                            <span>{{ isLiked ? 'Liked' : 'Like' }}</span>
                        </Button>
                        <Button
                            @click="sharePost"
                            variant="outline"
                            class="rounded-full"
                        >
                            <Share2 class="h-5 w-5" />
                            <span>Share</span>
                        </Button>
                    </div>

                    <!-- Content -->
                    <div
                        class="prose prose-lg dark:prose-invert max-w-none content-html"
                        v-html="post.content"
                    ></div>

                    <!-- Tags -->
                    <div v-if="getTags().length > 0" class="border-t pt-8 mt-8">
                        <div class="flex items-start gap-3">
                            <Tag class="h-5 w-5 text-muted-foreground mt-1" />
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="tag in getTags()"
                                    :key="tag"
                                    :href="`/search?q=${encodeURIComponent(tag)}&sort=recent&type=all`"
                                    class="inline-flex items-center rounded-full border px-3 py-1 text-sm transition-colors hover:border-primary hover:bg-accent"
                                >
                                    {{ tag }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <!-- Related Posts -->
        <section v-if="props.relatedPosts && props.relatedPosts.length > 0" class="py-8 border-t">
            <div class="container mx-auto px-4">
                <div class="mx-auto w-full">
                    <h2 class="mb-4 text-2xl font-bold">Related Posts</h2>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
        <DiscoverMode />
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
