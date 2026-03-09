<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const { appearance, updateAppearance } = useAppearance();

const getNextTheme = () => {
    if (appearance.value === 'light') return 'dark';
    if (appearance.value === 'dark') return 'system';
    return 'light';
};

const toggleTheme = () => {
    updateAppearance(getNextTheme());
};

const getCurrentIcon = () => {
    if (appearance.value === 'light') return Sun;
    if (appearance.value === 'dark') return Moon;
    return Monitor;
};

const getTitle = () => {
    if (appearance.value === 'light') return 'Switch to dark mode';
    if (appearance.value === 'dark') return 'Switch to system mode';
    return 'Switch to light mode';
};
</script>

<template>
    <Button
        @click="toggleTheme"
        variant="outline"
        size="lg"
        class="w-[40px] h-[40px]"
        :title="getTitle()"
    >
        <component :is="getCurrentIcon()" class="h-5 w-5" />
    </Button>
</template>
