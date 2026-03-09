<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Target,
    Clock,
    CheckCircle,
    AlertCircle,
    ArrowLeft,
    Play,
    BarChart3,
    Award,
    Star,
} from 'lucide-vue-next';

interface QuestionOption {
    id: number;
    option_text: string;
    order_index: number;
}

interface Question {
    id: number;
    question_text: string;
    question_type: string;
    points: number;
    order_index: number;
    options: QuestionOption[];
}

interface Attempt {
    id: number;
    score: number;
    passed: boolean;
    completed_at: string;
}

interface Module {
    id: number;
    title: string;
}

interface Course {
    id: number;
    title: string;
    slug: string;
}

interface Assessment {
    id: number;
    title: string;
    description: string;
    instructions: string;
    time_limit: number;
    passing_score: number;
    max_attempts: number;
    is_required: boolean;
    module: Module;
    course: Course;
    questions: Question[];
    attempts?: Attempt[];
    can_attempt?: boolean;
}

interface BreadcrumbItem {
    title: string;
    url?: string;
}

interface Props {
    assessment: Assessment;
    breadcrumbs: BreadcrumbItem[];
}

const props = defineProps<Props>();

const formatDuration = (minutes: number): string => {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const bestAttempt = props.assessment.attempts?.reduce((best, current) => {
    return current.score > (best?.score || 0) ? current : best;
}, null as Attempt | null);

const attemptsRemaining = props.assessment.max_attempts - (props.assessment.attempts?.length || 0);

const startAssessment = () => {
    // TODO: Implement assessment start logic
    console.log('Start assessment');
};
</script>

<template>
    <Head>
        <title>{{ assessment.title }} - {{ assessment.course.title }}</title>
        <meta name="description" :content="assessment.description" />
    </Head>

    <FrontLayout>
        <!-- Breadcrumbs -->
        <div class="border-b bg-gray-50/50 py-4 dark:bg-gray-900/20">
            <div class="container mx-auto px-4">
                <Breadcrumbs :breadcrumbs="breadcrumbs" :is-back="true" />
            </div>
        </div>

        <!-- Main Content -->
        <div class="min-h-screen bg-background">
            <div class="container mx-auto px-4 py-8">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-3">
                        <!-- Assessment Header -->
                        <div class="mb-8">
                            <div class="mb-4 flex items-center gap-3">
                                <Badge variant="outline">
                                    <Target class="mr-1 h-3 w-3" />
                                    Assessment
                                </Badge>
                                <Badge
                                    v-if="assessment.is_required"
                                    variant="destructive"
                                    class="text-xs"
                                >
                                    <Star class="mr-1 h-3 w-3 fill-current" />
                                    Required
                                </Badge>
                                <Badge v-else variant="secondary" class="text-xs">
                                    Optional
                                </Badge>
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock class="h-4 w-4" />
                                    <span>{{ formatDuration(assessment.time_limit) }} time limit</span>
                                </div>
                            </div>

                            <h1 class="mb-4 text-3xl font-bold leading-tight lg:text-4xl">
                                {{ assessment.title }}
                            </h1>

                            <p class="text-lg text-muted-foreground leading-relaxed">
                                {{ assessment.description }}
                            </p>
                        </div>

                        <!-- Assessment Instructions -->
                        <Card class="mb-8">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <AlertCircle class="h-5 w-5 text-blue-600" />
                                    Instructions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="prose prose-lg max-w-none dark:prose-invert" v-html="assessment.instructions"></div>
                            </CardContent>
                        </Card>

                        <!-- Previous Attempts -->
                        <Card v-if="assessment.attempts && assessment.attempts.length > 0" class="mb-8">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <BarChart3 class="h-5 w-5 text-purple-600" />
                                    Previous Attempts
                                </CardTitle>
                                <CardDescription>
                                    Your assessment history and scores
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-4">
                                    <div
                                        v-for="(attempt, index) in assessment.attempts"
                                        :key="attempt.id"
                                        class="flex items-center justify-between rounded-lg border p-4"
                                        :class="{
                                            'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800': attempt.passed,
                                            'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800': !attempt.passed
                                        }"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full"
                                                 :class="{
                                                     'bg-green-100 dark:bg-green-900/30': attempt.passed,
                                                     'bg-red-100 dark:bg-red-900/30': !attempt.passed
                                                 }"
                                            >
                                                <CheckCircle v-if="attempt.passed" class="h-5 w-5 text-green-600 dark:text-green-400" />
                                                <AlertCircle v-else class="h-5 w-5 text-red-600 dark:text-red-400" />
                                            </div>
                                            <div>
                                                <h4 class="font-semibold">Attempt {{ index + 1 }}</h4>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ formatDate(attempt.completed_at) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold"
                                                 :class="{
                                                     'text-green-600 dark:text-green-400': attempt.passed,
                                                     'text-red-600 dark:text-red-400': !attempt.passed
                                                 }"
                                            >
                                                {{ attempt.score }}%
                                            </div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ attempt.passed ? 'Passed' : 'Failed' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Assessment Actions -->
                        <div class="flex items-center justify-between">
                            <Button
                                :as="Link"
                                :href="`/courses/${assessment.course.slug}/modules/${assessment.module.id}`"
                                variant="outline"
                            >
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Back to Module
                            </Button>

                            <div class="flex items-center gap-4">
                                <Button
                                    v-if="assessment.can_attempt"
                                    @click="startAssessment"
                                    class="bg-purple-600 hover:bg-purple-700"
                                    size="lg"
                                >
                                    <Play class="mr-2 h-5 w-5" />
                                    {{ assessment.attempts?.length ? 'Retake Assessment' : 'Start Assessment' }}
                                </Button>
                                <div v-else class="text-center">
                                    <p class="text-sm text-muted-foreground">
                                        {{ attemptsRemaining <= 0 ? 'No attempts remaining' : 'Assessment not available' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-6">
                            <!-- Assessment Info -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg">Assessment Details</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-4 text-sm">
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Questions</span>
                                            <span class="font-semibold">{{ assessment.questions.length }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Time Limit</span>
                                            <span class="font-semibold">{{ formatDuration(assessment.time_limit) }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Passing Score</span>
                                            <span class="font-semibold">{{ assessment.passing_score }}%</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Max Attempts</span>
                                            <span class="font-semibold">{{ assessment.max_attempts }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Attempts Used</span>
                                            <span class="font-semibold">{{ assessment.attempts?.length || 0 }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-muted-foreground">Remaining</span>
                                            <span class="font-semibold text-blue-600">{{ attemptsRemaining }}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Best Score -->
                            <Card v-if="bestAttempt">
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2 text-lg">
                                        <Award class="h-5 w-5 text-yellow-600" />
                                        Best Score
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-center">
                                        <div class="text-4xl font-bold mb-2"
                                             :class="{
                                                 'text-green-600 dark:text-green-400': bestAttempt.passed,
                                                 'text-red-600 dark:text-red-400': !bestAttempt.passed
                                             }"
                                        >
                                            {{ bestAttempt.score }}%
                                        </div>
                                        <Badge
                                            :variant="bestAttempt.passed ? 'default' : 'destructive'"
                                            class="text-xs"
                                        >
                                            {{ bestAttempt.passed ? 'Passed' : 'Failed' }}
                                        </Badge>
                                        <p class="text-xs text-muted-foreground mt-2">
                                            {{ formatDate(bestAttempt.completed_at) }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Module Info -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg">Module</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-3">
                                        <h4 class="font-semibold">{{ assessment.module.title }}</h4>
                                        <Button
                                            :as="Link"
                                            :href="`/courses/${assessment.course.slug}/modules/${assessment.module.id}`"
                                            variant="outline"
                                            size="sm"
                                            class="w-full"
                                        >
                                            Back to Module
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Course Info -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg">Course</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-3">
                                        <h4 class="font-semibold">{{ assessment.course.title }}</h4>
                                        <Button
                                            :as="Link"
                                            :href="`/courses/${assessment.course.slug}`"
                                            variant="outline"
                                            size="sm"
                                            class="w-full"
                                        >
                                            View Course
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </FrontLayout>
</template>
