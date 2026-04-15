<script setup lang="ts">
import { ref, provide, computed } from 'vue';

interface Props {
    modelValue?: string;
    disabled?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const selectedValue = computed({
    get: () => props.modelValue || '',
    set: (value: string) => emit('update:modelValue', value),
});

const selectValue = (value: string) => {
    selectedValue.value = value;
    isOpen.value = false;
};

provide('select', {
    isOpen,
    selectedValue,
    selectValue,
    disabled: computed(() => props.disabled),
});
</script>

<template>
    <div class="relative">
        <slot />
    </div>
</template>
