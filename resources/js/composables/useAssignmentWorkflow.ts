/**
 * Assignment Workflow Composable
 *
 * Provides comprehensive assignment management with drag-and-drop support,
 * conflict detection, bulk operations, and real-time validation.
 */

import { ref, computed, type Ref, type ComputedRef } from 'vue';
import {
  ModuleAssignment,
  AssignmentTarget,
  AssignmentConflict,
  ConflictResolution,
  BulkAssignmentOperation,
  BulkAssignmentResult,
  DragDropState,
  DropZone,
  StandaloneModule,
  CreateModuleAssignmentRequest,
  UpdateModuleAssignmentRequest,
  BulkAssignmentRequest,
  ValidationResult
} from '@/types/enhanced-classroom';
import { useApi } from './useApi';
import { globalLoading } from './useLoading';
import { globalErrorHandler } from './useErrorHandler';

export interface AssignmentWorkflowComposable {
  // Reactive Data
  assignments: Ref<ModuleAssignment[]>;
  availableModules: Ref<StandaloneModule[]>;
  assignmentTargets: Ref<AssignmentTarget[]>;
  conflicts: Ref<AssignmentConflict[]>;
  dragDropState: Ref<DragDropState>;
  loading: Ref<boolean>;
  error: Ref<string | null>;

  // Assignment CRUD Methods
  createAssignment: (moduleId: string, targetId: string, position?: number) => Promise<ModuleAssignment>;
  updateAssignment: (assignmentId: string, updates: UpdateModuleAssignmentRequest) => Promise<ModuleAssignment>;
  updateAssignmentOrder: (assignments: ModuleAssignment[]) => Promise<void>;
  removeAssignment: (assignmentId: string) => Promise<void>;

  // Bulk Operations
  bulkAssignModules: (operation: BulkAssignmentRequest) => Promise<BulkAssignmentResult[]>;
  cancelBulkOperation: (operationId: string) => Promise<void>;

  // Conflict Management
  detectConflicts: (moduleId: string, targetId: string) => Promise<AssignmentConflict[]>;
  resolveConflict: (conflictId: string, resolution: ConflictResolution) => Promise<void>;

  // Drag and Drop
  startDrag: (item: any, event: MouseEvent | TouchEvent) => void;
  endDrag: () => void;
  handleDrop: (targetId: string, position: number) => Promise<void>;
  updateDropZones: (zones: DropZone[]) => void;

  // Validation
  validateAssignment: (assignment: Partial<ModuleAssignment>) => ValidationResult;

  // Computed Properties
  getAssignmentsByTarget: (targetId: string) => ComputedRef<ModuleAssignment[]>;
  conflictsByTarget: ComputedRef<Record<string, AssignmentConflict[]>>;
  activeOperations: ComputedRef<BulkAssignmentOperation[]>;
}

// Drag and drop utilities
class DragDropManager {
  private dragStartPosition = { x: 0, y: 0 };
  private ghostElement: HTMLElement | null = null;

  startDrag(item: any, event: MouseEvent | TouchEvent): DragDropState {
    const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX;
    const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY;

    this.dragStartPosition = { x: clientX, y: clientY };

    return {
      isDragging: true,
      draggedItem: item,
      dropZones: [],
      validDropTargets: [],
      dragOffset: { x: 0, y: 0 }
    };
  }

  updateDrag(event: MouseEvent | TouchEvent, state: DragDropState): DragDropState {
    if (!state.isDragging) return state;

    const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX;
    const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY;

    const dragOffset = {
      x: clientX - this.dragStartPosition.x,
      y: clientY - this.dragStartPosition.y
    };

    // Find current drop target
    const currentDropTarget = this.findDropTarget(clientX, clientY, state.dropZones);

    return {
      ...state,
      dragOffset,
      currentDropTarget: currentDropTarget?.id
    };
  }

  endDrag(): DragDropState {
    this.cleanup();

    return {
      isDragging: false,
      draggedItem: undefined,
      dropZones: [],
      validDropTargets: [],
      currentDropTarget: undefined,
      dragOffset: undefined
    };
  }

