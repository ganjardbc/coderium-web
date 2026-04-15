<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import DataTable, { type Column } from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Edit, BookOpen, Clock, Users, GraduationCap, CheckCircle, AlertCircle, Loader2, GripVertical, Eye, Trash2, SettingsIcon } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';

interface Pivot {
    course_id: number;
    module_id: number;
    order: number;
    is_required: boolean;
}

interface Module {
    id: number;
    title: string;
    description: string;
    order: number;
    is_required: boolean;
    lessons_count: number;
    estimated_duration: number;
    pivot: Pivot;
}

interface AvailableModule {
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

interface Course {
    id: number;
    title: string;
    description: string;
    slug: string;
    is_active: boolean;
    estimated_duration: number | null;
    modules_count: number;
    enrollments_count: number;
    certificate_template?: {
        id: number;
        name: string;
    };
    modules: Module[];
    created_at: string;
    updated_at: string;
}

interface Props {
    course: Course;
    availableModules?: AvailableModule[];
}

const props = defineProps<Props>();

// Tab state management with query parameters
const activeTab = ref<string>('overview');

// Get initial tab from URL query parameter
const getInitialTab = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const validTabs = ['overview', 'modules'];
    return validTabs.includes(tabParam || '') ? tabParam! : 'overview';
};

// Update URL when tab changes
const updateUrlTab = (tab: string) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
};

// Handle tab change
const handleTabChange = (tab: string) => {
    activeTab.value = tab;
    updateUrlTab(tab);
};

// Initialize tab from URL on mount
onMounted(() => {
    activeTab.value = getInitialTab();
});

// Reactive variables for module management
const isRemoving = ref<number | null>(null);
const isTogglingRequired = ref<number | null>(null);
const successMessage = ref<string>('');

// Form for assigning modules
const assignForm = useForm({
    module_id: null as number | null,
    order: 1,
    is_required: true,
});

// Computed properties
const requiredModulesCount = computed(() => {
    return props.course.modules.filter(module => module.is_required).length;
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Courses', href: '/admin/courses' },
    { title: props.course.title, href: `/admin/courses/${props.course.id}` },
];

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'Not set';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const toggleRequired = async (moduleId: number, isRequired: boolean) => {
    isTogglingRequired.value = moduleId;

    router.put(`/admin/courses/${props.course.id}/modules/${moduleId}/required`, {
        is_required: isRequired,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = `Module ${isRequired ? 'marked as required' : 'marked as optional'}!`;
            setTimeout(() => {
                successMessage.value = '';
            }, 3000);
        },
        onFinish: () => {
            isTogglingRequired.value = null;
        }
    });
};

const removeModule = (moduleId: number) => {
    if (confirm('Are you sure you want to remove this module from the course?')) {
        isRemoving.value = moduleId;

        router.delete(`/admin/courses/${props.course.id}/modules/${moduleId}`, {
            preserveScroll: true,
            onSuccess: () => {
                successMessage.value = 'Module removed successfully!';
                setTimeout(() => {
                    successMessage.value = '';
                }, 3000);
            },
            onFinish: () => {
                isRemoving.value = null;
            }
        });
    }
};

// Watch for form errors and clear success message
watch(() => assignForm.errors, (errors) => {
    if (Object.keys(errors).length > 0) {
        successMessage.value = '';
    }
});

const columns: Column<Module>[] = [
    { key: 'order', label: 'Order', align: 'left' },
    { key: 'title', label: 'Module', align: 'left' },
    { key: 'lessons_count', label: 'Lessons', align: 'left' },
    { key: 'estimated_duration', label: 'Duration', align: 'left' },
    { key: 'is_required', label: 'Required', align: 'left' },
];
</script>

