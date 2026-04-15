<script setup lang="ts">
import DataTable, {
    type Column,
} from '@/components/DataTable.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TrashIcon, EditIcon, PlusIcon } from 'lucide-vue-next';

interface Playlist {
    id: number;
    title: string;
    description: string;
    slug: string;
    cover: string;
    is_published: boolean;
    posts_count: number;
    created_at: string;
}

interface Props {
    playlists: {
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
    };
    filters?: {
        search?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Playlists', href: '/admin/playlists' },
];

const deletePlaylist = (playlist: Playlist) => {
    if (confirm('Are you sure you want to delete this playlist?')) {
        router.delete(`/admin/playlists/${playlist.slug}`, {
            preserveScroll: true,
        });
    }
};

const handleSearch = (query: string) => {
    router.get(
        '/admin/playlists',
        {
            search: query || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const handleClearSearch = () => {
    router.get(
        '/admin/playlists',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const columns: Column<Playlist>[] = [
    { key: 'cover', label: 'Cover', align: 'left' },
    { key: 'title', label: 'Title', align: 'left' },
    { key: 'posts_count', label: 'Posts', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
    { key: 'created_at', label: 'Created', align: 'left' },
];
</script>

<template>
    <Head title="Playlists - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Playlists</h1>
                    <p class="text-muted-foreground">
                        Manage your content playlists
                    </p>
                </div>
                <Link href="/admin/playlists/create">
                    <Button>
                        <PlusIcon class="h-4 w-4" />
                        Create Playlist
                    </Button>
                </Link>
            </div>

            <!-- Search Playlists -->
            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search playlists..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <!-- Playlists Table -->
            <DataTable
                :data="props.playlists.data"
                :columns="columns"
                :pagination="props.playlists"
                empty-message="No playlists found"
            >
                <!-- Cover Cell -->
                <template #cell-cover="{ row }">
                    <div class="h-12 w-20 overflow-hidden rounded border">
                        <img
                            v-if="row.cover"
                            :src="row.cover"
                            :alt="row.title"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-muted"
                        >
                            <svg
                                class="h-6 w-6 text-muted-foreground"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </div>
                    </div>
                </template>

                <!-- Title Cell -->
                <template #cell-title="{ row }">
                    <div>
                        <p class="font-medium">{{ row.title }}</p>
                        <p class="line-clamp-1 text-sm text-muted-foreground">
                            {{ row.description }}
                        </p>
                    </div>
                </template>

                <!-- Posts Count Cell -->
                <template #cell-posts_count="{ row }">
                    <span class="text-sm">{{ row.posts_count }} posts</span>
                </template>

                <!-- Status Cell -->
                <template #cell-is_published="{ row }">
                    <span
                        :class="
                            row.is_published
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                        "
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                    >
                        {{ row.is_published ? 'Published' : 'Draft' }}
                    </span>
                </template>

                <!-- Created Date Cell -->
                <template #cell-created_at="{ row }">
                    <span class="text-sm text-muted-foreground">
                        {{ new Date(row.created_at).toLocaleDateString() }}
                    </span>
                </template>

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex gap-2">
                        <Link :href="`/admin/playlists/${row.slug}/edit`">
                            <Button variant="outline">
                                <EditIcon class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="outline" @click="deletePlaylist(row)">
                            <TrashIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
