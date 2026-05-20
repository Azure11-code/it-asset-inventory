<script setup>
import { computed } from 'vue';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({
    asset: { type: Object, required: true },
});

const qrValue = computed(() => {
    const a = props.asset;
    const lines = [
        `Asset Tag: ${a.asset_tag}`,
        `Description: ${(a.category?.name || a.description || '—').toUpperCase()}`,
        `S/N: ${a.serial_number || '—'}`,
        `Purchased: ${a.purchase_date || '—'}`,
        `Deployed: ${a.deployment_date || '—'}`,
        `Department: ${a.current_holder?.department || '—'}`,
        `Location: ${a.current_location?.name || '—'}`,
    ];
    return lines.join('\n');
});
</script>

<template>
    <div class="asset-tag">
        <div class="asset-tag__header">
            <img src="/arvin-logo.png" alt="Arvin International" class="asset-tag__logo" crossorigin="anonymous" />
            <span class="asset-tag__company">ARVIN INTERNATIONAL MARKETING INC.</span>
        </div>

        <div class="asset-tag__body">
            <div class="asset-tag__details">
                <p class="asset-tag__row">
                    <span class="lbl">DESCRIPTION:</span>
                    <span class="val">{{ (asset.category?.name || asset.description || '—').toUpperCase() }}</span>
                </p>
                <p class="asset-tag__row">
                    <span class="lbl">ASSET CODE:</span>
                    <span class="val">{{ asset.asset_tag }}</span>
                </p>
                <p class="asset-tag__row">
                    <span class="lbl">S/N:</span>
                    <span class="val">{{ asset.serial_number || '—' }}</span>
                </p>
                <p class="asset-tag__row">
                    <span class="lbl">PURCHASED DATE:</span>
                    <span class="val">{{ asset.purchase_date || '—' }}</span>
                </p>
                <p class="asset-tag__row">
                    <span class="lbl">DEPLOYED DATE:</span>
                    <span class="val">{{ asset.deployment_date || '—' }}</span>
                </p>
                <p class="asset-tag__row">
                    <span class="lbl">DEPARTMENT:</span>
                    <span class="val">{{ asset.current_holder?.department || '—' }}</span>
                </p>
                <p class="asset-tag__row">
                    <span class="lbl">LOCATION:</span>
                    <span class="val">{{ asset.current_location?.name || '—' }}</span>
                </p>
            </div>

            <div class="asset-tag__qr">
                <QrcodeVue :value="qrValue" :size="180" level="M" :margin="0" />
            </div>
        </div>
    </div>
</template>

<style>
/* Natural rendering at 720px wide → with html2canvas scale 3 = 2160px output PNG. */
.asset-tag {
    width: 720px;
    box-sizing: border-box;
    padding: 16px;
    border: 2px solid #111827;
    background: #ffffff;
    color: #111827;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
}

.asset-tag__header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 10px;
    margin-bottom: 10px;
    border-bottom: 2px solid #111827;
}
.asset-tag__logo {
    height: 56px;
    width: auto;
    object-fit: contain;
    flex-shrink: 0;
}
.asset-tag__company {
    font-size: 16px;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: 0.2px;
}

.asset-tag__body {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

.asset-tag__details {
    flex: 1;
    min-width: 0;
}
.asset-tag__row {
    font-size: 14px;
    line-height: 22px;
    height: 22px;
    margin: 0 0 4px 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.asset-tag__row .lbl {
    font-weight: 700;
}
.asset-tag__row .val {
    font-weight: 500;
    margin-left: 4px;
}

.asset-tag__qr {
    flex-shrink: 0;
    padding: 4px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
}
</style>
