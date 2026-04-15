<script setup lang="ts">
import { ref, onErrorCaptured, provide, inject } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { globalErrorHandler } from '@/composables/useErrorHandler';
import { AlertTriangle, RefreshCw, ChevronDown, ChevronUp } from 'lucide-vue-next';

interface Props {
    fallbackComponent?: any;
    onError?: (error: Error, instance: any) => void;
    showDetails?: boolean;
    resetOnPropsChange?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showDetails: false,
    resetOnPropsChange: true,
});

const emit = defineEmits<{
    error: [error: Error, instance: any];
    reset: [];
}>();

const hasError = ref(false);
const error = ref<Error | null>(null);
const errorInfo = ref<any>(null);
const showErrorDetails = ref(false);
const retryCount = ref(0);

const { handleComponentError } = globalErrorHandler;

// Error boundary key for forcing re-render
const errorBoundaryKey = ref(0);

onErrorCaptured((err: Error, instance: any, info: string) => {
    hasError.value = true;
    error.value = err;
    errorInfo.value = { instance, info };

    // Handle the error through our error handler
    handleComponentError(err, instance?.$options?.name || 'Unknown Component');

    // Call custom error handler if provided
    props.onError?.(err, instance);

    // Emit error event
    emit('error', err, instance);

    // Prevent the error from propagating further
    return false;
});

const resetErrorBoundary = () => {
    hasError.value = false;
    error.value = null;
    errorInfo.value = null;
    showErrorDetails.value = false;
    retryCount.value++;
    errorBoundaryKey.value++;

    emit('reset');
};

const toggleErrorDetails = () => {
    showErrorDetails.value = !showErrorDetails.value;
};

// Provide error boundary context
provide('errorBoundary', {
    hasError,
    resetErrorBoundary,
});

// Auto-reset on props change if enabled
if (props.resetOnPropsChange) {
    // Watch for prop changes and reset if needed
    // This would need to be implemented based on specific prop watching needs
}
</script>

<template>
    <div :key="errorBoundaryKey">
        <!-- Error State -->
        <div v-if="hasError" class="error-boundary-fallback">
            <!-- Custom Fallback Component -->
            <component
                v-if="fallbackComponent"
                :is="fallbackComponent"
                :error="error"
                :error-info="errorInfo"
                :retry-count="retryCount"
                @reset="resetErrorBoundary"
            />

            <!-- Default Error UI -->
            <Card v-else class="border-destructive/50 bg-destructive/5">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="h-5 w-5" />
                        Something went wrong
                    </CardTitle>
                    <CardDescription>
                        An unexpected error occurred in this component. You can try refreshing or contact support if the problem persists.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <!-- Error Message -->
                    <Alert variant="destructive">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertDescription>
                            {{ error?.message || 'An unknown error occurred' }}
                        </AlertDescription>
                    </Alert>

                    <!-- Actions -->
                    <div class="flex items-center gap-3">
                        <Button @click="resetErrorBoundary" variant="outline" size="sm">
                            <RefreshCw class="h-4 w-4 mr-2" />
                            Try Again
                        </Button>

                        <Button
                            v-if="showDetails"
                            @click="toggleErrorDetails"
                            variant="ghost"
                            size="sm"
                        >
                            <ChevronDown v-if="!showErrorDetails" class="h-4 w-4 mr-2" />
                            <ChevronUp v-else class="h-4 w-4 mr-2" />
                            {{ showErrorDetails ? 'Hide' : 'Show' }} Details
                        </Button>
                    </div>

                    <!-- Error Details -->
                    <div v-if="showDetails && showErrorDetails" class="space-y-3">
                        <div class="text-sm">
                            <h4 class="font-medium mb-2">Error Details:</h4>
                            <pre class="bg-muted p-3 rounded text-xs overflow-auto max-h-40">{{ error?.stack || error?.message }}</pre>
                        </div>

                        <div v-if="errorInfo?.info" class="text-sm">
                            <h4 class="font-medium mb-2">Component Info:</h4>
                            <pre class="bg-muted p-3 rounded text-xs overflow-auto max-h-40">{{ errorInfo.info }}</pre>
                        </div>

                        <div class="text-sm">
                            <h4 class="font-medium mb-2">Retry Count:</h4>
                            <p class="text-muted-foreground">{{ retryCount }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Normal Content -->
        <slot v-else />
    </div>
</template>

<style scoped>
.error-boundary-fallback {
    padding: 1rem;
    margin: 1rem 0;
}

pre {
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
