<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsItem } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  Zap,
  Search,
  Filter,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Clock,
  Play,
  Pause,
  Square,
  BarChart3,
  Download,
  Upload,
  Settings,
  Eye,
  Loader2,
  Target,
  Package,
  TrendingUp,
  AlertCircle
} from 'lucide-vue-next';
import {
  AssignmentTarget,
  BulkAssignmentOperation,
  BulkAssignmentResult,
  BulkAssignmentError,
  StandaloneModule,
  BulkAssignmentRequest,
  UnlockCondition,
  AssignmentCustomization
} from '@/types/enhanced-classroom';
import { useAssignmentWorkflow } from '@/composables/useAssignmentWorkflow';
import { useNotifications } from '@/composables/useNotifications';

interface Props {
  selectedModules: string[];
  availableTargets: AssignmentTarget[];
  showProgressIndicator?: boolean;
  enablePreview?: boolean;
  enableAnalytics?: boolean;
  maxBatchSize?: number;
}

interface Emits {
  (e: 'bulkAssignmentStarted', operation: BulkAssignmentOperation): void;
  (e: 'bulkAssignmentCompleted', results: BulkAssignmentResult[]): void;
  (e: 'bulkAssignmentCancelled'): void;
  (e: 'close'): void;
}

const props = withDefaults(defineProps<Props>(), {
  showProgressIndicator: true,
  enablePreview: true,
  enableAnalytics: true,
  maxBatchSize: 100
});

const emit = defineEmits<Emits>();

// Composables
const { bulkAssignModules, cancelBulkOperation, activeOperations } = useAssignmentWorkflow();
const { showNotification } = useNotifications();

// Reactive state
const isOpen = ref(true);
const currentStep = ref<'selection' | 'configuration' | 'preview' | 'execution' | 'results'>('selection');
const selectedTargets = ref<string[]>([]);
const searchQuery = ref('');
const targetFilters = ref({
  type: [] as string[],
  hasModules: 'all' as 'all' | 'empty' | 'has_modules'
});

// Configuration state
const assignmentConfig = ref({
  isRequired: true,
  assignToAll: false,
  preserveOrder: true,
  skipConflicts: true,
  batchSize: 10,
  delayBetweenBatches: 100, // milliseconds
  unlockConditions: [] as UnlockCondition[],
  customization: null as AssignmentCustomization | null
});

// Operation state
const currentOperation = ref<BulkAssignmentOperation | null>(null);
const operationResults = ref<BulkAssignmentResult[]>([]);
const operationErrors = ref<BulkAssignmentError[]>([]);
const operationProgress = ref(0);
const isExecuting = ref(false);
const isPaused = ref(false);
const isCancelled = ref(false);

// Analytics state
const analyticsData = ref({
  estimatedDuration: 0,
  conflictPredictions: [] as any[],
  successProbability: 0,
  resourceUsage: {
    cpu: 0,
    memory: 0,
    network: 0
  }
});

// Computed properties
const filteredTargets = computed(() => {
  let filtered = props.availableTargets;

  // Apply search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(target =>
      target.title.toLowerCase().includes(query) ||
      target.description?.toLowerCase().includes(query)
    );
  }

  // Apply type filter
  if (targetFilters.value.type.length > 0) {
    filtered = filtered.filter(target =>
      targetFilters.value.type.includes(target.type)
    );
  }

  // Apply module count filter
  if (targetFilters.value.hasModules !== 'all') {
    filtered = filtered.filter(target => {
      const hasModules = target.currentAssignments.length > 0;
      return targetFilters.value.hasModules === 'has_modules' ? hasModules : !hasModules;
    });
  }

  return filtered;
});

const totalOperations = computed(() => {
  return props.selectedModules.length * selectedTargets.value.length;
});

const estimatedBatches = computed(() => {
  return Math.ceil(totalOperations.value / assignmentConfig.value.batchSize);
});

const estimatedDuration = computed(() => {
  const baseTimePerOperation = 50; // milliseconds
  const networkLatency = 100; // milliseconds per batch
  const totalTime = (totalOperations.value * baseTimePerOperation) +
                   (estimatedBatches.value * networkLatency) +
                   (estimatedBatches.value * assignmentConfig.value.delayBetweenBatches);
  return Math.ceil(totalTime / 1000); // Convert to seconds
});

