<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  GripVertical,
  Plus,
  Minus,
  Target,
  Move,
  Check,
  X,
  AlertTriangle,
  Zap,
  ArrowRight,
  ArrowDown,
  ChevronUp,
  ChevronDown,
  Keyboard,
  Touch
} from 'lucide-vue-next';
import {
  StandaloneModule,
  AssignmentTarget,
  ModuleAssignment,
  DragDropState,
  DropZone
} from '@/types/enhanced-classroom';
import ModuleCard from './ModuleCard.vue';

interface Props {
  availableModules: StandaloneModule[];
  assignmentTargets: AssignmentTarget[];
  existingAssignments: ModuleAssignment[];
  viewMode?: 'visual' | 'list' | 'grid';
  selectedModules?: string[];
  selectedTargets?: string[];
  readonly?: boolean;
  enableMobileOptimizations?: boolean;
  enableKeyboardNavigation?: boolean;
}

interface Emits {
  (e: 'moduleAssigned', moduleId: string, targetId: string, position: number): void;
  (e: 'assignmentReordered', assignments: ModuleAssignment[]): void;
  (e: 'assignmentRemoved', assignmentId: string): void;
  (e: 'moduleSelected', moduleId: string): void;
  (e: 'targetSelected', targetId: string): void;
  (e: 'dragStart', item: any): void;
  (e: 'dragEnd'): void;
}

const props = withDefaults(defineProps<Props>(), {
  viewMode: 'visual',
  selectedModules: () => [],
  selectedTargets: () => [],
  readonly: false,
  enableMobileOptimizations: true,
  enableKeyboardNavigation: true
});

const emit = defineEmits<Emits>();

// Reactive state
const dragState = ref<DragDropState>({
  isDragging: false,
  dropZones: [],
  validDropTargets: []
});

const dropZones = ref<DropZone[]>([]);
const autoScrollState = ref({
  isScrolling: false,
  direction: 'none' as 'up' | 'down' | 'left' | 'right' | 'none',
  speed: 0
});

// Touch interaction state
const touchState = ref({
  isTouch: false,
  startPosition: { x: 0, y: 0 },
  currentPosition: { x: 0, y: 0 },
  threshold: 10,
  longPressTimer: null as ReturnType<typeof setTimeout> | null,
  longPressDelay: 500,
  gestureStarted: false
});

// Keyboard navigation state
const keyboardState = ref({
  focusedElement: null as HTMLElement | null,
  focusedIndex: -1,
  focusedType: 'module' as 'module' | 'target' | 'assignment'
});

// Container refs
const containerRef = ref<HTMLElement>();
const modulesContainerRef = ref<HTMLElement>();
const targetsContainerRef = ref<HTMLElement>();

// Computed properties
const isMobile = computed(() => {
  if (typeof window === 'undefined') return false;
  return window.innerWidth < 768;
});

const assignmentsByTarget = computed(() => {
  const grouped: Record<string, ModuleAssignment[]> = {};

  props.existingAssignments.forEach(assignment => {
    if (!grouped[assignment.targetId]) {
      grouped[assignment.targetId] = [];
    }
    grouped[assignment.targetId].push(assignment);
  });

  // Sort assignments by order
  Object.keys(grouped).forEach(targetId => {
    grouped[targetId].sort((a, b) => a.order - b.order);
  });

  return grouped;
});

const unassignedModules = computed(() => {
  const assignedModuleIds = new Set(props.existingAssignments.map(a => a.moduleId));
  return props.availableModules.filter(module => !assignedModuleIds.has(module.id));
});

const draggedItem = computed(() => dragState.value.draggedItem);
const isDragging = computed(() => dragState.value.isDragging);

// Drag and Drop Methods
const startDrag = (item: any, event: MouseEvent | TouchEvent) => {
  if (props.readonly) return;

  const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX;
  const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY;

  dragState.value = {
    isDragging: true,
    draggedItem: item,
    dropZones: [...dropZones.value],
    validDropTargets: getValidDropTargets(item),
    dragOffset: { x: 0, y: 0 }
  };

  // Create ghost element for visual feedback
  createGhostElement(item, clientX, clientY);

  // Highlight valid drop zones
  highlightDropZones(true);

  // Provide haptic feedback on mobile
  if (props.enableMobileOptimizations && 'vibrate' in navigator) {
    navigator.vibrate(50);
  }

  emit('dragStart', item);

  // Add global event listeners
  document.addEventListener('mousemove', handleDragMove);
  document.addEventListener('touchmove', handleDragMove, { passive: false });
  document.addEventListener('mouseup', endDrag);
  document.addEventListener('touchend', endDrag);
};

