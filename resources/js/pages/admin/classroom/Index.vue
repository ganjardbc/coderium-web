<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Award, BarChart3, BookOpen, Settings, Users, GraduationCap, FileText } from 'lucide-vue-next';

interface Props {
    stats: {
        total_tracks: number;
        total_courses: number;
        total_modules: number;
        total_enrollments: number;
        total_certificates: number;
        active_learners: number;
    };
}

defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
];

const quickActions = [
    {
        title: 'Create Track',
        description: 'Start a new learning track',
        href: '/admin/classroom/tracks/create',
        icon: BookOpen,
        color: 'bg-blue-500',
    },
    {
        title: 'Create Course',
        description: 'Build a new course with modules',
        href: '/admin/classroom/courses/create',
        icon: GraduationCap,
        color: 'bg-indigo-500',
    },
    {
        title: 'Module Library',
        description: 'Browse and manage all modules',
        href: '/admin/classroom/modules',
        icon: BookOpen,
        color: 'bg-teal-500',
    },
    {
        title: 'Assignment Dashboard',
        description: 'Manage module assignments',
        href: '/admin/classroom/assignments',
        icon: Settings,
        color: 'bg-orange-500',
    },
    {
        title: 'Progress Dashboard',
        description: 'Monitor learner progress',
        href: '/admin/classroom/progress',
        icon: BarChart3,
        color: 'bg-green-500',
    },
    {
        title: 'Certificate Manager',
        description: 'Manage certificates and templates',
        href: '/admin/classroom/certificates',
        icon: Award,
        color: 'bg-yellow-500',
    },
    {
        title: 'Manage Tracks',
        description: 'View and edit all tracks',
        href: '/admin/classroom/tracks',
        icon: Settings,
        color: 'bg-purple-500',
    },
    {
        title: 'Manage Courses',
        description: 'View and edit all courses',
        href: '/admin/classroom/courses',
        icon: Settings,
        color: 'bg-pink-500',
    },
];
</script>

<template>
    <Head title="Classroom Admin - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Classroom Administration</h1>
                <p class="text-muted-foreground">
                    Manage your learning management system, tracks, and learner
                    progress
                </p>
            </div>

            <!-- Enhanced Stats Overview with Course Management -->
            <div class="mb-8 grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Tracks</CardTitle
                        >
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.total_tracks }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Learning tracks
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Courses</CardTitle
                        >
                        <GraduationCap class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.total_courses || 0 }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Course offerings
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Modules</CardTitle
                        >
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.total_modules || 0 }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Learning modules
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Enrollments</CardTitle
                        >
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.total_enrollments }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Learners enrolled
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Certificates Issued</CardTitle
                        >
                        <Award class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.total_certificates }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Completions recognized
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Active Learners</CardTitle
                        >
                        <BarChart3 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.active_learners }}
                        </div>
                        <p class="text-xs text-muted-foreground">This month</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="mb-4 text-xl font-semibold">Quick Actions</h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card
                        v-for="action in quickActions"
                        :key="action.title"
                        class="cursor-pointer transition-colors hover:bg-muted/50"
                        @click="$inertia.visit(action.href)"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-center gap-3">
                                <div
                                    :class="[
                                        action.color,
                                        'flex h-10 w-10 items-center justify-center rounded-lg text-white',
                                    ]"
                                >
                                    <component
                                        :is="action.icon"
                                        class="h-5 w-5"
                                    />
                                </div>
                                <div>
                                    <CardTitle class="text-base">{{
                                        action.title
                                    }}</CardTitle>
                                    <CardDescription class="text-sm">{{
                                        action.description
                                    }}</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Getting Started</CardTitle>
                        <CardDescription>
                            New to the enhanced classroom system? Follow these steps to get started
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
                            >
                                1
                            </div>
                            <div>
                                <p class="font-medium">
                                    Create standalone modules
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Build reusable learning modules that can be assigned to any learning path
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
                            >
                                2
                            </div>
                            <div>
                                <p class="font-medium">
                                    Create tracks or courses
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Set up learning paths using tracks (traditional) or courses (flexible module-based)
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
                            >
                                3
                            </div>
                            <div>
                                <p class="font-medium">
                                    Assign modules to learning paths
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Use the assignment dashboard to flexibly assign modules to tracks or courses
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
                            >
                                4
                            </div>
                            <div>
                                <p class="font-medium">Publish and monitor</p>
                                <p class="text-sm text-muted-foreground">
                                    Make your learning paths live and track unified progress across all formats
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <Button
                                class="flex-1"
                                @click="
                                    $inertia.visit('/admin/classroom/tracks/create')
                                "
                            >
                                Create Track
                            </Button>
                            <Button
                                variant="outline"
                                class="flex-1"
                                @click="
                                    $inertia.visit('/admin/classroom/courses/create')
                                "
                            >
                                Create Course
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Enhanced Management Tools</CardTitle>
                        <CardDescription>
                            Access unified management features for tracks, courses, and modules
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="$inertia.visit('/admin/classroom/tracks')"
                        >
                            <BookOpen class="mr-2 h-4 w-4" />
                            Manage All Tracks
                        </Button>
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="$inertia.visit('/admin/classroom/courses')"
                        >
                            <GraduationCap class="mr-2 h-4 w-4" />
                            Manage All Courses
                        </Button>
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="$inertia.visit('/admin/classroom/modules')"
                        >
                            <FileText class="mr-2 h-4 w-4" />
                            Module Library
                        </Button>
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="$inertia.visit('/admin/classroom/assignments')"
                        >
                            <Settings class="mr-2 h-4 w-4" />
                            Assignment Dashboard
                        </Button>
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="$inertia.visit('/admin/classroom/progress')"
                        >
                            <BarChart3 class="mr-2 h-4 w-4" />
                            View Progress Analytics
                        </Button>
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="
                                $inertia.visit('/admin/classroom/certificates')
                            "
                        >
                            <Award class="mr-2 h-4 w-4" />
                            Certificate Management
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
