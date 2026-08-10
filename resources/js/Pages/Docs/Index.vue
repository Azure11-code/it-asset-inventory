<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import {
    BookOpenIcon, LifebuoyIcon, CpuChipIcon, ClipboardDocumentCheckIcon,
    ArrowUpOnSquareIcon, ArrowDownTrayIcon, ArrowsRightLeftIcon, SparklesIcon,
    ShieldCheckIcon, UsersIcon, ArchiveBoxArrowDownIcon,
} from '@heroicons/vue/24/outline';

const sections = [
    { id: 'overview',   title: 'Overview' },
    { id: 'glossary',   title: 'Glossary' },
    { id: 'lifecycle',  title: 'Asset Lifecycle' },
    { id: 'permits',    title: 'Bringing Assets Off-Site' },
    { id: 'roles',      title: 'Roles & Signatures' },
    { id: 'faq',        title: 'FAQ' },
];

const active = ref('overview');
let observer = null;

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((e) => e.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (visible) active.value = visible.target.id;
    }, { rootMargin: '-30% 0px -55% 0px', threshold: 0.01 });
    sections.forEach((s) => {
        const el = document.getElementById(s.id);
        if (el) observer.observe(el);
    });
});
onBeforeUnmount(() => observer?.disconnect());

const goToSection = (id) => {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const startTour = () => {
    try { localStorage.removeItem('it_inventory_tour_v1'); } catch (e) { /* ignore */ }
    router.visit('/', { onSuccess: () => location.reload() });
};
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Documentation" subtitle="Terms, definitions, and how the system flows end-to-end." />
        </template>

        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <!-- ── Table of contents ── -->
            <aside class="hidden lg:block">
                <nav class="sticky top-20 space-y-1">
                    <button
                        v-for="s in sections"
                        :key="s.id"
                        type="button"
                        :class="[
                            'block w-full rounded-md px-3 py-1.5 text-left text-sm transition-colors',
                            active === s.id
                                ? 'bg-brand-50 text-brand-700 font-semibold'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                        ]"
                        @click="goToSection(s.id)"
                    >
                        {{ s.title }}
                    </button>
                    <button
                        type="button"
                        class="mt-4 inline-flex w-full items-center gap-2 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-colors"
                        @click="startTour"
                    >
                        <LifebuoyIcon class="h-4 w-4" /> Replay the tour
                    </button>
                </nav>
            </aside>

            <!-- ── Content ── -->
            <article class="prose-doc max-w-3xl space-y-12">
                <!-- OVERVIEW -->
                <section id="overview" class="card p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-brand-50 p-2 text-brand-600"><BookOpenIcon class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Overview</h2>
                            <p class="text-sm text-slate-500">What this system tracks and why.</p>
                        </div>
                    </div>
                    <div class="mt-5 text-sm leading-relaxed text-slate-700 space-y-3">
                        <p>The <strong>IT Asset Inventory</strong> tracks every IT peripheral your company owns through its full lifecycle —
                        from procurement to retirement. One <em>asset</em> equals one physical device with a unique <em>asset tag</em>.</p>
                        <p>Each asset can be <em>assigned</em> to an employee, <em>transferred</em>, <em>returned</em>, and ultimately <em>retired</em>
                        or <em>replaced</em>. Every change is recorded as a <em>movement</em>, so you have a full audit history.</p>
                        <p>When an employee needs to bring a device home or off-site, you issue a <strong>Permit to Bring Asset</strong>
                        — a printable authorization with line items and signatures.</p>
                    </div>
                </section>

                <!-- GLOSSARY -->
                <section id="glossary" class="card p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-brand-50 p-2 text-brand-600"><SparklesIcon class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Glossary</h2>
                            <p class="text-sm text-slate-500">Plain-language definitions for every key term.</p>
                        </div>
                    </div>
                    <dl class="mt-5 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-slate-900">Asset</dt>
                            <dd class="text-sm text-slate-600">A single physical IT device — laptop, monitor, printer, headset. Uniquely identified by its <em>asset tag</em>.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Asset Tag</dt>
                            <dd class="text-sm text-slate-600">The label stuck on the device (e.g. <code>LPT-001</code>). Generated when you create the asset.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Category</dt>
                            <dd class="text-sm text-slate-600">A class of asset — Laptop, Monitor, Printer, etc. Each can have a tag prefix.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Brand</dt>
                            <dd class="text-sm text-slate-600">The manufacturer — Dell, HP, Lenovo, etc.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Condition</dt>
                            <dd class="text-sm text-slate-600">A label describing physical state — <em>New</em>, <em>Good</em>, <em>Fair</em>, <em>Defective</em>. Configurable.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Status</dt>
                            <dd class="text-sm text-slate-600">Where the asset is in its lifecycle: <em>in stock</em>, <em>assigned</em>, <em>for repair</em>, <em>defective</em>, <em>retired</em>, <em>replaced</em>.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Movement</dt>
                            <dd class="text-sm text-slate-600">A logged event — <em>issuance</em>, <em>return</em>, or <em>transfer</em>. Every change to an asset's holder is a movement.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Holder</dt>
                            <dd class="text-sm text-slate-600">The employee currently in possession of an asset. Empty if the asset is in stock.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Specifications</dt>
                            <dd class="text-sm text-slate-600">Key/value pairs describing the device — Processor, RAM, OS, Storage. Like Windows System Info.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Warranty Until</dt>
                            <dd class="text-sm text-slate-600">When the manufacturer warranty expires. Calculated by default as <em>purchase date + 5 years</em>.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Lifespan</dt>
                            <dd class="text-sm text-slate-600">Expected service years. Defaults to 5. Used to decide eligibility for replacement.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Permit to Bring Asset</dt>
                            <dd class="text-sm text-slate-600">A printable authorization that lets an employee take one or more assets off-premises for a date range.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Employee</dt>
                            <dd class="text-sm text-slate-600">A staff member who can hold and use an asset. Required to assign devices.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">User</dt>
                            <dd class="text-sm text-slate-600">Someone with login access to <em>this app</em>. Distinct from an Employee.</dd>
                        </div>
                    </dl>
                </section>

                <!-- LIFECYCLE -->
                <section id="lifecycle" class="card p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-brand-50 p-2 text-brand-600"><CpuChipIcon class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Asset Lifecycle</h2>
                            <p class="text-sm text-slate-500">From procurement to retirement, step by step.</p>
                        </div>
                    </div>

                    <ol class="mt-6 relative border-l border-slate-200 pl-6 space-y-6">
                        <li class="relative">
                            <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white text-xs font-semibold">1</span>
                            <h3 class="font-semibold text-slate-900 flex items-center gap-2">
                                <ArchiveBoxArrowDownIcon class="h-4 w-4 text-slate-400" /> Receive
                            </h3>
                            <p class="text-sm text-slate-600">Register the device. Use <strong>Bulk Receive</strong> when you've just bought 10 identical units — it auto-numbers tags. Set category, brand, purchase date, cost, and lifespan.</p>
                        </li>
                        <li class="relative">
                            <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white text-xs font-semibold">2</span>
                            <h3 class="font-semibold text-slate-900 flex items-center gap-2">
                                <ArrowUpOnSquareIcon class="h-4 w-4 text-slate-400" /> Issue
                            </h3>
                            <p class="text-sm text-slate-600">Assign the asset to an employee. This creates an <em>issuance</em> movement and sets the asset's status to <em>assigned</em>. The deployment date is auto-set on first issuance.</p>
                        </li>
                        <li class="relative">
                            <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white text-xs font-semibold">3</span>
                            <h3 class="font-semibold text-slate-900 flex items-center gap-2">
                                <ArrowsRightLeftIcon class="h-4 w-4 text-slate-400" /> Transfer
                            </h3>
                            <p class="text-sm text-slate-600">Move the asset from one employee to another. Creates a <em>transfer</em> movement; both endpoints are logged.</p>
                        </li>
                        <li class="relative">
                            <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white text-xs font-semibold">4</span>
                            <h3 class="font-semibold text-slate-900 flex items-center gap-2">
                                <ArrowDownTrayIcon class="h-4 w-4 text-slate-400" /> Return
                            </h3>
                            <p class="text-sm text-slate-600">Employee returns the device (e.g. after resignation). Choose the resulting status: <em>In Stock</em>, <em>For Repair</em>, or <em>Defective</em>. Optionally update condition.</p>
                        </li>
                        <li class="relative">
                            <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white text-xs font-semibold">5</span>
                            <h3 class="font-semibold text-slate-900">Repair or Replace</h3>
                            <p class="text-sm text-slate-600">If a device is defective and is older than its lifespan (default 5 years), it's auto-eligible for <strong>replacement</strong>. If younger, an investigation/recommendation is required before replacing.</p>
                        </li>
                        <li class="relative">
                            <span class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full bg-slate-500 text-white text-xs font-semibold">6</span>
                            <h3 class="font-semibold text-slate-900">Retire</h3>
                            <p class="text-sm text-slate-600">End of life. The asset is soft-deleted but its history remains intact for audit.</p>
                        </li>
                    </ol>
                </section>

                <!-- PERMITS -->
                <section id="permits" class="card p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-brand-50 p-2 text-brand-600"><ClipboardDocumentCheckIcon class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Bringing Assets Off-Site</h2>
                            <p class="text-sm text-slate-500">When and how to issue a Permit to Bring Asset.</p>
                        </div>
                    </div>
                    <div class="mt-5 text-sm leading-relaxed text-slate-700 space-y-3">
                        <p>If an employee needs to take a company asset home or to a client site, issue a <strong>Permit to Bring Asset</strong>. The permit serves as written authorization and is printable in portrait A4.</p>
                        <p><strong>What it contains:</strong></p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Employee's name, position, department, and destination</li>
                            <li>Date borrowed and expected return date</li>
                            <li>Validity window (when the permit is in force)</li>
                            <li>One or more line items — pick an existing asset to autofill, or type a free-form description (e.g. <em>Laptop Bag</em>)</li>
                            <li>Signatures: Requested By, Issued By, Noted By, Approved By</li>
                        </ul>
                        <p><strong>Tip:</strong> the Name field is <em>hybrid</em> — pick from the employee list, or just type a name not in the system. When typed manually, you can also enter Position and Department.</p>
                    </div>
                </section>

                <!-- ROLES -->
                <section id="roles" class="card p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-brand-50 p-2 text-brand-600"><ShieldCheckIcon class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Roles & Signatures</h2>
                            <p class="text-sm text-slate-500">Who does what across the workflow.</p>
                        </div>
                    </div>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="grid grid-cols-1 sm:grid-cols-[10rem_1fr] gap-2">
                            <dt class="font-semibold text-slate-900">Requested By</dt>
                            <dd class="text-slate-600">The employee asking for the permit — usually the same person taking the device.</dd>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-[10rem_1fr] gap-2">
                            <dt class="font-semibold text-slate-900">Issued By</dt>
                            <dd class="text-slate-600">The IT staff member who hands over the device. A system user.</dd>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-[10rem_1fr] gap-2">
                            <dt class="font-semibold text-slate-900">Noted By</dt>
                            <dd class="text-slate-600">The IT Head and/or Supervisor witnessing the issuance. Two slots available.</dd>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-[10rem_1fr] gap-2">
                            <dt class="font-semibold text-slate-900">Approved By</dt>
                            <dd class="text-slate-600">The Manager / COO / CFO / CEO authorizing the off-site use. May be a system user, or recorded via a free-text approval note (e.g. <em>"THRU VIBER APPROVAL"</em>).</dd>
                        </div>
                    </dl>
                </section>

                <!-- FAQ -->
                <section id="faq" class="card p-6 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="rounded-md bg-brand-50 p-2 text-brand-600"><UsersIcon class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-slate-900">FAQ</h2>
                            <p class="text-sm text-slate-500">Quick answers to common questions.</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-5 text-sm">
                        <div>
                            <p class="font-semibold text-slate-900">What's the difference between a User and an Employee?</p>
                            <p class="text-slate-600">A <strong>User</strong> can sign into this app (e.g. IT staff). An <strong>Employee</strong> is anyone who holds an asset — they may or may not have a login.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">Why can't I delete a brand / location / condition?</p>
                            <p class="text-slate-600">Conditions block deletion when assets reference them. The others currently allow deletion but should be used carefully — referenced assets will lose that link.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">How do I print only the permit form?</p>
                            <p class="text-slate-600">Open the permit, click <strong>Print</strong>. The system hides the sidebar and toolbar; only the permit document prints, in portrait A4, in black and white with strong borders.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">Can I bulk-import assets?</p>
                            <p class="text-slate-600">Use <strong>Bulk Receive</strong> on the Assets page to create many assets at once with auto-numbered tags. CSV import isn't built yet.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">Where do I download an asset tag?</p>
                            <p class="text-slate-600">Open the asset, click <strong>Download Tag</strong>. You get a PNG with the company header and a QR code that encodes the asset's key info.</p>
                        </div>
                    </div>
                </section>
            </article>
        </div>
    </AppLayout>
</template>

<style scoped>
.prose-doc code {
    background: rgb(241 245 249);
    color: rgb(67 56 202);
    padding: 0.05rem 0.35rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
    font-family: ui-monospace, 'SF Mono', Menlo, monospace;
}
</style>
