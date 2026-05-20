<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import SortableTh from '@/Components/SortableTh.vue';
import Combobox from '@/Components/Combobox.vue';
import { PlusIcon, EyeIcon, MagnifyingGlassIcon, CpuChipIcon, ArchiveBoxArrowDownIcon } from '@heroicons/vue/24/outline';

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

let timer = null;
const refresh = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/assets', {
            search: search.value || undefined,
            status: status.value || undefined,
            category_id: category.value || undefined,
            brand_id: brand.value || undefined,
            warranty: warranty.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
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
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Assets" subtitle="Every physical IT peripheral your team owns — one row per device.">
                <template #actions>
                    <Link href="/assets/bulk-receive" class="btn-secondary">
                        <ArchiveBoxArrowDownIcon class="h-4 w-4" /> Bulk Receive
                    </Link>
                    <Link href="/assets/create" class="btn-primary">
                        <PlusIcon class="h-4 w-4" /> New Asset
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                <div class="relative flex-1 min-w-[240px] max-w-sm">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search tag, serial, model..." class="input pl-9" />
                </div>
                <div class="w-40"><Combobox v-model="status" :options="STATUS_OPTIONS" value-key="value" label-key="label" placeholder="All Status" null-label="All Status" /></div>
                <div class="w-44"><Combobox v-model="category" :options="lookups.categories" placeholder="All Categories" null-label="All Categories" /></div>
                <div class="w-40"><Combobox v-model="brand" :options="lookups.brands" placeholder="All Brands" null-label="All Brands" /></div>
                <div class="w-40"><Combobox v-model="warranty" :options="WARRANTY_OPTIONS" value-key="value" label-key="label" placeholder="Any Warranty" null-label="Any Warranty" /></div>
            </div>

            <EmptyState
                v-if="assets.data.length === 0 && !hasFilters"
                title="No assets yet"
                description="Register your IT peripherals to start tracking them through their full lifecycle."
                :icon="CpuChipIcon"
            >
                <Link href="/assets/create" class="btn-primary">
                    <PlusIcon class="h-4 w-4" /> Add your first asset
                </Link>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <SortableTh field="asset_tag" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Asset Tag</SortableTh>
                                <SortableTh field="category" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Category / Brand</SortableTh>
                                <SortableTh field="serial_number" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Serial</SortableTh>
                                <SortableTh field="current_status" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Status</SortableTh>
                                <SortableTh field="holder" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Holder / Location</SortableTh>
                                <SortableTh field="purchase_date" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Age <span class="font-normal normal-case text-slate-400">(purchase / service)</span></SortableTh>
                                <SortableTh field="warranty_until" :sort="filters?.sort" :direction="filters?.direction" url="/assets" :extra="sortExtras">Warranty</SortableTh>
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
                                    <div class="text-xs text-slate-500">{{ a.model || '—' }}</div>
                                </td>
                                <td>
                                    <div class="text-sm font-medium text-slate-700">{{ a.category?.name || '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ a.brand?.name || '—' }}</div>
                                </td>
                                <td class="cell-muted">{{ a.serial_number || '—' }}</td>
                                <td>
                                    <Badge :tone="statusTone[a.current_status] || 'slate'" dot>
                                        {{ a.current_status.replace('_', ' ') }}
                                    </Badge>
                                </td>
                                <td>
                                    <div class="text-sm text-slate-700">{{ a.current_holder?.full_name || '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ a.current_location?.name || '—' }}</div>
                                </td>
                                <td>
                                    <div v-if="a.age_formatted" :class="a.is_eligible_for_replacement ? 'font-semibold text-rose-600' : 'cell-strong'">
                                        {{ a.age_formatted }}
                                    </div>
                                    <div v-else class="text-slate-400">—</div>
                                    <div class="text-xs text-slate-500">
                                        <template v-if="a.service_duration_formatted">in service: {{ a.service_duration_formatted }}</template>
                                        <template v-else>not yet deployed</template>
                                    </div>
                                </td>
                                <td>
                                    <Badge :tone="warrantyTone[a.warranty_status]" dot>
                                        {{ a.warranty_status === 'expiring_soon' ? 'soon' : a.warranty_status }}
                                    </Badge>
                                    <div class="text-xs text-slate-500">{{ a.warranty_until || '—' }}</div>
                                </td>
                                <td class="cell-right">
                                    <Link :href="`/assets/${a.id}`" class="btn-ghost" title="View details">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="assets.links" :from="assets.from" :to="assets.to" :total="assets.total" />
            </template>
        </div>
    </AppLayout>
</template>
