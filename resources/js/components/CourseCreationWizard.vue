<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
  ChevronLeft,
  ChevronRight,
  Save,
  Eye,
  BookOpen,
  Users,
  Settings,
  FileText,
  Plus,
  X,
  GripVertical,
  AlertCircle,
  CheckCircle,
  Loader2,
  Upload,
  Template,
  Wand2
} from 'lucide-vue-next';
import { useCourseManagement } from '@/composables/useCourseManagement';
import { useModuleLibrary } from '@/composables/useModuleLibrary';
import { useDragDropAssignment } from '@/composables/useAssignmentWorkflow';
import RichTextEditor from './RichTextEditor.vue';
import ModuleCard from './ModuleCard.vue';
import DragDropAssignment from './DragDropAssignment.vue';
import {
  Course,
  CourseTemplate,
  CreateCourseRequest,
  StandaloneModule,
  ModuleAssignment,
  CourseSection,
  ValidationResult
} from '@/types/enhanced-classroom';

interface Props {
  template?: CourseTemplate;
  initialModules?: string[];
  open?: boolean;
}

interface Emits {
  (e: 'courseCreated', course: Course): void;
  (e: 'wizardCancelled'): void;
  (e: 'stepChanged', currentStep: number, totalSteps: number): void;
  (e: 'update:open', open: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
  open: false
});

const emit = defineEmits<Emits>();

// Composables
const {
  createCourse,
  validateCourse,
  courseTemplates,
  loading: courseLoading,
  error: courseError
} = useCourseManagement();

const {
  modules,
  fetchModules,
  loading: moduleLoading
} = useModuleLibrary();

// Wizard state
const currentStep = ref(1);
const totalSteps = 5;
const isOpen = ref(props.open);

// Course data
const courseData = ref<CreateCourseRequest>({
  title: '',
  description: '',
  category: '',
  difficulty: 'beginner',
  prerequisites: [],
  tags: [],
  thumbnail: '',
  structure: {
    sections: [],
    linearProgression: true,
    allowSkipping: false,
    completionRequirements: []
  },
  settings: {
    enrollmentType: 'open',
    allowLateSubmissions: true,
    showProgressToStudents: true,
    enableDiscussions: true,
    enablePeerReview: false
  },
  moduleAssignments: []
});

// Form validation
const validation = ref<ValidationResult>({
  isValid: false,
  errors: [],
  warnings: []
});

// Module selection state
const selectedModules = ref<StandaloneModule[]>([]);
const moduleAssignments = ref<ModuleAssignment[]>([]);
const showModuleLibrary = ref(false);

// Template state
const selectedTemplate = ref<CourseTemplate | null>(props.template || null);
const showTemplateSelector = ref(false);

// Draft state
const isDraft = ref(false);
const draftKey = ref<string>('');
const autoSaveEnabled = ref(true);

// Loading states
const saving = ref(false);
const publishing = ref(false);
const loadingDraft = ref(false);

// Computed properties
const progressPercentage = computed(() => {
  return (currentStep.value / totalSteps) * 100;
});

const canProceed = computed(() => {
  switch (currentStep.value) {
    case 1: // Basic Info
      return courseData.value.title.trim() &&
             courseData.value.description.trim() &&
             courseData.value.category.trim();
    case 2: // Module Selection
      return selectedModules.value.length > 0;
    case 3: // Course Structure
      return true; // Optional step
    case 4: // Settings
      return true; // Optional step
    case 5: // Review
      return validation.value.isValid;
    default:
      return false;
  }
});

const canGoBack = computed(() => {
  return currentStep.value > 1;
});

const isLastStep = computed(() => {
  return currentStep.value === totalSteps;
});

const stepTitles = computed(() => [
  'Basic Information',
  'Module Selection',
  'Course Structure',
  'Settings',
  'Review & Publish'
]);

const categories = [
  'Programming',
  'Design',
  'Business',
  'Science',
  'Mathematics',
  'Language',
  'Arts',
  'Technology',
  'Health',
  'Other'
];

