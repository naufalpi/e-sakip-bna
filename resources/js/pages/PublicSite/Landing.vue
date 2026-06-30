<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import FileText from 'lucide-vue-next/dist/esm/icons/file-text.js';
import Gauge from 'lucide-vue-next/dist/esm/icons/gauge.js';
import Network from 'lucide-vue-next/dist/esm/icons/network.js';
import ShieldCheck from 'lucide-vue-next/dist/esm/icons/shield-check.js';
import { computed, ref } from 'vue';

import CycleNavigation from './Components/CycleNavigation.vue';
import PublicDataSection from './Components/PublicDataSection.vue';
import PublicFooter from './Components/PublicFooter.vue';
import PublicHero from './Components/PublicHero.vue';
import PublicModuleHeader from './Components/PublicModuleHeader.vue';
import PublicOverview from './Components/PublicOverview.vue';
import PublicSiteHeader from './Components/PublicSiteHeader.vue';
import './public-site.css';
import type { Column, PublicHomeModule, PublicNavItem, PublicRow, PublicTableSection, SectionId, SectionUrls } from './types';
import { filterRows, progressWidth } from './utils';

const props = defineProps<{
    active_section: SectionId | null;
    section_urls: SectionUrls;
    available_years: number[];
    filters: {
        tahun: number;
    };
    meta: {
        tahun: number;
        periode_label: string;
        generated_at: string;
    };
    stats: {
        opd_count: number;
        planning_ready_count: number;
        measurement_ready_count: number;
        report_ready_count: number;
        evaluation_count: number;
        public_document_count: number;
        average_sakip?: number | null;
    };
    tables: {
        perencanaan: PublicRow[];
        pengukuran: PublicRow[];
        pelaporan: PublicRow[];
        evaluasi: PublicRow[];
    };
}>();

const page = usePage<SharedData>();
const searchQuery = ref('');

const user = computed(() => page.props.auth.user);
const entryUrl = computed(() => (user.value ? route('dashboard') : route('login')));
const entryLabel = computed(() => (user.value ? 'Dashboard' : 'Login'));
const currentYear = computed(() => new Date().getFullYear());

const navItems = computed<PublicNavItem[]>(() => [
    { id: 'beranda', label: 'Beranda', href: props.section_urls.home, isActive: props.active_section === null },
    { id: 'perencanaan', label: 'Perencanaan', href: props.section_urls.perencanaan, isActive: props.active_section === 'perencanaan' },
    { id: 'pengukuran', label: 'Pengukuran', href: props.section_urls.pengukuran, isActive: props.active_section === 'pengukuran' },
    { id: 'pelaporan', label: 'Pelaporan', href: props.section_urls.pelaporan, isActive: props.active_section === 'pelaporan' },
    { id: 'evaluasi', label: 'Evaluasi', href: props.section_urls.evaluasi, isActive: props.active_section === 'evaluasi' },
]);

const perencanaanColumns: Column[] = [
    { key: 'pohon_kinerja', label: 'Pohon Kinerja' },
    { key: 'cascading', label: 'Cascading' },
    { key: 'iku', label: 'IKU' },
    { key: 'renstra', label: 'Renstra' },
    { key: 'renja_rkt', label: 'Renja/RKT' },
    { key: 'rencana_aksi', label: 'Rencana Aksi' },
    { key: 'pk', label: 'PK' },
];

const pengukuranColumns: Column[] = [
    { key: 'tujuan', label: 'Tujuan' },
    { key: 'sasaran_strategis', label: 'Sasaran Strategis' },
    { key: 'program', label: 'Program' },
    { key: 'kegiatan', label: 'Kegiatan' },
    { key: 'sub_kegiatan', label: 'Sub Kegiatan' },
];

const pelaporanColumns = computed<Column[]>(() => [
    { key: 'lkjip', label: `LKJIP (tahun ${props.meta.tahun})` },
    { key: 'tw1', label: 'Laporan TW I' },
    { key: 'tw2', label: 'Laporan TW II' },
    { key: 'tw3', label: 'Laporan TW III' },
    { key: 'tw4', label: 'Laporan TW IV' },
]);

const evaluasiColumns: Column[] = [
    { key: 'nilai_sakip', label: 'Nilai SAKIP' },
    { key: 'lhe_internal', label: 'LHE Internal' },
    { key: 'tindak_lanjut_lhe', label: 'Tindak Lanjut LHE' },
];

