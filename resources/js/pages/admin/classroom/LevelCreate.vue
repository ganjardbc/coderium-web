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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Track {
    id: number;
    title: string;
    slug: string;
}

interface Props {
    tracks: Track[];
}

const props = defineProps<Props>();

const selectedTrackId = ref<string>('');

// Transform tracks data for the new CustomSelect component
const trackOptions = computed(() =>
    props.tracks.map(track => ({
        value: track.id.toString(),
        label: track.title
    }))
);

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Levels', href: '/admin/classroom/levels' },
    { title: 'Create Level', href: '#' },
];

const proceed = () => {
    if (selectedTrackId.value) {
        router.visit(`/admin/classroom/levels/create?track_id=${selectedTrackId.value}`);
    }
};
</script>

<template>
    <Head title="Create Level - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Create Level</h1>
                <p class="text-muted-foreground">
                    First, select the track where you want to create the level
                </p>
            </div>

            <div class="max-w-md">
                <Card>
                    <CardHeader>
                        <CardTitle>Select Track</CardTitle>
                        <CardDescription>
                            Choose the track where this level will be added
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <CustomSelect
                            id="track"
                            label="Track"
                            v-model="selectedTrackId"
                            :options="trackOptions"
                            placeholder="Select a track..."
                            required
                        />

                        <Button
                            @click="proceed"
                            :disabled="!selectedTrackId"
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
