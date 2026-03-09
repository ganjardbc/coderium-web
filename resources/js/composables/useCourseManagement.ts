/**
 * Course Management Composable
 *
 * Provides reactive data management and methods for course creation, editing,
 * and management with module assignment support.
 */

import { ref, computed, watch, type Ref, type ComputedRef } from 'vue';
import { debounce } from 'lodash-es';
import {
  Course,
  CertificateTemplate,
  CreateCourseRequest,
  UpdateCourseRequest,
  ValidationResult,
  ModuleAssignment,
  CourseSection,
  CourseFilters
} from '@/types/enhanced-classroom';
import { useApi } from './useApi';
import { globalLoading } from './useLoading';
import { globalErrorHandler } from './useErrorHandler';

export interface CourseManagementComposable {
  // Reactive Data
  courses: Ref<Course[]>;
  currentCourse: Ref<Course | null>;
  courseTemplates: Ref<CertificateTemplate[]>;
  loading: Ref<boolean>;
  error: Ref<string | null>;

  // Methods
  fetchCourses: (filters?: CourseFilters) => Promise<Course[]>;
  fetchCourse: (id: string) => Promise<Course>;
  createCourse: (courseData: CreateCourseRequest) => Promise<Course>;
  updateCourse: (id: string, updates: UpdateCourseRequest) => Promise<Course>;
  deleteCourse: (id: string) => Promise<void>;
  publishCourse: (id: string) => Promise<Course>;
  validateCourse: (course: Course) => ValidationResult;

  // Course Structure
  addModuleToSection: (courseId: string, sectionId: string, moduleId: string) => Promise<void>;
  removeModuleFromSection: (courseId: string, sectionId: string, assignmentId: string) => Promise<void>;
  reorderModulesInSection: (courseId: string, sectionId: string, assignments: ModuleAssignment[]) => Promise<void>;

  // Templates
  applyTemplate: (courseId: string, templateId: string) => Promise<Course>;
  createTemplate: (course: Course, templateName: string) => Promise<CertificateTemplate>;

  // Computed
  publishedCourses: ComputedRef<Course[]>;
  draftCourses: ComputedRef<Course[]>;
  coursesByCategory: ComputedRef<Record<string, Course[]>>;
}

// Cache configuration
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  expiresAt: number;
}

