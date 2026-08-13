<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Badge from '@/Components/Badge.vue';
import Combobox from '@/Components/Combobox.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    signatories: Array,
    roles: Array,
});

const roleLabel = (v) => props.roles.find(r => r.value === v)?.label || v;
const roleTone = { checked_by: 'brand', reviewed_by: 'sky', approved_by: 'emerald' };

const grouped = computed(() => {
    const map = { checked_by: [], reviewed_by: [], approved_by: [] };
    for (const s of props.signatories) {
        (map[s.role] || (map[s.role] = [])).push(s);
    }
    return map;
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({
    name: '', title: '', role: 'checked_by', sort_order: 0, is_active: true,
});

const openCreate = (role = 'checked_by') => {
    editing.value = null;
    form.reset();
    form.role = role;
    form.sort_order = (grouped.value[role]?.length ?? 0) + 1;
    form.is_active = true;
    showModal.value = true;
};

const openEdit = (row) => {
    editing.value = row;
    form.name = row.name;
    form.title = row.title ?? '';
    form.role = row.role;
    form.sort_order = row.sort_order;
    form.is_active = !!row.is_active;
    showModal.value = true;
};

const submit = () => {
    const opts = { onSuccess: () => { showModal.value = false; form.reset(); }, preserveScroll: true };
    editing.value ? form.put(`/signatories/${editing.value.id}`, opts) : form.post('/signatories', opts);
};

const showDelete = ref(false);
const toDelete = ref(null);
const confirmDelete = (row) => { toDelete.value = row; showDelete.value = true; };
const doDelete = () => router.delete(`/signatories/${toDelete.value.id}`, {
    onFinish: () => { showDelete.value = false; toDelete.value = null; },
    preserveScroll: true,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Signatories" subtitle="People who sign the Accountability Agreement Form (Checked, Reviewed, Approved).">
                <template #actions>
                    <button class="btn-primary" @click="openCreate()">
                        <PlusIcon class="h-4 w-4" /> Add Signatory
                    </button>
                </template>
            </PageHeader>
        </template>

        <div v-if="signatories.length === 0" class="card">
            <EmptyState
                title="No signatories yet"
                description="Add IT staff and management who sign the Accountability Agreement Form."
                :icon="UserGroupIcon"
            >
                <button class="btn-primary" @click="openCreate()">
                    <PlusIcon class="h-4 w-4" /> Add your first signatory
                </button>
            </EmptyState>
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-3">
            <div v-for="role in roles" :key="role.value" class="card">
                <div class="flex items-center justify-between border-b border-slate-100 p-3">
                    <div class="flex items-center gap-2">
                        <Badge :tone="roleTone[role.value]" dot>{{ role.label }}</Badge>
                        <span class="text-xs text-slate-500">{{ grouped[role.value]?.length || 0 }}</span>
                    </div>
                    <button class="btn-ghost" title="Add to this group" @click="openCreate(role.value)">
                        <PlusIcon class="h-4 w-4" />
                    </button>
                </div>
                <ul v-if="grouped[role.value]?.length" class="divide-y divide-slate-100">
                    <li v-for="s in grouped[role.value]" :key="s.id" class="flex items-center justify-between gap-2 p-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 truncate">{{ s.name }}</div>
                            <div class="text-[11px] text-slate-500 truncate">{{ s.title || '—' }}</div>
                            <div class="mt-1">
                                <Badge v-if="!s.is_active" tone="slate" dot>Inactive</Badge>
                            </div>
                        </div>
                        <div class="inline-flex gap-1">
                            <button class="btn-ghost" @click="openEdit(s)"><PencilSquareIcon class="h-4 w-4" /></button>
                            <button class="btn-ghost-danger" @click="confirmDelete(s)"><TrashIcon class="h-4 w-4" /></button>
                        </div>
                    </li>
                </ul>
                <div v-else class="p-6 text-center text-xs text-slate-400">No one added yet.</div>
            </div>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Signatory' : 'New Signatory'" max-width="lg" @close="showModal = false">
            <form @submit.prevent="submit">
                <div class="grid gap-4 p-5">
                    <FormField label="Full Name" :error="form.errors.name" required>
                        <input v-model="form.name" type="text" class="input" placeholder="e.g. Joseph Carrido" />
                    </FormField>
                    <FormField label="Title / Position" :error="form.errors.title">
                        <input v-model="form.title" type="text" class="input" placeholder="e.g. Junior IT Associate" />
                    </FormField>
                    <FormField label="Role" :error="form.errors.role" required>
                        <Combobox v-model="form.role" :options="roles" value-key="value" label-key="label" :nullable="false" />
                    </FormField>
                    <FormField label="Sort Order" :error="form.errors.sort_order">
                        <input v-model.number="form.sort_order" type="number" min="0" class="input" />
                        <p class="help">Lower numbers appear first within their role group.</p>
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
            :title="`Remove ${toDelete?.name}?`"
            message="This signatory will no longer appear on new Accountability Agreement forms."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
