/**
 * Enhanced Classroom TypeScript Definitions
 *
 * Complete type definitions for the enhanced classroom frontend system,
 * including standalone modules, assignments, courses, and unified progress tracking.
 */

// Base interfaces
export interface User {
  id: string;
  name: string;
  email: string;
  role: 'student' | 'instructor' | 'admin';
  avatar?: string;
  trackEnrollments: TrackEnrollment[];
  courseEnrollments: CourseEnrollment[];
  preferences: UserPreferences;
  learningAnalytics: UserLearningAnalytics;
  progress: LearningProgress[];
  achievements: Achievement[];
}

export interface UserPreferences {
  defaultView: 'grid' | 'list';
  learningPathTypes: ('track' | 'course')[];
  difficultyPreference: string[];
  categoryPreferences: string[];
  notificationSettings: NotificationSettings;
  accessibilitySettings: AccessibilitySettings;
}

export interface NotificationSettings {
  email: boolean;
  push: boolean;
  inApp: boolean;
  achievements: boolean;
  progress: boolean;
  assignments: boolean;
}

export interface AccessibilitySettings {
  highContrast: boolean;
  largeText: boolean;
  reducedMotion: boolean;
  screenReader: boolean;
}

export interface UserLearningAnalytics {
  totalTimeSpent: number;
  averageSessionDuration: number;
  completionRate: number;
  streakCount: number;
  preferredLearningTimes: string[];
  strongCategories: string[];
  improvementAreas: string[];
}

// Standalone Module System
export interface StandaloneModule {
  id: string;
  title: string;
  description: string;
  content: string;
  estimatedDuration: number; // minutes
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  category: string;
  tags: string[];
  prerequisites: string[];
  learningObjectives: string[];
  isReusable: boolean;
  isPublished: boolean;
  createdAt: Date;
  updatedAt: Date;
  author: User;
  lessons: Lesson[];

  // Standalone module specific fields
  assignmentCount: number;
  usageAnalytics: ModuleUsageAnalytics;
  assignmentHistory: ModuleAssignment[];
  averageCompletionTime: number;
  completionRate: number;
  rating: number;
  reviewCount: number;
}

export interface ModuleUsageAnalytics {
  totalAssignments: number;
  activeAssignments: number;
  completionsByMonth: Record<string, number>;
  averageScore: number;
  popularityRank: number;
  assignmentsByTarget: {
    tracks: number;
    courses: number;
    levels: number;
  };
}

export interface ModuleAssignment {
  id: string;
  moduleId: string;
  targetType: 'course' | 'track' | 'level';
  targetId: string;
  order: number;
  isRequired: boolean;
  isActive: boolean;
  unlockConditions?: UnlockCondition[];
  customization?: AssignmentCustomization;
  createdAt: Date;
  updatedAt: Date;
  createdBy: string;

  // Relationships
  module: StandaloneModule;
  target: AssignmentTarget;

  // Analytics
  completionRate: number;
  averageScore: number;
  timeSpentAverage: number;
}

export interface AssignmentTarget {
  id: string;
  type: 'course' | 'track' | 'level';
  title: string;
  description?: string;
  currentAssignments: ModuleAssignment[];
  maxModules?: number;
  allowDuplicateModules: boolean;
}

export interface UnlockCondition {
  type: 'prerequisite' | 'score' | 'time' | 'completion';
  value: any;
  description: string;
}

export interface AssignmentCustomization {
  customTitle?: string;
  customDescription?: string;
  customDuration?: number;
  additionalResources?: string[];
}

// Course System
export interface Course {
  id: string;
  title: string;
  description: string;
  thumbnail?: string;
  category: string;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  estimatedDuration: number; // minutes (calculated from assigned modules)
  prerequisites: string[];
  tags: string[];
  isPublished: boolean;
  createdAt: Date;
  updatedAt: Date;
  instructor: User;

  // Module assignments (standalone architecture)
  moduleAssignments: ModuleAssignment[];
  moduleCount: number;

  // Course metadata
  enrollmentCount: number;
  completionCount: number;
  rating: number;
  reviewCount: number;
  certificateTemplate?: CertificateTemplate;

  // Course structure
  structure: CourseStructure;
  settings: CourseSettings;
}

