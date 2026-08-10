<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import Combobox from '@/Components/Combobox.vue';
import FormField from '@/Components/FormField.vue';
import {
    QrCodeIcon, EyeIcon, ArrowsRightLeftIcon, ArrowUpOnSquareIcon, ArrowDownTrayIcon,
    UserIcon, MapPinIcon, BuildingOffice2Icon, IdentificationIcon, ExclamationTriangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    asset:    { type: Object, default: null },
    searched: { type: String, default: '' },
    lookups:  { type: Object, default: null },
});

const statusTone = { in_stock: 'sky', assigned: 'brand', for_repair: 'amber', defective: 'rose', retired: 'slate', replaced: 'slate' };
const today = () => new Date().toISOString().slice(0, 10);

const canIssue    = computed(() => props.asset && ['in_stock', 'for_repair'].includes(props.asset.current_status));
const canReturn   = computed(() => props.asset && props.asset.current_status === 'assigned');
const canTransfer = computed(() => props.asset && ['in_stock', 'assigned'].includes(props.asset.current_status));

// Active pane: 'menu' | 'transfer' | 'issue' | 'return'
const pane = ref('menu');

// ── Forms ──
const transferForm = useForm({ to_employee_id: '', to_location_id: '', movement_date: today(), reference: '', remarks: '' });
const issueForm    = useForm({ to_employee_id: '', to_location_id: props.asset?.current_location?.id ?? '', movement_date: today(), reference: '', remarks: '' });
const returnForm   = useForm({ to_location_id: props.asset?.current_location?.id ?? '', movement_date: today(), new_status: 'in_stock', new_condition_id: '', reference: '', remarks: '' });

const RETURN_STATUSES = [
    { value: 'in_stock',   label: 'In Stock (ready to re-issue)' },
    { value: 'for_repair', label: 'For Repair' },
    { value: 'defective',  label: 'Defective' },
];

// Re-fetch the scan/lookup page after any action so the details card shows fresh data.
// Using router.visit with the same URL guarantees Inertia re-runs ScanController::lookup
// and returns the updated asset (holder, status, location, etc).
const refreshResultPage = () => router.visit(`/scan/lookup?tag=${encodeURIComponent(props.asset.asset_tag)}`, {
    preserveScroll: true,
    replace: true,
});

