import { ref, computed, watch, type Ref } from 'vue';
import { globalNotifications } from './useNotifications';
import { globalErrorHandler } from './useErrorHandler';

export interface OptimisticUpdate<T = any> {
    id: string;
    operation: 'create' | 'update' | 'delete';
    entityType: string;
    entityId: string;
    originalData?: T;
    optimisticData: T;
    timestamp: Date;
    rollbackFn: () => void;
    retryFn: () => Promise<void>;
}

export interface DataConflict<T = any> {
    id: string;
    entityType: string;
    entityId: string;
    localVersion: T;
    serverVersion: T;
    conflictFields: string[];
    timestamp: Date;
    resolutionOptions: ConflictResolution<T>[];
}

export interface ConflictResolution<T = any> {
    id: string;
    label: string;
    description: string;
    action: 'use_local' | 'use_server' | 'merge' | 'custom';
    mergedData?: T;
    customResolver?: (local: T, server: T) => T;
}

export interface CacheEntry<T = any> {
    data: T;
    timestamp: Date;
    version: number;
    etag?: string;
    expiresAt?: Date;
    dependencies?: string[];
}

export interface SyncOperation {
    id: string;
    type: 'sync' | 'invalidate' | 'update';
    entityType: string;
    entityId?: string;
    data?: any;
    timestamp: Date;
    status: 'pending' | 'completed' | 'failed';
    retryCount: number;
}

// Global state for data consistency
const optimisticUpdates = ref<Map<string, OptimisticUpdate>>(new Map());
const dataConflicts = ref<Map<string, DataConflict>>(new Map());
const dataCache = ref<Map<string, CacheEntry>>(new Map());
const syncQueue = ref<SyncOperation[]>([]);
const isProcessingSyncQueue = ref(false);

