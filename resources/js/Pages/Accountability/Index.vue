<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import SortableTh from '@/Components/SortableTh.vue';
import Combobox from '@/Components/Combobox.vue';
import Modal from '@/Components/Modal.vue';
import Attachments from '@/Components/Attachments.vue';
import { MagnifyingGlassIcon, DocumentTextIcon, ArrowDownTrayIcon, CpuChipIcon, ArrowTopRightOnSquareIcon, ClipboardDocumentCheckIcon, PaperClipIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    employees: Object,
    departments: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const departmentFilter = ref(props.filters?.department_id ?? '');
const searchInput = ref(null);

let timer = null;
const refresh = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const el = searchInput.value;
        const wasFocused = el && document.activeElement === el;
        const caret = wasFocused ? el.selectionStart : null;
        router.get('/accountability', {
            search: search.value || undefined,
            department_id: departmentFilter.value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['employees', 'filters'],
            onSuccess: () => {
                if (wasFocused) {
                    nextTick(() => {
                        const input = searchInput.value;
                        if (!input) return;
                        input.focus();
                        if (caret !== null) {
                            try { input.setSelectionRange(caret, caret); } catch (e) { /* noop */ }
                        }
                    });
                }
            },
        });
    }, 1000);
};
watch([search, departmentFilter], refresh);

const hasFilters = computed(() => search.value || departmentFilter.value);

const sortExtras = computed(() => ({
    search: search.value || undefined,
    department_id: departmentFilter.value || undefined,
}));

