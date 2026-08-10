<script setup>
import { computed, ref, onMounted } from 'vue';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({
    asset: { type: Object, required: true },
});

// URL encoded in the QR — resolves to the scan-result page when scanned.
const qrValue = computed(() => {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    return `${origin}/scan/lookup?tag=${encodeURIComponent(props.asset.asset_tag || '')}`;
});

// N/A when brand-new — never issued yet or first issuance.
const transferredDate = computed(() => {
    const a = props.asset;
    if (!a.current_holder) return 'N/A';
    if (a.is_first_issuance) return 'N/A';
    return a.assigned_since || 'N/A';
});

const rows = computed(() => [
    { label: 'ASSET TYPE',       value: (props.asset.category?.name || props.asset.description || 'N/A').toUpperCase() },
    { label: 'ASSET CODE',       value: props.asset.asset_tag || 'N/A' },
    { label: 'SERIAL NUMBER',    value: props.asset.serial_number || 'N/A' },
    { label: 'PURCHASED DATE',   value: props.asset.purchase_date || 'N/A' },
    { label: 'DEPLOYED DATE',    value: props.asset.deployment_date || 'N/A' },
    { label: 'TRANSFERRED DATE', value: transferredDate.value },
]);

// Preload logo as a base64 data URI so the serialized SVG is fully self-contained
// (needed so the rasterized canvas doesn't hit tainted-image issues on download).
const logoDataUri = ref('');
onMounted(async () => {
    try {
        const res = await fetch('/arvin-logo.png');
        const blob = await res.blob();
        logoDataUri.value = await new Promise((resolve) => {
            const r = new FileReader();
            r.onloadend = () => resolve(r.result);
            r.readAsDataURL(blob);
        });
    } catch (e) { /* logo just won't show */ }
});

const svgRoot = ref(null);

// Public: rasterize the SVG to a PNG and trigger download.
const download = async (filename) => {
    if (!svgRoot.value) return;
    const clone = svgRoot.value.cloneNode(true);
    if (!clone.getAttribute('xmlns')) clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    const svgString = new XMLSerializer().serializeToString(clone);
    const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    try {
        const img = new Image();
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = url;
        });
        const scale = 3;
        const canvas = document.createElement('canvas');
        canvas.width  = 600 * scale;
        canvas.height = 340 * scale;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        const link = document.createElement('a');
        link.download = filename || `asset-tag-${props.asset.asset_tag || 'tag'}.png`;
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
    } finally {
        URL.revokeObjectURL(url);
    }
};

defineExpose({ download });
</script>

<template>
    <div class="asset-tag-wrap">
        <svg
            ref="svgRoot"
            class="asset-tag"
            xmlns="http://www.w3.org/2000/svg"
            width="600"
            height="340"
            viewBox="0 0 600 340"
            preserveAspectRatio="xMidYMid meet"
            font-family="Arial, Helvetica, sans-serif"
        >
            <!-- Outer frame -->
            <rect x="1.5" y="1.5" width="597" height="337" fill="#ffffff" stroke="#111827" stroke-width="3"/>

            <!-- Header: logo + company name -->
            <image
                v-if="logoDataUri"
                :href="logoDataUri"
                x="18" y="16"
                width="60" height="56"
                preserveAspectRatio="xMidYMid meet"
            />
            <text x="86" y="52" font-size="17" font-weight="700" fill="#111827">
                ARVIN INTERNATIONAL MARKETING INC.
            </text>

            <!-- Divider -->
            <line x1="18" y1="86" x2="582" y2="86" stroke="#111827" stroke-width="2"/>

            <!-- Info rows: label + value; value column starts after longest label -->
            <g fill="#111827">
                <template v-for="(row, i) in rows" :key="row.label">
                    <text :x="18"  :y="116 + i * 32" font-size="15" font-weight="700">{{ row.label }}:</text>
                    <text :x="200" :y="116 + i * 32" font-size="15" font-weight="500">{{ row.value }}</text>
                </template>
            </g>

            <!-- QR area (right column, hugs info) -->
            <g transform="translate(398, 96)">
                <rect x="0" y="0" width="184" height="184" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
                <g transform="translate(6, 6)">
                    <QrcodeVue :value="qrValue" :size="172" level="M" :margin="0" render-as="svg" />
                </g>
            </g>
        </svg>
    </div>
</template>

<style>
.asset-tag-wrap {
    display: block;
    width: 100%;
    max-width: 600px;
    background: #ffffff;
}
.asset-tag {
    display: block;
    width: 100%;
    height: auto;
}
</style>
