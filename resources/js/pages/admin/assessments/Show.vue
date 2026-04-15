<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Edit,
    FileCheck,
    Clock,
    Users,
    Target,
    CheckCircle,
    XCircle,
    Calendar
} from 'lucide-vue-next';

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
    order_index: number;
}

interface Question {
    id: number;
    question_text: string;
    question_type: 'multiple_choice' | 'true_false' | 'code_output' | 'conceptual';
    points: number;
    order_index: number;
    explanation: string | null;
    options: QuestionOption[];
}

interface Attempt {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    score: number;
    total_points: number;
    percentage: number;
    passed: boolean;
    started_at: string;
    completed_at: string | null;
}

interface Assessment {
    id: number;
    title: string;
    description: string;
    assessable_type: string | null;
    assessable?: {
        id: number;
        title: string;
    };
    time_limit: number | null;
    passing_score: number;
    max_attempts: number;
    is_required: boolean;
    questions_count: number;
    attempts_count: number;
    questions: Question[];
    attempts: Attempt[];
    created_at: string;
    updated_at: string;
}

interface Props {
    assessment: {
        data: Assessment;
    };
}

const props = defineProps<Props>();

// Extract the assessment data from the resource wrapper
const assessment = computed(() => props.assessment.data);

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Assessments', href: '/admin/assessments' },
    { title: assessment.value?.title || 'Assessment', href: `/admin/assessments/${assessment.value?.id}` },
];

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'No limit';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const getQuestionTypeColor = (type: string) => {
    switch (type) {
        case 'multiple_choice':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'true_false':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'code_output':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 'conceptual':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getPassingRate = () => {
    if (!assessment.value?.attempts_count || assessment.value.attempts_count === 0) return 0;
    const passedAttempts = assessment.value.attempts?.filter((attempt: any) => attempt.passed).length || 0;
    return Math.round((passedAttempts / assessment.value.attempts_count) * 100);
};

const getAverageScore = () => {
    if (!assessment.value?.attempts || assessment.value.attempts.length === 0) return 0;
    const totalScore = assessment.value.attempts.reduce((sum: number, attempt: any) => sum + attempt.percentage, 0);
    return Math.round(totalScore / assessment.value.attempts.length);
};
</script>

<template>
    <Head :title="`${assessment?.title || 'Assessment'} - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ assessment?.title || 'Assessment' }}</h1>
                    <p class="text-muted-foreground">
                        Assessment details and management
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button as-child>
                        <Link :href="`/admin/assessments/${assessment?.id}/edit`">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit Assessment
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Assessment Overview -->
            <div class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <FileCheck class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Questions</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ assessment?.questions_count || 0 }}</div>
                            <p class="text-xs text-muted-foreground">
                                Total questions
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <Users class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Attempts</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ assessment?.attempts_count || 0 }}</div>
                            <p class="text-xs text-muted-foreground">
                                Total attempts
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <Target class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Pass Rate</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ getPassingRate() }}%</div>
                            <p class="text-xs text-muted-foreground">
                                Students passing
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <Clock class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Time Limit</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ formatDuration(assessment?.time_limit) }}</div>
                            <p class="text-xs text-muted-foreground">
                                Maximum time
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Tabs default-value="overview" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="questions">Questions</TabsTrigger>
                    <TabsTrigger value="attempts">Attempts</TabsTrigger>
                    <TabsTrigger value="analytics">Analytics</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="space-y-4">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Assessment Information</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div>
                                        <h4 class="font-medium mb-2">Description</h4>
                                        <p class="text-muted-foreground">{{ assessment?.description || 'No description provided' }}</p>
                                    </div>

                                    <div v-if="assessment?.assessable" class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <h4 class="font-medium mb-2">Attached To</h4>
                                            <div class="flex items-center gap-2">
                                                <FileCheck class="h-4 w-4 text-muted-foreground" />
                                                <span>{{ assessment.assessable.title }}</span>
                                            </div>
                                            <p class="text-sm text-muted-foreground capitalize">
                                                {{ assessment.assessable_type?.replace('App\\Models\\', '').toLowerCase() }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <h4 class="font-medium mb-2">Created</h4>
                                            <div class="flex items-center gap-2 text-muted-foreground">
                                                <Calendar class="h-4 w-4" />
                                                <span>{{ formatDate(assessment?.created_at || '') }}</span>
                                            </div>
                                        </div>

                                        <div>
                                            <h4 class="font-medium mb-2">Last Updated</h4>
                                            <div class="flex items-center gap-2 text-muted-foreground">
                                                <Calendar class="h-4 w-4" />
                                                <span>{{ formatDate(assessment?.updated_at || '') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Settings</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Passing Score</span>
                                        <Badge variant="default">{{ assessment?.passing_score || 0 }}%</Badge>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Max Attempts</span>
                                        <Badge variant="secondary">{{ assessment?.max_attempts || 0 }}</Badge>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Required</span>
                                        <Badge :variant="assessment?.is_required ? 'destructive' : 'secondary'">
                                            {{ assessment?.is_required ? 'Yes' : 'No' }}
                                        </Badge>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Time Limit</span>
                                        <Badge variant="outline">{{ formatDuration(assessment?.time_limit) }}</Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="questions" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium">Assessment Questions</h3>
                        <Badge variant="outline">{{ assessment?.questions_count || 0 }} questions</Badge>
                    </div>

                    <div v-if="assessment?.questions && assessment.questions.length > 0" class="space-y-4">
                        <Card v-for="(question, index) in assessment.questions" :key="question.id">
                            <CardContent class="p-6">
                                <div class="space-y-4">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="font-mono text-sm bg-muted px-2 py-1 rounded">
                                                    Q{{ index + 1 }}
                                                </span>
                                                <Badge :class="getQuestionTypeColor(question.question_type)" class="text-xs capitalize">
                                                    {{ question.question_type.replace('_', ' ') }}
                                                </Badge>
                                                <Badge variant="outline" class="text-xs">
                                                    {{ question.points }} {{ question.points === 1 ? 'point' : 'points' }}
                                                </Badge>
                                            </div>
                                            <h4 class="font-medium mb-3">{{ question.question_text }}</h4>
                                        </div>
                                    </div>

                                    <!-- Question Options -->
                                    <div class="space-y-2">
                                        <div
                                            v-for="option in question.options"
                                            :key="option.id"
                                            class="flex items-center gap-3 p-3 border rounded-lg"
                                            :class="option.is_correct ? 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800' : ''"
                                        >
                                            <div class="flex-shrink-0">
                                                <CheckCircle v-if="option.is_correct" class="h-4 w-4 text-green-600" />
                                                <XCircle v-else class="h-4 w-4 text-gray-400" />
                                            </div>
                                            <span :class="option.is_correct ? 'font-medium text-green-800 dark:text-green-200' : ''">
                                                {{ option.option_text }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Explanation -->
                                    <div v-if="question.explanation" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                                        <h5 class="font-medium text-blue-800 dark:text-blue-200 mb-1">Explanation:</h5>
                                        <p class="text-sm text-blue-700 dark:text-blue-300">{{ question.explanation }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="text-center py-8">
                        <FileCheck class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-medium">No questions yet</h3>
                        <p class="text-muted-foreground">Add questions to this assessment to get started.</p>
                    </div>
                </TabsContent>

                <TabsContent value="attempts" class="space-y-4">
                    <h3 class="text-lg font-medium">Student Attempts</h3>

                    <div v-if="assessment?.attempts && assessment.attempts.length > 0" class="space-y-4">
                        <Card v-for="attempt in assessment.attempts" :key="attempt.id">
                            <CardContent class="p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">{{ attempt.user.name }}</h4>
                                        <p class="text-sm text-muted-foreground">{{ attempt.user.email }}</p>
                                        <div class="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
                                            <span>Started: {{ formatDate(attempt.started_at) }}</span>
                                            <span v-if="attempt.completed_at">
                                                Completed: {{ formatDate(attempt.completed_at) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-medium">{{ attempt.percentage }}%</div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ attempt.score }}/{{ attempt.total_points }} points
                                        </div>
                                        <Badge :variant="attempt.passed ? 'default' : 'destructive'" class="mt-1">
                                            {{ attempt.passed ? 'Passed' : 'Failed' }}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="text-center py-8">
                        <Users class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-medium">No attempts yet</h3>
                        <p class="text-muted-foreground">Student attempts will appear here once they start taking the assessment.</p>
                    </div>
                </TabsContent>

                <TabsContent value="analytics" class="space-y-4">
                    <h3 class="text-lg font-medium">Assessment Analytics</h3>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Target class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Pass Rate</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ getPassingRate() }}%</div>
                                    <p class="text-xs text-muted-foreground">
                                        Students passing the assessment
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Average Score</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ getAverageScore() }}%</div>
                                    <p class="text-xs text-muted-foreground">
                                        Across all attempts
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <FileCheck class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Completion Rate</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">
                                        {{ assessment?.attempts_count && assessment.attempts_count > 0 ? Math.round((assessment.attempts.filter((a: any) => a.completed_at).length / assessment.attempts_count) * 100) : 0 }}%
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        Attempts completed
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
