<script setup lang="ts">
import type { SharedData, SystemAnnouncement } from '@/types';
import { usePage } from '@inertiajs/vue3';
import ArrowUpRight from 'lucide-vue-next/dist/esm/icons/arrow-up-right.js';
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
let rotationTimer: ReturnType<typeof window.setInterval> | null = null;
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
            shell: 'border-red-200 bg-red-50 text-red-950',
            marker: 'bg-red-600 text-white',
            icon: CircleAlert,
            label: 'Penting',
            link: 'border-red-200 bg-white/80 text-red-800 hover:bg-white',
        },
        warning: {
            shell: 'border-amber-200 bg-amber-50 text-amber-950',
            marker: 'bg-amber-500 text-white',
            icon: AlertTriangle,
            label: 'Perhatian',
            link: 'border-amber-200 bg-white/80 text-amber-900 hover:bg-white',
        },
        info: {
            shell: 'border-blue-200 bg-blue-50 text-blue-950',
            marker: 'bg-[#00336C] text-white',
            icon: Info,
            label: 'Informasi',
            link: 'border-blue-200 bg-white/80 text-[#00336C] hover:bg-white',
        },
    } as const;

    return themes[current.value?.type ?? 'info'];
});

const measure = async () => {
    await nextTick();
    const viewportWidth = viewport.value?.clientWidth ?? 0;
    const contentWidth = movingContent.value?.scrollWidth ?? 0;
    overflowDistance.value = Math.max(0, contentWidth - viewportWidth);
    animationDuration.value = Math.min(38, Math.max(14, (contentWidth + viewportWidth) / 60));
};

const restartRotation = () => {
    if (rotationTimer) {
        window.clearInterval(rotationTimer);
    }

    rotationTimer = window.setInterval(() => {
        if (!isPaused.value && !reduceMotion.value && announcements.value.length > 1) {
            activeIndex.value = (activeIndex.value + 1) % announcements.value.length;
        }
    }, 12000);
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
    if (rotationTimer) window.clearInterval(rotationTimer);
    resizeObserver?.disconnect();
    mediaQuery?.removeEventListener('change', syncReducedMotion);
});
</script>

<template>
    <section
        v-if="current"
        class="relative z-20 shrink-0 border-b"
        :class="theme.shell"
        role="status"
        aria-live="polite"
        @mouseenter="isPaused = true"
        @mouseleave="isPaused = false"
        @focusin="isPaused = true"
        @focusout="isPaused = false"
    >
        <div class="flex min-h-11 items-center gap-2.5 px-3 py-1.5 sm:px-5">
            <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg" :class="theme.marker">
                <component :is="theme.icon" class="size-3.5" aria-hidden="true" />
            </span>

            <span class="hidden shrink-0 text-[11px] font-bold uppercase tracking-[0.13em] sm:inline">{{ theme.label }}</span>
            <span class="hidden h-4 w-px shrink-0 bg-current opacity-15 sm:block" aria-hidden="true" />

            <div ref="viewport" class="min-w-0 flex-1 overflow-hidden">
                <div
                    ref="movingContent"
                    class="announcement-moving-content inline-flex max-w-none items-center gap-2 whitespace-nowrap text-sm"
                    :class="{ 'is-running': needsMarquee }"
                    :style="marqueeStyle"
                >
                    <strong class="font-semibold">{{ current.title }}</strong>
                    <span class="opacity-85">— {{ current.message }}</span>
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

            <span v-if="announcements.length > 1" class="shrink-0 text-[11px] font-semibold opacity-60">
                {{ activeIndex + 1 }}/{{ announcements.length }}
            </span>

            <button
                v-if="current.is_dismissible"
                type="button"
                class="focus:ring-current/30 inline-flex size-8 shrink-0 items-center justify-center rounded-lg transition hover:bg-black/5 focus:outline-none focus:ring-2"
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
    animation: announcement-pan var(--announcement-duration) ease-in-out infinite alternate;
    will-change: transform;
}

section:hover .announcement-moving-content.is-running,
section:focus-within .announcement-moving-content.is-running {
    animation-play-state: paused;
}

@keyframes announcement-pan {
    0%,
    10% {
        transform: translateX(0);
    }
    90%,
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
