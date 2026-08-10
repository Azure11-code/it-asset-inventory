<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import QrScanner from 'qr-scanner';
import { QrCodeIcon, CameraIcon, PencilSquareIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const videoEl   = ref(null);
const scanner   = ref(null);
const scanning  = ref(false);
const cameraErr = ref('');
const manualTag = ref('');
const flashOn   = ref(false);
const hasFlash  = ref(false);

// Extract a tag from any QR content — accepts:
//   1. Full URL like http://host/scan/lookup?tag=LPT-001
//   2. Legacy text QR that starts with "Asset Tag: LPT-001"
//   3. Raw tag like "LPT-001"
const extractTag = (raw) => {
    const s = String(raw || '').trim();
    if (!s) return null;
    if (/^https?:\/\//i.test(s)) {
        try {
            const url = new URL(s);
            const t = url.searchParams.get('tag');
            if (t) return t;
            const m = url.pathname.match(/\/scan\/?([^/?#]+)$/);
            if (m) return decodeURIComponent(m[1]);
        } catch { /* fall through */ }
    }
    const legacy = s.match(/Asset Tag:\s*([^\r\n]+)/i);
    if (legacy) return legacy[1].trim();
    // Otherwise treat the entire string as a tag if it looks reasonable.
    return s.length <= 100 ? s : null;
};

const goToLookup = (tag) => {
    if (!tag) return;
    router.visit(`/scan/lookup?tag=${encodeURIComponent(tag)}`);
};

const onScan = (result) => {
    const tag = extractTag(result?.data ?? result);
    if (!tag) return;
    // Guard against back-to-back duplicate reads
    if (scanner.value) scanner.value.stop();
    goToLookup(tag);
};

const startCamera = async () => {
    if (!videoEl.value) return;
    cameraErr.value = '';
    try {
        // Prefer back camera on phones
        scanner.value = new QrScanner(videoEl.value, onScan, {
            preferredCamera: 'environment',
            highlightScanRegion: true,
            highlightCodeOutline: true,
            maxScansPerSecond: 5,
        });
        await scanner.value.start();
        scanning.value = true;
        hasFlash.value = await scanner.value.hasFlash();
    } catch (err) {
        scanning.value = false;
        // Common message on LAN over plain HTTP
        cameraErr.value = /permission|denied|secure|https/i.test(err?.message || '')
            ? 'Camera access blocked. Use HTTPS or scan with your phone\'s built-in camera app (it opens this page automatically).'
            : (err?.message || 'Cannot open camera.');
    }
};

const stopCamera = () => {
    if (scanner.value) {
        scanner.value.stop();
        scanner.value.destroy();
        scanner.value = null;
    }
    scanning.value = false;
    flashOn.value  = false;
};

const toggleFlash = async () => {
    if (!scanner.value) return;
    try {
        await scanner.value.toggleFlash();
        flashOn.value = scanner.value.isFlashOn();
    } catch { /* ignore */ }
};

const submitManual = () => {
    const tag = manualTag.value.trim();
    if (tag) goToLookup(tag);
};

onMounted(startCamera);
onBeforeUnmount(stopCamera);
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Scan Asset QR" subtitle="Point your phone camera at the QR sticker on the device." />
        </template>

        <div class="mx-auto max-w-md space-y-4">
            <!-- Camera panel -->
            <section class="card overflow-hidden">
                <div class="relative bg-black" style="aspect-ratio: 1 / 1;">
                    <video ref="videoEl" class="h-full w-full object-cover" playsinline muted></video>
                    <div v-if="!scanning && !cameraErr" class="absolute inset-0 flex items-center justify-center text-white/70">
                        <CameraIcon class="h-12 w-12" />
                    </div>
                    <button v-if="hasFlash && scanning"
                            type="button"
                            class="absolute right-3 bottom-3 rounded-full bg-white/90 p-3 shadow-lg active:scale-95"
                            @click="toggleFlash">
                        <span class="text-lg">{{ flashOn ? '🔦' : '💡' }}</span>
                    </button>
                </div>
                <div class="border-t border-slate-100 p-3">
                    <div v-if="cameraErr" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3">
                        <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-amber-600" />
                        <div>
                            <p class="text-xs font-semibold text-amber-900">Camera unavailable</p>
                            <p class="mt-0.5 text-xs text-amber-800">{{ cameraErr }}</p>
                            <button type="button" class="mt-2 text-xs font-semibold text-amber-900 underline" @click="startCamera">
                                Try again
                            </button>
                        </div>
                    </div>
                    <p v-else class="text-center text-xs text-slate-500">
                        Point the camera at the QR sticker. Detection is automatic.
                    </p>
                </div>
            </section>

            <!-- Manual entry -->
            <section class="card p-4">
                <label class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <PencilSquareIcon class="h-4 w-4" /> Enter Asset Tag manually
                </label>
                <div class="flex gap-2">
                    <input v-model="manualTag" type="text" placeholder="e.g. LPT-001"
                           class="input flex-1 uppercase"
                           @keyup.enter="submitManual" />
                    <button class="btn-primary" :disabled="!manualTag.trim()" @click="submitManual">
                        <QrCodeIcon class="h-4 w-4" /> Look up
                    </button>
                </div>
                <p class="mt-2 text-[11px] text-slate-500">
                    Use this if the QR sticker is damaged or if your camera won't open.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
