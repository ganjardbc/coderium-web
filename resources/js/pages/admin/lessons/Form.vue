<script setup lang="ts">
import MediaUploader from '@/components/admin/MediaUploader.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CustomSelect from '@/components/CustomSelect.vue';
import { Switch } from '@/components/ui/switch';
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

interface Module {
    id: number;
    title: string;
}

interface Props {
    lesson?: {
        id: number;
        module_id: number | null;
        title: string;
        content: string;
        lesson_type: string;
        estimated_duration: number;
        is_published: boolean;
        media?: Media[];
    };
    modules: Module[];
    selectedModuleId?: number;
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const form = ref({
    module_id: props.lesson?.module_id?.toString() ?? props.selectedModuleId?.toString() ?? '',
    title: props.lesson?.title || '',
    content: props.lesson?.content || '',
    lesson_type: props.lesson?.lesson_type || 'text',
    estimated_duration: props.lesson?.estimated_duration?.toString() || '30',
    is_published: props.lesson?.is_published ?? false,
    media: props.lesson?.media || ([] as Media[]),
});

const isEditing = !!props.lesson;

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Lessons', href: '/admin/lessons' },
    { title: isEditing ? 'Edit' : 'Create', href: '#' },
];

const moduleOptions = computed(() => [
    { value: '', label: 'No module (standalone lesson)' },
    ...props.modules.map(module => ({
        value: module.id.toString(),
        label: module.title
    }))
]);

const lessonTypeOptions = [
    { value: 'text', label: 'Text Content' },
    { value: 'video', label: 'Video Lesson' },
    { value: 'interactive', label: 'Interactive Content' },
];

const durationOptions = [
    { value: '15', label: '15 minutes' },
    { value: '30', label: '30 minutes' },
    { value: '45', label: '45 minutes' },
    { value: '60', label: '1 hour' },
    { value: '90', label: '1.5 hours' },
    { value: '120', label: '2 hours' },
];

const submit = () => {
    const data = {
        module_id: form.value.module_id ? parseInt(form.value.module_id.toString()) : null,
        title: form.value.title,
        content: form.value.content,
        lesson_type: form.value.lesson_type,
        estimated_duration: parseInt(form.value.estimated_duration.toString()),
        is_published: form.value.is_published,
        media_ids: form.value.media.map((m) => m.id),
    };

    if (isEditing) {
        router.put(`/admin/lessons/${props.lesson?.id}`, data, {
            preserveScroll: true,
        });
    } else {
        router.post('/admin/lessons', data);
    }
};

const cancel = () => {
    window.history.back();
};
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Lesson - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Lesson
                </h1>
                <p class="text-muted-foreground">
                    {{ isEditing ? 'Update' : 'Add a new' }} learning lesson
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
                            placeholder="Enter lesson title"
                            required
                        />
                        <p
                            v-if="errors?.title"
                            class="text-sm text-destructive"
                        >
                            {{ errors.title }}
                        </p>
                    </div>

                    <!-- Content -->
                    <div class="space-y-2">
                        <Label for="content">Content *</Label>
                        <RichTextEditor
                            v-model="form.content"
                            placeholder="Enter lesson content..."
                            class="content-html"
                        />
                        <p class="text-sm text-muted-foreground">
                            Use the toolbar to format your lesson content
                        </p>
                        <p
                            v-if="errors?.content"
                            class="text-sm text-destructive"
                        >
                            {{ errors.content }}
                        </p>
                    </div>

                    <!-- Module Selection -->
                    <div class="space-y-2">
                        <Label for="module_id">Module</Label>
                        <CustomSelect
                            id="module_id"
                            v-model="form.module_id"
                            :options="moduleOptions"
                            placeholder="Select a module"
                            :error="!!errors?.module_id"
                        />
                        <p class="text-sm text-muted-foreground">
                            Assign this lesson to a module or leave as standalone
                        </p>
                        <p
                            v-if="errors?.module_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.module_id }}
                        </p>
                    </div>

                    <!-- Lesson Type -->
                    <div class="space-y-2">
                        <Label for="lesson_type">Lesson Type *</Label>
                        <CustomSelect
                            id="lesson_type"
                            v-model="form.lesson_type"
                            :options="lessonTypeOptions"
                            placeholder="Select lesson type"
                            required
                            :error="!!errors?.lesson_type"
                        />
                        <p
                            v-if="errors?.lesson_type"
                            class="text-sm text-destructive"
                        >
                            {{ errors.lesson_type }}
                        </p>
                    </div>

                    <!-- Estimated Duration -->
                    <div class="space-y-2">
                        <Label for="estimated_duration">Estimated Duration *</Label>
                        <CustomSelect
                            id="estimated_duration"
                            v-model="form.estimated_duration"
                            :options="durationOptions"
                            placeholder="Select duration"
                            required
                            :error="!!errors?.estimated_duration"
                        />
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
                            accept="image/*,video/*,audio/*,.pdf"
                            :multiple="true"
                            :max-size="100"
                        />
                        <p class="text-sm text-muted-foreground">
                            Upload images, videos, audio files, or PDFs for this lesson (max 100MB each)
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
                                Make this lesson visible to students
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
                            {{ isEditing ? 'Update' : 'Create' }} Lesson
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
