<script setup>
import { computed, ref, watch } from 'vue';
import FormField from '@/Components/FormField.vue';
import KeyValueList from '@/Components/KeyValueList.vue';
import Combobox from '@/Components/Combobox.vue';
import { SparklesIcon } from '@heroicons/vue/24/outline';

const ASSET_STATUSES = [
    { value: 'in_stock',   label: 'In Stock' },
    { value: 'assigned',   label: 'Assigned' },
    { value: 'for_repair', label: 'For Repair' },
    { value: 'defective',  label: 'Defective' },
    { value: 'retired',    label: 'Retired' },
    { value: 'replaced',   label: 'Replaced' },
];

const props = defineProps({
    form: { type: Object, required: true },
    lookups: { type: Object, required: true },
    submitting: { type: Boolean, default: false },
    isEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'cancel']);

// Format "Y years and M months and D days" between two ISO dates (or to today)
const yearsMonths = (start, end = null) => {
    if (!start) return null;
    const s = new Date(start);
    const e = end ? new Date(end) : new Date();
    if (isNaN(s) || isNaN(e)) return null;
    let from = s, to = e;
    if (s > e) [from, to] = [e, s];

    let years = to.getFullYear() - from.getFullYear();
    let months = to.getMonth() - from.getMonth();
    let days = to.getDate() - from.getDate();

    if (days < 0) {
        // borrow days from the previous month
        const prevMonth = new Date(to.getFullYear(), to.getMonth(), 0); // last day of prev month
        days += prevMonth.getDate();
        months -= 1;
    }
    if (months < 0) {
        years -= 1;
        months += 12;
    }

    if (years === 0 && months === 0 && days === 0) return 'today';
    const parts = [];
    if (years > 0)  parts.push(`${years} year${years   === 1 ? '' : 's'}`);
    if (months > 0) parts.push(`${months} month${months === 1 ? '' : 's'}`);
    if (days > 0)   parts.push(`${days} day${days     === 1 ? '' : 's'}`);
    return parts.join(' and ');
};

const purchaseAge = computed(() => yearsMonths(props.form.purchase_date));
const serviceAge  = computed(() => yearsMonths(props.form.deployment_date));

const specsItems  = computed(() => Array.isArray(props.form.specifications) ? props.form.specifications : []);
const specsFilled = computed(() => specsItems.value.length > 0);
const specsCount  = computed(() => specsItems.value.length);

// Auto-compute warranty_until when purchase_date or lifespan changes (only if user hasn't manually set one)
const computedWarranty = computed(() => {
    if (!props.form.purchase_date) return '';
    const years = Number(props.form.expected_lifespan_years || 5);
    const d = new Date(props.form.purchase_date);
    d.setFullYear(d.getFullYear() + years);
    return d.toISOString().slice(0, 10);
});

watch(
    () => [props.form.purchase_date, props.form.expected_lifespan_years],
    () => {
        // Only auto-fill if it's blank or still matches the previously-computed value
        if (!props.form.warranty_until || props.form.warranty_until === props._lastAutoWarranty) {
            props.form.warranty_until = computedWarranty.value;
            props._lastAutoWarranty = computedWarranty.value;
        }
    }
);

// ── Auto-tag from Asset Code Rule ──
const selectedRuleId = ref(null);
const rulePreview    = ref('');
const ruleLoading    = ref(false);
const ruleError      = ref('');

