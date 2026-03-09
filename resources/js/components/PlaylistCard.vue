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
        class="group grid grid-cols-[82px_1fr] overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-lg snap-start"
    >
        <div class="aspect-square overflow-hidden bg-muted">
            <img
                v-if="playlist.cover"
                :src="playlist.cover"
                :alt="playlist.title"
                class="h-full w-full object-cover transition-transform group-hover:scale-105"
            />
            <div v-else class="flex h-full items-center justify-center">
                <PlaySquare class="h-16 w-16 text-muted-foreground" />
            </div>
        </div>
        <div class="p-4 flex flex-col justify-between overflow-hidden">
            <h3 class="mb-2 text-sm truncate font-semibold group-hover:text-primary transition-colors">
                {{ playlist.title }}
            </h3>
            <div class="flex items-center gap-2">
                <PlaySquare class="h-3 w-3" />
                <span class="text-xs text-muted-foreground">{{ playlist.posts_count }} posts</span>
            </div>
        </div>
    </Link>
</template>
