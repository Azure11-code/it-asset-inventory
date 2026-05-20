<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AssetForm from '@/Components/AssetForm.vue';

const props = defineProps({ asset: Object, lookups: Object });

const form = useForm({
    asset_tag: props.asset.asset_tag,
    serial_number: props.asset.serial_number ?? '',
    model: props.asset.model ?? '',
    description: props.asset.description ?? '',
    specifications: Array.isArray(props.asset.specifications) ? props.asset.specifications : [],
    brand_id: props.asset.brand_id ?? '',
    category_id: props.asset.category_id ?? '',
    purchase_date: props.asset.purchase_date ?? '',
    deployment_date: props.asset.deployment_date ?? '',
    purchase_cost: props.asset.purchase_cost ?? '',
    expected_lifespan_years: props.asset.expected_lifespan_years ?? 5,
    warranty_until: props.asset.warranty_until ?? '',
    condition_id: props.asset.condition_id ?? '',
    current_status: props.asset.current_status,
    current_holder_id: props.asset.current_holder_id ?? '',
    current_location_id: props.asset.current_location_id ?? '',
    notes: props.asset.notes ?? '',
});

const submit = () => form.put(`/assets/${props.asset.id}`);
const cancel = () => router.visit(`/assets/${props.asset.id}`);
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :title="`Edit ${asset.asset_tag}`" subtitle="Update asset information." />
        </template>
        <AssetForm :form="form" :lookups="lookups" :submitting="form.processing" :is-edit="true" @submit="submit" @cancel="cancel" />
    </AppLayout>
</template>