const handleDragMove = (event: MouseEvent | TouchEvent) => {
  if (!isDragging.value) return;

  event.preventDefault();

  const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX;
  const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY;

  // Update ghost element position
  updateGhostElement(clientX, clientY);

  // Check for auto-scroll
  handleAutoScroll(clientX, clientY);

  // Update drop zone highlighting
  const currentDropZone = findDropZone(clientX, clientY);
  updateDropZoneHighlighting(currentDropZone);
};

const endDrag = (event?: MouseEvent | TouchEvent) => {
  if (!isDragging.value) return;

  const clientX = event && ('touches' in event ? event.changedTouches[0].clientX : event.clientX);
  const clientY = event && ('touches' in event ? event.changedTouches[0].clientY : event.clientY);

  // Find drop target
  let dropZone: DropZone | null = null;
  if (clientX !== undefined && clientY !== undefined) {
    dropZone = findDropZone(clientX, clientY);
  }

  // Handle drop
  if (dropZone && dropZone.isValid && draggedItem.value) {
    handleDrop(dropZone, draggedItem.value);
  }

  // Clean up
  cleanupDrag();

  emit('dragEnd');
};

const handleDrop = (dropZone: DropZone, item: any) => {
  if (props.readonly) return;

  const position = calculateDropPosition(dropZone, item);

  if (item.type === 'module') {
    emit('moduleAssigned', item.id, dropZone.id, position);
  } else if (item.type === 'assignment') {
    // Handle assignment reordering
    if (item.targetId === dropZone.id) {
      // Reorder within same target
      const targetAssignments = assignmentsByTarget.value[dropZone.id] || [];
      const reorderedAssignments = reorderAssignments(targetAssignments, item.id, position);
      emit('assignmentReordered', reorderedAssignments);
    } else {
      // Move to different target
      emit('assignmentRemoved', item.id);
      nextTick(() => {
        emit('moduleAssigned', item.moduleId, dropZone.id, position);
      });
    }
  }

  // Provide success haptic feedback
  if (props.enableMobileOptimizations && 'vibrate' in navigator) {
    navigator.vibrate([50, 50, 100]);
  }
};

// Touch Gesture Handlers
const handleTouchStart = (item: any, event: TouchEvent) => {
  if (!props.enableMobileOptimizations) return;

  const touch = event.touches[0];
  touchState.value = {
    isTouch: true,
    startPosition: { x: touch.clientX, y: touch.clientY },
    currentPosition: { x: touch.clientX, y: touch.clientY },
    threshold: 10,
    longPressTimer: null,
    longPressDelay: 500,
    gestureStarted: false
  };

  // Start long press timer for drag initiation
  touchState.value.longPressTimer = setTimeout(() => {
    if (!touchState.value.gestureStarted) {
      touchState.value.gestureStarted = true;
      startDrag(item, event);

      // Provide long press haptic feedback
      if ('vibrate' in navigator) {
        navigator.vibrate(100);
      }
    }
  }, touchState.value.longPressDelay);
};

const handleTouchMove = (event: TouchEvent) => {
  if (!touchState.value.isTouch) return;

  const touch = event.touches[0];
  touchState.value.currentPosition = { x: touch.clientX, y: touch.clientY };

  // Calculate movement distance
  const deltaX = Math.abs(touch.clientX - touchState.value.startPosition.x);
  const deltaY = Math.abs(touch.clientY - touchState.value.startPosition.y);
  const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

  // Cancel long press if moved too much
  if (distance > touchState.value.threshold && touchState.value.longPressTimer) {
    clearTimeout(touchState.value.longPressTimer);
    touchState.value.longPressTimer = null;
  }

  // Handle drag move if gesture started
  if (touchState.value.gestureStarted) {
    handleDragMove(event);
  }
};

