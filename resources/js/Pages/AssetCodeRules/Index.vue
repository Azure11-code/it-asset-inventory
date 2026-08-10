<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import FormField from '@/Components/FormField.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Badge from '@/Components/Badge.vue';
import Combobox from '@/Components/Combobox.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon, HashtagIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    rules: Array,
    categories: Array,
});

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    label: '', prefix_start: 0, prefix_end: 99, category_id: '', is_active: true, sort_order: 0,
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.is_active = true;
    form.sort_order = (props.rules.length + 1) * 10;
    showModal.value = true;
};
const openEdit = (row) => {
    editing.value = row;
    form.label        = row.label;
    form.prefix_start = row.prefix_start;
    form.prefix_end   = row.prefix_end;
    form.category_id  = row.category_id ?? '';
    form.is_active    = row.is_active;
    form.sort_order   = row.sort_order;
    showModal.value = true;
};

const submit = () => {
    const opts = { onSuccess: () => { showModal.value = false; form.reset(); }, preserveScroll: true };
    editing.value
        ? form.put(`/asset-code-rules/${editing.value.id}`, opts)
        : form.post('/asset-code-rules', opts);
};

const showDelete = ref(false);
const toDelete = ref(null);
const confirmDelete = (row) => { toDelete.value = row; showDelete.value = true; };
const doDelete = () => router.delete(`/asset-code-rules/${toDelete.value.id}`, {
    onFinish: () => { showDelete.value = false; toDelete.value = null; },
    preserveScroll: true,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Asset Code Rules" subtitle="Numbering scheme used to auto-generate Asset Tags. Format: {YEAR}AIM{####}.">
                <template #actions>
                    <button class="btn-primary" @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> Add Rule
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="card">
            <div v-if="rules.length === 0" class="p-8 text-center">
                <HashtagIcon class="mx-auto h-10 w-10 text-slate-300" />
                <p class="mt-2 text-sm text-slate-600">No rules yet. Add one to enable auto-tagging on the Asset Create form.</p>
            </div>

            <div v-else class="table-wrap">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th class="hidden sm:table-cell">Range</th>
                            <th class="hidden md:table-cell">Category</th>
                            <th>Next Tag</th>
                            <th class="hidden lg:table-cell">Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rules" :key="r.id">
                            <td>
                                <div class="cell-strong">{{ r.label }}</div>
                                <div class="sm:hidden text-[11px] text-slate-500">Range {{ r.range }}</div>
                                <div class="md:hidden text-[11px] text-slate-500">{{ r.category?.name || '—' }}</div>
                            </td>
                            <td class="cell-muted hidden sm:table-cell">{{ r.range }}</td>
                            <td class="hidden md:table-cell">{{ r.category?.name || '—' }}</td>
                            <td>
                                <span class="font-mono text-xs" :class="r.next_preview === 'RANGE FULL' ? 'text-rose-600 font-semibold' : 'text-brand-700'">
                                    {{ r.next_preview }}
                                </span>
                            </td>
                            <td class="hidden lg:table-cell">
                                <Badge :tone="r.is_active ? 'emerald' : 'slate'" dot>
                                    {{ r.is_active ? 'active' : 'inactive' }}
                                </Badge>
                            </td>
                            <td class="cell-right">
                                <div class="inline-flex items-center gap-1">
                                    <button class="btn-ghost" @click="openEdit(r)"><PencilSquareIcon class="h-4 w-4" /></button>
                                    <button class="btn-ghost-danger" @click="confirmDelete(r)"><TrashIcon class="h-4 w-4" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4 text-xs text-slate-600">
            <p><strong>How it works:</strong></p>
            <ul class="mt-1 ml-4 list-disc space-y-1">
                <li>Format: <span class="font-mono text-slate-800">{YEAR}AIM{4-digit-number}</span> — year auto-detected mula sa current date.</li>
                <li>Bawat rule ay may reserved range (e.g., 001–199). Ang system ay hahanap ng pinaka-mababang available number sa loob ng range.</li>
                <li>Kung may Category link ang rule, kapag pinili sa Asset Create form, auto-select ang Category.</li>
                <li>Pag inedit mo ang range at overlap sa iba, allowed pero mag-aaway sila sa numbering — mas mabuti keep them disjoint.</li>
            </ul>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Rule' : 'New Rule'" max-width="lg" @close="showModal = false">
            <form @submit.prevent="submit">
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <FormField label="Label" :error="form.errors.label" required class="sm:col-span-2">
                        <input v-model="form.label" type="text" class="input" placeholder="e.g. HEAD OFFICE DESKTOP" />
                    </FormField>
                    <FormField label="Range Start" :error="form.errors.prefix_start" required>
                        <input v-model.number="form.prefix_start" type="number" min="0" class="input" />
                    </FormField>
                    <FormField label="Range End" :error="form.errors.prefix_end" required>
                        <input v-model.number="form.prefix_end" type="number" min="0" class="input" />
                    </FormField>
                    <FormField label="Category (optional)" :error="form.errors.category_id" class="sm:col-span-2">
                        <Combobox v-model="form.category_id" :options="categories" placeholder="— No category link —" null-label="— No category link —" />
                        <p class="help">If set, selecting this rule auto-selects the Category on the Asset Create form.</p>
                    </FormField>
                    <FormField label="Sort Order" :error="form.errors.sort_order">
                        <input v-model.number="form.sort_order" type="number" min="0" class="input" />
                    </FormField>
                    <FormField label="Status" :error="form.errors.is_active">
                        <label class="mt-2 inline-flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600" />
                            Active
                        </label>
                    </FormField>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <button type="button" class="btn-secondary" @click="showModal = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : (editing ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="showDelete"
            :title="`Delete ${toDelete?.label}?`"
            message="This numbering rule will be removed. Existing asset tags are unaffected."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
