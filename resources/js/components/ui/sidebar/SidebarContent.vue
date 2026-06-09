<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const sidebarContent = ref<HTMLElement | null>(null);
const scrollStorageKey = 'e-sakip:sidebar-scroll-top';
let scrollSaveFrame = 0;

const saveScrollPosition = () => {
    if (scrollSaveFrame) {
        cancelAnimationFrame(scrollSaveFrame);
    }

    scrollSaveFrame = requestAnimationFrame(() => {
        if (sidebarContent.value) {
            sessionStorage.setItem(scrollStorageKey, String(sidebarContent.value.scrollTop));
        }

        scrollSaveFrame = 0;
    });
};

const restoreScrollPosition = () => {
    const savedScrollTop = Number(sessionStorage.getItem(scrollStorageKey));

    if (!Number.isFinite(savedScrollTop) || !sidebarContent.value) {
        return;
    }

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            if (sidebarContent.value) {
                sidebarContent.value.scrollTop = savedScrollTop;
            }
        });
    });
};

const saveScrollPositionNow = () => {
    if (sidebarContent.value) {
        sessionStorage.setItem(scrollStorageKey, String(sidebarContent.value.scrollTop));
    }
};

onMounted(async () => {
    await nextTick();
    restoreScrollPosition();
    sidebarContent.value?.addEventListener('scroll', saveScrollPosition, { passive: true });
});

onBeforeUnmount(() => {
    saveScrollPositionNow();
    sidebarContent.value?.removeEventListener('scroll', saveScrollPosition);

    if (scrollSaveFrame) {
        cancelAnimationFrame(scrollSaveFrame);
    }
});
</script>

<template>
    <div
        ref="sidebarContent"
        data-sidebar="content"
        :class="cn('flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto overflow-x-hidden', props.class)"
    >
        <slot />
    </div>
</template>
