<script setup>
import { ref, computed } from 'vue';
import { XMarkIcon, Bars3Icon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Type a value and press Enter' },
    tone: { type: String, default: 'brand' },
});
const emit = defineEmits(['update:modelValue']);

const text = ref('');
const items = computed(() => Array.isArray(props.modelValue) ? props.modelValue : []);

const add = () => {
    const val = text.value.trim();
    if (!val) return;
    if (items.value.includes(val)) {
        text.value = '';
        return;
    }
    emit('update:modelValue', [...items.value, val]);
    text.value = '';
};

const remove = (i) => {
    const next = [...items.value];
    next.splice(i, 1);
    emit('update:modelValue', next);
};

const onBackspace = (e) => {
    if (text.value === '' && items.value.length) {
        e.preventDefault();
        remove(items.value.length - 1);
    }
};

// ── Drag-and-drop reordering ──
const dragIndex = ref(null);
const overIndex = ref(null);

const onDragStart = (i, e) => {
    dragIndex.value = i;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(i));
    // Use a subtle drag image
    if (e.dataTransfer.setDragImage && e.target?.cloneNode) {
        const ghost = e.target.cloneNode(true);
        ghost.style.opacity = '0.6';
        ghost.style.position = 'absolute';
        ghost.style.top = '-1000px';
        document.body.appendChild(ghost);
        e.dataTransfer.setDragImage(ghost, 10, 10);
        setTimeout(() => ghost.remove(), 0);
    }
};

const onDragOver = (i, e) => {
    e.preventDefault();
    overIndex.value = i;
    e.dataTransfer.dropEffect = 'move';
};

const onDrop = (i, e) => {
    e.preventDefault();
    if (dragIndex.value === null || dragIndex.value === i) {
        dragIndex.value = null;
        overIndex.value = null;
        return;
    }
    const next = [...items.value];
    const [moved] = next.splice(dragIndex.value, 1);
    next.splice(i, 0, moved);
    emit('update:modelValue', next);
    dragIndex.value = null;
    overIndex.value = null;
};

const onDragEnd = () => {
    dragIndex.value = null;
    overIndex.value = null;
};

// Up/down keyboard support — Alt+ArrowLeft/Right while focused on a chip
const moveBy = (i, delta) => {
    const target = i + delta;
    if (target < 0 || target >= items.value.length) return;
    const next = [...items.value];
    [next[i], next[target]] = [next[target], next[i]];
    emit('update:modelValue', next);
};
</script>

<template>
    <div class="chip-input">
        <div class="flex flex-wrap items-center gap-1.5 rounded-md ring-1 ring-inset ring-slate-200 bg-white p-1.5 focus-within:ring-2 focus-within:ring-brand-500">
            <span
                v-for="(item, i) in items"
                :key="`${item}-${i}`"
                :class="[
                    'group inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium cursor-grab select-none transition-opacity',
                    `badge-${tone}`,
                    dragIndex === i  ? 'opacity-30' : '',
                    overIndex === i && dragIndex !== i ? 'ring-2 ring-brand-500 ring-offset-1' : '',
                ]"
                draggable="true"
                tabindex="0"
                @dragstart="onDragStart(i, $event)"
                @dragover="onDragOver(i, $event)"
                @drop="onDrop(i, $event)"
                @dragend="onDragEnd"
                @keydown.alt.left.prevent="moveBy(i, -1)"
                @keydown.alt.right.prevent="moveBy(i, 1)"
            >
                <Bars3Icon class="h-3 w-3 opacity-50 group-hover:opacity-80" />
                <span>{{ item }}</span>
                <button
                    type="button"
                    class="rounded p-0.5 text-current/70 hover:bg-white/40"
                    @click.stop="remove(i)"
                    @mousedown.stop
                    aria-label="Remove"
                >
                    <XMarkIcon class="h-3 w-3" />
                </button>
            </span>
            <input
                v-model="text"
                type="text"
                class="min-w-[8rem] flex-1 border-0 bg-transparent text-sm placeholder:text-slate-400 focus:outline-none focus:ring-0 px-1 py-1"
                :placeholder="items.length ? 'Add another...' : placeholder"
                @keydown.enter.prevent="add"
                @keydown="(e) => { if (e.key === ',') { e.preventDefault(); add(); } }"
                @keydown.backspace="onBackspace"
                @blur="add"
            />
        </div>
        <p class="help">
            Press <kbd class="rounded border px-1">Enter</kbd> or <kbd class="rounded border px-1">,</kbd> to add ·
            Drag chips to reorder · <kbd class="rounded border px-1">Alt</kbd>+<kbd class="rounded border px-1">←/→</kbd> when focused
        </p>
    </div>
</template>
