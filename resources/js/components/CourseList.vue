<script setup lang="ts">
import { computed } from 'vue';
import CourseCard from '@/components/CourseCard.vue';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ChevronRight } from 'lucide-vue-next';

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
    courses: Course[];
    title?: string;
    showViewAll?: boolean;
    viewAllUrl?: string;
    limit?: number;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Courses',
    showViewAll: true,
    viewAllUrl: '/courses',
    limit: 8,
});

const displayedCourses = computed(() => {
    if (props.limit && props.courses.length > props.limit) {
        return props.courses.slice(0, props.limit);
    }
    return props.courses;
});

const hasMoreCourses = computed(() => {
    return props.limit && props.courses.length > props.limit;
});
</script>

<template>
    <section class="py-8">
        <div class="container mx-auto px-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">{{ title }}</h2>
                <Button
                    v-if="showViewAll && hasMoreCourses"
                    :as="Link"
                    :href="viewAllUrl"
                    variant="outline"
                    class="flex items-center gap-2"
                >
                    View All
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>

            <!-- Courses Grid -->
            <div v-if="displayedCourses.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <CourseCard
                    v-for="course in displayedCourses"
                    :key="course.id"
                    :course="course"
                />
            </div>

            <!-- Empty State -->
            <div v-else class="py-12 text-center text-muted-foreground">
                <BookOpen class="mx-auto mb-4 h-12 w-12" />
                <p>No courses available yet</p>
            </div>

            <!-- View All Button (bottom) -->
            <div v-if="showViewAll && hasMoreCourses" class="mt-8 text-center">
                <Button
                    :as="Link"
                    :href="viewAllUrl"
                    variant="outline"
                    size="lg"
                >
                    View All {{ courses.length }} Courses
                </Button>
            </div>
        </div>
    </section>
</template>
