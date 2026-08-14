<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import html2canvas from 'html2canvas';
import {
    ChevronLeftIcon, PencilSquareIcon, TrashIcon, ArrowDownTrayIcon, PrinterIcon,
    DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({ permit: Object });

const statusTone = {
    draft:     'slate',
    approved:  'emerald',
    returned:  'sky',
    cancelled: 'rose',
};

const fmtDate = (s) => {
    if (!s) return '';
    const d = new Date(s);
    return d.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: '2-digit' }).replace(',', '');
};

const downloading = ref(false);
const downloadPng = async () => {
    const el = document.querySelector('.permit-doc');
    if (!el) return;
    downloading.value = true;
    try {
        await document.fonts?.ready;
        const canvas = await html2canvas(el, { scale: 2.5, backgroundColor: '#ffffff', useCORS: true });
        const link = document.createElement('a');
        link.download = `${props.permit.permit_no}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } finally {
        downloading.value = false;
    }
};

const printDoc = () => window.print();

const showDelete = ref(false);
const doDelete = () => router.delete(`/permits/${props.permit.id}`);
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :title="permit.permit_no" :subtitle="permit.purpose">
                <template #actions>
                    <Link href="/permits" class="btn-secondary"><ChevronLeftIcon class="h-4 w-4" /> Back</Link>
                    <button v-if="can('permits', 'print')" class="btn-secondary" @click="printDoc"><PrinterIcon class="h-4 w-4" /> Print</button>
                    <button v-if="can('permits', 'print')" class="btn-secondary" :disabled="downloading" @click="downloadPng">
                        <ArrowDownTrayIcon class="h-4 w-4" /> {{ downloading ? 'Preparing…' : 'Download PNG' }}
                    </button>
                    <a v-if="can('permits', 'print')" :href="`/permits/${permit.id}/docx`" class="btn-secondary">
                        <DocumentArrowDownIcon class="h-4 w-4" /> Download Word
                    </a>
                    <Link v-if="can('permits', 'edit')" :href="`/permits/${permit.id}/edit`" class="btn-secondary">
                        <PencilSquareIcon class="h-4 w-4" /> Edit
                    </Link>
                    <button v-if="can('permits', 'delete')" class="btn-danger" @click="showDelete = true">
                        <TrashIcon class="h-4 w-4" /> Delete
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="flex items-center gap-3 mb-4 print:hidden">
            <Badge :tone="statusTone[permit.status] || 'slate'" dot>{{ permit.status }}</Badge>
            <span class="text-sm text-slate-500">
                Valid {{ fmtDate(permit.valid_from) }} → {{ fmtDate(permit.valid_to) }}
            </span>
        </div>

        <!-- ─────────── Printable document ─────────── -->
        <div class="permit-wrap print-area">
            <div class="permit-doc">
                <!-- Header -->
                <header class="permit-header">
                    <img src="/arvin-logo.png" alt="Arvin" class="permit-logo" crossorigin="anonymous" />
                    <div class="permit-company">
                        <div class="permit-company-name">Arvin International Marketing Inc.</div>
                        <div class="permit-company-addr">
                            15th Flr Unit B, A-Place Building Coral Way St., Macapagal Avenue, Pasay City
                        </div>
                        <div class="permit-company-tel">Tel No.: 843-3676 to 80</div>
                    </div>
                    <div class="permit-ref">
                        <div class="permit-ref-label">Permit&nbsp;No.</div>
                        <div class="permit-ref-value">{{ permit.permit_no }}</div>
                    </div>
                </header>

                <div class="permit-title-bar">
                    <span class="permit-title">PERMIT TO BRING ASSET</span>
                </div>

                <!-- Requester block -->
                <div class="permit-meta">
                    <div class="row">
                        <div class="cell"><span class="lbl">Name</span><span class="val name">{{ permit.employee?.name || '—' }}</span></div>
                        <div class="cell"><span class="lbl">Date Borrow</span><span class="val">{{ fmtDate(permit.date_borrow) }}</span></div>
                    </div>
                    <div class="row">
                        <div class="cell"><span class="lbl">Position</span><span class="val">{{ permit.employee?.position || '—' }}</span></div>
                        <div class="cell"><span class="lbl">Date Return</span><span class="val">{{ fmtDate(permit.date_return) }}</span></div>
                    </div>
                    <div class="row">
                        <div class="cell"><span class="lbl">Department</span><span class="val">{{ permit.employee?.department || '—' }}</span></div>
                        <div class="cell"><span class="lbl">Purpose</span><span class="val">{{ permit.purpose }}</span></div>
                    </div>
                    <div class="row">
                        <div class="cell"><span class="lbl">Destination</span><span class="val">{{ permit.destination }}</span></div>
                        <div class="cell"><span class="lbl">Valid On</span><span class="val">{{ fmtDate(permit.valid_from) }} — {{ fmtDate(permit.valid_to) }}</span></div>
                    </div>
                </div>

                <div class="permit-section-label">Items</div>

                <!-- Items -->
                <table class="permit-items">
                    <thead>
                        <tr>
                            <th class="w-qty">QTY</th>
                            <th class="w-unit">UNIT</th>
                            <th>DESCRIPTION</th>
                            <th class="w-serial">SERIAL NO.</th>
                            <th>REMARKS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in permit.items" :key="i">
                            <td class="center">{{ item.qty }}</td>
                            <td class="center">{{ item.unit }}</td>
                            <td class="center">{{ item.description }}</td>
                            <td class="center">{{ item.serial_no || 'N/A' }}</td>
                            <td class="center">{{ item.remarks || 'N/A' }}</td>
                        </tr>
                        <tr v-for="n in Math.max(0, 4 - permit.items.length)" :key="`pad-${n}`" class="pad">
                            <td>&nbsp;</td><td></td><td></td><td></td><td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="permit-section-label">Signatures</div>

                <!-- Signatures -->
                <div class="permit-sigs">
                    <div class="sig-cell">
                        <div class="sig-line">{{ permit.requested_by || permit.employee?.name || '' }}</div>
                        <div class="sig-label">Requested By</div>
                    </div>
                    <div class="sig-cell">
                        <div class="sig-line">{{ permit.issued_by || '' }}</div>
                        <div class="sig-label">Issued By</div>
                    </div>
                    <div class="sig-cell">
                        <div class="sig-line">
                            {{ permit.noted_by || '' }}<template v-if="permit.noted_by && permit.noted_by_secondary"> &amp; </template>{{ permit.noted_by_secondary || '' }}
                        </div>
                        <div class="sig-label">Noted By</div>
                        <div class="sig-role">IT Head &amp; Supervisor</div>
                    </div>
                    <div class="sig-cell">
                        <div v-if="permit.approval_note" class="sig-line approval-note">{{ permit.approval_note }}</div>
                        <div v-else class="sig-line">{{ permit.approved_by || '' }}</div>
                        <div class="sig-label">Approved By</div>
                        <div class="sig-role">Manager / COO / CFO / CEO</div>
                    </div>
                </div>

                <div class="permit-footer">
                    HELD LIABLE FOR ANY COSTS THAT WILL BE INCURRED
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="showDelete"
            :title="`Delete ${permit.permit_no}?`"
            message="This permit and its items will be removed permanently."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>

<style scoped>
.permit-wrap {
    display: flex; justify-content: center;
    padding: 1rem 0.5rem;
}

.permit-doc {
    --brand:        #1d4ed8;
    --brand-soft:   #eff6ff;
    --ink:          #0f172a;
    --ink-soft:     #334155;
    --line:         #cbd5e1;
    --line-strong:  #94a3b8;

    background: #ffffff;
    color: var(--ink);
    width: 8.5in;
    min-height: 11in;
    font-family: 'Poppins', 'Inter', ui-sans-serif, system-ui, sans-serif;
    font-size: 12.5px;
    line-height: 1.5;
    border: 1px solid var(--line-strong);
    border-radius: 4px;
    box-shadow: 0 4px 18px -8px rgba(15, 23, 42, 0.18);
    overflow: hidden;
}

/* ── Header ── */
.permit-header {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 16px;
    padding: 16px 24px;
    border-bottom: 1px solid var(--line);
}
.permit-logo { height: 64px; width: auto; object-fit: contain; }
.permit-company-name {
    font-size: 18px; font-weight: 700; letter-spacing: -0.005em;
    color: var(--ink);
}
.permit-company-addr,
.permit-company-tel {
    font-size: 11.5px; color: var(--ink-soft); line-height: 1.4;
}
.permit-company-tel { margin-top: 2px; }

.permit-ref {
    text-align: right;
    border-left: 3px solid var(--brand);
    padding-left: 12px;
}
.permit-ref-label {
    font-size: 9.5px; letter-spacing: 0.14em;
    text-transform: uppercase; color: var(--ink-soft); font-weight: 600;
}
.permit-ref-value {
    font-size: 14px; font-weight: 700; color: var(--brand); margin-top: 2px;
    letter-spacing: 0.02em;
}

/* ── Title bar ── */
.permit-title-bar {
    background: var(--brand);
    text-align: center;
    padding: 8px 0;
}
.permit-title {
    color: #ffffff;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.32em;
    text-transform: uppercase;
}

/* ── Meta block ── */
.permit-meta {
    padding: 12px 24px;
    border-bottom: 1px solid var(--line);
}
.permit-meta .row {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 24px;
    padding: 6px 0;
    border-bottom: 1px dashed var(--line);
}
.permit-meta .row:last-child { border-bottom: none; }
.permit-meta .cell {
    display: grid;
    grid-template-columns: 110px 1fr;
    align-items: baseline;
    gap: 8px;
}
.permit-meta .lbl {
    font-size: 10px; letter-spacing: 0.12em;
    text-transform: uppercase; color: var(--ink-soft); font-weight: 600;
}
.permit-meta .val {
    font-size: 13px; color: var(--ink); font-weight: 500;
    word-break: break-word;
}
.permit-meta .val.name { font-weight: 700; }

/* ── Section labels ── */
.permit-section-label {
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 10px; font-weight: 700;
    letter-spacing: 0.18em; text-transform: uppercase;
    padding: 6px 24px;
    border-top: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
}

/* ── Items table ── */
.permit-items {
    width: 100%; border-collapse: collapse;
}
.permit-items th {
    background: #f8fafc;
    color: var(--ink-soft);
    font-weight: 600;
    text-align: center;
    font-size: 10px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 8px 10px;
    border-bottom: 1px solid var(--line);
}
.permit-items td {
    padding: 9px 10px;
    border-bottom: 1px solid var(--line);
    font-size: 12.5px;
    color: var(--ink);
    vertical-align: middle;
}
.permit-items tbody tr:nth-child(even):not(.pad) td { background: #fcfcfd; }
.permit-items tr:last-child td { border-bottom: none; }
.permit-items td.center { text-align: center; }
.permit-items .w-qty    { width: 9%; }
.permit-items .w-unit   { width: 10%; }
.permit-items .w-serial { width: 20%; }
.permit-items .pad td { height: 28px; color: transparent; }

/* ── Signatures ── */
.permit-sigs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
}
.permit-sigs .sig-cell {
    padding: 28px 24px 14px;
    border-right: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
    text-align: center;
}
.permit-sigs .sig-cell:nth-child(2n) { border-right: none; }
.permit-sigs .sig-cell:nth-last-child(-n+2) { border-bottom: none; }

.permit-sigs .sig-line {
    font-weight: 700; font-size: 13px;
    border-bottom: 1px solid var(--ink);
    padding-bottom: 4px; min-height: 22px;
    margin: 0 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.permit-sigs .sig-line.approval-note {
    font-style: italic; font-weight: 500;
    text-transform: none;
    font-size: 11.5px;
    letter-spacing: 0;
}
.permit-sigs .sig-label {
    margin-top: 6px;
    font-size: 10px; letter-spacing: 0.16em;
    text-transform: uppercase; color: var(--ink-soft); font-weight: 600;
}
.permit-sigs .sig-role {
    margin-top: 2px;
    font-size: 10.5px; color: var(--ink-soft); font-style: italic;
}

/* ── Footer ── */
.permit-footer {
    text-align: center;
    font-style: italic;
    font-size: 11px;
    color: var(--ink-soft);
    padding: 10px 24px;
    border-top: 1px solid var(--line);
    letter-spacing: 0.08em;
    background: #fafbfc;
}

/* ── Print: monochrome with strong borders ── */
@media print {
    @page { size: 8.5in 11in; margin: 10mm; }

    /* Force monochrome ink everywhere in the doc */
    .permit-doc,
    .permit-doc *,
    .permit-doc *::before,
    .permit-doc *::after {
        color: #000 !important;
        background: transparent !important;
        box-shadow: none !important;
        text-shadow: none !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .permit-wrap { padding: 0; }
    .permit-doc  {
        width: 100%;
        min-height: 0;
        font-size: 11px;
        border: none !important;
        border-radius: 0;
        page-break-inside: avoid;
    }

    /* Header */
    .permit-header        { padding: 10px 16px; border-bottom: 1.5px solid #000 !important; }
    .permit-logo          { height: 48px; filter: grayscale(100%) contrast(1.2); }
    .permit-company-name  { font-size: 15px; font-weight: 700; }
    .permit-ref           { border-left: 2px solid #000 !important; }
    .permit-ref-value     { font-weight: 700; }

    /* Title bar — black border, no fill, bold uppercase */
    .permit-title-bar     {
        border-top: 1.5px solid #000 !important;
        border-bottom: 1.5px solid #000 !important;
        background: #fff !important;
        padding: 6px 0;
    }
    .permit-title         { color: #000 !important; font-size: 13px; letter-spacing: 0.28em; }

    /* Meta */
    .permit-meta          { padding: 8px 16px; border-bottom: 1.5px solid #000 !important; }
    .permit-meta .row     { border-bottom: 1px solid #000 !important; padding: 5px 0; }
    .permit-meta .row:last-child { border-bottom: none !important; }
    .permit-meta .val     { font-size: 11px; font-weight: 500; }
    .permit-meta .val.name{ font-weight: 700; }

    /* Section labels */
    .permit-section-label {
        padding: 4px 16px;
        border-top: 1.5px solid #000 !important;
        border-bottom: 1.5px solid #000 !important;
        font-weight: 700;
    }

    /* Items table */
    .permit-items         { border: none; }
    .permit-items th,
    .permit-items td      {
        padding: 6px 8px; font-size: 10.5px;
        border: 1px solid #000 !important;
    }
    .permit-items th      { font-weight: 700; }
    .permit-items tbody tr:nth-child(even):not(.pad) td { background: #fff !important; }

    /* Signatures */
    .permit-sigs .sig-cell {
        padding: 22px 14px 10px;
        border-right: 1px solid #000 !important;
        border-bottom: 1px solid #000 !important;
    }
    .permit-sigs .sig-cell:nth-child(2n)        { border-right: none !important; }
    .permit-sigs .sig-cell:nth-last-child(-n+2) { border-bottom: none !important; }
    .permit-sigs .sig-line {
        font-size: 11px; font-weight: 700;
        border-bottom: 1.5px solid #000 !important;
    }

    /* Footer */
    .permit-footer {
        padding: 6px 16px; font-size: 9.5px;
        border-top: 1.5px solid #000 !important;
    }
}
</style>
