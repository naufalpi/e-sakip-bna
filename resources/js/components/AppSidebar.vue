<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import Sidebar from '@/components/ui/sidebar/Sidebar.vue';
import SidebarContent from '@/components/ui/sidebar/SidebarContent.vue';
import SidebarFooter from '@/components/ui/sidebar/SidebarFooter.vue';
import SidebarHeader from '@/components/ui/sidebar/SidebarHeader.vue';
import SidebarMenu from '@/components/ui/sidebar/SidebarMenu.vue';
import SidebarMenuButton from '@/components/ui/sidebar/SidebarMenuButton.vue';
import SidebarMenuItem from '@/components/ui/sidebar/SidebarMenuItem.vue';
import { useNavigationPrefetch } from '@/composables/useNavigationPrefetch';
import { type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import BarChart3 from 'lucide-vue-next/dist/esm/icons/chart-column.js';
import Bell from 'lucide-vue-next/dist/esm/icons/bell.js';
import BookOpenCheck from 'lucide-vue-next/dist/esm/icons/book-open-check.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import CalendarDays from 'lucide-vue-next/dist/esm/icons/calendar-days.js';
import ClipboardCheck from 'lucide-vue-next/dist/esm/icons/clipboard-check.js';
import FileCheck2 from 'lucide-vue-next/dist/esm/icons/file-check-2.js';
import FileText from 'lucide-vue-next/dist/esm/icons/file-text.js';
import GitBranch from 'lucide-vue-next/dist/esm/icons/git-branch.js';
import History from 'lucide-vue-next/dist/esm/icons/history.js';
import Inbox from 'lucide-vue-next/dist/esm/icons/inbox.js';
import Landmark from 'lucide-vue-next/dist/esm/icons/landmark.js';
import Layers3 from 'lucide-vue-next/dist/esm/icons/layers.js';
import LayoutDashboard from 'lucide-vue-next/dist/esm/icons/layout-dashboard.js';
import ListChecks from 'lucide-vue-next/dist/esm/icons/list-checks.js';
import Network from 'lucide-vue-next/dist/esm/icons/network.js';
import Ruler from 'lucide-vue-next/dist/esm/icons/ruler.js';
import ScrollText from 'lucide-vue-next/dist/esm/icons/scroll-text.js';
import Settings from 'lucide-vue-next/dist/esm/icons/settings.js';
import ShieldCheck from 'lucide-vue-next/dist/esm/icons/shield-check.js';
import Users from 'lucide-vue-next/dist/esm/icons/users.js';
import { computed, defineAsyncComponent } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const NavUser = defineAsyncComponent(() => import('@/components/NavUser.vue'));
const hasPermission = (permission: string) => page.props.auth.user?.permissions?.includes(permission) ?? false;
const hasAnyPermission = (permissions: string[]) => permissions.some((permission) => hasPermission(permission));
const hasRole = (role: string) => page.props.auth.user?.roles?.some((item) => item.name === role) ?? false;
const hasAnyRole = (roles: string[]) => roles.some((role) => hasRole(role));
const visibleItems = (items: Array<NavItem | false>) => items.filter(Boolean) as NavItem[];
const notificationUnreadCount = computed(() => page.props.notifications?.unread_count ?? 0);
const notificationBadge = computed(() => (notificationUnreadCount.value > 99 ? '99+' : notificationUnreadCount.value || undefined));
const canAccessRolePermission = computed(() => hasAnyRole(['super_admin', 'admin_kabupaten_dinkominfo']));

const navigationGroups = computed<NavGroup[]>(() =>
    [
        {
            label: 'Beranda & Workflow',
            items: visibleItems([
                {
                    title: 'Dashboard',
                    href: '/dashboard',
                    icon: LayoutDashboard,
                },
                hasAnyPermission(['kinerja.manage', 'rpjmd.manage', 'renstra.manage', 'evaluasi.manage', 'lkjip.manage', 'verify_realisasi', 'manage_evaluasi', 'manage_rpjmd', 'manage_renstra_opd', 'view_dashboard_kabupaten', 'view_dashboard_opd']) && {
                    title: 'Inbox Workflow',
                    href: '/workflow/inbox',
                    icon: Inbox,
                },
                {
                    title: 'Notifikasi',
                    href: '/notifications',
                    icon: Bell,
                    badge: notificationBadge.value,
                },
            ]),
        },
        {
            label: 'Referensi Data',
            items: visibleItems([
                hasPermission('opd.view') && {
                    title: 'Master OPD',
                    href: '/master/opd',
                    icon: Building2,
                },
                hasPermission('opd.view') && {
                    title: 'Unit OPD',
                    href: '/master/opd-units',
                    icon: Building2,
                },
                hasPermission('periode.view') && {
                    title: 'Periode Tahun',
                    href: '/master/periode-tahun',
                    icon: CalendarDays,
                },
                hasPermission('satuan.view') && {
                    title: 'Satuan Indikator',
                    href: '/master/satuan-indikator',
                    icon: Ruler,
                },
                hasPermission('urusan.view') && {
                    title: 'Urusan Pemerintahan',
                    href: '/master/urusan-pemerintahan',
                    icon: Landmark,
                },
            ]),
        },
        {
            label: 'Perencanaan Kinerja',
            items: visibleItems([
                hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'rpjmd.manage', 'manage_rpjmd']) && {
                    title: 'RPJMD Kabupaten',
                    href: '/rpjmd',
                    icon: GitBranch,
                },
                hasAnyPermission(['renstra.view', 'view_renstra_opd', 'renstra.manage', 'manage_renstra_opd']) && {
                    title: 'Renstra OPD',
                    href: '/renstra-opd',
                    icon: Layers3,
                },
                hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'renstra.view', 'view_renstra_opd']) && {
                    title: 'Pohon Kinerja',
                    href: '/pohon-kinerja',
                    icon: Network,
                },
                hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_perjanjian_kinerja']) && {
                    title: 'Perjanjian Kinerja',
                    href: '/perjanjian-kinerja',
                    icon: ClipboardCheck,
                },
                hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_rencana_aksi']) && {
                    title: 'Rencana Aksi',
                    href: '/rencana-aksi',
                    icon: ListChecks,
                },
                hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'rpjmd.manage', 'manage_rpjmd', 'renstra.view', 'view_renstra_opd', 'renstra.manage', 'manage_renstra_opd']) && {
                    title: 'Revisi Target',
                    href: '/target-revisions',
                    icon: History,
                },
            ]),
        },
        {
            label: 'Pengukuran Kinerja',
            items: visibleItems([
                hasAnyPermission(['kinerja.view', 'kinerja.manage', 'input_realisasi', 'verify_realisasi']) && {
                    title: 'Realisasi Kinerja',
                    href: '/realisasi-kinerja',
                    icon: BarChart3,
                },
                hasAnyPermission(['dokumen.view', 'dokumen.manage', 'manage_dokumen']) && {
                    title: 'Bukti Dukung',
                    href: '/dokumen',
                    icon: FileText,
                },
            ]),
        },
        {
            label: 'Pelaporan Kinerja',
            items: visibleItems([
                hasAnyPermission(['lkjip.view', 'lkjip.manage', 'laporan.view']) && {
                    title: 'LKJIP',
                    href: '/lkjip',
                    icon: BookOpenCheck,
                },
            ]),
        },
        {
            label: 'Evaluasi Kinerja',
            items: visibleItems([
                hasAnyPermission(['evaluasi.view', 'evaluasi.manage', 'manage_evaluasi']) && {
                    title: 'Evaluasi SAKIP',
                    href: '/evaluasi-sakip',
                    icon: FileCheck2,
                },
            ]),
        },
        {
            label: 'Administrasi Sistem',
            items: visibleItems([
                hasPermission('users.view') && {
                    title: 'Master User',
                    href: '/master/users',
                    icon: Users,
                },
                canAccessRolePermission.value && {
                    title: 'Role Permission',
                    href: '/master/role-permission',
                    icon: ShieldCheck,
                },
                hasPermission('activity_logs.view') && {
                    title: 'Audit Log',
                    href: '/audit-log',
                    icon: ScrollText,
                },
                hasPermission('settings.view') && {
                    title: 'Pengaturan Sistem',
                    href: '/master/system-settings',
                    icon: Settings,
                },
            ]),
        },
    ].filter((group) => group.items.length > 0),
);

const navigationHrefs = computed(() => navigationGroups.value.flatMap((group) => group.items.map((item) => item.href)));
useNavigationPrefetch(navigationHrefs);

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')" prefetch="hover" cache-for="2m">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain v-for="group in navigationGroups" :key="group.label" :label="group.label" :items="group.items" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
