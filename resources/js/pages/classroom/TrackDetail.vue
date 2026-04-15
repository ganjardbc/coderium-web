<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { useApi } from '@/composables/useApi';
import { globalLoading } from '@/composables/useLoading';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { Track } from '@/types';
import type { ModuleAssignment, StandaloneModule } from '@/types/enhanced-classroom';
import { Head } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    CheckCircle,
    Clock,
    GraduationCap,
    Lock,
    Play,
    Target,
    Users,
    Layers,
    Settings,
    Edit,
    Plus,
    AlertCircle,
    TrendingUp,
    BarChart3,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    track: Track;
    // Enhanced props for module assignment display
    moduleAssignments?: ModuleAssignment[];
    availableModules?: StandaloneModule[];
    showModuleAssignments?: boolean;
    enableAssignmentEditing?: boolean;
    userRole?: 'student' | 'instructor' | 'admin';
}

const props = withDefaults(defineProps<Props>(), {
    showModuleAssignments: true,
    enableAssignmentEditing: false,
    userRole: 'student',
});

const emit = defineEmits<{
    moduleAssignmentUpdated: [assignments: ModuleAssignment[]];
    enrollmentChanged: [trackId: string, enrolled: boolean];
}>();

// Enhanced state for module assignment management
const showAssignmentEditor = ref(false);
const selectedLevel = ref<number | null>(null);
const assignmentEditMode = ref(false);

const { api, get } = useApi();
const { isLoading } = globalLoading;

const isEnrolling = isLoading('track-enrollment');

const enrollInTrack = async () => {
    try {
        await api.post(
            `/api/v1/classroom/tracks/${props.track.slug}/enroll`,
            {},
            {
                loadingKey: 'track-enrollment',
                successMessage: 'Successfully enrolled in track!',
                showSuccessMessage: true,
            },
        );

        // Refresh the page to get updated enrollment data
        get(
            `/classroom/${props.track.slug}`,
            {},
            {
                errorContext: 'Refresh track data',
            },
        );
    } catch {
        // Error is already handled by the API composable
    }
};

const formatDuration = (minutes: number | undefined): string => {
    if (!minutes) return 'N/A';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
};

