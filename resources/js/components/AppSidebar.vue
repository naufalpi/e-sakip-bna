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
import { usePageComponentPreload } from '@/composables/usePageComponentPreload';
import { type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import Bell from 'lucide-vue-next/dist/esm/icons/bell.js';
import BookOpenCheck from 'lucide-vue-next/dist/esm/icons/book-open-check.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import CalendarDays from 'lucide-vue-next/dist/esm/icons/calendar-days.js';
import BarChart3 from 'lucide-vue-next/dist/esm/icons/chart-column.js';
import ClipboardCheck from 'lucide-vue-next/dist/esm/icons/clipboard-check.js';
import ClipboardList from 'lucide-vue-next/dist/esm/icons/clipboard-list.js';
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
import Signpost from 'lucide-vue-next/dist/esm/icons/signpost.js';
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
const canAccessRolePermission = computed(
    () => hasRole('super_admin') || (hasRole('admin_kabupaten_dinkominfo') && hasAnyPermission(['roles.view', 'roles.manage', 'manage_roles'])),
);

const navigationGroups = computed<NavGroup[]>(() =>
    [
        {
            label: 'Beranda & Workflow',
            items: visibleItems([
                {
                    title: 'Dashboard',
                    href: '/dashboard',
                    pageComponent: 'Dashboard',
                    icon: LayoutDashboard,
                },
                hasAnyPermission([
                    'kinerja.manage',
                    'rpjmd.manage',
                    'renstra.manage',
                    'evaluasi.manage',
                    'lkjip.manage',
                    'verify_realisasi',
                    'manage_evaluasi',
                    'manage_rpjmd',
                    'manage_renstra_opd',
                    'view_dashboard_kabupaten',
                    'view_dashboard_opd',
                ]) && {
                    title: 'Inbox Workflow',
                    href: '/workflow/inbox',
                    pageComponent: 'Workflow/Inbox',
                    icon: Inbox,
                },
                {
                    title: 'Notifikasi',
                    href: '/notifications',
                    pageComponent: 'Notifications/Index',
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
                    pageComponent: 'Master/Opd/Index',
                    icon: Building2,
                },
                hasPermission('periode.view') && {
                    title: 'Periode Tahun',
                    href: '/master/periode-tahun',
                    pageComponent: 'Master/PeriodeTahun/Index',
                    icon: CalendarDays,
                },
                hasPermission('satuan.view') && {
                    title: 'Satuan Indikator',
                    href: '/master/satuan-indikator',
                    pageComponent: 'Master/SatuanIndikator/Index',
                    icon: Ruler,
                },
                hasPermission('strategi.view') && {
                    title: 'Strategi Daerah',
                    href: '/master/strategi-daerah',
                    pageComponent: 'Master/StrategiDaerah/Index',
                    icon: Signpost,
                },
                hasPermission('urusan.view') && {
                    title: 'Urusan Pemerintahan',
                    href: '/master/urusan-pemerintahan',
                    pageComponent: 'Master/UrusanPemerintahan/Index',
                    icon: Landmark,
                },
                hasPermission('urusan.view') && {
                    title: 'Program/Kegiatan',
                    href: '/master/program-pemerintahan',
                    pageComponent: 'Master/ProgramPemerintahan/Index',
                    icon: Network,
                },
            ]),
        },
        {
            label: 'Perencanaan Kinerja',
            items: visibleItems([
                hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'rpjmd.manage', 'manage_rpjmd']) && {
                    title: 'RPJMD Kabupaten',
                    href: '/rpjmd',
                    pageComponent: 'Rpjmd/Index',
                    icon: GitBranch,
                },
                hasAnyPermission(['rkpd.view', 'rkpd.manage']) && {
                    title: 'RKPD Kabupaten',
                    href: '/rkpd',
                    pageComponent: 'Rkpd/Index',
                    icon: CalendarDays,
                },
                hasAnyPermission(['renstra.view', 'view_renstra_opd', 'renstra.manage', 'manage_renstra_opd']) && {
                    title: 'Renstra OPD',
                    href: '/renstra-opd',
                    pageComponent: 'RenstraOpd/Index',
                    icon: Layers3,
                },
                hasAnyPermission(['renja.view', 'renja.manage']) && {
                    title: 'Renja OPD',
                    href: '/renja-opd',
                    pageComponent: 'RenjaOpd/Index',
                    icon: FileText,
                },
                hasAnyPermission(['rpjmd.view', 'view_rpjmd', 'renstra.view', 'view_renstra_opd']) && {
                    title: 'Pohon Kinerja',
                    href: '/pohon-kinerja',
                    pageComponent: 'Perencanaan/PohonKinerja',
                    icon: Network,
                },
                hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_perjanjian_kinerja']) && {
                    title: 'Perjanjian Kinerja',
                    href: '/perjanjian-kinerja',
                    pageComponent: 'Kinerja/PerjanjianKinerja/Index',
                    icon: ClipboardCheck,
                },
                hasAnyPermission(['kinerja.view', 'kinerja.manage', 'manage_rencana_aksi']) && {
                    title: 'Rencana Aksi',
                    href: '/rencana-aksi',
                    pageComponent: 'Kinerja/RencanaAksi/Index',
                    icon: ListChecks,
                },
                hasAnyPermission([
                    'rpjmd.view',
                    'view_rpjmd',
                    'rpjmd.manage',
                    'manage_rpjmd',
                    'renstra.view',
                    'view_renstra_opd',
                    'renstra.manage',
                    'manage_renstra_opd',
                ]) && {
                    title: 'Revisi Target',
                    href: '/target-revisions',
                    pageComponent: 'Perencanaan/TargetRevision/Index',
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
                    pageComponent: 'Kinerja/RealisasiKinerja/Index',
                    icon: BarChart3,
                },
            ]),
        },
        {
            label: 'Dokumen Publik',
            items: visibleItems([
                hasAnyPermission(['dokumen.view', 'dokumen.manage', 'manage_dokumen']) && {
                    title: 'Kelengkapan Dokumen',
                    href: '/dokumen-publik',
                    pageComponent: 'Dokumen/PublikChecklist',
                    icon: ClipboardList,
                },
                hasAnyPermission(['dokumen.view', 'dokumen.manage', 'manage_dokumen']) && {
                    title: 'Arsip & Bukti Dukung',
                    href: '/dokumen',
                    pageComponent: 'Dokumen/Index',
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
                    pageComponent: 'Lkjip/Index',
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
                    pageComponent: 'EvaluasiSakip/Index',
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
                    pageComponent: 'Master/User/Index',
                    icon: Users,
                },
                canAccessRolePermission.value && {
                    title: 'Role Permission',
                    href: '/master/role-permission',
                    pageComponent: 'Master/RolePermission/Index',
                    icon: ShieldCheck,
                },
                hasPermission('activity_logs.view') && {
                    title: 'Audit Log',
                    href: '/audit-log',
                    pageComponent: 'AuditLog/Index',
                    icon: ScrollText,
                },
                hasPermission('settings.view') && {
                    title: 'Pengaturan Sistem',
                    href: '/master/system-settings',
                    pageComponent: 'Master/SystemSetting/Index',
                    icon: Settings,
                },
            ]),
        },
    ].filter((group) => group.items.length > 0),
);

const navigationHrefs = computed(() => navigationGroups.value.flatMap((group) => group.items.map((item) => item.href)));
const navigationPageComponents = computed(() => navigationGroups.value.flatMap((group) => group.items.map((item) => item.pageComponent)));
useNavigationPrefetch(navigationHrefs, {
    cacheFor: '5m',
    initialDelayMs: 180,
    staggerMs: 70,
    freshMs: 270_000,
});
usePageComponentPreload(navigationPageComponents, {
    initialDelayMs: 80,
    staggerMs: 45,
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="app-sidebar-admin">
        <SidebarHeader class="app-sidebar-header">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="app-sidebar-brand">
                        <Link :href="route('dashboard')" prefetch="hover" cache-for="5m">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="app-sidebar-content">
            <NavMain v-for="group in navigationGroups" :key="group.label" :label="group.label" :items="group.items" />
        </SidebarContent>

        <SidebarFooter class="app-sidebar-footer">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
