<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    errors: string[] | Record<string, string | string[]>;
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Something went wrong.',
});

const uniqueErrors = computed(() => {
    if (Array.isArray(props.errors)) {
        return Array.from(new Set(props.errors));
    }

    // Handle object format (Inertia form errors)
    const errorMessages: string[] = [];
    for (const [field, messages] of Object.entries(props.errors)) {
        if (Array.isArray(messages)) {
            errorMessages.push(...messages);
        } else if (typeof messages === 'string') {
            errorMessages.push(messages);
        }
    }

    return Array.from(new Set(errorMessages));
});
</script>

<template>
    <Alert variant="destructive">
        <AlertCircle class="size-4" />
        <AlertTitle>{{ title }}</AlertTitle>
        <AlertDescription>
            <ul class="list-inside list-disc text-sm">
                <li v-for="(error, index) in uniqueErrors" :key="index">
                    {{ error }}
                </li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