const submitTransfer = () => transferForm.post(`/assets/${props.asset.id}/transfer`, {
    onSuccess: () => { pane.value = 'menu'; transferForm.reset(); refreshResultPage(); },
    preserveScroll: true,
});
const submitIssue = () => issueForm.post(`/assets/${props.asset.id}/issue`, {
    onSuccess: () => { pane.value = 'menu'; issueForm.reset(); refreshResultPage(); },
    preserveScroll: true,
});
const submitReturn = () => returnForm.post(`/assets/${props.asset.id}/return`, {
    onSuccess: () => { pane.value = 'menu'; returnForm.reset(); refreshResultPage(); },
    preserveScroll: true,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :title="asset ? asset.asset_tag : 'Asset not found'"
                        :subtitle="asset ? `${asset.category?.name || ''}${asset.brand ? ' · ' + asset.brand.name : ''}${asset.model ? ' · ' + asset.model : ''}` : ''">
                <template #actions>
                    <Link href="/scan" class="btn-secondary">
                        <QrCodeIcon class="h-4 w-4" /> Scan another
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="mx-auto max-w-md space-y-4">
            <!-- Not found -->
            <section v-if="!asset" class="card p-6 text-center">
                <ExclamationTriangleIcon class="mx-auto h-12 w-12 text-amber-500" />
                <h2 class="mt-3 text-base font-semibold text-slate-900">No asset found</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Looked up <span class="font-mono text-xs bg-slate-100 rounded px-1.5 py-0.5">{{ searched || '(empty)' }}</span> but nothing matched.
                </p>
                <Link href="/scan" class="btn-primary mt-4 inline-flex">
                    <QrCodeIcon class="h-4 w-4" /> Scan again
                </Link>
            </section>

            <template v-else>
                <!-- Details card -->
                <section class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Asset Tag</p>
                            <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ asset.asset_tag }}</h1>
                            <p v-if="asset.serial_number" class="text-xs text-slate-500">S/N: {{ asset.serial_number }}</p>
                        </div>
                        <Badge :tone="statusTone[asset.current_status] || 'slate'" dot>
                            {{ asset.current_status.replace('_', ' ') }}
                        </Badge>
                    </div>
                    <dl class="mt-3 space-y-2 border-t border-slate-100 pt-3 text-sm">
                        <div class="flex items-center gap-2">
                            <UserIcon class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="text-slate-500">Holder:</span>
                            <span class="font-medium text-slate-900">{{ asset.current_holder?.full_name || '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <BuildingOffice2Icon class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="text-slate-500">Dept:</span>
                            <span class="text-slate-900">{{ asset.department?.name || asset.current_holder?.department || '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <MapPinIcon class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="text-slate-500">Location:</span>
                            <span class="text-slate-900">{{ asset.current_location?.name || '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <IdentificationIcon class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="text-slate-500">Category:</span>
                            <span class="text-slate-900">{{ asset.category?.name || '—' }}{{ asset.brand ? ' · ' + asset.brand.name : '' }}{{ asset.model ? ' · ' + asset.model : '' }}</span>
                        </div>
                    </dl>
                </section>

                <!-- Action menu -->
                <section v-if="pane === 'menu'" class="card p-4">
                    <p class="mb-3 text-sm font-semibold text-slate-700">What do you want to do?</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button v-if="canTransfer" class="btn-primary flex-col py-4" @click="pane = 'transfer'">
                            <ArrowsRightLeftIcon class="h-6 w-6" />
                            <span class="mt-1 text-sm">Transfer</span>
                        </button>
                        <button v-if="canIssue" class="btn-primary flex-col py-4" @click="pane = 'issue'">
                            <ArrowUpOnSquareIcon class="h-6 w-6" />
                            <span class="mt-1 text-sm">Issue</span>
                        </button>
                        <button v-if="canReturn" class="btn-primary flex-col py-4" @click="pane = 'return'">
                            <ArrowDownTrayIcon class="h-6 w-6" />
                            <span class="mt-1 text-sm">Return</span>
                        </button>
                        <Link :href="`/assets/${asset.id}`" class="btn-secondary flex-col py-4">
                            <EyeIcon class="h-6 w-6" />
                            <span class="mt-1 text-sm">View full</span>
                        </Link>
                    </div>
                </section>

                <!-- Transfer form -->
                <section v-if="pane === 'transfer'" class="card">
                    <header class="card-header px-4 py-3">
                        <h2 class="card-title text-sm">Transfer Asset</h2>
                        <button class="btn-ghost" @click="pane = 'menu'"><XMarkIcon class="h-4 w-4" /></button>
                    </header>
                    <form @submit.prevent="submitTransfer" class="space-y-3 p-4">
                        <p class="text-xs text-slate-600">
                            Currently with <span class="font-semibold text-slate-900">{{ asset.current_holder?.full_name || asset.current_location?.name || 'no holder' }}</span>. Pick a new employee and/or location.
                        </p>
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
                        <FormField label="Remarks" :error="transferForm.errors.remarks">
                            <textarea v-model="transferForm.remarks" rows="2" class="input"></textarea>
                        </FormField>
                        <button type="submit" class="btn-primary w-full py-3" :disabled="transferForm.processing">
                            {{ transferForm.processing ? 'Transferring…' : 'Confirm Transfer' }}
                        </button>
                    </form>
                </section>

                <!-- Issue form -->
                <section v-if="pane === 'issue'" class="card">
                    <header class="card-header px-4 py-3">
                        <h2 class="card-title text-sm">Issue to Employee</h2>
                        <button class="btn-ghost" @click="pane = 'menu'"><XMarkIcon class="h-4 w-4" /></button>
                    </header>
                    <form @submit.prevent="submitIssue" class="space-y-3 p-4">
                        <FormField label="Assign to" :error="issueForm.errors.to_employee_id" required>
                            <Combobox v-model="issueForm.to_employee_id" :options="lookups.employees" placeholder="Search employee…" null-label="— Select employee —" />
                        </FormField>
                        <FormField label="Location" :error="issueForm.errors.to_location_id">
                            <Combobox v-model="issueForm.to_location_id" :options="lookups.locations" placeholder="Search location…" />
                        </FormField>
                        <FormField label="Date" :error="issueForm.errors.movement_date" required>
                            <input v-model="issueForm.movement_date" type="date" class="input" />
                        </FormField>
                        <FormField label="Reference / Form #" :error="issueForm.errors.reference">
                            <input v-model="issueForm.reference" type="text" class="input" placeholder="Issuance Form No." />
                        </FormField>
                        <FormField label="Remarks" :error="issueForm.errors.remarks">
                            <textarea v-model="issueForm.remarks" rows="2" class="input"></textarea>
                        </FormField>
                        <button type="submit" class="btn-primary w-full py-3" :disabled="issueForm.processing">
                            {{ issueForm.processing ? 'Issuing…' : 'Confirm Issue' }}
                        </button>
                    </form>
                </section>

                <!-- Return form -->
                <section v-if="pane === 'return'" class="card">
                    <header class="card-header px-4 py-3">
                        <h2 class="card-title text-sm">Return Asset</h2>
                        <button class="btn-ghost" @click="pane = 'menu'"><XMarkIcon class="h-4 w-4" /></button>
                    </header>
                    <form @submit.prevent="submitReturn" class="space-y-3 p-4">
                        <p class="text-xs text-slate-600">
                            Returning from <span class="font-semibold text-slate-900">{{ asset.current_holder?.full_name }}</span>.
                        </p>
                        <FormField label="Return to Location" :error="returnForm.errors.to_location_id">
                            <Combobox v-model="returnForm.to_location_id" :options="lookups.locations" placeholder="Search location…" null-label="— Keep current —" />
                        </FormField>
                        <FormField label="Resulting Status" :error="returnForm.errors.new_status" required>
                            <Combobox v-model="returnForm.new_status" :options="RETURN_STATUSES" value-key="value" label-key="label" :nullable="false" placeholder="Status…" />
                        </FormField>
                        <FormField label="Condition" :error="returnForm.errors.new_condition_id">
                            <Combobox v-model="returnForm.new_condition_id" :options="lookups.conditions" placeholder="Search condition…" null-label="— Keep current —" />
                        </FormField>
                        <FormField label="Date" :error="returnForm.errors.movement_date" required>
                            <input v-model="returnForm.movement_date" type="date" class="input" />
                        </FormField>
                        <FormField label="Remarks" :error="returnForm.errors.remarks">
                            <textarea v-model="returnForm.remarks" rows="2" class="input"
                                placeholder="e.g. Employee resigned"></textarea>
                        </FormField>
                        <button type="submit" class="btn-primary w-full py-3" :disabled="returnForm.processing">
                            {{ returnForm.processing ? 'Returning…' : 'Confirm Return' }}
                        </button>
                    </form>
                </section>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-primary.flex-col,
.btn-secondary.flex-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
}
</style>
