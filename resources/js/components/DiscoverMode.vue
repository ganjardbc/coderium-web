<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
</script>
<template>
    <section class="border-t py-8">
        <div class="container mx-auto px-4">
            <div class="mx-auto max-w-6xl text-center">
                <h2 class="mb-4 text-xl font-bold">Discover More</h2>
                <p class="mb-6 text-muted-foreground">
                    Explore more playlists and posts from the community
                </p>
                <div class="flex items-center justify-center gap-4">
                    <Button as-child variant="default">
                        <Link href="/explore"> Explore Posts </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/playlists"> Browse Playlists </Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
