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
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type {
    AssessmentAttempt,
    AssessmentFeedback,
    BreadcrumbItem,
} from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Award,
    CheckCircle,
    RotateCcw,
    Target,
    TrendingUp,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface AssessmentResults {
    assessment: {
        id: number;
        title: string;
        description?: string;
        passing_score: number;
        max_attempts: number;
        is_required: boolean;
    };
    latest_attempt: AssessmentAttempt;
    feedback: AssessmentFeedback;
    has_passed: boolean;
    can_retake: boolean;
    remaining_attempts: number;
    all_attempts: AssessmentAttempt[];
    best_attempt?: AssessmentAttempt;
}

interface Props {
    results: AssessmentResults;
    breadcrumbs: BreadcrumbItem[];
}

const props = defineProps<Props>();

const scorePercentage = computed(() => {
    return props.results.feedback.percentage;
});

const isPassed = computed(() => {
    return props.results.feedback.passed;
});

const formatTime = (seconds: number | undefined): string => {
    if (!seconds) return 'N/A';
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
};

const getScoreColor = (percentage: number): string => {
    if (percentage >= props.results.assessment.passing_score) {
        return 'text-green-600';
    } else if (percentage >= 50) {
        return 'text-yellow-600';
    } else {
        return 'text-red-600';
    }
};

const getScoreBadgeVariant = (percentage: number): string => {
    if (percentage >= props.results.assessment.passing_score) {
        return 'default';
    } else {
        return 'destructive';
    }
};

const retakeAssessment = () => {
    router.visit(`/classroom/assessments/${props.results.assessment.id}`);
};

const correctAnswersCount = computed(() => {
    return props.results.feedback.questions_feedback.filter((q) => q.is_correct)
        .length;
});

const totalQuestions = computed(() => {
    return props.results.feedback.questions_feedback.length;
});

const getQuestionIcon = (isCorrect: boolean) => {
    return isCorrect ? CheckCircle : XCircle;
};

const getQuestionIconColor = (isCorrect: boolean): string => {
    return isCorrect ? 'text-green-500' : 'text-red-500';
};

const formatAttemptDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`${results.assessment.title} - Results`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <div class="container mx-auto max-w-7xl px-4 py-4">
            <Breadcrumbs :items="breadcrumbs" />
        </div>

        <!-- Results Header -->
        <section
            class="border-b py-8"
            :class="
                isPassed
                    ? 'bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-950 dark:to-emerald-950'
                    : 'bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-950 dark:to-orange-950'
            "
        >
            <div class="container mx-auto max-w-7xl px-4">
                <div class="text-center">
                    <div class="mb-4 flex items-center justify-center">
                        <div
                            class="rounded-full p-4"
                            :class="
                                isPassed
                                    ? 'bg-green-100 dark:bg-green-900'
                                    : 'bg-red-100 dark:bg-red-900'
                            "
                        >
                            <Award
                                v-if="isPassed"
                                class="h-12 w-12 text-green-600"
                            />
                            <AlertCircle
                                v-else
                                class="h-12 w-12 text-red-600"
                            />
                        </div>
                    </div>

                    <h1 class="mb-2 text-3xl font-bold md:text-4xl">
                        {{
                            isPassed
                                ? 'Congratulations!'
                                : 'Assessment Complete'
                        }}
                    </h1>

                    <p class="mb-6 text-lg text-muted-foreground">
                        {{ results.assessment.title }}
                    </p>

                    <div class="flex items-center justify-center gap-6 text-sm">
                        <div class="text-center">
                            <div
                                class="text-2xl font-bold"
                                :class="getScoreColor(scorePercentage)"
                            >
                                {{ Math.round(scorePercentage) }}%
                            </div>
                            <div class="text-muted-foreground">Score</div>
                        </div>
                        <Separator orientation="vertical" class="h-12" />
                        <div class="text-center">
                            <div class="text-2xl font-bold">
                                {{ correctAnswersCount }}/{{ totalQuestions }}
                            </div>
                            <div class="text-muted-foreground">Correct</div>
                        </div>
                        <Separator orientation="vertical" class="h-12" />
                        <div class="text-center">
                            <div class="text-2xl font-bold">
                                {{ formatTime(results.feedback.time_taken) }}
                            </div>
                            <div class="text-muted-foreground">Time</div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <Badge
                            :variant="getScoreBadgeVariant(scorePercentage)"
                            class="px-4 py-2 text-lg"
                        >
                            {{ isPassed ? 'PASSED' : 'NOT PASSED' }}
                        </Badge>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Passing score:
                            {{ results.assessment.passing_score }}%
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Results Content -->
        <section class="py-8">
            <div class="container mx-auto max-w-7xl px-4">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Score Breakdown -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Target class="h-5 w-5" />
                                    Score Breakdown
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div
                                    class="mb-2 flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Your Score</span
                                    >
                                    <span class="font-medium"
                                        >{{ results.feedback.score }} /
                                        {{
                                            results.feedback.max_score
                                        }}
                                        points</span
                                    >
                                </div>
                                <Progress
                                    :value="scorePercentage"
                                    class="mb-4"
                                />

                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Correct Answers</span
                                        >
                                        <span
                                            class="font-medium text-green-600"
                                            >{{ correctAnswersCount }}</span
                                        >
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Incorrect Answers</span
                                        >
                                        <span
                                            class="font-medium text-red-600"
                                            >{{
                                                totalQuestions -
                                                correctAnswersCount
                                            }}</span
                                        >
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Percentage</span
                                        >
                                        <span
                                            class="font-medium"
                                            :class="
                                                getScoreColor(scorePercentage)
                                            "
                                            >{{
                                                Math.round(scorePercentage)
                                            }}%</span
                                        >
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Required</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                results.assessment
                                                    .passing_score
                                            }}%</span
                                        >
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Question Review -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Question Review</CardTitle>
                                <CardDescription>
                                    Review your answers and see the correct
                                    solutions
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div
                                    v-for="(question, index) in results.feedback
                                        .questions_feedback"
                                    :key="question.question_id"
                                    class="rounded-lg border p-4"
                                >
                                    <div class="mb-3 flex items-start gap-3">
                                        <component
                                            :is="
                                                getQuestionIcon(
                                                    question.is_correct,
                                                )
                                            "
                                            :class="
                                                getQuestionIconColor(
                                                    question.is_correct,
                                                )
                                            "
                                            class="mt-0.5 h-5 w-5 flex-shrink-0"
                                        />
                                        <div class="flex-1">
                                            <div
                                                class="mb-2 flex items-center gap-2"
                                            >
                                                <Badge
                                                    variant="outline"
                                                    class="text-xs"
                                                >
                                                    Question {{ index + 1 }}
                                                </Badge>
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        question.points_earned
                                                    }}
                                                    /
                                                    {{ question.points }} points
                                                </span>
                                            </div>
                                            <h4 class="mb-2 font-medium">
                                                {{ question.question_text }}
                                            </h4>

                                            <!-- Show user's answer -->
                                            <div class="text-sm">
                                                <div
                                                    v-if="
                                                        question.selected_options &&
                                                        question
                                                            .selected_options
                                                            .length > 0
                                                    "
                                                    class="mb-2"
                                                >
                                                    <span
                                                        class="text-muted-foreground"
                                                        >Your answer:
                                                    </span>
                                                    <span
                                                        :class="
                                                            question.is_correct
                                                                ? 'text-green-600'
                                                                : 'text-red-600'
                                                        "
                                                    >
                                                        Selected options:
                                                        {{
                                                            question.selected_options.join(
                                                                ', ',
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                                <div
                                                    v-else-if="
                                                        question.answer_text
                                                    "
                                                    class="mb-2"
                                                >
                                                    <span
                                                        class="text-muted-foreground"
                                                        >Your answer:
                                                    </span>
                                                    <span
                                                        :class="
                                                            question.is_correct
                                                                ? 'text-green-600'
                                                                : 'text-red-600'
                                                        "
                                                    >
                                                        {{
                                                            question.answer_text
                                                        }}
                                                    </span>
                                                </div>

                                                <!-- Show correct answer if wrong -->
                                                <div
                                                    v-if="
                                                        !question.is_correct &&
                                                        question.correct_options
                                                    "
                                                    class="mb-2"
                                                >
                                                    <span
                                                        class="text-muted-foreground"
                                                        >Correct answer:
                                                    </span>
                                                    <span
                                                        class="text-green-600"
                                                    >
                                                        Options:
                                                        {{
                                                            question.correct_options.join(
                                                                ', ',
                                                            )
                                                        }}
                                                    </span>
                                                </div>

                                                <!-- Show explanation if available -->
                                                <div
                                                    v-if="question.explanation"
                                                    class="mt-2 rounded bg-muted p-2 text-xs"
                                                >
                                                    <strong
                                                        >Explanation:</strong
                                                    >
                                                    {{ question.explanation }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4 space-y-6">
                            <!-- Actions -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Next Steps</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Button
                                        v-if="results.can_retake && !isPassed"
                                        @click="retakeAssessment"
                                        class="w-full"
                                    >
                                        <RotateCcw class="mr-2 h-4 w-4" />
                                        Retake Assessment
                                    </Button>

                                    <Button
                                        v-if="isPassed"
                                        :as="Link"
                                        href="#"
                                        class="w-full"
                                        variant="outline"
                                    >
                                        Continue Learning
                                    </Button>

                                    <Button
                                        :as="Link"
                                        href="#"
                                        variant="outline"
                                        class="w-full"
                                    >
                                        <ArrowLeft class="mr-2 h-4 w-4" />
                                        Back to Content
                                    </Button>
                                </CardContent>
                            </Card>

                            <!-- Assessment Stats -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-lg"
                                        >Assessment Details</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Status</span
                                        >
                                        <Badge
                                            :variant="
                                                isPassed
                                                    ? 'default'
                                                    : 'destructive'
                                            "
                                            class="text-xs"
                                        >
                                            {{ isPassed ? 'Passed' : 'Failed' }}
                                        </Badge>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Score</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                Math.round(scorePercentage)
                                            }}%</span
                                        >
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Passing Score</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                results.assessment
                                                    .passing_score
                                            }}%</span
                                        >
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Time Taken</span
                                        >
                                        <span class="font-medium">{{
                                            formatTime(
                                                results.feedback.time_taken,
                                            )
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Attempt</span
                                        >
                                        <span class="font-medium">{{
                                            results.latest_attempt
                                                .attempt_number
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground"
                                            >Remaining</span
                                        >
                                        <span class="font-medium">{{
                                            results.remaining_attempts
                                        }}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Attempt History -->
                            <Card v-if="results.all_attempts.length > 1">
                                <CardHeader>
                                    <CardTitle
                                        class="flex items-center gap-2 text-lg"
                                    >
                                        <TrendingUp class="h-5 w-5" />
                                        Attempt History
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-3">
                                        <div
                                            v-for="attempt in results.all_attempts
                                                .slice()
                                                .reverse()"
                                            :key="attempt.id"
                                            class="flex items-center justify-between rounded p-2 text-sm"
                                            :class="
                                                attempt.id ===
                                                results.latest_attempt.id
                                                    ? 'border border-primary/20 bg-primary/10'
                                                    : 'bg-muted/50'
                                            "
                                        >
                                            <div>
                                                <div class="font-medium">
                                                    Attempt
                                                    {{ attempt.attempt_number }}
                                                    <Badge
                                                        v-if="
                                                            attempt.id ===
                                                            results
                                                                .latest_attempt
                                                                .id
                                                        "
                                                        variant="outline"
                                                        class="ml-2 text-xs"
                                                    >
                                                        Latest
                                                    </Badge>
                                                </div>
                                                <div
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        formatAttemptDate(
                                                            attempt.completed_at ||
                                                                attempt.created_at,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div
                                                    class="font-medium"
                                                    :class="
                                                        attempt.passed
                                                            ? 'text-green-600'
                                                            : 'text-red-600'
                                                    "
                                                >
                                                    {{
                                                        Math.round(
                                                            (attempt.score /
                                                                attempt.max_score) *
                                                                100,
                                                        )
                                                    }}%
                                                </div>
                                                <div
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        attempt.passed
                                                            ? 'Passed'
                                                            : 'Failed'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Best Score -->
                            <Card
                                v-if="
                                    results.best_attempt &&
                                    results.best_attempt.id !==
                                        results.latest_attempt.id
                                "
                            >
                                <CardHeader>
                                    <CardTitle
                                        class="flex items-center gap-2 text-lg"
                                    >
                                        <Award class="h-5 w-5" />
                                        Best Score
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-center">
                                        <div
                                            class="mb-1 text-2xl font-bold text-green-600"
                                        >
                                            {{
                                                Math.round(
                                                    (results.best_attempt
                                                        .score /
                                                        results.best_attempt
                                                            .max_score) *
                                                        100,
                                                )
                                            }}%
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            Attempt
                                            {{
                                                results.best_attempt
                                                    .attempt_number
                                            }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatAttemptDate(
                                                    results.best_attempt
                                                        .completed_at ||
                                                        results.best_attempt
                                                            .created_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
