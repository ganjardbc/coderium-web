<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import FrontLayout from '@/layouts/FrontLayout.vue';
import type { Assessment, BreadcrumbItem, Question } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    CheckCircle,
    HelpCircle,
    Target,
    Timer,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface AssessmentData {
    assessment: Assessment;
    questions: Question[];
    can_take: boolean;
    has_passed: boolean;
    remaining_attempts: number;
    best_score?: number;
    time_limit?: number;
    attempt_id?: number;
    started_at?: string;
    time_remaining?: number;
}

interface Props {
    assessmentData: AssessmentData;
    breadcrumbs: BreadcrumbItem[];
}

const props = defineProps<Props>();

const answers = ref<
    Record<number, { selected_options?: number[]; answer_text?: string }>
>({});
const currentQuestionIndex = ref(0);
const isSubmitting = ref(false);
const timeRemaining = ref(props.assessmentData.time_remaining || 0);
const timerInterval = ref<NodeJS.Timeout | null>(null);

const currentQuestion = computed(() => {
    return props.assessmentData.questions[currentQuestionIndex.value];
});

const totalQuestions = computed(() => {
    return props.assessmentData.questions.length;
});

const answeredQuestions = computed(() => {
    return Object.keys(answers.value).length;
});

const progressPercentage = computed(() => {
    return totalQuestions.value > 0
        ? (answeredQuestions.value / totalQuestions.value) * 100
        : 0;
});

const canSubmit = computed(() => {
    return answeredQuestions.value === totalQuestions.value;
});

const formatTime = (seconds: number): string => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
};

const startTimer = () => {
    if (props.assessmentData.time_limit && timeRemaining.value > 0) {
        timerInterval.value = setInterval(() => {
            timeRemaining.value--;
            if (timeRemaining.value <= 0) {
                submitAssessment();
            }
        }, 1000);
    }
};

const stopTimer = () => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
    }
};

const selectOption = (
    questionId: number,
    optionId: number,
    isMultiple: boolean = false,
) => {
    if (!answers.value[questionId]) {
        answers.value[questionId] = { selected_options: [] };
    }

    if (isMultiple) {
        const selectedOptions =
            answers.value[questionId].selected_options || [];
        const index = selectedOptions.indexOf(optionId);
        if (index > -1) {
            selectedOptions.splice(index, 1);
        } else {
            selectedOptions.push(optionId);
        }
        answers.value[questionId].selected_options = selectedOptions;
    } else {
        answers.value[questionId].selected_options = [optionId];
    }
};

const updateTextAnswer = (questionId: number, text: string) => {
    if (!answers.value[questionId]) {
        answers.value[questionId] = {};
    }
    answers.value[questionId].answer_text = text;
};

const nextQuestion = () => {
    if (currentQuestionIndex.value < totalQuestions.value - 1) {
        currentQuestionIndex.value++;
    }
};

const previousQuestion = () => {
    if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value--;
    }
};

const goToQuestion = (index: number) => {
    currentQuestionIndex.value = index;
};

const submitAssessment = async () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;
    stopTimer();

    const timeTaken = props.assessmentData.time_limit
        ? props.assessmentData.time_limit * 60 - timeRemaining.value
        : undefined;

    try {
        await router.post(
            `/api/assessments/${props.assessmentData.assessment.id}/submit`,
            {
                answers: answers.value,
                time_taken: timeTaken,
            },
            {
                onSuccess: () => {
                    // Redirect to results page
                    router.visit(
                        `/classroom/assessments/${props.assessmentData.assessment.id}/results`,
                    );
                },
                onError: (errors) => {
                    console.error('Assessment submission failed:', errors);
                    isSubmitting.value = false;
                    startTimer(); // Restart timer if submission failed
                },
            },
        );
    } catch (error) {
        console.error('Assessment submission error:', error);
        isSubmitting.value = false;
        startTimer(); // Restart timer if submission failed
    }
};

const getQuestionTypeIcon = (type: string) => {
    switch (type) {
        case 'multiple_choice':
            return CheckCircle;
        case 'true_false':
            return Target;
        case 'code_output':
            return Target;
        case 'conceptual':
            return HelpCircle;
        default:
            return HelpCircle;
    }
};