const difficulties = [
  { value: 'beginner', label: 'Beginner', description: 'No prior knowledge required' },
  { value: 'intermediate', label: 'Intermediate', description: 'Some experience recommended' },
  { value: 'advanced', label: 'Advanced', description: 'Extensive experience required' }
];

// Methods
const nextStep = () => {
  if (currentStep.value < totalSteps && canProceed.value) {
    currentStep.value++;
    emit('stepChanged', currentStep.value, totalSteps);

    // Auto-validate on review step
    if (currentStep.value === totalSteps) {
      validateCurrentCourse();
    }
  }
};

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
    emit('stepChanged', currentStep.value, totalSteps);
  }
};

const goToStep = (step: number) => {
  if (step >= 1 && step <= totalSteps) {
    currentStep.value = step;
    emit('stepChanged', currentStep.value, totalSteps);
  }
};

const closeWizard = () => {
  isOpen.value = false;
  emit('update:open', false);
  emit('wizardCancelled');
};

// Module selection methods
const openModuleLibrary = () => {
  showModuleLibrary.value = true;
};

const selectModule = (module: StandaloneModule) => {
  if (!selectedModules.value.find(m => m.id === module.id)) {
    selectedModules.value.push(module);

    // Create module assignment
    const assignment: ModuleAssignment = {
      id: `temp-${Date.now()}-${module.id}`,
      moduleId: module.id,
      targetType: 'course',
      targetId: 'temp-course',
      order: selectedModules.value.length,
      isRequired: true,
      isActive: true,
      createdAt: new Date(),
      updatedAt: new Date(),
      createdBy: 'current-user',
      module: module,
      target: {
        id: 'temp-course',
        type: 'course',
        title: courseData.value.title || 'New Course',
        currentAssignments: [],
        allowDuplicateModules: false
      },
      completionRate: 0,
      averageScore: 0,
      timeSpentAverage: 0
    };

    moduleAssignments.value.push(assignment);
  }
  showModuleLibrary.value = false;
};

const removeModule = (moduleId: string) => {
  selectedModules.value = selectedModules.value.filter(m => m.id !== moduleId);
  moduleAssignments.value = moduleAssignments.value.filter(a => a.moduleId !== moduleId);

  // Update order
  moduleAssignments.value.forEach((assignment, index) => {
    assignment.order = index + 1;
  });
};

const reorderModules = (newOrder: ModuleAssignment[]) => {
  moduleAssignments.value = newOrder;

  // Update selected modules order
  selectedModules.value = newOrder.map(assignment => assignment.module);
};

// Template methods
const applyTemplate = (template: CourseTemplate) => {
  selectedTemplate.value = template;

  // Apply template data to course
  if (template.data) {
    Object.assign(courseData.value, template.data);
  }

  showTemplateSelector.value = false;
};

// Validation
const validateCurrentCourse = () => {
  const tempCourse: Course = {
    id: 'temp',
    title: courseData.value.title,
    description: courseData.value.description,
    category: courseData.value.category,
    difficulty: courseData.value.difficulty,
    estimatedDuration: selectedModules.value.reduce((total, module) => total + module.estimatedDuration, 0),
    prerequisites: courseData.value.prerequisites || [],
    tags: courseData.value.tags || [],
    isPublished: false,
    createdAt: new Date(),
    updatedAt: new Date(),
    instructor: { id: 'current-user', name: 'Current User', email: '', role: 'instructor' },
    moduleAssignments: moduleAssignments.value,
    moduleCount: selectedModules.value.length,
    enrollmentCount: 0,
    completionCount: 0,
    rating: 0,
    reviewCount: 0,
    structure: courseData.value.structure || {
      sections: [],
      linearProgression: true,
      allowSkipping: false,
      completionRequirements: []
    },
    settings: courseData.value.settings || {
      enrollmentType: 'open',
      allowLateSubmissions: true,
      showProgressToStudents: true,
      enableDiscussions: true,
      enablePeerReview: false
    }
  };

  validation.value = validateCourse(tempCourse);
};

// Draft management
const generateDraftKey = () => {
  return `course-draft-${Date.now()}`;
};

