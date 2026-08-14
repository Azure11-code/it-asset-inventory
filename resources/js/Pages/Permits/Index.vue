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
import { PlusIcon, EyeIcon, MagnifyingGlassIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({ permits: Object, filters: Object });

const STATUS_OPTIONS = [
    { value: 'draft',     label: 'Draft' },
    { value: 'approved',  label: 'Approved' },
    { value: 'returned',  label: 'Returned' },
    { value: 'cancelled', label: 'Cancelled' },
];

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

let timer = null;
const refresh = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/permits', {
            search: search.value || undefined,
            status: status.value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true, only: ['permits', 'filters'] });
    }, 1000);
};
watch([search, status], refresh);

const hasFilters = computed(() => search.value || status.value);

const statusTone = {
    draft:     'slate',
    approved:  'emerald',
    returned:  'sky',
    cancelled: 'rose',
};

const sortExtras = computed(() => ({
    search: search.value || undefined,
    status: status.value || undefined,
}));
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Permits to Bring Asset" subtitle="Authorize employees to bring assets off-premises.">
                <template #actions>
                    <Link v-if="can('permits', 'create')" href="/permits/create" class="btn-primary">
                        <PlusIcon class="h-4 w-4" /> New Permit
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="grid gap-2 border-b border-slate-100 p-3 sm:grid-cols-[minmax(220px,1fr)_minmax(0,10rem)] sm:items-center">
                <div class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search permit #, destination, purpose..." class="input pl-9" />
                </div>
                <Combobox v-model="status" :options="STATUS_OPTIONS" value-key="value" label-key="label" placeholder="All Status" null-label="All Status" />
            </div>

            <EmptyState
                v-if="permits.data.length === 0 && !hasFilters"
                title="No permits yet"
                description="Create a Permit to Bring Asset when an employee needs to take a device off-site."
                :icon="ClipboardDocumentCheckIcon"
            >
                <Link v-if="can('permits', 'create')" href="/permits/create" class="btn-primary">
                    <PlusIcon class="h-4 w-4" /> Create your first permit
                </Link>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <SortableTh field="permit_no" :sort="filters?.sort" :direction="filters?.direction" url="/permits" :extra="sortExtras">Permit #</SortableTh>
                                <SortableTh field="employee" :sort="filters?.sort" :direction="filters?.direction" url="/permits" :extra="sortExtras" class="hidden sm:table-cell">Employee</SortableTh>
                                <SortableTh field="destination" :sort="filters?.sort" :direction="filters?.direction" url="/permits" :extra="sortExtras" class="hidden lg:table-cell">Destination</SortableTh>
                                <SortableTh field="date_borrow" :sort="filters?.sort" :direction="filters?.direction" url="/permits" :extra="sortExtras" class="hidden md:table-cell">Borrow → Return</SortableTh>
                                <th class="text-right hidden lg:table-cell">Items</th>
                                <SortableTh field="status" :sort="filters?.sort" :direction="filters?.direction" url="/permits" :extra="sortExtras">Status</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="permits.data.length === 0">
                                <td colspan="7" class="cell-muted text-center py-10">No permits match your filters.</td>
                            </tr>
                            <tr v-for="p in permits.data" :key="p.id">
                                <td>
                                    <Link :href="`/permits/${p.id}`" class="cell-strong text-brand-600 hover:underline">{{ p.permit_no }}</Link>
                                    <div class="text-[11px] text-slate-500 truncate max-w-[220px]">{{ p.purpose }}</div>
                                    <!-- Fold employee + date onto mobile -->
                                    <div class="sm:hidden text-[11px] text-slate-500 truncate">{{ p.employee?.name || '—' }}</div>
                                    <div class="md:hidden text-[11px] text-slate-400">{{ p.date_borrow }} → {{ p.date_return }}</div>
                                </td>
                                <td class="hidden sm:table-cell">{{ p.employee?.name || '—' }}</td>
                                <td class="cell-muted hidden lg:table-cell">{{ p.destination }}</td>
                                <td class="hidden md:table-cell">
                                    <div class="text-xs text-slate-700">{{ p.date_borrow }}</div>
                                    <div class="text-[11px] text-slate-500">→ {{ p.date_return }}</div>
                                </td>
                                <td class="cell-right hidden lg:table-cell">{{ p.item_count }}</td>
                                <td>
                                    <Badge :tone="statusTone[p.status] || 'slate'" dot>{{ p.status }}</Badge>
                                </td>
                                <td class="cell-right">
                                    <Link :href="`/permits/${p.id}`" class="btn-ghost" title="View / Print">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="permits.links" :from="permits.from" :to="permits.to" :total="permits.total" />
            </template>
        </div>
    </AppLayout>
</template>