const getQuestionTypeLabel = (type: string): string => {
    switch (type) {
        case 'multiple_choice':
            return 'Multiple Choice';
        case 'true_false':
            return 'True/False';
        case 'code_output':
            return 'Code Output';
        case 'conceptual':
            return 'Conceptual';
        default:
            return 'Question';
    }
};

const isOptionSelected = (questionId: number, optionId: number): boolean => {
    return (
        answers.value[questionId]?.selected_options?.includes(optionId) || false
    );
};

const isQuestionAnswered = (questionId: number): boolean => {
    const answer = answers.value[questionId];
    return !!(answer?.selected_options?.length || answer?.answer_text?.trim());
};

onMounted(() => {
    // Initialize answers object
    props.assessmentData.questions.forEach((question) => {
        answers.value[question.id] = { selected_options: [], answer_text: '' };
    });

    // Start timer if assessment is timed
    startTimer();
});

onUnmounted(() => {
    stopTimer();
});
</script>

<template>
    <Head :title="`${assessmentData.assessment.title} - Assessment`" />

    <FrontLayout>
        <!-- Breadcrumbs -->
        <div class="container mx-auto max-w-7xl px-4 py-4">
            <Breadcrumbs :items="breadcrumbs" />
        </div>

        <!-- Assessment Header -->
        <section
            class="border-b bg-gradient-to-r from-orange-50 to-red-50 py-4 sm:py-6 dark:from-orange-950 dark:to-red-950"
        >
            <div class="container mx-auto max-w-7xl px-4">
                <div
                    class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
                >
                    <div class="flex-1">
                        <div
                            class="mb-2 flex flex-wrap items-center gap-2 sm:gap-3"
                        >
                            <Badge variant="outline">Assessment</Badge>
                            <Badge
                                v-if="assessmentData.assessment.is_required"
                                variant="destructive"
                            >
                                Required
                            </Badge>
                            <Badge
                                v-if="assessmentData.assessment.time_limit"
                                variant="secondary"
                            >
                                <Timer class="mr-1 h-3 w-3" />
                                {{ assessmentData.assessment.time_limit }} min
                            </Badge>
                        </div>
                        <h1 class="text-xl font-bold sm:text-2xl md:text-3xl">
                            {{ assessmentData.assessment.title }}
                        </h1>
                        <p
                            v-if="assessmentData.assessment.description"
                            class="mt-2 text-sm text-muted-foreground sm:text-base"
                        >
                            {{ assessmentData.assessment.description }}
                        </p>
                    </div>

                    <!-- Timer -->
                    <div
                        v-if="assessmentData.time_limit && timeRemaining > 0"
                        class="text-center sm:text-right"
                    >
                        <div
                            class="mb-1 text-xs text-muted-foreground sm:text-sm"
                        >
                            Time Remaining
                        </div>
                        <div
                            class="font-mono text-xl font-bold sm:text-2xl"
                            :class="
                                timeRemaining < 300
                                    ? 'text-red-600'
                                    : 'text-foreground'
                            "
                        >
                            {{ formatTime(timeRemaining) }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Assessment Content -->
        <section class="py-6 sm:py-8">
            <div class="container mx-auto max-w-7xl px-4">
                <div class="grid grid-cols-1 gap-6 sm:gap-8 lg:grid-cols-4">
                    <!-- Main Content -->
                    <div class="lg:col-span-3">
                        <!-- Progress -->
                        <Card class="mb-4 sm:mb-6">
                            <CardContent class="p-4">
                                <div
                                    class="mb-2 flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Progress</span
                                    >
                                    <span class="font-medium"
                                        >{{ answeredQuestions }} of
                                        {{ totalQuestions }} answered</span
                                    >
                                </div>
                                <Progress :value="progressPercentage" />
                            </CardContent>
                        </Card>

                        <!-- Current Question -->
                        <Card v-if="currentQuestion">
                            <CardHeader class="p-4 sm:p-6">
                                <div
                                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2 sm:gap-3"
                                    >
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            Question
                                            {{ currentQuestionIndex + 1 }} of
                                            {{ totalQuestions }}
                                        </Badge>
                                        <Badge
                                            :class="
                                                getQuestionTypeIcon(
                                                    currentQuestion.question_type,
                                                )
                                            "
                                            variant="secondary"
                                            class="text-xs"
                                        >
                                            <component
                                                :is="
                                                    getQuestionTypeIcon(
                                                        currentQuestion.question_type,
                                                    )
                                                "
                                                class="mr-1 h-3 w-3"
                                            />
                                            {{
                                                getQuestionTypeLabel(
                                                    currentQuestion.question_type,
                                                )
                                            }}
                                        </Badge>
                                        <div
                                            class="text-xs text-muted-foreground sm:text-sm"
                                        >
                                            {{ currentQuestion.points }}
                                            {{
                                                currentQuestion.points === 1
                                                    ? 'point'
                                                    : 'points'
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <CardTitle class="mt-3 text-base sm:text-lg">
                                    {{ currentQuestion.question_text }}
                                </CardTitle>
                            </CardHeader>

                            <CardContent class="space-y-4 p-4 sm:p-6">
                                <!-- Multiple Choice / True False -->
                                <div
                                    v-if="
                                        currentQuestion.question_type ===
                                            'multiple_choice' ||
                                        currentQuestion.question_type ===
                                            'true_false'
                                    "
                                >
                                    <div class="space-y-3">
                                        <div
                                            v-for="option in currentQuestion.options"
                                            :key="option.id"
                                            class="flex cursor-pointer touch-manipulation items-center space-x-3 rounded-lg border p-3 transition-colors hover:bg-muted/50 sm:p-4"
                                            :class="
                                                isOptionSelected(
                                                    currentQuestion.id,
                                                    option.id,
                                                )
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border'
                                            "
                                            @click="
                                                selectOption(
                                                    currentQuestion.id,
                                                    option.id,
                                                    currentQuestion.question_type ===
                                                        'multiple_choice',
                                                )
                                            "
                                        >
                                            <div
                                                class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border-2"
                                                :class="
                                                    isOptionSelected(
                                                        currentQuestion.id,
                                                        option.id,
                                                    )
                                                        ? 'border-primary bg-primary'
                                                        : 'border-muted-foreground'
                                                "
                                            >
                                                <div
                                                    v-if="
                                                        isOptionSelected(
                                                            currentQuestion.id,
                                                            option.id,
                                                        )
                                                    "
                                                    class="h-2.5 w-2.5 rounded-full bg-white"
                                                ></div>
                                            </div>
                                            <span
                                                class="flex-1 text-sm sm:text-base"
                                                >{{ option.option_text }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Code Output / Conceptual -->
                                <div
                                    v-else-if="
                                        currentQuestion.question_type ===
                                            'code_output' ||
                                        currentQuestion.question_type ===
                                            'conceptual'
                                    "
                                >
                                    <textarea
                                        :value="
                                            answers[currentQuestion.id]
                                                ?.answer_text || ''
                                        "
                                        @input="
                                            updateTextAnswer(
                                                currentQuestion.id,
                                                (
                                                    $event.target as HTMLTextAreaElement
                                                ).value,
                                            )
                                        "
                                        class="resize-vertical min-h-[120px] w-full rounded-lg border p-3 text-sm focus:border-transparent focus:ring-2 focus:ring-primary focus:outline-none sm:p-4 sm:text-base"
                                        :placeholder="
                                            currentQuestion.question_type ===
                                            'code_output'
                                                ? 'Enter the expected output...'
                                                : 'Enter your answer...'
                                        "
                                    ></textarea>
                                </div>

                                <!-- Navigation -->
                                <div
                                    class="flex flex-col items-center justify-between gap-4 pt-4 sm:flex-row"
                                >
                                    <Button
                                        @click="previousQuestion"
                                        :disabled="currentQuestionIndex === 0"
                                        variant="outline"
                                        class="h-12 w-full text-base sm:w-auto"
                                    >
                                        Previous
                                    </Button>

                                    <div
                                        class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"
                                    >
                                        <Button
                                            v-if="
                                                currentQuestionIndex <
                                                totalQuestions - 1
                                            "
                                            @click="nextQuestion"
                                            variant="outline"
                                            class="h-12 w-full text-base sm:w-auto"
                                        >
                                            Next
                                            <ArrowRight class="ml-2 h-4 w-4" />
                                        </Button>

                                        <Button
                                            v-if="
                                                currentQuestionIndex ===
                                                totalQuestions - 1
                                            "
                                            @click="submitAssessment"
                                            :disabled="
                                                !canSubmit || isSubmitting
                                            "
                                            variant="default"
                                            class="h-12 w-full text-base sm:w-auto"
                                        >
                                            {{
                                                isSubmitting
                                                    ? 'Submitting...'
                                                    : 'Submit Assessment'
                                            }}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="order-first lg:order-last lg:col-span-1">
                        <div class="space-y-4 sm:space-y-6 lg:sticky lg:top-4">
                            <!-- Assessment Info -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-base sm:text-lg"
                                        >Assessment Info</CardTitle
                                    >
                                </CardHeader>
                                <CardContent class="space-y-3 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Questions</span
                                        >
                                        <span class="font-medium">{{
                                            totalQuestions
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Passing Score</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                assessmentData.assessment
                                                    .passing_score
                                            }}%</span
                                        >
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Max Attempts</span
                                        >
                                        <span class="font-medium">{{
                                            assessmentData.assessment
                                                .max_attempts
                                        }}</span>
                                    </div>
                                    <div
                                        v-if="
                                            assessmentData.assessment.time_limit
                                        "
                                        class="flex justify-between"
                                    >
                                        <span class="text-muted-foreground"
                                            >Time Limit</span
                                        >
                                        <span class="font-medium"
                                            >{{
                                                assessmentData.assessment
                                                    .time_limit
                                            }}
                                            min</span
                                        >
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground"
                                            >Remaining Attempts</span
                                        >
                                        <span class="font-medium">{{
                                            assessmentData.remaining_attempts
                                        }}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Question Navigator -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-base sm:text-lg"
                                        >Questions</CardTitle
                                    >
                                </CardHeader>
                                <CardContent>
                                    <div class="grid grid-cols-5 gap-2">
                                        <button
                                            v-for="(
                                                question, index
                                            ) in assessmentData.questions"
                                            :key="question.id"
                                            @click="goToQuestion(index)"
                                            class="h-8 w-8 touch-manipulation rounded-full border text-xs font-medium transition-colors sm:h-10 sm:w-10"
                                            :class="[
                                                index === currentQuestionIndex
                                                    ? 'border-primary bg-primary text-white'
                                                    : isQuestionAnswered(
                                                            question.id,
                                                        )
                                                      ? 'border-green-500 bg-green-500 text-white'
                                                      : 'border-muted-foreground hover:border-primary',
                                            ]"
                                        >
                                            {{ index + 1 }}
                                        </button>
                                    </div>

                                    <div class="mt-4 space-y-2 text-xs">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="h-3 w-3 rounded-full border-primary bg-primary"
                                            ></div>
                                            <span class="text-muted-foreground"
                                                >Current</span
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="h-3 w-3 rounded-full border-green-500 bg-green-500"
                                            ></div>
                                            <span class="text-muted-foreground"
                                                >Answered</span
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="h-3 w-3 rounded-full border-muted-foreground"
                                            ></div>
                                            <span class="text-muted-foreground"
                                                >Unanswered</span
                                            >
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Warning -->
                            <Card
                                v-if="!canSubmit"
                                class="border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950"
                            >
                                <CardContent class="p-4">
                                    <div class="flex items-start gap-3">
                                        <AlertCircle
                                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-600"
                                        />
                                        <div>
                                            <p
                                                class="text-sm font-medium text-yellow-800 dark:text-yellow-200"
                                            >
                                                Answer all questions
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-yellow-700 dark:text-yellow-300"
                                            >
                                                You must answer all
                                                {{ totalQuestions }} questions
                                                before submitting.
                                            </p>
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
