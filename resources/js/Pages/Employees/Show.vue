<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import Attachments from '@/Components/Attachments.vue';
import {
    ChevronLeftIcon, EnvelopeIcon, PhoneIcon, BuildingOffice2Icon, MapPinIcon,
    CalendarIcon, DocumentTextIcon, CpuChipIcon, ArrowsRightLeftIcon,
    ClipboardDocumentCheckIcon, ExclamationTriangleIcon, PaperClipIcon,
    IdentificationIcon, PencilSquareIcon, ArrowDownTrayIcon,
    ArrowRightIcon, ArrowLeftIcon,
} from '@heroicons/vue/24/outline';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    employee:        Object,
    held_assets:     Array,
    movements:       Array,
    permits:         Array,
    incidents:       Array,
    recommendations: Array,
    attachments:     Array,
    stats:           Object,
});

const initials = computed(() => {
    const f = (props.employee.first_name || '').charAt(0);
    const l = (props.employee.last_name  || '').charAt(0);
    return (f + l).toUpperCase();
});

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const statusTone = {
    active:   'emerald',
    inactive: 'slate',
    resigned: 'rose',
};

const assetStatusTone = {
    in_stock:   'sky',
    assigned:   'brand',
    for_repair: 'amber',
    defective:  'rose',
    retired:    'slate',
    replaced:   'slate',
};

const permitStatusTone = {
    draft:     'slate',
    approved:  'emerald',
    returned:  'sky',
    cancelled: 'rose',
};

const incidentStatusTone = {
    draft:     'slate',
    submitted: 'sky',
    approved:  'emerald',
    closed:    'slate',
};

const recStatusTone = {
    draft:     'slate',
    submitted: 'sky',
    approved:  'emerald',
    closed:    'slate',
};

const movementLabel = { issuance: 'Issued', return: 'Returned', transfer: 'Transferred' };

