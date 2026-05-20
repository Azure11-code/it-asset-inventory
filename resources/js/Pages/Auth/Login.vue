<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { LockClosedIcon, UserIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });

// ── Typewriter animation for the headline ──
const FULL_TEXT = 'IT Asset';
const typed = ref('');
let typeTimer = null;

const playTyping = () => {
    let i = 0;
    let phase = 'typing'; // typing → hold → deleting → pause → typing …
    typed.value = '';

    const tick = () => {
        if (phase === 'typing') {
            if (i < FULL_TEXT.length) {
                typed.value = FULL_TEXT.slice(0, ++i);
                typeTimer = setTimeout(tick, 95);
            } else {
                phase = 'hold';
                typeTimer = setTimeout(tick, 2200);
            }
        } else if (phase === 'hold') {
            phase = 'deleting';
            tick();
        } else if (phase === 'deleting') {
            if (i > 0) {
                typed.value = FULL_TEXT.slice(0, --i);
                typeTimer = setTimeout(tick, 45);
            } else {
                phase = 'pause';
                typeTimer = setTimeout(tick, 600);
            }
        } else if (phase === 'pause') {
            phase = 'typing';
            tick();
        }
    };
    tick();
};

onMounted(playTyping);
onBeforeUnmount(() => { if (typeTimer) clearTimeout(typeTimer); });
</script>

<template>
    <Head title="Sign in" />

    <div class="login-shell">
        <!-- LEFT: form -->
        <div class="login-form">
            <div class="login-form__inner">
                <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Welcome back</h2>
                <p class="mt-1 text-sm text-slate-500">Sign in to continue.</p>

                <form @submit.prevent="submit" class="mt-8 space-y-4">
                    <div>
                        <label class="label">Username or Email</label>
                        <div class="relative">
                            <UserIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="form.login" type="text" autocomplete="username"
                                class="input pl-9" placeholder="username or you@example.com" required autofocus />
                        </div>
                        <p v-if="form.errors.login" class="error-text">{{ form.errors.login }}</p>
                    </div>

                    <div>
                        <label class="label">Password</label>
                        <div class="relative">
                            <LockClosedIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                class="input pl-9 pr-9" placeholder="••••••••" required />
                            <button type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-700"
                                @click="showPassword = !showPassword">
                                <EyeSlashIcon v-if="showPassword" class="h-4 w-4" />
                                <EyeIcon v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="error-text">{{ form.errors.password }}</p>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.remember" type="checkbox" class="checkbox" />
                        Remember me on this device
                    </label>

                    <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT: brand panel -->
        <aside class="login-brand">
            <div class="login-brand__bg" aria-hidden="true"></div>
            <div class="login-brand__inner">
                <span class="login-brand__eyebrow">Arvin International Marketing, Inc.</span>

                <h1 class="login-brand__title">
                    <span class="login-brand__typed">{{ typed }}</span><span class="login-brand__caret" aria-hidden="true"></span>
                </h1>
                <h2 class="login-brand__subtitle">Inventory Management System</h2>

                <p class="login-brand__tagline">
                    Enterprise-grade tracking for every device on your network —
                    from procurement and deployment to retirement.
                </p>

                <div class="login-brand__meta">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Secure session · Audit logged
                </div>
            </div>
        </aside>
    </div>
</template>

<style scoped>
.login-shell {
    display: grid; grid-template-columns: 1fr;
    min-height: 100vh;
}
@media (min-width: 1024px) {
    .login-shell { grid-template-columns: 1fr 1.1fr; }
}

/* ── LEFT: form ── */
.login-form {
    display: flex; align-items: center; justify-content: center;
    background: #ffffff; padding: 2rem 1.5rem;
}
.login-form__inner { width: 100%; max-width: 22rem; }

/* ── RIGHT: brand panel ── */
.login-brand {
    position: relative; display: none; overflow: hidden;
    background: #070b18; color: #e0e7ff;
}
@media (min-width: 1024px) { .login-brand { display: block; } }

.login-brand__bg {
    position: absolute; inset: 0;
    background:
        radial-gradient(circle at 25% 20%, rgba(99, 102, 241, 0.35), transparent 55%),
        radial-gradient(circle at 80% 85%, rgba(34, 211, 238, 0.20), transparent 60%),
        linear-gradient(180deg, #0b1024 0%, #070b18 100%);
}
.login-brand__bg::before,
.login-brand__bg::after {
    content: ""; position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(99, 102, 241, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(99, 102, 241, 0.08) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: radial-gradient(circle at 50% 50%, black 30%, transparent 75%);
    -webkit-mask-image: radial-gradient(circle at 50% 50%, black 30%, transparent 75%);
}

.login-brand__inner {
    position: relative; z-index: 10; height: 100%;
    display: flex; flex-direction: column; justify-content: center;
    padding: 4rem 4.5rem;
    max-width: 36rem;
}

.login-brand__eyebrow {
    display: inline-block; width: max-content;
    font-size: 0.6875rem; font-weight: 600;
    letter-spacing: 0.22em; text-transform: uppercase;
    color: rgba(199, 210, 254, 0.9);
    padding: 0.3rem 0.8rem;
    border: 1px solid rgba(129, 140, 248, 0.4);
    border-radius: 9999px;
    background: rgba(99, 102, 241, 0.1);
}

.login-brand__title {
    margin-top: 1.75rem;
    font-size: clamp(2.5rem, 5vw, 3.75rem);
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.05;
    color: #ffffff;
    min-height: 1.2em;
    text-shadow: 0 4px 30px rgba(67, 56, 202, 0.5);
}

.login-brand__typed {
    background: linear-gradient(90deg, #ffffff 0%, #c7d2fe 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.login-brand__caret {
    display: inline-block;
    width: 0.12em;
    height: 0.95em;
    margin-left: 0.08em;
    background: #a5b4fc;
    border-radius: 1px;
    vertical-align: -0.1em;
    animation: caret-blink 1s steps(1, end) infinite;
}
@keyframes caret-blink {
    0%, 50%   { opacity: 1; }
    51%, 100% { opacity: 0; }
}

.login-brand__subtitle {
    margin-top: 0.5rem;
    font-size: clamp(1.25rem, 2.1vw, 1.75rem);
    font-weight: 500;
    letter-spacing: -0.015em;
    line-height: 1.15;
    color: rgba(199, 210, 254, 0.9);
    white-space: nowrap;
}

.login-brand__tagline {
    margin-top: 1.5rem;
    font-size: 1rem;
    line-height: 1.65;
    color: rgba(199, 210, 254, 0.85);
    max-width: 28rem;
}

.login-brand__meta {
    margin-top: 2.5rem;
    display: inline-flex; width: max-content; align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem; letter-spacing: 0.05em;
    color: rgba(165, 180, 252, 0.85);
}
</style>
