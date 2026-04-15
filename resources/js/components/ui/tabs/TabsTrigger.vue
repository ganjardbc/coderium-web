<script setup lang="ts">
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
  value: string
  class?: string
  disabled?: boolean
}

const props = defineProps<Props>()

const activeTab = inject<any>('activeTab')
const setActiveTab = inject<any>('setActiveTab')

const isActive = computed(() => activeTab?.value === props.value)

const handleClick = () => {
  if (!props.disabled && setActiveTab) {
    setActiveTab(props.value)
  }
}
</script>

<template>
  <button
    @click="handleClick"
    :disabled="disabled"
    :class="cn(
      'border-b-2 px-1 py-2 text-sm font-medium transition-colors cursor-pointer',
      isActive
        ? 'border-primary text-primary'
        : 'border-transparent text-muted-foreground hover:border-muted-foreground hover:text-foreground',
      disabled && 'opacity-50 cursor-not-allowed',
      $props.class
    )"
  >
    <slot />
  </button>
</template>
