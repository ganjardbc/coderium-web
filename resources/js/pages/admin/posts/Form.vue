<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import MediaUploader from '@/components/admin/MediaUploader.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';

interface Media {
    id: number;
    name: string;
    url: string;
    type: 'image' | 'video' | 'file';
    mime_type: string;
    size: number;
}

interface Props {
    post?: {
        id: number;
        title: string;
        subtitle: string;
        content: string;
        slug: string;
        type: 'article' | 'carousel' | 'video';
        cover: string;
        tags: string[];
        media: Media[];
        is_published: boolean;
        published_at: string;
        meta_description: string;
        meta_keywords: string;
    };
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const form = ref({
    title: props.post?.title || '',
    subtitle: props.post?.subtitle || '',
    content: props.post?.content || '',
    type: props.post?.type || 'article',
    cover: props.post?.cover ? [{ id: 0, name: 'cover', url: props.post.cover, type: 'image' as const, mime_type: 'image/jpeg', size: 0 }] : [] as Media[],
    tags: Array.isArray(props.post?.tags) ? props.post?.tags.join(', ') : '',
    media: props.post?.media || [] as Media[],
    is_published: props.post?.is_published ?? true,
    meta_description: props.post?.meta_description || '',
    meta_keywords: props.post?.meta_keywords || '',
});

const isEditing = !!props.post;

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Posts', href: '/admin/posts' },
    { title: isEditing ? 'Edit' : 'Create', href: '#' },
];

const showMediaField = computed(() => form.value.type === 'carousel' || form.value.type === 'video');

const mediaAccept = computed(() => {
    if (form.value.type === 'carousel') return 'image/*';
    if (form.value.type === 'video') return 'video/*';
    return 'image/*,video/*';
});

const mediaMultiple = computed(() => form.value.type === 'carousel');

const submit = () => {
    const data = {
        title: form.value.title,
        subtitle: form.value.subtitle,
        content: form.value.content,
        type: form.value.type,
        cover: form.value.cover.length > 0 ? form.value.cover[0].url : '',
        tags: form.value.tags ? form.value.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        media_ids: form.value.media.map(m => m.id),
        is_published: form.value.is_published,
        meta_description: form.value.meta_description,
        meta_keywords: form.value.meta_keywords,
    };

    if (isEditing) {
        router.put(`/admin/posts/${props.post?.slug}`, data, {
            preserveScroll: true,
        });
    } else {
        router.post('/admin/posts', data);
    }
};

const cancel = () => {
    router.visit('/admin/posts');
};
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Post - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">{{ isEditing ? 'Edit' : 'Create' }} Post</h1>
                <p class="text-muted-foreground">{{ isEditing ? 'Update' : 'Add a new' }} post content</p>
            </div>

            <div class="w-full">
                <form @submit.prevent="submit" class="space-y-6 rounded-lg border bg-card p-6">
                    <!-- Title -->
                    <div class="space-y-2">
                        <Label for="title">Title *</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            type="text"
                            placeholder="Enter post title"
                            required
                        />
                        <p v-if="errors?.title" class="text-sm text-destructive">{{ errors.title }}</p>
                    </div>

                    <!-- Subtitle -->
                    <div class="space-y-2">
                        <Label for="subtitle">Subtitle</Label>
                        <Input
                            id="subtitle"
                            v-model="form.subtitle"
                            type="text"
                            placeholder="Enter post subtitle"
                        />
                        <p v-if="errors?.subtitle" class="text-sm text-destructive">{{ errors.subtitle }}</p>
                    </div>

                    <!-- Type -->
                    <div class="space-y-2">
                        <Label for="type">Post Type *</Label>
                        <select
                            id="type"
                            v-model="form.type"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            required
                        >
                            <option value="article">Article</option>
                            <option value="carousel">Carousel</option>
                            <option value="video">Video</option>
                        </select>
                        <p v-if="errors?.type" class="text-sm text-destructive">{{ errors.type }}</p>
                    </div>

                    <!-- Content -->
                    <div class="space-y-2">
                        <Label for="content">Content</Label>
                        <RichTextEditor
                            v-model="form.content"
                            placeholder="Write your post content here..."
                            class="content-html"
                        />
                        <p class="text-sm text-muted-foreground">Use the toolbar to format your content</p>
                        <p v-if="errors?.content" class="text-sm text-destructive">{{ errors.content }}</p>
                    </div>

                    <!-- Cover Image Upload -->
                    <div class="space-y-2">
                        <Label>Cover Image</Label>
                        <MediaUploader
                            v-model="form.cover"
                            accept="image/*"
                            :multiple="false"
                            :max-size="10"
                        />
                        <p class="text-sm text-muted-foreground">Upload a cover image for the post (max 10MB)</p>
                        <p v-if="errors?.cover" class="text-sm text-destructive">{{ errors.cover }}</p>
                    </div>

                    <!-- Media Upload (for carousel/video) -->
                    <div v-if="showMediaField" class="space-y-2">
                        <Label>
                            {{ form.type === 'carousel' ? 'Carousel Images' : 'Video File' }}
                            <span v-if="form.type === 'video'"> *</span>
                        </Label>
                        <MediaUploader
                            v-model="form.media"
                            :accept="mediaAccept"
                            :multiple="mediaMultiple"
                            :max-size="50"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ form.type === 'carousel' ? 'Upload multiple images for the carousel' : 'Upload a video file (max 50MB)' }}
                        </p>
                        <p v-if="errors?.media" class="text-sm text-destructive">{{ errors.media }}</p>
                    </div>

                    <!-- Tags -->
                    <div class="space-y-2">
                        <Label for="tags">Tags</Label>
                        <Input
                            id="tags"
                            v-model="form.tags"
                            type="text"
                            placeholder="laravel, vue, web-development"
                        />
                        <p class="text-sm text-muted-foreground">Separate tags with commas</p>
                        <p v-if="errors?.tags" class="text-sm text-destructive">{{ errors.tags }}</p>
                    </div>

                    <!-- SEO Meta Description -->
                    <div class="space-y-2">
                        <Label for="meta_description">Meta Description (SEO)</Label>
                        <Textarea
                            id="meta_description"
                            v-model="form.meta_description"
                            placeholder="Enter meta description for SEO"
                            rows="3"
                        />
                        <p v-if="errors?.meta_description" class="text-sm text-destructive">{{ errors.meta_description }}</p>
                    </div>

                    <!-- SEO Keywords -->
                    <div class="space-y-2">
                        <Label for="meta_keywords">Meta Keywords (SEO)</Label>
                        <Input
                            id="meta_keywords"
                            v-model="form.meta_keywords"
                            type="text"
                            placeholder="keyword1, keyword2, keyword3"
                        />
                        <p v-if="errors?.meta_keywords" class="text-sm text-destructive">{{ errors.meta_keywords }}</p>
                    </div>

                    <!-- Published Status -->
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <Label>Published</Label>
                            <p class="text-sm text-muted-foreground">Make this post visible to the public</p>
                        </div>
                        <Switch v-model:checked="form.is_published" />
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 justify-end">
                        <Button type="button" variant="outline" @click="cancel">
                            Cancel
                        </Button>
                        <Button type="submit">
                            {{ isEditing ? 'Update' : 'Create' }} Post
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
