<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, KeyRound, RefreshCw, ShieldCheck, UserRound } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';

import { useHeroMeshCanvas } from '../PublicSite/composables/useHeroMeshCanvas';
import '../PublicSite/public-site.css';

const props = defineProps<{
    status?: string;
    captchaQuestion: string;
}>();

const { heroMeshCanvas } = useHeroMeshCanvas();
const captchaQuestion = ref(props.captchaQuestion);
const showPassword = ref(false);
const refreshingCaptcha = ref(false);
type AuthState = 'idle' | 'loading' | 'error' | 'success';
const authState = ref<AuthState>('idle');
const authMotionStyle = 'gooey-liquid';
let authStateTimer: number | null = null;

type LoginField = 'email' | 'password' | 'remember' | 'captcha_answer' | 'login_website';
const loginFields = new Set<LoginField>(['email', 'password', 'remember', 'captcha_answer', 'login_website']);
const isLoginField = (field: string): field is LoginField => loginFields.has(field as LoginField);

const form = useForm({
    email: '',
    password: '',
    remember: false,
    captcha_answer: '',
    login_website: '',
});

const wait = (duration: number) => new Promise((resolve) => window.setTimeout(resolve, duration));

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const showErrorState = () => {
    authState.value = 'error';

    if (authStateTimer) window.clearTimeout(authStateTimer);
    authStateTimer = window.setTimeout(() => {
        authState.value = 'idle';
        authStateTimer = null;
    }, 680);
};

const submit = async () => {
    if (authState.value === 'loading' || authState.value === 'success') return;

    form.clearErrors();
    authState.value = 'loading';
    const minimumLoading = wait(650);

    try {
        const response = await fetch(route('login'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(form.data()),
        });

        await minimumLoading;

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as { errors?: Record<string, string | string[]> } | null;

            if (response.status === 422 && payload?.errors) {
                Object.entries(payload.errors).forEach(([field, message]) => {
                    const normalizedMessage = Array.isArray(message) ? (message[0] ?? '') : message;

                    if (isLoginField(field)) {
                        form.setError(field, normalizedMessage);
                    } else {
                        form.setError('email', normalizedMessage);
                    }
                });
            } else {
                form.setError('email', 'Login belum dapat diproses. Silakan coba lagi.');
            }

            form.reset('password');
            showErrorState();
            void refreshCaptcha(false);
            return;
        }

        const payload = (await response.json()) as { redirect?: string };
        authState.value = 'success';
        authStateTimer = window.setTimeout(() => {
            window.location.assign(payload.redirect ?? route('dashboard'));
        }, 1050);
    } catch {
        await minimumLoading;
        form.setError('email', 'Koneksi ke server bermasalah. Silakan coba lagi.');
        form.reset('password');
        showErrorState();
        void refreshCaptcha(false);
    }
};

const refreshCaptcha = async (clearCaptchaError = true) => {
    if (refreshingCaptcha.value || authState.value === 'loading' || authState.value === 'success') return;

    refreshingCaptcha.value = true;

    try {
        const response = await fetch(route('login.captcha'), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) return;

        const payload = (await response.json()) as { captchaQuestion?: string };

        if (payload.captchaQuestion) {
            captchaQuestion.value = payload.captchaQuestion;
            form.reset('captcha_answer');
            if (clearCaptchaError) form.clearErrors('captcha_answer');
        }
    } catch {
        return;
    } finally {
        refreshingCaptcha.value = false;
    }
};

onBeforeUnmount(() => {
    if (authStateTimer) window.clearTimeout(authStateTimer);
});
</script>

