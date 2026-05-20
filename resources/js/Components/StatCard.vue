<script setup>
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/solid';

defineProps({
    label: String,
    value: [String, Number],
    delta: { type: Number, default: null },
    icon: { type: Object, default: null },
    tone: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'emerald', 'amber', 'rose'].includes(v),
    },
});

const toneClasses = {
    brand:   'bg-brand-100 text-brand-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    amber:   'bg-amber-100 text-amber-700',
    rose:    'bg-rose-100 text-rose-700',
};
</script>

<template>
    <div class="card p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">{{ label }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ value }}</p>
            </div>
            <div v-if="icon" :class="['flex h-10 w-10 items-center justify-center rounded-lg', toneClasses[tone]]">
                <component :is="icon" class="h-5 w-5" />
            </div>
        </div>
        <p v-if="delta !== null" class="mt-3 flex items-center gap-1 text-sm">
            <ArrowUpIcon v-if="delta >= 0" class="h-4 w-4 text-emerald-600" />
            <ArrowDownIcon v-else class="h-4 w-4 text-rose-600" />
            <span :class="delta >= 0 ? 'text-emerald-600' : 'text-rose-600'" class="font-medium">
                {{ Math.abs(delta) }}%
            </span>
            <span class="text-slate-500">vs last month</span>
        </p>
    </div>
</template>
