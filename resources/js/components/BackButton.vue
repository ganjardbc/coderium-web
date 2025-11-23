<script setup lang="ts">
import { ArrowLeft as ArrowLeft } from 'lucide-vue-next';

const onBackClick = () => {
    window.history.back();
};
</script>
<template>
    <!-- Back Button -->
    <section class="border-b bg-card/50 py-4">
        <div class="container mx-auto px-4">
            <button
                class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                @click="onBackClick"
            >
                <ArrowLeft class="h-4 w-4" />
                Back to Previous Page
            </button>
        </div>
    </section>
</template>
