<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  BookOpen,
  Star,
  Users,
  Clock,
  Calendar,
  User,
  Play,
  CheckCircle,
  Lock,
  ArrowLeft,
  Share2,
  Heart,
  MessageCircle,
  TrendingUp,
  Award,
  Target,
  Loader2,
  AlertCircle,
  Edit,
  Settings,
  MoreHorizontal,
  Download,
  Flag
} from 'lucide-vue-next';
import { useCourseManagement } from '@/composables/useCourseManagement';
import { useUnifiedProgress } from '@/composables/useUnifiedProgress';
import ModuleCard from './ModuleCard.vue';
import {
  Course,
  CourseEnrollment,
  ModuleProgress,
  LearningProgress,
  Achievement
} from '@/types/enhanced-classroom';

interface Props {
  courseId: string;
  showEnrollmentButton?: boolean;
  showEditButton?: boolean;
  showProgressTracking?: boolean;
}

interface Emits {
  (e: 'enroll', courseId: string): void;
  (e: 'unenroll', courseId: string): void;
  (e: 'startModule', moduleId: string): void;
  (e: 'editCourse', courseId: string): void;
  (e: 'back'): void;
}

const props = withDefaults(defineProps<Props>(), {
  showEnrollmentButton: true,
  showEditButton: false,
  showProgressTracking: true
});

const emit = defineEmits<Emits>();

// Composables
const {
  currentCourse,
  fetchCourse,
  loading: courseLoading,
  error: courseError
} = useCourseManagement();

const {
  userProgress,
  courseProgress,
  moduleProgress,
  achievements,
  fetchUserProgress,
  loading: progressLoading
} = useUnifiedProgress();

// Local state
const activeTab = ref('overview');
const isEnrolled = ref(false);
const enrollmentLoading = ref(false);
const userEnrollment = ref<CourseEnrollment | null>(null);
const showFullDescription = ref(false);
const selectedModuleId = ref<string | null>(null);

// Reviews and ratings
const reviews = ref<any[]>([]);
const userRating = ref(0);
const showReviewForm = ref(false);

// Recommendations
const recommendedCourses = ref<Course[]>([]);

// Computed properties
const course = computed(() => currentCourse.value);

const courseStats = computed(() => {
  if (!course.value) return null;

  return {
    totalModules: course.value.moduleCount,
    completedModules: userEnrollment.value ?
      Object.values(userEnrollment.value.moduleProgress || {}).filter(p => p.progress === 100).length : 0,
    totalDuration: course.value.estimatedDuration,
    timeSpent: userEnrollment.value?.timeSpent || 0,
    overallProgress: userEnrollment.value?.progress || 0
  };
});

const formattedDuration = computed(() => {
  if (!course.value) return '';

  const minutes = course.value.estimatedDuration;
  if (minutes >= 60) {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
  }
  return `${minutes}m`;
});

const formattedTimeSpent = computed(() => {
  if (!courseStats.value) return '';

  const minutes = courseStats.value.timeSpent;
  if (minutes >= 60) {
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
  }
  return `${minutes}m`;
});

const sortedModuleAssignments = computed(() => {
  if (!course.value) return [];

  return [...course.value.moduleAssignments].sort((a, b) => a.order - b.order);
});

const sectionsWithModules = computed(() => {
  if (!course.value?.structure?.sections) return [];

  return course.value.structure.sections.map(section => ({
    ...section,
    moduleAssignments: course.value!.moduleAssignments
      .filter(assignment => assignment.targetId === section.id)
      .sort((a, b) => a.order - b.order)
  }));
});

const unassignedModules = computed(() => {
  if (!course.value) return [];

  const sectionIds = course.value.structure?.sections?.map(s => s.id) || [];
  return course.value.moduleAssignments
    .filter(assignment => !sectionIds.includes(assignment.targetId))
    .sort((a, b) => a.order - b.order);
});

const canEnroll = computed(() => {
  if (!course.value) return false;

  return course.value.isPublished &&
         !isEnrolled.value &&
         course.value.settings.enrollmentType === 'open';
});

const nextModule = computed(() => {
  if (!isEnrolled.value || !userEnrollment.value) return null;

  // Find the first incomplete module
  for (const assignment of sortedModuleAssignments.value) {
    const progress = userEnrollment.value.moduleProgress[assignment.moduleId];
    if (!progress || progress.progress < 100) {
      return assignment;
    }
  }

  return null;
});

