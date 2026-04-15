/**
 * Course Store
 *
 * Centralized state management for course creation, editing, and management,
 * including course templates and enrollment tracking.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
  Course,
  CourseEnrollment,
  CourseTemplate,
  CourseStructure,
  CreateCourseRequest,
  UpdateCourseRequest,
  CourseFilters
} from '@/types/enhanced-classroom';
import { useApi } from '@/composables/useApi';
import { handleStoreError, StatePersistence, StoreEventBus } from './index';

export const useCourseStore = defineStore('course', () => {
  // State
  const courses = ref<Course[]>([]);
  const currentCourse = ref<Course | null>(null);
  const courseTemplates = ref<CourseTemplate[]>([]);
  const userEnrollments = ref<CourseEnrollment[]>([]);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);
  const lastFetchTime = ref<Date | null>(null);

  // API instance
  const { api } = useApi();
  const eventBus = StoreEventBus.getInstance();

  // Computed properties
  const publishedCourses = computed(() => {
    return courses.value.filter(course => course.isPublished);
  });

  const draftCourses = computed(() => {
    return courses.value.filter(course => !course.isPublished);
  });

  const coursesByCategory = computed(() => {
    const grouped: Record<string, Course[]> = {};

    courses.value.forEach(course => {
      if (!grouped[course.category]) {
        grouped[course.category] = [];
      }
      grouped[course.category].push(course);
    });

    // Sort courses within each category by rating
    Object.keys(grouped).forEach(category => {
      grouped[category].sort((a, b) => b.rating - a.rating);
    });

    return grouped;
  });

  const enrolledCourses = computed(() => {
    const enrolledCourseIds = userEnrollments.value.map(e => e.courseId);
    return courses.value.filter(course => enrolledCourseIds.includes(course.id));
  });

  const availableCourses = computed(() => {
    const enrolledCourseIds = userEnrollments.value.map(e => e.courseId);
    return publishedCourses.value.filter(course => !enrolledCourseIds.includes(course.id));
  });

  const courseCategories = computed(() => {
    const categorySet = new Set(courses.value.map(course => course.category));
    return Array.from(categorySet).sort();
  });

  const totalCourses = computed(() => courses.value.length);

  // Actions
  const fetchCourses = async (filters?: CourseFilters): Promise<void> => {
    if (loading.value) return;

    try {
      loading.value = true;
      error.value = null;

      // Build query parameters
      const params = new URLSearchParams();
      if (filters) {
        if (filters.categories?.length) {
          params.append('categories', filters.categories.join(','));
        }
        if (filters.difficulties?.length) {
          params.append('difficulties', filters.difficulties.join(','));
        }
        if (filters.isPublished !== undefined) {
          params.append('published', filters.isPublished.toString());
        }
        if (filters.tags?.length) {
          params.append('tags', filters.tags.join(','));
        }
      }

      const queryString = params.toString();
      const url = `/api/v1/courses${queryString ? `?${queryString}` : ''}`;

      const response = await api.get(url);
      courses.value = response.data || [];
      lastFetchTime.value = new Date();

      eventBus.emit('courses:updated', courses.value);

      // Persist to localStorage
      StatePersistence.saveState('courses', {
        courses: courses.value,
        lastFetchTime: lastFetchTime.value
      });

    } catch (err) {
      error.value = 'Failed to fetch courses';
      handleStoreError(err, 'fetchCourses');
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const fetchCourse = async (courseId: string): Promise<Course> => {
    try {
      const response = await api.get(`/api/v1/courses/${courseId}`);
      const course = response.data;

      // Update in courses array if it exists
      const index = courses.value.findIndex(c => c.id === courseId);
      if (index !== -1) {
        courses.value[index] = course;
      } else {
        courses.value.push(course);
      }

      currentCourse.value = course;
      return course;

    } catch (err) {
      handleStoreError(err, 'fetchCourse');
      throw err;
    }
  };

  const createCourse = async (courseData: CreateCourseRequest): Promise<Course> => {
    try {
      loading.value = true;
      error.value = null;

      const response = await api.post('/api/v1/courses', courseData);
      const newCourse = response.data;

      // Add to local state
      courses.value.push(newCourse);
      currentCourse.value = newCourse;

      eventBus.emit('course:created', newCourse);

      return newCourse;

    } catch (err) {
      error.value = 'Failed to create course';
      handleStoreError(err, 'createCourse');
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const updateCourse = async (courseId: string, updates: UpdateCourseRequest): Promise<Course> => {
    try {
      const response = await api.put(`/api/v1/courses/${courseId}`, updates);
      const updatedCourse = response.data;

      // Update local state
      const index = courses.value.findIndex(c => c.id === courseId);
      if (index !== -1) {
        courses.value[index] = updatedCourse;
      }

      if (currentCourse.value?.id === courseId) {
        currentCourse.value = updatedCourse;
      }

      eventBus.emit('course:updated', updatedCourse);

      return updatedCourse;

    } catch (err) {
      handleStoreError(err, 'updateCourse');
      throw err;
    }
  };

  const deleteCourse = async (courseId: string): Promise<void> => {
    try {
      await api.delete(`/api/v1/courses/${courseId}`);

      // Remove from local state
      courses.value = courses.value.filter(c => c.id !== courseId);

      if (currentCourse.value?.id === courseId) {
        currentCourse.value = null;
      }

      eventBus.emit('course:deleted', courseId);

    } catch (err) {
      handleStoreError(err, 'deleteCourse');
      throw err;
    }
  };

  const publishCourse = async (courseId: string): Promise<Course> => {
    try {
      const response = await api.post(`/api/v1/courses/${courseId}/publish`);
      const publishedCourse = response.data;

      // Update local state
      const index = courses.value.findIndex(c => c.id === courseId);
      if (index !== -1) {
        courses.value[index] = publishedCourse;
      }

      if (currentCourse.value?.id === courseId) {
        currentCourse.value = publishedCourse;
      }

      eventBus.emit('course:published', publishedCourse);

      return publishedCourse;

    } catch (err) {
      handleStoreError(err, 'publishCourse');
      throw err;
    }
  };

  const unpublishCourse = async (courseId: string): Promise<Course> => {
    try {
      const response = await api.post(`/api/v1/courses/${courseId}/unpublish`);
      const unpublishedCourse = response.data;

      // Update local state
      const index = courses.value.findIndex(c => c.id === courseId);
      if (index !== -1) {
        courses.value[index] = unpublishedCourse;
      }

      if (currentCourse.value?.id === courseId) {
        currentCourse.value = unpublishedCourse;
      }

      eventBus.emit('course:unpublished', unpublishedCourse);

      return unpublishedCourse;

    } catch (err) {
      handleStoreError(err, 'unpublishCourse');
      throw err;
    }
  };

  // Course Templates
  const fetchCourseTemplates = async (): Promise<void> => {
    try {
      const response = await api.get('/api/course-templates');
      courseTemplates.value = response.data || [];

    } catch (err) {
      handleStoreError(err, 'fetchCourseTemplates');
      throw err;
    }
  };

  const applyCourseTemplate = async (courseId: string, templateId: string): Promise<Course> => {
    try {
      const response = await api.post(`/api/v1/courses/${courseId}/apply-template`, {
        templateId
      });
      const updatedCourse = response.data;

      // Update local state
      const index = courses.value.findIndex(c => c.id === courseId);
      if (index !== -1) {
        courses.value[index] = updatedCourse;
      }

      if (currentCourse.value?.id === courseId) {
        currentCourse.value = updatedCourse;
      }

      eventBus.emit('course:template:applied', { courseId, templateId, course: updatedCourse });

      return updatedCourse;

    } catch (err) {
      handleStoreError(err, 'applyCourseTemplate');
      throw err;
    }
  };

  const createCourseTemplate = async (course: Course, templateName: string): Promise<CourseTemplate> => {
    try {
      const response = await api.post('/api/course-templates', {
        name: templateName,
        courseId: course.id,
        structure: course.structure,
        moduleAssignments: course.moduleAssignments
      });
      const newTemplate = response.data;

      courseTemplates.value.push(newTemplate);

      eventBus.emit('course:template:created', newTemplate);

      return newTemplate;

    } catch (err) {
      handleStoreError(err, 'createCourseTemplate');
      throw err;
    }
  };

  // Enrollment Management
  const fetchUserEnrollments = async (userId?: string): Promise<void> => {
    try {
      const url = userId ? `/api/enrollments?userId=${userId}` : '/api/enrollments';
      const response = await api.get(url);
      userEnrollments.value = response.data || [];

      eventBus.emit('enrollments:updated', userEnrollments.value);

    } catch (err) {
      handleStoreError(err, 'fetchUserEnrollments');
      throw err;
    }
  };

  const enrollInCourse = async (courseId: string): Promise<CourseEnrollment> => {
    try {
      const response = await api.post(`/api/v1/courses/${courseId}/enroll`);
      const enrollment = response.data;

      userEnrollments.value.push(enrollment);

      eventBus.emit('course:enrolled', enrollment);

      return enrollment;

    } catch (err) {
      handleStoreError(err, 'enrollInCourse');
      throw err;
    }
  };

  const unenrollFromCourse = async (courseId: string): Promise<void> => {
    try {
      await api.delete(`/api/v1/courses/${courseId}/enroll`);

      userEnrollments.value = userEnrollments.value.filter(e => e.courseId !== courseId);

      eventBus.emit('course:unenrolled', courseId);

    } catch (err) {
      handleStoreError(err, 'unenrollFromCourse');
      throw err;
    }
  };

  // Utility methods
  const getCourseById = (courseId: string): Course | null => {
    return courses.value.find(course => course.id === courseId) || null;
  };

  const getEnrollmentByCourseId = (courseId: string): CourseEnrollment | null => {
    return userEnrollments.value.find(e => e.courseId === courseId) || null;
  };

  const isEnrolledInCourse = (courseId: string): boolean => {
    return userEnrollments.value.some(e => e.courseId === courseId);
  };

  const searchCourses = (query: string): Course[] => {
    if (!query.trim()) return courses.value;

    const searchTerm = query.toLowerCase().trim();

    return courses.value.filter(course =>
      course.title.toLowerCase().includes(searchTerm) ||
      course.description.toLowerCase().includes(searchTerm) ||
      course.category.toLowerCase().includes(searchTerm) ||
      course.tags.some(tag => tag.toLowerCase().includes(searchTerm))
    );
  };

  const initialize = async (): Promise<void> => {
    try {
      // Load persisted state
      const persistedState = StatePersistence.loadState<any>('courses');
      if (persistedState) {
        courses.value = persistedState.courses || [];
        lastFetchTime.value = persistedState.lastFetchTime ? new Date(persistedState.lastFetchTime) : null;
      }

      // Fetch fresh data
      await Promise.all([
        fetchCourses(),
        fetchCourseTemplates(),
        fetchUserEnrollments()
      ]);

    } catch (err) {
      handleStoreError(err, 'initialize');
    }
  };

  // Event listeners for cross-store communication
  eventBus.on('assignment:created', (assignment: any) => {
    // Update course module count if assignment is for a course
    if (assignment.targetType === 'course') {
      const course = getCourseById(assignment.targetId);
      if (course) {
        course.moduleCount += 1;
        // Recalculate estimated duration based on assigned modules
        // This would need module data to calculate properly
      }
    }
  });

  eventBus.on('assignment:deleted', (assignment: any) => {
    // Update course module count if assignment was for a course
    if (assignment.targetType === 'course') {
      const course = getCourseById(assignment.targetId);
      if (course && course.moduleCount > 0) {
        course.moduleCount -= 1;
      }
    }
  });

  return {
    // State
    courses,
    currentCourse,
    courseTemplates,
    userEnrollments,
    loading,
    error,
    lastFetchTime,

    // Computed
    publishedCourses,
    draftCourses,
    coursesByCategory,
    enrolledCourses,
    availableCourses,
    courseCategories,
    totalCourses,

    // Actions
    fetchCourses,
    fetchCourse,
    createCourse,
    updateCourse,
    deleteCourse,
    publishCourse,
    unpublishCourse,
    fetchCourseTemplates,
    applyCourseTemplate,
    createCourseTemplate,
    fetchUserEnrollments,
    enrollInCourse,
    unenrollFromCourse,
    getCourseById,
    getEnrollmentByCourseId,
    isEnrolledInCourse,
    searchCourses,
    initialize
  };
});
