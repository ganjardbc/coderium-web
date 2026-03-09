<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Save,
  Eye,
  Edit3,
  History,
  Users,
  Settings,
  Plus,
  X,
  GripVertical,
  AlertCircle,
  CheckCircle,
  Loader2,
  Upload,
  Undo,
  Redo,
  Clock,
  User,
  BookOpen,
  FileText,
  Trash2,
  Copy,
  MoreHorizontal,
  ChevronDown,
  ChevronRight
} from 'lucide-vue-next';
import { useCourseManagement } from '@/composables/useCourseManagement';
import { useModuleLibrary } from '@/composables/useModuleLibrary';
import { useDragDropAssignment } from '@/composables/useDragDropAssignment';
import RichTextEditor from './RichTextEditor.vue';
import ModuleCard from './ModuleCard.vue';
import DragDropAssignment from './DragDropAssignment.vue';
import {
  Course,
  UpdateCourseRequest,
  StandaloneModule,
  ModuleAssignment,
  CourseSection,
  ValidationResult
} from '@/types/enhanced-classroom';

interface Props {
  courseId: string;
  readonly?: boolean;
  showVersionHistory?: boolean;
}

interface Emits {
  (e: 'courseUpdated', course: Course): void;
  (e: 'moduleAssignmentChanged', assignments: ModuleAssignment[]): void;
  (e: 'publishingRequested', courseId: string): void;
}

const props = withDefaults(defineProps<Props>(), {
  readonly: false,
  showVersionHistory: false
});

const emit = defineEmits<Emits>();

// Composables
const {
  currentCourse,
  fetchCourse,
  updateCourse,
  publishCourse,
  validateCourse,
  addModuleToSection,
  removeModuleFromSection,
  reorderModulesInSection,
  loading: courseLoading,
  error: courseError
} = useCourseManagement();

const {
  modules,
  fetchModules,
  loading: moduleLoading
} = useModuleLibrary();

const {
  dragDropState,
  startDrag,
  endDrag,
  handleDrop,
  registerDropZone,
  unregisterDropZone
} = useDragDropAssignment();

// Editor state
const isEditing = ref(false);
const hasUnsavedChanges = ref(false);
const autoSaveEnabled = ref(true);
const lastSaved = ref<Date | null>(null);

// Form data
const editedCourse = ref<Course | null>(null);
const originalCourse = ref<Course | null>(null);

// Module management
const showModuleLibrary = ref(false);
const selectedSection = ref<string | null>(null);
const expandedSections = ref<Set<string>>(new Set());

// Version control
const versionHistory = ref<any[]>([]);
const showVersionDialog = ref(false);
const selectedVersion = ref<any | null>(null);

// Validation
const validation = ref<ValidationResult>({
  isValid: true,
  errors: [],
  warnings: []
});

// Collaboration
const collaborators = ref<any[]>([]);
const conflictResolution = ref<any[]>([]);

// Loading states
const saving = ref(false);
const publishing = ref(false);
const loadingHistory = ref(false);

// Auto-save timer
let autoSaveTimer: ReturnType<typeof setTimeout>;

// Computed properties
const canEdit = computed(() => {
  return !props.readonly && editedCourse.value && !courseLoading.value;
});

const canPublish = computed(() => {
  return canEdit.value &&
         editedCourse.value &&
         !editedCourse.value.isPublished &&
         validation.value.isValid;
});

const totalModules = computed(() => {
  if (!editedCourse.value) return 0;
  return editedCourse.value.moduleAssignments.length;
});

const totalDuration = computed(() => {
  if (!editedCourse.value) return 0;
  return editedCourse.value.moduleAssignments.reduce((total, assignment) => {
    return total + (assignment.module?.estimatedDuration || 0);
  }, 0);
});

const formattedDuration = computed(() => {
  const minutes = totalDuration.value;
  if (minutes >= 60) {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
  }
  return `${minutes}m`;
});

