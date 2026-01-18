<script setup lang="ts">
import MediaUploader from '@/components/admin/MediaUploader.vue';
import AlertError from '@/components/AlertError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Lesson {
    id?: number;
    module_id: number;
    title: string;
    content: string;
    order_index: number;
    estimated_duration: number | null;
    is_published: boolean;
    lesson_type: 'text' | 'video' | 'interactive';
    media?: any[];
}

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
    lesson?: Lesson;
    module: Module;
    maxOrderIndex: number;
}

const props = defineProps<Props>();

const isEditing = computed(() => !!props.lesson?.id);

const form = useForm({
    module_id: props.module?.id,
    title: props.lesson?.title || '',
    content: props.lesson?.content || '',
    order_index: props.lesson?.order_index || props.maxOrderIndex + 1,
    estimated_duration: props.lesson?.estimated_duration || 15,
    is_published: props.lesson?.is_published || false,
    lesson_type: props.lesson?.lesson_type || 'text',
    media: props.lesson?.media || [],
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Lessons', href: '/admin/classroom/lessons' },
    { title: isEditing.value ? 'Edit Lesson' : 'Create Lesson', href: '#' },
];

const submit = () => {
    // Client-side validation for required fields
    if (!form.module_id) {
        alert('Module is required');
        return;
    }

    if (!form.title.trim()) {
        alert('Title is required');
        return;
    }

    if (!form.content.trim()) {
        alert('Content is required');
        return;
    }

    if (!form.lesson_type) {
        alert('Lesson type is required');
        return;
    }

    if (!form.estimated_duration || form.estimated_duration < 1) {
        alert('Estimated duration must be at least 1 minute');
        return;
    }

    if (!form.order_index || form.order_index < 1) {
        alert('Position is required');
        return;
    }

    if (isEditing.value) {
        form.put(`/admin/classroom/lessons/${props.lesson!.id}`, {
            preserveScroll: true,
        });
    } else {
        form.post('/admin/classroom/lessons', {
            preserveScroll: true,
        });
    }
};

const cancel = () => {
    router.visit('/admin/classroom/lessons');
};

const orderOptions = computed(() => {
    const options = [];
    for (let i = 1; i <= props.maxOrderIndex + 1; i++) {
        options.push({ value: i, label: `Position ${i}` });
    }
    return options;
});

const lessonTypeOptions = [
    { value: 'text', label: 'Text-based' },
    { value: 'video', label: 'Video' },
    { value: 'interactive', label: 'Interactive' },
];

// Code snippet insertion helper
const insertCodeSnippet = () => {
    const language =
        prompt('Enter programming language (e.g., javascript, python, php):') ||
        'javascript';
    const code = prompt('Enter your code:') || '';

    if (code) {
        const codeBlock = `\n\`\`\`${language}\n${code}\n\`\`\`\n`;
        form.content += codeBlock;
    }
};
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Lesson - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Lesson
                </h1>
                <p class="text-muted-foreground">
                    {{
                        isEditing
                            ? 'Update lesson content'
                            : 'Create a new lesson in'
                    }}
                    "{{ module?.title }}"
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
                                    Enter the lesson title and basic details
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="module_id">Module *</Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{ module?.title }} ({{ module?.level?.title }} - {{ module?.level?.track?.title }})
                                    </p>
                                    <input type="hidden" v-model="form.module_id" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="title">Title *</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="Enter lesson title"
                                        required
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
                            </CardContent>
                        </Card>

                        <!-- Content -->
                        <Card>
                            <CardHeader>
                                <CardTitle
                                    class="flex items-center justify-between"
                                >
                                    Lesson Content *
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="insertCodeSnippet"
                                    >
                                        <svg
                                            class="mr-2 h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                                            />
                                        </svg>
                                        Add Code Snippet
                                    </Button>
                                </CardTitle>
                                <CardDescription>
                                    Write the lesson content with rich text
                                    formatting and code snippets (required)
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <RichTextEditor
                                    v-model="form.content"
                                    placeholder="Write your lesson content here. Use the toolbar to format text and add code blocks..."
                                    required
                                />
                                <p
                                    v-if="form.errors.content"
                                    class="mt-2 text-sm text-destructive"
                                >
                                    {{ form.errors.content }}
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Media -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Media</CardTitle>
                                <CardDescription>
                                    Upload videos, images, or other media for
                                    this lesson
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <MediaUploader
                                    v-model="form.media"
                                    accept="image/*,video/*,.pdf"
                                    :multiple="true"
                                    :max-size="100"
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
                                    id="lesson_type"
                                    label="Lesson Type *"
                                    v-model="form.lesson_type"
                                    :options="lessonTypeOptions"
                                    placeholder="Select type"
                                    required
                                    :error="!!form.errors.lesson_type"
                                />
                                <p
                                    v-if="form.errors.lesson_type"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.lesson_type }}
                                </p>

                                <CustomSelect
                                    id="order_index"
                                    label="Position *"
                                    v-model="form.order_index"
                                    :options="orderOptions"
                                    placeholder="Select position"
                                    required
                                    :error="!!form.errors.order_index"
                                />
                                <p
                                    v-if="form.errors.order_index"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.order_index }}
                                </p>

                                <div class="space-y-2">
                                    <Label for="estimated_duration"
                                        >Estimated Duration (minutes) *</Label
                                    >
                                    <Input
                                        id="estimated_duration"
                                        v-model.number="form.estimated_duration"
                                        type="number"
                                        placeholder="15"
                                        min="1"
                                        required
                                        :class="{
                                            'border-destructive':
                                                form.errors.estimated_duration,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.estimated_duration"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.estimated_duration }}
                                    </p>
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

                        <!-- Quick Tips -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Content Tips</CardTitle>
                            </CardHeader>
                            <CardContent
                                class="space-y-2 text-sm text-muted-foreground"
                            >
                                <p>• Use headings to structure your content</p>
                                <p>
                                    • Add code blocks for programming examples
                                </p>
                                <p>• Include images to illustrate concepts</p>
                                <p>
                                    • Keep videos under 10 minutes for better
                                    engagement
                                </p>
                                <p>• Use bullet points for key takeaways</p>
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
                                                  ? 'Update Lesson'
                                                  : 'Create Lesson'
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