export interface CourseStructure {
  sections?: CourseSection[];
  linearProgression: boolean;
  allowSkipping: boolean;
  completionRequirements: CompletionRequirement[];
}

export interface CourseSection {
  id: string;
  title: string;
  description?: string;
  moduleAssignments: ModuleAssignment[];
  order: number;
  isRequired: boolean;
}

export interface CourseSettings {
  allowSelfEnrollment: boolean;
  requireApproval: boolean;
  maxEnrollments?: number;
  enrollmentDeadline?: Date;
  accessDuration?: number; // days
}

export interface CompletionRequirement {
  type: 'all_modules' | 'percentage' | 'specific_modules' | 'assessment_score';
  value: number | string[];
  description: string;
}

export interface CourseEnrollment {
  id: string;
  userId: string;
  courseId: string;
  enrolledAt: Date;
  completedAt?: Date;
  progress: number; // 0-100
  lastAccessedAt: Date;
  currentModuleId?: string;
  certificateIssued?: boolean;
  certificateIssuedAt?: Date;

  // Enhanced progress tracking
  moduleProgress: Record<string, ModuleProgress>;
  timeSpent: number; // minutes
  completionStreak: number;
  achievements: Achievement[];
}

export interface CourseTemplate {
  id: string;
  name: string;
  description: string;
  structure: CourseStructure;
  moduleAssignments: ModuleAssignment[];
  createdAt: Date;
  updatedAt: Date;
}

// Track System (Enhanced)
export interface Track {
  id: string;
  title: string;
  description: string;
  thumbnail?: string;
  category: string;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  isPublished: boolean;
  createdAt: Date;
  updatedAt: Date;

  // Enhanced with module assignment support
  levels: Level[];
  totalModuleCount: number; // calculated from all level assignments
  estimatedDuration: number; // calculated from assigned modules

  // Unified learning path fields
  enrollmentCount: number;
  completionCount: number;
  rating: number;
  tags: string[];
}

export interface Level {
  id: string;
  trackId: string;
  title: string;
  description: string;
  order: number;
  isRequired: boolean;

  // Enhanced with standalone module assignments
  moduleAssignments: ModuleAssignment[];
  estimatedDuration: number; // calculated from assigned modules

  // Level progress and requirements
  unlockConditions?: UnlockCondition[];
  completionRequirements: CompletionRequirement[];
}

export interface TrackEnrollment {
  id: string;
  userId: string;
  trackId: string;
  enrolledAt: Date;
  completedAt?: Date;
  progress: number; // 0-100
  lastAccessedAt: Date;
  currentLevelId?: string;
  certificateIssued?: boolean;
  certificateIssuedAt?: Date;

  // Enhanced progress tracking
  levelProgress: Record<string, number>;
  moduleProgress: Record<string, ModuleProgress>;
  timeSpent: number; // minutes
  completionStreak: number;
  achievements: Achievement[];
}

// Lesson System
export interface Lesson {
  id: string;
  moduleId: string;
  title: string;
  content: string;
  type: 'text' | 'video' | 'interactive' | 'assessment';
  order: number;
  estimatedDuration: number; // minutes
  isRequired: boolean;
  resources?: LessonResource[];
  createdAt: Date;
  updatedAt: Date;
}

export interface LessonResource {
  id: string;
  type: 'file' | 'link' | 'video' | 'document';
  title: string;
  url: string;
  description?: string;
}

export interface LessonProgress {
  id: string;
  userId: string;
  lessonId: string;
  startedAt?: Date;
  completedAt?: Date;
  progress: number; // 0-100
  timeSpent: number; // minutes
  lastAccessedAt: Date;
}

// Progress and Analytics
export interface LearningProgress {
  id: string;
  userId: string;
  targetType: 'course' | 'track' | 'module' | 'lesson';
  targetId: string;
  progressType: 'completion' | 'time' | 'score' | 'engagement';
  value: number;
  maxValue: number;
  timestamp: Date;
  sessionId?: string;
  metadata?: Record<string, any>;

  // Real-time sync fields
  syncStatus: 'pending' | 'synced' | 'conflict';
  lastSyncAt?: Date;
  version: number;
}