const sectionsWithModules = computed(() => {
  if (!editedCourse.value?.structure?.sections) return [];

  return editedCourse.value.structure.sections.map(section => ({
    ...section,
    moduleAssignments: editedCourse.value!.moduleAssignments.filter(
      assignment => assignment.targetId === section.id
    ).sort((a, b) => a.order - b.order)
  }));
});

const unassignedModules = computed(() => {
  if (!editedCourse.value) return [];

  const sectionIds = editedCourse.value.structure?.sections?.map(s => s.id) || [];
  return editedCourse.value.moduleAssignments.filter(
    assignment => !sectionIds.includes(assignment.targetId)
  ).sort((a, b) => a.order - b.order);
});

// Methods
const startEditing = () => {
  if (!currentCourse.value) return;

  isEditing.value = true;
  editedCourse.value = JSON.parse(JSON.stringify(currentCourse.value));
  originalCourse.value = JSON.parse(JSON.stringify(currentCourse.value));

  // Enable auto-save
  scheduleAutoSave();
};

const cancelEditing = () => {
  if (hasUnsavedChanges.value) {
    const confirmed = confirm('You have unsaved changes. Are you sure you want to cancel?');
    if (!confirmed) return;
  }

  isEditing.value = false;
  hasUnsavedChanges.value = false;
  editedCourse.value = null;
  originalCourse.value = null;

  clearAutoSave();
};

const saveChanges = async () => {
  if (!editedCourse.value || !canEdit.value) return;

  try {
    saving.value = true;

    const updates: UpdateCourseRequest = {
      title: editedCourse.value.title,
      description: editedCourse.value.description,
      category: editedCourse.value.category,
      difficulty: editedCourse.value.difficulty,
      prerequisites: editedCourse.value.prerequisites,
      tags: editedCourse.value.tags,
      thumbnail: editedCourse.value.thumbnail,
      structure: editedCourse.value.structure,
      settings: editedCourse.value.settings
    };

    const updatedCourse = await updateCourse(props.courseId, updates);

    // Update local state
    originalCourse.value = JSON.parse(JSON.stringify(updatedCourse));
    editedCourse.value = JSON.parse(JSON.stringify(updatedCourse));
    hasUnsavedChanges.value = false;
    lastSaved.value = new Date();

    emit('courseUpdated', updatedCourse);

  } catch (error) {
    console.error('Failed to save course:', error);
  } finally {
    saving.value = false;
  }
};

const publishCourseHandler = async () => {
  if (!editedCourse.value || !canPublish.value) return;

  try {
    publishing.value = true;

    // Save changes first if there are any
    if (hasUnsavedChanges.value) {
      await saveChanges();
    }

    const publishedCourse = await publishCourse(props.courseId);
    editedCourse.value = publishedCourse;

    emit('publishingRequested', props.courseId);

  } catch (error) {
    console.error('Failed to publish course:', error);
  } finally {
    publishing.value = false;
  }
};

// Auto-save functionality
const scheduleAutoSave = () => {
  if (!autoSaveEnabled.value || props.readonly) return;

  clearAutoSave();
  autoSaveTimer = setTimeout(() => {
    if (hasUnsavedChanges.value) {
      saveChanges();
    }
  }, 3000); // Auto-save after 3 seconds of inactivity
};

const clearAutoSave = () => {
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer);
  }
};

// Module assignment methods
const openModuleLibrary = (sectionId?: string) => {
  selectedSection.value = sectionId || null;
  showModuleLibrary.value = true;
};

const addModuleToCurrentSection = async (module: StandaloneModule) => {
  if (!editedCourse.value) return;

  try {
    const targetId = selectedSection.value || 'main';
    const newAssignment: ModuleAssignment = {
      id: `temp-${Date.now()}-${module.id}`,
      moduleId: module.id,
      targetType: 'course',
      targetId: targetId,
      order: getNextOrderForTarget(targetId),
      isRequired: true,
      isActive: true,
      createdAt: new Date(),
      updatedAt: new Date(),
      createdBy: 'current-user',
      module: module,
      target: {
        id: targetId,
        type: 'course',
        title: selectedSection.value ? getSectionTitle(selectedSection.value) : 'Main Course',
        currentAssignments: [],
        allowDuplicateModules: false
      },
      completionRate: 0,
      averageScore: 0,
      timeSpentAverage: 0
    };

    editedCourse.value.moduleAssignments.push(newAssignment);
    editedCourse.value.moduleCount = editedCourse.value.moduleAssignments.length;

    markAsChanged();
    emit('moduleAssignmentChanged', editedCourse.value.moduleAssignments);

  } catch (error) {
    console.error('Failed to add module:', error);
  }

  showModuleLibrary.value = false;
};

