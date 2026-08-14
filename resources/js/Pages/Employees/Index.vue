<script setup>
import { ref, watch, computed, nextTick } from 'vue';
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
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, UsersIcon, CpuChipIcon, ArrowTopRightOnSquareIcon, DocumentTextIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

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

const searchInput = ref(null);
let timer = null;
const refresh = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const el = searchInput.value;
        const wasFocused = el && document.activeElement === el;
        const caret = wasFocused ? el.selectionStart : null;
        router.get('/employees', {
            search: search.value || undefined,
            department_id: departmentFilter.value || undefined,
            status: statusFilter.value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['employees', 'filters'],
            onSuccess: () => {
                if (wasFocused) {
                    nextTick(() => {
                        const input = searchInput.value;
                        if (!input) return;
                        input.focus();
                        if (caret !== null) {
                            try { input.setSelectionRange(caret, caret); } catch (e) { /* type=search may not support */ }
                        }
                    });
                }
            },
        });
    }, 1000);
};
watch([search, departmentFilter, statusFilter], refresh);

const hasFilters = computed(() => search.value || departmentFilter.value || statusFilter.value);

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    employee_no: '', first_name: '', middle_name: '', last_name: '',
    email: '', contact_no: '', position: '',
    department_id: '', location_id: '',
    date_hired: '', date_resigned: '', status: 'active',
    notes: '',
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
    form.date_hired = row.date_hired ? String(row.date_hired).substring(0, 10) : '';
    form.date_resigned = row.date_resigned ? String(row.date_resigned).substring(0, 10) : '';
    form.status = row.status ?? 'active';
    form.notes = row.notes ?? '';
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

