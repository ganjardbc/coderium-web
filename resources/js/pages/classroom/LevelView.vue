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
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { BreadcrumbItem, Level, Module } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle,
    ChevronRight,
    Clock,
    Play,
    Target,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    level: Level;
    breadcrumbs: BreadcrumbItem[];
    nextLevel?: Level;
    previousLevel?: Level;
}

const props = defineProps<Props>();

const sortedModules = computed(() => {
    return (
        props.level.modules
            ?.slice()
            .sort((a, b) => a.order_index - b.order_index) || []
    );
});

const levelProgress = computed(() => {
    if (!props.level.modules) return 0;

    const totalLessons = props.level.modules.reduce(
        (sum, module) => sum + (module.lessons_count || 0),
        0,
    );
    if (totalLessons === 0) return 0;

    const completedLessons = props.level.modules.reduce((sum, module) => {
        return sum + (module.progress?.completed_lessons || 0);
    }, 0);

    return (completedLessons / totalLessons) * 100;
});

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

const getModuleProgress = (module: Module): number => {
    if (!module.lessons_count || module.lessons_count === 0) return 0;
    const completed = module.progress?.completed_lessons || 0;
    return (completed / module.lessons_count) * 100;
};

const isModuleCompleted = (module: Module): boolean => {
    return getModuleProgress(module) === 100;
};

const getNextModule = (): Module | null => {
    const incompleteModule = sortedModules.value.find(
        (module) => !isModuleCompleted(module),
    );
    return incompleteModule || null;
};

const startNextModule = () => {
    const nextModule = getNextModule();
    if (nextModule) {
        router.visit(`/classroom/modules/${nextModule.id}`);
    }
};
</script>

