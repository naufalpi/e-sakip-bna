<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import SystemAnnouncementBar from '@/components/SystemAnnouncementBar.vue';
import { useInertiaNavigationIndicator } from '@/composables/useInertiaNavigationIndicator';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { isNavigating } = useInertiaNavigationIndicator();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="admin-main-shell">
            <AppSidebarHeader class="admin-topbar" :breadcrumbs="breadcrumbs" />
            <SystemAnnouncementBar />
            <div class="admin-route-loader" :class="{ 'is-visible': isNavigating }" aria-hidden="true">
                <span />
            </div>
            <div class="admin-page-stage relative flex min-w-0 flex-none flex-col">
                <div class="admin-page-content min-w-0">
                    <slot />
                </div>
            </div>
        </AppContent>
    </AppShell>
</template>
