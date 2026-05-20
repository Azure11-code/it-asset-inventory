<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, default: () => [] },
    from: Number,
    to: Number,
    total: Number,
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
            <template v-for="(link, i) in links" :key="i">
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
