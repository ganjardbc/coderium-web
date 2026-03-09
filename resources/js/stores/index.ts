/**
 * Unified State Management Setup
 *
 * This file sets up Pinia stores for the enhanced classroom frontend,
 * providing centralized state management for modules, assignments, courses,
 * progress tracking, and UI state.
 */

import { createPinia } from 'pinia';
import type { App } from 'vue';

// Store imports
export { useModuleLibraryStore } from './moduleLibrary';
export { useAssignmentStore } from './assignment';
export { useCourseStore } from './course';
export { useUnifiedProgressStore } from './unifiedProgress';
export { useUIStateStore } from './uiState';
export { useUserStore } from './user';

// Create and configure Pinia instance
export const pinia = createPinia();

// Plugin to install Pinia in Vue app
export function setupStores(app: App): void {
  app.use(pinia);
}

// Store initialization function
export async function initializeStores(): Promise<void> {
  // Import stores to trigger initialization
  const { useModuleLibraryStore } = await import('./moduleLibrary');
  const { useAssignmentStore } = await import('./assignment');
  const { useCourseStore } = await import('./course');
  const { useUnifiedProgressStore } = await import('./unifiedProgress');
  const { useUIStateStore } = await import('./uiState');
  const { useUserStore } = await import('./user');

  // Initialize stores with default data
  const moduleLibraryStore = useModuleLibraryStore();
  const assignmentStore = useAssignmentStore();
  const courseStore = useCourseStore();
  const progressStore = useUnifiedProgressStore();
  const uiStore = useUIStateStore();
  const userStore = useUserStore();

  // Initialize stores in dependency order
  await userStore.initialize();
  await moduleLibraryStore.initialize();
  await assignmentStore.initialize();
  await courseStore.initialize();
  await progressStore.initialize();
  await uiStore.initialize();
}

// Global error handler for store operations
export function handleStoreError(error: any, context: string): void {
  console.error(`Store Error [${context}]:`, error);

  // You can integrate with your global error handling system here
  if (typeof window !== 'undefined' && (window as any).globalErrorHandler) {
    (window as any).globalErrorHandler.handleError(error, context);
  }
}

// Store synchronization utilities
export class StoreSynchronizer {
  private static instance: StoreSynchronizer;
  private syncQueue: Array<() => Promise<void>> = [];
  private isProcessing = false;

  static getInstance(): StoreSynchronizer {
    if (!StoreSynchronizer.instance) {
      StoreSynchronizer.instance = new StoreSynchronizer();
    }
    return StoreSynchronizer.instance;
  }

  async queueSync(syncFn: () => Promise<void>): Promise<void> {
    this.syncQueue.push(syncFn);
    if (!this.isProcessing) {
      await this.processQueue();
    }
  }

  private async processQueue(): Promise<void> {
    this.isProcessing = true;

    while (this.syncQueue.length > 0) {
      const syncFn = this.syncQueue.shift();
      if (syncFn) {
        try {
          await syncFn();
        } catch (error) {
          handleStoreError(error, 'Store Synchronization');
        }
      }
    }

    this.isProcessing = false;
  }
}

// Cross-store communication utilities
export class StoreEventBus {
  private static instance: StoreEventBus;
  private listeners: Map<string, Array<(data: any) => void>> = new Map();

  static getInstance(): StoreEventBus {
    if (!StoreEventBus.instance) {
      StoreEventBus.instance = new StoreEventBus();
    }
    return StoreEventBus.instance;
  }

  on(event: string, callback: (data: any) => void): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }
    this.listeners.get(event)!.push(callback);
  }

  off(event: string, callback: (data: any) => void): void {
    const eventListeners = this.listeners.get(event);
    if (eventListeners) {
      const index = eventListeners.indexOf(callback);
      if (index > -1) {
        eventListeners.splice(index, 1);
      }
    }
  }

  emit(event: string, data?: any): void {
    const eventListeners = this.listeners.get(event);
    if (eventListeners) {
      eventListeners.forEach(callback => {
        try {
          callback(data);
        } catch (error) {
          handleStoreError(error, `Event: ${event}`);
        }
      });
    }
  }
}

// Store state persistence utilities
export class StatePersistence {
  private static readonly STORAGE_KEY = 'enhanced_classroom_state';
  private static readonly VERSION = '1.0.0';

  static saveState(storeId: string, state: any): void {
    try {
      const existingData = this.loadAllState();
      existingData[storeId] = {
        version: this.VERSION,
        timestamp: Date.now(),
        data: state
      };

      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(existingData));
    } catch (error) {
      console.warn('Failed to save state to localStorage:', error);
    }
  }

  static loadState<T>(storeId: string): T | null {
    try {
      const allState = this.loadAllState();
      const storeState = allState[storeId];

      if (storeState && storeState.version === this.VERSION) {
        return storeState.data;
      }

      return null;
    } catch (error) {
      console.warn('Failed to load state from localStorage:', error);
      return null;
    }
  }

  static clearState(storeId?: string): void {
    try {
      if (storeId) {
        const allState = this.loadAllState();
        delete allState[storeId];
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(allState));
      } else {
        localStorage.removeItem(this.STORAGE_KEY);
      }
    } catch (error) {
      console.warn('Failed to clear state from localStorage:', error);
    }
  }

  private static loadAllState(): Record<string, any> {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      return stored ? JSON.parse(stored) : {};
    } catch (error) {
      console.warn('Failed to parse stored state:', error);
      return {};
    }
  }
}
