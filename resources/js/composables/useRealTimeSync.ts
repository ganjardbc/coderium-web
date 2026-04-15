/**
 * Real-Time Synchronization Composable
 *
 * Provides comprehensive WebSocket connection management, real-time data synchronization,
 * conflict detection and resolution, and offline capability with operation queuing.
 */

import { ref, computed, onUnmounted, type Ref, type ComputedRef } from 'vue';
import {
  WebSocketMessage,
  SyncOperation,
  DataConflict,
  WebSocketConfig
} from '@/types/enhanced-classroom';
import { useApi } from './useApi';
import { globalErrorHandler } from './useErrorHandler';
import { useDataConsistency } from './useDataConsistency';

export interface RealTimeSyncComposable {
  // Connection State
  connectionStatus: Ref<'connected' | 'disconnected' | 'reconnecting'>;
  syncQueue: Ref<SyncOperation[]>;
  lastSyncAt: Ref<Date | null>;
  isOnline: Ref<boolean>;

  // Methods
  connect: () => Promise<void>;
  disconnect: () => void;
  syncData: (operation: SyncOperation) => Promise<void>;
  handleConflict: (conflict: DataConflict) => Promise<void>;

  // Event Handlers
  onDataUpdate: (callback: (data: any) => void) => void;
  onConflict: (callback: (conflict: DataConflict) => void) => void;
  onConnectionChange: (callback: (status: string) => void) => void;

  // Utilities
  queueOperation: (operation: SyncOperation) => void;
  processQueue: () => Promise<void>;
  retryFailedOperations: () => Promise<void>;

  // Computed
  pendingOperations: ComputedRef<number>;
  connectionHealth: ComputedRef<'healthy' | 'degraded' | 'offline'>;
}

// Default WebSocket configuration
const DEFAULT_CONFIG: WebSocketConfig = {
  url: '',
  reconnectInterval: 1000,
  maxReconnectAttempts: 5,
  heartbeatInterval: 30000,
  channels: ['progress', 'assignments', 'courses'],
  authToken: undefined
};

// Connection state management
let websocketConnection: WebSocket | null = null;
let reconnectAttempts = 0;
let heartbeatInterval: NodeJS.Timeout | null = null;
let reconnectTimeout: NodeJS.Timeout | null = null;

// Event callbacks
const dataUpdateCallbacks: ((data: any) => void)[] = [];
const conflictCallbacks: ((conflict: DataConflict) => void)[] = [];
const connectionChangeCallbacks: ((status: string) => void)[] = [];

