<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    Download,
    Eye,
    FileText,
    Gauge,
    LogIn,
    Menu,
    Network,
    Search,
    ShieldCheck,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type PublicDocument = {
    id: number;
    judul: string;
    filename: string;
    mime_type?: string | null;
    view_url: string;
    download_url: string;
};

type PublicCell = {
    kind: 'status' | 'metric' | 'file' | 'score';
    state: 'available' | 'data' | 'missing' | 'warning' | 'excellent';
    label: string;
    description?: string | null;
    dokumen?: PublicDocument | null;
};

type PublicRow = {
    no: number;
    opd: {
        id: number;
        kode?: string | null;
        nama: string;
        singkatan?: string | null;
        label: string;
    };
    is_ready: boolean;
    cells: Record<string, PublicCell>;
};

type Column = {
    key: string;
    label: string;
};

type SectionId = 'perencanaan' | 'pengukuran' | 'pelaporan' | 'evaluasi';

type SectionUrls = Record<'home' | SectionId, string>;

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
const isMobileMenuOpen = ref(false);
const searchQuery = ref('');

const user = computed(() => page.props.auth.user);
const entryUrl = computed(() => (user.value ? route('dashboard') : route('login')));
const entryLabel = computed(() => (user.value ? 'Dashboard' : 'Login'));
const currentYear = computed(() => new Date().getFullYear());

