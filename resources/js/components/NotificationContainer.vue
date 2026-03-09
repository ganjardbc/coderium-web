<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { globalNotifications } from '@/composables/useNotifications';
import type { NotificationAction } from '@/composables/useNotifications';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle,
    Info,
    X,
} from 'lucide-vue-next';

const { notifications, removeNotification } = globalNotifications;

const getIcon = (type: string) => {
    switch (type) {
        case 'success':
            return CheckCircle;
        case 'error':
            return AlertCircle;
        case 'warning':
            return AlertTriangle;
        case 'info':
        default:
            return Info;
    }
};

const getColorClasses = (type: string) => {
    switch (type) {
        case 'success':
            return 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200';
        case 'error':
            return 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200';
        case 'warning':
            return 'border-yellow-200 bg-yellow-50 text-yellow-800 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-200';
        case 'info':
        default:
            return 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200';
    }
};

const getIconColorClasses = (type: string) => {
    switch (type) {
        case 'success':
            return 'text-green-500';
        case 'error':
            return 'text-red-500';
        case 'warning':
            return 'text-yellow-500';
        case 'info':
        default:
            return 'text-blue-500';
    }
};

const handleAction = (action: NotificationAction, notificationId: string) => {
    action.action();
    if (action.variant !== 'outline') {
        removeNotification(notificationId);
    }
};
</script>

<template>
    <div class="fixed top-4 right-4 z-50 w-full max-w-sm space-y-2">
        <TransitionGroup name="notification" tag="div" class="space-y-2">
            <Card
                v-for="notification in notifications"
                :key="notification.id"
                :class="[
                    'border p-4 shadow-lg',
                    getColorClasses(notification.type),
                ]"
            >
                <div class="flex items-start gap-3">
                    <component
                        :is="getIcon(notification.type)"
                        :class="[
                            'mt-0.5 h-5 w-5 flex-shrink-0',
                            getIconColorClasses(notification.type),
                        ]"
                    />

                    <div class="min-w-0 flex-1">
                        <h4 class="text-sm font-medium">
                            {{ notification.title }}
                        </h4>
                        <p
                            v-if="notification.message"
                            class="mt-1 text-sm opacity-90 whitespace-pre-line"
                        >
                            {{ notification.message }}
                        </p>

                        <!-- Actions -->
                        <div
                            v-if="notification.actions && notification.actions.length > 0"
                            class="mt-3 flex gap-2"
                        >
                            <Button
                                v-for="action in notification.actions"
                                :key="action.label"
                                @click="handleAction(action, notification.id)"
                                :variant="action.variant || 'outline'"
                                size="sm"
                                class="text-xs"
                            >
                                {{ action.label }}
                            </Button>
                        </div>
                    </div>

                    <Button
                        v-if="notification.dismissible !== false"
                        @click="removeNotification(notification.id)"
                        variant="ghost"
                        size="sm"
                        class="h-6 w-6 p-0 hover:bg-black/10 dark:hover:bg-white/10"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </Card>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.notification-enter-active,
.notification-leave-active {
    transition: all 0.3s ease;
}

.notification-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.notification-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.notification-move {
    transition: transform 0.3s ease;
}
</style>
