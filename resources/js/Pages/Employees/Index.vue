<script setup>
import { ref, watch, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import SortableTh from '@/Components/SortableTh.vue';
import Combobox from '@/Components/Combobox.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, UsersIcon } from '@heroicons/vue/24/outline';

const EMPLOYEE_STATUSES = [
    { value: 'active',   label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'resigned', label: 'Resigned' },
];

const props = defineProps({
    employees: Object,
    departments: Array,
    locations: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const departmentFilter = ref(props.filters?.department_id ?? '');
const statusFilter = ref(props.filters?.status ?? '');

let timer = null;
const refresh = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/employees', {
            search: search.value || undefined,
            department_id: departmentFilter.value || undefined,
            status: statusFilter.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
};
watch([search, departmentFilter, statusFilter], refresh);

const hasFilters = computed(() => search.value || departmentFilter.value || statusFilter.value);

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    employee_no: '', first_name: '', middle_name: '', last_name: '',
    email: '', contact_no: '', position: '',
    department_id: '', location_id: '', date_hired: '', status: 'active',
});

const openCreate = () => { editing.value = null; form.reset(); form.status = 'active'; showModal.value = true; };
const openEdit = (row) => {
    editing.value = row;
    form.employee_no = row.employee_no;
    form.first_name = row.first_name;
    form.middle_name = row.middle_name ?? '';
    form.last_name = row.last_name;
    form.email = row.email ?? '';
    form.contact_no = row.contact_no ?? '';
    form.position = row.position ?? '';
    form.department_id = row.department_id ?? '';
    form.location_id = row.location_id ?? '';
    form.date_hired = row.date_hired ?? '';
    form.status = row.status ?? 'active';
    showModal.value = true;
};

const submit = () => {
    const opts = { onSuccess: () => { showModal.value = false; form.reset(); }, preserveScroll: true };
    editing.value ? form.put(`/employees/${editing.value.id}`, opts) : form.post('/employees', opts);
};

const showDelete = ref(false);
const toDelete = ref(null);
const confirmDelete = (row) => { toDelete.value = row; showDelete.value = true; };
const doDelete = () => router.delete(`/employees/${toDelete.value.id}`, {
    onFinish: () => { showDelete.value = false; toDelete.value = null; },
    preserveScroll: true,
});

const statusTone = {
    active:   'emerald',
    inactive: 'slate',
    resigned: 'rose',
};

const initials = (row) => `${row.first_name?.charAt(0) ?? ''}${row.last_name?.charAt(0) ?? ''}`.toUpperCase();
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Employees" subtitle="Staff who use and own IT assets.">
                <template #actions>
                    <button class="btn-primary" @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> Add Employee
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                <div class="relative flex-1 min-w-[240px] max-w-sm">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search name, employee #, email..." class="input pl-9" />
                </div>
                <div class="w-48"><Combobox v-model="departmentFilter" :options="departments" placeholder="All Departments" null-label="All Departments" /></div>
                <div class="w-40"><Combobox v-model="statusFilter" :options="EMPLOYEE_STATUSES" value-key="value" label-key="label" placeholder="All Status" null-label="All Status" /></div>
            </div>

            <EmptyState
                v-if="employees.data.length === 0 && !hasFilters"
                title="No employees yet"
                description="Add your staff so you can assign and track IT assets."
                :icon="UsersIcon"
            >
                <button class="btn-primary" @click="openCreate">
                    <PlusIcon class="h-4 w-4" /> Add your first employee
                </button>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <SortableTh field="employee" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }">Employee</SortableTh>
                                <SortableTh field="position" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }">Position</SortableTh>
                                <SortableTh field="department" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }">Department</SortableTh>
                                <SortableTh field="location" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }">Location</SortableTh>
                                <SortableTh field="status" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }">Status</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="employees.data.length === 0">
                                <td colspan="6" class="cell-muted text-center py-10">No employees match your filters.</td>
                            </tr>
                            <tr v-for="row in employees.data" :key="row.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-brand-100">
                                            {{ initials(row) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="cell-strong">
                                                {{ row.last_name }}, {{ row.first_name }}{{ row.middle_name ? ' ' + row.middle_name.charAt(0) + '.' : '' }}
                                            </div>
                                            <div class="text-xs text-slate-500">{{ row.email || row.employee_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ row.position || '—' }}</td>
                                <td>{{ row.department?.name || '—' }}</td>
                                <td>{{ row.location?.name || '—' }}</td>
                                <td><Badge :tone="statusTone[row.status]" dot>{{ row.status }}</Badge></td>
                                <td class="cell-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button class="btn-ghost" @click="openEdit(row)"><PencilSquareIcon class="h-4 w-4" /></button>
                                        <button class="btn-ghost-danger" @click="confirmDelete(row)"><TrashIcon class="h-4 w-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="employees.links" :from="employees.from" :to="employees.to" :total="employees.total" />
            </template>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Employee' : 'New Employee'" max-width="2xl" @close="showModal = false">
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
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <button type="button" class="btn-secondary" @click="showModal = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : (editing ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="showDelete"
            :title="`Delete ${toDelete?.first_name} ${toDelete?.last_name}?`"
            message="This employee will be removed. Inventory transactions referencing them will be unlinked."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
