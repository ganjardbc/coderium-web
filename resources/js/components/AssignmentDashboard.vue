<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsItem } from '@/components/ui/tabs';
import {
  Search,
  Filter,
  Grid3X3,
  List,
  Loader2,
  AlertCircle,
  Target,
  Zap,
  BarChart3,
  Undo2,
  Redo2,
  CheckCircle,
  XCircle,
  Clock,
  Users,
  TrendingUp,
  AlertTriangle
} from 'lucide-vue-next';
import { useAssignmentWorkflow } from '@/composables/useAssignmentWorkflow';
import { useModuleLibrary } from '@/composables/useModuleLibrary';
import { useNotifications } from '@/composables/useNotifications';
import {
  AssignmentTarget,
  ModuleAssignment,
  AssignmentConflict,
  BulkAssignmentOperation,
  StandaloneModule,
  DragDropState
} from '@/types/enhanced-classroom';
import ModuleCard from './ModuleCard.vue';
import DragDropAssignment from './DragDropAssignment.vue';
import BulkAssignmentManager from './BulkAssignmentManager.vue';

interface Props {
  viewMode?: 'visual' | 'list' | 'grid';
  showConflicts?: boolean;
  enableBulkOperations?: boolean;
  showAnalytics?: boolean;
  enableMobileOptimizations?: boolean;
}

interface Emits {
  (e: 'assignmentCreated', assignment: ModuleAssignment): void;
  (e: 'assignmentUpdated', assignment: ModuleAssignment): void;
  (e: 'assignmentDeleted', assignmentId: string): void;
  (e: 'bulkAssignmentRequested', moduleIds: string[], targetIds: string[]): void;
  (e: 'conflictResolved', conflictId: string): void;
}

const props = withDefaults(defineProps<Props>(), {
  viewMode: 'visual',
  showConflicts: true,
  enableBulkOperations: true,
  showAnalytics: true,
  enableMobileOptimizations: true
});

const emit = defineEmits<Emits>();

// Composables
const {
  assignments,
  assignmentTargets,
  conflicts,
  dragDropState,
  loading,
  error,
  createAssignment,
  updateAssignment,
  removeAssignment,
  detectConflicts,
  resolveConflict,
  getAssignmentsByTarget,
  conflictsByTarget,
  activeOperations
} = useAssignmentWorkflow();

const {
  filteredModules,
  searchQuery,
  loading: modulesLoading
} = useModuleLibrary();

const { showNotification } = useNotifications();

// Local reactive state
const currentViewMode = ref<'visual' | 'list' | 'grid'>(props.viewMode);
const selectedModules = ref<string[]>([]);
const selectedTargets = ref<string[]>([]);
const showBulkManager = ref(false);
const showAnalytics = ref(false);
const searchInput = ref('');
const activeTab = ref<'assignments' | 'conflicts' | 'analytics'>('assignments');

// Undo/Redo functionality
const undoStack = ref<any[]>([]);
const redoStack = ref<any[]>([]);
const maxHistorySize = 50;

// Mobile touch state
const touchState = ref({
  isTouch: false,
  lastTap: 0,
  tapCount: 0
});

// Analytics data
const analyticsData = computed(() => {
  const totalAssignments = assignments.value.length;
  const activeAssignments = assignments.value.filter(a => a.isActive).length;
  const conflictCount = conflicts.value.length;
  const criticalConflicts = conflicts.value.filter(c => c.severity === 'error').length;

  const targetStats = assignmentTargets.value.map(target => {
    const targetAssignments = getAssignmentsByTarget(target.id).value;
    const completionRate = targetAssignments.length > 0
      ? targetAssignments.reduce((sum, a) => sum + a.completionRate, 0) / targetAssignments.length
      : 0;

    return {
      target,
      assignmentCount: targetAssignments.length,
      completionRate,
      averageScore: targetAssignments.length > 0
        ? targetAssignments.reduce((sum, a) => sum + a.averageScore, 0) / targetAssignments.length
        : 0
    };
  });

  return {
    totalAssignments,
    activeAssignments,
    conflictCount,
    criticalConflicts,
    targetStats,
    bulkOperationsActive: activeOperations.value.length
  };
});

// Computed properties
const filteredTargets = computed(() => {
  if (!searchInput.value) return assignmentTargets.value;

  const query = searchInput.value.toLowerCase();
  return assignmentTargets.value.filter(target =>
    target.title.toLowerCase().includes(query) ||
    target.description?.toLowerCase().includes(query)
  );
});

