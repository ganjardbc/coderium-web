<script setup lang="ts" generic="T extends Record<string, any>">
import { Link } from '@inertiajs/vue3';
import { TrashIcon, EditIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/Pagination.vue';

export interface Column<T> {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'center' | 'right';
    render?: (row: T) => string | number | boolean;
    class?: string;
}

export interface Action<T> {
    label: string;
    onClick?: (row: T) => void;
    href?: (row: T) => string;
    variant?: 'default' | 'outline' | 'destructive' | 'ghost' | 'link';
    show?: (row: T) => boolean;
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
    actions?: Action<T>[];
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
        case 'center': return 'text-center';
        case 'right': return 'text-right';
        default: return 'text-left';
    }
};

const getNumberByIndexAndPagination = (index: number) => {
    if (props.pagination) {
        return (props.pagination.current_page - 1) * props.pagination.per_page + index + 1;
    }
    return index + 1;
};
</script>

<template>
    <div class="rounded-lg border bg-card">
        <div class="overflow-x-auto">
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
                                column.sortable ? 'cursor-pointer hover:bg-muted/50' : '',
                                column.class
                            ]"
                            @click="handleSort(column)"
                        >
                            {{ column.label }}
                        </th>
                        <th v-if="actions && actions.length > 0" class="px-6 py-3 text-right text-sm font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="loading" class="hover:bg-muted/50">
                        <td :colspan="columns.length + (actions ? 1 : 0)" class="px-6 py-8 text-center text-muted-foreground">
                            Loading...
                        </td>
                    </tr>
                    <tr v-else-if="data.length === 0" class="hover:bg-muted/50">
                        <td :colspan="columns.length + (actions ? 1 : 0)" class="px-6 py-8 text-center text-muted-foreground">
                            {{ emptyMessage }}
                        </td>
                    </tr>
                    <tr v-else v-for="(row, index) in data" :key="index" class="hover:bg-muted/50">
                        <td class="px-6 py-4 text-left text-sm font-medium">
                            {{ getNumberByIndexAndPagination(index) }}
                        </td>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="['px-6 py-4', getAlignClass(column.align), column.class]"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" :value="getCellValue(row, column)">
                                {{ getCellValue(row, column) }}
                            </slot>
                        </td>
                        <td v-if="actions && actions.length > 0" class="px-6 py-4">
                            <div class="flex justify-end gap-2">
                                <template v-for="(action, actionIndex) in actions" :key="actionIndex">
                                    <Link
                                        v-if="action.href && (!action.show || action.show(row))"
                                        :href="action.href(row)"
                                    >
                                        <Button :variant="action.variant">
                                            <slot :name="`action-${actionIndex}`" :row="row" :action="action">
                                                <EditIcon class="h-4 w-4" />
                                            </slot>
                                        </Button>
                                    </Link>

                                    <Button
                                        v-else-if="action.onClick && (!action.show || action.show(row))"
                                        @click="action.onClick(row)"
                                        :variant="action.variant"
                                    >
                                        <slot :name="`action-${actionIndex}`" :row="row" :action="action">
                                            <TrashIcon class="h-4 w-4" />
                                        </slot>
                                    </Button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="border-t px-6 py-4">
            <Pagination
                :current-page="pagination.current_page"
                :last-page="pagination.last_page"
                :links="pagination.links"
            />
        </div>
    </div>
</template>