const completionPercentage = computed(() => {
  if (!courseStats.value) return 0;
  return Math.round(courseStats.value.overallProgress);
});

const averageRating = computed(() => {
  if (!course.value) return 0;
  return course.value.rating;
});

const ratingDistribution = computed(() => {
  // This would typically come from the API
  return {
    5: 45,
    4: 30,
    3: 15,
    2: 7,
    1: 3
  };
});

// Methods
const enrollInCourse = async () => {
  if (!course.value || enrollmentLoading.value) return;

  try {
    enrollmentLoading.value = true;

    // This would typically call an API
    await new Promise(resolve => setTimeout(resolve, 1000));

    isEnrolled.value = true;

    // Create mock enrollment
    userEnrollment.value = {
      id: `enrollment-${Date.now()}`,
      userId: 'current-user',
      courseId: course.value.id,
      enrolledAt: new Date(),
      progress: 0,
      lastAccessedAt: new Date(),
      moduleProgress: {},
      timeSpent: 0,
      completionStreak: 0,
      achievements: []
    };

    emit('enroll', course.value.id);

  } catch (error) {
    console.error('Failed to enroll:', error);
  } finally {
    enrollmentLoading.value = false;
  }
};

const unenrollFromCourse = async () => {
  if (!course.value || enrollmentLoading.value) return;

  const confirmed = confirm('Are you sure you want to unenroll from this course? Your progress will be saved.');
  if (!confirmed) return;

  try {
    enrollmentLoading.value = true;

    // This would typically call an API
    await new Promise(resolve => setTimeout(resolve, 1000));

    isEnrolled.value = false;
    userEnrollment.value = null;

    emit('unenroll', course.value.id);

  } catch (error) {
    console.error('Failed to unenroll:', error);
  } finally {
    enrollmentLoading.value = false;
  }
};

const startModule = (moduleId: string) => {
  selectedModuleId.value = moduleId;
  emit('startModule', moduleId);
};

const continueFromLastModule = () => {
  if (nextModule.value) {
    startModule(nextModule.value.moduleId);
  }
};

const editCourse = () => {
  if (course.value) {
    emit('editCourse', course.value.id);
  }
};

const goBack = () => {
  emit('back');
};

const shareContent = async () => {
  if (!course.value) return;

  try {
    await navigator.share({
      title: course.value.title,
      text: course.value.description,
      url: window.location.href
    });
  } catch (error) {
    // Fallback to copying URL
    await navigator.clipboard.writeText(window.location.href);
    // Show toast notification
  }
};

const toggleFavorite = () => {
  // Implementation for favoriting courses
  console.log('Toggle favorite');
};

const reportContent = () => {
  // Implementation for reporting inappropriate content
  console.log('Report content');
};

const getModuleProgress = (moduleId: string): ModuleProgress | null => {
  if (!userEnrollment.value) return null;
  return userEnrollment.value.moduleProgress[moduleId] || null;
};

const isModuleCompleted = (moduleId: string): boolean => {
  const progress = getModuleProgress(moduleId);
  return progress ? progress.progress === 100 : false;
};

const isModuleUnlocked = (assignment: any): boolean => {
  if (!course.value?.structure?.linearProgression) return true;
  if (!userEnrollment.value) return assignment.order === 1;

  // Check if previous modules are completed
  const previousAssignments = sortedModuleAssignments.value.filter(a => a.order < assignment.order);
  return previousAssignments.every(a => isModuleCompleted(a.moduleId));
};

const formatDate = (date: Date) => {
  return new Date(date).toLocaleDateString();
};

const formatProgress = (progress: number) => {
  return Math.round(progress);
};

