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

interface Level {
    id: number;
    title: string;
    track: {
        id: number;
        title: string;
        slug: string;
    };
}

interface Props {
    levels: Level[];
}

const props = defineProps<Props>();

const selectedLevelId = ref<number | null>(null);

const levelOptions = computed(() =>
    props.levels.map(level => ({
        value: level.id,
        label: `${level.track.title} - ${level.title}`
    }))
);

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Modules', href: '/admin/classroom/modules' },
    { title: 'Create Module', href: '#' },
];

const proceed = () => {
    if (selectedLevelId.value) {
        router.visit(`/admin/classroom/modules/create?level_id=${selectedLevelId.value}`);
    }
};
</script>

<template>
    <Head title="Create Module - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Create Module</h1>
                <p class="text-muted-foreground">
                    First, select the level where you want to create the module
                </p>
            </div>

            <div class="max-w-md">
                <Card>
                    <CardHeader>
                        <CardTitle>Select Level</CardTitle>
                        <CardDescription>
                            Choose the level where this module will be added
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <CustomSelect
                            id="level"
                            label="Level"
                            v-model="selectedLevelId"
                            :options="levelOptions"
                            placeholder="Select a level"
                        />

                        <Button
                            @click="proceed"
                            :disabled="!selectedLevelId"
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
