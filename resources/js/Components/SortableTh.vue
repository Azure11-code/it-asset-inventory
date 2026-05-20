<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronUpDownIcon } from '@heroicons/vue/24/outline';
import { ChevronUpIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    field: { type: String, required: true },
    sort: { type: String, default: null },
    direction: { type: String, default: 'asc' },
    url: { type: String, required: true },
    extra: { type: Object, default: () => ({}) },
    align: { type: String, default: 'left' },
});

const isActive = computed(() => props.sort === props.field);
const nextDir = computed(() => (isActive.value && props.direction === 'asc') ? 'desc' : 'asc');

const onClick = () => {
    const params = { ...props.extra, sort: props.field, direction: nextDir.value };
    Object.keys(params).forEach((k) => {
        if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k];
    });
    router.get(props.url, params, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <th
        :class="[
            'group cursor-pointer select-none transition-colors hover:bg-slate-50',
            align === 'right' ? 'text-right' : '',
        ]"
        @click="onClick"
    >
        <span :class="['inline-flex items-center gap-1', align === 'right' ? 'justify-end w-full' : '']">
            <slot />
            <ChevronUpIcon v-if="isActive && direction === 'asc'" class="h-3.5 w-3.5 text-brand-600" />
            <ChevronDownIcon v-else-if="isActive && direction === 'desc'" class="h-3.5 w-3.5 text-brand-600" />
            <ChevronUpDownIcon v-else class="h-3.5 w-3.5 text-slate-300 group-hover:text-slate-400" />
        </span>
    </th>
</template>
