<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  BookOpen,
  Star,
  Users,
  TrendingUp,
  Eye,
  GripVertical,
  Play,
  CheckCircle,
  Tag,
  User,
  Clock
} from 'lucide-vue-next';
import { StandaloneModule } from '@/types/enhanced-classroom';
import { useMobileOptimization } from '@/composables/useMobileOptimization';

interface Props {
  module: StandaloneModule;
  showUsageStats?: boolean;
  showAssignmentStatus?: boolean;
  variant?: 'library' | 'assignment' | 'compact';
  draggable?: boolean;
  selected?: boolean;
  disabled?: boolean;
}

interface Emits {
  (e: 'preview', moduleId: string): void;
  (e: 'assign', moduleId: string): void;
  (e: 'dragStart', moduleId: string, event: DragEvent | TouchEvent): void;
  (e: 'dragEnd', moduleId: string, event: DragEvent | TouchEvent): void;
  (e: 'click', moduleId: string): void;
}

const props = withDefaults(defineProps<Props>(), {
  showUsageStats: false,
  showAssignmentStatus: false,
  variant: 'library',
  draggable: false,
  selected: false,
  disabled: false
});

const emit = defineEmits<Emits>();

// Mobile optimization composable
const {
  isMobile,
  isTouch,
  enableHapticFeedback,
  setupLongPress,
  addTouchFriendlyClass
} = useMobileOptimization();

// Local state
const cardRef = ref<HTMLElement>();
const isDragging = ref(false);
const isHovered = ref(false);
const showTooltip = ref(false);
const tooltipTimeout = ref<ReturnType<typeof setTimeout>>();
const longPressCleanup = ref<(() => void) | null>(null);

// Computed properties
const cardClasses = computed(() => {
  const base = 'group relative overflow-hidden rounded-lg border bg-card transition-all duration-200';
  const variants = {
    library: 'hover:border-gray-200 hover:shadow-xl',
    assignment: 'hover:border-primary/50 hover:shadow-lg',
    compact: 'hover:bg-accent/50'
  };

  const states = {
    selected: 'ring-2 ring-primary border-primary',
    disabled: 'opacity-50 cursor-not-allowed',
    dragging: 'opacity-75 scale-95 rotate-2 z-50',
    draggable: 'cursor-grab active:cursor-grabbing',
    mobile: isMobile.value ? 'touch-friendly mobile-card' : '',
    touch: isTouch.value ? 'touch-target' : ''
  };

  return [
    base,
    variants[props.variant],
    props.selected && states.selected,
    props.disabled && states.disabled,
    isDragging.value && states.dragging,
    props.draggable && !props.disabled && states.draggable,
    states.mobile,
    states.touch
  ].filter(Boolean).join(' ');
});

const difficultyColor = computed(() => {
  const colors = {
    beginner: 'bg-green-500/90 text-white',
    intermediate: 'bg-yellow-500/90 text-white',
    advanced: 'bg-red-500/90 text-white'
  };
  return colors[props.module.difficulty] || 'bg-gray-500/90 text-white';
});

const assignmentStatusColor = computed(() => {
  if (!props.showAssignmentStatus) return '';

  if (props.module.assignmentCount === 0) {
    return 'bg-gray-500/90 text-white';
  } else if (props.module.assignmentCount < 3) {
    return 'bg-blue-500/90 text-white';
  } else {
    return 'bg-green-500/90 text-white';
  }
});

const assignmentStatusText = computed(() => {
  if (!props.showAssignmentStatus) return '';

  if (props.module.assignmentCount === 0) {
    return 'Unassigned';
  } else {
    return `${props.module.assignmentCount} assignments`;
  }
});

const formattedDuration = computed(() => {
  const minutes = props.module.estimatedDuration;
  if (minutes >= 60) {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
  }
  return `${minutes}m`;
});

const formattedRating = computed(() => {
  return props.module.rating.toFixed(1);
});

const popularityRank = computed(() => {
  if (!props.showUsageStats) return null;
  return props.module.usageAnalytics.popularityRank;
});

const completionRate = computed(() => {
  return Math.round(props.module.completionRate);
});

const isCompactView = computed(() => props.variant === 'compact');

// Methods
const handleClick = (event: MouseEvent) => {
  if (props.disabled) return;

  // Don't trigger click if dragging
  if (isDragging.value) return;

  // Don't trigger click if clicking on action buttons
  const target = event.target as HTMLElement;
  if (target.closest('button') || target.closest('[role="button"]')) {
    return;
  }

  // Provide haptic feedback on mobile
  if (isMobile.value || isTouch.value) {
    enableHapticFeedback(25);
  }

  emit('click', props.module.id);
};

