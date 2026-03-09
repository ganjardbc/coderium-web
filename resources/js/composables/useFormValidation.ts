import { ref, computed, watch, type Ref } from 'vue';
import { globalNotifications } from './useNotifications';

export type ValidationRule<T = any> = (value: T) => string | null;
export type ValidationRules<T extends Record<string, any>> = {
    [K in keyof T]?: ValidationRule<T[K]>[];
};

export interface ValidationOptions {
    validateOnChange?: boolean;
    validateOnBlur?: boolean;
    showNotifications?: boolean;
    debounceMs?: number;
}

export interface FieldValidation {
    error: string | null;
    isValid: boolean;
    isDirty: boolean;
    isTouched: boolean;
}

export interface FormValidation<T extends Record<string, any>> {
    fields: Record<keyof T, FieldValidation>;
    isValid: boolean;
    isDirty: boolean;
    hasErrors: boolean;
    errorCount: number;
}

// Common validation rules
export const validationRules = {
    required: (message = 'This field is required'): ValidationRule =>
        (value: any) => {
            if (value === null || value === undefined || value === '' ||
                (Array.isArray(value) && value.length === 0)) {
                return message;
            }
            return null;
        },

    minLength: (min: number, message?: string): ValidationRule<string> =>
        (value: string) => {
            if (value && value.length < min) {
                return message || `Must be at least ${min} characters`;
            }
            return null;
        },

    maxLength: (max: number, message?: string): ValidationRule<string> =>
        (value: string) => {
            if (value && value.length > max) {
                return message || `Must be no more than ${max} characters`;
            }
            return null;
        },

    email: (message = 'Please enter a valid email address'): ValidationRule<string> =>
        (value: string) => {
            if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return message;
            }
            return null;
        },

    url: (message = 'Please enter a valid URL'): ValidationRule<string> =>
        (value: string) => {
            if (value) {
                try {
                    new URL(value);
                } catch {
                    return message;
                }
            }
            return null;
        },

    numeric: (message = 'Please enter a valid number'): ValidationRule =>
        (value: any) => {
            if (value !== null && value !== undefined && value !== '' && isNaN(Number(value))) {
                return message;
            }
            return null;
        },

    min: (min: number, message?: string): ValidationRule<number> =>
        (value: number) => {
            if (value !== null && value !== undefined && value < min) {
                return message || `Must be at least ${min}`;
            }
            return null;
        },

    max: (max: number, message?: string): ValidationRule<number> =>
        (value: number) => {
            if (value !== null && value !== undefined && value > max) {
                return message || `Must be no more than ${max}`;
            }
            return null;
        },

    pattern: (regex: RegExp, message = 'Invalid format'): ValidationRule<string> =>
        (value: string) => {
            if (value && !regex.test(value)) {
                return message;
            }
            return null;
        },

    custom: <T>(validator: (value: T) => boolean, message: string): ValidationRule<T> =>
        (value: T) => {
            if (!validator(value)) {
                return message;
            }
            return null;
        },

    // Async validation rule
    async: <T>(
        validator: (value: T) => Promise<boolean>,
        message: string
    ): ValidationRule<T> => {
        // This would need special handling in the validation system
        return (value: T) => {
            // For now, return null and handle async validation separately
            return null;
        };
    }
};