const removeModuleAssignment = async (assignmentId: string) => {
  if (!editedCourse.value) return;

  const confirmed = confirm('Are you sure you want to remove this module from the course?');
  if (!confirmed) return;

  try {
    editedCourse.value.moduleAssignments = editedCourse.value.moduleAssignments.filter(
      assignment => assignment.id !== assignmentId
    );
    editedCourse.value.moduleCount = editedCourse.value.moduleAssignments.length;

    // Reorder remaining assignments
    reorderAssignmentsInTarget();

    markAsChanged();
    emit('moduleAssignmentChanged', editedCourse.value.moduleAssignments);

  } catch (error) {
    console.error('Failed to remove module:', error);
  }
};

const reorderAssignments = (newOrder: ModuleAssignment[]) => {
  if (!editedCourse.value) return;

  editedCourse.value.moduleAssignments = newOrder.map((assignment, index) => ({
    ...assignment,
    order: index + 1
  }));

  markAsChanged();
  emit('moduleAssignmentChanged', editedCourse.value.moduleAssignments);
};

const getNextOrderForTarget = (targetId: string): number => {
  if (!editedCourse.value) return 1;

  const targetAssignments = editedCourse.value.moduleAssignments.filter(
    assignment => assignment.targetId === targetId
  );

  return targetAssignments.length > 0
    ? Math.max(...targetAssignments.map(a => a.order)) + 1
    : 1;
};

const getSectionTitle = (sectionId: string): string => {
  const section = editedCourse.value?.structure?.sections?.find(s => s.id === sectionId);
  return section?.title || 'Unknown Section';
};

const reorderAssignmentsInTarget = () => {
  if (!editedCourse.value) return;

  // Group assignments by target and reorder within each group
  const groupedAssignments = editedCourse.value.moduleAssignments.reduce((groups, assignment) => {
    const targetId = assignment.targetId;
    if (!groups[targetId]) {
      groups[targetId] = [];
    }
    groups[targetId].push(assignment);
    return groups;
  }, {} as Record<string, ModuleAssignment[]>);

  // Reorder within each group
  Object.keys(groupedAssignments).forEach(targetId => {
    groupedAssignments[targetId].forEach((assignment, index) => {
      assignment.order = index + 1;
    });
  });
};

// Section management
const addSection = () => {
  if (!editedCourse.value?.structure) return;

  const newSection: CourseSection = {
    id: `section-${Date.now()}`,
    title: 'New Section',
    description: '',
    moduleAssignments: [],
    order: (editedCourse.value.structure.sections?.length || 0) + 1,
    isRequired: true
  };

  if (!editedCourse.value.structure.sections) {
    editedCourse.value.structure.sections = [];
  }

  editedCourse.value.structure.sections.push(newSection);
  markAsChanged();
};

const removeSection = (sectionId: string) => {
  if (!editedCourse.value?.structure?.sections) return;

  const confirmed = confirm('Are you sure you want to remove this section? All modules in this section will be moved to unassigned.');
  if (!confirmed) return;

  // Move modules to unassigned
  editedCourse.value.moduleAssignments.forEach(assignment => {
    if (assignment.targetId === sectionId) {
      assignment.targetId = 'main';
    }
  });

  // Remove section
  editedCourse.value.structure.sections = editedCourse.value.structure.sections.filter(
    section => section.id !== sectionId
  );

  markAsChanged();
};

const toggleSectionExpansion = (sectionId: string) => {
  if (expandedSections.value.has(sectionId)) {
    expandedSections.value.delete(sectionId);
  } else {
    expandedSections.value.add(sectionId);
  }
};

