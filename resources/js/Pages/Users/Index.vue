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
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ users: Object, filters: Object });

const search = ref(props.filters?.search ?? '');
let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/users', { search: val || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name: '', username: '', email: '', password: '', password_confirmation: '' });

const openCreate = () => {
    editing.value = null;
    form.reset();
    showModal.value = true;
};
const openEdit = (row) => {
    editing.value = row;
    form.name = row.name;
    form.username = row.username ?? '';
    form.email = row.email;
    form.password = '';
    form.password_confirmation = '';
    showModal.value = true;
};

const submit = () => {
    const opts = { onSuccess: () => { showModal.value = false; form.reset(); }, preserveScroll: true };
    editing.value ? form.put(`/users/${editing.value.id}`, opts) : form.post('/users', opts);
};

const showDelete = ref(false);
const toDelete = ref(null);
const confirmDelete = (row) => { toDelete.value = row; showDelete.value = true; };
const doDelete = () => router.delete(`/users/${toDelete.value.id}`, {
    onFinish: () => { showDelete.value = false; toDelete.value = null; },
    preserveScroll: true,
});

const initials = (name) => (name || '?').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Users" subtitle="System users with login access to this app.">
                <template #actions>
                    <button class="btn-primary" @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> Add User
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                <div class="relative flex-1 max-w-sm">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Search name or email..." class="input pl-9" />
                </div>
            </div>

            <EmptyState
                v-if="users.data.length === 0 && !search"
                title="No users yet"
                description="Add system users to grant login access to the inventory app."
                :icon="ShieldCheckIcon"
            >
                <button class="btn-primary" @click="openCreate">
                    <PlusIcon class="h-4 w-4" /> Add your first user
                </button>
            </EmptyState>

            <template v-else>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <SortableTh field="name" :sort="filters?.sort" :direction="filters?.direction" url="/users" :extra="{ search: search || undefined }">User</SortableTh>
                                <SortableTh field="username" :sort="filters?.sort" :direction="filters?.direction" url="/users" :extra="{ search: search || undefined }">Username</SortableTh>
                                <SortableTh field="email" :sort="filters?.sort" :direction="filters?.direction" url="/users" :extra="{ search: search || undefined }">Email</SortableTh>
                                <SortableTh field="created_at" :sort="filters?.sort" :direction="filters?.direction" url="/users" :extra="{ search: search || undefined }">Joined</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="cell-muted text-center py-10">No users match "{{ search }}".</td>
                            </tr>
                            <tr v-for="row in users.data" :key="row.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-xs font-semibold text-white">
                                            {{ initials(row.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="cell-strong">{{ row.name }}</div>
                                            <Badge v-if="row.is_current" tone="brand" class="mt-0.5">you</Badge>
                                        </div>
                                    </div>
                                </td>
                                <td class="cell-muted">{{ row.username || '—' }}</td>
                                <td class="cell-muted">{{ row.email || '—' }}</td>
                                <td class="cell-muted">{{ row.created_at }}</td>
                                <td class="cell-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button class="btn-ghost" @click="openEdit(row)"><PencilSquareIcon class="h-4 w-4" /></button>
                                        <button
                                            class="btn-ghost-danger"
                                            :disabled="row.is_current"
                                            :title="row.is_current ? 'Cannot delete yourself' : 'Delete'"
                                            @click="confirmDelete(row)"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="users.links" :from="users.from" :to="users.to" :total="users.total" />
            </template>
        </div>

        <Modal :show="showModal" :title="editing ? `Edit ${editing.name}` : 'New User'" max-width="lg" @close="showModal = false">
            <form @submit.prevent="submit">
                <div class="space-y-4 p-5">
                    <FormField label="Name" :error="form.errors.name" required>
                        <input v-model="form.name" type="text" class="input" />
                    </FormField>
                    <FormField label="Username" :error="form.errors.username" required>
                        <input v-model="form.username" type="text" class="input" autocomplete="off" placeholder="e.g. jdoe" />
                        <p class="help">Letters, numbers, dash, underscore. Used to sign in.</p>
                    </FormField>
                    <FormField label="Email" :error="form.errors.email">
                        <input v-model="form.email" type="email" class="input" autocomplete="off" placeholder="optional" />
                    </FormField>
                    <FormField :label="editing ? 'New Password (leave blank to keep)' : 'Password'" :error="form.errors.password" :required="!editing">
                        <input v-model="form.password" type="password" class="input" autocomplete="new-password" />
                        <p class="help">Minimum 8 characters.</p>
                    </FormField>
                    <FormField label="Confirm Password" v-if="!editing || form.password">
                        <input v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" />
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
            message="This user will lose login access. This cannot be undone."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
