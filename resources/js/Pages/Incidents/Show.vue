<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import {
    ChevronLeftIcon, PencilSquareIcon, TrashIcon, PrinterIcon, ArrowDownTrayIcon, CheckIcon,
    DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline';
import html2canvas from 'html2canvas';
import Attachments from '@/Components/Attachments.vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
    report:      Object,
    attachments: { type: Array, default: () => [] },
});

const statusTone = { draft: 'slate', submitted: 'sky', approved: 'emerald', closed: 'slate' };

const fmtDate = (s) => {
    if (!s) return '';
    const d = new Date(s);
    return d.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: '2-digit' });
};

const bullets = (text) =>
    String(text || '').split(/\r?\n/).map((s) => s.trim()).filter((s) => s.length);

const actionLines = computed(() => bullets(props.report.action_taken));
const findingsLines = computed(() => bullets(props.report.findings));
const recommendationLines = computed(() => bullets(props.report.recommendation));

const downloading = ref(false);
const downloadPng = async () => {
    const el = document.querySelector('.ir-doc');
    if (!el) return;
    downloading.value = true;
    try {
        await document.fonts?.ready;
        const canvas = await html2canvas(el, { scale: 2.5, backgroundColor: '#ffffff', useCORS: true });
        const link = document.createElement('a');
        link.download = `${props.report.ir_no}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } finally { downloading.value = false; }
};
const printDoc = () => window.print();

const showDelete = ref(false);
const doDelete = () => router.delete(`/incidents/${props.report.id}`);
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :title="report.ir_no" :subtitle="report.reported_problem">
                <template #actions>
                    <Link href="/incidents" class="btn-secondary"><ChevronLeftIcon class="h-4 w-4" /> Back</Link>
                    <button v-if="can('incidents', 'print')" class="btn-secondary" @click="printDoc"><PrinterIcon class="h-4 w-4" /> Print</button>
                    <button v-if="can('incidents', 'print')" class="btn-secondary" :disabled="downloading" @click="downloadPng">
                        <ArrowDownTrayIcon class="h-4 w-4" /> {{ downloading ? 'Preparing…' : 'Download PNG' }}
                    </button>
                    <a v-if="can('incidents', 'print')" :href="`/incidents/${report.id}/docx`" class="btn-secondary">
                        <DocumentArrowDownIcon class="h-4 w-4" /> Download Word
                    </a>
                    <Link v-if="can('incidents', 'edit')" :href="`/incidents/${report.id}/edit`" class="btn-secondary">
                        <PencilSquareIcon class="h-4 w-4" /> Edit
                    </Link>
                    <button v-if="can('incidents', 'delete')" class="btn-danger" @click="showDelete = true">
                        <TrashIcon class="h-4 w-4" /> Delete
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="flex items-center gap-3 mb-4 print:hidden">
            <Badge :tone="statusTone[report.status] || 'slate'" dot>{{ report.status }}</Badge>
            <span class="text-sm text-slate-500">Reported {{ fmtDate(report.report_date) }}</span>
        </div>

        <!-- ─────────── Attachments ─────────── -->
        <div class="mb-4 print:hidden">
            <Attachments
                entity="incidents"
                :entity-id="report.id"
                resource-key="incidents"
                :attachments="attachments"
                title="Supporting Documents"
                subtitle="Attach the signed scanned copy, photos of the defect, warranty paperwork, or related files."
            />
        </div>

        <!-- ─────────── Printable IR ─────────── -->
        <div class="ir-wrap print-area">
            <div class="ir-doc">

                <!-- Header -->
                <header class="ir-header">
                    <img src="/arvin-logo.png" alt="Arvin" class="ir-logo" crossorigin="anonymous" />
                    <div class="ir-company">
                        <div class="ir-company-name">Arvin International Marketing Inc.</div>
                        <div class="ir-company-addr">18<sup>th</sup> Floor, Y - Tower, Macapagal Avenue Corner Coral Way Street</div>
                        <div class="ir-company-addr">Pasay City, Metro Manila</div>
                    </div>
                </header>

                <!-- Meta -->
                <div class="ir-meta">
                    <div class="row"><span class="lbl">End User</span><span class="colon">:</span><span class="val">{{ report.end_user || '—' }}</span></div>
                    <div class="row"><span class="lbl">Reported Problem</span><span class="colon">:</span><span class="val">{{ report.reported_problem }}</span></div>
                </div>

                <!-- Action Taken -->
                <div class="ir-section-head">
                    <span class="ir-section-title">Action Taken:</span>
                    <span class="ir-rule"></span>
                </div>
                <p class="ir-prose">Conducted troubleshoot &amp; perform the following:</p>
                <ul class="ir-bullets check">
                    <li v-for="(line, i) in actionLines" :key="i">{{ line }}</li>
                </ul>

                <!-- Findings -->
                <div class="ir-section-head">
                    <span class="ir-section-title">Findings/Possible Cause:</span>
                    <span class="ir-rule"></span>
                </div>
                <ul class="ir-bullets">
                    <li v-for="(line, i) in findingsLines" :key="i">{{ line }}</li>
                </ul>

                <!-- Quick Specs from linked asset -->
                <div v-if="report.asset" class="ir-specs-wrap">
                    <div class="ir-specs-title">Computer Quick Specs &amp; History:</div>
                    <div class="ir-specs">
                        <div class="row" v-if="(report.asset.specifications ?? []).length">
                            <span class="lbl">Specification</span><span class="colon">:</span>
                            <span class="val">
                                <template v-for="(spec, i) in report.asset.specifications" :key="i">
                                    <template v-if="typeof spec === 'object' && spec">{{ spec.value }}<span v-if="i &lt; report.asset.specifications.length - 1"> | </span></template>
                                    <template v-else>{{ spec }}<span v-if="i &lt; report.asset.specifications.length - 1"> | </span></template>
                                </template>
                            </span>
                        </div>
                        <div class="row"><span class="lbl">Serial Number</span><span class="colon">:</span><span class="val">{{ report.asset.serial_number || '—' }}</span></div>
                        <div class="row"><span class="lbl">Purchase Date</span><span class="colon">:</span><span class="val">{{ report.asset.purchase_date || '—' }}</span></div>
                        <div class="row"><span class="lbl">Deployed Date</span><span class="colon">:</span><span class="val">{{ report.asset.deployment_date || '—' }}</span></div>
                        <div class="row"><span class="lbl">Life Span</span><span class="colon">:</span><span class="val">{{ report.asset.lifespan_years ?? 5 }} ({{ report.asset.lifespan_years ?? 5 }}) YEARS</span></div>
                    </div>
                </div>

                <!-- Recommendation -->
                <div class="ir-section-head">
                    <span class="ir-section-title">Recommendation:</span>
                    <span class="ir-rule"></span>
                </div>
                <ul class="ir-bullets">
                    <li v-for="(line, i) in recommendationLines" :key="i">{{ line }}</li>
                </ul>

                <div class="ir-nothing">———————————————————— <em>Nothing Follows</em> ————————————————————</div>

                <!-- Signatures -->
                <div class="ir-sigs">
                    <div class="sig-block">
                        <div class="sig-name">Prepared by: <em>{{ report.prepared_by || '' }}</em></div>
                        <div class="sig-role">IT Associate - Junior Technical</div>
                    </div>
                    <div class="sig-block">
                        <div class="sig-name">Noted by: <em>{{ report.noted_by || '' }}</em></div>
                        <div class="sig-role">IT - MIS Head</div>
                    </div>
                    <div class="sig-block">
                        <div class="sig-name">Noted by: <em>{{ report.noted_by_secondary || '' }}</em></div>
                        <div class="sig-role">IT - Technical Head</div>
                    </div>
                    <div class="sig-block approved">
                        <div class="sig-name">Approved By:</div>
                        <div class="sig-approver"><em>{{ report.approved_by || '' }}</em></div>
                        <div class="sig-role">CFO – Executive</div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="showDelete"
            :title="`Delete ${report.ir_no}?`"
            message="This incident report will be removed permanently."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>

<style scoped>
.ir-wrap { display: flex; justify-content: center; padding: 1rem 0.5rem; }

.ir-doc {
    background: #ffffff;
    color: #0f172a;
    width: 8.5in;
    min-height: 11in;
    font-family: 'Poppins', 'Inter', ui-sans-serif, system-ui, sans-serif;
    font-size: 12.5px;
    line-height: 1.55;
    padding: 0.5in 0.6in;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    box-shadow: 0 4px 18px -8px rgba(15, 23, 42, 0.18);
}

.ir-header { display: flex; align-items: center; gap: 16px; margin-bottom: 18px; }
.ir-logo { height: 70px; width: auto; object-fit: contain; }
.ir-company-name { font-size: 15px; font-weight: 600; }
.ir-company-addr { font-size: 11.5px; color: #334155; }

.ir-meta { margin: 18px 0 22px; }
.ir-meta .row {
    display: grid; grid-template-columns: 160px 12px 1fr;
    align-items: baseline; padding: 4px 0;
}
.ir-meta .lbl { font-weight: 700; }
.ir-meta .colon { font-weight: 700; }
.ir-meta .val  { font-weight: 600; }

.ir-section-head {
    display: flex; align-items: center; gap: 12px;
    margin-top: 18px; margin-bottom: 8px;
}
.ir-section-title { font-weight: 700; }
.ir-rule { flex: 1; height: 0; border-top: 2px solid #0f172a; }

.ir-prose { font-size: 12.5px; margin: 4px 0 6px; }

.ir-bullets {
    list-style: disc; padding-left: 24px;
    margin: 4px 0 6px;
}
.ir-bullets li { font-size: 12.5px; line-height: 1.6; }
.ir-bullets.check { list-style: none; padding-left: 6px; }
.ir-bullets.check li {
    position: relative; padding-left: 22px;
}
.ir-bullets.check li::before {
    content: "✓";
    position: absolute; left: 0; top: 0;
    color: #0f172a; font-weight: 700;
}

.ir-specs-wrap {
    margin: 6px 0 10px;
    padding-left: 28px;
}
.ir-specs-title { font-weight: 700; text-decoration: underline; margin-bottom: 4px; }
.ir-specs .row {
    display: grid; grid-template-columns: 140px 12px 1fr;
    align-items: baseline; padding: 2px 0; font-size: 12px;
}
.ir-specs .lbl { font-weight: 700; }

.ir-nothing { text-align: center; margin: 16px 0 22px; font-size: 11.5px; color: #334155; }

.ir-sigs {
    display: flex; flex-direction: column;
    gap: 18px;
    margin-top: 22px;
}
.sig-block { font-size: 12.5px; }
.sig-name { font-weight: 600; }
.sig-name em { font-style: italic; font-weight: 600; padding-left: 4px; }
.sig-role { font-weight: 700; margin-left: 32px; margin-top: 2px; font-size: 12px; }
.sig-block.approved { align-self: flex-end; text-align: right; margin-top: 8px; min-width: 220px; }
.sig-block.approved .sig-name { font-weight: 700; }
.sig-block.approved .sig-approver { font-style: italic; font-weight: 600; margin-top: 4px; }
.sig-block.approved .sig-role { text-align: right; margin-left: 0; }

/* Print — monochrome, portrait */
@media print {
    @page { size: 8.5in 11in; margin: 12mm; }
    .ir-doc, .ir-doc *, .ir-doc *::before, .ir-doc *::after {
        color: #000 !important; background: transparent !important;
        box-shadow: none !important;
    }
    .ir-wrap { padding: 0; }
    .ir-doc {
        width: 100%;
        min-height: 0;
        border: none !important;
        border-radius: 0; padding: 0;
    }
    .ir-rule { border-top-color: #000 !important; }
}
</style>
