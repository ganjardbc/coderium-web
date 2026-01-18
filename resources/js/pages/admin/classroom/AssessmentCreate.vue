<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import CustomSelect from '@/components/CustomSelect.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Module {
    id: number;
    title: string;
    level: {
        id: number;
        title: string;
        track: {
            id: number;
            title: string;
            slug: string;
        };
    };
}

interface Props {
    modules: Module[];
}

const props = defineProps<Props>();

const selectedModuleId = ref<number | null>(null);

const moduleOptions = computed(() =>
    props.modules.map(module => ({
        value: module.id,
        label: `${module.level.track.title} → ${module.level.title} → ${module.title}`
    }))
);

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Assessments', href: '/admin/classroom/assessments' },
    { title: 'Create Assessment', href: '#' },
];

const proceed = () => {
    if (selectedModuleId.value) {
        router.visit(`/admin/classroom/assessments/create?module_id=${selectedModuleId.value}`);
    }
};
</script>

<template>
    <Head title="Create Assessment - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Create Assessment</h1>
                <p class="text-muted-foreground">
                    First, select the module where you want to create the assessment
                </p>
            </div>

            <div class="max-w-md">
                <Card>
                    <CardHeader>
                        <CardTitle>Select Module</CardTitle>
                        <CardDescription>
                            Choose the module where this assessment will be attached
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <CustomSelect
                            id="module"
                            label="Module"
                            v-model="selectedModuleId"
                            :options="moduleOptions"
                            placeholder="Select a module"
                        />

                        <Button
                            @click="proceed"
                            :disabled="!selectedModuleId"
                            class="w-full"
                        >
                            Continue
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
