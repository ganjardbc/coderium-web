/**
 * Performance Optimization Composable
 *
 * Provides utilities for performance optimization including virtual scrolling,
 * intelligent caching, lazy loading, and performance monitoring.
 */

import { ref, computed, onMounted, onUnmounted, nextTick, type Ref } from 'vue';
import { debounce, throttle } from 'lodash-es';

export interface VirtualScrollOptions {
  itemHeight: number;
  containerHeight: number;
  buffer?: number;
  threshold?: number;
}

export interface CacheOptions {
  maxSize?: number;
  ttl?: number; // Time to live in milliseconds
  strategy?: 'lru' | 'fifo' | 'lfu';
}

export interface LazyLoadOptions {
  threshold?: number;
  rootMargin?: string;
  triggerOnce?: boolean;
}

export interface PerformanceMetrics {
  renderTime: number;
  scrollPerformance: number;
  cacheHitRate: number;
  memoryUsage: number;
  componentCount: number;
}

export interface PerformanceOptimizationComposable {
  // Virtual scrolling
  setupVirtualScroll: (
    container: Ref<HTMLElement | undefined>,
    items: Ref<any[]>,
    options: VirtualScrollOptions
  ) => {
    visibleItems: Ref<any[]>;
    scrollTop: Ref<number>;
    totalHeight: Ref<number>;
    startIndex: Ref<number>;
    endIndex: Ref<number>;
    updateVisibleRange: () => void;
  };

  // Intelligent caching
  createCache: <T>(options?: CacheOptions) => {
    get: (key: string) => T | null;
    set: (key: string, value: T) => void;
    has: (key: string) => boolean;
    delete: (key: string) => boolean;
    clear: () => void;
    size: () => number;
    stats: () => { hits: number; misses: number; hitRate: number };
  };

  // Lazy loading
  setupLazyLoading: (
    elements: Ref<HTMLElement[]>,
    callback: (element: HTMLElement, index: number) => void,
    options?: LazyLoadOptions
  ) => () => void;

  // Performance monitoring
  startPerformanceMonitoring: () => void;
  stopPerformanceMonitoring: () => void;
  getPerformanceMetrics: () => PerformanceMetrics;

  // Optimistic updates
  createOptimisticUpdate: <T>(
    updateFn: (data: T) => Promise<T>,
    rollbackFn: (data: T) => T
  ) => (data: T) => Promise<T>;

  // Debounced and throttled functions
  createDebouncedFunction: <T extends (...args: any[]) => any>(
    fn: T,
    delay: number
  ) => T;
  createThrottledFunction: <T extends (...args: any[]) => any>(
    fn: T,
    delay: number
  ) => T;

  // Code splitting utilities
  loadComponent: (importFn: () => Promise<any>) => Promise<any>;
  preloadComponent: (importFn: () => Promise<any>) => void;

  // Memory management
  cleanupUnusedData: () => void;
  monitorMemoryUsage: () => number;
}

// Cache implementation
class IntelligentCache<T> {
  private cache = new Map<string, { value: T; timestamp: number; accessCount: number }>();
  private maxSize: number;
  private ttl: number;
  private strategy: 'lru' | 'fifo' | 'lfu';
  private hits = 0;
  private misses = 0;

  constructor(options: CacheOptions = {}) {
    this.maxSize = options.maxSize || 100;
    this.ttl = options.ttl || 5 * 60 * 1000; // 5 minutes default
    this.strategy = options.strategy || 'lru';
  }

  get(key: string): T | null {
    const entry = this.cache.get(key);

    if (!entry) {
      this.misses++;
      return null;
    }

    // Check if expired
    if (Date.now() - entry.timestamp > this.ttl) {
      this.cache.delete(key);
      this.misses++;
      return null;
    }

    // Update access count for LFU
    entry.accessCount++;
    this.hits++;

    // Move to end for LRU
    if (this.strategy === 'lru') {
      this.cache.delete(key);
      this.cache.set(key, entry);
    }

    return entry.value;
  }

  set(key: string, value: T): void {
    // Remove oldest entry if at capacity
    if (this.cache.size >= this.maxSize) {
      this.evict();
    }

    this.cache.set(key, {
      value,
      timestamp: Date.now(),
      accessCount: 1
    });
  }

  has(key: string): boolean {
    const entry = this.cache.get(key);
    if (!entry) return false;

    // Check if expired
    if (Date.now() - entry.timestamp > this.ttl) {
      this.cache.delete(key);
      return false;
    }

    return true;
  }

