<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, BarChart3, Building2, ClipboardCheck, FileCheck2, Gauge, GitBranch, ListChecks, SlidersHorizontal, TrendingUp, Trophy } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

type Option = { id?: number; tahun?: number; label: string };
type CacheMeta = { key: string; version: number; store: string; ttl_seconds: number; generated_at: string };
type Completion = { key: string; label: string; count: number; total: number; percent: number };
type WorkflowStatus = { status: string; label: string; count: number };
type RecommendationStatus = { status: string; label: string; count: number };
type Distribution = { status: string; label: string; count: number; percent: number };
type AchievementYear = { tahun: number; rata_capaian: number; indikator_count: number; selected: boolean };
type QuarterlyAchievement = { triwulan: string; label: string; rata_capaian: number; indikator_count: number; opd_count: number; completion_percent: number };
type AchievementIndicator = {
    id: number;
    realisasi_kinerja_id: number;
    opd_id: number;
    opd?: string | null;
    indikator: string;
    target?: number | null;
    target_text?: string | null;
    realisasi?: number | null;
    realisasi_text?: string | null;
    capaian_persen?: number | null;
    status_capaian?: string | null;
    serapan_anggaran_persen?: number | null;
    status_efisiensi?: string | null;
    periode_realisasi?: string | null;
    triwulan?: string | null;
    triwulan_label?: string | null;
    detail_url?: string | null;
    opd_detail_url?: string | null;
};
type ProgressOpd = {
    opd_id: number;
    kode?: string | null;
    nama: string;
    singkatan?: string | null;
    modules: Record<string, boolean>;
    progress_percent: number;
    nilai_evaluasi?: string | number | null;
    predikat?: string | null;
    status_evaluasi?: string | null;
    capaian_persen?: number | null;
    rekomendasi_terbuka_count: number;
    detail_url?: string | null;
    renstra_url?: string | null;
    pk_url?: string | null;
    rencana_aksi_url?: string | null;
    realisasi_url?: string | null;
    lkjip_url?: string | null;
    evaluasi_url?: string | null;
};
type OpdPerformanceRank = Pick<ProgressOpd, 'opd_id' | 'kode' | 'nama' | 'singkatan' | 'progress_percent' | 'capaian_persen' | 'nilai_evaluasi' | 'predikat' | 'rekomendasi_terbuka_count'> & {
    rank: number;
    monitoring_score: number;
    detail_url?: string | null;
};
type EvaluationRank = { id: number; opd?: string | null; nilai_akhir: string | number; predikat?: string | null; status: string };
type OpenRecommendation = {
    id: number;
    opd?: string | null;
    nomor?: string | null;
    rekomendasi: string;
    prioritas: string;
    status_tindak_lanjut: string;
    target_tanggal?: string | null;
};
type OverdueRecommendation = OpenRecommendation & { overdue_days: number };
type OpdWithoutRealization = { id: number; kode?: string | null; nama: string; singkatan?: string | null };
type LatestWorkflow = {
    id: number;
    module: string;
    module_label: string;
    status: string;
    status_label: string;
    submitted_by?: string | null;
    current_reviewer?: string | null;
    updated_at?: string | null;
};