// ── Local attachments ref so Attachments component's @updated event can update the list in-place ──
const localAttachments = ref([...props.attachments]);
const reloadAttachments = async () => {
    try {
        const res  = await fetch(`/accountability/${props.employee.id}/attachments`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        localAttachments.value = data.attachments || [];
    } catch (e) { /* noop */ }
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :title="employee.full_name" :subtitle="employee.position || 'No position on file'">
                <template #actions>
                    <Link href="/employees" class="btn-secondary">
                        <ChevronLeftIcon class="h-4 w-4" /> Back to list
                    </Link>
                    <a v-if="can('accountability', 'print')" :href="`/accountability/${employee.id}/download`" class="btn-secondary">
                        <ArrowDownTrayIcon class="h-4 w-4" /> Accountability form
                    </a>
                    <Link v-if="can('employees', 'edit')" :href="`/employees?edit=${employee.id}`" class="btn-primary">
                        <PencilSquareIcon class="h-4 w-4" /> Edit
                    </Link>
                </template>
            </PageHeader>
        </template>

        <!-- Identity card -->
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_320px] mb-5">
            <section class="card overflow-hidden">
                <div class="flex flex-col sm:flex-row gap-4 p-5 bg-gradient-to-br from-brand-50 via-white to-white">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-2xl font-semibold text-white shadow-md">
                        {{ initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-slate-900 truncate">{{ employee.full_name }}</h2>
                            <Badge :tone="statusTone[employee.status] || 'slate'" dot>{{ employee.status }}</Badge>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px] text-slate-600">
                            <span class="inline-flex items-center gap-1.5"><IdentificationIcon class="h-3.5 w-3.5 text-slate-400" /> {{ employee.employee_no }}</span>
                            <span v-if="employee.position" class="inline-flex items-center gap-1.5"><DocumentTextIcon class="h-3.5 w-3.5 text-slate-400" /> {{ employee.position }}</span>
                            <span v-if="employee.department" class="inline-flex items-center gap-1.5"><BuildingOffice2Icon class="h-3.5 w-3.5 text-slate-400" /> {{ employee.department }}</span>
                            <span v-if="employee.location" class="inline-flex items-center gap-1.5"><MapPinIcon class="h-3.5 w-3.5 text-slate-400" /> {{ employee.location }}</span>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 text-[12.5px]">
                            <div v-if="employee.email" class="flex items-center gap-2 text-slate-700">
                                <EnvelopeIcon class="h-4 w-4 text-slate-400" />
                                <a :href="`mailto:${employee.email}`" class="truncate hover:text-brand-600 hover:underline">{{ employee.email }}</a>
                            </div>
                            <div v-if="employee.contact_no" class="flex items-center gap-2 text-slate-700">
                                <PhoneIcon class="h-4 w-4 text-slate-400" /> {{ employee.contact_no }}
                            </div>
                            <div class="flex items-center gap-2 text-slate-700">
                                <CalendarIcon class="h-4 w-4 text-slate-400" />
                                <span>Hired <b>{{ fmt(employee.date_hired) }}</b></span>
                            </div>
                            <div v-if="employee.date_resigned" class="flex items-center gap-2 text-rose-600">
                                <CalendarIcon class="h-4 w-4 text-rose-400" />
                                <span>Resigned <b>{{ fmt(employee.date_resigned) }}</b></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="employee.notes" class="border-t border-slate-100 bg-amber-50/50 px-5 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700 mb-1">Notes</div>
                    <p class="text-[12.5px] text-slate-700 whitespace-pre-wrap">{{ employee.notes }}</p>
                </div>
            </section>

            <!-- Stats card -->
            <section class="card">
                <header class="card-header py-2.5 px-4">
                    <div>
                        <h2 class="card-title text-sm">At a glance</h2>
                        <p class="card-subtitle text-[11px]">Everything linked to this employee.</p>
                    </div>
                </header>
                <div class="grid grid-cols-2 gap-1 p-2">
                    <a href="#held-assets" class="stat-tile group">
                        <CpuChipIcon class="stat-icon text-sky-500" />
                        <div>
                            <div class="stat-num">{{ stats.held_assets_count }}</div>
                            <div class="stat-label">Held assets</div>
                        </div>
                    </a>
                    <a href="#movements" class="stat-tile group">
                        <ArrowsRightLeftIcon class="stat-icon text-indigo-500" />
                        <div>
                            <div class="stat-num">{{ stats.movements_count }}</div>
                            <div class="stat-label">Movements</div>
                        </div>
                    </a>
                    <a href="#permits" class="stat-tile group">
                        <ClipboardDocumentCheckIcon class="stat-icon text-emerald-500" />
                        <div>
                            <div class="stat-num">{{ stats.permits_count }}</div>
                            <div class="stat-label">Permits</div>
                        </div>
                    </a>
                    <a href="#incidents" class="stat-tile group">
                        <ExclamationTriangleIcon class="stat-icon text-rose-500" />
                        <div>
                            <div class="stat-num">{{ stats.incidents_count }}</div>
                            <div class="stat-label">Incidents</div>
                        </div>
                    </a>
                    <a href="#recommendations" class="stat-tile group">
                        <DocumentTextIcon class="stat-icon text-fuchsia-500" />
                        <div>
                            <div class="stat-num">{{ stats.recommendations_count }}</div>
                            <div class="stat-label">Recommendations</div>
                        </div>
                    </a>
                    <a href="#files" class="stat-tile group">
                        <PaperClipIcon class="stat-icon text-amber-500" />
                        <div>
                            <div class="stat-num">{{ stats.attachments_count }}</div>
                            <div class="stat-label">Signed files</div>
                        </div>
                    </a>
                </div>
            </section>
        </div>

        <!-- Held Assets -->
        <section id="held-assets" class="card mb-5 scroll-mt-20">
            <header class="card-header py-2.5 px-4">
                <div>
                    <h2 class="card-title text-sm flex items-center gap-1.5">
                        <CpuChipIcon class="h-4 w-4 text-sky-500" /> Currently held assets
                    </h2>
                    <p class="card-subtitle text-[11px]">Devices assigned right now.</p>
                </div>
                <Badge tone="sky" dot>{{ held_assets.length }}</Badge>
            </header>
            <div v-if="held_assets.length === 0" class="px-4 py-8 text-center text-xs text-slate-500">
                <CpuChipIcon class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                No assets currently held.
            </div>
            <div v-else class="table-wrap">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th class="hidden sm:table-cell">Category</th>
                            <th class="hidden md:table-cell">Brand / Model</th>
                            <th class="hidden lg:table-cell">Serial</th>
                            <th class="hidden md:table-cell">Location</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in held_assets" :key="a.id">
                            <td class="cell-strong">{{ a.asset_tag }}</td>
                            <td class="hidden sm:table-cell">{{ a.category || '—' }}</td>
                            <td class="hidden md:table-cell">
                                <div>{{ a.brand || '—' }}</div>
                                <div class="text-[11px] text-slate-500">{{ a.model || '' }}</div>
                            </td>
                            <td class="hidden lg:table-cell">{{ a.serial_number || '—' }}</td>
                            <td class="hidden md:table-cell">{{ a.current_location || '—' }}</td>
                            <td><Badge :tone="assetStatusTone[a.current_status] || 'slate'" dot>{{ a.current_status }}</Badge></td>
                            <td class="cell-right">
                                <Link :href="`/assets/${a.id}`" class="btn-ghost" title="Open asset">
                                    <ArrowRightIcon class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Movement history — timeline -->
        <section id="movements" class="card mb-5 scroll-mt-20">
            <header class="card-header py-2.5 px-4">
                <div>
                    <h2 class="card-title text-sm flex items-center gap-1.5">
                        <ArrowsRightLeftIcon class="h-4 w-4 text-indigo-500" /> Asset movement history
                    </h2>
                    <p class="card-subtitle text-[11px]">Latest 50 issuances, returns, and transfers.</p>
                </div>
                <Badge tone="brand" dot>{{ movements.length }}</Badge>
            </header>
            <div v-if="movements.length === 0" class="px-4 py-8 text-center text-xs text-slate-500">
                <ArrowsRightLeftIcon class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                No movement history.
            </div>
            <ol v-else class="relative p-4 pl-8 space-y-3">
                <div class="absolute left-4 top-4 bottom-4 w-px bg-slate-200"></div>
                <li v-for="m in movements" :key="m.id" class="relative">
                    <span
                        :class="[
                            'absolute -left-[15px] top-1 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-white',
                            m.type === 'issuance' ? 'bg-emerald-500'
                                : m.type === 'return' ? 'bg-sky-500'
                                : 'bg-indigo-500',
                        ]"
                    >
                        <component
                            :is="m.is_incoming ? ArrowLeftIcon : ArrowRightIcon"
                            class="h-2.5 w-2.5 text-white"
                        />
                    </span>
                    <div class="text-[12.5px]">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-medium text-slate-900">{{ movementLabel[m.type] || m.type }}</span>
                            <Link v-if="m.asset_id" :href="`/assets/${m.asset_id}`" class="font-mono text-brand-600 hover:underline">
                                {{ m.asset_tag }}
                            </Link>
                            <span class="text-slate-500">{{ fmt(m.movement_date) }}</span>
                        </div>
                        <div class="text-[11px] text-slate-500">
                            <template v-if="m.type === 'issuance'">
                                Issued to <b>{{ m.to_employee }}</b>
                            </template>
                            <template v-else-if="m.type === 'return'">
                                Returned by <b>{{ m.from_employee }}</b>
                            </template>
                            <template v-else>
                                From <b>{{ m.from_employee || '—' }}</b> → <b>{{ m.to_employee || '—' }}</b>
                            </template>
                        </div>
                    </div>
                </li>
            </ol>
        </section>

        <!-- Permits + Incidents (side by side on wide screens) -->
        <div class="grid gap-5 lg:grid-cols-2 mb-5">
            <section id="permits" class="card scroll-mt-20">
                <header class="card-header py-2.5 px-4">
                    <div>
                        <h2 class="card-title text-sm flex items-center gap-1.5">
                            <ClipboardDocumentCheckIcon class="h-4 w-4 text-emerald-500" /> Permits
                        </h2>
                        <p class="card-subtitle text-[11px]">As borrower or requestor.</p>
                    </div>
                    <Badge tone="emerald" dot>{{ permits.length }}</Badge>
                </header>
                <div v-if="permits.length === 0" class="px-4 py-8 text-center text-xs text-slate-500">
                    No permits on file.
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="p in permits" :key="p.id" class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <Link :href="`/permits/${p.id}`" class="font-mono text-[12.5px] font-semibold text-brand-600 hover:underline truncate">
                                        {{ p.permit_no }}
                                    </Link>
                                    <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded-md"
                                          :class="p.role === 'borrower' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-600'">
                                        {{ p.role }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-600 truncate">{{ p.destination }} — {{ p.purpose }}</div>
                                <div class="text-[10px] text-slate-400">
                                    {{ fmt(p.date_borrow) }} → {{ fmt(p.date_return) }} · {{ p.item_count }} item{{ p.item_count === 1 ? '' : 's' }}
                                </div>
                            </div>
                            <Badge :tone="permitStatusTone[p.status] || 'slate'" dot>{{ p.status }}</Badge>
                        </div>
                    </li>
                </ul>
            </section>

            <section id="incidents" class="card scroll-mt-20">
                <header class="card-header py-2.5 px-4">
                    <div>
                        <h2 class="card-title text-sm flex items-center gap-1.5">
                            <ExclamationTriangleIcon class="h-4 w-4 text-rose-500" /> Incident reports
                        </h2>
                        <p class="card-subtitle text-[11px]">As the affected end user.</p>
                    </div>
                    <Badge tone="rose" dot>{{ incidents.length }}</Badge>
                </header>
                <div v-if="incidents.length === 0" class="px-4 py-8 text-center text-xs text-slate-500">
                    No incident reports.
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="i in incidents" :key="i.id" class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <Link :href="`/incidents/${i.id}`" class="font-mono text-[12.5px] font-semibold text-brand-600 hover:underline truncate">
                                        {{ i.ir_no }}
                                    </Link>
                                    <Link v-if="i.asset_id" :href="`/assets/${i.asset_id}`" class="font-mono text-[11px] text-slate-500 hover:text-brand-600 hover:underline">
                                        {{ i.asset_tag }}
                                    </Link>
                                </div>
                                <div class="text-[11px] text-slate-600 truncate">{{ i.reported_problem }}</div>
                                <div class="text-[10px] text-slate-400">{{ fmt(i.report_date) }}</div>
                            </div>
                            <Badge :tone="incidentStatusTone[i.status] || 'slate'" dot>{{ i.status }}</Badge>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Recommendations -->
        <section id="recommendations" class="card mb-5 scroll-mt-20">
            <header class="card-header py-2.5 px-4">
                <div>
                    <h2 class="card-title text-sm flex items-center gap-1.5">
                        <DocumentTextIcon class="h-4 w-4 text-fuchsia-500" /> Recommendations filed
                    </h2>
                    <p class="card-subtitle text-[11px]">Requests this employee originated.</p>
                </div>
                <Badge tone="brand" dot>{{ recommendations.length }}</Badge>
            </header>
            <div v-if="recommendations.length === 0" class="px-4 py-8 text-center text-xs text-slate-500">
                No recommendations filed.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="r in recommendations" :key="r.id" class="px-4 py-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <Link :href="`/recommendations/${r.id}`" class="font-mono text-[12.5px] font-semibold text-brand-600 hover:underline truncate">
                                {{ r.doc_no }}
                            </Link>
                            <div class="text-[11px] text-slate-600 truncate">{{ r.subject }}</div>
                            <div class="text-[10px] text-slate-400">{{ fmt(r.report_date) }}</div>
                        </div>
                        <Badge :tone="recStatusTone[r.status] || 'slate'" dot>{{ r.status }}</Badge>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Signed files (attachments) -->
        <section id="files" class="mb-5 scroll-mt-20">
            <Attachments
                v-if="can('accountability', 'view')"
                entity="accountability"
                :entity-id="employee.id"
                resource-key="accountability"
                :attachments="localAttachments"
                title="Signed accountability files"
                subtitle="Scanned signed copies of accountability forms and related documents. Also visible in the Gallery."
                @updated="reloadAttachments"
            />
        </section>
    </AppLayout>
</template>

<style scoped>
.stat-tile {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem;
    border-radius: 0.5rem;
    transition: background 0.15s;
    cursor: pointer;
    color: rgb(15 23 42);
}
.stat-tile:hover { background: rgb(248 250 252); }
.stat-icon { height: 1.5rem; width: 1.5rem; flex-shrink: 0; }
.stat-num { font-size: 1.125rem; font-weight: 600; line-height: 1; color: rgb(15 23 42); }
.stat-label { font-size: 10.5px; color: rgb(100 116 139); margin-top: 2px; }
</style>
