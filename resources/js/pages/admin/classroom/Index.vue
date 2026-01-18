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
import { Award, BarChart3, BookOpen, Settings, Users } from 'lucide-vue-next';

interface Props {
    stats: {
        total_tracks: number;
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

            <!-- Stats Overview -->
            <div class="mb-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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
                            Learning paths available
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
                            New to the classroom system? Follow these steps to
                            get started
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
                                    Create your first track
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Set up a learning path with levels, modules,
                                    and lessons
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
                                    Add content and assessments
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Create engaging lessons with rich content
                                    and quizzes
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
                                <p class="font-medium">Publish and monitor</p>
                                <p class="text-sm text-muted-foreground">
                                    Make your track live and track learner
                                    progress
                                </p>
                            </div>
                        </div>
                        <Button
                            class="mt-4 w-full"
                            @click="
                                $inertia.visit('/admin/classroom/tracks/create')
                            "
                        >
                            Create Your First Track
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Management Tools</CardTitle>
                        <CardDescription>
                            Access key management features for your classroom
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
                        <Button
                            variant="outline"
                            class="w-full justify-start"
                            @click="$inertia.visit('/admin/classroom/settings')"
                        >
                            <Settings class="mr-2 h-4 w-4" />
                            Classroom Settings
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
