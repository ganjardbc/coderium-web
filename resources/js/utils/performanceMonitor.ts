/**
 * Performance Monitoring System
 *
 * Provides comprehensive performance monitoring for the enhanced classroom frontend,
 * including load times, memory usage, API response times, and user interaction metrics.
 */

export interface PerformanceMetric {
  name: string;
  value: number;
  timestamp: number;
  category: 'load' | 'api' | 'interaction' | 'memory' | 'render';
  metadata?: Record<string, any>;
}

export interface PerformanceThresholds {
  loadTime: number;
  apiResponse: number;
  memoryUsage: number;
  renderTime: number;
}

export interface PerformanceReport {
  metrics: PerformanceMetric[];
  summary: {
    averageLoadTime: number;
    averageApiResponse: number;
    memoryUsage: number;
    slowestOperations: PerformanceMetric[];
    recommendations: string[];
  };
  timestamp: number;
}

class PerformanceMonitor {
  private metrics: PerformanceMetric[] = [];
  private observers: PerformanceObserver[] = [];
  private thresholds: PerformanceThresholds = {
    loadTime: 3000, // 3 seconds
    apiResponse: 1000, // 1 second
    memoryUsage: 100 * 1024 * 1024, // 100MB
    renderTime: 16 // 16ms for 60fps
  };

  private isMonitoring = false;

  constructor() {
    this.setupPerformanceObservers();
  }

