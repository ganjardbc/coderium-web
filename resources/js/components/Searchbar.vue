<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebounceFn } from '@/lib/utils';
import { SearchIcon, XIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    modelValue?: string;
    placeholder?: string;
    showClearButton?: boolean;
}

interface Emits {
    (e: 'update:modelValue', value: string): void;
    (e: 'search', value: string): void;
    (e: 'clear'): void;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Search...',
    showClearButton: true,
});

const emit = defineEmits<Emits>();

const searchQuery = ref(props.modelValue);

watch(
    () => props.modelValue,
    (newValue) => {
        searchQuery.value = newValue;
    },
);

const debouncedHandleInput = useDebounceFn(() => {
    handleSearch();
}, 500);

const handleInput = () => {
    emit('update:modelValue', searchQuery.value);
    debouncedHandleInput();
};

const handleSearch = () => {
    emit('search', searchQuery.value);
};

const handleClear = () => {
    searchQuery.value = '';
    emit('update:modelValue', '');
    emit('clear');
};
</script>

<template>
    <form class="flex gap-2" @submit.prevent="handleSearch">
        <div class="relative flex-1">
            <Input
                type="text"
                :placeholder="placeholder"
                v-model="searchQuery"
                required
                @input="handleInput"
            />
            <Button
                v-if="showClearButton && searchQuery"
                @click="handleClear"
                variant="outline"
                size="sm"
                class="absolute top-1/2 right-2 h-[24px] w-[24px] -translate-y-1/2 rounded-full"
            >
                <XIcon class="h-4 w-4" />
            </Button>
        </div>
        <Button type="submit" variant="outline">
            <SearchIcon class="h-4 w-4" />
            Search
        </Button>
    </form>
</template>
