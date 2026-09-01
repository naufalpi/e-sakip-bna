<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDocumentDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, FileClock, FileText, GitBranch, Pencil, Plus, RefreshCcw, Search, Trash2 } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

type Option = { id: number; label: string; tahun?: number };
type Row = {
    id: number;
    judul: string;
    nomor_dokumen?: string | null;
    tahun: number;
    status: string;
    jenis_versi: 'awal' | 'ditetapkan' | 'perubahan';
    version_label: string;
    nomor_versi: number;
    is_active_version: boolean;
    can_update: boolean;
    can_delete: boolean;
    items_count: number;
    opd?: { id: number; kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { id: number; kode?: string | null; nama: string } | null;
    rkpd?: { id: number; judul: string; tahun: number; jenis_versi: string; version_label: string } | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
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
    items: Paginator<Row>;
    filters: { search?: string; status?: string; opd_id?: string; periode_tahun_id?: string; tahun?: string; jenis_versi?: string };
    opdOptions: Option[];
    periodeOptions: Option[];
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    periode_tahun_id: props.filters.periode_tahun_id ?? '',
    tahun: props.filters.tahun ?? '',
    jenis_versi: props.filters.jenis_versi ?? '',
});

const applyFilters = () => router.get(route('renja-opd.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    filterForm.periode_tahun_id = '';
    filterForm.tahun = '';
    filterForm.jenis_versi = '';
    applyFiltersNow();
};

const destroy = async (row: Row) => {
    if (await confirmDocumentDelete(`Hapus Renja ${row.tahun} - ${row.opd?.singkatan || row.opd?.nama || row.judul}?`)) {
        router.delete(route('renja-opd.destroy', row.id));
    }
};

const initialCount = computed(() => props.items.data.filter((row) => row.jenis_versi === 'awal').length);
const establishedCount = computed(() => props.items.data.filter((row) => row.jenis_versi === 'ditetapkan').length);
const changeCount = computed(() => props.items.data.filter((row) => row.jenis_versi === 'perubahan').length);
const versionLabel = (row: Pick<Row, 'jenis_versi' | 'version_label'>) => (row.jenis_versi === 'awal' ? 'RENJA Akhir Draft' : row.version_label);

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
        draft: 'bg-slate-100 text-slate-700 ring-slate-200',
        submitted: 'bg-blue-100 text-blue-800 ring-blue-200',
        revision: 'bg-amber-100 text-amber-800 ring-amber-200',
        verified: 'bg-cyan-100 text-cyan-800 ring-cyan-200',
        approved: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        rejected: 'bg-red-100 text-red-800 ring-red-200',
        locked: 'bg-zinc-200 text-zinc-800 ring-zinc-300',
    })[status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';

const versionClass = (version: Row['jenis_versi']) =>
    ({
        awal: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        ditetapkan: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
        perubahan: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    })[version];
</script>

<template>
    <Head title="Renja OPD" />

    <div class="flex flex-col gap-5 p-4 sm:p-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <div class="relative overflow-hidden border-b border-slate-200 bg-[linear-gradient(118deg,#ffffff_0%,#f5f9ff_58%,#edf6ff_100%)] px-5 py-5 dark:border-slate-800 dark:bg-slate-950 sm:px-6 sm:py-6">
                <div class="absolute -right-16 -top-20 size-56 rounded-full bg-blue-200/25 blur-3xl dark:bg-blue-500/10"></div>
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-3.5">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white shadow-sm">
                            <FileText class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#5276a0] dark:text-blue-300">Perencanaan tahunan</p>
                            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-950 dark:text-slate-50 sm:text-2xl">Renja OPD</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                                Kelola RENJA Akhir Draft, dokumen yang telah ditetapkan, dan RENJA Perubahan dalam satu riwayat tahunan per OPD.
                            </p>
                        </div>
                    </div>

                    <Link
                        v-if="can.manage"
                        :href="route('renja-opd.create')"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#002855] focus:outline-none focus:ring-2 focus:ring-[#00336C] focus:ring-offset-2 dark:focus:ring-offset-slate-950"
                    >
                        <Plus class="size-4" />
                        Tambah RENJA Akhir Draft
                    </Link>
                </div>
            </div>

            <div class="grid divide-y divide-slate-200 sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-slate-800">
                <article class="flex items-center gap-3 px-5 py-4 sm:px-6">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">
                        <FileClock class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">RENJA Akhir Draft</p>
                        <p class="mt-0.5 text-xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ initialCount }}</p>
                    </div>
                </article>
                <article class="flex items-center gap-3 px-5 py-4 sm:px-6">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <CheckCircle2 class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">RENJA Ditetapkan</p>
                        <p class="mt-0.5 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ establishedCount }}</p>
                    </div>
                </article>
                <article class="flex items-center gap-3 px-5 py-4 sm:px-6">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                        <RefreshCcw class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">RENJA Perubahan</p>
                        <p class="mt-0.5 text-xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ changeCount }}</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-card p-4 shadow-sm dark:border-slate-800 sm:p-5">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Temukan dokumen</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Saring berdasarkan OPD, tahun, periode, atau status dokumen.</p>
                </div>
                <button type="button" class="h-9 self-start rounded-lg px-3 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 sm:self-auto dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100" @click="resetFilters">Reset filter</button>
            </div>
            <form class="grid gap-3 xl:grid-cols-[minmax(14rem,1.2fr)_140px_180px_minmax(12rem,1fr)_160px_110px]" @submit.prevent="applyFiltersNow">
                <label class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        placeholder="Cari judul, nomor, atau OPD"
                    />
                </label>
                <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                    <option value="">Semua status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Diajukan</option>
                    <option value="revision">Perlu Perbaikan</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="locked">Terkunci</option>
                </select>
                <select v-model="filterForm.jenis_versi" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                    <option value="">Semua versi</option>
                    <option value="awal">RENJA Akhir Draft</option>
                    <option value="ditetapkan">RENJA Ditetapkan</option>
                    <option value="perubahan">RENJA Perubahan</option>
                </select>
                <select v-model="filterForm.opd_id" class="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                    <option value="">Semua OPD</option>
                    <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select
                    v-model="filterForm.periode_tahun_id"
                    class="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                >
                    <option value="">Semua periode</option>
                    <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <input v-model="filterForm.tahun" type="number" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Tahun" />
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6 dark:border-slate-800">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Daftar Renja OPD</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} dokumen.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <FileText class="size-3.5" />
                    Riwayat per OPD
                </span>
            </div>

            <div role="list">
                <div class="hidden border-b border-slate-100 px-5 py-3 xl:grid xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center xl:gap-6 sm:px-6 dark:border-slate-800">
                    <div class="grid max-w-[58rem] grid-cols-[minmax(12rem,0.8fr)_minmax(17rem,1.2fr)_auto_auto] items-center gap-x-6">
                        <p class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">OPD</p>
                        <p class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">Dokumen Renja</p>
                        <p class="text-center text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">Sub kegiatan</p>
                        <p class="text-center text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">Status</p>
                    </div>
                    <p class="text-right text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 dark:text-slate-500">Aksi</p>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    <article
                        v-for="row in items.data"
                        :key="row.id"
                        role="listitem"
                        class="grid gap-4 px-5 py-5 transition-colors hover:bg-slate-50/80 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center xl:gap-6 sm:px-6 dark:hover:bg-slate-900/45"
                    >
                        <div class="grid min-w-0 gap-x-6 gap-y-4 sm:grid-cols-[minmax(12rem,0.8fr)_minmax(16rem,1.2fr)_auto_auto] sm:items-center xl:max-w-[58rem]">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 xl:hidden dark:text-slate-500">OPD</p>
                                <p class="mt-1.5 font-bold text-slate-900 xl:mt-0 dark:text-slate-100">{{ row.opd?.singkatan || row.opd?.nama || '-' }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ row.opd_unit?.nama || row.opd?.kode || '-' }}</p>
                                <div v-if="row.rkpd" class="mt-2.5 flex min-w-0 items-start gap-2 text-slate-500 dark:text-slate-400">
                                    <GitBranch class="mt-0.5 size-3.5 shrink-0 text-[#00336C] dark:text-blue-300" />
                                    <span class="text-xs leading-5">{{ row.rkpd.tahun }} · {{ row.rkpd.version_label }}</span>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 xl:hidden dark:text-slate-500">Dokumen Renja</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2 xl:mt-0">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold" :class="versionClass(row.jenis_versi)">{{ versionLabel(row) }}</span>
                                    <span v-if="row.is_active_version" class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">Versi aktif</span>
                                    <span
                                        v-else-if="row.jenis_versi === 'awal' && ['approved', 'locked'].includes(row.status)"
                                        class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                        >Arsip proses</span
                                    >
                                </div>
                                <p class="mt-2 text-sm font-bold leading-5 text-slate-900 dark:text-slate-100">{{ row.judul }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ row.tahun }} · {{ row.nomor_dokumen || 'Nomor dokumen belum diisi' }}</p>
                            </div>

                            <div class="flex flex-col items-start xl:items-center">
                                <p class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 xl:hidden dark:text-slate-500">Sub kegiatan</p>
                                <span class="mt-1.5 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#00336C] xl:mt-0 dark:bg-blue-950/50 dark:text-blue-300">{{ row.items_count }} sub kegiatan</span>
                            </div>

                            <div class="flex flex-col items-start xl:items-center">
                                <p class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 xl:hidden dark:text-slate-500">Status</p>
                                <span class="mt-1.5 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 xl:mt-0" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col items-start xl:items-end">
                            <p class="text-[10px] font-bold uppercase tracking-[0.13em] text-slate-400 xl:hidden dark:text-slate-500">Aksi</p>
                            <div class="mt-1.5 inline-flex gap-2 xl:mt-0">
                                <Link :href="route('renja-opd.show', row.id)" class="inline-flex h-9 items-center gap-2 rounded-md bg-[#00336C] px-3 text-xs font-semibold text-white transition-colors hover:bg-[#0a4485]">
                                    Buka
                                    <ArrowRight class="size-3.5" />
                                </Link>
                                <Link
                                    v-if="row.can_update"
                                    :href="route('renja-opd.edit', row.id)"
                                    class="inline-flex h-9 items-center justify-center rounded-md border px-2 text-muted-foreground transition-colors hover:bg-muted"
                                    aria-label="Edit Renja"
                                    title="Edit Renja"
                                >
                                    <Pencil class="size-4" />
                                </Link>
                                <button
                                    v-if="row.can_delete"
                                    type="button"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-red-200 px-2 text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/80 dark:text-red-400 dark:hover:bg-red-950/35"
                                    aria-label="Hapus Renja"
                                    title="Hapus Renja"
                                    @click="destroy(row)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </article>
                    <div v-if="items.data.length === 0" class="px-5 py-14 text-center sm:px-6">
                        <FileText class="mx-auto size-8 text-slate-300 dark:text-slate-600" />
                        <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada Renja OPD.</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Buat dokumen Renja untuk mulai menyusun sub kegiatan.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Halaman {{ items.current_page }} / {{ items.last_page }}</span>
                <div class="flex gap-2">
                    <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Berikutnya</Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </section>
    </div>
</template>
