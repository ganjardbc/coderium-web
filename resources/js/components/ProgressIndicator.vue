<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import {
  CheckCircle,
  XCircle,
  AlertCircle,
  Loader2,
  X,
  Pause,
  Play,
  RotateCcw
} from 'lucide-vue-next';

interface ProgressItem {
  id: string;
  name: string;
  status: 'pending' | 'in_progress' | 'completed' | 'failed' | 'cancelled';
  progress?: number;
  error?: string;
  startTime?: Date;
  endTime?: Date;
}

interface Props {
  title: string;
  items: ProgressItem[];
  showDetails?: boolean;
  allowCancel?: boolean;
  allowPause?: boolean;
  allowRetry?: boolean;
  autoClose?: boolean;
  autoCloseDelay?: number;
}

interface Emits {
  (e: 'cancel'): void;
  (e: 'pause'): void;
  (e: 'resume'): void;
  (e: 'retry', itemId: string): void;
  (e: 'close'): void;
}

const props = withDefaults(defineProps<Props>(), {
  showDetails: true,
  allowCancel: true,
  allowPause: false,
  allowRetry: true,
  autoClose: false,
  autoCloseDelay: 3000
});

const emit = defineEmits<Emits>();

// Local state
const isPaused = ref(false);
const showDetailsPanel = ref(props.showDetails);
const autoCloseTimer = ref<ReturnType<typeof setTimeout> | null>(null);

// Computed properties
const totalItems = computed(() => props.items.length);

const completedItems = computed(() =>
  props.items.filter(item => item.status === 'completed').length
);

const failedItems = computed(() =>
  props.items.filter(item => item.status === 'failed').length
);

const inProgressItems = computed(() =>
  props.items.filter(item => item.status === 'in_progress').length
);

const pendingItems = computed(() =>
  props.items.filter(item => item.status === 'pending').length
);

const cancelledItems = computed(() =>
  props.items.filter(item => item.status === 'cancelled').length
);

const overallProgress = computed(() => {
  if (totalItems.value === 0) return 0;

  const completed = completedItems.value;
  const failed = failedItems.value;
  const cancelled = cancelledItems.value;

  return ((completed + failed + cancelled) / totalItems.value) * 100;
});

const isCompleted = computed(() =>
  completedItems.value + failedItems.value + cancelledItems.value === totalItems.value
);

const hasErrors = computed(() => failedItems.value > 0);

const statusText = computed(() => {
  if (isPaused.value) {
    return 'Paused';
  }

  if (isCompleted.value) {
    if (hasErrors.value) {
      return `Completed with ${failedItems.value} error${failedItems.value !== 1 ? 's' : ''}`;
    }
    return 'Completed successfully';
  }

  if (inProgressItems.value > 0) {
    return `Processing ${inProgressItems.value} of ${totalItems.value} items...`;
  }

  return `${completedItems.value} of ${totalItems.value} completed`;
});

const statusVariant = computed(() => {
  if (hasErrors.value) return 'destructive';
  if (isCompleted.value) return 'success';
  if (isPaused.value) return 'secondary';
  return 'default';
});

const estimatedTimeRemaining = computed(() => {
  if (isCompleted.value || isPaused.value || inProgressItems.value === 0) {
    return null;
  }

  const completedWithTime = props.items.filter(item =>
    item.status === 'completed' && item.startTime && item.endTime
  );

  if (completedWithTime.length === 0) return null;

  const averageTime = completedWithTime.reduce((sum, item) => {
    const duration = item.endTime!.getTime() - item.startTime!.getTime();
    return sum + duration;
  }, 0) / completedWithTime.length;

  const remainingItems = pendingItems.value + inProgressItems.value;
  const estimatedMs = remainingItems * averageTime;

  if (estimatedMs < 60000) {
    return `${Math.ceil(estimatedMs / 1000)}s remaining`;
  } else if (estimatedMs < 3600000) {
    return `${Math.ceil(estimatedMs / 60000)}m remaining`;
  } else {
    return `${Math.ceil(estimatedMs / 3600000)}h remaining`;
  }
});

// Methods
const handleCancel = () => {
  emit('cancel');
};

const handlePause = () => {
  isPaused.value = true;
  emit('pause');
};

const handleResume = () => {
  isPaused.value = false;
  emit('resume');
};

const handleRetry = (itemId: string) => {
  emit('retry', itemId);
};

const handleClose = () => {
  if (autoCloseTimer.value) {
    clearTimeout(autoCloseTimer.value);
    autoCloseTimer.value = null;
  }
  emit('close');
};

const toggleDetails = () => {
  showDetailsPanel.value = !showDetailsPanel.value;
};

const getItemIcon = (item: ProgressItem) => {
  switch (item.status) {
    case 'completed':
      return CheckCircle;
    case 'failed':
      return XCircle;
    case 'cancelled':
      return XCircle;
    case 'in_progress':
      return Loader2;
    default:
      return AlertCircle;
  }
};

const getItemStatusColor = (status: string) => {
  switch (status) {
    case 'completed':
      return 'text-green-600';
    case 'failed':
      return 'text-red-600';
    case 'cancelled':
      return 'text-gray-600';
    case 'in_progress':
      return 'text-blue-600';
    default:
      return 'text-gray-400';
  }
};

// Auto-close functionality
const setupAutoClose = () => {
  if (props.autoClose && isCompleted.value && !hasErrors.value) {
    autoCloseTimer.value = setTimeout(() => {
      handleClose();
    }, props.autoCloseDelay);
  }
};

