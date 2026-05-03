<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ArrowLeft as ArrowLeft } from 'lucide-vue-next';

const onBackClick = () => {
    window.history.back();
};
</script>
<template>
    <!-- Back Button -->
    <section class="sticky top-0 z-100 border-b bg-card h-16">
        <div class="container mx-auto px-4 flex justify-between items-center gap-4 h-full">
            <slot name="prepend">
                <Button
                    variant="ghost"
                    size="lg"
                    class="rounded-full"
                    @click="onBackClick"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span class="text-md font-semibold">
                        Back
                    </span>
                </Button>
            </slot>

            <slot name="append" />
        </div>
    </section>
</template>