const handleTouchEnd = (event: TouchEvent) => {
  if (!touchState.value.isTouch) return;

  // Clear long press timer
  if (touchState.value.longPressTimer) {
    clearTimeout(touchState.value.longPressTimer);
    touchState.value.longPressTimer = null;
  }

  // Handle drag end if gesture was started
  if (touchState.value.gestureStarted) {
    endDrag(event);
  }

  // Reset touch state
  touchState.value = {
    isTouch: false,
    startPosition: { x: 0, y: 0 },
    currentPosition: { x: 0, y: 0 },
    threshold: 10,
    longPressTimer: null,
    longPressDelay: 500,
    gestureStarted: false
  };
};

// Keyboard Navigation
const handleKeyDown = (event: KeyboardEvent) => {
  if (!props.enableKeyboardNavigation) return;

  const { key, ctrlKey, shiftKey, altKey } = event;

  switch (key) {
    case 'ArrowUp':
    case 'ArrowDown':
    case 'ArrowLeft':
    case 'ArrowRight':
      event.preventDefault();
      handleArrowNavigation(key);
      break;

    case 'Enter':
    case ' ':
      event.preventDefault();
      handleActivation();
      break;

    case 'Escape':
      event.preventDefault();
      if (isDragging.value) {
        cancelDrag();
      } else {
        clearFocus();
      }
      break;

    case 'Tab':
      handleTabNavigation(shiftKey);
      break;

    case 'd':
      if (ctrlKey) {
        event.preventDefault();
        handleKeyboardDrag();
      }
      break;
  }
};

const handleArrowNavigation = (direction: string) => {
  // Implementation for keyboard navigation between elements
  const focusableElements = getFocusableElements();

  if (focusableElements.length === 0) return;

  let newIndex = keyboardState.value.focusedIndex;

  switch (direction) {
    case 'ArrowUp':
      newIndex = Math.max(0, newIndex - 1);
      break;
    case 'ArrowDown':
      newIndex = Math.min(focusableElements.length - 1, newIndex + 1);
      break;
    case 'ArrowLeft':
      // Move to previous section
      break;
    case 'ArrowRight':
      // Move to next section
      break;
  }

  if (newIndex !== keyboardState.value.focusedIndex) {
    focusElement(focusableElements[newIndex], newIndex);
  }
};

const handleActivation = () => {
  const focusedElement = keyboardState.value.focusedElement;
  if (!focusedElement) return;

  // Trigger click or drag based on element type
  const elementType = focusedElement.dataset.type;
  const elementId = focusedElement.dataset.id;

  if (elementType === 'module') {
    emit('moduleSelected', elementId!);
  } else if (elementType === 'target') {
    emit('targetSelected', elementId!);
  }
};

// Auto-scroll functionality
const handleAutoScroll = (clientX: number, clientY: number) => {
  if (!containerRef.value) return;

  const container = containerRef.value;
  const rect = container.getBoundingClientRect();
  const scrollThreshold = 50;
  const maxScrollSpeed = 10;

  let scrollDirection: 'up' | 'down' | 'left' | 'right' | 'none' = 'none';
  let scrollSpeed = 0;

  // Vertical scrolling
  if (clientY < rect.top + scrollThreshold) {
    scrollDirection = 'up';
    scrollSpeed = Math.min(maxScrollSpeed, (rect.top + scrollThreshold - clientY) / scrollThreshold * maxScrollSpeed);
  } else if (clientY > rect.bottom - scrollThreshold) {
    scrollDirection = 'down';
    scrollSpeed = Math.min(maxScrollSpeed, (clientY - rect.bottom + scrollThreshold) / scrollThreshold * maxScrollSpeed);
  }

  // Horizontal scrolling
  if (clientX < rect.left + scrollThreshold) {
    scrollDirection = 'left';
    scrollSpeed = Math.min(maxScrollSpeed, (rect.left + scrollThreshold - clientX) / scrollThreshold * maxScrollSpeed);
  } else if (clientX > rect.right - scrollThreshold) {
    scrollDirection = 'right';
    scrollSpeed = Math.min(maxScrollSpeed, (clientX - rect.right + scrollThreshold) / scrollThreshold * maxScrollSpeed);
  }

  if (scrollDirection !== 'none' && scrollSpeed > 0) {
    startAutoScroll(scrollDirection, scrollSpeed);
  } else {
    stopAutoScroll();
  }
};

