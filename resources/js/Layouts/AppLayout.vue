<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FlashToast from '@/Components/FlashToast.vue';
import {
    HomeIcon,
    UsersIcon,
    BuildingOffice2Icon,
    TagIcon,
    Squares2X2Icon,
    MapPinIcon,
    ArchiveBoxIcon,
    CpuChipIcon,
    SparklesIcon,
    ShieldCheckIcon,
    Bars3Icon,
    XMarkIcon,
    ArrowRightStartOnRectangleIcon,
    ClipboardDocumentCheckIcon,
    BookOpenIcon,
    LifebuoyIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    QrCodeIcon,
    HashtagIcon,
    UserGroupIcon,
    ChevronRightIcon,
    CircleStackIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    PhotoIcon,
} from '@heroicons/vue/24/outline';
import OnboardingTour from '@/Components/OnboardingTour.vue';
import GlobalSearch from '@/Components/GlobalSearch.vue';
import AiChat from '@/Components/AiChat.vue';
import { usePermissions } from '@/composables/usePermissions';

const sidebarOpen = ref(false);

// Desktop sidebar collapse (hides sidebar entirely, persisted in localStorage)
const SIDEBAR_COLLAPSED_KEY = 'it_inventory_sidebar_collapsed_v1';
const readCollapsed = () => {
    try { return localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1'; }
    catch (e) { return false; }
};
const sidebarCollapsed = ref(readCollapsed());
const toggleSidebarCollapsed = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    try { localStorage.setItem(SIDEBAR_COLLAPSED_KEY, sidebarCollapsed.value ? '1' : '0'); }
    catch (e) { /* ignore */ }
};

const page = usePage();

const counts = computed(() => page.props.counts ?? {});
const user = computed(() => page.props.auth?.user);
const { can, isAdmin } = usePermissions();

const formatCount = (n) => {
    if (n == null) return null;
    if (n > 99) return '99+';
    return String(n);
};

const rawPrimaryNav = computed(() => [
    { name: 'Dashboard', href: '/',          icon: HomeIcon,                                                                    tour: 'nav-dashboard' },
    { name: 'Assets',    href: '/assets',    icon: CpuChipIcon,             count: formatCount(counts.value.assets),            tour: 'nav-assets',    resource: 'assets' },
    { name: 'Scan',      href: '/scan',      icon: QrCodeIcon,                                                                                          resource: 'scan' },
    { name: 'Employees', href: '/employees', icon: UsersIcon,               count: formatCount(counts.value.employees),         tour: 'nav-employees', resource: 'employees' },
    { name: 'Users',     href: '/users',     icon: ShieldCheckIcon,         count: formatCount(counts.value.users),                                     adminOnly: true },
]);

const rawDocumentsNav = computed(() => [
    { name: 'Accountability',   href: '/accountability',  icon: ShieldCheckIcon,            count: formatCount(counts.value.accountability),   resource: 'accountability' },
    { name: 'Permits',          href: '/permits',         icon: ClipboardDocumentCheckIcon, count: formatCount(counts.value.permits),         tour: 'nav-permits', resource: 'permits' },
    { name: 'Incident Reports', href: '/incidents',       icon: ExclamationTriangleIcon,    count: formatCount(counts.value.incidents),        resource: 'incidents' },
    { name: 'Recommendations',  href: '/recommendations', icon: DocumentTextIcon,           count: formatCount(counts.value.recommendations),  resource: 'recommendations' },
    { name: 'Gallery',          href: '/gallery',         icon: PhotoIcon,                                                                     galleryCheck: true },
    { name: 'Backups',          href: '/backups',         icon: CircleStackIcon,                                                              adminOnly: true },
]);

