/**
 * Performance Load Testing
 *
 * Tests the application's performance under various load conditions,
 * including large datasets, concurrent operations, and memory usage.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { performanceMonitor } from '@/utils/performanceMonitor';
import { useModuleLibraryStore } from '@/stores/moduleLibrary';
import { useAssignmentStore } from '@/stores/assignment';
import { useCourseStore } from '@/stores/course';

// Generate large datasets for testing
const generateLargeModuleDataset = (count: number) => {
  return Array.from({ length: count }, (_, index) => ({
    id: `module-${index + 1}`,
    title: `Module ${index + 1}: Advanced Topic`,
    description: `This is a comprehensive module covering advanced topics in the field. It includes multiple lessons, assessments, and practical exercises designed to enhance learning outcomes.`,
    category: ['Programming', 'Frontend', 'Backend', 'DevOps', 'Design'][index % 5],
    difficulty: ['beginner', 'intermediate', 'advanced'][index % 3] as const,
    estimatedDuration: 60 + (index % 240), // 60-300 minutes
    tags: [`tag-${index % 10}`, `category-${index % 5}`, `level-${index % 3}`],
    assignmentCount: index % 10,
    usageAnalytics: {
      totalAssignments: index % 20,
      popularityRank: index % 100,
      completionRate: 50 + (index % 50)
    },
    rating: 3 + (index % 3),
    isPublished: index % 4 !== 0,
    createdAt: new Date(Date.now() - index * 86400000),
    updatedAt: new Date(Date.now() - (index % 30) * 86400000)
  }));
};

const generateLargeCourseDataset = (count: number) => {
  return Array.from({ length: count }, (_, index) => ({
    id: `course-${index + 1}`,
    title: `Course ${index + 1}: Comprehensive Learning Path`,
    description: `A comprehensive course that covers multiple aspects of the subject matter with detailed modules and assessments.`,
    category: ['Programming', 'Frontend', 'Backend', 'DevOps', 'Design'][index % 5],
    difficulty: ['beginner', 'intermediate', 'advanced'][index % 3] as const,
    estimatedDuration: 300 + (index % 1200), // 5-25 hours
    moduleCount: 5 + (index % 15),
    rating: 3.5 + (index % 2),
    isPublished: index % 3 !== 0,
    createdAt: new Date(Date.now() - index * 86400000),
    updatedAt: new Date(Date.now() - (index % 30) * 86400000)
  }));
};

// Mock API with performance simulation
const createMockApiWithDelay = (delay: number = 100) => ({
  get: vi.fn().mockImplementation(async (url: string) => {
    await new Promise(resolve => setTimeout(resolve, delay));

    if (url === '/api/modules') {
      return { data: generateLargeModuleDataset(1000) };
    }
    if (url === '/api/v1/courses') {
      return { data: generateLargeCourseDataset(500) };
    }
    if (url.includes('/api/modules/search')) {
      const query = new URL(`http://example.com${url}`).searchParams.get('q') || '';
      const modules = generateLargeModuleDataset(1000);
      const filtered = modules.filter(m =>
        m.title.toLowerCase().includes(query.toLowerCase()) ||
        m.description.toLowerCase().includes(query.toLowerCase())
      );
      return { data: filtered.slice(0, 50) }; // Limit search results
    }
    return { data: [] };
  }),
  post: vi.fn().mockImplementation(async () => {
    await new Promise(resolve => setTimeout(resolve, delay));
    return { data: { id: 'new-item', success: true } };
  }),
  put: vi.fn(),
  delete: vi.fn()
});

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ api: createMockApiWithDelay(50) })
}));

describe('Performance Load Testing', () => {
  let moduleLibraryStore: ReturnType<typeof useModuleLibraryStore>;
  let assignmentStore: ReturnType<typeof useAssignmentStore>;
  let courseStore: ReturnType<typeof useCourseStore>;

  beforeEach(() => {
    setActivePinia(createPinia());

    moduleLibraryStore = useModuleLibraryStore();
    assignmentStore = useAssignmentStore();
    courseStore = useCourseStore();

    performanceMonitor.clearMetrics();
    performanceMonitor.startMonitoring();
  });

  afterEach(() => {
    performanceMonitor.stopMonitoring();
    vi.clearAllMocks();
  });

  describe('Large Dataset Performance', () => {
    it('should handle loading 1000 modules within performance threshold', async () => {
      const startTime = performance.now();

      await moduleLibraryStore.fetchModules();

      const loadTime = performance.now() - startTime;

      expect(moduleLibraryStore.modules).toHaveLength(1000);
      expect(loadTime).toBeLessThan(3000); // Should load within 3 seconds

      // Check memory usage
      const report = performanceMonitor.getReport();
      const memoryMetrics = report.metrics.filter(m => m.category === 'memory');

      if (memoryMetrics.length > 0) {
        const latestMemory = memoryMetrics[memoryMetrics.length - 1];
        expect(latestMemory.value).toBeLessThan(200 * 1024 * 1024); // Less than 200MB
      }
    });

    it('should handle filtering large datasets efficiently', async () => {
      await moduleLibraryStore.fetchModules();

      const startTime = performance.now();

      // Apply multiple filters
      moduleLibraryStore.updateFilters({
        categories: ['Programming', 'Frontend'],
        difficulties: ['intermediate', 'advanced'],
        durationRange: { min: 60, max: 180 }
      });

      await nextTick();

      const filterTime = performance.now() - startTime;

      expect(filterTime).toBeLessThan(100); // Filtering should be fast
      expect(moduleLibraryStore.filteredModules.length).toBeGreaterThan(0);
    });

    it('should handle search operations on large datasets', async () => {
      await moduleLibraryStore.fetchModules();

      const searchQueries = ['Advanced', 'Module', 'Topic', 'Programming', 'Design'];
      const searchTimes: number[] = [];

      for (const query of searchQueries) {
        const startTime = performance.now();

        await moduleLibraryStore.searchModules(query);

        const searchTime = performance.now() - startTime;
        searchTimes.push(searchTime);

        expect(searchTime).toBeLessThan(500); // Each search should complete within 500ms
      }

      const averageSearchTime = searchTimes.reduce((sum, time) => sum + time, 0) / searchTimes.length;
      expect(averageSearchTime).toBeLessThan(300); // Average search time should be under 300ms
    });
  });

  describe('Concurrent Operations Performance', () => {
    it('should handle multiple concurrent API calls efficiently', async () => {
      const startTime = performance.now();

      // Simulate concurrent operations
      const operations = [
        moduleLibraryStore.fetchModules(),
        courseStore.fetchCourses(),
        assignmentStore.fetchAssignments(),
        assignmentStore.fetchAssignmentTargets()
      ];

      await Promise.all(operations);

      const totalTime = performance.now() - startTime;

      // Concurrent operations should not take much longer than the slowest individual operation
      expect(totalTime).toBeLessThan(2000);

      expect(moduleLibraryStore.modules.length).toBeGreaterThan(0);
      expect(courseStore.courses.length).toBeGreaterThan(0);
    });

    it('should handle rapid successive operations without performance degradation', async () => {
      await moduleLibraryStore.fetchModules();

      const operationTimes: number[] = [];

      // Perform 10 rapid filter operations
      for (let i = 0; i < 10; i++) {
        const startTime = performance.now();

        moduleLibraryStore.updateFilters({
          categories: [`category-${i % 3}`],
          difficulties: [['beginner', 'intermediate', 'advanced'][i % 3] as const]
        });

        await nextTick();

        const operationTime = performance.now() - startTime;
        operationTimes.push(operationTime);
      }

      // Check that operation times don't increase significantly
      const firstHalf = operationTimes.slice(0, 5);
      const secondHalf = operationTimes.slice(5);

      const firstHalfAvg = firstHalf.reduce((sum, time) => sum + time, 0) / firstHalf.length;
      const secondHalfAvg = secondHalf.reduce((sum, time) => sum + time, 0) / secondHalf.length;

      // Second half should not be more than 50% slower than first half
      expect(secondHalfAvg).toBeLessThan(firstHalfAvg * 1.5);
    });
  });

  describe('Memory Usage Testing', () => {
    it('should not have memory leaks during repeated operations', async () => {
      const initialReport = performanceMonitor.getReport();
      const initialMemoryMetrics = initialReport.metrics.filter(m => m.category === 'memory');

      // Perform repeated operations that could cause memory leaks
      for (let i = 0; i < 20; i++) {
        await moduleLibraryStore.fetchModules();
        moduleLibraryStore.updateFilters({
          categories: [`category-${i % 5}`]
        });
        await nextTick();

        // Clear modules to simulate component unmounting
        moduleLibraryStore.modules.splice(0);
      }

      // Force garbage collection if available
      if (global.gc) {
        global.gc();
      }

      await new Promise(resolve => setTimeout(resolve, 100));

      const finalReport = performanceMonitor.getReport();
      const finalMemoryMetrics = finalReport.metrics.filter(m => m.category === 'memory');

      if (initialMemoryMetrics.length > 0 && finalMemoryMetrics.length > 0) {
        const initialMemory = initialMemoryMetrics[0].value;
        const finalMemory = finalMemoryMetrics[finalMemoryMetrics.length - 1].value;

        // Memory usage should not increase by more than 50MB
        expect(finalMemory - initialMemory).toBeLessThan(50 * 1024 * 1024);
      }
    });

    it('should handle large data structures efficiently', async () => {
      const largeDataset = generateLargeModuleDataset(5000);

      const startTime = performance.now();

      // Simulate processing large dataset
      moduleLibraryStore.modules.push(...largeDataset);

      // Perform operations on large dataset
      const filtered = moduleLibraryStore.filteredModules;
      const byCategory = moduleLibraryStore.modulesByCategory;
      const popular = moduleLibraryStore.popularModules;

      const processingTime = performance.now() - startTime;

      expect(processingTime).toBeLessThan(1000); // Should process within 1 second
      expect(filtered.length).toBe(5000);
      expect(Object.keys(byCategory).length).toBeGreaterThan(0);
      expect(popular.length).toBe(10);
    });
  });

  describe('Bundle Size and Loading Performance', () => {
    it('should have reasonable bundle size metrics', () => {
      // This would typically be measured by build tools
      // Here we simulate checking for large imports or heavy dependencies

      const report = performanceMonitor.getReport();
      const loadMetrics = report.metrics.filter(m => m.category === 'load');

      // Check that no individual resource is excessively large
      const largeResources = loadMetrics.filter(m =>
        m.metadata?.size && m.metadata.size > 1024 * 1024 // 1MB
      );

      expect(largeResources.length).toBeLessThan(3); // Should have fewer than 3 resources over 1MB
    });

    it('should load critical resources quickly', async () => {
      const startTime = performance.now();

      // Simulate critical resource loading
      await Promise.all([
        moduleLibraryStore.initialize(),
        courseStore.initialize()
      ]);

      const loadTime = performance.now() - startTime;

      expect(loadTime).toBeLessThan(2000); // Critical resources should load within 2 seconds
    });
  });

  describe('Real-time Performance', () => {
    it('should handle real-time updates efficiently', async () => {
      await moduleLibraryStore.fetchModules();

      const updateTimes: number[] = [];

      // Simulate 50 real-time updates
      for (let i = 0; i < 50; i++) {
        const startTime = performance.now();

        // Simulate real-time module update
        const moduleIndex = i % moduleLibraryStore.modules.length;
        if (moduleLibraryStore.modules[moduleIndex]) {
          moduleLibraryStore.modules[moduleIndex].assignmentCount += 1;
        }

        await nextTick();

        const updateTime = performance.now() - startTime;
        updateTimes.push(updateTime);
      }

      const averageUpdateTime = updateTimes.reduce((sum, time) => sum + time, 0) / updateTimes.length;
      const maxUpdateTime = Math.max(...updateTimes);

      expect(averageUpdateTime).toBeLessThan(10); // Average update should be under 10ms
      expect(maxUpdateTime).toBeLessThan(50); // No single update should take more than 50ms
    });
  });

  describe('Performance Monitoring Integration', () => {
    it('should collect performance metrics during operations', async () => {
      // Manually record some metrics to simulate real usage
      performanceMonitor.recordMetric({
        name: 'test_load',
        value: 150,
        timestamp: Date.now(),
        category: 'load'
      });

      performanceMonitor.recordMetric({
        name: 'test_api',
        value: 250,
        timestamp: Date.now(),
        category: 'api'
      });

      await moduleLibraryStore.fetchModules();
      await courseStore.fetchCourses();

      const report = performanceMonitor.getReport();

      expect(report.metrics.length).toBeGreaterThan(0);

      // Should have metrics from different categories
      const categories = new Set(report.metrics.map(m => m.category));
      expect(categories.size).toBeGreaterThan(0);
    });

    it('should generate meaningful performance recommendations', async () => {
      // Record slow metrics to trigger recommendations
      performanceMonitor.recordMetric({
        name: 'slow_api_call',
        value: 2500, // Slow API call
        timestamp: Date.now(),
        category: 'api'
      });

      performanceMonitor.recordMetric({
        name: 'slow_load',
        value: 4000, // Slow load time
        timestamp: Date.now(),
        category: 'load'
      });

      performanceMonitor.recordMetric({
        name: 'large_resource',
        value: 1000,
        timestamp: Date.now(),
        category: 'load',
        metadata: { size: 600000 } // Large resource
      });

      await moduleLibraryStore.fetchModules();

      const report = performanceMonitor.getReport();

      expect(report.summary.recommendations.length).toBeGreaterThan(0);
      expect(report.summary.recommendations.some(r =>
        r.includes('API') || r.includes('cache') || r.includes('optimize')
      )).toBe(true);
    });
  });
});
