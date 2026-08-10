<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import {
    ChevronLeftIcon, PencilSquareIcon, TrashIcon, PrinterIcon, ArrowDownTrayIcon,
    DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline';
import html2canvas from 'html2canvas';

const props = defineProps({ recommendation: Object });

const statusTone = { draft: 'slate', submitted: 'sky', approved: 'emerald', closed: 'slate' };

const fmtDate = (s) => {
    if (!s) return '';
    const d = new Date(s);
    return d.toLocaleDateString('en-US', { day: 'numeric', month: 'long', year: 'numeric' });
};

const downloading = ref(false);
const downloadPng = async () => {
    const el = document.querySelector('.rec-doc');
    if (!el) return;
    downloading.value = true;
    try {
        await document.fonts?.ready;
        const canvas = await html2canvas(el, { scale: 2.5, backgroundColor: '#ffffff', useCORS: true });
        const link = document.createElement('a');
        link.download = `${props.recommendation.doc_no}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } finally { downloading.value = false; }
};
const printDoc = () => window.print();

const showDelete = ref(false);
const doDelete = () => router.delete(`/recommendations/${props.recommendation.id}`);
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :title="recommendation.doc_no" :subtitle="recommendation.subject">
                <template #actions>
                    <Link href="/recommendations" class="btn-secondary"><ChevronLeftIcon class="h-4 w-4" /> Back</Link>
                    <button class="btn-secondary" @click="printDoc"><PrinterIcon class="h-4 w-4" /> Print</button>
                    <button class="btn-secondary" :disabled="downloading" @click="downloadPng">
                        <ArrowDownTrayIcon class="h-4 w-4" /> {{ downloading ? 'Preparing…' : 'Download PNG' }}
                    </button>
                    <a :href="`/recommendations/${recommendation.id}/docx`" class="btn-secondary">
                        <DocumentArrowDownIcon class="h-4 w-4" /> Download Word
                    </a>
                    <Link :href="`/recommendations/${recommendation.id}/edit`" class="btn-secondary">
                        <PencilSquareIcon class="h-4 w-4" /> Edit
                    </Link>
                    <button class="btn-danger" @click="showDelete = true">
                        <TrashIcon class="h-4 w-4" /> Delete
                    </button>
                </template>
            </PageHeader>
        </template>

        <div class="flex items-center gap-3 mb-4 print:hidden">
            <Badge :tone="statusTone[recommendation.status] || 'slate'" dot>{{ recommendation.status }}</Badge>
            <span class="text-sm text-slate-500">{{ fmtDate(recommendation.report_date) }}</span>
        </div>

        <!-- ─────────── Printable Recommendation ─────────── -->
        <div class="rec-wrap print-area">
            <div class="rec-doc">

                <!-- Header -->
                <header class="rec-header">
                    <img src="/arvin-logo.png" alt="Arvin" class="rec-logo" crossorigin="anonymous" />
                    <div class="rec-company">
                        <div class="rec-company-name">Arvin International Marketing Inc.</div>
                        <div class="rec-company-addr">18<sup>th</sup> Floor, Y - Tower, Macapagal Avenue Corner Coral Way Street</div>
                        <div class="rec-company-addr">Pasay City, Metro Manila</div>
                    </div>
                </header>

                <!-- Meta -->
                <div class="rec-meta">
                    <div class="row">
                        <span class="lbl">Date</span><span class="colon">:</span>
                        <span class="val">{{ fmtDate(recommendation.report_date) }}</span>
                    </div>
                    <div class="row">
                        <span class="lbl">Requestor</span><span class="colon">:</span>
                        <span class="val">
                            <div><strong>NAME:</strong> {{ (recommendation.requestor?.name || '').toUpperCase() }}</div>
                            <div v-if="recommendation.requestor?.employee_no"><strong>EMPLOYEE ID:</strong> {{ recommendation.requestor.employee_no }}</div>
                            <div v-if="recommendation.requestor?.position"><strong>POSITION:</strong> {{ recommendation.requestor.position.toUpperCase() }}</div>
                            <div v-if="recommendation.requestor?.department"><strong>DEPARTMENT:</strong> {{ recommendation.requestor.department.toUpperCase() }}</div>
                        </span>
                    </div>
                    <div class="row">
                        <span class="lbl">Thru</span><span class="colon">:</span>
                        <span class="val">{{ recommendation.thru }}</span>
                    </div>
                    <div class="row subject-row">
                        <span class="lbl">Subject</span><span class="colon">:</span>
                        <span class="val"><strong>{{ recommendation.subject }}</strong></span>
                    </div>
                </div>

                <!-- Body -->
                <p class="rec-body">{{ recommendation.body }}</p>

                <!-- Quick Specs -->
                <div v-if="recommendation.quick_specs?.length" class="rec-specs">
                    <div class="rec-specs-title">CURRENT DESKTOP - QUICK SPECS</div>
                    <div v-for="(spec, i) in recommendation.quick_specs" :key="i" class="rec-spec-row">
                        <span class="spec-label">{{ spec.label }}:</span>
                        <span class="spec-value">{{ spec.value }}</span>
                        <span v-if="spec.note" class="spec-note"> – <em>"{{ spec.note }}"</em></span>
                    </div>
                </div>

                <div class="rec-nothing">———————————————————— <em>Nothing Follows</em> ————————————————————</div>

                <!-- Signatures -->
                <div class="rec-sigs">
                    <div class="sig-row">
                        <div class="sig-block">
                            <div class="sig-line"><strong>Prepared By:</strong> <em>{{ recommendation.prepared_by || '' }}</em></div>
                            <div class="sig-role">IT Associate – Junior Technical</div>
                        </div>
                    </div>
                    <div class="sig-row">
                        <div class="sig-block">
                            <div class="sig-line"><strong>Review By:</strong> <em>{{ recommendation.reviewed_by || '' }}</em></div>
                            <div class="sig-role">IT Technical Head – Network Specialist</div>
                        </div>
                        <div class="sig-block right">
                            <div class="sig-line"><strong>Note by:</strong> <em>{{ recommendation.noted_by || '' }}</em></div>
                            <div class="sig-role">CFO-Executive Department</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="showDelete"
            :title="`Delete ${recommendation.doc_no}?`"
            message="This recommendation will be removed permanently."
            @close="showDelete = false"
            @confirm="doDelete"
        />
    </AppLayout>
</template>

<style scoped>
.rec-wrap { display: flex; justify-content: center; padding: 1rem 0.5rem; }

.rec-doc {
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

.rec-header { display: flex; align-items: center; gap: 16px; margin-bottom: 22px; }
.rec-logo { height: 70px; width: auto; object-fit: contain; }
.rec-company-name { font-size: 15px; font-weight: 600; }
.rec-company-addr { font-size: 11.5px; color: #334155; }

.rec-meta { margin: 14px 0 18px; }
.rec-meta .row {
    display: grid; grid-template-columns: 110px 12px 1fr;
    align-items: baseline; padding: 6px 0;
}
.rec-meta .lbl { font-weight: 700; }
.rec-meta .colon { font-weight: 700; }
.rec-meta .val  { font-weight: 500; }
.rec-meta .val div { padding: 1px 0; }
.rec-meta .subject-row {
    border-top: 1px solid #0f172a;
    border-bottom: 1px solid #0f172a;
    padding: 8px 0;
    margin-top: 6px;
}

.rec-body {
    font-size: 12.5px;
    text-align: justify;
    margin: 14px 0 16px;
    line-height: 1.65;
}

.rec-specs {
    background: #ffffff;
    margin: 0 0 8px 60px;
}
.rec-specs-title {
    font-weight: 700;
    text-decoration: underline;
    margin-bottom: 6px;
    font-size: 12.5px;
}
.rec-spec-row { font-size: 12px; padding: 2px 0; }
.spec-label { font-weight: 700; }
.spec-note  { color: #334155; font-style: italic; }

.rec-nothing { text-align: center; margin: 20px 0; font-size: 11.5px; color: #334155; }

.rec-sigs { margin-top: 22px; }
.sig-row {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 20px; padding: 14px 0;
}
.sig-block .sig-line em { font-weight: 600; padding-left: 6px; }
.sig-block .sig-role { font-weight: 700; margin-top: 2px; font-size: 12px; }
.sig-block.right { text-align: right; }

/* Print — monochrome */
@media print {
    @page { size: 8.5in 11in; margin: 12mm; }
    .rec-doc, .rec-doc *, .rec-doc *::before, .rec-doc *::after {
        color: #000 !important; background: transparent !important;
        box-shadow: none !important;
    }
    .rec-wrap { padding: 0; }
    .rec-doc {
        width: 100%;
        min-height: 0;
        border: none !important;
        border-radius: 0; padding: 0;
    }
    .rec-meta .subject-row {
        border-top: 1px solid #000 !important;
        border-bottom: 1px solid #000 !important;
    }
}
</style>
