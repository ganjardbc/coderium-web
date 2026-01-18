<script setup lang="ts">
import { cn } from '@/lib/utils'

interface Props {
  class?: string
}

defineProps<Props>()
</script>

<template>
  <div class="mb-6">
    <div class="border-b border-border">
      <nav :class="cn('-mb-px flex space-x-8', $props.class)">
        <slot />
      </nav>
    </div>
  </div>
</template>