  delete(key: string): boolean {
    return this.cache.delete(key);
  }

  clear(): void {
    this.cache.clear();
    this.hits = 0;
    this.misses = 0;
  }

  size(): number {
    return this.cache.size;
  }

  stats() {
    const total = this.hits + this.misses;
    return {
      hits: this.hits,
      misses: this.misses,
      hitRate: total > 0 ? this.hits / total : 0
    };
  }

  private evict(): void {
    if (this.cache.size === 0) return;

    let keyToRemove: string | undefined;

    switch (this.strategy) {
      case 'lru':
        // First key is the least recently used
        keyToRemove = this.cache.keys().next().value;
        break;
      case 'fifo':
        // First key is the oldest
        keyToRemove = this.cache.keys().next().value;
        break;
      case 'lfu':
        // Find key with lowest access count
        let minAccessCount = Infinity;
        keyToRemove = '';
        for (const [key, entry] of this.cache.entries()) {
          if (entry.accessCount < minAccessCount) {
            minAccessCount = entry.accessCount;
            keyToRemove = key;
          }
        }
        break;
    }

    this.cache.delete(keyToRemove);
  }
}

// Performance monitoring
class PerformanceMonitor {
  private metrics: PerformanceMetrics = {
    renderTime: 0,
    scrollPerformance: 0,
    cacheHitRate: 0,
    memoryUsage: 0,
    componentCount: 0
  };
  private isMonitoring = false;
  private observer?: PerformanceObserver;

  start(): void {
    if (this.isMonitoring) return;

    this.isMonitoring = true;

    // Monitor paint and layout metrics
    if ('PerformanceObserver' in window) {
      this.observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (entry.entryType === 'paint') {
            this.metrics.renderTime = entry.startTime;
          }
        }
      });

      this.observer.observe({ entryTypes: ['paint', 'measure'] });
    }

    // Monitor memory usage
    this.monitorMemory();
  }

  stop(): void {
    this.isMonitoring = false;
    if (this.observer) {
      this.observer.disconnect();
    }
  }

  getMetrics(): PerformanceMetrics {
    return { ...this.metrics };
  }

  updateCacheHitRate(hitRate: number): void {
    this.metrics.cacheHitRate = hitRate;
  }

  updateComponentCount(count: number): void {
    this.metrics.componentCount = count;
  }

  private monitorMemory(): void {
    if ('memory' in performance) {
      const memory = (performance as any).memory;
      this.metrics.memoryUsage = memory.usedJSHeapSize / memory.jsHeapSizeLimit;
    }
  }
}

