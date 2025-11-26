<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PlaySquare } from 'lucide-vue-next';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PlaylistCard from '@/components/PlaylistCard.vue';
import Pagination from '@/components/Pagination.vue';
import Searchbar from '@/components/Searchbar.vue';
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
    filters?: {
        search?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');

const handleSearch = (query: string) => {
    router.get('/playlists', {
        search: query || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleClearSearch = () => {
    router.get('/playlists', {}, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Browse Playlists" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Header -->
        <section class="border-b bg-gradient-to-b from-card/50 to-background py-8">
            <div class="container mx-auto px-4">
                <h1 class="text-2xl md:text-3xl font-bold text-center">Browse Playlists</h1>
                <p class="text-md md:text-lg text-muted-foreground text-center mt-2">
                    Discover curated collections of posts organized by topics and themes
                </p>
            </div>
        </section>

        <!-- Playlists Grid -->
        <section class="py-8">
            <div class="container mx-auto px-4">
                <!-- Search Playlists -->
                <div class="mb-8">
                    <Searchbar
                        v-model="searchQuery"
                        placeholder="Search playlists..."
                        @search="handleSearch"
                        @clear="handleClearSearch"
                    />
                </div>

                <div v-if="playlists.data.length > 0">
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
