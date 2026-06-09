<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    GitBranch,
    Layers3,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, reactive } from 'vue';

type Option = { id: number; label: string };

type RenstraRow = {
    id: number;
    judul: string;
    nomor_dokumen?: string | null;
    tahun_awal: number;
    tahun_akhir: number;
    status: string;
    opd?: { id: number; kode: string; nama: string; singkatan?: string | null } | null;
    rpjmd?: { id: number; judul: string; tahun_awal: number; tahun_akhir: number } | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
    progress: {
        tujuan_count: number;
        program_count: number;
        status: string;
    };
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    renstras: Paginator<RenstraRow>;
    filters: {
        search?: string;
        status?: string;
        opd_id?: string;
        rpjmd_id?: string;
        periode_tahun_id?: string;
    };
    opdOptions: Option[];
    rpjmdOptions: Option[];
    periodeOptions: Option[];
    can: {
        manage: boolean;
    };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    rpjmd_id: props.filters.rpjmd_id ?? '',
    periode_tahun_id: props.filters.periode_tahun_id ?? '',
});

const applyFilters = () => {
    router.get(route('renstra-opd.index'), filterForm, {
        preserveState: true,
        replace: true,
    });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    filterForm.rpjmd_id = '';
    filterForm.periode_tahun_id = '';
    applyFiltersNow();
};

const destroy = (renstra: RenstraRow) => {
    if (confirm(`Hapus Renstra ${renstra.opd?.singkatan || renstra.opd?.nama || ''} ${renstra.tahun_awal}-${renstra.tahun_akhir}?`)) {
        router.delete(route('renstra-opd.destroy', renstra.id));
    }
};

const visibleRows = computed(() => props.renstras.data);
const visibleTotal = computed(() => visibleRows.value.length);
const completedRows = computed(() => visibleRows.value.filter((renstra) => renstra.progress.status === 'terisi').length);
const linkedRows = computed(() => visibleRows.value.filter((renstra) => Boolean(renstra.rpjmd)).length);
const approvedRows = computed(() => visibleRows.value.filter((renstra) => ['verified', 'approved', 'locked'].includes(renstra.status)).length);
const draftRows = computed(() => visibleRows.value.filter((renstra) => ['draft', 'revision', 'rejected'].includes(renstra.status)).length);
const activeFilterCount = computed(() => Object.values(filterForm).filter((value) => String(value || '').trim() !== '').length);

const summaryCards = computed(() => [
    {
        label: 'Total Renstra',
        value: props.renstras.total,
        helper: `${visibleTotal.value} tampil di halaman ini`,
        icon: FileText,
        tone: 'slate',
    },
    {
        label: 'Cascading Terisi',
        value: completedRows.value,
        helper: `${formatPercent(completedRows.value, visibleTotal.value)} dari data tampil`,
        icon: CheckCircle2,
        tone: 'emerald',
    },
    {
        label: 'Terhubung RPJMD',
        value: linkedRows.value,
        helper: `${formatPercent(linkedRows.value, visibleTotal.value)} sudah sinkron`,
        icon: GitBranch,
        tone: 'blue',
    },
    {
        label: 'Siap Monitoring',
        value: approvedRows.value,
        helper: `${draftRows.value} masih draft/revisi`,
        icon: ClipboardList,
        tone: 'amber',
    },
]);

const selectedFilterLabels = computed(
    () =>
        [
            filterForm.search ? `Cari: ${filterForm.search}` : null,
            filterForm.status ? `Status: ${statusLabel(filterForm.status)}` : null,
            filterForm.opd_id
                ? `OPD: ${props.opdOptions.find((option) => String(option.id) === String(filterForm.opd_id))?.label ?? 'Terpilih'}`
                : null,
            filterForm.rpjmd_id
                ? `RPJMD: ${props.rpjmdOptions.find((option) => String(option.id) === String(filterForm.rpjmd_id))?.label ?? 'Terpilih'}`
                : null,
            filterForm.periode_tahun_id
                ? `Periode: ${props.periodeOptions.find((option) => String(option.id) === String(filterForm.periode_tahun_id))?.label ?? 'Terpilih'}`
                : null,
        ].filter(Boolean) as string[],
);

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Revisi',
        verified: 'Terverifikasi',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;