const startAutoScroll = (direction: 'up' | 'down' | 'left' | 'right', speed: number) => {
  if (autoScrollState.value.isScrolling &&
      autoScrollState.value.direction === direction &&
      autoScrollState.value.speed === speed) {
    return;
  }

  stopAutoScroll();

  autoScrollState.value = {
    isScrolling: true,
    direction,
    speed
  };

  const scroll = () => {
    if (!autoScrollState.value.isScrolling || !containerRef.value) return;

    const container = containerRef.value;
    const scrollAmount = autoScrollState.value.speed;

    switch (autoScrollState.value.direction) {
      case 'up':
        container.scrollTop -= scrollAmount;
        break;
      case 'down':
        container.scrollTop += scrollAmount;
        break;
      case 'left':
        container.scrollLeft -= scrollAmount;
        break;
      case 'right':
        container.scrollLeft += scrollAmount;
        break;
    }

    if (autoScrollState.value.isScrolling) {
      requestAnimationFrame(scroll);
    }
  };

  requestAnimationFrame(scroll);
};

const stopAutoScroll = () => {
  autoScrollState.value = {
    isScrolling: false,
    direction: 'none',
    speed: 0
  };
};

// Utility functions
const getValidDropTargets = (item: any): string[] => {
  if (item.type === 'module') {
    return props.assignmentTargets.map(target => target.id);
  } else if (item.type === 'assignment') {
    return props.assignmentTargets.map(target => target.id);
  }
  return [];
};

const findDropZone = (x: number, y: number): DropZone | null => {
  return dropZones.value.find(zone =>
    x >= zone.position.x &&
    x <= zone.position.x + zone.position.width &&
    y >= zone.position.y &&
    y <= zone.position.y + zone.position.height
  ) || null;
};

const calculateDropPosition = (dropZone: DropZone, item: any): number => {
  const targetAssignments = assignmentsByTarget.value[dropZone.id] || [];
  return targetAssignments.length;
};

const reorderAssignments = (assignments: ModuleAssignment[], movedId: string, newPosition: number): ModuleAssignment[] => {
  const reordered = [...assignments];
  const movedIndex = reordered.findIndex(a => a.id === movedId);

  if (movedIndex === -1) return reordered;

  const [movedAssignment] = reordered.splice(movedIndex, 1);
  reordered.splice(newPosition, 0, movedAssignment);

  // Update order values
  return reordered.map((assignment, index) => ({
    ...assignment,
    order: index
  }));
};

const updateDropZones = () => {
  const zones: DropZone[] = [];

  // Add drop zones for each target
  props.assignmentTargets.forEach(target => {
    const element = document.querySelector(`[data-target-id="${target.id}"]`);
    if (element) {
      const rect = element.getBoundingClientRect();
      zones.push({
        id: target.id,
        type: target.type,
        accepts: ['module', 'assignment'],
        isActive: true,
        isValid: true,
        position: {
          x: rect.left,
          y: rect.top,
          width: rect.width,
          height: rect.height
        },
        maxItems: target.maxModules,
        currentItems: assignmentsByTarget.value[target.id]?.length || 0
      });
    }
  });

  dropZones.value = zones;
};

const highlightDropZones = (highlight: boolean) => {
  dropZones.value.forEach(zone => {
    const element = document.querySelector(`[data-target-id="${zone.id}"]`);
    if (element) {
      if (highlight && zone.isValid) {
        element.classList.add('drop-zone-highlight');
      } else {
        element.classList.remove('drop-zone-highlight');
      }
    }
  });
};

const updateDropZoneHighlighting = (currentZone: DropZone | null) => {
  dropZones.value.forEach(zone => {
    const element = document.querySelector(`[data-target-id="${zone.id}"]`);
    if (element) {
      if (zone === currentZone && zone.isValid) {
        element.classList.add('drop-zone-active');
      } else {
        element.classList.remove('drop-zone-active');
      }
    }
  });
};

// Ghost element for drag feedback
let ghostElement: HTMLElement | null = null;

