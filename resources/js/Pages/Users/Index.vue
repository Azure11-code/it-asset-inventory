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
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    users:              { type: Object, required: true },
    filters:            { type: Object, default: () => ({}) },
    permission_catalog: { type: Object, default: () => ({ resources: [], action_labels: {} }) },
});

const search = ref(props.filters?.search ?? '');
let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/users', { search: val || undefined }, { preserveState: true, preserveScroll: true, replace: true, only: ['users', 'filters'] });
    }, 1000);
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({
    name: '', username: '', email: '',
    password: '', password_confirmation: '',
    is_admin: false, permissions: [],
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.permissions = [];
    form.is_admin = false;
    showModal.value = true;
};
const openEdit = (row) => {
    editing.value = row;
    form.name = row.name;
    form.username = row.username ?? '';
    form.email = row.email;
    form.password = '';
    form.password_confirmation = '';
    form.is_admin = !!row.is_admin;
    form.permissions = Array.isArray(row.permissions) ? [...row.permissions] : [];
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

// ── Permission matrix helpers ──
const resources    = computed(() => props.permission_catalog.resources || []);
const actionLabels = computed(() => props.permission_catalog.action_labels || {});
const allActions   = computed(() => Object.keys(actionLabels.value));

const permKey = (resource, action) => `${resource}.${action}`;
const hasPerm = (resource, action) => form.permissions.includes(permKey(resource, action));
const togglePerm = (resource, action) => {
    const k = permKey(resource, action);
    const i = form.permissions.indexOf(k);
    if (i >= 0) form.permissions.splice(i, 1);
    else        form.permissions.push(k);
};
const toggleAllForResource = (resource) => {
    const r = resources.value.find(x => x.key === resource);
    if (!r) return;
    const allSet = r.actions.every(a => hasPerm(resource, a));
    if (allSet) {
        r.actions.forEach(a => {
            const i = form.permissions.indexOf(permKey(resource, a));
            if (i >= 0) form.permissions.splice(i, 1);
        });
    } else {
        r.actions.forEach(a => {
            const k = permKey(resource, a);
            if (!form.permissions.includes(k)) form.permissions.push(k);
        });
    }
};
const allForResource = (resource) => {
    const r = resources.value.find(x => x.key === resource);
    if (!r) return false;
    return r.actions.every(a => hasPerm(resource, a));
};

const badgeCount = computed(() => (row) => {
    if (row.is_admin) return 'ALL';
    return `${row.permissions?.length || 0}`;
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Users" subtitle="Login accounts and per-page permissions.">
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
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <SortableTh field="name" :sort="filters?.sort" :direction="filters?.direction" url="/users" :extra="{ search: search || undefined }">User</SortableTh>
                                <SortableTh field="username" :sort="filters?.sort" :direction="filters?.direction" url="/users" :extra="{ search: search || undefined }" class="hidden md:table-cell">Username</SortableTh>
                                <th>Access</th>
                                <SortableTh field="created_at" :sort="filters?.sort" :direction="filters?.direction" url="/users" :extra="{ search: search || undefined }" class="hidden lg:table-cell">Joined</SortableTh>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="cell-muted text-center py-10">No users match "{{ search }}".</td>
                            </tr>
                            <tr v-for="row in users.data" :key="row.id">
                                <td>
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <div class="flex h-8 w-8 sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-[10px] sm:text-xs font-semibold text-white">
                                            {{ initials(row.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="cell-strong truncate">{{ row.name }}</div>
                                            <div class="text-[11px] text-slate-500 truncate">{{ row.email || row.username || '' }}</div>
                                            <Badge v-if="row.is_current" tone="brand" class="mt-0.5">you</Badge>
                                        </div>
                                    </div>
                                </td>
                                <td class="cell-muted hidden md:table-cell">{{ row.username || '—' }}</td>
                                <td>
                                    <Badge v-if="row.is_admin" tone="rose" dot>Admin</Badge>
                                    <Badge v-else tone="slate" dot>{{ row.permissions?.length || 0 }} perms</Badge>
                                </td>
                                <td class="cell-muted hidden lg:table-cell">{{ row.created_at }}</td>
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

        <Modal :show="showModal" :title="editing ? `Edit ${editing.name}` : 'New User'" max-width="4xl" @close="showModal = false">
            <form @submit.prevent="submit">
                <div class="space-y-5 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Name" :error="form.errors.name" required>
                            <input v-model="form.name" type="text" class="input" />
                        </FormField>
                        <FormField label="Username" :error="form.errors.username" required>
                            <input v-model="form.username" type="text" class="input" autocomplete="off" placeholder="e.g. jdoe" />
                        </FormField>
                        <FormField label="Email" :error="form.errors.email">
                            <input v-model="form.email" type="email" class="input" autocomplete="off" placeholder="optional" />
                        </FormField>
                        <FormField :label="editing ? 'New Password (leave blank to keep)' : 'Password'" :error="form.errors.password" :required="!editing">
                            <input v-model="form.password" type="password" class="input" autocomplete="new-password" />
                        </FormField>
                        <FormField label="Confirm Password" v-if="!editing || form.password">
                            <input v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" />
                        </FormField>
                    </div>

                    <!-- Admin toggle -->
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                v-model="form.is_admin"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                            />
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-slate-800">Administrator</div>
                                <div class="text-xs text-slate-500">Full access sa lahat ng pages at actions. Kapag naka-check, hindi na kailangan ang matrix sa baba.</div>
                            </div>
                        </label>
                    </div>

                    <!-- Permission matrix -->
                    <div v-if="!form.is_admin">
                        <div class="mb-2 flex items-baseline justify-between">
                            <label class="text-sm font-semibold text-slate-800">Page permissions</label>
                            <span class="text-xs text-slate-500">{{ form.permissions.length }} granted</span>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full table-fixed text-xs">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="w-48 px-3 py-2 text-left font-semibold text-slate-600">Page</th>
                                        <th v-for="a in allActions" :key="a" class="w-16 px-2 py-2 text-center font-semibold text-slate-600">
                                            {{ actionLabels[a] }}
                                        </th>
                                        <th class="w-16 px-2 py-2 text-center font-semibold text-slate-600">All</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="r in resources" :key="r.key" class="hover:bg-slate-50">
                                        <td class="px-3 py-2 font-medium text-slate-700">{{ r.label }}</td>
                                        <td v-for="a in allActions" :key="a" class="px-2 py-2 text-center">
                                            <input
                                                v-if="r.actions.includes(a)"
                                                type="checkbox"
                                                :checked="hasPerm(r.key, a)"
                                                class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                                @change="togglePerm(r.key, a)"
                                            />
                                            <span v-else class="text-slate-300">—</span>
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="allForResource(r.key)"
                                                class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                                @change="toggleAllForResource(r.key)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-[11px] text-slate-500">
                            Kapag walang <strong>View</strong> permission, hindi lalabas ang page sa sidebar ng user na yun. Yung ibang actions (Create, Edit, Delete, etc.) magco-control kung anong buttons ang lalabas sa page.
                        </p>
                    </div>
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
