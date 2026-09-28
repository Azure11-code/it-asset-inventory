<script setup>
import { ref, nextTick, computed, watch } from 'vue';
import { marked } from 'marked';
import { SparklesIcon, XMarkIcon, PaperAirplaneIcon, TrashIcon } from '@heroicons/vue/24/outline';

// Uses window.axios which has CSRF token pre-attached (see bootstrap.js).
const axios = window.axios;

// Configure marked — GFM tables, line breaks respected
marked.setOptions({ gfm: true, breaks: true });

const escapeHtml = (s) => s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

// The rendered markdown goes into v-html, and marked does not strip HTML. The
// answer text can quote database content (an asset note, an employee name),
// so raw tags in it would run as markup. Escaping first means markdown still
// renders and any HTML shows up as the literal text it was.
const renderMarkdown = (text) => {
    if (!text) return '';
    try {
        return marked.parse(escapeHtml(String(text)));
    } catch (e) {
        return escapeHtml(String(text));
    }
};

// ── Persist chat history in localStorage so it survives page navigation/refresh ──
// Cap at 50 messages and expire the whole session after 12 hours to avoid stale threads.
const STORAGE_KEY = 'it_inventory_ai_chat_v1';
const MAX_STORED = 50;
const EXPIRY_MS  = 12 * 60 * 60 * 1000; // 12 hours

const loadHistory = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        if (!parsed?.messages) return [];
        if (parsed.savedAt && (Date.now() - parsed.savedAt) > EXPIRY_MS) {
            localStorage.removeItem(STORAGE_KEY);
            return [];
        }
        return Array.isArray(parsed.messages) ? parsed.messages : [];
    } catch (e) {
        return [];
    }
};

const persistHistory = (msgs) => {
    try {
        const trimmed = msgs.slice(-MAX_STORED);
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            savedAt: Date.now(),
            messages: trimmed,
        }));
    } catch (e) { /* quota/private-mode — silently ignore */ }
};

const open = ref(false);
const input = ref('');
const messages = ref(loadHistory()); // [{ role: 'user'|'model', text, tools_used? }]
const loading = ref(false);
// Set when the server says the session lapsed — the composer is replaced by a
// reload prompt, because every further question would fail the same way.
const sessionExpired = ref(false);
const scrollAreaRef = ref(null);
const inputRef = ref(null);

// Auto-save whenever messages array mutates
watch(messages, (val) => persistHistory(val), { deep: true });

const SUGGESTED = [
    'Ilan lahat ang assets?',
    'Sino may pinakamaraming asset?',
    'Anong assets ang expiring soon?',
    'Show me stats per department',
];

