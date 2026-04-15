/**
 * User Store
 *
 * Centralized state management for user authentication, profile,
 * enrollments, and user-specific data.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
  User,
  TrackEnrollment,
  CourseEnrollment,
  UserPreferences,
  UserLearningAnalytics
} from '@/types/enhanced-classroom';
import { useApi } from '@/composables/useApi';
import { handleStoreError, StatePersistence, StoreEventBus } from './index';

export const useUserStore = defineStore('user', () => {
  // State
  const currentUser = ref<User | null>(null);
  const isAuthenticated = ref<boolean>(false);
  const trackEnrollments = ref<TrackEnrollment[]>([]);
  const courseEnrollments = ref<CourseEnrollment[]>([]);
  const userPreferences = ref<UserPreferences | null>(null);
  const userAnalytics = ref<UserLearningAnalytics | null>(null);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  // API instance
  const { api } = useApi();
  const eventBus = StoreEventBus.getInstance();

  // Computed properties
  const isAdmin = computed(() => {
    return currentUser.value?.role === 'admin';
  });

  const isInstructor = computed(() => {
    return currentUser.value?.role === 'instructor' || isAdmin.value;
  });

  const isStudent = computed(() => {
    return currentUser.value?.role === 'student';
  });

  const enrolledTrackIds = computed(() => {
    return trackEnrollments.value.map(enrollment => enrollment.trackId);
  });

  const enrolledCourseIds = computed(() => {
    return courseEnrollments.value.map(enrollment => enrollment.courseId);
  });

  const completedTrackIds = computed(() => {
    return trackEnrollments.value
      .filter(enrollment => enrollment.completedAt)
      .map(enrollment => enrollment.trackId);
  });

  const completedCourseIds = computed(() => {
    return courseEnrollments.value
      .filter(enrollment => enrollment.completedAt)
      .map(enrollment => enrollment.courseId);
  });

  const totalEnrollments = computed(() => {
    return trackEnrollments.value.length + courseEnrollments.value.length;
  });

  const totalCompletions = computed(() => {
    return completedTrackIds.value.length + completedCourseIds.value.length;
  });

  const overallCompletionRate = computed(() => {
    if (totalEnrollments.value === 0) return 0;
    return Math.round((totalCompletions.value / totalEnrollments.value) * 100);
  });

  // Actions
  const fetchCurrentUser = async (): Promise<void> => {
    try {
      loading.value = true;
      error.value = null;

      const response = await api.get('/api/user');
      currentUser.value = response.data;
      isAuthenticated.value = !!response.data;

      if (currentUser.value) {
        eventBus.emit('user:authenticated', currentUser.value);

        // Fetch user-related data
        await Promise.all([
          fetchUserEnrollments(),
          fetchUserPreferences(),
          fetchUserAnalytics()
        ]);
      }

      // Persist user data
      StatePersistence.saveState('currentUser', currentUser.value);

    } catch (err) {
      error.value = 'Failed to fetch user data';
      isAuthenticated.value = false;
      currentUser.value = null;
      handleStoreError(err, 'fetchCurrentUser');
    } finally {
      loading.value = false;
    }
  };

  const updateUserProfile = async (updates: Partial<User>): Promise<User> => {
    try {
      loading.value = true;
      error.value = null;

      const response = await api.put('/api/user/profile', updates);
      const updatedUser = response.data;

      currentUser.value = updatedUser;

      eventBus.emit('user:profile:updated', updatedUser);

      // Persist updated user data
      StatePersistence.saveState('currentUser', currentUser.value);

      return updatedUser;

    } catch (err) {
      error.value = 'Failed to update profile';
      handleStoreError(err, 'updateUserProfile');
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const fetchUserEnrollments = async (): Promise<void> => {
    try {
      const [trackResponse, courseResponse] = await Promise.all([
        api.get('/api/user/track-enrollments'),
        api.get('/api/user/course-enrollments')
      ]);

      trackEnrollments.value = trackResponse.data || [];
      courseEnrollments.value = courseResponse.data || [];

      eventBus.emit('user:enrollments:updated', {
        tracks: trackEnrollments.value,
        courses: courseEnrollments.value
      });

    } catch (err) {
      handleStoreError(err, 'fetchUserEnrollments');
    }
  };

  const enrollInTrack = async (trackId: string): Promise<TrackEnrollment> => {
    try {
      const response = await api.post(`/api/tracks/${trackId}/enroll`);
      const enrollment = response.data;

      trackEnrollments.value.push(enrollment);

      eventBus.emit('user:track:enrolled', enrollment);

      return enrollment;

    } catch (err) {
      handleStoreError(err, 'enrollInTrack');
      throw err;
    }
  };

  const unenrollFromTrack = async (trackId: string): Promise<void> => {
    try {
      await api.delete(`/api/tracks/${trackId}/enroll`);

      trackEnrollments.value = trackEnrollments.value.filter(
        enrollment => enrollment.trackId !== trackId
      );

      eventBus.emit('user:track:unenrolled', trackId);

    } catch (err) {
      handleStoreError(err, 'unenrollFromTrack');
      throw err;
    }
  };

  const enrollInCourse = async (courseId: string): Promise<CourseEnrollment> => {
    try {
      const response = await api.post(`/api/v1/courses/${courseId}/enroll`);
      const enrollment = response.data;

      courseEnrollments.value.push(enrollment);

      eventBus.emit('user:course:enrolled', enrollment);

      return enrollment;

    } catch (err) {
      handleStoreError(err, 'enrollInCourse');
      throw err;
    }
  };

  const unenrollFromCourse = async (courseId: string): Promise<void> => {
    try {
      await api.delete(`/api/v1/courses/${courseId}/enroll`);

      courseEnrollments.value = courseEnrollments.value.filter(
        enrollment => enrollment.courseId !== courseId
      );

      eventBus.emit('user:course:unenrolled', courseId);

    } catch (err) {
      handleStoreError(err, 'unenrollFromCourse');
      throw err;
    }
  };

  const fetchUserPreferences = async (): Promise<void> => {
    try {
      const response = await api.get('/api/user/preferences');
      userPreferences.value = response.data;

      eventBus.emit('user:preferences:updated', userPreferences.value);

    } catch (err) {
      handleStoreError(err, 'fetchUserPreferences');
    }
  };

  const updateUserPreferences = async (preferences: Partial<UserPreferences>): Promise<UserPreferences> => {
    try {
      const response = await api.put('/api/user/preferences', preferences);
      const updatedPreferences = response.data;

      userPreferences.value = updatedPreferences;

      eventBus.emit('user:preferences:updated', updatedPreferences);

      return updatedPreferences;

    } catch (err) {
      handleStoreError(err, 'updateUserPreferences');
      throw err;
    }
  };

  const fetchUserAnalytics = async (): Promise<void> => {
    try {
      const response = await api.get('/api/user/analytics');
      userAnalytics.value = response.data;

      eventBus.emit('user:analytics:updated', userAnalytics.value);

    } catch (err) {
      handleStoreError(err, 'fetchUserAnalytics');
    }
  };

  const logout = async (): Promise<void> => {
    try {
      await api.post('/api/logout');

      // Clear user state
      currentUser.value = null;
      isAuthenticated.value = false;
      trackEnrollments.value = [];
      courseEnrollments.value = [];
      userPreferences.value = null;
      userAnalytics.value = null;

      // Clear persisted data
      StatePersistence.clearState('currentUser');

      eventBus.emit('user:logged_out');

    } catch (err) {
      handleStoreError(err, 'logout');
      throw err;
    }
  };

  // Utility methods
  const isEnrolledInTrack = (trackId: string): boolean => {
    return enrolledTrackIds.value.includes(trackId);
  };

  const isEnrolledInCourse = (courseId: string): boolean => {
    return enrolledCourseIds.value.includes(courseId);
  };

  const hasCompletedTrack = (trackId: string): boolean => {
    return completedTrackIds.value.includes(trackId);
  };

  const hasCompletedCourse = (courseId: string): boolean => {
    return completedCourseIds.value.includes(courseId);
  };

  const getTrackEnrollment = (trackId: string): TrackEnrollment | null => {
    return trackEnrollments.value.find(enrollment => enrollment.trackId === trackId) || null;
  };

  const getCourseEnrollment = (courseId: string): CourseEnrollment | null => {
    return courseEnrollments.value.find(enrollment => enrollment.courseId === courseId) || null;
  };

  const getTrackProgress = (trackId: string): number => {
    const enrollment = getTrackEnrollment(trackId);
    return enrollment?.progress || 0;
  };

  const getCourseProgress = (courseId: string): number => {
    const enrollment = getCourseEnrollment(courseId);
    return enrollment?.progress || 0;
  };

  const initialize = async (): Promise<void> => {
    try {
      // Load persisted user data
      const persistedUser = StatePersistence.loadState<User>('currentUser');
      if (persistedUser) {
        currentUser.value = persistedUser;
        isAuthenticated.value = true;
      }

      // Fetch fresh user data if authenticated
      if (isAuthenticated.value) {
        await fetchCurrentUser();
      }

    } catch (err) {
      handleStoreError(err, 'initialize');
    }
  };

  // Event listeners for cross-store communication
  eventBus.on('progress:updated', (data: any) => {
    // Update enrollment progress when progress is updated
    if (data.trackProgress) {
      Object.entries(data.trackProgress).forEach(([trackId, progress]) => {
        const enrollment = getTrackEnrollment(trackId);
        if (enrollment) {
          enrollment.progress = progress as number;
        }
      });
    }

    if (data.courseProgress) {
      Object.entries(data.courseProgress).forEach(([courseId, progress]) => {
        const enrollment = getCourseEnrollment(courseId);
        if (enrollment) {
          enrollment.progress = progress as number;
        }
      });
    }
  });

  eventBus.on('achievement:earned', (achievement: any) => {
    // Add achievement to user's achievements
    if (currentUser.value) {
      if (!currentUser.value.achievements) {
        currentUser.value.achievements = [];
      }
      currentUser.value.achievements.push(achievement);
    }
  });

  return {
    // State
    currentUser,
    isAuthenticated,
    trackEnrollments,
    courseEnrollments,
    userPreferences,
    userAnalytics,
    loading,
    error,

    // Computed
    isAdmin,
    isInstructor,
    isStudent,
    enrolledTrackIds,
    enrolledCourseIds,
    completedTrackIds,
    completedCourseIds,
    totalEnrollments,
    totalCompletions,
    overallCompletionRate,

    // Actions
    fetchCurrentUser,
    updateUserProfile,
    fetchUserEnrollments,
    enrollInTrack,
    unenrollFromTrack,
    enrollInCourse,
    unenrollFromCourse,
    fetchUserPreferences,
    updateUserPreferences,
    fetchUserAnalytics,
    logout,
    isEnrolledInTrack,
    isEnrolledInCourse,
    hasCompletedTrack,
    hasCompletedCourse,
    getTrackEnrollment,
    getCourseEnrollment,
    getTrackProgress,
    getCourseProgress,
    initialize
  };
});
