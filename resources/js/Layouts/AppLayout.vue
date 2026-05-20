<script setup>
import { ref, computed } from 'vue';
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
} from '@heroicons/vue/24/outline';

const sidebarOpen = ref(false);
const page = usePage();

const counts = computed(() => page.props.counts ?? {});
const user = computed(() => page.props.auth?.user);

const formatCount = (n) => {
    if (n == null) return null;
    if (n > 99) return '99+';
    return String(n);
};

const primaryNav = computed(() => [
    { name: 'Dashboard', href: '/',          icon: HomeIcon },
    { name: 'Assets',    href: '/assets',    icon: CpuChipIcon,    count: formatCount(counts.value.assets) },
    { name: 'Employees', href: '/employees', icon: UsersIcon,      count: formatCount(counts.value.employees) },
    { name: 'Users',     href: '/users',     icon: ShieldCheckIcon, count: formatCount(counts.value.users) },
]);

const masterNav = computed(() => [
    { name: 'Brands',      href: '/brands',      icon: TagIcon,             count: formatCount(counts.value.brands) },
    { name: 'Categories',  href: '/categories',  icon: Squares2X2Icon,      count: formatCount(counts.value.categories) },
    { name: 'Conditions',  href: '/conditions',  icon: SparklesIcon,        count: formatCount(counts.value.conditions) },
    { name: 'Departments', href: '/departments', icon: BuildingOffice2Icon, count: formatCount(counts.value.departments) },
    { name: 'Locations',   href: '/locations',   icon: MapPinIcon,          count: formatCount(counts.value.locations) },
]);

const currentPath = computed(() => page.url);
const isActive = (href) =>
    href === '/' ? currentPath.value === '/' : currentPath.value.startsWith(href);

const userInitial = computed(() => (user.value?.name ?? 'G').charAt(0).toUpperCase());

const logout = () => router.post('/logout');
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
                'fixed inset-y-0 left-0 z-50 flex w-64 transform flex-col border-r border-slate-200/80 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between px-6">
                <Link href="/" class="flex items-center gap-2">
                    <svg class="h-7 w-7 text-brand-600" viewBox="0 0 32 32" fill="none">
                        <path d="M4 16c0-6 4-12 12-12s12 6 12 12c-4 0-6-3-9-3s-5 3-9 3-6-3-6 0z" fill="currentColor" />
                    </svg>
                    <span class="text-sm font-semibold tracking-tight text-slate-900">IT Inventory</span>
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
                            :class="[
                                'group flex items-center justify-between rounded-md px-3 py-2 text-sm font-semibold transition-colors',
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

                <!-- Section header -->
                <div class="mt-8 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Master Data
                </div>
                <ul class="mt-2 space-y-1">
                    <li v-for="item in masterNav" :key="item.name">
                        <Link
                            :href="item.href"
                            :class="[
                                'group flex items-center justify-between rounded-md px-3 py-2 text-sm font-semibold transition-colors',
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
        <div class="lg:pl-64">
            <!-- Mobile header strip -->
            <div class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur lg:hidden">
                <button class="text-slate-600 hover:text-slate-900" @click="sidebarOpen = true">
                    <Bars3Icon class="h-6 w-6" />
                </button>
                <span class="text-sm font-semibold text-slate-900">IT Inventory</span>
            </div>

            <!-- Page header slot -->
            <div v-if="$slots.header" class="px-4 sm:px-6 lg:px-8 pt-8 pb-6">
                <slot name="header" />
            </div>

            <!-- Content -->
            <main class="px-4 sm:px-6 lg:px-8 pb-12">
                <slot />
            </main>
        </div>

        <FlashToast />
    </div>
</template>