// View held-assets modal
const showAssetsModal = ref(false);
const viewingEmployee = ref(null);
const assetsList = ref([]);
const assetsLoading = ref(false);
const openAssets = async (row) => {
    viewingEmployee.value = row;
    showAssetsModal.value = true;
    assetsLoading.value = true;
    assetsList.value = [];
    try {
        const res = await fetch(`/employees/${row.id}/assets`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        assetsList.value = data.assets || [];
    } finally {
        assetsLoading.value = false;
    }
};

const assetStatusTone = {
    in_stock:   'sky',
    assigned:   'brand',
    for_repair: 'amber',
    defective:  'rose',
    retired:    'slate',
    replaced:   'slate',
};

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
                    <button v-if="can('employees', 'create')" class="btn-primary" @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> Add Employee
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="grid gap-2 border-b border-slate-100 p-3 sm:grid-cols-[minmax(220px,1fr)_repeat(2,minmax(0,12rem))] sm:items-center">
                <div class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input ref="searchInput" v-model="search" type="search" placeholder="Search name, employee #, email..." class="input pl-9" />
                </div>
                <Combobox v-model="departmentFilter" :options="departments" placeholder="All Departments" null-label="All Departments" />
                <Combobox v-model="statusFilter" :options="EMPLOYEE_STATUSES" value-key="value" label-key="label" placeholder="All Status" null-label="All Status" />
            </div>

            <EmptyState
                v-if="employees.data.length === 0 && !hasFilters"
                title="No employees yet"
                description="Add your staff so you can assign and track IT assets."
                :icon="UsersIcon"
            >
                <button v-if="can('employees', 'create')" class="btn-primary" @click="openCreate">
                    <PlusIcon class="h-4 w-4" /> Add your first employee
                </button>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <SortableTh field="employee" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }">Employee</SortableTh>
                                <SortableTh field="position" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }" class="hidden md:table-cell">Position</SortableTh>
                                <SortableTh field="department" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }" class="hidden sm:table-cell">Department</SortableTh>
                                <SortableTh field="location" :sort="filters?.sort" :direction="filters?.direction" url="/employees" :extra="{ search: search || undefined, department_id: departmentFilter || undefined, status: statusFilter || undefined }" class="hidden lg:table-cell">Location</SortableTh>
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
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <div class="flex h-8 w-8 sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[10px] sm:text-xs font-semibold text-brand-700 ring-1 ring-brand-100">
                                            {{ initials(row) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="cell-strong truncate flex items-center gap-2">
                                                <span>{{ row.last_name }}, {{ row.first_name }}{{ row.middle_name ? ' ' + row.middle_name.charAt(0) + '.' : '' }}</span>
                                                <Badge v-if="row.held_assets_count > 0" tone="brand" class="!py-0 !px-1.5 text-[10px]">
                                                    {{ row.held_assets_count }} {{ row.held_assets_count === 1 ? 'asset' : 'assets' }}
                                                </Badge>
                                            </div>
                                            <div class="text-[11px] text-slate-500 truncate">{{ row.email || row.employee_no }}</div>
                                            <!-- Fold Position + Department into the primary cell on mobile -->
                                            <div class="md:hidden text-[11px] text-slate-500 truncate">
                                                {{ row.position || '' }}<span v-if="row.position && row.department"> · </span>{{ row.department?.name || '' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden md:table-cell">{{ row.position || '—' }}</td>
                                <td class="hidden sm:table-cell">{{ row.department?.name || '—' }}</td>
                                <td class="hidden lg:table-cell">{{ row.location?.name || '—' }}</td>
                                <td><Badge :tone="statusTone[row.status]" dot>{{ row.status }}</Badge></td>
                                <td class="cell-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a v-if="row.held_assets_count > 0 && can('accountability', 'print')"
                                           :href="`/employees/${row.id}/accountability.docx`"
                                           class="btn-ghost" title="Download Accountability Form (.docx)">
                                            <DocumentTextIcon class="h-4 w-4" />
                                        </a>
                                        <button class="btn-ghost" title="View held assets" @click="openAssets(row)"><CpuChipIcon class="h-4 w-4" /></button>
                                        <button v-if="can('employees', 'edit')" class="btn-ghost" title="Edit" @click="openEdit(row)"><PencilSquareIcon class="h-4 w-4" /></button>
                                        <button v-if="can('employees', 'delete')" class="btn-ghost-danger" title="Delete" @click="confirmDelete(row)"><TrashIcon class="h-4 w-4" /></button>
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
                    <FormField label="Date Resigned" :error="form.errors.date_resigned">
                        <input v-model="form.date_resigned" type="date" class="input" />
                        <p class="help">Fill in only if the employee has resigned.</p>
                    </FormField>
                    <FormField label="Notes / Remarks" :error="form.errors.notes" class="sm:col-span-2">
                        <textarea v-model="form.notes" rows="3" class="input" placeholder="Optional — any relevant notes about this employee."></textarea>
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

        <Modal :show="showAssetsModal" max-width="3xl" :title="viewingEmployee ? `Assets held by ${viewingEmployee.first_name} ${viewingEmployee.last_name}` : 'Held Assets'" @close="showAssetsModal = false">
            <div class="p-5">
                <div v-if="assetsLoading" class="py-8 text-center text-sm text-slate-500">Loading...</div>
                <div v-else-if="assetsList.length === 0" class="py-8 text-center text-sm text-slate-500">
                    <CpuChipIcon class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                    No assets currently held by this employee.
                </div>
                <div v-else class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th class="hidden sm:table-cell">Category</th>
                                <th class="hidden md:table-cell">Brand / Model</th>
                                <th class="hidden lg:table-cell">Serial</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in assetsList" :key="a.id">
                                <td class="cell-strong">{{ a.asset_tag }}</td>
                                <td class="hidden sm:table-cell">{{ a.category || '—' }}</td>
                                <td class="hidden md:table-cell">
                                    <div>{{ a.brand || '—' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ a.model || '' }}</div>
                                </td>
                                <td class="hidden lg:table-cell">{{ a.serial_number || '—' }}</td>
                                <td><Badge :tone="assetStatusTone[a.current_status]" dot>{{ a.current_status }}</Badge></td>
                                <td class="cell-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a v-if="can('accountability', 'print')"
                                           :href="`/accountability/${viewingEmployee.id}/download?asset_id=${a.id}`"
                                           class="btn-ghost" title="Download Accountability Form for this device only">
                                            <ArrowDownTrayIcon class="h-4 w-4" />
                                        </a>
                                        <Link v-if="can('assets', 'view')" :href="`/assets/${a.id}`" class="btn-ghost" title="Open asset">
                                            <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                <a v-if="viewingEmployee && assetsList.length > 0 && can('accountability', 'print')"
                   :href="`/employees/${viewingEmployee.id}/accountability.docx`"
                   class="btn-secondary">
                    <DocumentTextIcon class="h-4 w-4" /> Accountability Form
                </a>
                <span v-else></span>
                <button type="button" class="btn-secondary" @click="showAssetsModal = false">Close</button>
            </div>
        </Modal>
    </AppLayout>
</template>
