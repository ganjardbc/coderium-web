<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { XIcon } from 'lucide-vue-next';

interface FilterOption {
    value: string;
    label: string;
    icon?: any;
    count?: number;
}

interface FilterSection {
    key: string;
    title: string;
    options: FilterOption[];
    selectedValue: string;
}

interface Props {
    sections: FilterSection[];
    showFilters: boolean;
    hasActiveFilters: boolean;
    showCounts?: boolean;
}

interface Emits {
    (e: 'update-filter', key: string, value: string): void;
    (e: 'clear-filters'): void;
}

const props = withDefaults(defineProps<Props>(), {
    showCounts: true,
});

const emit = defineEmits<Emits>();

const handleFilterUpdate = (key: string, value: string) => {
    emit('update-filter', key, value);
};

const handleClearFilters = () => {
    emit('clear-filters');
};
</script>

<template>
    <aside
        class="h-fit flex-1 rounded-lg border p-4"
        :class="[
            'space-y-4',
            props.showFilters ? 'block' : 'hidden md:block',
        ]"
    >
        <div class="flex items-center justify-between border-b pb-4">
            <div class="text-md font-semibold">Filters</div>

            <Button
                v-if="props.hasActiveFilters"
                @click="handleClearFilters"
                variant="outline"
                size="sm"
                class="rounded-full"
            >
                <XIcon class="h-4 w-4" />
                Clear
            </Button>
        </div>

        <!-- Filter Sections -->
        <div
            v-for="section in props.sections"
            :key="section.key"
            class="space-y-2"
        >
            <h3 class="mb-3 text-sm font-semibold text-muted-foreground">
                {{ section.title }}
            </h3>
            <div class="space-y-2">
                <Button
                    v-for="option in section.options"
                    :key="option.value"
                    @click="handleFilterUpdate(section.key, option.value)"
                    :variant="
                        section.selectedValue === option.value
                            ? 'default'
                            : 'ghost'
                    "
                    class="w-full justify-between"
                >
                    <div class="flex items-center gap-2">
                        <component
                            v-if="option.icon"
                            :is="option.icon"
                            class="h-4 w-4"
                        />
                        <span>{{ option.label }}</span>
                    </div>
                    <span
                        v-if="props.showCounts && option.count !== undefined"
                        class="text-xs opacity-75"
                    >
                        {{ option.count }}
                    </span>
                </Button>
            </div>
        </div>
    </aside>
</template>
