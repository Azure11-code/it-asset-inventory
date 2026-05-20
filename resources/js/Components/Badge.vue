<script setup>
defineProps({
    tone: {
        type: String,
        default: 'slate',
        validator: (v) => ['slate', 'emerald', 'amber', 'rose', 'sky', 'brand'].includes(v),
    },
    dot: { type: Boolean, default: false },
});

const dotClass = {
    slate:   'bg-slate-400',
    emerald: 'bg-emerald-500',
    amber:   'bg-amber-500',
    rose:    'bg-rose-500',
    sky:     'bg-sky-500',
    brand:   'bg-brand-500',
};
</script>

<template>
    <span :class="['badge', `badge-${tone}`, 'capitalize']">
        <span v-if="dot" :class="['badge-dot', dotClass[tone]]" />
        <slot />
    </span>
</template>
