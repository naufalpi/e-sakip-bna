<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDocumentDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, BadgeCheck, Banknote, FilePenLine, FileSpreadsheet, Pencil, Plus, Search, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

type Option = { id: number; label: string };
type Row = {
    id: number;
    judul: string;
    nomor_dokumen?: string | null;
    tahun: number;
    jenis_anggaran: 'murni' | 'perubahan';
    type_label: string;
    status: string;
    items_count: number;
    total_pagu_rka?: string | number | null;
    can_update: boolean;
    can_delete: boolean;
    opd?: { kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode?: string | null; nama: string } | null;
    renja?: { judul: string; jenis_versi: string } | null;
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
    links?: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    items: Paginator<Row>;
    filters: { search?: string; status?: string; opd_id?: string; tahun?: string; jenis_anggaran?: string; per_page?: string };
    opdOptions: Option[];
    stats: { documents: number; draft: number; review: number; approved: number };
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    tahun: props.filters.tahun ?? '',
    jenis_anggaran: props.filters.jenis_anggaran ?? '',
    per_page: props.filters.per_page ?? '10',
});

const applyFilters = () => router.get(route('rka-opd.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    Object.assign(filterForm, { search: '', status: '', opd_id: '', tahun: '', jenis_anggaran: '' });
    applyFiltersNow();
};

const destroy = async (row: Row) => {
    const opd = row.opd?.singkatan || row.opd?.nama || row.judul;
    if (await confirmDocumentDelete(`Hapus RKA ${opd} Tahun ${row.tahun}?`)) {
        router.delete(route('rka-opd.destroy', row.id));
    }
};

const rupiah = (value?: string | number | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Perlu Perbaikan',
        verified: 'Terverifikasi',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;

const statusClass = (status: string) =>
    ({
        draft: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
        submitted: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/50 dark:text-blue-200 dark:ring-blue-800',
        revision: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/50 dark:text-amber-200 dark:ring-amber-800',
        verified: 'bg-cyan-50 text-cyan-700 ring-cyan-200 dark:bg-cyan-950/50 dark:text-cyan-200 dark:ring-cyan-800',
        approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-800',
        rejected: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/50 dark:text-red-200 dark:ring-red-800',
        locked: 'bg-zinc-200 text-zinc-800 ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-700',
    })[status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
</script>

<template>
    <Head title="RKA OPD" />

    <div class="flex flex-col gap-5 p-4 sm:p-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <div
                class="relative border-b border-slate-200 bg-[linear-gradient(116deg,#fff_0%,#f5f9ff_60%,#edf6ff_100%)] px-5 py-6 dark:border-slate-800 dark:bg-[linear-gradient(116deg,#0f172a_0%,#0b1d34_62%,#0a2746_100%)] sm:px-6"
            >
                <div class="absolute -right-14 -top-24 size-64 rounded-full bg-blue-300/20 blur-3xl dark:bg-blue-500/10"></div>
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white shadow-[0_8px_24px_rgba(0,51,108,.22)]"
                        >
                            <FileSpreadsheet class="size-5" />
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#5276a0] dark:text-blue-300">
                                Penganggaran berbasis kinerja
                            </p>
                            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">RKA OPD</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                                Rencana Kerja dan Anggaran Satuan Kerja Perangkat Daerah.
                            </p>
                        </div>
                    </div>
                    <Link
                        v-if="can.manage"
                        :href="route('rka-opd.create')"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002855] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950"
                    >
                        <Plus class="size-4" />
                        Buat RKA dari RENJA
                    </Link>
                </div>
            </div>

            <div class="grid divide-y divide-slate-200 dark:divide-slate-800 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                <div
                    v-for="metric in [
                        {
                            label: 'Dokumen',
                            value: stats.documents,
                            icon: FileSpreadsheet,
                            tone: 'text-blue-700 bg-blue-50 dark:bg-blue-950/50 dark:text-blue-300',
                        },
                        {
                            label: 'Penyusunan',
                            value: stats.draft,
                            icon: FilePenLine,
                            tone: 'text-amber-700 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-300',
                        },
                        {
                            label: 'Pemeriksaan',
                            value: stats.review,
                            icon: ShieldCheck,
                            tone: 'text-cyan-700 bg-cyan-50 dark:bg-cyan-950/50 dark:text-cyan-300',
                        },
                        {
                            label: 'Disetujui',
                            value: stats.approved,
                            icon: BadgeCheck,
                            tone: 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-300',
                        },
                    ]"
                    :key="metric.label"
                    class="flex items-center gap-3 px-5 py-4"
                >
                    <div class="flex size-9 items-center justify-center rounded-lg" :class="metric.tone">
                        <component :is="metric.icon" class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ metric.label }}</p>
                        <p class="text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ metric.value }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-card p-4 shadow-sm dark:border-slate-800 sm:p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-slate-900 dark:text-white">Temukan dokumen</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Saring RKA berdasarkan OPD, tahun, jenis anggaran, atau status.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>
            <form class="grid gap-3 xl:grid-cols-[minmax(15rem,1.5fr)_minmax(12rem,1fr)_170px_160px_110px]" @submit.prevent="applyFiltersNow">
                <label class="relative"
                    ><Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input
                        v-model="filterForm.search"
                        type="search"
                        class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        placeholder="Cari judul, nomor, atau OPD"
                /></label>
                <select
                    v-model="filterForm.opd_id"
                    class="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                >
                    <option value="">Semua OPD</option>
                    <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select
                    v-model="filterForm.jenis_anggaran"
                    class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                >
                    <option value="">Semua jenis</option>
                    <option value="murni">RKA APBD</option>
                    <option value="perubahan">RKA Perubahan APBD</option>
                </select>
                <select
                    v-model="filterForm.status"
                    class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                >
                    <option value="">Semua status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Diajukan</option>
                    <option value="revision">Perlu Perbaikan</option>
                    <option value="verified">Terverifikasi (data lama)</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="locked">Terkunci</option>
                </select>
                <input
                    v-model="filterForm.tahun"
                    type="number"
                    class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                    placeholder="Tahun"
                />
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                <div>
                    <h2 class="font-bold text-slate-900 dark:text-white">Daftar RKA OPD</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ items.total }} dokumen ditemukan.</p>
                </div>
                <span
                    class="hidden items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300 sm:inline-flex"
                    ><Banknote class="size-3.5" /> Anggaran kinerja</span
                >
            </header>

            <div v-if="items.data.length" class="divide-y divide-slate-200 dark:divide-slate-800">
                <article
                    v-for="row in items.data"
                    :key="row.id"
                    class="grid gap-4 px-5 py-5 transition hover:bg-slate-50/70 dark:hover:bg-slate-900/50 sm:px-6 xl:grid-cols-[minmax(15rem,1.15fr)_minmax(12rem,.8fr)_minmax(11rem,.7fr)_150px_auto] xl:items-center"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-bold text-slate-950 dark:text-white">{{ row.opd?.singkatan || row.opd?.nama || 'OPD' }}</p>
                            <span
                                class="rounded-md border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-200"
                                >{{ row.type_label }}</span
                            >
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">{{ row.judul }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ row.nomor_dokumen || 'Nomor dokumen belum diisi' }} · {{ row.tahun }}</p>
                    </div>
                    <div class="min-w-0 border-l-2 border-blue-100 pl-3 dark:border-blue-900/60">
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-slate-400">Acuan RENJA</p>
                        <p class="mt-1 line-clamp-2 text-sm font-medium text-slate-700 dark:text-slate-200">{{ row.renja?.judul || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-slate-400">Pagu RKA</p>
                        <p class="mt-1 font-bold tabular-nums text-slate-950 dark:text-white">{{ rupiah(row.total_pagu_rka) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ row.items_count }} sub kegiatan</p>
                    </div>
                    <div class="xl:text-center">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="statusClass(row.status)">{{
                            statusLabel(row.status)
                        }}</span>
                    </div>
                    <div class="flex items-center gap-2 xl:justify-end">
                        <Link
                            :href="route('rka-opd.show', row.id)"
                            class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#00336C] px-3.5 text-sm font-semibold text-white hover:bg-[#002855]"
                            >Buka <ArrowRight class="size-4"
                        /></Link>
                        <Link
                            v-if="row.can_update"
                            :href="route('rka-opd.edit', row.id)"
                            class="inline-flex size-10 items-center justify-center rounded-lg border text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                            title="Edit RKA"
                            ><Pencil class="size-4"
                        /></Link>
                        <button
                            v-if="row.can_delete"
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/40"
                            title="Hapus RKA"
                            @click="destroy(row)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </article>
            </div>
            <div v-else class="px-6 py-16 text-center">
                <FileSpreadsheet class="mx-auto size-10 text-slate-300" />
                <p class="mt-3 font-semibold text-slate-700 dark:text-slate-200">Belum ada RKA</p>
                <p class="mt-1 text-sm text-slate-500">Buat RKA dari RENJA yang sudah ditetapkan.</p>
            </div>

            <DataPagination v-model:per-page="filterForm.per_page" :paginator="items" item-label="dokumen RKA" />
        </section>
    </div>
</template>
