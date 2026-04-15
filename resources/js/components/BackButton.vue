<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';

const onBackClick = () => {
    window.history.back();
};
</script>
<template>
    <!-- Back Button -->
    <section class="h-16 border-b bg-card/50">
        <div
            class="container mx-auto flex h-full items-center justify-between gap-4 px-4"
        >
            <slot name="prepend">
                <Button
                    variant="ghost"
                    size="lg"
                    class="rounded-full"
                    @click="onBackClick"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span class="text-md font-semibold"> Back </span>
                </Button>
            </slot>

            <slot name="append" />
        </div>
    </section>
</template>
