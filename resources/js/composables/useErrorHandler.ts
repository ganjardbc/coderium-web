import { globalNotifications } from './useNotifications';

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
    status?: number;
}

export function useErrorHandler() {
    const handleError = (error: any, context?: string) => {
        console.error('Error occurred:', error, context);

        let title = 'An error occurred';
        let message = 'Please try again later.';

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
                    message =
                        'You do not have permission to perform this action.';
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
                    break;
                case 500:
                    title = 'Server Error';
                    message =
                        'An internal server error occurred. Please try again later.';
                    break;
            }
        } else if (error?.message) {
            title = error.message;
        }

        // Add context to the title if provided
        if (context) {
            title = `${context}: ${title}`;
        }

        globalNotifications.error(title, message, {
            duration: 8000, // Longer duration for errors
        });

        return {
            title,
            message,
            status: error?.response?.status,
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

    return {
        handleError,
        handleValidationErrors,
        handleSuccess,
    };
}

// Global error handler instance
export const globalErrorHandler = useErrorHandler();