<template>
    <Head title="Login" />

    <main
        class="login-shell relative isolate flex min-h-dvh items-start justify-center overflow-y-auto overflow-x-hidden bg-[#002957] px-4 pb-8 pt-24 text-slate-900 sm:items-center sm:px-6 sm:py-20"
    >
        <div class="hero-motion" style="z-index: 0" aria-hidden="true">
            <div class="hero-aurora hero-aurora-a" />
            <div class="hero-aurora hero-aurora-b" />
            <div class="hero-aurora hero-aurora-c" />
            <div class="hero-network-texture" />
            <canvas ref="heroMeshCanvas" class="hero-mesh-canvas" />
        </div>
        <div
            class="pointer-events-none absolute inset-0 z-[3] bg-[radial-gradient(circle_at_center,rgba(0,51,108,0.04)_0%,rgba(0,24,63,0.28)_72%,rgba(0,16,44,0.5)_100%)]"
            aria-hidden="true"
        />

        <Link
            :href="route('home')"
            class="absolute left-4 top-4 z-10 flex items-center gap-2.5 text-white transition-opacity hover:opacity-80 focus-visible:rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 sm:left-8 sm:top-7 sm:gap-3"
            aria-label="Kembali ke beranda E-SAKIP"
        >
            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden sm:h-10 sm:w-10">
                <AppLogoIcon />
            </span>
            <span>
                <span class="block text-sm font-bold tracking-wide">E-SAKIP</span>
                <span class="block text-xs font-medium text-blue-100">Kabupaten Banjarnegara</span>
            </span>
        </Link>

        <section
            class="login-card relative z-10 w-full max-w-[26rem] rounded-[1.75rem] border border-white/70 bg-white/90 px-5 py-6 shadow-[0_28px_80px_rgba(0,21,58,0.38)] backdrop-blur-2xl sm:px-8 sm:py-8"
            aria-labelledby="login-title"
        >
            <div class="mb-6 text-center">
                <svg class="pointer-events-none absolute h-0 w-0" aria-hidden="true" focusable="false">
                    <defs>
                        <filter id="auth-liquid-goo" x="-35%" y="-35%" width="170%" height="170%" color-interpolation-filters="sRGB">
                            <feGaussianBlur in="SourceGraphic" stdDeviation="2.8" result="liquidBlur" />
                            <feColorMatrix in="liquidBlur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 22 -9" result="liquidGoo" />
                            <feBlend in="SourceGraphic" in2="liquidGoo" />
                        </filter>
                    </defs>
                </svg>
                <span class="auth-mark mx-auto" :data-motion="authMotionStyle" :data-state="authState" aria-hidden="true">
                    <span class="auth-liquid">
                        <span class="auth-liquid-wave auth-liquid-wave-back" />
                        <span class="auth-liquid-wave auth-liquid-wave-front" />
                        <span class="auth-liquid-orbit auth-liquid-orbit-a">
                            <span class="auth-liquid-droplet" />
                        </span>
                        <span class="auth-liquid-orbit auth-liquid-orbit-b">
                            <span class="auth-liquid-droplet" />
                        </span>
                        <span class="auth-liquid-sheen" />
                    </span>
                    <span class="auth-lock">
                        <span class="auth-lock-shackle" />
                        <span class="auth-lock-body">
                            <span class="auth-lock-keyhole" />
                        </span>
                    </span>
                </span>
                <span class="sr-only" role="status" aria-live="polite">
                    {{
                        authState === 'loading'
                            ? 'Sedang memeriksa akun'
                            : authState === 'success'
                              ? 'Login berhasil'
                              : authState === 'error'
                                ? 'Login gagal'
                                : ''
                    }}
                </span>

                <h1 id="login-title" class="mt-4 text-2xl font-bold tracking-tight text-slate-950">Login</h1>
                <p class="mt-1.5 text-sm font-medium text-slate-700">Silakan login untuk melanjutkan.</p>
            </div>

            <div
                v-if="props.status"
                class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-medium text-emerald-700"
                role="status"
            >
                {{ props.status }}
            </div>

            <form class="space-y-3 sm:space-y-3.5" @submit.prevent="submit">
                <div>
                    <Label for="email" class="sr-only">Email atau username</Label>
                    <div class="relative">
                        <UserRound class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-600" />
                        <Input
                            id="email"
                            v-model="form.email"
                            type="text"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Email atau username"
                            class="h-11 rounded-xl border-slate-300/80 bg-slate-100/80 pl-10 pr-3 text-base text-slate-950 shadow-none transition placeholder:text-slate-600 focus-visible:border-blue-500 focus-visible:bg-white focus-visible:ring-0 focus-visible:ring-offset-0 sm:text-[15px]"
                        />
                    </div>
                    <InputError :message="form.errors.email" class="mt-1.5" />
                </div>

                <div>
                    <Label for="password" class="sr-only">Kata sandi</Label>
                    <div class="relative">
                        <KeyRound class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-600" />
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            autocomplete="current-password"
                            placeholder="Kata sandi"
                            class="h-11 rounded-xl border-slate-300/80 bg-slate-100/80 pl-10 pr-11 text-base text-slate-950 shadow-none transition placeholder:text-slate-600 focus-visible:border-blue-500 focus-visible:bg-white focus-visible:ring-0 focus-visible:ring-offset-0 sm:text-[15px]"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 grid w-11 cursor-pointer place-items-center rounded-r-xl text-slate-600 transition hover:text-[#00336c] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
                            :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" class="mt-1.5" />
                </div>

                <div>
                    <div
                        class="flex min-h-12 items-center gap-1.5 rounded-xl border border-slate-200/80 bg-slate-100/80 p-1.5 pl-3 sm:gap-2 sm:pl-3.5"
                    >
                        <ShieldCheck class="h-4 w-4 shrink-0 text-[#00336c]" aria-hidden="true" />
                        <span class="shrink-0 font-mono text-sm font-bold tracking-wide text-slate-900" aria-hidden="true">{{
                            captchaQuestion
                        }}</span>
                        <Label for="captcha_answer" class="sr-only">Jawaban verifikasi keamanan untuk {{ captchaQuestion }}</Label>
                        <Input
                            id="captcha_answer"
                            v-model="form.captcha_answer"
                            inputmode="numeric"
                            required
                            autocomplete="off"
                            placeholder="Jawaban"
                            class="ml-auto h-10 min-w-0 max-w-[6.25rem] rounded-lg border-slate-300 bg-white px-2 text-center text-base text-slate-950 shadow-none placeholder:text-slate-600 focus-visible:border-blue-500 focus-visible:ring-0 focus-visible:ring-offset-0 sm:h-9 sm:max-w-[7.25rem] sm:px-2.5 sm:text-sm"
                        />
                        <button
                            type="button"
                            class="grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-lg text-slate-600 transition hover:bg-white hover:text-[#00336c] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50 sm:h-9 sm:w-9"
                            title="Ganti pertanyaan"
                            aria-label="Ganti pertanyaan CAPTCHA"
                            :disabled="refreshingCaptcha || authState === 'loading' || authState === 'success'"
                            @click="refreshCaptcha()"
                        >
                            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshingCaptcha }" />
                        </button>
                    </div>
                    <InputError :message="form.errors.captcha_answer" class="mt-1.5" />
                </div>

                <div class="pointer-events-none absolute -left-[10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                    <Label for="login_website">Website</Label>
                    <Input id="login_website" v-model="form.login_website" type="text" tabindex="-1" autocomplete="off" />
                </div>

                <Label for="remember" class="flex min-h-10 w-fit cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-800">
                    <Checkbox
                        id="remember"
                        v-model:checked="form.remember"
                        class="border-2 border-[#00336c] bg-white data-[state=checked]:border-[#00336c] data-[state=checked]:bg-[#00336c] data-[state=checked]:text-white"
                    />
                    <span>Ingat saya</span>
                </Label>

                <Button
                    type="submit"
                    class="h-11 w-full cursor-pointer rounded-xl bg-[#00336c] text-sm font-semibold text-white shadow-[0_8px_20px_rgba(0,51,108,0.2)] transition hover:bg-[#002957] focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-80"
                    :disabled="authState === 'loading' || authState === 'success'"
                    :aria-busy="authState === 'loading'"
                >
                    <span>{{ authState === 'loading' ? 'Memeriksa...' : authState === 'success' ? 'Berhasil' : 'Login' }}</span>
                </Button>
            </form>
        </section>
    </main>
