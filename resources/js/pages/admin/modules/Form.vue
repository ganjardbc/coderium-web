<script setup lang="ts">
import MediaUploader from '@/components/admin/MediaUploader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Media {
    id: number;
    name: string;
    url: string;
    type: 'image' | 'video' | 'file';
    mime_type: string;
    size: number;
}

interface Props {
    module?: {
        id: number;
        title: string;
        description: string;
        estimated_duration: number | null;
        is_published: boolean;
        media?: Media[];
    };
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const form = ref({
    title: props.module?.title || '',
    description: props.module?.description || '',
    estimated_duration: props.module?.estimated_duration || null,
    is_published: props.module?.is_published ?? false,
    media: props.module?.media || ([] as Media[]),
});

const isEditing = !!props.module;

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Modules', href: '/admin/modules' },
    { title: isEditing ? 'Edit' : 'Create', href: '#' },
];

const durationHours = computed({
    get: () => {
        if (!form.value.estimated_duration) return '';
        return Math.floor(form.value.estimated_duration / 60).toString();
    },
    set: (value: string) => {
        const hours = parseInt(value) || 0;
        const minutes = durationMinutes.value ? parseInt(durationMinutes.value) : 0;
        form.value.estimated_duration = hours > 0 || minutes > 0 ? (hours * 60) + minutes : null;
    }
});

const durationMinutes = computed({
    get: () => {
        if (!form.value.estimated_duration) return '';
        return (form.value.estimated_duration % 60).toString();
    },
    set: (value: string) => {
        const minutes = parseInt(value) || 0;
        const hours = durationHours.value ? parseInt(durationHours.value) : 0;
        form.value.estimated_duration = hours > 0 || minutes > 0 ? (hours * 60) + minutes : null;
    }
});

const submit = () => {
    const data = {
        title: form.value.title,
        description: form.value.description,
        estimated_duration: form.value.estimated_duration,
        is_published: form.value.is_published,
        media_ids: form.value.media.map((m) => m.id),
    };

    if (isEditing) {
        router.put(`/admin/modules/${props.module?.id}`, data, {
            preserveScroll: true,
        });
    } else {
        router.post('/admin/modules', data);
    }
};

const cancel = () => {
    window.history.back();
};
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Module - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Module
                </h1>
                <p class="text-muted-foreground">
                    {{ isEditing ? 'Update' : 'Add a new' }} learning module
                </p>
            </div>

            <div class="w-full">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 rounded-lg border bg-card p-6"
                >
                    <!-- Title -->
                    <div class="space-y-2">
                        <Label for="title">Title *</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            type="text"
                            placeholder="Enter module title"
                            required
                        />
                        <p
                            v-if="errors?.title"
                            class="text-sm text-destructive"
                        >
                            {{ errors.title }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Describe what learners will learn in this module"
                            rows="4"
                        />
                        <p
                            v-if="errors?.description"
                            class="text-sm text-destructive"
                        >
                            {{ errors.description }}
                        </p>
                    </div>

                    <!-- Estimated Duration -->
                    <div class="space-y-2">
                        <Label>Estimated Duration</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Input
                                    v-model="durationHours"
                                    type="number"
                                    placeholder="Hours"
                                    min="0"
                                    max="23"
                                />
                            </div>
                            <div>
                                <Input
                                    v-model="durationMinutes"
                                    type="number"
                                    placeholder="Minutes"
                                    min="0"
                                    max="59"
                                />
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Leave empty if duration is not known
                        </p>
                        <p
                            v-if="errors?.estimated_duration"
                            class="text-sm text-destructive"
                        >
                            {{ errors.estimated_duration }}
                        </p>
                    </div>

                    <!-- Media Upload -->
                    <div class="space-y-2">
                        <Label>Media</Label>
                        <MediaUploader
                            v-model="form.media"
                            accept="image/*,video/*"
                            :multiple="true"
                            :max-size="50"
                        />
                        <p class="text-sm text-muted-foreground">
                            Upload images or videos for this module (max 50MB each)
                        </p>
                        <p
                            v-if="errors?.media"
                            class="text-sm text-destructive"
                        >
                            {{ errors.media }}
                        </p>
                    </div>

                    <!-- Published Status -->
                    <div
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="space-y-0.5">
                            <Label>Published</Label>
                            <p class="text-sm text-muted-foreground">
                                Make this module visible to students
                            </p>
                        </div>
                        <Switch v-model="form.is_published" />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="cancel">
                            Cancel
                        </Button>
                        <Button type="submit">
                            {{ isEditing ? 'Update' : 'Create' }} Module
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