const getDifficultyColor = (difficulty: string): string => {
    switch (difficulty) {
        case 'beginner':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'intermediate':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'advanced':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const isEnrolled = computed(() => !!props.track.enrollment);
const canEnroll = computed(() => !isEnrolled.value && props.track.is_published);
const progressPercentage = computed(
    () => props.track.enrollment?.progress_percentage || 0,
);

const sortedLevels = computed(() => {
    return (
        props.track.levels
            ?.slice()
            .sort((a, b) => a.order_index - b.order_index) || []
    );
});

// Enhanced computed properties for module assignments
const moduleAssignmentsByLevel = computed(() => {
    if (!props.moduleAssignments) return {};

    const assignmentMap: Record<number, ModuleAssignment[]> = {};
    props.moduleAssignments.forEach(assignment => {
        if (assignment.targetType === 'level') {
            const levelId = parseInt(assignment.targetId);
            if (!assignmentMap[levelId]) {
                assignmentMap[levelId] = [];
            }
            assignmentMap[levelId].push(assignment);
        }
    });

    // Sort assignments by order within each level
    Object.keys(assignmentMap).forEach(levelId => {
        assignmentMap[parseInt(levelId)].sort((a, b) => a.order - b.order);
    });

    return assignmentMap;
});

const totalModuleAssignments = computed(() => {
    return props.moduleAssignments?.length || 0;
});

const activeModuleAssignments = computed(() => {
    return props.moduleAssignments?.filter(a => a.isActive).length || 0;
});

const moduleAssignmentProgress = computed(() => {
    if (!props.moduleAssignments?.length) return 0;
    const completedAssignments = props.moduleAssignments.filter(a => {
        // This would come from actual progress data
        return false; // Placeholder
    }).length;
    return (completedAssignments / props.moduleAssignments.length) * 100;
});

const canEditAssignments = computed(() => {
    return props.enableAssignmentEditing &&
           (props.userRole === 'admin' || props.userRole === 'instructor');
});

const getModuleAssignmentsForLevel = (levelId: number) => {
    return moduleAssignmentsByLevel.value[levelId] || [];
};

const getAssignmentStatusColor = (assignment: ModuleAssignment) => {
    if (!assignment.isActive) return 'text-gray-500';
    if (assignment.isRequired) return 'text-blue-600';
    return 'text-green-600';
};

const getAssignmentStatusIcon = (assignment: ModuleAssignment) => {
    if (!assignment.isActive) return AlertCircle;
    if (assignment.isRequired) return CheckCircle;
    return BookOpen;
};

const getNextLesson = () => {
    if (!props.track.progress?.current_lesson) return null;
    return props.track.progress.current_lesson;
};

const startLearning = () => {
    const nextLesson = getNextLesson();
    if (nextLesson) {
        get(
            `/classroom/lessons/${nextLesson.id}`,
            {},
            {
                errorContext: 'Load lesson',
            },
        );
    } else if (sortedLevels.value.length > 0) {
        get(
            `/classroom/levels/${sortedLevels.value[0].id}`,
            {},
            {
                errorContext: 'Load level',
            },
        );
    }
};

const navigateToLevel = (levelId: number) => {
    get(
        `/classroom/levels/${levelId}`,
        {},
        {
            errorContext: 'Load level',
        },
    );
};

// Enhanced methods for module assignment management
const toggleAssignmentEditor = () => {
    showAssignmentEditor.value = !showAssignmentEditor.value;
};

const editLevelAssignments = (levelId: number) => {
    selectedLevel.value = levelId;
    assignmentEditMode.value = true;
    showAssignmentEditor.value = true;
};

const saveAssignmentChanges = async (assignments: ModuleAssignment[]) => {
    try {
        // This would call the API to update assignments
        emit('moduleAssignmentUpdated', assignments);
        assignmentEditMode.value = false;
        showAssignmentEditor.value = false;
    } catch (error) {
        console.error('Failed to save assignment changes:', error);
    }
};

const addModuleToLevel = async (levelId: number, moduleId: string) => {
    try {
        // This would call the API to add a module assignment
        console.log('Adding module', moduleId, 'to level', levelId);
    } catch (error) {
        console.error('Failed to add module to level:', error);
    }
};

const removeModuleFromLevel = async (assignmentId: string) => {
    try {
        // This would call the API to remove a module assignment
        console.log('Removing assignment', assignmentId);
    } catch (error) {
        console.error('Failed to remove module from level:', error);
    }
};
</script>

<template>
    <Head :title="`${track.title} - Classroom`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Track Header -->
        <section class="w-full py-8 border-b">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Track Info -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 flex items-center gap-3">
                            <Badge
                                :class="
                                    getDifficultyColor(track.difficulty_level)
                                "
                                class="text-sm font-medium"
                            >
                                {{
                                    track.difficulty_level
                                        .charAt(0)
                                        .toUpperCase() +
                                    track.difficulty_level.slice(1)
                                }}
                            </Badge>
                            <Badge v-if="track.is_premium" variant="secondary">
                                {{
                                    track.price ? `$${track.price}` : 'Premium'
                                }}
                            </Badge>
                            <Badge v-else variant="outline"> Free </Badge>
                        </div>

                        <h1 class="mb-4 text-3xl font-bold md:text-4xl">
                            {{ track.title }}
                        </h1>

                        <p class="mb-6 text-lg text-muted-foreground">
                            {{ track.description }}
                        </p>

                        <div
                            class="flex flex-wrap gap-6 text-sm text-muted-foreground"
                        >
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                <span>{{
                                    formatDuration(track.estimated_duration)
                                }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Users class="h-4 w-4" />
                                <span
                                    >{{
                                        track.enrollments_count || 0
                                    }}
                                    enrolled</span
                                >
                            </div>
                            <div class="flex items-center gap-2">
                                <BookOpen class="h-4 w-4" />
                                <span
                                    >{{ track.levels_count || 0 }} levels</span
                                >
                            </div>
                            <div class="flex items-center gap-2">
                                <GraduationCap class="h-4 w-4" />
                                <span>by {{ track.instructor?.name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Enrollment Card -->
                    <div class="lg:col-span-1">
                        <Card class="sticky top-4">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Target class="h-5 w-5" />
                                    Your Progress
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Progress for enrolled users -->
                                <div v-if="isEnrolled">
                                    <div
                                        class="mb-2 flex items-center justify-between text-sm"
                                    >
                                        <span class="text-muted-foreground"
                                            >Overall Progress</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                Math.round(progressPercentage)
                                            }}%</span
                                        >
                                    </div>
                                    <Progress
                                        :value="progressPercentage"
                                        class="mb-4"
                                    />

                                    <Button
                                        @click="startLearning"
                                        class="w-full"
                                        size="lg"
                                    >
                                        <Play class="mr-2 h-4 w-4" />
                                        Continue Learning
                                    </Button>
                                </div>

                                <!-- Enrollment for non-enrolled users -->
                                <div v-else-if="canEnroll">
                                    <Button
                                        @click="enrollInTrack"
                                        :disabled="isEnrolling.value"
                                        class="w-full"
                                        size="lg"
                                    >
                                        <GraduationCap class="mr-2 h-4 w-4" />
                                        {{
                                            isEnrolling.value
                                                ? 'Enrolling...'
                                                : 'Enroll Now'
                                        }}
                                    </Button>

                                    <p
                                        class="mt-2 text-center text-xs text-muted-foreground"
                                    >
                                        {{
                                            track.is_free
                                                ? 'Free to enroll'
                                                : `$${track.price} one-time payment`
                                        }}
                                    </p>
                                </div>

                                <!-- Not available -->
                                <div v-else class="text-center">
                                    <Lock
                                        class="mx-auto mb-2 h-8 w-8 text-muted-foreground"
                                    />
                                    <p class="text-sm text-muted-foreground">
                                        This track is not available for
                                        enrollment yet.
                                    </p>
                                </div>

                                <Separator />

                                <!-- Enhanced Track Stats with Module Assignment Info -->
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Difficulty</span
                                        >
                                        <span class="font-medium">{{
                                            track.difficulty_level
                                                .charAt(0)
                                                .toUpperCase() +
                                            track.difficulty_level.slice(1)
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Duration</span
                                        >
                                        <span class="font-medium">{{
                                            formatDuration(
                                                track.estimated_duration,
                                            )
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Levels</span
                                        >
                                        <span class="font-medium">{{
                                            track.levels_count || 0
                                        }}</span>
                                    </div>

                                    <!-- Enhanced Module Assignment Stats -->
                                    <div v-if="showModuleAssignments && totalModuleAssignments > 0">
                                        <Separator class="my-2" />
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Total Modules</span>
                                            <span class="font-medium">{{ totalModuleAssignments }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Active Modules</span>
                                            <span class="font-medium text-green-600">{{ activeModuleAssignments }}</span>
                                        </div>
                                        <div v-if="moduleAssignmentProgress > 0" class="flex justify-between">
                                            <span class="text-muted-foreground">Module Progress</span>
                                            <span class="font-medium">{{ Math.round(moduleAssignmentProgress) }}%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Assignment Management Controls (Admin/Instructor) -->
                                <div v-if="canEditAssignments" class="pt-4 border-t">
                                    <Button
                                        @click="toggleAssignmentEditor"
                                        variant="outline"
                                        size="sm"
                                        class="w-full"
                                    >
                                        <Settings class="mr-2 h-4 w-4" />
                                        Manage Assignments
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- Track Content -->
        <section class="py-8">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-8 lg:col-span-2">
                        <!-- Levels -->
                        <div>
                            <h2 class="mb-6 text-2xl font-bold">
                                Course Content
                            </h2>

                            <div
                                v-if="sortedLevels.length > 0"
                                class="space-y-4"
                            >
                                <Card
                                    v-for="(level, index) in sortedLevels"
                                    :key="level.id"
                                    class="group transition-all duration-200 hover:shadow-md"
                                >
                                    <CardHeader class="pb-3">
                                        <div
                                            class="flex items-start justify-between"
                                        >
                                            <div class="flex-1">
                                                <div
                                                    class="mb-2 flex items-center gap-3"
                                                >
                                                    <Badge
                                                        variant="outline"
                                                        class="text-xs"
                                                    >
                                                        Level {{ index + 1 }}
                                                    </Badge>
                                                    <Badge
                                                        :class="
                                                            getDifficultyColor(
                                                                level.difficulty,
                                                            )
                                                        "
                                                        class="text-xs"
                                                    >
                                                        {{
                                                            level.difficulty
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                            level.difficulty.slice(
                                                                1,
                                                            )
                                                        }}
                                                    </Badge>

                                                    <!-- Module Assignment Count Badge -->
                                                    <Badge
                                                        v-if="showModuleAssignments && getModuleAssignmentsForLevel(level.id).length > 0"
                                                        variant="secondary"
                                                        class="text-xs"
                                                    >
                                                        <Layers class="mr-1 h-3 w-3" />
                                                        {{ getModuleAssignmentsForLevel(level.id).length }} assigned
                                                    </Badge>
                                                </div>
                                                <CardTitle
                                                    class="text-lg transition-colors group-hover:text-primary"
                                                >
                                                    {{ level.title }}
                                                </CardTitle>
                                                <CardDescription class="mt-1">
                                                    {{ level.description }}
                                                </CardDescription>
                                            </div>

                                            <div
                                                class="ml-4 flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    {{
                                                        level.modules_count || 0
                                                    }}
                                                    modules
                                                </span>

                                                <!-- Assignment Management Button (Admin/Instructor) -->
                                                <Button
                                                    v-if="canEditAssignments"
                                                    @click="editLevelAssignments(level.id)"
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Edit class="h-4 w-4" />
                                                </Button>

                                                <Button
                                                    v-if="isEnrolled"
                                                    @click="
                                                        navigateToLevel(
                                                            level.id,
                                                        )
                                                    "
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Play
                                                        class="mr-1 h-4 w-4"
                                                    />
                                                    Start
                                                </Button>
                                                <Lock
                                                    v-else
                                                    class="h-4 w-4 text-muted-foreground"
                                                />
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <!-- Enhanced Module Assignment Display -->
                                    <CardContent
                                        v-if="showModuleAssignments && getModuleAssignmentsForLevel(level.id).length > 0"
                                        class="pt-0"
                                    >
                                        <div class="border-t pt-3">
                                            <div class="mb-2 flex items-center justify-between">
                                                <span class="text-sm font-medium text-muted-foreground">
                                                    Assigned Modules
                                                </span>
                                                <Button
                                                    v-if="canEditAssignments"
                                                    @click="editLevelAssignments(level.id)"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-6 px-2 text-xs"
                                                >
                                                    <Plus class="mr-1 h-3 w-3" />
                                                    Add Module
                                                </Button>
                                            </div>

                                            <div class="space-y-2">
                                                <div
                                                    v-for="assignment in getModuleAssignmentsForLevel(level.id)"
                                                    :key="assignment.id"
                                                    class="flex items-center justify-between rounded-md border p-2 text-sm"
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <component
                                                            :is="getAssignmentStatusIcon(assignment)"
                                                            :class="['h-4 w-4', getAssignmentStatusColor(assignment)]"
                                                        />
                                                        <span class="font-medium">{{ assignment.module?.title || 'Module' }}</span>
                                                        <Badge
                                                            v-if="assignment.isRequired"
                                                            variant="destructive"
                                                            class="text-xs"
                                                        >
                                                            Required
                                                        </Badge>
                                                        <Badge
                                                            v-else
                                                            variant="secondary"
                                                            class="text-xs"
                                                        >
                                                            Optional
                                                        </Badge>
                                                    </div>

                                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <span v-if="assignment.module?.estimatedDuration">
                                                            {{ formatDuration(assignment.module.estimatedDuration) }}
                                                        </span>
                                                        <span v-if="assignment.completionRate > 0">
                                                            {{ Math.round(assignment.completionRate) }}% complete
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Assignment Analytics (for detailed view) -->
                                            <div v-if="getModuleAssignmentsForLevel(level.id).length > 0" class="mt-3 pt-3 border-t">
                                                <div class="grid grid-cols-3 gap-4 text-center">
                                                    <div>
                                                        <div class="text-lg font-semibold">
                                                            {{ getModuleAssignmentsForLevel(level.id).filter(a => a.isActive).length }}
                                                        </div>
                                                        <div class="text-xs text-muted-foreground">Active</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-lg font-semibold">
                                                            {{ getModuleAssignmentsForLevel(level.id).filter(a => a.isRequired).length }}
                                                        </div>
                                                        <div class="text-xs text-muted-foreground">Required</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-lg font-semibold">
                                                            {{ Math.round(getModuleAssignmentsForLevel(level.id).reduce((acc, a) => acc + (a.completionRate || 0), 0) / getModuleAssignmentsForLevel(level.id).length) }}%
                                                        </div>
                                                        <div class="text-xs text-muted-foreground">Avg. Complete</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div v-else class="py-8 text-center">
                                <BookOpen
                                    class="mx-auto mb-4 h-12 w-12 text-muted-foreground"
                                />
                                <p class="text-muted-foreground">
                                    No content available yet.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-6">
                            <!-- Instructor Info -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Instructor</CardTitle
                                    >
                                </CardHeader>
                                <CardContent>
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10"
                                        >
                                            <GraduationCap
                                                class="h-6 w-6 text-primary"
                                            />
                                        </div>
                                        <div>
                                            <p class="font-medium">
                                                {{ track.instructor?.name }}
                                            </p>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                Course Instructor
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Enhanced Module Assignment Analytics -->
                            <Card v-if="showModuleAssignments && totalModuleAssignments > 0">
                                <CardHeader>
                                    <CardTitle
                                        class="flex items-center gap-2 text-lg"
                                    >
                                        <BarChart3 class="h-5 w-5" />
                                        Module Analytics
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-4">
                                        <!-- Assignment Overview -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-primary">
                                                    {{ totalModuleAssignments }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    Total Modules
                                                </div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-green-600">
                                                    {{ activeModuleAssignments }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    Active Modules
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div v-if="moduleAssignmentProgress > 0">
                                            <div class="mb-2 flex items-center justify-between text-sm">
                                                <span class="text-muted-foreground">Module Progress</span>
                                                <span class="font-medium">{{ Math.round(moduleAssignmentProgress) }}%</span>
                                            </div>
                                            <Progress :value="moduleAssignmentProgress" class="h-2" />
                                        </div>

                                        <!-- Assignment Breakdown -->
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Required Modules</span>
                                                <span class="font-medium">
                                                    {{ moduleAssignments?.filter(a => a.isRequired).length || 0 }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Optional Modules</span>
                                                <span class="font-medium">
                                                    {{ moduleAssignments?.filter(a => !a.isRequired).length || 0 }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Avg. Completion</span>
                                                <span class="font-medium">
                                                    {{ Math.round((moduleAssignments?.reduce((acc, a) => acc + (a.completionRate || 0), 0) || 0) / Math.max(totalModuleAssignments, 1)) }}%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- What You'll Learn -->
                            <Card>
                                <CardHeader>
                                    <CardTitle
                                        class="flex items-center gap-2 text-lg"
                                    >
                                        <Award class="h-5 w-5" />
                                        What You'll Learn
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul class="space-y-2 text-sm">
                                        <li class="flex items-start gap-2">
                                            <CheckCircle
                                                class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500"
                                            />
                                            <span
                                                >Master the fundamentals through
                                                hands-on practice</span
                                            >
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle
                                                class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500"
                                            />
                                            <span
                                                >Build real-world projects and
                                                applications</span
                                            >
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle
                                                class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500"
                                            />
                                            <span
                                                >Complete assessments to
                                                validate your knowledge</span
                                            >
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle
                                                class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500"
                                            />
                                            <span
                                                >Earn a certificate of
                                                completion</span
                                            >
                                        </li>
                                    </ul>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