const canProceed = computed(() => {
  switch (currentStep.value) {
    case 'selection':
      return selectedTargets.value.length > 0;
    case 'configuration':
      return assignmentConfig.value.batchSize > 0 && assignmentConfig.value.batchSize <= props.maxBatchSize;
    case 'preview':
      return true;
    case 'execution':
      return false;
    case 'results':
      return true;
    default:
      return false;
  }
});

const operationSummary = computed(() => {
  if (operationResults.value.length === 0) return null;

  const successful = operationResults.value.filter(r => r.status === 'success').length;
  const failed = operationResults.value.filter(r => r.status === 'failed').length;
  const skipped = operationResults.value.filter(r => r.status === 'skipped').length;

  return {
    total: operationResults.value.length,
    successful,
    failed,
    skipped,
    successRate: operationResults.value.length > 0 ? (successful / operationResults.value.length) * 100 : 0
  };
});

// Methods
const handleTargetToggle = (targetId: string) => {
  const index = selectedTargets.value.indexOf(targetId);
  if (index > -1) {
    selectedTargets.value.splice(index, 1);
  } else {
    selectedTargets.value.push(targetId);
  }
};

const selectAllTargets = () => {
  selectedTargets.value = filteredTargets.value.map(t => t.id);
};

const clearTargetSelection = () => {
  selectedTargets.value = [];
};

const nextStep = () => {
  switch (currentStep.value) {
    case 'selection':
      currentStep.value = 'configuration';
      break;
    case 'configuration':
      if (props.enablePreview) {
        currentStep.value = 'preview';
        generatePreview();
      } else {
        executeOperation();
      }
      break;
    case 'preview':
      executeOperation();
      break;
    case 'execution':
      currentStep.value = 'results';
      break;
    case 'results':
      close();
      break;
  }
};

const previousStep = () => {
  switch (currentStep.value) {
    case 'configuration':
      currentStep.value = 'selection';
      break;
    case 'preview':
      currentStep.value = 'configuration';
      break;
    case 'results':
      currentStep.value = 'execution';
      break;
  }
};

const generatePreview = async () => {
  // Generate conflict predictions and analytics
  try {
    analyticsData.value = {
      estimatedDuration: estimatedDuration.value,
      conflictPredictions: await predictConflicts(),
      successProbability: calculateSuccessProbability(),
      resourceUsage: estimateResourceUsage()
    };
  } catch (error) {
    console.error('Failed to generate preview:', error);
  }
};

const predictConflicts = async (): Promise<any[]> => {
  // Simulate conflict prediction
  const conflicts = [];

  for (const moduleId of props.selectedModules) {
    for (const targetId of selectedTargets.value) {
      const target = props.availableTargets.find(t => t.id === targetId);
      if (target) {
        // Check for existing assignments
        const hasExisting = target.currentAssignments.some(a => a.moduleId === moduleId);
        if (hasExisting) {
          conflicts.push({
            moduleId,
            targetId,
            type: 'duplicate',
            severity: 'error',
            description: 'Module already assigned to this target'
          });
        }

        // Check capacity limits
        if (target.maxModules && target.currentAssignments.length >= target.maxModules) {
          conflicts.push({
            moduleId,
            targetId,
            type: 'capacity',
            severity: 'warning',
            description: 'Target is at maximum capacity'
          });
        }
      }
    }
  }

  return conflicts;
};

const calculateSuccessProbability = (): number => {
  // Simple success probability calculation
  const conflictCount = analyticsData.value.conflictPredictions.length;
  const totalOperations = props.selectedModules.length * selectedTargets.value.length;

  if (totalOperations === 0) return 100;

  const conflictRate = conflictCount / totalOperations;
  return Math.max(0, Math.min(100, (1 - conflictRate) * 100));
};

const estimateResourceUsage = () => {
  const operationCount = totalOperations.value;

  return {
    cpu: Math.min(100, operationCount * 0.1),
    memory: Math.min(100, operationCount * 0.05),
    network: Math.min(100, operationCount * 0.2)
  };
};

