<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    CheckCircle2,
    ChevronRight,
    Download,
    FileCheck2,
    FileText,
    Gauge,
    LogIn,
    Menu,
    Network,
    ShieldCheck,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
const isPageReady = ref(false);

const user = computed(() => page.props.auth.user);
const entryUrl = computed(() => (user.value ? route('dashboard') : route('login')));
const entryLabel = computed(() => (user.value ? 'Dashboard' : 'Login'));

onMounted(() => {
    window.requestAnimationFrame(() => {
        isPageReady.value = true;
    });
});

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
        completeness: progressWidth(section.rows.filter((row) => row.is_ready).length, props.stats.opd_count),
    })),
);

const heroTitle = computed(() =>
    currentSection.value ? `${currentSection.value.title} Publik` : 'Selamat Datang di E-SAKIP Kabupaten Banjarnegara',
);
const heroDescription = computed(() =>
    currentSection.value
        ? `${currentSection.value.summary} Data ditampilkan pada halaman khusus agar tetap ringan saat jumlah dokumen bertambah.`
        : 'Portal publik untuk melihat informasi perencanaan, pengukuran, pelaporan, dan evaluasi kinerja perangkat daerah secara ringkas dan mudah dipahami.',
);
const primaryCtaHref = computed(() => (currentSection.value ? `#${currentSection.value.id}` : props.section_urls.perencanaan));
const primaryCtaLabel = computed(() => (currentSection.value ? 'Lihat Tabel' : 'Mulai dari Perencanaan'));