export function useFormValidation<T extends Record<string, any>>(
    formData: Ref<T>,
    rules: ValidationRules<T>,
    options: ValidationOptions = {}
) {
    const {
        validateOnChange = true,
        validateOnBlur = true,
        showNotifications = false,
        debounceMs = 300
    } = options;

    // Initialize field validation state
    const fieldValidations = ref<Record<keyof T, FieldValidation>>({} as any);
    const isValidating = ref(false);
    const asyncValidationPromises = ref<Map<keyof T, Promise<any>>>(new Map());

    // Initialize field validations
    for (const field in formData.value) {
        fieldValidations.value[field] = {
            error: null,
            isValid: true,
            isDirty: false,
            isTouched: false
        };
    }

    // Debounced validation function
    let validationTimeouts: Record<string, NodeJS.Timeout> = {};

    const validateField = async (field: keyof T, value: any, immediate = false): Promise<string | null> => {
        const fieldRules = rules[field] || [];

        // Clear existing timeout
        if (validationTimeouts[field as string]) {
            clearTimeout(validationTimeouts[field as string]);
        }

        const runValidation = async () => {
            isValidating.value = true;

            try {
                // Run synchronous validations first
                for (const rule of fieldRules) {
                    const error = rule(value);
                    if (error) {
                        fieldValidations.value[field].error = error;
                        fieldValidations.value[field].isValid = false;
                        return error;
                    }
                }

                // Field is valid
                fieldValidations.value[field].error = null;
                fieldValidations.value[field].isValid = true;
                return null;
            } finally {
                isValidating.value = false;
            }
        };

        if (immediate || debounceMs === 0) {
            return await runValidation();
        } else {
            return new Promise((resolve) => {
                validationTimeouts[field as string] = setTimeout(async () => {
                    const result = await runValidation();
                    resolve(result);
                }, debounceMs);
            });
        }
    };

    const validateAll = async (): Promise<boolean> => {
        isValidating.value = true;
        let hasErrors = false;

        try {
            const validationPromises = Object.keys(formData.value).map(async (field) => {
                const error = await validateField(field as keyof T, formData.value[field], true);
                if (error) {
                    hasErrors = true;
                }
                return { field, error };
            });

            await Promise.all(validationPromises);

            if (hasErrors && showNotifications) {
                const errorFields = Object.entries(fieldValidations.value)
                    .filter(([_, validation]) => (validation as any).error)
                    .map(([field, validation]) => `${field}: ${(validation as any).error}`)
                    .join(', ');

                globalNotifications.error(
                    'Validation Error',
                    `Please fix the following errors: ${errorFields}`,
                    { duration: 8000 }
                );
            }

            return !hasErrors;
        } finally {
            isValidating.value = false;
        }
    };

    const markFieldAsTouched = (field: keyof T) => {
        fieldValidations.value[field].isTouched = true;
    };

    const markFieldAsDirty = (field: keyof T) => {
        fieldValidations.value[field].isDirty = true;
    };

    const clearFieldError = (field: keyof T) => {
        fieldValidations.value[field].error = null;
        fieldValidations.value[field].isValid = true;
    };

    const clearAllErrors = () => {
        for (const field in fieldValidations.value) {
            clearFieldError(field);
        }
    };

    const resetValidation = () => {
        for (const field in fieldValidations.value) {
            fieldValidations.value[field] = {
                error: null,
                isValid: true,
                isDirty: false,
                isTouched: false
            };
        }
    };

    // Watch form data changes
    if (validateOnChange) {
        watch(
            formData,
            (newData, oldData) => {
                for (const field in newData) {
                    if (newData[field] !== oldData?.[field]) {
                        markFieldAsDirty(field as keyof T);
                        if (fieldValidations.value[field].isTouched) {
                            validateField(field as keyof T, newData[field]);
                        }
                    }
                }
            },
            { deep: true }
        );
    }

    // Computed properties
    const formValidation = computed<FormValidation<T>>(() => {
        const fields = fieldValidations.value;
        const fieldEntries = Object.entries(fields) as [keyof T, FieldValidation][];

        return {
            fields,
            isValid: fieldEntries.every(([_, validation]) => validation.isValid),
            isDirty: fieldEntries.some(([_, validation]) => validation.isDirty),
            hasErrors: fieldEntries.some(([_, validation]) => validation.error !== null),
            errorCount: fieldEntries.filter(([_, validation]) => validation.error !== null).length
        };
    });

    const firstError = computed(() => {
        const fieldEntries = Object.entries(fieldValidations.value) as [keyof T, FieldValidation][];
        const firstErrorField = fieldEntries.find(([_, validation]) => validation.error);
        return firstErrorField ? {
            field: firstErrorField[0],
            error: firstErrorField[1].error
        } : null;
    });

    // Utility functions
    const getFieldError = (field: keyof T): string | null => {
        return fieldValidations.value[field]?.error || null;
    };

    const isFieldValid = (field: keyof T): boolean => {
        return fieldValidations.value[field]?.isValid ?? true;
    };

    const isFieldTouched = (field: keyof T): boolean => {
        return fieldValidations.value[field]?.isTouched ?? false;
    };

    const isFieldDirty = (field: keyof T): boolean => {
        return fieldValidations.value[field]?.isDirty ?? false;
    };

    // Cleanup
    const cleanup = () => {
        Object.values(validationTimeouts).forEach(timeout => clearTimeout(timeout));
        validationTimeouts = {};
    };

    return {
        // State
        formValidation,
        isValidating,
        firstError,

        // Methods
        validateField,
        validateAll,
        markFieldAsTouched,
        markFieldAsDirty,
        clearFieldError,
        clearAllErrors,
        resetValidation,

        // Utilities
        getFieldError,
        isFieldValid,
        isFieldTouched,
        isFieldDirty,
        cleanup
    };
}