const createGhostElement = (item: any, x: number, y: number) => {
  ghostElement = document.createElement('div');
  ghostElement.className = 'drag-ghost';
  ghostElement.style.position = 'fixed';
  ghostElement.style.pointerEvents = 'none';
  ghostElement.style.zIndex = '9999';
  ghostElement.style.opacity = '0.8';
  ghostElement.style.transform = 'rotate(5deg)';
  ghostElement.textContent = item.title || item.id;

  updateGhostElement(x, y);
  document.body.appendChild(ghostElement);
};

const updateGhostElement = (x: number, y: number) => {
  if (ghostElement) {
    ghostElement.style.left = `${x + 10}px`;
    ghostElement.style.top = `${y + 10}px`;
  }
};

const removeGhostElement = () => {
  if (ghostElement) {
    document.body.removeChild(ghostElement);
    ghostElement = null;
  }
};

const cleanupDrag = () => {
  dragState.value = {
    isDragging: false,
    dropZones: [],
    validDropTargets: []
  };

  highlightDropZones(false);
  updateDropZoneHighlighting(null);
  removeGhostElement();
  stopAutoScroll();

  // Remove global event listeners
  document.removeEventListener('mousemove', handleDragMove);
  document.removeEventListener('touchmove', handleDragMove);
  document.removeEventListener('mouseup', endDrag);
  document.removeEventListener('touchend', endDrag);
};

const cancelDrag = () => {
  cleanupDrag();
  emit('dragEnd');
};

// Keyboard navigation utilities
const getFocusableElements = (): HTMLElement[] => {
  if (!containerRef.value) return [];

  return Array.from(containerRef.value.querySelectorAll('[data-focusable="true"]'));
};