const handlePreview = (event: MouseEvent) => {
  event.stopPropagation();
  if (props.disabled) return;

  // Provide haptic feedback
  if (isMobile.value || isTouch.value) {
    enableHapticFeedback(50);
  }

  emit('preview', props.module.id);
};

const handleAssign = (event: MouseEvent) => {
  event.stopPropagation();
  if (props.disabled) return;

  // Provide haptic feedback
  if (isMobile.value || isTouch.value) {
    enableHapticFeedback(75);
  }

  emit('assign', props.module.id);
};

// Drag and drop handlers
const handleDragStart = (event: DragEvent) => {
  if (!props.draggable || props.disabled) {
    event.preventDefault();
    return;
  }

  isDragging.value = true;

  // Set drag data
  event.dataTransfer!.effectAllowed = 'move';
  event.dataTransfer!.setData('text/plain', props.module.id);
  event.dataTransfer!.setData('application/json', JSON.stringify({
    type: 'module',
    id: props.module.id,
    data: props.module
  }));

  // Create drag image
  if (cardRef.value) {
    const dragImage = cardRef.value.cloneNode(true) as HTMLElement;
    dragImage.style.transform = 'rotate(5deg)';
    dragImage.style.opacity = '0.8';
    dragImage.classList.add('drag-preview-mobile');
    document.body.appendChild(dragImage);
    event.dataTransfer!.setDragImage(dragImage, 50, 50);

    // Clean up drag image after a short delay
    setTimeout(() => {
      if (document.body.contains(dragImage)) {
        document.body.removeChild(dragImage);
      }
    }, 0);
  }

  // Provide haptic feedback
  if (isMobile.value || isTouch.value) {
    enableHapticFeedback(100);
  }

  emit('dragStart', props.module.id, event);
};

const handleDragEnd = (event: DragEvent) => {
  isDragging.value = false;

  // Provide haptic feedback
  if (isMobile.value || isTouch.value) {
    enableHapticFeedback([50, 50, 100]);
  }

  emit('dragEnd', props.module.id, event);
};

// Touch handlers for mobile drag and drop
const handleLongPress = () => {
  if (!props.draggable || props.disabled) return;

  isDragging.value = true;

  // Provide strong haptic feedback for long press
  enableHapticFeedback(200);

  // Create a synthetic touch event for drag start
  const syntheticEvent = new TouchEvent('touchstart', {
    touches: [new Touch({
      identifier: 0,
      target: cardRef.value!,
      clientX: 0,
      clientY: 0
    })]
  });

  emit('dragStart', props.module.id, syntheticEvent);
};

// Tooltip handlers
const showTooltipDelayed = () => {
  if (isCompactView.value || isMobile.value) return;

  tooltipTimeout.value = setTimeout(() => {
    showTooltip.value = true;
  }, 500);
};

const hideTooltip = () => {
  if (tooltipTimeout.value) {
    clearTimeout(tooltipTimeout.value);
  }
  showTooltip.value = false;
};

// Hover handlers
const handleMouseEnter = () => {
  if (isMobile.value || isTouch.value) return; // Skip hover on touch devices

  isHovered.value = true;
  showTooltipDelayed();
};

const handleMouseLeave = () => {
  if (isMobile.value || isTouch.value) return; // Skip hover on touch devices

  isHovered.value = false;
  hideTooltip();
};

// Lifecycle
onMounted(() => {
  if (cardRef.value) {
    // Add touch-friendly classes
    addTouchFriendlyClass(cardRef.value);

    // Setup long press for mobile drag and drop
    if (props.draggable && (isMobile.value || isTouch.value)) {
      longPressCleanup.value = setupLongPress(cardRef.value, handleLongPress, 600);
    }
  }
});

onUnmounted(() => {
  if (tooltipTimeout.value) {
    clearTimeout(tooltipTimeout.value);
  }

  if (longPressCleanup.value) {
    longPressCleanup.value();
  }
});
</script>

