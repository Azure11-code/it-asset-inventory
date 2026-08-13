<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import Combobox from '@/Components/Combobox.vue';
import { PlusIcon, EyeIcon, MagnifyingGlassIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ recommendations: Object, filters: Object });

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
        router.get('/recommendations', {
            search: search.value || undefined,
            status: status.value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true, only: ['recommendations', 'filters'] });
    }, 1000);
});

const hasFilters = computed(() => search.value || status.value);
const statusTone = { draft: 'slate', submitted: 'sky', approved: 'emerald', closed: 'slate' };
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Recommendations" subtitle="Upgrade or replacement request memos.">
                <template #actions>
                    <Link href="/recommendations/create" class="btn-primary">
                        <PlusIcon class="h-4 w-4" /> New Recommendation
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="grid gap-2 border-b border-slate-100 p-3 sm:grid-cols-[minmax(220px,1fr)_minmax(0,10rem)] sm:items-center">
                <div class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search doc #, subject..." class="input pl-9" />
                </div>
                <Combobox v-model="status" :options="STATUS_OPTIONS" value-key="value" label-key="label" placeholder="All Status" null-label="All Status" />
            </div>

            <EmptyState
                v-if="recommendations.data.length === 0 && !hasFilters"
                title="No recommendations yet"
                description="File a recommendation memo to justify a hardware upgrade or component swap."
                :icon="DocumentTextIcon"
            >
                <Link href="/recommendations/create" class="btn-primary">
                    <PlusIcon class="h-4 w-4" /> File your first recommendation
                </Link>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>Doc #</th>
                                <th class="hidden md:table-cell">Subject</th>
                                <th class="hidden sm:table-cell">Requestor</th>
                                <th class="hidden lg:table-cell">Asset</th>
                                <th class="hidden md:table-cell">Date</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="recommendations.data.length === 0">
                                <td colspan="7" class="cell-muted text-center py-10">No recommendations match your filters.</td>
                            </tr>
                            <tr v-for="r in recommendations.data" :key="r.id">
                                <td>
                                    <Link :href="`/recommendations/${r.id}`" class="cell-strong text-brand-600 hover:underline">{{ r.doc_no }}</Link>
                                    <div class="md:hidden text-[11px] text-slate-500 truncate max-w-[220px]">{{ r.subject }}</div>
                                    <div class="sm:hidden text-[11px] text-slate-400 truncate">{{ r.requestor || '—' }} · {{ r.report_date }}</div>
                                </td>
                                <td class="max-w-xs truncate hidden md:table-cell">{{ r.subject }}</td>
                                <td class="hidden sm:table-cell">{{ r.requestor || '—' }}</td>
                                <td class="cell-muted hidden lg:table-cell">{{ r.asset?.asset_tag || '—' }}</td>
                                <td class="cell-muted hidden md:table-cell">{{ r.report_date }}</td>
                                <td><Badge :tone="statusTone[r.status] || 'slate'" dot>{{ r.status }}</Badge></td>
                                <td class="cell-right">
                                    <Link :href="`/recommendations/${r.id}`" class="btn-ghost" title="View / Print">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :links="recommendations.links" :from="recommendations.from" :to="recommendations.to" :total="recommendations.total" />
            </template>
        </div>
    </AppLayout>
</template>
