<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    isBack?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    isBack: false,
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :is-back="isBack">
        <slot />
    </AppLayout>
</template>
