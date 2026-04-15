/**
 * Assignment Store
 *
 * Centralized state management for module assignments,
 * including drag-and-drop operations, conflict resolution, and bulk operations.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
  ModuleAssignment,
  AssignmentTarget,
  AssignmentConflict,
  ConflictResolution,
  BulkAssignmentOperation,
  BulkAssignmentResult,
  DragDropState,
  CreateModuleAssignmentRequest,
  UpdateModuleAssignmentRequest,
  BulkAssignmentRequest
} from '@/types/enhanced-classroom';
import { useApi } from '@/composables/useApi';
import { handleStoreError, StatePersistence, StoreEventBus } from './index';

export const useAssignmentStore = defineStore('assignment', () => {
  // State
  const assignments = ref<ModuleAssignment[]>([]);
  const assignmentTargets = ref<AssignmentTarget[]>([]);
  const conflicts = ref<AssignmentConflict[]>([]);
  const bulkOperations = ref<BulkAssignmentOperation[]>([]);
  const dragDropState = ref<DragDropState>({
    isDragging: false,
    dropZones: [],
    validDropTargets: []
  });
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  // API instance
  const { api } = useApi();
  const eventBus = StoreEventBus.getInstance();

  // Computed properties
  const getAssignmentsByTarget = computed(() => {
    return (targetId: string) => {
      return assignments.value
        .filter(assignment => assignment.targetId === targetId)
        .sort((a, b) => a.order - b.order);
    };
  });

  const getAssignmentsByModule = computed(() => {
    return (moduleId: string) => {
      return assignments.value.filter(assignment => assignment.moduleId === moduleId);
    };
  });

  const conflictsByTarget = computed(() => {
    const grouped: Record<string, AssignmentConflict[]> = {};

    conflicts.value.forEach(conflict => {
      if (!grouped[conflict.targetId]) {
        grouped[conflict.targetId] = [];
      }
      grouped[conflict.targetId].push(conflict);
    });

    return grouped;
  });

  const activeOperations = computed(() => {
    return bulkOperations.value.filter(op =>
      op.status === 'pending' || op.status === 'in_progress'
    );
  });

  const assignmentsByTargetType = computed(() => {
    const grouped: Record<string, ModuleAssignment[]> = {
      course: [],
      track: [],
      level: []
    };

    assignments.value.forEach(assignment => {
      if (grouped[assignment.targetType]) {
        grouped[assignment.targetType].push(assignment);
      }
    });

    return grouped;
  });

  // Actions
  const fetchAssignments = async (): Promise<void> => {
    if (loading.value) return;

    try {
      loading.value = true;
      error.value = null;

      const response = await api.get('/api/assignments');
      assignments.value = response.data || [];

      eventBus.emit('assignments:updated', assignments.value);

    } catch (err) {
      error.value = 'Failed to fetch assignments';
      handleStoreError(err, 'fetchAssignments');
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const fetchAssignmentTargets = async (): Promise<void> => {
    try {
      const response = await api.get('/api/assignment-targets');
      assignmentTargets.value = response.data || [];

    } catch (err) {
      handleStoreError(err, 'fetchAssignmentTargets');
      throw err;
    }
  };

  const createAssignment = async (
    moduleId: string,
    targetId: string,
    targetType: 'course' | 'track' | 'level',
    position?: number
  ): Promise<ModuleAssignment> => {
    try {
      loading.value = true;
      error.value = null;

      // Check for conflicts first
      const detectedConflicts = await detectConflicts(moduleId, targetId);
      if (detectedConflicts.some(c => c.severity === 'error')) {
        throw new Error('Assignment conflicts detected. Please resolve conflicts first.');
      }

      // Determine position if not provided
      if (position === undefined) {
        const targetAssignments = getAssignmentsByTarget.value(targetId);
        position = targetAssignments.length;
      }

      const requestData: CreateModuleAssignmentRequest = {
        moduleId,
        targetType,
        targetId,
        order: position,
        isRequired: true
      };

      const response = await api.post('/api/assignments', requestData);
      const newAssignment = response.data;

      // Update local state
      assignments.value.push(newAssignment);

      // Clear resolved conflicts
      conflicts.value = conflicts.value.filter(
        c => !(c.moduleId === moduleId && c.targetId === targetId)
      );

      // Emit events
      eventBus.emit('assignment:created', newAssignment);

      return newAssignment;

    } catch (err) {
      error.value = 'Failed to create assignment';
      handleStoreError(err, 'createAssignment');
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const updateAssignment = async (
    assignmentId: string,
    updates: UpdateModuleAssignmentRequest
  ): Promise<ModuleAssignment> => {
    try {
      const response = await api.put(`/api/assignments/${assignmentId}`, updates);
      const updatedAssignment = response.data;

      // Update local state
      const index = assignments.value.findIndex(a => a.id === assignmentId);
      if (index !== -1) {
        assignments.value[index] = updatedAssignment;
      }

      eventBus.emit('assignment:updated', updatedAssignment);

      return updatedAssignment;

    } catch (err) {
      handleStoreError(err, 'updateAssignment');
      throw err;
    }
  };

  const deleteAssignment = async (assignmentId: string): Promise<void> => {
    try {
      const assignment = assignments.value.find(a => a.id === assignmentId);

      await api.delete(`/api/assignments/${assignmentId}`);

      // Update local state
      assignments.value = assignments.value.filter(a => a.id !== assignmentId);

      if (assignment) {
        eventBus.emit('assignment:deleted', assignment);
      }

    } catch (err) {
      handleStoreError(err, 'deleteAssignment');
      throw err;
    }
  };

  const reorderAssignments = async (
    targetId: string,
    reorderedAssignments: ModuleAssignment[]
  ): Promise<void> => {
    try {
      const updates = reorderedAssignments.map((assignment, index) => ({
        id: assignment.id,
        order: index
      }));

      await api.post('/api/assignments/reorder', { assignments: updates });

      // Update local state optimistically
      reorderedAssignments.forEach((assignment, index) => {
        const localIndex = assignments.value.findIndex(a => a.id === assignment.id);
        if (localIndex !== -1) {
          assignments.value[localIndex].order = index;
        }
      });

      eventBus.emit('assignments:reordered', { targetId, assignments: reorderedAssignments });

    } catch (err) {
      handleStoreError(err, 'reorderAssignments');
      // Revert optimistic update on error
      await fetchAssignments();
      throw err;
    }
  };

  const bulkAssignModules = async (operation: BulkAssignmentRequest): Promise<BulkAssignmentResult[]> => {
    const operationId = `bulk_${Date.now()}`;

    // Create operation tracking
    const bulkOperation: BulkAssignmentOperation = {
      id: operationId,
      moduleIds: operation.moduleIds,
      targetIds: operation.targetIds,
      targetType: operation.targetType,
      status: 'pending',
      progress: 0,
      startedAt: new Date(),
      results: [],
      errors: []
    };

    bulkOperations.value.push(bulkOperation);

    try {
      bulkOperation.status = 'in_progress';

      const response = await api.post('/api/assignments/bulk', operation);
      const results = response.data.results || [];

      bulkOperation.results = results;
      bulkOperation.status = 'completed';
      bulkOperation.completedAt = new Date();
      bulkOperation.progress = 100;

      // Refresh assignments to get the latest data
      await fetchAssignments();

      eventBus.emit('bulk:assignment:completed', { operation: bulkOperation, results });

      return results;

    } catch (err) {
      bulkOperation.status = 'failed';
      bulkOperation.completedAt = new Date();
      handleStoreError(err, 'bulkAssignModules');
      throw err;
    }
  };

  const cancelBulkOperation = async (operationId: string): Promise<void> => {
    try {
      await api.post(`/api/assignments/bulk/${operationId}/cancel`);

      const operation = bulkOperations.value.find(op => op.id === operationId);
      if (operation) {
        operation.status = 'cancelled';
        operation.completedAt = new Date();
      }

    } catch (err) {
      handleStoreError(err, 'cancelBulkOperation');
      throw err;
    }
  };

  const detectConflicts = async (moduleId: string, targetId: string): Promise<AssignmentConflict[]> => {
    try {
      // Client-side conflict detection
      const clientConflicts: AssignmentConflict[] = [];

      // Check for duplicate assignments
      const duplicateAssignment = assignments.value.find(
        assignment => assignment.moduleId === moduleId && assignment.targetId === targetId
      );

      if (duplicateAssignment) {
        clientConflicts.push({
          id: `duplicate_${moduleId}_${targetId}`,
          type: 'duplicate',
          moduleId,
          targetId,
          conflictingAssignmentId: duplicateAssignment.id,
          description: 'This module is already assigned to this target',
          severity: 'error',
          resolutionOptions: [
            {
              id: 'skip',
              action: 'skip',
              description: 'Skip this assignment',
              consequences: ['Assignment will not be created']
            },
            {
              id: 'replace',
              action: 'replace',
              description: 'Replace existing assignment',
              consequences: ['Existing assignment will be removed', 'New assignment will be created']
            }
          ]
        });
      }

      // Server-side conflict detection
      const response = await api.get(
        `/api/assignments/conflicts?moduleId=${moduleId}&targetId=${targetId}`
      );
      const serverConflicts = response.data || [];

      const allConflicts = [...clientConflicts, ...serverConflicts];

      // Update conflicts state
      const existingConflictIds = conflicts.value.map(c => c.id);
      const newConflicts = allConflicts.filter(c => !existingConflictIds.includes(c.id));
      conflicts.value.push(...newConflicts);

      return allConflicts;

    } catch (err) {
      handleStoreError(err, 'detectConflicts');
      return [];
    }
  };

  const resolveConflict = async (conflictId: string, resolution: ConflictResolution): Promise<void> => {
    try {
      await api.post(`/api/assignments/conflicts/${conflictId}/resolve`, {
        resolutionId: resolution.id,
        action: resolution.action
      });

      // Remove resolved conflict
      conflicts.value = conflicts.value.filter(c => c.id !== conflictId);

      // Refresh assignments if needed
      if (resolution.action === 'replace' || resolution.action === 'modify') {
        await fetchAssignments();
      }

      eventBus.emit('conflict:resolved', { conflictId, resolution });

    } catch (err) {
      handleStoreError(err, 'resolveConflict');
      throw err;
    }
  };

  // Drag and drop operations
  const startDrag = (item: any): void => {
    dragDropState.value = {
      isDragging: true,
      draggedItem: item,
      dropZones: dragDropState.value.dropZones,
      validDropTargets: dragDropState.value.validDropTargets
    };
  };

  const endDrag = (): void => {
    dragDropState.value = {
      isDragging: false,
      draggedItem: undefined,
      dropZones: [],
      validDropTargets: [],
      currentDropTarget: undefined
    };
  };

  const updateDropZones = (zones: any[]): void => {
    dragDropState.value.dropZones = zones;
  };

  const handleDrop = async (targetId: string, position: number): Promise<void> => {
    if (!dragDropState.value.isDragging || !dragDropState.value.draggedItem) {
      return;
    }

    const draggedItem = dragDropState.value.draggedItem;

    try {
      if (draggedItem.type === 'module') {
        // Get target type from assignment targets
        const target = assignmentTargets.value.find(t => t.id === targetId);
        if (target) {
          await createAssignment(draggedItem.id, targetId, target.type, position);
        }
      } else if (draggedItem.type === 'assignment') {
        // Handle assignment reordering or moving
        const assignment = assignments.value.find(a => a.id === draggedItem.id);
        if (assignment) {
          if (assignment.targetId === targetId) {
            // Reorder within same target
            const targetAssignments = getAssignmentsByTarget.value(targetId);
            const reorderedAssignments = [...targetAssignments];

            // Remove from current position
            const currentIndex = reorderedAssignments.findIndex(a => a.id === draggedItem.id);
            if (currentIndex !== -1) {
              const [movedAssignment] = reorderedAssignments.splice(currentIndex, 1);
              // Insert at new position
              reorderedAssignments.splice(position, 0, movedAssignment);

              await reorderAssignments(targetId, reorderedAssignments);
            }
          } else {
            // Move to different target
            const target = assignmentTargets.value.find(t => t.id === targetId);
            if (target) {
              await deleteAssignment(assignment.id);
              await createAssignment(assignment.moduleId, targetId, target.type, position);
            }
          }
        }
      }
    } catch (err) {
      handleStoreError(err, 'handleDrop');
    } finally {
      endDrag();
    }
  };

  const initialize = async (): Promise<void> => {
    try {
      await Promise.all([
        fetchAssignments(),
        fetchAssignmentTargets()
      ]);
    } catch (err) {
      handleStoreError(err, 'initialize');
    }
  };

  return {
    // State
    assignments,
    assignmentTargets,
    conflicts,
    bulkOperations,
    dragDropState,
    loading,
    error,

    // Computed
    getAssignmentsByTarget,
    getAssignmentsByModule,
    conflictsByTarget,
    activeOperations,
    assignmentsByTargetType,

    // Actions
    fetchAssignments,
    fetchAssignmentTargets,
    createAssignment,
    updateAssignment,
    deleteAssignment,
    reorderAssignments,
    bulkAssignModules,
    cancelBulkOperation,
    detectConflicts,
    resolveConflict,
    startDrag,
    endDrag,
    updateDropZones,
    handleDrop,
    initialize
  };
});
