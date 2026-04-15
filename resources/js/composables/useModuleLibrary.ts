/**
 * Module Library Composable
 *
 * Provides reactive data management and methods for the standalone module library,
 * including search, filtering, analytics, and caching capabilities.
 */

import { ref, computed, watch, type Ref, type ComputedRef } from 'vue';
import { debounce } from 'lodash-es';
import {
  StandaloneModule,
  ModuleUsageAnalytics,
  ModuleFilters
} from '@/types/enhanced-classroom';
import { useApi } from './useApi';
import { globalLoading } from './useLoading';
import { globalErrorHandler } from './useErrorHandler';

export interface ModuleLibraryComposable {
  // Reactive Data
  modules: Ref<StandaloneModule[]>;
  filteredModules: Ref<StandaloneModule[]>;
  searchQuery: Ref<string>;
  filters: Ref<ModuleFilters>;
  loading: Ref<boolean>;
  error: Ref<string | null>;

  // Methods
  fetchModules: (filters?: ModuleFilters) => Promise<StandaloneModule[]>;
  searchModules: (query: string) => Promise<StandaloneModule[]>;
  getModuleAnalytics: (moduleId: string) => Promise<ModuleUsageAnalytics>;
  refreshModuleLibrary: () => Promise<void>;
  clearCache: () => void;

  // Computed Properties
  modulesByCategory: ComputedRef<Record<string, StandaloneModule[]>>;
  popularModules: ComputedRef<StandaloneModule[]>;
  recentModules: ComputedRef<StandaloneModule[]>;
  totalModules: ComputedRef<number>;
  categories: ComputedRef<string[]>;
  tags: ComputedRef<string[]>;
}

// Cache configuration
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes
const SEARCH_DEBOUNCE_DELAY = 300; // 300ms

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  expiresAt: number;
}

class ModuleCache {
  private cache = new Map<string, CacheEntry<any>>();

  set<T>(key: string, data: T, duration: number = CACHE_DURATION): void {
    const timestamp = Date.now();
    this.cache.set(key, {
      data,
      timestamp,
      expiresAt: timestamp + duration
    });
  }

  get<T>(key: string): T | null {
    const entry = this.cache.get(key);
    if (!entry) return null;

    if (Date.now() > entry.expiresAt) {
      this.cache.delete(key);
      return null;
    }

    return entry.data;
  }

  has(key: string): boolean {
    const entry = this.cache.get(key);
    if (!entry) return false;

    if (Date.now() > entry.expiresAt) {
      this.cache.delete(key);
      return false;
    }

    return true;
  }

  clear(): void {
    this.cache.clear();
  }

  invalidatePattern(pattern: string): void {
    const regex = new RegExp(pattern);
    for (const key of this.cache.keys()) {
      if (regex.test(key)) {
        this.cache.delete(key);
      }
    }
  }
}

// Global cache instance
const moduleCache = new ModuleCache();

