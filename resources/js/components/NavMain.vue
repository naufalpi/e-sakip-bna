<script setup lang="ts">
import SidebarGroup from '@/components/ui/sidebar/SidebarGroup.vue';
import SidebarGroupLabel from '@/components/ui/sidebar/SidebarGroupLabel.vue';
import SidebarMenu from '@/components/ui/sidebar/SidebarMenu.vue';
import SidebarMenuButton from '@/components/ui/sidebar/SidebarMenuButton.vue';
import SidebarMenuItem from '@/components/ui/sidebar/SidebarMenuItem.vue';
import { warmAppPageComponent } from '@/lib/inertiaPages';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const page = usePage<SharedData>();

const isActive = (href: string) => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
</script>

<template>
    <SidebarGroup class="app-sidebar-group">
        <SidebarGroupLabel class="app-sidebar-group-label">{{ label ?? 'Platform' }}</SidebarGroupLabel>
        <SidebarMenu class="space-y-1">
            <SidebarMenuItem v-for="item in items" :key="item.title" class="app-sidebar-menu-item">
                <SidebarMenuButton as-child :is-active="isActive(item.href)" class="app-sidebar-menu-button">
                    <Link
                        :href="item.href"
                        prefetch="hover"
                        cache-for="5m"
                        @focus="warmAppPageComponent(item.pageComponent)"
                        @pointerenter="warmAppPageComponent(item.pageComponent)"
                    >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                        <span
                            v-if="item.badge"
                            class="app-sidebar-badge ml-auto inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold leading-none"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
