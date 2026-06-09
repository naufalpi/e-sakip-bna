<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileSpreadsheet, Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type RpjmdRow = {
    id: number;
    judul: string;
    nomor_perda?: string | null;
    tahun_awal: number;
    tahun_akhir: number;
    status: string;
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

type ImportBatchRow = {
    id: number;
    status: string;
    original_filename: string;
    total_rows: number;
    created_at?: string | null;
    uploaded_by?: string | null;
};

const props = defineProps<{
    rpjmds: Paginator<RpjmdRow>;
    filters: {
        search?: string;
        status?: string;
    };
    can: {
        manage: boolean;
        import: boolean;
    };
    recentImports: ImportBatchRow[];
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('rpjmd.index'), filterForm, {
        preserveState: true,
        replace: true,
    });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const destroy = (rpjmd: RpjmdRow) => {
    if (confirm(`Hapus RPJMD ${rpjmd.tahun_awal}-${rpjmd.tahun_akhir}?`)) {
        router.delete(route('rpjmd.destroy', rpjmd.id));
    }
};

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
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-blue-100 text-blue-800',
        revision: 'bg-amber-100 text-amber-800',
        verified: 'bg-cyan-100 text-cyan-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
        locked: 'bg-zinc-200 text-zinc-800',
    })[status] ?? 'bg-slate-100 text-slate-700';

const importStatusClass = (status: string) =>
    ({
        previewed: 'bg-emerald-100 text-emerald-800',
        processing: 'bg-blue-100 text-blue-800',
        failed: 'bg-red-100 text-red-800',
        uploaded: 'bg-slate-100 text-slate-700',
    })[status] ?? 'bg-slate-100 text-slate-700';
</script>

<template>
    <Head title="RPJMD Kabupaten" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">RPJMD Kabupaten</h1>
                <p class="mt-1 text-sm text-muted-foreground">Cascading perencanaan kabupaten dari visi sampai program penanggung jawab OPD.</p>
            </div>
            <Link
                v-if="can.manage"
                :href="route('rpjmd.create')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
            >
                <Plus class="size-4" />
                Tambah RPJMD
            </Link>
        </div>

        <section v-if="can.import" class="grid gap-3 rounded-lg border bg-card p-3 lg:grid-cols-[1fr_360px]">
            <div class="flex flex-col justify-center">
                <h2 class="text-sm font-semibold">Import Excel Cascading RPJMD</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Upload file `.xlsx` atau `.csv` untuk membaca preview baris. Sistem menyimpan batch dan raw rows tanpa langsung membuat data
                    cascading.
                </p>
            </div>
            <Link
                :href="route('rpjmd.import.create')"
                class="inline-flex h-9 items-center justify-center gap-2 self-end rounded-md border px-3 text-sm font-medium hover:bg-muted"
            >
                <FileSpreadsheet class="size-4" />
                Buka Halaman Import
            </Link>
        </section>

        <section v-if="can.import && recentImports.length" class="rounded-lg border bg-card">
            <div class="border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Import Terakhir</h2>
            </div>
            <div class="divide-y">
                <Link
                    v-for="item in recentImports"
                    :key="item.id"
                    :href="route('rpjmd.import.show', item.id)"
                    class="grid gap-2 px-4 py-3 text-sm hover:bg-muted md:grid-cols-[1fr_140px_120px]"
                >
                    <div>
                        <div class="font-medium">{{ item.original_filename }}</div>
                        <div class="text-xs text-muted-foreground">{{ item.uploaded_by || '-' }} - {{ item.created_at || '-' }}</div>
                    </div>
                    <div class="text-muted-foreground">{{ item.total_rows }} baris</div>
                    <div>
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="importStatusClass(item.status)">
                            {{ item.status }}
                        </span>
                    </div>
                </Link>
            </div>
        </section>

        <form class="grid gap-3 rounded-lg border bg-card p-3 md:grid-cols-[1fr_220px_auto]" @submit.prevent="applyFiltersNow">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari judul RPJMD atau nomor perda"
                />
            </div>
            <select
                v-model="filterForm.status"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
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
            <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <div class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Dokumen</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rpjmd in rpjmds.data" :key="rpjmd.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ rpjmd.judul }}</div>
                                <div class="text-xs text-muted-foreground">{{ rpjmd.nomor_perda || 'Nomor perda belum diisi' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ rpjmd.tahun_awal }}-{{ rpjmd.tahun_akhir }}</div>
                                <div class="text-xs text-muted-foreground">{{ rpjmd.periode_tahun?.nama || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(rpjmd.status)">
                                    {{ statusLabel(rpjmd.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <Link :href="route('rpjmd.show', rpjmd.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted">Buka</Link>
                                    <Link
                                        v-if="can.manage"
                                        :href="route('rpjmd.edit', rpjmd.id)"
                                        class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        >Edit</Link
                                    >
                                    <button
                                        v-if="can.manage"
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="destroy(rpjmd)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rpjmds.data.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">Belum ada data RPJMD.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ rpjmds.from ?? 0 }}-{{ rpjmds.to ?? 0 }} dari {{ rpjmds.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="rpjmds.prev_page_url" :href="rpjmds.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ rpjmds.current_page }} / {{ rpjmds.last_page }}</span>
                    <Link v-if="rpjmds.next_page_url" :href="rpjmds.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </div>
    </div>
</template>