// ── View held-assets modal ──
const showAssetsModal = ref(false);
const viewingEmployee = ref(null);
const assetsList = ref([]);
const assetsLoading = ref(false);
const openAssets = async (row) => {
    viewingEmployee.value = row;
    showAssetsModal.value = true;
    assetsLoading.value = true;
    assetsList.value = [];
    try {
        const res = await fetch(`/employees/${row.id}/assets`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        assetsList.value = data.assets || [];
    } finally {
        assetsLoading.value = false;
    }
};

const assetStatusTone = {
    in_stock:   'sky',
    assigned:   'brand',
    for_repair: 'amber',
    defective:  'rose',
    retired:    'slate',
    replaced:   'slate',
};

// ── Signed-forms attachments modal ──
const showFilesModal = ref(false);
const filesEmployee = ref(null);
const filesList = ref([]);
const filesLoading = ref(false);

const openFiles = async (row) => {
    filesEmployee.value = row;
    showFilesModal.value = true;
    filesLoading.value = true;
    filesList.value = [];
    try {
        const res  = await fetch(`/accountability/${row.id}/attachments`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        filesList.value = data.attachments || [];
    } finally {
        filesLoading.value = false;
    }
};

// Refresh attachments list after upload/delete (Inertia posts to the polymorphic route
// and returns to this page, but the modal state is client-side, so we re-fetch).
const reloadFiles = async () => {
    if (!filesEmployee.value) return;
    filesLoading.value = true;
    try {
        const res  = await fetch(`/accountability/${filesEmployee.value.id}/attachments`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        filesList.value = data.attachments || [];
    } finally {
        filesLoading.value = false;
    }
    // Also reload the page so the "Files" count badge on the row updates.
    router.reload({ only: ['employees'], preserveScroll: true, preserveState: true });
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Accountability" subtitle="Generate the Accountability Agreement Form (.docx) per employee, listing all IT assets currently in their custody." />
        </template>

        <div class="card">
            <div class="grid gap-2 border-b border-slate-100 p-3 sm:grid-cols-[minmax(220px,1fr)_minmax(0,14rem)] sm:items-center">
                <div class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input ref="searchInput" v-model="search" type="search" placeholder="Search name, employee #..." class="input pl-9" />
                </div>
                <Combobox v-model="departmentFilter" :options="departments" placeholder="All Departments" null-label="All Departments" />
            </div>

            <EmptyState
                v-if="employees.data.length === 0 && !hasFilters"
                title="Nothing to render"
                description="Accountability forms are generated per employee. No one currently holds any assets."
                :icon="ClipboardDocumentCheckIcon"
            />

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <SortableTh field="employee"   :sort="filters?.sort" :direction="filters?.direction" url="/accountability" :extra="sortExtras">Employee</SortableTh>
                                <SortableTh field="department" :sort="filters?.sort" :direction="filters?.direction" url="/accountability" :extra="sortExtras" class="hidden sm:table-cell">Department</SortableTh>
                                <SortableTh field="assets"     :sort="filters?.sort" :direction="filters?.direction" url="/accountability" :extra="sortExtras" align="right">Held Assets</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="employees.data.length === 0">
                                <td colspan="4" class="cell-muted text-center py-10">No employees match your filters.</td>
                            </tr>
                            <tr v-for="row in employees.data" :key="row.id">
                                <td>
                                    <div class="min-w-0">
                                        <div class="cell-strong truncate">
                                            {{ row.last_name }}, {{ row.first_name }}{{ row.middle_name ? ' ' + row.middle_name.charAt(0) + '.' : '' }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 truncate">{{ row.employee_no }}<span v-if="row.position"> · {{ row.position }}</span></div>
                                        <div class="sm:hidden text-[11px] text-slate-500 truncate">
                                            {{ row.department?.name || '—' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell">{{ row.department?.name || '—' }}</td>
                                <td class="cell-right">
                                    <Badge tone="brand" dot>{{ row.held_assets_count }}</Badge>
                                </td>
                                <td class="cell-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button class="btn-ghost relative" title="Signed accountability files" @click="openFiles(row)">
                                            <PaperClipIcon class="h-4 w-4" />
                                            <span
                                                v-if="row.attachments_count"
                                                class="absolute -top-1 -right-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[9px] font-semibold text-white"
                                            >{{ row.attachments_count }}</span>
                                        </button>
                                        <button class="btn-ghost" title="View held assets" @click="openAssets(row)">
                                            <CpuChipIcon class="h-4 w-4" />
                                        </button>
                                        <a v-if="can('accountability', 'print')" :href="`/accountability/${row.id}/download`" class="btn-primary !py-1 !px-2 text-xs" title="Download Accountability Form (.docx)">
                                            <ArrowDownTrayIcon class="h-4 w-4" /> <span class="hidden sm:inline">Download</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="employees.links" :from="employees.from" :to="employees.to" :total="employees.total" />
            </template>
        </div>

        <Modal :show="showAssetsModal" max-width="3xl" :title="viewingEmployee ? `Assets held by ${viewingEmployee.first_name} ${viewingEmployee.last_name}` : 'Held Assets'" @close="showAssetsModal = false">
            <div class="p-5">
                <div v-if="assetsLoading" class="py-8 text-center text-sm text-slate-500">Loading...</div>
                <div v-else-if="assetsList.length === 0" class="py-8 text-center text-sm text-slate-500">
                    <CpuChipIcon class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                    No assets currently held by this employee.
                </div>
                <div v-else class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th class="hidden sm:table-cell">Category</th>
                                <th class="hidden md:table-cell">Brand / Model</th>
                                <th class="hidden lg:table-cell">Serial</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in assetsList" :key="a.id">
                                <td class="cell-strong">{{ a.asset_tag }}</td>
                                <td class="hidden sm:table-cell">{{ a.category || '—' }}</td>
                                <td class="hidden md:table-cell">
                                    <div>{{ a.brand || '—' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ a.model || '' }}</div>
                                </td>
                                <td class="hidden lg:table-cell">{{ a.serial_number || '—' }}</td>
                                <td><Badge :tone="assetStatusTone[a.current_status]" dot>{{ a.current_status }}</Badge></td>
                                <td class="cell-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a :href="`/accountability/${viewingEmployee.id}/download?asset_id=${a.id}`"
                                           class="btn-ghost" title="Download Accountability Form for this device only">
                                            <ArrowDownTrayIcon class="h-4 w-4" />
                                        </a>
                                        <Link :href="`/assets/${a.id}`" class="btn-ghost" title="Open asset">
                                            <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                <a v-if="viewingEmployee && assetsList.length > 0"
                   :href="`/accountability/${viewingEmployee.id}/download`"
                   class="btn-primary">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Download Accountability Form
                </a>
                <span v-else></span>
                <button type="button" class="btn-secondary" @click="showAssetsModal = false">Close</button>
            </div>
        </Modal>

        <!-- Signed accountability files modal -->
        <Modal :show="showFilesModal" max-width="2xl" :title="filesEmployee ? `Signed accountability files — ${filesEmployee.first_name} ${filesEmployee.last_name}` : 'Signed Files'" @close="showFilesModal = false">
            <div class="p-4">
                <div v-if="filesLoading" class="py-8 text-center text-sm text-slate-500">Loading…</div>
                <Attachments
                    v-else
                    entity="accountability"
                    :entity-id="filesEmployee?.id"
                    resource-key="accountability"
                    :attachments="filesList"
                    title="Signed accountability documents"
                    subtitle="Upload the scanned signed copy of this employee's accountability form. Accepts PDF or image (JPG/PNG)."
                    @updated="reloadFiles"
                />
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                <button type="button" class="btn-secondary" @click="showFilesModal = false">Close</button>
            </div>
        </Modal>
    </AppLayout>
</template>
