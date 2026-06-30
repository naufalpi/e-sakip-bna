<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import ArrowRight from 'lucide-vue-next/dist/esm/icons/arrow-right.js';
import ClipboardList from 'lucide-vue-next/dist/esm/icons/clipboard-list.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import { reactive } from 'vue';

type Option = { id?: number; value?: string; label: string };
type Row = {
    id: number;
    jenis: string;
    judul: string;
    nomor_dokumen?: string | null;
    status: string;
    original_filename: string;
    mime_type?: string | null;
    file_size: number;
    file_hash: string;
    created_at?: string | null;
    opd?: { kode?: string; nama: string; singkatan?: string | null } | null;
    periode_tahun?: { tahun: number; nama: string } | null;
    uploaded_by?: { name: string } | null;
    relations_count: number;
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
    dokumen: Paginator<Row>;
    filters: { search?: string; jenis?: string; status?: string; opd_id?: string; periode_tahun_id?: string };
    jenisOptions: Option[];
    statusOptions: Option[];
    opdOptions: Option[];
    periodeOptions: Option[];
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    jenis: props.filters.jenis ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    periode_tahun_id: props.filters.periode_tahun_id ?? '',
});

const applyFilters = () => router.get(route('dokumen.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.jenis = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    filterForm.periode_tahun_id = '';
    applyFiltersNow();
};

const destroy = async (row: Row) => {
    if (await confirmDelete(`Hapus dokumen ${row.judul}?`)) {
        router.delete(route('dokumen.destroy', row.id));
    }
};

const statusLabel = (status: string) => props.statusOptions.find((option) => option.value === status)?.label ?? status;
const jenisLabel = (jenis: string) => props.jenisOptions.find((option) => option.value === jenis)?.label ?? jenis;
const formatSize = (size: number) => {
    if (size >= 1024 * 1024) return `${(size / 1024 / 1024).toFixed(2)} MB`;
    if (size >= 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${size} B`;
};

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
    <Head title="Dokumen" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Dokumen</h1>
                <p class="mt-1 text-sm text-muted-foreground">Arsip dokumen dan bukti dukung yang tersimpan melalui Laravel Storage privat.</p>
            </div>
            <Link
                v-if="can.manage"
                :href="route('dokumen.create')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
            >
                <Plus class="size-4" />
                Unggah Dokumen
            </Link>
        </div>

        <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white text-emerald-800 shadow-sm">
                        <ClipboardList class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-semibold">Cek kelengkapan dokumen</h2>
                        <p class="mt-1 text-sm leading-6 text-emerald-900/80">
                            Lihat daftar Perencanaan, Pengukuran, Pelaporan, dan Evaluasi yang sudah siap publik atau masih perlu diunggah.
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('dokumen-publik.index')"
                    class="inline-flex h-9 shrink-0 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                    prefetch="hover"
                    cache-for="2m"
                >
                    Buka Kelengkapan
                    <ArrowRight class="size-4" />
                </Link>
            </div>
        </section>

        <form class="grid gap-3 rounded-lg border bg-card p-3 lg:grid-cols-[1fr_180px_170px_220px_190px_auto]" @submit.prevent="applyFiltersNow">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari judul, nomor, atau nama file"
                />
            </div>
            <select
                v-model="filterForm.jenis"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua jenis</option>
                <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
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
            <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <div class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Dokumen</th>
                            <th class="px-4 py-3">OPD</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Relasi</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in dokumen.data" :key="row.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.judul }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ jenisLabel(row.jenis) }} - {{ row.nomor_dokumen || 'Nomor belum diisi' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.opd?.singkatan || row.opd?.nama || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.periode_tahun?.nama || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="max-w-72 truncate font-medium">{{ row.original_filename }}</div>
                                <div class="text-xs text-muted-foreground">{{ formatSize(row.file_size) }} - {{ row.mime_type || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ row.relations_count }} relasi</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{
                                    statusLabel(row.status)
                                }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <Link :href="route('dokumen.show', row.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted">Buka</Link>
                                    <Link
                                        v-if="can.manage"
                                        :href="route('dokumen.edit', row.id)"
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
                        <tr v-if="dokumen.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada dokumen.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ dokumen.from ?? 0 }}-{{ dokumen.to ?? 0 }} dari {{ dokumen.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="dokumen.prev_page_url" :href="dokumen.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ dokumen.current_page }} / {{ dokumen.last_page }}</span>
                    <Link v-if="dokumen.next_page_url" :href="dokumen.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </div>
    </div>
</template>
