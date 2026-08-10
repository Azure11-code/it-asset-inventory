<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount, onMounted } from 'vue';
import { ChevronUpDownIcon, XMarkIcon, CheckIcon, PencilIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    customText: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    valueKey: { type: String, default: 'id' },
    labelKey: { type: String, default: 'name' },
    placeholder: { type: String, default: 'Select…' },
    nullable: { type: Boolean, default: true },
    nullLabel: { type: String, default: '— None —' },
    disabled: { type: Boolean, default: false },
    allowCustom: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:customText']);

const open = ref(false);
const query = ref('');
const activeIndex = ref(-1);
const rootEl = ref(null);
const inputEl = ref(null);
const listEl = ref(null);

const selected = computed(() =>
    props.options.find((o) => String(o[props.valueKey]) === String(props.modelValue)) || null
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => String(o[props.labelKey] ?? '').toLowerCase().includes(q));
});

// When closed, show either: selected option's label, OR custom text, OR empty
const displayValue = computed(() => {
    if (open.value) return query.value;
    if (selected.value) return selected.value[props.labelKey];
    if (props.allowCustom && props.customText) return props.customText;
    return '';
});

const trimmedQuery = computed(() => query.value.trim());
const exactMatchInOptions = computed(() =>
    props.options.some((o) => String(o[props.labelKey] ?? '').toLowerCase() === trimmedQuery.value.toLowerCase())
);

const openMenu = () => {
    if (props.disabled) return;
    open.value = true;
    // Seed query from custom text if we're in custom mode and no option is selected
    query.value = (!selected.value && props.allowCustom && props.customText) ? props.customText : '';
    activeIndex.value = filtered.value.findIndex((o) => String(o[props.valueKey]) === String(props.modelValue));
    nextTick(() => {
        inputEl.value?.select();
        scrollActiveIntoView();
    });
};

const closeMenu = () => {
    open.value = false;
    query.value = '';
    activeIndex.value = -1;
};

const choose = (opt) => {
    emit('update:modelValue', opt ? opt[props.valueKey] : null);
    if (props.allowCustom) emit('update:customText', '');
    closeMenu();
};

const chooseCustom = () => {
    const text = trimmedQuery.value;
    if (!text) return;
    emit('update:modelValue', null);
    emit('update:customText', text);
    closeMenu();
};

const clear = (e) => {
    e?.stopPropagation();
    emit('update:modelValue', null);
    if (props.allowCustom) emit('update:customText', '');
    closeMenu();
};

const onKeydown = (e) => {
    if (!open.value && (e.key === 'ArrowDown' || e.key === 'Enter')) {
        openMenu();
        e.preventDefault();
        return;
    }
    if (!open.value) return;
    const max = filtered.value.length - 1;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value = activeIndex.value >= max ? 0 : activeIndex.value + 1;
        scrollActiveIntoView();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value = activeIndex.value <= 0 ? max : activeIndex.value - 1;
        scrollActiveIntoView();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex.value >= 0 && filtered.value[activeIndex.value]) {
            choose(filtered.value[activeIndex.value]);
        } else if (props.allowCustom && trimmedQuery.value) {
            chooseCustom();
        }
    } else if (e.key === 'Escape') {
        closeMenu();
    } else if (e.key === 'Tab' && props.allowCustom && trimmedQuery.value && activeIndex.value < 0) {
        chooseCustom();
    }
};

const scrollActiveIntoView = () => {
    nextTick(() => {
        const list = listEl.value;
        if (!list) return;
        const node = list.querySelector(`[data-idx="${activeIndex.value}"]`);
        if (node) node.scrollIntoView({ block: 'nearest' });
    });
};

const onClickOutside = (e) => {
    const inRoot = rootEl.value && rootEl.value.contains(e.target);
    const inMenu = listEl.value && listEl.value.contains(e.target);
    if (!inRoot && !inMenu) {
        // If user typed but didn't pick, accept as custom text when allowed
        if (open.value && props.allowCustom && trimmedQuery.value && !exactMatchInOptions.value) {
            chooseCustom();
        } else {
            closeMenu();
        }
    }
};

watch(open, (v) => {
    if (v) document.addEventListener('mousedown', onClickOutside);
    else document.removeEventListener('mousedown', onClickOutside);
});

onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));

watch(query, () => { activeIndex.value = filtered.value.length ? 0 : -1; });

const isCustomMode = computed(() => props.allowCustom && !selected.value && !!props.customText);

// ── Teleport positioning ──
const menuStyle = ref({});
const updateMenuPosition = () => {
    if (!rootEl.value) return;
    const r = rootEl.value.getBoundingClientRect();
    const menuMax = 256; // matches max-height
    const gap = 4;
    const fitsBelow = window.innerHeight - r.bottom > menuMax + 16;
    const top = fitsBelow ? r.bottom + gap : Math.max(8, r.top - gap - menuMax);
    menuStyle.value = {
        position: 'fixed',
        left: `${r.left}px`,
        top: `${top}px`,
        width: `${r.width}px`,
        zIndex: 9999,
    };
};
const onWindowScrollOrResize = () => { if (open.value) updateMenuPosition(); };

