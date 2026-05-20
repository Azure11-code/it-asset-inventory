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
    ArrowRightIcon, ChartBarIcon, ShieldCheckIcon, ClockIcon,
    ArrowUpOnSquareIcon, ArrowDownTrayIcon, ArrowsRightLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: { type: Object, default: () => ({ total_assets: 0, assigned: 0, in_stock: 0, employees: 0 }) },
    recent_movements: { type: Array, default: () => [] },
    charts: { type: Object, default: () => ({ movements: [], by_category: [], by_status: {}, warranty: {} }) },
});

const tiles = computed(() => [
    { label: 'Total Assets', value: props.stats.total_assets, icon: CpuChipIcon,    tone: 'brand'   },
    { label: 'Assigned',     value: props.stats.assigned,     icon: CheckBadgeIcon, tone: 'emerald' },
    { label: 'In Stock',     value: props.stats.in_stock,     icon: InboxStackIcon, tone: 'amber'   },
    { label: 'Employees',    value: props.stats.employees,    icon: UserGroupIcon,  tone: 'sky'     },
]);

const toneBg = {
    brand:   'bg-brand-50   text-brand-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber:   'bg-amber-50   text-amber-700',
    sky:     'bg-sky-50     text-sky-700',
};

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
                    <Link href="/assets/bulk-receive" class="btn-secondary">Bulk Receive</Link>
                    <Link href="/assets/create" class="btn-primary">
                        New Asset <ArrowRightIcon class="h-4 w-4" />
                    </Link>
                </template>
            </PageHeader>
        </template>

        <!-- Stat grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="tile in tiles" :key="tile.label" class="card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ tile.label }}</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ tile.value }}</p>
                    </div>
                    <div :class="['flex h-10 w-10 items-center justify-center rounded-lg', toneBg[tile.tone]]">
                        <component :is="tile.icon" class="h-5 w-5" />
                    </div>
                </div>
            </div>
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
    </AppLayout>
</template>