<template>
  <div
    ref="cardRef"
    :class="cardClasses"
    :draggable="draggable && !disabled"
    @click="handleClick"
    @dragstart="handleDragStart"
    @dragend="handleDragEnd"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <!-- Drag Handle (for assignment variant) -->
    <div
      v-if="draggable && variant === 'assignment'"
      class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity z-10"
      :class="{ 'opacity-100': isMobile || isTouch }"
    >
      <GripVertical class="h-4 w-4 text-muted-foreground drag-handle-mobile" />
    </div>

    <!-- Compact Layout -->
    <div v-if="isCompactView" class="flex items-center p-3 gap-3 mobile-list-item">
      <!-- Module Icon/Thumbnail -->
      <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
        <BookOpen class="h-6 w-6 text-primary" />
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0 mobile-list-item-content">
        <div class="flex items-start justify-between">
          <div class="min-w-0 flex-1">
            <h3 class="font-medium text-sm truncate group-hover:text-primary transition-colors mobile-list-item-title">
              {{ module.title }}
            </h3>
            <p class="text-xs text-muted-foreground truncate mt-1 mobile-list-item-subtitle">
              {{ module.category }} • {{ formattedDuration }}
            </p>
          </div>

          <!-- Compact Stats -->
          <div class="flex items-center gap-2 ml-2">
            <div class="flex items-center gap-1 text-xs text-muted-foreground">
              <Star class="h-3 w-3" />
              <span>{{ formattedRating }}</span>
            </div>

            <Badge
              :class="difficultyColor"
              class="text-xs"
            >
              {{ module.difficulty.charAt(0).toUpperCase() }}
            </Badge>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex-shrink-0 flex gap-1 mobile-list-item-action">
        <Button
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0 touch-target-sm"
          @click="handlePreview"
        >
          <Eye class="h-4 w-4" />
        </Button>
      </div>
    </div>

    <!-- Full Layout -->
    <template v-else>
      <!-- Module Image/Thumbnail -->
      <div class="relative aspect-video w-full overflow-hidden bg-muted">
        <!-- Placeholder gradient based on category -->
        <div
          class="h-full w-full bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center"
        >
          <BookOpen class="h-12 w-12 text-primary/60" />
        </div>

        <!-- Badges Overlay -->
        <div class="absolute top-3 left-3 flex flex-col gap-2">
          <!-- Difficulty Badge -->
          <Badge :class="difficultyColor" class="text-xs font-semibold">
            <Star class="h-3 w-3 mr-1" />
            {{ module.difficulty.charAt(0).toUpperCase() + module.difficulty.slice(1) }}
          </Badge>

          <!-- Assignment Status Badge -->
          <Badge
            v-if="showAssignmentStatus"
            :class="assignmentStatusColor"
            class="text-xs font-semibold"
          >
            {{ assignmentStatusText }}
          </Badge>
        </div>

        <!-- Usage Stats (Admin) -->
        <div
          v-if="showUsageStats && popularityRank"
          class="absolute top-3 right-3"
        >
          <Badge variant="secondary" class="text-xs">
            <TrendingUp class="h-3 w-3 mr-1" />
            #{{ popularityRank }}
          </Badge>
        </div>

        <!-- Hover Actions (Desktop) / Always Visible (Mobile) -->
        <div
          :class="[
            'absolute inset-0 bg-black/50 flex items-center justify-center transition-opacity',
            (isMobile || isTouch) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'
          ]"
        >
          <div class="flex gap-2">
            <Button
              variant="secondary"
              size="sm"
              class="touch-target-md"
              @click="handlePreview"
            >
              <Eye class="h-4 w-4 mr-2" />
              <span class="mobile-only">View</span>
              <span class="desktop-only">Preview</span>
            </Button>

            <Button
              v-if="showAssignmentStatus"
              variant="default"
              size="sm"
              class="touch-target-md"
              @click="handleAssign"
            >
              <Play class="h-4 w-4 mr-2" />
              Assign
            </Button>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-4 mobile-padding">
        <!-- Header -->
        <div class="mb-3">
          <h3 class="font-semibold text-base mb-2 line-clamp-2 group-hover:text-primary transition-colors mobile-text-lg">
            {{ module.title }}
          </h3>

          <p class="text-sm text-muted-foreground line-clamp-2 mb-3 mobile-text-sm">
            {{ module.description }}
          </p>

          <!-- Author -->
          <div class="flex items-center text-xs text-muted-foreground mb-2 mobile-text-xs">
            <User class="h-3 w-3 mr-1" />
            <span>{{ module.author.name }}</span>
          </div>
        </div>

        <!-- Tags -->
        <div v-if="module.tags.length > 0" class="mb-3">
          <div class="flex flex-wrap gap-1">
            <Badge
              v-for="tag in module.tags.slice(0, isMobile ? 2 : 3)"
              :key="tag"
              variant="outline"
              class="text-xs mobile-text-xs"
            >
              <Tag class="h-2.5 w-2.5 mr-1" />
              {{ tag }}
            </Badge>
            <Badge
              v-if="module.tags.length > (isMobile ? 2 : 3)"
              variant="outline"
              class="text-xs mobile-text-xs"
            >
              +{{ module.tags.length - (isMobile ? 2 : 3) }}
            </Badge>
          </div>
        </div>

        <!-- Stats -->
        <div class="flex items-center justify-between text-xs text-muted-foreground mb-3 mobile-text-xs">
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-1">
              <BookOpen class="h-3.5 w-3.5" />
              <span>{{ module.lessons.length }} lessons</span>
            </div>

            <div class="flex items-center gap-1">
              <Clock class="h-3.5 w-3.5" />
              <span>{{ formattedDuration }}</span>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <Star class="h-3.5 w-3.5 fill-current text-yellow-400" />
            <span>{{ formattedRating }}</span>
            <span class="text-muted-foreground/70 desktop-only">({{ module.reviewCount }})</span>
          </div>
        </div>

        <!-- Usage Analytics (Admin) -->
        <div
          v-if="showUsageStats"
          class="border-t pt-3 mt-3"
        >
          <div class="grid grid-cols-2 gap-3 text-xs mobile-text-xs">
            <div class="flex items-center gap-1">
              <Users class="h-3 w-3" />
              <span>{{ module.usageAnalytics.totalAssignments }} assignments</span>
            </div>

            <div class="flex items-center gap-1">
              <CheckCircle class="h-3 w-3" />
              <span>{{ completionRate }}% completion</span>
            </div>
          </div>
        </div>

        <!-- Learning Objectives Preview -->
        <div v-if="module.learningObjectives.length > 0" class="border-t pt-3 mt-3">
          <h4 class="text-xs font-medium text-muted-foreground mb-2 mobile-text-xs">Learning Objectives</h4>
          <ul class="text-xs text-muted-foreground space-y-1 mobile-text-xs">
            <li
              v-for="objective in module.learningObjectives.slice(0, isMobile ? 1 : 2)"
              :key="objective"
              class="flex items-start gap-1"
            >
              <CheckCircle class="h-3 w-3 mt-0.5 flex-shrink-0 text-green-500" />
              <span class="line-clamp-1">{{ objective }}</span>
            </li>
            <li
              v-if="module.learningObjectives.length > (isMobile ? 1 : 2)"
              class="text-muted-foreground/70"
            >
              +{{ module.learningObjectives.length - (isMobile ? 1 : 2) }} more objectives
            </li>
          </ul>
        </div>
      </div>
    </template>

    <!-- Detailed Tooltip (for grid view on desktop only) -->
    <div
      v-if="showTooltip && !isCompactView && !isMobile && !isTouch"
      class="absolute z-50 top-full left-0 mt-2 w-80 p-4 bg-popover border rounded-lg shadow-lg"
    >
      <div class="space-y-3">
        <div>
          <h4 class="font-medium text-sm mb-1">{{ module.title }}</h4>
          <p class="text-xs text-muted-foreground">{{ module.description }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs">
          <div>
            <span class="font-medium">Duration:</span> {{ formattedDuration }}
          </div>
          <div>
            <span class="font-medium">Difficulty:</span> {{ module.difficulty }}
          </div>
          <div>
            <span class="font-medium">Category:</span> {{ module.category }}
          </div>
          <div>
            <span class="font-medium">Rating:</span> {{ formattedRating }}/5
          </div>
        </div>

        <div v-if="module.prerequisites.length > 0">
          <h5 class="font-medium text-xs mb-1">Prerequisites:</h5>
          <ul class="text-xs text-muted-foreground space-y-1">
            <li v-for="prereq in module.prerequisites.slice(0, 3)" :key="prereq">
              • {{ prereq }}
            </li>
          </ul>
        </div>

        <div v-if="showUsageStats" class="border-t pt-2">
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>Assignments: {{ module.usageAnalytics.totalAssignments }}</div>
            <div>Completion: {{ completionRate }}%</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Selection Indicator -->
    <div
      v-if="selected"
      class="absolute top-2 right-2 w-6 h-6 bg-primary rounded-full flex items-center justify-center"
    >
      <CheckCircle class="h-4 w-4 text-primary-foreground" />
    </div>

    <!-- Dragging Indicator -->
    <div
      v-if="isDragging"
      class="absolute inset-0 bg-primary/10 border-2 border-primary border-dashed rounded-lg flex items-center justify-center"
    >
      <div class="text-primary font-medium text-sm mobile-text-sm">
        {{ isMobile || isTouch ? 'Moving...' : 'Dragging...' }}
      </div>
    </div>

    <!-- Touch Feedback Overlay -->
    <div
      v-if="(isMobile || isTouch) && !isCompactView"
      class="touch-feedback"
      :class="{ 'active': isDragging }"
    ></div>
  </div>
</template>

<style scoped>
/* Line clamp utilities */
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Smooth transitions */
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}

