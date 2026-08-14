<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import {
    CpuChipIcon, UserIcon, MapPinIcon, BuildingOffice2Icon, Squares2X2Icon, TagIcon,
} from '@heroicons/vue/24/outline';

const query = ref('');
const groups = ref([]);
const loading = ref(false);
const open = ref(false);
const activeIndex = ref(-1);
const inputEl = ref(null);
const rootEl = ref(null);

let fetchTimer = null;
let abortCtrl = null;

const iconFor = (type) => ({
    asset: CpuChipIcon,
    employee: UserIcon,
    location: MapPinIcon,
    department: BuildingOffice2Icon,
    category: Squares2X2Icon,
    brand: TagIcon,
}[type] || MagnifyingGlassIcon);

const toneFor = (type) => ({
    asset:      'bg-brand-50 text-brand-700',
    employee:   'bg-sky-50 text-sky-700',
    location:   'bg-amber-50 text-amber-700',
    department: 'bg-emerald-50 text-emerald-700',
    category:   'bg-indigo-50 text-indigo-700',
    brand:      'bg-rose-50 text-rose-700',
}[type] || 'bg-slate-100 text-slate-700');

// Flatten groups → linear list of items so keyboard nav is easy.
const flatItems = computed(() => groups.value.flatMap(g => g.items.map(it => ({ ...it, type: g.type }))));

const doFetch = async () => {
    const q = query.value.trim();
    if (q.length < 2) {
        groups.value = [];
        loading.value = false;
        return;
    }
    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();
    loading.value = true;
    try {
        const res = await fetch(`/search/suggest?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
            signal: abortCtrl.signal,
        });
        const data = await res.json();
        groups.value = data.groups || [];
        activeIndex.value = flatItems.value.length > 0 ? 0 : -1;
    } catch (e) {
        if (e.name !== 'AbortError') groups.value = [];
    } finally {
        loading.value = false;
    }
};

watch(query, () => {
    open.value = true;
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(doFetch, 250);
});

const goToItem = (item) => {
    open.value = false;
    query.value = '';
    groups.value = [];
    router.visit(item.url);
};

const goToFullPage = () => {
    const q = query.value.trim();
    if (!q) return;
    open.value = false;
    router.visit(`/search?q=${encodeURIComponent(q)}`);
};

const onKeydown = (e) => {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (flatItems.value.length === 0) return;
        activeIndex.value = (activeIndex.value + 1) % flatItems.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (flatItems.value.length === 0) return;
        activeIndex.value = activeIndex.value <= 0 ? flatItems.value.length - 1 : activeIndex.value - 1;
    } else if (e.key === 'Enter') {
        e.preventDefault();
        // If there's an active suggestion → open it. Otherwise → full page.
        if (activeIndex.value >= 0 && flatItems.value[activeIndex.value]) {
            goToItem(flatItems.value[activeIndex.value]);
        } else {
            goToFullPage();
        }
    } else if (e.key === 'Escape') {
        open.value = false;
        inputEl.value?.blur();
    }
};

const clear = () => {
    query.value = '';
    groups.value = [];
    activeIndex.value = -1;
    inputEl.value?.focus();
};

// Global Ctrl+K / Cmd+K shortcut
const onGlobalKey = (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        inputEl.value?.focus();
        inputEl.value?.select();
    }
};

const onClickOutside = (e) => {
    if (rootEl.value && !rootEl.value.contains(e.target)) open.value = false;
};

onMounted(() => {
    document.addEventListener('keydown', onGlobalKey);
    document.addEventListener('mousedown', onClickOutside);
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onGlobalKey);
    document.removeEventListener('mousedown', onClickOutside);
});
</script>

<template>
    <div ref="rootEl" class="relative w-full max-w-lg">
        <div class="relative">
            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                ref="inputEl"
                v-model="query"
                type="search"
                placeholder="Search assets, employees, locations…  (Ctrl+K)"
                class="w-full rounded-md border border-slate-200 bg-white pl-9 pr-16 py-2 text-sm text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200"
                @focus="open = true"
                @keydown="onKeydown"
            />
            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                <button v-if="query" type="button" class="p-1 text-slate-400 hover:text-slate-600" @click="clear">
                    <XMarkIcon class="h-4 w-4" />
                </button>
                <kbd v-else class="hidden sm:inline-flex h-5 items-center rounded border border-slate-200 bg-slate-50 px-1.5 text-[10px] font-medium text-slate-500">⌘K</kbd>
            </div>
        </div>

        <!-- Suggestions dropdown -->
        <div
            v-if="open && (query.length >= 2)"
            class="absolute left-0 right-0 top-full z-50 mt-2 max-h-[70vh] overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl"
        >
            <div v-if="loading" class="px-4 py-6 text-center text-sm text-slate-500">Searching…</div>

            <div v-else-if="flatItems.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">
                No matches for "{{ query }}"
                <div class="mt-2">
                    <button class="text-brand-600 hover:underline text-xs" @click="goToFullPage">
                        Search everywhere →
                    </button>
                </div>
            </div>

            <template v-else>
                <div v-for="group in groups" :key="group.type" class="py-1">
                    <div class="flex items-center gap-2 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        {{ group.label }}
                    </div>
                    <button
                        v-for="item in group.items"
                        :key="`${group.type}-${item.id}`"
                        type="button"
                        :class="[
                            'flex w-full items-center gap-3 px-3 py-2 text-left transition-colors',
                            flatItems[activeIndex]?.type === group.type && flatItems[activeIndex]?.id === item.id
                                ? 'bg-brand-50'
                                : 'hover:bg-slate-50',
                        ]"
                        @click="goToItem(item)"
                        @mouseenter="activeIndex = flatItems.findIndex(fi => fi.type === group.type && fi.id === item.id)"
                    >
                        <span :class="['inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg', toneFor(group.type)]">
                            <component :is="iconFor(group.type)" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-slate-900">{{ item.title }}</span>
                            <span class="block truncate text-[11px] text-slate-500">{{ item.subtitle }}</span>
                        </span>
                    </button>
                </div>
                <div class="border-t border-slate-100 px-3 py-2">
                    <button class="w-full text-left text-xs text-brand-600 hover:underline" @click="goToFullPage">
                        Press <kbd class="rounded border border-slate-200 bg-slate-50 px-1 text-[10px]">Enter</kbd> or click here for all results →
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
