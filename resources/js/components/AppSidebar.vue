<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, LayoutDashboard, ShieldCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const hasPermission = (permission: string) => page.props.auth.user?.permissions?.includes(permission) ?? false;

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    ...(hasPermission('opd.view')
        ? [{
              title: 'Master OPD',
              href: '/master/opd',
              icon: Building2,
          }]
        : []),
    ...(hasPermission('users.view')
        ? [{
              title: 'Master User',
              href: '/master/users',
              icon: Users,
          }]
        : []),
    ...(hasPermission('roles.view')
        ? [{
              title: 'Role Permission',
              href: '/master/role-permission',
              icon: ShieldCheck,
          }]
        : []),
]);

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="footerNavItems.length" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
