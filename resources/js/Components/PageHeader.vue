<script setup>
defineProps({
    title: String,
    subtitle: String,
});
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-lg sm:text-2xl font-semibold tracking-tight text-slate-900 leading-tight">{{ title }}</h1>
            <p v-if="subtitle" class="mt-0.5 sm:mt-1 text-xs sm:text-sm text-slate-500">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
