<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import SortableTh from '@/Components/SortableTh.vue';
import Combobox from '@/Components/Combobox.vue';
import Modal from '@/Components/Modal.vue';
import { PlusIcon, EyeIcon, MagnifyingGlassIcon, CpuChipIcon, ArchiveBoxArrowDownIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const STATUS_OPTIONS = [
    { value: 'in_stock',   label: 'In Stock' },
    { value: 'assigned',   label: 'Assigned' },
    { value: 'for_repair', label: 'For Repair' },
    { value: 'defective',  label: 'Defective' },
    { value: 'retired',    label: 'Retired' },
    { value: 'replaced',   label: 'Replaced' },
];
const WARRANTY_OPTIONS = [
    { value: 'active',         label: 'Active' },
    { value: 'expiring_soon',  label: 'Expiring ≤ 90d' },
    { value: 'expired',        label: 'Expired' },
];

const props = defineProps({ assets: Object, lookups: Object, filters: Object });

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const category = ref(props.filters?.category_id ?? '');
const brand = ref(props.filters?.brand_id ?? '');
const warranty = ref(props.filters?.warranty ?? '');

const searchInput = ref(null);
let timer = null;
const refresh = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const el = searchInput.value;
        const wasFocused = el && document.activeElement === el;
        const caret = wasFocused ? el.selectionStart : null;
        router.get('/assets', {
            search: search.value || undefined,
            status: status.value || undefined,
            category_id: category.value || undefined,
            brand_id: brand.value || undefined,
            warranty: warranty.value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['assets', 'filters'],
            onSuccess: () => {
                if (wasFocused) {
                    nextTick(() => {
                        const input = searchInput.value;
                        if (!input) return;
                        input.focus();
                        if (caret !== null) {
                            try { input.setSelectionRange(caret, caret); } catch (e) { /* type=search may not support */ }
                        }
                    });
                }
            },
        });
    }, 1000);
};
watch([search, status, category, brand, warranty], refresh);

const hasFilters = computed(() => search.value || status.value || category.value || brand.value || warranty.value);

const statusTone = {
    in_stock:   'sky',
    assigned:   'brand',
    for_repair: 'amber',
    defective:  'rose',
    retired:    'slate',
    replaced:   'slate',
};

const warrantyTone = {
    active: 'emerald',
    expiring_soon: 'amber',
    expired: 'rose',
    unknown: 'slate',
};

const sortExtras = computed(() => ({
    search: search.value || undefined,
    status: status.value || undefined,
    category_id: category.value || undefined,
    brand_id: brand.value || undefined,
    warranty: warranty.value || undefined,
}));

// ── Import modal ──
const showImport = ref(false);
const importForm = useForm({ file: null });
const importSkips = computed(() => usePage().props.flash?.import_skips || []);
const onImportFile = (e) => { importForm.file = e.target.files?.[0] || null; };
const submitImport = () => {
    if (!importForm.file) return;
    importForm.post('/assets/import', {
        forceFormData: true,
        onSuccess: () => { showImport.value = false; importForm.reset('file'); },
    });
};

