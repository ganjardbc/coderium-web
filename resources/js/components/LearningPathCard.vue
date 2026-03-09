<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Clock,
    Star,
    Users,
    GraduationCap,
    CheckCircle,
    TrendingUp,
    Play,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { LearningPath } from '@/types/enhanced-classroom';

interface Props {
    learningPath: LearningPath;
    type: 'track' | 'course';
    showProgress?: boolean;
    showModuleCount?: boolean;
    variant?: 'default' | 'compact' | 'detailed';
}

const props = withDefaults(defineProps<Props>(), {
    showProgress: true,
    showModuleCount: true,
    variant: 'default',
});

const emit = defineEmits<{
    selected: [type: 'track' | 'course', id: string];
    enrolled: [type: 'track' | 'course', id: string];
    progressRequested: [type: 'track' | 'course', id: string];
}>();

const formatDuration = (minutes: number) => {
    if (!minutes) return '0m';
    if (minutes >= 60) {
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    }
    return `${minutes}m`;
};

const formatNumber = (num: number) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
};

const getDifficultyColor = (level: string) => {
    switch (level) {
        case 'beginner': return 'bg-green-500/90 text-white';
        case 'intermediate': return 'bg-yellow-500/90 text-white';
        case 'advanced': return 'bg-red-500/90 text-white';
        default: return 'bg-gray-500/90 text-white';
    }
};

const getTypeColor = (type: string) => {
    return type === 'track' ? 'bg-green-500/90 text-white' : 'bg-blue-500/90 text-white';
};

const getTypeIcon = (type: string) => {
    return type === 'track' ? GraduationCap : BookOpen;
};

const getCoverImage = (path: LearningPath) => {
    if (path.thumbnail) return path.thumbnail;

    // Default placeholders based on difficulty and type
    const placeholders = {
        track: {
            beginner: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=400&h=225&fit=crop&crop=center',
            intermediate: 'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?w=400&h=225&fit=crop&crop=center',
            advanced: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=400&h=225&fit=crop&crop=center'
        },
        course: {
            beginner: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=400&h=225&fit=crop&crop=center',
            intermediate: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=400&h=225&fit=crop&crop=center',
            advanced: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=225&fit=crop&crop=center'
        }
    };

    return placeholders[path.type][path.difficulty] || placeholders[path.type].beginner;
};

const progressPercentage = computed(() => {
    return props.learningPath.progress || 0;
});

const isEnrolled = computed(() => {
    return props.learningPath.enrollmentStatus === 'enrolled' ||
           props.learningPath.enrollmentStatus === 'completed';
});

const isCompleted = computed(() => {
    return props.learningPath.enrollmentStatus === 'completed';
});

const handleCardClick = () => {
    emit('selected', props.type, props.learningPath.id);
};

const handleEnrollClick = (event: Event) => {
    event.stopPropagation();
    emit('enrolled', props.type, props.learningPath.id);
};

const handleProgressClick = (event: Event) => {
    event.stopPropagation();
    emit('progressRequested', props.type, props.learningPath.id);
};
</script>