// Version control methods
const loadVersionHistory = async () => {
  if (!props.showVersionHistory) return;

  try {
    loadingHistory.value = true;
    // This would typically fetch from an API
    // For now, we'll simulate version history
    versionHistory.value = [
      {
        id: '1',
        version: '1.0',
        createdAt: new Date(Date.now() - 86400000), // 1 day ago
        createdBy: { name: 'John Doe', email: 'john@example.com' },
        changes: ['Initial course creation', 'Added 5 modules'],
        isCurrent: false
      },
      {
        id: '2',
        version: '1.1',
        createdAt: new Date(Date.now() - 43200000), // 12 hours ago
        createdBy: { name: 'Jane Smith', email: 'jane@example.com' },
        changes: ['Updated course description', 'Reordered modules'],
        isCurrent: true
      }
    ];
  } catch (error) {
    console.error('Failed to load version history:', error);
  } finally {
    loadingHistory.value = false;
  }
};

const revertToVersion = async (version: any) => {
  const confirmed = confirm(`Are you sure you want to revert to version ${version.version}? This will overwrite your current changes.`);
  if (!confirmed) return;

  try {
    // This would typically call an API to revert
    console.log('Reverting to version:', version);
    showVersionDialog.value = false;
  } catch (error) {
    console.error('Failed to revert to version:', error);
  }
};

// Validation
const validateCurrentCourse = () => {
  if (!editedCourse.value) return;

  validation.value = validateCourse(editedCourse.value);
};

// Change tracking
const markAsChanged = () => {
  hasUnsavedChanges.value = true;
  scheduleAutoSave();
  validateCurrentCourse();
};

// Watchers
watch(editedCourse, () => {
  if (isEditing.value) {
    markAsChanged();
  }
}, { deep: true });

// Lifecycle
onMounted(async () => {
  // Fetch course data
  await fetchCourse(props.courseId);

  // Fetch modules for library
  await fetchModules();

  // Load version history if enabled
  if (props.showVersionHistory) {
    await loadVersionHistory();
  }

  // Start editing if not readonly
  if (!props.readonly) {
    startEditing();
  }
});

onUnmounted(() => {
  clearAutoSave();
});