  private setupPerformanceObservers(): void {
    if (typeof window === 'undefined' || !('PerformanceObserver' in window)) {
      return;
    }

    try {
      // Navigation timing observer
      const navigationObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (entry.entryType === 'navigation') {
            const navEntry = entry as PerformanceNavigationTiming;
            this.recordMetric({
              name: 'page_load',
              value: navEntry.loadEventEnd - navEntry.navigationStart,
              timestamp: Date.now(),
              category: 'load',
              metadata: {
                domContentLoaded: navEntry.domContentLoadedEventEnd - navEntry.navigationStart,
                firstPaint: navEntry.responseEnd - navEntry.navigationStart,
                type: navEntry.type
              }
            });
          }
        }
      });

      navigationObserver.observe({ entryTypes: ['navigation'] });
      this.observers.push(navigationObserver);

      // Resource timing observer
      const resourceObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (entry.entryType === 'resource') {
            const resourceEntry = entry as PerformanceResourceTiming;

            // Only track significant resources
            if (resourceEntry.transferSize > 10000 || resourceEntry.duration > 100) {
              this.recordMetric({
                name: 'resource_load',
                value: resourceEntry.duration,
                timestamp: Date.now(),
                category: 'load',
                metadata: {
                  name: resourceEntry.name,
                  size: resourceEntry.transferSize,
                  type: this.getResourceType(resourceEntry.name)
                }
              });
            }
          }
        }
      });

      resourceObserver.observe({ entryTypes: ['resource'] });
      this.observers.push(resourceObserver);

      // Measure observer for custom metrics
      const measureObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (entry.entryType === 'measure') {
            this.recordMetric({
              name: entry.name,
              value: entry.duration,
              timestamp: Date.now(),
              category: 'interaction',
              metadata: {
                startTime: entry.startTime
              }
            });
          }
        }
      });

      measureObserver.observe({ entryTypes: ['measure'] });
      this.observers.push(measureObserver);

      // Long task observer
      if ('PerformanceObserver' in window && 'observe' in PerformanceObserver.prototype) {
        try {
          const longTaskObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
              this.recordMetric({
                name: 'long_task',
                value: entry.duration,
                timestamp: Date.now(),
                category: 'render',
                metadata: {
                  startTime: entry.startTime,
                  attribution: (entry as any).attribution
                }
              });
            }
          });

          longTaskObserver.observe({ entryTypes: ['longtask'] });
          this.observers.push(longTaskObserver);
        } catch (e) {
          console.warn('Long task observer not supported');
        }
      }

    } catch (error) {
      console.warn('Performance observers setup failed:', error);
    }
  }

  private getResourceType(url: string): string {
    if (url.includes('.js')) return 'script';
    if (url.includes('.css')) return 'stylesheet';
    if (url.match(/\.(png|jpg|jpeg|gif|svg|webp)$/)) return 'image';
    if (url.includes('/api/')) return 'api';
    return 'other';
  }

  startMonitoring(): void {
    if (this.isMonitoring) return;

    this.isMonitoring = true;
    this.startMemoryMonitoring();
    this.startInteractionMonitoring();

    console.log('Performance monitoring started');
  }

  stopMonitoring(): void {
    this.isMonitoring = false;
    this.observers.forEach(observer => observer.disconnect());
    this.observers = [];

    console.log('Performance monitoring stopped');
  }

  private startMemoryMonitoring(): void {
    if (typeof window === 'undefined' || !('performance' in window)) return;

    const checkMemory = () => {
      if (!this.isMonitoring) return;

      const memory = (performance as any).memory;
      if (memory) {
        this.recordMetric({
          name: 'memory_usage',
          value: memory.usedJSHeapSize,
          timestamp: Date.now(),
          category: 'memory',
          metadata: {
            totalHeapSize: memory.totalJSHeapSize,
            heapSizeLimit: memory.jsHeapSizeLimit,
            usagePercentage: (memory.usedJSHeapSize / memory.jsHeapSizeLimit) * 100
          }
        });
      }

      setTimeout(checkMemory, 5000); // Check every 5 seconds
    };

    checkMemory();
  }

  private startInteractionMonitoring(): void {
    if (typeof window === 'undefined') return;

    // Monitor click interactions
    const handleClick = (event: MouseEvent) => {
      const target = event.target as HTMLElement;
      const startTime = performance.now();

      // Use requestAnimationFrame to measure render time
      requestAnimationFrame(() => {
        const renderTime = performance.now() - startTime;

        this.recordMetric({
          name: 'click_interaction',
          value: renderTime,
          timestamp: Date.now(),
          category: 'interaction',
          metadata: {
            element: target.tagName,
            className: target.className,
            id: target.id
          }
        });
      });
    };

    document.addEventListener('click', handleClick, { passive: true });

    // Monitor scroll performance
    let scrollTimeout: number;
    const handleScroll = () => {
      const startTime = performance.now();

      clearTimeout(scrollTimeout);
      scrollTimeout = window.setTimeout(() => {
        const scrollTime = performance.now() - startTime;

        this.recordMetric({
          name: 'scroll_performance',
          value: scrollTime,
          timestamp: Date.now(),
          category: 'interaction'
        });
      }, 100);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
  }

  recordMetric(metric: PerformanceMetric): void {
    this.metrics.push(metric);

    // Keep only last 1000 metrics to prevent memory leaks
    if (this.metrics.length > 1000) {
      this.metrics = this.metrics.slice(-1000);
    }

    // Check thresholds and warn if exceeded
    this.checkThresholds(metric);
  }

  private checkThresholds(metric: PerformanceMetric): void {
    let threshold: number | undefined;

    switch (metric.category) {
      case 'load':
        threshold = this.thresholds.loadTime;
        break;
      case 'api':
        threshold = this.thresholds.apiResponse;
        break;
      case 'memory':
        threshold = this.thresholds.memoryUsage;
        break;
      case 'render':
        threshold = this.thresholds.renderTime;
        break;
    }

    if (threshold && metric.value > threshold) {
      console.warn(`Performance threshold exceeded for ${metric.name}:`, {
        value: metric.value,
        threshold,
        metric
      });

      // Emit custom event for performance issues
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('performance:threshold-exceeded', {
          detail: { metric, threshold }
        }));
      }
    }
  }

  // API timing helpers
  startApiTimer(operationName: string): () => void {
    const startTime = performance.now();

    return () => {
      const duration = performance.now() - startTime;
      this.recordMetric({
        name: `api_${operationName}`,
        value: duration,
        timestamp: Date.now(),
        category: 'api',
        metadata: {
          operation: operationName
        }
      });
    };
  }

  // Component render timing
  startRenderTimer(componentName: string): () => void {
    const startTime = performance.now();

    return () => {
      const duration = performance.now() - startTime;
      this.recordMetric({
        name: `render_${componentName}`,
        value: duration,
        timestamp: Date.now(),
        category: 'render',
        metadata: {
          component: componentName
        }
      });
    };
  }

  // Custom timing
  time(name: string): void {
    if (typeof performance !== 'undefined' && performance.mark) {
      performance.mark(`${name}_start`);
    }
  }

  timeEnd(name: string): void {
    if (typeof performance !== 'undefined' && performance.mark && performance.measure) {
      performance.mark(`${name}_end`);
      performance.measure(name, `${name}_start`, `${name}_end`);
    }
  }

  // Get performance report
  getReport(timeRange?: { start: number; end: number }): PerformanceReport {
    let filteredMetrics = this.metrics;

    if (timeRange) {
      filteredMetrics = this.metrics.filter(
        metric => metric.timestamp >= timeRange.start && metric.timestamp <= timeRange.end
      );
    }

    const loadMetrics = filteredMetrics.filter(m => m.category === 'load');
    const apiMetrics = filteredMetrics.filter(m => m.category === 'api');
    const memoryMetrics = filteredMetrics.filter(m => m.category === 'memory');

    const averageLoadTime = loadMetrics.length > 0
      ? loadMetrics.reduce((sum, m) => sum + m.value, 0) / loadMetrics.length
      : 0;

    const averageApiResponse = apiMetrics.length > 0
      ? apiMetrics.reduce((sum, m) => sum + m.value, 0) / apiMetrics.length
      : 0;

    const latestMemoryMetric = memoryMetrics[memoryMetrics.length - 1];
    const memoryUsage = latestMemoryMetric ? latestMemoryMetric.value : 0;

    const slowestOperations = [...filteredMetrics]
      .sort((a, b) => b.value - a.value)
      .slice(0, 10);

    const recommendations = this.generateRecommendations(filteredMetrics);

    return {
      metrics: filteredMetrics,
      summary: {
        averageLoadTime,
        averageApiResponse,
        memoryUsage,
        slowestOperations,
        recommendations
      },
      timestamp: Date.now()
    };
  }

  private generateRecommendations(metrics: PerformanceMetric[]): string[] {
    const recommendations: string[] = [];

    const loadMetrics = metrics.filter(m => m.category === 'load');
    const apiMetrics = metrics.filter(m => m.category === 'api');
    const memoryMetrics = metrics.filter(m => m.category === 'memory');
    const longTasks = metrics.filter(m => m.name === 'long_task');

    // Load time recommendations
    const avgLoadTime = loadMetrics.length > 0
      ? loadMetrics.reduce((sum, m) => sum + m.value, 0) / loadMetrics.length
      : 0;

    if (avgLoadTime > this.thresholds.loadTime) {
      recommendations.push('Consider optimizing bundle size and implementing code splitting');
    }

    // API response recommendations
    const slowApiCalls = apiMetrics.filter(m => m.value > this.thresholds.apiResponse);
    if (slowApiCalls.length > 0) {
      recommendations.push('Optimize slow API calls or implement caching strategies');
    }

    // Memory recommendations
    const latestMemory = memoryMetrics[memoryMetrics.length - 1];
    if (latestMemory && latestMemory.value > this.thresholds.memoryUsage) {
      recommendations.push('Monitor memory usage and implement cleanup strategies');
    }

    // Long task recommendations
    if (longTasks.length > 0) {
      recommendations.push('Break up long-running tasks to improve responsiveness');
    }

    // Resource recommendations
    const largeResources = metrics.filter(
      m => m.category === 'load' && m.metadata?.size > 500000 // 500KB
    );
    if (largeResources.length > 0) {
      recommendations.push('Optimize large resources (images, scripts, stylesheets)');
    }

    return recommendations;
  }

  // Export metrics for analysis
  exportMetrics(format: 'json' | 'csv' = 'json'): string {
    if (format === 'csv') {
      const headers = ['name', 'value', 'timestamp', 'category', 'metadata'];
      const rows = this.metrics.map(metric => [
        metric.name,
        metric.value.toString(),
        metric.timestamp.toString(),
        metric.category,
        JSON.stringify(metric.metadata || {})
      ]);

      return [headers, ...rows].map(row => row.join(',')).join('\n');
    }

    return JSON.stringify(this.metrics, null, 2);
  }

  // Clear metrics
  clearMetrics(): void {
    this.metrics = [];
  }

  // Update thresholds
  updateThresholds(newThresholds: Partial<PerformanceThresholds>): void {
    this.thresholds = { ...this.thresholds, ...newThresholds };
  }

  // Get current metrics
  getMetrics(): PerformanceMetric[] {
    return [...this.metrics];
  }
}

// Global performance monitor instance
export const performanceMonitor = new PerformanceMonitor();

// Vue composable for performance monitoring
export function usePerformanceMonitor() {
  return {
    startMonitoring: () => performanceMonitor.startMonitoring(),
    stopMonitoring: () => performanceMonitor.stopMonitoring(),
    recordMetric: (metric: PerformanceMetric) => performanceMonitor.recordMetric(metric),
    startApiTimer: (name: string) => performanceMonitor.startApiTimer(name),
    startRenderTimer: (name: string) => performanceMonitor.startRenderTimer(name),
    time: (name: string) => performanceMonitor.time(name),
    timeEnd: (name: string) => performanceMonitor.timeEnd(name),
    getReport: (timeRange?: { start: number; end: number }) => performanceMonitor.getReport(timeRange),
    exportMetrics: (format?: 'json' | 'csv') => performanceMonitor.exportMetrics(format),
    clearMetrics: () => performanceMonitor.clearMetrics(),
    updateThresholds: (thresholds: Partial<PerformanceThresholds>) => performanceMonitor.updateThresholds(thresholds),
    getMetrics: () => performanceMonitor.getMetrics()
  };
}
