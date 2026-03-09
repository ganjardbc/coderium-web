<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Edit,
    BookOpen,
    Clock,
    Users,
    DollarSign,
    Star,
    GraduationCap,
    Calendar,
    TrendingUp,
    MessageSquare
} from 'lucide-vue-next';

interface Level {
    id: number;
    title: string;
    description: string;
    order_index: number;
    modules_count: number;
    is_published: boolean;
}

interface Enrollment {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    enrolled_at: string;
    completed_at: string | null;
    progress_percentage: number;
}

interface Track {
    id: number;
    title: string;
    description: string;
    slug: string;
    is_premium: boolean;
    price: number | null;
    is_published: boolean;
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_duration: number | null;
    levels_count: number;
    enrollments_count: number;
    instructor?: {
        id: number;
        name: string;
        email: string;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    track: Track;
    levels?: Level[];
    enrollments?: Enrollment[];
    stats?: {
        total_enrollments: number;
        active_enrollments: number;
        completed_enrollments: number;
        average_progress: number;
        completion_rate: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Tracks', href: '/admin/tracks' },
    { title: props.track.title, href: `/admin/tracks/${props.track.slug}` },
];

const formatDuration = (minutes: number | null) => {
    if (!minutes) return 'Not set';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

const formatPrice = (price: number | null, isPremium: boolean) => {
    if (!isPremium || !price) return 'Free';
    return `$${price.toFixed(2)}`;
};

const getDifficultyColor = (level: string) => {
    switch (level) {
        case 'beginner':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'intermediate':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'advanced':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`${track.title} - Admin`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ track.title }}</h1>
                    <p class="text-muted-foreground">
                        Track details and management
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/admin/tracks">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Tracks
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/admin/tracks/${track.slug}/edit`">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit Track
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Track Overview -->
            <div class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <Users class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Enrollments</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ track.enrollments_count }}</div>
                            <p class="text-xs text-muted-foreground">
                                Total students enrolled
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <BookOpen class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Levels</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ track.levels_count }}</div>
                            <p class="text-xs text-muted-foreground">
                                Learning levels
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <Clock class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Duration</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ formatDuration(track.estimated_duration) }}</div>
                            <p class="text-xs text-muted-foreground">
                                Estimated time
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center space-x-2">
                            <DollarSign class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Price</span>
                        </div>
                        <div class="mt-2">
                            <div class="text-2xl font-bold">{{ formatPrice(track.price, track.is_premium) }}</div>
                            <p class="text-xs text-muted-foreground">
                                {{ track.is_premium ? 'Premium track' : 'Free access' }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Tabs default-value="overview" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="levels">Levels</TabsTrigger>
                    <TabsTrigger value="enrollments">Enrollments</TabsTrigger>
                    <TabsTrigger value="analytics">Analytics</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="space-y-4">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Track Information</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div>
                                        <h4 class="font-medium mb-2">Description</h4>
                                        <p class="text-muted-foreground">{{ track.description }}</p>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <h4 class="font-medium mb-2">Difficulty Level</h4>
                                            <Badge :class="getDifficultyColor(track.difficulty_level)" class="capitalize">
                                                <Star class="mr-1 h-3 w-3" />
                                                {{ track.difficulty_level }}
                                            </Badge>
                                        </div>

                                        <div v-if="track.instructor">
                                            <h4 class="font-medium mb-2">Instructor</h4>
                                            <div class="flex items-center gap-2">
                                                <GraduationCap class="h-4 w-4 text-muted-foreground" />
                                                <span>{{ track.instructor.name }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <h4 class="font-medium mb-2">Created</h4>
                                            <div class="flex items-center gap-2 text-muted-foreground">
                                                <Calendar class="h-4 w-4" />
                                                <span>{{ formatDate(track.created_at) }}</span>
                                            </div>
                                        </div>

                                        <div>
                                            <h4 class="font-medium mb-2">Last Updated</h4>
                                            <div class="flex items-center gap-2 text-muted-foreground">
                                                <Calendar class="h-4 w-4" />
                                                <span>{{ formatDate(track.updated_at) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Status & Settings</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Published</span>
                                        <Badge :variant="track.is_published ? 'default' : 'secondary'">
                                            {{ track.is_published ? 'Yes' : 'No' }}
                                        </Badge>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Premium</span>
                                        <Badge :variant="track.is_premium ? 'default' : 'secondary'">
                                            {{ track.is_premium ? 'Yes' : 'No' }}
                                        </Badge>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Slug</span>
                                        <code class="text-sm bg-muted px-2 py-1 rounded">{{ track.slug }}</code>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="levels" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium">Track Levels</h3>
                        <Button as-child>
                            <Link :href="`/admin/tracks/${track.slug}/levels/create`">
                                <BookOpen class="mr-2 h-4 w-4" />
                                Add Level
                            </Link>
                        </Button>
                    </div>

                    <div v-if="levels && levels.length > 0" class="space-y-4">
                        <Card v-for="level in levels" :key="level.id">
                            <CardContent class="p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">{{ level.title }}</h4>
                                        <p class="text-sm text-muted-foreground">{{ level.description }}</p>
                                        <div class="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
                                            <span>Order: {{ level.order_index }}</span>
                                            <span>Modules: {{ level.modules_count }}</span>
                                            <Badge :variant="level.is_published ? 'default' : 'secondary'" class="text-xs">
                                                {{ level.is_published ? 'Published' : 'Draft' }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="`/admin/levels/${level.id}/edit`">
                                                <Edit class="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="text-center py-8">
                        <BookOpen class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-medium">No levels yet</h3>
                        <p class="text-muted-foreground">Start building your track by adding levels.</p>
                    </div>
                </TabsContent>

                <TabsContent value="enrollments" class="space-y-4">
                    <h3 class="text-lg font-medium">Student Enrollments</h3>

                    <div v-if="enrollments && enrollments.length > 0" class="space-y-4">
                        <Card v-for="enrollment in enrollments" :key="enrollment.id">
                            <CardContent class="p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">{{ enrollment.user.name }}</h4>
                                        <p class="text-sm text-muted-foreground">{{ enrollment.user.email }}</p>
                                        <div class="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
                                            <span>Enrolled: {{ formatDate(enrollment.enrolled_at) }}</span>
                                            <span v-if="enrollment.completed_at">
                                                Completed: {{ formatDate(enrollment.completed_at) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-medium">{{ enrollment.progress_percentage }}%</div>
                                        <div class="text-sm text-muted-foreground">Progress</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="text-center py-8">
                        <Users class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-medium">No enrollments yet</h3>
                        <p class="text-muted-foreground">Students will appear here once they enroll in this track.</p>
                    </div>
                </TabsContent>

                <TabsContent value="analytics" class="space-y-4">
                    <h3 class="text-lg font-medium">Track Analytics</h3>

                    <div v-if="stats" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Completion Rate</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ stats.completion_rate }}%</div>
                                    <p class="text-xs text-muted-foreground">
                                        Students who completed the track
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Active Students</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ stats.active_enrollments }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Currently learning
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="p-6">
                                <div class="flex items-center space-x-2">
                                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Average Progress</span>
                                </div>
                                <div class="mt-2">
                                    <div class="text-2xl font-bold">{{ stats.average_progress }}%</div>
                                    <p class="text-xs text-muted-foreground">
                                        Across all students
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="text-center py-8">
                        <TrendingUp class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-medium">No analytics data</h3>
                        <p class="text-muted-foreground">Analytics will be available once students start enrolling.</p>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
