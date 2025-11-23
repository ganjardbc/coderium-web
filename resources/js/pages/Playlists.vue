<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlaySquare } from 'lucide-vue-next';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PlaylistCard from '@/components/PlaylistCard.vue';
import Pagination from '@/components/Pagination.vue';
import BackButton from '@/components/BackButton.vue';

interface Playlist {
    id: number;
    slug: string;
    title: string;
    description: string;
    cover: string;
    posts_count: number;
}

interface PaginatedPlaylists {
    data: Playlist[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    playlists: PaginatedPlaylists;
}

defineProps<Props>();
</script>

<template>
    <Head title="Browse Playlists - Coderium" />

    <FrontLayout>
        <BackButton />

        <!-- Header -->
        <section class="border-b bg-gradient-to-b from-card/50 to-background py-8">
            <div class="container mx-auto px-4">
                <div class="flex items-center gap-3 mb-4">
                    <PlaySquare class="h-8 w-8 text-primary" />
                    <h1 class="text-3xl md:text-4xl font-bold">Browse Playlists</h1>
                </div>
                <p class="text-md md:text-lg text-muted-foreground max-w-2xl">
                    Discover curated collections of posts organized by topics and themes
                </p>
            </div>
        </section>

        <!-- Playlists Grid -->
        <section class="py-8">
            <div class="container mx-auto px-4">
                <div v-if="playlists.data.length > 0">
                    <!-- Stats -->
                    <div class="mb-6 text-sm text-muted-foreground">
                        Showing {{ (playlists.current_page - 1) * playlists.per_page + 1 }} -
                        {{ Math.min(playlists.current_page * playlists.per_page, playlists.total) }}
                        of {{ playlists.total }} playlists
                    </div>

                    <!-- Grid -->
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <PlaylistCard
                            v-for="playlist in playlists.data"
                            :key="playlist.id"
                            :playlist="playlist"
                        />
                    </div>

                    <!-- Pagination -->
                    <div v-if="playlists.last_page > 1" class="mt-8">
                        <Pagination
                            :current-page="playlists.current_page"
                            :last-page="playlists.last_page"
                            :links="playlists.links"
                        />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="py-16 text-center">
                    <PlaySquare class="mx-auto mb-4 h-16 w-16 text-muted-foreground/50" />
                    <h3 class="mb-2 text-xl font-semibold">No Playlists Found</h3>
                    <p class="text-muted-foreground mb-6">
                        There are no playlists available at the moment.
                    </p>
                    <Link
                        href="/"
                        class="inline-flex items-center justify-center rounded-full border bg-background px-6 py-2 text-sm font-medium transition-colors hover:bg-accent hover:border-primary"
                    >
                        Back to Home
                    </Link>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
