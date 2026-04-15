<script setup lang="ts">
import { inject, computed } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

interface Props {
    class?: string;
}

const props = defineProps<Props>();

const select = inject('select') as any;
</script>

<template>
    <button
        type="button"
        :class="cn(
            'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1',
            props.class
        )"
        :disabled="select.disabled.value"
        @click="select.isOpen.value = !select.isOpen.value"
    >
        <slot />
        <ChevronDown class="h-4 w-4 opacity-50" />
    </button>
</template>
