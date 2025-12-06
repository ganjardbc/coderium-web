<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PlaySquare, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import emblaCarouselVue from 'embla-carousel-vue';
import { Button } from '@/components/ui/button';

interface Playlist {
    id: number;
    slug: string;
    title: string;
    description: string;
    cover: string;
    posts_count: number;
}

const [emblaRef, emblaApi] = emblaCarouselVue({
    align: 'start',
    containScroll: 'trimSnaps',
    dragFree: true,
});

const canScrollLeft = ref(false);
const canScrollRight = ref(true);

defineProps<{
    playlists: Playlist[];
}>();

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

const scrollPlaylists = (direction: 'left' | 'right') => {
    if (!emblaApi.value) return;

    if (direction === 'left') {
        emblaApi.value.scrollPrev();
    } else {
        emblaApi.value.scrollNext();
    }
};
</script>

<template>
    <section
        id="playlists"
        class="py-8"
    >
        <div class="container mx-auto px-4">
            <div class="mb-4 flex flex-row items-center justify-between gap-2">
                <h2 class="text-xl font-bold">Our Playlists</h2>

                <!-- Scroll Navigation Buttons -->
                <div class="flex gap-2">
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
                class="relative"
            >
                <div ref="emblaRef" class="overflow-hidden">
                    <div class="flex gap-4 touch-pan-y">
                        <div
                            v-for="playlist in playlists"
                            :key="playlist.id"
                            class="flex-[0_0_auto] min-w-0"
                        >
                            <Button
                                :as="Link"
                                :href="`/playlists/${playlist.slug}`"
                                size="lg"
                                variant="outline"
                                class="flex flex-row w-auto !py-6"
                            >
                                <PlaySquare class="h-10 w-10 mr-2" />
                                <span class="text-md font-medium">{{ playlist.title }}</span>
                                <span class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground transition-colors group-hover:bg-primary/10">
                                    {{ playlist.posts_count }}
                                </span>
                            </Button>
                        </div>
                        <div class="flex-[0_0_auto] min-w-0">
                            <Button
                                :as="Link"
                                href="/playlists"
                                size="lg"
                                variant="outline"
                                class="flex flex-row w-auto !py-6 bg-gray-50 dark:bg-gray-800"
                            >
                                <span class="text-md font-medium">
                                    Browse Playlists
                                </span>
                                <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary ml-4">
                                    <ChevronRight class="h-4 w-4 text-white dark:text-black" />
                                </div>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="py-12 text-center text-muted-foreground">
                <PlaySquare class="mx-auto mb-4 h-12 w-12" />
                <p>No playlists available yet</p>
            </div>
        </div>
    </section>
</template>
