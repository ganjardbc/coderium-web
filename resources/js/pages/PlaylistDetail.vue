<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlaySquare, User as UserIcon } from 'lucide-vue-next';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PostCard from '@/components/PostCard.vue';
import BackButton from '@/components/BackButton.vue';
import { Button } from '@/components/ui/button';

interface Post {
    id: number;
    slug: string;
    title: string;
    subtitle: string;
    cover: string;
    type: 'article' | 'carousel' | 'video';
    tags: string[];
    views_count: number;
    likes_count: number;
    published_at: string;
    order: number;
}

interface Playlist {
    id: number;
    slug: string;
    title: string;
    description: string;
    cover: string;
    user: {
        id: number;
        name: string;
    };
    posts_count: number;
    posts: Post[];
}

interface Props {
    playlist: Playlist;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`${playlist.title} - Coderium`" />

    <FrontLayout>
        <!-- Back Button -->
        <BackButton />

        <!-- Playlist Header -->
        <section
            class="relative border-b w-full overflow-hidden"
            :style="{
                backgroundImage: playlist.cover ? `url(${playlist.cover})` : 'none',
                backgroundSize: 'cover',
                backgroundPosition: 'center',
            }"
        >

            <div class="relative top-0 left-0 w-full h-full bg-black/60 px-4 py-18">
                <div class="container mx-auto max-w-6xl grid gap-8 lg:grid-cols-[300px,1fr]">
                    <!-- Playlist Info -->
                    <div class="flex flex-col justify-center">
                        <div class="mb-2 flex items-center gap-2 text-sm text-white">
                            <PlaySquare class="h-4 w-4" />
                            <span>Playlist</span>
                        </div>
                        <h1 class="mb-4 text-4xl font-bold tracking-tight lg:text-5xl text-white">
                            {{ playlist.title }}
                        </h1>
                        <p class="mb-6 text-lg text-white">
                            {{ playlist.description }}
                        </p>

                        <div class="flex items-center gap-6 text-sm">
                            <div class="flex items-center gap-2">
                                <UserIcon class="h-4 w-4 text-white" />
                                <span class="font-medium text-white">{{ playlist.user.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <PlaySquare class="h-4 w-4 text-white" />
                                <span class="text-white">{{ playlist.posts_count }} {{ playlist.posts_count === 1 ? 'post' : 'posts' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Playlist Posts -->
        <section class="py-8 px-4 md:px-0">
            <div class="container mx-auto">
                <div class="mx-auto max-w-6xl">
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold">Posts in this Playlist</h2>
                        <p class="mt-1 text-muted-foreground">
                            {{ playlist.posts_count }} {{ playlist.posts_count === 1 ? 'post' : 'posts' }} • Curated by {{ playlist.user.name }}
                        </p>
                    </div>

                    <!-- Posts Grid -->
                    <div v-if="playlist.posts.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <PostCard
                            v-for="post in playlist.posts"
                            :key="post.id"
                            :post="post"
                            :show-tags="true"
                        />
                    </div>

                    <!-- Empty State -->
                    <div v-else class="rounded-lg border border-dashed py-16 text-center">
                        <PlaySquare class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <h3 class="mb-2 text-lg font-semibold">No posts in this playlist yet</h3>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Posts will appear here once they are added to the playlist.
                        </p>
                        <Button as-child>
                            <Link href="/search">
                                Browse All Posts
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Related Actions -->
        <section class="border-t py-8">
            <div class="container mx-auto px-4">
                <div class="mx-auto max-w-6xl text-center">
                    <h2 class="mb-4 text-2xl font-bold">Discover More</h2>
                    <p class="mb-6 text-muted-foreground">
                        Explore more playlists and posts from the community
                    </p>
                    <div class="flex items-center justify-center gap-4">
                        <Button as-child variant="default">
                            <Link href="/">
                                Browse Playlists
                            </Link>
                        </Button>
                        <Button as-child variant="outline">
                            <Link href="/search">
                                Search Posts
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
