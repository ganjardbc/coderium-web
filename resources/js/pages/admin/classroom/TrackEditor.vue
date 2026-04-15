<script setup lang="ts">
import MediaUploader from '@/components/admin/MediaUploader.vue';
import CustomSelect from '@/components/CustomSelect.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Track {
    id?: number;
    title: string;
    description: string;
    slug: string;
    is_premium: boolean;
    price: number | null;
    is_published: boolean;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration: number | null;
    instructor_id: number | null;
    media?: any[];
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
}

interface Props {
    track?: Track;
    instructors?: Array<{ id: number; name: string; email: string }>;
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const isEditing = computed(() => !!props.track?.id);

const form = ref({
    title: props.track?.title || '',
    description: props.track?.description || '',
    slug: props.track?.slug || '',
    is_premium: props.track?.is_premium ?? false,
    price: props.track?.price || null,
    is_published: props.track?.is_published ?? false,
    difficulty_level: props.track?.difficulty_level || 'beginner',
    estimated_duration: props.track?.estimated_duration || null,
    instructor_id: props.track?.instructor_id || null,
    media: props.track?.media || [],
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Tracks', href: '/admin/classroom/tracks' },
    { title: isEditing.value ? 'Edit Track' : 'Create Track', href: '#' },
];

const generateSlug = () => {
    if (form.value.title) {
        form.value.slug = form.value.title
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();
    }
};

const submit = () => {
    const data = {
        title: form.value.title,
        description: form.value.description,
        slug: form.value.slug,
        is_premium: form.value.is_premium,
        price: form.value.price,
        is_published: form.value.is_published,
        difficulty_level: form.value.difficulty_level,
        estimated_duration: form.value.estimated_duration,
        instructor_id: form.value.instructor_id ? parseInt(form.value.instructor_id) : null,
        media: form.value.media,
    };

    if (isEditing.value) {
        router.put(`/admin/classroom/tracks/${props.track!.slug}`, data, {
            preserveScroll: true,
        });
    } else {
        router.post('/admin/classroom/tracks', data, {
            preserveScroll: true,
        });
    }
};

const cancel = () => {
    router.visit('/admin/classroom/tracks');
};

const difficultyLevelOptions = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'advanced', label: 'Advanced' },
];

const instructorOptions = computed(() => [
    { value: '', label: 'No instructor assigned' },
    ...(props.instructors || []).map(instructor => ({
        value: instructor.id.toString(),
        label: instructor.name
    }))
]);
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Track - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Track
                </h1>
                <p class="text-muted-foreground">
                    {{
                        isEditing
                            ? 'Update track information and settings'
                            : 'Create a new learning track'
                    }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Display errors if any -->
                <div v-if="errors && Object.keys(errors).length > 0" class="rounded-lg border border-destructive bg-destructive/10 p-4">
                    <h4 class="font-medium text-destructive">Please fix the following errors:</h4>
                    <ul class="mt-2 list-disc list-inside text-sm text-destructive">
                        <li v-for="(error, field) in errors" :key="field">{{ error }}</li>
                    </ul>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Basic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                                <CardDescription>
                                    Enter the basic details for your track
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="title">Title *</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        @input="generateSlug"
                                        placeholder="Enter track title"
                                        :class="{
                                            'border-destructive': errors?.title,
                                        }"
                                    />
                                    <p
                                        v-if="errors?.title"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.title }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="slug">Slug *</Label>
                                    <Input
                                        id="slug"
                                        v-model="form.slug"
                                        placeholder="track-slug"
                                        :class="{
                                            'border-destructive': errors?.slug,
                                        }"
                                    />
                                    <p
                                        v-if="errors?.slug"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.slug }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Describe what learners will achieve in this track"
                                        rows="4"
                                        :class="{
                                            'border-destructive': errors?.description,
                                        }"
                                    />
                                    <p
                                        v-if="errors?.description"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.description }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Media -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Media</CardTitle>
                                <CardDescription>
                                    Upload cover images or promotional videos
                                    for your track
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
                                    id="difficulty_level"
                                    label="Difficulty Level"
                                    v-model="form.difficulty_level"
                                    :options="difficultyLevelOptions"
                                    placeholder="Select difficulty level"
                                    :error="!!errors?.difficulty_level"
                                />
                                <p
                                    v-if="errors?.difficulty_level"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.difficulty_level }}
                                </p>

                                <CustomSelect
                                    id="instructor_id"
                                    label="Instructor"
                                    v-model="form.instructor_id"
                                    :options="instructorOptions"
                                    placeholder="Select instructor"
                                    :error="!!errors?.instructor_id"
                                />
                                <p
                                    v-if="errors?.instructor_id"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.instructor_id }}
                                </p>

                                <div class="space-y-2">
                                    <Label for="estimated_duration">Estimated Duration (minutes)</Label>
                                    <Input
                                        id="estimated_duration"
                                        v-model.number="form.estimated_duration"
                                        type="number"
                                        placeholder="120"
                                        min="0"
                                    />
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch
                                        id="is_premium"
                                        v-model="form.is_premium"
                                    />
                                    <Label for="is_premium">Premium Track</Label>
                                </div>

                                <div v-if="form.is_premium" class="space-y-2">
                                    <Label for="price">Price ($)</Label>
                                    <Input
                                        id="price"
                                        v-model.number="form.price"
                                        type="number"
                                        placeholder="29.99"
                                        min="0"
                                        step="0.01"
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
                                        class="w-full"
                                    >
                                        {{ isEditing ? 'Update Track' : 'Create Track' }}
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
