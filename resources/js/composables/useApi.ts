import { router } from '@inertiajs/vue3';
import { globalErrorHandler } from './useErrorHandler';
import { globalLoading } from './useLoading';

export interface ApiOptions {
    loadingKey?: string;
    showSuccessMessage?: boolean;
    successMessage?: string;
    errorContext?: string;
    retryConfig?: {
        maxRetries?: number;
        baseDelay?: number;
        retryCondition?: (error: any) => boolean;
    };
    preserveState?: boolean;
    preserveScroll?: boolean;
    onSuccess?: (response?: any) => void;
    onError?: (error: any) => void;
}

export function useApi() {
    const { setLoading } = globalLoading;
    const { handleError, handleSuccess, retryWithBackoff, queueOfflineOperation, networkStatus } = globalErrorHandler;

    const makeRequest = async (
        method: 'get' | 'post' | 'put' | 'patch' | 'delete',
        url: string,
        data?: any,
        options: ApiOptions = {},
    ) => {
        const {
            loadingKey = url,
            showSuccessMessage = false,
            successMessage = 'Operation completed successfully',
            errorContext,
            retryConfig,
            preserveState = true,
            preserveScroll = true,
            onSuccess,
            onError,
        } = options;

        const executeRequest = async (): Promise<any> => {
            // Check if offline and queue if necessary
            if (!networkStatus.value.isOnline) {
                return queueOfflineOperation(() => executeRequest());
            }

            return new Promise((resolve, reject) => {
                const requestOptions: any = {
                    preserveState,
                    preserveScroll,
                    onSuccess: (response?: any) => {
                        if (showSuccessMessage) {
                            handleSuccess(successMessage);
                        }
                        onSuccess?.(response);
                        resolve(response);
                    },
                    onError: (errors: any) => {
                        const errorResult = handleError(errors, errorContext, retryConfig);
                        onError?.(errors);
                        reject(errors);
                    },
                    onFinish: () => {
                        setLoading(loadingKey, false);
                    },
                };

                if (method === 'get') {
                    router.get(url, data, requestOptions);
                } else {
                    router[method](url, data, requestOptions);
                }
            });
        };

        try {
            setLoading(loadingKey, true);

            // Use retry mechanism if configured
            if (retryConfig) {
                return await retryWithBackoff(executeRequest, retryConfig);
            } else {
                return await executeRequest();
            }
        } catch (error) {
            setLoading(loadingKey, false);
            handleError(error, errorContext, retryConfig);
            onError?.(error);
            throw error;
        }
    };

    const get = (url: string, params?: any, options?: ApiOptions) => {
        return makeRequest('get', url, params, options);
    };

    const post = (url: string, data?: any, options?: ApiOptions) => {
        return makeRequest('post', url, data, {
            showSuccessMessage: true,
            ...options,
        });
    };

    const put = (url: string, data?: any, options?: ApiOptions) => {
        return makeRequest('put', url, data, {
            showSuccessMessage: true,
            ...options,
        });
    };

    const patch = (url: string, data?: any, options?: ApiOptions) => {
        return makeRequest('patch', url, data, {
            showSuccessMessage: true,
            ...options,
        });
    };

    const del = (url: string, data?: any, options?: ApiOptions) => {
        return makeRequest('delete', url, data, {
            showSuccessMessage: true,
            successMessage: 'Item deleted successfully',
            ...options,
        });
    };

    // Axios-style API for direct HTTP requests (for API endpoints)
    const api = {
        async get(url: string, config?: any) {
            const loadingKey = `api-${url}`;
            try {
                setLoading(loadingKey, true);
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...config?.headers,
                    },
                    ...config,
                });

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: ${response.statusText}`,
                    );
                }

                return await response.json();
            } catch (error) {
                handleError(error, 'API Request');
                throw error;
            } finally {
                setLoading(loadingKey, false);
            }
        },

        async post(url: string, data?: any, config?: any) {
            const loadingKey = `api-${url}`;
            try {
                setLoading(loadingKey, true);
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                        ...config?.headers,
                    },
                    body: JSON.stringify(data),
                    ...config,
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    const error = new Error(
                        errorData.message ||
                            `HTTP ${response.status}: ${response.statusText}`,
                    );
                    (error as any).response = {
                        status: response.status,
                        data: errorData,
                    };
                    throw error;
                }

                const result = await response.json();

                if (config?.showSuccessMessage !== false) {
                    handleSuccess(
                        config?.successMessage ||
                            'Operation completed successfully',
                    );
                }

                return result;
            } catch (error) {
                handleError(error, 'API Request');
                throw error;
            } finally {
                setLoading(loadingKey, false);
            }
        },

        async put(url: string, data?: any, config?: any) {
            return this.post(url, data, { ...config, method: 'PUT' });
        },

        async patch(url: string, data?: any, config?: any) {
            return this.post(url, data, { ...config, method: 'PATCH' });
        },

        async delete(url: string, config?: any) {
            const loadingKey = `api-${url}`;
            try {
                setLoading(loadingKey, true);
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                        ...config?.headers,
                    },
                    ...config,
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    const error = new Error(
                        errorData.message ||
                            `HTTP ${response.status}: ${response.statusText}`,
                    );
                    (error as any).response = {
                        status: response.status,
                        data: errorData,
                    };
                    throw error;
                }

                const result = await response.json();

                if (config?.showSuccessMessage !== false) {
                    handleSuccess(
                        config?.successMessage || 'Item deleted successfully',
                    );
                }

                return result;
            } catch (error) {
                handleError(error, 'API Request');
                throw error;
            } finally {
                setLoading(loadingKey, false);
            }
        },
    };

    return {
        get,
        post,
        put,
        patch,
        delete: del,
        api,
    };
}

// Global API instance
export const globalApi = useApi();
