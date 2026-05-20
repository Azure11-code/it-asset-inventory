<script setup>
import { InboxIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, default: 'No results' },
    description: { type: String, default: '' },
    icon: { type: Object, default: () => InboxIcon },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-2 px-5 py-12 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <component :is="icon" class="h-6 w-6" />
        </div>
        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="description" class="max-w-sm text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