const rawMasterNav = computed(() => [
    { name: 'Asset Code Rules', href: '/asset-code-rules', icon: HashtagIcon,         count: formatCount(counts.value.assetCodeRules), resource: 'asset_code_rules' },
    { name: 'Brands',           href: '/brands',           icon: TagIcon,             count: formatCount(counts.value.brands),        resource: 'brands' },
    { name: 'Categories',       href: '/categories',       icon: Squares2X2Icon,      count: formatCount(counts.value.categories),    resource: 'categories' },
    { name: 'Conditions',       href: '/conditions',       icon: SparklesIcon,        count: formatCount(counts.value.conditions),    resource: 'conditions' },
    { name: 'Departments',      href: '/departments',      icon: BuildingOffice2Icon, count: formatCount(counts.value.departments),   resource: 'departments' },
    { name: 'Locations',        href: '/locations',        icon: MapPinIcon,          count: formatCount(counts.value.locations),     resource: 'locations' },
    { name: 'Signatories',      href: '/signatories',      icon: UserGroupIcon,       count: formatCount(counts.value.signatories),   resource: 'signatories' },
]);

const filterNav = (items) => items.filter(i => {
    if (i.adminOnly)    return isAdmin.value;
    if (i.galleryCheck) return can('recommendations', 'view') || can('incidents', 'view') || can('permits', 'view') || can('accountability', 'view');
    if (i.resource)     return can(i.resource, 'view');
    return true;
});

const primaryNav   = computed(() => filterNav(rawPrimaryNav.value));
const documentsNav = computed(() => filterNav(rawDocumentsNav.value));
const masterNav    = computed(() => filterNav(rawMasterNav.value));

const currentPath = computed(() => page.url);
const isActive = (href) =>
    href === '/' ? currentPath.value === '/' : currentPath.value.startsWith(href);

// ── Collapsible sidebar sections (persisted in localStorage) ──
const SIDEBAR_STATE_KEY = 'it_inventory_sidebar_sections_v1';
const readSectionState = (key, defaultOpen) => {
    try {
        const raw = localStorage.getItem(SIDEBAR_STATE_KEY);
        if (!raw) return defaultOpen;
        const state = JSON.parse(raw);
        return typeof state[key] === 'boolean' ? state[key] : defaultOpen;
    } catch (e) { return defaultOpen; }
};
const persistSectionState = (key, value) => {
    try {
        const raw = localStorage.getItem(SIDEBAR_STATE_KEY);
        const state = raw ? JSON.parse(raw) : {};
        state[key] = value;
        localStorage.setItem(SIDEBAR_STATE_KEY, JSON.stringify(state));
    } catch (e) { /* ignore */ }
};

const documentsOpen = ref(readSectionState('documents', true));
const masterOpen    = ref(readSectionState('master', false));
const helpOpen      = ref(readSectionState('help', false));

// If a child page is active, force the section open so the active nav is visible.
const anyDocActive    = computed(() => documentsNav.value.some(i => isActive(i.href)));
const anyMasterActive = computed(() => masterNav.value.some(i => isActive(i.href)));
if (anyDocActive.value)     documentsOpen.value = true;
if (anyMasterActive.value)  masterOpen.value    = true;
if (isActive('/docs'))      helpOpen.value      = true;

const toggleDocuments = () => {
    documentsOpen.value = !documentsOpen.value;
    persistSectionState('documents', documentsOpen.value);
};
const toggleMaster = () => {
    masterOpen.value = !masterOpen.value;
    persistSectionState('master', masterOpen.value);
};
const toggleHelp = () => {
    helpOpen.value = !helpOpen.value;
    persistSectionState('help', helpOpen.value);
};

const userInitial = computed(() => (user.value?.name ?? 'G').charAt(0).toUpperCase());

const logout = () => router.post('/logout');

