<script setup lang="ts">
import type { SharedData, SystemAnnouncement } from '@/types';
import { usePage } from '@inertiajs/vue3';
import ArrowUpRight from 'lucide-vue-next/dist/esm/icons/arrow-up-right.js';
import ChevronLeft from 'lucide-vue-next/dist/esm/icons/chevron-left.js';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import CircleAlert from 'lucide-vue-next/dist/esm/icons/circle-alert.js';
import Info from 'lucide-vue-next/dist/esm/icons/info.js';
import AlertTriangle from 'lucide-vue-next/dist/esm/icons/triangle-alert.js';
import X from 'lucide-vue-next/dist/esm/icons/x.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage<SharedData>();
const dismissedIds = ref<number[]>([]);
const activeIndex = ref(0);
const isPaused = ref(false);
const reduceMotion = ref(false);
const viewport = ref<HTMLElement | null>(null);
const movingContent = ref<HTMLElement | null>(null);
const overflowDistance = ref(0);
const animationDuration = ref(18);
let rotationTimer: ReturnType<typeof window.setTimeout> | null = null;
let resizeObserver: ResizeObserver | null = null;
let mediaQuery: MediaQueryList | null = null;

const announcements = computed<SystemAnnouncement[]>(() =>
    (page.props.system_announcements ?? []).filter((announcement) => !dismissedIds.value.includes(announcement.id)),
);
const current = computed(() => announcements.value[activeIndex.value] ?? null);
const isExternalLink = computed(() => current.value?.link_url?.startsWith('http://') || current.value?.link_url?.startsWith('https://'));
const needsMarquee = computed(() => overflowDistance.value > 8 && !reduceMotion.value);
const marqueeStyle = computed(() => ({
    '--announcement-distance': `${overflowDistance.value}px`,
    '--announcement-duration': `${animationDuration.value}s`,
}));

const theme = computed(() => {
    const themes = {
        important: {
            shell: 'border-red-800 bg-[linear-gradient(90deg,#991b1b_0%,#b91c1c_45%,#dc2626_100%)] text-white shadow-[0_8px_22px_rgba(153,27,27,0.24)]',
            icon: CircleAlert,
            label: 'Penting',
            badge: 'border-white bg-white text-red-700 shadow-[0_3px_10px_rgba(69,10,10,0.2)]',
            link: 'border-white/30 bg-white text-red-800 hover:bg-red-50',
            close: 'hover:bg-white/15 focus:ring-white/60',
        },
        warning: {
            shell: 'border-[#c7b600] bg-[#E9D502] text-slate-950 shadow-[0_8px_22px_rgba(117,106,0,0.22)]',
            icon: AlertTriangle,
            label: 'Perhatian',
            badge: 'border-black bg-black text-[#E9D502] shadow-[0_3px_10px_rgba(54,49,0,0.2)]',
            link: 'border-black/20 bg-white/85 text-slate-950 hover:bg-white',
            close: 'hover:bg-black/10 focus:ring-black/50',
        },
        info: {
            shell: 'border-blue-950 bg-[linear-gradient(90deg,#002957_0%,#003b7d_52%,#07559e_100%)] text-white shadow-[0_8px_22px_rgba(0,51,108,0.22)]',
            icon: Info,
            label: 'Informasi',
            badge: 'border-white bg-white text-[#00336C] shadow-[0_3px_10px_rgba(0,25,54,0.2)]',
            link: 'border-white/30 bg-white text-[#00336C] hover:bg-blue-50',
            close: 'hover:bg-white/15 focus:ring-white/60',
        },
    } as const;

    return themes[current.value?.type ?? 'info'];
});

const measure = async () => {
    await nextTick();
    const viewportWidth = viewport.value?.clientWidth ?? 0;
    const contentWidth = movingContent.value?.scrollWidth ?? 0;
    overflowDistance.value = Math.max(0, contentWidth - viewportWidth);
    animationDuration.value = Math.min(42, Math.max(14, (overflowDistance.value + viewportWidth * 0.35) / 48));
    restartRotation();
};

const clearRotationTimer = () => {
    if (rotationTimer) {
        window.clearTimeout(rotationTimer);
        rotationTimer = null;
    }
};

const goToAnnouncement = (index: number) => {
    const total = announcements.value.length;
    if (total <= 1) return;

    clearRotationTimer();
    activeIndex.value = (index + total) % total;
};

const scheduleNext = (delay: number) => {
    clearRotationTimer();

    if (announcements.value.length <= 1 || reduceMotion.value) {
        return;
    }

    rotationTimer = window.setTimeout(() => {
        if (isPaused.value) {
            scheduleNext(500);
            return;
        }

        goToAnnouncement(activeIndex.value + 1);
    }, delay);
};

const restartRotation = () => {
    clearRotationTimer();

    if (announcements.value.length <= 1 || reduceMotion.value || needsMarquee.value) {
        return;
    }

    scheduleNext(12000);
};

const handleMarqueeEnd = () => {
    if (announcements.value.length > 1 && needsMarquee.value && !reduceMotion.value) {
        scheduleNext(3000);
    }
};

const dismiss = () => {
    if (!current.value) return;

    const id = current.value.id;
    dismissedIds.value = [...dismissedIds.value, id];
    window.sessionStorage.setItem(`system-announcement-dismissed:${id}`, '1');
    activeIndex.value = Math.min(activeIndex.value, Math.max(0, announcements.value.length - 1));
};