// Keyboard shortcuts
const handleKeydown = (event: KeyboardEvent) => {
  if (!isEditing.value) return;

  // Ctrl/Cmd + S to save
  if ((event.ctrlKey || event.metaKey) && event.key === 's') {
    event.preventDefault();
    saveChanges();
  }

  // Ctrl/Cmd + Z to undo (placeholder)
  if ((event.ctrlKey || event.metaKey) && event.key === 'z' && !event.shiftKey) {
    event.preventDefault();
    // Implement undo functionality
  }

  // Ctrl/Cmd + Shift + Z to redo (placeholder)
  if ((event.ctrlKey || event.metaKey) && event.key === 'z' && event.shiftKey) {
    event.preventDefault();
    // Implement redo functionality
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <div class="course-editor h-full flex flex-col">
    <!-- Header -->
    <div class="flex-shrink-0 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div>
              <h1 class="text-2xl font-bold">
                {{ editedCourse?.title || currentCourse?.title || 'Loading...' }}
              </h1>
              <div class="flex items-center gap-2 mt-1">
                <Badge v-if="editedCourse?.isPublished" variant="default">
                  Published
                </Badge>
                <Badge v-else variant="secondary">
                  Draft
                </Badge>

                <Badge v-if="hasUnsavedChanges" variant="outline" class="text-orange-600">
                  <Edit3 class="h-3 w-3 mr-1" />
                  Unsaved Changes
                </Badge>

                <span v-if="lastSaved" class="text-sm text-muted-foreground">
                  Last saved: {{ lastSaved.toLocaleTimeString() }}
                </span>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <Button
              v-if="showVersionHistory"
              variant="outline"
              size="sm"
              @click="showVersionDialog = true"
            >
              <History class="h-4 w-4 mr-2" />
              History
            </Button>

            <Button
              v-if="!isEditing && !readonly"
              variant="outline"
              @click="startEditing"
            >
              <Edit3 class="h-4 w-4 mr-2" />
              Edit Course
            </Button>

            <template v-if="isEditing">
              <Button
                variant="outline"
                @click="cancelEditing"
              >
                Cancel
              </Button>

              <Button
                @click="saveChanges"
                :disabled="!hasUnsavedChanges || saving"
              >
                <Loader2 v-if="saving" class="h-4 w-4 mr-2 animate-spin" />
                <Save v-else class="h-4 w-4 mr-2" />
                Save
              </Button>
            </template>

            <Button
              v-if="canPublish"
              @click="publishCourseHandler"
              :disabled="publishing"
            >
              <Loader2 v-if="publishing" class="h-4 w-4 mr-2 animate-spin" />
              <Eye v-else class="h-4 w-4 mr-2" />
              Publish
            </Button>
          </div>
        </div>

        <!-- Course Stats -->
        <div class="flex items-center gap-6 mt-4 text-sm text-muted-foreground">
          <div class="flex items-center gap-1">
            <BookOpen class="h-4 w-4" />
            <span>{{ totalModules }} modules</span>
          </div>
          <div class="flex items-center gap-1">
            <Clock class="h-4 w-4" />
            <span>{{ formattedDuration }}</span>
          </div>
          <div class="flex items-center gap-1">
            <Users class="h-4 w-4" />
            <span>{{ editedCourse?.enrollmentCount || 0 }} enrolled</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-hidden">
      <!-- Loading State -->
      <div v-if="courseLoading" class="flex items-center justify-center h-full">
        <div class="text-center">
          <Loader2 class="h-8 w-8 animate-spin mx-auto mb-4" />
          <p class="text-muted-foreground">Loading course...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="courseError" class="flex items-center justify-center h-full">
        <div class="text-center">
          <AlertCircle class="h-8 w-8 text-destructive mx-auto mb-4" />
          <p class="text-destructive mb-2">{{ courseError }}</p>
          <Button variant="outline" @click="fetchCourse(courseId)">
            Try Again
          </Button>
        </div>
      </div>

      <!-- Editor Content -->
      <div v-else-if="editedCourse || currentCourse" class="h-full">
        <Tabs default-value="content" class="h-full flex flex-col">
          <div class="flex-shrink-0 border-b px-4">
            <TabsList class="grid w-full grid-cols-4">
              <TabsTrigger value="content">Content</TabsTrigger>
              <TabsTrigger value="modules">Modules</TabsTrigger>
              <TabsTrigger value="settings">Settings</TabsTrigger>
              <TabsTrigger value="preview">Preview</TabsTrigger>
            </TabsList>
          </div>

          <div class="flex-1 overflow-auto">
            <!-- Content Tab -->
            <TabsContent value="content" class="p-6 space-y-6">
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <Card>
                  <CardHeader>
                    <CardTitle>Basic Information</CardTitle>
                  </CardHeader>
                  <CardContent class="space-y-4">
                    <div>
                      <Label for="title">Course Title</Label>
                      <Input
                        id="title"
                        v-model="editedCourse!.title"
                        :readonly="!isEditing"
                        class="mt-1"
                      />
                    </div>

                    <div>
                      <Label for="category">Category</Label>
                      <Select v-model="editedCourse!.category" :disabled="!isEditing">
                        <SelectTrigger class="mt-1">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="programming">Programming</SelectItem>
                          <SelectItem value="design">Design</SelectItem>
                          <SelectItem value="business">Business</SelectItem>
                          <SelectItem value="science">Science</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div>
                      <Label for="difficulty">Difficulty</Label>
                      <Select v-model="editedCourse!.difficulty" :disabled="!isEditing">
                        <SelectTrigger class="mt-1">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="beginner">Beginner</SelectItem>
                          <SelectItem value="intermediate">Intermediate</SelectItem>
                          <SelectItem value="advanced">Advanced</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </CardContent>
                </Card>

                <!-- Description -->
                <Card>
                  <CardHeader>
                    <CardTitle>Description</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <RichTextEditor
                      v-if="isEditing"
                      v-model="editedCourse!.description"
                      placeholder="Describe what students will learn..."
                    />
                    <div
                      v-else
                      class="prose prose-sm max-w-none"
                      v-html="editedCourse?.description || currentCourse?.description"
                    />
                  </CardContent>
                </Card>
              </div>

              <!-- Tags and Prerequisites -->
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Tags</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="flex flex-wrap gap-1">
                      <Badge
                        v-for="tag in editedCourse?.tags || currentCourse?.tags || []"
                        :key="tag"
                        variant="secondary"
                      >
                        {{ tag }}
                      </Badge>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Prerequisites</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div class="space-y-1">
                      <div
                        v-for="prereq in editedCourse?.prerequisites || currentCourse?.prerequisites || []"
                        :key="prereq"
                        class="text-sm"
                      >
                        • {{ prereq }}
                      </div>
                      <p v-if="!editedCourse?.prerequisites?.length && !currentCourse?.prerequisites?.length"
                         class="text-sm text-muted-foreground">
                        No prerequisites
                      </p>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <!-- Modules Tab -->
            <TabsContent value="modules" class="p-6 space-y-6">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold">Course Modules</h3>
                  <p class="text-sm text-muted-foreground">
                    Manage module assignments and course structure
                  </p>
                </div>
                <Button
                  v-if="isEditing"
                  @click="openModuleLibrary()"
                  class="flex items-center gap-2"
                >
                  <Plus class="h-4 w-4" />
                  Add Module
                </Button>
              </div>

              <!-- Validation Alerts -->
              <div v-if="validation.errors.length > 0 || validation.warnings.length > 0" class="space-y-2">
                <Alert v-for="error in validation.errors" :key="error.field" variant="destructive">
                  <AlertCircle class="h-4 w-4" />
                  <AlertDescription>{{ error.message }}</AlertDescription>
                </Alert>

                <Alert v-for="warning in validation.warnings" :key="warning.field">
                  <AlertCircle class="h-4 w-4" />
                  <AlertDescription>{{ warning.message }}</AlertDescription>
                </Alert>
              </div>

              <!-- Course Sections -->
              <div v-if="sectionsWithModules.length > 0" class="space-y-4">
                <div
                  v-for="section in sectionsWithModules"
                  :key="section.id"
                  class="border rounded-lg"
                >
                  <div
                    class="flex items-center justify-between p-4 cursor-pointer"
                    @click="toggleSectionExpansion(section.id)"
                  >
                    <div class="flex items-center gap-2">
                      <ChevronRight
                        v-if="!expandedSections.has(section.id)"
                        class="h-4 w-4"
                      />
                      <ChevronDown
                        v-else
                        class="h-4 w-4"
                      />
                      <h4 class="font-medium">{{ section.title }}</h4>
                      <Badge variant="outline">
                        {{ section.moduleAssignments.length }} modules
                      </Badge>
                    </div>

                    <div v-if="isEditing" class="flex items-center gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        @click.stop="openModuleLibrary(section.id)"
                      >
                        <Plus class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click.stop="removeSection(section.id)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>

                  <div
                    v-if="expandedSections.has(section.id)"
                    class="border-t p-4"
                  >
                    <DragDropAssignment
                      v-if="isEditing"
                      :available-modules="[]"
                      :assignment-targets="[{
                        id: section.id,
                        type: 'section',
                        title: section.title,
                        currentAssignments: section.moduleAssignments,
                        allowDuplicateModules: false
                      }]"
                      :existing-assignments="section.moduleAssignments"
                      @assignment-reordered="reorderAssignments"
                      @assignment-removed="removeModuleAssignment"
                    />

                    <div v-else class="space-y-2">
                      <ModuleCard
                        v-for="assignment in section.moduleAssignments"
                        :key="assignment.id"
                        :module="assignment.module"
                        variant="compact"
                        :readonly="true"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Unassigned Modules -->
              <div v-if="unassignedModules.length > 0">
                <Card>
                  <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                      <span>Unassigned Modules</span>
                      <Badge variant="outline">{{ unassignedModules.length }}</Badge>
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <DragDropAssignment
                      v-if="isEditing"
                      :available-modules="[]"
                      :assignment-targets="[{
                        id: 'main',
                        type: 'course',
                        title: 'Main Course',
                        currentAssignments: unassignedModules,
                        allowDuplicateModules: false
                      }]"
                      :existing-assignments="unassignedModules"
                      @assignment-reordered="reorderAssignments"
                      @assignment-removed="removeModuleAssignment"
                    />

                    <div v-else class="space-y-2">
                      <ModuleCard
                        v-for="assignment in unassignedModules"
                        :key="assignment.id"
                        :module="assignment.module"
                        variant="compact"
                        :readonly="true"
                      />
                    </div>
                  </CardContent>
                </Card>
              </div>

              <!-- Empty State -->
              <div v-if="totalModules === 0" class="text-center py-12">
                <BookOpen class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <h3 class="text-lg font-medium mb-2">No modules assigned</h3>
                <p class="text-muted-foreground mb-4">
                  Add modules to build your course content
                </p>
                <Button v-if="isEditing" @click="openModuleLibrary()">
                  <Plus class="h-4 w-4 mr-2" />
                  Add First Module
                </Button>
              </div>

              <!-- Section Management -->
              <div v-if="isEditing" class="flex justify-center">
                <Button variant="outline" @click="addSection">
                  <Plus class="h-4 w-4 mr-2" />
                  Add Section
                </Button>
              </div>
            </TabsContent>

            <!-- Settings Tab -->
            <TabsContent value="settings" class="p-6 space-y-6">
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Enrollment Settings -->
                <Card>
                  <CardHeader>
                    <CardTitle>Enrollment Settings</CardTitle>
                  </CardHeader>
                  <CardContent class="space-y-4">
                    <div>
                      <Label>Enrollment Type</Label>
                      <Select
                        v-model="editedCourse!.settings.enrollmentType"
                        :disabled="!isEditing"
                      >
                        <SelectTrigger class="mt-1">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="open">Open Enrollment</SelectItem>
                          <SelectItem value="invitation">Invitation Only</SelectItem>
                          <SelectItem value="approval_required">Approval Required</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div>
                      <Label>Maximum Enrollments</Label>
                      <Input
                        type="number"
                        v-model.number="editedCourse!.settings.maxEnrollments"
                        :readonly="!isEditing"
                        placeholder="Unlimited"
                        class="mt-1"
                      />
                    </div>
                  </CardContent>
                </Card>

                <!-- Learning Settings -->
                <Card>
                  <CardHeader>
                    <CardTitle>Learning Settings</CardTitle>
                  </CardHeader>
                  <CardContent class="space-y-4">
                    <div class="flex items-center justify-between">
                      <div>
                        <Label>Linear Progression</Label>
                        <p class="text-sm text-muted-foreground">
                          Students must complete modules in order
                        </p>
                      </div>
                      <input
                        type="checkbox"
                        v-model="editedCourse!.structure.linearProgression"
                        :disabled="!isEditing"
                        class="rounded"
                      />
                    </div>

                    <div class="flex items-center justify-between">
                      <div>
                        <Label>Allow Late Submissions</Label>
                        <p class="text-sm text-muted-foreground">
                          Students can submit after deadlines
                        </p>
                      </div>
                      <input
                        type="checkbox"
                        v-model="editedCourse!.settings.allowLateSubmissions"
                        :disabled="!isEditing"
                        class="rounded"
                      />
                    </div>

                    <div class="flex items-center justify-between">
                      <div>
                        <Label>Enable Discussions</Label>
                        <p class="text-sm text-muted-foreground">
                          Students can participate in discussions
                        </p>
                      </div>
                      <input
                        type="checkbox"
                        v-model="editedCourse!.settings.enableDiscussions"
                        :disabled="!isEditing"
                        class="rounded"
                      />
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <!-- Preview Tab -->
            <TabsContent value="preview" class="p-6">
              <Card>
                <CardHeader>
                  <CardTitle>Course Preview</CardTitle>
                  <CardDescription>
                    This is how your course will appear to students
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div class="space-y-6">
                    <!-- Course Header -->
                    <div>
                      <h2 class="text-2xl font-bold mb-2">
                        {{ editedCourse?.title || currentCourse?.title }}
                      </h2>
                      <div class="flex items-center gap-4 text-sm text-muted-foreground mb-4">
                        <Badge>{{ editedCourse?.category || currentCourse?.category }}</Badge>
                        <Badge variant="outline">{{ editedCourse?.difficulty || currentCourse?.difficulty }}</Badge>
                        <span>{{ formattedDuration }}</span>
                        <span>{{ totalModules }} modules</span>
                      </div>
                      <div
                        class="prose prose-sm max-w-none"
                        v-html="editedCourse?.description || currentCourse?.description"
                      />
                    </div>

                    <!-- Course Content -->
                    <div>
                      <h3 class="text-lg font-semibold mb-4">Course Content</h3>
                      <div class="space-y-2">
                        <div
                          v-for="(assignment, index) in (editedCourse?.moduleAssignments || currentCourse?.moduleAssignments || [])"
                          :key="assignment.id"
                          class="flex items-center gap-3 p-3 border rounded-lg"
                        >
                          <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center text-sm font-medium">
                            {{ index + 1 }}
                          </div>
                          <div class="flex-1">
                            <h4 class="font-medium">{{ assignment.module.title }}</h4>
                            <p class="text-sm text-muted-foreground">
                              {{ assignment.module.estimatedDuration }} minutes
                            </p>
                          </div>
                          <Badge v-if="assignment.isRequired" variant="outline">
                            Required
                          </Badge>
                        </div>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </div>
        </Tabs>
      </div>
    </div>

    <!-- Module Library Modal -->
    <Dialog v-model:open="showModuleLibrary">
      <DialogContent class="max-w-4xl max-h-[80vh]">
        <DialogHeader>
          <DialogTitle>Add Modules</DialogTitle>
          <DialogDescription>
            Select modules to add to {{ selectedSection ? getSectionTitle(selectedSection) : 'the course' }}
          </DialogDescription>
        </DialogHeader>

        <div class="overflow-auto max-h-96">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <ModuleCard
              v-for="module in modules.filter(m =>
                !editedCourse?.moduleAssignments.find(a => a.moduleId === m.id)
              )"
              :key="module.id"
              :module="module"
              variant="compact"
              @click="addModuleToCurrentSection(module)"
              class="cursor-pointer hover:border-primary"
            />
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Version History Modal -->
    <Dialog v-model:open="showVersionDialog">
      <DialogContent class="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Version History</DialogTitle>
          <DialogDescription>
            View and restore previous versions of this course
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 max-h-96 overflow-auto">
          <div
            v-for="version in versionHistory"
            :key="version.id"
            class="border rounded-lg p-4"
          >
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <Badge :variant="version.isCurrent ? 'default' : 'outline'">
                  v{{ version.version }}
                </Badge>
                <span class="text-sm text-muted-foreground">
                  {{ version.createdAt.toLocaleDateString() }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">
                  by {{ version.createdBy.name }}
                </span>
                <Button
                  v-if="!version.isCurrent"
                  variant="outline"
                  size="sm"
                  @click="revertToVersion(version)"
                >
                  Restore
                </Button>
              </div>
            </div>
            <div class="space-y-1">
              <p
                v-for="change in version.changes"
                :key="change"
                class="text-sm"
              >
                • {{ change }}
              </p>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style scoped>
/* Custom scrollbar */
.overflow-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-auto::-webkit-scrollbar-thumb {
  background: hsl(var(--border));
  border-radius: 3px;
}

.overflow-auto::-webkit-scrollbar-thumb:hover {
  background: hsl(var(--muted-foreground));
}

/* Transition animations */
.course-editor {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Drag and drop styling */
.drop-zone-active {
  border-color: hsl(var(--primary));
  background-color: hsl(var(--primary) / 0.05);
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  .grid-cols-1.lg\:grid-cols-2 {
    grid-template-columns: 1fr;
  }
}

/* Auto-save indicator */
.auto-save-indicator {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>