// Watchers
watch(isCompleted, (completed) => {
  if (completed) {
    setupAutoClose();
  }
});

// Lifecycle
onMounted(() => {
  if (isCompleted.value) {
    setupAutoClose();
  }
});

onUnmounted(() => {
  if (autoCloseTimer.value) {
    clearTimeout(autoCloseTimer.value);
  }
});
</script>

<template>
  <div class="progress-indicator bg-card border rounded-lg shadow-lg">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b">
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2">
          <Loader2
            v-if="!isCompleted && !isPaused"
            class="h-4 w-4 animate-spin text-primary"
          />
          <CheckCircle
            v-else-if="isCompleted && !hasErrors"
            class="h-4 w-4 text-green-600"
          />
          <XCircle
            v-else-if="isCompleted && hasErrors"
            class="h-4 w-4 text-red-600"
          />
          <Pause
            v-else-if="isPaused"
            class="h-4 w-4 text-gray-600"
          />

          <h3 class="font-semibold">{{ title }}</h3>
        </div>

        <Badge :variant="statusVariant" class="text-xs">
          {{ statusText }}
        </Badge>
      </div>

      <div class="flex items-center gap-2">
        <!-- Pause/Resume Button -->
        <Button
          v-if="allowPause && !isCompleted"
          variant="ghost"
          size="sm"
          @click="isPaused ? handleResume() : handlePause()"
        >
          <Play v-if="isPaused" class="h-4 w-4" />
          <Pause v-else class="h-4 w-4" />
        </Button>

        <!-- Cancel Button -->
        <Button
          v-if="allowCancel && !isCompleted"
          variant="ghost"
          size="sm"
          @click="handleCancel"
        >
          <X class="h-4 w-4" />
        </Button>

        <!-- Close Button -->
        <Button
          v-if="isCompleted"
          variant="ghost"
          size="sm"
          @click="handleClose"
        >
          <X class="h-4 w-4" />
        </Button>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="p-4 space-y-3">
      <div class="flex items-center justify-between text-sm">
        <span class="text-muted-foreground">
          {{ completedItems + failedItems + cancelledItems }} of {{ totalItems }} items
        </span>
        <span class="font-medium">{{ Math.round(overallProgress) }}%</span>
      </div>

      <Progress :value="overallProgress" class="h-2" />

      <div v-if="estimatedTimeRemaining" class="text-xs text-muted-foreground text-center">
        {{ estimatedTimeRemaining }}
      </div>
    </div>

    <!-- Summary Stats -->
    <div class="px-4 pb-4">
      <div class="flex items-center justify-between text-xs">
        <div class="flex items-center gap-4">
          <div v-if="completedItems > 0" class="flex items-center gap-1 text-green-600">
            <CheckCircle class="h-3 w-3" />
            <span>{{ completedItems }} completed</span>
          </div>
          <div v-if="failedItems > 0" class="flex items-center gap-1 text-red-600">
            <XCircle class="h-3 w-3" />
            <span>{{ failedItems }} failed</span>
          </div>
          <div v-if="inProgressItems > 0" class="flex items-center gap-1 text-blue-600">
            <Loader2 class="h-3 w-3 animate-spin" />
            <span>{{ inProgressItems }} in progress</span>
          </div>
        </div>

        <Button
          v-if="showDetails"
          variant="ghost"
          size="sm"
          @click="toggleDetails"
          class="text-xs"
        >
          {{ showDetailsPanel ? 'Hide' : 'Show' }} Details
        </Button>
      </div>
    </div>

    <!-- Details Panel -->
    <div v-if="showDetailsPanel && showDetails" class="border-t max-h-64 overflow-y-auto">
      <div class="p-2 space-y-1">
        <div
          v-for="item in items"
          :key="item.id"
          class="flex items-center justify-between p-2 rounded hover:bg-accent/50 text-sm"
        >
          <div class="flex items-center gap-2 flex-1 min-w-0">
            <component
              :is="getItemIcon(item)"
              :class="[
                'h-4 w-4 flex-shrink-0',
                getItemStatusColor(item.status),
                item.status === 'in_progress' ? 'animate-spin' : ''
              ]"
            />
            <span class="truncate">{{ item.name }}</span>
          </div>

          <div class="flex items-center gap-2">
            <div v-if="item.progress !== undefined" class="text-xs text-muted-foreground">
              {{ Math.round(item.progress) }}%
            </div>

            <Button
              v-if="allowRetry && item.status === 'failed'"
              variant="ghost"
              size="sm"
              @click="handleRetry(item.id)"
              class="h-6 w-6 p-0"
            >
              <RotateCcw class="h-3 w-3" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Details -->
    <div v-if="hasErrors && showDetailsPanel" class="border-t bg-destructive/5 p-3">
      <h4 class="text-sm font-medium text-destructive mb-2">Errors:</h4>
      <div class="space-y-1">
        <div
          v-for="item in items.filter(i => i.status === 'failed')"
          :key="item.id"
          class="text-xs text-destructive"
        >
          <span class="font-medium">{{ item.name }}:</span>
          <span class="ml-1">{{ item.error || 'Unknown error' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.progress-indicator {
  min-width: 400px;
  max-width: 600px;
}

@media (max-width: 640px) {
  .progress-indicator {
    min-width: 300px;
    max-width: 100%;
  }
}

/* Custom scrollbar for details panel */
.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: hsl(var(--border));
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: hsl(var(--muted-foreground));
}
</style>