const criticalConflicts = computed(() =>
  conflicts.value.filter(c => c.severity === 'error')
);

const warningConflicts = computed(() =>
  conflicts.value.filter(c => c.severity === 'warning')
);

const canUndo = computed(() => undoStack.value.length > 0);
const canRedo = computed(() => redoStack.value.length > 0);

const isMobile = computed(() => {
  if (typeof window === 'undefined') return false;
  return window.innerWidth < 768;
});

// Methods
const handleModuleSelect = (moduleId: string) => {
  const index = selectedModules.value.indexOf(moduleId);
  if (index > -1) {
    selectedModules.value.splice(index, 1);
  } else {
    selectedModules.value.push(moduleId);
  }
};

const handleTargetSelect = (targetId: string) => {
  const index = selectedTargets.value.indexOf(targetId);
  if (index > -1) {
    selectedTargets.value.splice(index, 1);
  } else {
    selectedTargets.value.push(targetId);
  }
};

const clearSelections = () => {
  selectedModules.value = [];
  selectedTargets.value = [];
};

const openBulkManager = () => {
  if (selectedModules.value.length === 0) {
    showNotification({
      type: 'warning',
      title: 'No Modules Selected',
      message: 'Please select at least one module for bulk assignment.'
    });
    return;
  }

  showBulkManager.value = true;
};

const handleBulkAssignmentComplete = (results: any[]) => {
  showBulkManager.value = false;
  clearSelections();

  const successCount = results.filter(r => r.status === 'success').length;
  const failureCount = results.filter(r => r.status === 'failed').length;

  showNotification({
    type: successCount > 0 ? 'success' : 'error',
    title: 'Bulk Assignment Complete',
    message: `${successCount} successful, ${failureCount} failed assignments.`
  });

  emit('bulkAssignmentRequested', selectedModules.value, selectedTargets.value);
};

const handleAssignmentCreated = async (moduleId: string, targetId: string, position?: number) => {
  try {
    // Save state for undo
    saveStateForUndo();

    const assignment = await createAssignment(moduleId, targetId, position);

    // Provide haptic feedback on mobile
    if (props.enableMobileOptimizations && 'vibrate' in navigator) {
      navigator.vibrate(50);
    }

    emit('assignmentCreated', assignment);

    showNotification({
      type: 'success',
      title: 'Assignment Created',
      message: `Module assigned to ${assignmentTargets.value.find(t => t.id === targetId)?.title || 'target'}`
    });

  } catch (error) {
    showNotification({
      type: 'error',
      title: 'Assignment Failed',
      message: error instanceof Error ? error.message : 'Failed to create assignment'
    });
  }
};

const handleAssignmentRemoved = async (assignmentId: string) => {
  try {
    // Save state for undo
    saveStateForUndo();

    await removeAssignment(assignmentId);

    // Provide haptic feedback on mobile
    if (props.enableMobileOptimizations && 'vibrate' in navigator) {
      navigator.vibrate([50, 50, 50]);
    }

    emit('assignmentDeleted', assignmentId);

    showNotification({
      type: 'success',
      title: 'Assignment Removed',
      message: 'Assignment has been successfully removed'
    });

  } catch (error) {
    showNotification({
      type: 'error',
      title: 'Removal Failed',
      message: error instanceof Error ? error.message : 'Failed to remove assignment'
    });
  }
};

const handleConflictResolve = async (conflictId: string, resolution: any) => {
  try {
    await resolveConflict(conflictId, resolution);
    emit('conflictResolved', conflictId);

    showNotification({
      type: 'success',
      title: 'Conflict Resolved',
      message: 'Assignment conflict has been resolved successfully'
    });

  } catch (error) {
    showNotification({
      type: 'error',
      title: 'Resolution Failed',
      message: error instanceof Error ? error.message : 'Failed to resolve conflict'
    });
  }
};

// Undo/Redo functionality
const saveStateForUndo = () => {
  const state = {
    assignments: [...assignments.value],
    timestamp: Date.now()
  };

  undoStack.value.push(state);

  // Limit stack size
  if (undoStack.value.length > maxHistorySize) {
    undoStack.value.shift();
  }

  // Clear redo stack when new action is performed
  redoStack.value = [];
};

