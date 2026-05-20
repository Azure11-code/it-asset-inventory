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

const RETURN_STATUSES = [
    { value: 'in_stock',   label: 'In Stock (ready to re-issue)' },
    { value: 'for_repair', label: 'For Repair' },
    { value: 'defective',  label: 'Defective' },
];
import html2canvas from 'html2canvas';
import {
    PencilSquareIcon, TrashIcon, ClockIcon, IdentificationIcon,
    ShieldCheckIcon, CalendarDaysIcon, BanknotesIcon, MapPinIcon,
    UserIcon, ChevronLeftIcon, ArrowsRightLeftIcon, ArrowDownTrayIcon,
    ArrowUpOnSquareIcon, ArrowDownOnSquareIcon,
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
const doDownload = async () => {
    const el = document.querySelector('.asset-tag');
    if (!el) return;
    downloading.value = true;
    try {
        // Wait for web fonts so text renders crisply (not as fallback font)
        if (document.fonts?.ready) await document.fonts.ready;

        // Wait for any <img> inside the tag (the logo) to fully load
        const imgs = Array.from(el.querySelectorAll('img'));
        await Promise.all(imgs.map((img) =>
            img.complete && img.naturalWidth
                ? Promise.resolve()
                : new Promise((res) => {
                    img.addEventListener('load', res, { once: true });
                    img.addEventListener('error', res, { once: true });
                })
        ));

        const canvas = await html2canvas(el, {
            scale: 3,
            backgroundColor: '#ffffff',
            useCORS: true,
            allowTaint: true,
            logging: false,
            imageTimeout: 5000,
            letterRendering: true,
            windowWidth: el.scrollWidth,
            windowHeight: el.scrollHeight,
        });

        const link = document.createElement('a');
        link.download = `asset-tag-${props.asset.asset_tag}.png`;
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
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
const submitIssue = () => issueForm.post(`/assets/${props.asset.id}/issue`, {
    onSuccess: () => { showIssue.value = false; issueForm.reset(); },
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
    onSuccess: () => { showReturn.value = false; returnForm.reset(); },
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
    onSuccess: () => { showTransfer.value = false; transferForm.reset(); },
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
</script>

<template>
    <AppLayout>
        <template #header>
            <div class="flex items-end justify-between gap-3">
                <div>
                    <Link href="/assets" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
                        <ChevronLeftIcon class="h-4 w-4" /> Back to assets
                    </Link>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ asset.asset_tag }}</h1>
                    <p class="text-sm text-slate-500">
                        {{ asset.category?.name || 'Uncategorized' }}
                        <template v-if="asset.brand"> · {{ asset.brand.name }}</template>
                        <template v-if="asset.model"> · {{ asset.model }}</template>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button v-if="canIssue"    class="btn-primary"   @click="openIssue">
                        <ArrowUpOnSquareIcon class="h-4 w-4" /> Issue to Employee
                    </button>
                    <button v-if="canReturn"   class="btn-primary"   @click="openReturn">
                        <ArrowDownTrayIcon class="h-4 w-4" /> Return
                    </button>
                    <button v-if="canTransfer" class="btn-secondary" @click="openTransfer">
                        <ArrowsRightLeftIcon class="h-4 w-4" /> Transfer
                    </button>
                    <button class="btn-secondary" @click="showPrintTag = true">
                        <ArrowDownOnSquareIcon class="h-4 w-4" /> Download Tag
                    </button>
                    <Link :href="`/assets/${asset.id}/edit`" class="btn-secondary">
                        <PencilSquareIcon class="h-4 w-4" /> Edit
                    </Link>
                    <button class="btn-ghost-danger" @click="showDelete = true" title="Delete">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </template>

        <!-- Replacement-eligible banner -->
        <div v-if="asset.is_eligible_for_replacement"
             class="mb-6 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50/60 p-4">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100">
                <ClockIcon class="h-5 w-5 text-rose-600" />
            </div>
            <div>
                <p class="text-sm font-semibold text-rose-900">Eligible for replacement</p>
                <p class="text-xs text-rose-700">
                    This asset is {{ asset.age_years }} years old — past its {{ asset.expected_lifespan_years }}-year service life.
                    If reported broken, it qualifies for automatic replacement (no investigation required).
                </p>
            </div>
        </div>

        <!-- Quick facts grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <IdentificationIcon class="h-4 w-4" /> Status
                </div>
                <div class="mt-2">
                    <Badge :tone="statusTone[asset.current_status]" dot>{{ asset.current_status.replace('_', ' ') }}</Badge>
                </div>
                <p v-if="asset.current_holder" class="mt-2 text-sm text-slate-600">
                    <UserIcon class="inline h-4 w-4 -mt-0.5" /> {{ asset.current_holder.full_name }}
                </p>
                <p v-if="asset.current_holder" class="text-xs text-slate-500">
                    <template v-if="asset.assigned_since">
                        {{ asset.is_first_issuance ? 'Deployed' : 'Transferred' }}: {{ asset.assigned_since }}
                    </template>
                    <template v-else>new — no transfer date</template>
                </p>
                <p v-if="asset.current_location" class="text-sm text-slate-500">
                    <MapPinIcon class="inline h-4 w-4 -mt-0.5" /> {{ asset.current_location.name }}
                </p>
            </div>

            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <CalendarDaysIcon class="h-4 w-4" /> Age
                </div>
                <p class="mt-2 text-2xl font-semibold tracking-tight" :class="ageColor">
                    {{ asset.age_formatted || '—' }}
                </p>
                <p class="text-xs text-slate-500">
                    Purchased {{ asset.purchase_date || 'unknown' }} · {{ asset.expected_lifespan_years }}yr lifespan
                </p>
                <p v-if="asset.service_duration_formatted" class="mt-1 text-xs text-slate-500">
                    In service: <span class="font-medium text-slate-700">{{ asset.service_duration_formatted }}</span>
                    <template v-if="asset.deployment_date"> (since {{ asset.deployment_date }})</template>
                </p>
            </div>

            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <ShieldCheckIcon class="h-4 w-4" /> Warranty
                </div>
                <div class="mt-2">
                    <Badge :tone="warrantyTone[asset.warranty_status]" dot>
                        {{ asset.warranty_status === 'expiring_soon' ? 'expiring soon' : asset.warranty_status }}
                    </Badge>
                </div>
                <p class="mt-1 text-xs text-slate-500">Until {{ asset.warranty_until || '—' }}</p>
            </div>

            <div class="card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <BanknotesIcon class="h-4 w-4" /> Acquisition
                </div>
                <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ money(asset.purchase_cost) }}</p>
                <p class="text-xs text-slate-500">
                    Condition:
                    <Badge v-if="asset.condition" :tone="asset.condition.tone" class="ml-1">{{ asset.condition.name }}</Badge>
                    <span v-else class="ml-1 text-slate-400">—</span>
                </p>
            </div>
        </div>

        <!-- Details + Movement history -->
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="card">
                <header class="card-header">
                    <div><h2 class="card-title">Asset Details</h2></div>
                </header>
                <dl class="divide-y divide-slate-100">
                    <div class="grid grid-cols-3 px-5 py-3">
                        <dt class="text-sm text-slate-500">Serial #</dt>
                        <dd class="col-span-2 text-sm font-medium text-slate-900">{{ asset.serial_number || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 px-5 py-3">
                        <dt class="text-sm text-slate-500">Model</dt>
                        <dd class="col-span-2 text-sm font-medium text-slate-900">{{ asset.model || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 px-5 py-3">
                        <dt class="text-sm text-slate-500">Description</dt>
                        <dd class="col-span-2 text-sm text-slate-700">{{ asset.description || '—' }}</dd>
                    </div>
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between">
                            <dt class="text-sm text-slate-500">Specifications</dt>
                            <Badge :tone="asset.specifications?.length ? 'emerald' : 'slate'" dot>
                                {{ asset.specifications?.length
                                    ? `${asset.specifications.length} item${asset.specifications.length === 1 ? '' : 's'}`
                                    : 'Empty' }}
                            </Badge>
                        </div>
                        <dd v-if="asset.specifications?.length" class="mt-2 divide-y divide-slate-100 rounded-md border border-slate-100 bg-slate-50/60">
                            <div
                                v-for="(item, i) in asset.specifications"
                                :key="i"
                                class="grid grid-cols-3 gap-3 px-3 py-2 text-sm"
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
                    <div class="grid grid-cols-3 px-5 py-3">
                        <dt class="text-sm text-slate-500">Purchase Date</dt>
                        <dd class="col-span-2 text-sm font-medium text-slate-900">{{ asset.purchase_date || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 px-5 py-3">
                        <dt class="text-sm text-slate-500">Deployment Date</dt>
                        <dd class="col-span-2 text-sm font-medium text-slate-900">{{ asset.deployment_date || '—' }}</dd>
                    </div>
                    <div v-if="asset.notes" class="px-5 py-3">
                        <dt class="text-sm text-slate-500">Notes</dt>
                        <dd class="mt-1 text-sm text-slate-700 whitespace-pre-line">{{ asset.notes }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card lg:col-span-2">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Movement History</h2>
                        <p class="card-subtitle">Every issuance, return, or transfer of this device.</p>
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
                            class="flex w-full gap-4 px-5 py-4 text-left transition-colors hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                            @click="openMovement(m)"
                        >
                            <div :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-full', `badge-${movementTone[m.type]}`]">
                                <component :is="movementIcon[m.type]" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900 capitalize">{{ m.type }}</p>
                                    <p class="text-xs text-slate-500">{{ m.movement_date }}</p>
                                </div>
                                <p class="text-sm text-slate-600">
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
                                <p v-if="m.remarks" class="mt-1 text-xs text-slate-500">{{ m.remarks }}</p>
                                <p v-if="m.reference" class="mt-0.5 text-xs text-slate-400">Ref: {{ m.reference }}</p>
                                <p v-if="m.performer" class="mt-0.5 text-xs text-slate-400">by {{ m.performer.name }}</p>
                            </div>
                        </button>
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
                <button type="button" class="btn-ghost-danger inline-flex items-center gap-1 px-2" @click="showMovementDelete = true">
                    <TrashIcon class="h-4 w-4" /> Delete
                </button>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary" @click="showMovement = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="movementForm.processing" @click="submitMovement">
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

        <!-- ============== Asset Tag Preview / Download Modal ============== -->
        <Modal :show="showPrintTag" title="Asset Tag" max-width="xl" @close="showPrintTag = false">
            <div class="flex justify-center bg-slate-100 p-6">
                <AssetTag :asset="asset" />
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
