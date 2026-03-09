import { ref } from 'vue';

export interface NotificationAction {
    label: string;
    action: () => void;
    variant?: 'default' | 'destructive' | 'outline';
}

export interface Notification {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    title: string;
    message?: string;
    duration?: number;
    persistent?: boolean;
    actions?: NotificationAction[];
    dismissible?: boolean;
}

const notifications = ref<Notification[]>([]);

export function useNotifications() {
    const addNotification = (notification: Omit<Notification, 'id'>) => {
        const id = Math.random().toString(36).substr(2, 9);
        const newNotification: Notification = {
            id,
            duration: 5000,
            persistent: false,
            dismissible: true,
            ...notification,
        };

        notifications.value.push(newNotification);

        // Auto-remove non-persistent notifications
        if (!newNotification.persistent && newNotification.duration) {
            setTimeout(() => {
                removeNotification(id);
            }, newNotification.duration);
        }

        return id;
    };

    const removeNotification = (id: string) => {
        const index = notifications.value.findIndex((n) => n.id === id);
        if (index > -1) {
            notifications.value.splice(index, 1);
        }
    };

    const clearAll = () => {
        notifications.value = [];
    };

    // Convenience methods
    const success = (
        title: string,
        message?: string,
        options?: Partial<Notification>,
    ) => {
        return addNotification({ type: 'success', title, message, ...options });
    };

    const error = (
        title: string,
        message?: string,
        options?: Partial<Notification>,
    ) => {
        return addNotification({ type: 'error', title, message, ...options });
    };

    const warning = (
        title: string,
        message?: string,
        options?: Partial<Notification>,
    ) => {
        return addNotification({ type: 'warning', title, message, ...options });
    };

    const info = (
        title: string,
        message?: string,
        options?: Partial<Notification>,
    ) => {
        return addNotification({ type: 'info', title, message, ...options });
    };

    return {
        notifications,
        addNotification,
        removeNotification,
        clearAll,
        success,
        error,
        warning,
        info,
    };
}

// Global notification instance
export const globalNotifications = useNotifications();
