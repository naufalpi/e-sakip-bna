<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Bell, BookOpenCheck, Building2, CalendarDays, ClipboardCheck, FileCheck2, FileText, GitBranch, Inbox, Landmark, Layers3, LayoutDashboard, ListChecks, Network, Ruler, ScrollText, Settings, ShieldCheck, Users } from 'lucide-vue-next';
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
    ...(hasAnyPermission(['kinerja.manage', 'rpjmd.manage', 'renstra.manage', 'evaluasi.manage', 'lkjip.manage', 'verify_realisasi', 'manage_evaluasi', 'manage_rpjmd', 'manage_renstra_opd', 'view_dashboard_kabupaten', 'view_dashboard_opd'])
        ? [{
              title: 'Inbox Workflow',
              href: '/workflow/inbox',
              icon: Inbox,
          }]
        : []),
    ...(hasPermission('opd.view')
        ? [{
              title: 'Master OPD',
              href: '/master/opd',
              icon: Building2,
          }]
        : []),
    ...(hasPermission('opd.view')
        ? [{
              title: 'Unit OPD',
              href: '/master/opd-units',
              icon: Building2,
          }]
        : []),
    ...(hasPermission('periode.view')
        ? [{
              title: 'Periode Tahun',
              href: '/master/periode-tahun',
              icon: CalendarDays,
          }]
        : []),
    ...(hasPermission('satuan.view')
        ? [{
              title: 'Satuan Indikator',
              href: '/master/satuan-indikator',
              icon: Ruler,
          }]
        : []),
    ...(hasPermission('urusan.view')
        ? [{
              title: 'Urusan Pemerintahan',
              href: '/master/urusan-pemerintahan',
              icon: Landmark,
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
    ...(hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'renstra.view', 'view_renstra_opd'])
        ? [{
              title: 'Pohon Kinerja',
              href: '/pohon-kinerja',
              icon: Network,
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
    ...(hasPermission('settings.view')
        ? [{
              title: 'Pengaturan Sistem',
              href: '/master/system-settings',
              icon: Settings,
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
