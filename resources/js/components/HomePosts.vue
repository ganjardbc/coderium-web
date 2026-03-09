<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { PlaySquare, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import emblaCarouselVue from 'embla-carousel-vue';
import { Button } from '@/components/ui/button';

import PostCard from '@/components/PostCard.vue';
import Pagination from '@/components/Pagination.vue';
import Searchbar from '@/components/Searchbar.vue';

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

const scrollPosts = (direction: 'left' | 'right') => {
    if (!emblaApi.value) return;

    if (direction === 'left') {
        emblaApi.value.scrollPrev();
    } else {
        emblaApi.value.scrollNext();
    }
};

const ENABLE_POST_SEARCH = false;
const ENABLE_POST_PAGINATION = false;

const props = defineProps<{
    posts: Post[];
    title: string;
    filters?: {
        search?: string;
        sort?: string;
    };
    pagination: {
        current_page: number;
        last_page: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
}>();

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/', {
        q: query || undefined,
        // sort: 'recent',
        // type: 'all',
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
    <section
        id="posts"
        class="py-8 border-t"
    >
        <div class="container mx-auto px-4">
            <div class="mb-4 flex flex-row items-center justify-between gap-2">
                <h2 class="text-xl font-bold">{{ title || 'Posts' }}</h2>

                <!-- Scroll Navigation Buttons -->
                <div class="flex gap-2">
                    <Button
                        size="lg"
                        variant="outline"
                        class="px-0 w-[40px] rounded-full"
                        :disabled="!canScrollLeft"
                        @click="scrollPosts('left')"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        class="px-0 w-[40px] rounded-full"
                        :disabled="!canScrollRight"
                        @click="scrollPosts('right')"
                    >
                        <ChevronRight class="h-5 w-5" />
                    </Button>
                </div>
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
                v-if="posts.length > 0"
                class="relative"
            >
                <div ref="emblaRef" class="overflow-hidden">
                    <div class="flex gap-4 touch-pan-y">
                        <PostCard
                            v-for="post in posts"
                            :key="post.id"
                            :post="post"
                            :show-tags="true"
                            class="!min-w-[310px] !max-w-[310px]"
                        />
                        <div class="flex-[0_0_auto] min-w-0">
                            <Button
                                :as="Link"
                                :href="`/explore?q=${props.filters?.search || ''}&sort=${props.filters?.sort || ''}&type=all`"
                                size="lg"
                                variant="outline"
                                class="flex flex-col !py-6 !min-w-[310px] !max-w-[310px] h-full bg-gray-50 dark:bg-gray-800"
                            >
                                <span class="mb-2 font-medium text-lg">
                                    Explore Posts
                                </span>
                                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary">
                                    <ChevronRight class="h-5 w-5 text-white dark:text-black" />
                                </div>
                            </Button>
                        </div>
                    </div>
                </div>
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
                v-if="pagination.last_page > 1 && ENABLE_POST_PAGINATION"
                class="mt-8"
            >
                <Pagination
                    :current-page="pagination.current_page"
                    :last-page="pagination.last_page"
                    :links="pagination.links"
                />
            </div>
        </div>
    </section>
</template>
