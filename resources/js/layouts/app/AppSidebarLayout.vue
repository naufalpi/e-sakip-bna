<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItemType } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const pageTransitionKey = computed(() => `${page.component}:${page.url.split('?')[0]}`);
const isNavigating = ref(false);

let hideNavigationTimer: ReturnType<typeof window.setTimeout> | undefined;
let navigationStartedAt = 0;
let stopStartListener: VoidFunction | undefined;
let stopFinishListener: VoidFunction | undefined;

onMounted(() => {
    stopStartListener = router.on('start', (event) => {
        if (event.detail.visit.prefetch) {
            return;
        }

        if (hideNavigationTimer) {
            window.clearTimeout(hideNavigationTimer);
        }

        navigationStartedAt = Date.now();
        isNavigating.value = true;
    });

    stopFinishListener = router.on('finish', (event) => {
        if (event.detail.visit.prefetch) {
            return;
        }

        const remainingVisibleMs = Math.max(0, 180 - (Date.now() - navigationStartedAt));
        hideNavigationTimer = window.setTimeout(() => {
            isNavigating.value = false;
        }, remainingVisibleMs);
    });
});

onBeforeUnmount(() => {
    stopStartListener?.();
    stopFinishListener?.();

    if (hideNavigationTimer) {
        window.clearTimeout(hideNavigationTimer);
    }
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="admin-route-loader" :class="{ 'is-visible': isNavigating }" aria-hidden="true">
                <span />
            </div>
            <div class="relative flex min-w-0 flex-1 flex-col">
                <Transition name="page-drop" appear>
                    <div :key="pageTransitionKey" class="flex min-w-0 flex-1 flex-col">
                        <slot />
                    </div>
                </Transition>
            </div>
        </AppContent>
    </AppShell>
</template>
