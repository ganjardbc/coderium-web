<script setup lang="ts">
import AlertError from '@/components/AlertError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CustomSelect from '@/components/CustomSelect.vue';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Level {
    id?: number;
    track_id: number;
    title: string;
    description: string;
    difficulty: 'beginner' | 'intermediate' | 'advanced';
    order_index: number;
    is_published: boolean;
}

interface Track {
    id: number;
    title: string;
    slug: string;
}

interface Props {
    level?: Level;
    track?: Track;
    maxOrderIndex?: number;
}

const props = defineProps<Props>();

const isEditing = computed(() => !!props.level?.id);

const form = useForm({
    track_id: props.track?.id || 0,
    title: props.level?.title || '',
    description: props.level?.description || '',
    difficulty: props.level?.difficulty || 'beginner',
    order_index: props.level?.order_index || (props.maxOrderIndex || 0) + 1,
    is_published: props.level?.is_published || false,
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Levels', href: '/admin/classroom/levels' },
    { title: isEditing.value ? 'Edit Level' : 'Create Level', href: '#' },
];

const submit = () => {
    if (isEditing.value) {
        form.put(`/admin/classroom/levels/${props.level!.id}`, {
            preserveScroll: true,
        });
    } else {
        form.post('/admin/classroom/levels', {
            preserveScroll: true,
        });
    }
};

const cancel = () => {
    router.visit('/admin/classroom/levels');
};

const orderOptions = computed(() => {
    const options = [];
    const maxIndex = props.maxOrderIndex || 0;
    for (let i = 1; i <= maxIndex + 1; i++) {
        options.push({ value: i, label: `Position ${i}` });
    }
    return options;
});

const difficultyOptions = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'advanced', label: 'Advanced' },
];
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Level - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Level
                </h1>
                <p class="text-muted-foreground">
                    {{
                        isEditing
                            ? 'Update level information'
                            : 'Create a new level in'
                    }}
                    "{{ track?.title || 'Track' }}"
                </p>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <AlertError v-if="form.hasErrors" :errors="form.errors" />

                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                        <CardDescription>
                            Enter the details for this level
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="title">Title *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="Enter level title"
                                :class="{
                                    'border-destructive': form.errors.title,
                                }"
                            />
                            <p
                                v-if="form.errors.title"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description *</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Describe what learners will learn in this level"
                                rows="4"
                                :class="{
                                    'border-destructive':
                                        form.errors.description,
                                }"
                            />
                            <p
                                v-if="form.errors.description"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle>Settings</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <CustomSelect
                                id="difficulty"
                                label="Difficulty"
                                v-model="form.difficulty"
                                :options="difficultyOptions"
                                placeholder="Select difficulty"
                            />

                            <CustomSelect
                                id="order_index"
                                label="Position"
                                v-model="form.order_index"
                                :options="orderOptions"
                                placeholder="Select position"
                            />
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch
                                id="is_published"
                                v-model="form.is_published"
                            />
                            <Label for="is_published">Published</Label>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex gap-4">
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update Level'
                                  : 'Create Level'
                        }}
                    </Button>
                    <Button type="button" variant="outline" @click="cancel">
                        Cancel
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
