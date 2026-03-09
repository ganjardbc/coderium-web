<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DataTable, { type Action, type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus, BookOpen } from 'lucide-vue-next';

interface Level {
    id: number;
    title: string;
    description: string;
    order_index: number;
    is_published: boolean;
    modules_count: number;
    created_at: string;
    updated_at: string;
}

interface Track {
    id: number;
    title: string;
    slug: string;
}

interface Props {
    track: Track;
    levels: {
        data?: Level[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total?: number;
        from?: number | null;
        to?: number | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Tracks', href: '/admin/tracks' },
    { title: props.track.title, href: `/admin/tracks/${props.track.slug}` },
    { title: 'Levels', href: `/admin/tracks/${props.track.slug}/levels` },
];

const deleteLevel = (level: Level) => {
    if (confirm(`Are you sure you want to delete "${level.title}"?`)) {
        router.delete(`/admin/levels/${level.id}`, {
            preserveScroll: true,
        });
    }
};

const moveLevel = (level: Level, direction: 'up' | 'down') => {
    router.put(`/admin/levels/${level.id}/move`, {
        direction: direction,
    }, {
        preserveScroll: true,
    });
};

const columns: Column<Level>[] = [
    { key: 'order_index', label: 'Order', align: 'left' },
    { key: 'title', label: 'Level', align: 'left' },
    { key: 'modules_count', label: 'Modules', align: 'left' },
    { key: 'is_published', label: 'Status', align: 'left' },
];

const actions: Action<Level>[] = [
    {
        label: 'View',
        href: (level) => `/admin/levels/${level.id}`,
        variant: 'outline',
    },
    {
        label: 'Edit',
        href: (level) => `/admin/levels/${level.id}/edit`,
        variant: 'outline',
    },
    {
        label: 'Modules',
        href: (level) => `/admin/levels/${level.id}/modules`,
        variant: 'outline',
    },
    {
        label: 'Move Up',
        onClick: (level) => moveLevel(level, 'up'),
        variant: 'outline',
    },
    {
        label: 'Move Down',
        onClick: (level) => moveLevel(level, 'down'),
        variant: 'outline',
    },
    {
        label: 'Delete',
        onClick: (level) => deleteLevel(level),
        variant: 'outline',
    },
];
</script>

<template>
    <Head :title="`${track.title} Levels - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ track.title }} - Levels</h1>
                    <p class="text-muted-foreground">
                        Manage the learning levels for this track
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="`/admin/tracks/${track.slug}`">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Track
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/admin/levels/create?track_id=${track.id}`">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Level
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Track Levels</CardTitle>
                    <CardDescription>
                        Levels are ordered learning sections within the track. Students progress through levels sequentially.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :data="props.levels.data || []"
                        :columns="columns"
                        :actions="actions"
                        :pagination="props.levels"
                        empty-message="No levels found"
                        empty-description="Create your first level to start building the track structure."
                    >
                        <template #cell-order_index="{ row }">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm bg-muted px-2 py-1 rounded">
                                    {{ row.order_index }}
                                </span>
                            </div>
                        </template>

                        <template #cell-title="{ row }">
                            <div class="min-w-[200px]">
                                <div class="font-medium">{{ row.title }}</div>
                                <div class="text-sm text-muted-foreground line-clamp-2">
                                    {{ row.description }}
                                </div>
                            </div>
                        </template>

                        <template #cell-modules_count="{ row }">
                            <div class="flex items-center gap-1">
                                <BookOpen class="h-4 w-4" />
                                {{ row.modules_count }}
                            </div>
                        </template>

                        <template #cell-is_published="{ row }">
                            <span
                                :class="row.is_published
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                                class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                            >
                                {{ row.is_published ? 'Published' : 'Draft' }}
                            </span>
                        </template>
                    </DataTable>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