  private findDropTarget(x: number, y: number, dropZones: DropZone[]): DropZone | null {
    return dropZones.find(zone =>
      zone.isActive &&
      x >= zone.position.x &&
      x <= zone.position.x + zone.position.width &&
      y >= zone.position.y &&
      y <= zone.position.y + zone.position.height
    ) || null;
  }

  private cleanup(): void {
    if (this.ghostElement) {
      this.ghostElement.remove();
      this.ghostElement = null;
    }
  }
}

// Conflict detection engine
class ConflictDetector {
  detectAssignmentConflicts(
    moduleId: string,
    targetId: string,
    existingAssignments: ModuleAssignment[]
  ): AssignmentConflict[] {
    const conflicts: AssignmentConflict[] = [];

    // Check for duplicate assignments
    const duplicateAssignment = existingAssignments.find(
      assignment => assignment.moduleId === moduleId && assignment.targetId === targetId
    );

    if (duplicateAssignment) {
      conflicts.push({
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

    // Check capacity constraints
    // This would need target info to check maxModules - simplified for now

    return conflicts;
  }

  detectCircularDependencies(
    _moduleId: string,
    _targetId: string,
    _assignments: ModuleAssignment[]
  ): AssignmentConflict[] {
    // Simplified circular dependency detection
    // In a real implementation, this would traverse the dependency graph
    return [];
  }
}

export function useAssignmentWorkflow(): AssignmentWorkflowComposable {
  // Reactive state
  const assignments = ref<ModuleAssignment[]>([]);
  const availableModules = ref<StandaloneModule[]>([]);
  const assignmentTargets = ref<AssignmentTarget[]>([]);
  const conflicts = ref<AssignmentConflict[]>([]);
  const dragDropState = ref<DragDropState>({
    isDragging: false,
    dropZones: [],
    validDropTargets: []
  });
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  // Active bulk operations
  const bulkOperations = ref<BulkAssignmentOperation[]>([]);

  // Utilities
  const { api } = useApi();
  const { setLoading } = globalLoading;
  const { handleError, handleSuccess } = globalErrorHandler;
  const dragDropManager = new DragDropManager();
  const conflictDetector = new ConflictDetector();

  // Computed properties
  const getAssignmentsByTarget = (targetId: string): ComputedRef<ModuleAssignment[]> => {
    return computed(() =>
      assignments.value.filter(assignment => assignment.targetId === targetId)
        .sort((a, b) => a.order - b.order)
    );
  };

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

  const activeOperations = computed(() =>
    bulkOperations.value.filter(op =>
      op.status === 'pending' || op.status === 'in_progress'
    )
  );

  // Assignment CRUD operations
  const createAssignment = async (
    moduleId: string,
    targetId: string,
    position?: number
  ): Promise<ModuleAssignment> => {
    const loadingKey = `createAssignment_${moduleId}_${targetId}`;

    try {
      setLoading(loadingKey, true);
      error.value = null;

      // Detect conflicts first
      const detectedConflicts = await detectConflicts(moduleId, targetId);
      if (detectedConflicts.some(c => c.severity === 'error')) {
        throw new Error('Assignment conflicts detected. Please resolve conflicts first.');
      }

      // Determine position if not provided
      if (position === undefined) {
        const targetAssignments = getAssignmentsByTarget(targetId).value;
        position = targetAssignments.length;
      }

      const requestData: CreateModuleAssignmentRequest = {
        moduleId,
        targetType: 'course', // This should be determined based on target
        targetId,
        order: position,
        isRequired: true
      };

      const response = await api.post('/api/assignments', requestData);
      const newAssignment = response.data;

      // Update local state
      assignments.value.push(newAssignment);

      // Clear any resolved conflicts
      conflicts.value = conflicts.value.filter(
        c => !(c.moduleId === moduleId && c.targetId === targetId)
      );

      handleSuccess('Assignment created successfully');
      return newAssignment;

    } catch (err) {
      handleError(err, 'Create Assignment');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const updateAssignment = async (
    assignmentId: string,
    updates: UpdateModuleAssignmentRequest
  ): Promise<ModuleAssignment> => {
    const loadingKey = `updateAssignment_${assignmentId}`;

    try {
      setLoading(loadingKey, true);

      const response = await api.put(`/api/assignments/${assignmentId}`, updates);
      const updatedAssignment = response.data;

      // Update local state
      const index = assignments.value.findIndex(a => a.id === assignmentId);
      if (index !== -1) {
        assignments.value[index] = updatedAssignment;
      }

      handleSuccess('Assignment updated successfully');
      return updatedAssignment;

    } catch (err) {
      handleError(err, 'Update Assignment');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const updateAssignmentOrder = async (reorderedAssignments: ModuleAssignment[]): Promise<void> => {
    const loadingKey = 'updateAssignmentOrder';

    try {
      setLoading(loadingKey, true);

      // Prepare batch update data
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

      handleSuccess('Assignment order updated successfully');

    } catch (err) {
      handleError(err, 'Update Assignment Order');
      // Revert optimistic update on error
      await fetchAssignments();
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const removeAssignment = async (assignmentId: string): Promise<void> => {
    const loadingKey = `removeAssignment_${assignmentId}`;

    try {
      setLoading(loadingKey, true);

      await api.delete(`/api/assignments/${assignmentId}`);

      // Update local state
      assignments.value = assignments.value.filter(a => a.id !== assignmentId);

      handleSuccess('Assignment removed successfully');

    } catch (err) {
      handleError(err, 'Remove Assignment');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  // Bulk operations
  const bulkAssignModules = async (operation: BulkAssignmentRequest): Promise<BulkAssignmentResult[]> => {
    const operationId = `bulk_${Date.now()}`;
    const loadingKey = `bulkAssign_${operationId}`;

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
      setLoading(loadingKey, true);
      bulkOperation.status = 'in_progress';

      const response = await api.post('/api/assignments/bulk', operation);
      const results = response.data.results || [];

      bulkOperation.results = results;
      bulkOperation.status = 'completed';
      bulkOperation.completedAt = new Date();
      bulkOperation.progress = 100;

      // Update local assignments with successful results
      const successfulAssignments = results
        .filter((result: BulkAssignmentResult) => result.status === 'success')
        .map((result: BulkAssignmentResult) => result.assignmentId)
        .filter(Boolean);

      if (successfulAssignments.length > 0) {
        // Fetch updated assignments
        await fetchAssignments();
      }

      const successCount = results.filter((r: BulkAssignmentResult) => r.status === 'success').length;
      const failureCount = results.filter((r: BulkAssignmentResult) => r.status === 'failed').length;

      handleSuccess(`Bulk assignment completed: ${successCount} successful, ${failureCount} failed`);

      return results;

    } catch (err) {
      bulkOperation.status = 'failed';
      bulkOperation.completedAt = new Date();
      handleError(err, 'Bulk Assignment');
      throw err;
    } finally {
      setLoading(loadingKey, false);
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

      handleSuccess('Bulk operation cancelled');
    } catch (err) {
      handleError(err, 'Cancel Bulk Operation');
      throw err;
    }
  };

  // Conflict management
  const detectConflicts = async (moduleId: string, targetId: string): Promise<AssignmentConflict[]> => {
    try {
      // Client-side conflict detection
      const clientConflicts = conflictDetector.detectAssignmentConflicts(
        moduleId,
        targetId,
        assignments.value
      );

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
      handleError(err, 'Conflict Detection');
      return [];
    }
  };

  const resolveConflict = async (conflictId: string, resolution: ConflictResolution): Promise<void> => {
    const loadingKey = `resolveConflict_${conflictId}`;

    try {
      setLoading(loadingKey, true);

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

      handleSuccess('Conflict resolved successfully');

    } catch (err) {
      handleError(err, 'Resolve Conflict');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  // Drag and drop operations
  const startDrag = (item: any, event: MouseEvent | TouchEvent): void => {
    dragDropState.value = dragDropManager.startDrag(item, event);

    // Add event listeners for drag tracking
    const handleMouseMove = (e: MouseEvent) => {
      dragDropState.value = dragDropManager.updateDrag(e, dragDropState.value);
    };

    const handleTouchMove = (e: TouchEvent) => {
      e.preventDefault();
      dragDropState.value = dragDropManager.updateDrag(e, dragDropState.value);
    };

    const cleanup = () => {
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('touchmove', handleTouchMove);
      document.removeEventListener('mouseup', cleanup);
      document.removeEventListener('touchend', cleanup);
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('touchmove', handleTouchMove, { passive: false });
    document.addEventListener('mouseup', cleanup);
    document.addEventListener('touchend', cleanup);
  };

  const endDrag = (): void => {
    dragDropState.value = dragDropManager.endDrag();
  };

  const handleDrop = async (targetId: string, position: number): Promise<void> => {
    if (!dragDropState.value.isDragging || !dragDropState.value.draggedItem) {
      return;
    }

    const draggedItem = dragDropState.value.draggedItem;

    try {
      if (draggedItem.type === 'module') {
        await createAssignment(draggedItem.id, targetId, position);
      } else if (draggedItem.type === 'assignment') {
        // Handle assignment reordering
        const assignment = assignments.value.find(a => a.id === draggedItem.id);
        if (assignment && assignment.targetId === targetId) {
          // Reorder within same target
          const targetAssignments = getAssignmentsByTarget(targetId).value;
          const reorderedAssignments = [...targetAssignments];

          // Remove from current position
          const currentIndex = reorderedAssignments.findIndex(a => a.id === draggedItem.id);
          if (currentIndex !== -1) {
            const [movedAssignment] = reorderedAssignments.splice(currentIndex, 1);
            // Insert at new position
            reorderedAssignments.splice(position, 0, movedAssignment);

            await updateAssignmentOrder(reorderedAssignments);
          }
        } else if (assignment) {
          // Move to different target
          await removeAssignment(assignment.id);
          await createAssignment(assignment.moduleId, targetId, position);
        }
      }
    } catch (err) {
      handleError(err, 'Drop Operation');
    } finally {
      endDrag();
    }
  };

  const updateDropZones = (zones: DropZone[]): void => {
    dragDropState.value.dropZones = zones;
  };

  // Validation
  const validateAssignment = (assignment: Partial<ModuleAssignment>): ValidationResult => {
    const errors: any[] = [];
    const warnings: any[] = [];

    if (!assignment.moduleId) {
      errors.push({
        field: 'moduleId',
        message: 'Module ID is required',
        code: 'REQUIRED_FIELD'
      });
    }

    if (!assignment.targetId) {
      errors.push({
        field: 'targetId',
        message: 'Target ID is required',
        code: 'REQUIRED_FIELD'
      });
    }

    if (assignment.order !== undefined && assignment.order < 0) {
      errors.push({
        field: 'order',
        message: 'Order must be non-negative',
        code: 'INVALID_VALUE'
      });
    }

    return {
      isValid: errors.length === 0,
      errors,
      warnings
    };
  };

  // Helper methods
  const fetchAssignments = async (): Promise<void> => {
    try {
      const response = await api.get('/api/assignments');
      assignments.value = response.data || [];
    } catch (err) {
      handleError(err, 'Fetch Assignments');
    }
  };

  const fetchAssignmentTargets = async (): Promise<void> => {
    try {
      const response = await api.get('/api/assignment-targets');
      assignmentTargets.value = response.data || [];
    } catch (err) {
      handleError(err, 'Fetch Assignment Targets');
    }
  };

  // Initialize data on composable creation
  const initialize = async (): Promise<void> => {
    await Promise.all([
      fetchAssignments(),
      fetchAssignmentTargets()
    ]);
  };

  // Auto-initialize
  initialize();

  return {
    // Reactive data
    assignments,
    availableModules,
    assignmentTargets,
    conflicts,
    dragDropState,
    loading,
    error,

    // Assignment CRUD methods
    createAssignment,
    updateAssignment,
    updateAssignmentOrder,
    removeAssignment,

    // Bulk operations
    bulkAssignModules,
    cancelBulkOperation,

    // Conflict management
    detectConflicts,
    resolveConflict,

    // Drag and drop
    startDrag,
    endDrag,
    handleDrop,
    updateDropZones,

    // Validation
    validateAssignment,

    // Computed properties
    getAssignmentsByTarget,
    conflictsByTarget,
    activeOperations
  };
}

// Global instance for shared state
let globalAssignmentWorkflow: AssignmentWorkflowComposable | null = null;

export function useGlobalAssignmentWorkflow(): AssignmentWorkflowComposable {
  if (!globalAssignmentWorkflow) {
    globalAssignmentWorkflow = useAssignmentWorkflow();
  }
  return globalAssignmentWorkflow;
}
