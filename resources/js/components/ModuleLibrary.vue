<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Search,
  Filter,
  Grid3X3,
  List,
  Loader2,
  AlertCircle,
  BookOpen,
  Star
} from 'lucide-vue-next';
import { useModuleLibrary } from '@/composables/useModuleLibrary';
import { usePerformanceOptimization } from '@/composables/usePerformanceOptimization';
import { useMobileOptimization } from '@/composables/useMobileOptimization';
import { ModuleFilters } from '@/types/enhanced-classroom';
import ModuleCard from './ModuleCard.vue';
import ModulePreview from './ModulePreview.vue';

interface Props {
  searchQuery?: string;
  categoryFilter?: string[];
  difficultyFilter?: string[];
  showAdminFeatures?: boolean;
  viewMode?: 'grid' | 'list';
  enableVirtualScrolling?: boolean;
}

interface Emits {
  (e: 'moduleSelected', moduleId: string): void;
  (e: 'searchChanged', query: string): void;
  (e: 'filtersChanged', filters: ModuleFilters): void;
}

const props = withDefaults(defineProps<Props>(), {
  searchQuery: '',
  categoryFilter: () => [],
  difficultyFilter: () => [],
  showAdminFeatures: false,
  viewMode: 'grid',
  enableVirtualScrolling: true
});

const emit = defineEmits<Emits>();

// Composables
const {
  filteredModules,
  searchQuery: searchQueryRef,
  filters,
  loading,
  error,
  fetchModules,
  searchModules,
  totalModules,
  categories,
  tags
} = useModuleLibrary();

// Performance optimization composable
const {
  setupVirtualScroll,
  createCache,
  createDebouncedFunction,
  startPerformanceMonitoring,
  stopPerformanceMonitoring,
  getPerformanceMetrics,
  cleanupUnusedData
} = usePerformanceOptimization();

// Mobile optimization composable
const {
  isMobile,
  isTouch,
  screenSize
} = useMobileOptimization();

// Local reactive state
const currentViewMode = ref<'grid' | 'list'>(props.viewMode);
const showFilters = ref(false);
const showPreview = ref(false);
const selectedModuleId = ref<string | null>(null);
const searchInput = ref(props.searchQuery);

// Performance optimization state
const performanceCache = createCache({ maxSize: 200, ttl: 10 * 60 * 1000 }); // 10 minutes
const isPerformanceMonitoringEnabled = ref(false);

// Virtual scrolling state
const containerRef = ref<HTMLElement>();
const itemHeight = ref(currentViewMode.value === 'grid' ? 320 : 120);
const containerHeight = ref(600);
const scrollTop = ref(0);
const visibleRange = ref({ start: 0, end: 20 });

// Enhanced virtual scrolling with performance optimization
const virtualScrollConfig = computed(() => ({
  itemHeight: itemHeight.value,
  containerHeight: containerHeight.value,
  buffer: isMobile.value ? 3 : 5, // Smaller buffer on mobile
  threshold: 100
}));

// Setup virtual scrolling
const {
  visibleItems,
  totalHeight,
  startIndex,
  endIndex,
  updateVisibleRange
} = setupVirtualScroll(containerRef, filteredModules, virtualScrollConfig.value);

// Filter state
const localFilters = ref<ModuleFilters>({
  categories: [...props.categoryFilter],
  difficulties: [...props.difficultyFilter],
  tags: [],
  durationRange: { min: 0, max: 1000 },
  assignmentStatus: 'all',
  usageRange: { min: 0, max: 100 },
  rating: { min: 0, max: 5 }
});

// Computed properties
const itemsPerRow = computed(() => {
  if (currentViewMode.value === 'list') return 1;

  // Responsive grid based on screen size
  switch (screenSize.value) {
    case 'xs': return 1;
    case 'sm': return 2;
    case 'md': return 2;
    case 'lg': return 3;
    case 'xl': return 4;
    default: return 3;
  }
});

const totalRows = computed(() => {
  return Math.ceil(filteredModules.value.length / itemsPerRow.value);
});

