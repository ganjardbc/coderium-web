<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Edit,
    Eye,
    Heart,
    User,
    Calendar,
    Hash,
    FileText,
    Image,
    Video,
    Layers,
    CheckCircle,
    XCircle,
    ExternalLink,
    Tag,
    Globe,
    Search,
} from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Playlist {
    id: number;
    title: string;
    slug: string;
    description: string;
}

interface Media {
    id: number;
    name: string;
    url: string;
    type: string;
    size?: number;
    pivot?: {
        tag: string;
        order: number;
    };
}

interface Post {
    id: number;
    slug: string;
    title: string;
    subtitle: string | null;
    content: string | null;
    tags: string[] | null;
    cover: string | null;
    type: 'article' | 'carousel' | 'video' | 'stack_gallery';
    is_published: boolean;
    published_at: string | null;
    views_count: number;
    likes_count: number;
    meta_description: string | null;
    meta_keywords: string | null;
    user: User;
    playlists?: Playlist[];
    media?: Media[];
    created_at: string;
    updated_at: string;
}

interface Props {
    post: Post;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Posts', href: '/admin/posts' },
    { title: props.post.title, href: '#' },
];

const getPostTypeIcon = (type: string) => {
    switch (type) {
        case 'video':
            return Video;
        case 'carousel':
            return Image;
        case 'stack_gallery':
            return Layers;
        default:
            return FileText;
    }
};