const navItems = computed(() => [
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

const tableSections = computed(() => [
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
const homeModules = computed(() =>
    tableSections.value.map((section) => ({
        ...section,
        href: props.section_urls[section.id as SectionId],
        completeness: progressWidth(sectionReadyCount(section.id), props.stats.opd_count),
    })),
);

const filteredTableSections = computed(() =>
    visibleTableSections.value.map((section) => ({
        ...section,
        rows: filterRows(section.rows),
    })),
);
const currentRowsCount = computed(() => currentSection.value?.rows.length ?? 0);
const filteredRowsCount = computed(() => filteredTableSections.value[0]?.rows.length ?? 0);
const selectedYearLabel = computed(() => `Tahun ${props.filters.tahun}`);

const statCards = computed(() => [
    {
        label: 'OPD aktif',
        value: props.stats.opd_count,
        note: 'perangkat daerah',
    },
    {
        label: 'Dokumen publik',
        value: props.stats.public_document_count,
        note: 'dokumen resmi',
    },
    {
        label: 'Evaluasi SAKIP',
        value: props.stats.evaluation_count,
        note: 'OPD dinilai',
    },
    {
        label: 'Rata-rata SAKIP',
        value: props.stats.average_sakip !== null && props.stats.average_sakip !== undefined ? formatDecimal(props.stats.average_sakip) : '-',
        note: `tahun ${props.meta.tahun}`,
    },
]);

function formatDecimal(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}

function cellClass(cell?: PublicCell): string {
    return {
        available: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        excellent: 'border-sky-200 bg-sky-50 text-sky-800',
        data: 'border-blue-200 bg-blue-50 text-blue-800',
        warning: 'border-amber-200 bg-amber-50 text-amber-800',
        missing: 'border-slate-200 bg-slate-50 text-slate-500',
    }[cell?.state ?? 'missing'];
}

function dotClass(cell?: PublicCell): string {
    return {
        available: 'bg-emerald-500',
        excellent: 'bg-sky-500',
        data: 'bg-blue-500',
        warning: 'bg-amber-500',
        missing: 'bg-slate-300',
    }[cell?.state ?? 'missing'];
}

function cycleCardClass(id: string): string {
    return (
        {
            perencanaan: 'cycle-card-planning',
            pengukuran: 'cycle-card-measurement',
            pelaporan: 'cycle-card-reporting',
            evaluasi: 'cycle-card-evaluation',
        }[id] ?? ''
    );
}

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

function filterRows(rows: PublicRow[]): PublicRow[] {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return rows;
    }

    return rows.filter((row) => rowSearchText(row).includes(query));
}

function rowSearchText(row: PublicRow): string {
    const cellText = Object.values(row.cells)
        .flatMap((cell) => [cell.label, cell.description, cell.dokumen?.judul, cell.dokumen?.filename])
        .filter(Boolean)
        .join(' ');

    return [row.opd.nama, row.opd.singkatan, row.opd.kode, row.opd.label, cellText].filter(Boolean).join(' ').toLowerCase();
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

function emptyTableMessage(sectionRows: PublicRow[]): string {
    if (searchQuery.value.trim() && sectionRows.length === 0) {
        return 'Tidak ada data yang cocok dengan pencarian.';
    }

    return 'Data OPD belum tersedia.';
}

function progressWidth(count: number, total: number): string {
    if (total <= 0) {
        return '0%';
    }

    return `${Math.min(100, Math.max(0, Math.round((count / total) * 100)))}%`;
}

function closeMobileMenu(): void {
    isMobileMenuOpen.value = false;
}
</script>

<template>
    <Head :title="currentSection ? currentSection.title : 'Beranda Publik'" />

    <div class="public-site min-h-dvh bg-[#f6f8fb] text-slate-900">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50">
                <div class="mx-auto flex min-h-9 max-w-7xl items-center gap-2 px-4 text-xs font-medium text-slate-600 sm:px-6 lg:px-8">
                    <ShieldCheck class="h-3.5 w-3.5 text-emerald-800" />
                    Portal publik akuntabilitas kinerja Pemerintah Kabupaten Banjarnegara
                </div>
            </div>
            <div class="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link
                    :href="props.section_urls.home"
                    class="flex min-h-11 items-center gap-3 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                >
                    <img src="/images/logo-banjarnegara.svg" alt="Lambang Kabupaten Banjarnegara" class="h-11 w-11 object-contain" />
                    <div class="leading-tight">
                        <p class="text-sm font-bold uppercase text-emerald-900">E-SAKIP</p>
                        <p class="text-sm font-medium text-slate-700">Kabupaten Banjarnegara</p>
                    </div>
                </Link>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                    <Link
                        v-for="item in navItems"
                        :key="item.id"
                        :href="item.href"
                        class="rounded-md px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                        :class="
                            item.isActive
                                ? 'bg-emerald-800 text-white shadow-sm shadow-emerald-950/20'
                                : 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-800'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-3 lg:flex">
                    <Link
                        :href="entryUrl"
                        class="inline-flex min-h-11 items-center gap-2 rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                    >
                        <LogIn class="h-4 w-4" />
                        {{ entryLabel }}
                    </Link>
                </div>

                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-800 shadow-sm lg:hidden"
                    aria-label="Buka menu navigasi"
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                >
                    <X v-if="isMobileMenuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>

            <div v-if="isMobileMenuOpen" class="border-t border-slate-200 bg-white px-4 py-4 lg:hidden">
                <nav class="grid gap-2" aria-label="Navigasi mobile">
                    <Link
                        v-for="item in navItems"
                        :key="item.id"
                        :href="item.href"
                        class="min-h-11 rounded-md px-3 py-3 text-sm font-semibold"
                        :class="item.isActive ? 'bg-emerald-800 text-white' : 'text-slate-700 hover:bg-emerald-50'"
                        @click="closeMobileMenu"
                    >
                        {{ item.label }}
                    </Link>
                    <Link
                        :href="entryUrl"
                        class="mt-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white"
                    >
                        <LogIn class="h-4 w-4" />
                        {{ entryLabel }}
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <section v-if="!currentSection" id="beranda" class="hero-section relative isolate flex min-h-[74dvh] items-end overflow-hidden pt-28">
                <img
                    src="/images/hero-dieng-banjarnegara.webp"
                    alt="Lanskap Dieng Banjarnegara dengan danau, candi, dan dataran tinggi berkabut"
                    class="hero-photo absolute inset-0 -z-30 h-full w-full object-cover"
                />
                <div class="hero-vignette absolute inset-0 -z-20"></div>

                <div class="mx-auto w-full max-w-7xl px-4 pb-10 pt-14 sm:px-6 lg:px-8">
                    <div class="hero-content max-w-5xl">
                        <p class="hero-kicker text-sm font-semibold uppercase tracking-normal text-amber-200">
                            Portal Publik Akuntabilitas Kinerja
                        </p>
                        <h1 class="hero-title mt-4 max-w-5xl text-4xl font-bold leading-tight text-white sm:text-5xl lg:text-6xl">
                            E-SAKIP Kabupaten Banjarnegara
                        </h1>
                        <p class="mt-5 max-w-3xl text-base leading-8 text-slate-100 sm:text-lg">
                            Selamat datang di kanal informasi kinerja perangkat daerah. Masyarakat dapat melihat dokumen perencanaan, capaian,
                            pelaporan, dan evaluasi SAKIP berdasarkan periode berjalan.
                        </p>

                        <div class="hero-proofline mt-7 grid max-w-3xl gap-x-8 gap-y-3 text-sm font-semibold text-white sm:grid-cols-2 lg:grid-cols-4">
                            <span><CheckCircle2 class="h-4 w-4" /> Perencanaan</span>
                            <span><CheckCircle2 class="h-4 w-4" /> Pengukuran</span>
                            <span><CheckCircle2 class="h-4 w-4" /> Pelaporan</span>
                            <span><CheckCircle2 class="h-4 w-4" /> Evaluasi</span>
                        </div>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a
                                :href="props.section_urls.perencanaan"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-white px-5 py-3 text-sm font-semibold text-emerald-900 shadow-lg shadow-slate-950/15 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-900"
                            >
                                Lihat Data Publik
                                <ChevronRight class="h-4 w-4" />
                            </a>
                            <Link
                                :href="entryUrl"
                                class="hover:bg-white/18 inline-flex min-h-12 items-center justify-center gap-2 rounded-md border border-white/35 bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur transition focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-900"
                            >
                                <LogIn class="h-4 w-4" />
                                {{ entryLabel }}
                            </Link>
                        </div>
                    </div>

                    <dl class="hero-data-strip mt-10 grid border-y border-white/20 py-4 text-white sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="stat in statCards"
                            :key="stat.label"
                            class="border-white/15 py-3 sm:px-4 lg:border-l lg:first:border-l-0"
                        >
                            <dt class="text-xs font-semibold uppercase tracking-normal text-emerald-100">{{ stat.label }}</dt>
                            <dd class="mt-2 text-2xl font-bold leading-none">{{ stat.value }}</dd>
                            <p class="mt-1 text-sm text-slate-200">{{ stat.note }}</p>
                        </div>
                    </dl>
                </div>
            </section>

            <section v-else class="module-header pt-28">
                <div class="mx-auto max-w-7xl px-4 pb-8 pt-8 sm:px-6 lg:px-8">
                    <div class="module-header-panel rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex gap-4">
                                <div class="cycle-icon shrink-0" :class="cycleCardClass(currentSection.id)">
                                    <component :is="currentSection.icon" class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold uppercase text-emerald-800">{{ currentSection.eyebrow }}</p>
                                    <h1 class="mt-1 text-2xl font-bold text-slate-950 sm:text-3xl">{{ currentSection.title }}</h1>
                                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                        {{ currentSection.summary }} Gunakan pencarian dan filter tahun untuk melihat data publik yang relevan.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[22rem]">
                                <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Periode</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ meta.periode_label }}</p>
                                </div>
                                <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Baris tampil</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ filteredRowsCount }} dari {{ currentRowsCount }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cycle-band border-b border-slate-200 bg-white">
                <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 md:grid-cols-4 lg:px-8">
                    <Link
                        v-for="(module, index) in homeModules"
                        :key="module.id"
                        :href="module.href"
                        class="cycle-card"
                        :class="[cycleCardClass(module.id), module.id === activeSection ? 'cycle-card-active' : '']"
                    >
                        <div class="cycle-icon">
                            <component :is="module.icon" class="h-5 w-5" />
                        </div>
                        <div>
                            <p>{{ String(index + 1).padStart(2, '0') }}</p>
                            <span>{{ module.eyebrow }}</span>
                        </div>
                    </Link>
                </div>
            </section>

            <section v-if="!currentSection" class="overview-section bg-[#f6f8fb] py-14 sm:py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase text-emerald-800">Data Publik</p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">Pilih siklus SAKIP yang ingin dilihat</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">
                            Setiap siklus memiliki halaman sendiri supaya tabel perangkat daerah tetap nyaman dibuka saat data dan dokumen semakin
                            banyak.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <Link
                            v-for="module in homeModules"
                            :key="`overview-${module.id}`"
                            :href="module.href"
                            class="module-card group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                            :class="cycleCardClass(module.id)"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="cycle-icon">
                                    <component :is="module.icon" class="h-5 w-5" />
                                </div>
                                <ChevronRight class="mt-1 h-5 w-5 text-slate-400 transition group-hover:translate-x-1 group-hover:text-emerald-800" />
                            </div>
                            <h3 class="mt-5 text-lg font-bold text-slate-950">{{ module.title }}</h3>
                            <p class="mt-2 min-h-16 text-sm leading-6 text-slate-600">{{ module.summary }}</p>
                            <div class="mt-5">
                                <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
                                    <span>Kelengkapan</span>
                                    <span>{{ module.completeness }}</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <span
                                        class="block h-full rounded-full bg-[linear-gradient(90deg,var(--cycle-color),#d6a326)]"
                                        :style="{ width: module.completeness }"
                                    ></span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>

            <section
                v-for="section in filteredTableSections"
                :id="section.id"
                :key="section.id"
                class="scroll-mt-24 border-b border-slate-200 bg-[#f6f8fb] py-14 sm:py-16"
            >
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-7 flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div class="max-w-3xl">
                            <p class="inline-flex items-center gap-2 text-sm font-semibold uppercase text-emerald-800">
                                <component :is="section.icon" class="h-4 w-4" />
                                {{ section.eyebrow }}
                            </p>
                            <h2 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">{{ section.title }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ section.summary }}</p>
                        </div>

                        <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-[13rem_minmax(16rem,24rem)]">
                            <label class="block">
                                <span class="mb-1.5 flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    Tahun
                                </span>
                                <select
                                    :value="props.filters.tahun"
                                    class="min-h-11 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
                                    @change="changeYear"
                                >
                                    <option v-for="year in props.available_years" :key="year" :value="year">
                                        {{ year }}
                                    </option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1.5 flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                                    <Search class="h-3.5 w-3.5" />
                                    Cari tabel
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    class="min-h-11 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
                                    placeholder="Cari OPD, status, dokumen..."
                                />
                            </label>
                        </div>
                    </div>

                    <div class="mb-4 flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            Menampilkan <span class="font-semibold text-slate-950">{{ section.rows.length }}</span> dari
                            <span class="font-semibold text-slate-950">{{ currentRowsCount }}</span> OPD untuk
                            <span class="font-semibold text-slate-950">{{ selectedYearLabel }}</span
                            >.
                        </p>
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800"
                            @click="searchQuery = ''"
                        >
                            Reset pencarian
                        </button>
                    </div>

                    <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="w-16 px-4 py-4 text-xs font-semibold uppercase text-slate-500">No</th>
                                        <th class="min-w-72 px-4 py-4 text-xs font-semibold uppercase text-slate-500">Perangkat Daerah</th>
                                        <th
                                            v-for="column in section.columns"
                                            :key="column.key"
                                            class="min-w-36 px-4 py-4 text-xs font-semibold uppercase text-slate-500"
                                        >
                                            {{ column.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="row in section.rows" :key="`${section.id}-${row.opd.id}`" class="transition hover:bg-slate-50">
                                        <td class="px-4 py-4 text-sm font-medium text-slate-500">{{ row.no }}</td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold leading-6 text-slate-950">{{ row.opd.nama }}</p>
                                        </td>
                                        <td v-for="column in section.columns" :key="column.key" class="px-4 py-4 align-top">
                                            <div class="space-y-2">
                                                <div v-if="row.cells[column.key]?.dokumen" class="flex flex-wrap gap-2">
                                                    <a
                                                        :href="row.cells[column.key].dokumen?.view_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        title="Lihat dokumen"
                                                        :aria-label="`Lihat ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                        class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800"
                                                    >
                                                        <Eye class="h-4 w-4" />
                                                        <span class="sr-only">Lihat</span>
                                                    </a>
                                                    <a
                                                        :href="row.cells[column.key].dokumen?.download_url"
                                                        title="Download dokumen"
                                                        :aria-label="`Download ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                        class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800"
                                                    >
                                                        <Download class="h-4 w-4" />
                                                        <span class="sr-only">Download</span>
                                                    </a>
                                                </div>
                                                <template v-else-if="row.cells[column.key]?.state === 'missing'">
                                                    <span
                                                        class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-400"
                                                        title="Belum tersedia"
                                                        aria-label="Belum tersedia"
                                                    >
                                                        <X class="h-4 w-4" />
                                                        <span class="sr-only">Belum tersedia</span>
                                                    </span>
                                                </template>
                                                <template v-else>
                                                    <span
                                                        class="inline-flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs font-semibold"
                                                        :class="cellClass(row.cells[column.key])"
                                                    >
                                                        <span class="h-2 w-2 rounded-full" :class="dotClass(row.cells[column.key])"></span>
                                                        {{ row.cells[column.key]?.label || 'Belum tersedia' }}
                                                    </span>
                                                    <p v-if="row.cells[column.key]?.description" class="text-xs text-slate-500">
                                                        {{ row.cells[column.key].description }}
                                                    </p>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="section.rows.length === 0">
                                        <td :colspan="section.columns.length + 2" class="px-4 py-10 text-center text-sm text-slate-500">
                                            {{ emptyTableMessage(section.rows) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:hidden">
                        <article
                            v-for="row in section.rows"
                            :key="`${section.id}-mobile-${row.opd.id}`"
                            class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase text-slate-500">No {{ row.no }}</p>
                                    <h3 class="mt-1 text-base font-bold leading-snug text-slate-950">{{ row.opd.nama }}</h3>
                                </div>
                                <span
                                    class="shrink-0 rounded-md border px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        row.is_ready
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                            : 'border-slate-200 bg-slate-50 text-slate-500'
                                    "
                                >
                                    {{ row.is_ready ? 'Ada data' : 'Belum lengkap' }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3">
                                <div v-for="column in section.columns" :key="column.key" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-xs font-semibold uppercase text-slate-500">{{ column.label }}</p>
                                    <div class="mt-2 space-y-2">
                                        <div v-if="row.cells[column.key]?.dokumen" class="flex flex-wrap gap-2">
                                            <a
                                                :href="row.cells[column.key].dokumen?.view_url"
                                                target="_blank"
                                                rel="noopener"
                                                title="Lihat dokumen"
                                                :aria-label="`Lihat ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-700"
                                            >
                                                <Eye class="h-4 w-4" />
                                                <span class="sr-only">Lihat</span>
                                            </a>
                                            <a
                                                :href="row.cells[column.key].dokumen?.download_url"
                                                title="Download dokumen"
                                                :aria-label="`Download ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-700"
                                            >
                                                <Download class="h-4 w-4" />
                                                <span class="sr-only">Download</span>
                                            </a>
                                        </div>
                                        <template v-else-if="row.cells[column.key]?.state === 'missing'">
                                            <span
                                                class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-400"
                                                title="Belum tersedia"
                                                aria-label="Belum tersedia"
                                            >
                                                <X class="h-4 w-4" />
                                                <span class="sr-only">Belum tersedia</span>
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span
                                                class="inline-flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs font-semibold"
                                                :class="cellClass(row.cells[column.key])"
                                            >
                                                <span class="h-2 w-2 rounded-full" :class="dotClass(row.cells[column.key])"></span>
                                                {{ row.cells[column.key]?.label || 'Belum tersedia' }}
                                            </span>
                                            <p v-if="row.cells[column.key]?.description" class="text-xs text-slate-500">
                                                {{ row.cells[column.key].description }}
                                            </p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div
                            v-if="section.rows.length === 0"
                            class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
                        >
                            {{ emptyTableMessage(section.rows) }}
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-slate-950 py-8 text-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-sm text-slate-300 sm:px-6 lg:px-8"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="font-semibold text-white">E-SAKIP Kabupaten Banjarnegara</p>
                    <p>Data publik diperbarui dari status dokumen resmi yang sudah diverifikasi.</p>
                </div>
                <div class="border-t border-white/10 pt-3 text-xs text-slate-400">
                    &copy; {{ currentYear }} Dinas Komunikasi dan Informatika Kabupaten Banjarnegara.
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.public-site {
    --civic-ink: #071916;
    --civic-green: #063f35;
    --civic-gold: #d6a326;
    --civic-blue: #0f5f7d;
    scroll-behavior: smooth;
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
}

.public-site :is(h1, h2) {
    font-family: Cambria, Georgia, 'Times New Roman', serif;
    letter-spacing: 0;
}

.hero-section {
    background-color: #062e2b;
}

.hero-photo {
    transform: scale(1.04);
    transform-origin: center;
    object-position: center 42%;
}

.hero-vignette {
    background:
        linear-gradient(90deg, rgba(4, 35, 32, 0.98) 0%, rgba(4, 35, 32, 0.84) 42%, rgba(4, 35, 32, 0.3) 74%, rgba(4, 35, 32, 0.18) 100%),
        linear-gradient(180deg, rgba(2, 6, 23, 0.2) 0%, rgba(2, 6, 23, 0.08) 48%, rgba(2, 6, 23, 0.72) 100%);
}

.hero-section::after {
    position: absolute;
    inset: auto 0 0 0;
    height: 8rem;
    content: '';
    background: linear-gradient(to bottom, transparent, rgba(246, 248, 251, 0.92));
}

.hero-title {
    text-wrap: balance;
    text-shadow: 0 1.5rem 3rem rgba(2, 6, 23, 0.42);
}

.hero-content {
    border-left: 0.28rem solid var(--civic-gold);
    padding-left: clamp(1rem, 2vw, 1.5rem);
}

.hero-kicker {
    text-shadow: 0 0.8rem 1.6rem rgba(2, 6, 23, 0.46);
}

.hero-proofline span {
    display: flex;
    min-height: 1.75rem;
    align-items: center;
    gap: 0.45rem;
}

.hero-proofline svg {
    color: var(--civic-gold);
}

.hero-data-strip {
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02));
}

.cycle-band {
    position: relative;
    overflow: hidden;
}

.cycle-band::before {
    position: absolute;
    inset: 0;
    content: '';
    background:
        linear-gradient(90deg, rgba(6, 63, 53, 0.08), transparent 34%, rgba(214, 163, 38, 0.11)),
        repeating-linear-gradient(135deg, rgba(15, 23, 42, 0.035) 0 1px, transparent 1px 18px);
    pointer-events: none;
}

.cycle-card {
    position: relative;
    display: flex;
    min-height: 5.5rem;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    padding: 1rem 1.1rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: rgb(15 23 42);
    box-shadow: 0 0.5rem 1.4rem rgb(15 23 42 / 0.05);
    isolation: isolate;
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease;
}

.cycle-card::after {
    position: absolute;
    inset: auto 0 0;
    height: 0.22rem;
    content: '';
    background: var(--cycle-color, var(--civic-green));
    transform: scaleX(0.48);
    transform-origin: left;
    transition: transform 180ms ease;
}

.cycle-card:hover {
    border-color: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 35%, white);
    box-shadow: 0 0.7rem 1.8rem rgb(15 23 42 / 0.08);
}

.cycle-card:hover::after {
    transform: scaleX(1);
}

.cycle-card-active {
    border-color: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 45%, white);
    background: linear-gradient(180deg, white, color-mix(in srgb, var(--cycle-color, var(--civic-green)) 8%, white));
}

.cycle-card-active::after {
    transform: scaleX(1);
}

.cycle-card p {
    margin-bottom: 0.15rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 88%, black);
}

.cycle-card span {
    font-size: 1rem;
    color: var(--civic-ink);
}

.cycle-icon {
    display: inline-flex;
    min-width: 3rem;
    min-height: 3rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 12%, white);
    color: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 86%, black);
}

