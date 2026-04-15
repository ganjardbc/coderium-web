<script setup lang="ts">
import DataTable, {
    type Column,
} from '@/components/DataTable.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TrashIcon, EditIcon, PlusIcon, EyeIcon } from 'lucide-vue-next';

interface Post {
    id: number;
    title: string;
    subtitle: string;
    slug: string;
    type: 'article' | 'carousel' | 'video';
    cover: string;
    is_published: boolean;
    views_count: number;
    likes_count: number;
    published_at: string;
    created_at: string;
}

interface Props {
    posts: {
        data: Post[];
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
    { title: 'Posts', href: '/admin/posts' },
];

const deletePost = (post: Post) => {
    if (confirm('Are you sure you want to delete this post?')) {
        router.delete(`/admin/posts/${post.slug}`, {
            preserveScroll: true,
        });
    }
};

const handleSearch = (query: string) => {
    router.get(
        '/admin/posts',
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
        '/admin/posts',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const getTypeColor = (type: string) => {
    switch (type) {
        case 'article':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'carousel':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 'video':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'stack_gallery':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

const getTypeLabel = (type: string) => {
    switch (type) {
        case 'article':
            return 'Article';
        case 'carousel':
            return 'Carousel';
        case 'video':
            return 'Video';
        case 'stack_gallery':
            return 'Stack Gallery';
        default:
            return 'Unknown';
    }
};

const columns: Column<Post>[] = [
    { key: 'cover', label: 'Cover', align: 'left' },
    { key: 'title', label: 'Title', align: 'left' },
    { key: 'type', label: 'Type', align: 'left' },
    { key: 'stats', label: 'Stats', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
    { key: 'published_at', label: 'Published', align: 'left' },
];
</script>

<template>
    <Head title="Posts - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Posts</h1>
                    <p class="text-muted-foreground">Manage all your content</p>
                </div>
                <Link href="/admin/posts/create">
                    <Button>
                        <PlusIcon class="h-4 w-4" />
                        Create Post
                    </Button>
                </Link>
            </div>

            <!-- Search Posts -->
            <div class="mb-4">
                <Searchbar
                    v-model="searchQuery"
                    placeholder="Search posts..."
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <!-- Posts Table -->
            <DataTable
                :data="props.posts.data"
                :columns="columns"
                :pagination="props.posts"
                empty-message="No posts found"
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
                    <div class="w-[220px]">
                        <p class="line-clamp-1 font-medium">{{ row.title }}</p>
                        <p class="line-clamp-1 text-sm text-muted-foreground">
                            {{ row.subtitle }}
                        </p>
                    </div>
                </template>

                <!-- Type Cell -->
                <template #cell-type="{ row }">
                    <div class="w-[100px]">
                        <span
                            :class="getTypeColor(row.type)"
                            class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                        >
                            {{ getTypeLabel(row.type) }}
                        </span>
                    </div>
                </template>

                <!-- Stats Cell -->
                <template #cell-stats="{ row }">
                    <div class="flex gap-3 text-sm">
                        <span class="flex items-center gap-1">
                            <svg
                                class="h-4 w-4 text-muted-foreground"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            {{ row.views_count }}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg
                                class="h-4 w-4 text-red-500"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                />
                            </svg>
                            {{ row.likes_count }}
                        </span>
                    </div>
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

                <!-- Published Date Cell -->
                <template #cell-published_at="{ row }">
                    <span class="text-sm text-muted-foreground">
                        {{
                            row.published_at
                                ? new Date(
                                      row.published_at,
                                  ).toLocaleDateString()
                                : '-'
                        }}
                    </span>
                </template>

                <!-- Actions -->
                <template #actions="{ row }">
                    <div class="flex gap-2">
                        <Link :href="`/admin/posts/${row.slug}`">
                            <Button variant="outline">
                                <EyeIcon class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Link :href="`/admin/posts/${row.slug}/edit`">
                            <Button variant="outline">
                                <EditIcon class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button variant="outline" @click="deletePost(row)">
                            <TrashIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