const optimizedVisibleItems = computed(() => {
  if (!props.enableVirtualScrolling) {
    return filteredModules.value;
  }

  // Use cached visible items if available
  const cacheKey = `visible-${startIndex.value}-${endIndex.value}-${currentViewMode.value}`;
  const cached = performanceCache.get(cacheKey);
  if (cached) {
    return cached;
  }

  const items = visibleItems.value;
  performanceCache.set(cacheKey, items);
  return items;
});

const virtualScrollerStyle = computed(() => {
  if (!props.enableVirtualScrolling) return {};

  return {
    height: `${totalHeight.value}px`,
    position: 'relative' as const
  };
});

const visibleItemsStyle = computed(() => {
  if (!props.enableVirtualScrolling) return {};

  return {
    transform: `translateY(${startIndex.value * itemHeight.value}px)`,
    position: 'absolute' as const,
    top: '0',
    left: '0',
    right: '0'
  };
});

const hasActiveFilters = computed(() => {
  return (
    localFilters.value.categories.length > 0 ||
    localFilters.value.difficulties.length > 0 ||
    localFilters.value.tags.length > 0 ||
    localFilters.value.assignmentStatus !== 'all' ||
    localFilters.value.durationRange.min > 0 ||
    localFilters.value.durationRange.max < 1000 ||
    localFilters.value.rating.min > 0 ||
    localFilters.value.rating.max < 5
  );
});

const statsData = computed(() => {
  return {
    total: totalModules.value,
    filtered: filteredModules.value.length,
    categories: categories.value.length,
    avgRating: filteredModules.value.length > 0
      ? (filteredModules.value.reduce((sum, m) => sum + m.rating, 0) / filteredModules.value.length).toFixed(1)
      : '0.0'
  };
});

// Virtual scrolling methods
const updateVisibleRange = () => {
  if (!props.enableVirtualScrolling || !containerRef.value) return;

  const container = containerRef.value;
  const scrollTop = container.scrollTop;
  const containerHeight = container.clientHeight;

  const startRow = Math.floor(scrollTop / itemHeight.value);
  const endRow = Math.min(
    startRow + Math.ceil(containerHeight / itemHeight.value) + 2, // Buffer rows
    totalRows.value - 1
  );

  visibleRange.value = {
    start: Math.max(0, startRow - 1), // Buffer before
    end: endRow
  };
};

const onScroll = (event: Event) => {
  const target = event.target as HTMLElement;
  scrollTop.value = target.scrollTop;
  updateVisibleRange();
};

// Enhanced search with caching and debouncing
const debouncedSearch = createDebouncedFunction(async (query: string) => {
  const cacheKey = `search-${query}`;
  const cached = performanceCache.get(cacheKey);

  if (cached) {
    return cached;
  }

  const results = await searchModules(query);
  performanceCache.set(cacheKey, results);
  return results;
}, 300);

const handleSearch = async (query: string) => {
  searchInput.value = query;
  searchQueryRef.value = query;

  try {
    await debouncedSearch(query);
    emit('searchChanged', query);

    // Reset scroll position after search
    if (containerRef.value) {
      containerRef.value.scrollTop = 0;
      updateVisibleRange();
    }
  } catch (error) {
    console.error('Search failed:', error);
  }
};

const clearSearch = () => {
  handleSearch('');
};

const applyFilters = () => {
  const cacheKey = `filters-${JSON.stringify(localFilters.value)}`;
  const cached = performanceCache.get(cacheKey);

  if (!cached) {
    filters.value = { ...localFilters.value };
    performanceCache.set(cacheKey, filters.value);
  } else {
    filters.value = cached;
  }

  emit('filtersChanged', filters.value);
  showFilters.value = false;

  // Reset scroll position after filtering
  if (containerRef.value) {
    containerRef.value.scrollTop = 0;
    updateVisibleRange();
  }
};

const clearFilters = () => {
  localFilters.value = {
    categories: [],
    difficulties: [],
    tags: [],
    durationRange: { min: 0, max: 1000 },
    assignmentStatus: 'all',
    usageRange: { min: 0, max: 100 },
    rating: { min: 0, max: 5 }
  };
  applyFilters();

  // Clear related cache entries
  performanceCache.clear();
};

