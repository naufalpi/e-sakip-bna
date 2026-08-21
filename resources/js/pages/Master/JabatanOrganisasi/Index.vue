<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import CircleUserRound from 'lucide-vue-next/dist/esm/icons/circle-user-round.js';
import FileSpreadsheet from 'lucide-vue-next/dist/esm/icons/file-spreadsheet.js';
import Network from 'lucide-vue-next/dist/esm/icons/network.js';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import UserRoundCheck from 'lucide-vue-next/dist/esm/icons/user-round-check.js';
import UserRoundX from 'lucide-vue-next/dist/esm/icons/user-round-x.js';
import { reactive } from 'vue';

type Option = { id?: number; value?: string; label: string };
type Pejabat = {
    id: number;
    nama_pejabat: string;
    nip?: string | null;
    jenis_penugasan_label: string;
};
type Jabatan = {
    id: number;
    nama: string;
    level_jabatan: string;
    level_label: string;
    eselon?: string | null;
    status: string;
    children_count?: number | null;
    opd?: { nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode: string; nama: string } | null;
    parent?: { nama: string } | null;
    current_pejabat?: Pejabat | null;
    current_pejabat_count: number;
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
    items: Paginator<Jabatan>;
    filters: { search?: string; opd_id?: string; level_jabatan?: string; status?: string; keterisian?: string };
    opdOptions: Option[];
    levelOptions: Option[];
    stats: { total: number; active: number; occupied: number; vacant: number };
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    opd_id: props.filters.opd_id ?? '',
    level_jabatan: props.filters.level_jabatan ?? '',
    status: props.filters.status ?? '',
    keterisian: props.filters.keterisian ?? '',
});

