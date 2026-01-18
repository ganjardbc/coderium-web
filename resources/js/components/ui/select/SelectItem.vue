<script setup lang="ts">
import { inject, computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    value: string;
    class?: string;
}

const props = defineProps<Props>();

const select = inject('select') as any;

const isSelected = computed(() => select.selectedValue.value === props.value);

const handleClick = () => {
    select.selectValue(props.value);
};
</script>

<template>
    <div
        :class="cn(
            'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
            isSelected && 'bg-accent text-accent-foreground',
            props.class
        )"
        @click="handleClick"
    >
        <slot />
    </div>
</template>