export function useRealTimeSync(config?: Partial<WebSocketConfig>): RealTimeSyncComposable {
  // Merge configuration
  const wsConfig = { ...DEFAULT_CONFIG, ...config };

  // Reactive state
  const connectionStatus = ref<'connected' | 'disconnected' | 'reconnecting'>('disconnected');
  const syncQueue = ref<SyncOperation[]>([]);
  const lastSyncAt = ref<Date | null>(null);
  const isOnline = ref<boolean>(navigator.onLine);

  // API utilities
  const { api } = useApi();
  const { handleError } = globalErrorHandler;
  const {
    createOptimisticUpdate,
    confirmOptimisticUpdate,
    rollbackOptimisticUpdate,
    detectConflict,
    setCacheEntry,
    invalidateCache,
    addSyncOperation
  } = useDataConsistency();

  // Computed properties
  const pendingOperations = computed(() => {
    return syncQueue.value.filter(op => op.status === 'pending').length;
  });

  const connectionHealth = computed((): 'healthy' | 'degraded' | 'offline' => {
    if (!isOnline.value) return 'offline';
    if (connectionStatus.value === 'connected') return 'healthy';
    if (connectionStatus.value === 'reconnecting') return 'degraded';
    return 'offline';
  });

  // WebSocket URL generation
  const generateWebSocketUrl = (): string => {
    if (wsConfig.url) return wsConfig.url;

    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.host;
    const token = wsConfig.authToken || localStorage.getItem('auth_token') || '';

    return `${protocol}//${host}/ws/sync?token=${encodeURIComponent(token)}`;
  };

  // Connection management
  const connect = async (): Promise<void> => {
    if (websocketConnection?.readyState === WebSocket.OPEN) {
      return;
    }

    try {
      const wsUrl = generateWebSocketUrl();
      websocketConnection = new WebSocket(wsUrl);

      connectionStatus.value = 'reconnecting';

      websocketConnection.onopen = handleConnectionOpen;
      websocketConnection.onmessage = handleMessage;
      websocketConnection.onclose = handleConnectionClose;
      websocketConnection.onerror = handleConnectionError;

    } catch (error) {
      console.error('Failed to establish WebSocket connection:', error);
      handleError(error, 'WebSocket Connection');
      connectionStatus.value = 'disconnected';
    }
  };

  const disconnect = (): void => {
    if (heartbeatInterval) {
      clearInterval(heartbeatInterval);
      heartbeatInterval = null;
    }

    if (reconnectTimeout) {
      clearTimeout(reconnectTimeout);
      reconnectTimeout = null;
    }

    if (websocketConnection) {
      websocketConnection.close(1000, 'Manual disconnect');
      websocketConnection = null;
    }

    connectionStatus.value = 'disconnected';
    notifyConnectionChange('disconnected');
  };

  // Connection event handlers
  const handleConnectionOpen = (): void => {
    console.log('WebSocket connected successfully');
    connectionStatus.value = 'connected';
    reconnectAttempts = 0;

    // Start heartbeat
    startHeartbeat();

    // Subscribe to channels
    subscribeToChannels();

    // Process queued operations
    processQueue();

    notifyConnectionChange('connected');
  };

  const handleConnectionClose = (event: CloseEvent): void => {
    console.log('WebSocket connection closed:', event.code, event.reason);
    connectionStatus.value = 'disconnected';

    if (heartbeatInterval) {
      clearInterval(heartbeatInterval);
      heartbeatInterval = null;
    }

    notifyConnectionChange('disconnected');

    // Attempt to reconnect if not a manual disconnect
    if (event.code !== 1000 && reconnectAttempts < wsConfig.maxReconnectAttempts) {
      attemptReconnect();
    }
  };

  const handleConnectionError = (error: Event): void => {
    console.error('WebSocket error:', error);
    handleError(error, 'WebSocket Error');
  };

  const handleMessage = (event: MessageEvent): void => {
    try {
      const message: WebSocketMessage = JSON.parse(event.data);
      processWebSocketMessage(message);
    } catch (error) {
      console.error('Failed to parse WebSocket message:', error);
    }
  };

  // Reconnection logic
  const attemptReconnect = (): void => {
    if (reconnectAttempts >= wsConfig.maxReconnectAttempts) {
      console.log('Max reconnection attempts reached');
      return;
    }

    reconnectAttempts++;
    connectionStatus.value = 'reconnecting';

    const delay = Math.min(
      wsConfig.reconnectInterval * Math.pow(2, reconnectAttempts - 1),
      30000 // Max 30 seconds
    );

    console.log(`Attempting to reconnect in ${delay}ms (attempt ${reconnectAttempts})`);

    reconnectTimeout = setTimeout(() => {
      connect();
    }, delay);
  };

  // Heartbeat management
  const startHeartbeat = (): void => {
    if (heartbeatInterval) {
      clearInterval(heartbeatInterval);
    }

    heartbeatInterval = setInterval(() => {
      if (websocketConnection?.readyState === WebSocket.OPEN) {
        sendMessage({
          type: 'heartbeat',
          payload: { timestamp: new Date() },
          timestamp: new Date(),
          userId: getCurrentUserId(),
          sessionId: getSessionId()
        });
      }
    }, wsConfig.heartbeatInterval);
  };

  // Channel subscription
  const subscribeToChannels = (): void => {
    wsConfig.channels.forEach(channel => {
      sendMessage({
        type: 'subscribe',
        payload: { channel },
        timestamp: new Date(),
        userId: getCurrentUserId(),
        sessionId: getSessionId()
      });
    });
  };

  // Message processing
  const processWebSocketMessage = (message: WebSocketMessage): void => {
    switch (message.type) {
      case 'progress_update':
      case 'assignment_change':
      case 'course_update':
        notifyDataUpdate(message.payload);
        break;

      case 'conflict_detected':
        const conflict = message.payload as DataConflict;
        notifyConflict(conflict);
        break;

      case 'sync_request':
        handleSyncRequest(message.payload);
        break;

      case 'heartbeat_response':
        // Connection is healthy
        break;

      default:
        console.log('Unknown WebSocket message type:', message.type);
    }
  };

  // Data synchronization with optimistic updates
  const syncData = async (operation: SyncOperation): Promise<void> => {
    if (connectionStatus.value !== 'connected') {
      queueOperation(operation);
      return;
    }

    // Create optimistic update
    const optimisticUpdateId = createOptimisticUpdate(
      operation.type,
      operation.entity,
      operation.entityId,
      operation.data,
      undefined, // originalData would need to be passed in
      () => {
        // Rollback function
        invalidateCache(`${operation.entity}:${operation.entityId}`);
      },
      () => syncData(operation) // Retry function
    );

    try {
      operation.status = 'pending';

      // Send operation via WebSocket
      sendMessage({
        type: 'sync_operation',
        payload: operation,
        timestamp: new Date(),
        userId: getCurrentUserId(),
        sessionId: getSessionId()
      });

      // Also sync via HTTP API as backup
      const result = await syncViaApi(operation);

      // Confirm optimistic update
      confirmOptimisticUpdate(optimisticUpdateId);

      // Update cache with server response
      if (result) {
        const cacheKey = `${operation.entity}:${operation.entityId}`;
        setCacheEntry(cacheKey, result);
      }

      operation.status = 'synced';
      lastSyncAt.value = new Date();

      // Add to sync operation queue for consistency tracking
      addSyncOperation({
        type: 'sync',
        entityType: operation.entity,
        entityId: operation.entityId,
        data: operation.data
      });

    } catch (error) {
      operation.status = 'failed';
      operation.retryCount++;

      // Rollback optimistic update
      rollbackOptimisticUpdate(optimisticUpdateId);

      handleError(error, 'Data Sync');

      // Queue for retry if under retry limit
      if (operation.retryCount < 3) {
        queueOperation(operation);
      }
    }
  };

  const syncViaApi = async (operation: SyncOperation): Promise<any> => {
    const endpoint = getApiEndpoint(operation.entity, operation.type);

    switch (operation.type) {
      case 'create':
        return await api.post(endpoint, operation.data);
      case 'update':
        return await api.put(`${endpoint}/${operation.entityId}`, operation.data);
      case 'delete':
        return await api.delete(`${endpoint}/${operation.entityId}`);
      default:
        throw new Error(`Unknown operation type: ${operation.type}`);
    }
  };

  const getApiEndpoint = (entity: string, type: string): string => {
    const endpoints = {
      module: '/api/modules',
      assignment: '/api/assignments',
      progress: '/api/progress',
      course: '/api/v1/courses'
    };

    return endpoints[entity as keyof typeof endpoints] || '/api/sync';
  };

  // Enhanced conflict handling with data consistency integration
  const handleConflict = async (conflict: DataConflict): Promise<void> => {
    try {
      // Use data consistency system to detect and handle conflicts
      const detectedConflict = detectConflict(
        conflict.entityType,
        conflict.entityId,
        conflict.localData,
        conflict.remoteData
      );

      if (detectedConflict) {
        // Let the conflict resolution system handle it
        notifyConflict(detectedConflict);
        return;
      }

      // Apply resolution strategy for simple conflicts
      switch (conflict.resolutionStrategy) {
        case 'local_wins':
          await resolveConflictLocalWins(conflict);
          break;
        case 'remote_wins':
          await resolveConflictRemoteWins(conflict);
          break;
        case 'merge':
          await resolveConflictMerge(conflict);
          break;
        case 'manual':
        default:
          // Let user handle manually
          notifyConflict(conflict);
          return;
      }

    } catch (error) {
      handleError(error, 'Conflict Resolution');
    }
  };

  const resolveConflictLocalWins = async (conflict: DataConflict): Promise<void> => {
    const operation: SyncOperation = {
      id: generateOperationId(),
      type: 'update',
      entity: conflict.entityType as any,
      entityId: conflict.entityId,
      data: conflict.localData,
      timestamp: new Date(),
      userId: getCurrentUserId(),
      status: 'pending',
      retryCount: 0
    };

    await syncData(operation);
  };

  const resolveConflictRemoteWins = async (conflict: DataConflict): Promise<void> => {
    // Update local data with remote version
    notifyDataUpdate({
      type: 'conflict_resolved',
      entityType: conflict.entityType,
      entityId: conflict.entityId,
      data: conflict.remoteData
    });
  };

  const resolveConflictMerge = async (conflict: DataConflict): Promise<void> => {
    // Simple merge strategy - could be enhanced
    const mergedData = { ...conflict.remoteData, ...conflict.localData };

    const operation: SyncOperation = {
      id: generateOperationId(),
      type: 'update',
      entity: conflict.entityType as any,
      entityId: conflict.entityId,
      data: mergedData,
      timestamp: new Date(),
      userId: getCurrentUserId(),
      status: 'pending',
      retryCount: 0
    };

    await syncData(operation);
  };

  // Queue management
  const queueOperation = (operation: SyncOperation): void => {
    // Avoid duplicate operations
    const existingIndex = syncQueue.value.findIndex(
      op => op.entityId === operation.entityId &&
           op.entity === operation.entity &&
           op.type === operation.type
    );

    if (existingIndex >= 0) {
      syncQueue.value[existingIndex] = operation;
    } else {
      syncQueue.value.push(operation);
    }
  };

  const processQueue = async (): Promise<void> => {
    const pendingOps = syncQueue.value.filter(op => op.status === 'pending');

    for (const operation of pendingOps) {
      try {
        await syncData(operation);

        // Remove successful operations from queue
        const index = syncQueue.value.indexOf(operation);
        if (index >= 0 && operation.status === 'synced') {
          syncQueue.value.splice(index, 1);
        }

      } catch (error) {
        console.error('Failed to process queued operation:', error);
      }
    }
  };

  const retryFailedOperations = async (): Promise<void> => {
    const failedOps = syncQueue.value.filter(op => op.status === 'failed');

    for (const operation of failedOps) {
      if (operation.retryCount < 3) {
        operation.status = 'pending';
        await syncData(operation);
      }
    }
  };

  // Event handling
  const onDataUpdate = (callback: (data: any) => void): void => {
    dataUpdateCallbacks.push(callback);
  };

  const onConflict = (callback: (conflict: DataConflict) => void): void => {
    conflictCallbacks.push(callback);
  };

  const onConnectionChange = (callback: (status: string) => void): void => {
    connectionChangeCallbacks.push(callback);
  };

  // Event notification
  const notifyDataUpdate = (data: any): void => {
    dataUpdateCallbacks.forEach(callback => {
      try {
        callback(data);
      } catch (error) {
        console.error('Error in data update callback:', error);
      }
    });
  };

  const notifyConflict = (conflict: DataConflict): void => {
    conflictCallbacks.forEach(callback => {
      try {
        callback(conflict);
      } catch (error) {
        console.error('Error in conflict callback:', error);
      }
    });
  };

  const notifyConnectionChange = (status: string): void => {
    connectionChangeCallbacks.forEach(callback => {
      try {
        callback(status);
      } catch (error) {
        console.error('Error in connection change callback:', error);
      }
    });
  };

  // Utility functions
  const sendMessage = (message: WebSocketMessage): void => {
    if (websocketConnection?.readyState === WebSocket.OPEN) {
      websocketConnection.send(JSON.stringify(message));
    }
  };

  const handleSyncRequest = (payload: any): void => {
    // Handle incoming sync requests from server
    console.log('Received sync request:', payload);
  };

  const getCurrentUserId = (): string => {
    // Get from auth store or localStorage
    return localStorage.getItem('user_id') || 'anonymous';
  };

  const getSessionId = (): string => {
    // Generate or retrieve session ID
    let sessionId = sessionStorage.getItem('session_id');
    if (!sessionId) {
      sessionId = generateSessionId();
      sessionStorage.setItem('session_id', sessionId);
    }
    return sessionId;
  };

  const generateOperationId = (): string => {
    return `op_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  };

  const generateSessionId = (): string => {
    return `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  };

  // Online/offline detection
  const handleOnlineStatus = (): void => {
    isOnline.value = navigator.onLine;

    if (isOnline.value && connectionStatus.value === 'disconnected') {
      // Reconnect when coming back online
      connect();
    }
  };

  // Setup event listeners
  window.addEventListener('online', handleOnlineStatus);
  window.addEventListener('offline', handleOnlineStatus);

  // Cleanup on unmount
  onUnmounted(() => {
    disconnect();
    window.removeEventListener('online', handleOnlineStatus);
    window.removeEventListener('offline', handleOnlineStatus);

    // Clear callbacks
    dataUpdateCallbacks.length = 0;
    conflictCallbacks.length = 0;
    connectionChangeCallbacks.length = 0;
  });

  return {
    // Connection State
    connectionStatus,
    syncQueue,
    lastSyncAt,
    isOnline,

    // Methods
    connect,
    disconnect,
    syncData,
    handleConflict,

    // Event Handlers
    onDataUpdate,
    onConflict,
    onConnectionChange,

    // Utilities
    queueOperation,
    processQueue,
    retryFailedOperations,

    // Computed
    pendingOperations,
    connectionHealth
  };
}

// Global instance for shared WebSocket connection
let globalRealTimeSync: RealTimeSyncComposable | null = null;

export function useGlobalRealTimeSync(config?: Partial<WebSocketConfig>): RealTimeSyncComposable {
  if (!globalRealTimeSync) {
    globalRealTimeSync = useRealTimeSync(config);
  }
  return globalRealTimeSync;
}

// Cleanup function for app unmount
export function cleanupRealTimeSync(): void {
  if (globalRealTimeSync) {
    globalRealTimeSync.disconnect();
    globalRealTimeSync = null;
  }
}