const statCards = computed(() => [
    {
        label: 'OPD aktif',
        value: props.stats.opd_count,
        note: 'perangkat daerah',
        icon: Building2,
    },
    {
        label: 'Dokumen publik',
        value: props.stats.public_document_count,
        note: 'dokumen resmi',
        icon: FileCheck2,
    },
    {
        label: 'Evaluasi SAKIP',
        value: props.stats.evaluation_count,
        note: 'OPD dinilai',
        icon: BarChart3,
    },
    {
        label: 'Rata-rata SAKIP',
        value: props.stats.average_sakip !== null && props.stats.average_sakip !== undefined ? formatDecimal(props.stats.average_sakip) : '-',
        note: `tahun ${props.meta.tahun}`,
        icon: CheckCircle2,
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
        <Transition name="page-loader">
            <div v-if="!isPageReady" class="page-loader fixed inset-0 z-[70] flex items-center justify-center bg-[#071916] text-white">
                <div class="w-64 text-center">
                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-md border border-emerald-300/35 bg-white/10 shadow-2xl shadow-emerald-950/50"
                    >
                        <img src="/images/logo-banjarnegara.svg" alt="" class="h-12 w-12 object-contain" />
                    </div>
                    <p class="mt-5 text-sm font-semibold uppercase text-emerald-100">Memuat Portal Publik</p>
                    <div class="loader-track mt-4"><span></span></div>
                </div>
            </div>
        </Transition>

        <header class="shadow-slate-900/8 fixed inset-x-0 top-0 z-50 border-b border-emerald-900/15 bg-white shadow-lg">
            <div class="h-1 bg-[linear-gradient(90deg,#063f35,#d6a326,#0f5f7d)]"></div>
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link
                    :href="props.section_urls.home"
                    class="flex min-h-11 items-center gap-3 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                >
                    <img src="/images/logo-banjarnegara.svg" alt="Lambang Kabupaten Banjarnegara" class="h-12 w-12 object-contain" />
                    <div class="leading-tight">
                        <p class="text-sm font-semibold uppercase text-emerald-800">E-SAKIP</p>
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
            <section
                id="beranda"
                class="hero-section relative isolate flex items-center overflow-hidden pt-24"
                :class="currentSection ? 'min-h-[62dvh]' : 'min-h-[88dvh]'"
            >
                <img
                    src="/images/hero-dieng-banjarnegara.webp"
                    alt="Lanskap Dieng Banjarnegara dengan danau, candi, dan dataran tinggi berkabut"
                    class="hero-photo absolute inset-0 -z-30 h-full w-full object-cover"
                />
                <div class="hero-vignette absolute inset-0 -z-20"></div>
                <div class="hero-grid absolute inset-0 -z-10"></div>
                <div class="hero-grain absolute inset-0 -z-10"></div>
                <div class="hero-ridge absolute -z-10"></div>

                <div
                    v-if="!currentSection"
                    class="hero-status-panel bg-slate-950/36 pointer-events-none absolute right-6 top-32 hidden w-72 rounded-lg border border-white/20 p-4 text-white shadow-2xl shadow-slate-950/25 backdrop-blur-xl lg:block"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold uppercase text-emerald-100">Layanan Kinerja</span>
                        <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-300 shadow-lg shadow-emerald-300/70"></span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div class="hero-progress-row">
                            <span>Perencanaan</span>
                            <strong>{{ stats.planning_ready_count }}/{{ stats.opd_count }}</strong>
                        </div>
                        <div class="hero-progress-track">
                            <span class="hero-progress-fill" :style="{ width: progressWidth(stats.planning_ready_count, stats.opd_count) }"></span>
                        </div>
                        <div class="hero-progress-row">
                            <span>Pelaporan</span>
                            <strong>{{ stats.report_ready_count }}/{{ stats.opd_count }}</strong>
                        </div>
                        <div class="hero-progress-track">
                            <span class="hero-progress-fill" :style="{ width: progressWidth(stats.report_ready_count, stats.opd_count) }"></span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!currentSection"
                    class="asn-walkway pointer-events-none absolute bottom-8 right-4 hidden h-24 w-[34rem] overflow-hidden lg:block"
                    aria-hidden="true"
                >
                    <div class="asn-ground"></div>
                    <div class="asn-person asn-one">
                        <span class="asn-head"></span>
                        <span class="asn-body"></span>
                        <span class="asn-arm left"></span>
                        <span class="asn-arm right"></span>
                        <span class="asn-leg left"></span>
                        <span class="asn-leg right"></span>
                        <span class="asn-folder"></span>
                    </div>
                    <div class="asn-person asn-two">
                        <span class="asn-head"></span>
                        <span class="asn-body"></span>
                        <span class="asn-arm left"></span>
                        <span class="asn-arm right"></span>
                        <span class="asn-leg left"></span>
                        <span class="asn-leg right"></span>
                        <span class="asn-folder"></span>
                    </div>
                    <div class="asn-person asn-three">
                        <span class="asn-head"></span>
                        <span class="asn-body"></span>
                        <span class="asn-arm left"></span>
                        <span class="asn-arm right"></span>
                        <span class="asn-leg left"></span>
                        <span class="asn-leg right"></span>
                        <span class="asn-folder"></span>
                    </div>
                </div>

                <div class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <div class="animate-rise max-w-4xl lg:max-w-3xl">
                        <p
                            class="inline-flex rounded-md border border-white/25 bg-white/10 px-3 py-2 text-sm font-semibold uppercase tracking-normal text-emerald-50 backdrop-blur"
                        >
                            {{ currentSection ? currentSection.eyebrow : 'Transparansi Akuntabilitas Kinerja' }}
                        </p>
                        <h1 class="hero-title mt-6 max-w-4xl text-4xl font-bold leading-tight text-white sm:text-5xl lg:text-6xl">
                            {{ heroTitle }}
                        </h1>
                        <p class="mt-5 max-w-3xl text-base leading-8 text-emerald-50 sm:text-lg">
                            {{ heroDescription }}
                        </p>
                        <div class="hero-proofline mt-6 flex flex-wrap gap-2 text-xs font-semibold uppercase text-emerald-50/90">
                            <span>Perencanaan</span>
                            <span>Pengukuran</span>
                            <span>Pelaporan</span>
                            <span>Evaluasi</span>
                        </div>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a
                                :href="primaryCtaHref"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-white px-5 py-3 text-sm font-semibold text-emerald-900 shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-900"
                            >
                                {{ primaryCtaLabel }}
                                <ChevronRight class="h-4 w-4" />
                            </a>
                            <Link
                                v-if="currentSection"
                                :href="props.section_urls.home"
                                class="hover:bg-white/18 inline-flex min-h-12 items-center justify-center gap-2 rounded-md border border-white/35 bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-900"
                            >
                                Kembali Beranda
                            </Link>
                            <Link
                                :href="entryUrl"
                                class="hover:bg-white/18 inline-flex min-h-12 items-center justify-center gap-2 rounded-md border border-white/35 bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-900"
                            >
                                <LogIn class="h-4 w-4" />
                                {{ entryLabel }}
                            </Link>
                        </div>
                    </div>

                    <dl class="mt-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="(stat, index) in statCards"
                            :key="stat.label"
                            class="animate-rise bg-white/12 rounded-lg border border-white/20 p-4 text-white shadow-sm backdrop-blur"
                            :style="{ animationDelay: `${100 + index * 70}ms` }"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-sm font-medium text-emerald-50">{{ stat.label }}</dt>
                                <component :is="stat.icon" class="h-5 w-5 text-amber-200" />
                            </div>
                            <dd class="mt-3 text-3xl font-bold leading-none">{{ stat.value }}</dd>
                            <p class="mt-2 text-sm text-emerald-100">{{ stat.note }}</p>
                        </div>
                    </dl>
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
                            class="module-card-3d group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
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
                v-for="section in visibleTableSections"
                :id="section.id"
                :key="section.id"
                class="scroll-mt-24 border-b border-slate-200 bg-[#f6f8fb] py-14 sm:py-16"
            >
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="max-w-3xl">
                            <p class="inline-flex items-center gap-2 text-sm font-semibold uppercase text-emerald-800">
                                <component :is="section.icon" class="h-4 w-4" />
                                {{ section.eyebrow }}
                            </p>
                            <h2 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">{{ section.title }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ section.summary }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">
                            Periode: <span class="font-semibold text-slate-900">{{ meta.periode_label }}</span>
                        </div>
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
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-sm font-bold text-emerald-800"
                                                >
                                                    {{ row.opd.singkatan?.slice(0, 3) || row.no }}
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-950">{{ row.opd.nama }}</p>
                                                    <p class="text-xs text-slate-500">{{ row.opd.kode || 'Kode belum tersedia' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td v-for="column in section.columns" :key="column.key" class="px-4 py-4 align-top">
                                            <div class="space-y-2">
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
                                                <div v-if="row.cells[column.key]?.dokumen" class="flex flex-wrap gap-2">
                                                    <a
                                                        :href="row.cells[column.key].dokumen?.view_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800"
                                                    >
                                                        <FileText class="h-3.5 w-3.5" />
                                                        Lihat
                                                    </a>
                                                    <a
                                                        :href="row.cells[column.key].dokumen?.download_url"
                                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-800"
                                                    >
                                                        <Download class="h-3.5 w-3.5" />
                                                        Download
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="section.rows.length === 0">
                                        <td :colspan="section.columns.length + 2" class="px-4 py-10 text-center text-sm text-slate-500">
                                            Data OPD belum tersedia.
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
                                    <p class="mt-1 text-xs text-slate-500">{{ row.opd.kode || 'Kode belum tersedia' }}</p>
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
                                        <div v-if="row.cells[column.key]?.dokumen" class="flex flex-wrap gap-2">
                                            <a
                                                :href="row.cells[column.key].dokumen?.view_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex min-h-10 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                            >
                                                <FileText class="h-3.5 w-3.5" />
                                                Lihat
                                            </a>
                                            <a
                                                :href="row.cells[column.key].dokumen?.download_url"
                                                class="inline-flex min-h-10 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                            >
                                                <Download class="h-3.5 w-3.5" />
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div
                            v-if="section.rows.length === 0"
                            class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
                        >
                            Data OPD belum tersedia.
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-slate-950 py-8 text-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
            >
                <p>E-SAKIP Kabupaten Banjarnegara</p>
                <p>Data publik diperbarui dari status dokumen resmi yang sudah diverifikasi.</p>
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

.page-loader {
    background: radial-gradient(circle at 50% 38%, rgba(16, 185, 129, 0.18), transparent 34%), linear-gradient(135deg, #071916, #062e2b 54%, #0f172a);
}

.page-loader-enter-active,
.page-loader-leave-active {
    transition:
        opacity 260ms ease,
        transform 260ms ease;
}

.page-loader-enter-from,
.page-loader-leave-to {
    opacity: 0;
    transform: scale(1.015);
}

.loader-track {
    height: 0.35rem;
    overflow: hidden;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.14);
}

.loader-track span {
    display: block;
    height: 100%;
    width: 46%;
    border-radius: inherit;
    background: linear-gradient(90deg, #10b981, #fbbf24, #38bdf8);
    animation: loader-sweep 900ms ease-in-out infinite alternate;
}

.hero-section {
    background-color: #062e2b;
}

.hero-photo {
    transform: scale(1.04);
    transform-origin: center;
    animation: hero-photo-drift 18s ease-in-out infinite alternate;
}

.hero-vignette {
    background:
        linear-gradient(90deg, rgba(4, 35, 32, 0.97) 0%, rgba(4, 35, 32, 0.82) 37%, rgba(4, 35, 32, 0.22) 68%, rgba(4, 35, 32, 0.2) 100%),
        linear-gradient(180deg, rgba(2, 6, 23, 0.18) 0%, rgba(2, 6, 23, 0.05) 46%, rgba(2, 6, 23, 0.68) 100%);
}

.hero-grid {
    opacity: 0.22;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.16) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: linear-gradient(90deg, black, transparent 70%);
}

.hero-grain {
    opacity: 0.16;
    background-image:
        linear-gradient(115deg, rgba(255, 255, 255, 0.08), transparent 38%, rgba(255, 255, 255, 0.06) 58%, transparent),
        repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 4px);
    mix-blend-mode: soft-light;
}

.hero-ridge {
    left: -12rem;
    top: 8rem;
    width: 64rem;
    height: 10rem;
    border-top: 1px solid rgba(255, 255, 255, 0.28);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(90deg, rgba(214, 163, 38, 0.18), transparent);
    transform: rotate(-10deg);
    animation: ridge-sweep 11s ease-in-out infinite alternate;
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

.hero-proofline span {
    display: inline-flex;
    min-height: 2rem;
    align-items: center;
    border-radius: 0.375rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    padding: 0.35rem 0.7rem;
    backdrop-filter: blur(10px);
}

.hero-status-panel {
    animation: panel-float 6s ease-in-out infinite;
}

.hero-progress-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.8125rem;
    color: rgb(209 250 229);
}

.hero-progress-row strong {
    color: white;
}

.hero-progress-track {
    height: 0.5rem;
    overflow: hidden;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.16);
}

.hero-progress-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, rgb(16 185 129), rgb(251 191 36));
    animation: progress-shine 2.8s ease-in-out infinite;
}

.asn-walkway {
    mask-image: linear-gradient(90deg, transparent, black 12%, black 88%, transparent);
}

.asn-ground {
    position: absolute;
    bottom: 0.75rem;
    left: 0;
    right: 0;
    height: 0.25rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.28);
}

