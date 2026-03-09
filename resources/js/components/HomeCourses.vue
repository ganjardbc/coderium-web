<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import emblaCarouselVue from 'embla-carousel-vue';
import { ChevronLeft, ChevronRight, BookOpen } from 'lucide-vue-next';
import { ref, watchEffect } from 'vue';

import CourseCard from '@/components/CourseCard.vue';

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
    title?: string;
    courses: Course[];
    filters?: {
        search?: string;
        sort?: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Featured Courses',
});

const [emblaRef, emblaApi] = emblaCarouselVue({
    align: 'start',
    containScroll: 'trimSnaps',
    dragFree: true,
});

const canScrollLeft = ref(false);
const canScrollRight = ref(true);

// Update scroll buttons based on embla state
watchEffect(() => {
    if (emblaApi.value) {
        const updateScrollButtons = () => {
            canScrollLeft.value = emblaApi.value?.canScrollPrev() || false;
            canScrollRight.value = emblaApi.value?.canScrollNext() || false;
        };

        emblaApi.value.on('select', updateScrollButtons);
        emblaApi.value.on('reInit', updateScrollButtons);

        // Initialize button states
        updateScrollButtons();
    }
});

const scrollCourses = (direction: 'left' | 'right') => {
    if (!emblaApi.value) return;

    if (direction === 'left') {
        emblaApi.value.scrollPrev();
    } else {
        emblaApi.value.scrollNext();
    }
};
</script>

<template>
    <section id="courses" class="border-t py-8">
        <div class="container mx-auto px-4">
            <div class="mb-4 flex flex-row items-center justify-between gap-2">
                <h2 class="text-xl font-bold">{{ props.title || 'Featured Courses' }}</h2>

                <!-- Scroll Navigation Buttons -->
                <div class="flex gap-2">
                    <Button
                        size="lg"
                        variant="outline"
                        class="w-[40px] rounded-full px-0"
                        :disabled="!canScrollLeft"
                        @click="scrollCourses('left')"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        class="w-[40px] rounded-full px-0"
                        :disabled="!canScrollRight"
                        @click="scrollCourses('right')"
                    >
                        <ChevronRight class="h-5 w-5" />
                    </Button>
                </div>
            </div>

            <div v-if="courses.length > 0" class="relative">
                <div ref="emblaRef" class="overflow-hidden">
                    <div class="flex touch-pan-y gap-4">
                        <CourseCard
                            v-for="course in courses"
                            :key="course.id"
                            :course="course"
                            class="!max-w-[310px] !min-w-[310px]"
                        />
                        <div class="min-w-0 flex-[0_0_auto]">
                            <Button
                                :as="Link"
                                :href="`/courses?q=${props.filters?.search || ''}&sort=${props.filters?.sort || ''}`"
                                size="lg"
                                variant="outline"
                                class="flex h-full !max-w-[310px] !min-w-[310px] flex-col bg-gray-50 !py-6 dark:bg-gray-800"
                            >
                                <span class="mb-2 text-lg font-medium">
                                    Explore Courses
                                </span>
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full bg-primary"
                                >
                                    <ChevronRight
                                        class="h-5 w-5 text-white dark:text-black"
                                    />
                                </div>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="py-12 text-center text-muted-foreground">
                <BookOpen class="mx-auto mb-4 h-12 w-12" />
                <p>No courses available yet</p>
            </div>
        </div>
    </section>
</template>
