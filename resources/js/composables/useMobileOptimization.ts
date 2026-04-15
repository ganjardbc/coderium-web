/**
 * Mobile Optimization Composable
 *
 * Provides utilities for mobile-responsive design, touch interactions,
 * haptic feedback, and mobile-specific optimizations.
 */

import { ref, computed, onMounted, onUnmounted, type Ref } from 'vue';

export interface TouchGesture {
  startX: number;
  startY: number;
  currentX: number;
  currentY: number;
  deltaX: number;
  deltaY: number;
  distance: number;
  direction: 'up' | 'down' | 'left' | 'right' | 'none';
  duration: number;
  velocity: number;
}

export interface MobileOptimizationComposable {
  // Device detection
  isMobile: Ref<boolean>;
  isTablet: Ref<boolean>;
  isTouch: Ref<boolean>;
  screenSize: Ref<'xs' | 'sm' | 'md' | 'lg' | 'xl'>;
  orientation: Ref<'portrait' | 'landscape'>;

  // Touch interaction state
  touchState: Ref<{
    isActive: boolean;
    startTime: number;
    gesture: TouchGesture | null;
    longPressTimer: ReturnType<typeof setTimeout> | null;
  }>;

  // Methods
  enableHapticFeedback: (pattern?: number | number[]) => void;
  handleTouchStart: (event: TouchEvent, callback?: (gesture: TouchGesture) => void) => void;
  handleTouchMove: (event: TouchEvent, callback?: (gesture: TouchGesture) => void) => void;
  handleTouchEnd: (event: TouchEvent, callback?: (gesture: TouchGesture) => void) => void;
  setupLongPress: (element: HTMLElement, callback: () => void, delay?: number) => () => void;
  optimizeScrolling: (element: HTMLElement) => () => void;
  preventZoom: (element: HTMLElement) => () => void;
  addTouchFriendlyClass: (element: HTMLElement) => void;

  // Computed properties
  touchTargetSize: Ref<number>;
  isLandscape: Ref<boolean>;
  safeAreaInsets: Ref<{ top: number; bottom: number; left: number; right: number }>;
}

// Constants
const TOUCH_TARGET_MIN_SIZE = 44; // Apple's recommended minimum touch target size
const LONG_PRESS_DELAY = 500;
const SWIPE_THRESHOLD = 50;
const VELOCITY_THRESHOLD = 0.3;

