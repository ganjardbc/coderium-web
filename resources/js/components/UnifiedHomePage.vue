<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link, router } from '@inertiajs/vue3';
import emblaCarouselVue from 'embla-carousel-vue';
import { ChevronLeft, ChevronRight, GraduationCap, BookOpen, Search, Filter, Grid, List } from 'lucide-vue-next';
import { ref, watchEffect, computed } from 'vue';

import Pagination from '@/components/Pagination.vue';
import TrackCard from '@/components/TrackCard.vue';
import Searchbar from '@/components/Searchbar.vue';
import type { LearningPath } from '@/types/enhanced-classroom';

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
    // Enhanced fields
    moduleAssignments?: any[];
    totalModuleCount?: number;
    completionRate?: number;
    rating?: number;
    tags?: string[];
}

interface Course {
    id: string;
    title: string;
    description: string;
    thumbnail?: string;
    category: string;
    difficulty: 'beginner' | 'intermediate' | 'advanced';
    estimatedDuration: number;
    moduleCount: number;
    enrollmentCount: number;
    rating: number;
    tags: string[];
    instructor?: {
        id: number;
        name: string;
    };
    enrollment?: {
        id: string;
        progress: number;
        completedAt?: string;
    };
}

const [emblaRef, emblaApi] = emblaCarouselVue({
    align: 'start',
    containScroll: 'trimSnaps',
    dragFree: true,
});

const canScrollLeft = ref(false);
const canScrollRight = ref(true);

// Update scroll buttons based on embla state
watchEffect(() => {
    if (emblaApi.value) {
        const updateScrollButtons = () => {
            canScrollLeft.value = emblaApi.value?.canScrollPrev() || false;
            canScrollRight.value = emblaApi.value?.canScrollNext() || false;
        };

        emblaApi.value.on('select', updateScrollButtons);
        emblaApi.value.on('reInit', updateScrollButtons);

        // Initialize button states
        updateScrollButtons();
    }
});

const scrollLearningPaths = (direction: 'left' | 'right') => {
    if (!emblaApi.value) return;

    if (direction === 'left') {
        emblaApi.value.scrollPrev();
    } else {
        emblaApi.value.scrollNext();
    }
};

interface Props {
    tracks?: Track[];
    courses?: Course[];
    title?: string;
    showTracks?: boolean;
    showCourses?: boolean;
    defaultView?: 'grid' | 'list';
    enableUnifiedSearch?: boolean;
    filters?: {
        search?: string;
        sort?: string;
        type?: string;
        category?: string;
        difficulty?: string;
    };
    pagination?: {
        current_page: number;
        last_page: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
}

const props = withDefaults(defineProps<Props>(), {
    tracks: () => [],
    courses: () => [],
    title: 'Learning Paths',
    showTracks: true,
    showCourses: true,
    defaultView: 'grid',
    enableUnifiedSearch: true,
});

// Unified search and filtering state
const searchQuery = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || 'all');
const selectedCategory = ref(props.filters?.category || 'all');
const selectedDifficulty = ref(props.filters?.difficulty || 'all');
const viewMode = ref<'grid' | 'list'>(props.defaultView);
const showFilters = ref(false);

