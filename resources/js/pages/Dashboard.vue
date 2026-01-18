<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col items-center justify-center gap-4 overflow-x-auto rounded-xl p-4"
        >
            <h1 class="text-center text-3xl font-bold">Dashboard</h1>
            <p class="text-center text-muted-foreground">
                Overview of your Coderium content
            </p>
        </div>
    </AppLayout>
</template>