.asn-person {
    position: absolute;
    bottom: 1rem;
    width: 2.5rem;
    height: 4.5rem;
    animation: asn-walk 11s linear infinite;
}

.asn-one {
    animation-delay: -1s;
}

.asn-two {
    animation-delay: -4.8s;
    transform: scale(0.92);
}

.asn-three {
    animation-delay: -8.2s;
    transform: scale(0.86);
}

.asn-head,
.asn-body,
.asn-arm,
.asn-leg,
.asn-folder {
    position: absolute;
    display: block;
}

.asn-head {
    left: 0.78rem;
    top: 0;
    width: 0.92rem;
    height: 0.92rem;
    border-radius: 9999px;
    background: #f3c7a4;
    box-shadow: 0 -0.22rem 0 #111827;
}

.asn-body {
    left: 0.55rem;
    top: 1rem;
    width: 1.35rem;
    height: 1.8rem;
    border-radius: 0.35rem 0.35rem 0.2rem 0.2rem;
    background: linear-gradient(#c9aa74, #9d7d45);
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.12);
}

.asn-arm {
    top: 1.18rem;
    width: 0.26rem;
    height: 1.38rem;
    border-radius: 9999px;
    background: #9d7d45;
    transform-origin: top center;
    animation: asn-arm-swing 640ms ease-in-out infinite alternate;
}

