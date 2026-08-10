<script setup>
import { computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Combobox from '@/Components/Combobox.vue';
import { DocumentTextIcon, ChevronLeftIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    recommendation: Object,
    lookups: Object,
    defaults: Object,
});

const isEdit = computed(() => !!props.recommendation);

const STATUSES = [
    { value: 'draft',     label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'approved',  label: 'Approved' },
    { value: 'closed',    label: 'Closed' },
];

const blankSpec = () => ({ label: '', value: '', note: '' });

const form = useForm({
    doc_no:                props.recommendation?.doc_no      ?? props.defaults?.doc_no ?? '',
    report_date:           props.recommendation?.report_date ?? props.defaults?.report_date ?? '',
    asset_id:              props.recommendation?.asset_id ?? null,
    requestor_employee_id: props.recommendation?.requestor_employee_id ?? null,
    requestor_name:        props.recommendation?.requestor_name ?? '',
    requestor_employee_no: props.recommendation?.requestor_employee_no ?? '',
    requestor_position:    props.recommendation?.requestor_position ?? '',
    requestor_department:  props.recommendation?.requestor_department ?? '',
    thru:                  props.recommendation?.thru ?? props.defaults?.thru ?? 'Information Technology Department',
    subject:               props.recommendation?.subject ?? '',
    body:                  props.recommendation?.body ?? '',
    quick_specs:           (props.recommendation?.quick_specs?.length ? props.recommendation.quick_specs : [blankSpec()]).map((s) => ({ ...blankSpec(), ...s })),
    prepared_by_user_id:   props.recommendation?.prepared_by_user_id ?? null,
    reviewed_by_user_id:   props.recommendation?.reviewed_by_user_id ?? null,
    noted_by_user_id:      props.recommendation?.noted_by_user_id ?? null,
    status:                props.recommendation?.status ?? 'draft',
});

// When an employee is picked, autofill their employee_no/position/department
const onRequestorChange = (id) => {
    if (!id) return;
    const emp = props.lookups.employees.find((e) => e.id === Number(id));
    if (!emp) return;
    form.requestor_name = '';
    if (!form.requestor_employee_no) form.requestor_employee_no = emp.employee_no ?? '';
    if (!form.requestor_position)    form.requestor_position    = emp.position ?? '';
};

const isCustomRequestor = computed(() => !form.requestor_employee_id && !!form.requestor_name);

const addSpec = () => { form.quick_specs.push(blankSpec()); };
const removeSpec = (i) => {
    if (form.quick_specs.length === 1) { form.quick_specs.splice(0, 1, blankSpec()); return; }
    form.quick_specs.splice(i, 1);
};

const submit = () => {
    if (isEdit.value) form.put(`/recommendations/${props.recommendation.id}`);
    else form.post('/recommendations');
};
const cancel = () => router.visit(isEdit.value ? `/recommendations/${props.recommendation.id}` : '/recommendations');
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader
                :title="isEdit ? `Edit ${form.doc_no}` : 'New Recommendation'"
                subtitle="Request and recommendation memo for an upgrade or part change."
            >
                <template #actions>
                    <Link href="/recommendations" class="btn-secondary">
                        <ChevronLeftIcon class="h-4 w-4" /> Back
                    </Link>
                </template>
            </PageHeader>
        </template>

        <form @submit.prevent="submit" class="space-y-6">

            <!-- Basic -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Header</h2>
                        <p class="card-subtitle">Document number, date, status, subject.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="Doc #" :error="form.errors.doc_no" required>
                        <input v-model="form.doc_no" type="text" class="input" />
                    </FormField>
                    <FormField label="Date" :error="form.errors.report_date" required>
                        <input v-model="form.report_date" type="date" class="input" />
                    </FormField>
                    <FormField label="Status" :error="form.errors.status" required>
                        <Combobox v-model="form.status" :options="STATUSES" value-key="value" label-key="label" :nullable="false" />
                    </FormField>
                    <FormField label="Thru" :error="form.errors.thru" required>
                        <input v-model="form.thru" type="text" class="input" />
                    </FormField>
                    <FormField label="Subject" :error="form.errors.subject" required class="sm:col-span-2">
                        <input v-model="form.subject" type="text" class="input" placeholder="e.g. Request and Recommendation for RAM Upgrade" />
                    </FormField>
                </div>
            </section>

            <!-- Requestor -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Requestor</h2>
                        <p class="card-subtitle">Who is making the request.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="Name" :error="form.errors.requestor_employee_id || form.errors.requestor_name" required class="lg:col-span-2">
                        <Combobox
                            :model-value="form.requestor_employee_id"
                            @update:model-value="(v) => { form.requestor_employee_id = v; onRequestorChange(v); }"
                            :custom-text="form.requestor_name"
                            @update:custom-text="(v) => { form.requestor_name = v; }"
                            :options="lookups.employees"
                            allow-custom
                            placeholder="Search or type a name…"
                        />
                    </FormField>
                    <FormField label="Employee ID" :error="form.errors.requestor_employee_no">
                        <input v-model="form.requestor_employee_no" type="text" class="input" />
                    </FormField>
                    <FormField label="Position" :error="form.errors.requestor_position">
                        <input v-model="form.requestor_position" type="text" class="input" />
                    </FormField>
                    <FormField label="Department" :error="form.errors.requestor_department">
                        <input v-model="form.requestor_department" type="text" class="input" />
                    </FormField>
                </div>
            </section>

            <!-- Body -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Body</h2>
                        <p class="card-subtitle">Justification paragraph and current device assessment.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5">
                    <FormField label="Affected Asset" :error="form.errors.asset_id">
                        <Combobox v-model="form.asset_id" :options="lookups.assets" placeholder="Pick the asset under review…" />
                    </FormField>
                    <FormField label="Body / Justification" :error="form.errors.body" required>
                        <textarea v-model="form.body" rows="6" class="input" placeholder="Explain why the upgrade is needed and what you recommend…"></textarea>
                    </FormField>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="label !mb-0">Current Desktop — Quick Specs</label>
                            <button type="button" class="btn-secondary !py-1 !px-2 text-xs" @click="addSpec">
                                <PlusIcon class="h-3.5 w-3.5" /> Add row
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(spec, i) in form.quick_specs" :key="i" class="grid gap-2 sm:grid-cols-12">
                                <input v-model="spec.label" type="text" class="input sm:col-span-3" placeholder="e.g. Asset Code, PROCESSOR" />
                                <input v-model="spec.value" type="text" class="input sm:col-span-4" placeholder="e.g. Intel Core i5-10400" />
                                <input v-model="spec.note" type="text" class="input sm:col-span-4" placeholder="Optional italic note in quotes" />
                                <div class="sm:col-span-1 flex items-end">
                                    <button type="button" class="btn-ghost-danger w-full" @click="removeSpec(i)">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="help">Format mirrors the memo: <em>LABEL : VALUE — "note"</em>.</p>
                    </div>
                </div>
            </section>

            <!-- Signatures -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Signatures</h2>
                        <p class="card-subtitle">Prepared / Reviewed / Noted.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    <FormField label="Prepared By" :error="form.errors.prepared_by_user_id">
                        <Combobox v-model="form.prepared_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Review By" :error="form.errors.reviewed_by_user_id">
                        <Combobox v-model="form.reviewed_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Note By (Executive)" :error="form.errors.noted_by_user_id">
                        <Combobox v-model="form.noted_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="cancel">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <DocumentTextIcon class="h-4 w-4" />
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Update' : 'Create') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
