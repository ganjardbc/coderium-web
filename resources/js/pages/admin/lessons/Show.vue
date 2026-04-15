<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Edit,
    Clock,
    BookOpen,
    Play,
    FileText,
    Zap,
    Users,
    Eye,
    CheckCircle,
    XCircle,
    Calendar,
    Hash,
    Target,
    Activity
} from 'lucide-vue-next';

interface Module {
    id: number;
    title: string;
}

interface LessonProgress {
    id: number;
    user_id: number;
    completed_at: string | null;
    progress_percentage: number;
    time_spent: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

interface Lesson {
    id: number;
    title: string;
    content: string;
    lesson_type: string;
    estimated_duration: number;
    is_published: boolean;
    order_index: number;
    module?: Module;
    media?: any[];
    lesson_progress?: LessonProgress[];
    views_count?: number;
    completion_rate?: number;
    average_time_spent?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    lesson: Lesson;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Lessons', href: '/admin/lessons' },
    { title: props.lesson.title, href: '#' },
];

const formatDuration = (minutes: number) => {
    if (!minutes) return 'Not set';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const getLessonTypeIcon = (type: string) => {
    switch (type) {
        case 'video':
            return Play;
        case 'interactive':
            return Zap;
        default:
            return FileText;
    }
};

const getLessonTypeColor = (type: string) => {
    switch (type) {
        case 'video':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'interactive':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        default:
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    }
};

// Statistics calculations
const totalStudents = props.lesson.lesson_progress?.length || 0;
const completedStudents = props.lesson.lesson_progress?.filter(p => p.completed_at).length || 0;
const completionRate = totalStudents > 0 ? Math.round((completedStudents / totalStudents) * 100) : 0;
const averageProgress = totalStudents > 0
    ? Math.round(props.lesson.lesson_progress?.reduce((sum: any, p) => sum + p.progress_percentage, 0) / totalStudents)
    : 0;
const totalTimeSpent = props.lesson.lesson_progress?.reduce((sum, p) => sum + (p.time_spent || 0), 0) || 0;
const averageTimeSpent = totalStudents > 0 ? Math.round(totalTimeSpent / totalStudents) : 0;
</script>

<template>
    <Head :title="`${lesson.title} - Lessons - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs" is-back>
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ lesson.title }}</h1>
                        <Badge :variant="lesson.is_published ? 'default' : 'secondary'">
                            {{ lesson.is_published ? 'Published' : 'Draft' }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-4 text-muted-foreground">
                        <div class="flex items-center gap-1">
                            <span>Standalone lesson</span>
                        </div>
                    </div>
                </div>
                <Button as-child>
                    <Link :href="`/admin/lessons/${lesson.id}/edit`">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Lesson
                    </Link>
                </Button>
            </div>

            <Tabs default-value="overview" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="content">Content</TabsTrigger>
                    <TabsTrigger value="analytics">Analytics</TabsTrigger>
                    <TabsTrigger value="media" v-if="lesson.media && lesson.media.length > 0">Media</TabsTrigger>
                </TabsList>

                <!-- Overview Tab -->
                <TabsContent value="overview" class="space-y-4">
                    <!-- Statistics -->
                    <div class="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Students</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ totalStudents }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Enrolled in lesson
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Target class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Completion Rate</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ completionRate }}%</div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ completedStudents }} completed
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Activity class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Avg Progress</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ averageProgress }}%</div>
                                    <p class="text-xs text-muted-foreground">
                                        Overall progress
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Clock class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Avg Time</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ formatDuration(averageTimeSpent) }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Time spent
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Lesson Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Lesson Information</CardTitle>
                            <CardDescription>
                                Details about this lesson
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <component :is="getLessonTypeIcon(lesson.lesson_type)" class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">
                                                <span
                                                    :class="getLessonTypeColor(lesson.lesson_type)"
                                                    class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                                >
                                                    {{ lesson.lesson_type }}
                                                </span>
                                            </div>
                                            <div class="text-sm text-muted-foreground">
                                                Lesson type
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Clock class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">{{ formatDuration(lesson.estimated_duration) }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Estimated duration
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Hash class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">#{{ lesson.order_index || 'Not set' }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Position in module
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <CheckCircle v-if="lesson.is_published" class="h-5 w-5 text-green-600" />
                                        <XCircle v-else class="h-5 w-5 text-gray-400" />
                                        <div>
                                            <div class="font-medium">{{ lesson.is_published ? 'Published' : 'Draft' }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Publication status
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Calendar class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">{{ new Date(lesson.created_at).toLocaleDateString() }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Created date
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Calendar class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <div class="font-medium">{{ new Date(lesson.updated_at).toLocaleDateString() }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                Last updated
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Module Information -->
                    <Card v-if="lesson.module">
                        <CardHeader>
                            <CardTitle>Module Information</CardTitle>
                            <CardDescription>
                                This lesson belongs to a module
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <BookOpen class="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <div class="font-medium">{{ lesson.module.title }}</div>
                                        <div class="text-sm text-muted-foreground">
                                            Parent module
                                        </div>
                                    </div>
                                </div>
                                <Button as-child variant="outline">
                                    <Link :href="`/admin/modules/${lesson.module.id}`">
                                        <Eye class="mr-2 h-4 w-4" />
                                        View Module
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Content Tab -->
                <TabsContent value="content" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Lesson Content</CardTitle>
                            <CardDescription>
                                The main content of this lesson
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="prose max-w-none dark:prose-invert" v-html="lesson.content"></div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Analytics Tab -->
                <TabsContent value="analytics" class="space-y-4">
                    <!-- Student Progress -->
                    <Card v-if="lesson.lesson_progress && lesson.lesson_progress.length > 0">
                        <CardHeader>
                            <CardTitle>Student Progress</CardTitle>
                            <CardDescription>
                                Individual student progress and completion status
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="progress in lesson.lesson_progress"
                                    :key="progress.id"
                                    class="flex items-center justify-between p-4 border rounded-lg"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                            <span class="text-sm font-medium">
                                                {{ progress.user.name.charAt(0).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="font-medium">{{ progress.user.name }}</div>
                                            <div class="text-sm text-muted-foreground">{{ progress.user.email }}</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="font-medium">{{ progress.progress_percentage }}%</div>
                                            <div class="text-sm text-muted-foreground">Progress</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium">{{ formatDuration(progress.time_spent || 0) }}</div>
                                            <div class="text-sm text-muted-foreground">Time spent</div>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <CheckCircle v-if="progress.completed_at" class="h-4 w-4 text-green-600" />
                                            <XCircle v-else class="h-4 w-4 text-gray-400" />
                                            <span class="text-sm">
                                                {{ progress.completed_at ? 'Completed' : 'In Progress' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- No Analytics Data -->
                    <Card v-else>
                        <CardContent class="p-12 text-center">
                            <Activity class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                            <h3 class="text-lg font-medium mb-2">No Analytics Data</h3>
                            <p class="text-muted-foreground">
                                No student progress data available for this lesson yet.
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Media Tab -->
                <TabsContent value="media" class="space-y-4" v-if="lesson.media && lesson.media.length > 0">
                    <Card>
                        <CardHeader>
                            <CardTitle>Media Files</CardTitle>
                            <CardDescription>
                                Files attached to this lesson
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="media in lesson.media"
                                    :key="media.id"
                                    class="flex items-center gap-3 p-4 border rounded-lg"
                                >
                                    <div class="flex-shrink-0">
                                        <img
                                            v-if="media.type?.startsWith('image')"
                                            :src="media.url"
                                            :alt="media.name"
                                            class="w-16 h-16 object-cover rounded"
                                        />
                                        <div
                                            v-else
                                            class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded flex items-center justify-center"
                                        >
                                            <FileText class="h-8 w-8 text-gray-500" />
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate">{{ media.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ media.type }}</p>
                                        <p class="text-xs text-muted-foreground" v-if="media.size">
                                            {{ (media.size / 1024 / 1024).toFixed(2) }} MB
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