const scrollToBottom = () => {
    nextTick(() => {
        const el = scrollAreaRef.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
};

const openChat = () => {
    open.value = true;
    nextTick(() => {
        inputRef.value?.focus();
        scrollToBottom();
    });
};

const clearChat = () => {
    messages.value = [];
    input.value = '';
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
};

const send = async (overrideText = null) => {
    const text = (overrideText ?? input.value).trim();
    if (!text || loading.value) return;

    messages.value.push({ role: 'user', text });
    input.value = '';
    loading.value = true;
    scrollToBottom();

    // Keep last 10 turns for context (excluding the just-added user message)
    const history = messages.value
        .slice(0, -1)
        .slice(-10)
        .map(m => ({ role: m.role, text: m.text }));

    try {
        const { data } = await axios.post('/ai/chat', { message: text, history });
        if (data.ok) {
            messages.value.push({ role: 'model', text: data.text, tools_used: data.tools_used });
        } else {
            messages.value.push({ role: 'model', text: `⚠️ ${data.error || 'Unknown error'}`, error: true });
        }
    } catch (e) {
        const resp = e.response;
        // 429 = rate limit hit — show friendly countdown
        if (resp?.status === 429 && resp.data?.rate_limit) {
            const wait = resp.data.retry_after || 30;
            messages.value.push({
                role: 'model',
                text: `⏱️ Rate limit reached. Please wait ~${wait} seconds bago mag-tanong ulit.\n\n(Free tier: 15 requests/minute. Kung mabilis mo ginagamit, hintayin lang ~1 minute at gagana ulit.)`,
                error: true,
            });
        } else if (resp?.status === 419 || resp?.status === 401) {
            // 419 = CSRF token no longer valid, 401 = signed out. Both mean the
            // session lapsed while this tab sat open; only a reload fixes it.
            sessionExpired.value = true;
            messages.value.push({
                role: 'model',
                text: '🔒 Nag-expire ang session mo habang nakabukas ang page na ito. '
                    + 'I-reload ang page para makapag-tanong ulit — hindi mawawala ang chat history.',
                error: true,
            });
        } else if (!resp) {
            messages.value.push({
                role: 'model',
                text: '⚠️ Hindi ma-abot ang server. Check your connection, then try again.',
                error: true,
            });
        } else {
            const msg = resp.data?.error || `Unexpected error (HTTP ${resp.status}).`;
            messages.value.push({ role: 'model', text: `⚠️ ${msg}`, error: true });
        }
    } finally {
        loading.value = false;
        scrollToBottom();
        nextTick(() => inputRef.value?.focus());
    }
};

// Chat history lives in localStorage, so it survives the reload.
const reloadPage = () => window.location.reload();

const toolNames = (m) => (m.tools_used || []).map(t => t.name).join(', ');
const hasMessages = computed(() => messages.value.length > 0);
</script>

<template>
    <!-- Floating trigger button -->
    <button
        v-if="!open"
        type="button"
        class="fixed bottom-5 right-5 z-40 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg ring-1 ring-white/10 transition-transform hover:scale-105"
        title="AI Assistant"
        @click="openChat"
    >
        <SparklesIcon class="h-5 w-5" />
    </button>

    <!-- Chat panel -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-4"
    >
        <div
            v-if="open"
            class="fixed bottom-5 right-5 z-40 flex w-[92vw] max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
            style="height: min(600px, 80vh)"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-brand-600 to-brand-700 px-3.5 py-2.5 text-white">
                <div class="flex items-center gap-2">
                    <SparklesIcon class="h-4 w-4" />
                    <div>
                        <div class="text-[13px] font-semibold leading-none">AI Assistant</div>
                        <div class="text-[10px] opacity-90 mt-0.5">Ask about your inventory</div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button v-if="hasMessages" type="button" class="rounded p-1 hover:bg-white/15" title="Clear chat" @click="clearChat">
                        <TrashIcon class="h-3.5 w-3.5" />
                    </button>
                    <button type="button" class="rounded p-1 hover:bg-white/15" title="Close" @click="open = false">
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <div ref="scrollAreaRef" class="flex-1 overflow-y-auto bg-slate-50/60 p-3 space-y-2.5">
                <!-- Empty state with suggested prompts -->
                <div v-if="!hasMessages" class="flex flex-col items-center justify-center py-8">
                    <SparklesIcon class="h-8 w-8 text-brand-400" />
                    <p class="mt-2 text-[13px] font-semibold text-slate-800">Ask me about your inventory</p>
                    <p class="mt-1 text-[11px] text-slate-500 text-center px-4">
                        Assets, employees, warranties, movements, stats — I have live access to your data.
                    </p>
                    <div class="mt-4 grid w-full gap-1.5 px-2">
                        <button
                            v-for="s in SUGGESTED"
                            :key="s"
                            type="button"
                            class="w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-left text-[11.5px] text-slate-700 hover:bg-brand-50 hover:border-brand-200 hover:text-brand-700 transition"
                            @click="send(s)"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div
                    v-for="(m, i) in messages"
                    :key="i"
                    :class="['flex', m.role === 'user' ? 'justify-end' : 'justify-start']"
                >
                    <div
                        :class="[
                            'max-w-[92%] rounded-lg px-3 py-2 text-[12.5px] leading-relaxed',
                            m.role === 'user'
                                ? 'bg-brand-600 text-white whitespace-pre-wrap'
                                : m.error
                                    ? 'bg-rose-50 text-rose-800 border border-rose-200 whitespace-pre-wrap'
                                    : 'bg-white text-slate-800 border border-slate-200 ai-md',
                        ]"
                    >
                        <template v-if="m.role === 'user' || m.error">{{ m.text }}</template>
                        <div v-else v-html="renderMarkdown(m.text)"></div>
                        <div
                            v-if="m.tools_used?.length"
                            class="mt-1.5 pt-1.5 border-t border-slate-100 text-[10px] text-slate-400"
                        >used: {{ toolNames(m) }}</div>
                    </div>
                </div>

                <div v-if="loading" class="flex justify-start">
                    <div class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-[12px] text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-pulse"></span>
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-pulse [animation-delay:150ms]"></span>
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-pulse [animation-delay:300ms]"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Input -->

            <!-- Session lapsed: asking again cannot work until the page reloads. -->
            <div v-if="sessionExpired" class="border-t border-slate-100 bg-amber-50 p-2">
                <button
                    type="button"
                    class="w-full rounded-md bg-amber-600 px-3 py-2 text-[13px] font-semibold text-white transition hover:bg-amber-700"
                    @click="reloadPage"
                >
                    Reload page to continue
                </button>
            </div>

            <form v-else class="flex gap-1.5 border-t border-slate-100 bg-white p-2" @submit.prevent="send()">
                <input
                    ref="inputRef"
                    v-model="input"
                    type="text"
                    :disabled="loading"
                    placeholder="Ask about your inventory..."
                    class="flex-1 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[13px] text-slate-800 placeholder:text-slate-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-slate-50"
                />
                <button
                    type="submit"
                    :disabled="loading || !input.trim()"
                    class="inline-flex items-center justify-center rounded-md bg-brand-600 px-3 py-1.5 text-white hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    title="Send"
                >
                    <PaperAirplaneIcon class="h-4 w-4" />
                </button>
            </form>
        </div>
    </Transition>
</template>

<style scoped>
/* Compact, tight markdown rendering inside the assistant bubbles */
.ai-md :deep(p)          { margin: 0 0 0.35em 0; }
.ai-md :deep(p:last-child) { margin-bottom: 0; }
.ai-md :deep(strong)     { font-weight: 600; color: rgb(15 23 42); }
.ai-md :deep(em)         { font-style: italic; }
.ai-md :deep(code)       { background: rgb(241 245 249); padding: 1px 4px; border-radius: 3px; font-size: 0.92em; font-family: ui-monospace, monospace; }
.ai-md :deep(ul), .ai-md :deep(ol) { margin: 0.25em 0 0.35em 1.15em; padding: 0; }
.ai-md :deep(li)         { margin: 0.1em 0; }
.ai-md :deep(h1),
.ai-md :deep(h2),
.ai-md :deep(h3)         { font-weight: 600; margin: 0.5em 0 0.25em 0; font-size: 1em; color: rgb(15 23 42); }
.ai-md :deep(a)          { color: rgb(37 99 235); text-decoration: underline; }
.ai-md :deep(hr)         { border: 0; border-top: 1px solid rgb(226 232 240); margin: 0.5em 0; }

/* Tables — the main upgrade */
.ai-md :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0.4em 0;
    font-size: 11.5px;
    display: block;
    overflow-x: auto;
}
.ai-md :deep(thead)      { background: rgb(248 250 252); }
.ai-md :deep(th) {
    border: 1px solid rgb(226 232 240);
    padding: 4px 8px;
    text-align: left;
    font-weight: 600;
    color: rgb(51 65 85);
    white-space: nowrap;
}
.ai-md :deep(td) {
    border: 1px solid rgb(226 232 240);
    padding: 4px 8px;
    vertical-align: top;
}
.ai-md :deep(tbody tr:nth-child(even)) { background: rgb(248 250 252); }
</style>

