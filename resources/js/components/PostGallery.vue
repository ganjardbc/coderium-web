<script setup lang="ts">
import { computed } from 'vue';

interface Media {
    id: number;
    url: string;
    type?: 'image' | 'video';
}

interface Props {
    medias: Media[];
}

const props = defineProps<Props>();

// Ensure props is recognized as used
const medias = computed(() => props.medias);
</script>

<template>
    <div
        class="w-full overflow-hidden rounded-lg border"
    >
        <div
            v-for="(media, index) in medias"
            :key="media.id || index"
            class="overflow-hidden"
        >
            <img
                :src="media.url"
                :alt="`Image ${index + 1}`"
                class="h-full w-full object-cover"
            />
        </div>
    </div>
</template>
