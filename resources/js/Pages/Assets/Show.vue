<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import Combobox from '@/Components/Combobox.vue';
import AssetTag from '@/Components/AssetTag.vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const RETURN_STATUSES = [
    { value: 'in_stock',   label: 'In Stock (ready to re-issue)' },
    { value: 'for_repair', label: 'For Repair' },
    { value: 'defective',  label: 'Defective' },
];
import {
    PencilSquareIcon, TrashIcon, ClockIcon, IdentificationIcon,
    ShieldCheckIcon, CalendarDaysIcon, BanknotesIcon, MapPinIcon,
    UserIcon, ChevronLeftIcon, ArrowsRightLeftIcon, ArrowDownTrayIcon,
    ArrowUpOnSquareIcon, ArrowDownOnSquareIcon,
    WrenchScrewdriverIcon, PlusIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({ asset: Object, lookups: Object });

const statusTone = {
    in_stock: 'sky', assigned: 'brand', for_repair: 'amber',
    defective: 'rose', retired: 'slate', replaced: 'slate',
};
const warrantyTone = { active: 'emerald', expiring_soon: 'amber', expired: 'rose', unknown: 'slate' };

const movementIcon = { issuance: ArrowUpOnSquareIcon, return: ArrowDownTrayIcon, transfer: ArrowsRightLeftIcon };
const movementTone = { issuance: 'brand', return: 'amber', transfer: 'sky' };

const money = (n) => n == null ? '—' : new Intl.NumberFormat(undefined, { style: 'currency', currency: 'PHP' }).format(Number(n));
const today = () => new Date().toISOString().slice(0, 10);

const showDelete = ref(false);
const doDelete = () => router.delete(`/assets/${props.asset.id}`);

const showPrintTag = ref(false);
const downloading = ref(false);
const assetTagRef = ref(null);
const doDownload = async () => {
    if (!assetTagRef.value) return;
    downloading.value = true;
    try {
        await assetTagRef.value.download(`asset-tag-${props.asset.asset_tag}.png`);
    } finally {
        downloading.value = false;
    }
};

const ageColor = computed(() => props.asset.is_eligible_for_replacement ? 'text-rose-600' : 'text-slate-900');

const canIssue    = computed(() => ['in_stock', 'for_repair'].includes(props.asset.current_status));
const canReturn   = computed(() => props.asset.current_status === 'assigned');
const canTransfer = computed(() => ['in_stock', 'assigned'].includes(props.asset.current_status));

// --- Issue modal ---
const showIssue = ref(false);
const issueForm = useForm({
    to_employee_id: '',
    to_location_id: props.asset.current_location?.id ?? '',
    movement_date: today(),
    reference: '',
    remarks: '',
});
const openIssue = () => {
    issueForm.reset();
    issueForm.movement_date = today();
    issueForm.to_location_id = props.asset.current_location?.id ?? '';
    showIssue.value = true;
};
// Force a re-fetch of the current asset page after any state-changing action.
const reloadAsset = () => router.reload({ only: ['asset'], preserveScroll: true });

const submitIssue = () => issueForm.post(`/assets/${props.asset.id}/issue`, {
    onSuccess: () => { showIssue.value = false; issueForm.reset(); reloadAsset(); },
    preserveScroll: true,
});

// --- Return modal ---
const showReturn = ref(false);
const returnForm = useForm({
    to_location_id: props.asset.current_location?.id ?? '',
    movement_date: today(),
    new_status: 'in_stock',
    new_condition_id: props.asset.condition?.id ?? '',
    reference: '',
    remarks: '',
});
const openReturn = () => {
    returnForm.reset();
    returnForm.movement_date = today();
    returnForm.new_status = 'in_stock';
    returnForm.new_condition_id = props.asset.condition?.id ?? '';
    returnForm.to_location_id = props.asset.current_location?.id ?? '';
    showReturn.value = true;
};
const submitReturn = () => returnForm.post(`/assets/${props.asset.id}/return`, {
    onSuccess: () => { showReturn.value = false; returnForm.reset(); reloadAsset(); },
    preserveScroll: true,
});

// --- Transfer modal ---
const showTransfer = ref(false);
const transferForm = useForm({
    to_employee_id: '',
    to_location_id: '',
    movement_date: today(),
    reference: '',
    remarks: '',
});
const openTransfer = () => {
    transferForm.reset();
    transferForm.movement_date = today();
    showTransfer.value = true;
};
const submitTransfer = () => transferForm.post(`/assets/${props.asset.id}/transfer`, {
    onSuccess: () => { showTransfer.value = false; transferForm.reset(); reloadAsset(); },
    preserveScroll: true,
});

// --- Movement details / edit modal ---
const showMovement = ref(false);
const editingMovement = ref(null);
const movementForm = useForm({
    movement_date: '',
    from_employee_id: '',
    to_employee_id: '',
    from_location_id: '',
    to_location_id: '',
    reference: '',
    remarks: '',
});
const openMovement = (m) => {
    editingMovement.value = m;
    movementForm.clearErrors();
    movementForm.movement_date    = m.movement_date ?? '';
    movementForm.from_employee_id = m.from_employee?.id ?? '';
    movementForm.to_employee_id   = m.to_employee?.id ?? '';
    movementForm.from_location_id = m.from_location?.id ?? '';
    movementForm.to_location_id   = m.to_location?.id ?? '';
    movementForm.reference        = m.reference ?? '';
    movementForm.remarks          = m.remarks ?? '';
    showMovement.value = true;
};
const submitMovement = () => movementForm.patch(`/assets/${props.asset.id}/movements/${editingMovement.value.id}`, {
    onSuccess: () => { showMovement.value = false; editingMovement.value = null; },
    preserveScroll: true,
});
const showMovementDelete = ref(false);
const deleteMovement = () => {
    router.delete(`/assets/${props.asset.id}/movements/${editingMovement.value.id}`, {
        onFinish: () => {
            showMovementDelete.value = false;
            showMovement.value = false;
            editingMovement.value = null;
        },
        preserveScroll: true,
    });
};

// --- Part Changes ---
const PART_PRESETS = ['RAM', 'SSD', 'HDD', 'GPU', 'CPU', 'Motherboard', 'PSU', 'Cooler', 'Case', 'Keyboard', 'Mouse', 'Monitor', 'Other'];
const REASON_PRESETS = ['Upgrade', 'Failure', 'Defective', 'End of life', 'Compatibility', 'Other'];

const showPartChange = ref(false);
const editingPartChange = ref(null);
const partForm = useForm({
    part_name: '',
    old_value: '',
    new_value: '',
    reason: '',
    changed_at: today(),
    performed_by_user_id: '',
    notes: '',
    incident_report_id: null,
    recommendation_id: null,
});
const openPartCreate = () => {
    editingPartChange.value = null;
    partForm.reset();
    partForm.changed_at = today();
    showPartChange.value = true;
};
const openPartEdit = (pc) => {
    editingPartChange.value = pc;
    partForm.clearErrors();
    partForm.part_name            = pc.part_name;
    partForm.old_value            = pc.old_value ?? '';
    partForm.new_value            = pc.new_value;
    partForm.reason               = pc.reason ?? '';
    partForm.changed_at           = pc.changed_at ?? today();
    partForm.performed_by_user_id = pc.performer?.id ?? '';
    partForm.notes                = pc.notes ?? '';
    partForm.incident_report_id   = pc.incident_report_id ?? null;
    partForm.recommendation_id    = pc.recommendation_id ?? null;
    showPartChange.value = true;
};
const submitPartChange = () => {
    const opts = { onSuccess: () => { showPartChange.value = false; partForm.reset(); }, preserveScroll: true };
    if (editingPartChange.value) {
        partForm.patch(`/assets/${props.asset.id}/part-changes/${editingPartChange.value.id}`, opts);
    } else {
        partForm.post(`/assets/${props.asset.id}/part-changes`, opts);
    }
};
const showPartDelete = ref(false);
const partToDelete = ref(null);
const askDeletePart = (pc) => { partToDelete.value = pc; showPartDelete.value = true; };
const deletePart = () => router.delete(`/assets/${props.asset.id}/part-changes/${partToDelete.value.id}`, {
    onFinish: () => { showPartDelete.value = false; partToDelete.value = null; },
    preserveScroll: true,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <Link href="/assets" class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-700">
                        <ChevronLeftIcon class="h-3.5 w-3.5" /> Back to assets
                    </Link>
                    <h1 class="mt-1 text-lg sm:text-xl font-semibold tracking-tight text-slate-900 leading-tight break-all">{{ asset.asset_tag }}</h1>
                    <p class="text-xs text-slate-500">
                        {{ asset.category?.name || 'Uncategorized' }}
                        <template v-if="asset.brand"> · {{ asset.brand.name }}</template>
                        <template v-if="asset.model"> · {{ asset.model }}</template>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                    <button v-if="canIssue    && can('assets', 'edit')" class="btn-primary"   @click="openIssue">
                        <ArrowUpOnSquareIcon class="h-3.5 w-3.5" /> Issue
                    </button>
                    <button v-if="canReturn   && can('assets', 'edit')" class="btn-primary"   @click="openReturn">
                        <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Return
                    </button>
                    <button v-if="canTransfer && can('assets', 'edit')" class="btn-secondary" @click="openTransfer">
                        <ArrowsRightLeftIcon class="h-3.5 w-3.5" /> Transfer
                    </button>
                    <button v-if="can('assets', 'print')" class="btn-secondary" @click="showPrintTag = true">
                        <ArrowDownOnSquareIcon class="h-3.5 w-3.5" /> Tag
                    </button>
                    <Link v-if="can('assets', 'edit')" :href="`/assets/${asset.id}/edit`" class="btn-secondary">
                        <PencilSquareIcon class="h-3.5 w-3.5" /> Edit
                    </Link>
                    <button v-if="can('assets', 'delete')" class="btn-ghost-danger" @click="showDelete = true" title="Delete">
                        <TrashIcon class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </template>

        <!-- Replacement-eligible banner -->
        <div v-if="asset.is_eligible_for_replacement"
             class="mb-4 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50/60 p-2.5">
            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-100">
                <ClockIcon class="h-3.5 w-3.5 text-rose-600" />
            </div>
            <div>
                <p class="text-xs font-semibold text-rose-900">Eligible for replacement</p>
                <p class="text-[11px] text-rose-700">
                    {{ asset.age_years }} years old — past its {{ asset.expected_lifespan_years }}-year service life. Qualifies for automatic replacement if reported broken.
                </p>
            </div>
        </div>

        <!-- Quick facts grid -->
        <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-3">
                <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    <IdentificationIcon class="h-3.5 w-3.5" /> Status
                </div>
                <div class="mt-1.5">
                    <Badge :tone="statusTone[asset.current_status]" dot>{{ asset.current_status.replace('_', ' ') }}</Badge>
                </div>
                <p v-if="asset.current_holder" class="mt-1.5 text-xs text-slate-600">
                    <UserIcon class="inline h-3.5 w-3.5 -mt-0.5" /> {{ asset.current_holder.full_name }}
                </p>
                <p v-if="asset.current_holder" class="text-[11px] text-slate-500">
                    <template v-if="asset.assigned_since">
                        {{ asset.is_first_issuance ? 'Deployed' : 'Transferred' }}: {{ asset.assigned_since }}
                    </template>
                    <template v-else>new — no transfer date</template>
                </p>
                <p v-if="asset.current_location" class="text-xs text-slate-500">
                    <MapPinIcon class="inline h-3.5 w-3.5 -mt-0.5" /> {{ asset.current_location.name }}
                </p>
            </div>

            <div class="card p-3">
                <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    <CalendarDaysIcon class="h-3.5 w-3.5" /> Age
                </div>
                <p class="mt-1.5 text-lg font-semibold tracking-tight leading-tight" :class="ageColor">
                    {{ asset.age_formatted || '—' }}
                </p>
                <p class="text-[11px] text-slate-500">
                    Purchased {{ asset.purchase_date || 'unknown' }} · {{ asset.expected_lifespan_years }}yr lifespan
                </p>
                <p v-if="asset.service_duration_formatted" class="mt-0.5 text-[11px] text-slate-500">
                    In service: <span class="font-medium text-slate-700">{{ asset.service_duration_formatted }}</span>
                    <template v-if="asset.deployment_date"> (since {{ asset.deployment_date }})</template>
                </p>
            </div>

            <div class="card p-3">
                <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    <ShieldCheckIcon class="h-3.5 w-3.5" /> Warranty
                </div>
                <div class="mt-1.5">
                    <Badge :tone="warrantyTone[asset.warranty_status]" dot>
                        {{ asset.warranty_status === 'expiring_soon' ? 'expiring soon' : asset.warranty_status }}
                    </Badge>
                </div>
                <p class="mt-1 text-[11px] text-slate-500">Until {{ asset.warranty_until || '—' }}</p>
            </div>

            <div class="card p-3">
                <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    <BanknotesIcon class="h-3.5 w-3.5" /> Acquisition
                </div>
                <p class="mt-1.5 text-lg font-semibold tracking-tight leading-tight text-slate-900">{{ money(asset.purchase_cost) }}</p>
                <p class="text-[11px] text-slate-500">
                    Condition:
                    <Badge v-if="asset.condition" :tone="asset.condition.tone" class="ml-1">{{ asset.condition.name }}</Badge>
                    <span v-else class="ml-1 text-slate-400">—</span>
                </p>
            </div>
        </div>

        <!-- Details + Movement history -->
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <section class="card">
                <header class="card-header py-2.5 px-4">
                    <div><h2 class="card-title text-sm">Asset Details</h2></div>
                </header>
                <dl class="divide-y divide-slate-100">
                    <div class="grid grid-cols-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">Serial #</dt>
                        <dd class="col-span-2 text-xs font-medium text-slate-900">{{ asset.serial_number || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">Model</dt>
                        <dd class="col-span-2 text-xs font-medium text-slate-900">{{ asset.model || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">Description</dt>
                        <dd class="col-span-2 text-xs text-slate-700">{{ asset.description || '—' }}</dd>
                    </div>
                    <div class="px-4 py-2">
                        <div class="flex items-center justify-between">
                            <dt class="text-xs text-slate-500">Specifications</dt>
                            <Badge :tone="asset.specifications?.length ? 'emerald' : 'slate'" dot>
                                {{ asset.specifications?.length
                                    ? `${asset.specifications.length} item${asset.specifications.length === 1 ? '' : 's'}`
                                    : 'Empty' }}
                            </Badge>
                        </div>
                        <dd v-if="asset.specifications?.length" class="mt-1.5 divide-y divide-slate-100 rounded-md border border-slate-100 bg-slate-50/60">
                            <div
                                v-for="(item, i) in asset.specifications"
                                :key="i"
                                class="grid grid-cols-3 gap-2 px-2.5 py-1.5 text-xs"
                            >
                                <div class="font-medium text-slate-600">
                                    {{ (item && typeof item === 'object' ? item.key : '') || '—' }}
                                </div>
                                <div class="col-span-2 text-slate-900">
                                    {{ item && typeof item === 'object' ? (item.value || '—') : item }}
                                </div>
                            </div>
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">Purchase Date</dt>
                        <dd class="col-span-2 text-xs font-medium text-slate-900">{{ asset.purchase_date || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">Deployment Date</dt>
                        <dd class="col-span-2 text-xs font-medium text-slate-900">{{ asset.deployment_date || '—' }}</dd>
                    </div>
                    <div v-if="asset.notes" class="px-4 py-2">
                        <dt class="text-xs text-slate-500">Notes</dt>
                        <dd class="mt-1 text-xs text-slate-700 whitespace-pre-line">{{ asset.notes }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card lg:col-span-2">
                <header class="card-header py-2.5 px-4">
                    <div>
                        <h2 class="card-title text-sm">Movement History</h2>
                        <p class="card-subtitle text-[11px]">Every issuance, return, or transfer of this device.</p>
                    </div>
                </header>

                <EmptyState
                    v-if="asset.movements.length === 0"
                    title="No movements yet"
                    description="Click Issue to Employee above to assign this asset and start the audit trail."
                    :icon="ClockIcon"
                />

                <ol v-else class="divide-y divide-slate-100">
                    <li v-for="m in asset.movements" :key="m.id">
                        <button
                            type="button"
                            class="flex w-full gap-3 px-4 py-2.5 text-left transition-colors hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                            @click="openMovement(m)"
                        >
                            <div :class="['flex h-7 w-7 shrink-0 items-center justify-center rounded-full', `badge-${movementTone[m.type]}`]">
                                <component :is="movementIcon[m.type]" class="h-3.5 w-3.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-xs font-semibold text-slate-900 capitalize">{{ m.type }}</p>
                                    <p class="text-[11px] text-slate-500">{{ m.movement_date }}</p>
                                </div>
                                <p class="text-xs text-slate-600">
                                    <template v-if="m.type === 'issuance'">
                                        Issued to <span class="font-medium text-slate-800">{{ m.to_employee?.full_name || '—' }}</span>
                                        <template v-if="m.to_location"> at {{ m.to_location.name }}</template>
                                    </template>
                                    <template v-else-if="m.type === 'return'">
                                        Returned by <span class="font-medium text-slate-800">{{ m.from_employee?.full_name || '—' }}</span>
                                    </template>
                                    <template v-else-if="m.type === 'transfer'">
                                        From <span class="font-medium text-slate-800">{{ m.from_employee?.full_name || m.from_location?.name || '—' }}</span>
                                        → <span class="font-medium text-slate-800">{{ m.to_employee?.full_name || m.to_location?.name || '—' }}</span>
                                    </template>
                                </p>
                                <p v-if="m.remarks" class="mt-0.5 text-[11px] text-slate-500">{{ m.remarks }}</p>
                                <p v-if="m.reference" class="text-[11px] text-slate-400">Ref: {{ m.reference }}</p>
                                <p v-if="m.performer" class="text-[11px] text-slate-400">by {{ m.performer.name }}</p>
                            </div>
                        </button>
                    </li>
                </ol>
            </section>

            <!-- ── Part Changes ── -->
            <section class="card lg:col-span-2">
                <header class="card-header py-2.5 px-4">
                    <div>
                        <h2 class="card-title text-sm">Part Changes</h2>
                        <p class="card-subtitle text-[11px]">Log component swaps and upgrades (RAM, SSD, GPU, etc.).</p>
                    </div>
                    <button v-if="can('assets', 'edit')" class="btn-primary" @click="openPartCreate">
                        <PlusIcon class="h-3.5 w-3.5" /> Add Part Change
                    </button>
                </header>

                <EmptyState
                    v-if="(asset.part_changes ?? []).length === 0"
                    title="No part changes yet"
                    description="When a component is replaced or upgraded, log it here so the device's hardware history stays accurate."
                    :icon="WrenchScrewdriverIcon"
                />

                <ol v-else class="divide-y divide-slate-100">
                    <li v-for="pc in asset.part_changes" :key="pc.id" class="px-4 py-2.5">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-100">
                                <WrenchScrewdriverIcon class="h-3.5 w-3.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="text-xs font-semibold text-slate-900">{{ pc.part_name }}</p>
                                        <Badge v-if="pc.reason" tone="amber">{{ pc.reason }}</Badge>
                                        <Link v-if="pc.incident_report" :href="`/incidents/${pc.incident_report.id}`" class="inline-flex items-center">
                                            <Badge tone="rose">IR · {{ pc.incident_report.ir_no }}</Badge>
                                        </Link>
                                        <Link v-if="pc.recommendation" :href="`/recommendations/${pc.recommendation.id}`" class="inline-flex items-center">
                                            <Badge tone="brand">REC · {{ pc.recommendation.doc_no }}</Badge>
                                        </Link>
                                    </div>
                                    <p class="text-[11px] text-slate-500">{{ pc.changed_at }}</p>
                                </div>
                                <div class="mt-0.5 grid gap-1 sm:grid-cols-[1fr_auto_1fr] sm:items-center text-xs text-slate-600">
                                    <div class="truncate"><span class="text-slate-400">From:</span> {{ pc.old_value || '—' }}</div>
                                    <ArrowsRightLeftIcon class="hidden sm:block h-3.5 w-3.5 text-slate-400" />
                                    <div class="truncate"><span class="text-slate-400">To:</span> <span class="font-medium text-slate-800">{{ pc.new_value }}</span></div>
                                </div>
                                <p v-if="pc.notes" class="mt-0.5 text-[11px] text-slate-500">{{ pc.notes }}</p>
                                <p v-if="pc.performer" class="text-[11px] text-slate-400">by {{ pc.performer.name }}</p>
                            </div>
                            <div class="inline-flex items-center gap-1">
                                <button v-if="can('assets', 'edit')" class="btn-ghost" @click="openPartEdit(pc)" title="Edit"><PencilSquareIcon class="h-3.5 w-3.5" /></button>
                                <button v-if="can('assets', 'delete')" class="btn-ghost-danger" @click="askDeletePart(pc)" title="Delete"><TrashIcon class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <!-- ============== Issue Modal ============== -->
        <Modal :show="showIssue" title="Issue Asset to Employee" max-width="xl" @close="showIssue = false">
            <form @submit.prevent="submitIssue">
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <FormField label="Assign to" :error="issueForm.errors.to_employee_id" required class="sm:col-span-2">
                        <Combobox v-model="issueForm.to_employee_id" :options="lookups.employees" placeholder="Search employee…" null-label="— Select employee —" />
                    </FormField>
                    <FormField label="Location" :error="issueForm.errors.to_location_id">
                        <Combobox v-model="issueForm.to_location_id" :options="lookups.locations" placeholder="Search location…" />
                    </FormField>
                    <FormField label="Date" :error="issueForm.errors.movement_date" required>
                        <input v-model="issueForm.movement_date" type="date" class="input" />
                    </FormField>
                    <FormField label="Reference / Form #" :error="issueForm.errors.reference" class="sm:col-span-2">
                        <input v-model="issueForm.reference" type="text" class="input" placeholder="Asset Issuance Form, etc." />
                    </FormField>
                    <FormField label="Remarks" :error="issueForm.errors.remarks" class="sm:col-span-2">
                        <textarea v-model="issueForm.remarks" rows="2" class="input"></textarea>
                    </FormField>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <button type="button" class="btn-secondary" @click="showIssue = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="issueForm.processing">
                        {{ issueForm.processing ? 'Issuing...' : 'Issue Asset' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ============== Return Modal ============== -->
        <Modal :show="showReturn" title="Return Asset" max-width="xl" @close="showReturn = false">
            <form @submit.prevent="submitReturn">
                <div class="p-5">
                    <p class="mb-4 text-sm text-slate-600">
                        Returning from
                        <span class="font-semibold text-slate-900">{{ asset.current_holder?.full_name }}</span>.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Return to Location" :error="returnForm.errors.to_location_id">
                            <Combobox v-model="returnForm.to_location_id" :options="lookups.locations" placeholder="Search location…" null-label="— Keep current —" />
                        </FormField>
                        <FormField label="Date" :error="returnForm.errors.movement_date" required>
                            <input v-model="returnForm.movement_date" type="date" class="input" />
                        </FormField>
                        <FormField label="Resulting Status" :error="returnForm.errors.new_status" required>
                            <Combobox v-model="returnForm.new_status" :options="RETURN_STATUSES" value-key="value" label-key="label" :nullable="false" placeholder="Status…" />
                        </FormField>
                        <FormField label="Condition" :error="returnForm.errors.new_condition_id">
                            <Combobox v-model="returnForm.new_condition_id" :options="lookups.conditions" placeholder="Search condition…" null-label="— Keep current —" />
                        </FormField>
                        <FormField label="Reference / Form #" :error="returnForm.errors.reference" class="sm:col-span-2">
                            <input v-model="returnForm.reference" type="text" class="input" placeholder="Clearance, return slip, etc." />
                        </FormField>
                        <FormField label="Remarks" :error="returnForm.errors.remarks" class="sm:col-span-2">
                            <textarea v-model="returnForm.remarks" rows="2" class="input"
                                placeholder="e.g. Employee resigned, asset returned in good condition"></textarea>
                        </FormField>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <button type="button" class="btn-secondary" @click="showReturn = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="returnForm.processing">
                        {{ returnForm.processing ? 'Returning...' : 'Return Asset' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ============== Transfer Modal ============== -->
        <Modal :show="showTransfer" title="Transfer Asset" max-width="xl" @close="showTransfer = false">
            <form @submit.prevent="submitTransfer">
                <div class="p-5">
                    <p class="mb-4 text-sm text-slate-600">
                        Currently with
                        <span class="font-semibold text-slate-900">
                            {{ asset.current_holder?.full_name || asset.current_location?.name || 'no holder' }}
                        </span>. Pick a new employee, a new location, or both.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="New Employee" :error="transferForm.errors.to_employee_id">
                            <Combobox v-model="transferForm.to_employee_id" :options="lookups.employees" placeholder="Search employee…" null-label="— No change —" />
                        </FormField>
                        <FormField label="New Location" :error="transferForm.errors.to_location_id">
                            <Combobox v-model="transferForm.to_location_id" :options="lookups.locations" placeholder="Search location…" null-label="— No change —" />
                        </FormField>
                        <FormField label="Date" :error="transferForm.errors.movement_date" required>
                            <input v-model="transferForm.movement_date" type="date" class="input" />
                        </FormField>
                        <FormField label="Reference / Form #" :error="transferForm.errors.reference">
                            <input v-model="transferForm.reference" type="text" class="input" />
                        </FormField>
                        <FormField label="Remarks" :error="transferForm.errors.remarks" class="sm:col-span-2">
                            <textarea v-model="transferForm.remarks" rows="2" class="input"></textarea>
                        </FormField>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <button type="button" class="btn-secondary" @click="showTransfer = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="transferForm.processing">
                        {{ transferForm.processing ? 'Transferring...' : 'Transfer Asset' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ============== Movement Detail / Edit Modal ============== -->
        <Modal :show="showMovement" :title="editingMovement ? `${editingMovement.type.charAt(0).toUpperCase() + editingMovement.type.slice(1)} Details` : ''" max-width="2xl" @close="showMovement = false">
            <div v-if="editingMovement" class="p-5 space-y-5">
                <!-- Meta strip -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Type</p>
                        <p class="mt-0.5">
                            <Badge :tone="movementTone[editingMovement.type]" dot>{{ editingMovement.type }}</Badge>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Logged</p>
                        <p class="mt-0.5 text-xs text-slate-700">{{ editingMovement.created_at || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Updated</p>
                        <p class="mt-0.5 text-xs text-slate-700">{{ editingMovement.updated_at || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">By</p>
                        <p class="mt-0.5 text-xs text-slate-700">{{ editingMovement.performer?.name || '—' }}</p>
                    </div>
                </div>

                <!-- Editable form -->
                <form @submit.prevent="submitMovement" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Date" :error="movementForm.errors.movement_date" required>
                            <input v-model="movementForm.movement_date" type="date" class="input" />
                        </FormField>
                        <FormField label="Reference / Form #" :error="movementForm.errors.reference">
                            <input v-model="movementForm.reference" type="text" class="input" />
                        </FormField>

                        <FormField label="From Employee" :error="movementForm.errors.from_employee_id">
                            <Combobox v-model="movementForm.from_employee_id" :options="lookups.employees" placeholder="Search employee…" />
                        </FormField>
                        <FormField label="To Employee" :error="movementForm.errors.to_employee_id">
                            <Combobox v-model="movementForm.to_employee_id" :options="lookups.employees" placeholder="Search employee…" />
                        </FormField>

                        <FormField label="From Location" :error="movementForm.errors.from_location_id">
                            <Combobox v-model="movementForm.from_location_id" :options="lookups.locations" placeholder="Search location…" />
                        </FormField>
                        <FormField label="To Location" :error="movementForm.errors.to_location_id">
                            <Combobox v-model="movementForm.to_location_id" :options="lookups.locations" placeholder="Search location…" />
                        </FormField>

                        <FormField label="Remarks" :error="movementForm.errors.remarks" class="sm:col-span-2">
                            <textarea v-model="movementForm.remarks" rows="3" class="input"></textarea>
                        </FormField>
                    </div>
                    <p class="text-xs text-slate-500">
                        Editing only affects this movement record. If you change the most recent movement, the asset's current holder/location is updated to match.
                    </p>
                </form>
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                <button v-if="can('assets', 'delete')" type="button" class="btn-ghost-danger inline-flex items-center gap-1 px-2" @click="showMovementDelete = true">
                    <TrashIcon class="h-4 w-4" /> Delete
                </button>
                <span v-else></span>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary" @click="showMovement = false">Close</button>
                    <button v-if="can('assets', 'edit')" type="button" class="btn-primary" :disabled="movementForm.processing" @click="submitMovement">
                        {{ movementForm.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </Modal>

        <ConfirmDialog
            :show="showMovementDelete"
            title="Delete this movement?"
            message="This will permanently remove the movement entry from the audit log. The asset's current status will not be auto-rolled back. Continue?"
            confirm-text="Delete Movement"
            @close="showMovementDelete = false"
            @confirm="deleteMovement"
        />

        <ConfirmDialog
            :show="showDelete"
            :title="`Delete ${asset.asset_tag}?`"
            message="This asset and its movement history will be removed (soft delete — recoverable from the database). Are you sure?"
            @close="showDelete = false"
            @confirm="doDelete"
        />

        <!-- ============== Part Change Modal ============== -->
        <Modal :show="showPartChange" :title="editingPartChange ? 'Edit Part Change' : 'Add Part Change'" max-width="xl" @close="showPartChange = false">
            <form @submit.prevent="submitPartChange">
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <FormField label="Part" :error="partForm.errors.part_name" required class="sm:col-span-2">
                        <input v-model="partForm.part_name" type="text" class="input" placeholder="e.g. RAM, SSD, GPU" list="part-presets" />
                        <datalist id="part-presets">
                            <option v-for="p in PART_PRESETS" :key="p" :value="p" />
                        </datalist>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <button
                                v-for="p in PART_PRESETS"
                                :key="p"
                                type="button"
                                class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600 hover:bg-brand-50 hover:text-brand-700 hover:border-brand-200"
                                @click="partForm.part_name = p"
                            >{{ p }}</button>
                        </div>
                    </FormField>
                    <FormField label="Old value" :error="partForm.errors.old_value">
                        <input v-model="partForm.old_value" type="text" class="input" placeholder="e.g. 8GB DDR4" />
                    </FormField>
                    <FormField label="New value" :error="partForm.errors.new_value" required>
                        <input v-model="partForm.new_value" type="text" class="input" placeholder="e.g. 16GB DDR4" />
                    </FormField>
                    <FormField label="Reason" :error="partForm.errors.reason">
                        <input v-model="partForm.reason" type="text" class="input" list="reason-presets" placeholder="e.g. Upgrade" />
                        <datalist id="reason-presets">
                            <option v-for="r in REASON_PRESETS" :key="r" :value="r" />
                        </datalist>
                    </FormField>
                    <FormField label="Date" :error="partForm.errors.changed_at" required>
                        <input v-model="partForm.changed_at" type="date" class="input" />
                    </FormField>
                    <FormField label="Performed by" :error="partForm.errors.performed_by_user_id" class="sm:col-span-2">
                        <Combobox v-model="partForm.performed_by_user_id" :options="lookups.users" placeholder="Search user…" />
                        <p class="help">Defaults to the current signed-in user.</p>
                    </FormField>
                    <FormField label="Linked Incident Report" :error="partForm.errors.incident_report_id">
                        <Combobox v-model="partForm.incident_report_id" :options="lookups.incident_reports" placeholder="Search IR for this asset…" />
                        <p class="help">For defect-driven swaps. <Link href="/incidents/create" class="text-brand-600 hover:underline">Create IR</Link></p>
                    </FormField>
                    <FormField label="Linked Recommendation" :error="partForm.errors.recommendation_id">
                        <Combobox v-model="partForm.recommendation_id" :options="lookups.recommendations" placeholder="Search recommendation…" />
                        <p class="help">For upgrade-driven swaps. <Link href="/recommendations/create" class="text-brand-600 hover:underline">Create Recommendation</Link></p>
                    </FormField>
                    <FormField label="Notes" :error="partForm.errors.notes" class="sm:col-span-2">
                        <textarea v-model="partForm.notes" rows="3" class="input" placeholder="Optional — serial of replaced part, model, warranty, etc."></textarea>
                    </FormField>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <button type="button" class="btn-secondary" @click="showPartChange = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="partForm.processing">
                        {{ partForm.processing ? 'Saving...' : (editingPartChange ? 'Save Changes' : 'Add Entry') }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="showPartDelete"
            :title="`Delete this part change?`"
            message="This entry will be removed from the part-change history. This cannot be undone."
            confirm-text="Delete"
            @close="showPartDelete = false"
            @confirm="deletePart"
        />

        <!-- ============== Asset Tag Preview / Download Modal ============== -->
        <Modal :show="showPrintTag" title="Asset Tag" max-width="3xl" @close="showPrintTag = false">
            <div class="flex justify-center bg-slate-100 p-6">
                <AssetTag ref="assetTagRef" :asset="asset" />
            </div>
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                <p class="text-xs text-slate-500">PNG at ~300 dpi, sized for a 3.5″ × 2″ label.</p>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary" @click="showPrintTag = false">Close</button>
                    <button type="button" class="btn-primary" :disabled="downloading" @click="doDownload">
                        <ArrowDownOnSquareIcon class="h-4 w-4" />
                        {{ downloading ? 'Generating...' : 'Download PNG' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
