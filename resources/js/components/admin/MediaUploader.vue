<script setup lang="ts">
import axios from 'axios';
import { Film, Image as ImageIcon, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Media {
    id: number;
    name: string;
    url: string;
    type: 'image' | 'video' | 'file';
    mime_type: string;
    size: number;
}

interface Props {
    modelValue?: Media[];
    accept?: string;
    multiple?: boolean;
    maxSize?: number; // in MB
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
    accept: 'image/*,video/*',
    multiple: true,
    maxSize: 50,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: Media[]];
}>();

const fileInput = ref<HTMLInputElement>();
const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const files = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

const validateFile = (file: File): string | null => {
    if (file.size > props.maxSize * 1024 * 1024) {
        return `File size exceeds ${props.maxSize}MB`;
    }
    return null;
};

const uploadFiles = async (fileList: FileList | File[]) => {
    if (props.disabled) return;

    const filesToUpload = Array.from(fileList);
    const validFiles: File[] = [];

    // Validate files
    for (const file of filesToUpload) {
        const error = validateFile(file);
        if (error) {
            alert(`${file.name}: ${error}`);
        } else {
            validFiles.push(file);
        }
    }

    if (validFiles.length === 0) return;

    isUploading.value = true;
    uploadProgress.value = 0;

    try {
        const formData = new FormData();

        if (props.multiple) {
            validFiles.forEach((file) => {
                formData.append('files[]', file);
            });
            formData.append('collection', 'posts');

            const response = await axios.post(
                '/api/v1/media/upload-multiple',
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                    onUploadProgress: (progressEvent) => {
                        if (progressEvent.total) {
                            uploadProgress.value = Math.round(
                                (progressEvent.loaded * 100) /
                                    progressEvent.total,
                            );
                        }
                    },
                },
            );

            const uploadedMedia = response.data.media;
            files.value = [...files.value, ...uploadedMedia];
        } else {
            formData.append('file', validFiles[0]);
            formData.append('collection', 'posts');

            const response = await axios.post(
                '/api/v1/media/upload',
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                    onUploadProgress: (progressEvent) => {
                        if (progressEvent.total) {
                            uploadProgress.value = Math.round(
                                (progressEvent.loaded * 100) /
                                    progressEvent.total,
                            );
                        }
                    },
                },
            );

            const uploadedMedia = response.data.media;
            files.value = [uploadedMedia];
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert('Failed to upload files. Please try again.');
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        uploadFiles(target.files);
        target.value = ''; // Reset input
    }
};

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
        uploadFiles(event.dataTransfer.files);
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = () => {
    isDragging.value = false;
};

const removeFile = (index: number) => {
    files.value = files.value.filter((_, i) => i !== index);
};

const openFilePicker = () => {
    if (!props.disabled) {
        fileInput.value?.click();
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- Upload Area -->
        <div
            @click="openFilePicker"
            @drop.prevent="handleDrop"
            @dragover.prevent="handleDragOver"
            @dragleave="handleDragLeave"
            :class="[
                'relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors',
                isDragging
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-primary/50',
                disabled ? 'cursor-not-allowed opacity-50' : '',
                isUploading ? 'pointer-events-none' : '',
            ]"
        >
            <input
                ref="fileInput"
                type="file"
                :accept="accept"
                :multiple="multiple"
                :disabled="disabled"
                class="hidden"
                @change="handleFileSelect"
            />

            <div v-if="isUploading" class="text-center">
                <div class="mb-2 flex justify-center">
                    <Upload class="h-8 w-8 animate-bounce text-primary" />
                </div>
                <p class="text-sm font-medium">Uploading...</p>
                <div class="mt-2 w-64">
                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-secondary"
                    >
                        <div
                            class="h-full bg-primary transition-all duration-300"
                            :style="{ width: `${uploadProgress}%` }"
                        ></div>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ uploadProgress }}%
                    </p>
                </div>
            </div>

            <div v-else class="text-center">
                <Upload class="mx-auto h-12 w-12 text-muted-foreground" />
                <div class="mt-4">
                    <p class="text-sm font-medium">
                        {{
                            isDragging
                                ? 'Drop files here'
                                : 'Click to upload or drag and drop'
                        }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ accept.includes('image') ? 'Images' : '' }}
                        {{
                            accept.includes('image') && accept.includes('video')
                                ? ' and '
                                : ''
                        }}
                        {{ accept.includes('video') ? 'Videos' : '' }}
                        up to {{ maxSize }}MB
                    </p>
                </div>
            </div>
        </div>

        <!-- Uploaded Files Preview -->
        <div v-if="files.length > 0" class="space-y-2">
            <p class="text-sm font-medium">
                Uploaded Files ({{ files.length }})
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(file, index) in files"
                    :key="file.id"
                    class="group relative overflow-hidden rounded-lg border bg-card"
                >
                    <!-- Preview -->
                    <div class="aspect-video w-full overflow-hidden bg-muted">
                        <img
                            v-if="file.type === 'image'"
                            :src="file.url"
                            :alt="file.name"
                            class="h-full w-full object-cover"
                        />
                        <video
                            v-else-if="file.type === 'video'"
                            :src="file.url"
                            class="h-full w-full object-cover"
                        ></video>
                        <div
                            v-else
                            class="flex h-full items-center justify-center"
                        >
                            <Film class="h-12 w-12 text-muted-foreground" />
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-3">
                        <p
                            class="truncate text-sm font-medium"
                            :title="file.name"
                        >
                            {{ file.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatFileSize(file.size) }}
                        </p>
                    </div>

                    <!-- Remove Button -->
                    <button
                        v-if="!disabled"
                        @click="removeFile(index)"
                        class="absolute top-2 right-2 rounded-full bg-destructive p-1 text-destructive-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:bg-destructive/90"
                        type="button"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <!-- Type Badge -->
                    <div class="absolute top-2 left-2">
                        <span
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold',
                                file.type === 'image'
                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                    : '',
                                file.type === 'video'
                                    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                    : '',
                            ]"
                        >
                            <ImageIcon
                                v-if="file.type === 'image'"
                                class="h-3 w-3"
                            />
                            <Film
                                v-else-if="file.type === 'video'"
                                class="h-3 w-3"
                            />
                            {{ file.type }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
