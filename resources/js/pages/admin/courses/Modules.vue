<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';

import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, Clock, Save, GripVertical, Plus, CheckCircle, AlertCircle, Loader2, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Module {
    id: number;
    title: string;
    description: string;
    lessons_count: number;
    estimated_duration: number;
    is_published: boolean;
    level?: {
        id: number;
        title: string;
        track: {
            id: number;
            title: string;
        };
    };
}

interface AssignedModule {
    id: number;
    title: string;
    description: string;
    order: number;
    is_required: boolean;
    lessons_count: number;
    estimated_duration: number;
}

interface Course {
    id: number;
    title: string;
    description: string;
    modules: AssignedModule[];
}

interface Props {
    course: Course;
    availableModules: Module[];
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

console.log('props', props.course);

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Courses', href: '/admin/courses' },
    { title: props.course.title, href: `/admin/courses/${props.course.id}` },
    { title: 'Manage Modules', href: `/admin/courses/${props.course.id}/modules` },
];

// State for managing module assignments
const currentModules = ref<AssignedModule[]>([...props.course.modules]);
const isLoading = ref(false);
const successMessage = ref<string>('');
const hasChanges = ref(false);

// Computed properties
const totalDuration = computed(() => {
    return currentModules.value.reduce((total, module) => total + module.estimated_duration, 0);
});

const requiredModulesCount = computed(() => {
    return currentModules.value.filter(module => module.is_required).length;
});