class CourseCache {
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
const courseCache = new CourseCache();

export function useCourseManagement(): CourseManagementComposable {
  // Reactive state
  const courses = ref<Course[]>([]);
  const currentCourse = ref<Course | null>(null);
  const courseTemplates = ref<CertificateTemplate[]>([]);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  // API and utilities
  const { api } = useApi();
  const { setLoading } = globalLoading;
  const { handleError } = globalErrorHandler;

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

    // Sort courses within each category by creation date
    Object.keys(grouped).forEach(category => {
      grouped[category].sort((a, b) =>
        new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
      );
    });

    return grouped;
  });

  // Main methods
  const fetchCourses = async (filters?: CourseFilters): Promise<Course[]> => {
    const loadingKey = 'fetchCourses';

    try {
      setLoading(loadingKey, true);
      loading.value = true;
      error.value = null;

      // Create cache key based on filters
      const cacheKey = `courses:${JSON.stringify(filters || {})}`;

      // Check cache first
      const cachedCourses = courseCache.get<Course[]>(cacheKey);
      if (cachedCourses) {
        courses.value = cachedCourses;
        return cachedCourses;
      }

      // Build query parameters
      const params = new URLSearchParams();
      if (filters) {
        if (filters.categories && filters.categories.length > 0) {
          params.append('categories', filters.categories.join(','));
        }
        if (filters.difficulties && filters.difficulties.length > 0) {
          params.append('difficulties', filters.difficulties.join(','));
        }
        if (filters.isPublished !== undefined) {
          params.append('published', filters.isPublished.toString());
        }
        if (filters.instructorId) {
          params.append('instructor_id', filters.instructorId);
        }
      }

      const queryString = params.toString();
      const url = `/api/v1/courses${queryString ? `?${queryString}` : ''}`;

      const response = await api.get(url);
      const fetchedCourses = response.data || [];

      // Cache the results
      courseCache.set(cacheKey, fetchedCourses, CACHE_DURATION);

      courses.value = fetchedCourses;
      return fetchedCourses;

    } catch (err) {
      handleError(err, 'Fetch Courses');
      error.value = 'Failed to fetch courses';
      throw err;
    } finally {
      setLoading(loadingKey, false);
      loading.value = false;
    }
  };

  const fetchCourse = async (id: string): Promise<Course> => {
    const loadingKey = `fetchCourse:${id}`;

    try {
      setLoading(loadingKey, true);

      // Check cache first
      const cacheKey = `course:${id}`;
      const cachedCourse = courseCache.get<Course>(cacheKey);
      if (cachedCourse) {
        currentCourse.value = cachedCourse;
        return cachedCourse;
      }

      const response = await api.get(`/api/v1/courses/${id}`);
      const course = response.data;

      // Cache the course
      courseCache.set(cacheKey, course, CACHE_DURATION);

      currentCourse.value = course;
      return course;

    } catch (err) {
      handleError(err, 'Fetch Course');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const createCourse = async (courseData: CreateCourseRequest): Promise<Course> => {
    const loadingKey = 'createCourse';

    try {
      setLoading(loadingKey, true);
      error.value = null;

      const response = await api.post('/api/v1/courses', courseData);
      const newCourse = response.data;

      // Add to local state
      courses.value.unshift(newCourse);

      // Invalidate relevant cache entries
      courseCache.invalidatePattern('^courses:');

      return newCourse;

    } catch (err) {
      handleError(err, 'Create Course');
      error.value = 'Failed to create course';
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const updateCourse = async (id: string, updates: UpdateCourseRequest): Promise<Course> => {
    const loadingKey = `updateCourse:${id}`;

    try {
      setLoading(loadingKey, true);
      error.value = null;

      const response = await api.put(`/api/v1/courses/${id}`, updates);
      const updatedCourse = response.data;

      // Update local state
      const index = courses.value.findIndex(course => course.id === id);
      if (index !== -1) {
        courses.value[index] = updatedCourse;
      }

      // Update current course if it's the same
      if (currentCourse.value?.id === id) {
        currentCourse.value = updatedCourse;
      }

      // Invalidate cache
      courseCache.invalidatePattern(`^course:${id}`);
      courseCache.invalidatePattern('^courses:');

      return updatedCourse;

    } catch (err) {
      handleError(err, 'Update Course');
      error.value = 'Failed to update course';
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const deleteCourse = async (id: string): Promise<void> => {
    const loadingKey = `deleteCourse:${id}`;

    try {
      setLoading(loadingKey, true);
      error.value = null;

      await api.delete(`/api/v1/courses/${id}`);

      // Remove from local state
      courses.value = courses.value.filter(course => course.id !== id);

      // Clear current course if it's the deleted one
      if (currentCourse.value?.id === id) {
        currentCourse.value = null;
      }

      // Invalidate cache
      courseCache.invalidatePattern(`^course:${id}`);
      courseCache.invalidatePattern('^courses:');

    } catch (err) {
      handleError(err, 'Delete Course');
      error.value = 'Failed to delete course';
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const publishCourse = async (id: string): Promise<Course> => {
    const loadingKey = `publishCourse:${id}`;

    try {
      setLoading(loadingKey, true);
      error.value = null;

      const response = await api.post(`/api/v1/courses/${id}/publish`);
      const publishedCourse = response.data;

      // Update local state
      const index = courses.value.findIndex(course => course.id === id);
      if (index !== -1) {
        courses.value[index] = publishedCourse;
      }

      // Update current course if it's the same
      if (currentCourse.value?.id === id) {
        currentCourse.value = publishedCourse;
      }

      // Invalidate cache
      courseCache.invalidatePattern(`^course:${id}`);
      courseCache.invalidatePattern('^courses:');

      return publishedCourse;

    } catch (err) {
      handleError(err, 'Publish Course');
      error.value = 'Failed to publish course';
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const validateCourse = (course: Course): ValidationResult => {
    const errors: any[] = [];
    const warnings: any[] = [];

    // Required fields validation
    if (!course.title?.trim()) {
      errors.push({
        field: 'title',
        message: 'Course title is required',
        code: 'REQUIRED_FIELD'
      });
    }

    if (!course.description?.trim()) {
      errors.push({
        field: 'description',
        message: 'Course description is required',
        code: 'REQUIRED_FIELD'
      });
    }

    if (!course.category?.trim()) {
      errors.push({
        field: 'category',
        message: 'Course category is required',
        code: 'REQUIRED_FIELD'
      });
    }

    // Module assignments validation
    if (course.moduleAssignments.length === 0) {
      warnings.push({
        field: 'moduleAssignments',
        message: 'Course has no module assignments',
        code: 'NO_MODULES',
        suggestion: 'Add at least one module to the course'
      });
    }

    // Duration validation
    if (course.estimatedDuration <= 0) {
      warnings.push({
        field: 'estimatedDuration',
        message: 'Course duration is not calculated',
        code: 'INVALID_DURATION',
        suggestion: 'Add modules to automatically calculate duration'
      });
    }

    return {
      isValid: errors.length === 0,
      errors,
      warnings
    };
  };

  // Course Structure Methods
  const addModuleToSection = async (courseId: string, sectionId: string, moduleId: string): Promise<void> => {
    const loadingKey = `addModuleToSection:${courseId}:${sectionId}`;

    try {
      setLoading(loadingKey, true);

      await api.post(`/api/v1/courses/${courseId}/sections/${sectionId}/modules`, {
        moduleId
      });

      // Refresh course data
      await fetchCourse(courseId);

    } catch (err) {
      handleError(err, 'Add Module to Section');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const removeModuleFromSection = async (courseId: string, sectionId: string, assignmentId: string): Promise<void> => {
    const loadingKey = `removeModuleFromSection:${courseId}:${sectionId}`;

    try {
      setLoading(loadingKey, true);

      await api.delete(`/api/v1/courses/${courseId}/sections/${sectionId}/assignments/${assignmentId}`);

      // Refresh course data
      await fetchCourse(courseId);

    } catch (err) {
      handleError(err, 'Remove Module from Section');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const reorderModulesInSection = async (courseId: string, sectionId: string, assignments: ModuleAssignment[]): Promise<void> => {
    const loadingKey = `reorderModulesInSection:${courseId}:${sectionId}`;

    try {
      setLoading(loadingKey, true);

      await api.put(`/api/v1/courses/${courseId}/sections/${sectionId}/reorder`, {
        assignments: assignments.map((assignment, index) => ({
          id: assignment.id,
          order: index + 1
        }))
      });

      // Refresh course data
      await fetchCourse(courseId);

    } catch (err) {
      handleError(err, 'Reorder Modules in Section');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  // Template Methods
  const applyTemplate = async (courseId: string, templateId: string): Promise<Course> => {
    const loadingKey = `applyTemplate:${courseId}`;

    try {
      setLoading(loadingKey, true);

      const response = await api.post(`/api/v1/courses/${courseId}/apply-template`, {
        templateId
      });

      const updatedCourse = response.data;

      // Update local state
      const index = courses.value.findIndex(course => course.id === courseId);
      if (index !== -1) {
        courses.value[index] = updatedCourse;
      }

      if (currentCourse.value?.id === courseId) {
        currentCourse.value = updatedCourse;
      }

      // Invalidate cache
      courseCache.invalidatePattern(`^course:${courseId}`);

      return updatedCourse;

    } catch (err) {
      handleError(err, 'Apply Template');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  const createTemplate = async (course: Course, templateName: string): Promise<CertificateTemplate> => {
    const loadingKey = 'createTemplate';

    try {
      setLoading(loadingKey, true);

      const response = await api.post('/api/course-templates', {
        name: templateName,
        courseId: course.id
      });

      const newTemplate = response.data;

      // Add to local state
      courseTemplates.value.push(newTemplate);

      return newTemplate;

    } catch (err) {
      handleError(err, 'Create Template');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  // Fetch templates
  const fetchTemplates = async (): Promise<CertificateTemplate[]> => {
    const loadingKey = 'fetchTemplates';

    try {
      setLoading(loadingKey, true);

      const response = await api.get('/api/course-templates');
      const templates = response.data || [];

      courseTemplates.value = templates;
      return templates;

    } catch (err) {
      handleError(err, 'Fetch Templates');
      throw err;
    } finally {
      setLoading(loadingKey, false);
    }
  };

  return {
    // Reactive data
    courses,
    currentCourse,
    courseTemplates,
    loading,
    error,

    // Methods
    fetchCourses,
    fetchCourse,
    createCourse,
    updateCourse,
    deleteCourse,
    publishCourse,
    validateCourse,

    // Course Structure
    addModuleToSection,
    removeModuleFromSection,
    reorderModulesInSection,

    // Templates
    applyTemplate,
    createTemplate,

    // Computed properties
    publishedCourses,
    draftCourses,
    coursesByCategory
  };
}

// Global instance for shared state across components
let globalCourseManagement: CourseManagementComposable | null = null;

export function useGlobalCourseManagement(): CourseManagementComposable {
  if (!globalCourseManagement) {
    globalCourseManagement = useCourseManagement();
  }
  return globalCourseManagement;
}
