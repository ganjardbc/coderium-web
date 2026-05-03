<script setup lang="ts">
import {
    FileText,
    Image as ImageIcon,
    Video,
} from 'lucide-vue-next';

interface Props {
    postType?: string;
}

withDefaults(defineProps<Props>(), {
    postType: '',
});

const PostType = {
    ARTICLE: 'article',
    CAROUSEL: 'carousel',
    VIDEO: 'video',
    STACK_GALLERY: 'stack_gallery',
};
</script>

<template>
    <!-- Type Badge -->
    <div
        :class="[
            'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur-sm',
            postType === PostType.ARTICLE ? 'bg-blue-500/90 text-white' : '',
            postType === PostType.CAROUSEL ? 'bg-green-500/90 text-white' : '',
            postType === PostType.VIDEO ? 'bg-purple-500/90 text-white' : '',
            postType === PostType.STACK_GALLERY ? 'bg-yellow-500/90 text-white' : '',
        ]"
    >
        <FileText v-if="postType === PostType.ARTICLE" class="h-3 w-3" />
        <ImageIcon v-if="postType === PostType.CAROUSEL" class="h-3 w-3" />
        <Video v-if="postType === PostType.VIDEO" class="h-3 w-3" />
        <ImageIcon v-if="postType === PostType.STACK_GALLERY" class="h-3 w-3" />
        {{ postType.charAt(0).toUpperCase() + postType.slice(1).replace('_', ' ') }}
    </div>
</template>
