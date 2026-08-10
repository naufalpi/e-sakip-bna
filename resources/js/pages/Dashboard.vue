<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import ArrowRight from 'lucide-vue-next/dist/esm/icons/arrow-right.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import BarChart3 from 'lucide-vue-next/dist/esm/icons/chart-column.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import ChevronLeft from 'lucide-vue-next/dist/esm/icons/chevron-left.js';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import ClipboardCheck from 'lucide-vue-next/dist/esm/icons/clipboard-check.js';
import FileCheck2 from 'lucide-vue-next/dist/esm/icons/file-check-2.js';
import Gauge from 'lucide-vue-next/dist/esm/icons/gauge.js';
import ListChecks from 'lucide-vue-next/dist/esm/icons/list-checks.js';
import TrendingUp from 'lucide-vue-next/dist/esm/icons/trending-up.js';
import AlertTriangle from 'lucide-vue-next/dist/esm/icons/triangle-alert.js';
import Trophy from 'lucide-vue-next/dist/esm/icons/trophy.js';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

type Option = { id?: number; tahun?: number; label: string };
type Completion = { key: string; label: string; count: number; total: number; percent: number };
type WorkflowStatus = { status: string; label: string; count: number };
type RecommendationStatus = { status: string; label: string; count: number };
type Distribution = { status: string; label: string; count: number; percent: number };
type AchievementYear = { tahun: number; rata_capaian: number; indikator_count: number; selected: boolean };
type QuarterlyAchievement = {
    triwulan: string;
    label: string;
    rata_capaian: number;
    indikator_count: number;
    opd_count: number;
    completion_percent: number;
};
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
    triwulan_label?: string | null;
    detail_url?: string | null;
    opd_detail_url?: string | null;
};
type SasaranDrilldown = {
    opd_id: number;
    opd?: string | null;
    sasaran_opd_id?: number | null;
    sasaran: string;
    indicator_count: number;
    avg_capaian?: number | null;
    status_capaian?: string | null;
    merah_count: number;
    kuning_count: number;
    hijau_count: number;
    detail_url?: string | null;
};
type ProgramDrilldown = {
    opd_id: number;
    opd?: string | null;
    opd_program_id?: number | null;
    program_kode?: string | null;
    program: string;
    indicator_count: number;
    avg_capaian?: number | null;
    avg_serapan?: number | null;
    total_anggaran: number;
    total_realisasi_anggaran: number;
    dominant_efficiency_status?: string | null;
    detail_url?: string | null;
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
type OpdPerformanceRank = Pick<
    ProgressOpd,
    'opd_id' | 'kode' | 'nama' | 'singkatan' | 'progress_percent' | 'capaian_persen' | 'nilai_evaluasi' | 'predikat' | 'rekomendasi_terbuka_count'
> & {
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
    module_label: string;
    status: string;
    status_label: string;
    submitted_by?: string | null;
    current_reviewer?: string | null;
};

const props = defineProps<{
    dashboard: {
        type: 'kabupaten' | 'opd' | 'pimpinan' | 'evaluasi';
        title: string;
        tahun: number;
        can_filter_opd: boolean;
    };
    filters: { tahun: number; opd_id?: number | null };
    opdOptions: Option[];
    periodeOptions: Option[];
    stats: {
        opd_count: number;
        rpjmd_count: number;
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
    sasaranDrilldown: SasaranDrilldown[];
    programDrilldown: ProgramDrilldown[];
    opdsWithoutRealization: OpdWithoutRealization[];
    quickLinks: Array<{ label: string; href: string }>;
}>();

const filterForm = reactive({
    tahun: String(props.filters.tahun ?? props.dashboard.tahun),
    opd_id: props.filters.opd_id ? String(props.filters.opd_id) : '',
});

const moduleLabels: Record<string, string> = {
    rpjmd: 'RPJMD',
    renstra: 'Renstra',
    pk: 'PK',
    rencana_aksi: 'Rencana Aksi',
    realisasi: 'Realisasi',
    lkjip: 'LKJIP',
    evaluasi: 'Evaluasi',
};

const selectedOpdLabel = computed(() => {
    if (!filterForm.opd_id) {
        return 'Semua OPD';
    }

    return props.opdOptions.find((option) => String(option.id) === filterForm.opd_id)?.label ?? 'OPD terpilih';
});

const priorityCards = computed(() => [
    {
        label: 'Pengajuan Menunggu',
        value: props.stats.workflow_pending_count,
        helper: 'Diajukan / revisi',
        icon: ClipboardCheck,
        tone: props.stats.workflow_pending_count > 0 ? 'blue' : 'green',
    },
    {
        label: 'Lewat Target TL',
        value: props.stats.rekomendasi_overdue_count,
        helper: 'Rekomendasi terlambat',
        icon: AlertTriangle,
        tone: props.stats.rekomendasi_overdue_count > 0 ? 'red' : 'green',
    },
    {
        label: 'Belum Realisasi',
        value: props.stats.opd_belum_realisasi_count,
        helper: 'OPD belum input',
        icon: Building2,
        tone: props.stats.opd_belum_realisasi_count > 0 ? 'amber' : 'green',
    },
    {
        label: 'Indikator Merah',
        value: props.stats.indikator_merah_count,
        helper: 'Capaian < 70%',
        icon: Gauge,
        tone: props.stats.indikator_merah_count > 0 ? 'red' : 'green',
    },
]);

const mainMetrics = computed(() => [
    {
        key: 'metric-avg-capaian',
        label: 'Rata-rata Capaian',
        target: Number(props.stats.avg_capaian ?? 0),
        format: 'percent',
        helper: `${props.stats.indikator_merah_count} merah, ${props.stats.indikator_kuning_count} kuning, ${props.stats.indikator_hijau_count} hijau`,
        icon: BarChart3,
        tone: 'blue',
    },
    {
        key: 'metric-avg-evaluasi',
        label: 'Nilai Evaluasi',
        target: Number(props.stats.avg_evaluasi ?? 0),
        format: 'score',
        helper: `${props.stats.evaluasi_opd_count} OPD dievaluasi`,
        icon: FileCheck2,
        tone: 'violet',
    },
    {
        key: 'metric-realisasi-opd',
        label: 'Realisasi OPD',
        target: Number(props.stats.realisasi_opd_count ?? 0),
        total: Number(props.stats.opd_count ?? 0),
        format: 'ratio',
        helper: 'OPD sudah input realisasi',
        icon: CheckCircle2,
        tone: 'green',
    },
    {
        key: 'metric-rekomendasi-terbuka',
        label: 'Rekomendasi Terbuka',
        target: Number(props.stats.rekomendasi_terbuka_count ?? 0),
        format: 'number',
        helper: 'Belum selesai',
        icon: ListChecks,
        tone: 'orange',
    },
]);

const planningSummary = computed(() => [
    { label: 'RPJMD', value: props.stats.rpjmd_count },
    { label: 'Renstra', value: props.stats.renstra_opd_count },
    { label: 'PK', value: props.stats.perjanjian_kinerja_opd_count },
    { label: 'Rencana Aksi', value: props.stats.rencana_aksi_opd_count },
    { label: 'Realisasi', value: props.stats.realisasi_opd_count },
    { label: 'LKJIP', value: props.stats.lkjip_opd_count },
]);

const averageCompletion = computed(() => {
    if (props.moduleCompletion.length === 0) {
        return 0;
    }

    return Math.round(props.moduleCompletion.reduce((sum, item) => sum + item.percent, 0) / props.moduleCompletion.length);
});

const totalPlanningDocuments = computed(() => planningSummary.value.reduce((sum, item) => sum + item.value, 0));

const achievementDistributionTotal = computed(() => props.achievementStatusDistribution.reduce((sum, row) => sum + row.count, 0));

const periodLabel = computed(
    () => props.periodeOptions.find((option) => String(option.tahun) === filterForm.tahun)?.label ?? `Tahun ${filterForm.tahun}`,
);

const monitoringSummary = computed(() => {
    if (props.stats.workflow_pending_count > 0) {
        return `${props.stats.workflow_pending_count} pengajuan menunggu pemeriksaan.`;
    }

    if (props.stats.indikator_merah_count > 0) {
        return `${props.stats.indikator_merah_count} indikator perlu perhatian.`;
    }

    if (props.stats.opd_belum_realisasi_count > 0) {
        return `${props.stats.opd_belum_realisasi_count} OPD belum mengisi realisasi.`;
    }

    return 'Tidak ada perhatian mendesak pada cakupan saat ini.';
});

const applyFilters = () => {
    router.get(route('dashboard'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const animatedValues = reactive<Record<string, number>>({});
const motionFrame = ref<number | null>(null);
const rankingPage = ref(1);
const rankingPageSize = ref(5);
const progressPage = ref(1);
const progressPageSize = ref(10);
const pageSizeOptions = [5, 10, 20, 0];

const motionTargets = computed<Record<string, number>>(() => {
    const targets: Record<string, number> = {
        averageCompletion: averageCompletion.value,
        totalPlanningDocuments: totalPlanningDocuments.value,
        opdCount: Number(props.stats.opd_count ?? 0),
    };

    mainMetrics.value.forEach((metric) => {
        targets[metric.key] = metric.target;
    });

    priorityCards.value.forEach((card) => {
        targets[`priority-${card.label}`] = Number(card.value ?? 0);
    });

    planningSummary.value.forEach((item) => {
        targets[`document-${item.label}`] = Number(item.value ?? 0);
    });

    props.moduleCompletion.forEach((item) => {
        targets[`module-${item.key}`] = Number(item.percent ?? 0);
    });

    props.quarterlyAchievement.forEach((item) => {
        targets[`quarter-${item.triwulan}`] = Number(item.rata_capaian ?? 0);
    });

    props.achievementByYear.forEach((item) => {
        targets[`year-${item.tahun}`] = Number(item.rata_capaian ?? 0);
    });

    props.progressOpd.forEach((item) => {
        targets[`progress-${item.opd_id}`] = Number(item.progress_percent ?? 0);
    });

    return targets;
});

const motionKey = computed(() => JSON.stringify(motionTargets.value));

const rankingPageCount = computed(() => pageCount(props.opdPerformanceRanking.length, rankingPageSize.value));
const progressPageCount = computed(() => pageCount(props.progressOpd.length, progressPageSize.value));

const paginatedRanking = computed(() => paginate(props.opdPerformanceRanking, rankingPage.value, rankingPageSize.value));
const paginatedProgressOpd = computed(() => paginate(props.progressOpd, progressPage.value, progressPageSize.value));

const rankingPageSummary = computed(() => pageSummary(props.opdPerformanceRanking.length, rankingPage.value, rankingPageSize.value));
const progressPageSummary = computed(() => pageSummary(props.progressOpd.length, progressPage.value, progressPageSize.value));

const resetFilters = () => {
    filterForm.tahun = String(props.dashboard.tahun);
    filterForm.opd_id = '';
    applyFiltersNow();
};

function formatPercent(value?: number | string | null) {
    const number = Number(value ?? 0);
    return `${number.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

function animatedValue(key: string, fallback = 0) {
    return animatedValues[key] ?? fallback;
}

function animatedInteger(key: string, fallback = 0) {
    return Math.round(animatedValue(key, fallback));
}

function animatedPercent(key: string, fallback = 0) {
    return formatPercent(animatedValue(key, fallback));
}

function animatedMetricValue(metric: (typeof mainMetrics.value)[number]) {
    const value = animatedValue(metric.key, metric.target);

    if (metric.format === 'percent') {
        return formatPercent(value);
    }

    if (metric.format === 'score') {
        return formatScore(value);
    }

    if (metric.format === 'ratio') {
        return `${Math.round(value)}/${metric.total ?? 0}`;
    }

    return Math.round(value).toLocaleString('id-ID');
}

function animatedBarWidth(key: string, fallback = 0) {
    return barWidth(animatedValue(key, fallback));
}

function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function runDashboardAnimation() {
    if (motionFrame.value !== null) {
        window.cancelAnimationFrame(motionFrame.value);
        motionFrame.value = null;
    }

    const targets = motionTargets.value;

    if (prefersReducedMotion()) {
        Object.entries(targets).forEach(([key, value]) => {
            animatedValues[key] = value;
        });
        return;
    }

    Object.keys(targets).forEach((key) => {
        animatedValues[key] = 0;
    });

    const startedAt = window.performance.now();
    const duration = 680;

    const update = (now: number) => {
        const progress = Math.min((now - startedAt) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 4);

        Object.entries(targets).forEach(([key, value]) => {
            animatedValues[key] = value * eased;
        });

        if (progress < 1) {
            motionFrame.value = window.requestAnimationFrame(update);
            return;
        }

        motionFrame.value = null;
    };

    motionFrame.value = window.requestAnimationFrame(update);
}

function pageCount(total: number, pageSize: number) {
    return pageSize === 0 ? 1 : Math.max(1, Math.ceil(total / pageSize));
}

function paginate<T>(items: T[], page: number, pageSize: number) {
    if (pageSize === 0) {
        return items;
    }

    const start = (page - 1) * pageSize;
    return items.slice(start, start + pageSize);
}

function pageSummary(total: number, page: number, pageSize: number) {
    if (total === 0) {
        return 'Tidak ada data';
    }

    if (pageSize === 0) {
        return `${total} data`;
    }

    const start = (page - 1) * pageSize + 1;
    const end = Math.min(page * pageSize, total);
    return `${start}-${end} dari ${total}`;
}

watch([rankingPageSize, () => props.opdPerformanceRanking.length], () => {
    rankingPage.value = 1;
});

watch([progressPageSize, () => props.progressOpd.length], () => {
    progressPage.value = 1;
});

watch(motionKey, () => {
    nextTick(runDashboardAnimation);
});

onMounted(() => {
    runDashboardAnimation();
});

onBeforeUnmount(() => {
    if (motionFrame.value !== null) {
        window.cancelAnimationFrame(motionFrame.value);
    }
});

function formatScore(value?: number | string | null) {
    const number = Number(value ?? 0);
    return number.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function formatCurrency(value?: number | string | null) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
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
            draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800/80 dark:text-slate-200',
            submitted: 'bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-200',
            revision: 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200',
            verified: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/70 dark:text-cyan-200',
            approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200',
            rejected: 'bg-red-100 text-red-800 dark:bg-red-950/70 dark:text-red-200',
            locked: 'bg-zinc-200 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
            belum: 'bg-slate-100 text-slate-700 dark:bg-slate-800/80 dark:text-slate-200',
            proses: 'bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-200',
            selesai: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200',
            ditolak: 'bg-red-100 text-red-800 dark:bg-red-950/70 dark:text-red-200',
            perlu_perbaikan: 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200',
            merah: 'bg-red-100 text-red-800 dark:bg-red-950/70 dark:text-red-200',
            kuning: 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200',
            hijau: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200',
            efisien: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200',
            cukup_efisien: 'bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-200',
            tidak_efisien: 'bg-red-100 text-red-800 dark:bg-red-950/70 dark:text-red-200',
            tinggi: 'bg-red-100 text-red-800 dark:bg-red-950/70 dark:text-red-200',
            sedang: 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200',
            rendah: 'bg-slate-100 text-slate-700 dark:bg-slate-800/80 dark:text-slate-200',
        }[status ?? ''] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800/80 dark:text-slate-200'
    );
}

function completionClass(value: number) {
    if (value >= 100) {
        return 'bg-emerald-700';
    }

    if (value >= 70) {
        return 'bg-blue-600';
    }

    if (value >= 40) {
        return 'bg-amber-500';
    }

    return 'bg-red-600';
}

function booleanClass(value: boolean) {
    return value
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/35 dark:text-emerald-200'
        : 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-400';
}
</script>

<template>
    <Head :title="dashboard.title" />
    <div class="dashboard-shell flex h-full flex-1 flex-col gap-5 p-4 lg:p-5">
        <section class="dashboard-page-head">
            <div class="dashboard-page-head__title">
                <div class="dashboard-page-head__eyebrow">Ringkasan kinerja</div>
                <div class="dashboard-page-head__row">
                    <h1>{{ dashboard.title }}</h1>
                    <span class="dashboard-live-status"><span aria-hidden="true" /> Data terkini</span>
                </div>
                <p>{{ selectedOpdLabel }} · {{ periodLabel }}</p>
            </div>

            <form class="dashboard-filterbar" @submit.prevent="applyFiltersNow">
                <label class="dashboard-filterbar__field">
                    <span>Tahun</span>
                    <select v-model="filterForm.tahun" aria-label="Filter tahun dashboard">
                        <option v-for="option in periodeOptions" :key="option.tahun" :value="option.tahun">{{ option.label }}</option>
                    </select>
                </label>
                <label v-if="dashboard.can_filter_opd" class="dashboard-filterbar__field dashboard-filterbar__field--opd">
                    <span>Perangkat daerah</span>
                    <select v-model="filterForm.opd_id" aria-label="Filter OPD dashboard">
                        <option value="">Semua OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>
                <button type="button" class="dashboard-filterbar__reset" @click="resetFilters">Reset</button>
            </form>
        </section>

        <section v-if="quickLinks.length" class="dashboard-quick-links" aria-label="Akses cepat">
            <Link
                v-for="link in quickLinks"
                :key="link.href"
                :href="link.href"
                class="dashboard-quick-link"
            >
                {{ link.label }}
                <ArrowRight class="size-4" />
            </Link>
        </section>

        <section class="dashboard-kpi-grid" aria-label="Indikator utama">
            <article
                v-for="metric in mainMetrics"
                :key="metric.label"
                class="dashboard-kpi-card"
                :class="`dashboard-kpi-card--${metric.tone}`"
            >
                <span class="dashboard-kpi-card__icon"><component :is="metric.icon" class="size-5" /></span>
                <div class="dashboard-kpi-card__body">
                    <p>{{ metric.label }}</p>
                    <strong>{{ animatedMetricValue(metric) }}</strong>
                    <span>{{ metric.helper }}</span>
                </div>
            </article>
        </section>

        <section class="dashboard-analytics-grid">
            <article class="dashboard-board-card dashboard-progress-card">
                <header class="dashboard-board-card__header">
                    <div>
                        <h2>Progress Dokumen</h2>
                        <p>Kelengkapan siklus SAKIP</p>
                    </div>
                    <strong>{{ animatedPercent('averageCompletion', averageCompletion) }}</strong>
                </header>
                <div class="dashboard-progress-card__content">
                    <div
                        class="dashboard-progress-ring"
                        :style="{ '--progress': `${animatedValue('averageCompletion', averageCompletion) * 3.6}deg` }"
                    >
                        <div>
                            <strong>{{ animatedPercent('averageCompletion', averageCompletion) }}</strong>
                            <span>rata-rata</span>
                        </div>
                    </div>
                    <div class="dashboard-module-list">
                        <div v-for="item in moduleCompletion.slice(0, 5)" :key="item.key" class="dashboard-module-row">
                            <div class="dashboard-module-row__label">
                                <span aria-hidden="true" />
                                <p>{{ item.label }}</p>
                            </div>
                            <div class="dashboard-module-row__track">
                                <span :style="{ width: animatedBarWidth(`module-${item.key}`, item.percent) }" />
                            </div>
                            <strong>{{ animatedPercent(`module-${item.key}`, item.percent) }}</strong>
                        </div>
                    </div>
                </div>
            </article>

            <article class="dashboard-board-card dashboard-document-card">
                <header class="dashboard-board-card__header">
                    <div>
                        <h2>Ringkasan Dokumen</h2>
                        <p>{{ periodLabel }}</p>
                    </div>
                    <span>{{ animatedInteger('totalPlanningDocuments', totalPlanningDocuments) }} data</span>
                </header>
                <div class="dashboard-document-grid">
                    <div v-for="(item, index) in planningSummary" :key="item.label" :class="`dashboard-document-tile--${(index % 4) + 1}`">
                        <span>{{ item.label }}</span>
                        <strong>{{ animatedInteger(`document-${item.label}`, item.value) }}</strong>
                    </div>
                </div>
                <div class="dashboard-document-card__footer">
                    <span>OPD aktif</span>
                    <strong>{{ animatedInteger('opdCount', stats.opd_count) }}</strong>
                </div>
            </article>

            <article class="dashboard-board-card dashboard-quarter-card">
                <header class="dashboard-board-card__header">
                    <div>
                        <h2>Capaian Triwulan</h2>
                        <p>Rata-rata realisasi kinerja</p>
                    </div>
                    <span>{{ filters.tahun }}</span>
                </header>
                <div v-if="quarterlyAchievement.length" class="dashboard-quarter-chart">
                    <div v-for="row in quarterlyAchievement" :key="row.triwulan" class="dashboard-quarter-chart__item">
                        <strong>{{ animatedPercent(`quarter-${row.triwulan}`, row.rata_capaian) }}</strong>
                        <div class="dashboard-quarter-chart__bar">
                            <span :style="{ height: animatedBarWidth(`quarter-${row.triwulan}`, row.rata_capaian) }" />
                        </div>
                        <span>{{ row.label.replace('Triwulan ', 'TW ') }}</span>
                    </div>
                </div>
                <div v-else class="dashboard-empty-chart">Belum ada data triwulan.</div>
                <div class="dashboard-quarter-card__legend"><span aria-hidden="true" /> Capaian kinerja</div>
            </article>
        </section>

        <section class="dashboard-lower-grid">
            <article class="dashboard-board-card dashboard-trend-card">
                <header class="dashboard-board-card__header">
                    <div>
                        <h2>Tren Capaian Kinerja</h2>
                        <p>Perbandingan capaian per tahun</p>
                    </div>
                    <span>{{ selectedOpdLabel }}</span>
                </header>
                <div v-if="achievementByYear.length" class="dashboard-trend-chart">
                    <div v-for="row in achievementByYear" :key="row.tahun" class="dashboard-trend-chart__row">
                        <span>{{ row.tahun }}</span>
                        <div>
                            <i
                                :class="{ 'is-current': row.selected }"
                                :style="{ width: animatedBarWidth(`year-${row.tahun}`, row.rata_capaian) }"
                            />
                        </div>
                        <strong>{{ animatedPercent(`year-${row.tahun}`, row.rata_capaian) }}</strong>
                    </div>
                </div>
                <div v-else class="dashboard-empty-chart">Belum ada data capaian tahunan.</div>
                <div class="dashboard-trend-card__status">
                    <span>{{ achievementDistributionTotal }} indikator terukur</span>
                    <div>
                        <span v-for="row in achievementStatusDistribution" :key="row.status" :class="`is-${row.status}`">
                            {{ row.count }} {{ row.label.toLowerCase() }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="dashboard-board-card dashboard-priority-list">
                <header class="dashboard-board-card__header">
                    <div>
                        <h2>Perlu Ditindaklanjuti</h2>
                        <p>{{ monitoringSummary }}</p>
                    </div>
                </header>
                <div class="dashboard-priority-list__items">
                    <div
                        v-for="card in priorityCards"
                        :key="card.label"
                        class="dashboard-priority-row"
                        :class="`dashboard-priority-row--${card.tone}`"
                    >
                        <span class="dashboard-priority-row__icon"><component :is="card.icon" class="size-4" /></span>
                        <div class="dashboard-priority-row__content">
                            <strong>{{ card.label }}</strong>
                            <span>{{ card.helper }}</span>
                        </div>
                        <b class="dashboard-priority-row__value">{{ animatedInteger(`priority-${card.label}`, card.value) }}</b>
                    </div>
                </div>
            </article>
        </section>

        <div class="dashboard-detail-label">
            <span>Detail monitoring</span>
            <i aria-hidden="true" />
        </div>

        <section class="dashboard-detail-grid">
            <div class="dashboard-panel dashboard-panel--orange">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><Gauge class="size-4" /></span>
                        <div>
                            <h2>Indikator Perlu Perhatian</h2>
                            <p>Capaian dan efisiensi yang perlu ditinjau</p>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="dashboard-data-table">
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
                                    <Link
                                        :href="row.detail_url || route('realisasi-kinerja.show', row.realisasi_kinerja_id)"
                                        class="dashboard-data-link"
                                    >
                                        {{ row.indikator }}
                                    </Link>
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <Link v-if="row.opd_detail_url" :href="row.opd_detail_url" class="dashboard-data-link dashboard-data-link--muted">{{
                                            row.opd || '-'
                                        }}</Link>
                                        <span v-else>{{ row.opd || '-' }}</span>
                                        <span>{{ row.triwulan_label || row.periode_realisasi || '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div>{{ formatMetricValue(row.target, row.target_text) }}</div>
                                    <div class="mt-1 text-muted-foreground">{{ formatMetricValue(row.realisasi, row.realisasi_text) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">
                                        {{
                                            row.capaian_persen === null || row.capaian_persen === undefined ? '-' : formatPercent(row.capaian_persen)
                                        }}
                                    </div>
                                    <span
                                        class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="statusClass(row.status_capaian)"
                                    >
                                        {{ statusLabel(row.status_capaian) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            row.serapan_anggaran_persen === null || row.serapan_anggaran_persen === undefined
                                                ? '-'
                                                : formatPercent(row.serapan_anggaran_persen)
                                        }}
                                    </div>
                                    <span
                                        class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="statusClass(row.status_efisiensi)"
                                    >
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

            <div class="dashboard-panel dashboard-panel--violet">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><Trophy class="size-4" /></span>
                        <div>
                            <h2>Ranking OPD</h2>
                            <p>Urutan skor monitoring kinerja</p>
                        </div>
                    </div>
                    <label class="dashboard-panel__page-size">
                        <span>Baris</span>
                        <select v-model.number="rankingPageSize" aria-label="Jumlah baris ranking OPD">
                            <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size === 0 ? 'Semua' : size }}</option>
                        </select>
                    </label>
                </div>
                <div class="dashboard-ranking-list">
                    <div
                        v-for="row in paginatedRanking"
                        :key="row.opd_id"
                        class="dashboard-ranking-row dashboard-list-enter"
                    >
                        <div class="dashboard-ranking-row__rank">
                            {{ row.rank }}
                        </div>
                        <div class="min-w-0">
                            <Link
                                :href="row.detail_url || route('dashboard', { tahun: filters.tahun, opd_id: row.opd_id })"
                                class="dashboard-data-link block truncate"
                            >
                                {{ row.singkatan || row.nama }}
                            </Link>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                <span>Progress {{ row.progress_percent }}%</span>
                                <span
                                    >Capaian
                                    {{
                                        row.capaian_persen === null || row.capaian_persen === undefined ? '-' : formatPercent(row.capaian_persen)
                                    }}</span
                                >
                                <span>Evaluasi {{ row.nilai_evaluasi ?? '-' }}</span>
                                <span>{{ row.rekomendasi_terbuka_count }} rekomendasi</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-base font-semibold">{{ formatScore(row.monitoring_score) }}</div>
                            <div class="text-xs text-muted-foreground">skor</div>
                        </div>
                    </div>
                    <div v-if="opdPerformanceRanking.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada data OPD untuk diranking.
                    </div>
                </div>
                <footer v-if="opdPerformanceRanking.length > 0" class="dashboard-pagination">
                    <span>{{ rankingPageSummary }}</span>
                    <div>
                        <button type="button" :disabled="rankingPage === 1" aria-label="Halaman ranking sebelumnya" @click="rankingPage -= 1">
                            <ChevronLeft class="size-4" />
                        </button>
                        <strong>{{ rankingPage }} / {{ rankingPageCount }}</strong>
                        <button
                            type="button"
                            :disabled="rankingPage === rankingPageCount"
                            aria-label="Halaman ranking berikutnya"
                            @click="rankingPage += 1"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </footer>
            </div>
        </section>

        <section class="dashboard-detail-grid">
            <div class="dashboard-panel dashboard-panel--blue">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><BarChart3 class="size-4" /></span>
                        <div>
                            <h2>Capaian per Sasaran</h2>
                            <p>Ringkasan indikator pada sasaran OPD</p>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="dashboard-data-table">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Sasaran</th>
                                <th class="px-4 py-3">Indikator</th>
                                <th class="px-4 py-3">Capaian</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in sasaranDrilldown"
                                :key="`${row.opd_id}-${row.sasaran_opd_id || row.sasaran}`"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="row.detail_url || route('dashboard', { tahun: filters.tahun, opd_id: row.opd_id })"
                                        class="dashboard-data-link"
                                    >
                                        {{ row.sasaran }}
                                    </Link>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ row.opd || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ row.indicator_count }}</td>
                                <td class="px-4 py-3 font-semibold">
                                    {{ row.avg_capaian === null || row.avg_capaian === undefined ? '-' : formatPercent(row.avg_capaian) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status_capaian)">
                                        {{ statusLabel(row.status_capaian) }}
                                    </span>
                                    <div class="mt-2 flex gap-1 text-[11px] text-muted-foreground">
                                        <span class="rounded bg-red-50 px-1.5 py-0.5 text-red-800">M {{ row.merah_count }}</span>
                                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-amber-800">K {{ row.kuning_count }}</span>
                                        <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-emerald-800">H {{ row.hijau_count }}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="sasaranDrilldown.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">Belum ada data capaian per sasaran.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-panel dashboard-panel--green">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><TrendingUp class="size-4" /></span>
                        <div>
                            <h2>Capaian per Program</h2>
                            <p>Kinerja, serapan anggaran, dan efisiensi</p>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="dashboard-data-table">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Program</th>
                                <th class="px-4 py-3">Capaian / Serapan</th>
                                <th class="px-4 py-3">Anggaran</th>
                                <th class="px-4 py-3">Efisiensi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in programDrilldown"
                                :key="`${row.opd_id}-${row.opd_program_id || row.program}`"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="row.detail_url || route('dashboard', { tahun: filters.tahun, opd_id: row.opd_id })"
                                        class="dashboard-data-link"
                                    >
                                        {{ row.program_kode ? `${row.program_kode} - ` : '' }}{{ row.program }}
                                    </Link>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ row.opd || '-' }} / {{ row.indicator_count }} indikator</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">
                                        {{ row.avg_capaian === null || row.avg_capaian === undefined ? '-' : formatPercent(row.avg_capaian) }}
                                    </div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        Serapan {{ row.avg_serapan === null || row.avg_serapan === undefined ? '-' : formatPercent(row.avg_serapan) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div>{{ formatCurrency(row.total_anggaran) }}</div>
                                    <div class="mt-1 text-muted-foreground">Realisasi {{ formatCurrency(row.total_realisasi_anggaran) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.dominant_efficiency_status)">
                                        {{ statusLabel(row.dominant_efficiency_status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="programDrilldown.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">Belum ada data capaian per program.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="dashboard-panel dashboard-panel--blue dashboard-panel--wide">
            <div class="dashboard-panel__header">
                <div class="dashboard-panel__heading">
                    <span class="dashboard-panel__icon"><Building2 class="size-4" /></span>
                    <div>
                        <h2>Progress per OPD</h2>
                        <p>Kelengkapan modul, capaian, evaluasi, dan rekomendasi</p>
                    </div>
                </div>
                <label class="dashboard-panel__page-size">
                    <span>Baris</span>
                    <select v-model.number="progressPageSize" aria-label="Jumlah baris progress OPD">
                        <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size === 0 ? 'Semua' : size }}</option>
                    </select>
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="dashboard-data-table dashboard-data-table--wide">
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
                        <tr v-for="row in paginatedProgressOpd" :key="row.opd_id" class="dashboard-list-enter border-b last:border-0">
                            <td class="px-4 py-3">
                                <Link
                                    :href="row.detail_url || route('dashboard', { tahun: filters.tahun, opd_id: row.opd_id })"
                                    class="dashboard-data-link"
                                >
                                    {{ row.singkatan || row.nama }}
                                </Link>
                                <div class="text-xs text-muted-foreground">{{ row.kode || row.nama }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="(done, key) in row.modules"
                                        :key="key"
                                        class="rounded-full border px-2 py-1 text-xs"
                                        :class="booleanClass(done)"
                                    >
                                        {{ moduleLabels[key] ?? key }}
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    <Link v-if="row.renstra_url" :href="row.renstra_url" class="dashboard-data-link dashboard-data-link--small">Renstra</Link>
                                    <Link v-if="row.pk_url" :href="row.pk_url" class="dashboard-data-link dashboard-data-link--small">PK</Link>
                                    <Link v-if="row.rencana_aksi_url" :href="row.rencana_aksi_url" class="dashboard-data-link dashboard-data-link--small"
                                        >Rencana Aksi</Link
                                    >
                                    <Link v-if="row.realisasi_url" :href="row.realisasi_url" class="dashboard-data-link dashboard-data-link--small">Realisasi</Link>
                                    <Link v-if="row.evaluasi_url" :href="row.evaluasi_url" class="dashboard-data-link dashboard-data-link--small">Evaluasi</Link>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex min-w-32 items-center gap-2">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="dashboard-progress-fill h-full rounded-full"
                                            :class="completionClass(row.progress_percent)"
                                            :style="{ width: animatedBarWidth(`progress-${row.opd_id}`, row.progress_percent) }"
                                        />
                                    </div>
                                    <span class="w-10 text-right text-xs font-medium">{{ animatedPercent(`progress-${row.opd_id}`, row.progress_percent) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ row.capaian_persen === null || row.capaian_persen === undefined ? '-' : formatPercent(row.capaian_persen) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.nilai_evaluasi ?? '-' }}</div>
                                <div class="text-xs text-muted-foreground">Predikat {{ row.predikat || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="row.rekomendasi_terbuka_count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                >
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
            <footer v-if="progressOpd.length > 0" class="dashboard-pagination">
                <span>{{ progressPageSummary }}</span>
                <div>
                    <button type="button" :disabled="progressPage === 1" aria-label="Halaman progress OPD sebelumnya" @click="progressPage -= 1">
                        <ChevronLeft class="size-4" />
                    </button>
                    <strong>{{ progressPage }} / {{ progressPageCount }}</strong>
                    <button
                        type="button"
                        :disabled="progressPage === progressPageCount"
                        aria-label="Halaman progress OPD berikutnya"
                        @click="progressPage += 1"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                </div>
            </footer>
        </section>

        <section class="dashboard-detail-grid">
            <div class="dashboard-panel dashboard-panel--violet">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><TrendingUp class="size-4" /></span>
                        <div>
                            <h2>Capaian per Tahun</h2>
                            <p>Perbandingan rata-rata capaian tahunan</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-year-list">
                    <div v-for="row in achievementByYear" :key="row.tahun" class="dashboard-year-row">
                        <span class="font-medium">{{ row.tahun }}</span>
                        <div class="dashboard-year-row__track">
                            <div
                                :class="row.selected ? 'is-current' : ''"
                                :style="{ width: barWidth(row.rata_capaian) }"
                            />
                        </div>
                        <span class="text-right text-xs text-muted-foreground">{{ formatPercent(row.rata_capaian) }}</span>
                    </div>
                    <div v-if="achievementByYear.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Belum ada data realisasi indikator.
                    </div>
                </div>
            </div>

            <div class="dashboard-panel dashboard-panel--orange">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><ClipboardCheck class="size-4" /></span>
                        <div>
                            <h2>Status Persetujuan</h2>
                            <p>Posisi dokumen dalam proses persetujuan</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-status-list">
                    <div v-for="row in workflowStatus" :key="row.status" class="dashboard-status-row">
                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.label }}</span>
                        <span class="font-semibold">{{ row.count }}</span>
                    </div>
                    <div v-if="workflowStatus.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada pengajuan pada tahun ini.
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-detail-grid">
            <div class="dashboard-panel dashboard-panel--blue">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><FileCheck2 class="size-4" /></span>
                        <div>
                            <h2>Evaluasi SAKIP</h2>
                            <p>Nilai dan predikat perangkat daerah</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-status-list">
                    <div v-for="row in evaluationRanking" :key="row.id" class="dashboard-evaluation-row">
                        <span class="font-medium">{{ row.opd || '-' }}</span>
                        <span class="text-right font-semibold">{{ row.nilai_akhir }}</span>
                        <span class="rounded-full px-2 py-1 text-center text-xs font-medium" :class="statusClass(row.status)">{{
                            row.predikat || '-'
                        }}</span>
                    </div>
                    <div v-if="evaluationRanking.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada nilai evaluasi SAKIP.
                    </div>
                </div>
            </div>

            <div class="dashboard-panel dashboard-panel--green">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><ListChecks class="size-4" /></span>
                        <div>
                            <h2>Status Tindak Lanjut</h2>
                            <p>Perkembangan penyelesaian rekomendasi</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-status-list">
                    <div v-for="row in recommendationStatus" :key="row.status" class="dashboard-status-row">
                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.label }}</span>
                        <span class="font-semibold">{{ row.count }}</span>
                    </div>
                    <div v-if="recommendationStatus.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada rekomendasi evaluasi.
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-detail-grid">
            <div class="dashboard-panel dashboard-panel--red">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><AlertTriangle class="size-4" /></span>
                        <div>
                            <h2>OPD Belum Realisasi</h2>
                            <p>Perangkat daerah yang belum mengisi realisasi</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-status-list">
                    <div v-for="row in opdsWithoutRealization" :key="row.id" class="dashboard-status-row">
                        <div>
                            <div class="font-medium">{{ row.singkatan || row.nama }}</div>
                            <div class="text-xs text-muted-foreground">{{ row.kode || '-' }}</div>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Belum input</span>
                    </div>
                    <div v-if="opdsWithoutRealization.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Semua OPD pada cakupan ini sudah memiliki realisasi.
                    </div>
                </div>
            </div>

            <div class="dashboard-panel dashboard-panel--orange">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><AlertTriangle class="size-4" /></span>
                        <div>
                            <h2>Rekomendasi Lewat Target</h2>
                            <p>Tindak lanjut yang melewati batas waktu</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-recommendation-list">
                    <div v-for="row in overdueRecommendations" :key="row.id" class="dashboard-recommendation-row">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ row.opd || '-' }}</div>
                                <p class="mt-1 text-muted-foreground">{{ row.rekomendasi }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.prioritas)">{{
                                row.prioritas
                            }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <span>{{ row.nomor || 'Tanpa nomor' }}</span>
                            <span>Target {{ row.target_tanggal || '-' }}</span>
                            <span class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800">{{ row.overdue_days }} hari lewat</span>
                        </div>
                    </div>
                    <div v-if="overdueRecommendations.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Tidak ada rekomendasi lewat target.
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-detail-grid">
            <div class="dashboard-panel dashboard-panel--red">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><ListChecks class="size-4" /></span>
                        <div>
                            <h2>Rekomendasi Belum Selesai</h2>
                            <p>Rekomendasi yang masih memerlukan tindak lanjut</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-recommendation-list">
                    <div v-for="row in openRecommendations" :key="row.id" class="dashboard-recommendation-row">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ row.opd || '-' }}</div>
                                <p class="mt-1 text-muted-foreground">{{ row.rekomendasi }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.prioritas)">{{
                                row.prioritas
                            }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <span>{{ row.nomor || 'Tanpa nomor' }}</span>
                            <span v-if="row.target_tanggal">Target {{ row.target_tanggal }}</span>
                            <span class="rounded-full px-2 py-0.5 font-medium" :class="statusClass(row.status_tindak_lanjut)">{{
                                row.status_tindak_lanjut
                            }}</span>
                        </div>
                    </div>
                    <div v-if="openRecommendations.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Tidak ada rekomendasi terbuka.
                    </div>
                </div>
            </div>

            <div class="dashboard-panel dashboard-panel--violet">
                <div class="dashboard-panel__header">
                    <div class="dashboard-panel__heading">
                        <span class="dashboard-panel__icon"><CheckCircle2 class="size-4" /></span>
                        <div>
                            <h2>Pengajuan Terbaru</h2>
                            <p>Aktivitas persetujuan dokumen terkini</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-status-list">
                    <div v-for="row in latestWorkflow" :key="row.id" class="dashboard-workflow-row">
                        <div>
                            <div class="font-medium">{{ row.module_label }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ row.submitted_by || 'Belum ada pengaju' }}
                                <span v-if="row.current_reviewer">/ Pemeriksa {{ row.current_reviewer }}</span>
                            </div>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{ row.status_label }}</span>
                    </div>
                    <div v-if="latestWorkflow.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        Belum ada riwayat persetujuan.
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.dashboard-shell {
    --dashboard-blue: #00336c;
    --dashboard-blue-deep: #002957;
    --dashboard-sky: #eaf4ff;
    --dashboard-line: rgb(191 219 254 / 0.82);
    --dashboard-shadow: 0 0.75rem 1.8rem rgb(15 23 42 / 0.055);
    background: #f7f9fc;
    font-variant-numeric: tabular-nums;
}

.dashboard-overview {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(20rem, 39rem);
    gap: 1.5rem;
    align-items: center;
    overflow: hidden;
    border: 1px solid var(--dashboard-line);
    border-radius: 0.5rem;
    padding: 1.35rem;
    background: rgb(255 255 255 / 0.96);
    box-shadow: var(--dashboard-shadow);
}

.dashboard-overview__copy {
    animation: dashboard-reveal 340ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.dashboard-eyebrow,
.dashboard-panel__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--dashboard-blue);
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.075em;
    text-transform: uppercase;
}

.dashboard-eyebrow__dot {
    width: 0.48rem;
    height: 0.48rem;
    border-radius: 999px;
    background: #0ea5e9;
    box-shadow: 0 0 0 0.22rem rgb(14 165 233 / 0.14);
}

.dashboard-overview h1 {
    margin-top: 0.42rem;
    color: #0f2f58;
    font-size: clamp(1.5rem, 2vw, 2rem);
    font-weight: 740;
    line-height: 1.16;
    letter-spacing: 0;
}

.dashboard-overview__description {
    max-width: 42rem;
    margin-top: 0.55rem;
    color: rgb(71 85 105);
    font-size: 0.9rem;
    line-height: 1.6;
}

.dashboard-context {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.85rem;
}

.dashboard-context span {
    border: 1px solid rgb(191 219 254 / 0.9);
    border-radius: 999px;
    padding: 0.32rem 0.62rem;
    background: rgb(255 255 255 / 0.82);
    color: rgb(30 64 175);
    font-size: 0.72rem;
    font-weight: 680;
}

.dashboard-filters {
    border: 1px solid rgb(203 213 225 / 0.86);
    border-radius: 0.5rem;
    padding: 0.9rem;
    background: #f8fbff;
    box-shadow: none;
    animation: dashboard-reveal 380ms 60ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.dashboard-filters__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.72rem;
    color: rgb(30 41 59);
    font-size: 0.78rem;
    font-weight: 700;
}

.dashboard-filters__note {
    color: rgb(100 116 139);
    font-size: 0.7rem;
    font-weight: 560;
}

.dashboard-filters__controls {
    display: grid;
    grid-template-columns: minmax(8.7rem, 0.7fr) minmax(12rem, 1.3fr) auto;
    gap: 0.6rem;
    align-items: end;
}

.dashboard-control {
    display: grid;
    gap: 0.35rem;
    min-width: 0;
}

.dashboard-control > span {
    color: rgb(100 116 139);
    font-size: 0.66rem;
    font-weight: 720;
    letter-spacing: 0.055em;
    text-transform: uppercase;
}

.dashboard-control select,
.dashboard-reset {
    height: 2.5rem;
    min-width: 0;
    border: 1px solid rgb(203 213 225);
    border-radius: 0.4rem;
    background: rgb(255 255 255 / 0.96);
    color: rgb(30 41 59);
    font-size: 0.84rem;
    font-weight: 560;
    outline: none;
    transition:
        border-color 160ms ease,
        box-shadow 160ms ease,
        background-color 160ms ease,
        transform 160ms ease;
}

.dashboard-control select {
    width: 100%;
    padding: 0 0.75rem;
}

.dashboard-control select:hover,
.dashboard-reset:hover {
    border-color: rgb(96 165 250 / 0.95);
    background: #f8fbff;
}

.dashboard-control select:focus,
.dashboard-reset:focus-visible {
    border-color: rgb(0 51 108 / 0.78);
    box-shadow: 0 0 0 3px rgb(0 51 108 / 0.12);
}

.dashboard-reset {
    padding: 0 0.75rem;
    color: var(--dashboard-blue);
}

.dashboard-reset:hover {
    transform: translateY(-1px);
}

.dashboard-quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}

.dashboard-quick-link {
    display: inline-flex;
    height: 2.25rem;
    align-items: center;
    gap: 0.42rem;
    border: 1px solid rgb(203 213 225 / 0.9);
    border-radius: 0.4rem;
    padding: 0 0.75rem;
    background: rgb(255 255 255 / 0.92);
    color: rgb(51 65 85);
    font-size: 0.8rem;
    font-weight: 650;
    box-shadow: 0 0.3rem 0.8rem rgb(15 23 42 / 0.035);
    transition:
        border-color 160ms ease,
        background-color 160ms ease,
        color 160ms ease,
        transform 160ms ease;
}

.dashboard-quick-link:hover {
    border-color: rgb(147 197 253);
    background: #eef6ff;
    color: var(--dashboard-blue);
    transform: translateY(-1px);
}

.dashboard-section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.dashboard-section-heading p {
    color: rgb(100 116 139);
    font-size: 0.68rem;
    font-weight: 720;
    letter-spacing: 0.075em;
    text-transform: uppercase;
}

.dashboard-section-heading h2 {
    margin-top: 0.15rem;
    color: rgb(30 41 59);
    font-size: 1rem;
    font-weight: 720;
    letter-spacing: 0;
}

.dashboard-section-heading__status {
    max-width: 24rem;
    color: rgb(100 116 139);
    font-size: 0.75rem;
    line-height: 1.45;
    text-align: right;
}

.dashboard-priority-card,
.dashboard-metric-card,
.dashboard-panel {
    box-shadow: var(--dashboard-shadow);
}

.dashboard-priority-card {
    position: relative;
    overflow: hidden;
    min-height: 8.1rem;
    border-color: rgb(226 232 240);
    background: #fff;
    transition:
        transform 170ms ease,
        box-shadow 170ms ease;
}

.dashboard-priority-card::after {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    width: 0.23rem;
    height: auto;
    content: '';
    background: var(--priority-accent);
    opacity: 1;
}

.dashboard-priority-card:hover,
.dashboard-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.9rem 1.8rem rgb(15 23 42 / 0.09);
}

.dashboard-priority-card__icon,
.dashboard-metric-card__icon {
    display: flex;
    width: 2.2rem;
    height: 2.2rem;
    flex: none;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.65);
}

.dashboard-priority-card__value {
    margin-top: 0.55rem;
    color: inherit;
    font-size: 1.82rem;
    font-weight: 760;
    line-height: 1;
}

.dashboard-priority-card--blue {
    --priority-accent: #2563eb;
    border-color: #cfe0ff;
    background: #f4f8ff;
    color: #173f75;
}

.dashboard-priority-card--blue .dashboard-priority-card__icon {
    background: #dceaff;
    color: #2563eb;
}

.dashboard-priority-card--green {
    --priority-accent: #16a36a;
    border-color: #c9eee0;
    background: #f1fbf6;
    color: #14533b;
}

.dashboard-priority-card--green .dashboard-priority-card__icon {
    background: #d6f6e5;
    color: #078457;
}

.dashboard-priority-card--amber {
    --priority-accent: #f59e0b;
    border-color: #f9dfad;
    background: #fff9ed;
    color: #7c4600;
}

.dashboard-priority-card--amber .dashboard-priority-card__icon {
    background: #ffedc6;
    color: #c76b00;
}

.dashboard-priority-card--red {
    --priority-accent: #e5484d;
    border-color: #f5ced0;
    background: #fff6f6;
    color: #85212b;
}

.dashboard-priority-card--red .dashboard-priority-card__icon {
    background: #ffe0e1;
    color: #cf2d3b;
}

.dashboard-metric-card {
    position: relative;
    overflow: hidden;
    min-height: 7.8rem;
    border-color: transparent;
    background: rgb(255 255 255 / 0.92);
    transition:
        transform 170ms ease,
        box-shadow 170ms ease,
        border-color 170ms ease;
}

.dashboard-metric-card:hover {
    border-color: color-mix(in srgb, var(--metric-accent) 34%, white);
}

.dashboard-metric-card__icon {
    color: var(--metric-accent);
    background: rgb(255 255 255 / 0.74);
}

.dashboard-metric-card__label {
    color: var(--metric-text);
    font-size: 0.72rem;
    font-weight: 720;
    letter-spacing: 0.035em;
    text-transform: uppercase;
}

.dashboard-metric-card__value {
    margin-top: 0.58rem;
    color: var(--metric-text);
    font-size: 1.72rem;
    font-weight: 760;
    line-height: 1;
}

.dashboard-metric-card__footer {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.78rem;
    color: var(--metric-muted);
    font-size: 0.72rem;
    line-height: 1.25;
}

.dashboard-metric-card__line {
    display: block;
    width: 2.2rem;
    height: 0.23rem;
    flex: none;
    border-radius: 999px;
    background: var(--metric-accent);
}

.dashboard-metric-card--blue {
    --metric-accent: #2563eb;
    --metric-text: #173f75;
    --metric-muted: #5474a4;
    background: #edf5ff;
}

.dashboard-metric-card--violet {
    --metric-accent: #7c5ce0;
    --metric-text: #4c328e;
    --metric-muted: #7867a8;
    background: #f4f0ff;
}

.dashboard-metric-card--green {
    --metric-accent: #0f9f6e;
    --metric-text: #136447;
    --metric-muted: #51836f;
    background: #ecfaf3;
}

.dashboard-metric-card--orange {
    --metric-accent: #e98819;
    --metric-text: #864908;
    --metric-muted: #9b7549;
    background: #fff6e8;
}

.dashboard-panel {
    overflow: hidden;
    border-color: rgb(203 213 225 / 0.9);
    background: rgb(255 255 255 / 0.96);
}

.dashboard-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 4rem;
    border-color: rgb(226 232 240 / 0.9);
    background: #fbfdff;
}

.dashboard-panel__header h2 {
    margin-top: 0.1rem;
    color: rgb(30 41 59);
    font-size: 0.92rem;
    font-weight: 720;
}

.dashboard-panel__period {
    border: 1px solid rgb(191 219 254);
    border-radius: 999px;
    padding: 0.25rem 0.52rem;
    background: rgb(239 246 255 / 0.82);
    color: rgb(30 64 175);
    font-size: 0.7rem;
    font-weight: 700;
}

.dashboard-completion-item,
.dashboard-document-item {
    border-color: rgb(226 232 240);
    background: #fbfcfe;
}

.dashboard-completion-item:hover,
.dashboard-document-item:hover {
    border-color: rgb(191 219 254);
    background: rgb(248 252 255 / 0.9);
}

.dashboard-completion-item:nth-child(3n + 1) {
    border-top: 2px solid #70a8ff;
}

.dashboard-completion-item:nth-child(3n + 2) {
    border-top: 2px solid #4cc38d;
}

.dashboard-completion-item:nth-child(3n) {
    border-top: 2px solid #a68bea;
}

.dashboard-document-item:nth-child(4n + 1) {
    background: #f1f7ff;
}

.dashboard-document-item:nth-child(4n + 2) {
    background: #effbf5;
}

.dashboard-document-item:nth-child(4n + 3) {
    background: #f7f2ff;
}

.dashboard-document-item:nth-child(4n) {
    background: #fff8ed;
}

.dashboard-shell :deep(.dashboard-panel > .border-b) {
    border-color: rgb(226 232 240 / 0.92);
    background: rgb(248 250 252 / 0.7);
}

.dashboard-shell :deep(.dashboard-panel > .border-b h2) {
    color: rgb(30 41 59);
    font-size: 0.92rem;
    font-weight: 720;
}

.dashboard-shell :deep(.dashboard-panel table thead) {
    background: rgb(248 250 252 / 0.88);
}

.dashboard-shell :deep(.dashboard-panel table tbody tr) {
    transition: background-color 140ms ease;
}

.dashboard-shell :deep(.dashboard-panel table tbody tr:hover) {
    background: rgb(239 246 255 / 0.52);
}

.dashboard-shell :deep(.text-emerald-800) {
    color: var(--dashboard-blue) !important;
}

@keyframes dashboard-reveal {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

:global(.dark) .dashboard-shell {
    --dashboard-line: rgb(51 65 85 / 0.9);
    --dashboard-shadow: 0 0.75rem 1.7rem rgb(0 0 0 / 0.2);
    background: #0b1220;
}

:global(.dark) .dashboard-overview {
    border-color: rgb(51 65 85 / 0.94);
    background: linear-gradient(112deg, rgb(15 23 42 / 0.96), rgb(15 23 42 / 0.92) 58%, rgb(12 42 71 / 0.92));
}

:global(.dark) .dashboard-overview h1,
:global(.dark) .dashboard-filters__header,
:global(.dark) .dashboard-section-heading h2,
:global(.dark) .dashboard-panel__header h2,
:global(.dark) .dashboard-shell :deep(.dashboard-panel > .border-b h2) {
    color: rgb(226 232 240);
}

:global(.dark) .dashboard-overview__description,
:global(.dark) .dashboard-section-heading__status,
:global(.dark) .dashboard-filters__note {
    color: rgb(148 163 184);
}

:global(.dark) .dashboard-context span,
:global(.dark) .dashboard-panel__period {
    border-color: rgb(59 130 246 / 0.35);
    background: rgb(30 64 175 / 0.2);
    color: rgb(191 219 254);
}

:global(.dark) .dashboard-filters,
:global(.dark) .dashboard-metric-card,
:global(.dark) .dashboard-panel {
    border-color: rgb(51 65 85 / 0.94);
    background: rgb(15 23 42 / 0.88);
}

:global(.dark) .dashboard-priority-card--blue {
    border-color: rgb(59 130 246 / 0.42);
    background: rgb(30 58 138 / 0.24);
    color: rgb(219 234 254);
}

:global(.dark) .dashboard-priority-card--green {
    border-color: rgb(16 185 129 / 0.36);
    background: rgb(6 78 59 / 0.3);
    color: rgb(209 250 229);
}

:global(.dark) .dashboard-priority-card--amber {
    border-color: rgb(245 158 11 / 0.38);
    background: rgb(120 53 15 / 0.22);
    color: rgb(254 243 199);
}

:global(.dark) .dashboard-priority-card--red {
    border-color: rgb(239 68 68 / 0.4);
    background: rgb(127 29 29 / 0.22);
    color: rgb(254 226 226);
}

:global(.dark) .dashboard-priority-card--blue .dashboard-priority-card__icon,
:global(.dark) .dashboard-priority-card--green .dashboard-priority-card__icon,
:global(.dark) .dashboard-priority-card--amber .dashboard-priority-card__icon,
:global(.dark) .dashboard-priority-card--red .dashboard-priority-card__icon {
    background: rgb(15 23 42 / 0.72);
}

:global(.dark) .dashboard-metric-card--blue {
    --metric-accent: #7cb4ff;
    --metric-text: #dbeafe;
    --metric-muted: #a9c8f5;
    border-color: rgb(59 130 246 / 0.36);
    background: rgb(30 58 138 / 0.22);
}

:global(.dark) .dashboard-metric-card--violet {
    --metric-accent: #b3a0ff;
    --metric-text: #eeeaff;
    --metric-muted: #c8c1ea;
    border-color: rgb(139 92 246 / 0.34);
    background: rgb(76 29 149 / 0.2);
}

:global(.dark) .dashboard-metric-card--green {
    --metric-accent: #5ee6af;
    --metric-text: #d1fae5;
    --metric-muted: #a6d9c1;
    border-color: rgb(16 185 129 / 0.34);
    background: rgb(6 78 59 / 0.24);
}

:global(.dark) .dashboard-metric-card--orange {
    --metric-accent: #f7ba67;
    --metric-text: #ffedd5;
    --metric-muted: #efcfab;
    border-color: rgb(249 115 22 / 0.34);
    background: rgb(124 45 18 / 0.2);
}

:global(.dark) .dashboard-control select,
:global(.dark) .dashboard-reset {
    border-color: rgb(71 85 105 / 0.9);
    background: rgb(15 23 42 / 0.92);
    color: rgb(226 232 240);
}

:global(.dark) .dashboard-control select:hover,
:global(.dark) .dashboard-reset:hover,
:global(.dark) .dashboard-quick-link:hover {
    border-color: rgb(96 165 250 / 0.65);
    background: rgb(30 41 59 / 0.92);
}

:global(.dark) .dashboard-quick-link {
    border-color: rgb(51 65 85 / 0.9);
    background: rgb(15 23 42 / 0.78);
    color: rgb(203 213 225);
}

:global(.dark) .dashboard-metric-card__icon,
:global(.dark) .dashboard-priority-card__icon {
    background: rgb(30 41 59 / 0.78);
}

:global(.dark) .dashboard-completion-item,
:global(.dark) .dashboard-document-item,
:global(.dark) .dashboard-shell :deep(.dashboard-panel > .border-b),
:global(.dark) .dashboard-shell :deep(.dashboard-panel table thead) {
    border-color: rgb(51 65 85 / 0.8);
    background: rgb(15 23 42 / 0.62);
}

:global(.dark) .dashboard-shell :deep(.dashboard-panel table tbody tr:hover) {
    background: rgb(30 41 59 / 0.72);
}

:global(.dark) .dashboard-shell :deep(.text-emerald-800) {
    color: rgb(125 211 252) !important;
}

@media (max-width: 1100px) {
    .dashboard-overview {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .dashboard-overview {
        gap: 1rem;
        padding: 1rem;
    }

    .dashboard-filters__controls {
        grid-template-columns: 1fr;
    }

    .dashboard-reset {
        width: 100%;
    }

    .dashboard-section-heading {
        align-items: start;
        flex-direction: column;
    }

    .dashboard-section-heading__status {
        max-width: none;
        text-align: left;
    }
}

/* Compact project-dashboard composition */
.dashboard-shell {
    gap: 1.1rem;
    padding: 1.25rem;
    color: #172033;
    background: #f5f7fb;
}

.dashboard-page-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0.15rem 0.1rem 0.1rem;
}

.dashboard-page-head__eyebrow {
    color: #64748b;
    font-size: 0.73rem;
    font-weight: 700;
    text-transform: uppercase;
}

.dashboard-page-head__row {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-top: 0.25rem;
}

.dashboard-page-head h1 {
    color: #111827;
    font-size: 1.62rem;
    font-weight: 750;
    line-height: 1.2;
}

.dashboard-page-head__title > p {
    margin-top: 0.4rem;
    color: #64748b;
    font-size: 0.85rem;
}

.dashboard-live-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid #d9f2e4;
    border-radius: 999px;
    padding: 0.28rem 0.58rem;
    color: #198754;
    background: #f0fbf5;
    font-size: 0.69rem;
    font-weight: 700;
}

.dashboard-live-status > span {
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 50%;
    background: #21b66f;
}

.dashboard-filterbar {
    display: flex;
    align-items: end;
    gap: 0.65rem;
}

.dashboard-filterbar__field {
    display: grid;
    gap: 0.32rem;
    width: 10.75rem;
}

.dashboard-filterbar__field--opd {
    width: min(18rem, 28vw);
}

.dashboard-filterbar__field > span {
    color: #64748b;
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
}

.dashboard-filterbar select,
.dashboard-filterbar__reset {
    height: 2.45rem;
    border: 1px solid #dfe5ee;
    border-radius: 0.45rem;
    outline: none;
    background: #fff;
    color: #334155;
    font-size: 0.82rem;
    transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
}

.dashboard-filterbar select {
    width: 100%;
    padding: 0 0.75rem;
}

.dashboard-filterbar__reset {
    padding: 0 0.85rem;
    color: #526078;
}

.dashboard-filterbar select:hover,
.dashboard-filterbar__reset:hover {
    border-color: #b9c9df;
    background: #fbfdff;
}

.dashboard-filterbar select:focus,
.dashboard-filterbar__reset:focus-visible {
    border-color: #6aa4ef;
    box-shadow: 0 0 0 3px rgb(59 130 246 / 0.11);
}

.dashboard-quick-links {
    gap: 0.42rem;
    margin-top: -0.2rem;
}

.dashboard-quick-link {
    height: 2.05rem;
    border: 0;
    border-radius: 0.38rem;
    padding: 0 0.65rem;
    color: #526078;
    background: transparent;
    box-shadow: none;
    font-size: 0.76rem;
}

.dashboard-quick-link:hover {
    color: #2563eb;
    background: #eaf2ff;
    transform: none;
}

.dashboard-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
}

.dashboard-kpi-card,
.dashboard-board-card,
.dashboard-panel {
    border: 1px solid #e4e9f1;
    border-radius: 0.5rem;
    background: #fff;
    box-shadow: 0 0.2rem 0.75rem rgb(15 23 42 / 0.045);
}

.dashboard-kpi-card {
    display: flex;
    min-width: 0;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1rem;
    transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
}

.dashboard-kpi-card:hover {
    border-color: #cfdaea;
    box-shadow: 0 0.4rem 1rem rgb(15 23 42 / 0.07);
    transform: translateY(-1px);
}

.dashboard-kpi-card__icon {
    display: grid;
    width: 2.55rem;
    height: 2.55rem;
    flex: none;
    place-items: center;
    border-radius: 0.48rem;
    color: var(--kpi-color);
    background: var(--kpi-soft);
}

.dashboard-kpi-card__icon :deep(svg) {
    width: 1.35rem;
    height: 1.35rem;
}

.dashboard-kpi-card__body {
    min-width: 0;
}

.dashboard-kpi-card__body p {
    overflow: hidden;
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-kpi-card__body strong {
    display: block;
    margin-top: 0.25rem;
    color: #182235;
    font-size: 1.4rem;
    font-weight: 760;
    line-height: 1.1;
}

.dashboard-kpi-card__body > span {
    display: block;
    overflow: hidden;
    margin-top: 0.38rem;
    color: #7b8799;
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-kpi-card--blue {
    --kpi-color: #3979db;
    --kpi-soft: #eaf2ff;
}

.dashboard-kpi-card--green {
    --kpi-color: #159b63;
    --kpi-soft: #e8f8f0;
}

.dashboard-kpi-card--orange {
    --kpi-color: #e68a22;
    --kpi-soft: #fff2e2;
}

.dashboard-kpi-card--violet {
    --kpi-color: #7b5bd6;
    --kpi-soft: #f0ebff;
}

.dashboard-analytics-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.03fr) minmax(15rem, 0.82fr);
    gap: 0.85rem;
}

.dashboard-board-card {
    min-width: 0;
    overflow: hidden;
}

.dashboard-board-card__header {
    display: flex;
    min-height: 3.65rem;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
    border-bottom: 1px solid #edf0f5;
    padding: 0.8rem 1rem;
}

.dashboard-board-card__header h2 {
    color: #1f2937;
    font-size: 0.87rem;
    font-weight: 750;
}

.dashboard-board-card__header p {
    overflow: hidden;
    max-width: 19rem;
    margin-top: 0.18rem;
    color: #8590a2;
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-board-card__header > strong,
.dashboard-board-card__header > span {
    flex: none;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 750;
}

.dashboard-progress-card__content {
    display: grid;
    grid-template-columns: 7.45rem minmax(0, 1fr);
    gap: 1.1rem;
    align-items: center;
    min-height: 13.25rem;
    padding: 1rem;
}

.dashboard-progress-ring {
    display: grid;
    width: 6.45rem;
    height: 6.45rem;
    place-items: center;
    border-radius: 50%;
    background: conic-gradient(#4c82e8 var(--progress), #e9eef7 0);
}

.dashboard-progress-ring::before {
    width: 4.85rem;
    height: 4.85rem;
    border-radius: 50%;
    content: '';
    background: #fff;
}

.dashboard-progress-ring > div {
    position: absolute;
    display: grid;
    text-align: center;
}

.dashboard-progress-ring strong {
    color: #1f2937;
    font-size: 1.25rem;
}

.dashboard-progress-ring span {
    color: #8a95a6;
    font-size: 0.63rem;
}

.dashboard-module-list {
    display: grid;
    gap: 0.7rem;
}

.dashboard-module-row {
    display: grid;
    grid-template-columns: minmax(5.6rem, 0.85fr) minmax(4rem, 1fr) 2.1rem;
    align-items: center;
    gap: 0.45rem;
}

.dashboard-module-row__label {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.38rem;
}

.dashboard-module-row__label span {
    width: 0.42rem;
    height: 0.42rem;
    flex: none;
    border-radius: 50%;
    background: #4c82e8;
}

.dashboard-module-row:nth-child(2) .dashboard-module-row__label span,
.dashboard-module-row:nth-child(2) .dashboard-module-row__track span {
    background: #28ad72;
}

.dashboard-module-row:nth-child(3) .dashboard-module-row__label span,
.dashboard-module-row:nth-child(3) .dashboard-module-row__track span {
    background: #f0a044;
}

.dashboard-module-row:nth-child(4) .dashboard-module-row__label span,
.dashboard-module-row:nth-child(4) .dashboard-module-row__track span {
    background: #8a6bdd;
}

.dashboard-module-row:nth-child(5) .dashboard-module-row__label span,
.dashboard-module-row:nth-child(5) .dashboard-module-row__track span {
    background: #e46a76;
}

.dashboard-module-row__label p {
    overflow: hidden;
    color: #475569;
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-module-row__track {
    height: 0.38rem;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1f6;
}

.dashboard-module-row__track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #4c82e8;
}

.dashboard-module-row > strong {
    color: #334155;
    font-size: 0.68rem;
    text-align: right;
}

.dashboard-document-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
    padding: 1rem;
}

.dashboard-document-grid > div {
    min-width: 0;
    border-radius: 0.42rem;
    padding: 0.72rem;
    background: var(--tile-soft);
}

.dashboard-document-grid span {
    display: block;
    overflow: hidden;
    color: var(--tile-color);
    font-size: 0.64rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-document-grid strong {
    display: block;
    margin-top: 0.34rem;
    color: #243147;
    font-size: 1.15rem;
}

.dashboard-document-tile--1 {
    --tile-color: #3269c4;
    --tile-soft: #edf4ff;
}

.dashboard-document-tile--2 {
    --tile-color: #188455;
    --tile-soft: #ecf9f2;
}

.dashboard-document-tile--3 {
    --tile-color: #ce781b;
    --tile-soft: #fff5e9;
}

.dashboard-document-tile--4 {
    --tile-color: #7251c7;
    --tile-soft: #f3efff;
}

.dashboard-document-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #edf0f5;
    padding: 0.65rem 1rem;
    color: #7c8798;
    font-size: 0.69rem;
}

.dashboard-document-card__footer strong {
    color: #344054;
}

.dashboard-quarter-card {
    display: flex;
    flex-direction: column;
}

.dashboard-quarter-chart {
    display: flex;
    height: 11.4rem;
    align-items: end;
    justify-content: space-around;
    gap: 0.5rem;
    padding: 1.15rem 1rem 0.75rem;
}

.dashboard-quarter-chart__item {
    display: grid;
    height: 100%;
    flex: 1;
    grid-template-rows: auto 1fr auto;
    gap: 0.4rem;
    justify-items: center;
}

.dashboard-quarter-chart__item strong,
.dashboard-quarter-chart__item > span {
    color: #7b8799;
    font-size: 0.62rem;
    font-weight: 650;
}

.dashboard-quarter-chart__bar {
    display: flex;
    width: 1.8rem;
    height: 100%;
    align-items: end;
    overflow: hidden;
    border-radius: 0.28rem 0.28rem 0 0;
    background: #edf1f7;
}

.dashboard-quarter-chart__bar span {
    width: 100%;
    min-height: 0.15rem;
    border-radius: inherit;
    background: #75a4f0;
    transition: height 420ms cubic-bezier(0.16, 1, 0.3, 1);
}

.dashboard-quarter-chart__item:nth-child(2) .dashboard-quarter-chart__bar span {
    background: #4f87e8;
}

.dashboard-quarter-chart__item:nth-child(3) .dashboard-quarter-chart__bar span {
    background: #2ea971;
}

.dashboard-quarter-chart__item:nth-child(4) .dashboard-quarter-chart__bar span {
    background: #8a6bdd;
}

.dashboard-quarter-card__legend {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    border-top: 1px solid #edf0f5;
    padding: 0.62rem 1rem;
    color: #7c8798;
    font-size: 0.66rem;
}

.dashboard-quarter-card__legend > span {
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 50%;
    background: #4f87e8;
}

.dashboard-empty-chart {
    display: grid;
    min-height: 10.5rem;
    place-items: center;
    color: #94a3b8;
    font-size: 0.77rem;
}

.dashboard-lower-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.9fr) minmax(17rem, 0.85fr);
    gap: 0.85rem;
}

.dashboard-trend-chart {
    display: grid;
    gap: 0.7rem;
    padding: 1rem;
}

.dashboard-trend-chart__row {
    display: grid;
    grid-template-columns: 2.8rem minmax(0, 1fr) 3.25rem;
    align-items: center;
    gap: 0.72rem;
}

.dashboard-trend-chart__row > span,
.dashboard-trend-chart__row > strong {
    color: #667085;
    font-size: 0.68rem;
}

.dashboard-trend-chart__row > strong {
    text-align: right;
}

.dashboard-trend-chart__row > div {
    height: 0.54rem;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1f6;
}

.dashboard-trend-chart__row i {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #b6c6e4;
    will-change: width;
}

.dashboard-trend-chart__row i.is-current {
    background: #4f7fe4;
}

.dashboard-trend-card__status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    border-top: 1px solid #edf0f5;
    padding: 0.72rem 1rem;
    color: #7c8798;
    font-size: 0.68rem;
}

.dashboard-trend-card__status > div {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.dashboard-trend-card__status > div span {
    border-radius: 999px;
    padding: 0.2rem 0.45rem;
    background: #f1f5f9;
}

.dashboard-trend-card__status .is-merah {
    color: #c1434c;
    background: #fff0f1;
}

.dashboard-trend-card__status .is-kuning {
    color: #b86c13;
    background: #fff6e7;
}

.dashboard-trend-card__status .is-hijau {
    color: #138553;
    background: #eaf8f1;
}

.dashboard-priority-list__items {
    display: grid;
    padding: 0.25rem 0.75rem 0.55rem;
}

.dashboard-priority-row {
    display: grid;
    grid-template-columns: 2rem minmax(0, 1fr) minmax(1.8rem, auto);
    align-items: center;
    column-gap: 0.72rem;
    border-bottom: 1px solid #f0f2f6;
    min-height: 3.95rem;
    padding: 0.52rem 0.15rem;
}

.dashboard-priority-row:last-child {
    border-bottom: 0;
}

.dashboard-priority-row__icon {
    display: grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    align-self: center;
    border: 1px solid color-mix(in srgb, var(--priority-color) 16%, transparent);
    border-radius: 0.5rem;
    color: var(--priority-color);
    background: var(--priority-soft);
}

.dashboard-priority-list__items strong,
.dashboard-priority-list__items span {
    display: block;
}

.dashboard-priority-list__items strong {
    color: #344054;
    font-size: 0.76rem;
    font-weight: 700;
    line-height: 1.2;
}

.dashboard-priority-row__content {
    min-width: 0;
}

.dashboard-priority-row__content span {
    margin-top: 0.17rem;
    color: #8a95a6;
    font-size: 0.65rem;
    line-height: 1.25;
}

.dashboard-priority-row__value {
    display: inline-grid;
    min-width: 1.75rem;
    height: 1.75rem;
    place-items: center;
    justify-self: end;
    border-radius: 0.5rem;
    color: var(--priority-color);
    background: color-mix(in srgb, var(--priority-soft) 78%, #ffffff);
    font-size: 0.84rem;
    font-variant-numeric: tabular-nums;
    font-weight: 750;
}

.dashboard-priority-row--blue {
    --priority-color: #3979db;
    --priority-soft: #eaf2ff;
}

.dashboard-priority-row--green {
    --priority-color: #159b63;
    --priority-soft: #e8f8f0;
}

.dashboard-priority-row--amber {
    --priority-color: #d98321;
    --priority-soft: #fff3e4;
}

.dashboard-priority-row--red {
    --priority-color: #d7525f;
    --priority-soft: #fff0f1;
}

.dashboard-detail-label {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin: 0.3rem 0 0;
}

.dashboard-detail-label span {
    color: #334155;
    font-size: 0.82rem;
    font-weight: 750;
}

.dashboard-detail-label i {
    height: 1px;
    flex: 1;
    background: linear-gradient(90deg, #d8e0ec, transparent);
}

.dashboard-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.dashboard-panel {
    --panel-color: #3979db;
    --panel-soft: #eaf2ff;
    position: relative;
    min-width: 0;
    overflow: hidden;
    border-color: #e4e9f1;
    box-shadow: 0 0.2rem 0.75rem rgb(15 23 42 / 0.045), inset 0 2px 0 var(--panel-color);
}

.dashboard-panel--blue {
    --panel-color: #3979db;
    --panel-soft: #eaf2ff;
}

.dashboard-panel--green {
    --panel-color: #159b63;
    --panel-soft: #e8f8f0;
}

.dashboard-panel--orange {
    --panel-color: #de8422;
    --panel-soft: #fff2e2;
}

.dashboard-panel--violet {
    --panel-color: #7758cf;
    --panel-soft: #f0ebff;
}

.dashboard-panel--red {
    --panel-color: #d7525f;
    --panel-soft: #fff0f1;
}

.dashboard-panel__header,
.dashboard-shell :deep(.dashboard-panel > .border-b) {
    display: flex;
    min-height: 3.65rem;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    border-bottom: 1px solid #edf0f5;
    padding: 0.78rem 1rem;
    background: #fff;
}

.dashboard-panel__heading {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.72rem;
}

.dashboard-panel__icon {
    display: grid;
    width: 2.15rem;
    height: 2.15rem;
    flex: none;
    place-items: center;
    border-radius: 0.42rem;
    color: var(--panel-color);
    background: var(--panel-soft);
}

.dashboard-panel__icon :deep(svg) {
    width: 1.1rem;
    height: 1.1rem;
}

.dashboard-panel__heading h2 {
    color: #1f2937;
    font-size: 0.84rem;
    font-weight: 750;
    line-height: 1.25;
}

.dashboard-panel__heading p {
    margin-top: 0.12rem;
    color: #8792a5;
    font-size: 0.66rem;
    line-height: 1.35;
}

.dashboard-panel__page-size {
    display: inline-flex;
    flex: none;
    align-items: center;
    gap: 0.42rem;
    color: #7c8798;
    font-size: 0.66rem;
    font-weight: 650;
}

.dashboard-panel__page-size select {
    height: 2rem;
    min-width: 4.1rem;
    border: 1px solid #d9e2ef;
    border-radius: 0.45rem;
    padding: 0 1.55rem 0 0.56rem;
    color: #334155;
    font: inherit;
    font-weight: 700;
    background-color: #fff;
}

.dashboard-pagination {
    display: flex;
    min-height: 2.9rem;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border-top: 1px solid #edf0f5;
    padding: 0.56rem 0.9rem;
    color: #7c8798;
    font-size: 0.7rem;
}

.dashboard-pagination > div {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.dashboard-pagination button {
    display: grid;
    width: 1.85rem;
    height: 1.85rem;
    place-items: center;
    border: 1px solid #d9e2ef;
    border-radius: 0.4rem;
    color: #4b5b73;
    background: #fff;
    transition: border-color 150ms ease, color 150ms ease, background-color 150ms ease;
}

.dashboard-pagination button:not(:disabled):hover {
    border-color: #9db7e7;
    color: #1451a5;
    background: #f2f7ff;
}

.dashboard-pagination button:disabled {
    cursor: not-allowed;
    opacity: 0.42;
}

.dashboard-pagination strong {
    min-width: 2.7rem;
    color: #344054;
    font-size: 0.7rem;
    font-weight: 750;
    text-align: center;
}

.dashboard-list-enter {
    animation: dashboard-list-in 230ms ease-out both;
}

@keyframes dashboard-list-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dashboard-data-table {
    width: 100%;
    min-width: 39rem;
    border-collapse: collapse;
    color: #344054;
    font-size: 0.75rem;
    text-align: left;
}

.dashboard-data-table--wide {
    min-width: 64rem;
}

.dashboard-data-table thead {
    border-bottom: 1px solid #e7ebf1;
    color: #697586;
    background: #f7f9fc;
}

.dashboard-data-table th {
    padding: 0.68rem 0.9rem;
    font-size: 0.64rem;
    font-weight: 750;
    letter-spacing: 0;
    text-transform: uppercase;
    white-space: nowrap;
}

.dashboard-data-table td {
    padding: 0.75rem 0.9rem;
    border-bottom: 1px solid #edf0f5;
    vertical-align: top;
}

.dashboard-data-table tbody tr:last-child td {
    border-bottom: 0;
}

.dashboard-data-table tbody tr:not(:has(td[colspan])) {
    transition: background-color 140ms ease;
}

.dashboard-data-table tbody tr:not(:has(td[colspan])):hover {
    background: #fafcff;
}

.dashboard-data-link {
    color: #225fab;
    font-weight: 680;
    text-decoration: none;
}

.dashboard-data-link:hover {
    color: #174b91;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.dashboard-data-link--muted {
    color: #64748b;
    font-weight: 600;
}

.dashboard-data-link--small {
    font-size: 0.68rem;
}

.dashboard-ranking-list,
.dashboard-status-list,
.dashboard-recommendation-list {
    display: grid;
}

.dashboard-ranking-row {
    display: grid;
    grid-template-columns: 2.35rem minmax(0, 1fr) 4.9rem;
    align-items: center;
    gap: 0.75rem;
    min-height: 4.1rem;
    border-bottom: 1px solid #edf0f5;
    padding: 0.66rem 0.9rem;
    font-size: 0.75rem;
    transition: background-color 140ms ease;
}

.dashboard-ranking-row:last-of-type {
    border-bottom: 0;
}

.dashboard-ranking-row:hover {
    background: #fafcff;
}

.dashboard-ranking-row__rank {
    display: grid;
    width: 2.05rem;
    height: 2.05rem;
    place-items: center;
    border-radius: 0.42rem;
    color: var(--panel-color);
    background: var(--panel-soft);
    font-size: 0.75rem;
    font-weight: 750;
}

.dashboard-year-list {
    display: grid;
    gap: 0.78rem;
    padding: 0.92rem;
}

.dashboard-year-row {
    display: grid;
    grid-template-columns: 3.45rem minmax(0, 1fr) 3.65rem;
    align-items: center;
    gap: 0.72rem;
    color: #5f6b7d;
    font-size: 0.74rem;
}

.dashboard-year-row__track {
    height: 0.5rem;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1f6;
}

.dashboard-year-row__track > div {
    height: 100%;
    border-radius: inherit;
    background: #b7c4d8;
    will-change: width;
}

.dashboard-year-row__track > div.is-current {
    background: var(--panel-color);
}

.dashboard-status-row,
.dashboard-evaluation-row,
.dashboard-workflow-row,
.dashboard-recommendation-row {
    border-bottom: 1px solid #edf0f5;
    padding: 0.72rem 0.9rem;
    font-size: 0.75rem;
    transition: background-color 140ms ease;
}

.dashboard-status-row:last-of-type,
.dashboard-evaluation-row:last-of-type,
.dashboard-workflow-row:last-of-type,
.dashboard-recommendation-row:last-of-type {
    border-bottom: 0;
}

.dashboard-status-row:hover,
.dashboard-evaluation-row:hover,
.dashboard-workflow-row:hover,
.dashboard-recommendation-row:hover {
    background: #fafcff;
}

.dashboard-status-row,
.dashboard-workflow-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
}

.dashboard-workflow-row {
    align-items: flex-start;
}

.dashboard-evaluation-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 4.9rem 4.35rem;
    align-items: center;
    gap: 0.72rem;
}

.dashboard-recommendation-row p {
    color: #667085;
    line-height: 1.45;
}

.dashboard-panel .rounded-full {
    font-size: 0.65rem;
}

.dashboard-panel .h-2 {
    height: 0.44rem;
}

.dashboard-panel .bg-muted {
    background: #edf1f6;
}

.dashboard-panel--wide {
    width: 100%;
}

:global(.dark) .dashboard-shell {
    color: #e5e7eb;
    background: #0d1422;
}

:global(.dark) .dashboard-page-head h1,
:global(.dark) .dashboard-kpi-card__body strong,
:global(.dark) .dashboard-board-card__header h2,
:global(.dark) .dashboard-document-grid strong,
:global(.dark) .dashboard-priority-list__items strong,
:global(.dark) .dashboard-priority-list__items b,
:global(.dark) .dashboard-panel__heading h2 {
    color: #e7edf6;
}

:global(.dark) .dashboard-page-head__title > p,
:global(.dark) .dashboard-page-head__eyebrow,
:global(.dark) .dashboard-kpi-card__body p,
:global(.dark) .dashboard-kpi-card__body > span,
:global(.dark) .dashboard-board-card__header p {
    color: #93a0b4;
}

:global(.dark) .dashboard-kpi-card,
:global(.dark) .dashboard-board-card,
:global(.dark) .dashboard-panel,
:global(.dark) .dashboard-filterbar select,
:global(.dark) .dashboard-filterbar__reset {
    border-color: #273449;
    background: #131d2d;
}

:global(.dark) .dashboard-panel__header {
    background: #131d2d;
}

:global(.dark) .dashboard-panel__page-size {
    color: #a9b5c6;
}

:global(.dark) .dashboard-panel__page-size select,
:global(.dark) .dashboard-pagination button {
    border-color: #334157;
    color: #c5d0df;
    background: #172235;
}

:global(.dark) .dashboard-pagination {
    border-color: #273449;
    color: #9aa8bc;
}

:global(.dark) .dashboard-pagination strong {
    color: #e7edf6;
}

:global(.dark) .dashboard-panel__heading p,
:global(.dark) .dashboard-data-table,
:global(.dark) .dashboard-year-row,
:global(.dark) .dashboard-recommendation-row p {
    color: #9aa8bc;
}

:global(.dark) .dashboard-data-table thead {
    border-color: #273449;
    color: #a9b5c6;
    background: #182437;
}

:global(.dark) .dashboard-data-table td,
:global(.dark) .dashboard-ranking-row,
:global(.dark) .dashboard-status-row,
:global(.dark) .dashboard-evaluation-row,
:global(.dark) .dashboard-workflow-row,
:global(.dark) .dashboard-recommendation-row {
    border-color: #273449;
}

:global(.dark) .dashboard-data-table tbody tr:not(:has(td[colspan])):hover,
:global(.dark) .dashboard-ranking-row:hover,
:global(.dark) .dashboard-status-row:hover,
:global(.dark) .dashboard-evaluation-row:hover,
:global(.dark) .dashboard-workflow-row:hover,
:global(.dark) .dashboard-recommendation-row:hover {
    background: #182437;
}

:global(.dark) .dashboard-data-link {
    color: #8dbbf7;
}

:global(.dark) .dashboard-data-link:hover {
    color: #b8d5fb;
}

:global(.dark) .dashboard-data-link--muted {
    color: #9aa8bc;
}

:global(.dark) .dashboard-year-row__track,
:global(.dark) .dashboard-panel .bg-muted {
    background: #273449;
}

:global(.dark) .dashboard-detail-label span {
    color: #aeb9c9;
}

:global(.dark) .dashboard-detail-label i {
    background: linear-gradient(90deg, #334155, transparent);
}

:global(.dark) .dashboard-filterbar select,
:global(.dark) .dashboard-filterbar__reset {
    color: #d9e2ef;
}

:global(.dark) .dashboard-progress-ring::before {
    background: #131d2d;
}

:global(.dark) .dashboard-document-grid > div,
:global(.dark) .dashboard-priority-row__icon,
:global(.dark) .dashboard-kpi-card__icon {
    filter: brightness(0.82) saturate(0.9);
}

:global(.dark) .dashboard-board-card__header,
:global(.dark) .dashboard-document-card__footer,
:global(.dark) .dashboard-quarter-card__legend,
:global(.dark) .dashboard-trend-card__status,
:global(.dark) .dashboard-panel__header,
:global(.dark) .dashboard-shell :deep(.dashboard-panel > .border-b) {
    border-color: #273449;
}

:global(.dark) .dashboard-module-row__track,
:global(.dark) .dashboard-quarter-chart__bar,
:global(.dark) .dashboard-trend-chart__row > div {
    background: #273449;
}

@media (max-width: 1200px) {
    .dashboard-analytics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-quarter-card {
        grid-column: 1 / -1;
    }

    .dashboard-detail-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .dashboard-page-head {
        align-items: stretch;
        flex-direction: column;
    }

    .dashboard-filterbar {
        width: 100%;
    }

    .dashboard-filterbar__field,
    .dashboard-filterbar__field--opd {
        width: auto;
        flex: 1;
    }

    .dashboard-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-lower-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .dashboard-shell {
        gap: 0.75rem;
        padding: 0.75rem;
    }

    .dashboard-page-head__row {
        align-items: start;
        flex-direction: column;
    }

    .dashboard-page-head h1 {
        font-size: 1.35rem;
    }

    .dashboard-filterbar {
        display: grid;
        grid-template-columns: 1fr;
    }

    .dashboard-filterbar__reset {
        width: 100%;
    }

    .dashboard-kpi-grid,
    .dashboard-analytics-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-quarter-card {
        grid-column: auto;
    }

    .dashboard-progress-card__content {
        grid-template-columns: 1fr;
        justify-items: center;
    }

    .dashboard-module-list {
        width: 100%;
    }

    .dashboard-document-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-trend-card__status {
        align-items: start;
        flex-direction: column;
    }

    .dashboard-ranking-row {
        grid-template-columns: 2rem minmax(0, 1fr) 3.6rem;
    }

    .dashboard-panel__heading p {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .dashboard-overview__copy,
    .dashboard-filters {
        animation: none;
    }

    .dashboard-priority-card,
    .dashboard-metric-card,
    .dashboard-quick-link,
    .dashboard-reset {
        transition: none;
    }

    .dashboard-list-enter {
        animation: none;
    }
}
</style>
