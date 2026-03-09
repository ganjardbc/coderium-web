<script setup lang="ts">
import { ref, onMounted, onUnmounted, type Component } from 'vue';
import { usePerformanceOptimization } from '@/composables/usePerformanceOptimization';

interface Props {
  component: () => Promise<Component>;
  fallback?: Component;
  threshold?: number;
  rootMargin?: string;
  preload?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  threshold: 0.1,
  rootMargin: '50px',
  preload: false
});

const { loadComponent, preloadComponent, setupLazyLoading } = usePerformanceOptimization();

const containerRef = ref<HTMLElement>();
const loadedComponent = ref<Component | null>(null);
const isLoading = ref(false);
const hasError = ref(false);
const error = ref<Error | null>(null);

const loadComponentAsync = async () => {
  if (loadedComponent.value || isLoading.value) return;

  try {
    isLoading.value = true;
    hasError.value = false;
    error.value = null;

    const component = await loadComponent(props.component);
    loadedComponent.value = component.default || component;
  } catch (err) {
    hasError.value = true;
    error.value = err as Error;
    console.error('Failed to load lazy component:', err);
  } finally {
    isLoading.value = false;
  }
};

let cleanupLazyLoading: (() => void) | null = null;

onMounted(() => {
  if (props.preload) {
    // Preload component immediately
    loadComponentAsync();
  } else if (containerRef.value) {
    // Setup lazy loading
    cleanupLazyLoading = setupLazyLoading(
      ref([containerRef.value]),
      () => loadComponentAsync(),
      {
        threshold: props.threshold,
        rootMargin: props.rootMargin,
        triggerOnce: true
      }
    );
  }

  // Preload component in background if requested
  if (props.preload) {
    preloadComponent(props.component);
  }
});

onUnmounted(() => {
  if (cleanupLazyLoading) {
    cleanupLazyLoading();
  }
});
</script>

<template>
  <div ref="containerRef" class="lazy-component">
    <!-- Loaded Component -->
    <component
      v-if="loadedComponent && !hasError"
      :is="loadedComponent"
      v-bind="$attrs"
    />

    <!-- Loading State -->
    <div v-else-if="isLoading" class="lazy-loading">
      <slot name="loading">
        <div class="flex items-center justify-center p-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
          <span class="ml-2 text-muted-foreground">Loading...</span>
        </div>
      </slot>
    </div>

    <!-- Error State -->
    <div v-else-if="hasError" class="lazy-error">
      <slot name="error" :error="error" :retry="loadComponentAsync">
        <div class="flex flex-col items-center justify-center p-8 text-center">
          <div class="text-destructive mb-2">Failed to load component</div>
          <button
            @click="loadComponentAsync"
            class="text-sm text-primary hover:underline"
          >
            Try again
          </button>
        </div>
      </slot>
    </div>

    <!-- Fallback Component -->
    <component
      v-else-if="fallback"
      :is="fallback"
      v-bind="$attrs"
    />

    <!-- Default Placeholder -->
    <div v-else class="lazy-placeholder">
      <slot name="placeholder">
        <div class="h-32 bg-muted rounded-lg animate-pulse"></div>
      </slot>
    </div>
  </div>
</template>

<style scoped>
.lazy-component {
  min-height: 1rem;
}

.lazy-loading,
.lazy-error,
.lazy-placeholder {
  min-height: 8rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lazy-error {
  border: 1px dashed hsl(var(--destructive));
  border-radius: 0.5rem;
  background-color: hsl(var(--destructive) / 0.05);
}

.lazy-placeholder {
  background-color: hsl(var(--muted));
  border-radius: 0.5rem;
}

/* Animation for loading state */
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