<template>
    <Head :title="`${course.title} - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <!-- Success Message -->
            <Alert v-if="successMessage" class="mb-6 border-green-200 bg-green-50 text-green-800">
                <CheckCircle class="h-4 w-4" />
                <AlertDescription>{{ successMessage }}</AlertDescription>
            </Alert>

            <!-- Form Errors -->
            <Alert v-if="Object.keys(assignForm.errors).length > 0" class="mb-6 border-red-200 bg-red-50 text-red-800">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    <div v-for="(error, field) in assignForm.errors" :key="field">
                        {{ error }}
                    </div>
                </AlertDescription>
            </Alert>

            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ course.title }}</h1>
                        <Badge :variant="course.is_active ? 'default' : 'secondary'">
                            {{ course.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground">
                        Course details and module management
                    </p>
                </div>
                <Button as-child>
                    <Link :href="`/admin/courses/${course.id}/edit`">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Course
                    </Link>
                </Button>
            </div>

            <Tabs :model-value="activeTab" @update:model-value="handleTabChange" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="modules">Modules</TabsTrigger>
                </TabsList>

                <!-- Course Overview -->
                <TabsContent value="overview" class="space-y-4">
                    <!-- Overview Statistics -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <BookOpen class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Modules</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ course.modules_count }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ requiredModulesCount }} required
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Enrollments</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ course.enrollments_count }}</div>
                                    <p class="text-xs text-muted-foreground truncate">Active students</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Duration</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ formatDuration(course.estimated_duration) }}</div>
                                    <p class="text-xs text-muted-foreground truncate">Estimated time</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <GraduationCap class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Status</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ course.is_active ? 'Live' : 'Inactive' }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ course.is_active ? 'Available to students' : 'Not available' }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Course Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Course Information</CardTitle>
                            <CardDescription>
                                Details about this course
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h3 class="font-medium mb-2">Description</h3>
                                <p class="text-muted-foreground">{{ course.description }}</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <h3 class="font-medium mb-2">Course Slug</h3>
                                    <code class="text-sm bg-muted px-2 py-1 rounded">{{ course.slug }}</code>
                                </div>

                                <div v-if="course.certificate_template">
                                    <h3 class="font-medium mb-2">Certificate</h3>
                                    <div class="flex items-center gap-2">
                                        <GraduationCap class="h-4 w-4" />
                                        <span class="text-sm">{{ course.certificate_template.name }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <h3 class="font-medium mb-2">Created</h3>
                                    <p class="text-muted-foreground">
                                        {{ new Date(course.created_at).toLocaleDateString() }}
                                    </p>
                                </div>
                                <div>
                                    <h3 class="font-medium mb-2">Last Updated</h3>
                                    <p class="text-muted-foreground">
                                        {{ new Date(course.updated_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Module Status Breakdown -->
                    <div v-if="course.modules && course.modules.length > 0" class="grid gap-4 md:grid-cols-2">
                        <!-- Module Requirements -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Module Requirements</CardTitle>
                                <CardDescription>
                                    Required vs optional modules
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle class="h-4 w-4 text-red-600" />
                                            <span class="text-sm">Required</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold">{{ requiredModulesCount }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ course.modules_count > 0 ? Math.round((requiredModulesCount / course.modules_count) * 100) : 0 }}%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle class="h-4 w-4 text-gray-400" />
                                            <span class="text-sm">Optional</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold">{{ course.modules_count - requiredModulesCount }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ course.modules_count > 0 ? Math.round(((course.modules_count - requiredModulesCount) / course.modules_count) * 100) : 0 }}%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Total Lessons -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-lg">Content Summary</CardTitle>
                                <CardDescription>
                                    Total lessons across all modules
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <BookOpen class="h-4 w-4 text-muted-foreground" />
                                            <span class="text-sm">Total Lessons</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold">
                                                {{ course.modules.reduce((sum, module) => sum + module.lessons_count, 0) }}
                                            </div>
                                            <div class="text-xs text-muted-foreground">
                                                Across {{ course.modules_count }} modules
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Clock class="h-4 w-4 text-muted-foreground" />
                                            <span class="text-sm">Total Duration</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold">
                                                {{ formatDuration(course.modules.reduce((sum, module) => sum + (module.estimated_duration || 0), 0)) }}
                                            </div>
                                            <div class="text-xs text-muted-foreground">
                                                From modules
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Course Modules -->
                <TabsContent value="modules" class="space-y-4">
                    <!-- Module Statistics -->
                    <div v-if="course.modules && course.modules.length > 0" class="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <BookOpen class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Total Modules</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ course.modules_count }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        In this course
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <CheckCircle class="h-4 w-4 text-red-600" />
                                    <span class="text-sm font-medium truncate">Required</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ requiredModulesCount }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ course.modules_count > 0 ? Math.round((requiredModulesCount / course.modules_count) * 100) : 0 }}% of total
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <CheckCircle class="h-4 w-4 text-gray-400" />
                                    <span class="text-sm font-medium truncate">Optional</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ course.modules_count - requiredModulesCount }}</div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ course.modules_count > 0 ? Math.round(((course.modules_count - requiredModulesCount) / course.modules_count) * 100) : 0 }}% of total
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent>
                                <div class="flex items-center space-x-2">
                                    <BookOpen class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium truncate">Total Lessons</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">
                                        {{ course.modules.reduce((sum, module) => sum + module.lessons_count, 0) }}
                                    </div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        Across all modules
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Course Modules</CardTitle>
                                    <CardDescription>
                                        Modules assigned to this course in order
                                    </CardDescription>
                                </div>
                                <Link :href="`/admin/courses/${course.id}/modules`">
                                    <Button
                                        size="sm"
                                    >
                                        <SettingsIcon class="mr-2 h-4 w-4" />
                                        Manage Module
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                :data="course.modules"
                                :columns="columns"
                                empty-message="No modules assigned"
                                empty-description="Add modules to structure your course content."
                            >
                                <template #cell-order="{ row }">
                                    <div class="flex items-center gap-2">
                                        <GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
                                        <Badge variant="outline">{{ row.pivot.order }}</Badge>
                                    </div>
                                </template>

                                <template #cell-title="{ row }">
                                    <div class="min-w-[224px]">
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
                                            :disabled="isTogglingRequired === row.id"
                                            @update:checked="(checked) => toggleRequired(row.id, checked)"
                                        />
                                        <Loader2
                                            v-if="isTogglingRequired === row.id"
                                            class="h-4 w-4 animate-spin text-muted-foreground"
                                        />
                                    </div>
                                </template>

                                <!-- Actions -->
                                <template #actions="{ row }">
                                    <div class="flex gap-2">
                                        <Link :href="`/admin/modules/${row.id}`">
                                            <Button variant="outline">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Link :href="`/admin/modules/${row.id}/edit`">
                                            <Button variant="outline">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            variant="outline"
                                            @click="removeModule(row.id)"
                                            :disabled="isRemoving === row.id"
                                        >
                                            <Loader2 v-if="isRemoving === row.id" class="h-4 w-4 animate-spin" />
                                            <Trash2 v-else class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </template>
                            </DataTable>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
