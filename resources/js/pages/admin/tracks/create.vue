<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed } from 'vue';

interface Instructor {
    id: number;
    name: string;
    email: string;
}

interface Props {
    instructors: Instructor[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Tracks', href: '/admin/tracks' },
    { title: 'Create Track', href: '/admin/tracks/create' },
];

const form = useForm({
    title: '',
    description: '',
    slug: '',
    difficulty_level: 'beginner' as 'beginner' | 'intermediate' | 'advanced',
    estimated_duration: null as number | null,
    instructor_id: null as number | null,
    is_premium: false,
    price: null as number | null,
    is_published: false,
});

const generateSlug = () => {
    if (form.title) {
        form.slug = form.title
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();
    }
};

const isPriceRequired = computed(() => form.is_premium);

const submit = () => {
    form.post('/admin/tracks', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Track - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Create New Track</h1>
                    <p class="text-muted-foreground">
                        Set up a new learning track with structured content
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <a href="/admin/tracks">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Tracks
                    </a>
                </Button>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                                <CardDescription>
                                    Enter the basic details for your track
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="title">Title *</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        placeholder="Enter track title"
                                        @input="generateSlug"
                                        :class="{ 'border-destructive': form.errors.title }"
                                    />
                                    <InputError :message="form.errors.title" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="slug">Slug *</Label>
                                    <Input
                                        id="slug"
                                        v-model="form.slug"
                                        placeholder="track-slug"
                                        :class="{ 'border-destructive': form.errors.slug }"
                                    />
                                    <InputError :message="form.errors.slug" />
                                    <p class="text-sm text-muted-foreground">
                                        URL-friendly version of the title. Auto-generated from title.
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description *</Label>
                                    <Textarea
                                        id="description"
                                        v-model="form.description"
                                        placeholder="Describe what students will learn in this track"
                                        rows="4"
                                        :class="{ 'border-destructive': form.errors.description }"
                                    />
                                    <InputError :message="form.errors.description" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Track Details</CardTitle>
                                <CardDescription>
                                    Configure the track difficulty and duration
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="difficulty_level">Difficulty Level *</Label>
                                        <Select v-model="form.difficulty_level">
                                            <SelectTrigger :class="{ 'border-destructive': form.errors.difficulty_level }">
                                                <SelectValue placeholder="Select difficulty" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="beginner">Beginner</SelectItem>
                                                <SelectItem value="intermediate">Intermediate</SelectItem>
                                                <SelectItem value="advanced">Advanced</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.difficulty_level" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="estimated_duration">Estimated Duration (minutes)</Label>
                                        <Input
                                            id="estimated_duration"
                                            v-model.number="form.estimated_duration"
                                            type="number"
                                            min="1"
                                            placeholder="e.g., 120"
                                            :class="{ 'border-destructive': form.errors.estimated_duration }"
                                        />
                                        <InputError :message="form.errors.estimated_duration" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="instructor_id">Instructor</Label>
                                    <Select v-model="form.instructor_id">
                                        <SelectTrigger :class="{ 'border-destructive': form.errors.instructor_id }">
                                            <SelectValue placeholder="Select an instructor (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="instructor in props.instructors"
                                                :key="instructor.id"
                                                :value="instructor.id"
                                            >
                                                {{ instructor.name }} ({{ instructor.email }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.instructor_id" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Pricing</CardTitle>
                                <CardDescription>
                                    Set the track pricing and access level
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="space-y-0.5">
                                        <Label>Premium Track</Label>
                                        <p class="text-sm text-muted-foreground">
                                            Require payment to access
                                        </p>
                                    </div>
                                    <Switch
                                        v-model:checked="form.is_premium"
                                        :class="{ 'border-destructive': form.errors.is_premium }"
                                    />
                                </div>
                                <InputError :message="form.errors.is_premium" />

                                <div v-if="form.is_premium" class="space-y-2">
                                    <Label for="price">Price (USD) *</Label>
                                    <Input
                                        id="price"
                                        v-model.number="form.price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        :class="{ 'border-destructive': form.errors.price }"
                                    />
                                    <InputError :message="form.errors.price" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Publishing</CardTitle>
                                <CardDescription>
                                    Control track visibility and availability
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="space-y-0.5">
                                        <Label>Published</Label>
                                        <p class="text-sm text-muted-foreground">
                                            Make track visible to students
                                        </p>
                                    </div>
                                    <Switch
                                        v-model:checked="form.is_published"
                                        :class="{ 'border-destructive': form.errors.is_published }"
                                    />
                                </div>
                                <InputError :message="form.errors.is_published" />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="pt-6">
                                <Button
                                    type="submit"
                                    class="w-full"
                                    :disabled="form.processing"
                                >
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ form.processing ? 'Creating...' : 'Create Track' }}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
