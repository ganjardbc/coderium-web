<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PlaySquare } from 'lucide-vue-next';

interface Playlist {
    id: number;
    slug: string;
    title: string;
    description: string;
    cover: string;
    posts_count: number;
}

interface Props {
    playlist: Playlist;
}

defineProps<Props>();
</script>

<template>
    <Link
        :href="`/playlists/${playlist.slug}`"
        class="group overflow-hidden rounded-lg border bg-card transition-all hover:shadow-md grid grid-cols-[80px_1fr]"
    >
        <div class="relative aspect-1/1 overflow-hidden bg-muted">
            <img
                v-if="playlist.cover"
                :src="playlist.cover"
                :alt="playlist.title"
                class="h-full w-full object-cover transition-transform group-hover:scale-105"
            />
            <div v-else class="flex h-full items-center justify-center">
                <PlaySquare class="h-12 w-12 text-muted-foreground" />
            </div>
        </div>
        <div class="p-3">
            <h3 class="mb-2 line-clamp-2 text-sm font-semibold group-hover:text-primary">
                {{ playlist.title }}
            </h3>
            <p class="text-xs text-muted-foreground flex items-center gap-1">
                <PlaySquare class="h-4 w-4" />
                {{ playlist.posts_count }} {{ playlist.posts_count === 1 ? 'post' : 'posts' }}
            </p>
        </div>
    </Link>
</template>
