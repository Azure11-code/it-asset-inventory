<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AssetForm from '@/Components/AssetForm.vue';

const props = defineProps({ lookups: Object, defaults: Object });

const form = useForm({
    asset_tag: '',
    serial_number: '',
    model: '',
    description: '',
    specifications: [],
    brand_id: '',
    category_id: '',
    purchase_date: props.defaults?.purchase_date ?? '',
    deployment_date: '',
    purchase_cost: '',
    vendor: '',
    expected_lifespan_years: props.defaults?.expected_lifespan_years ?? 5,
    warranty_until: '',
    condition_id: '',
    current_status: props.defaults?.current_status ?? 'in_stock',
    current_holder_id: '',
    current_location_id: '',
    department_id: null,
    notes: '',
});

const submit = () => form.post('/assets');
const cancel = () => router.visit('/assets');
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="New Asset" subtitle="Register a physical IT peripheral." />
        </template>
        <AssetForm :form="form" :lookups="lookups" :submitting="form.processing" @submit="submit" @cancel="cancel" />
    </AppLayout>
</template>
