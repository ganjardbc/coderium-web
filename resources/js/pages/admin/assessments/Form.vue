<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CustomSelect from '@/components/CustomSelect.vue';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Trash2 } from 'lucide-vue-next';

interface Module {
    id: number;
    title: string;
}

interface QuestionOption {
    id?: number;
    option_text: string;
    is_correct: boolean;
    order_index: number;
}

interface Question {
    id?: number;
    question_text: string;
    question_type: 'multiple_choice' | 'true_false' | 'code_output' | 'conceptual';
    points: number;
    order_index: number;
    explanation: string | null;
    options: QuestionOption[];
}

interface Assessment {
    id: number;
    title: string;
    description: string;
    assessable_type: string | null;
    assessable_id: number | null;
    time_limit: number | null;
    passing_score: number;
    max_attempts: number;
    is_required: boolean;
    questions: Question[];
}

interface Props {
    assessment?: Assessment;
    modules: Module[];
    selectedModuleId?: number;
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

// Handle both raw assessment data and resource-wrapped data
const assessmentData = computed(() => {
    if (!props.assessment) return null;
    // If the assessment has a 'data' property, it's wrapped in a resource
    return (props.assessment as any).data || props.assessment;
});

const form = ref({
    module_id: assessmentData.value?.assessable_id || props.selectedModuleId || '',
    title: assessmentData.value?.title || '',
    description: assessmentData.value?.description || '',
    time_limit: assessmentData.value?.time_limit || '',
    passing_score: assessmentData.value?.passing_score || 70,
    max_attempts: assessmentData.value?.max_attempts || 3,
    is_required: assessmentData.value?.is_required || false,
    questions: assessmentData.value?.questions?.map((q: Question) => ({
        id: q.id,
        question_text: q.question_text,
        question_type: q.question_type,
        points: q.points,
        order_index: q.order_index,
        explanation: q.explanation || '',
        options: q.options.map((o: QuestionOption) => ({
            id: o.id,
            option_text: o.option_text,
            is_correct: o.is_correct,
            order_index: o.order_index,
        })),
    })) || [],
});

const isEditing = !!assessmentData.value;

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Assessments', href: '/admin/assessments' },
    { title: isEditing ? 'Edit' : 'Create', href: '#' },
];

const submit = () => {
    const data = {
        module_id: form.value.module_id === '' ? null : form.value.module_id,
        title: form.value.title,
        description: form.value.description,
        time_limit: form.value.time_limit === '' ? null : form.value.time_limit,
        passing_score: form.value.passing_score,
        max_attempts: form.value.max_attempts,
        is_required: form.value.is_required,
        questions: form.value.questions,
    };

    if (isEditing) {
        router.put(`/admin/assessments/${assessmentData.value?.id}`, data, {
            preserveScroll: true,
        });
    } else {
        router.post('/admin/assessments', data, {
            preserveScroll: true,
        });
    }
};

const cancel = () => {
    window.history.back();
};

const moduleOptions = computed(() => [
    { value: '', label: 'No module (standalone assessment)' },
    ...props.modules.map(module => ({
        value: module.id,
        label: module.title
    }))
]);

const questionTypeOptions = [
    { value: 'multiple_choice', label: 'Multiple Choice' },
    { value: 'true_false', label: 'True/False' },
    { value: 'code_output', label: 'Code Output' },
    { value: 'conceptual', label: 'Conceptual' },
];

const timeLimitOptions = [
    { value: '', label: 'No time limit' },
    { value: 15, label: '15 minutes' },
    { value: 30, label: '30 minutes' },
    { value: 45, label: '45 minutes' },
    { value: 60, label: '1 hour' },
    { value: 90, label: '1.5 hours' },
    { value: 120, label: '2 hours' },
];

const addQuestion = () => {
    form.value.questions.push({
        id: undefined,
        question_text: '',
        question_type: 'multiple_choice',
        points: 1,
        order_index: form.value.questions.length + 1,
        explanation: '',
        options: [
            { id: undefined, option_text: '', is_correct: true, order_index: 1 },
            { id: undefined, option_text: '', is_correct: false, order_index: 2 },
        ],
    });
};

const removeQuestion = (index: number) => {
    form.value.questions.splice(index, 1);
    // Reorder remaining questions
    form.value.questions.forEach((question: any, i: number) => {
        question.order_index = i + 1;
    });
};

const addOption = (questionIndex: number) => {
    const question = form.value.questions[questionIndex];
    question.options.push({
        id: undefined,
        option_text: '',
        is_correct: false,
        order_index: question.options.length + 1,
    });
};

const removeOption = (questionIndex: number, optionIndex: number) => {
    const question = form.value.questions[questionIndex];
    question.options.splice(optionIndex, 1);
    // Reorder remaining options
    question.options.forEach((option: any, i: number) => {
        option.order_index = i + 1;
    });
};