const applyFilters = () =>
    router.get(route('master.jabatan-organisasi.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    Object.assign(filterForm, { search: '', opd_id: '', level_jabatan: '', status: '', keterisian: '' });
    applyFiltersNow();
};

const destroy = async (item: Jabatan) => {
    if (await confirmDelete(`Hapus jabatan ${item.nama}? Jabatan yang memiliki turunan atau riwayat pejabat tidak dapat dihapus.`)) {
        router.delete(route('master.jabatan-organisasi.destroy', item.id));
    }
};

const levelClass = (level: string) =>
    ({
        kepala_daerah: 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
        jpt_pratama: 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
        administrator: 'border-cyan-300 bg-cyan-50 text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200',
        pengawas: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
        fungsional: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
        pelaksana: 'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
    })[level] ?? 'border-border bg-muted text-foreground';
</script>

<template>
    <Head title="Struktur Jabatan" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300">
                    <Network class="size-4" />
                    Struktur akuntabilitas
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-foreground md:text-3xl">Struktur Jabatan</h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Susun posisi dan rantai atasan–bawahan sebagai kerangka penempatan pegawai dan Perjanjian Kinerja.
                </p>
            </div>
            <div v-if="can.manage" class="flex flex-wrap gap-2">
                <Link
                    :href="route('master.jabatan-organisasi.import.create')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-card px-4 text-sm font-semibold shadow-sm transition hover:bg-muted"
                >
                    <FileSpreadsheet class="size-4 text-blue-700 dark:text-blue-300" />
                    Import Excel
                </Link>
                <Link
                    :href="route('master.jabatan-organisasi.create')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 dark:bg-blue-600 dark:hover:bg-blue-500"
                >
                    <Plus class="size-4" />
                    Tambah Jabatan
                </Link>
            </div>
        </header>

        <section class="grid gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-card p-4">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-semibold uppercase tracking-wider">Total jabatan</span><BriefcaseBusiness class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums">{{ stats.total }}</p>
            </div>
            <div class="bg-card p-4">
                <div class="flex items-center justify-between text-muted-foreground">
                    <span class="text-xs font-semibold uppercase tracking-wider">Aktif</span><Building2 class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums">{{ stats.active }}</p>
            </div>
            <div class="bg-card p-4">
                <div class="flex items-center justify-between text-emerald-700 dark:text-emerald-300">
                    <span class="text-xs font-semibold uppercase tracking-wider">Terisi</span><UserRoundCheck class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums">{{ stats.occupied }}</p>
            </div>
            <div class="bg-card p-4">
                <div class="flex items-center justify-between text-amber-700 dark:text-amber-300">
                    <span class="text-xs font-semibold uppercase tracking-wider">Kosong</span><UserRoundX class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-semibold tabular-nums">{{ stats.vacant }}</p>
            </div>
        </section>

        <form
            class="grid gap-3 rounded-xl border bg-card p-3 md:grid-cols-2 xl:grid-cols-[minmax(240px,1.6fr)_1.15fr_1fr_0.75fr_0.75fr_auto]"
            @submit.prevent="applyFiltersNow"
        >
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
                    placeholder="Cari jabatan atau nama pegawai"
                />
            </div>
            <select
                v-model="filterForm.opd_id"
                class="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
            >
                <option value="">Semua perangkat daerah</option>
                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
            <select
                v-model="filterForm.level_jabatan"
                class="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
            >
                <option value="">Semua level</option>
                <option v-for="option in levelOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <select v-model="filterForm.keterisian" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600">
                <option value="">Semua isian</option>
                <option value="terisi">Terisi</option>
                <option value="kosong">Kosong</option>
            </select>
            <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
            <button
                type="button"
                class="h-10 rounded-lg px-3 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                @click="resetFilters"
            >
                Reset
            </button>
        </form>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="border-b bg-muted/50 text-[11px] uppercase tracking-[0.12em] text-muted-foreground">
                        <tr>
                            <th class="w-[31%] px-5 py-3.5">Jabatan</th>
                            <th class="w-[22%] px-4 py-3.5">Perangkat daerah / unit</th>
                            <th class="w-[20%] px-4 py-3.5">Atasan langsung</th>
                            <th class="w-[18%] px-4 py-3.5">Pegawai aktif</th>
                            <th class="w-[9%] px-4 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in items.data" :key="item.id" class="group align-top transition hover:bg-muted/35">
                            <td class="px-5 py-4">
                                <div class="flex gap-3">
                                    <div
                                        class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg border bg-background text-blue-800 dark:text-blue-300"
                                    >
                                        <BriefcaseBusiness class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="route('master.jabatan-organisasi.show', item.id)"
                                            class="font-semibold leading-5 text-foreground hover:text-blue-700 dark:hover:text-blue-300"
                                            >{{ item.nama }}</Link
                                        >
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                                :class="levelClass(item.level_jabatan)"
                                                >{{ item.level_label }}</span
                                            >
                                            <span v-if="item.eselon" class="text-[11px] text-muted-foreground">{{
                                                item.eselon.replace('_', '.').toUpperCase()
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">{{ item.opd?.singkatan || item.opd?.nama || 'Pemerintah Kabupaten' }}</p>
                                <p v-if="item.opd_unit" class="mt-1 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                    {{ item.opd_unit.kode }} · {{ item.opd_unit.nama }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
                                <template v-if="item.parent">
                                    <p class="line-clamp-2 font-medium leading-5">{{ item.parent.nama }}</p>
                                </template>
                                <span v-else class="text-xs text-muted-foreground">Puncak hierarki</span>
                            </td>
                            <td class="px-4 py-4">
                                <div v-if="item.current_pejabat" class="flex gap-2.5">
                                    <CircleUserRound class="mt-0.5 size-4 shrink-0 text-emerald-700 dark:text-emerald-300" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ item.current_pejabat.nama_pejabat }}</p>
                                        <p class="mt-1 truncate text-[11px] text-muted-foreground">
                                            {{ item.current_pejabat.jenis_penugasan_label
                                            }}<template v-if="item.current_pejabat.nip"> · {{ item.current_pejabat.nip }}</template>
                                        </p>
                                        <p
                                            v-if="item.current_pejabat_count > 1"
                                            class="mt-1 text-[11px] font-semibold text-blue-700 dark:text-blue-300"
                                        >
                                            +{{ item.current_pejabat_count - 1 }} pegawai lainnya
                                        </p>
                                    </div>
                                </div>
                                <span
                                    v-else
                                    class="inline-flex rounded-full border border-dashed border-amber-400/70 px-2 py-1 text-[11px] font-medium text-amber-700 dark:text-amber-300"
                                    >Belum terisi</span
                                >
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <Link
                                        :href="route('master.jabatan-organisasi.show', item.id)"
                                        class="inline-flex size-8 items-center justify-center rounded-lg border text-muted-foreground hover:bg-muted hover:text-foreground"
                                        title="Lihat detail"
                                        ><ChevronRight class="size-4"
                                    /></Link>
                                    <Link
                                        v-if="can.manage"
                                        :href="route('master.jabatan-organisasi.edit', item.id)"
                                        class="inline-flex size-8 items-center justify-center rounded-lg border text-muted-foreground hover:bg-muted hover:text-foreground"
                                        title="Edit"
                                        ><Pencil class="size-3.5"
                                    /></Link>
                                    <button
                                        v-if="can.manage"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg border text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                                        title="Hapus"
                                        @click="destroy(item)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="divide-y lg:hidden">
                <article v-for="item in items.data" :key="item.id" class="p-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-background text-blue-800 dark:text-blue-300"
                        >
                            <BriefcaseBusiness class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <Link :href="route('master.jabatan-organisasi.show', item.id)" class="font-semibold leading-5">{{ item.nama }}</Link>
                        </div>
                        <Link
                            :href="route('master.jabatan-organisasi.show', item.id)"
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border"
                            ><ChevronRight class="size-4"
                        /></Link>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold" :class="levelClass(item.level_jabatan)">{{
                            item.level_label
                        }}</span
                        ><span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium">{{
                            item.opd?.singkatan || item.opd?.nama || 'Pemkab'
                        }}</span>
                    </div>
                    <div class="mt-3 border-l-2 border-border pl-3 text-xs leading-5">
                        <span class="text-muted-foreground">Pegawai: </span
                        ><span class="font-medium">{{ item.current_pejabat?.nama_pejabat || 'Belum terisi' }}</span
                        ><span v-if="item.current_pejabat_count > 1" class="text-blue-700"> +{{ item.current_pejabat_count - 1 }}</span>
                    </div>
                </article>
            </div>

            <div v-if="items.data.length === 0" class="flex flex-col items-center px-6 py-14 text-center">
                <div class="flex size-11 items-center justify-center rounded-full bg-muted"><Network class="size-5 text-muted-foreground" /></div>
                <p class="mt-4 font-semibold">Belum ada jabatan yang sesuai</p>
                <p class="mt-1 text-sm text-muted-foreground">Ubah filter atau tambahkan struktur jabatan baru.</p>
            </div>

            <footer class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>{{ items.from ?? 0 }}–{{ items.to ?? 0 }} dari {{ items.total }} jabatan</span>
                <div class="flex items-center gap-2">
                    <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-lg border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link
                    ><span v-else class="rounded-lg border px-3 py-1.5 opacity-40">Sebelumnya</span>
                    <span class="hidden px-1 sm:inline">{{ items.current_page }} / {{ items.last_page }}</span>
                    <Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-lg border px-3 py-1.5 hover:bg-muted">Berikutnya</Link
                    ><span v-else class="rounded-lg border px-3 py-1.5 opacity-40">Berikutnya</span>
                </div>
            </footer>
        </section>
    </div>
</template>