const saveDraft = async () => {
  if (!draftKey.value) {
    draftKey.value = generateDraftKey();
  }

  const draftData = {
    courseData: courseData.value,
    selectedModules: selectedModules.value,
    moduleAssignments: moduleAssignments.value,
    currentStep: currentStep.value,
    timestamp: new Date().toISOString()
  };

  try {
    localStorage.setItem(draftKey.value, JSON.stringify(draftData));
    isDraft.value = true;
  } catch (error) {
    console.error('Failed to save draft:', error);
  }
};

const loadDraft = async (key: string) => {
  try {
    loadingDraft.value = true;
    const draftData = localStorage.getItem(key);

    if (draftData) {
      const parsed = JSON.parse(draftData);

      courseData.value = parsed.courseData;
      selectedModules.value = parsed.selectedModules || [];
      moduleAssignments.value = parsed.moduleAssignments || [];
      currentStep.value = parsed.currentStep || 1;
      draftKey.value = key;
      isDraft.value = true;
    }
  } catch (error) {
    console.error('Failed to load draft:', error);
  } finally {
    loadingDraft.value = false;
  }
};

const deleteDraft = () => {
  if (draftKey.value) {
    localStorage.removeItem(draftKey.value);
    draftKey.value = '';
    isDraft.value = false;
  }
};

// Auto-save functionality
let autoSaveTimeout: ReturnType<typeof setTimeout>;

const scheduleAutoSave = () => {
  if (!autoSaveEnabled.value) return;

  clearTimeout(autoSaveTimeout);
  autoSaveTimeout = setTimeout(() => {
    saveDraft();
  }, 2000); // Auto-save after 2 seconds of inactivity
};

// Course creation
const createNewCourse = async (publish: boolean = false) => {
  try {
    if (publish) {
      publishing.value = true;
    } else {
      saving.value = true;
    }

    // Prepare course data
    const finalCourseData: CreateCourseRequest = {
      ...courseData.value,
      moduleAssignments: moduleAssignments.value.map((assignment, index) => ({
        moduleId: assignment.moduleId,
        targetType: 'course' as const,
        targetId: 'new-course',
        order: index + 1,
        isRequired: assignment.isRequired,
        unlockConditions: assignment.unlockConditions,
        customization: assignment.customization
      }))
    };

    const newCourse = await createCourse(finalCourseData);

    // Clean up draft
    if (isDraft.value) {
      deleteDraft();
    }

    emit('courseCreated', newCourse);
    closeWizard();

  } catch (error) {
    console.error('Failed to create course:', error);
  } finally {
    saving.value = false;
    publishing.value = false;
  }
};

// Tag management
const newTag = ref('');

const addTag = () => {
  const tag = newTag.value.trim();
  if (tag && !courseData.value.tags?.includes(tag)) {
    if (!courseData.value.tags) {
      courseData.value.tags = [];
    }
    courseData.value.tags.push(tag);
    newTag.value = '';
    scheduleAutoSave();
  }
};

const removeTag = (tag: string) => {
  if (courseData.value.tags) {
    courseData.value.tags = courseData.value.tags.filter(t => t !== tag);
    scheduleAutoSave();
  }
};

// Prerequisite management
const newPrerequisite = ref('');

const addPrerequisite = () => {
  const prereq = newPrerequisite.value.trim();
  if (prereq && !courseData.value.prerequisites?.includes(prereq)) {
    if (!courseData.value.prerequisites) {
      courseData.value.prerequisites = [];
    }
    courseData.value.prerequisites.push(prereq);
    newPrerequisite.value = '';
    scheduleAutoSave();
  }
};

const removePrerequisite = (prereq: string) => {
  if (courseData.value.prerequisites) {
    courseData.value.prerequisites = courseData.value.prerequisites.filter(p => p !== prereq);
    scheduleAutoSave();
  }
};

// Watchers
watch(() => props.open, (newValue) => {
  isOpen.value = newValue;
});

watch(isOpen, (newValue) => {
  emit('update:open', newValue);
});

watch([courseData, selectedModules], () => {
  scheduleAutoSave();
}, { deep: true });