.cycle-card-planning {
    --cycle-color: #047857;
}

.cycle-card-measurement {
    --cycle-color: #0f5f7d;
}

.cycle-card-reporting {
    --cycle-color: #d6a326;
}

.cycle-card-evaluation {
    --cycle-color: #b42318;
}

.overview-section {
    background: radial-gradient(circle at top left, rgba(4, 120, 87, 0.1), transparent 28rem), linear-gradient(180deg, #f6f8fb, #eef5f2);
}

.module-header {
    background:
        linear-gradient(135deg, rgba(6, 63, 53, 0.08), transparent 24rem),
        repeating-linear-gradient(135deg, rgba(15, 23, 42, 0.035) 0 1px, transparent 1px 18px), #f6f8fb;
}

.module-header-panel {
    position: relative;
    overflow: hidden;
}

.module-header-panel::after {
    position: absolute;
    inset: auto -8rem -7rem auto;
    width: 16rem;
    height: 16rem;
    content: '';
    border-radius: 9999px;
    background: color-mix(in srgb, var(--civic-green) 10%, transparent);
    pointer-events: none;
}

.module-card {
    position: relative;
    isolation: isolate;
    transition:
        border-color 200ms ease,
        box-shadow 200ms ease;
}

.module-card::before {
    position: absolute;
    inset: auto 0 0;
    z-index: -1;
    content: '';
    height: 0.22rem;
    border-radius: 0 0 0.5rem 0.5rem;
    background: var(--cycle-color, var(--civic-green));
    opacity: 0.72;
}

.module-card:hover {
    border-color: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 36%, white);
    box-shadow: 0 0.9rem 2rem rgb(15 23 42 / 0.08);
}

@media (prefers-reduced-motion: reduce) {
    .public-site {
        scroll-behavior: auto;
    }

    .hero-photo {
        animation: none;
    }

    * {
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>
