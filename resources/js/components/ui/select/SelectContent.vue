<script setup lang="ts">
import { inject, onMounted, onUnmounted, ref } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    class?: string;
}

const props = defineProps<Props>();

const select = inject('select') as any;
const contentRef = ref<HTMLElement>();

const handleClickOutside = (event: MouseEvent) => {
    if (contentRef.value && !contentRef.value.contains(event.target as Node)) {
        select.isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div
        v-if="select.isOpen.value"
        ref="contentRef"
        :class="cn(
            'absolute top-full z-50 mt-1 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md',
            props.class
        )"
    >
        <slot />
    </div>
</template>
