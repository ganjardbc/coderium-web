<script setup lang="ts" generic="T extends Record<string, any>">
import Pagination from '@/components/Pagination.vue';

export interface Column<T> {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'center' | 'right';
    render?: (row: T) => string | number | boolean;
    class?: string;
}

interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    data: T[];
    columns: Column<T>[];
    pagination?: PaginationData;
    loading?: boolean;
    emptyMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    emptyMessage: 'No data available',
});

const emit = defineEmits<{
    sort: [key: string];
}>();

const handleSort = (column: Column<T>) => {
    if (column.sortable) {
        emit('sort', column.key);
    }
};

const getCellValue = (row: T, column: Column<T>) => {
    if (column.render) {
        return column.render(row);
    }
    return row[column.key];
};

const getAlignClass = (align?: 'left' | 'center' | 'right') => {
    switch (align) {
        case 'center':
            return 'text-center';
        case 'right':
            return 'text-right';
        default:
            return 'text-left';
    }
};

const getNumberByIndexAndPagination = (index: number) => {
    if (props.pagination) {
        return (
            (props.pagination.current_page - 1) * props.pagination.per_page +
            index +
            1
        );
    }
    return index + 1;
};
</script>

<template>
    <div class="rounded-lg border bg-card">
        <div class="h-[520px] overflow-y-auto overflow-x-auto">
            <table class="w-full">
                <thead class="border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium">
                            No
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                'px-6 py-3 text-sm font-medium',
                                getAlignClass(column.align),
                                column.sortable
                                    ? 'cursor-pointer hover:bg-muted/50'
                                    : '',
                                column.class,
                            ]"
                            @click="handleSort(column)"
                        >
                            {{ column.label }}
                        </th>
                        <th
                            v-if="$slots['actions']"
                            class="px-6 py-3 text-right text-sm font-medium"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="loading" class="hover:bg-muted/50">
                        <td
                            :colspan="columns?.length + ($slots['actions'] ? 1 : 0)"
                            class="px-6 py-8 text-center text-muted-foreground"
                        >
                            Loading...
                        </td>
                    </tr>
                    <tr v-else-if="data?.length === 0" class="hover:bg-muted/50">
                        <td
                            :colspan="columns?.length + ($slots['actions'] ? 1 : 0)"
                            class="px-6 py-8 text-center text-muted-foreground"
                        >
                            {{ emptyMessage }}
                        </td>
                    </tr>
                    <tr
                        v-else
                        v-for="(row, index) in data"
                        :key="index"
                        class="hover:bg-muted/50"
                    >
                        <td class="px-6 py-4 text-left text-sm font-medium">
                            {{ getNumberByIndexAndPagination(index) }}
                        </td>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                'px-6 py-4',
                                getAlignClass(column.align),
                                column.class,
                            ]"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="getCellValue(row, column)"
                            >
                                {{ getCellValue(row, column) }}
                            </slot>
                        </td>
                        <td v-if="$slots['actions']" class="px-6 py-4">
                            <slot name="actions" :row="row"></slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="pagination && pagination.last_page > 1"
            class="border-t px-6 py-4"
        >
            <Pagination
                :current-page="pagination.current_page"
                :last-page="pagination.last_page"
                :links="pagination.links"
            />
        </div>
    </div>
</template>
