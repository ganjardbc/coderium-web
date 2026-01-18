<script setup lang="ts">
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
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    BookOpen,
    CheckCircle,
    Clock,
    GraduationCap,
    Lock,
    Play,
    Target,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    track: Track;
}

const props = defineProps<Props>();

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
            `/classroom/tracks/${props.track.slug}`,
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
</script>

<template>
    <Head :title="`${track.title} - Classroom`" />

    <FrontLayout>
        <!-- Back Navigation -->
        <div class="container mx-auto max-w-7xl px-4 py-4">
            <Button
                :as="Link"
                href="/classroom/tracks"
                variant="ghost"
                class="mb-4"
            >
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Tracks
            </Button>
        </div>

        <!-- Track Header -->
        <section
            class="border-b bg-gradient-to-r from-blue-50 to-purple-50 py-8 dark:from-blue-950 dark:to-purple-950"
        >
            <div class="container mx-auto max-w-7xl px-4">
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
                                <span>by {{ track.instructor.name }}</span>
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

                                <!-- Track Stats -->
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
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- Track Content -->
        <section class="py-8">
            <div class="container mx-auto max-w-7xl px-4">
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
                                                {{ track.instructor.name }}
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
