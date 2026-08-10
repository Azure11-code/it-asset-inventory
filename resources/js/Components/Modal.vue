<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'lg' },
});

const emit = defineEmits(['close']);

const close = () => emit('close');

const onEsc = (e) => {
    if (e.key === 'Escape' && props.show) close();
};

onMounted(() => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));

watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});

// Full-width on mobile (w-full with small side gutter), constrained on sm+ screens.
const widthClass = {
    sm:   'w-full sm:max-w-sm',
    md:   'w-full sm:max-w-md',
    lg:   'w-full sm:max-w-lg',
    xl:   'w-full sm:max-w-xl',
    '2xl':'w-full sm:max-w-2xl',
    '3xl':'w-full sm:max-w-3xl',
};
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-slate-900/50" @click="close" />
            <div class="flex min-h-full items-center justify-center p-2 sm:p-4">
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-2 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-2 sm:scale-95"
                >
                    <div
                        v-if="show"
                        :class="['relative w-full transform overflow-hidden rounded-xl bg-white shadow-xl', widthClass[maxWidth]]"
                    >
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                            <button class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="close">
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>
                        <slot />
                    </div>
                </Transition>
            </div>
        </div>
    </Transition>
</template>