const focusElement = (element: HTMLElement, index: number) => {
  keyboardState.value.focusedElement = element;
  keyboardState.value.focusedIndex = index;
  element.focus();
  element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

const clearFocus = () => {
  keyboardState.value.focusedElement = null;
  keyboardState.value.focusedIndex = -1;
};

const handleTabNavigation = (reverse: boolean) => {
  // Let browser handle tab navigation naturally
};

const handleKeyboardDrag = () => {
  // Implement keyboard-initiated drag
  const focusedElement = keyboardState.value.focusedElement;
  if (!focusedElement) return;

  const elementType = focusedElement.dataset.type;
  const elementId = focusedElement.dataset.id;

  if (elementType && elementId) {
    const item = {
      type: elementType,
      id: elementId,
      title: focusedElement.textContent || elementId
    };

    // Start keyboard drag mode
    dragState.value = {
      isDragging: true,
      draggedItem: item,
      dropZones: [...dropZones.value],
      validDropTargets: getValidDropTargets(item)
    };

    highlightDropZones(true);
    emit('dragStart', item);
  }
};

// Lifecycle
onMounted(() => {
  updateDropZones();

  if (props.enableKeyboardNavigation) {
    document.addEventListener('keydown', handleKeyDown);
  }

  // Update drop zones on window resize
  window.addEventListener('resize', updateDropZones);

  // Update drop zones on scroll
  if (containerRef.value) {
    containerRef.value.addEventListener('scroll', updateDropZones);
  }
});

onUnmounted(() => {
  cleanupDrag();

  if (props.enableKeyboardNavigation) {
    document.removeEventListener('keydown', handleKeyDown);
  }

  window.removeEventListener('resize', updateDropZones);

  if (containerRef.value) {
    containerRef.value.removeEventListener('scroll', updateDropZones);
  }
});

// Watch for changes that require drop zone updates
watch([() => props.assignmentTargets, () => props.existingAssignments], () => {
  nextTick(() => {
    updateDropZones();
  });
}, { deep: true });
</script>

<template>
  <div
    ref="containerRef"
    class="drag-drop-assignment h-full flex flex-col overflow-hidden"
    :class="{ 'touch-enabled': enableMobileOptimizations }"
  >
    <!-- Accessibility Instructions -->
    <div v-if="enableKeyboardNavigation" class="sr-only" aria-live="polite">
      <p>Use arrow keys to navigate, Enter to select, D+Ctrl to drag, Escape to cancel</p>
    </div>

    <!-- Visual Mode -->
    <div v-if="viewMode === 'visual'" class="flex-1 flex overflow-hidden">
      <!-- Available Modules Panel -->
      <div class="w-1/3 border-r bg-muted/30 flex flex-col">
        <div class="p-4 border-b bg-background">
          <h3 class="font-medium flex items-center gap-2">
            <Move class="h-4 w-4" />
            Available Modules
            <Badge variant="secondary">{{ unassignedModules.length }}</Badge>
          </h3>
          <p class="text-xs text-muted-foreground mt-1">
            {{ enableMobileOptimizations ? 'Long press to drag' : 'Drag to assign' }}
          </p>
        </div>

        <ScrollArea class="flex-1 p-4">
          <div class="space-y-2">
            <div
              v-for="module in unassignedModules"
              :key="module.id"
              :data-type="'module'"
              :data-id="module.id"
              :data-focusable="true"
              class="module-item cursor-move p-3 border rounded-lg bg-background hover:bg-accent transition-colors"
              :class="{
                'selected': selectedModules?.includes(module.id),
                'touch-target': enableMobileOptimizations
              }"
              draggable="true"
              tabindex="0"
              @mousedown="startDrag({ type: 'module', id: module.id, title: module.title, moduleId: module.id }, $event)"
              @touchstart="handleTouchStart({ type: 'module', id: module.id, title: module.title, moduleId: module.id }, $event)"
              @touchmove="handleTouchMove"
              @touchend="handleTouchEnd"
              @click="$emit('moduleSelected', module.id)"
            >
              <div class="flex items-start gap-3">
                <GripVertical class="h-4 w-4 text-muted-foreground mt-1 flex-shrink-0" />
                <div class="flex-1 min-w-0">
                  <h4 class="font-medium text-sm truncate">{{ module.title }}</h4>
                  <p class="text-xs text-muted-foreground mt-1 line-clamp-2">
                    {{ module.description }}
                  </p>
                  <div class="flex items-center gap-2 mt-2">
                    <Badge variant="outline" class="text-xs">
                      {{ module.difficulty }}
                    </Badge>
                    <span class="text-xs text-muted-foreground">
                      {{ module.estimatedDuration }}min
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </ScrollArea>
      </div>

      <!-- Assignment Targets Panel -->
      <div class="flex-1 flex flex-col">
        <div class="p-4 border-b bg-background">
          <h3 class="font-medium flex items-center gap-2">
            <Target class="h-4 w-4" />
            Assignment Targets
            <Badge variant="secondary">{{ assignmentTargets.length }}</Badge>
          </h3>
          <p class="text-xs text-muted-foreground mt-1">
            Drop modules here to create assignments
          </p>
        </div>

        <ScrollArea class="flex-1 p-4">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Card
              v-for="target in assignmentTargets"
              :key="target.id"
              :data-target-id="target.id"
              :data-type="'target'"
              :data-id="target.id"
              :data-focusable="true"
              class="target-card min-h-[200px] transition-all duration-200"
              :class="{
                'selected': selectedTargets?.includes(target.id),
                'drop-zone': true,
                'touch-target': enableMobileOptimizations
              }"
              tabindex="0"
              @click="$emit('targetSelected', target.id)"
            >
              <CardHeader class="pb-3">
                <CardTitle class="text-sm flex items-center justify-between">
                  <span class="truncate">{{ target.title }}</span>
                  <Badge variant="outline" class="ml-2">
                    {{ assignmentsByTarget[target.id]?.length || 0 }}
                  </Badge>
                </CardTitle>
                <p class="text-xs text-muted-foreground">
                  {{ target.description }}
                </p>
              </CardHeader>

              <CardContent class="pt-0">
                <!-- Assigned Modules -->
                <div class="space-y-2">
                  <div
                    v-for="(assignment, index) in assignmentsByTarget[target.id] || []"
                    :key="assignment.id"
                    :data-type="'assignment'"
                    :data-id="assignment.id"
                    :data-focusable="true"
                    class="assignment-item p-2 border rounded bg-background/50 cursor-move group"
                    :class="{ 'touch-target': enableMobileOptimizations }"
                    draggable="true"
                    tabindex="0"
                    @mousedown="startDrag({
                      type: 'assignment',
                      id: assignment.id,
                      moduleId: assignment.moduleId,
                      targetId: assignment.targetId,
                      title: assignment.module?.title || assignment.moduleId
                    }, $event)"
                    @touchstart="handleTouchStart({
                      type: 'assignment',
                      id: assignment.id,
                      moduleId: assignment.moduleId,
                      targetId: assignment.targetId,
                      title: assignment.module?.title || assignment.moduleId
                    }, $event)"
                    @touchmove="handleTouchMove"
                    @touchend="handleTouchEnd"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2 flex-1 min-w-0">
                        <GripVertical class="h-3 w-3 text-muted-foreground" />
                        <span class="text-xs font-medium truncate">
                          {{ assignment.module?.title || assignment.moduleId }}
                        </span>
                      </div>
                      <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <Button
                          v-if="!readonly"
                          variant="ghost"
                          size="sm"
                          class="h-6 w-6 p-0"
                          @click.stop="$emit('assignmentRemoved', assignment.id)"
                        >
                          <X class="h-3 w-3" />
                        </Button>
                      </div>
                    </div>

                    <!-- Assignment Status -->
                    <div class="flex items-center gap-2 mt-1">
                      <Badge
                        :variant="assignment.isRequired ? 'default' : 'secondary'"
                        class="text-xs"
                      >
                        {{ assignment.isRequired ? 'Required' : 'Optional' }}
                      </Badge>
                      <span class="text-xs text-muted-foreground">
                        Order: {{ assignment.order + 1 }}
                      </span>
                    </div>
                  </div>

                  <!-- Drop Zone Indicator -->
                  <div
                    v-if="assignmentsByTarget[target.id]?.length === 0"
                    class="drop-zone-placeholder p-4 border-2 border-dashed border-muted-foreground/25 rounded-lg text-center"
                  >
                    <Target class="h-8 w-8 text-muted-foreground/50 mx-auto mb-2" />
                    <p class="text-xs text-muted-foreground">
                      {{ enableMobileOptimizations ? 'Long press and drag modules here' : 'Drop modules here' }}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </ScrollArea>
      </div>
    </div>

    <!-- List/Grid Mode -->
    <div v-else class="flex-1 overflow-auto p-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Available Modules -->
        <div>
          <h3 class="font-medium mb-4 flex items-center gap-2">
            <Move class="h-4 w-4" />
            Available Modules ({{ unassignedModules.length }})
          </h3>
          <div :class="viewMode === 'grid' ? 'grid grid-cols-1 gap-2' : 'space-y-2'">
            <ModuleCard
              v-for="module in unassignedModules"
              :key="module.id"
              :module="module"
              variant="compact"
              :draggable="!readonly"
              :class="{ 'selected': selectedModules?.includes(module.id) }"
              @dragstart="startDrag({ type: 'module', id: module.id, title: module.title, moduleId: module.id }, $event)"
              @click="$emit('moduleSelected', module.id)"
            />
          </div>
        </div>

        <!-- Assignment Targets -->
        <div>
          <h3 class="font-medium mb-4 flex items-center gap-2">
            <Target class="h-4 w-4" />
            Assignment Targets ({{ assignmentTargets.length }})
          </h3>
          <div class="space-y-4">
            <Card
              v-for="target in assignmentTargets"
              :key="target.id"
              :data-target-id="target.id"
              class="target-card"
              :class="{ 'selected': selectedTargets?.includes(target.id) }"
              @click="$emit('targetSelected', target.id)"
            >
              <CardHeader>
                <CardTitle class="text-sm">{{ target.title }}</CardTitle>
                <p class="text-xs text-muted-foreground">{{ target.description }}</p>
              </CardHeader>
              <CardContent>
                <div class="text-xs text-muted-foreground mb-2">
                  Assigned Modules: {{ assignmentsByTarget[target.id]?.length || 0 }}
                </div>
                <div class="space-y-1">
                  <div
                    v-for="assignment in (assignmentsByTarget[target.id] || []).slice(0, 3)"
                    :key="assignment.id"
                    class="text-xs p-1 bg-muted rounded truncate"
                  >
                    {{ assignment.module?.title || assignment.moduleId }}
                  </div>
                  <div
                    v-if="(assignmentsByTarget[target.id]?.length || 0) > 3"
                    class="text-xs text-muted-foreground"
                  >
                    +{{ (assignmentsByTarget[target.id]?.length || 0) - 3 }} more
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>

    <!-- Drag Feedback Overlay -->
    <div
      v-if="isDragging"
      class="fixed inset-0 pointer-events-none z-50 flex items-center justify-center"
    >
      <div class="bg-primary text-primary-foreground px-4 py-2 rounded-lg shadow-lg">
        <div class="flex items-center gap-2">
          <Move class="h-4 w-4" />
          <span class="text-sm">
            Dragging: {{ draggedItem?.title || draggedItem?.id }}
          </span>
        </div>
      </div>
    </div>

    <!-- Touch Instructions -->
    <div
      v-if="enableMobileOptimizations && isMobile"
      class="fixed bottom-4 right-4 bg-background border rounded-lg p-3 shadow-lg max-w-xs"
    >
      <div class="flex items-center gap-2 mb-2">
        <Touch class="h-4 w-4" />
        <span class="text-sm font-medium">Touch Controls</span>
      </div>
      <ul class="text-xs text-muted-foreground space-y-1">
        <li>• Long press to start dragging</li>
        <li>• Drag to assignment targets</li>
        <li>• Release to drop</li>
      </ul>
    </div>

    <!-- Keyboard Instructions -->
    <div
      v-if="enableKeyboardNavigation && !isMobile"
      class="fixed bottom-4 left-4 bg-background border rounded-lg p-3 shadow-lg max-w-xs"
    >
      <div class="flex items-center gap-2 mb-2">
        <Keyboard class="h-4 w-4" />
        <span class="text-sm font-medium">Keyboard Shortcuts</span>
      </div>
      <ul class="text-xs text-muted-foreground space-y-1">
        <li>• Arrow keys: Navigate</li>
        <li>• Enter/Space: Select</li>
        <li>• Ctrl+D: Start drag</li>
        <li>• Escape: Cancel</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.drag-drop-assignment {
  height: 100%;
}