<template>
    <div
        @click="handleCardClick"
        :class="[
            'group cursor-pointer overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-xl',
            {
                'max-w-sm': variant === 'compact',
                'max-w-md': variant === 'default',
                'max-w-lg': variant === 'detailed'
            }
        ]"
    >
        <div class="relative aspect-video w-full overflow-hidden bg-muted">
            <img
                :src="getCoverImage(learningPath)"
                :alt="learningPath.title"
                class="h-full w-full object-cover transition-transform group-hover:scale-105"
            />

            <!-- Type Badge -->
            <div class="absolute top-3 left-3">
                <span
                    :class="[
                        'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur-sm',
                        getTypeColor(learningPath.type)
                    ]"
                >
                    <component :is="getTypeIcon(learningPath.type)" class="h-3 w-3" />
                    {{ learningPath.type.charAt(0).toUpperCase() + learningPath.type.slice(1) }}
                </span>
            </div>

            <!-- Difficulty Badge -->
            <div class="absolute top-3 right-3">
                <span
                    :class="[
                        'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur-sm',
                        getDifficultyColor(learningPath.difficulty)
                    ]"
                >
                    <Star class="h-3 w-3" />
                    {{ learningPath.difficulty.charAt(0).toUpperCase() + learningPath.difficulty.slice(1) }}
                </span>
            </div>

            <!-- Rating Badge -->
            <div v-if="learningPath.rating > 0" class="absolute bottom-3 left-3">
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-xs font-medium text-gray-800 backdrop-blur-sm"
                >
                    <Star class="h-3 w-3 fill-yellow-400 text-yellow-400" />
                    {{ learningPath.rating.toFixed(1) }}
                </span>
            </div>

            <!-- Completion Badge -->
            <div v-if="isCompleted" class="absolute bottom-3 right-3">
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-green-500/90 px-2 py-1 text-xs font-medium text-white backdrop-blur-sm"
                >
                    <CheckCircle class="h-3 w-3" />
                    Completed
                </span>
            </div>
        </div>

        <div class="flex flex-col justify-between p-5">
            <div class="flex min-h-[124px] flex-col">
                <h3
                    class="text-md mb-2 truncate font-semibold transition-colors group-hover:text-primary"
                >
                    {{ learningPath.title }}
                </h3>
                <p
                    v-if="learningPath.description"
                    class="mb-4 line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ learningPath.description }}
                </p>

                <!-- Tags -->
                <div v-if="learningPath.tags?.length" class="mb-3 flex flex-wrap gap-1">
                    <span
                        v-for="tag in learningPath.tags.slice(0, 3)"
                        :key="tag"
                        class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ tag }}
                    </span>
                    <span
                        v-if="learningPath.tags.length > 3"
                        class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                    >
                        +{{ learningPath.tags.length - 3 }}
                    </span>
                </div>

                <!-- Instructor -->
                <div v-if="learningPath.instructor" class="mb-3 text-xs text-muted-foreground">
                    by {{ learningPath.instructor.name }}
                </div>
            </div>

            <!-- Enhanced Metadata Section -->
            <div class="space-y-3">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <div class="flex items-center gap-3">
                        <div v-if="showModuleCount" class="flex items-center gap-1">
                            <BookOpen class="h-3.5 w-3.5" />
                            <span>{{ learningPath.moduleCount }} {{ learningPath.type === 'track' ? 'levels' : 'modules' }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Users class="h-3.5 w-3.5" />
                            <span>{{ formatNumber(learningPath.enrollmentCount) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Clock class="h-3.5 w-3.5" />
                        <span>{{ formatDuration(learningPath.estimatedDuration) }}</span>
                    </div>
                </div>

                <!-- Completion Rate -->
                <div v-if="learningPath.completionRate > 0" class="flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">Completion Rate</span>
                    <div class="flex items-center gap-1">
                        <TrendingUp class="h-3 w-3 text-green-600" />
                        <span class="font-medium text-green-600">{{ Math.round(learningPath.completionRate) }}%</span>
                    </div>
                </div>
            </div>

            <!-- Progress Section -->
            <div v-if="showProgress && isEnrolled" class="mt-4 pt-4 border-t">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Your Progress</span>
                    <button
                        @click="handleProgressClick"
                        class="font-medium hover:text-primary transition-colors"
                    >
                        {{ Math.round(progressPercentage) }}%
                    </button>
                </div>
                <div class="h-2 w-full rounded-full bg-muted">
                    <div
                        class="h-2 rounded-full bg-primary transition-all duration-300"
                        :style="{ width: `${progressPercentage}%` }"
                    ></div>
                </div>
                <div v-if="isCompleted" class="mt-2 flex items-center gap-1 text-xs text-green-600 font-medium">
                    <CheckCircle class="h-3 w-3" />
                    <span>Completed</span>
                </div>
            </div>

            <!-- Action Button -->
            <div v-if="!isEnrolled" class="mt-4 pt-4 border-t">
                <button
                    @click="handleEnrollClick"
                    class="w-full flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                >
                    <Play class="h-4 w-4" />
                    {{ learningPath.type === 'track' ? 'Enroll in Track' : 'Enroll in Course' }}
                </button>
            </div>
        </div>
    </div>
</template>
