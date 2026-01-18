<script setup lang="ts">
import AlertError from '@/components/AlertError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import QuestionEditor from './QuestionEditor.vue';

interface Question {
    id?: number;
    question_text: string;
    question_type:
        | 'multiple_choice'
        | 'true_false'
        | 'code_output'
        | 'conceptual';
    points: number;
    order_index: number;
    explanation: string;
    options: Array<{
        id?: number;
        option_text: string;
        is_correct: boolean;
        order_index: number;
    }>;
}

interface Assessment {
    id?: number;
    assessable_type: string;
    assessable_id: number;
    title: string;
    description: string;
    passing_score: number;
    max_attempts: number;
    time_limit: number | null;
    is_required: boolean;
    questions?: Question[];
}

interface Module {
    id: number;
    title: string;
    level: {
        id: number;
        title: string;
        track: {
            id: number;
            title: string;
            slug: string;
        };
    };
}

interface Props {
    assessment?: Assessment;
    module: Module;
}

const props = defineProps<Props>();

const isEditing = computed(() => !!props.assessment?.id);

const form = useForm({
    module_id: props.module?.id,
    title: props.assessment?.title || '',
    description: props.assessment?.description || '',
    passing_score: props.assessment?.passing_score || 70,
    max_attempts: props.assessment?.max_attempts || 3,
    time_limit: props.assessment?.time_limit || null,
    is_required: props.assessment?.is_required || false,
    questions: props.assessment?.questions || [],
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Assessments', href: '/admin/classroom/assessments' },
    {
        title: isEditing.value ? 'Edit Assessment' : 'Create Assessment',
        href: '#',
    },
];

const addQuestion = () => {
    const newQuestion: Question = {
        question_text: '',
        question_type: 'multiple_choice',
        points: 1,
        order_index: form.questions.length + 1,
        explanation: '',
        options: [
            { option_text: '', is_correct: true, order_index: 1 },
            { option_text: '', is_correct: false, order_index: 2 },
        ],
    };
    form.questions.push(newQuestion);
};

const removeQuestion = (index: number) => {
    if (confirm('Are you sure you want to remove this question?')) {
        form.questions.splice(index, 1);
        // Reorder remaining questions
        form.questions.forEach((question, i) => {
            question.order_index = i + 1;
        });
    }
};

const moveQuestion = (index: number, direction: 'up' | 'down') => {
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex >= 0 && newIndex < form.questions.length) {
        const temp = form.questions[index];
        form.questions[index] = form.questions[newIndex];
        form.questions[newIndex] = temp;

        // Update order indices
        form.questions[index].order_index = index + 1;
        form.questions[newIndex].order_index = newIndex + 1;
    }
};

const submit = () => {
    // Client-side validation for required fields
    if (!form.module_id) {
        alert('Module is required');
        return;
    }

    if (!form.title.trim()) {
        alert('Title is required');
        return;
    }

    if (!form.description.trim()) {
        alert('Description is required');
        return;
    }

    if (!form.passing_score || form.passing_score < 0 || form.passing_score > 100) {
        alert('Passing score must be between 0 and 100');
        return;
    }

    if (form.questions.length === 0) {
        alert('At least one question is required');
        return;
    }

    if (isEditing.value) {
        form.put(`/admin/classroom/assessments/${props.assessment!.id}`, {
            preserveScroll: true,
        });
    } else {
        form.post('/admin/classroom/assessments', {
            preserveScroll: true,
        });
    }
};

const cancel = () => {
    router.visit('/admin/classroom/assessments');
};