export interface ModuleProgress {
  moduleId: string;
  assignmentId: string;
  startedAt?: Date;
  completedAt?: Date;
  progress: number; // 0-100
  timeSpent: number; // minutes
  score?: number;
  attempts: number;
  lastAccessedAt: Date;
  lessonProgress: Record<string, LessonProgress>;
}

export interface ProgressMetrics {
  totalTimeSpent: number;
  averageSessionDuration: number;
  completionRate: number;
  streakCount: number;
  activeDays: number;
  learningVelocity: number;
  strongAreas: string[];
  improvementAreas: string[];
}

// Achievement System
export interface Achievement {
  id: string;
  title: string;
  description: string;
  icon: string;
  type: 'completion' | 'streak' | 'score' | 'time' | 'social';
  criteria: AchievementCriteria;
  points: number;
  rarity: 'common' | 'uncommon' | 'rare' | 'epic' | 'legendary';
  createdAt: Date;
}

export interface AchievementCriteria {
  type: string;
  value: number | string;
  description: string;
}

export interface UserAchievement {
  id: string;
  userId: string;
  achievementId: string;
  earnedAt: Date;
  progress: number;
  isCompleted: boolean;
}

// Certificate System
export interface Certificate {
  id: string;
  userId: string;
  targetType: 'course' | 'track';
  targetId: string;
  templateId: string;
  issuedAt: Date;
  expiresAt?: Date;
  certificateUrl: string;
  verificationCode: string;
}

export interface CertificateTemplate {
  id: string;
  name: string;
  design: string;
  fields: CertificateField[];
  createdAt: Date;
  updatedAt: Date;
}

export interface CertificateField {
  name: string;
  type: 'text' | 'date' | 'signature' | 'logo';
  position: { x: number; y: number };
  style: Record<string, any>;
}

// Assignment Workflow
export interface AssignmentConflict {
  id: string;
  type: 'duplicate' | 'prerequisite' | 'circular_dependency' | 'capacity_exceeded';
  moduleId: string;
  targetId: string;
  conflictingAssignmentId?: string;
  description: string;
  severity: 'warning' | 'error';
  resolutionOptions: ConflictResolution[];
}

export interface ConflictResolution {
  id: string;
  action: 'replace' | 'skip' | 'modify' | 'force';
  description: string;
  consequences: string[];
}

export interface BulkAssignmentOperation {
  id: string;
  moduleIds: string[];
  targetIds: string[];
  targetType: 'course' | 'track' | 'level';
  status: 'pending' | 'in_progress' | 'completed' | 'failed' | 'cancelled';
  progress: number; // 0-100
  startedAt: Date;
  completedAt?: Date;
  results: BulkAssignmentResult[];
  errors: BulkAssignmentError[];
}

export interface BulkAssignmentResult {
  moduleId: string;
  targetId: string;
  assignmentId?: string;
  status: 'success' | 'failed' | 'skipped';
  reason?: string;
}

export interface BulkAssignmentError {
  moduleId: string;
  targetId: string;
  error: string;
  code: string;
}

// Drag and Drop
export interface DragDropState {
  isDragging: boolean;
  draggedItem?: {
    type: 'module' | 'assignment';
    id: string;
    data: any;
  };
  dropZones: DropZone[];
  validDropTargets: string[];
  currentDropTarget?: string;
  dragOffset?: { x: number; y: number };
}

export interface DropZone {
  id: string;
  type: 'course' | 'track' | 'level' | 'section';
  accepts: ('module' | 'assignment')[];
  isActive: boolean;
  isValid: boolean;
  position: { x: number; y: number; width: number; height: number };
  maxItems?: number;
  currentItems?: number;
}

// Search and Filtering
export interface ModuleFilters {
  categories: string[];
  difficulties: string[];
  tags: string[];
  durationRange: { min: number; max: number };
  assignmentStatus: 'assigned' | 'unassigned' | 'all';
  usageRange: { min: number; max: number };
  rating: { min: number; max: number };
}

export interface CourseFilters {
  categories?: string[];
  difficulties?: string[];
  tags?: string[];
  isPublished?: boolean;
  instructorId?: string;
}

export interface SearchFilters {
  query: string;
  type: ('track' | 'course' | 'module')[];
  categories: string[];
  difficulties: string[];
  tags: string[];
  sortBy: 'relevance' | 'rating' | 'popularity' | 'recent' | 'alphabetical';
  sortOrder: 'asc' | 'desc';
}

