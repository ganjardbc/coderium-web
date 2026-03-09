<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    currentPage: number;
    lastPage: number;
    links: PaginationLink[];
    preserveScroll?: boolean;
}

withDefaults(defineProps<Props>(), {
    preserveScroll: true,
});
</script>

<template>
    <div v-if="lastPage > 1" class="flex items-center justify-center gap-2">
        <template v-for="(link, index) in links" :key="index">
            <template v-if="link">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'min-w-[40px] rounded-md border px-4 py-2 text-sm font-medium transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-accent',
                    ]"
                    :preserve-scroll="preserveScroll"
                >
                    <span v-html="link.label" />
                </Link>
                <span
                    v-else
                    :class="[
                        'min-w-[40px] rounded-md border px-4 py-2 text-sm font-medium opacity-50',
                    ]"
                    v-html="link.label"
                />
            </template>
        </template>
    </div>
</template>