/* Drag and drop styles */
.cursor-grab {
  cursor: grab;
}

.cursor-grabbing {
  cursor: grabbing;
}

/* Touch-friendly hover states */
@media (hover: hover) {
  .group:hover .opacity-0 {
    opacity: 1;
  }
}

/* Mobile optimizations */
@media (max-width: 640px) {
  .aspect-video {
    aspect-ratio: 16 / 10; /* Slightly taller on mobile */
  }

  /* Ensure touch targets are large enough */
  .touch-target-sm {
    min-height: 36px;
    min-width: 36px;
  }

  .touch-target-md {
    min-height: 44px;
    min-width: 44px;
  }

  /* Mobile-specific spacing */
  .mobile-padding {
    padding: 0.75rem;
  }

  /* Mobile typography adjustments */
  .mobile-text-xs {
    font-size: 0.75rem;
    line-height: 1.4;
  }

  .mobile-text-sm {
    font-size: 0.875rem;
    line-height: 1.4;
  }

  .mobile-text-lg {
    font-size: 1.125rem;
    line-height: 1.4;
  }

  /* Hide desktop-only elements */
  .desktop-only {
    display: none;
  }

  /* Show mobile-only elements */
  .mobile-only {
    display: inline;
  }

  /* Mobile list item optimizations */
  .mobile-list-item {
    min-height: 60px;
    touch-action: manipulation;
    user-select: none;
    -webkit-user-select: none;
    -webkit-tap-highlight-color: transparent;
  }

  .mobile-list-item:active {
    background-color: hsl(var(--accent) / 0.5);
    transform: scale(0.99);
  }

  /* Mobile card optimizations */
  .mobile-card {
    touch-action: manipulation;
    user-select: none;
    -webkit-user-select: none;
    -webkit-tap-highlight-color: transparent;
  }

  .mobile-card:active {
    transform: scale(0.98);
  }

  /* Drag handle visibility on mobile */
  .drag-handle-mobile {
    opacity: 1 !important;
    padding: 0.5rem;
    touch-action: none;
  }
}

