<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Clock,
    DollarSign,
    Star,
    Users,
} from 'lucide-vue-next';

interface Track {
    id: number;
    slug: string;
    title: string;
    description: string;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration: number;
    is_premium: boolean;
    price?: number;
    is_free: boolean;
    levels_count: number;
    enrollments_count: number;
    instructor?: {
        id: number;
        name: string;
        email?: string;
    } | null;
    media?: Array<{
        id: number;
        url: string;
        type: string;
    }>;
    enrollment?: {
        id: number;
        enrolled_at: string;
        progress_percentage: number;
        completed_at?: string;
    } | null;
}

interface Props {
    track: Track;
}

defineProps<Props>();

const formatDuration = (minutes: number) => {
    if (!minutes) {
        return '0m';
    }
    if (minutes >= 60) {
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    }
    return `${minutes}m`;
};

const formatNumber = (num: number) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
};

const getDifficultyColor = (level: string) => {
    switch (level) {
        case 'beginner':
            return 'bg-green-500/90 text-white';
        case 'intermediate':
            return 'bg-yellow-500/90 text-white';
        case 'advanced':
            return 'bg-red-500/90 text-white';
        default:
            return 'bg-gray-500/90 text-white';
    }
};

const getCoverImage = (track: Track) => {
    if (track.media && track.media.length > 0) {
        return track.media[0].url;
    }
    // Return a default placeholder based on difficulty level
    const placeholders = {
        beginner: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=400&h=225&fit=crop&crop=center',
        intermediate: 'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?w=400&h=225&fit=crop&crop=center',
        advanced: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=400&h=225&fit=crop&crop=center'
    };
    return placeholders[track.difficulty_level] || placeholders.beginner;
};
</script>

<template>
    <Link
        :href="`/classroom/${track.slug}`"
        class="group overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-xl"
    >
        <div class="relative aspect-video w-full overflow-hidden bg-muted">
            <img
                :src="getCoverImage(track)"
                :alt="track.title"
                class="h-full w-full object-cover transition-transform group-hover:scale-105"
            />

            <!-- Difficulty Badge -->
            <div class="absolute top-3 left-3">
                <span
                    :class="[
                        'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur-sm',
                        getDifficultyColor(track.difficulty_level)
                    ]"
                >
                    <Star class="h-3 w-3" />
                    {{ track.difficulty_level.charAt(0).toUpperCase() + track.difficulty_level.slice(1) }}
                </span>
            </div>

            <!-- Premium Badge -->
            <div v-if="track.is_premium && !track.is_free" class="absolute top-3 right-3">
                <span
                    class="inline-flex items-center gap-1 rounded-full bg-purple-500/90 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm"
                >
                    <DollarSign class="h-3 w-3" />
                    Premium
                </span>
            </div>
        </div>

        <div class="flex flex-col justify-between p-5">
            <div class="flex min-h-[124px] flex-col">
                <h3
                    class="text-md mb-2 truncate font-semibold transition-colors group-hover:text-primary"
                >
                    {{ track.title }}
                </h3>
                <p
                    v-if="track.description"
                    class="mb-4 line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ track.description }}
                </p>

                <!-- Instructor -->
                <div v-if="track.instructor" class="mb-3 text-xs text-muted-foreground">
                    by {{ track.instructor.name }}
                </div>
                <div v-else class="mb-3 text-xs text-muted-foreground">
                    No instructor assigned
                </div>
            </div>

            <div
                class="flex items-center justify-between text-xs text-muted-foreground"
            >
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1">
                        <BookOpen class="h-3.5 w-3.5" />
                        <span>{{ track.levels_count }} levels</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Users class="h-3.5 w-3.5" />
                        <span>{{ formatNumber(track.enrollments_count) }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <Clock class="h-3.5 w-3.5" />
                    <span>{{ formatDuration(track.estimated_duration) }}</span>
                </div>
            </div>

            <!-- Progress Section -->
            <div v-if="track.enrollment" class="mt-4 pt-4 border-t">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Progress</span>
                    <span class="font-medium">
                        {{ Math.round(track.enrollment.progress_percentage) }}%
                    </span>
                </div>
                <div class="h-2 w-full rounded-full bg-muted">
                    <div
                        class="h-2 rounded-full bg-primary transition-all duration-300"
                        :style="{
                            width: `${track.enrollment.progress_percentage}%`,
                        }"
                    ></div>
                </div>
                <div v-if="track.enrollment.completed_at" class="mt-2 text-xs text-green-600 font-medium">
                    ✓ Completed
                </div>
            </div>
        </div>
    </Link>
</template>
