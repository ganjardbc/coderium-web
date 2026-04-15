<script setup lang="ts">
import AlertError from '@/components/AlertError.vue';
import DataTable, {
    type Action,
    type Column,
} from '@/components/DataTable.vue';
import Searchbar from '@/components/Searchbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import CustomSelect from '@/components/CustomSelect.vue';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Award, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Certificate {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    track: {
        id: number;
        title: string;
        slug: string;
    };
    certificate_number: string;
    issued_at: string;
    downloaded_at?: string;
    download_count: number;
    is_valid: boolean;
}

interface CertificateTemplate {
    id: number;
    name: string;
    description: string;
    template_content: string;
    is_default: boolean;
    created_at: string;
}

interface Props {
    certificates: {
        data: Certificate[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    templates?: CertificateTemplate[];
    tracks?: Array<{ id: number; title: string }>;
    filters?: {
        search?: string;
        track_id?: number;
        status?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');
const selectedTrack = ref(props.filters?.track_id || null);
const selectedStatus = ref(props.filters?.status || '');
const activeTab = ref<'certificates' | 'templates' | 'bulk'>('certificates');
const showTemplateEditor = ref(false);
const editingTemplate = ref<CertificateTemplate | null>(null);

const trackOptions = computed(() => [
    { value: null, label: 'All tracks' },
    ...(props.tracks || []).map(track => ({
        value: track.id,
        label: track.title
    }))
]);

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'valid', label: 'Valid' },
    { value: 'revoked', label: 'Revoked' },
];

const templateOptions = computed(() => [
    { value: null, label: 'Default Template' },
    ...(props.templates || []).map(template => ({
        value: template.id,
        label: template.name
    }))
]);

const breadcrumbs = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Classroom', href: '/admin/classroom' },
    { title: 'Certificate Manager', href: '/admin/classroom/certificates' },
];

// Template form
const templateForm = useForm({
    name: '',
    description: '',
    template_content: '',
    is_default: false,
});

// Bulk generation form
const bulkForm = useForm({
    track_id: null as number | null,
    user_ids: [] as number[],
    template_id: null as number | null,
});

const handleSearch = (query: string) => {
    router.get(
        '/admin/classroom/certificates',
        {
            search: query || undefined,
            track_id: selectedTrack.value || undefined,
            status: selectedStatus.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const handleFilterChange = () => {
    router.get(
        '/admin/classroom/certificates',
        {
            search: searchQuery.value || undefined,
            track_id: selectedTrack.value || undefined,
            status: selectedStatus.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedTrack.value = null;
    selectedStatus.value = '';
    router.get(
        '/admin/classroom/certificates',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const downloadCertificate = (certificate: Certificate) => {
    window.open(
        `/admin/classroom/certificates/${certificate.id}/download`,
        '_blank',
    );
};

const revokeCertificate = (certificate: Certificate) => {
    if (
        confirm(
            `Are you sure you want to revoke the certificate for ${certificate.user.name}?`,
        )
    ) {
        router.delete(`/admin/classroom/certificates/${certificate.id}`, {
            preserveScroll: true,
        });
    }
};

const resendCertificate = (certificate: Certificate) => {
    if (confirm(`Resend certificate to ${certificate.user.name}?`)) {
        router.post(
            `/admin/classroom/certificates/${certificate.id}/resend`,
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

// Template management
const openTemplateEditor = (template?: CertificateTemplate) => {
    if (template) {
        editingTemplate.value = template;
        // Fix: Use individual property assignments instead of setData
        templateForm.name = template.name;
        templateForm.description = template.description;
        templateForm.template_content = template.template_content;
        templateForm.is_default = template.is_default;
    } else {
        editingTemplate.value = null;
        templateForm.reset();
    }
    showTemplateEditor.value = true;
};

const saveTemplate = () => {
    if (editingTemplate.value) {
        templateForm.put(
            `/admin/classroom/certificate-templates/${editingTemplate.value.id}`,
            {
                onSuccess: () => {
                    showTemplateEditor.value = false;
                    editingTemplate.value = null;
                },
            },
        );
    } else {
        templateForm.post('/admin/classroom/certificate-templates', {
            onSuccess: () => {
                showTemplateEditor.value = false;
            },
        });
    }
};

const deleteTemplate = (template: CertificateTemplate) => {
    if (
        confirm(
            `Are you sure you want to delete the template "${template.name}"?`,
        )
    ) {
        router.delete(`/admin/classroom/certificate-templates/${template.id}`, {
            preserveScroll: true,
        });
    }
};

const setDefaultTemplate = (template: CertificateTemplate) => {
    router.post(
        `/admin/classroom/certificate-templates/${template.id}/set-default`,
        {},
        {
            preserveScroll: true,
        },
    );
};

// Bulk generation
const generateBulkCertificates = () => {
    if (
        confirm(`Generate certificates for ${bulkForm.user_ids.length} users?`)
    ) {
        bulkForm.post('/admin/classroom/certificates/bulk-generate', {
            preserveScroll: true,
            onSuccess: () => {
                bulkForm.reset();
            },
        });
    }
};

// Table columns
const certificateColumns: Column<Certificate>[] = [
    { key: 'certificate_number', label: 'Certificate #', align: 'left' },
    { key: 'user', label: 'Learner', align: 'left' },
    { key: 'track', label: 'Track', align: 'left' },
    { key: 'issued_at', label: 'Issued', align: 'center' },
    { key: 'status', label: 'Status', align: 'center' },
    { key: 'downloads', label: 'Downloads', align: 'center' },
];

const certificateActions: Action<Certificate>[] = [
    {
        label: 'Download',
        onClick: (cert) => downloadCertificate(cert),
        variant: 'outline',
    },
    {
        label: 'Resend',
        onClick: (cert) => resendCertificate(cert),
        variant: 'outline',
    },
    {
        label: 'Revoke',
        onClick: (cert) => revokeCertificate(cert),
        variant: 'destructive',
        show: (cert) => cert.is_valid,
    },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <Head title="Certificate Manager - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">Certificate Manager</h1>
                <p class="text-muted-foreground">
                    Manage digital certificates and templates for course
                    completions
                </p>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab">
                <TabsList>
                    <TabsTrigger value="certificates">
                        Certificates
                    </TabsTrigger>
                    <TabsTrigger value="templates">
                        Templates
                    </TabsTrigger>
                    <TabsTrigger value="bulk">
                        Bulk Generation
                    </TabsTrigger>
                </TabsList>

                <!-- Certificates Tab -->
                <TabsContent value="certificates" class="space-y-6">
                    <!-- Filters -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="space-y-2">
                                    <Label>Search</Label>
                                    <Searchbar
                                        v-model="searchQuery"
                                        placeholder="Search certificates..."
                                        @search="handleSearch"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <CustomSelect
                                        label="Track"
                                        v-model="selectedTrack"
                                        :options="trackOptions"
                                        placeholder="All tracks"
                                        @update:modelValue="handleFilterChange"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <CustomSelect
                                        label="Status"
                                        v-model="selectedStatus"
                                        :options="statusOptions"
                                        placeholder="All statuses"
                                        @update:modelValue="handleFilterChange"
                                    />
                                </div>

                                <div class="flex items-end">
                                    <Button
                                        variant="outline"
                                        @click="clearFilters"
                                        class="w-full"
                                    >
                                        Clear Filters
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <DataTable
                        :data="certificates?.data || []"
                        :columns="certificateColumns"
                        :actions="certificateActions"
                        :pagination="certificates"
                        empty-message="No certificates found"
                    >
                        <!-- Certificate Number Cell -->
                        <template #cell-certificate_number="{ row }">
                            <div class="font-mono text-sm">
                                {{ row.certificate_number }}
                            </div>
                        </template>

                        <!-- User Cell -->
                        <template #cell-user="{ row }">
                            <div>
                                <p class="font-medium">{{ row.user.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ row.user.email }}
                                </p>
                            </div>
                        </template>

                        <!-- Track Cell -->
                        <template #cell-track="{ row }">
                            <div>
                                <p class="font-medium">{{ row.track.title }}</p>
                            </div>
                        </template>

                        <!-- Issued Date Cell -->
                        <template #cell-issued_at="{ row }">
                            <span class="text-sm">{{
                                formatDate(row.issued_at)
                            }}</span>
                        </template>

                        <!-- Status Cell -->
                        <template #cell-status="{ row }">
                            <Badge
                                :variant="row.is_valid ? 'default' : 'destructive'"
                            >
                                {{ row.is_valid ? 'Valid' : 'Revoked' }}
                            </Badge>
                        </template>

                        <!-- Downloads Cell -->
                        <template #cell-downloads="{ row }">
                            <div class="text-center">
                                <p class="font-medium">{{ row.download_count }}</p>
                                <p
                                    v-if="row.downloaded_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    Last: {{ formatDate(row.downloaded_at) }}
                                </p>
                            </div>
                        </template>
                    </DataTable>
                </TabsContent>

                <!-- Templates Tab -->
                <TabsContent value="templates" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold">
                                Certificate Templates
                            </h2>
                            <p class="text-muted-foreground">
                                Manage certificate design templates
                            </p>
                        </div>
                        <Button @click="openTemplateEditor()">
                            <FileText class="mr-2 h-4 w-4" />
                            Create Template
                        </Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div v-if="!templates || templates.length === 0" class="col-span-full">
                            <Card>
                                <CardContent class="pt-6 text-center">
                                    <p class="text-muted-foreground">No templates found. Create your first template to get started.</p>
                                </CardContent>
                            </Card>
                        </div>
                        <Card
                            v-for="template in (templates || [])"
                            :key="template.id"
                            :class="{ 'ring-2 ring-primary': template.is_default }"
                        >
                            <CardHeader>
                                <CardTitle
                                    class="flex items-center justify-between"
                                >
                                    <span>{{ template.name }}</span>
                                    <Badge
                                        v-if="template.is_default"
                                        variant="default"
                                        >Default</Badge
                                    >
                                </CardTitle>
                                <CardDescription>{{
                                    template.description
                                }}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openTemplateEditor(template)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        v-if="!template.is_default"
                                        variant="outline"
                                        size="sm"
                                        @click="setDefaultTemplate(template)"
                                    >
                                        Set Default
                                    </Button>
                                    <Button
                                        v-if="!template.is_default"
                                        variant="destructive"
                                        size="sm"
                                        @click="deleteTemplate(template)"
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Template Editor Modal -->
                    <div
                        v-if="showTemplateEditor"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                    >
                        <Card class="max-h-[90vh] w-full max-w-2xl overflow-y-auto">
                            <CardHeader>
                                <CardTitle>
                                    {{
                                        editingTemplate
                                            ? 'Edit Template'
                                            : 'Create Template'
                                    }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <AlertError
                                    v-if="templateForm.hasErrors"
                                    :errors="templateForm.errors"
                                />

                                <div class="space-y-2">
                                    <Label for="template_name">Name *</Label>
                                    <Input
                                        id="template_name"
                                        v-model="templateForm.name"
                                        placeholder="Template name"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="template_description"
                                        >Description</Label
                                    >
                                    <Textarea
                                        id="template_description"
                                        v-model="templateForm.description"
                                        placeholder="Template description"
                                        rows="2"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="template_content"
                                        >Template Content *</Label
                                    >
                                    <Textarea
                                        id="template_content"
                                        v-model="templateForm.template_content"
                                        placeholder="HTML template content with placeholders like {{user_name}}, {{track_title}}, {{completion_date}}"
                                        rows="10"
                                        class="font-mono text-sm"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Available placeholders: {{ user_name }},
                                        {{ track_title }}, {{ completion_date }},
                                        {{ certificate_number }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <input
                                        id="is_default"
                                        type="checkbox"
                                        v-model="templateForm.is_default"
                                        class="h-4 w-4"
                                    />
                                    <Label for="is_default"
                                        >Set as default template</Label
                                    >
                                </div>

                                <div class="flex gap-2 pt-4">
                                    <Button
                                        @click="saveTemplate"
                                        :disabled="templateForm.processing"
                                    >
                                        {{
                                            templateForm.processing
                                                ? 'Saving...'
                                                : 'Save Template'
                                        }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        @click="showTemplateEditor = false"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Bulk Generation Tab -->
                <TabsContent value="bulk" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Bulk Certificate Generation</CardTitle>
                            <CardDescription>
                                Generate certificates for multiple learners who have
                                completed a track
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <AlertError
                                v-if="bulkForm.hasErrors"
                                :errors="bulkForm.errors"
                            />

                            <div class="grid gap-4 md:grid-cols-2">
                                <CustomSelect
                                    id="bulk_track"
                                    label="Track *"
                                    v-model="bulkForm.track_id"
                                    :options="trackOptions.slice(1)"
                                    placeholder="Select track"
                                    required
                                />

                                <CustomSelect
                                    id="bulk_template"
                                    label="Template"
                                    v-model="bulkForm.template_id"
                                    :options="templateOptions"
                                    placeholder="Use default template"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Instructions</Label>
                                <div class="rounded-lg bg-muted p-4 text-sm">
                                    <p class="mb-2">
                                        To generate certificates in bulk:
                                    </p>
                                    <ol class="list-inside list-decimal space-y-1">
                                        <li>
                                            Select a track from the dropdown above
                                        </li>
                                        <li>
                                            The system will automatically identify
                                            learners who have completed the track
                                            but don't have certificates
                                        </li>
                                        <li>
                                            Choose a template or use the default
                                        </li>
                                        <li>
                                            Click "Generate Certificates" to create
                                            and send certificates to all eligible
                                            learners
                                        </li>
                                    </ol>
                                </div>
                            </div>

                            <Button
                                @click="generateBulkCertificates"
                                :disabled="
                                    !bulkForm.track_id || bulkForm.processing
                                "
                                class="w-full"
                            >
                                <Award class="mr-2 h-4 w-4" />
                                {{
                                    bulkForm.processing
                                        ? 'Generating...'
                                        : 'Generate Certificates'
                                }}
                            </Button>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
