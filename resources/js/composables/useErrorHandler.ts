import { ref } from 'vue';
import { globalNotifications } from './useNotifications';

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
    status?: number;
    code?: string;
    details?: Record<string, any>;
    timestamp?: Date;
}

export interface RetryConfig {
    maxRetries: number;
    baseDelay: number;
    maxDelay: number;
    backoffMultiplier: number;
    retryCondition?: (error: any) => boolean;
}

export interface NetworkStatus {
    isOnline: boolean;
    connectionType: string;
    effectiveType: string;
}

const defaultRetryConfig: RetryConfig = {
    maxRetries: 3,
    baseDelay: 1000,
    maxDelay: 10000,
    backoffMultiplier: 2,
    retryCondition: (error) => {
        // Retry on network errors and 5xx server errors
        return !error.response || (error.response.status >= 500 && error.response.status < 600);
    }
};

// Global state for error handling
const networkStatus = ref<NetworkStatus>({
    isOnline: navigator.onLine,
    connectionType: 'unknown',
    effectiveType: 'unknown'
});

const offlineQueue = ref<Array<{ operation: () => Promise<any>; resolve: Function; reject: Function }>>([]);
const errorBoundaryErrors = ref<Array<{ error: Error; componentName: string; timestamp: Date }>>([]);

