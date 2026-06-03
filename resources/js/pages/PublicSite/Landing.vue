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

const props = defineProps<{
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

const user = computed(() => page.props.auth.user);
const entryUrl = computed(() => (user.value ? route('dashboard') : route('login')));
const entryLabel = computed(() => (user.value ? 'Dashboard' : 'Login'));

const navItems = [
    { id: 'beranda', label: 'Beranda' },
    { id: 'perencanaan', label: 'Perencanaan' },
    { id: 'pengukuran', label: 'Pengukuran' },
    { id: 'pelaporan', label: 'Pelaporan' },
    { id: 'evaluasi', label: 'Evaluasi' },
];

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

function closeMobileMenu(): void {
    isMobileMenuOpen.value = false;
}
</script>

<template>
    <Head title="Beranda Publik" />

    <div class="public-site min-h-dvh bg-[#f6f8fb] text-slate-900">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-white/20 bg-white/88 shadow-sm shadow-slate-900/5 backdrop-blur-xl">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="#beranda" class="flex min-h-11 items-center gap-3 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                    <img src="/images/logo-banjarnegara.svg" alt="Lambang Kabupaten Banjarnegara" class="h-12 w-12 object-contain" />
                    <div class="leading-tight">
                        <p class="text-sm font-semibold uppercase text-emerald-800">E-SAKIP</p>
                        <p class="text-sm font-medium text-slate-700">Kabupaten Banjarnegara</p>
                    </div>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                    <a
                        v-for="item in navItems"
                        :key="item.id"
                        :href="`#${item.id}`"
                        class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                    >
                        {{ item.label }}
                    </a>
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
                    <a
                        v-for="item in navItems"
                        :key="item.id"
                        :href="`#${item.id}`"
                        class="min-h-11 rounded-md px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        @click="closeMobileMenu"
                    >
                        {{ item.label }}
                    </a>
                    <Link :href="entryUrl" class="mt-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white">
                        <LogIn class="h-4 w-4" />
                        {{ entryLabel }}
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <section id="beranda" class="hero-section relative isolate flex min-h-[82dvh] items-center overflow-hidden pt-24">
                <div class="hero-backdrop absolute inset-0 -z-10"></div>
                <img
                    src="/images/logo-banjarnegara.svg"
                    alt=""
                    aria-hidden="true"
                    class="hero-emblem pointer-events-none absolute right-[-3rem] top-24 -z-10 h-[34rem] w-[34rem] max-w-none opacity-10 sm:right-10 lg:right-24"
                />

                <div class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <div class="max-w-4xl animate-rise">
                        <p class="inline-flex rounded-md border border-white/25 bg-white/10 px-3 py-2 text-sm font-semibold uppercase tracking-normal text-emerald-50 backdrop-blur">
                            Transparansi Akuntabilitas Kinerja
                        </p>
                        <h1 class="mt-6 max-w-4xl text-4xl font-bold leading-tight text-white sm:text-5xl lg:text-6xl">
                            Selamat Datang di E-SAKIP Kabupaten Banjarnegara
                        </h1>
                        <p class="mt-5 max-w-3xl text-base leading-8 text-emerald-50 sm:text-lg">
                            Portal publik untuk melihat informasi perencanaan, pengukuran, pelaporan, dan evaluasi kinerja perangkat daerah secara ringkas dan mudah dipahami.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a
                                href="#perencanaan"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-white px-5 py-3 text-sm font-semibold text-emerald-900 shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-900"
                            >
                                Lihat Data Publik
                                <ChevronRight class="h-4 w-4" />
                            </a>
                            <Link
                                :href="entryUrl"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md border border-white/35 bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/18 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-900"
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
                            class="animate-rise rounded-lg border border-white/20 bg-white/12 p-4 text-white shadow-sm backdrop-blur"
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

            <section class="border-b border-slate-200 bg-white">
                <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 md:grid-cols-4 lg:px-8">
                    <div class="cycle-card">
                        <Network class="h-5 w-5 text-emerald-700" />
                        <span>Perencanaan</span>
                    </div>
                    <div class="cycle-card">
                        <Gauge class="h-5 w-5 text-blue-700" />
                        <span>Pengukuran</span>
                    </div>
                    <div class="cycle-card">
                        <FileText class="h-5 w-5 text-amber-700" />
                        <span>Pelaporan</span>
                    </div>
                    <div class="cycle-card">
                        <ShieldCheck class="h-5 w-5 text-red-700" />
                        <span>Evaluasi</span>
                    </div>
                </div>
            </section>

            <section
                v-for="section in tableSections"
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
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-sm font-bold text-emerald-800">
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
                                    :class="row.is_ready ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-500'"
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

                        <div v-if="section.rows.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                            Data OPD belum tersedia.
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-slate-950 py-8 text-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p>E-SAKIP Kabupaten Banjarnegara</p>
                <p>Data publik diperbarui dari status dokumen resmi yang sudah diverifikasi.</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.public-site {
    scroll-behavior: smooth;
}

.hero-section {
    background-color: #063b34;
}

.hero-backdrop {
    background:
        radial-gradient(circle at 16% 22%, rgba(250, 204, 21, 0.28), transparent 24rem),
        radial-gradient(circle at 86% 28%, rgba(14, 165, 233, 0.24), transparent 26rem),
        linear-gradient(135deg, rgba(4, 47, 46, 0.98), rgba(15, 68, 91, 0.96) 48%, rgba(12, 74, 110, 0.98));
}

.hero-section::after {
    position: absolute;
    inset: auto 0 0 0;
    height: 8rem;
    content: '';
    background: linear-gradient(to bottom, transparent, rgba(246, 248, 251, 0.92));
}

.hero-emblem {
    animation: emblem-float 9s ease-in-out infinite;
}

.cycle-card {
    display: flex;
    min-height: 4rem;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    padding: 1rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: rgb(15 23 42);
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
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

@keyframes emblem-float {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }

    50% {
        transform: translateY(14px) rotate(1deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .public-site {
        scroll-behavior: auto;
    }

    .animate-rise,
    .hero-emblem {
        animation: none;
    }

    * {
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>
