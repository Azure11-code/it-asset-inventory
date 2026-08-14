<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    PhotoIcon, DocumentIcon, DocumentTextIcon, MagnifyingGlassIcon,
    ArrowDownTrayIcon, ArrowTopRightOnSquareIcon, XMarkIcon,
    ChevronLeftIcon, ChevronRightIcon, FolderOpenIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    attachments:         Object,
    filters:             Object,
    available_entities:  Array,
    totals:              Object,
});

const search = ref(props.filters?.search ?? '');
const entity = ref(props.filters?.entity ?? '');
const kind   = ref(props.filters?.kind   ?? '');

let searchTimer = null;
const applyFilters = () => {
    router.get('/gallery', {
        search: search.value || undefined,
        entity: entity.value || undefined,
        kind:   kind.value   || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
};

const setEntity = (v) => { entity.value = v; applyFilters(); };
const setKind   = (v) => { kind.value   = v; applyFilters(); };

const formatSize = (b) => {
    if (!b) return '';
    if (b < 1024) return b + ' B';
    if (b < 1024*1024) return (b/1024).toFixed(1) + ' KB';
    return (b/1024/1024).toFixed(1) + ' MB';
};

const iconFor = (mime) => {
    if (mime?.includes('image')) return PhotoIcon;
    if (mime?.includes('pdf'))   return DocumentTextIcon;
    return DocumentIcon;
};

const extBadge = (name) => {
    const ext = (name || '').split('.').pop()?.toLowerCase();
    return ext || '';
};

const entityBadgeClass = (slug) => ({
    'bg-sky-500/90 text-white':      slug === 'recommendations',
    'bg-rose-500/90 text-white':     slug === 'incidents',
    'bg-emerald-500/90 text-white':  slug === 'permits',
    'bg-amber-500/90 text-white':    slug === 'accountability',
}[slug === 'recommendations' ? 'bg-sky-500/90 text-white'
    : slug === 'incidents'   ? 'bg-rose-500/90 text-white'
    : slug === 'permits'     ? 'bg-emerald-500/90 text-white'
    : 'bg-amber-500/90 text-white'] ? '' : '');

// Lightbox — supports both image and PDF preview
const lightboxIndex = ref(-1);
const previewables = computed(() => props.attachments.data.filter(a => a.is_previewable));

const handleTileClick = (a) => {
    if (a.is_previewable) {
        const idx = previewables.value.findIndex(x => x.id === a.id);
        if (idx >= 0) lightboxIndex.value = idx;
    } else {
        window.open(a.download_url, '_blank');
    }
};

const closeLightbox = () => { lightboxIndex.value = -1; };
const nextItem = () => {
    if (lightboxIndex.value < previewables.value.length - 1) lightboxIndex.value++;
};
const prevItem = () => {
    if (lightboxIndex.value > 0) lightboxIndex.value--;
};
const active = computed(() => lightboxIndex.value >= 0 ? previewables.value[lightboxIndex.value] : null);

const onKey = (e) => {
    if (lightboxIndex.value < 0) return;
    if (e.key === 'Escape')     closeLightbox();
    if (e.key === 'ArrowRight') nextItem();
    if (e.key === 'ArrowLeft')  prevItem();
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <AppLayout>
        <template #header>
            <PageHeader title="Gallery" subtitle="All uploaded files across accountability, permits, incidents, and recommendations.">
                <template #actions>
                    <div class="text-xs text-slate-500">
                        <span class="font-semibold text-slate-700">{{ totals.all }}</span> total files
                    </div>
                </template>
            </PageHeader>
        </template>

        <!-- Toolbar -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-slate-400" />
                <input
                    v-model="search"
                    @input="onSearchInput"
                    type="text"
                    placeholder="Search filename or label…"
                    class="w-full rounded-md border border-slate-200 bg-white pl-8 pr-3 py-1.5 text-[12.5px] placeholder-slate-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                />
            </div>
            <div class="text-xs text-slate-500">
                Showing <b>{{ attachments.from ?? 0 }}–{{ attachments.to ?? 0 }}</b> of <b>{{ attachments.total }}</b>
            </div>
        </div>

        <!-- Filter chips -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button type="button" :class="['chip', kind === '' ? 'chip-active' : '']" @click="setKind('')">
                All <span class="chip-count">{{ totals.all }}</span>
            </button>
            <button type="button" :class="['chip', kind === 'images' ? 'chip-active' : '']" @click="setKind('images')">
                <PhotoIcon class="h-3.5 w-3.5" /> Images <span class="chip-count">{{ totals.images }}</span>
            </button>
            <button type="button" :class="['chip', kind === 'pdf' ? 'chip-active' : '']" @click="setKind('pdf')">
                <DocumentTextIcon class="h-3.5 w-3.5" /> PDF <span class="chip-count">{{ totals.pdf }}</span>
            </button>
            <button type="button" :class="['chip', kind === 'docs' ? 'chip-active' : '']" @click="setKind('docs')">
                <DocumentIcon class="h-3.5 w-3.5" /> Docs <span class="chip-count">{{ totals.docs }}</span>
            </button>
            <span class="mx-1 h-4 border-l border-slate-200"></span>
            <button type="button" :class="['chip', entity === '' ? 'chip-active' : '']" @click="setEntity('')">
                All Sources
            </button>
            <button
                v-for="e in available_entities"
                :key="e.slug"
                type="button"
                :class="['chip', entity === e.slug ? 'chip-active' : '']"
                @click="setEntity(e.slug)"
            >
                <FolderOpenIcon class="h-3.5 w-3.5" /> {{ e.label }} <span class="chip-count">{{ totals.by_entity[e.slug] ?? 0 }}</span>
            </button>
        </div>

        <!-- Grid -->
        <div v-if="attachments.data.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            <div
                v-for="a in attachments.data"
                :key="a.id"
                class="group relative flex flex-col rounded-lg border border-slate-200 bg-white overflow-hidden hover:shadow-md hover:border-brand-300 transition-all cursor-pointer"
                @click="handleTileClick(a)"
            >
                <!-- Thumbnail -->
                <div class="aspect-square bg-slate-50 flex items-center justify-center overflow-hidden relative">
                    <img
                        v-if="a.is_image"
                        :src="a.preview_url"
                        :alt="a.original_name"
                        loading="lazy"
                        class="h-full w-full object-cover transition-transform group-hover:scale-105"
                    />
                    <div v-else class="flex flex-col items-center gap-1.5 text-slate-400 px-2">
                        <component :is="iconFor(a.mime_type)" class="h-12 w-12" :class="a.is_pdf ? 'text-rose-400' : 'text-slate-400'" />
                        <span class="text-[10px] font-semibold uppercase tracking-wide">{{ extBadge(a.original_name) }}</span>
                    </div>
                    <!-- Entity badge -->
                    <span
                        :class="[
                            'absolute top-1.5 left-1.5 inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide backdrop-blur-sm',
                            a.entity_slug === 'recommendations' ? 'bg-sky-500/90 text-white'      : '',
                            a.entity_slug === 'incidents'       ? 'bg-rose-500/90 text-white'     : '',
                            a.entity_slug === 'permits'         ? 'bg-emerald-500/90 text-white'  : '',
                            a.entity_slug === 'accountability'  ? 'bg-amber-500/90 text-white'    : '',
                        ]"
                    >{{ a.entity_label }}</span>
                    <!-- Preview hint -->
                    <span
                        v-if="a.is_previewable"
                        class="absolute bottom-1.5 right-1.5 inline-flex items-center rounded-md bg-black/60 text-white px-1.5 py-0.5 text-[9px] font-medium opacity-0 group-hover:opacity-100 transition-opacity"
                    >Preview</span>
                </div>

                <!-- Meta -->
                <div class="p-2 space-y-0.5">
                    <div class="text-[11.5px] font-medium text-slate-900 truncate" :title="a.original_name">
                        {{ a.label || a.original_name }}
                    </div>
                    <div class="text-[10px] text-slate-500 truncate">
                        <Link :href="a.source_url" @click.stop class="hover:text-brand-600 hover:underline">
                            {{ a.parent_title }}
                        </Link>
                        <span v-if="a.parent_subtitle" class="text-slate-400"> · {{ a.parent_subtitle }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                        <span>{{ formatSize(a.size_bytes) }}</span>
                        <span>{{ a.created_at }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-16 text-slate-500 border border-dashed border-slate-300 rounded-lg bg-slate-50/50">
            <PhotoIcon class="h-14 w-14 mx-auto text-slate-300" />
            <p class="mt-3 text-sm">No files found matching your filters.</p>
        </div>

        <!-- Pagination -->
        <Pagination v-if="attachments.links && attachments.data.length" :links="attachments.links" class="mt-6" />

        <!-- Lightbox -->
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="active"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
                @click.self="closeLightbox"
            >
                <button
                    class="absolute top-4 right-4 rounded-full bg-white/10 hover:bg-white/20 text-white p-2 transition-colors"
                    title="Close (Esc)"
                    @click="closeLightbox"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>

                <button
                    v-if="lightboxIndex > 0"
                    class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 hover:bg-white/20 text-white p-2 transition-colors"
                    title="Previous (←)"
                    @click.stop="prevItem"
                >
                    <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <button
                    v-if="lightboxIndex < previewables.length - 1"
                    class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 hover:bg-white/20 text-white p-2 transition-colors"
                    title="Next (→)"
                    @click.stop="nextItem"
                >
                    <ChevronRightIcon class="h-5 w-5" />
                </button>

                <div class="w-full max-w-5xl h-full flex flex-col items-center justify-center" @click.self="closeLightbox">
                    <!-- Image preview -->
                    <img
                        v-if="active.is_image"
                        :src="active.preview_url"
                        :alt="active.original_name"
                        class="max-h-[78vh] max-w-full object-contain rounded shadow-2xl"
                        @click.stop
                    />
                    <!-- PDF preview via iframe -->
                    <div
                        v-else-if="active.is_pdf"
                        class="w-full h-[78vh] bg-white rounded shadow-2xl overflow-hidden"
                        @click.stop
                    >
                        <iframe
                            :src="active.preview_url"
                            class="w-full h-full"
                            :title="active.original_name"
                        />
                    </div>

                    <!-- Info + actions -->
                    <div class="mt-4 text-center text-white/95 text-sm max-w-2xl" @click.stop>
                        <div class="font-medium truncate">{{ active.label || active.original_name }}</div>
                        <div class="text-white/60 text-xs mt-1">
                            <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide mr-2"
                                :class="[
                                    active.entity_slug === 'recommendations' ? 'bg-sky-500/90 text-white'      : '',
                                    active.entity_slug === 'incidents'       ? 'bg-rose-500/90 text-white'     : '',
                                    active.entity_slug === 'permits'         ? 'bg-emerald-500/90 text-white'  : '',
                                    active.entity_slug === 'accountability'  ? 'bg-amber-500/90 text-white'    : '',
                                ]"
                            >{{ active.entity_label }}</span>
                            {{ active.parent_title }}
                            <span v-if="active.parent_subtitle"> · {{ active.parent_subtitle }}</span>
                            <span class="opacity-70"> · {{ formatSize(active.size_bytes) }} · {{ active.created_at }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 justify-center">
                            <a
                                :href="active.download_url"
                                class="inline-flex items-center gap-1.5 rounded-md bg-white/10 hover:bg-white/20 border border-white/20 text-white px-3 py-1.5 text-xs font-medium transition-colors"
                            >
                                <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Download
                            </a>
                            <a
                                :href="active.preview_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 rounded-md bg-white/10 hover:bg-white/20 border border-white/20 text-white px-3 py-1.5 text-xs font-medium transition-colors"
                            >
                                <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" /> Open in new tab
                            </a>
                            <Link
                                :href="active.source_url"
                                class="inline-flex items-center gap-1.5 rounded-md bg-white/10 hover:bg-white/20 border border-white/20 text-white px-3 py-1.5 text-xs font-medium transition-colors"
                            >
                                <FolderOpenIcon class="h-3.5 w-3.5" /> View source
                            </Link>
                        </div>
                        <div class="mt-2 text-[10px] text-white/40">
                            {{ lightboxIndex + 1 }} / {{ previewables.length }} · Esc to close · ← → to navigate
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<style scoped>
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 9999px;
    border: 1px solid rgb(226 232 240);
    background: white;
    padding: 0.25rem 0.625rem;
    font-size: 11px;
    font-weight: 500;
    color: rgb(71 85 105);
    transition: colors 0.15s;
    cursor: pointer;
}
.chip:hover {
    border-color: rgb(147 197 253);
    color: rgb(29 78 216);
}
.chip-active {
    border-color: rgb(59 130 246);
    background: rgb(239 246 255);
    color: rgb(29 78 216);
}
.chip-count {
    margin-left: 0.125rem;
    border-radius: 9999px;
    background: rgb(241 245 249);
    padding: 0 0.375rem;
    font-size: 10px;
    font-weight: 600;
    color: rgb(100 116 139);
}
.chip-active .chip-count {
    background: rgb(219 234 254);
    color: rgb(29 78 216);
}
</style>