const toggleCategory = (category: string) => {
  const index = localFilters.value.categories.indexOf(category);
  if (index > -1) {
    localFilters.value.categories.splice(index, 1);
  } else {
    localFilters.value.categories.push(category);
  }
};

const toggleDifficulty = (difficulty: string) => {
  const index = localFilters.value.difficulties.indexOf(difficulty);
  if (index > -1) {
    localFilters.value.difficulties.splice(index, 1);
  } else {
    localFilters.value.difficulties.push(difficulty);
  }
};

const toggleTag = (tag: string) => {
  const index = localFilters.value.tags.indexOf(tag);
  if (index > -1) {
    localFilters.value.tags.splice(index, 1);
  } else {
    localFilters.value.tags.push(tag);
  }
};

// Module interaction methods
const selectModule = (moduleId: string) => {
  selectedModuleId.value = moduleId;
  showPreview.value = true;
  emit('moduleSelected', moduleId);
};

const closePreview = () => {
  showPreview.value = false;
  selectedModuleId.value = null;
};

// View mode methods with performance optimization
const setViewMode = (mode: 'grid' | 'list') => {
  currentViewMode.value = mode;
  itemHeight.value = mode === 'grid' ? (isMobile.value ? 280 : 320) : (isMobile.value ? 100 : 120);

  nextTick(() => {
    updateVisibleRange();
    // Clear cache when view mode changes
    performanceCache.clear();
  });
};

// Performance monitoring methods
const togglePerformanceMonitoring = () => {
  if (isPerformanceMonitoringEnabled.value) {
    stopPerformanceMonitoring();
    isPerformanceMonitoringEnabled.value = false;
  } else {
    startPerformanceMonitoring();
    isPerformanceMonitoringEnabled.value = true;
  }
};

// Memory cleanup
const performCleanup = () => {
  cleanupUnusedData();
  performanceCache.clear();
};

// Lifecycle
onMounted(async () => {
  // Initialize filters from props
  if (props.categoryFilter.length > 0) {
    localFilters.value.categories = [...props.categoryFilter];
  }
  if (props.difficultyFilter.length > 0) {
    localFilters.value.difficulties = [...props.difficultyFilter];
  }

  // Apply initial filters
  filters.value = { ...localFilters.value };

  // Fetch initial data with caching
  const cacheKey = `initial-modules-${JSON.stringify(filters.value)}`;
  const cached = performanceCache.get(cacheKey);

  if (!cached) {
    await fetchModules(filters.value);
    performanceCache.set(cacheKey, filteredModules.value);
  }

  // Setup virtual scrolling
  if (props.enableVirtualScrolling && containerRef.value) {
    containerHeight.value = containerRef.value.clientHeight;
    updateVisibleRange();
  }

  // Handle initial search
  if (props.searchQuery) {
    await handleSearch(props.searchQuery);
  }

  // Start performance monitoring in development
  if (process.env.NODE_ENV === 'development') {
    startPerformanceMonitoring();
    isPerformanceMonitoringEnabled.value = true;
  }
});

onUnmounted(() => {
  // Cleanup performance monitoring
  if (isPerformanceMonitoringEnabled.value) {
    stopPerformanceMonitoring();
  }

  // Cleanup memory
  performCleanup();
});

// Watch for container resize
watch(containerRef, (newContainer) => {
  if (newContainer && props.enableVirtualScrolling) {
    const resizeObserver = new ResizeObserver(() => {
      containerHeight.value = newContainer.clientHeight;
      updateVisibleRange();
    });
    resizeObserver.observe(newContainer);
  }
});

// Watch for view mode changes
watch(currentViewMode, () => {
  nextTick(() => {
    updateVisibleRange();
  });
});

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchInput, (newQuery) => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    handleSearch(newQuery);
  }, 300);
});
</script>

