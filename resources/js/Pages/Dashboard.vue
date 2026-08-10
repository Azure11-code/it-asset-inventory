<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import VueApexCharts from 'vue3-apexcharts';
import { Link } from '@inertiajs/vue3';
import {
    CpuChipIcon, CheckBadgeIcon, InboxStackIcon, UserGroupIcon,
    ArrowRightIcon, ChartBarIcon, ShieldCheckIcon, ShieldExclamationIcon,
    ClockIcon, WrenchScrewdriverIcon, BuildingOffice2Icon,
    ArrowUpOnSquareIcon, ArrowDownTrayIcon, ArrowsRightLeftIcon,
    PrinterIcon, TableCellsIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: { type: Object, default: () => ({ total_assets: 0, maintained: 0, assigned: 0, in_stock: 0, employees: 0, bitdefender: {} }) },
    recent_movements: { type: Array, default: () => [] },
    charts: { type: Object, default: () => ({ movements: [], by_category: [], by_department: [], cat_dept: { departments: [], rows: [] }, loc_cat: { categories: [], rows: [] }, by_status: {}, warranty: {} }) },
});

const tiles = computed(() => [
    { label: 'Total Assets',     value: props.stats.total_assets, icon: CpuChipIcon,            tone: 'brand'   },
    { label: 'Maintained Asset', value: props.stats.maintained,   icon: WrenchScrewdriverIcon,  tone: 'emerald' },
    { label: 'Assigned',         value: props.stats.assigned,     icon: CheckBadgeIcon,         tone: 'sky'     },
    { label: 'In Stock',         value: props.stats.in_stock,     icon: InboxStackIcon,         tone: 'amber'   },
    { label: 'Employees',        value: props.stats.employees,    icon: UserGroupIcon,          tone: 'slate'   },
]);

const toneBg = {
    brand:   'bg-brand-50   text-brand-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber:   'bg-amber-50   text-amber-700',
    sky:     'bg-sky-50     text-sky-700',
    slate:   'bg-slate-100  text-slate-700',
    rose:    'bg-rose-50    text-rose-700',
};

// ── Bitdefender breakdown ──
const bitdefenderStats = computed(() => {
    const raw = props.stats.bitdefender || {};
    const total = (raw.Yes || 0) + (raw.No || 0) + (raw.Excluded || 0);
    return [
        { label: 'Protected (Yes)', count: raw.Yes || 0,      tone: 'emerald' },
        { label: 'No AV',           count: raw.No || 0,       tone: 'rose'    },
        { label: 'Excluded',        count: raw.Excluded || 0, tone: 'slate'   },
        { label: 'Total tracked',   count: total,             tone: 'brand'   },
    ];
});

// ── By-Department horizontal bar ──
const deptSeries = computed(() => [{ name: 'Assets', data: (props.charts.by_department || []).map(d => d.count) }]);
const deptOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, ui-sans-serif, system-ui', foreColor: '#475569' },
    colors: ['#4f46e5'],
    plotOptions: { bar: { horizontal: true, barHeight: '65%', borderRadius: 4, distributed: false } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 600, colors: ['#ffffff'] }, offsetX: -4 },
    xaxis: {
        categories: (props.charts.by_department || []).map(d => d.name),
        axisBorder: { show: false }, axisTicks: { show: false },
        labels: { style: { fontSize: '10px', colors: '#94a3b8' } },
    },
    yaxis: { labels: { style: { fontSize: '11px', colors: '#475569' } } },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    tooltip: { theme: 'light', y: { formatter: (v) => `${v} asset${v === 1 ? '' : 's'}` } },
    noData: { text: 'No department data yet', style: { color: '#94a3b8', fontSize: '13px' } },
}));

// ── Cell heat-color for pivot tables (subtle green shading, more intense = higher count) ──
const heatClass = (v, max) => {
    if (!v) return 'text-slate-300';
    const ratio = max ? v / max : 0;
    if (ratio > 0.7) return 'bg-brand-100 font-semibold text-brand-900';
    if (ratio > 0.4) return 'bg-brand-50 font-medium text-brand-800';
    if (ratio > 0)   return 'text-slate-800';
    return 'text-slate-300';
};

const catDeptMax = computed(() => {
    let m = 0;
    for (const row of (props.charts.cat_dept?.rows || []))
        for (const v of Object.values(row.cells)) if (v > m) m = v;
    return m;
});

