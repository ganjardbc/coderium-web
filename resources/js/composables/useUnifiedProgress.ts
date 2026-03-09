/**
 * Unified Progress Composable
 *
 * Provides unified progress tracking across tracks and courses with real-time updates
 * and comprehensive analytics.
 */

import { ref, computed, watch, onUnmounted, type Ref, type ComputedRef } from 'vue';
import {
  LearningProgress,
  ModuleProgress,
  UserLearningAnalytics,
  ProgressMetrics,
  Achievement,
  Milestone,
  SyncOperation
} from '@/types/enhanced-classroom';
import { useApi } from './useApi';
import { globalLoading } from './useLoading';
import { globalErrorHandler } from './useErrorHandler';
import { useGlobalRealTimeSync } from './useRealTimeSync';

export interface UnifiedProgressComposable {
  // Reactive Data
  userProgress: Ref<LearningProgress[]>;
  trackProgress: Ref<Record<string, number>>;
  courseProgress: Ref<Record<string, number>>;
  moduleProgress: Ref<Record<string, ModuleProgress>>;
  achievements: Ref<Achievement[]>;
  analytics: Ref<UserLearningAnalytics | null>;

  // Methods
  fetchUserProgress: (userId: string) => Promise<LearningProgress[]>;
  updateProgress: (progressData: any) => Promise<LearningProgress>;
  calculateCompletionRate: (targetType: string, targetId: string) => number;
  getProgressMetrics: (timeRange: string) => Promise<ProgressMetrics>;
  exportProgress: (format: 'pdf' | 'csv' | 'json') => Promise<Blob>;

  // Real-time Updates (Enhanced)
  subscribeToProgressUpdates: (userId: string) => void;
  unsubscribeFromProgressUpdates: () => void;
  syncProgressAcrossComponents: (progressUpdate: LearningProgress) => void;

  // Achievement and Milestone Tracking
  trackAchievement: (achievement: Achievement) => Promise<void>;
  trackMilestone: (milestone: Milestone) => Promise<void>;
  checkMilestones: (userId: string) => Promise<Milestone[]>;

  // Computed
  overallProgress: ComputedRef<number>;
  recentActivity: ComputedRef<LearningProgress[]>;
  upcomingMilestones: ComputedRef<Milestone[]>;
  learningStreak: ComputedRef<number>;
  loading: Ref<boolean>;
  error: Ref<string | null>;

  // Real-time sync status
  syncStatus: ComputedRef<'synced' | 'syncing' | 'offline' | 'error'>;
  pendingSyncOperations: ComputedRef<number>;
}

// Cache configuration
const CACHE_DURATION = 2 * 60 * 1000; // 2 minutes for progress data

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  expiresAt: number;
}

class ProgressCache {
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
const progressCache = new ProgressCache();

export function useUnifiedProgress(): UnifiedProgressComposable {
  // Reactive state
  const userProgress = ref<LearningProgress[]>([]);
  const trackProgress = ref<Record<string, number>>({});
  const courseProgress = ref<Record<string, number>>({});
  const moduleProgress = ref<Record<string, ModuleProgress>>({});
  const achievements = ref<Achievement[]>([]);
  const analytics = ref<UserLearningAnalytics | null>(null);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  // API and utilities
  const { api } = useApi();
  const { setLoading } = globalLoading;
  const { handleError } = globalErrorHandler;

  // Real-time sync integration
  const realTimeSync = useGlobalRealTimeSync({
    channels: ['progress', 'achievements', 'milestones']
  });

  // Computed properties
  const overallProgress = computed(() => {
    if (userProgress.value.length === 0) return 0;

    const totalProgress = userProgress.value.reduce((sum, progress) => {
      return sum + (progress.value / progress.maxValue) * 100;
    }, 0);

    return Math.round(totalProgress / userProgress.value.length);
  });

  const recentActivity = computed(() => {
    return [...userProgress.value]
      .sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())
      .slice(0, 10);
  });