const tableSections = computed<PublicTableSection[]>(() => [
    {
        id: 'perencanaan',
        eyebrow: 'Perencanaan',
        title: 'Perencanaan Kinerja',
        summary: `${props.stats.planning_ready_count} dari ${props.stats.opd_count} OPD memiliki rangkaian perencanaan utama.`,
        icon: Network,
        columns: perencanaanColumns,
        rows: props.tables.perencanaan,
    },
    {
        id: 'pengukuran',
        eyebrow: 'Pengukuran',
        title: 'Pengukuran Kinerja',
        summary: `${props.stats.measurement_ready_count} OPD sudah memiliki struktur tujuan sampai sub kegiatan.`,
        icon: Gauge,
        columns: pengukuranColumns,
        rows: props.tables.pengukuran,
    },
    {
        id: 'pelaporan',
        eyebrow: 'Pelaporan',
        title: 'Pelaporan Kinerja',
        summary: `${props.stats.report_ready_count} OPD sudah memiliki data LKJIP pada periode berjalan.`,
        icon: FileText,
        columns: pelaporanColumns.value,
        rows: props.tables.pelaporan,
    },
    {
        id: 'evaluasi',
        eyebrow: 'Evaluasi',
        title: 'Evaluasi Kinerja',
        summary: `${props.stats.evaluation_count} OPD sudah memiliki nilai evaluasi SAKIP.`,
        icon: ShieldCheck,
        columns: evaluasiColumns,
        rows: props.tables.evaluasi,
    },
]);

const activeSection = computed(() => props.active_section);
const currentSection = computed(() => tableSections.value.find((section) => section.id === activeSection.value) ?? null);
const visibleTableSections = computed(() => (currentSection.value ? [currentSection.value] : []));
const homeModules = computed<PublicHomeModule[]>(() =>
    tableSections.value.map((section) => ({
        ...section,
        href: props.section_urls[section.id],
        completeness: progressWidth(sectionReadyCount(section.id), props.stats.opd_count),
    })),
);

const filteredTableSections = computed<PublicTableSection[]>(() =>
    visibleTableSections.value.map((section) => ({
        ...section,
        rows: filterRows(section.rows, searchQuery.value),
    })),
);
const currentRowsCount = computed(() => currentSection.value?.rows.length ?? 0);
const filteredRowsCount = computed(() => filteredTableSections.value[0]?.rows.length ?? 0);
const selectedYearLabel = computed(() => `Tahun ${props.filters.tahun}`);

function sectionReadyCount(id: string): number {
    return (
        {
            perencanaan: props.stats.planning_ready_count,
            pengukuran: props.stats.measurement_ready_count,
            pelaporan: props.stats.report_ready_count,
            evaluasi: props.stats.evaluation_count,
        }[id] ?? 0
    );
}

function changeYear(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const tahun = Number(target.value);
    const destination = currentSection.value ? route('public.section', { section: currentSection.value.id }) : route('home');

    router.get(
        destination,
        { tahun },
        {
            preserveScroll: false,
            preserveState: false,
        },
    );
}
</script>

<template>
    <Head :title="currentSection ? currentSection.title : 'Beranda Publik'" />

    <div class="public-site min-h-dvh bg-white text-slate-900">
        <PublicSiteHeader :home-url="props.section_urls.home" :nav-items="navItems" :entry-url="entryUrl" :entry-label="entryLabel" />

        <main>
            <PublicHero v-if="!currentSection" :planning-url="props.section_urls.perencanaan" :entry-url="entryUrl" :entry-label="entryLabel" />
            <PublicModuleHeader
                v-else
                :section="currentSection"
                :meta="props.meta"
                :filtered-rows-count="filteredRowsCount"
                :current-rows-count="currentRowsCount"
            />

            <CycleNavigation :modules="homeModules" :active-section="activeSection" />
            <PublicOverview v-if="!currentSection" :modules="homeModules" />

            <PublicDataSection
                v-for="section in filteredTableSections"
                :key="section.id"
                :section="section"
                :filters="props.filters"
                :available-years="props.available_years"
                :selected-year-label="selectedYearLabel"
                :current-rows-count="currentRowsCount"
                v-model:search-query="searchQuery"
                @change-year="changeYear"
                @reset-search="searchQuery = ''"
            />
        </main>

        <PublicFooter :year="currentYear" />
    </div>
</template>
