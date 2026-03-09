/**
 * Unified Progress Store
 *
 * Centralized state management for progress tracking across tracks and courses,
 * including real-time updates, analytics, and achievement tracking.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
  LearningProgress,
  ModuleProgress,
  UserLearningAnalytics,
  Achievement,
  ProgressMetrics,
  ProgressUpdateRequest
} from '@/types/enhanced-classroom';
import { useApi } from '@/composables/useApi';
import { handleStoreError, StatePersistence, StoreEventBus } from './index';

export const useUnifiedProgressStore = defineStore('unifiedProgress', () => {
  // State
  const userProgress = ref<LearningProgress[]>([]);
  const moduleProgress = ref<Record<string, ModuleProgress>>({});
  const trackProgress = ref<Record<string, number>>({});
  const courseProgress = ref<Record<string, number>>({});
  const achievements = ref<Achievement[]>([]);
  const analytics = ref<UserLearningAnalytics | null>(null);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);
  const lastSyncTime = ref<Date | null>(null);

  // Real-time sync state
  const syncQueue = ref<ProgressUpdateRequest[]>([]);
  const isSyncing = ref<boolean>(false);

  // API instance
  const { api } = useApi();
  const eventBus = StoreEventBus.getInstance();

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

  const learningStreak = computed(() => {
    if (!analytics.value) return 0;
    return analytics.value.streakCount || 0;
  });

  const totalTimeSpent = computed(() => {
    if (!analytics.value) return 0;
    return analytics.value.totalTimeSpent || 0;
  });

  const completionRate = computed(() => {
    if (!analytics.value) return 0;
    return Math.round(analytics.value.completionRate * 100) || 0;
  });

  const progressByTargetType = computed(() => {
    const grouped: Record<string, LearningProgress[]> = {
      course: [],
      track: [],
      module: [],
      lesson: []
    };

    userProgress.value.forEach(progress => {
      if (grouped[progress.targetType]) {
        grouped[progress.targetType].push(progress);
      }
    });

    return grouped;
  });

  const upcomingMilestones = computed(() => {
    // Calculate upcoming milestones based on current progress
    const milestones: any[] = [];

    // Track completion milestones
    Object.entries(trackProgress.value).forEach(([trackId, progress]) => {
      if (progress >= 80 && progress < 100) {
        milestones.push({
          id: `track_${trackId}_completion`,
          type: 'track_completion',
          targetId: trackId,
          title: 'Track Completion',
          description: 'Complete this track to earn a certificate',
          progress: progress,
          target: 100
        });
      }
    });

    // Course completion milestones
    Object.entries(courseProgress.value).forEach(([courseId, progress]) => {
      if (progress >= 80 && progress < 100) {
        milestones.push({
          id: `course_${courseId}_completion`,
          type: 'course_completion',
          targetId: courseId,
          title: 'Course Completion',
          description: 'Complete this course to earn a certificate',
          progress: progress,
          target: 100
        });
      }
    });

    return milestones.slice(0, 5); // Return top 5 upcoming milestones
  });

  // Actions
  const fetchUserProgress = async (userId?: string): Promise<void> => {
    if (loading.value) return;

    try {
      loading.value = true;
      error.value = null;

      const url = userId ? `/api/progress?userId=${userId}` : '/api/progress';
      const response = await api.get(url);

      userProgress.value = response.data.progress || [];
      trackProgress.value = response.data.trackProgress || {};
      courseProgress.value = response.data.courseProgress || {};
      moduleProgress.value = response.data.moduleProgress || {};

      lastSyncTime.value = new Date();

      eventBus.emit('progress:updated', {
        userProgress: userProgress.value,
        trackProgress: trackProgress.value,
        courseProgress: courseProgress.value
      });

      // Persist to localStorage
      StatePersistence.saveState('unifiedProgress', {
        userProgress: userProgress.value,
        trackProgress: trackProgress.value,
        courseProgress: courseProgress.value,
        moduleProgress: moduleProgress.value,
        lastSyncTime: lastSyncTime.value
      });

    } catch (err) {
      error.value = 'Failed to fetch progress data';
      handleStoreError(err, 'fetchUserProgress');
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const updateProgress = async (progressData: ProgressUpdateRequest): Promise<LearningProgress> => {
    try {
      // Add to sync queue for batch processing
      syncQueue.value.push(progressData);

      // Process queue if not already syncing
      if (!isSyncing.value) {
        await processSyncQueue();
      }

      // Create optimistic update
      const optimisticProgress: LearningProgress = {
        id: `temp_${Date.now()}`,
        userId: progressData.userId,
        targetType: progressData.targetType,
        targetId: progressData.targetId,
        progressType: progressData.progressType,
        value: progressData.value,
        maxValue: progressData.maxValue,
        timestamp: new Date(),
        syncStatus: 'pending',
        version: 1
      };

      userProgress.value.push(optimisticProgress);

      // Update aggregated progress
      updateAggregatedProgress(progressData);

      return optimisticProgress;

    } catch (err) {
      handleStoreError(err, 'updateProgress');
      throw err;
    }
  };

  const processSyncQueue = async (): Promise<void> => {
    if (isSyncing.value || syncQueue.value.length === 0) return;

    try {
      isSyncing.value = true;

      // Process in batches of 10
      while (syncQueue.value.length > 0) {
        const batch = syncQueue.value.splice(0, 10);

        const response = await api.post('/api/progress/batch', {
          updates: batch
        });

        const syncedProgress = response.data || [];

        // Update local state with synced data
        syncedProgress.forEach((progress: LearningProgress) => {
          const index = userProgress.value.findIndex(p =>
            p.targetType === progress.targetType &&
            p.targetId === progress.targetId &&
            p.progressType === progress.progressType
          );

          if (index !== -1) {
            userProgress.value[index] = progress;
          } else {
            userProgress.value.push(progress);
          }
        });
      }

      lastSyncTime.value = new Date();

    } catch (err) {
      handleStoreError(err, 'processSyncQueue');
      // Re-queue failed updates
      // In a real implementation, you might want to implement retry logic
    } finally {
      isSyncing.value = false;
    }
  };

  const updateAggregatedProgress = (progressData: ProgressUpdateRequest): void => {
    const progressPercentage = (progressData.value / progressData.maxValue) * 100;

    if (progressData.targetType === 'track') {
      trackProgress.value[progressData.targetId] = progressPercentage;
    } else if (progressData.targetType === 'course') {
      courseProgress.value[progressData.targetId] = progressPercentage;
    } else if (progressData.targetType === 'module') {
      if (!moduleProgress.value[progressData.targetId]) {
        moduleProgress.value[progressData.targetId] = {
          moduleId: progressData.targetId,
          assignmentId: '', // This would need to be provided
          progress: progressPercentage,
          timeSpent: 0,
          attempts: 1,
          lastAccessedAt: new Date(),
          lessonProgress: {}
        };
      } else {
        moduleProgress.value[progressData.targetId].progress = progressPercentage;
        moduleProgress.value[progressData.targetId].lastAccessedAt = new Date();
      }
    }
  };

  const fetchProgressMetrics = async (timeRange: string): Promise<ProgressMetrics> => {
    try {
      const response = await api.get(`/api/progress/metrics?timeRange=${timeRange}`);
      return response.data;

    } catch (err) {
      handleStoreError(err, 'fetchProgressMetrics');
      throw err;
    }
  };

  const fetchUserAnalytics = async (userId?: string): Promise<void> => {
    try {
      const url = userId ? `/api/analytics?userId=${userId}` : '/api/analytics';
      const response = await api.get(url);

      analytics.value = response.data;

      eventBus.emit('analytics:updated', analytics.value);

    } catch (err) {
      handleStoreError(err, 'fetchUserAnalytics');
      throw err;
    }
  };

  const fetchAchievements = async (userId?: string): Promise<void> => {
    try {
      const url = userId ? `/api/achievements?userId=${userId}` : '/api/achievements';
      const response = await api.get(url);

      achievements.value = response.data || [];

      eventBus.emit('achievements:updated', achievements.value);

    } catch (err) {
      handleStoreError(err, 'fetchAchievements');
      throw err;
    }
  };

  const exportProgress = async (format: 'pdf' | 'csv' | 'json'): Promise<Blob> => {
    try {
      const response = await api.get(`/api/progress/export?format=${format}`, {
        responseType: 'blob'
      });

      return response.data;

    } catch (err) {
      handleStoreError(err, 'exportProgress');
      throw err;
    }
  };

  const calculateCompletionRate = (targetType: string, targetId: string): number => {
    const relevantProgress = userProgress.value.filter(p =>
      p.targetType === targetType && p.targetId === targetId
    );

    if (relevantProgress.length === 0) return 0;

    const totalProgress = relevantProgress.reduce((sum, progress) => {
      return sum + (progress.value / progress.maxValue);
    }, 0);

    return Math.round((totalProgress / relevantProgress.length) * 100);
  };

  const getProgressByTarget = (targetType: string, targetId: string): LearningProgress[] => {
    return userProgress.value.filter(p =>
      p.targetType === targetType && p.targetId === targetId
    );
  };

  const getModuleProgress = (moduleId: string): ModuleProgress | null => {
    return moduleProgress.value[moduleId] || null;
  };

  const syncProgressAcrossComponents = (progressUpdate: LearningProgress): void => {
    // Update local state
    const index = userProgress.value.findIndex(p =>
      p.targetType === progressUpdate.targetType &&
      p.targetId === progressUpdate.targetId &&
      p.progressType === progressUpdate.progressType
    );

    if (index !== -1) {
      userProgress.value[index] = progressUpdate;
    } else {
      userProgress.value.push(progressUpdate);
    }

    // Update aggregated progress
    const progressPercentage = (progressUpdate.value / progressUpdate.maxValue) * 100;

    if (progressUpdate.targetType === 'track') {
      trackProgress.value[progressUpdate.targetId] = progressPercentage;
    } else if (progressUpdate.targetType === 'course') {
      courseProgress.value[progressUpdate.targetId] = progressPercentage;
    }

    // Emit event for other components
    eventBus.emit('progress:sync', progressUpdate);
  };

  const initialize = async (): Promise<void> => {
    try {
      // Load persisted state
      const persistedState = StatePersistence.loadState<any>('unifiedProgress');
      if (persistedState) {
        userProgress.value = persistedState.userProgress || [];
        trackProgress.value = persistedState.trackProgress || {};
        courseProgress.value = persistedState.courseProgress || {};
        moduleProgress.value = persistedState.moduleProgress || {};
        lastSyncTime.value = persistedState.lastSyncTime ? new Date(persistedState.lastSyncTime) : null;
      }

      // Fetch fresh data
      await Promise.all([
        fetchUserProgress(),
        fetchUserAnalytics(),
        fetchAchievements()
      ]);

    } catch (err) {
      handleStoreError(err, 'initialize');
    }
  };

  // Event listeners for cross-store communication
  eventBus.on('lesson:completed', (data: any) => {
    updateProgress({
      userId: data.userId,
      targetType: 'lesson',
      targetId: data.lessonId,
      progressType: 'completion',
      value: 1,
      maxValue: 1
    });
  });

  eventBus.on('module:completed', (data: any) => {
    updateProgress({
      userId: data.userId,
      targetType: 'module',
      targetId: data.moduleId,
      progressType: 'completion',
      value: 1,
      maxValue: 1
    });
  });

  eventBus.on('assessment:completed', (data: any) => {
    updateProgress({
      userId: data.userId,
      targetType: 'module',
      targetId: data.moduleId,
      progressType: 'score',
      value: data.score,
      maxValue: data.maxScore
    });
  });

  return {
    // State
    userProgress,
    moduleProgress,
    trackProgress,
    courseProgress,
    achievements,
    analytics,
    loading,
    error,
    lastSyncTime,
    syncQueue,
    isSyncing,

    // Computed
    overallProgress,
    recentActivity,
    learningStreak,
    totalTimeSpent,
    completionRate,
    progressByTargetType,
    upcomingMilestones,

    // Actions
    fetchUserProgress,
    updateProgress,
    processSyncQueue,
    fetchProgressMetrics,
    fetchUserAnalytics,
    fetchAchievements,
    exportProgress,
    calculateCompletionRate,
    getProgressByTarget,
    getModuleProgress,
    syncProgressAcrossComponents,
    initialize
  };
});