// Convert tracks and courses to unified learning paths
const unifiedLearningPaths = computed(() => {
    const paths: LearningPath[] = [];

    if (props.showTracks && props.tracks) {
        props.tracks.forEach(track => {
            paths.push({
                id: track.id.toString(),
                type: 'track',
                title: track.title,
                description: track.description,
                thumbnail: track.media?.[0]?.url,
                category: 'General', // Default category for tracks
                difficulty: track.difficulty_level,
                estimatedDuration: track.estimated_duration || 0,
                moduleCount: track.totalModuleCount || track.levels_count || 0,
                progress: track.enrollment?.progress_percentage,
                enrollmentStatus: track.enrollment ?
                    (track.enrollment.completed_at ? 'completed' : 'enrolled') : 'available',
                lastAccessedAt: track.enrollment ? new Date(track.enrollment.enrolled_at) : undefined,
                rating: track.rating || 0,
                enrollmentCount: track.enrollments_count || 0,
                completionRate: track.completionRate || 0,
                tags: track.tags || [],
                instructor: track.instructor,
            });
        });
    }

    if (props.showCourses && props.courses) {
        props.courses.forEach(course => {
            paths.push({
                id: course.id,
                type: 'course',
                title: course.title,
                description: course.description,
                thumbnail: course.thumbnail,
                category: course.category,
                difficulty: course.difficulty,
                estimatedDuration: course.estimatedDuration,
                moduleCount: course.moduleCount,
                progress: course.enrollment?.progress,
                enrollmentStatus: course.enrollment ?
                    (course.enrollment.completedAt ? 'completed' : 'enrolled') : 'available',
                lastAccessedAt: course.enrollment ? new Date() : undefined,
                rating: course.rating,
                enrollmentCount: course.enrollmentCount,
                completionRate: 0, // Calculate from course data
                tags: course.tags,
                instructor: course.instructor,
            });
        });
    }

    return paths;
});

// Filtered learning paths based on search and filters
const filteredLearningPaths = computed(() => {
    let filtered = unifiedLearningPaths.value;

    // Search filter
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(path =>
            path.title.toLowerCase().includes(query) ||
            path.description.toLowerCase().includes(query) ||
            path.tags.some(tag => tag.toLowerCase().includes(query))
        );
    }

    // Type filter
    if (selectedType.value !== 'all') {
        filtered = filtered.filter(path => path.type === selectedType.value);
    }

    // Category filter
    if (selectedCategory.value !== 'all') {
        filtered = filtered.filter(path => path.category === selectedCategory.value);
    }

    // Difficulty filter
    if (selectedDifficulty.value !== 'all') {
        filtered = filtered.filter(path => path.difficulty === selectedDifficulty.value);
    }

    return filtered;
});

// Available filter options
const categories = computed(() => {
    const cats = new Set(unifiedLearningPaths.value.map(path => path.category));
    return Array.from(cats).sort();
});

const difficulties = ['beginner', 'intermediate', 'advanced'];

const handleUnifiedSearch = (query: string) => {
    searchQuery.value = query;
    performSearch();
};

const handleClearSearch = () => {
    searchQuery.value = '';
    selectedType.value = 'all';
    selectedCategory.value = 'all';
    selectedDifficulty.value = 'all';
    performSearch();
};

