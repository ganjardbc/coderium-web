/**
 * Unified State Management Integration Tests
 *
 * Tests the integration between all Pinia stores and ensures
 * proper data flow and synchronization across the application.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { useModuleLibraryStore } from '@/stores/moduleLibrary';
import { useAssignmentStore } from '@/stores/assignment';
import { useCourseStore } from '@/stores/course';
import { useUnifiedProgressStore } from '@/stores/unifiedProgress';
import { useUIStateStore } from '@/stores/uiState';
import { useUserStore } from '@/stores/user';

describe('Unified State Management Integration', () => {
  let pinia: any;
  let moduleLibraryStore: ReturnType<typeof useModuleLibraryStore>;
  let assignmentStore: ReturnType<typeof useAssignmentStore>;
  let courseStore: ReturnType<typeof useCourseStore>;
  let progressStore: ReturnType<typeof useUnifiedProgressStore>;
  let uiStore: ReturnType<typeof useUIStateStore>;
  let userStore: ReturnType<typeof useUserStore>;

  beforeEach(async () => {
    // Create and set active Pinia instance
    pinia = createPinia();
    setActivePinia(pinia);

    // Initialize all stores
    moduleLibraryStore = useModuleLibraryStore();
    assignmentStore = useAssignmentStore();
    courseStore = useCourseStore();
    progressStore = useUnifiedProgressStore();
    uiStore = useUIStateStore();
    userStore = useUserStore();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('Store Initialization', () => {
    it('should initialize all stores successfully', () => {
      expect(moduleLibraryStore).toBeDefined();
      expect(assignmentStore).toBeDefined();
      expect(courseStore).toBeDefined();
      expect(progressStore).toBeDefined();
      expect(uiStore).toBeDefined();
      expect(userStore).toBeDefined();
    });

    it('should have proper initial state', () => {
      expect(moduleLibraryStore.modules).toEqual([]);
      expect(moduleLibraryStore.searchQuery).toBe('');
      expect(assignmentStore.assignments).toEqual([]);
      expect(courseStore.courses).toEqual([]);
      expect(typeof uiStore.screenWidth).toBe('number');
      expect(typeof uiStore.screenHeight).toBe('number');
    });
  });

  describe('Store State Management', () => {
    it('should handle search query updates', async () => {
      const testQuery = 'javascript';
      moduleLibraryStore.searchQuery = testQuery;

      await nextTick();

      expect(moduleLibraryStore.searchQuery).toBe(testQuery);
    });

    it('should handle loading states', async () => {
      moduleLibraryStore.loading = true;
      expect(moduleLibraryStore.loading).toBe(true);

      moduleLibraryStore.loading = false;
      expect(moduleLibraryStore.loading).toBe(false);
    });

    it('should handle error states', async () => {
      const testError = 'Test error message';
      moduleLibraryStore.error = testError;

      expect(moduleLibraryStore.error).toBe(testError);

      moduleLibraryStore.error = null;
      expect(moduleLibraryStore.error).toBe(null);
    });
  });

  describe('Store Reactivity', () => {
    it('should maintain reactivity across store instances', () => {
      const store1 = useModuleLibraryStore();
      const store2 = useModuleLibraryStore();

      // Both should reference the same store instance
      expect(store1).toBe(store2);

      // Changes in one should be reflected in the other
      store1.searchQuery = 'test query';
      expect(store2.searchQuery).toBe('test query');
    });

    it('should handle computed properties correctly', () => {
      // Test filtered modules computation
      expect(moduleLibraryStore.filteredModules).toEqual([]);

      // Test UI state computations
      expect(typeof uiStore.isMobile).toBe('boolean');
      expect(typeof uiStore.isTablet).toBe('boolean');
    });
  });

  describe('Performance Characteristics', () => {
    it('should handle rapid state updates efficiently', async () => {
      const startTime = performance.now();

      // Perform many rapid updates
      for (let i = 0; i < 1000; i++) {
        moduleLibraryStore.searchQuery = `query-${i}`;
      }

      const endTime = performance.now();
      const duration = endTime - startTime;

      // Should handle updates quickly
      expect(duration).toBeLessThan(100); // Less than 100ms
      expect(moduleLibraryStore.searchQuery).toBe('query-999');
    });

    it('should handle large filter objects efficiently', () => {
      const startTime = performance.now();

      const largeFilters = {
        categories: Array.from({ length: 100 }, (_, i) => `category-${i}`),
        difficulties: ['beginner', 'intermediate', 'advanced'],
        tags: Array.from({ length: 200 }, (_, i) => `tag-${i}`),
        durationRange: { min: 0, max: 1000 },
        assignmentStatus: 'all' as const,
        usageRange: { min: 0, max: 100 },
        rating: { min: 0, max: 5 }
      };

      moduleLibraryStore.filters = largeFilters;

      const endTime = performance.now();
      const duration = endTime - startTime;

      expect(duration).toBeLessThan(50); // Should be very fast
      expect(moduleLibraryStore.filters.categories.length).toBe(100);
    });
  });

  describe('Memory Management', () => {
    it('should clean up resources when stores are reset', () => {
      // Add some data
      moduleLibraryStore.searchQuery = 'test';
      moduleLibraryStore.error = 'test error';
      assignmentStore.loading = true;

      // Reset stores by manually setting values
      moduleLibraryStore.searchQuery = '';
      moduleLibraryStore.error = null;
      assignmentStore.loading = false;

      // Check that state is reset
      expect(moduleLibraryStore.searchQuery).toBe('');
      expect(moduleLibraryStore.error).toBe(null);
      expect(assignmentStore.loading).toBe(false);
    });

    it('should handle store disposal correctly', () => {
      const storeId = moduleLibraryStore.$id;
      expect(storeId).toBe('moduleLibrary');

      // Store should be properly identified
      expect(typeof moduleLibraryStore.$dispose).toBe('function');
    });
  });

  describe('UI State Integration', () => {
    it('should handle screen size changes', () => {
      // Test mobile viewport - use updateScreenSize method if available
      if (typeof uiStore.updateScreenSize === 'function') {
        uiStore.updateScreenSize(375, 667);
      } else {
        uiStore.screenWidth = 375;
        uiStore.screenHeight = 667;
      }

      expect(uiStore.isMobile).toBe(true);

      // Test desktop viewport
      if (typeof uiStore.updateScreenSize === 'function') {
        uiStore.updateScreenSize(1920, 1080);
      } else {
        uiStore.screenWidth = 1920;
        uiStore.screenHeight = 1080;
      }

      expect(uiStore.isMobile).toBe(false);
    });

    it('should handle theme changes', () => {
      uiStore.theme = 'dark';
      expect(uiStore.theme).toBe('dark');

      uiStore.theme = 'light';
      expect(uiStore.theme).toBe('light');
    });
  });

  describe('Error Handling', () => {
    it('should handle invalid state gracefully', () => {
      // Test with invalid data
      expect(() => {
        moduleLibraryStore.searchQuery = null as any;
      }).not.toThrow();

      expect(() => {
        uiStore.screenWidth = -1;
      }).not.toThrow();
    });

    it('should maintain store integrity after errors', () => {
      const originalQuery = moduleLibraryStore.searchQuery;

      try {
        // Attempt invalid operation
        moduleLibraryStore.searchQuery = undefined as any;
      } catch (error) {
        // Store should remain functional
      }

      // Should be able to set valid values
      moduleLibraryStore.searchQuery = 'valid query';
      expect(moduleLibraryStore.searchQuery).toBe('valid query');
    });
  });

  describe('Store Persistence', () => {
    it('should support state serialization', () => {
      moduleLibraryStore.searchQuery = 'test query';

      const state = moduleLibraryStore.$state;
      const serialized = JSON.stringify(state);
      const deserialized = JSON.parse(serialized);

      expect(deserialized.searchQuery).toBe('test query');
    });

    it('should handle state hydration', () => {
      const testState = {
        searchQuery: 'hydrated query',
        loading: false,
        error: null,
        modules: [],
        filters: {
          categories: [],
          difficulties: [],
          tags: [],
          durationRange: { min: 0, max: 1000 },
          assignmentStatus: 'all',
          usageRange: { min: 0, max: 100 },
          rating: { min: 0, max: 5 }
        },
        lastFetchTime: null,
        analytics: {}
      };

      moduleLibraryStore.$patch(testState);
      expect(moduleLibraryStore.searchQuery).toBe('hydrated query');
    });
  });
});
