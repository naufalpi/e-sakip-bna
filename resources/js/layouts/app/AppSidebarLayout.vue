<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { useInertiaNavigationIndicator } from '@/composables/useInertiaNavigationIndicator';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const pageTransitionKey = computed(() => `${page.component}:${page.url.split('?')[0]}`);
const { isNavigating } = useInertiaNavigationIndicator();
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