const locCatMax = computed(() => {
    let m = 0;
    for (const row of (props.charts.loc_cat?.rows || []))
        for (const v of Object.values(row.cells)) if (v > m) m = v;
    return m;
});

const catDeptColTotals = computed(() => {
    const cols = props.charts.cat_dept?.departments || [];
    const rows = props.charts.cat_dept?.rows || [];
    const totals = Object.fromEntries(cols.map(c => [c, 0]));
    for (const row of rows) for (const c of cols) totals[c] += row.cells[c] || 0;
    return totals;
});

const locCatColTotals = computed(() => {
    const cols = props.charts.loc_cat?.categories || [];
    const rows = props.charts.loc_cat?.rows || [];
    const totals = Object.fromEntries(cols.map(c => [c, 0]));
    for (const row of rows) for (const c of cols) totals[c] += row.cells[c] || 0;
    return totals;
});

const catDeptGrandTotal = computed(() => Object.values(catDeptColTotals.value).reduce((s, v) => s + v, 0));
const locCatGrandTotal  = computed(() => Object.values(locCatColTotals.value).reduce((s, v) => s + v, 0));

// ── Print helpers ──
// Prints either the whole dashboard or one section by temporarily marking the
// target element with the global `.print-area` class (defined in app.css).
const printTarget = (selector) => {
    const el = document.querySelector(selector);
    if (!el) return;
    el.classList.add('print-area');
    const cleanup = () => {
        el.classList.remove('print-area');
        window.removeEventListener('afterprint', cleanup);
    };
    window.addEventListener('afterprint', cleanup);
    // Give the DOM a tick to apply the class before opening the dialog
    setTimeout(() => window.print(), 50);
};
const printDashboard = () => printTarget('#dashboard-root');
const printSection   = (id) => printTarget(`#${id}`);

const movementTone = { issuance: 'brand', return: 'amber', transfer: 'sky' };
const movementIcon = { issuance: ArrowUpOnSquareIcon, return: ArrowDownTrayIcon, transfer: ArrowsRightLeftIcon };

// --- Movements stacked column chart ---
const movementsSeries = computed(() => [
    { name: 'Issuance', data: props.charts.movements.map((d) => d.issuance) },
    { name: 'Return',   data: props.charts.movements.map((d) => d.return) },
    { name: 'Transfer', data: props.charts.movements.map((d) => d.transfer) },
]);

const movementsOptions = computed(() => ({
    chart: {
        type: 'bar', stacked: true, toolbar: { show: false },
        fontFamily: 'Inter, ui-sans-serif, system-ui', foreColor: '#475569',
    },
    colors: ['#4f46e5', '#f59e0b', '#0ea5e9'],
    plotOptions: {
        bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' },
    },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    xaxis: {
        categories: props.charts.movements.map((d) => d.label),
        axisBorder: { show: false }, axisTicks: { show: false },
        labels: { style: { fontSize: '11px', colors: '#94a3b8' } },
    },
    yaxis: {
        labels: { style: { fontSize: '11px', colors: '#94a3b8' } },
        forceNiceScale: true,
    },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { left: 8, right: 8 } },
    legend: {
        position: 'top', horizontalAlign: 'right', fontSize: '12px', fontWeight: 500,
        markers: { width: 8, height: 8, radius: 4 }, itemMargin: { horizontal: 10 },
    },
    tooltip: { theme: 'light', shared: true },
    noData: {
        text: 'No movements in the last 14 days',
        align: 'center', verticalAlign: 'middle',
        style: { color: '#94a3b8', fontSize: '13px' },
    },
}));

// --- Category donut ---
const categorySeries = computed(() => props.charts.by_category.map((c) => c.count));
const categoryOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'Inter, ui-sans-serif, system-ui' },
    labels: props.charts.by_category.map((c) => c.name),
    colors: ['#4f46e5', '#10b981', '#f59e0b', '#0ea5e9', '#e11d48', '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#22c55e'],
    legend: {
        position: 'bottom', fontSize: '12px', fontWeight: 500,
        markers: { width: 8, height: 8, radius: 4 }, itemMargin: { horizontal: 6, vertical: 4 },
    },
    stroke: { width: 2, colors: ['#ffffff'] },
    dataLabels: {
        enabled: true,
        style: { fontSize: '11px', fontWeight: 600 },
        formatter: (val) => `${Math.round(val)}%`,
    },
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    name: { fontSize: '13px', color: '#64748b' },
                    value: { fontSize: '24px', fontWeight: 700, color: '#0f172a' },
                    total: {
                        show: true, label: 'Total Assets', color: '#64748b',
                        formatter: () => props.charts.by_category.reduce((s, c) => s + c.count, 0),
                    },
                },
            },
        },
    },
    tooltip: { theme: 'light', y: { formatter: (v) => `${v} asset${v === 1 ? '' : 's'}` } },
}));