const formatDuration = (minutes: number) => {
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

// Add module to current list
const addModule = (module: Module) => {
    const newModule: AssignedModule = {
        id: module.id,
        title: module.title,
        description: module.description,
        order: currentModules.value.length + 1,
        is_required: true,
        lessons_count: module.lessons_count,
        estimated_duration: module.estimated_duration,
    };

    currentModules.value.push(newModule);
    hasChanges.value = true;
};

// Remove module from current list
const removeModule = (moduleId: number) => {
    const index = currentModules.value.findIndex(m => m.id === moduleId);
    if (index > -1) {
        currentModules.value.splice(index, 1);
        // Reorder remaining modules
        currentModules.value.forEach((module, idx) => {
            module.order = idx + 1;
        });
        hasChanges.value = true;
    }
};

// Toggle required status
const toggleRequired = (moduleId: number, isRequired: boolean) => {
    const module = currentModules.value.find(m => m.id === moduleId);
    if (module) {
        module.is_required = isRequired;
        hasChanges.value = true;
    }
};

// Save changes
const saveChanges = () => {
    isLoading.value = true;

    // Ensure all modules have required fields with proper types
    const modulesData = currentModules.value.map((module, index) => {
        const moduleData = {
            id: Number(module.id),
            order: Number(module.order) || (index + 1), // Ensure it's a number and has a default
            is_required: Boolean(module.is_required), // Ensure it's a boolean
        };

        return moduleData;
    });

    router.put(`/admin/courses/${props.course.id}/modules/bulk`, {
        modules: modulesData
    }, {
        preserveScroll: true,
        onSuccess: () => {
            hasChanges.value = false;
            successMessage.value = 'Course modules updated successfully!';
            setTimeout(() => {
                successMessage.value = '';
            }, 4000);
        },
        onError: (errors) => {
            console.error('Error updating modules:', errors);
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

// Cancel changes
const cancelChanges = () => {
    currentModules.value = [...props.course.modules];
    hasChanges.value = false;
};

// Check if module is already added
const isModuleAdded = (moduleId: number) => {
    return currentModules.value.some(m => m.id === moduleId);
};

const columns: Column<AssignedModule>[] = [
    { key: 'order', label: 'Order', align: 'left' },
    { key: 'title', label: 'Module', align: 'left' },
    { key: 'lessons_count', label: 'Lessons', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'is_required', label: 'Required', align: 'left' },
];

const availableColumns: Column<Module>[] = [
    { key: 'title', label: 'Module', align: 'left' },
    { key: 'level', label: 'Track/Level', align: 'left' },
    { key: 'lessons_count', label: 'Lessons', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
];
</script>

<template>
    <Head :title="`Manage Modules - ${course.title} - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <!-- Success Message -->
            <Alert v-if="successMessage" class="mb-6 border-green-200 bg-green-50 text-green-800">
                <CheckCircle class="h-4 w-4" />
                <AlertDescription>{{ successMessage }}</AlertDescription>
            </Alert>

            <!-- Error Messages -->
            <Alert v-if="errors && Object.keys(errors).length > 0" class="mb-6 border-red-200 bg-red-50 text-red-800">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    <div v-for="(error, field) in errors" :key="field">
                        {{ error }}
                    </div>
                </AlertDescription>
            </Alert>

            <div class="mb-6">
                <h1 class="text-3xl font-bold">Manage Course Modules</h1>
                <p class="text-muted-foreground">
                    Add and organize modules for {{ course.title }}
                </p>
            </div>

            <!-- Course Statistics -->
            <div class="mb-6 grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent>
                        <div class="flex items-center space-x-2">
                            <BookOpen class="h-5 w-5 text-blue-500" />
                            <div>
                                <p class="text-sm font-medium">Total Modules</p>
                                <p class="text-2xl font-bold">{{ currentModules.length }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <div class="flex items-center space-x-2">
                            <CheckCircle class="h-5 w-5 text-green-500" />
                            <div>
                                <p class="text-sm font-medium">Required</p>
                                <p class="text-2xl font-bold">{{ requiredModulesCount }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <div class="flex items-center space-x-2">
                            <Clock class="h-5 w-5 text-orange-500" />
                            <div>
                                <p class="text-sm font-medium">Total Duration</p>
                                <p class="text-2xl font-bold">{{ formatDuration(totalDuration) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <div class="flex items-center space-x-2">
                            <Plus class="h-5 w-5 text-purple-500" />
                            <div>
                                <p class="text-sm font-medium">Available</p>
                                <p class="text-2xl font-bold">{{ availableModules.length }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <!-- Available Modules Grid -->
                <Card>
                    <CardHeader>
                        <CardTitle>Available Modules ({{ availableModules.length }})</CardTitle>
                        <CardDescription>
                            Published modules that can be added to this course
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            :data="availableModules"
                            :columns="availableColumns"
                            empty-message="No available modules"
                            empty-description="All published modules are already assigned to this course."
                        >
                            <template #cell-title="{ row }">
                                <div>
                                    <div class="font-medium">{{ row.title }}</div>
                                    <div class="text-sm text-muted-foreground line-clamp-1">
                                        {{ row.description }}
                                    </div>
                                </div>
                            </template>

                            <template #cell-level="{ row }">
                                <div v-if="row.level" class="text-sm">
                                    <div class="font-medium">{{ row.level.track.title }}</div>
                                    <div class="text-muted-foreground">{{ row.level.title }}</div>
                                </div>
                                <div v-else class="text-sm text-muted-foreground">
                                    Standalone module
                                </div>
                            </template>

                            <template #cell-lessons_count="{ row }">
                                <div class="flex items-center gap-1">
                                    <BookOpen class="h-4 w-4" />
                                    {{ row.lessons_count }}
                                </div>
                            </template>

                            <template #cell-estimated_duration="{ row }">
                                <div class="min-w-[92px] flex items-center gap-1">
                                    <Clock class="h-4 w-4" />
                                    {{ formatDuration(row.estimated_duration) }}
                                </div>
                            </template>

                            <template #actions="{ row }">
                                <div class="flex gap-2 justify-end">
                                    <Button
                                        @click="addModule(row)"
                                        :disabled="isModuleAdded(row.id)"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Add
                                    </Button>
                                </div>
                            </template>
                        </DataTable>
                    </CardContent>
                </Card>

                <!-- Current Course Modules Grid -->
                <Card>
                    <CardHeader>
                        <CardTitle>Course Modules ({{ currentModules.length }})</CardTitle>
                        <CardDescription>
                            Modules currently assigned to this course
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            :data="currentModules"
                            :columns="columns"
                            empty-message="No modules assigned"
                            empty-description="Add modules from the available list above to structure your course."
                        >
                            <template #cell-order="{ row }">
                                <div class="flex items-center gap-2">
                                    <GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
                                    <Badge variant="outline">{{ row.order }}</Badge>
                                </div>
                            </template>

                            <template #cell-title="{ row }">
                                <div>
                                    <div class="font-medium">{{ row.title }}</div>
                                    <div class="text-sm text-muted-foreground line-clamp-1">
                                        {{ row.description }}
                                    </div>
                                </div>
                            </template>

                            <template #cell-lessons_count="{ row }">
                                <div class="flex items-center gap-1">
                                    <BookOpen class="h-4 w-4" />
                                    {{ row.lessons_count }}
                                </div>
                            </template>

                            <template #cell-estimated_duration="{ row }">
                                <div class="min-w-[92px] flex items-center gap-1">
                                    <Clock class="h-4 w-4" />
                                    {{ formatDuration(row.estimated_duration) }}
                                </div>
                            </template>

                            <template #cell-is_required="{ row }">
                                <div class="flex items-center space-x-2">
                                    <Switch
                                        :checked="row.is_required"
                                        @update:checked="(checked) => toggleRequired(row.id, checked)"
                                    />
                                </div>
                            </template>

                            <!-- Actions -->
                            <template #actions="{ row }">
                                <div class="flex gap-2 justify-end">
                                    <Button
                                        variant="outline"
                                        @click="removeModule(row.id)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                            </template>
                        </DataTable>
                    </CardContent>
                </Card>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3 rounded-lg border bg-card p-6">
                    <Button
                        type="button"
                        variant="outline"
                        @click="cancelChanges"
                        :disabled="!hasChanges || isLoading"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="saveChanges"
                        :disabled="!hasChanges || isLoading"
                    >
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ isLoading ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
