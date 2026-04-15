/**
 * Drag and Drop Assignment Composable
 *
 * Provides drag and drop functionality for module assignments with touch support
 * and conflict detection.
 */

import { ref, computed, type Ref } from 'vue';
import {
  DragDropState,
  DropZone,
  DragEvent,
  ModuleAssignment,
  StandaloneModule,
  AssignmentTarget,
  AssignmentConflict
} from '@/types/enhanced-classroom';

export interface DragDropAssignmentComposable {
  // State
  dragDropState: Ref<DragDropState>;
  isDragging: Ref<boolean>;
  currentDropTarget: Ref<string | null>;

  // Methods
  startDrag: (item: any, event: DragEvent | TouchEvent) => void;
  endDrag: () => void;
  handleDrop: (targetId: string, position: number) => Promise<void>;
  registerDropZone: (zone: DropZone) => void;
  unregisterDropZone: (zoneId: string) => void;
  updateDropZonePosition: (zoneId: string, position: { x: number; y: number; width: number; height: number }) => void;

  // Validation
  canDropOnTarget: (targetId: string) => boolean;
  getValidDropTargets: () => string[];

  // Touch Support
  handleTouchStart: (item: any, event: TouchEvent) => void;
  handleTouchMove: (event: TouchEvent) => void;
  handleTouchEnd: (event: TouchEvent) => void;
}

