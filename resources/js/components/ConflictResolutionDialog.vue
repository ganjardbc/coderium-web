<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useDataConsistency, type DataConflict, type ConflictResolution } from '@/composables/useDataConsistency';
import {
    AlertTriangle,
    Check,
    X,
    GitMerge,
    User,
    Server,
    Clock,
    ChevronRight
} from 'lucide-vue-next';

interface Props {
    conflict: DataConflict | null;
    open: boolean;
}

interface Emits {
    'update:open': [value: boolean];
    'resolved': [resolvedData: any];
    'cancelled': [];
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { resolveConflict } = useDataConsistency();

const selectedResolution = ref<string | null>(null);
const isResolving = ref(false);
const showDiff = ref(false);

const conflictFieldsData = computed(() => {
    if (!props.conflict) return [];

    return props.conflict.conflictFields.map(field => ({
        field,
        localValue: (props.conflict!.localVersion as any)[field],
        serverValue: (props.conflict!.serverVersion as any)[field]
    }));
});

const selectedResolutionOption = computed(() => {
    if (!props.conflict || !selectedResolution.value) return null;
    return props.conflict.resolutionOptions.find(r => r.id === selectedResolution.value);
});

const formatValue = (value: any): string => {
    if (value === null || value === undefined) return 'null';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
};

const getResolutionIcon = (action: ConflictResolution['action']) => {
    switch (action) {
        case 'use_local':
            return User;
        case 'use_server':
            return Server;
        case 'merge':
            return GitMerge;
        case 'custom':
            return Check;
        default:
            return Check;
    }
};

const getResolutionColor = (action: ConflictResolution['action']) => {
    switch (action) {
        case 'use_local':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'use_server':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'merge':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'custom':
            return 'bg-orange-100 text-orange-800 border-orange-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const handleResolve = async () => {
    if (!props.conflict || !selectedResolution.value) return;

    isResolving.value = true;

    try {
        const resolvedData = await resolveConflict(
            props.conflict.id,
            selectedResolution.value
        );

        if (resolvedData) {
            emit('resolved', resolvedData);
            emit('update:open', false);
        }
    } catch (error) {
        console.error('Failed to resolve conflict:', error);
    } finally {
        isResolving.value = false;
    }
};

const handleCancel = () => {
    selectedResolution.value = null;
    emit('cancelled');
    emit('update:open', false);
};

const toggleDiff = () => {
    showDiff.value = !showDiff.value;
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-hidden">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 text-amber-500" />
                    Data Conflict Detected
                </DialogTitle>
                <DialogDescription>
                    Changes were made to this {{ conflict?.entityType }} both locally and on the server.
                    Please choose how to resolve this conflict.
                </DialogDescription>
            </DialogHeader>

            <div v-if="conflict" class="space-y-6">
                <!-- Conflict Summary -->
                <Alert>
                    <AlertTriangle class="h-4 w-4" />
                    <AlertDescription>
                        <div class="space-y-2">
                            <p>
                                <strong>{{ conflict.conflictFields.length }}</strong> field(s) have conflicting changes:
                                <span class="font-mono text-sm">{{ conflict.conflictFields.join(', ') }}</span>
                            </p>
                            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                                <div class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    {{ new Date(conflict.timestamp).toLocaleString() }}
                                </div>
                                <Badge variant="outline">{{ conflict.entityType }}</Badge>
                            </div>
                        </div>
                    </AlertDescription>
                </Alert>

                <!-- Resolution Options -->
                <div class="space-y-3">
                    <h3 class="text-lg font-semibold">Choose Resolution</h3>
                    <div class="grid gap-3">
                        <Card
                            v-for="option in conflict.resolutionOptions"
                            :key="option.id"
                            :class="[
                                'cursor-pointer transition-all border-2',
                                selectedResolution === option.id
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-primary/50'
                            ]"
                            @click="selectedResolution = option.id"
                        >
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-3 text-base">
                                    <component
                                        :is="getResolutionIcon(option.action)"
                                        class="h-5 w-5"
                                    />
                                    {{ option.label }}
                                    <Badge
                                        :class="getResolutionColor(option.action)"
                                        variant="outline"
                                    >
                                        {{ option.action.replace('_', ' ') }}
                                    </Badge>
                                    <Check
                                        v-if="selectedResolution === option.id"
                                        class="h-4 w-4 text-primary ml-auto"
                                    />
                                </CardTitle>
                                <CardDescription>
                                    {{ option.description }}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                </div>

                <!-- Field Differences -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Field Differences</h3>
                        <Button
                            @click="toggleDiff"
                            variant="outline"
                            size="sm"
                        >
                            {{ showDiff ? 'Hide' : 'Show' }} Details
                            <ChevronRight
                                :class="[
                                    'h-4 w-4 ml-2 transition-transform',
                                    showDiff ? 'rotate-90' : ''
                                ]"
                            />
                        </Button>
                    </div>

                    <div v-if="showDiff" class="space-y-4">
                        <Card
                            v-for="fieldData in conflictFieldsData"
                            :key="fieldData.field"
                        >
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base">{{ fieldData.field }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid md:grid-cols-2 gap-4">
                                    <!-- Local Version -->
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <User class="h-4 w-4 text-blue-500" />
                                            <span class="font-medium text-blue-700">Your Version</span>
                                        </div>
                                        <ScrollArea class="h-32 w-full rounded border bg-blue-50 p-3">
                                            <pre class="text-sm">{{ formatValue(fieldData.localValue) }}</pre>
                                        </ScrollArea>
                                    </div>

                                    <!-- Server Version -->
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <Server class="h-4 w-4 text-green-500" />
                                            <span class="font-medium text-green-700">Server Version</span>
                                        </div>
                                        <ScrollArea class="h-32 w-full rounded border bg-green-50 p-3">
                                            <pre class="text-sm">{{ formatValue(fieldData.serverValue) }}</pre>
                                        </ScrollArea>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Preview of Selected Resolution -->
                <div v-if="selectedResolutionOption" class="space-y-3">
                    <h3 class="text-lg font-semibold">Resolution Preview</h3>
                    <Alert>
                        <component :is="getResolutionIcon(selectedResolutionOption.action)" class="h-4 w-4" />
                        <AlertDescription>
                            <strong>{{ selectedResolutionOption.label }}:</strong>
                            {{ selectedResolutionOption.description }}
                        </AlertDescription>
                    </Alert>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4 border-t">
                <Button
                    @click="handleCancel"
                    variant="outline"
                    :disabled="isResolving"
                >
                    Cancel
                </Button>
                <Button
                    @click="handleResolve"
                    :disabled="!selectedResolution || isResolving"
                    :loading="isResolving"
                >
                    <Check class="h-4 w-4 mr-2" />
                    Resolve Conflict
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
pre {
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