// Load mock data
const loadMockData = () => {
  // Mock reviews
  reviews.value = [
    {
      id: '1',
      user: { name: 'Alice Johnson', avatar: null },
      rating: 5,
      comment: 'Excellent course! Very comprehensive and well-structured.',
      createdAt: new Date(Date.now() - 86400000 * 7),
      helpful: 12
    },
    {
      id: '2',
      user: { name: 'Bob Smith', avatar: null },
      rating: 4,
      comment: 'Great content, but could use more practical examples.',
      createdAt: new Date(Date.now() - 86400000 * 14),
      helpful: 8
    },
    {
      id: '3',
      user: { name: 'Carol Davis', avatar: null },
      rating: 5,
      comment: 'Perfect for beginners. The instructor explains everything clearly.',
      createdAt: new Date(Date.now() - 86400000 * 21),
      helpful: 15
    }
  ];

  // Mock enrollment status (this would come from API)
  isEnrolled.value = Math.random() > 0.5;

  if (isEnrolled.value && course.value) {
    userEnrollment.value = {
      id: `enrollment-${course.value.id}`,
      userId: 'current-user',
      courseId: course.value.id,
      enrolledAt: new Date(Date.now() - 86400000 * 30),
      progress: Math.floor(Math.random() * 100),
      lastAccessedAt: new Date(Date.now() - 86400000 * 2),
      moduleProgress: {},
      timeSpent: Math.floor(Math.random() * 300),
      completionStreak: Math.floor(Math.random() * 10),
      achievements: []
    };

    // Mock module progress
    course.value.moduleAssignments.forEach((assignment, index) => {
      if (userEnrollment.value) {
        const progress = index < 3 ? 100 : Math.floor(Math.random() * 100);
        userEnrollment.value.moduleProgress[assignment.moduleId] = {
          moduleId: assignment.moduleId,
          assignmentId: assignment.id,
          startedAt: new Date(Date.now() - 86400000 * (10 - index)),
          completedAt: progress === 100 ? new Date(Date.now() - 86400000 * (8 - index)) : undefined,
          progress,
          timeSpent: Math.floor(Math.random() * 60),
          attempts: 1,
          lastAccessedAt: new Date(Date.now() - 86400000 * (5 - index)),
          lessonProgress: {}
        };
      }
    });
  }
};

// Watchers
watch(() => props.courseId, async (newId) => {
  if (newId) {
    await fetchCourse(newId);
    loadMockData();
  }
});

// Lifecycle
onMounted(async () => {
  if (props.courseId) {
    await fetchCourse(props.courseId);
    loadMockData();

    if (props.showProgressTracking && isEnrolled.value) {
      await fetchUserProgress('current-user');
    }
  }
});
</script>

