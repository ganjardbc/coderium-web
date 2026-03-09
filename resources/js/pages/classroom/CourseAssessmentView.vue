<script setup lang="ts">
import BackButton from '@/components/BackButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    BookOpen,
    CheckCircle,
    Clock,
    Target,
    AlertCircle,
} from 'lucide-vue-next';

interface Course {
    id: number;
    title: string;
    slug: string;
}

interface Module {
    id: number;
    title: string;
}

interface Assessment {
    id: number;
    title: string;
    description: string;
    passing_score: number;
    max_attempts: number;
    is_required: boolean;
    time_limit?: number;
}

interface AssessmentResults {
    attempts: any[];
    best_score?: number;
    passed?: boolean;
    can_retake?: boolean;
}

interface Props {
    assessment: Assessment;
    results: AssessmentResults;
    breadcrumbs: BreadcrumbItem[];
    course: Course;
    module: Module;
}

defineProps<Props>();

const formatDuration = (minutes: number | undefined): string => {
    if (!minutes) return 'No time limit';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
};
</script>

<template>
    <Head :title="`${assessment.title} - ${course.title}`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <BackButton />

        <!-- Assessment Header -->
        <section class="w-full py-8 border-b bg-gradient-to-br from-orange-50/50 to-red-50/50 dark:from-orange-950/20 dark:to-red-950/20">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Assessment Info -->
                    <div class="lg:col-span-2">
                        <div class="mb-6 flex items-center gap-3">
                            <Badge variant="outline" class="text-sm bg-white/80 backdrop-blur-sm">
                                <Award class="mr-1 h-3 w-3" />
                                Assessment
                            </Badge>
                            <Badge
                                v-if="assessment.is_required"
                                variant="destructive"
                                class="text-sm"
                            >
                                Required
                            </Badge>
                            <Badge
                                v-else
                                variant="secondary"
                                class="text-sm"
                            >
                                Optional
                            </Badge>
                            <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                <Clock class="h-4 w-4" />
                                <span>{{ formatDuration(assessment.time_limit) }}</span>
                            </div>
                        </div>

                        <div class="mb-4 space-y-2">
                            <Link
                                :href="`/courses/${course.slug}`"
                                class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                            >
                                {{ course.title }}
                            </Link>
                            <div class="text-sm text-muted-foreground">
                                {{ module.title }}
                            </div>
                        </div>

                        <h1 class="mb-6 text-3xl font-bold leading-tight md:text-4xl">
                            {{ assessment.title }}
                        </h1>

                        <p class="mb-8 text-lg text-muted-foreground leading-relaxed">
                            {{ assessment.description }}
                        </p>

                        <!-- Navigation -->
                        <div class="flex flex-wrap gap-4">
                            <Button
                                :as="Link"
                                :href="`/courses/${course.slug}/modules/${module.id}`"
                                variant="outline"
                            >
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Back to Module
                            </Button>
                        </div>
                    </div>

                    <!-- Assessment Status Card -->
                    <div class="lg:col-span-1">
                        <Card class="sticky top-4 shadow-lg border-0 bg-white/80 backdrop-blur-sm dark:bg-gray-900/80">
                            <CardHeader class="pb-4">
                                <CardTitle class="flex items-center gap-2 text-lg">
                                    <Target class="h-5 w-5 text-orange-600" />
                                    Assessment Status
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <!-- Results Summary -->
                                <div v-if="results.attempts && results.attempts.length > 0">
                                    <div class="mb-4 flex items-center justify-between">
                                        <span class="text-sm text-muted-foreground">Best Score</span>
                                        <span class="text-lg font-bold">{{ results.best_score || 0 }}%</span>
                                    </div>

                                    <div v-if="results.passed" class="mb-4 flex items-center gap-2 text-green-600">
                                        <CheckCircle class="h-5 w-5" />
                                        <span class="font-medium">Assessment Passed!</span>
                                    </div>

                                    <div v-else class="mb-4 flex items-center gap-2 text-orange-600">
                                        <AlertCircle class="h-5 w-5" />
                                        <span class="font-medium">Not yet passed</span>
                                    </div>

                                    <Button
                                        v-if="results.can_retake"
                                        class="w-full"
                                        size="lg"
                                    >
                                        <Award class="mr-2 h-4 w-4" />
                                        Retake Assessment
                                    </Button>

                                    <Button
                                        v-else-if="!results.passed"
                                        disabled
                                        class="w-full"
                                        size="lg"
                                    >
                                        No more attempts
                                    </Button>

                                    <Button
                                        v-else
                                        variant="outline"
                                        class="w-full"
                                        size="lg"
                                    >
                                        View Results
                                    </Button>
                                </div>

                                <!-- First Attempt -->
                                <div v-else>
                                    <div class="mb-6 text-center">
                                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 mx-auto dark:bg-orange-900/30">
                                            <Award class="h-8 w-8 text-orange-600 dark:text-orange-400" />
                                        </div>
                                        <h3 class="font-semibold text-lg mb-2">Ready to Start?</h3>
                                        <p class="text-sm text-muted-foreground">Test your knowledge with this assessment</p>
                                    </div>

                                    <Button
                                        class="w-full h-12 text-lg font-semibold"
                                        size="lg"
                                    >
                                        <Award class="mr-2 h-5 w-5" />
                                        Start Assessment
                                    </Button>
                                </div>

                                <!-- Assessment Details -->
                                <div class="space-y-3 border-t pt-4">
                                    <h4 class="font-semibold text-sm uppercase tracking-wide text-muted-foreground">Assessment Details</h4>
                                    <div class="space-y-3 text-sm">
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Passing Score</span>
                                            <span class="font-semibold">{{ assessment.passing_score }}%</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Max Attempts</span>
                                            <span class="font-semibold">{{ assessment.max_attempts || 'Unlimited' }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Time Limit</span>
                                            <span class="font-semibold">{{ formatDuration(assessment.time_limit) }}</span>
                                        </div>
                                        <div v-if="results.attempts && results.attempts.length > 0" class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Attempts Used</span>
                                            <span class="font-semibold">{{ results.attempts.length }}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- Assessment Content -->
        <section class="py-12">
            <div class="w-full px-4">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2">
                        <!-- Assessment Instructions -->
                        <div class="mb-12">
                            <h2 class="mb-6 text-2xl font-bold">Instructions</h2>
                            <Card class="border-0 shadow-lg">
                                <CardContent class="p-8">
                                    <div class="prose prose-lg max-w-none dark:prose-invert">
                                        <p>This assessment will test your understanding of the concepts covered in this module.</p>
                                        <ul>
                                            <li>You need to score at least <strong>{{ assessment.passing_score }}%</strong> to pass</li>
                                            <li v-if="assessment.max_attempts">You have <strong>{{ assessment.max_attempts }}</strong> attempts to complete this assessment</li>
                                            <li v-if="assessment.time_limit">Time limit: <strong>{{ formatDuration(assessment.time_limit) }}</strong></li>
                                            <li>Make sure you have a stable internet connection</li>
                                            <li>Read each question carefully before answering</li>
                                        </ul>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Previous Attempts -->
                        <div v-if="results.attempts && results.attempts.length > 0" class="mb-12">
                            <h2 class="mb-6 text-2xl font-bold">Previous Attempts</h2>
                            <div class="space-y-4">
                                <Card
                                    v-for="(attempt, index) in results.attempts"
                                    :key="attempt.id"
                                    class="border-0 shadow-lg"
                                >
                                    <CardContent class="p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h3 class="font-semibold">Attempt {{ index + 1 }}</h3>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ new Date(attempt.created_at).toLocaleDateString() }}
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-2xl font-bold" :class="attempt.score >= assessment.passing_score ? 'text-green-600' : 'text-red-600'">
                                                    {{ attempt.score }}%
                                                </div>
                                                <Badge
                                                    v-if="attempt.score >= assessment.passing_score"
                                                    variant="default"
                                                    class="bg-green-600"
                                                >
                                                    Passed
                                                </Badge>
                                                <Badge
                                                    v-else
                                                    variant="destructive"
                                                >
                                                    Failed
                                                </Badge>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-6">
                            <!-- Assessment Tips -->
                            <Card class="border-0 shadow-lg">
                                <CardHeader>
                                    <CardTitle class="text-lg">Assessment Tips</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul class="space-y-2 text-sm">
                                        <li class="flex items-start gap-2">
                                            <CheckCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                                            <span>Review the module content before starting</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                                            <span>Read each question carefully</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                                            <span>Manage your time effectively</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                                            <span>Don't rush through the questions</span>
                                        </li>
                                    </ul>
                                </CardContent>
                            </Card>

                            <!-- Quick Navigation -->
                            <Card class="border-0 shadow-lg">
                                <CardHeader>
                                    <CardTitle class="text-lg">Quick Navigation</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Button
                                        :as="Link"
                                        :href="`/courses/${course.slug}/modules/${module.id}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <BookOpen class="mr-2 h-4 w-4" />
                                        Back to Module
                                    </Button>

                                    <Button
                                        :as="Link"
                                        :href="`/courses/${course.slug}`"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        Course Overview
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