const totalPoints = computed(() => {
    return form.questions.reduce((sum, question) => sum + question.points, 0);
});
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Assessment - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Assessment
                </h1>
                <p class="text-muted-foreground">
                    {{
                        isEditing
                            ? 'Update assessment'
                            : 'Create a new assessment for'
                    }}
                    "{{ module?.title }}"
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <AlertError v-if="form.hasErrors" :errors="form.errors" />

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Basic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Assessment Details</CardTitle>
                                <CardDescription>
                                    Configure the basic assessment information
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="module_id">Module *</Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{ module?.title }} ({{ module?.level?.title }} - {{ module?.level?.track?.title }})
                                    </p>
                                    <input type="hidden" v-model="form.module_id" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="title">Title *</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="Enter assessment title"
                                        required
                                        :class="{
                                            'border-destructive':
                                                form.errors.title,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.title"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.title }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description *</Label>
                                    <Textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Describe what this assessment covers"
                                        rows="3"
                                        required
                                        :class="{
                                            'border-destructive':
                                                form.errors.description,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.description"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.description }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Questions -->
                        <Card>
                            <CardHeader>
                                <CardTitle
                                    class="flex items-center justify-between"
                                >
                                    Questions ({{ form.questions.length }})
                                    <div
                                        class="flex items-center gap-2 text-sm text-muted-foreground"
                                    >
                                        Total Points: {{ totalPoints }}
                                    </div>
                                </CardTitle>
                                <CardDescription>
                                    Add and configure questions for this
                                    assessment
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="addQuestion"
                                    class="w-full"
                                >
                                    <svg
                                        class="mr-2 h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 4v16m8-8H4"
                                        />
                                    </svg>
                                    Add Question
                                </Button>

                                <div
                                    v-if="form.questions.length === 0"
                                    class="py-8 text-center text-muted-foreground"
                                >
                                    No questions added yet. Click "Add Question"
                                    to get started.
                                </div>

                                <div v-else class="space-y-4">
                                    <QuestionEditor
                                        v-for="(
                                            question, index
                                        ) in form.questions"
                                        :key="index"
                                        v-model="form.questions[index]"
                                        :question-number="index + 1"
                                        :can-move-up="index > 0"
                                        :can-move-down="
                                            index < form.questions.length - 1
                                        "
                                        @move-up="moveQuestion(index, 'up')"
                                        @move-down="moveQuestion(index, 'down')"
                                        @remove="removeQuestion(index)"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Configuration -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Configuration</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="passing_score"
                                        >Passing Score (%) *</Label
                                    >
                                    <Input
                                        id="passing_score"
                                        v-model.number="form.passing_score"
                                        type="number"
                                        min="0"
                                        max="100"
                                        placeholder="70"
                                        required
                                        :class="{
                                            'border-destructive':
                                                form.errors.passing_score,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.passing_score"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.passing_score }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="max_attempts"
                                        >Max Attempts</Label
                                    >
                                    <Input
                                        id="max_attempts"
                                        v-model.number="form.max_attempts"
                                        type="number"
                                        min="1"
                                        placeholder="3"
                                        :class="{
                                            'border-destructive':
                                                form.errors.max_attempts,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.max_attempts"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.max_attempts }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="time_limit"
                                        >Time Limit (minutes)</Label
                                    >
                                    <Input
                                        id="time_limit"
                                        v-model.number="form.time_limit"
                                        type="number"
                                        min="0"
                                        placeholder="30"
                                        :class="{
                                            'border-destructive':
                                                form.errors.time_limit,
                                        }"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Leave empty for no time limit
                                    </p>
                                    <p
                                        v-if="form.errors.time_limit"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.time_limit }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch
                                        id="is_required"
                                        v-model="form.is_required"
                                    />
                                    <Label for="is_required"
                                        >Required Assessment</Label
                                    >
                                </div>
                                <p
                                    v-if="form.is_required"
                                    class="text-xs text-muted-foreground"
                                >
                                    Learners must pass this assessment to
                                    continue
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Assessment Info -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Assessment Info</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Type:</span
                                    >
                                    <span>Module Assessment</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Attached to:</span
                                    >
                                    <span>{{ module?.title }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Questions:</span
                                    >
                                    <span>{{ form.questions.length }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Total Points:</span
                                    >
                                    <span>{{ totalPoints }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Actions -->
                        <Card>
                            <CardContent class="pt-6">
                                <div class="flex flex-col gap-2">
                                    <Button
                                        type="submit"
                                        :disabled="
                                            form.processing ||
                                            form.questions.length === 0
                                        "
                                        class="w-full"
                                    >
                                        {{
                                            form.processing
                                                ? 'Saving...'
                                                : isEditing
                                                  ? 'Update Assessment'
                                                  : 'Create Assessment'
                                        }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="cancel"
                                        class="w-full"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
