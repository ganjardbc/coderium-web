import { computed, ref } from 'vue';

const loadingStates = ref<Record<string, boolean>>({});

export function useLoading() {
    const setLoading = (key: string, loading: boolean) => {
        if (loading) {
            loadingStates.value[key] = true;
        } else {
            delete loadingStates.value[key];
        }
    };

    const isLoading = (key: string) => {
        return computed(() => loadingStates.value[key] || false);
    };

    const isAnyLoading = computed(() => {
        return Object.keys(loadingStates.value).length > 0;
    });

    const getLoadingKeys = computed(() => {
        return Object.keys(loadingStates.value);
    });

    return {
        setLoading,
        isLoading,
        isAnyLoading,
        getLoadingKeys,
    };
}

// Global loading instance
export const globalLoading = useLoading();
