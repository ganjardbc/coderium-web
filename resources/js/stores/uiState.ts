/**
 * UI State Store
 *
 * Centralized state management for UI-related state including
 * mobile responsiveness, navigation, modals, and user preferences.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
  UserPreferences,
  NotificationSettings,
  AccessibilitySettings
} from '@/types/enhanced-classroom';
import { handleStoreError, StatePersistence, StoreEventBus } from './index';

export const useUIStateStore = defineStore('uiState', () => {
  // State
  const isMobile = ref<boolean>(false);
  const isTablet = ref<boolean>(false);
  const screenWidth = ref<number>(0);
  const screenHeight = ref<number>(0);
  const sidebarOpen = ref<boolean>(false);
  const mobileMenuOpen = ref<boolean>(false);
  const currentView = ref<'grid' | 'list'>('grid');
  const activeModals = ref<string[]>([]);
  const notifications = ref<any[]>([]);
  const loading = ref<Record<string, boolean>>({});
  const errors = ref<Record<string, string>>({});

  // User preferences
  const preferences = ref<UserPreferences>({
    defaultView: 'grid',
    learningPathTypes: ['track', 'course'],
    difficultyPreference: [],
    categoryPreferences: [],
    notificationSettings: {
      email: true,
      push: true,
      inApp: true,
      achievements: true,
      progress: true,
      assignments: true
    },
    accessibilitySettings: {
      highContrast: false,
      largeText: false,
      reducedMotion: false,
      screenReader: false
    }
  });

  // Navigation state
  const navigationHistory = ref<string[]>([]);
  const currentRoute = ref<string>('');
  const breadcrumbs = ref<any[]>([]);

  // Performance monitoring
  const performanceMetrics = ref<Record<string, any>>({});
  const loadTimes = ref<Record<string, number>>({});

  // Event bus
  const eventBus = StoreEventBus.getInstance();

  // Computed properties
  const deviceType = computed(() => {
    if (isMobile.value) return 'mobile';
    if (isTablet.value) return 'tablet';
    return 'desktop';
  });

  const isSmallScreen = computed(() => screenWidth.value < 768);
  const isMediumScreen = computed(() => screenWidth.value >= 768 && screenWidth.value < 1024);
  const isLargeScreen = computed(() => screenWidth.value >= 1024);

  const hasActiveModals = computed(() => activeModals.value.length > 0);

  const unreadNotifications = computed(() => {
    return notifications.value.filter(n => !n.read).length;
  });

  const isLoading = computed(() => {
    return (key?: string) => {
      if (key) return loading.value[key] || false;
      return Object.values(loading.value).some(Boolean);
    };
  });

  const hasErrors = computed(() => {
    return Object.keys(errors.value).length > 0;
  });

  // Actions
  const updateScreenSize = (width: number, height: number): void => {
    screenWidth.value = width;
    screenHeight.value = height;
    isMobile.value = width < 768;
    isTablet.value = width >= 768 && width < 1024;

    // Automatically close mobile menu on desktop
    if (!isMobile.value) {
      mobileMenuOpen.value = false;
    }

    eventBus.emit('screen:resize', { width, height, deviceType: deviceType.value });
  };

  const toggleSidebar = (): void => {
    sidebarOpen.value = !sidebarOpen.value;

    // Persist sidebar state
    StatePersistence.saveState('sidebarOpen', sidebarOpen.value);
  };

  const toggleMobileMenu = (): void => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
  };

  const setCurrentView = (view: 'grid' | 'list'): void => {
    currentView.value = view;
    preferences.value.defaultView = view;

    // Persist view preference
    StatePersistence.saveState('viewPreference', view);

    eventBus.emit('view:changed', view);
  };

  const openModal = (modalId: string): void => {
    if (!activeModals.value.includes(modalId)) {
      activeModals.value.push(modalId);
    }

    eventBus.emit('modal:opened', modalId);
  };

  const closeModal = (modalId: string): void => {
    activeModals.value = activeModals.value.filter(id => id !== modalId);

    eventBus.emit('modal:closed', modalId);
  };

  const closeAllModals = (): void => {
    const closedModals = [...activeModals.value];
    activeModals.value = [];

    closedModals.forEach(modalId => {
      eventBus.emit('modal:closed', modalId);
    });
  };

  const addNotification = (notification: any): void => {
    const newNotification = {
      id: `notification_${Date.now()}`,
      timestamp: new Date(),
      read: false,
      ...notification
    };

    notifications.value.unshift(newNotification);

    // Limit to 50 notifications
    if (notifications.value.length > 50) {
      notifications.value = notifications.value.slice(0, 50);
    }

    eventBus.emit('notification:added', newNotification);
  };

  const markNotificationAsRead = (notificationId: string): void => {
    const notification = notifications.value.find(n => n.id === notificationId);
    if (notification) {
      notification.read = true;
      eventBus.emit('notification:read', notification);
    }
  };

  const markAllNotificationsAsRead = (): void => {
    notifications.value.forEach(notification => {
      notification.read = true;
    });

    eventBus.emit('notifications:all_read');
  };

  const removeNotification = (notificationId: string): void => {
    notifications.value = notifications.value.filter(n => n.id !== notificationId);

    eventBus.emit('notification:removed', notificationId);
  };

  const setLoading = (key: string, isLoading: boolean): void => {
    if (isLoading) {
      loading.value[key] = true;
    } else {
      delete loading.value[key];
    }

    eventBus.emit('loading:changed', { key, isLoading });
  };

  const setError = (key: string, error: string | null): void => {
    if (error) {
      errors.value[key] = error;
    } else {
      delete errors.value[key];
    }

    eventBus.emit('error:changed', { key, error });
  };

  const clearAllErrors = (): void => {
    errors.value = {};
    eventBus.emit('errors:cleared');
  };

  const updatePreferences = (newPreferences: Partial<UserPreferences>): void => {
    preferences.value = { ...preferences.value, ...newPreferences };

    // Persist preferences
    StatePersistence.saveState('userPreferences', preferences.value);

    eventBus.emit('preferences:updated', preferences.value);
  };

  const updateNavigationHistory = (route: string): void => {
    currentRoute.value = route;

    // Add to history if it's different from the last route
    if (navigationHistory.value[navigationHistory.value.length - 1] !== route) {
      navigationHistory.value.push(route);

      // Limit history to 20 entries
      if (navigationHistory.value.length > 20) {
        navigationHistory.value = navigationHistory.value.slice(-20);
      }
    }
  };

  const updateBreadcrumbs = (crumbs: any[]): void => {
    breadcrumbs.value = crumbs;
  };

  const recordPerformanceMetric = (key: string, value: any): void => {
    performanceMetrics.value[key] = {
      value,
      timestamp: Date.now()
    };
  };

  const recordLoadTime = (key: string, startTime: number): void => {
    const loadTime = Date.now() - startTime;
    loadTimes.value[key] = loadTime;

    // Emit performance event for monitoring
    eventBus.emit('performance:load_time', { key, loadTime });
  };

  // Touch and gesture support
  const handleTouchStart = (event: TouchEvent): void => {
    eventBus.emit('touch:start', event);
  };

  const handleTouchMove = (event: TouchEvent): void => {
    eventBus.emit('touch:move', event);
  };

  const handleTouchEnd = (event: TouchEvent): void => {
    eventBus.emit('touch:end', event);
  };

  // Keyboard navigation support
  const handleKeyboardNavigation = (event: KeyboardEvent): void => {
    // Handle common keyboard shortcuts
    if (event.ctrlKey || event.metaKey) {
      switch (event.key) {
        case 'k':
          event.preventDefault();
          eventBus.emit('keyboard:search');
          break;
        case '/':
          event.preventDefault();
          eventBus.emit('keyboard:search');
          break;
        case 'b':
          event.preventDefault();
          toggleSidebar();
          break;
      }
    }

    // Handle escape key
    if (event.key === 'Escape') {
      if (hasActiveModals.value) {
        closeAllModals();
      } else if (mobileMenuOpen.value) {
        toggleMobileMenu();
      }
    }
  };

  const initialize = async (): Promise<void> => {
    try {
      // Load persisted preferences
      const persistedPreferences = StatePersistence.loadState<UserPreferences>('userPreferences');
      if (persistedPreferences) {
        preferences.value = persistedPreferences;
      }

      // Load persisted UI state
      const persistedSidebarState = StatePersistence.loadState<boolean>('sidebarOpen');
      if (persistedSidebarState !== null) {
        sidebarOpen.value = persistedSidebarState;
      }

      const persistedViewPreference = StatePersistence.loadState<'grid' | 'list'>('viewPreference');
      if (persistedViewPreference) {
        currentView.value = persistedViewPreference;
      }

      // Set up screen size monitoring
      if (typeof window !== 'undefined') {
        updateScreenSize(window.innerWidth, window.innerHeight);

        window.addEventListener('resize', () => {
          updateScreenSize(window.innerWidth, window.innerHeight);
        });

        // Set up keyboard navigation
        document.addEventListener('keydown', handleKeyboardNavigation);
      }

    } catch (err) {
      handleStoreError(err, 'initialize');
    }
  };

  // Cleanup function
  const cleanup = (): void => {
    if (typeof window !== 'undefined') {
      document.removeEventListener('keydown', handleKeyboardNavigation);
    }
  };

  return {
    // State
    isMobile,
    isTablet,
    screenWidth,
    screenHeight,
    sidebarOpen,
    mobileMenuOpen,
    currentView,
    activeModals,
    notifications,
    loading,
    errors,
    preferences,
    navigationHistory,
    currentRoute,
    breadcrumbs,
    performanceMetrics,
    loadTimes,

    // Computed
    deviceType,
    isSmallScreen,
    isMediumScreen,
    isLargeScreen,
    hasActiveModals,
    unreadNotifications,
    isLoading,
    hasErrors,

    // Actions
    updateScreenSize,
    toggleSidebar,
    toggleMobileMenu,
    setCurrentView,
    openModal,
    closeModal,
    closeAllModals,
    addNotification,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    removeNotification,
    setLoading,
    setError,
    clearAllErrors,
    updatePreferences,
    updateNavigationHistory,
    updateBreadcrumbs,
    recordPerformanceMetric,
    recordLoadTime,
    handleTouchStart,
    handleTouchMove,
    handleTouchEnd,
    handleKeyboardNavigation,
    initialize,
    cleanup
  };
});
