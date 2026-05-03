<script setup lang="ts">
import { Button } from '@/components/ui/button';
import emblaCarouselVue from 'embla-carousel-vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';

interface Media {
    id: number;
    url: string;
    type?: 'image' | 'video';
}

interface Props {
    medias: Media[];
}

const props = defineProps<Props>();

const [emblaRef, emblaApi] = emblaCarouselVue();
const currentImageIndex = ref(0);

// Ensure props is recognized as used
const medias = computed(() => props.medias);

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
</script>

<template>
    <div
        v-if="medias.length > 0"
        class="relative overflow-hidden aspect-4/5 bg-black"
    >
        <div ref="emblaRef" class="h-full">
            <div class="flex h-full touch-pan-y">
                <div
                    v-for="(media, index) in medias"
                    :key="media.id"
                    class="flex min-w-0 flex-[0_0_100%] items-center justify-center"
                >
                    <img
                        :src="media.url"
                        :alt="`Image ${index + 1}`"
                        class="h-full max-w-full object-contain"
                    />
                </div>
            </div>
        </div>

        <!-- Navigation Arrows -->
        <Button
            v-if="medias.length > 1"
            @click="prevImage"
            :disabled="currentImageIndex === 0"
            variant="default"
            class="absolute bottom-1/2 left-4 h-[32px] w-[32px] -translate-y-1/2 rounded-full"
        >
            <ChevronLeft class="h-5 w-5" />
        </Button>
        <Button
            v-if="medias.length > 1"
            @click="nextImage"
            :disabled="currentImageIndex === medias.length - 1"
            variant="default"
            class="absolute right-4 bottom-1/2 h-[32px] w-[32px] -translate-y-1/2 rounded-full"
        >
            <ChevronRight class="h-5 w-5" />
        </Button>

        <!-- Image Counter -->
        <div
            class="shadow-inner-lg absolute top-2 right-2 rounded-full bg-black/50 px-3 py-1.5 text-sm text-white shadow-lg backdrop-blur-md"
        >
            {{ currentImageIndex + 1 }} / {{ medias.length }}
        </div>

        <!-- Dot Indicators -->
        <div
            v-if="medias.length > 1"
            class="shadow-inner-lg absolute bottom-4 left-1/2 flex -translate-x-1/2 justify-center gap-2 rounded-full bg-black/50 px-3 py-2 shadow-lg backdrop-blur-sm"
        >
            <button
                v-for="(_, index) in medias"
                :key="index"
                @click="goToImage(index)"
                :class="[
                    'h-2 rounded-full transition-all',
                    index === currentImageIndex
                        ? 'w-8 bg-primary'
                        : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/50',
                ]"
            />
        </div>
    </div>
</template>