const executeOperation = async () => {
  if (isExecuting.value) return;

  currentStep.value = 'execution';
  isExecuting.value = true;
  isPaused.value = false;
  isCancelled.value = false;
  operationProgress.value = 0;
  operationResults.value = [];
  operationErrors.value = [];

  try {
    const operation: BulkAssignmentRequest = {
      moduleIds: props.selectedModules,
      targetIds: selectedTargets.value,
      targetType: 'course', // This should be determined dynamically
      assignmentOptions: {
        isRequired: assignmentConfig.value.isRequired,
        unlockConditions: assignmentConfig.value.unlockConditions,
        customization: assignmentConfig.value.customization
      }
    };

    // Create operation tracking
    currentOperation.value = {
      id: `bulk_${Date.now()}`,
      moduleIds: operation.moduleIds,
      targetIds: operation.targetIds,
      targetType: operation.targetType,
      status: 'in_progress',
      progress: 0,
      startedAt: new Date(),
      results: [],
      errors: []
    };

    emit('bulkAssignmentStarted', currentOperation.value);

    // Execute in batches
    await executeBatchedOperation(operation);

    currentOperation.value.status = 'completed';
    currentOperation.value.completedAt = new Date();
    currentOperation.value.progress = 100;

    emit('bulkAssignmentCompleted', operationResults.value);

    showNotification({
      type: 'success',
      title: 'Bulk Assignment Complete',
      message: `${operationSummary.value?.successful || 0} assignments created successfully`
    });

    currentStep.value = 'results';

  } catch (error) {
    currentOperation.value!.status = 'failed';
    currentOperation.value!.completedAt = new Date();

    showNotification({
      type: 'error',
      title: 'Bulk Assignment Failed',
      message: error instanceof Error ? error.message : 'Operation failed'
    });

    currentStep.value = 'results';
  } finally {
    isExecuting.value = false;
  }
};

const executeBatchedOperation = async (operation: BulkAssignmentRequest) => {
  const totalOps = operation.moduleIds.length * operation.targetIds.length;
  let completedOps = 0;

  // Create batches
  const batches = createBatches(operation);

  for (let i = 0; i < batches.length && !isCancelled.value; i++) {
    if (isPaused.value) {
      await waitForResume();
    }

    const batch = batches[i];

    try {
      const batchResults = await bulkAssignModules(batch);
      operationResults.value.push(...batchResults);

      completedOps += batch.moduleIds.length * batch.targetIds.length;
      operationProgress.value = (completedOps / totalOps) * 100;

      if (currentOperation.value) {
        currentOperation.value.progress = operationProgress.value;
        currentOperation.value.results = [...operationResults.value];
      }

      // Delay between batches
      if (i < batches.length - 1 && assignmentConfig.value.delayBetweenBatches > 0) {
        await new Promise(resolve => setTimeout(resolve, assignmentConfig.value.delayBetweenBatches));
      }

    } catch (error) {
      const batchError: BulkAssignmentError = {
        moduleId: batch.moduleIds[0] || 'unknown',
        targetId: batch.targetIds[0] || 'unknown',
        error: error instanceof Error ? error.message : 'Batch failed',
        code: 'BATCH_ERROR'
      };

      operationErrors.value.push(batchError);

      if (currentOperation.value) {
        currentOperation.value.errors = [...operationErrors.value];
      }
    }
  }
};

const createBatches = (operation: BulkAssignmentRequest): BulkAssignmentRequest[] => {
  const batches: BulkAssignmentRequest[] = [];
  const batchSize = assignmentConfig.value.batchSize;

  // Simple batching strategy - split by modules
  for (let i = 0; i < operation.moduleIds.length; i += batchSize) {
    const moduleSlice = operation.moduleIds.slice(i, i + batchSize);

    batches.push({
      ...operation,
      moduleIds: moduleSlice
    });
  }

  return batches;
};

const pauseOperation = () => {
  isPaused.value = true;

  showNotification({
    type: 'info',
    title: 'Operation Paused',
    message: 'Bulk assignment operation has been paused'
  });
};

const resumeOperation = () => {
  isPaused.value = false;

  showNotification({
    type: 'info',
    title: 'Operation Resumed',
    message: 'Bulk assignment operation has been resumed'
  });
};

const cancelOperation = async () => {
  isCancelled.value = true;

  if (currentOperation.value) {
    try {
      await cancelBulkOperation(currentOperation.value.id);
      currentOperation.value.status = 'cancelled';

      emit('bulkAssignmentCancelled');

      showNotification({
        type: 'warning',
        title: 'Operation Cancelled',
        message: 'Bulk assignment operation has been cancelled'
      });

    } catch (error) {
      showNotification({
        type: 'error',
        title: 'Cancellation Failed',
        message: 'Failed to cancel the operation'
      });
    }
  }

  currentStep.value = 'results';
  isExecuting.value = false;
};

