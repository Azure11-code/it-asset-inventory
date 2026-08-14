<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import {
    PaperClipIcon, ArrowUpTrayIcon, ArrowDownTrayIcon, TrashIcon,
    DocumentIcon, DocumentTextIcon, PhotoIcon, TableCellsIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    entity:      { type: String, required: true }, // "recommendations" | "incidents" | "permits" | "accountability"
    entityId:    { type: [Number, String], required: true },
    resourceKey: { type: String, required: true }, // maps to permission resource
    attachments: { type: Array,  default: () => [] },
    title:       { type: String, default: 'Supporting Documents' },
    subtitle:    { type: String, default: 'Scanned signed copies, receipts, proof photos, and related files.' },
});

const emit = defineEmits(['updated']);

const { can } = usePermissions();
const canUpload = computed(() => can(props.resourceKey, 'edit'));
const canDelete = computed(() => can(props.resourceKey, 'edit'));

const fileInput = ref(null);
const showUpload = ref(false);
const upload = useForm({ file: null, label: '' });

const openPicker = () => fileInput.value?.click();
const onFileChange = (e) => {
    upload.file = e.target.files?.[0] || null;
    if (upload.file) showUpload.value = true;
};

const submitUpload = () => {
    if (!upload.file) return;
    upload.post(`/attachments/${props.entity}/${props.entityId}`, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            upload.reset('file', 'label');
            showUpload.value = false;
            if (fileInput.value) fileInput.value.value = '';
            emit('updated');
        },
    });
};

const toDelete = ref(null);
const askDelete = (a) => { toDelete.value = a; };
const doDelete = () => {
    if (!toDelete.value) return;
    router.delete(`/attachments/${props.entity}/${props.entityId}/${toDelete.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => emit('updated'),
        onFinish:  () => { toDelete.value = null; },
    });
};

const iconFor = (mime) => {
    if (!mime) return DocumentIcon;
    if (mime.startsWith('image/')) return PhotoIcon;
    if (mime.includes('spreadsheet') || mime.includes('excel')) return TableCellsIcon;
    if (mime === 'application/pdf' || mime.includes('word') || mime.includes('document')) return DocumentTextIcon;
    return DocumentIcon;
};

const formatSize = (b) => {
    if (!b) return '—';
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
    return `${(b / 1024 / 1024).toFixed(1)} MB`;
};
</script>

<template>
    <section class="card">
        <header class="card-header py-2.5 px-4">
            <div>
                <h2 class="card-title text-sm flex items-center gap-1.5">
                    <PaperClipIcon class="h-3.5 w-3.5" /> {{ title }}
                    <span v-if="attachments.length" class="ml-1 text-[11px] font-normal text-slate-500">({{ attachments.length }})</span>
                </h2>
                <p class="card-subtitle text-[11px]">{{ subtitle }}</p>
            </div>
            <button v-if="canUpload" type="button" class="btn-primary" @click="openPicker">
                <ArrowUpTrayIcon class="h-3.5 w-3.5" /> Upload
            </button>
            <input ref="fileInput" type="file" class="hidden"
                   accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                   @change="onFileChange" />
        </header>

        <div v-if="attachments.length === 0" class="px-4 py-6 text-center text-xs text-slate-500">
            <PaperClipIcon class="mx-auto mb-2 h-6 w-6 text-slate-300" />
            No attachments yet.
            <span v-if="canUpload">Click <strong>Upload</strong> to add a scanned document or photo.</span>
        </div>

        <ul v-else class="divide-y divide-slate-100">
            <li v-for="a in attachments" :key="a.id" class="flex items-start gap-3 px-4 py-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500">
                    <component :is="iconFor(a.mime_type)" class="h-4 w-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold text-slate-900 truncate">{{ a.original_name }}</div>
                    <div class="text-[11px] text-slate-500">
                        {{ formatSize(a.size_bytes) }}
                        <span v-if="a.label"> · {{ a.label }}</span>
                        <span v-if="a.uploaded_by"> · by {{ a.uploaded_by }}</span>
                        <span v-if="a.created_at"> · {{ a.created_at }}</span>
                    </div>
                </div>
                <div class="inline-flex items-center gap-1">
                    <a :href="`/attachments/${entity}/${entityId}/${a.id}/download`"
                       class="btn-ghost" title="Download">
                        <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                    </a>
                    <button v-if="canDelete" type="button" class="btn-ghost-danger" title="Delete"
                            @click="askDelete(a)">
                        <TrashIcon class="h-3.5 w-3.5" />
                    </button>
                </div>
            </li>
        </ul>

        <!-- Optional label modal shown after picking a file -->
        <div v-if="showUpload" class="border-t border-slate-100 bg-slate-50 px-4 py-3 space-y-2">
            <div class="text-xs text-slate-600">
                Selected: <strong>{{ upload.file?.name }}</strong> ({{ formatSize(upload.file?.size) }})
            </div>
            <div>
                <label class="label !text-[11px]">Label (optional)</label>
                <input v-model="upload.label" type="text" class="input"
                       placeholder="e.g. Scanned with signatures, receipt, etc." />
                <p v-if="upload.errors.file" class="mt-1 text-[11px] text-rose-600">{{ upload.errors.file }}</p>
                <p v-if="upload.errors.label" class="mt-1 text-[11px] text-rose-600">{{ upload.errors.label }}</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" :disabled="upload.processing"
                        @click="showUpload = false; upload.reset('file', 'label')">Cancel</button>
                <button type="button" class="btn-primary" :disabled="upload.processing || !upload.file"
                        @click="submitUpload">
                    {{ upload.processing ? 'Uploading…' : 'Upload' }}
                </button>
            </div>
        </div>

        <ConfirmDialog
            :show="!!toDelete"
            :title="`Delete attachment?`"
            :message="`Aalisin ang ${toDelete?.original_name}. Hindi na mababawi.`"
            confirm-text="Delete"
            @close="toDelete = null"
            @confirm="doDelete"
        />
    </section>
</template>
