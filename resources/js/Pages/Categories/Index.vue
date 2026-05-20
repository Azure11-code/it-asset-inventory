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
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';

const props = defineProps({ categories: Object, filters: Object });

const search = ref(props.filters?.search ?? '');
let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/categories', { search: val || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name: '', slug: '', prefix: '', description: '', is_active: true });

const openCreate = () => { editing.value = null; form.reset(); form.is_active = true; showModal.value = true; };
const openEdit = (row) => {
    editing.value = row;
    form.name = row.name; form.slug = row.slug ?? ''; form.prefix = row.prefix ?? '';
    form.description = row.description ?? ''; form.is_active = !!row.is_active;
    showModal.value = true;
};

const submit = () => {
    const opts = { onSuccess: () => { showModal.value = false; form.reset(); }, preserveScroll: true };
    editing.value ? form.put(`/categories/${editing.value.id}`, opts) : form.post('/categories', opts);
};

const showDelete = ref(false);
const toDelete = ref(null);
const confirmDelete = (row) => { toDelete.value = row; showDelete.value = true; };
const doDelete = () => router.delete(`/categories/${toDelete.value.id}`, {
    onFinish: () => { showDelete.value = false; toDelete.value = null; },
    preserveScroll: true,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Categories" subtitle="Asset categories (laptop, desktop, monitor, etc.).">
                <template #actions>
                    <button class="btn-primary" @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> Add Category
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                <div class="relative flex-1 max-w-sm">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search category..." class="input pl-9" />
                </div>
            </div>

            <EmptyState
                v-if="categories.data.length === 0 && !search"
                title="No categories yet"
                description="Group your assets by category (laptops, monitors, printers, etc.)."
                :icon="Squares2X2Icon"
            >
                <button class="btn-primary" @click="openCreate">
                    <PlusIcon class="h-4 w-4" /> Add your first category
                </button>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <SortableTh field="name" :sort="filters?.sort" :direction="filters?.direction" url="/categories" :extra="{ search: search || undefined }">Name</SortableTh>
                                <SortableTh field="prefix" :sort="filters?.sort" :direction="filters?.direction" url="/categories" :extra="{ search: search || undefined }">Prefix</SortableTh>
                                <SortableTh field="description" :sort="filters?.sort" :direction="filters?.direction" url="/categories" :extra="{ search: search || undefined }">Description</SortableTh>
                                <SortableTh field="is_active" :sort="filters?.sort" :direction="filters?.direction" url="/categories" :extra="{ search: search || undefined }">Status</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="categories.data.length === 0">
                                <td colspan="5" class="cell-muted text-center py-10">No categories match "{{ search }}".</td>
                            </tr>
                            <tr v-for="row in categories.data" :key="row.id">
                                <td class="cell-strong">{{ row.name }}</td>
                                <td>
                                    <Badge v-if="row.prefix" tone="brand">{{ row.prefix }}</Badge>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="cell-muted max-w-xl truncate">{{ row.description || '—' }}</td>
                                <td>
                                    <Badge :tone="row.is_active ? 'emerald' : 'slate'" dot>
                                        {{ row.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
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

                <Pagination :links="categories.links" :from="categories.from" :to="categories.to" :total="categories.total" />
            </template>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Category' : 'New Category'" @close="showModal = false">
            <form @submit.prevent="submit">
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <FormField label="Name" :error="form.errors.name" required>
                        <input v-model="form.name" type="text" class="input" placeholder="e.g. Laptop" />
                    </FormField>
                    <FormField label="Prefix" :error="form.errors.prefix">
                        <input v-model="form.prefix" type="text" maxlength="10" class="input" placeholder="LPT" />
                    </FormField>
                    <FormField label="Description" :error="form.errors.description" class="sm:col-span-2">
                        <textarea v-model="form.description" rows="3" class="input"></textarea>
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
            message="This category will be removed. Inventory items referencing it will be unlinked."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