const waitForResume = (): Promise<void> => {
  return new Promise((resolve) => {
    const checkResume = () => {
      if (!isPaused.value || isCancelled.value) {
        resolve();
      } else {
        setTimeout(checkResume, 100);
      }
    };
    checkResume();
  });
};

const exportResults = () => {
  if (!operationSummary.value) return;

  const data = {
    operation: currentOperation.value,
    results: operationResults.value,
    errors: operationErrors.value,
    summary: operationSummary.value,
    timestamp: new Date().toISOString()
  };

  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `bulk-assignment-results-${Date.now()}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);

  showNotification({
    type: 'success',
    title: 'Results Exported',
    message: 'Operation results have been exported successfully'
  });
};

const close = () => {
  isOpen.value = false;
  emit('close');
};

// Lifecycle
onMounted(() => {
  // Initialize with available targets if none selected
  if (selectedTargets.value.length === 0 && props.availableTargets.length > 0) {
    selectedTargets.value = [props.availableTargets[0].id];
  }
});

// Watch for operation updates
watch(activeOperations, (operations) => {
  const currentOp = operations.find(op => op.id === currentOperation.value?.id);
  if (currentOp) {
    currentOperation.value = currentOp;
    operationProgress.value = currentOp.progress;
    operationResults.value = currentOp.results;
    operationErrors.value = currentOp.errors;
  }
}, { deep: true });
</script>

<template>
  <Dialog :open="isOpen" @update:open="close">
    <DialogContent class="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Zap class="h-5 w-5" />
          Bulk Assignment Manager
          <Badge variant="outline">
            {{ props.selectedModules.length }} modules
          </Badge>
        </DialogTitle>
      </DialogHeader>

      <!-- Progress Indicator -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <div
            v-for="(step, index) in ['selection', 'configuration', 'preview', 'execution', 'results']"
            :key="step"
            class="flex items-center"
          >
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium"
              :class="{
                'bg-primary text-primary-foreground': currentStep === step,
                'bg-green-500 text-white': ['selection', 'configuration', 'preview', 'execution'].indexOf(step) < ['selection', 'configuration', 'preview', 'execution'].indexOf(currentStep),
                'bg-muted text-muted-foreground': ['selection', 'configuration', 'preview', 'execution'].indexOf(step) > ['selection', 'configuration', 'preview', 'execution'].indexOf(currentStep)
              }"
            >
              {{ index + 1 }}
            </div>
            <div
              v-if="index < 4"
              class="w-8 h-0.5 mx-2"
              :class="{
                'bg-green-500': ['selection', 'configuration', 'preview', 'execution'].indexOf(step) < ['selection', 'configuration', 'preview', 'execution'].indexOf(currentStep),
                'bg-muted': ['selection', 'configuration', 'preview', 'execution'].indexOf(step) >= ['selection', 'configuration', 'preview', 'execution'].indexOf(currentStep)
              }"
            />
          </div>
        </div>

        <div class="text-sm text-muted-foreground capitalize">
          {{ currentStep.replace('_', ' ') }}
        </div>
      </div>

      <!-- Step Content -->
      <div class="flex-1 overflow-hidden">
        <!-- Selection Step -->
        <div v-if="currentStep === 'selection'" class="h-full flex flex-col">
          <div class="mb-4">
            <h3 class="text-lg font-medium mb-2">Select Assignment Targets</h3>
            <p class="text-sm text-muted-foreground">
              Choose which targets to assign the {{ props.selectedModules.length }} selected modules to.
            </p>
          </div>

          <!-- Search and Filters -->
          <div class="flex gap-4 mb-4">
            <div class="relative flex-1">
              <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="searchQuery"
                placeholder="Search targets..."
                class="pl-10"
              />
            </div>

            <div class="flex gap-2">
              <Button variant="outline" size="sm" @click="selectAllTargets">
                Select All
              </Button>
              <Button variant="outline" size="sm" @click="clearTargetSelection">
                Clear
              </Button>
            </div>
          </div>

          <!-- Target List -->
          <ScrollArea class="flex-1">
            <div class="space-y-2">
              <Card
                v-for="target in filteredTargets"
                :key="target.id"
                class="cursor-pointer transition-colors"
                :class="{
                  'border-primary bg-primary/5': selectedTargets.includes(target.id)
                }"
                @click="handleTargetToggle(target.id)"
              >
                <CardContent class="p-4">
                  <div class="flex items-start gap-3">
                    <Checkbox
                      :checked="selectedTargets.includes(target.id)"
                      @update:checked="handleTargetToggle(target.id)"
                    />
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1">
                        <h4 class="font-medium truncate">{{ target.title }}</h4>
                        <Badge variant="outline" class="text-xs">
                          {{ target.type }}
                        </Badge>
                      </div>
                      <p class="text-sm text-muted-foreground mb-2">
                        {{ target.description }}
                      </p>
                      <div class="flex items-center gap-4 text-xs text-muted-foreground">
                        <span>{{ target.currentAssignments.length }} modules assigned</span>
                        <span v-if="target.maxModules">
                          Capacity: {{ target.currentAssignments.length }}/{{ target.maxModules }}
                        </span>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </ScrollArea>

          <!-- Selection Summary -->
          <div class="mt-4 p-3 bg-muted rounded-lg">
            <div class="flex items-center justify-between text-sm">
              <span>Selected Targets: {{ selectedTargets.length }}</span>
              <span>Total Operations: {{ totalOperations }}</span>
            </div>
          </div>
        </div>

        <!-- Configuration Step -->
        <div v-if="currentStep === 'configuration'" class="h-full flex flex-col">
          <div class="mb-4">
            <h3 class="text-lg font-medium mb-2">Configure Assignment Options</h3>
            <p class="text-sm text-muted-foreground">
              Set up how the bulk assignment should be executed.
            </p>
          </div>

          <ScrollArea class="flex-1">
            <div class="space-y-6">
              <!-- Basic Options -->
              <Card>
                <CardHeader>
                  <CardTitle class="text-sm">Basic Options</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div class="flex items-center space-x-2">
                    <Checkbox
                      id="required"
                      v-model:checked="assignmentConfig.isRequired"
                    />
                    <label for="required" class="text-sm">Mark assignments as required</label>
                  </div>

                  <div class="flex items-center space-x-2">
                    <Checkbox
                      id="preserve-order"
                      v-model:checked="assignmentConfig.preserveOrder"
                    />
                    <label for="preserve-order" class="text-sm">Preserve module order</label>
                  </div>

                  <div class="flex items-center space-x-2">
                    <Checkbox
                      id="skip-conflicts"
                      v-model:checked="assignmentConfig.skipConflicts"
                    />
                    <label for="skip-conflicts" class="text-sm">Skip conflicting assignments</label>
                  </div>
                </CardContent>
              </Card>

              <!-- Batch Configuration -->
              <Card>
                <CardHeader>
                  <CardTitle class="text-sm">Batch Configuration</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div>
                    <label class="text-sm font-medium mb-2 block">
                      Batch Size: {{ assignmentConfig.batchSize }}
                    </label>
                    <input
                      v-model.number="assignmentConfig.batchSize"
                      type="range"
                      min="1"
                      :max="maxBatchSize"
                      class="w-full"
                    />
                    <div class="flex justify-between text-xs text-muted-foreground mt-1">
                      <span>1</span>
                      <span>{{ maxBatchSize }}</span>
                    </div>
                  </div>

                  <div>
                    <label class="text-sm font-medium mb-2 block">
                      Delay Between Batches: {{ assignmentConfig.delayBetweenBatches }}ms
                    </label>
                    <input
                      v-model.number="assignmentConfig.delayBetweenBatches"
                      type="range"
                      min="0"
                      max="1000"
                      step="50"
                      class="w-full"
                    />
                    <div class="flex justify-between text-xs text-muted-foreground mt-1">
                      <span>0ms</span>
                      <span>1000ms</span>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <!-- Estimated Impact -->
              <Card>
                <CardHeader>
                  <CardTitle class="text-sm">Estimated Impact</CardTitle>
                </CardHeader>
                <CardContent>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span class="text-muted-foreground">Total Operations:</span>
                      <span class="font-medium ml-2">{{ totalOperations }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">Estimated Batches:</span>
                      <span class="font-medium ml-2">{{ estimatedBatches }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">Estimated Duration:</span>
                      <span class="font-medium ml-2">{{ estimatedDuration }}s</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">Max Batch Size:</span>
                      <span class="font-medium ml-2">{{ maxBatchSize }}</span>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </ScrollArea>
        </div>

        <!-- Preview Step -->
        <div v-if="currentStep === 'preview'" class="h-full flex flex-col">
          <div class="mb-4">
            <h3 class="text-lg font-medium mb-2">Preview & Analytics</h3>
            <p class="text-sm text-muted-foreground">
              Review the operation details and potential issues before execution.
            </p>
          </div>

          <Tabs default-value="summary" class="flex-1 flex flex-col">
            <TabsList class="grid w-full grid-cols-3">
              <TabsItem value="summary">Summary</TabsItem>
              <TabsItem value="conflicts">Conflicts</TabsItem>
              <TabsItem value="analytics">Analytics</TabsItem>
            </TabsList>

            <TabsContent value="summary" class="flex-1 overflow-auto">
              <div class="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle class="text-sm">Operation Summary</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                      <div>Modules to Assign: {{ props.selectedModules.length }}</div>
                      <div>Target Destinations: {{ selectedTargets.length }}</div>
                      <div>Total Operations: {{ totalOperations }}</div>
                      <div>Batch Size: {{ assignmentConfig.batchSize }}</div>
                      <div>Estimated Duration: {{ estimatedDuration }}s</div>
                      <div>Success Probability: {{ Math.round(analyticsData.successProbability) }}%</div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle class="text-sm">Configuration</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="space-y-2 text-sm">
                      <div class="flex justify-between">
                        <span>Required Assignments:</span>
                        <Badge :variant="assignmentConfig.isRequired ? 'default' : 'secondary'">
                          {{ assignmentConfig.isRequired ? 'Yes' : 'No' }}
                        </Badge>
                      </div>
                      <div class="flex justify-between">
                        <span>Skip Conflicts:</span>
                        <Badge :variant="assignmentConfig.skipConflicts ? 'default' : 'secondary'">
                          {{ assignmentConfig.skipConflicts ? 'Yes' : 'No' }}
                        </Badge>
                      </div>
                      <div class="flex justify-between">
                        <span>Preserve Order:</span>
                        <Badge :variant="assignmentConfig.preserveOrder ? 'default' : 'secondary'">
                          {{ assignmentConfig.preserveOrder ? 'Yes' : 'No' }}
                        </Badge>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <TabsContent value="conflicts" class="flex-1 overflow-auto">
              <div v-if="analyticsData.conflictPredictions.length === 0" class="text-center py-8">
                <CheckCircle class="h-12 w-12 text-green-500 mx-auto mb-4" />
                <h3 class="text-lg font-medium mb-2">No Conflicts Detected</h3>
                <p class="text-muted-foreground">All assignments should proceed without issues.</p>
              </div>

              <div v-else class="space-y-2">
                <Card
                  v-for="conflict in analyticsData.conflictPredictions"
                  :key="`${conflict.moduleId}-${conflict.targetId}`"
                  :class="{
                    'border-red-200': conflict.severity === 'error',
                    'border-yellow-200': conflict.severity === 'warning'
                  }"
                >
                  <CardContent class="p-4">
                    <div class="flex items-start gap-3">
                      <AlertTriangle
                        class="h-4 w-4 mt-0.5"
                        :class="{
                          'text-red-500': conflict.severity === 'error',
                          'text-yellow-500': conflict.severity === 'warning'
                        }"
                      />
                      <div class="flex-1">
                        <p class="text-sm font-medium">{{ conflict.description }}</p>
                        <p class="text-xs text-muted-foreground mt-1">
                          Module: {{ conflict.moduleId }} → Target: {{ conflict.targetId }}
                        </p>
                      </div>
                      <Badge
                        :variant="conflict.severity === 'error' ? 'destructive' : 'secondary'"
                        class="text-xs"
                      >
                        {{ conflict.type }}
                      </Badge>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <TabsContent value="analytics" class="flex-1 overflow-auto">
              <div class="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle class="text-sm">Resource Usage Estimate</CardTitle>
                  </CardHeader>
                  <CardContent class="space-y-3">
                    <div>
                      <div class="flex justify-between text-sm mb-1">
                        <span>CPU Usage</span>
                        <span>{{ Math.round(analyticsData.resourceUsage.cpu) }}%</span>
                      </div>
                      <Progress :value="analyticsData.resourceUsage.cpu" class="h-2" />
                    </div>
                    <div>
                      <div class="flex justify-between text-sm mb-1">
                        <span>Memory Usage</span>
                        <span>{{ Math.round(analyticsData.resourceUsage.memory) }}%</span>
                      </div>
                      <Progress :value="analyticsData.resourceUsage.memory" class="h-2" />
                    </div>
                    <div>
                      <div class="flex justify-between text-sm mb-1">
                        <span>Network Usage</span>
                        <span>{{ Math.round(analyticsData.resourceUsage.network) }}%</span>
                      </div>
                      <Progress :value="analyticsData.resourceUsage.network" class="h-2" />
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle class="text-sm">Success Metrics</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="text-center">
                      <div class="text-3xl font-bold text-green-600 mb-2">
                        {{ Math.round(analyticsData.successProbability) }}%
                      </div>
                      <p class="text-sm text-muted-foreground">
                        Estimated Success Rate
                      </p>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>
          </Tabs>
        </div>

        <!-- Execution Step -->
        <div v-if="currentStep === 'execution'" class="h-full flex flex-col items-center justify-center">
          <div class="text-center max-w-md">
            <div class="mb-6">
              <Loader2 v-if="!isPaused" class="h-12 w-12 animate-spin mx-auto mb-4" />
              <Pause v-else class="h-12 w-12 mx-auto mb-4 text-yellow-500" />
            </div>

            <h3 class="text-lg font-medium mb-2">
              {{ isPaused ? 'Operation Paused' : 'Executing Bulk Assignment' }}
            </h3>
            <p class="text-muted-foreground mb-6">
              {{ isPaused ? 'Operation is paused. Click resume to continue.' : 'Please wait while assignments are being created...' }}
            </p>

            <!-- Progress -->
            <div class="mb-6">
              <div class="flex justify-between text-sm mb-2">
                <span>Progress</span>
                <span>{{ Math.round(operationProgress) }}%</span>
              </div>
              <Progress :value="operationProgress" class="h-3" />
              <div class="flex justify-between text-xs text-muted-foreground mt-1">
                <span>{{ operationResults.length }} completed</span>
                <span>{{ totalOperations }} total</span>
              </div>
            </div>

            <!-- Controls -->
            <div class="flex gap-2 justify-center">
              <Button
                v-if="!isPaused"
                variant="outline"
                size="sm"
                @click="pauseOperation"
                :disabled="!isExecuting"
              >
                <Pause class="h-4 w-4 mr-2" />
                Pause
              </Button>
              <Button
                v-else
                variant="outline"
                size="sm"
                @click="resumeOperation"
              >
                <Play class="h-4 w-4 mr-2" />
                Resume
              </Button>
              <Button
                variant="destructive"
                size="sm"
                @click="cancelOperation"
                :disabled="!isExecuting && !isPaused"
              >
                <Square class="h-4 w-4 mr-2" />
                Cancel
              </Button>
            </div>
          </div>
        </div>

        <!-- Results Step -->
        <div v-if="currentStep === 'results'" class="h-full flex flex-col">
          <div class="mb-4">
            <h3 class="text-lg font-medium mb-2">Operation Results</h3>
            <p class="text-sm text-muted-foreground">
              Review the results of the bulk assignment operation.
            </p>
          </div>

          <!-- Summary Cards -->
          <div class="grid grid-cols-4 gap-4 mb-6">
            <Card>
              <CardContent class="p-4 text-center">
                <div class="text-2xl font-bold text-green-600">
                  {{ operationSummary?.successful || 0 }}
                </div>
                <div class="text-xs text-muted-foreground">Successful</div>
              </CardContent>
            </Card>
            <Card>
              <CardContent class="p-4 text-center">
                <div class="text-2xl font-bold text-red-600">
                  {{ operationSummary?.failed || 0 }}
                </div>
                <div class="text-xs text-muted-foreground">Failed</div>
              </CardContent>
            </Card>
            <Card>
              <CardContent class="p-4 text-center">
                <div class="text-2xl font-bold text-yellow-600">
                  {{ operationSummary?.skipped || 0 }}
                </div>
                <div class="text-xs text-muted-foreground">Skipped</div>
              </CardContent>
            </Card>
            <Card>
              <CardContent class="p-4 text-center">
                <div class="text-2xl font-bold">
                  {{ Math.round(operationSummary?.successRate || 0) }}%
                </div>
                <div class="text-xs text-muted-foreground">Success Rate</div>
              </CardContent>
            </Card>
          </div>

          <!-- Detailed Results -->
          <Tabs default-value="results" class="flex-1 flex flex-col">
            <TabsList class="grid w-full grid-cols-2">
              <TabsItem value="results">Results</TabsItem>
              <TabsItem value="errors">Errors</TabsItem>
            </TabsList>

            <TabsContent value="results" class="flex-1 overflow-auto">
              <ScrollArea class="h-full">
                <div class="space-y-2">
                  <div
                    v-for="result in operationResults"
                    :key="`${result.moduleId}-${result.targetId}`"
                    class="p-3 border rounded-lg"
                    :class="{
                      'border-green-200 bg-green-50': result.status === 'success',
                      'border-red-200 bg-red-50': result.status === 'failed',
                      'border-yellow-200 bg-yellow-50': result.status === 'skipped'
                    }"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <CheckCircle v-if="result.status === 'success'" class="h-4 w-4 text-green-600" />
                        <XCircle v-else-if="result.status === 'failed'" class="h-4 w-4 text-red-600" />
                        <AlertCircle v-else class="h-4 w-4 text-yellow-600" />
                        <span class="text-sm font-medium">
                          {{ result.moduleId }} → {{ result.targetId }}
                        </span>
                      </div>
                      <Badge
                        :variant="result.status === 'success' ? 'default' : result.status === 'failed' ? 'destructive' : 'secondary'"
                        class="text-xs"
                      >
                        {{ result.status }}
                      </Badge>
                    </div>
                    <p v-if="result.reason" class="text-xs text-muted-foreground mt-1">
                      {{ result.reason }}
                    </p>
                  </div>
                </div>
              </ScrollArea>
            </TabsContent>

            <TabsContent value="errors" class="flex-1 overflow-auto">
              <div v-if="operationErrors.length === 0" class="text-center py-8">
                <CheckCircle class="h-12 w-12 text-green-500 mx-auto mb-4" />
                <h3 class="text-lg font-medium mb-2">No Errors</h3>
                <p class="text-muted-foreground">All operations completed without errors.</p>
              </div>

              <ScrollArea v-else class="h-full">
                <div class="space-y-2">
                  <Card
                    v-for="error in operationErrors"
                    :key="`${error.moduleId}-${error.targetId}`"
                    class="border-red-200"
                  >
                    <CardContent class="p-4">
                      <div class="flex items-start gap-3">
                        <XCircle class="h-4 w-4 text-red-500 mt-0.5" />
                        <div class="flex-1">
                          <p class="text-sm font-medium">{{ error.error }}</p>
                          <p class="text-xs text-muted-foreground mt-1">
                            Module: {{ error.moduleId }} → Target: {{ error.targetId }}
                          </p>
                        </div>
                        <Badge variant="destructive" class="text-xs">
                          {{ error.code }}
                        </Badge>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </ScrollArea>
            </TabsContent>
          </Tabs>
        </div>
      </div>

      <!-- Footer -->
      <DialogFooter class="flex justify-between">
        <div class="flex gap-2">
          <Button
            v-if="currentStep === 'results'"
            variant="outline"
            size="sm"
            @click="exportResults"
            :disabled="!operationSummary"
          >
            <Download class="h-4 w-4 mr-2" />
            Export Results
          </Button>
        </div>

        <div class="flex gap-2">
          <Button
            v-if="currentStep !== 'selection' && currentStep !== 'execution'"
            variant="outline"
            @click="previousStep"
          >
            Previous
          </Button>
          <Button
            v-if="currentStep !== 'results' && currentStep !== 'execution'"
            @click="nextStep"
            :disabled="!canProceed"
          >
            {{ currentStep === 'preview' ? 'Execute' : 'Next' }}
          </Button>
          <Button
            v-if="currentStep === 'results'"
            @click="close"
          >
            Close
          </Button>
        </div>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
/* Custom progress bar styling */
.progress-bar {
  transition: width 0.3s ease-in-out;
}

/* Smooth transitions for step changes */
.step-content {
  transition: opacity 0.2s ease-in-out;
}

/* Custom scrollbar */
.overflow-auto::-webkit-scrollbar {
  width: 8px;
}

.overflow-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-auto::-webkit-scrollbar-thumb {
  background: hsl(var(--border));
  border-radius: 4px;
}

.overflow-auto::-webkit-scrollbar-thumb:hover {
  background: hsl(var(--muted-foreground));
}

/* Animation for operation status */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.animate-pulse {
  animation: pulse 2s infinite;
}

/* Success/error state colors */
.success-state {
  background-color: hsl(var(--success) / 0.1);
  border-color: hsl(var(--success));
}

.error-state {
  background-color: hsl(var(--destructive) / 0.1);
  border-color: hsl(var(--destructive));
}

.warning-state {
  background-color: hsl(var(--warning) / 0.1);
  border-color: hsl(var(--warning));
}
</style>
