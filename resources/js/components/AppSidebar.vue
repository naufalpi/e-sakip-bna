<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { BarChart3, Bell, BookOpenCheck, Building2, CalendarDays, ClipboardCheck, FileCheck2, FileText, GitBranch, History, Inbox, Landmark, Layers3, LayoutDashboard, ListChecks, Network, Ruler, ScrollText, Settings, ShieldCheck, Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const hasPermission = (permission: string) => page.props.auth.user?.permissions?.includes(permission) ?? false;
const hasAnyPermission = (permissions: string[]) => permissions.some((permission) => hasPermission(permission));
const visibleItems = (items: Array<NavItem | false>) => items.filter(Boolean) as NavItem[];
const notificationUnreadCount = computed(() => page.props.notifications?.unread_count ?? 0);
const notificationBadge = computed(() => (notificationUnreadCount.value > 99 ? '99+' : notificationUnreadCount.value || undefined));

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
                hasPermission('roles.view') && {
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
const prefetchedNavigationHrefs = new Map<string, number>();
const prefetchTimers: ReturnType<typeof window.setTimeout>[] = [];
const prefetchVisitOptions = {
    method: 'get' as const,
    preserveScroll: true,
    preserveState: false,
    showProgress: false,
};
const prefetchCacheFor = '2m';
const prefetchFreshMs = 110_000;

const normalizedInternalHref = (href: string): string | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const url = new URL(href, window.location.origin);

    if (url.origin !== window.location.origin) {
        return null;
    }

    return `${url.pathname}${url.search}`;
};

const clearPrefetchTimers = () => {
    while (prefetchTimers.length > 0) {
        const timer = prefetchTimers.pop();

        if (timer) {
            window.clearTimeout(timer);
        }
    }
};

const prefetchNavigationHref = (href: string) => {
    const normalizedHref = normalizedInternalHref(href);

    if (!normalizedHref || normalizedHref === `${window.location.pathname}${window.location.search}`) {
        return;
    }

    const lastPrefetchedAt = prefetchedNavigationHrefs.get(normalizedHref);

    if (lastPrefetchedAt && Date.now() - lastPrefetchedAt < prefetchFreshMs) {
        return;
    }

    if (router.getCached(normalizedHref, prefetchVisitOptions) || router.getPrefetching(normalizedHref, prefetchVisitOptions)) {
        prefetchedNavigationHrefs.set(normalizedHref, Date.now());
        return;
    }

    router.prefetch(normalizedHref, prefetchVisitOptions, { cacheFor: prefetchCacheFor });
    prefetchedNavigationHrefs.set(normalizedHref, Date.now());
};

const scheduleNavigationPrefetch = () => {
    if (typeof window === 'undefined' || document.visibilityState === 'hidden') {
        return;
    }

    clearPrefetchTimers();

    const uniqueHrefs = [...new Set(navigationHrefs.value)]
        .map((href) => normalizedInternalHref(href))
        .filter((href): href is string => Boolean(href) && href !== `${window.location.pathname}${window.location.search}`);

    uniqueHrefs.forEach((href, index) => {
        prefetchTimers.push(window.setTimeout(() => prefetchNavigationHref(href), 650 + index * 180));
    });
};

let stopNavigateListener: VoidFunction | undefined;

onMounted(() => {
    scheduleNavigationPrefetch();
    stopNavigateListener = router.on('navigate', () => scheduleNavigationPrefetch());
});

onBeforeUnmount(() => {
    clearPrefetchTimers();
    stopNavigateListener?.();
});

watch(navigationHrefs, () => scheduleNavigationPrefetch());

const footerNavItems: NavItem[] = [];
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
            <NavFooter v-if="footerNavItems.length" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