export function useMobileOptimization(): MobileOptimizationComposable {
  // Reactive state
  const isMobile = ref(false);
  const isTablet = ref(false);
  const isTouch = ref(false);
  const screenSize = ref<'xs' | 'sm' | 'md' | 'lg' | 'xl'>('md');
  const orientation = ref<'portrait' | 'landscape'>('portrait');

  const touchState = ref<{
    isActive: boolean;
    startTime: number;
    gesture: TouchGesture | null;
    longPressTimer: ReturnType<typeof setTimeout> | null;
  }>({
    isActive: false,
    startTime: 0,
    gesture: null,
    longPressTimer: null
  });

  // Computed properties
  const touchTargetSize = computed(() => {
    return isMobile.value ? TOUCH_TARGET_MIN_SIZE : 32;
  });

  const isLandscape = computed(() => {
    return orientation.value === 'landscape';
  });

  const safeAreaInsets = computed(() => {
    if (typeof window === 'undefined') {
      return { top: 0, bottom: 0, left: 0, right: 0 };
    }

    const style = getComputedStyle(document.documentElement);
    return {
      top: parseInt(style.getPropertyValue('--safe-area-inset-top') || '0'),
      bottom: parseInt(style.getPropertyValue('--safe-area-inset-bottom') || '0'),
      left: parseInt(style.getPropertyValue('--safe-area-inset-left') || '0'),
      right: parseInt(style.getPropertyValue('--safe-area-inset-right') || '0')
    };
  });

  // Device detection
  const detectDevice = () => {
    if (typeof window === 'undefined') return;

    const userAgent = navigator.userAgent.toLowerCase();
    const width = window.innerWidth;
    const height = window.innerHeight;

    // Touch capability detection
    isTouch.value = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    // Mobile detection
    isMobile.value = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent) ||
                     (isTouch.value && Math.max(width, height) < 1024);

    // Tablet detection
    isTablet.value = /ipad|android(?!.*mobile)|tablet/i.test(userAgent) ||
                     (isTouch.value && Math.min(width, height) >= 768 && Math.max(width, height) < 1024);

    // Screen size detection
    if (width < 640) {
      screenSize.value = 'xs';
    } else if (width < 768) {
      screenSize.value = 'sm';
    } else if (width < 1024) {
      screenSize.value = 'md';
    } else if (width < 1280) {
      screenSize.value = 'lg';
    } else {
      screenSize.value = 'xl';
    }

    // Orientation detection
    orientation.value = width > height ? 'landscape' : 'portrait';
  };

  // Haptic feedback
  const enableHapticFeedback = (pattern: number | number[] = 50) => {
    if ('vibrate' in navigator && (isMobile.value || isTouch.value)) {
      try {
        navigator.vibrate(pattern);
      } catch (error) {
        console.warn('Haptic feedback not supported:', error);
      }
    }
  };

  // Touch gesture calculation
  const calculateGesture = (startTouch: Touch, currentTouch: Touch, startTime: number): TouchGesture => {
    const deltaX = currentTouch.clientX - startTouch.clientX;
    const deltaY = currentTouch.clientY - startTouch.clientY;
    const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
    const duration = Date.now() - startTime;
    const velocity = distance / Math.max(duration, 1);

    let direction: 'up' | 'down' | 'left' | 'right' | 'none' = 'none';
    if (distance > SWIPE_THRESHOLD) {
      if (Math.abs(deltaX) > Math.abs(deltaY)) {
        direction = deltaX > 0 ? 'right' : 'left';
      } else {
        direction = deltaY > 0 ? 'down' : 'up';
      }
    }

    return {
      startX: startTouch.clientX,
      startY: startTouch.clientY,
      currentX: currentTouch.clientX,
      currentY: currentTouch.clientY,
      deltaX,
      deltaY,
      distance,
      direction,
      duration,
      velocity
    };
  };

  // Touch event handlers
  const handleTouchStart = (event: TouchEvent, callback?: (gesture: TouchGesture) => void) => {
    if (event.touches.length !== 1) return;

    const touch = event.touches[0];
    const startTime = Date.now();

    touchState.value = {
      isActive: true,
      startTime,
      gesture: {
        startX: touch.clientX,
        startY: touch.clientY,
        currentX: touch.clientX,
        currentY: touch.clientY,
        deltaX: 0,
        deltaY: 0,
        distance: 0,
        direction: 'none',
        duration: 0,
        velocity: 0
      },
      longPressTimer: null
    };

    if (callback) {
      callback(touchState.value.gesture!);
    }
  };

  const handleTouchMove = (event: TouchEvent, callback?: (gesture: TouchGesture) => void) => {
    if (!touchState.value.isActive || event.touches.length !== 1) return;

    const touch = event.touches[0];
    const startTouch = {
      clientX: touchState.value.gesture!.startX,
      clientY: touchState.value.gesture!.startY
    } as Touch;

    touchState.value.gesture = calculateGesture(startTouch, touch, touchState.value.startTime);

    if (callback) {
      callback(touchState.value.gesture);
    }

    // Cancel long press if moved too much
    if (touchState.value.gesture.distance > 10 && touchState.value.longPressTimer) {
      clearTimeout(touchState.value.longPressTimer);
      touchState.value.longPressTimer = null;
    }
  };

  const handleTouchEnd = (event: TouchEvent, callback?: (gesture: TouchGesture) => void) => {
    if (!touchState.value.isActive) return;

    if (touchState.value.longPressTimer) {
      clearTimeout(touchState.value.longPressTimer);
      touchState.value.longPressTimer = null;
    }

    if (callback && touchState.value.gesture) {
      callback(touchState.value.gesture);
    }

    touchState.value = {
      isActive: false,
      startTime: 0,
      gesture: null,
      longPressTimer: null
    };
  };

  // Long press setup
  const setupLongPress = (
    element: HTMLElement,
    callback: () => void,
    delay: number = LONG_PRESS_DELAY
  ): (() => void) => {
    let longPressTimer: ReturnType<typeof setTimeout> | null = null;
    let startPosition: { x: number; y: number } | null = null;

    const handleStart = (event: TouchEvent) => {
      if (event.touches.length !== 1) return;

      const touch = event.touches[0];
      startPosition = { x: touch.clientX, y: touch.clientY };

      longPressTimer = setTimeout(() => {
        enableHapticFeedback(100);
        callback();
        longPressTimer = null;
      }, delay);
    };

    const handleMove = (event: TouchEvent) => {
      if (!longPressTimer || !startPosition || event.touches.length !== 1) return;

      const touch = event.touches[0];
      const distance = Math.sqrt(
        Math.pow(touch.clientX - startPosition.x, 2) +
        Math.pow(touch.clientY - startPosition.y, 2)
      );

      // Cancel long press if moved too much
      if (distance > 10) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
      }
    };

    const handleEnd = () => {
      if (longPressTimer) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
      }
      startPosition = null;
    };

    element.addEventListener('touchstart', handleStart, { passive: false });
    element.addEventListener('touchmove', handleMove, { passive: false });
    element.addEventListener('touchend', handleEnd);
    element.addEventListener('touchcancel', handleEnd);

    // Return cleanup function
    return () => {
      element.removeEventListener('touchstart', handleStart);
      element.removeEventListener('touchmove', handleMove);
      element.removeEventListener('touchend', handleEnd);
      element.removeEventListener('touchcancel', handleEnd);
      if (longPressTimer) {
        clearTimeout(longPressTimer);
      }
    };
  };

  // Scroll optimization
  const optimizeScrolling = (element: HTMLElement): (() => void) => {
    // Enable momentum scrolling on iOS
    (element.style as any).webkitOverflowScrolling = 'touch';
    (element.style as any).overflowScrolling = 'touch';

    // Prevent scroll chaining
    element.style.overscrollBehavior = 'contain';

    // Add smooth scrolling
    element.style.scrollBehavior = 'smooth';

    return () => {
      (element.style as any).webkitOverflowScrolling = '';
      (element.style as any).overflowScrolling = '';
      element.style.overscrollBehavior = '';
      element.style.scrollBehavior = '';
    };
  };

  // Prevent zoom on double tap
  const preventZoom = (element: HTMLElement): (() => void) => {
    element.style.touchAction = 'manipulation';
    element.style.userSelect = 'none';
    (element.style as any).webkitUserSelect = 'none';
    (element.style as any).webkitTouchCallout = 'none';

    return () => {
      element.style.touchAction = '';
      element.style.userSelect = '';
      (element.style as any).webkitUserSelect = '';
      (element.style as any).webkitTouchCallout = '';
    };
  };

  // Add touch-friendly classes
  const addTouchFriendlyClass = (element: HTMLElement) => {
    if (isMobile.value || isTouch.value) {
      element.classList.add('touch-friendly');

      // Ensure minimum touch target size
      const computedStyle = getComputedStyle(element);
      const width = parseInt(computedStyle.width);
      const height = parseInt(computedStyle.height);

      if (width < touchTargetSize.value || height < touchTargetSize.value) {
        element.style.minWidth = `${touchTargetSize.value}px`;
        element.style.minHeight = `${touchTargetSize.value}px`;
      }
    }
  };

  // Event listeners
  const handleResize = () => {
    detectDevice();
  };

  const handleOrientationChange = () => {
    // Delay to ensure dimensions are updated
    setTimeout(detectDevice, 100);
  };

  // Lifecycle
  onMounted(() => {
    detectDevice();

    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleOrientationChange);

    // Add CSS custom properties for safe area insets
    if (typeof window !== 'undefined') {
      const style = document.createElement('style');
      style.textContent = `
        :root {
          --safe-area-inset-top: env(safe-area-inset-top, 0px);
          --safe-area-inset-bottom: env(safe-area-inset-bottom, 0px);
          --safe-area-inset-left: env(safe-area-inset-left, 0px);
          --safe-area-inset-right: env(safe-area-inset-right, 0px);
          --touch-target-size: ${touchTargetSize.value}px;
        }
      `;
      document.head.appendChild(style);
    }
  });

  onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('orientationchange', handleOrientationChange);

    if (touchState.value.longPressTimer) {
      clearTimeout(touchState.value.longPressTimer);
    }
  });

  return {
    // Device detection
    isMobile,
    isTablet,
    isTouch,
    screenSize,
    orientation,

    // Touch interaction state
    touchState,

    // Methods
    enableHapticFeedback,
    handleTouchStart,
    handleTouchMove,
    handleTouchEnd,
    setupLongPress,
    optimizeScrolling,
    preventZoom,
    addTouchFriendlyClass,

    // Computed properties
    touchTargetSize,
    isLandscape,
    safeAreaInsets
  };
}
