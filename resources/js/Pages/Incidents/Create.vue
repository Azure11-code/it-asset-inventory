<script setup>
import { computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Combobox from '@/Components/Combobox.vue';
import { ExclamationTriangleIcon, ChevronLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    report:   Object,
    lookups:  Object,
    defaults: Object,
});

const isEdit = computed(() => !!props.report);

const STATUSES = [
    { value: 'draft',     label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'approved',  label: 'Approved' },
    { value: 'closed',    label: 'Closed' },
];

const form = useForm({
    ir_no:                       props.report?.ir_no       ?? props.defaults?.ir_no ?? '',
    report_date:                 props.report?.report_date ?? props.defaults?.report_date ?? '',
    asset_id:                    props.report?.asset_id ?? null,
    end_user_employee_id:        props.report?.end_user_employee_id ?? null,
    end_user_name:               props.report?.end_user_name ?? '',
    reported_problem:            props.report?.reported_problem ?? '',
    action_taken:                props.report?.action_taken ?? '',
    findings:                    props.report?.findings ?? '',
    recommendation:              props.report?.recommendation ?? '',
    prepared_by_user_id:         props.report?.prepared_by_user_id ?? null,
    noted_by_user_id:            props.report?.noted_by_user_id ?? null,
    noted_by_secondary_user_id:  props.report?.noted_by_secondary_user_id ?? null,
    approved_by_user_id:         props.report?.approved_by_user_id ?? null,
    status:                      props.report?.status ?? 'draft',
});

const submit = () => {
    if (isEdit.value) form.put(`/incidents/${props.report.id}`);
    else form.post('/incidents');
};
const cancel = () => router.visit(isEdit.value ? `/incidents/${props.report.id}` : '/incidents');
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader
                :title="isEdit ? `Edit ${form.ir_no}` : 'New Incident Report'"
                subtitle="Documents the defect and recommends corrective action."
            >
                <template #actions>
                    <Link href="/incidents" class="btn-secondary">
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
                        <h2 class="card-title">Basic Info</h2>
                        <p class="card-subtitle">Identification and the affected device.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="IR #" :error="form.errors.ir_no" required>
                        <input v-model="form.ir_no" type="text" class="input" />
                    </FormField>
                    <FormField label="Report Date" :error="form.errors.report_date" required>
                        <input v-model="form.report_date" type="date" class="input" />
                    </FormField>
                    <FormField label="Status" :error="form.errors.status" required>
                        <Combobox v-model="form.status" :options="STATUSES" value-key="value" label-key="label" :nullable="false" />
                    </FormField>
                    <FormField label="Affected Asset" :error="form.errors.asset_id" class="sm:col-span-2 lg:col-span-2">
                        <Combobox v-model="form.asset_id" :options="lookups.assets" placeholder="Search asset…" />
                        <p class="help">Pick an asset so its specs and serial appear in the printable IR.</p>
                    </FormField>
                    <FormField label="End User" :error="form.errors.end_user_employee_id || form.errors.end_user_name" required>
                        <Combobox
                            :model-value="form.end_user_employee_id"
                            @update:model-value="(v) => { form.end_user_employee_id = v; if (v) form.end_user_name = ''; }"
                            :custom-text="form.end_user_name"
                            @update:custom-text="(v) => { form.end_user_name = v; }"
                            :options="lookups.employees"
                            allow-custom
                            placeholder="Search or type a name…"
                        />
                    </FormField>
                </div>
            </section>

            <!-- Problem -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Problem & Investigation</h2>
                        <p class="card-subtitle">What was reported, what was done, what was found.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5">
                    <FormField label="Reported Problem" :error="form.errors.reported_problem" required>
                        <input v-model="form.reported_problem" type="text" class="input" placeholder="e.g. CORRUPTED SSD" />
                    </FormField>
                    <FormField label="Action Taken" :error="form.errors.action_taken" required>
                        <textarea v-model="form.action_taken" rows="5" class="input" placeholder="One step per line — each line becomes a bullet"></textarea>
                        <p class="help">Each line is rendered as a bullet on the printout.</p>
                    </FormField>
                    <FormField label="Findings / Possible Cause" :error="form.errors.findings" required>
                        <textarea v-model="form.findings" rows="3" class="input" placeholder="One finding per line"></textarea>
                    </FormField>
                    <FormField label="Recommendation" :error="form.errors.recommendation" required>
                        <textarea v-model="form.recommendation" rows="4" class="input" placeholder="One recommendation per line"></textarea>
                    </FormField>
                </div>
            </section>

            <!-- Signatures -->
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Signatures</h2>
                        <p class="card-subtitle">Who prepared, noted, and approved this IR.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <FormField label="Prepared By" :error="form.errors.prepared_by_user_id">
                        <Combobox v-model="form.prepared_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Noted By — IT MIS Head" :error="form.errors.noted_by_user_id">
                        <Combobox v-model="form.noted_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Noted By — IT Technical Head" :error="form.errors.noted_by_secondary_user_id">
                        <Combobox v-model="form.noted_by_secondary_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                    <FormField label="Approved By (Executive)" :error="form.errors.approved_by_user_id">
                        <Combobox v-model="form.approved_by_user_id" :options="lookups.users" placeholder="Search user…" />
                    </FormField>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="cancel">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <ExclamationTriangleIcon class="h-4 w-4" />
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Update IR' : 'Create IR') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
