<script setup lang="ts">
defineProps({
    url: {
        type: String,
        required: true
    }
})
</script>

<template>
    <div class="overflow-hidden bg-muted">
        <video
            :src="url"
            controls
            class="aspect-video h-auto w-full"
        >
            Your browser does not support the video tag.
        </video>
    </div>
</template>
