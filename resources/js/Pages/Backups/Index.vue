<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import Modal from '@/Components/Modal.vue';
import {
    CircleStackIcon, ArchiveBoxIcon, ArrowDownTrayIcon, TrashIcon,
    DocumentDuplicateIcon, ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    backups:   { type: Array,  default: () => [] },
    retention: { type: Number, default: 5 },
});

const busy = ref(null);
const confirmDelete = ref(null);

const formatSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
};

const createSql = () => {
    if (busy.value) return;
    busy.value = 'sql';
    router.post('/backups/sql', {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = null; },
    });
};

const createFull = () => {
    if (busy.value) return;
    busy.value = 'full';
    router.post('/backups/full', {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = null; },
    });
};

const doDelete = () => {
    if (!confirmDelete.value) return;
    const name = confirmDelete.value.name;
    router.delete(`/backups/${encodeURIComponent(name)}`, {
        preserveScroll: true,
        onFinish: () => { confirmDelete.value = null; },
    });
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Backups" subtitle="Download database snapshots or a full archive with uploaded files.">
                <template #actions>
                    <button type="button" class="btn-secondary" :disabled="busy === 'sql'" @click="createSql">
                        <CircleStackIcon class="h-4 w-4" />
                        <span>{{ busy === 'sql' ? 'Creating…' : 'Create SQL Backup' }}</span>
                    </button>
                    <button type="button" class="btn-primary" :disabled="busy === 'full'" @click="createFull">
                        <ArchiveBoxIcon class="h-4 w-4" />
                        <span>{{ busy === 'full' ? 'Creating…' : 'Create Full Backup (ZIP)' }}</span>
                    </button>
                </template>
            </PageHeader>
        </template>

        <!-- Info card -->
        <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <div class="flex items-start gap-3">
                <ShieldCheckIcon class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" />
                <div class="flex-1">
                    <p class="font-semibold text-slate-800">Backup retention: last {{ retention }} per type</p>
                    <p class="mt-1 text-xs">
                        <strong>SQL Backup</strong> — database schema + data lang (`.sql` file). Mabilis, maliit.
                        <br>
                        <strong>Full Backup</strong> — SQL + uploaded files (asset images, docs) na naka-zip. Complete restore.
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        I-download at i-store ang mga backup sa external drive or cloud storage. Auto-delete ang pinakaluma pag lagpas na sa {{ retention }} files.
                    </p>
                </div>
            </div>
        </div>

        <div class="card">
            <EmptyState
                v-if="backups.length === 0"
                title="No backups yet"
                description="Click 'Create SQL Backup' or 'Create Full Backup' above para mag-generate ng first snapshot."
                :icon="ArchiveBoxIcon"
            />

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in backups" :key="b.name">
                                <td class="cell-strong font-mono text-[12px]">{{ b.name }}</td>
                                <td>
                                    <Badge :tone="b.type === 'full' ? 'brand' : 'sky'" dot>
                                        {{ b.type === 'full' ? 'Full ZIP' : 'SQL' }}
                                    </Badge>
                                </td>
                                <td class="cell-muted">{{ formatSize(b.size_bytes) }}</td>
                                <td class="cell-muted">{{ b.created_at }}</td>
                                <td class="cell-right">
                                    <a :href="`/backups/${encodeURIComponent(b.name)}/download`"
                                       class="btn-ghost" title="Download">
                                        <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                                    </a>
                                    <button type="button" class="btn-ghost !text-rose-600" title="Delete"
                                            @click="confirmDelete = b">
                                        <TrashIcon class="h-3.5 w-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <Modal :show="!!confirmDelete" title="Delete backup?" max-width="md" @close="confirmDelete = null">
            <div class="p-5 space-y-3">
                <p class="text-sm text-slate-600">
                    Aalisin ang backup file na ito. Hindi mababawi kapag hindi mo pa na-download.
                </p>
                <p class="rounded-md bg-slate-50 p-2 font-mono text-xs text-slate-700">
                    {{ confirmDelete?.name }}
                </p>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                <button class="btn-secondary" @click="confirmDelete = null">Cancel</button>
                <button class="btn-danger" @click="doDelete">Delete</button>
            </div>
        </Modal>
    </AppLayout>
</template>
