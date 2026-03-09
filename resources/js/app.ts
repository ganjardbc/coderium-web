import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { setupStores, initializeStores } from './stores';
import { performanceMonitor } from './utils/performanceMonitor';

// Start performance monitoring
performanceMonitor.startMonitoring();

createInertiaApp({
    title: (title) => {
        const appName = (window as any).appName || 'Coderium';
        return title ? `${title} - ${appName}` : appName;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin);

        // Setup Pinia stores
        setupStores(app);

        // Add global error handler
        app.config.errorHandler = (error, instance, info) => {
            console.error('Vue error:', error, info);

            // Record performance metric for errors
            performanceMonitor.recordMetric({
                name: 'vue_error',
                value: 1,
                timestamp: Date.now(),
                category: 'interaction',
                metadata: {
                    error: error.message,
                    info,
                    component: instance?.$options.name || 'unknown'
                }
            });
        };

        // Mount the app
        app.mount(el);

        // Initialize stores after mounting
        initializeStores().catch(error => {
            console.error('Failed to initialize stores:', error);

            performanceMonitor.recordMetric({
                name: 'store_initialization_error',
                value: 1,
                timestamp: Date.now(),
                category: 'load',
                metadata: {
                    error: error.message
                }
            });
        });

        // Record app initialization time
        performanceMonitor.recordMetric({
            name: 'app_initialization',
            value: performance.now(),
            timestamp: Date.now(),
            category: 'load'
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Performance monitoring for page visibility changes
if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            // Page is hidden, generate performance report
            const report = performanceMonitor.getReport();
            console.log('Performance report (page hidden):', report);
        }
    });
}