const setCorrectOption = (questionIndex: number, optionIndex: number) => {
    const question = form.value.questions[questionIndex];
    question.options.forEach((option: any, i: number) => {
        option.is_correct = i === optionIndex;
    });
};
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'Create'} Assessment - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    {{ isEditing ? 'Edit' : 'Create' }} Assessment
                </h1>
                <p class="text-muted-foreground">
                    {{ isEditing ? 'Update assessment details and questions' : 'Create a new assessment or quiz' }}
                </p>
            </div>

            <div class="w-full">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 rounded-lg border bg-card p-6"
                >
                    <!-- Title -->
                    <div class="space-y-2">
                        <Label for="title">Title *</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            type="text"
                            placeholder="Enter assessment title"
                            required
                        />
                        <p
                            v-if="errors?.title"
                            class="text-sm text-destructive"
                        >
                            {{ errors.title }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description *</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Describe what this assessment covers"
                            rows="3"
                            required
                        />
                        <p
                            v-if="errors?.description"
                            class="text-sm text-destructive"
                        >
                            {{ errors.description }}
                        </p>
                    </div>

                    <!-- Module -->
                    <div class="space-y-2">
                        <Label for="module_id">Module</Label>
                        <CustomSelect
                            id="module_id"
                            v-model="form.module_id"
                            :options="moduleOptions"
                            placeholder="Select a module"
                            :error="!!errors?.module_id"
                        />
                        <p class="text-sm text-muted-foreground">
                            Associate this assessment with a specific module or leave as standalone
                        </p>
                        <p
                            v-if="errors?.module_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.module_id }}
                        </p>
                    </div>

                    <!-- Assessment Settings -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Time Limit -->
                        <div class="space-y-2">
                            <Label for="time_limit">Time Limit</Label>
                            <CustomSelect
                                id="time_limit"
                                v-model="form.time_limit"
                                :options="timeLimitOptions"
                                placeholder="Select time limit"
                                :error="!!errors?.time_limit"
                            />
                            <p
                                v-if="errors?.time_limit"
                                class="text-sm text-destructive"
                            >
                                {{ errors.time_limit }}
                            </p>
                        </div>

                        <!-- Passing Score -->
                        <div class="space-y-2">
                            <Label for="passing_score">Passing Score (%)</Label>
                            <Input
                                id="passing_score"
                                v-model.number="form.passing_score"
                                type="number"
                                min="0"
                                max="100"
                                placeholder="70"
                            />
                            <p
                                v-if="errors?.passing_score"
                                class="text-sm text-destructive"
                            >
                                {{ errors.passing_score }}
                            </p>
                        </div>
                    </div>

                    <!-- Max Attempts -->
                    <div class="space-y-2">
                        <Label for="max_attempts">Max Attempts</Label>
                        <Input
                            id="max_attempts"
                            v-model.number="form.max_attempts"
                            type="number"
                            min="1"
                            placeholder="3"
                            class="max-w-xs"
                        />
                        <p class="text-sm text-muted-foreground">
                            Maximum number of attempts allowed for this assessment
                        </p>
                        <p
                            v-if="errors?.max_attempts"
                            class="text-sm text-destructive"
                        >
                            {{ errors.max_attempts }}
                        </p>
                    </div>

                    <!-- Required Assessment -->
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <Label>Required Assessment</Label>
                            <p class="text-sm text-muted-foreground">
                                Students must pass this assessment to progress
                            </p>
                        </div>
                        <Switch v-model="form.is_required" />
                    </div>

                    <!-- Questions Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <Label class="text-lg font-semibold">Questions</Label>
                                <p class="text-sm text-muted-foreground">
                                    {{ isEditing ? 'Update questions for your assessment' : 'Add questions to your assessment' }}
                                </p>
                            </div>
                            <Button type="button" @click="addQuestion" size="sm">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Question
                            </Button>
                        </div>

                        <div v-if="form.questions.length === 0" class="text-center py-8 text-muted-foreground border rounded-lg">
                            No questions added yet. Click "Add Question" to get started.
                        </div>

                        <div v-else class="space-y-6">
                            <div
                                v-for="(question, questionIndex) in form.questions"
                                :key="questionIndex"
                                class="border rounded-lg p-4 space-y-4 bg-muted/30"
                            >
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium">Question {{ (questionIndex as number) + 1 }}</h4>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="removeQuestion(questionIndex as number)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>Question Text *</Label>
                                        <Textarea
                                            v-model="question.question_text"
                                            placeholder="Enter your question"
                                            rows="3"
                                            required
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Question Type</Label>
                                        <CustomSelect
                                            v-model="question.question_type"
                                            :options="questionTypeOptions"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>Points</Label>
                                        <Input
                                            v-model.number="question.points"
                                            type="number"
                                            min="1"
                                            placeholder="1"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Explanation (Optional)</Label>
                                        <Input
                                            v-model="question.explanation"
                                            placeholder="Explain the correct answer"
                                        />
                                    </div>
                                </div>

                                <!-- Options -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <Label>Answer Options</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="addOption(questionIndex as number)"
                                        >
                                            <Plus class="mr-2 h-4 w-4" />
                                            Add Option
                                        </Button>
                                    </div>

                                    <div class="space-y-2">
                                        <div
                                            v-for="(option, optionIndex) in question.options"
                                            :key="optionIndex"
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                type="radio"
                                                :name="`question_${questionIndex}_correct`"
                                                :checked="option.is_correct"
                                                @change="setCorrectOption(questionIndex as number, optionIndex as number)"
                                                class="flex-shrink-0"
                                            />
                                            <Input
                                                v-model="option.option_text"
                                                placeholder="Enter option text"
                                                class="flex-1"
                                                required
                                            />
                                            <Button
                                                v-if="question.options.length > 2"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="removeOption(questionIndex as number, optionIndex as number)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="cancel">
                            Cancel
                        </Button>
                        <Button type="submit">
                            {{ isEditing ? 'Update' : 'Create' }} Assessment
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