// ── Onboarding Tour ──
const TOUR_KEY = 'it_inventory_tour_v1';
const tourOpen = ref(false);
const tourSteps = [
    {
        target: '[data-tour="nav-dashboard"]',
        title: 'Welcome to IT Inventory',
        body:  'This is your home base. The dashboard shows totals, recent movements, and warranty health at a glance.',
    },
    {
        target: '[data-tour="nav-assets"]',
        title: 'Assets',
        body:  'Every device you own — one row per physical item. Register new assets, bulk-receive, and track them through their full lifecycle.',
    },
    {
        target: '[data-tour="nav-permits"]',
        title: 'Permits to Bring Asset',
        body:  'Authorize an employee to take a device off-premises. Each permit lists line items and signatures, and can be printed in portrait.',
    },
    {
        target: '[data-tour="nav-employees"]',
        title: 'Employees',
        body:  'Staff who hold and use IT assets. Required so you can assign devices to a person.',
    },
    {
        target: '[data-tour="nav-master"]',
        title: 'Master Data',
        body:  'Reference lists — brands, categories, conditions, departments, and locations. Set these up first.',
    },
    {
        target: '[data-tour="nav-docs"]',
        title: 'Documentation',
        body:  'Glossary, term definitions, and a step-by-step system flow. Bookmark this for new users.',
    },
    {
        target: '[data-tour="nav-tour"]',
        title: 'Replay the tour anytime',
        body:  'Click here to restart this tour. The system remembers you’ve seen it for future logins.',
    },
];
const isMobileViewport = () => typeof window !== 'undefined' && window.innerWidth < 1024;
let sidebarWasClosed = false;

const startTour = () => {
    // Nav targets live in the sidebar. On mobile the sidebar is off-screen by default,
    // so the tour would highlight nothing. Force it open for the tour and remember to
    // close it again on finish.
    if (isMobileViewport()) {
        sidebarWasClosed = !sidebarOpen.value;
        sidebarOpen.value = true;
        // Let the slide-in transition (200ms) finish before the tour measures targets.
        setTimeout(() => { tourOpen.value = true; }, 260);
    } else {
        tourOpen.value = true;
    }
};
const finishTour = () => {
    tourOpen.value = false;
    if (isMobileViewport() && sidebarWasClosed) {
        sidebarOpen.value = false;
        sidebarWasClosed = false;
    }
    try { localStorage.setItem(TOUR_KEY, '1'); } catch (e) { /* ignore */ }
};
onMounted(() => {
    try {
        if (user.value && !localStorage.getItem(TOUR_KEY)) {
            setTimeout(startTour, 600);
        }
    } catch (e) { /* ignore */ }
});
</script>

