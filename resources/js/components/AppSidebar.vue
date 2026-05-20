<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Bell, BookOpenCheck, Building2, ClipboardCheck, FileCheck2, FileText, GitBranch, Layers3, LayoutDashboard, ListChecks, ScrollText, ShieldCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const hasPermission = (permission: string) => page.props.auth.user?.permissions?.includes(permission) ?? false;
const hasAnyPermission = (permissions: string[]) => permissions.some((permission) => hasPermission(permission));

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
    ...(hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'rpjmd.manage', 'manage_rpjmd'])
        ? [{
              title: 'RPJMD Kabupaten',
              href: '/rpjmd',
              icon: GitBranch,
          }]
        : []),
    ...(hasAnyPermission(['renstra.view', 'view_renstra_opd', 'renstra.manage', 'manage_renstra_opd'])
        ? [{
              title: 'Renstra OPD',
              href: '/renstra-opd',
              icon: Layers3,
          }]
        : []),
    ...(hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_perjanjian_kinerja'])
        ? [{
              title: 'Perjanjian Kinerja',
              href: '/perjanjian-kinerja',
              icon: ClipboardCheck,
          }]
        : []),
    ...(hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_rencana_aksi'])
        ? [{
              title: 'Rencana Aksi',
              href: '/rencana-aksi',
              icon: ListChecks,
          }]
        : []),
    ...(hasAnyPermission(['kinerja.view', 'kinerja.manage', 'input_realisasi', 'verify_realisasi'])
        ? [{
              title: 'Realisasi Kinerja',
              href: '/realisasi-kinerja',
              icon: BarChart3,
          }]
        : []),
    ...(hasAnyPermission(['dokumen.view', 'dokumen.manage', 'manage_dokumen'])
        ? [{
              title: 'Dokumen',
              href: '/dokumen',
              icon: FileText,
          }]
        : []),
    ...(hasAnyPermission(['lkjip.view', 'lkjip.manage', 'laporan.view'])
        ? [{
              title: 'LKJIP',
              href: '/lkjip',
              icon: BookOpenCheck,
          }]
        : []),
    ...(hasAnyPermission(['evaluasi.view', 'evaluasi.manage', 'manage_evaluasi'])
        ? [{
              title: 'Evaluasi SAKIP',
              href: '/evaluasi-sakip',
              icon: FileCheck2,
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
    ...(hasPermission('activity_logs.view')
        ? [{
              title: 'Audit Log',
              href: '/audit-log',
              icon: ScrollText,
          }]
        : []),
    {
        title: 'Notifikasi',
        href: '/notifications',
        icon: Bell,
    },
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
