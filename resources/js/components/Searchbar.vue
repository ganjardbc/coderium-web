<script setup lang="ts">
import { ref, watch } from 'vue';
import { XIcon, SearchIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebounceFn } from '@/lib/utils';

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

watch(() => props.modelValue, (newValue) => {
    searchQuery.value = newValue;
});

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

const handleKeyup = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        handleSearch();
    }
};
</script>

<template>
    <div class="flex gap-2">
        <div class="relative flex-1">
            <Input
                type="text"
                :placeholder="placeholder"
                v-model="searchQuery"
                @input="handleInput"
                @keyup="handleKeyup"
            />
            <Button
                v-if="showClearButton && searchQuery"
                @click="handleClear"
                variant="outline"
                size="sm"
                class="absolute right-2 top-1/2 -translate-y-1/2 w-[24px] h-[24px] rounded-full"
            >
                <XIcon class="h-4 w-4" />
            </Button>
        </div>
        <Button @click="handleSearch" variant="outline">
            <SearchIcon class="h-4 w-4" />
            Search
        </Button>
    </div>
</template>
