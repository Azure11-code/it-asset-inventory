<script setup>
import { computed, ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Combobox from '@/Components/Combobox.vue';
import { ClipboardDocumentCheckIcon, PlusIcon, TrashIcon, ChevronLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    permit: Object,    // null for new
    lookups: Object,
    defaults: Object,  // null when editing
});

const isEdit = computed(() => !!props.permit);

const STATUSES = [
    { value: 'draft',     label: 'Draft' },
    { value: 'approved',  label: 'Approved' },
    { value: 'returned',  label: 'Returned' },
    { value: 'cancelled', label: 'Cancelled' },
];

const blankItem = () => ({
    asset_id: null,
    qty: 1,
    unit: 'PC',
    description: '',
    serial_no: '',
    remarks: '',
});

const form = useForm({
    permit_no:                  props.permit?.permit_no   ?? props.defaults?.permit_no ?? '',
    employee_id:                props.permit?.employee_id ?? null,
    employee_name:              props.permit?.employee_name ?? '',
    position_text:              props.permit?.position_text ?? '',
    department_text:            props.permit?.department_text ?? '',
    destination:                props.permit?.destination ?? '',
    purpose:                    props.permit?.purpose     ?? '',
    date_borrow:                props.permit?.date_borrow ?? props.defaults?.date_borrow ?? '',
    date_return:                props.permit?.date_return ?? props.defaults?.date_return ?? '',
    valid_from:                 props.permit?.valid_from  ?? props.defaults?.valid_from  ?? '',
    valid_to:                   props.permit?.valid_to    ?? props.defaults?.valid_to    ?? '',
    requested_by_employee_id:   props.permit?.requested_by_employee_id   ?? null,
    issued_by_user_id:          props.permit?.issued_by_user_id          ?? null,
    noted_by_user_id:           props.permit?.noted_by_user_id           ?? null,
    noted_by_secondary_user_id: props.permit?.noted_by_secondary_user_id ?? null,
    approved_by_user_id:        props.permit?.approved_by_user_id        ?? null,
    approval_note:              props.permit?.approval_note ?? '',
    status:                     props.permit?.status ?? 'draft',
    items: (props.permit?.items?.length ? props.permit.items : [blankItem()]).map((it) => ({ ...blankItem(), ...it })),
});

// Auto-fill requester when main employee selected, if requester is empty
const onEmployeeChange = (id) => {
    if (id && !form.requested_by_employee_id) form.requested_by_employee_id = id;
    if (id) {
        // Clear free-text fallback when a real employee is picked
        form.employee_name = '';
        form.position_text = '';
        form.department_text = '';
    }
};

// True when the user typed a custom name (no employee_id selected)
const isCustomEmployee = computed(() => !form.employee_id && !!form.employee_name);

// Pick an asset for a line item → autofill description, serial
const onPickAsset = (idx, assetId) => {
    form.items[idx].asset_id = assetId;
    if (!assetId) return;
    const a = props.lookups.assets.find((x) => x.id === Number(assetId));
    if (!a) return;
    if (!form.items[idx].description) form.items[idx].description = a.description ?? a.name;
    if (!form.items[idx].serial_no)   form.items[idx].serial_no   = a.serial_no ?? '';
};

const addItem = () => { form.items.push(blankItem()); };
const removeItem = (i) => {
    if (form.items.length === 1) {
        form.items.splice(0, 1, blankItem());
        return;
    }
    form.items.splice(i, 1);
};