// Helper function to create validation rules for common patterns
export function createValidationRules<T extends Record<string, any>>(
    schema: {
        [K in keyof T]?: {
            required?: boolean | string;
            minLength?: number | { value: number; message?: string };
            maxLength?: number | { value: number; message?: string };
            email?: boolean | string;
            url?: boolean | string;
            numeric?: boolean | string;
            min?: number | { value: number; message?: string };
            max?: number | { value: number; message?: string };
            pattern?: { regex: RegExp; message?: string };
            custom?: Array<{ validator: (value: T[K]) => boolean; message: string }>;
        };
    }
): ValidationRules<T> {
    const rules: ValidationRules<T> = {};

    for (const [field, constraints] of Object.entries(schema)) {
        const fieldRules: ValidationRule[] = [];

        if (constraints.required) {
            const message = typeof constraints.required === 'string' ? constraints.required : undefined;
            fieldRules.push(validationRules.required(message));
        }

        if (constraints.minLength) {
            const config = typeof constraints.minLength === 'number'
                ? { value: constraints.minLength }
                : constraints.minLength;
            fieldRules.push(validationRules.minLength(config.value, config.message));
        }

        if (constraints.maxLength) {
            const config = typeof constraints.maxLength === 'number'
                ? { value: constraints.maxLength }
                : constraints.maxLength;
            fieldRules.push(validationRules.maxLength(config.value, config.message));
        }

        if (constraints.email) {
            const message = typeof constraints.email === 'string' ? constraints.email : undefined;
            fieldRules.push(validationRules.email(message));
        }

        if (constraints.url) {
            const message = typeof constraints.url === 'string' ? constraints.url : undefined;
            fieldRules.push(validationRules.url(message));
        }

        if (constraints.numeric) {
            const message = typeof constraints.numeric === 'string' ? constraints.numeric : undefined;
            fieldRules.push(validationRules.numeric(message));
        }

        if (constraints.min) {
            const config = typeof constraints.min === 'number'
                ? { value: constraints.min }
                : constraints.min;
            fieldRules.push(validationRules.min(config.value, config.message));
        }

        if (constraints.max) {
            const config = typeof constraints.max === 'number'
                ? { value: constraints.max }
                : constraints.max;
            fieldRules.push(validationRules.max(config.value, config.message));
        }

        if (constraints.pattern) {
            fieldRules.push(validationRules.pattern(constraints.pattern.regex, constraints.pattern.message));
        }

        if (constraints.custom) {
            constraints.custom.forEach(({ validator, message }: any) => {
                fieldRules.push(validationRules.custom(validator, message));
            });
        }

        if (fieldRules.length > 0) {
            rules[field as keyof T] = fieldRules;
        }
    }

    return rules;
}