.asn-arm.left {
    left: 0.38rem;
}

.asn-arm.right {
    right: 0.38rem;
    animation-delay: 320ms;
}

.asn-leg {
    top: 2.68rem;
    width: 0.3rem;
    height: 1.45rem;
    border-radius: 9999px;
    background: #27364a;
    transform-origin: top center;
    animation: asn-leg-swing 640ms ease-in-out infinite alternate;
}

.asn-leg.left {
    left: 0.82rem;
}

.asn-leg.right {
    right: 0.82rem;
    animation-delay: 320ms;
}

.asn-folder {
    right: -0.18rem;
    top: 1.75rem;
    width: 0.82rem;
    height: 0.58rem;
    border-radius: 0.12rem;
    background: #f59e0b;
    transform: rotate(-7deg);
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
    box-shadow: 0 1.2rem 2.6rem rgb(15 23 42 / 0.07);
    isolation: isolate;
    perspective: 900px;
    transform: perspective(900px) translateZ(0);
    transform-style: preserve-3d;
    transition:
        transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 180ms ease,
        box-shadow 220ms ease;
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
    transform: perspective(900px) translateY(-0.28rem) rotateX(2deg) rotateY(-1.4deg);
    border-color: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 35%, white);
    box-shadow: 0 1.4rem 3rem rgb(15 23 42 / 0.11);
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
    transform: translateZ(1.2rem);
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

