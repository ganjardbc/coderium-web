<script setup lang="ts">
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
  value: string
  class?: string
}

const props = defineProps<Props>()

const activeTab = inject<any>('activeTab')

const isActive = computed(() => activeTab?.value === props.value)
</script>

<template>
  <div
    v-if="isActive"
    :class="cn('tabs-content', $props.class)"
  >
    <slot />
  </div>
</template>
