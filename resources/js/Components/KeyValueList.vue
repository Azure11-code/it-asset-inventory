<script setup>
import { computed, ref } from 'vue';
import { PlusIcon, XMarkIcon, Bars3Icon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    keyPlaceholder: { type: String, default: 'Label (e.g. Processor)' },
    valuePlaceholder: { type: String, default: 'Value (e.g. Intel i5-11400)' },
    presets: {
        type: Array,
        default: () => [
            'OS Name', 'Version', 'System Manufacturer', 'System Model',
            'System Type', 'Processor', 'BIOS Mode', 'Installed Physical Memory (RAM)',
            'Storage', 'Graphics', 'Display Size', 'MAC Address', 'Hostname',
        ],
    },
});
const emit = defineEmits(['update:modelValue']);

// Normalize legacy string items into {key, value} pairs (with empty key)
const normalized = computed(() => {
    const raw = Array.isArray(props.modelValue) ? props.modelValue : [];
    return raw.map((item) => {
        if (item && typeof item === 'object') {
            return { key: String(item.key ?? ''), value: String(item.value ?? '') };
        }
        return { key: '', value: String(item ?? '') };
    });
});

const update = (rows) => {
    const clean = rows
        .map((r) => ({ key: r.key.trim(), value: r.value.trim() }))
        .filter((r) => r.key || r.value);
    emit('update:modelValue', clean);
};

const setKey = (i, val) => {
    const rows = normalized.value.map((r, idx) => idx === i ? { ...r, key: val } : r);
    emit('update:modelValue', rows);
};
const setVal = (i, val) => {
    const rows = normalized.value.map((r, idx) => idx === i ? { ...r, value: val } : r);
    emit('update:modelValue', rows);
};

const addRow = (preset = null) => {
    const rows = [...normalized.value, { key: preset ?? '', value: '' }];
    emit('update:modelValue', rows);
};

const removeRow = (i) => {
    const rows = normalized.value.filter((_, idx) => idx !== i);
    emit('update:modelValue', rows);
};

const dragIndex = ref(null);
const overIndex = ref(null);
const onDragStart = (i, e) => {
    dragIndex.value = i;
    e.dataTransfer.effectAllowed = 'move';
};
const onDragOver = (i, e) => {
    e.preventDefault();
    overIndex.value = i;
};
const onDrop = (i, e) => {
    e.preventDefault();
    if (dragIndex.value === null || dragIndex.value === i) { dragIndex.value = null; overIndex.value = null; return; }
    const next = [...normalized.value];
    const [moved] = next.splice(dragIndex.value, 1);
    next.splice(i, 0, moved);
    emit('update:modelValue', next);
    dragIndex.value = null; overIndex.value = null;
};
const onDragEnd = () => { dragIndex.value = null; overIndex.value = null; };

const unusedPresets = computed(() => {
    const used = new Set(normalized.value.map((r) => r.key.toLowerCase()));
    return props.presets.filter((p) => !used.has(p.toLowerCase()));
});
</script>

<template>
    <div class="kv-list">
        <div v-if="normalized.length === 0" class="kv-empty">
            No specifications yet. Add fields below.
        </div>

        <div v-else class="space-y-1.5">
            <div
                v-for="(row, i) in normalized"
                :key="i"
                :class="['kv-row', overIndex === i && dragIndex !== i ? 'kv-row--over' : '', dragIndex === i ? 'kv-row--dragging' : '']"
                @dragover="onDragOver(i, $event)"
                @drop="onDrop(i, $event)"
            >
                <button
                    type="button"
                    class="kv-handle"
                    draggable="true"
                    @dragstart="onDragStart(i, $event)"
                    @dragend="onDragEnd"
                    title="Drag to reorder"
                >
                    <Bars3Icon class="h-4 w-4" />
                </button>
                <input
                    type="text"
                    class="input kv-key"
                    :value="row.key"
                    @input="(e) => setKey(i, e.target.value)"
                    :placeholder="keyPlaceholder"
                />
                <input
                    type="text"
                    class="input kv-val"
                    :value="row.value"
                    @input="(e) => setVal(i, e.target.value)"
                    :placeholder="valuePlaceholder"
                />
                <button
                    type="button"
                    class="kv-remove"
                    @click="removeRow(i)"
                    title="Remove"
                >
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <button type="button" class="btn-secondary !py-1 !px-2 text-xs" @click="addRow()">
                <PlusIcon class="h-3.5 w-3.5" /> Add field
            </button>
            <span v-if="unusedPresets.length" class="text-xs text-slate-400">or quick add:</span>
            <button
                v-for="p in unusedPresets"
                :key="p"
                type="button"
                class="kv-preset"
                @click="addRow(p)"
            >
                {{ p }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.kv-empty {
    padding: 0.75rem; border: 1px dashed rgb(203 213 225);
    border-radius: 0.5rem; color: rgb(100 116 139); font-size: 0.875rem; text-align: center;
}
.kv-row {
    display: grid;
    grid-template-columns: auto minmax(8rem, 14rem) 1fr auto;
    align-items: center; gap: 0.5rem;
    padding: 0.25rem; border-radius: 0.375rem;
}
.kv-row--over { background: rgb(238 242 255); }
.kv-row--dragging { opacity: 0.4; }
.kv-handle {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.75rem; height: 1.75rem; border-radius: 0.375rem;
    color: rgb(148 163 184); cursor: grab; background: transparent;
}
.kv-handle:hover { background: rgb(241 245 249); color: rgb(71 85 105); }
.kv-key { font-weight: 600; }
.kv-remove {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.75rem; height: 1.75rem; border-radius: 0.375rem;
    color: rgb(100 116 139); background: transparent;
}
.kv-remove:hover { background: rgb(254 226 226); color: rgb(190 18 60); }

.kv-preset {
    display: inline-flex; align-items: center;
    padding: 0.2rem 0.55rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 500;
    background: rgb(241 245 249); color: rgb(71 85 105);
    border: 1px solid rgb(226 232 240);
}
.kv-preset:hover { background: rgb(238 242 255); color: rgb(67 56 202); border-color: rgb(199 210 254); }

@media (max-width: 640px) {
    .kv-row { grid-template-columns: auto 1fr auto; }
    .kv-val { grid-column: 2 / 4; }
}
</style>