</template>

<style scoped>
.login-card {
    animation: login-card-enter 360ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.auth-mark {
    --lock-primary: #0b5da8;
    --lock-secondary: #00336c;
    --lock-keyhole: #ffffff;
    position: relative;
    display: grid;
    width: 3.5rem;
    height: 3.5rem;
    place-items: center;
    overflow: hidden;
    border: 1px solid rgb(203 213 225 / 0.9);
    border-radius: 1rem;
    background: white;
    color: #00336c;
    box-shadow: 0 0.5rem 1.25rem rgb(15 23 42 / 0.1);
    transition:
        width 280ms cubic-bezier(0.16, 1, 0.3, 1),
        border-radius 280ms cubic-bezier(0.16, 1, 0.3, 1),
        border-color 200ms ease,
        background-color 200ms ease,
        color 200ms ease;
}

.auth-lock {
    position: relative;
    z-index: 3;
    display: block;
    width: 1.78rem;
    height: 1.95rem;
    filter: drop-shadow(0 0.16rem 0.2rem rgb(15 23 42 / 0.2));
}

.auth-lock-shackle {
    position: absolute;
    top: 0.02rem;
    left: 0.39rem;
    width: 1.03rem;
    height: 1.04rem;
    box-sizing: border-box;
    border: 0.18rem solid var(--lock-primary);
    border-bottom: 0;
    border-radius: 0.78rem 0.78rem 0.16rem 0.16rem;
    box-shadow:
        inset 0.08rem 0.03rem 0 rgb(255 255 255 / 0.2),
        0 0.08rem 0.16rem rgb(15 23 42 / 0.12);
    transform-origin: 86% 92%;
}

.auth-lock-body {
    position: absolute;
    bottom: 0.02rem;
    left: 0.07rem;
    display: block;
    width: 1.64rem;
    height: 1.16rem;
    overflow: hidden;
    border: 1px solid rgb(255 255 255 / 0.28);
    border-radius: 0.46rem;
    background: linear-gradient(145deg, var(--lock-primary) 0%, var(--lock-secondary) 82%);
    box-shadow:
        inset 0 0.11rem 0 rgb(255 255 255 / 0.22),
        inset 0 -0.18rem 0.3rem rgb(0 24 63 / 0.2),
        0 0.12rem 0.24rem rgb(15 23 42 / 0.18);
}

.auth-lock-body::before {
    position: absolute;
    top: 0.11rem;
    right: 0.2rem;
    left: 0.2rem;
    height: 0.18rem;
    border-radius: 9999px;
    background: linear-gradient(90deg, transparent, rgb(255 255 255 / 0.42), transparent);
    content: '';
    opacity: 0.74;
}

.auth-lock-keyhole {
    position: absolute;
    top: 0.34rem;
    left: 50%;
    width: 0.3rem;
    height: 0.3rem;
    border-radius: 9999px;
    background: var(--lock-keyhole);
    box-shadow: 0 0.04rem 0.08rem rgb(0 24 63 / 0.2);
    transform: translateX(-50%);
}

.auth-lock-keyhole::after {
    position: absolute;
    top: 0.21rem;
    left: 50%;
    width: 0.12rem;
    height: 0.34rem;
    border-radius: 9999px;
    background: inherit;
    content: '';
    transform: translateX(-50%);
}

.auth-liquid {
    position: absolute;
    inset: -0.18rem;
    z-index: 1;
    overflow: hidden;
    border-radius: inherit;
    filter: url('#auth-liquid-goo') saturate(1.16);
    isolation: isolate;
    opacity: 0;
    transform: scale(0.72);
}

.auth-liquid-wave {
    position: absolute;
    top: 50%;
    left: 50%;
    transform-origin: center;
}

.auth-liquid-wave-back {
    width: 4.15rem;
    height: 3.1rem;
    margin: -1.55rem 0 0 -2.075rem;
    border-radius: 43% 57% 52% 48% / 58% 42% 56% 44%;
    background: radial-gradient(circle at 35% 24%, #e0f2fe 0 8%, #7dd3fc 24%, #0ea5e9 52%, #0369a1 84%);
    box-shadow:
        inset 0.35rem 0.3rem 0.75rem rgb(255 255 255 / 0.2),
        0 0 0.7rem rgb(14 165 233 / 0.28);
}

.auth-liquid-wave-front {
    width: 3.15rem;
    height: 2.42rem;
    margin: -1.21rem 0 0 -1.575rem;
    border-radius: 56% 44% 39% 61% / 42% 57% 43% 58%;
    background: linear-gradient(145deg, rgb(56 189 248 / 0.94), #0284c7 48%, #075985 100%);
    box-shadow:
        inset -0.25rem -0.2rem 0.6rem rgb(3 105 161 / 0.42),
        inset 0.22rem 0.2rem 0.45rem rgb(224 242 254 / 0.24);
}

.auth-liquid-orbit {
    position: absolute;
    border-radius: 9999px;
    transform-origin: center;
}

.auth-liquid-orbit-a {
    inset: 0.22rem 0.34rem;
}

.auth-liquid-orbit-b {
    inset: 0.48rem 0.72rem;
}

.auth-liquid-droplet {
    position: absolute;
    top: -0.03rem;
    left: 50%;
    display: block;
    width: 0.78rem;
    height: 0.72rem;
    margin-left: -0.39rem;
    border-radius: 54% 46% 58% 42% / 47% 56% 44% 53%;
    background: linear-gradient(145deg, #bae6fd 4%, #38bdf8 48%, #0284c7 100%);
    box-shadow:
        inset 0.13rem 0.1rem 0.18rem rgb(255 255 255 / 0.3),
        0 0 0.45rem rgb(56 189 248 / 0.48);
}

.auth-liquid-orbit-b .auth-liquid-droplet {
    top: auto;
    right: -0.02rem;
    bottom: 0.02rem;
    left: auto;
    width: 0.58rem;
    height: 0.62rem;
    margin-left: 0;
    background: linear-gradient(145deg, #7dd3fc, #0369a1);
}

.auth-liquid-sheen {
    position: absolute;
    top: 0.58rem;
    left: 0.92rem;
    width: 1.12rem;
    height: 0.34rem;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.48);
    mix-blend-mode: screen;
    opacity: 0.72;
    transform: rotate(-16deg);
}

.auth-mark[data-state='loading'] {
    --lock-primary: #ffffff;
    --lock-secondary: #bae6fd;
    --lock-keyhole: #075985;
    width: 4.65rem;
    border-color: rgb(125 211 252 / 0.8);
    border-radius: 9999px;
    background: #082f49;
    color: white;
    box-shadow:
        0 0.65rem 1.5rem rgb(2 132 199 / 0.2),
        inset 0 0 0 1px rgb(255 255 255 / 0.08);
}

.auth-mark[data-state='loading'] .auth-liquid {
    opacity: 1;
    animation: auth-liquid-enter 300ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.auth-mark[data-state='loading'] .auth-liquid-wave-back {
    animation: auth-liquid-turn-back 1.65s linear infinite;
}

.auth-mark[data-state='loading'] .auth-liquid-wave-front {
    animation: auth-liquid-turn-front 1.12s linear infinite reverse;
}

.auth-mark[data-state='loading'] .auth-liquid-orbit-a {
    animation: auth-liquid-orbit-a 1.18s linear infinite;
}

.auth-mark[data-state='loading'] .auth-liquid-orbit-b {
    animation: auth-liquid-orbit-b 1.72s linear infinite reverse;
}

.auth-mark[data-state='loading'] .auth-liquid-droplet {
    animation: auth-liquid-droplet-pulse 0.72s ease-in-out infinite alternate;
}

.auth-mark[data-state='loading'] .auth-liquid-sheen {
    animation: auth-liquid-sheen 1.65s ease-in-out infinite;
}

.auth-mark[data-state='loading'] .auth-lock {
    filter: drop-shadow(0 0.15rem 0.28rem rgb(0 32 74 / 0.48));
    animation: auth-liquid-float 0.9s ease-in-out infinite;
}

.auth-mark[data-state='error'] {
    --lock-primary: #ef4444;
    --lock-secondary: #b91c1c;
    --lock-keyhole: #ffffff;
    border-color: rgb(248 113 113 / 0.8);
    background: #fff1f2;
    color: #dc2626;
    animation: auth-shake 480ms cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}

.auth-mark[data-state='success'] {
    --lock-primary: #10b981;
    --lock-secondary: #047857;
    --lock-keyhole: #ffffff;
    width: 4.35rem;
    border-color: rgb(52 211 153 / 0.72);
    border-radius: 9999px;
    background: #ecfdf5;
    color: #047857;
    box-shadow:
        0 0.65rem 1.5rem rgb(5 150 105 / 0.2),
        inset 0 0 0 1px rgb(255 255 255 / 0.7);
}

.auth-mark[data-state='success'] .auth-lock-shackle {
    animation: auth-shackle-open 720ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.auth-mark[data-state='success'] .auth-lock-body {
    animation: auth-lock-confirm 720ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

/* Alternative motion. Change authMotionStyle to "gooey-liquid" to restore the
   preserved liquid animation above without rewriting the component. */
.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid {
    inset: 0;
    background: radial-gradient(circle at center, rgb(56 189 248 / 0.28), rgb(14 165 233 / 0.08) 48%, transparent 72%);
    filter: none;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-wave-back {
    width: 4.15rem;
    height: 3.2rem;
    margin: -1.6rem 0 0 -2.075rem;
    border-radius: 9999px;
    background: conic-gradient(
        from 15deg,
        transparent 0 12%,
        rgb(125 211 252 / 0.18) 19%,
        #e0f2fe 27%,
        #38bdf8 35%,
        transparent 48% 67%,
        rgb(14 165 233 / 0.48) 78%,
        transparent 91%
    );
    box-shadow: none;
    -webkit-mask: radial-gradient(closest-side, transparent 0 58%, #000 61% 70%, transparent 73%);
    mask: radial-gradient(closest-side, transparent 0 58%, #000 61% 70%, transparent 73%);
    animation: auth-security-ring 1.25s linear infinite;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-wave-front {
    width: 3.24rem;
    height: 2.7rem;
    margin: -1.35rem 0 0 -1.62rem;
    border-radius: 9999px;
    background: conic-gradient(
        from 205deg,
        transparent 0 18%,
        #7dd3fc 31%,
        #0284c7 43%,
        transparent 56% 78%,
        rgb(186 230 253 / 0.72) 89%,
        transparent 98%
    );
    box-shadow: none;
    -webkit-mask: radial-gradient(closest-side, transparent 0 63%, #000 66% 75%, transparent 78%);
    mask: radial-gradient(closest-side, transparent 0 63%, #000 66% 75%, transparent 78%);
    animation: auth-security-ring-reverse 1.75s linear infinite;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-orbit-a {
    inset: 0.24rem 0.46rem;
    animation: auth-security-orbit 0.92s linear infinite;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-orbit-b {
    inset: 0.58rem 0.9rem;
    animation: auth-security-orbit 1.42s linear infinite reverse;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-droplet,
.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-orbit-b .auth-liquid-droplet {
    top: -0.02rem;
    right: auto;
    bottom: auto;
    left: 50%;
    width: 0.42rem;
    height: 0.42rem;
    margin-left: -0.21rem;
    border: 1px solid rgb(224 242 254 / 0.9);
    border-radius: 9999px;
    background: #7dd3fc;
    box-shadow:
        0 0 0.22rem #e0f2fe,
        0 0 0.62rem rgb(56 189 248 / 0.92);
    animation: auth-security-particle 0.68s ease-in-out infinite alternate;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-orbit-b .auth-liquid-droplet {
    width: 0.3rem;
    height: 0.3rem;
    margin-left: -0.15rem;
    background: #e0f2fe;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-liquid-sheen {
    top: 0.2rem;
    left: 0.24rem;
    width: 0.24rem;
    height: 3.1rem;
    border-radius: 9999px;
    background: linear-gradient(180deg, transparent, rgb(224 242 254 / 0.74) 24%, #7dd3fc 50%, rgb(224 242 254 / 0.74) 76%, transparent);
    box-shadow: 0 0 0.48rem rgb(56 189 248 / 0.7);
    mix-blend-mode: screen;
    opacity: 0;
    animation: auth-security-sweep 1.35s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}

.auth-mark[data-motion='security-scan'][data-state='loading'] .auth-lock {
    animation: auth-security-lock 1.08s ease-in-out infinite;
}

@keyframes login-card-enter {
    from {
        opacity: 0;
        transform: translate3d(0, 12px, 0) scale(0.985);
    }

    to {
        opacity: 1;
        transform: translate3d(0, 0, 0) scale(1);
    }
}

@keyframes auth-security-ring {
    0% {
        transform: rotate(0deg) scale(0.96);
        opacity: 0.68;
    }

    50% {
        transform: rotate(180deg) scale(1.06);
        opacity: 1;
    }

    100% {
        transform: rotate(360deg) scale(0.96);
        opacity: 0.68;
    }
}

@keyframes auth-security-ring-reverse {
    0% {
        transform: rotate(360deg) scale(1.04, 0.96);
    }

    50% {
        transform: rotate(180deg) scale(0.94, 1.05);
    }

    100% {
        transform: rotate(0deg) scale(1.04, 0.96);
    }
}

@keyframes auth-security-orbit {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@keyframes auth-security-particle {
    from {
        opacity: 0.58;
        transform: scale(0.72);
    }

    to {
        opacity: 1;
        transform: scale(1.22);
    }
}

@keyframes auth-security-sweep {
    0%,
    12% {
        opacity: 0;
        transform: translate3d(0, 0, 0) rotate(7deg) scaleY(0.78);
    }

    34% {
        opacity: 0.78;
    }

    72% {
        opacity: 0.58;
    }

    88%,
    100% {
        opacity: 0;
        transform: translate3d(3.9rem, 0, 0) rotate(7deg) scaleY(0.78);
    }
}

@keyframes auth-security-lock {
    0%,
    100% {
        transform: scale(0.96);
        filter: drop-shadow(0 0.12rem 0.24rem rgb(0 32 74 / 0.5));
    }

    50% {
        transform: scale(1.06);
        filter: drop-shadow(0 0 0.42rem rgb(224 242 254 / 0.62));
    }
}

@keyframes auth-liquid-enter {
    from {
        opacity: 0;
        transform: scale(0.5) rotate(-35deg);
    }

    to {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

@keyframes auth-liquid-turn-back {
    0% {
        border-radius: 43% 57% 52% 48% / 58% 42% 56% 44%;
        transform: rotate(0deg) scale(1, 0.94);
    }

    33% {
        border-radius: 57% 43% 39% 61% / 45% 60% 40% 55%;
        transform: rotate(120deg) scale(1.05, 0.98);
    }

    67% {
        border-radius: 48% 52% 62% 38% / 61% 39% 53% 47%;
        transform: rotate(240deg) scale(0.97, 1.04);
    }

    100% {
        border-radius: 43% 57% 52% 48% / 58% 42% 56% 44%;
        transform: rotate(360deg) scale(1, 0.94);
    }
}

@keyframes auth-liquid-turn-front {
    0% {
        border-radius: 56% 44% 39% 61% / 42% 57% 43% 58%;
        transform: rotate(0deg) scale(0.92, 1.05);
    }

    50% {
        border-radius: 41% 59% 55% 45% / 60% 38% 62% 40%;
        transform: rotate(180deg) scale(1.08, 0.93);
    }

    100% {
        border-radius: 56% 44% 39% 61% / 42% 57% 43% 58%;
        transform: rotate(360deg) scale(0.92, 1.05);
    }
}

@keyframes auth-liquid-orbit-a {
    from {
        transform: rotate(0deg) scale(1, 0.96);
    }

    to {
        transform: rotate(360deg) scale(1, 0.96);
    }
}

@keyframes auth-liquid-orbit-b {
    from {
        transform: rotate(0deg) scale(0.96, 1.04);
    }

    to {
        transform: rotate(360deg) scale(0.96, 1.04);
    }
}

@keyframes auth-liquid-droplet-pulse {
    from {
        border-radius: 54% 46% 58% 42% / 47% 56% 44% 53%;
        transform: scale(0.82);
    }

    to {
        border-radius: 42% 58% 45% 55% / 59% 43% 57% 41%;
        transform: scale(1.14);
    }
}

@keyframes auth-liquid-sheen {
    0%,
    100% {
        opacity: 0.38;
        transform: translate3d(-0.18rem, 0.1rem, 0) rotate(-18deg) scaleX(0.78);
    }

    50% {
        opacity: 0.82;
        transform: translate3d(1.9rem, -0.1rem, 0) rotate(-8deg) scaleX(1.08);
    }
}

@keyframes auth-liquid-float {
    0%,
    100% {
        transform: translate3d(0, -0.12rem, 0) scale(0.96) rotate(-2deg);
        opacity: 0.88;
    }

    50% {
        transform: translate3d(0, 0.16rem, 0) scale(1.06) rotate(2deg);
        opacity: 1;
    }
}

@keyframes auth-shake {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }

    20% {
        transform: translate3d(-0.42rem, 0, 0) rotate(-3deg);
    }

    40% {
        transform: translate3d(0.36rem, 0, 0) rotate(2deg);
    }

    60% {
        transform: translate3d(-0.25rem, 0, 0) rotate(-1deg);
    }

    80% {
        transform: translate3d(0.14rem, 0, 0);
    }
}

@keyframes auth-shackle-open {
    0% {
        transform: translate3d(0, 0, 0) rotate(0deg);
    }

    38% {
        transform: translate3d(0, -0.38rem, 0) rotate(0deg);
    }

    100% {
        transform: translate3d(0.4rem, -0.34rem, 0) rotate(29deg);
    }
}

@keyframes auth-lock-confirm {
    0% {
        transform: scale(0.82);
        opacity: 0.5;
    }

    56% {
        transform: scale(1.14);
        opacity: 1;
    }

    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .login-card,
    .auth-mark,
    .auth-lock,
    .auth-lock-shackle,
    .auth-lock-body,
    .auth-liquid,
    .auth-liquid-wave,
    .auth-liquid-orbit,
    .auth-liquid-droplet,
    .auth-liquid-sheen {
        animation: none;
        transition: none;
    }
}
</style>