export function useDataConsistency() {
    const { handleError, networkStatus } = globalErrorHandler;

    // Cache management
    const getCacheKey = (entityType: string, entityId?: string, params?: Record<string, any>): string => {
        const baseKey = entityId ? `${entityType}:${entityId}` : entityType;
        if (params) {
            const paramString = Object.entries(params)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([key, value]) => `${key}=${JSON.stringify(value)}`)
                .join('&');
            return `${baseKey}?${paramString}`;
        }
        return baseKey;
    };

    const setCacheEntry = <T>(
        key: string,
        data: T,
        options: {
            ttl?: number;
            version?: number;
            etag?: string;
            dependencies?: string[]
        } = {}
    ): void => {
        const { ttl = 300000, version = 1, etag, dependencies } = options; // 5 minutes default TTL

        dataCache.value.set(key, {
            data,
            timestamp: new Date(),
            version,
            etag,
            expiresAt: new Date(Date.now() + ttl),
            dependencies
        });
    };

    const getCacheEntry = <T>(key: string): CacheEntry<T> | null => {
        const entry = dataCache.value.get(key);
        if (!entry) return null;

        // Check if expired
        if (entry.expiresAt && entry.expiresAt < new Date()) {
            dataCache.value.delete(key);
            return null;
        }

        return entry as CacheEntry<T>;
    };

    const invalidateCache = (pattern: string | RegExp): void => {
        const keysToDelete: string[] = [];

        for (const [key] of dataCache.value) {
            if (typeof pattern === 'string') {
                if (key.includes(pattern)) {
                    keysToDelete.push(key);
                }
            } else {
                if (pattern.test(key)) {
                    keysToDelete.push(key);
                }
            }
        }

        keysToDelete.forEach(key => dataCache.value.delete(key));
    };

    const invalidateDependencies = (dependency: string): void => {
        const keysToDelete: string[] = [];

        for (const [key, entry] of dataCache.value) {
            if (entry.dependencies?.includes(dependency)) {
                keysToDelete.push(key);
            }
        }

        keysToDelete.forEach(key => dataCache.value.delete(key));
    };

    // Optimistic updates
    const createOptimisticUpdate = <T>(
        operation: OptimisticUpdate<T>['operation'],
        entityType: string,
        entityId: string,
        optimisticData: T,
        originalData: T | undefined,
        rollbackFn: () => void,
        retryFn: () => Promise<void>
    ): string => {
        const id = `${entityType}:${entityId}:${Date.now()}`;

        const update: OptimisticUpdate<T> = {
            id,
            operation,
            entityType,
            entityId,
            originalData,
            optimisticData,
            timestamp: new Date(),
            rollbackFn,
            retryFn
        };

        optimisticUpdates.value.set(id, update);

        // Auto-cleanup after 30 seconds if not resolved
        setTimeout(() => {
            if (optimisticUpdates.value.has(id)) {
                rollbackOptimisticUpdate(id);
            }
        }, 30000);

        return id;
    };

    const confirmOptimisticUpdate = (updateId: string): void => {
        optimisticUpdates.value.delete(updateId);
    };

    const rollbackOptimisticUpdate = (updateId: string): void => {
        const update = optimisticUpdates.value.get(updateId);
        if (update) {
            update.rollbackFn();
            optimisticUpdates.value.delete(updateId);

            globalNotifications.warning(
                'Update Rolled Back',
                `Changes to ${update.entityType} were rolled back due to a server error.`,
                { duration: 5000 }
            );
        }
    };

    const retryOptimisticUpdate = async (updateId: string): Promise<void> => {
        const update = optimisticUpdates.value.get(updateId);
        if (update) {
            try {
                await update.retryFn();
                confirmOptimisticUpdate(updateId);
            } catch (error) {
                handleError(error, `Retry ${update.entityType} update`);
            }
        }
    };

    // Conflict resolution
    const detectConflict = <T>(
        entityType: string,
        entityId: string,
        localData: T,
        serverData: T,
        compareFields?: string[]
    ): DataConflict<T> | null => {
        const conflictFields: string[] = [];
        const fieldsToCheck = compareFields || Object.keys(localData as any);

        for (const field of fieldsToCheck) {
            const localValue = (localData as any)[field];
            const serverValue = (serverData as any)[field];

            if (JSON.stringify(localValue) !== JSON.stringify(serverValue)) {
                conflictFields.push(field);
            }
        }

        if (conflictFields.length === 0) {
            return null;
        }

        const conflictId = `${entityType}:${entityId}:${Date.now()}`;
        const conflict: DataConflict<T> = {
            id: conflictId,
            entityType,
            entityId,
            localVersion: localData,
            serverVersion: serverData,
            conflictFields,
            timestamp: new Date(),
            resolutionOptions: generateResolutionOptions(localData, serverData, conflictFields)
        };

        dataConflicts.value.set(conflictId, conflict);
        return conflict;
    };

    const generateResolutionOptions = <T>(
        localData: T,
        serverData: T,
        conflictFields: string[]
    ): ConflictResolution<T>[] => {
        const options: ConflictResolution<T>[] = [
            {
                id: 'use_local',
                label: 'Keep Local Changes',
                description: 'Use your local changes and overwrite server data',
                action: 'use_local'
            },
            {
                id: 'use_server',
                label: 'Use Server Version',
                description: 'Discard local changes and use server data',
                action: 'use_server'
            }
        ];

        // Add merge option if possible
        if (conflictFields.length > 1) {
            const mergedData = { ...serverData };
            // Simple merge strategy - could be enhanced
            for (const field of conflictFields) {
                if ((localData as any)[field] !== undefined) {
                    (mergedData as any)[field] = (localData as any)[field];
                }
            }

            options.push({
                id: 'merge',
                label: 'Merge Changes',
                description: 'Combine local and server changes where possible',
                action: 'merge',
                mergedData
            });
        }

        return options;
    };

    const resolveConflict = async <T>(
        conflictId: string,
        resolutionId: string,
        customData?: T
    ): Promise<T | null> => {
        const conflict = dataConflicts.value.get(conflictId);
        if (!conflict) return null;

        const resolution = conflict.resolutionOptions.find(r => r.id === resolutionId);
        if (!resolution) return null;

        let resolvedData: T;

        switch (resolution.action) {
            case 'use_local':
                resolvedData = conflict.localVersion;
                break;
            case 'use_server':
                resolvedData = conflict.serverVersion;
                break;
            case 'merge':
                resolvedData = resolution.mergedData || conflict.serverVersion;
                break;
            case 'custom':
                if (customData) {
                    resolvedData = customData;
                } else if (resolution.customResolver) {
                    resolvedData = resolution.customResolver(conflict.localVersion, conflict.serverVersion);
                } else {
                    resolvedData = conflict.serverVersion;
                }
                break;
            default:
                resolvedData = conflict.serverVersion;
        }

        // Remove conflict from tracking
        dataConflicts.value.delete(conflictId);

        // Invalidate related cache entries
        invalidateCache(conflict.entityType);

        globalNotifications.success(
            'Conflict Resolved',
            `Data conflict for ${conflict.entityType} has been resolved.`
        );

        return resolvedData;
    };

    // State synchronization
    const addSyncOperation = (operation: Omit<SyncOperation, 'id' | 'timestamp' | 'status' | 'retryCount'>): void => {
        const syncOp: SyncOperation = {
            ...operation,
            id: `sync:${operation.entityType}:${operation.entityId || 'all'}:${Date.now()}`,
            timestamp: new Date(),
            status: 'pending',
            retryCount: 0
        };

        syncQueue.value.push(syncOp);

        if (!isProcessingSyncQueue.value) {
            processSyncQueue();
        }
    };

    const processSyncQueue = async (): Promise<void> => {
        if (isProcessingSyncQueue.value || syncQueue.value.length === 0) {
            return;
        }

        isProcessingSyncQueue.value = true;

        try {
            while (syncQueue.value.length > 0) {
                const operation = syncQueue.value.shift();
                if (!operation) continue;

                try {
                    await processSyncOperation(operation);
                    operation.status = 'completed';
                } catch (error) {
                    operation.status = 'failed';
                    operation.retryCount++;

                    // Retry up to 3 times
                    if (operation.retryCount < 3) {
                        syncQueue.value.unshift(operation);
                        await new Promise(resolve => setTimeout(resolve, 1000 * operation.retryCount));
                    } else {
                        handleError(error, `Sync operation failed: ${operation.entityType}`);
                    }
                }
            }
        } finally {
            isProcessingSyncQueue.value = false;
        }
    };

    const processSyncOperation = async (operation: SyncOperation): Promise<void> => {
        switch (operation.type) {
            case 'sync':
                // Implement sync logic based on entity type
                break;
            case 'invalidate':
                if (operation.entityId) {
                    invalidateCache(`${operation.entityType}:${operation.entityId}`);
                } else {
                    invalidateCache(operation.entityType);
                }
                break;
            case 'update':
                if (operation.data && operation.entityId) {
                    const cacheKey = getCacheKey(operation.entityType, operation.entityId);
                    setCacheEntry(cacheKey, operation.data);
                }
                break;
        }
    };

    // Data validation and integrity
    const validateDataIntegrity = <T>(
        data: T,
        schema: Record<string, (value: any) => boolean>,
        entityType: string
    ): { isValid: boolean; errors: string[] } => {
        const errors: string[] = [];

        for (const [field, validator] of Object.entries(schema)) {
            const value = (data as any)[field];
            if (!validator(value)) {
                errors.push(`Invalid ${field} in ${entityType}`);
            }
        }

        return {
            isValid: errors.length === 0,
            errors
        };
    };

    const ensureDataConsistency = async <T>(
        entityType: string,
        entityId: string,
        localData: T,
        fetchServerData: () => Promise<T>
    ): Promise<{ data: T; hasConflict: boolean; conflictId?: string }> => {
        try {
            const serverData = await fetchServerData();
            const conflict = detectConflict(entityType, entityId, localData, serverData);

            if (conflict) {
                return {
                    data: localData,
                    hasConflict: true,
                    conflictId: conflict.id
                };
            }

            return {
                data: serverData,
                hasConflict: false
            };
        } catch (error) {
            handleError(error, `Data consistency check for ${entityType}`);
            return {
                data: localData,
                hasConflict: false
            };
        }
    };

    // Computed properties
    const pendingOptimisticUpdates = computed(() =>
        Array.from(optimisticUpdates.value.values())
    );

    const activeConflicts = computed(() =>
        Array.from(dataConflicts.value.values())
    );

    const pendingSyncOperations = computed(() =>
        syncQueue.value.filter(op => op.status === 'pending')
    );

    const cacheStats = computed(() => ({
        totalEntries: dataCache.value.size,
        expiredEntries: Array.from(dataCache.value.values()).filter(
            entry => entry.expiresAt && entry.expiresAt < new Date()
        ).length
    }));

    // Cleanup expired cache entries
    const cleanupExpiredCache = (): void => {
        const now = new Date();
        const keysToDelete: string[] = [];

        for (const [key, entry] of dataCache.value) {
            if (entry.expiresAt && entry.expiresAt < now) {
                keysToDelete.push(key);
            }
        }

        keysToDelete.forEach(key => dataCache.value.delete(key));
    };

    // Auto-cleanup every 5 minutes
    setInterval(cleanupExpiredCache, 5 * 60 * 1000);

    return {
        // Cache management
        getCacheKey,
        setCacheEntry,
        getCacheEntry,
        invalidateCache,
        invalidateDependencies,

        // Optimistic updates
        createOptimisticUpdate,
        confirmOptimisticUpdate,
        rollbackOptimisticUpdate,
        retryOptimisticUpdate,

        // Conflict resolution
        detectConflict,
        resolveConflict,

        // State synchronization
        addSyncOperation,
        processSyncQueue,

        // Data validation
        validateDataIntegrity,
        ensureDataConsistency,

        // State
        pendingOptimisticUpdates,
        activeConflicts,
        pendingSyncOperations,
        cacheStats,

        // Utilities
        cleanupExpiredCache
    };
}
