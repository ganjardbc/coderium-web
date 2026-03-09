<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  X,
  BookOpen,
  Star,
  User,
  Calendar,
  Target,
  CheckCircle,
  AlertCircle,
  TrendingUp,
  Users,
  BarChart3,
  Play,
  Download,
  Share,
  Edit,
  Trash2,
  Plus,
  Tag,
  Award,
  Activity
} from 'lucide-vue-next';
import { useModuleLibrary } from '@/composables/useModuleLibrary';
import { useAssignmentWorkflow } from '@/composables/useAssignmentWorkflow';
import { StandaloneModule, ModuleUsageAnalytics, AssignmentTarget } from '@/types/enhanced-classroom';

interface Props {
  moduleId: string;
  showAssignmentOptions?: boolean;
  showAnalytics?: boolean;
  isMobile?: boolean;
}

interface Emits {
  (e: 'assign', moduleId: string, targets: AssignmentTarget[]): void;
  (e: 'close'): void;
  (e: 'edit', moduleId: string): void;
  (e: 'delete', moduleId: string): void;
}

const props = withDefaults(defineProps<Props>(), {
  showAssignmentOptions: false,
  showAnalytics: false,
  isMobile: false
});

const emit = defineEmits<Emits>();

// Composables
const { getModuleAnalytics } = useModuleLibrary();
const { assignmentTargets, createAssignment } = useAssignmentWorkflow();

// Local state
const module = ref<StandaloneModule | null>(null);
const analytics = ref<ModuleUsageAnalytics | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const activeTab = ref<'overview' | 'content' | 'analytics' | 'assignments'>('overview');
const selectedTargets = ref<string[]>([]);
const isAssigning = ref(false);

// Computed properties
const formattedDuration = computed(() => {
  if (!module.value) return '';
  const minutes = module.value.estimatedDuration;
  if (minutes >= 60) {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
  }
  return `${minutes}m`;
});

const difficultyColor = computed(() => {
  if (!module.value) return '';
  const colors = {
    beginner: 'bg-green-500 text-white',
    intermediate: 'bg-yellow-500 text-white',
    advanced: 'bg-red-500 text-white'
  };
  return colors[module.value.difficulty] || 'bg-gray-500 text-white';
});

const completionRate = computed(() => {
  return module.value ? Math.round(module.value.completionRate) : 0;
});

const averageRating = computed(() => {
  return module.value ? module.value.rating.toFixed(1) : '0.0';
});

const assignmentHistory = computed(() => {
  return module.value?.assignmentHistory || [];
});

const availableTargets = computed(() => {
  return assignmentTargets.value.filter(target =>
    !assignmentHistory.value.some(assignment =>
      assignment.targetId === target.id && assignment.isActive
    )
  );
});

const tabs = computed(() => {
  const baseTabs = [
    { id: 'overview', label: 'Overview', icon: BookOpen },
    { id: 'content', label: 'Content', icon: Play }
  ];

  if (props.showAnalytics) {
    baseTabs.push({ id: 'analytics', label: 'Analytics', icon: BarChart3 });
  }

  if (props.showAssignmentOptions) {
    baseTabs.push({ id: 'assignments', label: 'Assignments', icon: Target });
  }

  return baseTabs;
});

const modalClasses = computed(() => {
  if (props.isMobile) {
    return 'fixed inset-0 z-50 bg-background';
  }
  return 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50';
});

const contentClasses = computed(() => {
  if (props.isMobile) {
    return 'h-full flex flex-col';
  }
  return 'bg-background rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col';
});

// Methods
const fetchModuleData = async () => {
  try {
    loading.value = true;
    error.value = null;

    // In a real implementation, this would fetch from API
    // For now, we'll simulate the data structure
    const mockModule: StandaloneModule = {
      id: props.moduleId,
      title: 'Advanced JavaScript Concepts',
      description: 'Deep dive into advanced JavaScript concepts including closures, prototypes, async programming, and modern ES6+ features.',
      content: 'Comprehensive module covering advanced JavaScript topics...',
      estimatedDuration: 180,
      difficulty: 'advanced',
      category: 'Programming',
      tags: ['JavaScript', 'ES6', 'Async', 'Closures', 'Prototypes'],
      prerequisites: ['Basic JavaScript', 'DOM Manipulation', 'Functions and Scope'],
      learningObjectives: [
        'Understand and implement closures effectively',
        'Master prototype-based inheritance',
        'Work with async/await and Promises',
        'Use modern ES6+ features confidently',
        'Debug complex JavaScript applications'
      ],
      isReusable: true,
      isPublished: true,
      createdAt: new Date('2024-01-15'),
      updatedAt: new Date('2024-01-19'),
      author: {
        id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        role: 'instructor'
      },
      lessons: [
        { id: 1, title: 'Closures and Scope', content: '', order: 1 },
        { id: 2, title: 'Prototypes and Inheritance', content: '', order: 2 },
        { id: 3, title: 'Async Programming', content: '', order: 3 },
        { id: 4, title: 'ES6+ Features', content: '', order: 4 }
      ],
      assignmentCount: 5,
      usageAnalytics: {
        totalAssignments: 5,
        activeAssignments: 3,
        completionsByMonth: { '2024-01': 15, '2024-02': 23, '2024-03': 18 },
        averageScore: 87.5,
        popularityRank: 3,
        assignmentsByTarget: { tracks: 2, courses: 3, levels: 0 }
      },
      assignmentHistory: [],
      averageCompletionTime: 165,
      completionRate: 78.5,
      rating: 4.6,
      reviewCount: 42
    };

    module.value = mockModule;

    // Fetch analytics if needed
    if (props.showAnalytics) {
      analytics.value = await getModuleAnalytics(props.moduleId);
    }

  } catch (err) {
    error.value = 'Failed to load module data';
    console.error('Error fetching module:', err);
  } finally {
    loading.value = false;
  }
};

