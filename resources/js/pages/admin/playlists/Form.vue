<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import MediaUploader from '@/components/admin/MediaUploader.vue';
import { X, GripVertical } from 'lucide-vue-next';

interface Media {
    id: number;
    name: string;
    url: string;
    type: 'image' | 'video' | 'file';
    mime_type: string;
    size: number;
}

interface Post {
    id: number;
    title: string;
    slug: string;
    type: 'article' | 'carousel' | 'video';
    cover: string;
}

interface Props {
    playlist?: {
        id: number;
        title: string;
        description: string;
        slug: string;
        cover: string;
        is_published: boolean;
        order: number;
        posts?: Post[];
    };
    availablePosts: Post[];
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const form = ref({
    title: props.playlist?.title || '',
    description: props.playlist?.description || '',
    cover: props.playlist?.cover ? [{ id: 0, name: 'cover', url: props.playlist.cover, type: 'image' as const, mime_type: 'image/jpeg', size: 0 }] : [] as Media[],
    is_published: props.playlist?.is_published ?? true,
    order: props.playlist?.order || 0,
    selectedPosts: props.playlist?.posts || [] as Post[],
});

const isEditing = !!props.playlist;

const availablePostsFiltered = computed(() => {
    const selectedIds = form.value.selectedPosts.map(p => p.id);
    return props.availablePosts.filter(p => !selectedIds.includes(p.id));
});

const addPost = (post: Post) => {
    form.value.selectedPosts.push(post);
};

const removePost = (index: number) => {
    form.value.selectedPosts.splice(index, 1);
};

const movePostUp = (index: number) => {
    if (index > 0) {
        const temp = form.value.selectedPosts[index];
        form.value.selectedPosts[index] = form.value.selectedPosts[index - 1];
        form.value.selectedPosts[index - 1] = temp;
    }
};

const movePostDown = (index: number) => {
    if (index < form.value.selectedPosts.length - 1) {
        const temp = form.value.selectedPosts[index];
        form.value.selectedPosts[index] = form.value.selectedPosts[index + 1];
        form.value.selectedPosts[index + 1] = temp;
    }
};

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Playlists', href: '/admin/playlists' },
    { title: isEditing ? 'Edit' : 'Create', href: '#' },
];

const submit = () => {
    const data = {
        title: form.value.title,
        description: form.value.description,
        cover: form.value.cover.length > 0 ? form.value.cover[0].url : '',
        is_published: form.value.is_published,
        order: form.value.order,
        post_ids: form.value.selectedPosts.map(p => p.id),
    };

    if (isEditing) {
        router.put(`/admin/playlists/${props.playlist?.slug}`, data, {
            preserveScroll: true,
        });
    } else {
        router.post('/admin/playlists', data);
    }
};

const cancel = () => {
    router.visit('/admin/playlists');
};
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Playlist - Admin - Coderium`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">{{ isEditing ? 'Edit' : 'Create' }} Playlist</h1>
                <p class="text-muted-foreground">{{ isEditing ? 'Update' : 'Add a new' }} playlist details</p>
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
                            placeholder="Enter playlist title"
                            required
                        />
                        <p v-if="errors?.title" class="text-sm text-destructive">{{ errors.title }}</p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Enter playlist description"
                            rows="4"
                        />
                        <p v-if="errors?.description" class="text-sm text-destructive">{{ errors.description }}</p>
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
                        <p class="text-sm text-muted-foreground">Upload a cover image for the playlist (max 10MB)</p>
                        <p v-if="errors?.cover" class="text-sm text-destructive">{{ errors.cover }}</p>
                    </div>

                    <!-- Order -->
                    <div class="space-y-2">
                        <Label for="order">Display Order</Label>
                        <Input
                            id="order"
                            v-model.number="form.order"
                            type="number"
                            min="0"
                            placeholder="0"
                        />
                        <p class="text-sm text-muted-foreground">Lower numbers appear first</p>
                        <p v-if="errors?.order" class="text-sm text-destructive">{{ errors.order }}</p>
                    </div>

                    <!-- Posts Selection -->
                    <div class="space-y-4">
                        <div>
                            <Label>Posts in Playlist</Label>
                            <p class="text-sm text-muted-foreground">Select posts to include in this playlist</p>
                        </div>

                        <!-- Selected Posts -->
                        <div v-if="form.selectedPosts.length > 0" class="space-y-2">
                            <div
                                v-for="(post, index) in form.selectedPosts"
                                :key="post.id"
                                class="flex items-center gap-3 rounded-lg border bg-muted/50 p-3"
                            >
                                <div class="flex items-center gap-2 text-muted-foreground">
                                    <GripVertical class="h-4 w-4" />
                                    <span class="text-sm font-medium">{{ index + 1 }}</span>
                                </div>
                                <img
                                    v-if="post.cover"
                                    :src="post.cover"
                                    :alt="post.title"
                                    class="h-12 w-12 rounded object-cover"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium truncate">{{ post.title }}</p>
                                    <p class="text-sm text-muted-foreground capitalize">{{ post.type }}</p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="movePostUp(index)"
                                        :disabled="index === 0"
                                    >
                                        ↑
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="movePostDown(index)"
                                        :disabled="index === form.selectedPosts.length - 1"
                                    >
                                        ↓
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="removePost(index)"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground italic">No posts added yet</p>

                        <!-- Available Posts Selector -->
                        <div v-if="availablePostsFiltered.length > 0" class="space-y-2">
                            <Label for="add-post">Add Post</Label>
                            <select
                                id="add-post"
                                @change="(e) => {
                                    const postId = parseInt((e.target as HTMLSelectElement).value);
                                    const post = availablePostsFiltered.find(p => p.id === postId);
                                    if (post) {
                                        addPost(post);
                                        (e.target as HTMLSelectElement).value = '';
                                    }
                                }"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">Select a post to add...</option>
                                <option v-for="post in availablePostsFiltered" :key="post.id" :value="post.id">
                                    {{ post.title }} ({{ post.type }})
                                </option>
                            </select>
                        </div>
                        <p v-else-if="form.selectedPosts.length > 0" class="text-sm text-muted-foreground">
                            All available posts have been added
                        </p>
                    </div>

                    <!-- Published Status -->
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <Label>Published</Label>
                            <p class="text-sm text-muted-foreground">Make this playlist visible to the public</p>
                        </div>
                        <Switch v-model:checked="form.is_published" />
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <Button type="submit">
                            {{ isEditing ? 'Update' : 'Create' }} Playlist
                        </Button>
                        <Button type="button" variant="outline" @click="cancel">
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