watch(open, async (v) => {
    if (v) {
        await nextTick();
        updateMenuPosition();
        window.addEventListener('scroll', onWindowScrollOrResize, true);
        window.addEventListener('resize', onWindowScrollOrResize);
    } else {
        window.removeEventListener('scroll', onWindowScrollOrResize, true);
        window.removeEventListener('resize', onWindowScrollOrResize);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onWindowScrollOrResize, true);
    window.removeEventListener('resize', onWindowScrollOrResize);
});
</script>

<template>
    <div ref="rootEl" class="combobox" :class="{ 'combobox--open': open, 'combobox--disabled': disabled }">
        <div class="combobox__control" @click="openMenu">
            <input
                ref="inputEl"
                :value="displayValue"
                @input="(e) => { query = e.target.value; if (!open) openMenu(); }"
                @focus="openMenu"
                @keydown="onKeydown"
                :placeholder="placeholder"
                :disabled="disabled"
                type="text"
                class="combobox__input input pr-20"
                autocomplete="off"
                spellcheck="false"
            />
            <div class="combobox__icons">
                <PencilIcon v-if="isCustomMode" class="h-3.5 w-3.5 text-amber-500" title="Custom text" />
                <button
                    v-if="nullable && (selected || isCustomMode) && !disabled"
                    type="button"
                    class="combobox__clear"
                    title="Clear"
                    @click="clear"
                >
                    <XMarkIcon class="h-4 w-4" />
                </button>
                <ChevronUpDownIcon class="h-4 w-4 text-slate-400" />
            </div>
        </div>

        <Teleport to="body">
        <Transition name="pop">
        <ul v-if="open" ref="listEl" class="combobox__menu" :style="menuStyle">
            <li
                v-if="nullable && !allowCustom"
                :class="['combobox__option', !selected ? 'combobox__option--selected' : '']"
                @mousedown.prevent="choose(null)"
            >
                <span class="text-slate-500 italic">{{ nullLabel }}</span>
            </li>

            <li
                v-if="allowCustom && trimmedQuery && !exactMatchInOptions"
                class="combobox__option combobox__option--custom"
                @mousedown.prevent="chooseCustom"
            >
                <PencilIcon class="h-3.5 w-3.5 text-amber-500" />
                <span class="flex-1 truncate">
                    Use <span class="font-semibold">"{{ trimmedQuery }}"</span> as typed name
                </span>
            </li>

            <li v-if="filtered.length === 0 && !(allowCustom && trimmedQuery)" class="combobox__option combobox__option--empty">
                No matches for "{{ query }}"
            </li>

            <li
                v-for="(opt, idx) in filtered"
                :key="opt[valueKey]"
                :data-idx="idx"
                :class="[
                    'combobox__option',
                    idx === activeIndex ? 'combobox__option--active' : '',
                    String(opt[valueKey]) === String(modelValue) ? 'combobox__option--selected' : '',
                ]"
                @mousedown.prevent="choose(opt)"
                @mouseenter="activeIndex = idx"
            >
                <span class="flex-1 truncate">{{ opt[labelKey] }}</span>
                <CheckIcon
                    v-if="String(opt[valueKey]) === String(modelValue)"
                    class="h-4 w-4 text-brand-600"
                />
            </li>
        </ul>
        </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.combobox { position: relative; }
.combobox__control { position: relative; }
.combobox__input { width: 100%; cursor: text; }
.combobox--disabled .combobox__input { cursor: not-allowed; opacity: 0.6; }

.combobox__icons {
    position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%);
    display: inline-flex; align-items: center; gap: 0.25rem;
    pointer-events: none;
}
.combobox__clear {
    pointer-events: auto;
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.25rem; height: 1.25rem; border-radius: 9999px;
    color: rgb(100 116 139);
}
.combobox__clear:hover { background: rgb(241 245 249); color: rgb(15 23 42); }

.combobox__menu {
    position: absolute; z-index: 30; left: 0; right: 0; top: calc(100% + 0.25rem);
    max-height: 16rem; overflow-y: auto;
    background: #fff;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.5rem;
    box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.2);
    padding: 0.25rem;
}
.combobox__option {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.45rem 0.65rem; border-radius: 0.375rem;
    font-size: 0.875rem; color: rgb(30 41 59); cursor: pointer;
}
.combobox__option--active   { background: rgb(238 242 255); color: rgb(67 56 202); }
.combobox__option--selected { font-weight: 600; }
.combobox__option--empty    { color: rgb(100 116 139); cursor: default; font-style: italic; }
.combobox__option--custom {
    background: rgb(255 251 235); color: rgb(146 64 14);
    border: 1px dashed rgb(252 211 77);
}
.combobox__option--custom:hover { background: rgb(254 243 199); }
</style>