  const upcomingMilestones = computed((): Milestone[] => {
    // This would typically be calculated based on current progress and targets
    const milestones: Milestone[] = [];

    // Example milestones
    Object.entries(courseProgress.value).forEach(([courseId, progress]) => {
      if (progress >= 80 && progress < 100) {
        milestones.push({
          id: `course-completion-${courseId}`,
          title: 'Course Completion',
          description: 'Complete the remaining modules',
          type: 'course_completion',
          targetValue: 100,
          currentValue: progress,
          isCompleted: false,
          icon: 'award'
        });
      }
    });

    return milestones.sort((a, b) =>
      (b.currentValue / b.targetValue) - (a.currentValue / a.targetValue)
    );
  });

  const learningStreak = computed(() => {
    if (!analytics.value) return 0;
    return analytics.value.streakCount;
  });

  // Real-time sync status
  const syncStatus = computed((): 'synced' | 'syncing' | 'offline' | 'error' => {
    if (!realTimeSync.isOnline.value) return 'offline';
    if (realTimeSync.connectionStatus.value === 'connected' && realTimeSync.pendingOperations.value === 0) {
      return 'synced';
    }
    if (realTimeSync.pendingOperations.value > 0) return 'syncing';
    if (realTimeSync.connectionStatus.value === 'disconnected') return 'error';
    return 'synced';
  });

  const pendingSyncOperations = computed(() => realTimeSync.pendingOperations.value);

  // Main methods
  const fetchUserProgress = async (userId: string): Promise<LearningProgress[]> => {
    const loadingKey = `fetchUserProgress:${userId}`;

    try {
      setLoading(loadingKey, true);
      loading.value = true;
      error.value = null;

      // Check cache first
      const cacheKey = `progress:${userId}`;
      const cachedProgress = progressCache.get<LearningProgress[]>(cacheKey);
      if (cachedProgress) {
        userProgress.value = cachedProgress;
        processProgressData(cachedProgress);
        return cachedProgress;
      }

      const response = await api.get(`/api/users/${userId}/progress`);
      const progressData = response.data || [];

      // Cache the results
      progressCache.set(cacheKey, progressData, CACHE_DURATION);

      userProgress.value = progressData;
      processProgressData(progressData);

      return progressData;

    } catch (err) {
      handleError(err, 'Fetch User Progress');
      error.value = 'Failed to fetch progress data';
      throw err;
    } finally {
      setLoading(loadingKey, false);
      loading.value = false;
    }
  };

