<script setup lang="ts">
import { Label } from '@/components/ui/label';

interface Option {
    value: string | number;
    label: string;
}

interface Props {
    id?: string;
    label?: string;
    modelValue: string | number;
    options: Option[];
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    error?: boolean;
}

interface Emits {
    (e: 'update:modelValue', value: string | number): void;
}

const props = withDefaults(defineProps<Props>(), {
    id: '',
    disabled: false,
    required: false,
    error: false,
});

const emit = defineEmits<Emits>();

const handleChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    emit('update:modelValue', target.value);
};
</script>

<template>
    <div class="space-y-2">
        <Label v-if="label" :for="id || 'custom-select'">
            {{ label }}
        </Label>
        <select
            :id="id || 'custom-select'"
            :value="modelValue"
            :disabled="disabled"
            :required="required"
            @change="handleChange"
            :class="[
                'flex min-h-[36px] w-full rounded-md border bg-background dark:bg-input/30 px-3 py-1 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
                error ? 'border-destructive' : 'border-input'
            ]"
        >
            <option value="">
                {{ placeholder }}
            </option>
            <option
                v-for="option in props.options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
    </div>
</template>
