<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  class?: string;
  orientation?: 'vertical' | 'horizontal' | 'both';
  type?: 'auto' | 'always' | 'scroll' | 'hover';
}

const props = withDefaults(defineProps<Props>(), {
  class: '',
  orientation: 'vertical',
  type: 'auto'
});

const scrollAreaClass = computed(() => {
  const baseClasses = 'relative overflow-hidden';
  const orientationClasses = {
    vertical: 'overflow-y-auto',
    horizontal: 'overflow-x-auto',
    both: 'overflow-auto'
  };

  return `${baseClasses} ${orientationClasses[props.orientation]} ${props.class}`;
});
</script>

<template>
  <div :class="scrollAreaClass">
    <slot />
  </div>
</template>

<style scoped>
/* Custom scrollbar styling */
:deep(.overflow-auto)::-webkit-scrollbar,
:deep(.overflow-y-auto)::-webkit-scrollbar,
:deep(.overflow-x-auto)::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

:deep(.overflow-auto)::-webkit-scrollbar-track,
:deep(.overflow-y-auto)::-webkit-scrollbar-track,
:deep(.overflow-x-auto)::-webkit-scrollbar-track {
  background: transparent;
}

:deep(.overflow-auto)::-webkit-scrollbar-thumb,
:deep(.overflow-y-auto)::-webkit-scrollbar-thumb,
:deep(.overflow-x-auto)::-webkit-scrollbar-thumb {
  background: hsl(var(--border));
  border-radius: 4px;
}

:deep(.overflow-auto)::-webkit-scrollbar-thumb:hover,
:deep(.overflow-y-auto)::-webkit-scrollbar-thumb:hover,
:deep(.overflow-x-auto)::-webkit-scrollbar-thumb:hover {
  background: hsl(var(--muted-foreground));
}

:deep(.overflow-auto)::-webkit-scrollbar-corner,
:deep(.overflow-y-auto)::-webkit-scrollbar-corner,
:deep(.overflow-x-auto)::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
