<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount, onMounted } from 'vue';
import { XMarkIcon, ChevronLeftIcon, ChevronRightIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    steps: { type: Array, required: true }, // [{ target, title, body, placement? }]
});
const emit = defineEmits(['close', 'finish']);

const index = ref(0);
const targetRect = ref(null);
const placement = ref('bottom');

const total = computed(() => props.steps.length);
const current = computed(() => props.steps[index.value] || null);
const progress = computed(() => total.value === 0 ? 0 : ((index.value + 1) / total.value) * 100);

const PADDING = 8;

const recalc = async () => {
    await nextTick();
    if (!current.value) return;
    const sel = current.value.target;
    const el = sel ? document.querySelector(sel) : null;
    if (!el) { targetRect.value = null; return; }
    el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    await new Promise((r) => setTimeout(r, 220));
    const r = el.getBoundingClientRect();
    targetRect.value = {
        top:    r.top    - PADDING,
        left:   r.left   - PADDING,
        width:  r.width  + PADDING * 2,
        height: r.height + PADDING * 2,
    };
    placement.value = current.value.placement || (r.top < window.innerHeight / 2 ? 'bottom' : 'top');
};

watch(() => [props.show, index.value], () => { if (props.show) recalc(); });
watch(() => props.show, (v) => { if (v) index.value = 0; });

const onResize = () => { if (props.show) recalc(); };
onMounted(() => {
    window.addEventListener('resize', onResize);
    window.addEventListener('scroll', onResize, true);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    window.removeEventListener('scroll', onResize, true);
});

const next = () => {
    if (index.value >= total.value - 1) {
        emit('finish');
    } else {
        index.value++;
    }
};
const prev = () => { if (index.value > 0) index.value--; };
const skip = () => emit('close');

const tipStyle = computed(() => {
    const isMobile = typeof window !== 'undefined' && window.innerWidth < 640;

    // On mobile, pin the tip to the bottom of the viewport (like a bottom sheet).
    if (isMobile) {
        return {
            bottom: '12px',
            left:   '12px',
            right:  '12px',
            width:  'auto',
        };
    }

    if (!targetRect.value) {
        return { left: '50%', top: '50%', transform: 'translate(-50%, -50%)' };
    }
    const r = targetRect.value;
    const margin = 12;
    const tipWidth = 360;
    if (placement.value === 'bottom') {
        return {
            top:  `${r.top + r.height + margin}px`,
            left: `${Math.min(window.innerWidth - tipWidth - 16, Math.max(16, r.left))}px`,
        };
    }
    return {
        top:  `${Math.max(16, r.top - margin)}px`,
        left: `${Math.min(window.innerWidth - tipWidth - 16, Math.max(16, r.left))}px`,
        transform: 'translateY(-100%)',
    };
});

const spotlightStyle = computed(() => {
    if (!targetRect.value) return { display: 'none' };
    const r = targetRect.value;
    return {
        top:    `${r.top}px`,
        left:   `${r.left}px`,
        width:  `${r.width}px`,
        height: `${r.height}px`,
    };
});
</script>

<template>
    <Teleport to="body">
        <Transition name="tour-fade">
            <div v-if="show && current" class="tour-root" role="dialog" aria-modal="true" aria-label="Product tour">
                <!-- Dimmer with spotlight cutout -->
                <div class="tour-dim" @click="skip"></div>
                <div class="tour-spotlight" :style="spotlightStyle"></div>

                <!-- Tooltip card -->
                <div class="tour-tip" :style="tipStyle">
                    <div class="tour-tip__head">
                        <div class="tour-tip__step">Step {{ index + 1 }} of {{ total }}</div>
                        <button class="tour-tip__close" @click="skip" aria-label="Skip tour">
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>
                    <h3 class="tour-tip__title">{{ current.title }}</h3>
                    <p class="tour-tip__body">{{ current.body }}</p>
                    <div class="tour-tip__progress"><div :style="{ width: progress + '%' }"></div></div>
                    <div class="tour-tip__actions">
                        <button v-if="index > 0" class="btn-secondary !py-1.5 !px-3 text-xs" @click="prev">
                            <ChevronLeftIcon class="h-3.5 w-3.5" /> Back
                        </button>
                        <button v-else class="btn-secondary !py-1.5 !px-3 text-xs" @click="skip">
                            Skip tour
                        </button>
                        <button class="btn-primary !py-1.5 !px-3 text-xs" @click="next">
                            <template v-if="index >= total - 1">
                                <CheckIcon class="h-3.5 w-3.5" /> Finish
                            </template>
                            <template v-else>
                                Next <ChevronRightIcon class="h-3.5 w-3.5" />
                            </template>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.tour-root { position: fixed; inset: 0; z-index: 100; pointer-events: none; }
.tour-dim {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
    pointer-events: auto;
}
.tour-spotlight {
    position: absolute;
    border-radius: 12px;
    box-shadow:
        0 0 0 9999px rgba(15, 23, 42, 0.55),
        0 0 0 3px rgba(99, 102, 241, 0.9),
        0 0 20px 4px rgba(99, 102, 241, 0.5);
    transition: top 220ms ease, left 220ms ease, width 220ms ease, height 220ms ease;
    pointer-events: none;
}
.tour-tip {
    position: absolute;
    width: 360px; max-width: calc(100vw - 24px);
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid rgb(226 232 240);
    box-shadow: 0 22px 50px -12px rgba(15, 23, 42, 0.4);
    padding: 14px 14px 12px;
    pointer-events: auto;
    transition: top 220ms ease, left 220ms ease;
}
@media (max-width: 639px) {
    .tour-tip {
        width: auto;
        max-width: none;
        border-radius: 14px;
        padding: 14px;
    }
    .tour-tip__title { font-size: 15px; }
    .tour-tip__body  { font-size: 12.5px; }
}
.tour-tip__head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 6px;
}
.tour-tip__step {
    font-size: 10px; font-weight: 600; letter-spacing: 0.14em;
    text-transform: uppercase; color: rgb(99 102 241);
}
.tour-tip__close {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.5rem; height: 1.5rem; border-radius: 9999px;
    color: rgb(100 116 139);
}
.tour-tip__close:hover { background: rgb(241 245 249); color: rgb(15 23 42); }
.tour-tip__title { font-size: 16px; font-weight: 600; color: rgb(15 23 42); margin: 0; }
.tour-tip__body  { margin-top: 6px; font-size: 13px; line-height: 1.55; color: rgb(71 85 105); }
.tour-tip__progress {
    margin: 14px 0 12px;
    height: 4px; background: rgb(241 245 249); border-radius: 9999px; overflow: hidden;
}
.tour-tip__progress > div {
    height: 100%; background: linear-gradient(90deg, rgb(99 102 241), rgb(34 211 238));
    transition: width 220ms ease;
}
.tour-tip__actions { display: flex; justify-content: space-between; gap: 8px; }

.tour-fade-enter-active, .tour-fade-leave-active { transition: opacity 200ms ease; }
.tour-fade-enter-from,   .tour-fade-leave-to     { opacity: 0; }
</style>