const statusClass = (status: string) =>
    ({
        draft: 'bg-slate-100 text-slate-700 ring-slate-200',
        submitted: 'bg-blue-100 text-blue-800 ring-blue-200',
        revision: 'bg-amber-100 text-amber-800 ring-amber-200',
        verified: 'bg-cyan-100 text-cyan-800 ring-cyan-200',
        approved: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        rejected: 'bg-red-100 text-red-800 ring-red-200',
        locked: 'bg-zinc-200 text-zinc-800 ring-zinc-300',
    })[status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';

const progressClass = (status: string) =>
    status === 'terisi' ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-amber-100 text-amber-800 ring-amber-200';

function rowProgressPercent(renstra: RenstraRow): number {
    let percent = 0;

    if (renstra.progress.tujuan_count > 0) {
        percent += 50;
    }

    if (renstra.progress.program_count > 0) {
        percent += 50;
    }

    return percent;
}

function formatPercent(value: number, total: number): string {
    if (total <= 0) {
        return '0%';
    }

    return `${Math.round((value / total) * 100)}%`;
}

function summaryToneClass(tone: string): string {
    return (
        {
            slate: 'border-slate-200 bg-white text-slate-950',
            emerald: 'border-emerald-200 bg-emerald-50 text-emerald-950',
            blue: 'border-sky-200 bg-sky-50 text-sky-950',
            amber: 'border-amber-200 bg-amber-50 text-amber-950',
        }[tone] ?? 'border-slate-200 bg-white text-slate-950'
    );
}

function iconToneClass(tone: string): string {
    return (
        {
            slate: 'bg-slate-100 text-slate-700',
            emerald: 'bg-emerald-100 text-emerald-800',
            blue: 'bg-sky-100 text-sky-800',
            amber: 'bg-amber-100 text-amber-800',
        }[tone] ?? 'bg-slate-100 text-slate-700'
    );
}
</script>

<template>
    <Head title="Renstra OPD" />
    <div class="flex flex-col gap-5 p-4">
        <section class="overflow-hidden rounded-lg border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fafc,#ecfdf5)] px-4 py-5 sm:px-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1 text-xs font-semibold uppercase text-emerald-800"
                        >
                            <Layers3 class="size-3.5" />
                            Perencanaan OPD
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-normal text-slate-950">Renstra OPD</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola dokumen Renstra, pantau keterhubungan RPJMD, dan lanjutkan pengisian cascading tujuan, sasaran, program, kegiatan,
                            sub kegiatan, indikator, serta target triwulan.
                        </p>
                    </div>

                    <div v-if="can.manage" class="flex flex-col gap-2 sm:flex-row">
                        <Link
                            :href="route('renstra-opd.import.create')"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border bg-white px-3 text-sm font-medium text-slate-800 hover:bg-slate-50"
                        >
                            <FileSpreadsheet class="size-4" />
                            Import Excel
                        </Link>
                        <Link
                            :href="route('renstra-opd.create')"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-semibold text-white hover:bg-emerald-800"
                        >
                            <Plus class="size-4" />
                            Tambah Renstra
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in summaryCards" :key="card.label" class="rounded-lg border p-4" :class="summaryToneClass(card.tone)">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium">{{ card.label }}</p>
                            <p class="mt-2 text-3xl font-semibold leading-none">{{ card.value }}</p>
                            <p class="mt-2 text-xs opacity-75">{{ card.helper }}</p>
                        </div>
                        <span class="inline-flex size-10 items-center justify-center rounded-md" :class="iconToneClass(card.tone)">
                            <component :is="card.icon" class="size-5" />
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4 shadow-sm">
            <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Filter dan Pencarian</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Filter otomatis berjalan saat kolom diubah.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm text-muted-foreground hover:bg-muted"
                    @click="resetFilters"
                >
                    Reset filter
                </button>
            </div>

            <form
                class="grid gap-3 lg:grid-cols-[minmax(16rem,1.4fr)_170px_minmax(14rem,1fr)_minmax(14rem,1fr)_170px]"
                @submit.prevent="applyFiltersNow"
            >
                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Pencarian</span>
                    <span class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            placeholder="Cari judul, nomor dokumen, atau OPD"
                        />
                    </span>
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Status</span>
                    <select
                        v-model="filterForm.status"
                        class="h-10 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Diajukan</option>
                        <option value="revision">Revisi</option>
                        <option value="verified">Terverifikasi</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                        <option value="locked">Terkunci</option>
                    </select>
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">OPD</span>
                    <select
                        v-model="filterForm.opd_id"
                        class="h-10 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">RPJMD</span>
                    <select
                        v-model="filterForm.rpjmd_id"
                        class="h-10 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua RPJMD</option>
                        <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Periode</span>
                    <select
                        v-model="filterForm.periode_tahun_id"
                        class="h-10 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option value="">Semua tahun</option>
                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>
            </form>

            <div v-if="activeFilterCount" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="label in selectedFilterLabels"
                    :key="label"
                    class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800"
                >
                    {{ label }}
                </span>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="overflow-hidden rounded-lg border bg-card shadow-sm">
                <div class="flex flex-col gap-2 border-b px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Daftar Renstra</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Menampilkan {{ renstras.from ?? 0 }}-{{ renstras.to ?? 0 }} dari {{ renstras.total }} data.
                        </p>
                    </div>
                    <span class="rounded-full border bg-background px-3 py-1 text-xs font-semibold text-muted-foreground">
                        Halaman {{ renstras.current_page }} / {{ renstras.last_page }}
                    </span>
                </div>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">OPD dan Koneksi</th>
                                <th class="px-4 py-3">Dokumen Renstra</th>
                                <th class="px-4 py-3">Kelengkapan Cascading</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="renstra in renstras.data" :key="renstra.id" class="border-b align-top last:border-0 hover:bg-muted/40">
                                <td class="min-w-72 px-4 py-4">
                                    <div class="font-semibold text-slate-950">{{ renstra.opd?.singkatan || renstra.opd?.nama || '-' }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ renstra.opd?.kode || 'Kode belum diisi' }}</div>
                                    <div class="mt-3 rounded-md border bg-background p-3">
                                        <p class="text-xs font-semibold uppercase text-muted-foreground">Terhubung RPJMD</p>
                                        <p v-if="renstra.rpjmd" class="mt-1 line-clamp-2 text-xs leading-5 text-slate-700">
                                            {{ renstra.rpjmd.tahun_awal }}-{{ renstra.rpjmd.tahun_akhir }} - {{ renstra.rpjmd.judul }}
                                        </p>
                                        <p v-else class="mt-1 text-xs text-amber-700">Belum terhubung ke RPJMD.</p>
                                    </div>
                                </td>
                                <td class="min-w-80 px-4 py-4">
                                    <div class="font-semibold text-slate-950">{{ renstra.judul }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ renstra.nomor_dokumen || 'Nomor dokumen belum diisi' }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            {{ renstra.tahun_awal }}-{{ renstra.tahun_akhir }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            {{ renstra.periode_tahun?.nama || 'Periode belum diisi' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="min-w-72 px-4 py-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Progress</span>
                                        <span class="text-xs font-semibold text-slate-700">{{ rowProgressPercent(renstra) }}%</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-700" :style="{ width: `${rowProgressPercent(renstra)}%` }"></div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-medium ring-1"
                                            :class="progressClass(renstra.progress.status)"
                                        >
                                            {{ renstra.progress.status === 'terisi' ? 'Terisi' : 'Belum lengkap' }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            {{ renstra.progress.tujuan_count }} tujuan
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            {{ renstra.progress.program_count }} program
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                                        :class="statusClass(renstra.status)"
                                    >
                                        {{ statusLabel(renstra.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="inline-flex gap-2">
                                        <Link
                                            :href="route('renstra-opd.show', renstra.id)"
                                            class="inline-flex h-9 items-center gap-2 rounded-md bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-800"
                                        >
                                            Buka
                                            <ArrowRight class="size-3.5" />
                                        </Link>
                                        <Link
                                            v-if="can.manage"
                                            :href="route('renstra-opd.edit', renstra.id)"
                                            class="inline-flex h-9 items-center justify-center rounded-md border px-2 text-muted-foreground hover:bg-muted"
                                            title="Edit Renstra"
                                            aria-label="Edit Renstra"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                        <button
                                            v-if="can.manage"
                                            type="button"
                                            class="inline-flex h-9 items-center justify-center rounded-md border px-2 text-red-700 hover:bg-red-50"
                                            title="Hapus Renstra"
                                            aria-label="Hapus Renstra"
                                            @click="destroy(renstra)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="renstras.data.length === 0">
                                <td colspan="5" class="px-4 py-14 text-center">
                                    <div class="mx-auto max-w-md">
                                        <Layers3 class="mx-auto size-10 text-muted-foreground" />
                                        <p class="mt-3 font-semibold">Belum ada data Renstra OPD</p>
                                        <p class="mt-1 text-sm text-muted-foreground">Tambahkan Renstra atau ubah filter pencarian.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 p-4 lg:hidden">
                    <article v-for="renstra in renstras.data" :key="`mobile-${renstra.id}`" class="rounded-lg border bg-background p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase text-muted-foreground">{{ renstra.opd?.kode || 'OPD' }}</p>
                                <h3 class="mt-1 text-base font-semibold leading-snug text-slate-950">
                                    {{ renstra.opd?.singkatan || renstra.opd?.nama || '-' }}
                                </h3>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(renstra.status)">
                                {{ statusLabel(renstra.status) }}
                            </span>
                        </div>

                        <div class="mt-4">
                            <p class="font-medium">{{ renstra.judul }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ renstra.nomor_dokumen || 'Nomor dokumen belum diisi' }}</p>
                        </div>

                        <div class="mt-4 grid gap-3 rounded-md border bg-card p-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold uppercase text-muted-foreground">Cascading</span>
                                <span class="font-semibold">{{ rowProgressPercent(renstra) }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-700" :style="{ width: `${rowProgressPercent(renstra)}%` }"></div>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                    >{{ renstra.progress.tujuan_count }} tujuan</span
                                >
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                    >{{ renstra.progress.program_count }} program</span
                                >
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                    >{{ renstra.tahun_awal }}-{{ renstra.tahun_akhir }}</span
                                >
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('renstra-opd.show', renstra.id)"
                                class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-semibold text-white"
                            >
                                Buka
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                v-if="can.manage"
                                :href="route('renstra-opd.edit', renstra.id)"
                                class="inline-flex min-h-10 items-center justify-center rounded-md border px-3 text-sm"
                                aria-label="Edit Renstra"
                            >
                                <Pencil class="size-4" />
                            </Link>
                        </div>
                    </article>

                    <div v-if="renstras.data.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Belum ada data Renstra OPD.
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                    <span>Menampilkan {{ renstras.from ?? 0 }}-{{ renstras.to ?? 0 }} dari {{ renstras.total }} data</span>
                    <div class="flex flex-wrap gap-2">
                        <Link v-if="renstras.prev_page_url" :href="renstras.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">
                            Sebelumnya
                        </Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                        <span class="px-2 py-1.5">Halaman {{ renstras.current_page }} / {{ renstras.last_page }}</span>
                        <Link v-if="renstras.next_page_url" :href="renstras.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">
                            Berikutnya
                        </Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                    </div>
                </div>
            </div>

            <aside class="grid gap-4 xl:sticky xl:top-20 xl:self-start">
                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-semibold">Alur kerja</h2>
                    <ol class="mt-4 space-y-3 text-sm">
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800"
                                >1</span
                            >
                            <span>Buat identitas Renstra dan hubungkan ke RPJMD.</span>
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800"
                                >2</span
                            >
                            <span>Isi cascading tujuan, sasaran, program, kegiatan, dan sub kegiatan.</span>
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800"
                                >3</span
                            >
                            <span>Lengkapi indikator serta target tahunan/triwulan.</span>
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800"
                                >4</span
                            >
                            <span>Ajukan workflow setelah struktur dan target siap diverifikasi.</span>
                        </li>
                    </ol>
                </section>

                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-semibold">Kondisi perlu dicek</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-950">
                            <p class="font-semibold">{{ visibleTotal - completedRows }} belum lengkap</p>
                            <p class="mt-1 text-xs leading-5">Lengkapi minimal tujuan dan program agar Renstra siap dimonitor.</p>
                        </div>
                        <div class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sky-950">
                            <p class="font-semibold">{{ visibleTotal - linkedRows }} belum terhubung RPJMD</p>
                            <p class="mt-1 text-xs leading-5">Koneksi RPJMD membantu sinkronisasi cascading kabupaten ke OPD.</p>
                        </div>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</template>
