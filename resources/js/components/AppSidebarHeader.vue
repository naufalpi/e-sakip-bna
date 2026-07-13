<script setup lang="ts">
import Breadcrumb from '@/components/ui/breadcrumb/Breadcrumb.vue';
import BreadcrumbItem from '@/components/ui/breadcrumb/BreadcrumbItem.vue';
import BreadcrumbLink from '@/components/ui/breadcrumb/BreadcrumbLink.vue';
import BreadcrumbList from '@/components/ui/breadcrumb/BreadcrumbList.vue';
import BreadcrumbPage from '@/components/ui/breadcrumb/BreadcrumbPage.vue';
import BreadcrumbSeparator from '@/components/ui/breadcrumb/BreadcrumbSeparator.vue';
import SidebarTrigger from '@/components/ui/sidebar/SidebarTrigger.vue';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import Bell from 'lucide-vue-next/dist/esm/icons/bell.js';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage<SharedData>();
const notificationUnreadCount = computed(() => page.props.notifications?.unread_count ?? 0);
const notificationBadge = computed(() => (notificationUnreadCount.value > 99 ? '99+' : notificationUnreadCount.value || undefined));
const breadcrumbTitleByPrefix: Array<[string, string]> = [
    ['/dashboard', 'Dashboard'],
    ['/workflow/inbox', 'Inbox Workflow'],
    ['/notifications', 'Notifikasi'],
    ['/master/opd-units', 'Unit OPD'],
    ['/master/opd', 'Master OPD'],
    ['/master/periode-tahun', 'Periode Tahun'],
    ['/master/satuan-indikator', 'Satuan Indikator'],
    ['/master/strategi-daerah', 'Strategi Daerah'],
    ['/master/urusan-pemerintahan', 'Urusan Pemerintahan'],
    ['/master/users', 'Master User'],
    ['/master/role-permission', 'Role Permission'],
    ['/master/system-settings', 'Pengaturan Sistem'],
    ['/rpjmd', 'RPJMD Kabupaten'],
    ['/renstra-opd', 'Renstra OPD'],
    ['/pohon-kinerja', 'Pohon Kinerja'],
    ['/perjanjian-kinerja', 'Perjanjian Kinerja'],
    ['/rencana-aksi', 'Rencana Aksi'],
    ['/target-revisions', 'Revisi Target'],
    ['/realisasi-kinerja', 'Realisasi Kinerja'],
    ['/dokumen', 'Dokumen dan Bukti Dukung'],
    ['/lkjip', 'LKJIP'],
    ['/evaluasi-sakip', 'Evaluasi SAKIP'],
    ['/audit-log', 'Audit Log'],
    ['/settings/profile', 'Profil'],
    ['/settings/password', 'Password'],
    ['/settings/appearance', 'Tampilan'],
];

const fallbackBreadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const currentPath = page.url.split('?')[0];
    const title = breadcrumbTitleByPrefix.find(([prefix]) => currentPath === prefix || currentPath.startsWith(`${prefix}/`))?.[1] ?? 'E-SAKIP';

    return [{ title, href: currentPath }];
});

const activeBreadcrumbs = computed(() => (props.breadcrumbs.length > 0 ? props.breadcrumbs : fallbackBreadcrumbs.value));
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="activeBreadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in activeBreadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === activeBreadcrumbs.length - 1 || !item.href">
                                    <BreadcrumbPage class="admin-breadcrumb-page">{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink class="admin-breadcrumb-link" :href="item.href">
                                        {{ item.title }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== activeBreadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>
        <Link
            :href="route('notifications.index')"
            prefetch="hover"
            cache-for="45s"
            class="admin-topbar-notification relative inline-flex size-9 items-center justify-center rounded-md border text-muted-foreground hover:bg-muted hover:text-foreground"
            aria-label="Notifikasi"
        >
            <Bell class="size-4" />
            <span
                v-if="notificationBadge"
                class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-700 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white"
            >
                {{ notificationBadge }}
            </span>
        </Link>
    </header>
</template>
