<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import {
    MagnifyingGlassIcon, CpuChipIcon, UserIcon, MapPinIcon,
    BuildingOffice2Icon, Squares2X2Icon, TagIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    query:  { type: String, default: '' },
    groups: { type: Array,  default: () => [] },
    total:  { type: Number, default: 0 },
});

const search = ref(props.query);
const doSearch = () => {
    const q = search.value.trim();
    if (!q) return;
    router.get('/search', { q }, { preserveState: false });
};

const iconFor = (type) => ({
    asset: CpuChipIcon, employee: UserIcon, location: MapPinIcon,
    department: BuildingOffice2Icon, category: Squares2X2Icon, brand: TagIcon,
}[type] || MagnifyingGlassIcon);

const toneFor = (type) => ({
    asset:      'bg-brand-50 text-brand-700',
    employee:   'bg-sky-50 text-sky-700',
    location:   'bg-amber-50 text-amber-700',
    department: 'bg-emerald-50 text-emerald-700',
    category:   'bg-indigo-50 text-indigo-700',
    brand:      'bg-rose-50 text-rose-700',
}[type] || 'bg-slate-100 text-slate-700');
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader :title="query ? `Search results for &quot;${query}&quot;` : 'Search'" :subtitle="query ? `${total} result${total === 1 ? '' : 's'} across the system` : 'Search across assets, employees, locations, and master data.'" />
        </template>

        <!-- Search input -->
        <div class="mb-4">
            <form @submit.prevent="doSearch">
                <div class="relative w-full">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search anything…"
                        class="w-full rounded-lg border border-slate-200 bg-white pl-11 pr-4 py-3 text-base text-slate-800 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200"
                        autofocus
                    />
                </div>
            </form>
        </div>

        <!-- Empty query prompt -->
        <div v-if="!query" class="card p-10 text-center">
            <MagnifyingGlassIcon class="mx-auto h-10 w-10 text-slate-300" />
            <p class="mt-3 text-sm text-slate-500">Start typing above to search assets, employees, locations, and master data.</p>
            <p class="mt-1 text-xs text-slate-400">Tip: press <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 text-[10px] font-semibold">Ctrl</kbd> + <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 text-[10px] font-semibold">K</kbd> anywhere to open the quick search bar.</p>
        </div>

        <!-- No results -->
        <div v-else-if="total === 0" class="card p-10 text-center">
            <MagnifyingGlassIcon class="mx-auto h-10 w-10 text-slate-300" />
            <p class="mt-3 text-sm font-semibold text-slate-700">No matches for "{{ query }}"</p>
            <p class="mt-1 text-xs text-slate-500">Try a shorter or different keyword.</p>
        </div>

        <!-- Grouped results — responsive grid uses full page width -->
        <div v-else class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <section v-for="group in groups" :key="group.type" class="card">
                <header class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <component :is="iconFor(group.type)" :class="['h-4 w-4', 'text-slate-500']" />
                        <h2 class="text-sm font-semibold text-slate-900">{{ group.label }}</h2>
                        <span class="text-xs text-slate-400">{{ group.count }} result{{ group.count === 1 ? '' : 's' }}</span>
                    </div>
                </header>
                <ul class="divide-y divide-slate-100">
                    <li v-for="item in group.items" :key="`${group.type}-${item.id}`" class="group">
                        <Link :href="item.url" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50">
                            <span :class="['inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg', toneFor(group.type)]">
                                <component :is="iconFor(group.type)" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-brand-700 group-hover:underline">{{ item.title }}</span>
                                <span class="block text-[13px] text-slate-500">{{ item.subtitle }}</span>
                                <span class="mt-0.5 block truncate text-[11px] text-slate-400">{{ item.url }}</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