  const updateProgress = async (progressData: any): Promise<LearningProgress> => {
    const loadingKey = 'updateProgress';

    try {
      setLoading(loadingKey, true);

      // Create sync operation for real-time updates
      const syncOperation: SyncOperation = {
        id: generateOperationId(),
        type: 'update',
        entity: 'progress',
        entityId: `${progressData.targetType}_${progressData.targetId}`,
        data: progressData,
        timestamp: new Date(),
        userId: getCurrentUserId(),
        status: 'pending',
        retryCount: 0
      };

      // Optimistic update
      const optimisticProgress: LearningProgress = {
        id: syncOperation.entityId,
        userId: progressData.userId || getCurrentUserId(),
        targetType: progressData.targetType,
        targetId: progressData.targetId,
        progressType: progressData.progressType || 'completion',
        value: progressData.value,
        maxValue: progressData.maxValue || 100,
        timestamp: new Date(),
        syncStatus: 'pending',
        version: Date.now()
      };

      // Update local state optimistically
      const existingIndex = userProgress.value.findIndex(
        p => p.targetType === optimisticProgress.targetType && p.targetId === optimisticProgress.targetId
      );

      if (existingIndex >= 0) {
        userProgress.value[existingIndex] = optimisticProgress;
      } else {
        userProgress.value.push(optimisticProgress);
      }

      // Sync via real-time connection
      await realTimeSync.syncData(syncOperation);

      // Also make HTTP request as backup
      const response = await api.post('/api/progress', progressData);
      const updatedProgress = response.data;

      // Update with server response
      if (existingIndex >= 0) {
        userProgress.value[existingIndex] = { ...updatedProgress, syncStatus: 'synced' };
      } else {
        const newIndex = userProgress.value.findIndex(p => p.id === optimisticProgress.id);
        if (newIndex >= 0) {
          userProgress.value[newIndex] = { ...updatedProgress, syncStatus: 'synced' };
        }
      }

      // Invalidate cache
      progressCache.invalidatePattern('^progress:');

      // Sync across components
      syncProgressAcrossComponents(updatedProgress);

      // Check for new milestones
      await checkMilestones(updatedProgress.userId);

      return updatedProgress;

    } catch (err) {
      // Rollback optimistic update on error
      const rollbackIndex = userProgress.value.findIndex(
        p => p.targetType === progressData.targetType && p.targetId === progressData.targetId
      );

      if (rollbackIndex >= 0) {
        userProgress.value[rollbackIndex].syncStatus = 'conflict';
      }

      handleError(err, 'Update Progress');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const calculateCompletionRate = (targetType: string, targetId: string): number => {
    const relevantProgress = userProgress.value.filter(
      p => p.targetType === targetType && p.targetId === targetId
    );

    if (relevantProgress.length === 0) return 0;

    const totalProgress = relevantProgress.reduce((sum, progress) => {
      return sum + (progress.value / progress.maxValue);
    }, 0);

    return Math.round((totalProgress / relevantProgress.length) * 100);
  };

  const getProgressMetrics = async (timeRange: string): Promise<ProgressMetrics> => {
    const loadingKey = `progressMetrics:${timeRange}`;

    try {
      setLoading(loadingKey, true);

      const response = await api.get(`/api/progress/metrics?range=${timeRange}`);
      return response.data;

    } catch (err) {
      handleError(err, 'Get Progress Metrics');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const exportProgress = async (format: 'pdf' | 'csv' | 'json'): Promise<Blob> => {
    const loadingKey = `exportProgress:${format}`;

    try {
      setLoading(loadingKey, true);

      const response = await api.get(`/api/progress/export?format=${format}`, {
        responseType: 'blob'
      });

      return response.data;

    } catch (err) {
      handleError(err, 'Export Progress');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  // Real-time synchronization methods (Enhanced)
  const subscribeToProgressUpdates = (userId: string) => {
    // Connect to real-time sync
    realTimeSync.connect();

    // Set up data update handlers
    realTimeSync.onDataUpdate((data) => {
      handleRealTimeUpdate(data);
    });

    // Set up conflict handlers
    realTimeSync.onConflict((conflict) => {
      handleProgressConflict(conflict);
    });

    // Set up connection change handlers
    realTimeSync.onConnectionChange((status) => {
      console.log('Progress sync connection status:', status);
      if (status === 'connected') {
        // Sync any pending operations
        realTimeSync.processQueue();
      }
    });
  };

  const unsubscribeFromProgressUpdates = () => {
    realTimeSync.disconnect();
  };

  const syncProgressAcrossComponents = (progressUpdate: LearningProgress) => {
    // Update relevant progress tracking
    if (progressUpdate.targetType === 'course') {
      courseProgress.value[progressUpdate.targetId] =
        (progressUpdate.value / progressUpdate.maxValue) * 100;
    } else if (progressUpdate.targetType === 'track') {
      trackProgress.value[progressUpdate.targetId] =
        (progressUpdate.value / progressUpdate.maxValue) * 100;
    } else if (progressUpdate.targetType === 'module') {
      // Update module progress
      const existing = moduleProgress.value[progressUpdate.targetId];
      if (existing) {
        existing.progress = (progressUpdate.value / progressUpdate.maxValue) * 100;
        existing.lastAccessedAt = progressUpdate.timestamp;
      }
    }

    // Broadcast to other components via custom event
    window.dispatchEvent(new CustomEvent('progressUpdate', {
      detail: progressUpdate
    }));
  };

  // Achievement and Milestone Tracking
  const trackAchievement = async (achievement: Achievement): Promise<void> => {
    try {
      // Add to local state immediately
      achievements.value.push(achievement);

      // Sync via real-time connection
      const syncOperation: SyncOperation = {
        id: generateOperationId(),
        type: 'create',
        entity: 'achievement',
        entityId: achievement.id,
        data: achievement,
        timestamp: new Date(),
        userId: getCurrentUserId(),
        status: 'pending',
        retryCount: 0
      };

      await realTimeSync.syncData(syncOperation);

      // Also persist via API
      await api.post('/api/achievements', achievement);

      // Broadcast achievement event
      window.dispatchEvent(new CustomEvent('achievementUnlocked', {
        detail: achievement
      }));

    } catch (error) {
      // Remove from local state on error
      const index = achievements.value.findIndex(a => a.id === achievement.id);
      if (index >= 0) {
        achievements.value.splice(index, 1);
      }
      handleError(error, 'Track Achievement');
    }
  };

  const trackMilestone = async (milestone: Milestone): Promise<void> => {
    try {
      // Sync milestone via real-time connection
      const syncOperation: SyncOperation = {
        id: generateOperationId(),
        type: 'update',
        entity: 'milestone',
        entityId: milestone.id,
        data: milestone,
        timestamp: new Date(),
        userId: getCurrentUserId(),
        status: 'pending',
        retryCount: 0
      };

      await realTimeSync.syncData(syncOperation);

      // Persist via API
      await api.post('/api/milestones', milestone);

      // Broadcast milestone event
      window.dispatchEvent(new CustomEvent('milestoneReached', {
        detail: milestone
      }));

    } catch (error) {
      handleError(error, 'Track Milestone');
    }
  };

  const checkMilestones = async (userId: string): Promise<Milestone[]> => {
    try {
      const response = await api.get(`/api/users/${userId}/milestones/check`);
      const newMilestones = response.data || [];

      // Track any new milestones
      for (const milestone of newMilestones) {
        if (!milestone.isCompleted) {
          await trackMilestone({ ...milestone, isCompleted: true, completedAt: new Date() });
        }
      }

      return newMilestones;

    } catch (error) {
      handleError(error, 'Check Milestones');
      return [];
    }
  };

  // Real-time update handlers
  const handleRealTimeUpdate = (data: any) => {
    switch (data.type) {
      case 'progress_update':
        const progressUpdate = data.payload as LearningProgress;
        updateLocalProgress(progressUpdate);
        syncProgressAcrossComponents(progressUpdate);
        break;

      case 'achievement_unlocked':
        const achievement = data.payload as Achievement;
        if (!achievements.value.find(a => a.id === achievement.id)) {
          achievements.value.push(achievement);
        }
        window.dispatchEvent(new CustomEvent('achievementUnlocked', {
          detail: achievement
        }));
        break;

      case 'milestone_reached':
        const milestone = data.payload as Milestone;
        window.dispatchEvent(new CustomEvent('milestoneReached', {
          detail: milestone
        }));
        break;

      case 'analytics_updated':
        analytics.value = data.payload as UserLearningAnalytics;
        break;

      default:
        console.log('Unknown real-time update type:', data.type);
    }
  };

  const handleProgressConflict = (conflict: any) => {
    console.warn('Progress conflict detected:', conflict);

    // For now, use remote wins strategy for progress conflicts
    // In a real app, you might want to show a UI for manual resolution
    realTimeSync.handleConflict({
      ...conflict,
      resolutionStrategy: 'remote_wins'
    });
  };

  const updateLocalProgress = (progressUpdate: LearningProgress) => {
    const existingIndex = userProgress.value.findIndex(
      p => p.targetType === progressUpdate.targetType && p.targetId === progressUpdate.targetId
    );

    if (existingIndex >= 0) {
      userProgress.value[existingIndex] = { ...progressUpdate, syncStatus: 'synced' };
    } else {
      userProgress.value.push({ ...progressUpdate, syncStatus: 'synced' });
    }

    // Invalidate cache
    progressCache.invalidatePattern('^progress:');
  };

  // Helper methods
  const processProgressData = (progressData: LearningProgress[]) => {
    const courseProgressData: Record<string, number> = {};
    const trackProgressData: Record<string, number> = {};
    const moduleProgressData: Record<string, ModuleProgress> = {};

    progressData.forEach(progress => {
      const completionRate = (progress.value / progress.maxValue) * 100;

      if (progress.targetType === 'course') {
        courseProgressData[progress.targetId] = completionRate;
      } else if (progress.targetType === 'track') {
        trackProgressData[progress.targetId] = completionRate;
      } else if (progress.targetType === 'module') {
        // Create or update module progress
        if (!moduleProgressData[progress.targetId]) {
          moduleProgressData[progress.targetId] = {
            moduleId: progress.targetId,
            assignmentId: '', // Would be provided in real data
            progress: completionRate,
            timeSpent: 0,
            attempts: 1,
            lastAccessedAt: progress.timestamp,
            lessonProgress: {}
          };
        } else {
          moduleProgressData[progress.targetId].progress = completionRate;
          moduleProgressData[progress.targetId].lastAccessedAt = progress.timestamp;
        }
      }
    });

    courseProgress.value = courseProgressData;
    trackProgress.value = trackProgressData;
    moduleProgress.value = moduleProgressData;
  };

  const fetchAnalytics = async (userId: string) => {
    try {
      const response = await api.get(`/api/users/${userId}/analytics`);
      analytics.value = response.data;
    } catch (error) {
      console.error('Failed to fetch analytics:', error);
    }
  };

  const fetchAchievements = async (userId: string) => {
    try {
      const response = await api.get(`/api/users/${userId}/achievements`);
      achievements.value = response.data || [];
    } catch (error) {
      console.error('Failed to fetch achievements:', error);
    }
  };

  // Utility functions
  const generateOperationId = (): string => {
    return `op_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  };

  const getCurrentUserId = (): string => {
    // Get from auth store or localStorage
    return localStorage.getItem('user_id') || 'anonymous';
  };

  // Cleanup on unmount
  onUnmounted(() => {
    unsubscribeFromProgressUpdates();
    progressCache.clear();
  });

  return {
    // Reactive data
    userProgress,
    trackProgress,
    courseProgress,
    moduleProgress,
    achievements,
    analytics,
    loading,
    error,

    // Methods
    fetchUserProgress,
    updateProgress,
    calculateCompletionRate,
    getProgressMetrics,
    exportProgress,

    // Real-time Updates (Enhanced)
    subscribeToProgressUpdates,
    unsubscribeFromProgressUpdates,
    syncProgressAcrossComponents,

    // Achievement and Milestone Tracking
    trackAchievement,
    trackMilestone,
    checkMilestones,

    // Computed properties
    overallProgress,
    recentActivity,
    upcomingMilestones,
    learningStreak,

    // Real-time sync status
    syncStatus,
    pendingSyncOperations
  };
}

// Global instance for shared state across components
let globalUnifiedProgress: UnifiedProgressComposable | null = null;

export function useGlobalUnifiedProgress(): UnifiedProgressComposable {
  if (!globalUnifiedProgress) {
    globalUnifiedProgress = useUnifiedProgress();
  }
  return globalUnifiedProgress;
}

// Cleanup function for app unmount
export function cleanupUnifiedProgress() {
  if (globalUnifiedProgress) {
    globalUnifiedProgress.unsubscribeFromProgressUpdates();
  }
}
