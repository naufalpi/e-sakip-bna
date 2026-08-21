<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDocumentDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, CheckCircle2, FileClock, FileText, Plus, RefreshCcw, Search, Trash2 } from 'lucide-vue-next';
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
    rpjmd?: { id: number; judul: string; tahun_awal: number; tahun_akhir: number } | null;
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
    filters: { search?: string; status?: string; tahun?: string; rpjmd_id?: string; jenis_versi?: string };
    rpjmdOptions: Option[];
    periodeOptions: Option[];
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    tahun: props.filters.tahun ?? '',
    rpjmd_id: props.filters.rpjmd_id ?? '',
    jenis_versi: props.filters.jenis_versi ?? '',
});

const applyFilters = () => router.get(route('rkpd.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.tahun = '';
    filterForm.rpjmd_id = '';
    filterForm.jenis_versi = '';
    applyFiltersNow();
};

const destroy = async (row: Row) => {
    if (await confirmDocumentDelete(`Hapus RKPD ${row.tahun} - ${row.judul}?`)) {
        router.delete(route('rkpd.destroy', row.id));
    }
};

const initialCount = computed(() => props.items.data.filter((row) => row.jenis_versi === 'awal').length);
const establishedCount = computed(() => props.items.data.filter((row) => row.jenis_versi === 'ditetapkan').length);
const changeCount = computed(() => props.items.data.filter((row) => row.jenis_versi === 'perubahan').length);

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
        awal: 'border-sky-200 bg-sky-50 text-sky-800',
        ditetapkan: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        perubahan: 'border-amber-200 bg-amber-50 text-amber-800',
    })[version];
</script>

<template>
    <Head title="RKPD Kabupaten" />

    <div class="flex flex-col gap-5 p-4">
        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#edf7ff)] px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white px-3 py-1 text-xs font-semibold uppercase text-[#00336C]">
                            <CalendarDays class="size-3.5" />
                            Perencanaan Tahunan
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-normal">RKPD Kabupaten</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola RKPD Awal, dokumen yang telah ditetapkan, dan RKPD Perubahan dalam satu riwayat tahunan yang aman.
                        </p>
                    </div>

                    <Link
                        v-if="can.manage"
                        :href="route('rkpd.create')"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#002855]"
                    >
                        <Plus class="size-4" />
                        Tambah RKPD Awal
                    </Link>
                </div>
            </div>

            <div class="grid gap-3 p-4 md:grid-cols-3">
                <article class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sky-950">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase text-sky-700"><FileClock class="size-4" /> RKPD Awal</div>
                    <p class="mt-2 text-3xl font-semibold">{{ initialCount }}</p>
                    <p class="mt-1 text-sm text-sky-700">dokumen kerja pada halaman ini</p>
                </article>
                <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase text-emerald-700"><CheckCircle2 class="size-4" /> RKPD Ditetapkan</div>
                    <p class="mt-2 text-3xl font-semibold">{{ establishedCount }}</p>
                    <p class="mt-1 text-sm text-emerald-700">dokumen resmi pada halaman ini</p>
                </article>
                <article class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-950">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase text-amber-700"><RefreshCcw class="size-4" /> RKPD Perubahan</div>
                    <p class="mt-2 text-3xl font-semibold">{{ changeCount }}</p>
                    <p class="mt-1 text-sm text-amber-700">dokumen perubahan pada halaman ini</p>
                </article>
            </div>
        </section>

        <section class="rounded-xl border bg-card p-4 shadow-sm">
            <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Filter RKPD</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Pencarian berjalan otomatis.</p>
                </div>
                <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
            </div>

            <form class="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_170px_190px_140px_minmax(14rem,1fr)]" @submit.prevent="applyFiltersNow">
                <label class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        placeholder="Cari judul atau nomor dokumen"
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
                    <option value="awal">RKPD Awal</option>
                    <option value="ditetapkan">RKPD Ditetapkan</option>
                    <option value="perubahan">RKPD Perubahan</option>
                </select>
                <input
                    v-model="filterForm.tahun"
                    type="number"
                    class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                    placeholder="Tahun"
                />
                <select v-model="filterForm.rpjmd_id" class="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                    <option value="">Semua RPJMD</option>
                    <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <h2 class="text-base font-semibold">Daftar RKPD</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Tahun</th>
                            <th class="px-4 py-3">Dokumen RKPD</th>
                            <th class="px-4 py-3">Acuan RPJMD</th>
                            <th class="px-4 py-3">Isi</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in items.data" :key="row.id" class="border-b align-top last:border-0 hover:bg-muted/40">
                            <td class="px-4 py-4">
                                <div class="text-xl font-semibold">{{ row.tahun }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.periode_tahun?.nama || '-' }}</div>
                            </td>
                            <td class="min-w-80 px-4 py-4">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold" :class="versionClass(row.jenis_versi)">
                                        {{ row.version_label }}
                                    </span>
                                    <span v-if="row.is_active_version" class="text-[11px] font-semibold text-emerald-700">Versi aktif</span>
                                </div>
                                <div class="font-semibold">{{ row.judul }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">{{ row.nomor_dokumen || 'Nomor dokumen belum diisi' }}</div>
                            </td>
                            <td class="min-w-72 px-4 py-4 text-sm text-muted-foreground">
                                <span v-if="row.rpjmd">{{ row.rpjmd.tahun_awal }}-{{ row.rpjmd.tahun_akhir }} - {{ row.rpjmd.judul }}</span>
                                <span v-else>Belum terhubung RPJMD</span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ row.items_count }} baris</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(row.status)">
                                    {{ statusLabel(row.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="inline-flex overflow-hidden rounded-lg border bg-background">
                                    <Link :href="route('rkpd.show', row.id)" class="inline-flex h-10 items-center gap-2 px-3 text-sm font-medium hover:bg-muted">
                                        Buka
                                        <ArrowRight class="size-4" />
                                    </Link>
                                    <Link v-if="row.can_update" :href="route('rkpd.edit', row.id)" class="inline-flex h-10 items-center px-3 text-sm hover:bg-muted">
                                        Edit
                                    </Link>
                                    <button v-if="row.can_delete" type="button" class="inline-flex h-10 items-center px-3 text-red-600 hover:bg-red-50" @click="destroy(row)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center">
                                <FileText class="mx-auto size-10 text-muted-foreground" />
                                <p class="mt-3 font-semibold">Belum ada RKPD</p>
                                <p class="mt-1 text-sm text-muted-foreground">Tambahkan dokumen RKPD tahunan terlebih dahulu.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