export interface SearchResult {
  id: string;
  type: 'track' | 'course' | 'module';
  title: string;
  description: string;
  thumbnail?: string;
  relevanceScore: number;
  matchedFields: string[];
  highlightedText: Record<string, string>;
}

// Unified Learning Path
export interface DragEvent extends Event {
  dataTransfer: DataTransfer | null;
}

export interface WebSocketMessage {
  type: string;
  channel: string;
  data: any;
  timestamp: Date;
}

export interface SyncOperation {
  id: string;
  type: 'create' | 'update' | 'delete';
  entityType: string;
  entityId: string;
  data: any;
  status: 'pending' | 'synced' | 'failed';
  timestamp: Date;
  retryCount: number;
}

export interface DataConflict {
  id: string;
  entityType: string;
  entityId: string;
  localVersion: any;
  remoteVersion: any;
  conflictType: 'concurrent_modification' | 'version_mismatch';
  timestamp: Date;
}

export interface WebSocketConfig {
  url: string;
  channels: string[];
  reconnectInterval: number;
  maxReconnectAttempts: number;
}

export interface Milestone {
  id: string;
  title: string;
  description: string;
  targetValue: number;
  currentValue: number;
  isCompleted: boolean;
  completedAt?: Date;
  type: 'progress' | 'time' | 'achievement';
}

export interface LearningPath {
  id: string;
  type: 'course' | 'track';
  title: string;
  description: string;
  thumbnail?: string;
  category: string;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  estimatedDuration: number;
  moduleCount: number;

  // Unified progress and enrollment
  progress?: number;
  enrollmentStatus?: 'enrolled' | 'completed' | 'available';
  lastAccessedAt?: Date;

  // Unified metadata
  rating: number;
  enrollmentCount: number;
  completionRate: number;
  tags: string[];
}

export interface LearningPath {
  id: string;
  type: 'course' | 'track';
  title: string;
  description: string;
  thumbnail?: string;
  category: string;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  estimatedDuration: number;
  moduleCount: number;

  // Unified progress and enrollment
  progress?: number;
  enrollmentStatus?: 'enrolled' | 'completed' | 'available';
  lastAccessedAt?: Date;

  // Unified metadata
  rating: number;
  enrollmentCount: number;
  completionRate: number;
  tags: string[];
}

// API Request/Response Types
export interface CreateModuleAssignmentRequest {
  moduleId: string;
  targetType: 'course' | 'track' | 'level';
  targetId: string;
  order: number;
  isRequired: boolean;
  customization?: AssignmentCustomization;
}

export interface UpdateModuleAssignmentRequest {
  order?: number;
  isRequired?: boolean;
  isActive?: boolean;
  customization?: AssignmentCustomization;
}

export interface BulkAssignmentRequest {
  moduleIds: string[];
  targetIds: string[];
  targetType: 'course' | 'track' | 'level';
  isRequired?: boolean;
  customization?: AssignmentCustomization;
}

export interface CreateCourseRequest {
  title: string;
  description: string;
  category: string;
  difficulty: 'beginner' | 'intermediate' | 'advanced';
  tags: string[];
  thumbnail?: string;
  structure?: Partial<CourseStructure>;
  settings?: Partial<CourseSettings>;
}

export interface UpdateCourseRequest {
  title?: string;
  description?: string;
  category?: string;
  difficulty?: 'beginner' | 'intermediate' | 'advanced';
  tags?: string[];
  thumbnail?: string;
  structure?: Partial<CourseStructure>;
  settings?: Partial<CourseSettings>;
}

export interface ProgressUpdateRequest {
  userId: string;
  targetType: 'course' | 'track' | 'module' | 'lesson';
  targetId: string;
  progressType: 'completion' | 'time' | 'score' | 'engagement';
  value: number;
  maxValue: number;
  metadata?: Record<string, any>;
}

// Validation
export interface ValidationResult {
  isValid: boolean;
  errors: ValidationError[];
  warnings: ValidationWarning[];
}

export interface ValidationError {
  field: string;
  message: string;
  code: string;
}

export interface ValidationWarning {
  field: string;
  message: string;
  code: string;
}