const submit = () => {
    if (isEdit.value) {
        form.put(`/permits/${props.permit.id}`);
    } else {
        form.post('/permits');
    }
};
const cancel = () => router.visit(isEdit.value ? `/permits/${props.permit.id}` : '/permits');
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader
                :title="isEdit ? `Edit ${form.permit_no}` : 'New Permit to Bring Asset'"
                subtitle="Authorize an employee to bring assets off-premises."
            >
                <template #actions>
                    <Link href="/permits" class="btn-secondary">
                        <ChevronLeftIcon class="h-4 w-4" /> Back
                    </Link>
                </template>
            </PageHeader>
        </template>

        <form @submit.prevent="submit" class="space-y-6">

            <!-- ── Requester & Trip ── -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Requester & Trip</h2>
                        <p class="card-subtitle">Who's bringing the asset, where, when, and why.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="Permit #" :error="form.errors.permit_no" required>
                        <input v-model="form.permit_no" type="text" class="input" />
                    </FormField>
                    <FormField label="Status" :error="form.errors.status" required>
                        <Combobox v-model="form.status" :options="STATUSES" value-key="value" label-key="label" :nullable="false" />
                    </FormField>
                    <div class="hidden lg:block" />
                    <FormField label="Employee (name on permit)" :error="form.errors.employee_id || form.errors.employee_name" required class="sm:col-span-2">
                        <Combobox
                            :model-value="form.employee_id"
                            @update:model-value="(v) => { form.employee_id = v; onEmployeeChange(v); }"
                            :custom-text="form.employee_name"
                            @update:custom-text="(v) => { form.employee_name = v; }"
                            :options="lookups.employees"
                            allow-custom
                            placeholder="Search or type a name…"
                        />
                        <p class="help">Pick from the list, or type a name not in the system.</p>
                    </FormField>
                    <FormField label="Destination" :error="form.errors.destination" required>
                        <input v-model="form.destination" type="text" class="input" placeholder="e.g. SALUDES RESIDENCE" />
                    </FormField>
                    <template v-if="isCustomEmployee">
                        <FormField label="Position (manual)" :error="form.errors.position_text">
                            <input v-model="form.position_text" type="text" class="input" placeholder="e.g. SENIOR IMPORT ASSOCIATE" />
                        </FormField>
                        <FormField label="Department (manual)" :error="form.errors.department_text">
                            <input v-model="form.department_text" type="text" class="input" placeholder="e.g. IMPORTATION DEPT." />
                        </FormField>
                        <div class="hidden lg:block" />
                    </template>
                    <FormField label="Purpose" :error="form.errors.purpose" required class="sm:col-span-2 lg:col-span-3">
                        <input v-model="form.purpose" type="text" class="input" placeholder="e.g. WORK FROM HOME DURING HOLIDAY" />
                    </FormField>
                    <FormField label="Date Borrow" :error="form.errors.date_borrow" required>
                        <input v-model="form.date_borrow" type="date" class="input" />
                    </FormField>
                    <FormField label="Date Return" :error="form.errors.date_return" required>
                        <input v-model="form.date_return" type="date" class="input" />
                    </FormField>
                    <div class="hidden lg:block" />
                    <FormField label="Valid From" :error="form.errors.valid_from" required>
                        <input v-model="form.valid_from" type="date" class="input" />
                    </FormField>
                    <FormField label="Valid To" :error="form.errors.valid_to" required>
                        <input v-model="form.valid_to" type="date" class="input" />
                    </FormField>
                </div>
            </section>

            <!-- ── Items ── -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Items</h2>
                        <p class="card-subtitle">Devices and accessories covered by this permit.</p>
                    </div>
                    <button type="button" class="btn-secondary" @click="addItem">
                        <PlusIcon class="h-4 w-4" /> Add row
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div v-for="(item, i) in form.items" :key="i" class="rounded-lg border border-slate-200 p-3">
                        <div class="grid gap-3 sm:grid-cols-12">
                            <FormField label="Asset (optional)" class="sm:col-span-4">
                                <Combobox
                                    :model-value="item.asset_id"
                                    @update:model-value="(v) => onPickAsset(i, v)"
                                    :options="lookups.assets"
                                    placeholder="Pick to autofill, or leave blank"
                                />
                            </FormField>
                            <FormField label="Qty" class="sm:col-span-1">
                                <input v-model.number="item.qty" type="number" min="1" class="input" />
                            </FormField>
                            <FormField label="Unit" class="sm:col-span-1">
                                <input v-model="item.unit" type="text" class="input" placeholder="PC" />
                            </FormField>
                            <FormField label="Description" :error="form.errors[`items.${i}.description`]" required class="sm:col-span-3">
                                <input v-model="item.description" type="text" class="input" placeholder="e.g. IDEAPAD 3 - 15IAU7" />
                            </FormField>
                            <FormField label="Serial #" class="sm:col-span-3">
                                <input v-model="item.serial_no" type="text" class="input" placeholder="N/A" />
                            </FormField>
                            <FormField label="Remarks" class="sm:col-span-11">
                                <input v-model="item.remarks" type="text" class="input" placeholder="e.g. 12TH GEN INTEL CORE i3 8GB" />
                            </FormField>
                            <div class="sm:col-span-1 flex items-end justify-end">
                                <button
                                    type="button"
                                    class="btn-ghost-danger"
                                    @click="removeItem(i)"
                                    :title="form.items.length === 1 ? 'Clear row' : 'Remove row'"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.items" class="error-text">{{ form.errors.items }}</p>
                </div>
            </section>

            <!-- ── Signatures ── -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Signatures</h2>
                        <p class="card-subtitle">Who is requesting, issuing, noting, and approving this permit.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <FormField label="Requested By (employee)" :error="form.errors.requested_by_employee_id">
                        <Combobox v-model="form.requested_by_employee_id" :options="lookups.employees" placeholder="Search employee…" />
                    </FormField>
                    <FormField label="Issued By (user)" :error="form.errors.issued_by_user_id">
                        <Combobox v-model="form.issued_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Noted By — IT Head (user)" :error="form.errors.noted_by_user_id">
                        <Combobox v-model="form.noted_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Noted By — Supervisor (user)" :error="form.errors.noted_by_secondary_user_id">
                        <Combobox v-model="form.noted_by_secondary_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Approved By (user)" :error="form.errors.approved_by_user_id">
                        <Combobox v-model="form.approved_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Approval Note" :error="form.errors.approval_note">
                        <input v-model="form.approval_note" type="text" class="input" placeholder="e.g. THRU VIBER APPROVAL PLEASE SEE ATTACHED FILE" />
                    </FormField>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="cancel">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <ClipboardDocumentCheckIcon class="h-4 w-4" />
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Update Permit' : 'Create Permit') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