const undo = () => {
  if (!canUndo.value) return;

  const currentState = {
    assignments: [...assignments.value],
    timestamp: Date.now()
  };

  redoStack.value.push(currentState);

  const previousState = undoStack.value.pop();
  if (previousState) {
    assignments.value = previousState.assignments;

    showNotification({
      type: 'info',
      title: 'Action Undone',
      message: 'Previous action has been undone'
    });
  }
};

const redo = () => {
  if (!canRedo.value) return;

  const currentState = {
    assignments: [...assignments.value],
    timestamp: Date.now()
  };

  undoStack.value.push(currentState);

  const nextState = redoStack.value.pop();
  if (nextState) {
    assignments.value = nextState.assignments;

    showNotification({
      type: 'info',
      title: 'Action Redone',
      message: 'Action has been redone'
    });
  }
};

// Touch interaction handlers
const handleTouchStart = (event: TouchEvent) => {
  if (!props.enableMobileOptimizations) return;

  touchState.value.isTouch = true;

  // Handle double-tap for quick actions
  const now = Date.now();
  const timeDiff = now - touchState.value.lastTap;

  if (timeDiff < 300 && timeDiff > 0) {
    touchState.value.tapCount++;
    if (touchState.value.tapCount === 2) {
      // Double-tap detected - could trigger quick assignment
      event.preventDefault();
      handleDoubleTap(event);
    }
  } else {
    touchState.value.tapCount = 1;
  }

  touchState.value.lastTap = now;
};

const handleDoubleTap = (event: TouchEvent) => {
  // Provide haptic feedback
  if ('vibrate' in navigator) {
    navigator.vibrate(100);
  }

  // Could implement quick assignment logic here
  showNotification({
    type: 'info',
    title: 'Quick Action',
    message: 'Double-tap detected - quick actions available'
  });
};

// Keyboard shortcuts
const handleKeydown = (event: KeyboardEvent) => {
  if (event.ctrlKey || event.metaKey) {
    switch (event.key) {
      case 'z':
        event.preventDefault();
        if (event.shiftKey) {
          redo();
        } else {
          undo();
        }
        break;
      case 'a':
        event.preventDefault();
        // Select all modules
        selectedModules.value = filteredModules.value.map(m => m.id);
        break;
      case 'Escape':
        clearSelections();
        break;
    }
  }
};

// Lifecycle
onMounted(() => {
  document.addEventListener('keydown', handleKeydown);

  if (props.enableMobileOptimizations) {
    document.addEventListener('touchstart', handleTouchStart, { passive: false });
  }
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown);

  if (props.enableMobileOptimizations) {
    document.removeEventListener('touchstart', handleTouchStart);
  }
});
</script>