export function useModuleLibrary(): ModuleLibraryComposable {
  // Reactive state
  const modules = ref<StandaloneModule[]>([]);
  const filteredModules = ref<StandaloneModule[]>([]);
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

  // API and utilities
  const { api } = useApi();
  const { setLoading } = globalLoading;
  const { handleError } = globalErrorHandler;

  // Computed properties
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

  const totalModules = computed(() => modules.value.length);

  const categories = computed(() => {
    const categorySet = new Set(modules.value.map(module => module.category));
    return Array.from(categorySet).sort();
  });

  const tags = computed(() => {
    const tagSet = new Set(modules.value.flatMap(module => module.tags));
    return Array.from(tagSet).sort();
  });

  // Helper function to apply filters
  const applyFilters = (moduleList: StandaloneModule[], currentFilters: ModuleFilters): StandaloneModule[] => {
    return moduleList.filter(module => {
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
  };

  // Helper function to apply search
  const applySearch = (moduleList: StandaloneModule[], query: string): StandaloneModule[] => {
    if (!query.trim()) return moduleList;

    const searchTerm = query.toLowerCase().trim();

    return moduleList.filter(module => {
      return (
        module.title.toLowerCase().includes(searchTerm) ||
        module.description.toLowerCase().includes(searchTerm) ||
        module.tags.some(tag => tag.toLowerCase().includes(searchTerm)) ||
        module.category.toLowerCase().includes(searchTerm) ||
        module.learningObjectives.some(obj => obj.toLowerCase().includes(searchTerm))
      );
    });
  };

  // Update filtered modules when modules, search, or filters change
  const updateFilteredModules = () => {
    let result = [...modules.value];

    // Apply search first
    if (searchQuery.value.trim()) {
      result = applySearch(result, searchQuery.value);
    }

    // Then apply filters
    result = applyFilters(result, filters.value);

    filteredModules.value = result;
  };

  // Watch for changes and update filtered modules
  watch([modules, searchQuery, filters], updateFilteredModules, { deep: true });

  // Debounced search function
  const debouncedSearch = debounce(async (query: string) => {
    if (!query.trim()) {
      updateFilteredModules();
      return;
    }

    try {
      const cacheKey = `search:${query}`;

      // Check cache first
      const cachedResults = moduleCache.get<StandaloneModule[]>(cacheKey);
      if (cachedResults) {
        const filtered = applyFilters(cachedResults, filters.value);
        filteredModules.value = filtered;
        return;
      }

      // Perform API search
      const results = await api.get(`/api/modules/search?q=${encodeURIComponent(query)}`);

      // Cache the results
      moduleCache.set(cacheKey, results.data || [], CACHE_DURATION);

      // Apply current filters to search results
      const filtered = applyFilters(results.data || [], filters.value);
      filteredModules.value = filtered;

    } catch (err) {
      handleError(err, 'Module Search');
      error.value = 'Failed to search modules';
    }
  }, SEARCH_DEBOUNCE_DELAY);

  // Main methods
  const fetchModules = async (filterOptions?: ModuleFilters): Promise<StandaloneModule[]> => {
    const loadingKey = 'fetchModules';

    try {
      setLoading(loadingKey, true);
      loading.value = true;
      error.value = null;

      // Create cache key based on filters
      const cacheKey = `modules:${JSON.stringify(filterOptions || {})}`;

      // Check cache first
      const cachedModules = moduleCache.get<StandaloneModule[]>(cacheKey);
      if (cachedModules) {
        modules.value = cachedModules;
        return cachedModules;
      }

      // Build query parameters
      const params = new URLSearchParams();
      if (filterOptions) {
        if (filterOptions.categories.length > 0) {
          params.append('categories', filterOptions.categories.join(','));
        }
        if (filterOptions.difficulties.length > 0) {
          params.append('difficulties', filterOptions.difficulties.join(','));
        }
        if (filterOptions.tags.length > 0) {
          params.append('tags', filterOptions.tags.join(','));
        }
        if (filterOptions.assignmentStatus !== 'all') {
          params.append('assignment_status', filterOptions.assignmentStatus);
        }
      }

      const queryString = params.toString();
      const url = `/api/modules${queryString ? `?${queryString}` : ''}`;

      const response = await api.get(url);
      const fetchedModules = response.data || [];

      // Cache the results
      moduleCache.set(cacheKey, fetchedModules, CACHE_DURATION);

      modules.value = fetchedModules;
      return fetchedModules;

    } catch (err) {
      handleError(err, 'Fetch Modules');
      error.value = 'Failed to fetch modules';
      throw err;
    } finally {
      setLoading(loadingKey, false);
      loading.value = false;
    }
  };

  const searchModules = async (query: string): Promise<StandaloneModule[]> => {
    searchQuery.value = query;
    await debouncedSearch(query);
    return filteredModules.value;
  };

  const getModuleAnalytics = async (moduleId: string): Promise<ModuleUsageAnalytics> => {
    const loadingKey = `moduleAnalytics:${moduleId}`;

    try {
      setLoading(loadingKey, true);

      // Check cache first
      const cacheKey = `analytics:${moduleId}`;
      const cachedAnalytics = moduleCache.get<ModuleUsageAnalytics>(cacheKey);
      if (cachedAnalytics) {
        return cachedAnalytics;
      }

      const response = await api.get(`/api/modules/${moduleId}/analytics`);
      const analytics = response.data;

      // Cache the analytics with shorter duration (2 minutes)
      moduleCache.set(cacheKey, analytics, 2 * 60 * 1000);

      return analytics;

    } catch (err) {
      handleError(err, 'Module Analytics');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const refreshModuleLibrary = async (): Promise<void> => {
    // Clear relevant cache entries
    moduleCache.invalidatePattern('^modules:');
    moduleCache.invalidatePattern('^search:');

    // Fetch fresh data
    await fetchModules(filters.value);
  };

  const clearCache = (): void => {
    moduleCache.clear();
  };

  // Retry mechanism for failed requests
  const retryRequest = async <T>(
    requestFn: () => Promise<T>,
    maxRetries: number = 3,
    delay: number = 1000
  ): Promise<T> => {
    let lastError: Error;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        return await requestFn();
      } catch (err) {
        lastError = err as Error;

        if (attempt === maxRetries) {
          throw lastError;
        }

        // Exponential backoff
        await new Promise(resolve => setTimeout(resolve, delay * Math.pow(2, attempt - 1)));
      }
    }

    throw lastError!;
  };

  // Enhanced fetch with retry and caching
  const fetchModulesWithRetry = async (filterOptions?: ModuleFilters): Promise<StandaloneModule[]> => {
    return retryRequest(() => fetchModules(filterOptions));
  };

  // Preload popular modules for better performance
  const preloadPopularModules = async (): Promise<void> => {
    try {
      const popularFilters: ModuleFilters = {
        categories: [],
        difficulties: [],
        tags: [],
        durationRange: { min: 0, max: 1000 },
        assignmentStatus: 'all',
        usageRange: { min: 50, max: 100 }, // High usage modules
        rating: { min: 4, max: 5 } // High rated modules
      };

      await fetchModules(popularFilters);
    } catch (error) {
      console.warn('Failed to preload popular modules:', error);
    }
  };

  // Batch operations for better performance
  const batchGetModuleAnalytics = async (moduleIds: string[]): Promise<Record<string, ModuleUsageAnalytics>> => {
    const results: Record<string, ModuleUsageAnalytics> = {};
    const batchSize = 10;

    for (let i = 0; i < moduleIds.length; i += batchSize) {
      const batch = moduleIds.slice(i, i + batchSize);
      const promises = batch.map(id => getModuleAnalytics(id).catch(() => null));
      const batchResults = await Promise.all(promises);

      batch.forEach((id, index) => {
        if (batchResults[index]) {
          results[id] = batchResults[index]!;
        }
      });
    }

    return results;
  };

  return {
    // Reactive data
    modules,
    filteredModules,
    searchQuery,
    filters,
    loading,
    error,

    // Methods
    fetchModules: fetchModulesWithRetry,
    searchModules,
    getModuleAnalytics,
    refreshModuleLibrary,
    clearCache,
    batchGetModuleAnalytics,

    // Computed properties
    modulesByCategory,
    popularModules,
    recentModules,
    totalModules,
    categories,
    tags
  };
}

// Global instance for shared state across components
let globalModuleLibrary: ModuleLibraryComposable | null = null;

export function useGlobalModuleLibrary(): ModuleLibraryComposable {
  if (!globalModuleLibrary) {
    globalModuleLibrary = useModuleLibrary();
  }
  return globalModuleLibrary;
}
