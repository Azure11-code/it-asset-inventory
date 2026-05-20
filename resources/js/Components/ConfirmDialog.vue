<script setup>
import Modal from './Modal.vue';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

defineProps({
    show: Boolean,
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: 'This action cannot be undone.' },
    confirmText: { type: String, default: 'Delete' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" :title="title" max-width="md" @close="emit('close')">
        <div class="p-5">
            <div class="flex gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                    <ExclamationTriangleIcon class="h-5 w-5 text-rose-600" />
                </div>
                <p class="text-sm text-slate-600">{{ message }}</p>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button class="btn-secondary" :disabled="processing" @click="emit('close')">Cancel</button>
            <button
                class="inline-flex items-center justify-center gap-2 rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:opacity-50"
                :disabled="processing"
                @click="emit('confirm')"
            >
                {{ processing ? 'Working...' : confirmText }}
            </button>
        </div>
    </Modal>
</template>