/* Touch-friendly targets */
.touch-target {
  min-height: 44px;
  min-width: 44px;
}

.touch-enabled .touch-target {
  padding: 0.75rem;
}

/* Drag and drop states */
.drop-zone-highlight {
  background-color: hsl(var(--primary) / 0.1);
  border: 2px dashed hsl(var(--primary));
  transform: scale(1.02);
}

.drop-zone-active {
  background-color: hsl(var(--primary) / 0.2);
  border: 2px solid hsl(var(--primary));
  transform: scale(1.05);
  box-shadow: 0 8px 25px -5px hsl(var(--primary) / 0.3);
}

.drag-ghost {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
  max-width: 200px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Selection states */
.selected {
  background-color: hsl(var(--primary) / 0.1);
  border-color: hsl(var(--primary));
}

/* Module and assignment items */
.module-item:hover,
.assignment-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.1);
}

.module-item:active,
.assignment-item:active {
  transform: translateY(0);
}

/* Target cards */
.target-card {
  transition: all 0.2s ease-in-out;
}

.target-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Drop zone placeholder */
.drop-zone-placeholder {
  transition: all 0.2s ease-in-out;
}

.drop-zone-highlight .drop-zone-placeholder {
  border-color: hsl(var(--primary));
  background-color: hsl(var(--primary) / 0.05);
}

/* Auto-scroll indicators */
.auto-scroll-indicator {
  position: fixed;
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  z-index: 1000;
  pointer-events: none;
}

/* Focus styles for keyboard navigation */
[data-focusable="true"]:focus {
  outline: 2px solid hsl(var(--primary));
  outline-offset: 2px;
}

/* Mobile optimizations */
@media (max-width: 768px) {
  .drag-drop-assignment {
    touch-action: manipulation;
  }

  .module-item,
  .assignment-item,
  .target-card {
    user-select: none;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
  }

  /* Larger touch targets on mobile */
  .touch-enabled button {
    min-height: 44px;
    min-width: 44px;
  }

  /* Improved spacing */
  .touch-enabled .p-2 {
    padding: 0.75rem;
  }

  .touch-enabled .p-3 {
    padding: 1rem;
  }
}

/* Smooth animations */
* {
  transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
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

/* Line clamp utility */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