<template>
  <div class="assignment-dashboard flex flex-col h-full bg-background">
    <!-- Header -->
    <div class="flex-shrink-0 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="container mx-auto px-4 py-4">
        <!-- Title and Actions -->
        <div class="flex items-center justify-between mb-4">
          <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
              <Target class="h-6 w-6" />
              Assignment Dashboard
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
              Manage module assignments with drag-and-drop interface
            </p>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-2">
            <!-- Undo/Redo -->
            <div class="flex border rounded-md">
              <Button
                variant="ghost"
                size="sm"
                @click="undo"
                :disabled="!canUndo"
                class="rounded-r-none"
                title="Undo (Ctrl+Z)"
              >
                <Undo2 class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                @click="redo"
                :disabled="!canRedo"
                class="rounded-l-none border-l"
                title="Redo (Ctrl+Shift+Z)"
              >
                <Redo2 class="h-4 w-4" />
              </Button>
            </div>

            <!-- Bulk Operations -->
            <Button
              v-if="enableBulkOperations"
              variant="outline"
              size="sm"
              @click="openBulkManager"
              :disabled="selectedModules.length === 0"
            >
              <Zap class="h-4 w-4 mr-2" />
              Bulk Assign
              <Badge v-if="selectedModules.length > 0" variant="secondary" class="ml-2">
                {{ selectedModules.length }}
              </Badge>
            </Button>

            <!-- View Mode Toggle -->
            <div class="flex border rounded-md">
              <Button
                variant="ghost"
                size="sm"
                @click="currentViewMode = 'visual'"
                :class="{ 'bg-muted': currentViewMode === 'visual' }"
                class="rounded-r-none"
                title="Visual View"
              >
                <Target class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                @click="currentViewMode = 'grid'"
                :class="{ 'bg-muted': currentViewMode === 'grid' }"
                class="rounded-none border-x"
                title="Grid View"
              >
                <Grid3X3 class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                @click="currentViewMode = 'list'"
                :class="{ 'bg-muted': currentViewMode === 'list' }"
                class="rounded-l-none"
                title="List View"
              >
                <List class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        <!-- Search and Stats -->
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
          <!-- Search -->
          <div class="relative flex-1">
            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              v-model="searchInput"
              placeholder="Search assignment targets..."
              class="pl-10"
            />
          </div>

          <!-- Quick Stats -->
          <div class="flex gap-4 text-sm">
            <div class="flex items-center gap-1">
              <CheckCircle class="h-4 w-4 text-green-500" />
              <span>{{ analyticsData.activeAssignments }} active</span>
            </div>
            <div v-if="analyticsData.conflictCount > 0" class="flex items-center gap-1">
              <AlertTriangle class="h-4 w-4 text-yellow-500" />
              <span>{{ analyticsData.conflictCount }} conflicts</span>
            </div>
            <div v-if="analyticsData.bulkOperationsActive > 0" class="flex items-center gap-1">
              <Clock class="h-4 w-4 text-blue-500" />
              <span>{{ analyticsData.bulkOperationsActive }} operations</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Content Tabs -->
    <div class="flex-1 overflow-hidden">
      <Tabs v-model="activeTab" class="h-full flex flex-col">
        <TabsList class="grid w-full grid-cols-3 mx-4 mt-4">
          <TabsItem value="assignments">
            Assignments
            <Badge v-if="analyticsData.totalAssignments > 0" variant="secondary" class="ml-2">
              {{ analyticsData.totalAssignments }}
            </Badge>
          </TabsItem>
          <TabsItem value="conflicts" v-if="showConflicts">
            Conflicts
            <Badge
              v-if="analyticsData.conflictCount > 0"
              :variant="analyticsData.criticalConflicts > 0 ? 'destructive' : 'secondary'"
              class="ml-2"
            >
              {{ analyticsData.conflictCount }}
            </Badge>
          </TabsItem>
          <TabsItem value="analytics" v-if="showAnalytics">
            Analytics
            <TrendingUp class="h-4 w-4 ml-2" />
          </TabsItem>
        </TabsList>

        <!-- Assignments Tab -->
        <TabsContent value="assignments" class="flex-1 overflow-hidden mt-4">
          <!-- Loading State -->
          <div v-if="loading || modulesLoading" class="flex items-center justify-center h-full">
            <div class="text-center">
              <Loader2 class="h-8 w-8 animate-spin mx-auto mb-4" />
              <p class="text-muted-foreground">Loading assignment data...</p>
            </div>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="flex items-center justify-center h-full">
            <div class="text-center">
              <AlertCircle class="h-8 w-8 text-destructive mx-auto mb-4" />
              <p class="text-destructive mb-2">{{ error }}</p>
              <Button variant="outline" @click="$emit('refresh')">
                Try Again
              </Button>
            </div>
          </div>

          <!-- Assignment Interface -->
          <div v-else class="h-full">
            <DragDropAssignment
              :available-modules="filteredModules"
              :assignment-targets="filteredTargets"
              :existing-assignments="assignments"
              :view-mode="currentViewMode"
              :selected-modules="selectedModules"
              :selected-targets="selectedTargets"
              :enable-mobile-optimizations="enableMobileOptimizations"
              @module-assigned="handleAssignmentCreated"
              @assignment-removed="handleAssignmentRemoved"
              @module-selected="handleModuleSelect"
              @target-selected="handleTargetSelect"
            />
          </div>
        </TabsContent>

        <!-- Conflicts Tab -->
        <TabsContent value="conflicts" v-if="showConflicts" class="flex-1 overflow-auto p-4">
          <div v-if="conflicts.length === 0" class="flex items-center justify-center h-full">
            <div class="text-center">
              <CheckCircle class="h-12 w-12 text-green-500 mx-auto mb-4" />
              <h3 class="text-lg font-medium mb-2">No Conflicts</h3>
              <p class="text-muted-foreground">All assignments are conflict-free</p>
            </div>
          </div>

          <div v-else class="space-y-4">
            <!-- Critical Conflicts -->
            <div v-if="criticalConflicts.length > 0">
              <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                <XCircle class="h-5 w-5 text-red-500" />
                Critical Conflicts ({{ criticalConflicts.length }})
              </h3>
              <div class="space-y-2">
                <Card v-for="conflict in criticalConflicts" :key="conflict.id" class="border-red-200">
                  <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-red-700">{{ conflict.description }}</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="flex justify-between items-center">
                      <div class="text-xs text-muted-foreground">
                        Module: {{ conflict.moduleId }} → Target: {{ conflict.targetId }}
                      </div>
                      <div class="flex gap-2">
                        <Button
                          v-for="resolution in conflict.resolutionOptions"
                          :key="resolution.id"
                          variant="outline"
                          size="sm"
                          @click="handleConflictResolve(conflict.id, resolution)"
                        >
                          {{ resolution.description }}
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            <!-- Warning Conflicts -->
            <div v-if="warningConflicts.length > 0">
              <h3 class="text-lg font-medium mb-3 flex items-center gap-2">
                <AlertTriangle class="h-5 w-5 text-yellow-500" />
                Warnings ({{ warningConflicts.length }})
              </h3>
              <div class="space-y-2">
                <Card v-for="conflict in warningConflicts" :key="conflict.id" class="border-yellow-200">
                  <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-yellow-700">{{ conflict.description }}</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="flex justify-between items-center">
                      <div class="text-xs text-muted-foreground">
                        Module: {{ conflict.moduleId }} → Target: {{ conflict.targetId }}
                      </div>
                      <div class="flex gap-2">
                        <Button
                          v-for="resolution in conflict.resolutionOptions"
                          :key="resolution.id"
                          variant="outline"
                          size="sm"
                          @click="handleConflictResolve(conflict.id, resolution)"
                        >
                          {{ resolution.description }}
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>
          </div>
        </TabsContent>

        <!-- Analytics Tab -->
        <TabsContent value="analytics" v-if="showAnalytics" class="flex-1 overflow-auto p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Overview Stats -->
            <Card>
              <CardHeader>
                <CardTitle class="text-sm">Assignment Overview</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-sm text-muted-foreground">Total Assignments</span>
                    <span class="font-medium">{{ analyticsData.totalAssignments }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-sm text-muted-foreground">Active Assignments</span>
                    <span class="font-medium">{{ analyticsData.activeAssignments }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-sm text-muted-foreground">Conflicts</span>
                    <span class="font-medium text-red-600">{{ analyticsData.conflictCount }}</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Target Performance -->
            <Card v-for="stat in analyticsData.targetStats.slice(0, 6)" :key="stat.target.id">
              <CardHeader>
                <CardTitle class="text-sm">{{ stat.target.title }}</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-sm text-muted-foreground">Modules</span>
                    <span class="font-medium">{{ stat.assignmentCount }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-sm text-muted-foreground">Completion</span>
                    <span class="font-medium">{{ Math.round(stat.completionRate) }}%</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-sm text-muted-foreground">Avg Score</span>
                    <span class="font-medium">{{ Math.round(stat.averageScore) }}%</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>

    <!-- Bulk Assignment Manager Modal -->
    <BulkAssignmentManager
      v-if="showBulkManager"
      :selected-modules="selectedModules"
      :available-targets="assignmentTargets"
      :show-progress-indicator="true"
      @bulk-assignment-completed="handleBulkAssignmentComplete"
      @bulk-assignment-cancelled="showBulkManager = false"
      @close="showBulkManager = false"
    />
  </div>
</template>

<style scoped>
.assignment-dashboard {
  height: 100vh;
}

/* Touch-friendly interactions */
@media (max-width: 768px) {
  .assignment-dashboard {
    touch-action: manipulation;
  }

  /* Larger touch targets */
  button {
    min-height: 44px;
    min-width: 44px;
  }

  /* Improved spacing for mobile */
  .container {
    padding-left: 1rem;
    padding-right: 1rem;
  }
}

/* Drag and drop visual feedback */
.drag-over {
  background-color: hsl(var(--primary) / 0.1);
  border: 2px dashed hsl(var(--primary));
}

.drag-active {
  transform: scale(1.02);
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Smooth transitions */
.assignment-dashboard * {
  transition: all 0.2s ease-in-out;
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

/* Haptic feedback animation */
@keyframes haptic-pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

.haptic-feedback {
  animation: haptic-pulse 0.2s ease-in-out;
}
</style>