<template>
    <div class="min-h-screen bg-white">
        <!-- Mobile sidebar backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-64 transform flex-col border-r border-slate-200/80 bg-white transition-transform duration-200 ease-in-out',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'lg:-translate-x-full' : 'lg:translate-x-0',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between px-6">
                <Link href="/" class="flex items-center gap-2.5">
                    <span class="brand-mark">IT</span>
                    <span class="flex flex-col leading-tight">
                        <span class="text-sm font-semibold tracking-tight text-slate-900">IT Asset</span>
                        <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Inventory</span>
                    </span>
                </Link>
                <button class="lg:hidden text-slate-400 hover:text-slate-700" @click="sidebarOpen = false">
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-4 pb-4">
                <ul class="space-y-1">
                    <li v-for="item in primaryNav" :key="item.name">
                        <Link
                            :href="item.href"
                            :data-tour="item.tour"
                            :class="[
                                'group flex items-center justify-between rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-colors',
                                isActive(item.href)
                                    ? 'bg-brand-50 text-brand-600'
                                    : 'text-slate-700 hover:bg-slate-50 hover:text-brand-600',
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <component
                                    :is="item.icon"
                                    :class="['h-5 w-5 shrink-0 transition-colors', isActive(item.href) ? 'text-brand-600' : 'text-slate-400 group-hover:text-brand-600']"
                                />
                                {{ item.name }}
                            </span>
                            <span
                                v-if="item.count"
                                :class="[
                                    'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-2 text-xs font-medium ring-1 ring-inset',
                                    isActive(item.href)
                                        ? 'bg-white text-brand-700 ring-brand-200'
                                        : 'bg-white text-slate-500 ring-slate-200',
                                ]"
                            >
                                {{ item.count }}
                            </span>
                        </Link>
                    </li>
                </ul>

                <!-- Documents section (collapsible) -->
                <button
                    v-if="documentsNav.length"
                    type="button"
                    class="mt-5 flex w-full items-center justify-between px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-50"
                    @click="toggleDocuments"
                >
                    <span>Documents</span>
                    <ChevronRightIcon :class="['h-3.5 w-3.5 transition-transform', documentsOpen ? 'rotate-90' : '']" />
                </button>
                <Transition
                    enter-active-class="transition-all duration-150 overflow-hidden"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-96 opacity-100"
                    leave-active-class="transition-all duration-150 overflow-hidden"
                    leave-from-class="max-h-96 opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <ul v-if="documentsOpen" class="mt-1 space-y-1">
                        <li v-for="item in documentsNav" :key="item.name">
                            <Link
                                :href="item.href"
                                :data-tour="item.tour"
                                :class="[
                                    'group flex items-center justify-between rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-colors',
                                    isActive(item.href)
                                        ? 'bg-brand-50 text-brand-600'
                                        : 'text-slate-700 hover:bg-slate-50 hover:text-brand-600',
                                ]"
                            >
                                <span class="flex items-center gap-3">
                                    <component
                                        :is="item.icon"
                                        :class="['h-5 w-5 shrink-0 transition-colors', isActive(item.href) ? 'text-brand-600' : 'text-slate-400 group-hover:text-brand-600']"
                                    />
                                    {{ item.name }}
                                </span>
                                <span
                                    v-if="item.count"
                                    :class="[
                                        'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-2 text-xs font-medium ring-1 ring-inset',
                                        isActive(item.href)
                                            ? 'bg-white text-brand-700 ring-brand-200'
                                            : 'bg-white text-slate-500 ring-slate-200',
                                    ]"
                                >
                                    {{ item.count }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </Transition>

                <!-- Master Data (collapsible, collapsed by default) -->
                <button
                    v-if="masterNav.length"
                    type="button"
                    data-tour="nav-master"
                    class="mt-5 flex w-full items-center justify-between px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-50"
                    @click="toggleMaster"
                >
                    <span>Master Data</span>
                    <ChevronRightIcon :class="['h-3.5 w-3.5 transition-transform', masterOpen ? 'rotate-90' : '']" />
                </button>
                <Transition
                    enter-active-class="transition-all duration-150 overflow-hidden"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[500px] opacity-100"
                    leave-active-class="transition-all duration-150 overflow-hidden"
                    leave-from-class="max-h-[500px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <ul v-if="masterOpen" class="mt-1 space-y-1">
                        <li v-for="item in masterNav" :key="item.name">
                            <Link
                                :href="item.href"
                                :class="[
                                    'group flex items-center justify-between rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-colors',
                                    isActive(item.href)
                                        ? 'bg-brand-50 text-brand-600'
                                        : 'text-slate-700 hover:bg-slate-50 hover:text-brand-600',
                                ]"
                            >
                                <span class="flex items-center gap-3">
                                    <component
                                        :is="item.icon"
                                        :class="['h-5 w-5 shrink-0 transition-colors', isActive(item.href) ? 'text-brand-600' : 'text-slate-400 group-hover:text-brand-600']"
                                    />
                                    {{ item.name }}
                                </span>
                                <span
                                    v-if="item.count"
                                    :class="[
                                        'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-2 text-xs font-medium ring-1 ring-inset',
                                        isActive(item.href)
                                            ? 'bg-white text-brand-700 ring-brand-200'
                                            : 'bg-white text-slate-500 ring-slate-200',
                                    ]"
                                >
                                    {{ item.count }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </Transition>
                <!-- Help section (collapsible, collapsed by default) -->
                <button
                    type="button"
                    class="mt-5 flex w-full items-center justify-between px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-50"
                    @click="toggleHelp"
                >
                    <span>Help</span>
                    <ChevronRightIcon :class="['h-3.5 w-3.5 transition-transform', helpOpen ? 'rotate-90' : '']" />
                </button>
                <Transition
                    enter-active-class="transition-all duration-150 overflow-hidden"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-40 opacity-100"
                    leave-active-class="transition-all duration-150 overflow-hidden"
                    leave-from-class="max-h-40 opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <ul v-if="helpOpen" class="mt-1 space-y-1">
                        <li>
                            <Link
                                href="/docs"
                                data-tour="nav-docs"
                                :class="[
                                    'group flex items-center gap-3 rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-colors',
                                    isActive('/docs')
                                        ? 'bg-brand-50 text-brand-600'
                                        : 'text-slate-700 hover:bg-slate-50 hover:text-brand-600',
                                ]"
                            >
                                <BookOpenIcon :class="['h-5 w-5 shrink-0 transition-colors', isActive('/docs') ? 'text-brand-600' : 'text-slate-400 group-hover:text-brand-600']" />
                                Documentation
                            </Link>
                        </li>
                        <li>
                            <button
                                type="button"
                                data-tour="nav-tour"
                                class="group w-full flex items-center gap-3 rounded-md px-3 py-1.5 text-[12.5px] font-medium text-slate-700 hover:bg-slate-50 hover:text-brand-600 transition-colors"
                                @click="startTour"
                            >
                                <LifebuoyIcon class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-brand-600 transition-colors" />
                                Take a tour
                            </button>
                        </li>
                    </ul>
                </Transition>
            </nav>

            <!-- User block -->
            <div class="border-t border-slate-100 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-semibold text-white shadow-sm">
                        {{ userInitial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ user?.name ?? 'Guest' }}</p>
                        <p class="truncate text-xs text-slate-500">{{ user?.email ?? 'not signed in' }}</p>
                    </div>
                    <button
                        class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-rose-600 transition-colors"
                        title="Sign out"
                        @click="logout"
                    >
                        <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main column -->
        <div :class="['transition-[padding] duration-200 ease-in-out', sidebarCollapsed ? 'lg:pl-0' : 'lg:pl-64']">
            <!-- Top bar (mobile + desktop) with global search -->
            <div class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-slate-200/80 bg-white/95 px-3 sm:px-4 lg:px-8 backdrop-blur">
                <button class="text-slate-600 hover:text-slate-900 lg:hidden" @click="sidebarOpen = true">
                    <Bars3Icon class="h-6 w-6" />
                </button>
                <button
                    class="hidden lg:inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors"
                    :title="sidebarCollapsed ? 'Show sidebar' : 'Hide sidebar'"
                    @click="toggleSidebarCollapsed"
                >
                    <component :is="sidebarCollapsed ? ChevronDoubleRightIcon : ChevronDoubleLeftIcon" class="h-4 w-4" />
                </button>
                <div class="ml-auto lg:ml-0 flex-1 min-w-0 max-w-lg">
                    <GlobalSearch />
                </div>
            </div>

            <!-- Page header slot -->
            <div v-if="$slots.header" class="px-3 sm:px-6 lg:px-8 pt-4 sm:pt-8 pb-3 sm:pb-6">
                <slot name="header" />
            </div>

            <!-- Content -->
            <main class="px-3 sm:px-6 lg:px-8 pb-8 sm:pb-12">
                <Transition name="page" mode="out-in">
                    <div :key="currentPath">
                        <slot />
                    </div>
                </Transition>
            </main>
        </div>

        <FlashToast />

        <AiChat />

        <OnboardingTour
            :show="tourOpen"
            :steps="tourSteps"
            @close="finishTour"
            @finish="finishTour"
        />
    </div>
</template>