const syncReducedMotion = () => {
    reduceMotion.value = mediaQuery?.matches ?? false;
    void measure();
};

watch(
    () => [current.value?.id, current.value?.message, current.value?.title],
    () => void measure(),
);

watch(
    () => page.props.system_announcements,
    (items) => {
        dismissedIds.value = (items ?? [])
            .filter((item) => window.sessionStorage.getItem(`system-announcement-dismissed:${item.id}`) === '1')
            .map((item) => item.id);
        activeIndex.value = 0;
        restartRotation();
    },
);

onMounted(() => {
    dismissedIds.value = (page.props.system_announcements ?? [])
        .filter((item) => window.sessionStorage.getItem(`system-announcement-dismissed:${item.id}`) === '1')
        .map((item) => item.id);

    mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    mediaQuery.addEventListener('change', syncReducedMotion);
    syncReducedMotion();

    resizeObserver = new ResizeObserver(() => void measure());
    if (viewport.value) resizeObserver.observe(viewport.value);
    if (movingContent.value) resizeObserver.observe(movingContent.value);
    restartRotation();
});

onBeforeUnmount(() => {
    if (rotationTimer) window.clearTimeout(rotationTimer);
    resizeObserver?.disconnect();
    mediaQuery?.removeEventListener('change', syncReducedMotion);
});
</script>

<template>
    <section
        v-if="current"
        class="system-announcement-bar shrink-0 border-b"
        :class="theme.shell"
        role="status"
        aria-live="polite"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
    >
        <div class="relative flex min-h-12 items-center gap-2.5 overflow-hidden px-3 py-2 sm:px-5">
            <span
                class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg border px-2.5 text-[10px] font-extrabold uppercase tracking-[0.14em]"
                :class="theme.badge"
            >
                <component :is="theme.icon" class="size-4" aria-hidden="true" />
                <span>{{ theme.label }}</span>
            </span>

            <div ref="viewport" class="min-w-0 flex-1 overflow-hidden">
                <div
                    :key="current.id"
                    ref="movingContent"
                    class="announcement-moving-content inline-flex max-w-none items-center gap-2 whitespace-nowrap text-[13px] sm:text-sm"
                    :class="{ 'is-running': needsMarquee, 'is-rotating': needsMarquee && announcements.length > 1 }"
                    :style="marqueeStyle"
                    @animationend="handleMarqueeEnd"
                >
                    <strong class="font-bold tracking-[0.01em]">{{ current.title }}</strong>
                    <span class="font-medium opacity-95">— {{ current.message }}</span>
                </div>
            </div>

            <a
                v-if="current.link_url"
                :href="current.link_url"
                :target="isExternalLink ? '_blank' : undefined"
                :rel="isExternalLink ? 'noopener noreferrer' : undefined"
                class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-semibold transition"
                :class="theme.link"
            >
                <span class="hidden sm:inline">{{ current.link_label || 'Lihat' }}</span>
                <ArrowUpRight class="size-3.5" aria-hidden="true" />
            </a>

            <div
                v-if="announcements.length > 1"
                class="border-current/25 inline-flex h-7 shrink-0 items-center rounded-full border bg-black/10 p-0.5 text-current shadow-inner shadow-black/10 backdrop-blur-sm"
                aria-label="Navigasi pengumuman"
            >
                <button
                    type="button"
                    class="inline-flex size-5 items-center justify-center rounded-full text-current opacity-80 transition hover:bg-white/25 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
                    aria-label="Pengumuman sebelumnya"
                    title="Sebelumnya"
                    @click="goToAnnouncement(activeIndex - 1)"
                >
                    <ChevronLeft class="size-3" aria-hidden="true" />
                </button>
                <span class="min-w-6 px-0.5 text-center text-[9px] font-bold tabular-nums text-current" aria-live="off">
                    {{ activeIndex + 1 }}/{{ announcements.length }}
                </span>
                <button
                    type="button"
                    class="inline-flex size-5 items-center justify-center rounded-full text-current opacity-80 transition hover:bg-white/25 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
                    aria-label="Pengumuman berikutnya"
                    title="Berikutnya"
                    @click="goToAnnouncement(activeIndex + 1)"
                >
                    <ChevronRight class="size-3" aria-hidden="true" />
                </button>
            </div>

            <button
                v-if="current.is_dismissible"
                type="button"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg transition focus:outline-none focus:ring-2"
                :class="theme.close"
                aria-label="Tutup pengumuman"
                @click="dismiss"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>
    </section>
</template>

<style scoped>
.announcement-moving-content.is-running {
    animation: announcement-pan var(--announcement-duration) linear infinite;
    will-change: transform;
}

.system-announcement-bar {
    position: sticky;
    top: calc(var(--admin-shell-inset) + var(--admin-header-height));
    right: 0;
    left: 0;
    z-index: 90;
    isolation: isolate;
}

section:hover .announcement-moving-content.is-running {
    animation-play-state: paused;
}

.announcement-moving-content.is-running.is-rotating {
    animation-iteration-count: 1;
    animation-fill-mode: forwards;
}

@keyframes announcement-pan {
    0%,
    7% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(calc(-1 * var(--announcement-distance)));
    }
}

@media (prefers-reduced-motion: reduce) {
    .announcement-moving-content {
        animation: none !important;
    }
}
</style>