/* Desktop-only styles */
@media (min-width: 641px) {
  .mobile-only {
    display: none;
  }

  .desktop-only {
    display: inline;
  }
}

/* Accessibility improvements */
.group:focus-visible {
  outline: 2px solid hsl(var(--ring));
  outline-offset: 2px;
}

/* Animation for drag state */
@keyframes dragPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.02); }
}

.group.dragging {
  animation: dragPulse 1s ease-in-out infinite;
}

/* Touch feedback animation */
.touch-feedback {
  position: relative;
  overflow: hidden;
}

.touch-feedback::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
  transform: translate(-50%, -50%);
  transition: width 0.3s ease-out, height 0.3s ease-out;
  pointer-events: none;
  z-index: 1;
}

.touch-feedback.active::before {
  width: 200px;
  height: 200px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .group {
    border-width: 2px;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .transition-all,
  .touch-feedback::before {
    transition-duration: 0.01ms !important;
  }

  .group.dragging {
    animation: none;
  }
}

/* Dark mode optimizations */
@media (prefers-color-scheme: dark) {
  .touch-feedback::before {
    background: rgba(255, 255, 255, 0.2);
  }
}

/* Landscape orientation optimizations */
@media (orientation: landscape) and (max-height: 500px) {
  .mobile-padding {
    padding: 0.5rem;
  }

  .mobile-text-lg {
    font-size: 1rem;
  }
}

/* Print optimizations */
@media print {
  .touch-feedback,
  .drag-handle-mobile,
  .mobile-only {
    display: none !important;
  }

  .desktop-only {
    display: inline !important;
  }
}
</style>