const getPostTypeColor = (type: string) => {
    switch (type) {
        case 'video':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'carousel':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'stack_gallery':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        default:
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};

// Group media by tag
const mediaByTag = (props.post.media || []).reduce((acc, media) => {
    const tag = media.pivot?.tag || 'general';
    if (!acc[tag]) acc[tag] = [];
    acc[tag].push(media);
    return acc;
}, {} as Record<string, Media[]>);
</script>

<template>
    <Head :title="`${post.title} - Posts - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ post.title }}</h1>
                        <Badge :variant="post.is_published ? 'default' : 'secondary'">
                            {{ post.is_published ? 'Published' : 'Draft' }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-4 text-muted-foreground">
                        <div class="flex items-center gap-1">
                            <User class="h-4 w-4" />
                            <span>{{ post.user.name }}</span>
                        </div>
                        <div class="flex items-center gap-1" v-if="post.published_at">
                            <Calendar class="h-4 w-4" />
                            <span>{{ formatDate(post.published_at) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline">
                        <Link :href="`/posts/${post.slug}`" target="_blank">
                            <ExternalLink class="mr-2 h-4 w-4" />
                            View Live
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/admin/posts/${post.slug}/edit`">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit Post
                        </Link>
                    </Button>
                </div>
            </div>

            <Tabs default-value="overview" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="content">Content</TabsTrigger>
                    <TabsTrigger value="analytics">Analytics</TabsTrigger>
                    <TabsTrigger value="media" v-if="post.media && post.media.length > 0">Media</TabsTrigger>
                    <TabsTrigger value="seo">SEO</TabsTrigger>
                </TabsList>

                <!-- Overview Tab -->
                <TabsContent value="overview" class="space-y-4">
                    <!-- Statistics -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Eye class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Views</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ post.views_count.toLocaleString() }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Total views
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Heart class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Likes</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ post.likes_count.toLocaleString() }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Total likes
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <component :is="getPostTypeIcon(post.type)" class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Type</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold capitalize">{{ post.type }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Content type
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <CheckCircle class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Status</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ post.is_published ? 'Live' : 'Draft' }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ post.is_published ? 'Publicly visible' : 'Not published' }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Post Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Post Information</CardTitle>
                            <CardDescription>
                                Details about this post
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <component :is="getPostTypeIcon(post.type)" class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">
                                                <span
                                                    :class="getPostTypeColor(post.type)"
                                                    class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                                >
                                                    {{ post.type }}
                                                </span>
                                            </div>
                                            <div class="text-sm text-muted-foreground">
                                                Content type
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <User class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">{{ post.user.name }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                {{ post.user.email }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Hash class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">{{ post.slug }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                URL slug
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <CheckCircle v-if="post.is_published" class="h-5 w-5 text-green-600" />
                                        <XCircle v-else class="h-5 w-5 text-gray-400" />
                                        <div>
                                            <div class="font-medium">{{ post.is_published ? 'Published' : 'Draft' }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Publication status
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3" v-if="post.published_at">
                                        <Calendar class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">{{ formatDateTime(post.published_at) }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Published date
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Calendar class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">{{ formatDateTime(post.updated_at) }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Last updated
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Subtitle -->
                            <div v-if="post.subtitle">
                                <h3 class="font-medium mb-2">Subtitle</h3>
                                <p class="text-muted-foreground">{{ post.subtitle }}</p>
                            </div>

                            <!-- Tags -->
                            <div v-if="post.tags && post.tags.length > 0">
                                <h3 class="font-medium mb-2">Tags</h3>
                                <div class="flex flex-wrap gap-2">
                                    <Badge v-for="tag in post.tags" :key="tag" variant="outline">
                                        <Tag class="mr-1 h-3 w-3" />
                                        {{ tag }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Cover Image -->
                            <div v-if="post.cover">
                                <h3 class="font-medium mb-2">Cover Image</h3>
                                <div class="max-w-md">
                                    <img :src="post.cover" :alt="post.title" class="w-full h-48 object-cover rounded-lg border" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Playlists -->
                    <Card v-if="post.playlists && post.playlists.length > 0">
                        <CardHeader>
                            <CardTitle>Playlists</CardTitle>
                            <CardDescription>
                                This post is included in the following playlists
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="playlist in post.playlists"
                                    :key="playlist.id"
                                    class="flex items-center justify-between p-4 border rounded-lg"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                            <Layers class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div class="font-medium">{{ playlist.title }}</div>
                                            <div class="text-sm text-muted-foreground">{{ playlist.description }}</div>
                                        </div>
                                    </div>
                                    <Button as-child variant="outline" size="sm">
                                        <Link :href="`/admin/playlists/${playlist.slug}`">
                                            <Eye class="mr-2 h-4 w-4" />
                                            View
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Content Tab -->
                <TabsContent value="content" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Post Content</CardTitle>
                            <CardDescription>
                                The main content of this post
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="post.content" class="prose max-w-none dark:prose-invert" v-html="post.content"></div>
                            <div v-else class="text-center py-8 text-muted-foreground">
                                <FileText class="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>No content available</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Analytics Tab -->
                <TabsContent value="analytics" class="space-y-4">
                    <!-- Engagement Metrics -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Eye class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Total Views</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ post.views_count.toLocaleString() }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        All time views
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Heart class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Total Likes</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ post.likes_count.toLocaleString() }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        All time likes
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Layers class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Playlists</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ (post.playlists || []).length }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Featured in playlists
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Performance Overview -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Performance Overview</CardTitle>
                            <CardDescription>
                                Key metrics for this post
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-4 border rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <Eye class="h-8 w-8 text-blue-600" />
                                        <div>
                                            <div class="font-medium">Views</div>
                                            <div class="text-sm text-muted-foreground">Total page views</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold">{{ post.views_count.toLocaleString() }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between p-4 border rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <Heart class="h-8 w-8 text-red-600" />
                                        <div>
                                            <div class="font-medium">Likes</div>
                                            <div class="text-sm text-muted-foreground">User engagement</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold">{{ post.likes_count.toLocaleString() }}</div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ post.views_count > 0 ? ((post.likes_count / post.views_count) * 100).toFixed(1) : 0 }}% engagement
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Media Tab -->
                <TabsContent value="media" class="space-y-4" v-if="post.media && post.media.length > 0">
                    <Card>
                        <CardHeader>
                            <CardTitle>Media Files</CardTitle>
                            <CardDescription>
                                Files attached to this post
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-for="(mediaList, tag) in mediaByTag" :key="tag" class="space-y-4">
                                <div v-if="Object.keys(mediaByTag).length > 1">
                                    <h3 class="font-medium capitalize mb-3">{{ tag }} Media</h3>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div
                                        v-for="media in mediaList"
                                        :key="media.id"
                                        class="flex items-center gap-3 p-4 border rounded-lg"
                                    >
                                        <div class="flex-shrink-0">
                                            <img
                                                v-if="media.type?.startsWith('image')"
                                                :src="media.url"
                                                :alt="media.name"
                                                class="w-16 h-16 object-cover rounded"
                                            />
                                            <div
                                                v-else-if="media.type?.startsWith('video')"
                                                class="w-16 h-16 bg-red-100 dark:bg-red-900 rounded flex items-center justify-center"
                                            >
                                                <Video class="h-8 w-8 text-red-600" />
                                            </div>
                                            <div
                                                v-else
                                                class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded flex items-center justify-center"
                                            >
                                                <FileText class="h-8 w-8 text-gray-500" />
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium truncate">{{ media.name }}</p>
                                            <p class="text-sm text-muted-foreground">{{ media.type }}</p>
                                            <p class="text-xs text-muted-foreground" v-if="media.size">
                                                {{ (media.size / 1024 / 1024).toFixed(2) }} MB
                                            </p>
                                            <p class="text-xs text-muted-foreground" v-if="media.pivot?.order">
                                                Order: {{ media.pivot.order }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- SEO Tab -->
                <TabsContent value="seo" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>SEO Information</CardTitle>
                            <CardDescription>
                                Search engine optimization details
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <Search class="h-5 w-5 text-muted-foreground" />
                                        <div class="flex-1">
                                            <div class="font-medium">Meta Description</div>
                                            <div class="text-sm text-muted-foreground mt-1">
                                                {{ post.meta_description || 'Not set' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Tag class="h-5 w-5 text-muted-foreground" />
                                        <div class="flex-1">
                                            <div class="font-medium">Meta Keywords</div>
                                            <div class="text-sm text-muted-foreground mt-1">
                                                {{ post.meta_keywords || 'Not set' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <Globe class="h-5 w-5 text-muted-foreground" />
                                        <div class="flex-1">
                                            <div class="font-medium">Public URL</div>
                                            <div class="text-sm text-muted-foreground mt-1">
                                                <Link :href="`/posts/${post.slug}`" target="_blank" class="text-blue-600 hover:underline">
                                                    /posts/{{ post.slug }}
                                                </Link>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Hash class="h-5 w-5 text-muted-foreground" />
                                        <div class="flex-1">
                                            <div class="font-medium">URL Slug</div>
                                            <div class="text-sm text-muted-foreground mt-1">
                                                {{ post.slug }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEO Preview -->
                            <div class="border rounded-lg p-4 bg-muted/50">
                                <h3 class="font-medium mb-3">Search Engine Preview</h3>
                                <div class="space-y-2">
                                    <div class="text-blue-600 text-lg font-medium hover:underline cursor-pointer">
                                        {{ post.title }}
                                    </div>
                                    <div class="text-green-700 text-sm">
                                        yoursite.com/posts/{{ post.slug }}
                                    </div>
                                    <div class="text-gray-600 text-sm">
                                        {{ post.meta_description || post.subtitle || 'No description available' }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