const exportUrl = computed(() => {
    const qs = new URLSearchParams();
    if (search.value)   qs.set('search', search.value);
    if (status.value)   qs.set('status', status.value);
    if (category.value) qs.set('category_id', category.value);
    if (brand.value)    qs.set('brand_id', brand.value);
    if (warranty.value) qs.set('warranty', warranty.value);
    if (props.filters?.sort)      qs.set('sort', props.filters.sort);
    if (props.filters?.direction) qs.set('direction', props.filters.direction);
    const q = qs.toString();
    return q ? `/assets/export?${q}` : '/assets/export';
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Assets" subtitle="Every physical IT peripheral your team owns — one row per device.">
                <template #actions>
                    <button v-if="can('assets', 'import')" type="button" class="btn-secondary" @click="showImport = true" title="Import Excel">
                        <ArrowUpTrayIcon class="h-4 w-4" /> <span class="hidden sm:inline">Import Excel</span><span class="sm:hidden">Import</span>
                    </button>
                    <a v-if="can('assets', 'export')" :href="exportUrl" class="btn-secondary" :title="hasFilters ? 'Export filtered rows' : 'Export all rows'">
                        <ArrowDownTrayIcon class="h-4 w-4" /> <span class="hidden sm:inline">Export Excel</span><span class="sm:hidden">Export</span>
                    </a>
                    <Link v-if="can('assets', 'create')" href="/assets/bulk-receive" class="btn-secondary" title="Bulk Receive">
                        <ArchiveBoxArrowDownIcon class="h-4 w-4" /> <span class="hidden sm:inline">Bulk Receive</span><span class="sm:hidden">Bulk</span>
                    </Link>
                    <Link v-if="can('assets', 'create')" href="/assets/create" class="btn-primary" title="New Asset">
                        <PlusIcon class="h-4 w-4" /> <span class="hidden sm:inline">New Asset</span><span class="sm:hidden">New</span>
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="grid gap-2 border-b border-slate-100 p-3 sm:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(0,10rem))] sm:items-center">
                <div class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input ref="searchInput" v-model="search" type="search" placeholder="Search tag, serial, model, holder name..." class="input pl-9" />
                </div>
                <Combobox v-model="status"   :options="STATUS_OPTIONS"    value-key="value" label-key="label" placeholder="All Status"    null-label="All Status" />
                <Combobox v-model="category" :options="lookups.categories"                                    placeholder="All Categories" null-label="All Categories" />
                <Combobox v-model="brand"    :options="lookups.brands"                                        placeholder="All Brands"    null-label="All Brands" />
                <Combobox v-model="warranty" :options="WARRANTY_OPTIONS"  value-key="value" label-key="label" placeholder="Any Warranty"  null-label="Any Warranty" />
            </div>

            <EmptyState
                v-if="assets.data.length === 0 && !hasFilters"
                title="No assets yet"
                description="Register your IT peripherals to start tracking them through their full lifecycle."
                :icon="CpuChipIcon"
            >
                <Link v-if="can('assets', 'create')" href="/assets/create" class="btn-primary">
                    <PlusIcon class="h-4 w-4" /> Add your first asset
                </Link>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <SortableTh field="asset_tag" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Asset Tag</SortableTh>
                                <SortableTh field="category" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras" class="hidden md:table-cell">Category / Brand</SortableTh>
                                <SortableTh field="serial_number" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras" class="hidden lg:table-cell">Serial</SortableTh>
                                <SortableTh field="current_status" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Status</SortableTh>
                                <SortableTh field="holder" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras" class="hidden sm:table-cell">Holder / Location</SortableTh>
                                <SortableTh field="purchase_date" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras" class="hidden xl:table-cell">Age <span class="font-normal normal-case text-slate-400">(purchase / service)</span></SortableTh>
                                <SortableTh field="warranty_until" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras" class="hidden lg:table-cell">Warranty</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="assets.data.length === 0">
                                <td colspan="8" class="cell-muted text-center py-10">No assets match your filters.</td>
                            </tr>
                            <tr v-for="a in assets.data" :key="a.id">
                                <td>
                                    <Link :href="`/assets/${a.id}`" class="cell-strong text-brand-600 hover:underline">
                                        {{ a.asset_tag }}
                                    </Link>
                                    <div class="text-[11px] text-slate-500 leading-tight">{{ a.model || '—' }}</div>
                                    <!-- Mobile-only: fold category/brand into the tag cell so it's not lost -->
                                    <div class="md:hidden text-[11px] text-slate-500 leading-tight">
                                        {{ a.category?.name || '—' }}<span v-if="a.brand"> · {{ a.brand.name }}</span>
                                    </div>
                                </td>
                                <td class="hidden md:table-cell">
                                    <div class="font-medium text-slate-700">{{ a.category?.name || '—' }}</div>
                                    <div class="text-[11px] text-slate-500 leading-tight">{{ a.brand?.name || '—' }}</div>
                                </td>
                                <td class="cell-muted hidden lg:table-cell">{{ a.serial_number || '—' }}</td>
                                <td>
                                    <Badge :tone="statusTone[a.current_status] || 'slate'" dot>
                                        {{ a.current_status.replace('_', ' ') }}
                                    </Badge>
                                </td>
                                <td class="hidden sm:table-cell">
                                    <div class="text-slate-700 truncate max-w-[180px]">{{ a.current_holder?.full_name || '—' }}</div>
                                    <div class="text-[11px] text-slate-500 leading-tight truncate max-w-[180px]">{{ a.current_location?.name || '—' }}</div>
                                </td>
                                <td class="hidden xl:table-cell">
                                    <div v-if="a.age_formatted" :class="a.is_eligible_for_replacement ? 'font-semibold text-rose-600' : 'cell-strong'">
                                        {{ a.age_formatted }}
                                    </div>
                                    <div v-else class="text-slate-400">—</div>
                                    <div class="text-[11px] text-slate-500 leading-tight">
                                        <template v-if="a.service_duration_formatted">in service: {{ a.service_duration_formatted }}</template>
                                        <template v-else>not yet deployed</template>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell">
                                    <Badge :tone="warrantyTone[a.warranty_status]" dot>
                                        {{ a.warranty_status === 'expiring_soon' ? 'soon' : a.warranty_status }}
                                    </Badge>
                                    <div class="text-[11px] text-slate-500 leading-tight">{{ a.warranty_until || '—' }}</div>
                                </td>
                                <td class="cell-right">
                                    <Link :href="`/assets/${a.id}`" class="btn-ghost" title="View details">
                                        <EyeIcon class="h-3.5 w-3.5" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="assets.links" :from="assets.from" :to="assets.to" :total="assets.total" />
            </template>
        </div>

        <!-- Skipped-rows report from a completed import -->
        <div v-if="importSkips.length" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <div class="mb-2 text-sm font-semibold text-amber-900">Some rows were skipped:</div>
            <ul class="ml-5 list-disc space-y-1 text-xs text-amber-900">
                <li v-for="(msg, i) in importSkips" :key="i">{{ msg }}</li>
            </ul>
        </div>

        <Modal :show="showImport" title="Import Assets from Excel" max-width="lg" @close="showImport = false">
            <div class="space-y-4 p-5">
                <p class="text-sm text-slate-600">
                    Upload an <code class="rounded bg-slate-100 px-1">.xlsx</code> file to bulk-create assets.
                    Each row becomes one asset. Rows with a duplicate Asset Tag or unknown Category / Brand / Condition / Location will be reported and skipped.
                </p>

                <a href="/assets/import-template"
                   class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <DocumentArrowDownIcon class="h-4 w-4" /> Download Template
                </a>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Excel file</label>
                    <input type="file" accept=".xlsx,.xls"
                           class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700"
                           @change="onImportFile" />
                    <p v-if="importForm.errors.file" class="mt-1 text-xs text-rose-600">{{ importForm.errors.file }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                <button class="btn-secondary" :disabled="importForm.processing" @click="showImport = false">Cancel</button>
                <button class="btn-primary" :disabled="!importForm.file || importForm.processing" @click="submitImport">
                    {{ importForm.processing ? 'Uploading…' : 'Upload & Import' }}
                </button>
            </div>
        </Modal>
    </AppLayout>
</template>