const performSearch = () => {
    const params: Record<string, any> = {};

    if (searchQuery.value.trim()) params.q = searchQuery.value;
    if (selectedType.value !== 'all') params.type = selectedType.value;
    if (selectedCategory.value !== 'all') params.category = selectedCategory.value;
    if (selectedDifficulty.value !== 'all') params.difficulty = selectedDifficulty.value;

    router.get('/classroom', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const navigateToLearningPath = (path: LearningPath) => {
    if (path.type === 'track') {
        router.visit(`/classroom/${path.id}`);
    } else {
        router.visit(`/courses/${path.id}`);
    }
};

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
</script>

<template>
    <section id="unified-learning-paths" class="border-t py-8">
        <div class="container mx-auto px-4">
            <!-- Header with unified search and controls -->
            <div class="mb-6 space-y-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-bold">{{ title }}</h2>

                    <!-- View Controls -->
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showFilters = !showFilters"
                            class="flex items-center gap-2"
                        >
                            <Filter class="h-4 w-4" />
                            Filters
                        </Button>

                        <div class="flex rounded-lg border">
                            <Button
                                variant="ghost"
                                size="sm"
                                :class="{ 'bg-muted': viewMode === 'grid' }"
                                @click="viewMode = 'grid'"
                            >
                                <Grid class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                :class="{ 'bg-muted': viewMode === 'list' }"
                                @click="viewMode = 'list'"
                            >
                                <List class="h-4 w-4" />
                            </Button>
                        </div>

                        <!-- Scroll Navigation Buttons (for carousel view) -->
                        <div v-if="viewMode === 'grid'" class="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-[36px] rounded-full px-0"
                                :disabled="!canScrollLeft"
                                @click="scrollLearningPaths('left')"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-[36px] rounded-full px-0"
                                :disabled="!canScrollRight"
                                @click="scrollLearningPaths('right')"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Unified Search -->
                <div v-if="enableUnifiedSearch">
                    <Searchbar
                        v-model="searchQuery"
                        placeholder="Search tracks and courses..."
                        @search="handleUnifiedSearch"
                        @clear="handleClearSearch"
                    />
                </div>

                <!-- Advanced Filters -->
                <div v-if="showFilters" class="grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium">Type</label>
                        <select
                            v-model="selectedType"
                            @change="performSearch"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="all">All Types</option>
                            <option value="track">Tracks</option>
                            <option value="course">Courses</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">Category</label>
                        <select
                            v-model="selectedCategory"
                            @change="performSearch"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="all">All Categories</option>
                            <option v-for="category in categories" :key="category" :value="category">
                                {{ category }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">Difficulty</label>
                        <select
                            v-model="selectedDifficulty"
                            @change="performSearch"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="all">All Levels</option>
                            <option v-for="difficulty in difficulties" :key="difficulty" :value="difficulty">
                                {{ difficulty.charAt(0).toUpperCase() + difficulty.slice(1) }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Learning Paths Display -->
            <div v-if="filteredLearningPaths.length > 0">
                <!-- Grid View (Carousel) -->
                <div v-if="viewMode === 'grid'" class="relative">
                    <div ref="emblaRef" class="overflow-hidden">
                        <div class="flex touch-pan-y gap-4">
                            <!-- Unified Learning Path Cards -->
                            <div
                                v-for="path in filteredLearningPaths"
                                :key="`${path.type}-${path.id}`"
                                class="!max-w-[310px] !min-w-[310px]"
                            >
                                <!-- Track Card (Enhanced) -->
                                <TrackCard
                                    v-if="path.type === 'track'"
                                    :track="tracks?.find(t => t.id.toString() === path.id)"
                                    :show-module-assignments="true"
                                    :unified-styling="true"
                                    variant="default"
                                />

                                <!-- Course Card (Unified Style) -->
                                <div
                                    v-else
                                    @click="navigateToLearningPath(path)"
                                    class="group cursor-pointer overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-xl !max-w-[310px] !min-w-[310px]"
                                >
                                    <div class="relative aspect-video w-full overflow-hidden bg-muted">
                                        <img
                                            :src="path.thumbnail || 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=400&h=225&fit=crop&crop=center'"
                                            :alt="path.title"
                                            class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                        />

                                        <!-- Type Badge -->
                                        <div class="absolute top-3 left-3">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/90 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                                                <BookOpen class="h-3 w-3" />
                                                Course
                                            </span>
                                        </div>

                                        <!-- Difficulty Badge -->
                                        <div class="absolute top-3 right-3">
                                            <span :class="['inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur-sm', getDifficultyColor(path.difficulty)]">
                                                {{ path.difficulty.charAt(0).toUpperCase() + path.difficulty.slice(1) }}
                                            </span>
                                        </div>

                                        <!-- Rating -->
                                        <div v-if="path.rating > 0" class="absolute bottom-3 right-3">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-xs font-medium text-gray-800 backdrop-blur-sm">
                                                ⭐ {{ path.rating.toFixed(1) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col justify-between p-5">
                                        <div class="flex min-h-[124px] flex-col">
                                            <h3 class="text-md mb-2 truncate font-semibold transition-colors group-hover:text-primary">
                                                {{ path.title }}
                                            </h3>
                                            <p class="mb-4 line-clamp-2 text-sm text-muted-foreground">
                                                {{ path.description }}
                                            </p>

                                            <!-- Tags -->
                                            <div v-if="path.tags.length" class="mb-3 flex flex-wrap gap-1">
                                                <span
                                                    v-for="tag in path.tags.slice(0, 3)"
                                                    :key="tag"
                                                    class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                                >
                                                    {{ tag }}
                                                </span>
                                            </div>

                                            <!-- Instructor -->
                                            <div v-if="path.instructor" class="mb-3 text-xs text-muted-foreground">
                                                by {{ path.instructor.name }}
                                            </div>
                                        </div>

                                        <!-- Metadata -->
                                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                                            <div class="flex items-center gap-3">
                                                <div class="flex items-center gap-1">
                                                    <BookOpen class="h-3.5 w-3.5" />
                                                    <span>{{ path.moduleCount }} modules</span>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <GraduationCap class="h-3.5 w-3.5" />
                                                    <span>{{ formatNumber(path.enrollmentCount) }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <Clock class="h-3.5 w-3.5" />
                                                <span>{{ formatDuration(path.estimatedDuration) }}</span>
                                            </div>
                                        </div>

                                        <!-- Progress -->
                                        <div v-if="path.progress !== undefined" class="mt-4 pt-4 border-t">
                                            <div class="mb-2 flex items-center justify-between text-sm">
                                                <span class="text-muted-foreground">Progress</span>
                                                <span class="font-medium">{{ Math.round(path.progress) }}%</span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-muted">
                                                <div
                                                    class="h-2 rounded-full bg-primary transition-all duration-300"
                                                    :style="{ width: `${path.progress}%` }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Explore More Button -->
                            <div class="min-w-0 flex-[0_0_auto]">
                                <Button
                                    :as="Link"
                                    href="/classroom"
                                    size="lg"
                                    variant="outline"
                                    class="flex h-full !max-w-[310px] !min-w-[310px] flex-col bg-gray-50 !py-6 dark:bg-gray-800"
                                >
                                    <span class="mb-2 text-lg font-medium">Explore More</span>
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary">
                                        <ChevronRight class="h-5 w-5 text-white dark:text-black" />
                                    </div>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="path in filteredLearningPaths"
                        :key="`${path.type}-${path.id}`"
                        @click="navigateToLearningPath(path)"
                        class="group cursor-pointer overflow-hidden rounded-lg border bg-card p-4 transition-all hover:border-gray-200 hover:shadow-md"
                    >
                        <div class="flex items-start gap-4">
                            <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg bg-muted">
                                <img
                                    :src="path.thumbnail || 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=64&h=64&fit=crop&crop=center'"
                                    :alt="path.title"
                                    class="h-full w-full object-cover"
                                />
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="mb-1 flex items-center gap-2">
                                    <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium', path.type === 'track' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800']">
                                        <component :is="path.type === 'track' ? GraduationCap : BookOpen" class="h-3 w-3" />
                                        {{ path.type.charAt(0).toUpperCase() + path.type.slice(1) }}
                                    </span>
                                    <span :class="['text-xs px-2 py-1 rounded-full', getDifficultyColor(path.difficulty)]">
                                        {{ path.difficulty }}
                                    </span>
                                </div>

                                <h3 class="font-semibold truncate group-hover:text-primary transition-colors">
                                    {{ path.title }}
                                </h3>

                                <p class="text-sm text-muted-foreground line-clamp-2 mt-1">
                                    {{ path.description }}
                                </p>

                                <div class="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
                                    <span>{{ path.moduleCount }} modules</span>
                                    <span>{{ formatDuration(path.estimatedDuration) }}</span>
                                    <span v-if="path.rating > 0">⭐ {{ path.rating.toFixed(1) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="py-12 text-center text-muted-foreground">
                <Search class="mx-auto mb-4 h-12 w-12" />
                <p class="text-lg font-medium mb-2">No learning paths found</p>
                <p>Try adjusting your search or filters</p>
                <Button
                    variant="outline"
                    class="mt-4"
                    @click="handleClearSearch"
                >
                    Clear Filters
                </Button>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination && pagination.last_page > 1"
                class="mt-8"
            >
                <Pagination
                    :current-page="pagination.current_page"
                    :last-page="pagination.last_page"
                    :links="pagination.links"
                />
            </div>
        </div>
    </section>
</template>