<template>
  <div class="module-library flex flex-col h-full">
    <!-- Header -->
    <div class="flex-shrink-0 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="container mx-auto px-4 py-4">
        <!-- Title and Stats -->
        <div class="flex items-center justify-between mb-4">
          <div>
            <h1 class="text-2xl font-bold">Module Library</h1>
            <p class="text-sm text-muted-foreground mt-1">
              Showing {{ statsData.filtered }} of {{ statsData.total }} modules
              <span v-if="statsData.filtered !== statsData.total">
                (filtered)
              </span>
            </p>
          </div>

          <!-- Admin Stats -->
          <div v-if="showAdminFeatures" class="flex gap-4 text-sm">
            <div class="flex items-center gap-1">
              <BookOpen class="h-4 w-4" />
              <span>{{ statsData.categories }} categories</span>
            </div>
            <div class="flex items-center gap-1">
              <Star class="h-4 w-4" />
              <span>{{ statsData.avgRating }} avg rating</span>
            </div>
          </div>
        </div>

        <!-- Search and Controls -->
        <div class="flex flex-col sm:flex-row gap-4">
          <!-- Search -->
          <div class="relative flex-1">
            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              v-model="searchInput"
              placeholder="Search modules by title, description, tags..."
              class="pl-10 pr-10"
            />
            <Button
              v-if="searchInput"
              variant="ghost"
              size="sm"
              class="absolute right-1 top-1/2 transform -translate-y-1/2 h-6 w-6 p-0"
              @click="clearSearch"
            >
              ×
            </Button>
          </div>

          <!-- Controls -->
          <div class="flex gap-2">
            <!-- Filter Toggle -->
            <Button
              variant="outline"
              size="sm"
              @click="showFilters = !showFilters"
              :class="{ 'bg-primary text-primary-foreground': hasActiveFilters }"
            >
              <Filter class="h-4 w-4 mr-2" />
              Filters
              <Badge v-if="hasActiveFilters" variant="secondary" class="ml-2">
                Active
              </Badge>
            </Button>

            <!-- View Mode -->
            <div class="flex border rounded-md">
              <Button
                variant="ghost"
                size="sm"
                @click="setViewMode('grid')"
                :class="{ 'bg-muted': currentViewMode === 'grid' }"
                class="rounded-r-none"
              >
                <Grid3X3 class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                @click="setViewMode('list')"
                :class="{ 'bg-muted': currentViewMode === 'list' }"
                class="rounded-l-none border-l"
              >
                <List class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        <!-- Filter Panel -->
        <div v-if="showFilters" class="mt-4 p-4 border rounded-lg bg-muted/50">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Categories -->
            <div>
              <label class="text-sm font-medium mb-2 block">Categories</label>
              <div class="flex flex-wrap gap-1">
                <Badge
                  v-for="category in categories"
                  :key="category"
                  variant="outline"
                  class="cursor-pointer"
                  :class="{ 'bg-primary text-primary-foreground': localFilters.categories.includes(category) }"
                  @click="toggleCategory(category)"
                >
                  {{ category }}
                </Badge>
              </div>
            </div>

            <!-- Difficulties -->
            <div>
              <label class="text-sm font-medium mb-2 block">Difficulty</label>
              <div class="flex flex-wrap gap-1">
                <Badge
                  v-for="difficulty in ['beginner', 'intermediate', 'advanced']"
                  :key="difficulty"
                  variant="outline"
                  class="cursor-pointer capitalize"
                  :class="{ 'bg-primary text-primary-foreground': localFilters.difficulties.includes(difficulty) }"
                  @click="toggleDifficulty(difficulty)"
                >
                  {{ difficulty }}
                </Badge>
              </div>
            </div>

            <!-- Assignment Status -->
            <div>
              <label class="text-sm font-medium mb-2 block">Assignment Status</label>
              <select
                v-model="localFilters.assignmentStatus"
                class="w-full p-2 border rounded-md text-sm"
              >
                <option value="all">All Modules</option>
                <option value="assigned">Assigned</option>
                <option value="unassigned">Unassigned</option>
              </select>
            </div>

            <!-- Duration Range -->
            <div>
              <label class="text-sm font-medium mb-2 block">
                Duration: {{ localFilters.durationRange.min }}-{{ localFilters.durationRange.max }} min
              </label>
              <div class="space-y-2">
                <input
                  v-model.number="localFilters.durationRange.min"
                  type="range"
                  min="0"
                  max="1000"
                  step="10"
                  class="w-full"
                />
                <input
                  v-model.number="localFilters.durationRange.max"
                  type="range"
                  min="0"
                  max="1000"
                  step="10"
                  class="w-full"
                />
              </div>
            </div>
          </div>

          <!-- Popular Tags -->
          <div class="mt-4">
            <label class="text-sm font-medium mb-2 block">Popular Tags</label>
            <div class="flex flex-wrap gap-1">
              <Badge
                v-for="tag in tags.slice(0, 20)"
                :key="tag"
                variant="outline"
                class="cursor-pointer text-xs"
                :class="{ 'bg-primary text-primary-foreground': localFilters.tags.includes(tag) }"
                @click="toggleTag(tag)"
              >
                {{ tag }}
              </Badge>
            </div>
          </div>

          <!-- Filter Actions -->
          <div class="flex justify-between mt-4">
            <Button variant="outline" size="sm" @click="clearFilters">
              Clear All
            </Button>
            <Button size="sm" @click="applyFilters">
              Apply Filters
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-hidden">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center h-full">
        <div class="text-center">
          <Loader2 class="h-8 w-8 animate-spin mx-auto mb-4" />
          <p class="text-muted-foreground">Loading modules...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex items-center justify-center h-full">
        <div class="text-center">
          <AlertCircle class="h-8 w-8 text-destructive mx-auto mb-4" />
          <p class="text-destructive mb-2">{{ error }}</p>
          <Button variant="outline" @click="fetchModules(filters)">
            Try Again
          </Button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredModules.length === 0" class="flex items-center justify-center h-full">
        <div class="text-center">
          <BookOpen class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
          <h3 class="text-lg font-medium mb-2">No modules found</h3>
          <p class="text-muted-foreground mb-4">
            {{ searchQuery ? 'Try adjusting your search or filters' : 'No modules available yet' }}
          </p>
          <div class="flex gap-2 justify-center">
            <Button v-if="searchQuery" variant="outline" @click="clearSearch">
              Clear Search
            </Button>
            <Button v-if="hasActiveFilters" variant="outline" @click="clearFilters">
              Clear Filters
            </Button>
          </div>
        </div>
      </div>

      <!-- Module Grid/List -->
      <div
        v-else
        ref="containerRef"
        class="h-full overflow-auto p-4"
        @scroll="onScroll"
      >
        <div :style="virtualScrollerStyle">
          <div
            :style="visibleItemsStyle"
            :class="{
              'grid gap-4': currentViewMode === 'grid',
              'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4': currentViewMode === 'grid',
              'space-y-2': currentViewMode === 'list'
            }"
          >
            <ModuleCard
              v-for="module in optimizedVisibleItems"
              :key="module.id"
              :module="module"
              :show-usage-stats="showAdminFeatures"
              :show-assignment-status="showAdminFeatures"
              :variant="currentViewMode === 'list' ? 'compact' : 'library'"
              @preview="selectModule"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Module Preview Modal -->
    <ModulePreview
      v-if="showPreview && selectedModuleId"
      :module-id="selectedModuleId"
      :show-assignment-options="showAdminFeatures"
      :show-analytics="showAdminFeatures"
      @close="closePreview"
    />
  </div>
</template>

<style scoped>
.module-library {
  height: 100vh;
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

/* Smooth transitions */
.grid > * {
  transition: transform 0.2s ease-in-out;
}

.grid > *:hover {
  transform: translateY(-2px);
}

/* Mobile optimizations */
@media (max-width: 640px) {
  .grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-3.xl\:grid-cols-4 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }
}

@media (min-width: 640px) and (max-width: 1024px) {
  .grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-3.xl\:grid-cols-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1024px) and (max-width: 1280px) {
  .grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-3.xl\:grid-cols-4 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
