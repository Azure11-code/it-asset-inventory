<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import Combobox from '@/Components/Combobox.vue';

/**
 * The create/edit form for an employee.
 *
 * Lives in one place so both the Employees list and an employee's own page
 * can open it where the user already is, rather than navigating somewhere
 * else and hoping a form appears.
 */
const props = defineProps({
    show:        { type: Boolean, default: false },
    // null = create a new employee; an object = edit that one
    employee:    { type: Object, default: null },
    departments: { type: Array, default: () => [] },
    locations:   { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const EMPLOYEE_STATUSES = [
    { value: 'active',   label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'resigned', label: 'Resigned' },
];

const form = useForm({
    employee_no: '', first_name: '', middle_name: '', last_name: '',
    email: '', contact_no: '', position: '',
    department_id: '', location_id: '',
    date_hired: '', date_resigned: '', status: 'active',
    notes: '',
});

const isEdit = ref(false);

/** A date column arrives as an ISO timestamp; the date input wants YYYY-MM-DD. */
const asDateInput = (v) => (v ? String(v).substring(0, 10) : '');

const fill = (row) => {
    if (! row) {
        form.reset();
        form.clearErrors();
        form.status = 'active';
        isEdit.value = false;

        return;
    }

    form.employee_no   = row.employee_no ?? '';
    form.first_name    = row.first_name ?? '';
    form.middle_name   = row.middle_name ?? '';
    form.last_name     = row.last_name ?? '';
    form.email         = row.email ?? '';
    form.contact_no    = row.contact_no ?? '';
    form.position      = row.position ?? '';
    form.department_id = row.department_id ?? '';
    form.location_id   = row.location_id ?? '';
    form.date_hired    = asDateInput(row.date_hired);
    form.date_resigned = asDateInput(row.date_resigned);
    form.status        = row.status ?? 'active';
    form.notes         = row.notes ?? '';
    form.clearErrors();
    isEdit.value = true;
};

// Refill every time the modal opens, so reopening never shows stale values.
watch(() => [props.show, props.employee], ([show]) => {
    if (show) {
        fill(props.employee);
    }
}, { immediate: true });

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('close');
        },
    };

    isEdit.value && props.employee
        ? form.put(`/employees/${props.employee.id}`, options)
        : form.post('/employees', options);
};
</script>

<template>
    <Modal :show="show" :title="isEdit ? 'Edit Employee' : 'New Employee'" max-width="2xl" @close="emit('close')">
        <form @submit.prevent="submit">
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <FormField label="Employee #" :error="form.errors.employee_no" required>
                    <input v-model="form.employee_no" type="text" class="input" />
                </FormField>
                <FormField label="Status" :error="form.errors.status" required>
                    <Combobox v-model="form.status" :options="EMPLOYEE_STATUSES" value-key="value" label-key="label" :nullable="false" placeholder="Status…" />
                </FormField>
                <FormField label="First Name" :error="form.errors.first_name" required>
                    <input v-model="form.first_name" type="text" class="input" />
                </FormField>
                <FormField label="Middle Name" :error="form.errors.middle_name">
                    <input v-model="form.middle_name" type="text" class="input" />
                </FormField>
                <FormField label="Last Name" :error="form.errors.last_name" required>
                    <input v-model="form.last_name" type="text" class="input" />
                </FormField>
                <FormField label="Position" :error="form.errors.position">
                    <input v-model="form.position" type="text" class="input" />
                </FormField>
                <FormField label="Email" :error="form.errors.email">
                    <input v-model="form.email" type="email" class="input" />
                </FormField>
                <FormField label="Contact #" :error="form.errors.contact_no">
                    <input v-model="form.contact_no" type="text" class="input" />
                </FormField>
                <FormField label="Department" :error="form.errors.department_id">
                    <Combobox v-model="form.department_id" :options="departments" placeholder="Search department…" />
                </FormField>
                <FormField label="Location" :error="form.errors.location_id">
                    <Combobox v-model="form.location_id" :options="locations" placeholder="Search location…" />
                </FormField>
                <FormField label="Date Hired" :error="form.errors.date_hired">
                    <input v-model="form.date_hired" type="date" class="input" />
                </FormField>
                <FormField label="Date Resigned" :error="form.errors.date_resigned">
                    <input v-model="form.date_resigned" type="date" class="input" />
                    <p class="help">Fill in only if the employee has resigned.</p>
                </FormField>
                <FormField label="Notes / Remarks" :error="form.errors.notes" class="sm:col-span-2">
                    <textarea v-model="form.notes" rows="3" class="input" placeholder="Optional — any relevant notes about this employee."></textarea>
                </FormField>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                <button type="button" class="btn-secondary" @click="emit('close')">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Update' : 'Create') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
