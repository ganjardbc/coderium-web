/**
 * Module Library Store
 *
 * Centralized state management for the standalone module library,
 * including modules, search, filtering, and analytics.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
  StandaloneModule,
  ModuleUsageAnalytics,
  ModuleFilters,
  SearchResult
} from '@/types/enhanced-classroom';
import { useApi } from '@/composables/useApi';
import { handleStoreError, StatePersistence, StoreEventBus } from './index';

export const useModuleLibraryStore = defineStore('moduleLibrary', () => {
  // State
  const modules = ref<StandaloneModule[]>([]);
  const searchQuery = ref<string>('');
  const filters = ref<ModuleFilters>({
    categories: [],
    difficulties: [],
    tags: [],
    durationRange: { min: 0, max: 1000 },
    assignmentStatus: 'all',
    usageRange: { min: 0, max: 100 },
    rating: { min: 0, max: 5 }
  });
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);
  const lastFetchTime = ref<Date | null>(null);
  const analytics = ref<Record<string, ModuleUsageAnalytics>>({});

  // API instance
  const { api } = useApi();
  const eventBus = StoreEventBus.getInstance();

  // Computed properties
  const filteredModules = computed(() => {
    let result = [...modules.value];

    // Apply search filter
    if (searchQuery.value.trim()) {
      const query = searchQuery.value.toLowerCase().trim();
      result = result.filter(module =>
        module.title.toLowerCase().includes(query) ||
        module.description.toLowerCase().includes(query) ||
        module.tags.some(tag => tag.toLowerCase().includes(query)) ||
        module.category.toLowerCase().includes(query)
      );
    }

    // Apply filters
    const currentFilters = filters.value;

    result = result.filter(module => {
      // Category filter
      if (currentFilters.categories.length > 0 &&
          !currentFilters.categories.includes(module.category)) {
        return false;
      }

      // Difficulty filter
      if (currentFilters.difficulties.length > 0 &&
          !currentFilters.difficulties.includes(module.difficulty)) {
        return false;
      }

      // Tags filter
      if (currentFilters.tags.length > 0 &&
          !currentFilters.tags.some(tag => module.tags.includes(tag))) {
        return false;
      }

      // Duration range filter
      if (module.estimatedDuration < currentFilters.durationRange.min ||
          module.estimatedDuration > currentFilters.durationRange.max) {
        return false;
      }

      // Assignment status filter
      if (currentFilters.assignmentStatus === 'assigned' && module.assignmentCount === 0) {
        return false;
      }
      if (currentFilters.assignmentStatus === 'unassigned' && module.assignmentCount > 0) {
        return false;
      }

      // Usage range filter
      if (module.usageAnalytics.totalAssignments < currentFilters.usageRange.min ||
          module.usageAnalytics.totalAssignments > currentFilters.usageRange.max) {
        return false;
      }

      // Rating filter
      if (module.rating < currentFilters.rating.min ||
          module.rating > currentFilters.rating.max) {
        return false;
      }

      return true;
    });

    return result;
  });

  const modulesByCategory = computed(() => {
    const grouped: Record<string, StandaloneModule[]> = {};

    filteredModules.value.forEach(module => {
      if (!grouped[module.category]) {
        grouped[module.category] = [];
      }
      grouped[module.category].push(module);
    });

    // Sort modules within each category by popularity
    Object.keys(grouped).forEach(category => {
      grouped[category].sort((a, b) =>
        b.usageAnalytics.popularityRank - a.usageAnalytics.popularityRank
      );
    });

    return grouped;
  });

  const popularModules = computed(() => {
    return [...filteredModules.value]
      .sort((a, b) => b.usageAnalytics.popularityRank - a.usageAnalytics.popularityRank)
      .slice(0, 10);
  });

  const recentModules = computed(() => {
    return [...filteredModules.value]
      .sort((a, b) => new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime())
      .slice(0, 10);
  });

  const categories = computed(() => {
    const categorySet = new Set(modules.value.map(module => module.category));
    return Array.from(categorySet).sort();
  });

  const tags = computed(() => {
    const tagSet = new Set(modules.value.flatMap(module => module.tags));
    return Array.from(tagSet).sort();
  });

  const totalModules = computed(() => modules.value.length);

  // Actions
  const fetchModules = async (forceRefresh = false): Promise<void> => {
    if (loading.value) return;

    try {
      loading.value = true;
      error.value = null;

      // Check if we need to fetch (cache for 5 minutes)
      if (!forceRefresh && lastFetchTime.value) {
        const timeSinceLastFetch = Date.now() - lastFetchTime.value.getTime();
        if (timeSinceLastFetch < 5 * 60 * 1000) { // 5 minutes
          return;
        }
      }

      const response = await api.get('/api/modules');
      modules.value = response.data || [];
      lastFetchTime.value = new Date();

      // Emit event for other stores
      eventBus.emit('modules:updated', modules.value);

      // Persist to localStorage
      StatePersistence.saveState('moduleLibrary', {
        modules: modules.value,
        lastFetchTime: lastFetchTime.value
      });

    } catch (err) {
      error.value = 'Failed to fetch modules';
      handleStoreError(err, 'fetchModules');
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const searchModules = async (query: string): Promise<SearchResult[]> => {
    try {
      searchQuery.value = query;

      if (!query.trim()) {
        return [];
      }

      const response = await api.get(`/api/modules/search?q=${encodeURIComponent(query)}`);
      return response.data || [];

    } catch (err) {
      handleStoreError(err, 'searchModules');
      return [];
    }
  };

  const getModuleAnalytics = async (moduleId: string): Promise<ModuleUsageAnalytics | null> => {
    try {
      // Check cache first
      if (analytics.value[moduleId]) {
        return analytics.value[moduleId];
      }

      const response = await api.get(`/api/modules/${moduleId}/analytics`);
      const moduleAnalytics = response.data;

      // Cache the analytics
      analytics.value[moduleId] = moduleAnalytics;

      return moduleAnalytics;

    } catch (err) {
      handleStoreError(err, 'getModuleAnalytics');
      return null;
    }
  };

  const updateFilters = (newFilters: Partial<ModuleFilters>): void => {
    filters.value = { ...filters.value, ...newFilters };

    // Persist filters
    StatePersistence.saveState('moduleLibraryFilters', filters.value);
  };

  const clearFilters = (): void => {
    filters.value = {
      categories: [],
      difficulties: [],
      tags: [],
      durationRange: { min: 0, max: 1000 },
      assignmentStatus: 'all',
      usageRange: { min: 0, max: 100 },
      rating: { min: 0, max: 5 }
    };
    searchQuery.value = '';

    StatePersistence.clearState('moduleLibraryFilters');
  };

  const getModuleById = (moduleId: string): StandaloneModule | null => {
    return modules.value.find(module => module.id === moduleId) || null;
  };

  const refreshModule = async (moduleId: string): Promise<void> => {
    try {
      const response = await api.get(`/api/modules/${moduleId}`);
      const updatedModule = response.data;

      const index = modules.value.findIndex(m => m.id === moduleId);
      if (index !== -1) {
        modules.value[index] = updatedModule;
      }

      // Clear cached analytics for this module
      delete analytics.value[moduleId];

      eventBus.emit('module:updated', updatedModule);

    } catch (err) {
      handleStoreError(err, 'refreshModule');
      throw err;
    }
  };

  const initialize = async (): Promise<void> => {
    try {
      // Load persisted state
      const persistedState = StatePersistence.loadState<any>('moduleLibrary');
      if (persistedState) {
        modules.value = persistedState.modules || [];
        lastFetchTime.value = persistedState.lastFetchTime ? new Date(persistedState.lastFetchTime) : null;
      }

      // Load persisted filters
      const persistedFilters = StatePersistence.loadState<ModuleFilters>('moduleLibraryFilters');
      if (persistedFilters) {
        filters.value = persistedFilters;
      }

      // Fetch fresh data if needed
      await fetchModules();

    } catch (err) {
      handleStoreError(err, 'initialize');
    }
  };

  // Event listeners for cross-store communication
  eventBus.on('assignment:created', (assignment: any) => {
    // Update module assignment count
    const module = getModuleById(assignment.moduleId);
    if (module) {
      module.assignmentCount += 1;
    }
  });

  eventBus.on('assignment:deleted', (assignment: any) => {
    // Update module assignment count
    const module = getModuleById(assignment.moduleId);
    if (module && module.assignmentCount > 0) {
      module.assignmentCount -= 1;
    }
  });

  return {
    // State
    modules,
    searchQuery,
    filters,
    loading,
    error,
    lastFetchTime,
    analytics,

    // Computed
    filteredModules,
    modulesByCategory,
    popularModules,
    recentModules,
    categories,
    tags,
    totalModules,

    // Actions
    fetchModules,
    searchModules,
    getModuleAnalytics,
    updateFilters,
    clearFilters,
    getModuleById,
    refreshModule,
    initialize
  };
});