export function useDragDropAssignment(): DragDropAssignmentComposable {
  // State
  const dragDropState = ref<DragDropState>({
    isDragging: false,
    dropZones: [],
    validDropTargets: [],
    currentDropTarget: undefined
  });

  const isDragging = computed(() => dragDropState.value.isDragging);
  const currentDropTarget = ref<string | null>(null);

  // Touch state for mobile
  const touchState = ref({
    startX: 0,
    startY: 0,
    currentX: 0,
    currentY: 0,
    isDragging: false,
    draggedElement: null as HTMLElement | null
  });

  // Methods
  const startDrag = (item: any, event: DragEvent | TouchEvent) => {
    dragDropState.value.isDragging = true;
    dragDropState.value.draggedItem = {
      type: item.type || 'module',
      id: item.id,
      data: item
    };

    // Update valid drop targets based on item type
    updateValidDropTargets(item);

    // Handle different event types
    if ('dataTransfer' in event) {
      // Mouse drag event
      const dragEvent = event as globalThis.DragEvent;
      if (dragEvent.dataTransfer) {
        dragEvent.dataTransfer.effectAllowed = 'move';
        dragEvent.dataTransfer.setData('text/plain', item.id);
        dragEvent.dataTransfer.setData('application/json', JSON.stringify(item));
      }
    } else {
      // Touch event
      handleTouchStart(item, event as TouchEvent);
    }
  };

  const endDrag = () => {
    dragDropState.value.isDragging = false;
    dragDropState.value.draggedItem = undefined;
    dragDropState.value.validDropTargets = [];
    currentDropTarget.value = null;

    // Clean up touch state
    touchState.value.isDragging = false;
    touchState.value.draggedElement = null;

    // Remove any ghost elements
    const ghostElements = document.querySelectorAll('.drag-ghost');
    ghostElements.forEach(el => el.remove());
  };

  const handleDrop = async (targetId: string, position: number = -1) => {
    if (!dragDropState.value.draggedItem) return;

    const draggedItem = dragDropState.value.draggedItem;
    const targetZone = dragDropState.value.dropZones.find(zone => zone.id === targetId);

    if (!targetZone || !canDropOnTarget(targetId)) {
      endDrag();
      return;
    }

    try {
      // Emit drop event or handle assignment logic here
      // This would typically call an API or update local state
      console.log('Dropping item:', draggedItem, 'on target:', targetId, 'at position:', position);

      // For now, just end the drag operation
      endDrag();
    } catch (error) {
      console.error('Drop operation failed:', error);
      endDrag();
    }
  };

  const registerDropZone = (zone: DropZone) => {
    const existingIndex = dragDropState.value.dropZones.findIndex(z => z.id === zone.id);
    if (existingIndex >= 0) {
      dragDropState.value.dropZones[existingIndex] = zone;
    } else {
      dragDropState.value.dropZones.push(zone);
    }
  };

  const unregisterDropZone = (zoneId: string) => {
    dragDropState.value.dropZones = dragDropState.value.dropZones.filter(zone => zone.id !== zoneId);
  };

  const updateDropZonePosition = (zoneId: string, position: { x: number; y: number; width: number; height: number }) => {
    const zone = dragDropState.value.dropZones.find(z => z.id === zoneId);
    if (zone) {
      zone.position = position;
    }
  };

  const canDropOnTarget = (targetId: string): boolean => {
    const targetZone = dragDropState.value.dropZones.find(zone => zone.id === targetId);
    if (!targetZone || !dragDropState.value.draggedItem) return false;

    // Check if target accepts this item type
    if (!targetZone.accepts.includes(dragDropState.value.draggedItem.type as any)) {
      return false;
    }

    // Check capacity limits
    if (targetZone.maxItems && targetZone.currentItems >= targetZone.maxItems) {
      return false;
    }

    return true;
  };

  const getValidDropTargets = (): string[] => {
    if (!dragDropState.value.draggedItem) return [];

    return dragDropState.value.dropZones
      .filter(zone => canDropOnTarget(zone.id))
      .map(zone => zone.id);
  };

  const updateValidDropTargets = (item: any) => {
    dragDropState.value.validDropTargets = dragDropState.value.dropZones
      .filter(zone => {
        // Check if zone accepts this item type
        const itemType = item.type || 'module';
        return zone.accepts.includes(itemType);
      })
      .map(zone => zone.id);
  };

  // Touch Support Methods
  const handleTouchStart = (item: any, event: TouchEvent) => {
    event.preventDefault();

    const touch = event.touches[0];
    touchState.value.startX = touch.clientX;
    touchState.value.startY = touch.clientY;
    touchState.value.currentX = touch.clientX;
    touchState.value.currentY = touch.clientY;
    touchState.value.isDragging = true;

    // Create ghost element for visual feedback
    const target = event.target as HTMLElement;
    const ghostElement = target.cloneNode(true) as HTMLElement;
    ghostElement.classList.add('drag-ghost');
    ghostElement.style.position = 'fixed';
    ghostElement.style.pointerEvents = 'none';
    ghostElement.style.zIndex = '9999';
    ghostElement.style.opacity = '0.8';
    ghostElement.style.transform = 'rotate(5deg) scale(0.95)';
    ghostElement.style.left = `${touch.clientX - 50}px`;
    ghostElement.style.top = `${touch.clientY - 50}px`;

    document.body.appendChild(ghostElement);
    touchState.value.draggedElement = ghostElement;

    // Add touch event listeners
    document.addEventListener('touchmove', handleTouchMove, { passive: false });
    document.addEventListener('touchend', handleTouchEnd);
  };

  const handleTouchMove = (event: TouchEvent) => {
    if (!touchState.value.isDragging) return;

    event.preventDefault();

    const touch = event.touches[0];
    touchState.value.currentX = touch.clientX;
    touchState.value.currentY = touch.clientY;

    // Update ghost element position
    if (touchState.value.draggedElement) {
      touchState.value.draggedElement.style.left = `${touch.clientX - 50}px`;
      touchState.value.draggedElement.style.top = `${touch.clientY - 50}px`;
    }

    // Check for drop zone intersections
    const elementBelow = document.elementFromPoint(touch.clientX, touch.clientY);
    if (elementBelow) {
      const dropZone = elementBelow.closest('[data-drop-zone]');
      if (dropZone) {
        const zoneId = dropZone.getAttribute('data-drop-zone');
        if (zoneId && canDropOnTarget(zoneId)) {
          currentDropTarget.value = zoneId;

          // Add visual feedback
          dropZone.classList.add('drop-zone-active');
        }
      } else {
        // Clear current drop target if not over a valid zone
        if (currentDropTarget.value) {
          const currentZone = document.querySelector(`[data-drop-zone="${currentDropTarget.value}"]`);
          currentZone?.classList.remove('drop-zone-active');
          currentDropTarget.value = null;
        }
      }
    }
  };

  const handleTouchEnd = (event: TouchEvent) => {
    event.preventDefault();

    // Remove touch event listeners
    document.removeEventListener('touchmove', handleTouchMove);
    document.removeEventListener('touchend', handleTouchEnd);

    // Handle drop if over valid target
    if (currentDropTarget.value && canDropOnTarget(currentDropTarget.value)) {
      handleDrop(currentDropTarget.value);
    }

    // Clean up visual feedback
    const activeZones = document.querySelectorAll('.drop-zone-active');
    activeZones.forEach(zone => zone.classList.remove('drop-zone-active'));

    // Clean up touch state
    touchState.value.isDragging = false;
    if (touchState.value.draggedElement) {
      touchState.value.draggedElement.remove();
      touchState.value.draggedElement = null;
    }

    endDrag();
  };

  return {
    // State
    dragDropState,
    isDragging,
    currentDropTarget,

    // Methods
    startDrag,
    endDrag,
    handleDrop,
    registerDropZone,
    unregisterDropZone,
    updateDropZonePosition,

    // Validation
    canDropOnTarget,
    getValidDropTargets,

    // Touch Support
    handleTouchStart,
    handleTouchMove,
    handleTouchEnd
  };
}

// Global instance for shared drag and drop state
let globalDragDropAssignment: DragDropAssignmentComposable | null = null;

export function useGlobalDragDropAssignment(): DragDropAssignmentComposable {
  if (!globalDragDropAssignment) {
    globalDragDropAssignment = useDragDropAssignment();
  }
  return globalDragDropAssignment;
}
