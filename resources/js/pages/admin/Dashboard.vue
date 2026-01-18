<script setup lang="ts">
import DashboardAnalytics from '@/components/admin/DashboardAnalytics.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Props {
    analytics: any;
}

defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Dashboard', href: '/admin/dashboard' },
];
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Dashboard</h1>
                <p class="text-muted-foreground">
                    Overview of your Coderium content
                </p>
            </div>

            <DashboardAnalytics :analytics="analytics" />
        </div>
    </AppLayout>
</template>