<template>
  <div class="course-detail h-full flex flex-col">
    <!-- Header -->
    <div class="flex-shrink-0 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div class="container mx-auto px-4 py-4">
        <div class="flex items-center gap-4 mb-4">
          <Button variant="ghost" size="sm" @click="goBack">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back
          </Button>

          <div class="flex items-center gap-2 ml-auto">
            <Button variant="ghost" size="sm" @click="shareContent">
              <Share2 class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="sm" @click="toggleFavorite">
              <Heart class="h-4 w-4" />
            </Button>
            <Button v-if="showEditButton" variant="outline" size="sm" @click="editCourse">
              <Edit class="h-4 w-4 mr-2" />
              Edit
            </Button>
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

      <!-- Course Content -->
      <div v-else-if="course" class="h-full overflow-auto">
        <!-- Course Hero -->
        <div class="bg-gradient-to-r from-primary/10 to-primary/5 border-b">
          <div class="container mx-auto px-4 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
              <!-- Course Info -->
              <div class="lg:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                  <Badge>{{ course.category }}</Badge>
                  <Badge
                    :class="{
                      'bg-green-500 text-white': course.difficulty === 'beginner',
                      'bg-yellow-500 text-white': course.difficulty === 'intermediate',
                      'bg-red-500 text-white': course.difficulty === 'advanced'
                    }"
                  >
                    {{ course.difficulty }}
                  </Badge>
                  <Badge v-if="course.isPublished" variant="default">
                    Published
                  </Badge>
                  <Badge v-else variant="secondary">
                    Draft
                  </Badge>
                </div>

                <h1 class="text-3xl font-bold mb-4">{{ course.title }}</h1>

                <div class="flex items-center gap-6 text-sm text-muted-foreground mb-6">
                  <div class="flex items-center gap-1">
                    <Star class="h-4 w-4 fill-current text-yellow-400" />
                    <span class="font-medium">{{ averageRating.toFixed(1) }}</span>
                    <span>({{ course.reviewCount }} reviews)</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <Users class="h-4 w-4" />
                    <span>{{ course.enrollmentCount }} students</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <Clock class="h-4 w-4" />
                    <span>{{ formattedDuration }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <BookOpen class="h-4 w-4" />
                    <span>{{ course.moduleCount }} modules</span>
                  </div>
                </div>

                <!-- Description -->
                <div class="prose prose-sm max-w-none">
                  <div
                    v-if="showFullDescription || course.description.length <= 300"
                    v-html="course.description"
                  />
                  <div v-else>
                    <div v-html="course.description.substring(0, 300) + '...'" />
                    <Button
                      variant="link"
                      class="p-0 h-auto mt-2"
                      @click="showFullDescription = true"
                    >
                      Show more
                    </Button>
                  </div>
                </div>

                <!-- Instructor -->
                <div class="flex items-center gap-3 mt-6">
                  <Avatar>
                    <AvatarImage :src="course.instructor.avatar" />
                    <AvatarFallback>
                      {{ course.instructor.name.split(' ').map(n => n[0]).join('') }}
                    </AvatarFallback>
                  </Avatar>
                  <div>
                    <p class="font-medium">{{ course.instructor.name }}</p>
                    <p class="text-sm text-muted-foreground">Instructor</p>
                  </div>
                </div>
              </div>

              <!-- Enrollment Card -->
              <div class="lg:col-span-1">
                <Card class="sticky top-4">
                  <CardContent class="p-6">
                    <!-- Progress (if enrolled) -->
                    <div v-if="isEnrolled && courseStats" class="mb-6">
                      <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">Your Progress</span>
                        <span class="text-sm text-muted-foreground">
                          {{ completionPercentage }}%
                        </span>
                      </div>
                      <Progress :value="completionPercentage" class="mb-4" />

                      <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                          <p class="text-muted-foreground">Completed</p>
                          <p class="font-medium">
                            {{ courseStats.completedModules }}/{{ courseStats.totalModules }} modules
                          </p>
                        </div>
                        <div>
                          <p class="text-muted-foreground">Time Spent</p>
                          <p class="font-medium">{{ formattedTimeSpent }}</p>
                        </div>
                      </div>
                    </div>

                    <!-- Enrollment Actions -->
                    <div class="space-y-3">
                      <template v-if="isEnrolled">
                        <Button
                          v-if="nextModule"
                          class="w-full"
                          @click="continueFromLastModule"
                        >
                          <Play class="h-4 w-4 mr-2" />
                          Continue Learning
                        </Button>
                        <Button
                          v-else-if="completionPercentage === 100"
                          variant="outline"
                          class="w-full"
                        >
                          <Award class="h-4 w-4 mr-2" />
                          Course Completed
                        </Button>
                        <Button
                          v-else
                          variant="outline"
                          class="w-full"
                          @click="activeTab = 'content'"
                        >
                          <BookOpen class="h-4 w-4 mr-2" />
                          View Content
                        </Button>

                        <Button
                          variant="ghost"
                          size="sm"
                          class="w-full"
                          @click="unenrollFromCourse"
                          :disabled="enrollmentLoading"
                        >
                          Unenroll
                        </Button>
                      </template>

                      <template v-else>
                        <Button
                          v-if="canEnroll && showEnrollmentButton"
                          class="w-full"
                          @click="enrollInCourse"
                          :disabled="enrollmentLoading"
                        >
                          <Loader2 v-if="enrollmentLoading" class="h-4 w-4 mr-2 animate-spin" />
                          <Play v-else class="h-4 w-4 mr-2" />
                          Enroll Now
                        </Button>

                        <Alert v-else-if="!course.isPublished">
                          <AlertCircle class="h-4 w-4" />
                          <AlertDescription>
                            This course is not yet published.
                          </AlertDescription>
                        </Alert>

                        <Alert v-else-if="course.settings.enrollmentType !== 'open'">
                          <AlertCircle class="h-4 w-4" />
                          <AlertDescription>
                            This course requires {{ course.settings.enrollmentType === 'invitation' ? 'an invitation' : 'approval' }} to enroll.
                          </AlertDescription>
                        </Alert>
                      </template>
                    </div>

                    <!-- Course Features -->
                    <Separator class="my-6" />

                    <div class="space-y-3 text-sm">
                      <h4 class="font-medium">This course includes:</h4>
                      <div class="space-y-2">
                        <div class="flex items-center gap-2">
                          <BookOpen class="h-4 w-4 text-muted-foreground" />
                          <span>{{ course.moduleCount }} modules</span>
                        </div>
                        <div class="flex items-center gap-2">
                          <Clock class="h-4 w-4 text-muted-foreground" />
                          <span>{{ formattedDuration }} of content</span>
                        </div>
                        <div v-if="course.settings.enableDiscussions" class="flex items-center gap-2">
                          <MessageCircle class="h-4 w-4 text-muted-foreground" />
                          <span>Discussion forums</span>
                        </div>
                        <div v-if="course.certificateTemplate" class="flex items-center gap-2">
                          <Award class="h-4 w-4 text-muted-foreground" />
                          <span>Certificate of completion</span>
                        </div>
                        <div class="flex items-center gap-2">
                          <Download class="h-4 w-4 text-muted-foreground" />
                          <span>Downloadable resources</span>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>
          </div>
        </div>

        <!-- Course Tabs -->
        <div class="container mx-auto px-4 py-8">
          <Tabs v-model="activeTab" class="w-full">
            <TabsList class="grid w-full grid-cols-4">
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="content">Content</TabsTrigger>
              <TabsTrigger value="reviews">Reviews</TabsTrigger>
              <TabsTrigger value="instructor">Instructor</TabsTrigger>
            </TabsList>

            <!-- Overview Tab -->
            <TabsContent value="overview" class="mt-6 space-y-6">
              <!-- Learning Objectives -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center gap-2">
                    <Target class="h-5 w-5" />
                    What you'll learn
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div
                      v-for="objective in course.moduleAssignments.flatMap(a => a.module.learningObjectives).slice(0, 8)"
                      :key="objective"
                      class="flex items-start gap-2"
                    >
                      <CheckCircle class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                      <span class="text-sm">{{ objective }}</span>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <!-- Prerequisites -->
              <Card v-if="course.prerequisites.length > 0">
                <CardHeader>
                  <CardTitle>Prerequisites</CardTitle>
                </CardHeader>
                <CardContent>
                  <ul class="space-y-2">
                    <li
                      v-for="prereq in course.prerequisites"
                      :key="prereq"
                      class="flex items-start gap-2 text-sm"
                    >
                      <div class="w-1.5 h-1.5 bg-muted-foreground rounded-full mt-2 flex-shrink-0" />
                      <span>{{ prereq }}</span>
                    </li>
                  </ul>
                </CardContent>
              </Card>

              <!-- Course Stats -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card>
                  <CardContent class="p-6 text-center">
                    <div class="text-2xl font-bold text-primary mb-2">
                      {{ course.enrollmentCount }}
                    </div>
                    <p class="text-sm text-muted-foreground">Students Enrolled</p>
                  </CardContent>
                </Card>

                <Card>
                  <CardContent class="p-6 text-center">
                    <div class="text-2xl font-bold text-primary mb-2">
                      {{ averageRating.toFixed(1) }}
                    </div>
                    <div class="flex items-center justify-center gap-1 mb-1">
                      <Star
                        v-for="i in 5"
                        :key="i"
                        class="h-3 w-3"
                        :class="i <= averageRating ? 'fill-current text-yellow-400' : 'text-muted-foreground'"
                      />
                    </div>
                    <p class="text-sm text-muted-foreground">Average Rating</p>
                  </CardContent>
                </Card>

                <Card>
                  <CardContent class="p-6 text-center">
                    <div class="text-2xl font-bold text-primary mb-2">
                      {{ Math.round(course.completionCount / course.enrollmentCount * 100) || 0 }}%
                    </div>
                    <p class="text-sm text-muted-foreground">Completion Rate</p>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <!-- Content Tab -->
            <TabsContent value="content" class="mt-6">
              <Card>
                <CardHeader>
                  <CardTitle>Course Content</CardTitle>
                  <CardDescription>
                    {{ course.moduleCount }} modules • {{ formattedDuration }} total length
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <!-- Sections -->
                  <div v-if="sectionsWithModules.length > 0" class="space-y-4">
                    <div
                      v-for="section in sectionsWithModules"
                      :key="section.id"
                      class="border rounded-lg"
                    >
                      <div class="p-4 border-b bg-muted/50">
                        <h3 class="font-medium">{{ section.title }}</h3>
                        <p v-if="section.description" class="text-sm text-muted-foreground mt-1">
                          {{ section.description }}
                        </p>
                      </div>

                      <div class="p-4 space-y-3">
                        <div
                          v-for="assignment in section.moduleAssignments"
                          :key="assignment.id"
                          class="flex items-center gap-3 p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                          :class="{
                            'cursor-pointer': isEnrolled && isModuleUnlocked(assignment),
                            'opacity-50': isEnrolled && !isModuleUnlocked(assignment)
                          }"
                          @click="isEnrolled && isModuleUnlocked(assignment) && startModule(assignment.moduleId)"
                        >
                          <div class="flex-shrink-0">
                            <div
                              v-if="isEnrolled && isModuleCompleted(assignment.moduleId)"
                              class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center"
                            >
                              <CheckCircle class="h-4 w-4 text-white" />
                            </div>
                            <div
                              v-else-if="isEnrolled && !isModuleUnlocked(assignment)"
                              class="w-6 h-6 bg-muted rounded-full flex items-center justify-center"
                            >
                              <Lock class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div
                              v-else
                              class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center text-sm font-medium"
                            >
                              {{ assignment.order }}
                            </div>
                          </div>

                          <div class="flex-1 min-w-0">
                            <h4 class="font-medium truncate">{{ assignment.module.title }}</h4>
                            <p class="text-sm text-muted-foreground">
                              {{ assignment.module.estimatedDuration }} minutes
                            </p>
                          </div>

                          <div class="flex items-center gap-2">
                            <Badge v-if="assignment.isRequired" variant="outline" class="text-xs">
                              Required
                            </Badge>

                            <div v-if="isEnrolled" class="text-sm text-muted-foreground">
                              {{ formatProgress(getModuleProgress(assignment.moduleId)?.progress || 0) }}%
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Unassigned Modules -->
                  <div v-if="unassignedModules.length > 0" class="space-y-3">
                    <h3 v-if="sectionsWithModules.length > 0" class="font-medium mt-6">Additional Modules</h3>

                    <div
                      v-for="assignment in unassignedModules"
                      :key="assignment.id"
                      class="flex items-center gap-3 p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                      :class="{
                        'cursor-pointer': isEnrolled && isModuleUnlocked(assignment),
                        'opacity-50': isEnrolled && !isModuleUnlocked(assignment)
                      }"
                      @click="isEnrolled && isModuleUnlocked(assignment) && startModule(assignment.moduleId)"
                    >
                      <div class="flex-shrink-0">
                        <div
                          v-if="isEnrolled && isModuleCompleted(assignment.moduleId)"
                          class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center"
                        >
                          <CheckCircle class="h-4 w-4 text-white" />
                        </div>
                        <div
                          v-else-if="isEnrolled && !isModuleUnlocked(assignment)"
                          class="w-6 h-6 bg-muted rounded-full flex items-center justify-center"
                        >
                          <Lock class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div
                          v-else
                          class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center text-sm font-medium"
                        >
                          {{ assignment.order }}
                        </div>
                      </div>

                      <div class="flex-1 min-w-0">
                        <h4 class="font-medium truncate">{{ assignment.module.title }}</h4>
                        <p class="text-sm text-muted-foreground">
                          {{ assignment.module.estimatedDuration }} minutes
                        </p>
                      </div>

                      <div class="flex items-center gap-2">
                        <Badge v-if="assignment.isRequired" variant="outline" class="text-xs">
                          Required
                        </Badge>

                        <div v-if="isEnrolled" class="text-sm text-muted-foreground">
                          {{ formatProgress(getModuleProgress(assignment.moduleId)?.progress || 0) }}%
                        </div>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <!-- Reviews Tab -->
            <TabsContent value="reviews" class="mt-6 space-y-6">
              <!-- Rating Summary -->
              <Card>
                <CardContent class="p-6">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="text-center">
                      <div class="text-4xl font-bold mb-2">{{ averageRating.toFixed(1) }}</div>
                      <div class="flex items-center justify-center gap-1 mb-2">
                        <Star
                          v-for="i in 5"
                          :key="i"
                          class="h-5 w-5"
                          :class="i <= averageRating ? 'fill-current text-yellow-400' : 'text-muted-foreground'"
                        />
                      </div>
                      <p class="text-sm text-muted-foreground">
                        Based on {{ course.reviewCount }} reviews
                      </p>
                    </div>

                    <div class="space-y-2">
                      <div
                        v-for="(count, rating) in ratingDistribution"
                        :key="rating"
                        class="flex items-center gap-2"
                      >
                        <span class="text-sm w-8">{{ rating }}★</span>
                        <div class="flex-1 bg-muted rounded-full h-2">
                          <div
                            class="bg-yellow-400 h-2 rounded-full"
                            :style="{ width: `${(count / course.reviewCount) * 100}%` }"
                          />
                        </div>
                        <span class="text-sm text-muted-foreground w-8">{{ count }}</span>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <!-- Reviews List -->
              <div class="space-y-4">
                <div
                  v-for="review in reviews"
                  :key="review.id"
                  class="border rounded-lg p-4"
                >
                  <div class="flex items-start gap-3">
                    <Avatar class="w-10 h-10">
                      <AvatarImage :src="review.user.avatar" />
                      <AvatarFallback>
                        {{ review.user.name.split(' ').map(n => n[0]).join('') }}
                      </AvatarFallback>
                    </Avatar>

                    <div class="flex-1">
                      <div class="flex items-center gap-2 mb-2">
                        <span class="font-medium">{{ review.user.name }}</span>
                        <div class="flex items-center gap-1">
                          <Star
                            v-for="i in 5"
                            :key="i"
                            class="h-3 w-3"
                            :class="i <= review.rating ? 'fill-current text-yellow-400' : 'text-muted-foreground'"
                          />
                        </div>
                        <span class="text-sm text-muted-foreground">
                          {{ formatDate(review.createdAt) }}
                        </span>
                      </div>

                      <p class="text-sm mb-3">{{ review.comment }}</p>

                      <div class="flex items-center gap-4 text-xs text-muted-foreground">
                        <button class="hover:text-foreground">
                          Helpful ({{ review.helpful }})
                        </button>
                        <button class="hover:text-foreground">
                          <Flag class="h-3 w-3 mr-1 inline" />
                          Report
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>

            <!-- Instructor Tab -->
            <TabsContent value="instructor" class="mt-6">
              <Card>
                <CardContent class="p-6">
                  <div class="flex items-start gap-6">
                    <Avatar class="w-20 h-20">
                      <AvatarImage :src="course.instructor.avatar" />
                      <AvatarFallback class="text-lg">
                        {{ course.instructor.name.split(' ').map(n => n[0]).join('') }}
                      </AvatarFallback>
                    </Avatar>

                    <div class="flex-1">
                      <h3 class="text-xl font-semibold mb-2">{{ course.instructor.name }}</h3>
                      <p class="text-muted-foreground mb-4">Course Instructor</p>

                      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="text-center">
                          <div class="text-lg font-semibold">4.8</div>
                          <div class="text-sm text-muted-foreground">Rating</div>
                        </div>
                        <div class="text-center">
                          <div class="text-lg font-semibold">1,234</div>
                          <div class="text-sm text-muted-foreground">Reviews</div>
                        </div>
                        <div class="text-center">
                          <div class="text-lg font-semibold">5,678</div>
                          <div class="text-sm text-muted-foreground">Students</div>
                        </div>
                        <div class="text-center">
                          <div class="text-lg font-semibold">12</div>
                          <div class="text-sm text-muted-foreground">Courses</div>
                        </div>
                      </div>

                      <p class="text-sm leading-relaxed">
                        {{ course.instructor.name }} is an experienced educator with over 10 years in the field.
                        They specialize in creating engaging and comprehensive learning experiences that help
                        students achieve their goals. Their teaching approach focuses on practical application
                        and real-world examples.
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </div>
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

/* Smooth transitions */
.course-detail {
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

/* Hover effects */
.group:hover {
  transform: translateY(-1px);
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  .grid-cols-1.lg\:grid-cols-3 {
    grid-template-columns: 1fr;
  }

  .grid-cols-1.md\:grid-cols-2 {
    grid-template-columns: 1fr;
  }

  .grid-cols-1.md\:grid-cols-3 {
    grid-template-columns: 1fr;
  }
}

/* Progress bar styling */
.progress-bar {
  transition: width 0.3s ease-in-out;
}

/* Rating stars */
.rating-stars {
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
}
</style>
