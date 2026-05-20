<script setup>
import { computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Combobox from '@/Components/Combobox.vue';
import { ArchiveBoxArrowDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ lookups: Object, defaults: Object });

const form = useForm({
    category_id: '',
    brand_id: '',
    model: '',
    description: '',
    purchase_date: props.defaults?.purchase_date ?? '',
    purchase_cost: '',
    expected_lifespan_years: props.defaults?.expected_lifespan_years ?? 5,
    condition_id: '',
    current_location_id: '',
    tag_prefix: '',
    start_number: props.defaults?.start_number ?? 1,
    quantity: props.defaults?.quantity ?? 1,
    notes: '',
});

// When category changes, fill the tag prefix with the category's prefix (e.g. "LPT-")
const selectedCategory = computed(() =>
    props.lookups.categories.find((c) => c.id === Number(form.category_id))
);
watch(selectedCategory, (cat) => {
    if (cat?.prefix && !form.tag_prefix) {
        form.tag_prefix = cat.prefix + '-';
    }
});

const preview = computed(() => {
    if (!form.tag_prefix || !form.quantity) return [];
    const tags = [];
    const max = Math.min(form.quantity, 5);
    for (let i = 0; i < max; i++) {
        const n = Number(form.start_number) + i;
        tags.push(form.tag_prefix + String(n).padStart(3, '0'));
    }
    if (form.quantity > 5) tags.push(`… and ${form.quantity - 5} more`);
    return tags;
});

const submit = () => form.post('/assets/bulk-receive');
const cancel = () => router.visit('/assets');
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader
                title="Bulk Receive Assets"
                subtitle="Create multiple assets at once — same category and properties, auto-numbered tags."
            />
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Shared properties</h2>
                        <p class="card-subtitle">These apply to every asset created in this batch.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="Category" :error="form.errors.category_id" required>
                        <Combobox v-model="form.category_id" :options="lookups.categories" placeholder="Search category…" null-label="— Select category —" />
                    </FormField>
                    <FormField label="Brand" :error="form.errors.brand_id">
                        <Combobox v-model="form.brand_id" :options="lookups.brands" placeholder="Search brand…" />
                    </FormField>
                    <FormField label="Model" :error="form.errors.model">
                        <input v-model="form.model" type="text" class="input" />
                    </FormField>
                    <FormField label="Description" :error="form.errors.description" class="sm:col-span-2 lg:col-span-3">
                        <input v-model="form.description" type="text" class="input" />
                    </FormField>
                    <FormField label="Purchase Date" :error="form.errors.purchase_date">
                        <input v-model="form.purchase_date" type="date" class="input" />
                    </FormField>
                    <FormField label="Purchase Cost (per item)" :error="form.errors.purchase_cost">
                        <input v-model.number="form.purchase_cost" type="number" step="0.01" min="0" class="input" />
                    </FormField>
                    <FormField label="Lifespan (years)" :error="form.errors.expected_lifespan_years" required>
                        <input v-model.number="form.expected_lifespan_years" type="number" min="1" max="30" class="input" />
                    </FormField>
                    <FormField label="Condition" :error="form.errors.condition_id">
                        <Combobox v-model="form.condition_id" :options="lookups.conditions" placeholder="Search condition…" />
                    </FormField>
                    <FormField label="Store at Location" :error="form.errors.current_location_id">
                        <Combobox v-model="form.current_location_id" :options="lookups.locations" placeholder="Search location…" />
                    </FormField>
                </div>
            </section>

            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Tagging</h2>
                        <p class="card-subtitle">Auto-generate sequential asset tags for this batch.</p>
                    </div>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    <FormField label="Tag Prefix" :error="form.errors.tag_prefix" required>
                        <input v-model="form.tag_prefix" type="text" class="input" placeholder="LPT-" />
                        <p class="help">Auto-filled from category if blank.</p>
                    </FormField>
                    <FormField label="Start Number" :error="form.errors.start_number" required>
                        <input v-model.number="form.start_number" type="number" min="0" class="input" />
                    </FormField>
                    <FormField label="Quantity" :error="form.errors.quantity" required>
                        <input v-model.number="form.quantity" type="number" min="1" max="500" class="input" />
                    </FormField>
                </div>
                <div v-if="preview.length" class="border-t border-slate-100 bg-slate-50 px-5 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Preview tags</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <span v-for="t in preview" :key="t" class="badge badge-brand">{{ t }}</span>
                    </div>
                </div>
            </section>

            <FormField label="Notes" :error="form.errors.notes">
                <textarea v-model="form.notes" rows="2" class="input"
                    placeholder="Supplier name, invoice #, PO #, anything useful for audit"></textarea>
            </FormField>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="cancel">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <ArchiveBoxArrowDownIcon class="h-4 w-4" />
                    {{ form.processing ? 'Creating...' : `Create ${form.quantity || 0} Asset${form.quantity === 1 ? '' : 's'}` }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
