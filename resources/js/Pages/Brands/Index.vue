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
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, TagIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ brands: Object, filters: Object });

const search = ref(props.filters?.search ?? '');
let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/brands', { search: val || undefined }, { preserveState: true, preserveScroll: true, replace: true, only: ['brands', 'filters'] });
    }, 1000);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name: '', slug: '', description: '', is_active: true });

const openCreate = () => { editing.value = null; form.reset(); form.is_active = true; showModal.value = true; };
const openEdit = (row) => {
    editing.value = row;
    form.name = row.name; form.slug = row.slug ?? ''; form.description = row.description ?? '';
    form.is_active = !!row.is_active;
    showModal.value = true;
};

const submit = () => {
    const opts = { onSuccess: () => { showModal.value = false; form.reset(); }, preserveScroll: true };
    editing.value ? form.put(`/brands/${editing.value.id}`, opts) : form.post('/brands', opts);
};

const showDelete = ref(false);
const toDelete = ref(null);
const confirmDelete = (row) => { toDelete.value = row; showDelete.value = true; };
const doDelete = () => router.delete(`/brands/${toDelete.value.id}`, {
    onFinish: () => { showDelete.value = false; toDelete.value = null; },
    preserveScroll: true,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Brands" subtitle="Manufacturers and asset brands.">
                <template #actions>
                    <button class="btn-primary" @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> Add Brand
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                <div class="relative flex-1 max-w-sm">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search brand name..." class="input pl-9" />
                </div>
            </div>

            <EmptyState
                v-if="brands.data.length === 0 && !search"
                title="No brands yet"
                description="Add the manufacturers your IT assets come from (Dell, HP, Lenovo, etc.)."
                :icon="TagIcon"
            >
                <button class="btn-primary" @click="openCreate">
                    <PlusIcon class="h-4 w-4" /> Add your first brand
                </button>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <SortableTh field="name" :sort="filters?.sort" :direction="filters?.direction" url="/brands" :extra="{ search: search || undefined }">Name</SortableTh>
                                <SortableTh field="description" :sort="filters?.sort" :direction="filters?.direction" url="/brands" :extra="{ search: search || undefined }" class="hidden md:table-cell">Description</SortableTh>
                                <SortableTh field="is_active" :sort="filters?.sort" :direction="filters?.direction" url="/brands" :extra="{ search: search || undefined }">Status</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="brands.data.length === 0">
                                <td colspan="4" class="cell-muted text-center py-10">No brands match "{{ search }}".</td>
                            </tr>
                            <tr v-for="row in brands.data" :key="row.id">
                                <td class="cell-strong">{{ row.name }}</td>
                                <td class="cell-muted max-w-xl truncate hidden md:table-cell">{{ row.description || '—' }}</td>
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

                <Pagination :links="brands.links" :from="brands.from" :to="brands.to" :total="brands.total" />
            </template>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Brand' : 'New Brand'" @close="showModal = false">
            <form @submit.prevent="submit">
                <div class="space-y-4 p-5">
                    <FormField label="Name" :error="form.errors.name" required>
                        <input v-model="form.name" type="text" class="input" placeholder="e.g. Dell" />
                    </FormField>
                    <FormField label="Description" :error="form.errors.description">
                        <textarea v-model="form.description" rows="3" class="input"></textarea>
                    </FormField>
                    <FormField>
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
            message="This brand will be removed. Inventory items referencing it will be unlinked."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