// --- Status horizontal bars ---
const statusEntries = computed(() => {
    const labels = {
        in_stock: 'In Stock', assigned: 'Assigned', for_repair: 'For Repair',
        defective: 'Defective', retired: 'Retired', replaced: 'Replaced',
    };
    const tones = {
        in_stock: 'bg-sky-500', assigned: 'bg-brand-500', for_repair: 'bg-amber-500',
        defective: 'bg-rose-500', retired: 'bg-slate-400', replaced: 'bg-slate-400',
    };
    const total = Object.values(props.charts.by_status).reduce((s, v) => s + v, 0) || 1;
    return Object.keys(labels).map((k) => ({
        key: k, label: labels[k], tone: tones[k],
        count: props.charts.by_status[k] ?? 0,
        pct: ((props.charts.by_status[k] ?? 0) / total) * 100,
    }));
});

// --- Warranty buckets ---
const warrantyTiles = computed(() => [
    { label: 'Active warranty',  count: props.charts.warranty.active        ?? 0, tone: 'emerald' },
    { label: 'Expiring ≤ 90d',   count: props.charts.warranty.expiring_soon ?? 0, tone: 'amber'   },
    { label: 'Expired',          count: props.charts.warranty.expired       ?? 0, tone: 'rose'    },
]);
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Dashboard" subtitle="A quick look at your IT assets and recent activity.">
                <template #actions>
                    <button type="button" class="btn-secondary no-print" @click="printDashboard" title="Print or save as PDF">
                        <PrinterIcon class="h-4 w-4" /> Print
                    </button>
                    <Link href="/assets/bulk-receive" class="btn-secondary no-print">Bulk Receive</Link>
                    <Link href="/assets/create" class="btn-primary no-print">
                        New Asset <ArrowRightIcon class="h-4 w-4" />
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div id="dashboard-root">
        <!-- Stat grid -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div v-for="tile in tiles" :key="tile.label" class="card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-medium text-slate-500">{{ tile.label }}</p>
                        <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ tile.value }}</p>
                    </div>
                    <div :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-lg', toneBg[tile.tone]]">
                        <component :is="tile.icon" class="h-4 w-4" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Bitdefender + By-Department row -->
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <section id="section-bitdefender" class="card">
                <header class="card-header py-2.5 px-4">
                    <div>
                        <h2 class="card-title text-sm">Bitdefender Coverage</h2>
                        <p class="card-subtitle text-[11px]">Endpoint antivirus status across the fleet.</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-ghost no-print" @click="printSection('section-bitdefender')" title="Print section">
                            <PrinterIcon class="h-4 w-4" />
                        </button>
                        <ShieldExclamationIcon class="h-5 w-5 text-slate-400" />
                    </div>
                </header>
                <ul class="divide-y divide-slate-100">
                    <li v-for="s in bitdefenderStats" :key="s.label" class="flex items-center justify-between px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <span :class="['h-2.5 w-2.5 rounded-full',
                                s.tone === 'emerald' ? 'bg-emerald-500' :
                                s.tone === 'rose'    ? 'bg-rose-500'    :
                                s.tone === 'slate'   ? 'bg-slate-400'   : 'bg-brand-500']"></span>
                            <span class="text-xs font-medium text-slate-700">{{ s.label }}</span>
                        </div>
                        <span class="text-base font-semibold text-slate-900">{{ s.count }}</span>
                    </li>
                </ul>
            </section>

            <section id="section-by-dept" class="card lg:col-span-2">
                <header class="card-header py-2.5 px-4">
                    <div>
                        <h2 class="card-title text-sm">Assets per Department</h2>
                        <p class="card-subtitle text-[11px]">Distribution across the organization.</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-ghost no-print" @click="printSection('section-by-dept')" title="Print section">
                            <PrinterIcon class="h-4 w-4" />
                        </button>
                        <BuildingOffice2Icon class="h-5 w-5 text-slate-400" />
                    </div>
                </header>
                <div class="p-2">
                    <VueApexCharts
                        v-if="(charts.by_department || []).length"
                        type="bar" :height="Math.max(280, (charts.by_department.length * 26) + 60)"
                        :options="deptOptions" :series="deptSeries"
                    />
                    <EmptyState v-else title="No department data yet" description="Import assets with a Department to populate this chart." />
                </div>
            </section>
        </div>

        <!-- Charts row -->
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="card lg:col-span-2">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Asset movements — last 14 days</h2>
                        <p class="card-subtitle">Daily volume of issuances, returns, and transfers.</p>
                    </div>
                    <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                        <ChartBarIcon class="h-4 w-4" /> Daily
                    </div>
                </header>
                <div class="p-2">
                    <VueApexCharts type="bar" height="320" :options="movementsOptions" :series="movementsSeries" />
                </div>
            </section>

            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Asset status</h2>
                        <p class="card-subtitle">Current state of every device.</p>
                    </div>
                </header>
                <ul class="space-y-3 p-5">
                    <li v-for="row in statusEntries" :key="row.key">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700">{{ row.label }}</span>
                            <span class="text-slate-500">{{ row.count }}</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div :class="['h-full rounded-full transition-all', row.tone]" :style="{ width: `${row.pct}%` }"></div>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Warranty + category + activity -->
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Warranty health</h2>
                        <p class="card-subtitle">Pay attention to amber and red.</p>
                    </div>
                    <ShieldCheckIcon class="h-5 w-5 text-slate-400" />
                </header>
                <ul class="divide-y divide-slate-100">
                    <li v-for="t in warrantyTiles" :key="t.label" class="flex items-center justify-between px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span :class="['h-2.5 w-2.5 rounded-full', t.tone === 'emerald' ? 'bg-emerald-500' : t.tone === 'amber' ? 'bg-amber-500' : 'bg-rose-500']"></span>
                            <span class="text-sm font-medium text-slate-700">{{ t.label }}</span>
                        </div>
                        <span class="text-lg font-semibold text-slate-900">{{ t.count }}</span>
                    </li>
                </ul>
            </section>

            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">By category</h2>
                        <p class="card-subtitle">Asset distribution.</p>
                    </div>
                </header>
                <div class="p-4">
                    <VueApexCharts
                        v-if="categorySeries.length"
                        type="donut" height="280"
                        :options="categoryOptions" :series="categorySeries"
                    />
                    <EmptyState v-else title="No assets yet" description="Bulk-receive or add your first asset to populate this chart." />
                </div>
            </section>

            <section class="card">
                <header class="card-header">
                    <div>
                        <h2 class="card-title">Recent activity</h2>
                        <p class="card-subtitle">Latest movements.</p>
                    </div>
                    <Link href="/assets" class="text-sm font-semibold text-brand-600 hover:text-brand-700">All assets →</Link>
                </header>

                <EmptyState
                    v-if="recent_movements.length === 0"
                    title="No movements yet"
                    description="Issue an asset to start the audit trail."
                    :icon="ClockIcon"
                />

                <ol v-else class="divide-y divide-slate-100">
                    <li v-for="m in recent_movements" :key="m.id" class="flex gap-3 px-5 py-3">
                        <div :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-full', `badge-${movementTone[m.type]}`]">
                            <component :is="movementIcon[m.type]" class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <Link :href="`/assets/${m.asset_id}`" class="font-semibold text-slate-900 hover:text-brand-600">
                                    {{ m.asset_tag }}
                                </Link>
                                <span class="text-slate-500 capitalize"> · {{ m.type }}</span>
                            </p>
                            <p class="text-xs text-slate-500">
                                <template v-if="m.type === 'issuance'">→ {{ m.to_employee?.full_name }}</template>
                                <template v-else-if="m.type === 'return'">from {{ m.from_employee?.full_name }}</template>
                                <template v-else>{{ m.from_employee?.full_name }} → {{ m.to_employee?.full_name }}</template>
                            </p>
                            <p class="text-xs text-slate-400">{{ m.movement_date }}</p>
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <!-- Category × Department pivot -->
        <section id="section-cat-dept" class="mt-4 card">
            <header class="card-header py-2.5 px-4">
                <div>
                    <h2 class="card-title text-sm">Category × Department</h2>
                    <p class="card-subtitle text-[11px]">Assets broken down per category, per department.</p>
                </div>
                <div class="flex items-center gap-1 no-print">
                    <a href="/dashboard/exports/cat-dept.xlsx" class="btn-ghost" title="Download Excel">
                        <TableCellsIcon class="h-4 w-4" />
                    </a>
                    <button type="button" class="btn-ghost" @click="printSection('section-cat-dept')" title="Print section">
                        <PrinterIcon class="h-4 w-4" />
                    </button>
                </div>
            </header>
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-slate-50/60 z-10">Category</th>
                            <th v-for="d in charts.cat_dept?.departments" :key="d" class="text-center">{{ d }}</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!(charts.cat_dept?.rows || []).length">
                            <td :colspan="(charts.cat_dept?.departments?.length || 0) + 2" class="cell-muted text-center py-6">
                                No assets to pivot yet.
                            </td>
                        </tr>
                        <tr v-for="row in charts.cat_dept?.rows" :key="row.category">
                            <td class="sticky left-0 bg-white font-medium text-slate-800">{{ row.category }}</td>
                            <td v-for="d in charts.cat_dept?.departments" :key="d"
                                :class="['text-center', heatClass(row.cells[d], catDeptMax)]">
                                {{ row.cells[d] || '' }}
                            </td>
                            <td class="text-right font-semibold text-slate-900">{{ row.total }}</td>
                        </tr>
                        <tr v-if="(charts.cat_dept?.rows || []).length" class="bg-slate-50/60 border-t-2 border-slate-200">
                            <td class="sticky left-0 bg-slate-50/60 font-semibold text-slate-900">Grand Total</td>
                            <td v-for="d in charts.cat_dept?.departments" :key="d" class="text-center font-semibold text-slate-900">
                                {{ catDeptColTotals[d] || '' }}
                            </td>
                            <td class="text-right font-bold text-slate-900">{{ catDeptGrandTotal }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Location × Category pivot -->
        <section id="section-loc-cat" class="mt-4 card">
            <header class="card-header py-2.5 px-4">
                <div>
                    <h2 class="card-title text-sm">Location × Category</h2>
                    <p class="card-subtitle text-[11px]">Where each device category is deployed.</p>
                </div>
                <div class="flex items-center gap-1 no-print">
                    <a href="/dashboard/exports/loc-cat.xlsx" class="btn-ghost" title="Download Excel">
                        <TableCellsIcon class="h-4 w-4" />
                    </a>
                    <button type="button" class="btn-ghost" @click="printSection('section-loc-cat')" title="Print section">
                        <PrinterIcon class="h-4 w-4" />
                    </button>
                </div>
            </header>
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-slate-50/60 z-10">Location</th>
                            <th v-for="c in charts.loc_cat?.categories" :key="c" class="text-center">{{ c }}</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!(charts.loc_cat?.rows || []).length">
                            <td :colspan="(charts.loc_cat?.categories?.length || 0) + 2" class="cell-muted text-center py-6">
                                No assets to pivot yet.
                            </td>
                        </tr>
                        <tr v-for="row in charts.loc_cat?.rows" :key="row.location">
                            <td class="sticky left-0 bg-white font-medium text-slate-800">{{ row.location }}</td>
                            <td v-for="c in charts.loc_cat?.categories" :key="c"
                                :class="['text-center', heatClass(row.cells[c], locCatMax)]">
                                {{ row.cells[c] || '' }}
                            </td>
                            <td class="text-right font-semibold text-slate-900">{{ row.total }}</td>
                        </tr>
                        <tr v-if="(charts.loc_cat?.rows || []).length" class="bg-slate-50/60 border-t-2 border-slate-200">
                            <td class="sticky left-0 bg-slate-50/60 font-semibold text-slate-900">Grand Total</td>
                            <td v-for="c in charts.loc_cat?.categories" :key="c" class="text-center font-semibold text-slate-900">
                                {{ locCatColTotals[c] || '' }}
                            </td>
                            <td class="text-right font-bold text-slate-900">{{ locCatGrandTotal }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        </div><!-- /#dashboard-root -->
    </AppLayout>
</template>
