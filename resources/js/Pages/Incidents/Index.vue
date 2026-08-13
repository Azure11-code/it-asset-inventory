<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import Combobox from '@/Components/Combobox.vue';
import { PlusIcon, EyeIcon, MagnifyingGlassIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ reports: Object, filters: Object });

const STATUS_OPTIONS = [
    { value: 'draft',     label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'approved',  label: 'Approved' },
    { value: 'closed',    label: 'Closed' },
];

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

let timer = null;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/incidents', {
            search: search.value || undefined,
            status: status.value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true, only: ['reports', 'filters'] });
    }, 1000);
});

const hasFilters = computed(() => search.value || status.value);
const statusTone = { draft: 'slate', submitted: 'sky', approved: 'emerald', closed: 'slate' };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Incident Reports" subtitle="Defect or failure reports — prerequisite for a part replacement.">
                <template #actions>
                    <Link href="/incidents/create" class="btn-primary">
                        <PlusIcon class="h-4 w-4" /> New IR
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="grid gap-2 border-b border-slate-100 p-3 sm:grid-cols-[minmax(220px,1fr)_minmax(0,10rem)] sm:items-center">
                <div class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search IR #, problem..." class="input pl-9" />
                </div>
                <Combobox v-model="status" :options="STATUS_OPTIONS" value-key="value" label-key="label" placeholder="All Status" null-label="All Status" />
            </div>

            <EmptyState
                v-if="reports.data.length === 0 && !hasFilters"
                title="No incident reports yet"
                description="File an IR when an IT asset develops a defect — it's required before recording a part replacement."
                :icon="ExclamationTriangleIcon"
            >
                <Link href="/incidents/create" class="btn-primary">
                    <PlusIcon class="h-4 w-4" /> File your first IR
                </Link>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>IR #</th>
                                <th class="hidden md:table-cell">Asset</th>
                                <th class="hidden sm:table-cell">End User</th>
                                <th class="hidden lg:table-cell">Reported Problem</th>
                                <th class="hidden md:table-cell">Date</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="reports.data.length === 0">
                                <td colspan="7" class="cell-muted text-center py-10">No IRs match your filters.</td>
                            </tr>
                            <tr v-for="r in reports.data" :key="r.id">
                                <td>
                                    <Link :href="`/incidents/${r.id}`" class="cell-strong text-brand-600 hover:underline">{{ r.ir_no }}</Link>
                                    <div class="sm:hidden text-[11px] text-slate-500 truncate max-w-[220px]">{{ r.end_user || '—' }}</div>
                                    <div class="md:hidden text-[11px] text-slate-400">{{ r.asset?.asset_tag || '—' }} · {{ r.report_date }}</div>
                                    <div class="lg:hidden text-[11px] text-slate-500 truncate max-w-[220px]">{{ r.reported_problem }}</div>
                                </td>
                                <td class="cell-muted hidden md:table-cell">{{ r.asset?.asset_tag || '—' }}</td>
                                <td class="hidden sm:table-cell">{{ r.end_user || '—' }}</td>
                                <td class="cell-muted max-w-xs truncate hidden lg:table-cell">{{ r.reported_problem }}</td>
                                <td class="cell-muted hidden md:table-cell">{{ r.report_date }}</td>
                                <td>
                                    <Badge :tone="statusTone[r.status] || 'slate'" dot>{{ r.status }}</Badge>
                                </td>
                                <td class="cell-right">
                                    <Link :href="`/incidents/${r.id}`" class="btn-ghost" title="View / Print">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :links="reports.links" :from="reports.from" :to="reports.to" :total="reports.total" />
            </template>
        </div>
    </AppLayout>
</template>