const handleClose = () => {
  emit('close');
};

const handleEdit = () => {
  if (module.value) {
    emit('edit', module.value.id);
  }
};

const handleDelete = () => {
  if (module.value) {
    emit('delete', module.value.id);
  }
};

const toggleTargetSelection = (targetId: string) => {
  const index = selectedTargets.value.indexOf(targetId);
  if (index > -1) {
    selectedTargets.value.splice(index, 1);
  } else {
    selectedTargets.value.push(targetId);
  }
};

const handleAssign = async () => {
  if (!module.value || selectedTargets.value.length === 0) return;

  try {
    isAssigning.value = true;

    // Create assignments for selected targets
    await Promise.all(
      selectedTargets.value.map(targetId =>
        createAssignment(module.value!.id, targetId)
      )
    );

    // Get the target objects for the emit
    const targets = assignmentTargets.value.filter(target =>
      selectedTargets.value.includes(target.id)
    );

    emit('assign', module.value.id, targets);

    // Reset selection
    selectedTargets.value = [];

    // Refresh module data to show new assignments
    await fetchModuleData();

  } catch (err) {
    console.error('Error creating assignments:', err);
  } finally {
    isAssigning.value = false;
  }
};

const formatDate = (date: Date) => {
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  }).format(date);
};

const getTargetTypeIcon = (type: string) => {
  switch (type) {
    case 'course': return BookOpen;
    case 'track': return Target;
    case 'level': return Award;
    default: return BookOpen;
  }
};

// Lifecycle
onMounted(() => {
  fetchModuleData();
});

