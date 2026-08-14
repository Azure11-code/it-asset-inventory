<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    links: { type: Array, default: () => [] },
    from: Number,
    to: Number,
    total: Number,
});

// Show only: Previous, page 1, current ±1, last page, Next.
// Everything else collapses to a single ellipsis per gap.
const compactLinks = computed(() => {
    const raw = props.links || [];
    if (raw.length <= 3) return raw;

    // Laravel structure: first = Previous, last = Next, middle = numbered pages + '...' markers.
    const prev = raw[0];
    const next = raw[raw.length - 1];
    const pages = raw.slice(1, -1);

    // Get numeric pages (skip Laravel's own '...' entries which have url:null and label:'...')
    const numeric = pages.filter(p => /^\d+$/.test(String(p.label)));
    if (numeric.length === 0) return raw;

    const activeIdx = numeric.findIndex(p => p.active);
    const total = numeric.length;
    const keep = new Set();
    keep.add(0);
    keep.add(total - 1);
    for (let d = -1; d <= 1; d++) {
        const i = activeIdx + d;
        if (i >= 0 && i < total) keep.add(i);
    }

    const out = [prev];
    let lastPushed = -2;
    numeric.forEach((p, i) => {
        if (keep.has(i)) {
            if (i - lastPushed > 1) {
                out.push({ url: null, label: '…', active: false });
            }
            out.push(p);
            lastPushed = i;
        }
    });
    out.push(next);
    return out;
});
</script>

<template>
    <div v-if="total > 0" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-white px-5 py-3">
        <p class="text-sm text-slate-600">
            Showing <span class="font-medium">{{ from }}</span> to
            <span class="font-medium">{{ to }}</span> of
            <span class="font-medium">{{ total }}</span> results
        </p>
        <nav class="flex flex-wrap gap-1">
            <template v-for="(link, i) in compactLinks" :key="i">
                <span
                    v-if="!link.url"
                    class="rounded-md border border-slate-200 px-3 py-1 text-sm text-slate-400"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    :class="[
                        'rounded-md border px-3 py-1 text-sm',
                        link.active
                            ? 'border-brand-600 bg-brand-600 text-white'
                            : 'border-slate-200 text-slate-700 hover:bg-slate-50',
                    ]"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