export function usePerformanceOptimization(): PerformanceOptimizationComposable {
  const performanceMonitor = new PerformanceMonitor();

  // Virtual scrolling implementation
  const setupVirtualScroll = (
    container: Ref<HTMLElement | undefined>,
    items: Ref<any[]>,
    options: VirtualScrollOptions
  ) => {
    const scrollTop = ref(0);
    const visibleItems = ref<any[]>([]);
    const startIndex = ref(0);
    const endIndex = ref(0);
    const totalHeight = ref(0);

    const buffer = options.buffer || 5;
    const threshold = options.threshold || 100;

    const updateVisibleRange = () => {
      if (!container.value || items.value.length === 0) return;

      const containerHeight = options.containerHeight;
      const itemHeight = options.itemHeight;
      const scrollPosition = scrollTop.value;

      // Calculate visible range
      const start = Math.floor(scrollPosition / itemHeight);
      const visibleCount = Math.ceil(containerHeight / itemHeight);
      const end = Math.min(start + visibleCount + buffer, items.value.length);

      // Add buffer before start
      const bufferedStart = Math.max(0, start - buffer);

      startIndex.value = bufferedStart;
      endIndex.value = end;
      visibleItems.value = items.value.slice(bufferedStart, end);
      totalHeight.value = items.value.length * itemHeight;
    };

    const handleScroll = throttle((event: Event) => {
      const target = event.target as HTMLElement;
      scrollTop.value = target.scrollTop;
      updateVisibleRange();
    }, 16); // ~60fps

    // Watch for changes
    const cleanup = () => {
      if (container.value) {
        container.value.removeEventListener('scroll', handleScroll);
      }
    };

    // Setup scroll listener
    nextTick(() => {
      if (container.value) {
        container.value.addEventListener('scroll', handleScroll, { passive: true });
        updateVisibleRange();
      }
    });

    onUnmounted(cleanup);

    return {
      visibleItems,
      scrollTop,
      totalHeight,
      startIndex,
      endIndex,
      updateVisibleRange
    };
  };

  // Cache creation
  const createCache = <T>(options?: CacheOptions) => {
    const cache = new IntelligentCache<T>(options);

    return {
      get: (key: string) => cache.get(key),
      set: (key: string, value: T) => cache.set(key, value),
      has: (key: string) => cache.has(key),
      delete: (key: string) => cache.delete(key),
      clear: () => cache.clear(),
      size: () => cache.size(),
      stats: () => cache.stats()
    };
  };

  // Lazy loading setup
  const setupLazyLoading = (
    elements: Ref<HTMLElement[]>,
    callback: (element: HTMLElement, index: number) => void,
    options: LazyLoadOptions = {}
  ) => {
    const threshold = options.threshold || 0.1;
    const rootMargin = options.rootMargin || '50px';
    const triggerOnce = options.triggerOnce !== false;

    let observer: IntersectionObserver | null = null;

    const initObserver = () => {
      if (!('IntersectionObserver' in window)) {
        // Fallback for browsers without IntersectionObserver
        elements.value.forEach((element, index) => {
          callback(element, index);
        });
        return;
      }

      observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const index = elements.value.indexOf(entry.target as HTMLElement);
              if (index !== -1) {
                callback(entry.target as HTMLElement, index);

                if (triggerOnce) {
                  observer!.unobserve(entry.target);
                }
              }
            }
          });
        },
        { threshold, rootMargin }
      );

      elements.value.forEach((element) => {
        observer!.observe(element);
      });
    };

    nextTick(initObserver);

    return () => {
      if (observer) {
        observer.disconnect();
      }
    };
  };

  // Performance monitoring
  const startPerformanceMonitoring = () => {
    performanceMonitor.start();
  };

  const stopPerformanceMonitoring = () => {
    performanceMonitor.stop();
  };

  const getPerformanceMetrics = (): PerformanceMetrics => {
    return performanceMonitor.getMetrics();
  };

  // Optimistic updates
  const createOptimisticUpdate = <T>(
    updateFn: (data: T) => Promise<T>,
    rollbackFn: (data: T) => T
  ) => {
    return async (data: T): Promise<T> => {
      const originalData = { ...data } as T;

      try {
        // Apply optimistic update immediately
        const result = await updateFn(data);
        return result;
      } catch (error) {
        // Rollback on failure
        const rolledBackData = rollbackFn(originalData);
        throw error;
      }
    };
  };

  // Debounced and throttled functions
  const createDebouncedFunction = <T extends (...args: any[]) => any>(
    fn: T,
    delay: number
  ): T => {
    return debounce(fn, delay) as unknown as T;
  };

  const createThrottledFunction = <T extends (...args: any[]) => any>(
    fn: T,
    delay: number
  ): T => {
    return throttle(fn, delay) as unknown as T;
  };

  // Code splitting utilities
  const componentCache = new Map<string, Promise<any>>();

  const loadComponent = async (importFn: () => Promise<any>): Promise<any> => {
    const key = importFn.toString();

    if (componentCache.has(key)) {
      return componentCache.get(key);
    }

    const componentPromise = importFn();
    componentCache.set(key, componentPromise);

    return componentPromise;
  };

  const preloadComponent = (importFn: () => Promise<any>): void => {
    // Preload component in the background
    requestIdleCallback(() => {
      loadComponent(importFn).catch(() => {
        // Ignore preload errors
      });
    });
  };

  // Memory management
  const cleanupUnusedData = () => {
    // Clear component cache of unused components
    if (componentCache.size > 50) {
      componentCache.clear();
    }

    // Force garbage collection if available
    if ('gc' in window && typeof (window as any).gc === 'function') {
      (window as any).gc();
    }
  };

  const monitorMemoryUsage = (): number => {
    if ('memory' in performance) {
      const memory = (performance as any).memory;
      return memory.usedJSHeapSize / memory.jsHeapSizeLimit;
    }
    return 0;
  };

  return {
    setupVirtualScroll,
    createCache,
    setupLazyLoading,
    startPerformanceMonitoring,
    stopPerformanceMonitoring,
    getPerformanceMetrics,
    createOptimisticUpdate,
    createDebouncedFunction,
    createThrottledFunction,
    loadComponent,
    preloadComponent,
    cleanupUnusedData,
    monitorMemoryUsage
  };
}
