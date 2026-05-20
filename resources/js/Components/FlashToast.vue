<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/solid';

const page = usePage();
const visible = ref(false);
const flash = computed(() => page.props.flash ?? {});

watch(flash, (val) => {
    if (val.success || val.error) {
        visible.value = true;
        setTimeout(() => (visible.value = false), 3500);
    }
}, { deep: true, immediate: true });
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="visible" class="fixed bottom-5 right-5 z-50">
            <div
                v-if="flash.success"
                class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-white px-4 py-3 shadow-lg"
            >
                <CheckCircleIcon class="h-5 w-5 text-emerald-600" />
                <p class="text-sm text-slate-800">{{ flash.success }}</p>
            </div>
            <div
                v-else-if="flash.error"
                class="flex items-center gap-3 rounded-lg border border-rose-200 bg-white px-4 py-3 shadow-lg"
            >
                <XCircleIcon class="h-5 w-5 text-rose-600" />
                <p class="text-sm text-slate-800">{{ flash.error }}</p>
            </div>
        </div>
    </Transition>
</template>