.module-card-3d {
    position: relative;
    isolation: isolate;
    perspective: 1100px;
    transform: perspective(1100px) translateZ(0);
    transform-style: preserve-3d;
    transition:
        transform 240ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 200ms ease,
        box-shadow 240ms ease;
}

.module-card-3d::before {
    position: absolute;
    inset: 0.55rem -0.25rem -0.55rem 0.75rem;
    z-index: -1;
    content: '';
    border-radius: inherit;
    background: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 18%, transparent);
    filter: blur(1.1rem);
    opacity: 0;
    transition: opacity 220ms ease;
}

.module-card-3d:hover {
    transform: perspective(1100px) translateY(-0.45rem) rotateX(3deg) rotateY(-2deg);
    border-color: color-mix(in srgb, var(--cycle-color, var(--civic-green)) 36%, white);
    box-shadow: 0 1.6rem 3.6rem rgb(15 23 42 / 0.12);
}

.module-card-3d:hover::before {
    opacity: 1;
}

.animate-rise {
    animation: rise-in 560ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes rise-in {
    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes loader-sweep {
    from {
        transform: translateX(-72%);
    }

    to {
        transform: translateX(126%);
    }
}

@keyframes hero-photo-drift {
    from {
        transform: scale(1.04) translate3d(0, 0, 0);
    }

    to {
        transform: scale(1.09) translate3d(-1.2rem, 0.8rem, 0);
    }
}

@keyframes ridge-sweep {
    from {
        transform: translateX(-1rem) rotate(-10deg);
    }

    to {
        transform: translateX(3rem) rotate(-10deg);
    }
}

@keyframes panel-float {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(10px);
    }
}

@keyframes progress-shine {
    0%,
    100% {
        filter: brightness(1);
    }

    50% {
        filter: brightness(1.2);
    }
}

@keyframes asn-walk {
    from {
        left: 100%;
    }

    to {
        left: -10%;
    }
}

@keyframes asn-arm-swing {
    from {
        transform: rotate(18deg);
    }

    to {
        transform: rotate(-18deg);
    }
}

@keyframes asn-leg-swing {
    from {
        transform: rotate(-14deg);
    }

    to {
        transform: rotate(16deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .public-site {
        scroll-behavior: auto;
    }

    .animate-rise,
    .loader-track span,
    .hero-photo,
    .hero-ridge,
    .hero-status-panel,
    .asn-person,
    .asn-arm,
    .asn-leg,
    .hero-progress-fill {
        animation: none;
    }

    * {
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>
