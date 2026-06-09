<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type Option = { id?: number; value?: string; label: string };
type Row = {
    id: number;
    tahun: number;
    tanggal_evaluasi?: string | null;
    status: string;
    nilai_akhir: string | number;
    predikat?: string | null;
    items_count: number;
    rekomendasi_count: number;
    opd?: { kode?: string; nama: string; singkatan?: string | null } | null;
    periode_tahun?: { nama: string; tahun: number } | null;
    evaluator?: { name: string } | null;
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
    evaluasi: Paginator<Row>;
    filters: { search?: string; status?: string; opd_id?: string; periode_tahun_id?: string; tahun?: string; predikat?: string };
    opdOptions: Option[];
    periodeOptions: Option[];
    statusOptions: Option[];
    predikatOptions: string[];
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    periode_tahun_id: props.filters.periode_tahun_id ?? '',
    tahun: props.filters.tahun ?? '',
    predikat: props.filters.predikat ?? '',
});

const applyFilters = () => router.get(route('evaluasi-sakip.index'), filterForm, { preserveState: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    filterForm.periode_tahun_id = '';
    filterForm.tahun = '';
    filterForm.predikat = '';
    applyFiltersNow();
};

const destroy = (row: Row) => {
    if (confirm(`Hapus evaluasi SAKIP ${row.opd?.singkatan || row.opd?.nama || ''} tahun ${row.tahun}?`)) {
        router.delete(route('evaluasi-sakip.destroy', row.id));
    }
};

const statusLabel = (status: string) => props.statusOptions.find((option) => option.value === status)?.label ?? status;
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
</script>

<template>
    <Head title="Evaluasi SAKIP" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Evaluasi SAKIP</h1>
                <p class="mt-1 text-sm text-muted-foreground">Evaluasi Inspektorat, LHE, rekomendasi, dan tindak lanjut OPD.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('evaluasi-sakip.kriteria')" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Kriteria Evaluasi</Link>
                <Link
                    v-if="can.manage"
                    :href="route('evaluasi-sakip.create')"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    <Plus class="size-4" />
                    Tambah Evaluasi
                </Link>
            </div>
        </div>

        <form
            class="grid gap-3 rounded-lg border bg-card p-3 xl:grid-cols-[1fr_170px_220px_190px_110px_120px_auto]"
            @submit.prevent="applyFiltersNow"
        >
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari OPD"
                />
            </div>
            <select
                v-model="filterForm.status"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua status</option>
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <select
                v-model="filterForm.opd_id"
                class="h-9 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua OPD</option>
                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
            <select
                v-model="filterForm.periode_tahun_id"
                class="h-9 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua periode</option>
                <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
            <input
                v-model="filterForm.tahun"
                type="number"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                placeholder="Tahun"
            />
            <select
                v-model="filterForm.predikat"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Predikat</option>
                <option v-for="predikat in predikatOptions" :key="predikat" :value="predikat">{{ predikat }}</option>
            </select>
            <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <div class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">OPD</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3">Nilai</th>
                            <th class="px-4 py-3">Progress</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in evaluasi.data" :key="row.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.opd?.singkatan || row.opd?.nama || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.opd?.kode || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.tahun }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.periode_tahun?.nama || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ row.nilai_akhir }}</div>
                                <div class="text-xs text-muted-foreground">Predikat {{ row.predikat || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ row.items_count }} kriteria</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                        >{{ row.rekomendasi_count }} rekomendasi</span
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{
                                    statusLabel(row.status)
                                }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <Link :href="route('evaluasi-sakip.show', row.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        >Buka</Link
                                    >
                                    <Link
                                        v-if="can.manage"
                                        :href="route('evaluasi-sakip.edit', row.id)"
                                        class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        >Edit</Link
                                    >
                                    <button
                                        v-if="can.manage"
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="destroy(row)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="evaluasi.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada data evaluasi SAKIP.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ evaluasi.from ?? 0 }}-{{ evaluasi.to ?? 0 }} dari {{ evaluasi.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="evaluasi.prev_page_url" :href="evaluasi.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ evaluasi.current_page }} / {{ evaluasi.last_page }}</span>
                    <Link v-if="evaluasi.next_page_url" :href="evaluasi.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </div>
    </div>
</template>
