<script setup>
import { ref, watch } from 'vue';
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
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({ conditions: Object, filters: Object });

const TONES = [
    { value: 'emerald', label: 'Emerald' },
    { value: 'sky',     label: 'Sky' },
    { value: 'brand',   label: 'Brand' },
    { value: 'amber',   label: 'Amber' },
    { value: 'rose',    label: 'Rose' },
    { value: 'slate',   label: 'Slate' },
];

const search = ref(props.filters?.search ?? '');
let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/conditions', { search: val || undefined }, { preserveState: true, preserveScroll: true, replace: true, only: ['conditions', 'filters'] });
    }, 1000);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name: '', description: '', tone: 'slate', sort_order: 0, is_active: true });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.tone = 'slate';
    form.sort_order = (props.conditions.total ?? 0) + 1;
    form.is_active = true;
    showModal.value = true;
};
const openEdit = (row) => {
    editing.value = row;
    form.name = row.name;
    form.description = row.description ?? '';
    form.tone = row.tone;
    form.sort_order = row.sort_order;
    form.is_active = !!row.is_active;
    showModal.value = true;
};

const submit = () => {
    const opts = { onSuccess: () => { showModal.value = false; form.reset(); }, preserveScroll: true };
    editing.value ? form.put(`/conditions/${editing.value.id}`, opts) : form.post('/conditions', opts);
};

const showDelete = ref(false);
const toDelete = ref(null);
const confirmDelete = (row) => { toDelete.value = row; showDelete.value = true; };
const doDelete = () => router.delete(`/conditions/${toDelete.value.id}`, {
    onFinish: () => { showDelete.value = false; toDelete.value = null; },
    preserveScroll: true,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Conditions" subtitle="Asset condition labels (new, good, fair, poor, defective, …).">
                <template #actions>
                    <button v-if="can('conditions', 'create')" class="btn-primary" @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> Add Condition
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                <div class="relative flex-1 max-w-sm">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search condition..." class="input pl-9" />
                </div>
            </div>

            <EmptyState
                v-if="conditions.data.length === 0 && !search"
                title="No conditions yet"
                description="Define the labels you'll use to describe asset condition."
                :icon="SparklesIcon"
            >
                <button v-if="can('conditions', 'create')" class="btn-primary" @click="openCreate">
                    <PlusIcon class="h-4 w-4" /> Add your first condition
                </button>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <SortableTh field="sort_order" :sort="filters?.sort" :direction="filters?.direction" url="/conditions" :extra="{ search: search || undefined }" class="w-16">Order</SortableTh>
                                <SortableTh field="name" :sort="filters?.sort" :direction="filters?.direction" url="/conditions" :extra="{ search: search || undefined }">Name</SortableTh>
                                <SortableTh field="description" :sort="filters?.sort" :direction="filters?.direction" url="/conditions" :extra="{ search: search || undefined }" class="hidden md:table-cell">Description</SortableTh>
                                <th>Badge</th>
                                <SortableTh field="assets_count" :sort="filters?.sort" :direction="filters?.direction" url="/conditions" :extra="{ search: search || undefined }" align="right">Assets</SortableTh>
                                <SortableTh field="is_active" :sort="filters?.sort" :direction="filters?.direction" url="/conditions" :extra="{ search: search || undefined }">Status</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="conditions.data.length === 0">
                                <td colspan="7" class="cell-muted text-center py-10">No conditions match "{{ search }}".</td>
                            </tr>
                            <tr v-for="row in conditions.data" :key="row.id">
                                <td class="cell-muted">{{ row.sort_order }}</td>
                                <td class="cell-strong">{{ row.name }}</td>
                                <td class="cell-muted max-w-md truncate hidden md:table-cell">{{ row.description || '—' }}</td>
                                <td>
                                    <Badge :tone="row.tone" dot>{{ row.name }}</Badge>
                                </td>
                                <td class="cell-right">{{ row.assets_count }}</td>
                                <td>
                                    <Badge :tone="row.is_active ? 'emerald' : 'slate'" dot>
                                        {{ row.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
                                <td class="cell-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button v-if="can('conditions', 'edit')" class="btn-ghost" @click="openEdit(row)"><PencilSquareIcon class="h-4 w-4" /></button>
                                        <button v-if="can('conditions', 'delete')" class="btn-ghost-danger" @click="confirmDelete(row)" :disabled="row.assets_count > 0" :title="row.assets_count > 0 ? 'In use, cannot delete' : 'Delete'">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="conditions.links" :from="conditions.from" :to="conditions.to" :total="conditions.total" />
            </template>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Condition' : 'New Condition'" max-width="xl" @close="showModal = false">
            <form @submit.prevent="submit">
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <FormField label="Name" :error="form.errors.name" required class="sm:col-span-2">
                        <input v-model="form.name" type="text" class="input" placeholder="e.g. Refurbished" />
                    </FormField>
                    <FormField label="Description" :error="form.errors.description" class="sm:col-span-2">
                        <input v-model="form.description" type="text" class="input" />
                    </FormField>
                    <FormField label="Badge Color (tone)" :error="form.errors.tone" required>
                        <Combobox v-model="form.tone" :options="TONES" value-key="value" label-key="label" :nullable="false" placeholder="Pick tone…" />
                        <div class="mt-2">
                            <Badge :tone="form.tone" dot>{{ form.name || 'Preview' }}</Badge>
                        </div>
                    </FormField>
                    <FormField label="Sort Order" :error="form.errors.sort_order" required>
                        <input v-model.number="form.sort_order" type="number" min="0" class="input" />
                        <p class="help">Lower numbers appear first.</p>
                    </FormField>
                    <FormField class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="checkbox" /> Active
                        </label>
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
            :title="`Delete ${toDelete?.name}?`"
            message="This condition will be removed. Cannot be undone."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
