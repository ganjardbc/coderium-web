<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Save } from 'lucide-vue-next';

interface Course {
    id: number;
    title: string;
    description: string;
    slug: string;
    is_active: boolean;
    estimated_duration: number | null;
    certificate_template_id: number | null;
}

interface CertificateTemplate {
    id: number;
    name: string;
    description?: string;
}

interface Props {
    course?: Course;
    certificateTemplates: CertificateTemplate[];
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const form = ref({
    title: props.course?.title || '',
    description: props.course?.description || '',
    slug: props.course?.slug || '',
    is_active: props.course?.is_active ?? true,
    estimated_duration: props.course?.estimated_duration?.toString() || '',
    certificate_template_id: props.course?.certificate_template_id?.toString() || '',
});

const isEditing = !!props.course;

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Courses', href: '/admin/courses' },
    { title: isEditing ? 'Edit' : 'Create', href: '#' },
];

const generateSlug = () => {
    if (form.value.title) {
        form.value.slug = form.value.title
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
};

const submit = () => {
    const data = {
        title: form.value.title,
        description: form.value.description,
        slug: form.value.slug,
        is_active: form.value.is_active,
        estimated_duration: form.value.estimated_duration ? parseInt(form.value.estimated_duration) : null,
        certificate_template_id: form.value.certificate_template_id ? parseInt(form.value.certificate_template_id) : null,
    };

    if (isEditing) {
        router.put(`/admin/courses/${props.course?.id}`, data, {
            preserveScroll: true,
        });
    } else {
        router.post('/admin/courses', data);
    }
};

const cancel = () => {
    window.history.back();
};
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Course - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Course
                </h1>
                <p class="text-muted-foreground">
                    {{ isEditing ? 'Update' : 'Add a new' }} course with modular content structure
                </p>
            </div>

            <div class="w-full">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 rounded-lg border bg-card p-6"
                >
                    <!-- Title -->
                    <div class="space-y-2">
                        <Label for="title">Course Title *</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            type="text"
                            placeholder="Enter course title"
                            required
                            @input="generateSlug"
                        />
                        <p
                            v-if="errors?.title"
                            class="text-sm text-destructive"
                        >
                            {{ errors.title }}
                        </p>
                    </div>

                    <!-- Slug -->
                    <div class="space-y-2">
                        <Label for="slug">Course Slug *</Label>
                        <Input
                            id="slug"
                            v-model="form.slug"
                            type="text"
                            placeholder="course-slug"
                            required
                        />
                        <p class="text-sm text-muted-foreground">
                            URL-friendly version of the course title
                        </p>
                        <p
                            v-if="errors?.slug"
                            class="text-sm text-destructive"
                        >
                            {{ errors.slug }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description *</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Describe what students will learn in this course"
                            rows="4"
                            required
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
                        <Label for="estimated_duration">Estimated Duration (minutes)</Label>
                        <Input
                            id="estimated_duration"
                            v-model="form.estimated_duration"
                            type="number"
                            placeholder="120"
                            min="1"
                        />
                        <p class="text-sm text-muted-foreground">
                            Total estimated time to complete the course
                        </p>
                        <p
                            v-if="errors?.estimated_duration"
                            class="text-sm text-destructive"
                        >
                            {{ errors.estimated_duration }}
                        </p>
                    </div>

                    <!-- Certificate Template -->
                    <div class="space-y-2">
                        <Label for="certificate_template">Certificate Template</Label>
                        <Select v-model="form.certificate_template_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a certificate template (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">No certificate</SelectItem>
                                <SelectItem
                                    v-for="template in certificateTemplates"
                                    :key="template.id"
                                    :value="template.id.toString()"
                                >
                                    {{ template.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-sm text-muted-foreground">
                            Certificate awarded upon course completion
                        </p>
                        <p
                            v-if="errors?.certificate_template_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.certificate_template_id }}
                        </p>
                    </div>

                    <!-- Active Status -->
                    <div
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="space-y-0.5">
                            <Label>Active Course</Label>
                            <p class="text-sm text-muted-foreground">
                                Students can enroll in active courses
                            </p>
                        </div>
                        <Switch v-model="form.is_active" />
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="cancel">
                            Cancel
                        </Button>
                        <Button type="submit">
                            <Save class="mr-2 h-4 w-4" />
                            {{ isEditing ? 'Update' : 'Create' }} Course
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