// Handle escape key
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    handleClose();
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
  <div :class="modalClasses" @click.self="handleClose">
    <div :class="contentClasses">
      <!-- Header -->
      <div class="flex-shrink-0 flex items-center justify-between p-6 border-b">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
            <BookOpen class="h-5 w-5 text-primary" />
          </div>
          <div>
            <h2 class="text-xl font-semibold">Module Preview</h2>
            <p class="text-sm text-muted-foreground">
              {{ module?.category || 'Loading...' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Action Buttons -->
          <Button
            v-if="showAssignmentOptions"
            variant="outline"
            size="sm"
            @click="handleEdit"
          >
            <Edit class="h-4 w-4 mr-2" />
            Edit
          </Button>

          <Button
            v-if="showAssignmentOptions"
            variant="outline"
            size="sm"
          >
            <Share class="h-4 w-4 mr-2" />
            Share
          </Button>

          <Button
            variant="ghost"
            size="sm"
            @click="handleClose"
          >
            <X class="h-4 w-4" />
          </Button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex-1 flex items-center justify-center">
        <div class="text-center">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
          <p class="text-muted-foreground">Loading module...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex-1 flex items-center justify-center">
        <div class="text-center">
          <AlertCircle class="h-8 w-8 text-destructive mx-auto mb-4" />
          <p class="text-destructive mb-4">{{ error }}</p>
          <Button variant="outline" @click="fetchModuleData">
            Try Again
          </Button>
        </div>
      </div>

      <!-- Content -->
      <template v-else-if="module">
        <!-- Tabs -->
        <div class="flex-shrink-0 border-b">
          <div class="flex px-6">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id as typeof activeTab.value"
              :class="[
                'flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                activeTab === tab.id
                  ? 'border-primary text-primary'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              ]"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="flex-1 overflow-auto">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'" class="p-6 space-y-6">
            <!-- Module Header -->
            <div>
              <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                  <h1 class="text-2xl font-bold mb-2">{{ module.title }}</h1>
                  <p class="text-muted-foreground mb-4">{{ module.description }}</p>

                  <!-- Author and Date -->
                  <div class="flex items-center gap-4 text-sm text-muted-foreground">
                    <div class="flex items-center gap-1">
                      <User class="h-4 w-4" />
                      <span>{{ module.author.name }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                      <Calendar class="h-4 w-4" />
                      <span>Updated {{ formatDate(module.updatedAt) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Quick Stats -->
                <div class="flex flex-col gap-2 text-right">
                  <Badge :class="difficultyColor">
                    {{ module.difficulty.charAt(0).toUpperCase() + module.difficulty.slice(1) }}
                  </Badge>
                  <div class="flex items-center gap-1 text-sm">
                    <Star class="h-4 w-4 fill-current text-yellow-400" />
                    <span>{{ averageRating }}</span>
                    <span class="text-muted-foreground">({{ module.reviewCount }})</span>
                  </div>
                </div>
              </div>

              <!-- Key Metrics -->
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-muted/50 rounded-lg">
                <div class="text-center">
                  <div class="text-2xl font-bold text-primary">{{ formattedDuration }}</div>
                  <div class="text-xs text-muted-foreground">Duration</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-primary">{{ module.lessons.length }}</div>
                  <div class="text-xs text-muted-foreground">Lessons</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-primary">{{ completionRate }}%</div>
                  <div class="text-xs text-muted-foreground">Completion</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-primary">{{ module.assignmentCount }}</div>
                  <div class="text-xs text-muted-foreground">Assignments</div>
                </div>
              </div>
            </div>

            <!-- Tags -->
            <div v-if="module.tags.length > 0">
              <h3 class="text-lg font-semibold mb-3">Tags</h3>
              <div class="flex flex-wrap gap-2">
                <Badge
                  v-for="tag in module.tags"
                  :key="tag"
                  variant="outline"
                >
                  <Tag class="h-3 w-3 mr-1" />
                  {{ tag }}
                </Badge>
              </div>
            </div>

            <!-- Learning Objectives -->
            <div v-if="module.learningObjectives.length > 0">
              <h3 class="text-lg font-semibold mb-3">Learning Objectives</h3>
              <ul class="space-y-2">
                <li
                  v-for="objective in module.learningObjectives"
                  :key="objective"
                  class="flex items-start gap-2"
                >
                  <CheckCircle class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" />
                  <span>{{ objective }}</span>
                </li>
              </ul>
            </div>

            <!-- Prerequisites -->
            <div v-if="module.prerequisites.length > 0">
              <h3 class="text-lg font-semibold mb-3">Prerequisites</h3>
              <ul class="space-y-2">
                <li
                  v-for="prereq in module.prerequisites"
                  :key="prereq"
                  class="flex items-start gap-2"
                >
                  <AlertCircle class="h-5 w-5 text-yellow-500 mt-0.5 flex-shrink-0" />
                  <span>{{ prereq }}</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Content Tab -->
          <div v-if="activeTab === 'content'" class="p-6 space-y-6">
            <div>
              <h3 class="text-lg font-semibold mb-4">Module Content</h3>

              <!-- Lessons List -->
              <div class="space-y-3">
                <div
                  v-for="(lesson, index) in module.lessons"
                  :key="lesson.id"
                  class="flex items-center gap-3 p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                >
                  <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center text-sm font-medium">
                    {{ index + 1 }}
                  </div>
                  <div class="flex-1">
                    <h4 class="font-medium">{{ lesson.title }}</h4>
                  </div>
                  <Button variant="ghost" size="sm">
                    <Play class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>

            <!-- Module Description -->
            <div>
              <h3 class="text-lg font-semibold mb-3">Description</h3>
              <div class="prose prose-sm max-w-none">
                <p>{{ module.content || module.description }}</p>
              </div>
            </div>
          </div>

          <!-- Analytics Tab -->
          <div v-if="activeTab === 'analytics' && showAnalytics" class="p-6 space-y-6">
            <div>
              <h3 class="text-lg font-semibold mb-4">Usage Analytics</h3>

              <!-- Analytics Grid -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div class="p-4 border rounded-lg">
                  <div class="flex items-center gap-2 mb-2">
                    <TrendingUp class="h-4 w-4 text-primary" />
                    <span class="font-medium">Popularity Rank</span>
                  </div>
                  <div class="text-2xl font-bold">#{{ module.usageAnalytics.popularityRank }}</div>
                </div>

                <div class="p-4 border rounded-lg">
                  <div class="flex items-center gap-2 mb-2">
                    <Users class="h-4 w-4 text-primary" />
                    <span class="font-medium">Total Assignments</span>
                  </div>
                  <div class="text-2xl font-bold">{{ module.usageAnalytics.totalAssignments }}</div>
                </div>

                <div class="p-4 border rounded-lg">
                  <div class="flex items-center gap-2 mb-2">
                    <Activity class="h-4 w-4 text-primary" />
                    <span class="font-medium">Average Score</span>
                  </div>
                  <div class="text-2xl font-bold">{{ module.usageAnalytics.averageScore }}%</div>
                </div>
              </div>

              <!-- Assignment Distribution -->
              <div class="p-4 border rounded-lg">
                <h4 class="font-medium mb-3">Assignment Distribution</h4>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span>Tracks:</span>
                    <span class="font-medium">{{ module.usageAnalytics.assignmentsByTarget.tracks }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>Courses:</span>
                    <span class="font-medium">{{ module.usageAnalytics.assignmentsByTarget.courses }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>Levels:</span>
                    <span class="font-medium">{{ module.usageAnalytics.assignmentsByTarget.levels }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Assignments Tab -->
          <div v-if="activeTab === 'assignments' && showAssignmentOptions" class="p-6 space-y-6">
            <!-- Current Assignments -->
            <div v-if="assignmentHistory.length > 0">
              <h3 class="text-lg font-semibold mb-4">Current Assignments</h3>
              <div class="space-y-2">
                <div
                  v-for="assignment in assignmentHistory"
                  :key="assignment.id"
                  class="flex items-center justify-between p-3 border rounded-lg"
                >
                  <div class="flex items-center gap-3">
                    <component :is="getTargetTypeIcon(assignment.targetType)" class="h-4 w-4" />
                    <div>
                      <div class="font-medium">{{ assignment.target.title }}</div>
                      <div class="text-sm text-muted-foreground">{{ assignment.targetType }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <Badge :variant="assignment.isActive ? 'default' : 'secondary'">
                      {{ assignment.isActive ? 'Active' : 'Inactive' }}
                    </Badge>
                    <Button variant="ghost" size="sm">
                      <Edit class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            <!-- New Assignment -->
            <div>
              <h3 class="text-lg font-semibold mb-4">Assign to New Targets</h3>

              <div v-if="availableTargets.length > 0" class="space-y-4">
                <!-- Target Selection -->
                <div class="space-y-2">
                  <div
                    v-for="target in availableTargets"
                    :key="target.id"
                    class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-muted/50 transition-colors"
                    @click="toggleTargetSelection(target.id)"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedTargets.includes(target.id)"
                      class="rounded"
                      @click.stop
                      @change="toggleTargetSelection(target.id)"
                    />
                    <component :is="getTargetTypeIcon(target.type)" class="h-4 w-4" />
                    <div class="flex-1">
                      <div class="font-medium">{{ target.title }}</div>
                      <div class="text-sm text-muted-foreground">
                        {{ target.type }} • {{ target.currentAssignments.length }} modules assigned
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Assignment Actions -->
                <div class="flex gap-2">
                  <Button
                    @click="handleAssign"
                    :disabled="selectedTargets.length === 0 || isAssigning"
                    class="flex-1"
                  >
                    <Plus class="h-4 w-4 mr-2" />
                    {{ isAssigning ? 'Assigning...' : `Assign to ${selectedTargets.length} target${selectedTargets.length !== 1 ? 's' : ''}` }}
                  </Button>
                </div>
              </div>

              <div v-else class="text-center py-8 text-muted-foreground">
                <Target class="h-8 w-8 mx-auto mb-2" />
                <p>No available targets for assignment</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex-shrink-0 flex items-center justify-between p-6 border-t bg-muted/50">
          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm">
              <Download class="h-4 w-4 mr-2" />
              Export
            </Button>

            <Button
              v-if="showAssignmentOptions"
              variant="outline"
              size="sm"
              @click="handleDelete"
            >
              <Trash2 class="h-4 w-4 mr-2" />
              Delete
            </Button>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" @click="handleClose">
              Cancel
            </Button>

            <Button v-if="!showAssignmentOptions">
              <Play class="h-4 w-4 mr-2" />
              Start Learning
            </Button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
/* Custom scrollbar for content area */
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

/* Smooth transitions */
.transition-colors {
  transition-property: color, background-color, border-color;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}

/* Mobile optimizations */
@media (max-width: 640px) {
  .grid-cols-2.md\:grid-cols-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }
}

/* Prose styling for content */
.prose {
  color: hsl(var(--foreground));
}

.prose p {
  margin-bottom: 1rem;
  line-height: 1.6;
}

/* Animation for loading spinner */
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}

/* Focus styles for accessibility */
button:focus-visible,
input:focus-visible {
  outline: 2px solid hsl(var(--ring));
  outline-offset: 2px;
}

/* Tab indicator animation */
.border-b-2 {
  transition: border-color 0.2s ease-in-out;
}
</style>
