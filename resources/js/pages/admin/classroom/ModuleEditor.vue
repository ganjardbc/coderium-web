<script setup lang="ts">
import MediaUploader from '@/components/admin/MediaUploader.vue';
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

interface Module {
    id?: number;
    level_id: number;
    title: string;
    description: string;
    order_index: number;
    estimated_duration: number | null;
    is_published: boolean;
    media?: any[];
}

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
    module?: Module;
    level: Level;
    maxOrderIndex: number;
}

const props = defineProps<Props>();

const isEditing = computed(() => !!props.module?.id);

const form = useForm({
    level_id: props.level.id,
    title: props.module?.title || '',
    description: props.module?.description || '',
    order_index: props.module?.order_index || props.maxOrderIndex + 1,
    estimated_duration: props.module?.estimated_duration || null,
    is_published: props.module?.is_published || false,
    media: props.module?.media || [],
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Modules', href: '/admin/classroom/modules' },
    { title: isEditing.value ? 'Edit Module' : 'Create Module', href: '#' },
];

const submit = () => {
    if (isEditing.value) {
        form.put(`/admin/classroom/modules/${props.module!.id}`, {
            preserveScroll: true,
        });
    } else {
        form.post('/admin/classroom/modules', {
            preserveScroll: true,
        });
    }
};

const cancel = () => {
    router.visit('/admin/classroom/modules');
};

const orderOptions = computed(() => {
    const options = [];
    for (let i = 1; i <= props.maxOrderIndex + 1; i++) {
        options.push({ value: i, label: `Position ${i}` });
    }
    return options;
});
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Module - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Module
                </h1>
                <p class="text-muted-foreground">
                    {{
                        isEditing
                            ? 'Update module information'
                            : 'Create a new module in'
                    }}
                    "{{ level.title }}"
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <AlertError v-if="form.hasErrors" :errors="form.errors" />

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Basic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                                <CardDescription>
                                    Enter the details for this module
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="title">Title *</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="Enter module title"
                                        :class="{
                                            'border-destructive':
                                                form.errors.title,
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
                                    <Label for="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Describe what learners will learn in this module"
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

                        <!-- Media -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Media</CardTitle>
                                <CardDescription>
                                    Upload images or videos for this module
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <MediaUploader
                                    v-model="form.media"
                                    accept="image/*,video/*"
                                    :multiple="true"
                                    :max-size="50"
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Settings -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Settings</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <CustomSelect
                                    id="order_index"
                                    label="Position"
                                    v-model="form.order_index"
                                    :options="orderOptions"
                                    placeholder="Select position"
                                />

                                <div class="space-y-2">
                                    <Label for="estimated_duration"
                                        >Estimated Duration (minutes)</Label
                                    >
                                    <Input
                                        id="estimated_duration"
                                        v-model.number="form.estimated_duration"
                                        type="number"
                                        placeholder="30"
                                        min="0"
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
                        <Card>
                            <CardContent class="pt-6">
                                <div class="flex flex-col gap-2">
                                    <Button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="w-full"
                                    >
                                        {{
                                            form.processing
                                                ? 'Saving...'
                                                : isEditing
                                                  ? 'Update Module'
                                                  : 'Create Module'
                                        }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="cancel"
                                        class="w-full"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
