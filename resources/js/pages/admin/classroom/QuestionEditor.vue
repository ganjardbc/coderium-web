<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CustomSelect from '@/components/CustomSelect.vue';
import { Textarea } from '@/components/ui/textarea';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface QuestionOption {
    id?: number;
    option_text: string;
    is_correct: boolean;
    order_index: number;
}

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
    options: QuestionOption[];
}

interface Props {
    modelValue: Question;
    questionNumber: number;
    canMoveUp: boolean;
    canMoveDown: boolean;
}

interface Emits {
    (e: 'update:modelValue', value: Question): void;
    (e: 'moveUp'): void;
    (e: 'moveDown'): void;
    (e: 'remove'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const questionTypeOptions = [
    { value: 'multiple_choice', label: 'Multiple Choice' },
    { value: 'true_false', label: 'True/False' },
    { value: 'code_output', label: 'Code Output' },
    { value: 'conceptual', label: 'Conceptual' },
];

const question = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const updateQuestion = (field: keyof Question, value: any) => {
    question.value = { ...question.value, [field]: value };
};

const updateOption = (
    index: number,
    field: keyof QuestionOption,
    value: any,
) => {
    const options = [...question.value.options];
    options[index] = { ...options[index], [field]: value };
    updateQuestion('options', options);
};

const addOption = () => {
    const options = [...question.value.options];
    options.push({
        option_text: '',
        is_correct: false,
        order_index: options.length + 1,
    });
    updateQuestion('options', options);
};

const removeOption = (index: number) => {
    if (question.value.options.length <= 2) {
        alert('A question must have at least 2 options');
        return;
    }

    const options = [...question.value.options];
    options.splice(index, 1);

    // Reorder remaining options
    options.forEach((option, i) => {
        option.order_index = i + 1;
    });

    updateQuestion('options', options);
};

const onTypeChange = (newType: string) => {
    updateQuestion('question_type', newType);

    // Reset options based on question type
    if (newType === 'true_false') {
        updateQuestion('options', [
            { option_text: 'True', is_correct: true, order_index: 1 },
            { option_text: 'False', is_correct: false, order_index: 2 },
        ]);
    } else if (
        newType === 'multiple_choice' &&
        question.value.options.length < 2
    ) {
        updateQuestion('options', [
            { option_text: '', is_correct: true, order_index: 1 },
            { option_text: '', is_correct: false, order_index: 2 },
        ]);
    } else if (newType === 'code_output' || newType === 'conceptual') {
        // These types don't need predefined options
        updateQuestion('options', []);
    }
};

const setCorrectAnswer = (index: number) => {
    const options = [...question.value.options];
    options.forEach((option, i) => {
        option.is_correct = i === index;
    });
    updateQuestion('options', options);
};

const questionTypeLabel = computed(() => {
    switch (question.value.question_type) {
        case 'multiple_choice':
            return 'Multiple Choice';
        case 'true_false':
            return 'True/False';
        case 'code_output':
            return 'Code Output';
        case 'conceptual':
            return 'Conceptual';
        default:
            return 'Unknown';
    }
});

const showOptions = computed(() => {
    return ['multiple_choice', 'true_false'].includes(
        question.value.question_type,
    );
});
</script>

<template>
    <Card class="relative">
        <CardHeader class="pb-4">
            <CardTitle class="flex items-center justify-between text-lg">
                <div class="flex items-center gap-2">
                    <span
                        class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
                    >
                        {{ questionNumber }}
                    </span>
                    <span>{{ questionTypeLabel }}</span>
                    <span class="text-sm font-normal text-muted-foreground">
                        ({{ question.points }}
                        {{ question.points === 1 ? 'point' : 'points' }})
                    </span>
                </div>

                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="!canMoveUp"
                        @click="$emit('moveUp')"
                        title="Move up"
                    >
                        <ChevronUp class="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="!canMoveDown"
                        @click="$emit('moveDown')"
                        title="Move down"
                    >
                        <ChevronDown class="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="$emit('remove')"
                        title="Remove question"
                        class="text-destructive hover:text-destructive"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <!-- Question Configuration -->
            <div class="grid grid-cols-2 gap-4">
                <CustomSelect
                    label="Question Type"
                    :model-value="question.question_type"
                    :options="questionTypeOptions"
                    @update:modelValue="onTypeChange"
                />

                <div class="space-y-2">
                    <Label>Points</Label>
                    <Input
                        type="number"
                        min="1"
                        :model-value="question.points"
                        @update:model-value="
                            (value) =>
                                updateQuestion('points', parseInt(value) || 1)
                        "
                    />
                </div>
            </div>

            <!-- Question Text -->
            <div class="space-y-2">
                <Label>Question *</Label>
                <Textarea
                    :model-value="question.question_text"
                    @update:model-value="
                        (value) => updateQuestion('question_text', value)
                    "
                    placeholder="Enter your question here..."
                    rows="3"
                />
            </div>

            <!-- Options (for multiple choice and true/false) -->
            <div v-if="showOptions" class="space-y-4">
                <div class="flex items-center justify-between">
                    <Label>Answer Options</Label>
                    <Button
                        v-if="question.question_type === 'multiple_choice'"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addOption"
                    >
                        <Plus class="mr-1 h-3 w-3" />
                        Add Option
                    </Button>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(option, index) in question.options"
                        :key="index"
                        class="flex items-center gap-3 rounded-lg border p-3"
                        :class="{
                            'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950':
                                option.is_correct,
                        }"
                    >
                        <div class="flex items-center gap-2">
                            <input
                                type="radio"
                                :name="`question-${questionNumber}-correct`"
                                :checked="option.is_correct"
                                @change="setCorrectAnswer(index)"
                                class="h-4 w-4"
                            />
                            <span class="text-sm font-medium">
                                {{ String.fromCharCode(65 + index) }}
                            </span>
                        </div>

                        <Input
                            v-if="question.question_type !== 'true_false'"
                            :model-value="option.option_text"
                            @update:model-value="
                                (value) =>
                                    updateOption(index, 'option_text', value)
                            "
                            placeholder="Enter option text"
                            class="flex-1"
                        />
                        <span v-else class="flex-1 py-2">{{
                            option.option_text
                        }}</span>

                        <Button
                            v-if="
                                question.question_type === 'multiple_choice' &&
                                question.options.length > 2
                            "
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="removeOption(index)"
                            class="text-destructive hover:text-destructive"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Code Output Instructions -->
            <div
                v-if="question.question_type === 'code_output'"
                class="rounded-lg bg-muted p-4"
            >
                <p class="text-sm text-muted-foreground">
                    For code output questions, provide a code snippet in your
                    question text and ask learners to predict the output. The
                    correct answer will be entered as free text during
                    assessment attempts.
                </p>
            </div>

            <!-- Conceptual Instructions -->
            <div
                v-if="question.question_type === 'conceptual'"
                class="rounded-lg bg-muted p-4"
            >
                <p class="text-sm text-muted-foreground">
                    Conceptual questions allow for open-ended responses. These
                    will need to be manually graded by instructors.
                </p>
            </div>

            <!-- Explanation -->
            <div class="space-y-2">
                <Label>Explanation (Optional)</Label>
                <Textarea
                    :model-value="question.explanation"
                    @update:model-value="
                        (value) => updateQuestion('explanation', value)
                    "
                    placeholder="Provide an explanation for the correct answer..."
                    rows="2"
                />
                <p class="text-xs text-muted-foreground">
                    This explanation will be shown to learners after they submit
                    their answer
                </p>
            </div>
        </CardContent>
    </Card>
</template>