const props = defineProps<{
    dashboard: {
        type: 'kabupaten' | 'opd' | 'pimpinan' | 'evaluasi';
        title: string;
        description: string;
        tahun: number;
        can_filter_opd: boolean;
    };
    filters: { tahun: number; opd_id?: number | null };
    cache: CacheMeta;
    opdOptions: Option[];
    periodeOptions: Option[];
    stats: {
        opd_count: number;
        rpjmd_count: number;
        rpjmd_linked_opd_count: number;
        renstra_opd_count: number;
        perjanjian_kinerja_opd_count: number;
        rencana_aksi_opd_count: number;
        realisasi_opd_count: number;
        lkjip_opd_count: number;
        evaluasi_opd_count: number;
        avg_capaian: number;
        avg_evaluasi: number;
        rekomendasi_terbuka_count: number;
        rekomendasi_overdue_count: number;
        opd_belum_realisasi_count: number;
        indikator_merah_count: number;
        indikator_kuning_count: number;
        indikator_hijau_count: number;
        workflow_pending_count: number;
    };
    moduleCompletion: Completion[];
    progressOpd: ProgressOpd[];
    opdPerformanceRanking: OpdPerformanceRank[];
    achievementByYear: AchievementYear[];
    workflowStatus: WorkflowStatus[];
    recommendationStatus: RecommendationStatus[];
    evaluationRanking: EvaluationRank[];
    openRecommendations: OpenRecommendation[];
    overdueRecommendations: OverdueRecommendation[];
    latestWorkflow: LatestWorkflow[];
    achievementStatusDistribution: Distribution[];
    efficiencyStatusDistribution: Distribution[];
    quarterlyAchievement: QuarterlyAchievement[];
    achievementIndicatorDrilldown: AchievementIndicator[];
    opdsWithoutRealization: OpdWithoutRealization[];
    quickLinks: Array<{ label: string; href: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const filterForm = reactive({
    tahun: String(props.filters.tahun ?? props.dashboard.tahun),
    opd_id: props.filters.opd_id ? String(props.filters.opd_id) : '',
});

const applyFilters = () => {
    router.get(route('dashboard'), filterForm, { preserveState: true, replace: true });
};

const resetFilters = () => {
    filterForm.tahun = String(props.dashboard.tahun);
    filterForm.opd_id = '';
    applyFilters();
};

const moduleLabels: Record<string, string> = {
    rpjmd: 'RPJMD',
    renstra: 'Renstra',
    pk: 'PK',
    rencana_aksi: 'Rencana Aksi',
    realisasi: 'Realisasi',
    lkjip: 'LKJIP',
    evaluasi: 'Evaluasi',
};

const metricCards = computed(() => [
    { label: 'OPD Terpantau', value: props.stats.opd_count, helper: `${props.stats.rpjmd_count} RPJMD aktif`, icon: Building2 },
    { label: 'OPD Input Renstra', value: props.stats.renstra_opd_count, helper: `dari ${props.stats.opd_count} OPD`, icon: GitBranch },
    { label: 'OPD Input Realisasi', value: props.stats.realisasi_opd_count, helper: `capaian rata-rata ${formatPercent(props.stats.avg_capaian)}`, icon: BarChart3 },
    { label: 'Nilai Evaluasi', value: formatScore(props.stats.avg_evaluasi), helper: `${props.stats.evaluasi_opd_count} OPD sudah dievaluasi`, icon: FileCheck2 },
    { label: 'Workflow Perlu Diproses', value: props.stats.workflow_pending_count, helper: 'status diajukan/revisi', icon: ClipboardCheck },
    { label: 'Rekomendasi Terbuka', value: props.stats.rekomendasi_terbuka_count, helper: 'belum/proses/perlu perbaikan', icon: ListChecks },
    { label: 'OPD Belum Realisasi', value: props.stats.opd_belum_realisasi_count, helper: 'perlu input capaian tahun berjalan', icon: AlertTriangle },
    { label: 'Lewat Target TL', value: props.stats.rekomendasi_overdue_count, helper: 'rekomendasi belum selesai melewati target', icon: AlertTriangle },
]);

function formatPercent(value?: number | string | null) {
    const number = Number(value ?? 0);
    return `${number.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

function formatScore(value?: number | string | null) {
    const number = Number(value ?? 0);
    return number.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function formatDateTime(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatMetricValue(value?: number | string | null, text?: string | null) {
    if (text) {
        return text;
    }

    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });
}

function barWidth(value?: number | string | null) {
    return `${Math.min(Math.max(Number(value ?? 0), 0), 100)}%`;
}

function statusLabel(status?: string | null) {
    return (
        {
            merah: 'Merah',
            kuning: 'Kuning',
            hijau: 'Hijau',
            efisien: 'Efisien',
            cukup_efisien: 'Cukup efisien',
            tidak_efisien: 'Tidak efisien',
        }[status ?? ''] ?? (status ? status.replaceAll('_', ' ') : '-')
    );
}

function statusClass(status?: string | null) {
    return (
        {
            draft: 'bg-slate-100 text-slate-700',
            submitted: 'bg-blue-100 text-blue-800',
            revision: 'bg-amber-100 text-amber-800',
            verified: 'bg-cyan-100 text-cyan-800',
            approved: 'bg-emerald-100 text-emerald-800',
            rejected: 'bg-red-100 text-red-800',
            locked: 'bg-zinc-200 text-zinc-800',
            belum: 'bg-slate-100 text-slate-700',
            proses: 'bg-blue-100 text-blue-800',
            selesai: 'bg-emerald-100 text-emerald-800',
            ditolak: 'bg-red-100 text-red-800',
            perlu_perbaikan: 'bg-amber-100 text-amber-800',
            merah: 'bg-red-100 text-red-800',
            kuning: 'bg-amber-100 text-amber-800',
            hijau: 'bg-emerald-100 text-emerald-800',
            efisien: 'bg-emerald-100 text-emerald-800',
            cukup_efisien: 'bg-blue-100 text-blue-800',
            tidak_efisien: 'bg-red-100 text-red-800',
            tinggi: 'bg-red-100 text-red-800',
            sedang: 'bg-amber-100 text-amber-800',
            rendah: 'bg-slate-100 text-slate-700',
        }[status ?? ''] ?? 'bg-slate-100 text-slate-700'
    );
}

function booleanClass(value: boolean) {
    return value ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-500';
}
</script>

<template>
    <Head :title="dashboard.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal text-foreground">{{ dashboard.title }}</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted-foreground">{{ dashboard.description }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">Data diperbarui {{ formatDateTime(cache.generated_at) }} melalui cache {{ cache.store }} versi {{ cache.version }} selama {{ cache.ttl_seconds }} detik.</p>
                </div>

                <form class="flex flex-col gap-2 md:flex-row md:items-center" @submit.prevent="applyFilters">
                    <select v-model="filterForm.tahun" class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700">
                        <option v-for="option in periodeOptions" :key="option.tahun" :value="option.tahun">{{ option.label }}</option>
                    </select>
                    <select v-if="dashboard.can_filter_opd" v-model="filterForm.opd_id" class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700">
                        <option value="">Semua OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <SlidersHorizontal class="size-4" />
                        Filter
                    </button>
                    <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
                </form>
            </div>

            <div v-if="quickLinks.length" class="flex flex-wrap gap-2">
                <Link v-for="link in quickLinks" :key="link.href" :href="link.href" class="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm hover:bg-muted">
                    {{ link.label }}
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="card in metricCards" :key="card.label" class="rounded-lg border bg-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                            <p class="mt-3 text-2xl font-semibold">{{ card.value }}</p>
                        </div>
                        <component :is="card.icon" class="size-5 text-emerald-700" />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">{{ card.helper }}</p>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Distribusi Status Capaian</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Jumlah indikator berdasarkan warna capaian tahun terpilih.</p>
                    </div>
                    <div class="space-y-3 p-4">
                        <div v-for="row in achievementStatusDistribution" :key="row.status" class="space-y-1.5">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.label }}</span>
                                <span class="font-semibold">{{ row.count }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full" :class="row.status === 'merah' ? 'bg-red-600' : row.status === 'kuning' ? 'bg-amber-500' : 'bg-emerald-700'" :style="{ width: `${row.percent}%` }" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Tren Capaian Triwulan</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Rata-rata capaian indikator dan cakupan OPD per triwulan.</p>
                    </div>
                    <div class="space-y-3 p-4">
                        <div v-for="row in quarterlyAchievement" :key="row.triwulan" class="grid grid-cols-[92px_1fr_84px] items-center gap-3 text-sm">
                            <div>
                                <div class="font-medium">{{ row.label }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.opd_count }} OPD</div>
                            </div>
                            <div class="space-y-1">
                                <div class="h-2 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-emerald-700" :style="{ width: barWidth(row.rata_capaian) }" />
                                </div>
                                <div class="h-1 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-slate-400" :style="{ width: `${row.completion_percent}%` }" />
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ formatPercent(row.rata_capaian) }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.indikator_count }} indikator</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Efisiensi Kinerja</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Perbandingan capaian kinerja dengan serapan anggaran.</p>
                    </div>
                    <div class="space-y-3 p-4">
                        <div v-for="row in efficiencyStatusDistribution" :key="row.status" class="space-y-1.5">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.label }}</span>
                                <span class="font-semibold">{{ row.count }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-emerald-700" :class="{ 'bg-blue-600': row.status === 'cukup_efisien', 'bg-red-600': row.status === 'tidak_efisien' }" :style="{ width: `${row.percent}%` }" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <div v-for="item in moduleCompletion" :key="item.key" class="rounded-lg border bg-card p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium">{{ item.label }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ item.count }} / {{ item.total }} OPD</p>
                        </div>
                        <span class="text-lg font-semibold">{{ item.percent }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-emerald-700" :style="{ width: `${item.percent}%` }" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Trophy class="size-4 text-emerald-700" />
                            <h2 class="text-sm font-semibold">Ranking Monitoring OPD</h2>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Skor gabungan progress input, capaian, nilai evaluasi, dan rekomendasi terbuka.</p>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in opdPerformanceRanking" :key="row.opd_id" class="grid grid-cols-[44px_1fr_92px] items-center gap-3 px-4 py-3 text-sm">
                            <div class="flex size-8 items-center justify-center rounded-full bg-emerald-50 text-sm font-semibold text-emerald-800">
                                {{ row.rank }}
                            </div>
                            <div class="min-w-0">
                                <Link :href="row.detail_url || route('dashboard', { tahun: filters.tahun, opd_id: row.opd_id })" class="truncate font-medium text-emerald-800 hover:underline">
                                    {{ row.singkatan || row.nama }}
                                </Link>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>Progress {{ row.progress_percent }}%</span>
                                    <span>Capaian {{ row.capaian_persen === null || row.capaian_persen === undefined ? '-' : formatPercent(row.capaian_persen) }}</span>
                                    <span>Evaluasi {{ row.nilai_evaluasi ?? '-' }}</span>
                                    <span>{{ row.rekomendasi_terbuka_count }} rekomendasi</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-base font-semibold">{{ formatScore(row.monitoring_score) }}</div>
                                <div class="text-xs text-muted-foreground">skor</div>
                            </div>
                        </div>
                        <div v-if="opdPerformanceRanking.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada data OPD untuk diranking.</div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Gauge class="size-4 text-emerald-700" />
                            <h2 class="text-sm font-semibold">Drilldown Capaian Indikator</h2>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Prioritas indikator dimulai dari status merah, lalu kuning, lalu hijau.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3">Indikator</th>
                                    <th class="px-4 py-3">Target / Realisasi</th>
                                    <th class="px-4 py-3">Capaian</th>
                                    <th class="px-4 py-3">Efisiensi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in achievementIndicatorDrilldown" :key="row.id" class="border-b last:border-0">
                                    <td class="px-4 py-3">
                                        <Link :href="row.detail_url || route('realisasi-kinerja.show', row.realisasi_kinerja_id)" class="font-medium text-emerald-800 hover:underline">
                                            {{ row.indikator }}
                                        </Link>
                                        <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                            <Link v-if="row.opd_detail_url" :href="row.opd_detail_url" class="hover:text-emerald-800 hover:underline">{{ row.opd || '-' }}</Link>
                                            <span v-else>{{ row.opd || '-' }}</span>
                                            <span>{{ row.triwulan_label || row.periode_realisasi || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <div>Target {{ formatMetricValue(row.target, row.target_text) }}</div>
                                        <div class="mt-1 text-muted-foreground">Realisasi {{ formatMetricValue(row.realisasi, row.realisasi_text) }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ row.capaian_persen === null || row.capaian_persen === undefined ? '-' : formatPercent(row.capaian_persen) }}</div>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status_capaian)">
                                            {{ statusLabel(row.status_capaian) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs text-muted-foreground">Serapan {{ row.serapan_anggaran_persen === null || row.serapan_anggaran_persen === undefined ? '-' : formatPercent(row.serapan_anggaran_persen) }}</div>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status_efisiensi)">
                                            {{ statusLabel(row.status_efisiensi) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="achievementIndicatorDrilldown.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">Belum ada data capaian indikator.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold">Progress Input per OPD</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Urutan dimulai dari OPD dengan progress terendah.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">OPD</th>
                                <th class="px-4 py-3">Modul</th>
                                <th class="px-4 py-3">Progress</th>
                                <th class="px-4 py-3">Capaian</th>
                                <th class="px-4 py-3">Evaluasi</th>
                                <th class="px-4 py-3">Rekomendasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in progressOpd" :key="row.opd_id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <Link :href="row.detail_url || route('dashboard', { tahun: filters.tahun, opd_id: row.opd_id })" class="font-medium text-emerald-800 hover:underline">
                                        {{ row.singkatan || row.nama }}
                                    </Link>
                                    <div class="text-xs text-muted-foreground">{{ row.kode || row.nama }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="(done, key) in row.modules" :key="key" class="rounded-full border px-2 py-1 text-xs" :class="booleanClass(done)">
                                            {{ moduleLabels[key] ?? key }}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <Link v-if="row.renstra_url" :href="row.renstra_url" class="text-emerald-800 hover:underline">Renstra</Link>
                                        <Link v-if="row.pk_url" :href="row.pk_url" class="text-emerald-800 hover:underline">PK</Link>
                                        <Link v-if="row.rencana_aksi_url" :href="row.rencana_aksi_url" class="text-emerald-800 hover:underline">Rencana Aksi</Link>
                                        <Link v-if="row.realisasi_url" :href="row.realisasi_url" class="text-emerald-800 hover:underline">Realisasi</Link>
                                        <Link v-if="row.evaluasi_url" :href="row.evaluasi_url" class="text-emerald-800 hover:underline">Evaluasi</Link>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex min-w-32 items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div class="h-full rounded-full bg-emerald-700" :style="{ width: `${row.progress_percent}%` }" />
                                        </div>
                                        <span class="w-10 text-right text-xs font-medium">{{ row.progress_percent }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ row.capaian_persen === null || row.capaian_persen === undefined ? '-' : formatPercent(row.capaian_persen) }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ row.nilai_evaluasi ?? '-' }}</div>
                                    <div class="text-xs text-muted-foreground">Predikat {{ row.predikat || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="row.rekomendasi_terbuka_count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">
                                        {{ row.rekomendasi_terbuka_count }} terbuka
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="progressOpd.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada OPD aktif pada cakupan dashboard ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <div class="flex items-center gap-2">
                            <TrendingUp class="size-4 text-emerald-700" />
                            <h2 class="text-sm font-semibold">Capaian Indikator per Tahun</h2>
                        </div>
                    </div>
                    <div class="space-y-3 p-4">
                        <div v-for="row in achievementByYear" :key="row.tahun" class="grid grid-cols-[70px_1fr_90px] items-center gap-3 text-sm">
                            <span class="font-medium">{{ row.tahun }}</span>
                            <div class="h-3 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full" :class="row.selected ? 'bg-emerald-700' : 'bg-slate-400'" :style="{ width: barWidth(row.rata_capaian) }" />
                            </div>
                            <span class="text-right text-xs text-muted-foreground">{{ formatPercent(row.rata_capaian) }}</span>
                        </div>
                        <div v-if="achievementByYear.length === 0" class="py-8 text-center text-sm text-muted-foreground">Belum ada data realisasi indikator.</div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Status Workflow</h2>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in workflowStatus" :key="row.status" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.label }}</span>
                            <span class="font-semibold">{{ row.count }}</span>
                        </div>
                        <div v-if="workflowStatus.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada workflow pada tahun ini.</div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Nilai Evaluasi SAKIP per OPD</h2>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in evaluationRanking" :key="row.id" class="grid grid-cols-[1fr_80px_70px] items-center gap-3 px-4 py-3 text-sm">
                            <span class="font-medium">{{ row.opd || '-' }}</span>
                            <span class="text-right font-semibold">{{ row.nilai_akhir }}</span>
                            <span class="rounded-full px-2 py-1 text-center text-xs font-medium" :class="statusClass(row.status)">{{ row.predikat || '-' }}</span>
                        </div>
                        <div v-if="evaluationRanking.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada nilai evaluasi SAKIP.</div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Status Tindak Lanjut Rekomendasi</h2>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in recommendationStatus" :key="row.status" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.label }}</span>
                            <span class="font-semibold">{{ row.count }}</span>
                        </div>
                        <div v-if="recommendationStatus.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada rekomendasi evaluasi.</div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">OPD Belum Input Realisasi</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Daftar OPD aktif yang belum memiliki realisasi pada tahun terpilih.</p>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in opdsWithoutRealization" :key="row.id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <div>
                                <div class="font-medium">{{ row.singkatan || row.nama }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.kode || '-' }}</div>
                            </div>
                            <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Belum input</span>
                        </div>
                        <div v-if="opdsWithoutRealization.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Semua OPD pada cakupan ini sudah memiliki realisasi.</div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Rekomendasi Lewat Target</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Rekomendasi terbuka dengan target tindak lanjut yang sudah terlewati.</p>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in overdueRecommendations" :key="row.id" class="px-4 py-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ row.opd || '-' }}</div>
                                    <p class="mt-1 text-muted-foreground">{{ row.rekomendasi }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.prioritas)">{{ row.prioritas }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                <span>{{ row.nomor || 'Tanpa nomor' }}</span>
                                <span>Target {{ row.target_tanggal || '-' }}</span>
                                <span class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800">{{ row.overdue_days }} hari lewat</span>
                            </div>
                        </div>
                        <div v-if="overdueRecommendations.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Tidak ada rekomendasi lewat target.</div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Rekomendasi Belum Selesai</h2>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in openRecommendations" :key="row.id" class="px-4 py-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ row.opd || '-' }}</div>
                                    <p class="mt-1 text-muted-foreground">{{ row.rekomendasi }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.prioritas)">{{ row.prioritas }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                <span>{{ row.nomor || 'Tanpa nomor' }}</span>
                                <span v-if="row.target_tanggal">Target {{ row.target_tanggal }}</span>
                                <span class="rounded-full px-2 py-0.5 font-medium" :class="statusClass(row.status_tindak_lanjut)">{{ row.status_tindak_lanjut }}</span>
                            </div>
                        </div>
                        <div v-if="openRecommendations.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Tidak ada rekomendasi terbuka.</div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-semibold">Workflow Terbaru</h2>
                    </div>
                    <div class="divide-y">
                        <div v-for="row in latestWorkflow" :key="row.id" class="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                            <div>
                                <div class="font-medium">{{ row.module_label }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ row.submitted_by || 'Belum ada pengaju' }}
                                    <span v-if="row.current_reviewer">/ Reviewer {{ row.current_reviewer }}</span>
                                </div>
                            </div>
                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.status_label }}</span>
                        </div>
                        <div v-if="latestWorkflow.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada riwayat workflow.</div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