// Lifecycle
onMounted(async () => {
  // Fetch modules for selection
  await fetchModules();

  // Apply initial template if provided
  if (props.template) {
    applyTemplate(props.template);
  }

  // Load initial modules if provided
  if (props.initialModules && props.initialModules.length > 0) {
    const initialModules = modules.value.filter(m =>
      props.initialModules!.includes(m.id)
    );

    initialModules.forEach(module => {
      selectModule(module);
    });
  }
});
</script>

<template>
  <Dialog v-model:open="isOpen" class="max-w-6xl">
    <DialogContent class="max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
      <DialogHeader class="flex-shrink-0">
        <DialogTitle class="flex items-center gap-2">
          <BookOpen class="h-5 w-5" />
          Create New Course
          <Badge v-if="isDraft" variant="secondary" class="ml-2">
            <Save class="h-3 w-3 mr-1" />
            Draft Saved
          </Badge>
        </DialogTitle>
        <DialogDescription>
          Follow the steps to create a comprehensive course with module assignments
        </DialogDescription>
      </DialogHeader>

      <!-- Progress Bar -->
      <div class="flex-shrink-0 px-6 py-4 border-b">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">
            Step {{ currentStep }} of {{ totalSteps }}: {{ stepTitles[currentStep - 1] }}
          </span>
          <span class="text-sm text-muted-foreground">
            {{ Math.round(progressPercentage) }}% Complete
          </span>
        </div>
        <Progress :value="progressPercentage" class="h-2" />

        <!-- Step indicators -->
        <div class="flex justify-between mt-4">
          <button
            v-for="(title, index) in stepTitles"
            :key="index"
            @click="goToStep(index + 1)"
            :class="[
              'flex flex-col items-center gap-1 text-xs transition-colors',
              index + 1 === currentStep ? 'text-primary' : 'text-muted-foreground',
              index + 1 < currentStep ? 'text-green-600' : ''
            ]"
          >
            <div
              :class="[
                'w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors',
                index + 1 === currentStep ? 'border-primary bg-primary text-primary-foreground' :
                index + 1 < currentStep ? 'border-green-600 bg-green-600 text-white' : 'border-muted-foreground'
              ]"
            >
              <CheckCircle v-if="index + 1 < currentStep" class="h-4 w-4" />
              <span v-else>{{ index + 1 }}</span>
            </div>
            <span class="max-w-20 text-center leading-tight">{{ title }}</span>
          </button>
        </div>
      </div>

      <!-- Step Content -->
      <div class="flex-1 overflow-auto">
        <!-- Step 1: Basic Information -->
        <div v-if="currentStep === 1" class="p-6 space-y-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="space-y-4">
              <div>
                <Label for="title">Course Title *</Label>
                <Input
                  id="title"
                  v-model="courseData.title"
                  placeholder="Enter course title"
                  class="mt-1"
                />
              </div>

              <div>
                <Label for="category">Category *</Label>
                <Select v-model="courseData.category">
                  <SelectTrigger class="mt-1">
                    <SelectValue placeholder="Select category" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="category in categories" :key="category" :value="category">
                      {{ category }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div>
                <Label for="difficulty">Difficulty Level *</Label>
                <Select v-model="courseData.difficulty">
                  <SelectTrigger class="mt-1">
                    <SelectValue placeholder="Select difficulty" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="difficulty in difficulties"
                      :key="difficulty.value"
                      :value="difficulty.value"
                    >
                      <div>
                        <div class="font-medium">{{ difficulty.label }}</div>
                        <div class="text-sm text-muted-foreground">{{ difficulty.description }}</div>
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <!-- Tags -->
              <div>
                <Label>Tags</Label>
                <div class="mt-1 space-y-2">
                  <div class="flex gap-2">
                    <Input
                      v-model="newTag"
                      placeholder="Add a tag"
                      @keyup.enter="addTag"
                      class="flex-1"
                    />
                    <Button @click="addTag" size="sm">
                      <Plus class="h-4 w-4" />
                    </Button>
                  </div>
                  <div v-if="courseData.tags?.length" class="flex flex-wrap gap-1">
                    <Badge
                      v-for="tag in courseData.tags"
                      :key="tag"
                      variant="secondary"
                      class="cursor-pointer"
                      @click="removeTag(tag)"
                    >
                      {{ tag }}
                      <X class="h-3 w-3 ml-1" />
                    </Badge>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-4">
              <div>
                <Label for="description">Course Description *</Label>
                <RichTextEditor
                  v-model="courseData.description"
                  placeholder="Describe what students will learn in this course..."
                  class="mt-1"
                />
              </div>

              <!-- Prerequisites -->
              <div>
                <Label>Prerequisites</Label>
                <div class="mt-1 space-y-2">
                  <div class="flex gap-2">
                    <Input
                      v-model="newPrerequisite"
                      placeholder="Add a prerequisite"
                      @keyup.enter="addPrerequisite"
                      class="flex-1"
                    />
                    <Button @click="addPrerequisite" size="sm">
                      <Plus class="h-4 w-4" />
                    </Button>
                  </div>
                  <div v-if="courseData.prerequisites?.length" class="space-y-1">
                    <Badge
                      v-for="prereq in courseData.prerequisites"
                      :key="prereq"
                      variant="outline"
                      class="cursor-pointer mr-1"
                      @click="removePrerequisite(prereq)"
                    >
                      {{ prereq }}
                      <X class="h-3 w-3 ml-1" />
                    </Badge>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Template Selection -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Template class="h-4 w-4" />
                Course Templates
              </CardTitle>
              <CardDescription>
                Start with a pre-built template to speed up course creation
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div class="flex gap-2">
                <Button
                  variant="outline"
                  @click="showTemplateSelector = true"
                  class="flex items-center gap-2"
                >
                  <Wand2 class="h-4 w-4" />
                  Browse Templates
                </Button>
                <Badge v-if="selectedTemplate" variant="secondary">
                  Using: {{ selectedTemplate.name }}
                </Badge>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Step 2: Module Selection -->
        <div v-if="currentStep === 2" class="p-6 space-y-6">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold">Select Course Modules</h3>
              <p class="text-sm text-muted-foreground">
                Choose modules that will be part of this course
              </p>
            </div>
            <Button @click="openModuleLibrary" class="flex items-center gap-2">
              <Plus class="h-4 w-4" />
              Add Modules
            </Button>
          </div>

          <!-- Selected Modules -->
          <div v-if="selectedModules.length > 0" class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="font-medium">Selected Modules ({{ selectedModules.length }})</h4>
              <div class="text-sm text-muted-foreground">
                Total Duration: {{ selectedModules.reduce((total, m) => total + m.estimatedDuration, 0) }} minutes
              </div>
            </div>

            <DragDropAssignment
              :available-modules="[]"
              :assignment-targets="[{
                id: 'course-modules',
                type: 'course',
                title: 'Course Modules',
                currentAssignments: moduleAssignments,
                allowDuplicateModules: false
              }]"
              :existing-assignments="moduleAssignments"
              @assignment-reordered="reorderModules"
              @assignment-removed="(assignmentId) => {
                const assignment = moduleAssignments.find(a => a.id === assignmentId);
                if (assignment) removeModule(assignment.moduleId);
              }"
            />
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-12">
            <BookOpen class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
            <h3 class="text-lg font-medium mb-2">No modules selected</h3>
            <p class="text-muted-foreground mb-4">
              Add modules to build your course content
            </p>
            <Button @click="openModuleLibrary">
              <Plus class="h-4 w-4 mr-2" />
              Browse Module Library
            </Button>
          </div>
        </div>

        <!-- Step 3: Course Structure -->
        <div v-if="currentStep === 3" class="p-6 space-y-6">
          <div>
            <h3 class="text-lg font-semibold">Course Structure</h3>
            <p class="text-sm text-muted-foreground">
              Configure how students will progress through the course
            </p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Structure Settings -->
            <Card>
              <CardHeader>
                <CardTitle>Progression Settings</CardTitle>
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
                    v-model="courseData.structure!.linearProgression"
                    class="rounded"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <div>
                    <Label>Allow Skipping</Label>
                    <p class="text-sm text-muted-foreground">
                      Students can skip optional modules
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="courseData.structure!.allowSkipping"
                    class="rounded"
                  />
                </div>
              </CardContent>
            </Card>

            <!-- Completion Requirements -->
            <Card>
              <CardHeader>
                <CardTitle>Completion Requirements</CardTitle>
              </CardHeader>
              <CardContent>
                <div class="space-y-2">
                  <Label>Completion Criteria</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Select completion criteria" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all_modules">Complete all modules</SelectItem>
                      <SelectItem value="percentage">Complete percentage of modules</SelectItem>
                      <SelectItem value="specific_modules">Complete specific modules</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        <!-- Step 4: Settings -->
        <div v-if="currentStep === 4" class="p-6 space-y-6">
          <div>
            <h3 class="text-lg font-semibold">Course Settings</h3>
            <p class="text-sm text-muted-foreground">
              Configure enrollment and learning preferences
            </p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Enrollment Settings -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center gap-2">
                  <Users class="h-4 w-4" />
                  Enrollment Settings
                </CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div>
                  <Label>Enrollment Type</Label>
                  <Select v-model="courseData.settings!.enrollmentType">
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
                    v-model.number="courseData.settings!.maxEnrollments"
                    placeholder="Leave empty for unlimited"
                    class="mt-1"
                  />
                </div>
              </CardContent>
            </Card>

            <!-- Learning Settings -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center gap-2">
                  <Settings class="h-4 w-4" />
                  Learning Settings
                </CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="flex items-center justify-between">
                  <div>
                    <Label>Allow Late Submissions</Label>
                    <p class="text-sm text-muted-foreground">
                      Students can submit after deadlines
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="courseData.settings!.allowLateSubmissions"
                    class="rounded"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <div>
                    <Label>Show Progress to Students</Label>
                    <p class="text-sm text-muted-foreground">
                      Students can see their progress
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="courseData.settings!.showProgressToStudents"
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
                    v-model="courseData.settings!.enableDiscussions"
                    class="rounded"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <div>
                    <Label>Enable Peer Review</Label>
                    <p class="text-sm text-muted-foreground">
                      Students can review each other's work
                    </p>
                  </div>
                  <input
                    type="checkbox"
                    v-model="courseData.settings!.enablePeerReview"
                    class="rounded"
                  />
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        <!-- Step 5: Review & Publish -->
        <div v-if="currentStep === 5" class="p-6 space-y-6">
          <div>
            <h3 class="text-lg font-semibold">Review & Publish</h3>
            <p class="text-sm text-muted-foreground">
              Review your course details before publishing
            </p>
          </div>

          <!-- Validation Results -->
          <Card v-if="validation.errors.length > 0 || validation.warnings.length > 0">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <AlertCircle class="h-4 w-4 text-destructive" />
                Validation Results
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <!-- Errors -->
              <div v-if="validation.errors.length > 0">
                <h4 class="font-medium text-destructive mb-2">Errors (must be fixed)</h4>
                <div class="space-y-1">
                  <div
                    v-for="error in validation.errors"
                    :key="error.field"
                    class="text-sm text-destructive bg-destructive/10 p-2 rounded"
                  >
                    {{ error.message }}
                  </div>
                </div>
              </div>

              <!-- Warnings -->
              <div v-if="validation.warnings.length > 0">
                <h4 class="font-medium text-yellow-600 mb-2">Warnings (recommended to fix)</h4>
                <div class="space-y-1">
                  <div
                    v-for="warning in validation.warnings"
                    :key="warning.field"
                    class="text-sm text-yellow-600 bg-yellow-50 p-2 rounded"
                  >
                    {{ warning.message }}
                    <span v-if="warning.suggestion" class="block text-xs mt-1">
                      Suggestion: {{ warning.suggestion }}
                    </span>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Course Summary -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card>
              <CardHeader>
                <CardTitle>Course Overview</CardTitle>
              </CardHeader>
              <CardContent class="space-y-3">
                <div>
                  <Label class="text-sm font-medium">Title</Label>
                  <p class="text-sm">{{ courseData.title }}</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Category</Label>
                  <p class="text-sm">{{ courseData.category }}</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Difficulty</Label>
                  <Badge :variant="courseData.difficulty === 'beginner' ? 'secondary' :
                                  courseData.difficulty === 'intermediate' ? 'default' : 'destructive'">
                    {{ courseData.difficulty }}
                  </Badge>
                </div>
                <div>
                  <Label class="text-sm font-medium">Modules</Label>
                  <p class="text-sm">{{ selectedModules.length }} modules selected</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Estimated Duration</Label>
                  <p class="text-sm">
                    {{ Math.round(selectedModules.reduce((total, m) => total + m.estimatedDuration, 0) / 60) }} hours
                  </p>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Settings Summary</CardTitle>
              </CardHeader>
              <CardContent class="space-y-3">
                <div>
                  <Label class="text-sm font-medium">Enrollment</Label>
                  <p class="text-sm capitalize">{{ courseData.settings?.enrollmentType?.replace('_', ' ') }}</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Progression</Label>
                  <p class="text-sm">
                    {{ courseData.structure?.linearProgression ? 'Linear' : 'Flexible' }}
                  </p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Features</Label>
                  <div class="flex flex-wrap gap-1 mt-1">
                    <Badge v-if="courseData.settings?.enableDiscussions" variant="outline" class="text-xs">
                      Discussions
                    </Badge>
                    <Badge v-if="courseData.settings?.enablePeerReview" variant="outline" class="text-xs">
                      Peer Review
                    </Badge>
                    <Badge v-if="courseData.settings?.allowLateSubmissions" variant="outline" class="text-xs">
                      Late Submissions
                    </Badge>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="flex-shrink-0 flex items-center justify-between p-6 border-t">
        <div class="flex gap-2">
          <Button
            variant="outline"
            @click="closeWizard"
          >
            Cancel
          </Button>

          <Button
            v-if="canGoBack"
            variant="outline"
            @click="previousStep"
          >
            <ChevronLeft class="h-4 w-4 mr-2" />
            Previous
          </Button>
        </div>

        <div class="flex gap-2">
          <Button
            v-if="!isLastStep"
            @click="nextStep"
            :disabled="!canProceed"
          >
            Next
            <ChevronRight class="h-4 w-4 ml-2" />
          </Button>

          <template v-else>
            <Button
              variant="outline"
              @click="createNewCourse(false)"
              :disabled="!validation.isValid || saving"
            >
              <Save class="h-4 w-4 mr-2" />
              <Loader2 v-if="saving" class="h-4 w-4 mr-2 animate-spin" />
              Save as Draft
            </Button>

            <Button
              @click="createNewCourse(true)"
              :disabled="!validation.isValid || publishing"
            >
              <Loader2 v-if="publishing" class="h-4 w-4 mr-2 animate-spin" />
              <Eye v-else class="h-4 w-4 mr-2" />
              Publish Course
            </Button>
          </template>
        </div>
      </div>
    </DialogContent>

    <!-- Module Library Modal -->
    <Dialog v-model:open="showModuleLibrary">
      <DialogContent class="max-w-4xl max-h-[80vh]">
        <DialogHeader>
          <DialogTitle>Select Modules</DialogTitle>
          <DialogDescription>
            Choose modules to add to your course
          </DialogDescription>
        </DialogHeader>

        <div class="overflow-auto max-h-96">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
            <ModuleCard
              v-for="module in modules.filter(m => !selectedModules.find(sm => sm.id === m.id))"
              :key="module.id"
              :module="module"
              variant="compact"
              @click="selectModule(module)"
              class="cursor-pointer hover:border-primary"
            />
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </Dialog>
</template>

<style scoped>
/* Custom scrollbar for step content */
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

/* Step transition animations */
.step-content {
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

/* Progress bar styling */
.progress-bar {
  transition: width 0.3s ease-in-out;
}

/* Form styling improvements */
.form-section {
  border-radius: 8px;
  border: 1px solid hsl(var(--border));
  padding: 1rem;
  background: hsl(var(--card));
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  .grid-cols-1.lg\:grid-cols-2 {
    grid-template-columns: 1fr;
  }

  .max-w-6xl {
    max-width: 95vw;
  }
}
</style>