<template>
    <Head :title="`${level.title} - Classroom`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Level Header -->
        <section class="w-full py-8 border-b">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Level Info -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 flex items-center gap-3">
                            <Badge
                                :class="getDifficultyColor(level.difficulty)"
                                class="text-sm font-medium"
                            >
                                {{
                                    level.difficulty.charAt(0).toUpperCase() +
                                    level.difficulty.slice(1)
                                }}
                            </Badge>
                            <Badge variant="outline" class="text-sm">
                                {{ level.modules?.length || 0 }} modules
                            </Badge>
                        </div>

                        <h1 class="mb-4 text-3xl font-bold md:text-4xl">
                            {{ level.title }}
                        </h1>

                        <p class="mb-6 text-lg text-muted-foreground">
                            {{ level.description }}
                        </p>

                        <!-- Navigation -->
                        <div class="flex flex-wrap gap-4">
                            <Button
                                v-if="previousLevel"
                                :as="Link"
                                :href="`/classroom/levels/${previousLevel.id}`"
                                variant="outline"
                            >
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Previous Level
                            </Button>

                            <Button
                                v-if="nextLevel"
                                :as="Link"
                                :href="`/classroom/levels/${nextLevel.id}`"
                                variant="outline"
                            >
                                Next Level
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Progress Card -->
                    <div class="lg:col-span-1">
                        <Card class="sticky top-4">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Target class="h-5 w-5" />
                                    Level Progress
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div
                                    class="mb-2 flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Overall Progress</span
                                    >
                                    <span class="font-medium"
                                        >{{ Math.round(levelProgress) }}%</span
                                    >
                                </div>
                                <Progress :value="levelProgress" class="mb-4" />

                                <Button
                                    @click="startNextModule"
                                    :disabled="!getNextModule()"
                                    class="w-full"
                                    size="lg"
                                >
                                    <Play class="mr-2 h-4 w-4" />
                                    {{
                                        getNextModule()
                                            ? 'Continue Learning'
                                            : 'Level Complete'
                                    }}
                                </Button>

                                <div
                                    class="text-center text-xs text-muted-foreground"
                                >
                                    {{
                                        sortedModules.filter(isModuleCompleted)
                                            .length
                                    }}
                                    of {{ sortedModules.length }} modules
                                    completed
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modules Content -->
        <section class="py-8">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2">
                        <h2 class="mb-6 text-2xl font-bold">Modules</h2>

                        <div v-if="sortedModules.length > 0" class="space-y-4">
                            <Card
                                v-for="(module, index) in sortedModules"
                                :key="module.id"
                                class="group cursor-pointer transition-all duration-200 hover:shadow-md"
                                @click="
                                    router.visit(
                                        `/classroom/modules/${module.id}`,
                                    )
                                "
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
                                                    Module {{ index + 1 }}
                                                </Badge>
                                                <div
                                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    <span>{{
                                                        formatDuration(
                                                            module.estimated_duration,
                                                        )
                                                    }}</span>
                                                </div>
                                                <div
                                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <BookOpen class="h-3 w-3" />
                                                    <span
                                                        >{{
                                                            module.lessons_count ||
                                                            0
                                                        }}
                                                        lessons</span
                                                    >
                                                </div>
                                            </div>
                                            <CardTitle
                                                class="flex items-center gap-2 text-lg transition-colors group-hover:text-primary"
                                            >
                                                {{ module.title }}
                                                <CheckCircle
                                                    v-if="
                                                        isModuleCompleted(
                                                            module,
                                                        )
                                                    "
                                                    class="h-5 w-5 text-green-500"
                                                />
                                            </CardTitle>
                                            <CardDescription class="mt-1">
                                                {{ module.description }}
                                            </CardDescription>
                                        </div>

                                        <ChevronRight
                                            class="ml-4 h-5 w-5 text-muted-foreground transition-colors group-hover:text-primary"
                                        />
                                    </div>

                                    <!-- Module Progress -->
                                    <div class="mt-4">
                                        <div
                                            class="mb-1 flex items-center justify-between text-sm"
                                        >
                                            <span class="text-muted-foreground"
                                                >Progress</span
                                            >
                                            <span class="font-medium"
                                                >{{
                                                    Math.round(
                                                        getModuleProgress(
                                                            module,
                                                        ),
                                                    )
                                                }}%</span
                                            >
                                        </div>
                                        <Progress
                                            :value="getModuleProgress(module)"
                                            class="h-1.5"
                                        />
                                    </div>
                                </CardHeader>
                            </Card>
                        </div>

                        <div v-else class="py-12 text-center">
                            <BookOpen
                                class="mx-auto mb-4 h-16 w-16 text-muted-foreground"
                            />
                            <h3 class="mb-2 text-lg font-semibold">
                                No modules available
                            </h3>
                            <p class="text-muted-foreground">
                                This level doesn't have any modules yet.
                            </p>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-6">
                            <!-- Level Stats -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Level Overview</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Difficulty</span
                                        >
                                        <span class="font-medium">{{
                                            level.difficulty
                                                .charAt(0)
                                                .toUpperCase() +
                                            level.difficulty.slice(1)
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Modules</span
                                        >
                                        <span class="font-medium">{{
                                            sortedModules.length
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Total Lessons</span
                                        >
                                        <span class="font-medium">
                                            {{
                                                sortedModules.reduce(
                                                    (sum, m) =>
                                                        sum +
                                                        (m.lessons_count || 0),
                                                    0,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Completed</span
                                        >
                                        <span class="font-medium">
                                            {{
                                                sortedModules.filter(
                                                    isModuleCompleted,
                                                ).length
                                            }}/{{ sortedModules.length }}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Quick Navigation -->
                            <Card v-if="previousLevel || nextLevel">
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Quick Navigation</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Button
                                        v-if="previousLevel"
                                        :as="Link"
                                        :href="`/classroom/levels/${previousLevel.id}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        {{ previousLevel.title }}
                                    </Button>

                                    <Button
                                        v-if="nextLevel"
                                        :as="Link"
                                        :href="`/classroom/levels/${nextLevel.id}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <ArrowRight class="mr-2 h-4 w-4" />
                                        {{ nextLevel.title }}
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