const applyRule = async (ruleId) => {
    if (!ruleId) { rulePreview.value = ''; return; }
    ruleLoading.value = true;
    ruleError.value = '';
    try {
        const res  = await fetch(`/asset-code-rules/${ruleId}/next`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (data.full || !data.formatted) {
            ruleError.value = 'Range is full — nothing to assign.';
            rulePreview.value = '';
            return;
        }
        rulePreview.value = data.formatted;
        props.form.asset_tag = data.formatted;
        // Auto-select the category if the rule has one AND the field is blank
        const rule = (props.lookups.code_rules || []).find(r => String(r.id) === String(ruleId));
        if (rule?.category_id && !props.form.category_id) {
            props.form.category_id = rule.category_id;
        }
    } catch (e) {
        ruleError.value = 'Could not fetch the next number.';
    } finally {
        ruleLoading.value = false;
    }
};

watch(selectedRuleId, (id) => applyRule(id));
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <section class="card">
            <header class="card-header">
                <div>
                    <h2 class="card-title">Asset Information</h2>
                    <p class="card-subtitle">Identifying details for this physical device.</p>
                </div>
            </header>
            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-if="!isEdit && (lookups.code_rules?.length)" label="Auto-generate from Rule" class="sm:col-span-2 lg:col-span-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="flex-1">
                            <Combobox v-model="selectedRuleId" :options="lookups.code_rules" placeholder="Pick a code rule (e.g. LAPTOP)" null-label="— No rule (type manually) —" />
                        </div>
                        <div v-if="rulePreview" class="inline-flex items-center gap-2 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-mono text-brand-800">
                            <SparklesIcon class="h-4 w-4 text-brand-600" /> Next: <strong>{{ rulePreview }}</strong>
                        </div>
                        <p v-if="ruleLoading" class="text-xs text-slate-500">Computing…</p>
                        <p v-if="ruleError" class="text-xs text-rose-600">{{ ruleError }}</p>
                    </div>
                    <p class="help">Optional. Picks the next available number sa loob ng rule range. You can still type the tag manually below.</p>
                </FormField>
                <FormField label="Asset Tag" :error="form.errors.asset_tag" required>
                    <input v-model="form.asset_tag" type="text" class="input" placeholder="e.g. LPT-001 or auto-generated" />
                </FormField>
                <FormField label="Serial Number" :error="form.errors.serial_number">
                    <input v-model="form.serial_number" type="text" class="input" />
                </FormField>
                <FormField label="Model" :error="form.errors.model">
                    <input v-model="form.model" type="text" class="input" />
                </FormField>
                <FormField label="Category" :error="form.errors.category_id">
                    <Combobox v-model="form.category_id" :options="lookups.categories" placeholder="Search category…" />
                </FormField>
                <FormField label="Brand" :error="form.errors.brand_id">
                    <Combobox v-model="form.brand_id" :options="lookups.brands" placeholder="Search brand…" />
                </FormField>
                <FormField label="Condition" :error="form.errors.condition_id">
                    <Combobox v-model="form.condition_id" :options="lookups.conditions" placeholder="Search condition…" />
                </FormField>
                <FormField label="Description" :error="form.errors.description" class="sm:col-span-2 lg:col-span-3">
                    <input v-model="form.description" type="text" class="input" />
                </FormField>

                <FormField :error="form.errors.specifications" class="sm:col-span-2 lg:col-span-3">
                    <div class="mb-1 flex items-center justify-between">
                        <label class="label !mb-0">Specifications</label>
                        <span :class="['badge', specsFilled ? 'badge-emerald' : 'badge-slate']">
                            <span :class="['badge-dot', specsFilled ? 'bg-emerald-500' : 'bg-slate-400']" />
                            {{ specsFilled ? `${specsCount} item${specsCount === 1 ? '' : 's'}` : 'Empty' }}
                        </span>
                    </div>
                    <KeyValueList v-model="form.specifications" />
                </FormField>
            </div>
        </section>

        <section class="card">
            <header class="card-header">
                <div>
                    <h2 class="card-title">Acquisition & Warranty</h2>
                    <p class="card-subtitle">Purchase info and 5-year service window.</p>
                </div>
            </header>
            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <FormField label="Purchase Date" :error="form.errors.purchase_date">
                    <input v-model="form.purchase_date" type="date" class="input" />
                </FormField>
                <FormField label="Deployment Date" :error="form.errors.deployment_date">
                    <input v-model="form.deployment_date" type="date" class="input" />
                    <p class="help">Auto-set when first issued; editable here.</p>
                </FormField>
                <FormField label="Purchase Cost" :error="form.errors.purchase_cost">
                    <input v-model.number="form.purchase_cost" type="number" step="0.01" min="0" class="input" />
                </FormField>
                <FormField label="Lifespan (years)" :error="form.errors.expected_lifespan_years" required>
                    <input v-model.number="form.expected_lifespan_years" type="number" min="1" max="30" class="input" />
                </FormField>
                <FormField label="Warranty Until" :error="form.errors.warranty_until" class="lg:col-span-2">
                    <input v-model="form.warranty_until" type="date" class="input" />
                </FormField>
                <FormField v-if="form.purchase_date" class="lg:col-span-2">
                    <label class="label">Age (since purchase)</label>
                    <p class="text-sm font-semibold text-slate-900">{{ purchaseAge || '—' }}</p>
                    <p v-if="form.deployment_date" class="mt-1 text-xs text-slate-500">
                        Service: <span class="font-medium text-slate-700">{{ serviceAge || '—' }}</span>
                    </p>
                </FormField>
            </div>
        </section>

        <section class="card">
            <header class="card-header">
                <div>
                    <h2 class="card-title">Status & Assignment</h2>
                    <p class="card-subtitle">Current state of this asset.</p>
                </div>
            </header>
            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <FormField label="Status" :error="form.errors.current_status" required>
                    <Combobox
                        v-model="form.current_status"
                        :options="ASSET_STATUSES"
                        value-key="value"
                        label-key="label"
                        :nullable="false"
                        placeholder="Status…"
                    />
                </FormField>
                <FormField label="Current Holder" :error="form.errors.current_holder_id">
                    <Combobox v-model="form.current_holder_id" :options="lookups.employees" placeholder="Search employee…" />
                </FormField>
                <FormField label="Current Location" :error="form.errors.current_location_id">
                    <Combobox v-model="form.current_location_id" :options="lookups.locations" placeholder="Search location…" />
                </FormField>
                <FormField label="Notes" :error="form.errors.notes" class="sm:col-span-2 lg:col-span-3">
                    <textarea v-model="form.notes" rows="2" class="input"></textarea>
                </FormField>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <button type="button" class="btn-secondary" @click="emit('cancel')">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="submitting">
                {{ submitting ? 'Saving...' : (isEdit ? 'Update Asset' : 'Create Asset') }}
            </button>
        </div>
    </form>
</template>
