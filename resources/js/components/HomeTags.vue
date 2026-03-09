<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Tag } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';

interface PopularTag {
    name: string;
    count: number;
}

defineProps<{
    popularTags: PopularTag[];
}>();
</script>

<template>
    <section class="py-8 border-t">
        <div class="container max-w-6xl mx-auto px-4">
            <h2 class="text-xl font-bold text-center mb-4">
                Popular Tags
            </h2>

            <div class="flex flex-wrap justify-center gap-3">
                <Button
                    v-for="tag in popularTags"
                    :key="tag.name"
                    :as="Link"
                    :href="`/explore?q=${encodeURIComponent(tag.name)}&sort=recent&type=all`"
                    variant="outline"
                    size="lg"
                    class="group relative overflow-hidden rounded-full transition-all hover:scale-105"
                >
                    <Tag class="h-4 w-4 mr-2" />
                    <span class="font-medium">{{ tag.name }}</span>
                    <span class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground transition-colors group-hover:bg-primary/10">
                        {{ tag.count }}
                    </span>
                </Button>
            </div>
        </div>
    </section>
</template>
