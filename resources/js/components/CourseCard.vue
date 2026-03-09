<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Users, BookOpen, Award } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

interface Course {
    id: number;
    slug: string;
    title: string;
    description: string;
    estimated_duration: number;
    is_active: boolean;
    modules_count: number;
    enrollments_count: number;
    certificate_template?: {
        id: number;
        name: string;
        description: string;
    };
    url: string;
}

interface Props {
    course: Course;
}

defineProps<Props>();

const formatDuration = (minutes: number): string => {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
};

const formatNumber = (num: number) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
};
</script>

<template>
    <Link
        :href="course.url"
        class="group overflow-hidden rounded-lg border bg-card transition-all hover:border-gray-200 hover:shadow-xl"
    >
        <div class="relative aspect-video w-full overflow-hidden bg-gradient-to-br from-blue-500 to-purple-600">
            <!-- Course Icon/Visual -->
            <div class="flex h-full items-center justify-center">
                <BookOpen class="h-16 w-16 text-white/80" />
            </div>

            <!-- Certificate Badge -->
            <div v-if="course.certificate_template" class="absolute top-3 left-3">
                <Badge variant="success" class="backdrop-blur-sm">
                    <Award class="mr-1 h-3 w-3" />
                    Certificate
                </Badge>
            </div>
        </div>

        <div class="flex flex-col justify-between p-5">
            <div class="flex min-h-[124px] flex-col">
                <h3
                    class="text-md mb-2 line-clamp-2 font-semibold transition-colors group-hover:text-primary"
                >
                    {{ course.title }}
                </h3>
                <p
                    class="mb-4 line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ course.description }}
                </p>
            </div>

            <div class="space-y-3">
                <!-- Course Stats -->
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1">
                            <BookOpen class="h-3.5 w-3.5" />
                            <span>{{ course.modules_count }} modules</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Users class="h-3.5 w-3.5" />
                            <span>{{ formatNumber(course.enrollments_count) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Clock class="h-3.5 w-3.5" />
                        <span>{{ formatDuration(course.estimated_duration) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </Link>
</template>