export function useErrorHandler() {
    // Network status monitoring
    const updateNetworkStatus = () => {
        networkStatus.value.isOnline = navigator.onLine;

        if ('connection' in navigator) {
            const connection = (navigator as any).connection;
            networkStatus.value.connectionType = connection.type || 'unknown';
            networkStatus.value.effectiveType = connection.effectiveType || 'unknown';
        }
    };

    // Set up network status listeners
    if (typeof window !== 'undefined') {
        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus();
    }

    // Retry mechanism with exponential backoff
    const retryWithBackoff = async <T>(
        operation: () => Promise<T>,
        config: Partial<RetryConfig> = {}
    ): Promise<T> => {
        const finalConfig = { ...defaultRetryConfig, ...config };
        let lastError: any;

        for (let attempt = 0; attempt <= finalConfig.maxRetries; attempt++) {
            try {
                return await operation();
            } catch (error) {
                lastError = error;

                // Don't retry if condition is not met
                if (finalConfig.retryCondition && !finalConfig.retryCondition(error)) {
                    throw error;
                }

                // Don't retry on last attempt
                if (attempt === finalConfig.maxRetries) {
                    throw error;
                }

                // Calculate delay with exponential backoff
                const delay = Math.min(
                    finalConfig.baseDelay * Math.pow(finalConfig.backoffMultiplier, attempt),
                    finalConfig.maxDelay
                );

                // Add jitter to prevent thundering herd
                const jitteredDelay = delay + Math.random() * 1000;

                await new Promise(resolve => setTimeout(resolve, jitteredDelay));
            }
        }

        throw lastError;
    };

    // Queue operations for offline execution
    const queueOfflineOperation = <T>(operation: () => Promise<T>): Promise<T> => {
        return new Promise((resolve, reject) => {
            offlineQueue.value.push({ operation, resolve, reject });
        });
    };

    // Process offline queue when back online
    const processOfflineQueue = async () => {
        if (!networkStatus.value.isOnline || offlineQueue.value.length === 0) {
            return;
        }

        const queue = [...offlineQueue.value];
        offlineQueue.value = [];

        for (const { operation, resolve, reject } of queue) {
            try {
                const result = await operation();
                resolve(result);
            } catch (error) {
                reject(error);
            }
        }
    };

    // Enhanced error handling with retry and offline support
    const handleError = (error: any, context?: string, retryConfig?: Partial<RetryConfig>) => {
        console.error('Error occurred:', error, context);

        let title = 'An error occurred';
        let message = 'Please try again later.';
        let canRetry = false;

        // Check if offline
        if (!networkStatus.value.isOnline) {
            title = 'Connection Lost';
            message = 'You appear to be offline. Changes will be saved when connection is restored.';

            globalNotifications.warning(title, message, {
                duration: 0, // Persistent until online
                persistent: true,
            });

            return { title, message, status: 0, offline: true };
        }

        if (error?.response?.data) {
            const errorData = error.response.data;

            if (errorData.message) {
                title = errorData.message;
            }

            // Handle validation errors
            if (errorData.errors) {
                const validationErrors = Object.values(errorData.errors).flat();
                if (validationErrors.length > 0) {
                    message = validationErrors.join(', ');
                }
            }

            // Handle specific HTTP status codes
            switch (error.response.status) {
                case 401:
                    title = 'Authentication Required';
                    message = 'Please log in to continue.';
                    break;
                case 403:
                    title = 'Access Denied';
                    message = 'You do not have permission to perform this action.';
                    break;
                case 404:
                    title = 'Not Found';
                    message = 'The requested resource could not be found.';
                    break;
                case 422:
                    title = 'Validation Error';
                    // message already set from validation errors above
                    break;
                case 429:
                    title = 'Too Many Requests';
                    message = 'Please wait a moment before trying again.';
                    canRetry = true;
                    break;
                case 500:
                case 502:
                case 503:
                case 504:
                    title = 'Server Error';
                    message = 'An internal server error occurred. Please try again later.';
                    canRetry = true;
                    break;
            }
        } else if (error?.message) {
            title = error.message;

            // Network errors are retryable
            if (error.message.includes('fetch') || error.message.includes('network')) {
                canRetry = true;
            }
        }

        // Add context to the title if provided
        if (context) {
            title = `${context}: ${title}`;
        }

        // Show retry option for retryable errors
        if (canRetry && retryConfig) {
            globalNotifications.error(title, `${message} Click to retry.`, {
                duration: 0,
                persistent: true,
                actions: [{
                    label: 'Retry',
                    action: () => {
                        // This would need to be handled by the calling component
                        console.log('Retry requested');
                    }
                }]
            });
        } else {
            globalNotifications.error(title, message, {
                duration: 8000, // Longer duration for errors
            });
        }

        return {
            title,
            message,
            status: error?.response?.status,
            canRetry,
        };
    };

    // Progressive error disclosure
    const handleProgressiveError = (error: any, context?: string, level: 'basic' | 'detailed' | 'technical' = 'basic') => {
        const errorInfo = handleError(error, context);

        if (level === 'detailed' || level === 'technical') {
            const detailedMessage = [
                errorInfo.message,
                level === 'technical' && error.stack ? `Stack: ${error.stack}` : '',
                level === 'technical' && error.response?.data ? `Response: ${JSON.stringify(error.response.data)}` : ''
            ].filter(Boolean).join('\n\n');

            globalNotifications.error(errorInfo.title, detailedMessage, {
                duration: 0,
                persistent: true,
            });
        }

        return errorInfo;
    };

    // Error boundary handling
    const handleComponentError = (error: Error, componentName: string) => {
        const errorRecord = {
            error,
            componentName,
            timestamp: new Date()
        };

        errorBoundaryErrors.value.push(errorRecord);

        console.error(`Component error in ${componentName}:`, error);

        globalNotifications.error(
            'Component Error',
            `An error occurred in ${componentName}. The component has been reset.`,
            { duration: 8000 }
        );

        return errorRecord;
    };

    // Form validation with real-time feedback
    const createFormValidator = <T extends Record<string, any>>(
        rules: Record<keyof T, Array<(value: any) => string | null>>
    ) => {
        const errors = ref<Partial<Record<keyof T, string>>>({});
        const isValid = ref(true);

        const validateField = (field: keyof T, value: any): string | null => {
            const fieldRules = rules[field] || [];

            for (const rule of fieldRules) {
                const error = rule(value);
                if (error) {
                    errors.value[field] = error;
                    isValid.value = false;
                    return error;
                }
            }

            delete errors.value[field];
            isValid.value = Object.keys(errors.value).length === 0;
            return null;
        };

        const validateAll = (data: T): boolean => {
            let hasErrors = false;

            for (const [field, value] of Object.entries(data)) {
                const error = validateField(field as keyof T, value);
                if (error) {
                    hasErrors = true;
                }
            }

            isValid.value = !hasErrors;
            return !hasErrors;
        };

        const clearErrors = () => {
            errors.value = {};
            isValid.value = true;
        };

        return {
            errors,
            isValid,
            validateField,
            validateAll,
            clearErrors
        };
    };

    const handleValidationErrors = (errors: Record<string, string[]>) => {
        Object.entries(errors).forEach(([field, messages]) => {
            messages.forEach((message) => {
                globalNotifications.error(`${field}: ${message}`, undefined, {
                    duration: 6000,
                });
            });
        });
    };

    const handleSuccess = (message: string, title = 'Success') => {
        globalNotifications.success(title, message);
    };

    // Cleanup function
    const cleanup = () => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('online', updateNetworkStatus);
            window.removeEventListener('offline', updateNetworkStatus);
        }
    };

    // Process offline queue when coming back online
    if (typeof window !== 'undefined') {
        window.addEventListener('online', processOfflineQueue);
    }

    return {
        handleError,
        handleProgressiveError,
        handleComponentError,
        handleValidationErrors,
        handleSuccess,
        retryWithBackoff,
        queueOfflineOperation,
        processOfflineQueue,
        createFormValidator,
        networkStatus,
        offlineQueue,
        errorBoundaryErrors,
        cleanup
    };
}

// Global error handler instance
export const globalErrorHandler = useErrorHandler();
