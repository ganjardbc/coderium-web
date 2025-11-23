<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PlaySquare, User as UserIcon } from 'lucide-vue-next';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PostCard from '@/components/PostCard.vue';
import BackButton from '@/components/BackButton.vue';
import DiscoverMode from '@/components/DiscoverMode.vue';
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
        <section class="relative border-b w-full">
            <div class="relative container h-full px-4 py-8">
                <div class="w-full flex flex-col md:flex-row-reverse items-center gap-8">
                    <!-- Playlist Cover -->
                    <img
                        :src="playlist.cover"
                        alt="Playlist Cover"
                        class="w-full md:w-[300px] aspect-video rounded-lg object-cover"
                    />

                    <!-- Playlist Info -->
                    <div class="flex-1 w-full flex flex-col justify-center">
                        <div class="mb-2 flex items-center gap-2 text-sm text-muted-foreground">
                            <PlaySquare class="h-4 w-4" />
                            <span>Playlist</span>
                        </div>
                        <h1 class="mb-4 text-2xl font-bold tracking-tight lg:text-3xl">
                            {{ playlist.title }}
                        </h1>
                        <p class="mb-4 text-sm lg:text-lg text-muted-foreground">
                            {{ playlist.description }}
                        </p>

                        <div class="flex items-center gap-6 text-sm">
                            <div class="flex items-center gap-2">
                                <UserIcon class="h-4 w-4" />
                                <span class="font-medium">{{ playlist.user.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <PlaySquare class="h-4 w-4" />
                                <span class="">{{ playlist.posts_count }} {{ playlist.posts_count === 1 ? 'post' : 'posts' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Playlist Posts -->
        <section class="py-8 px-4">
            <div class="container mx-auto">
                <div class="mx-auto max-w-6xl">
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
        <DiscoverMode />
    </FrontLayout>
</template>
