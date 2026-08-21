<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import BadgeCheck from 'lucide-vue-next/dist/esm/icons/badge-check.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import CircleUserRound from 'lucide-vue-next/dist/esm/icons/circle-user-round.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import UsersRound from 'lucide-vue-next/dist/esm/icons/users-round.js';
import { reactive } from 'vue';

type Option = { id?: number; value?: string; label: string };
type Placement = { id: number; jabatan?: { nama: string; level_label: string } | null };
type Row = {
    id: number;
    nama: string;
    nip?: string | null;
    pangkat_golongan?: string | null;
    jenis_pegawai_label: string;
    status: string;
    opd?: { nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode: string; nama: string } | null;
    current_placements: Placement[];
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
    filters: { search?: string; opd_id?: string; jenis_pegawai?: string; status?: string };
    opdOptions: Option[];
    jenisOptions: Option[];
    stats: { total: number; active: number; withPlacement: number };
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    opd_id: props.filters.opd_id ?? '',
    jenis_pegawai: props.filters.jenis_pegawai ?? '',
    status: props.filters.status ?? '',
});
const applyFilters = () => router.get(route('master.pegawai.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    Object.assign(filterForm, { search: '', opd_id: '', jenis_pegawai: '', status: '' });
    applyFiltersNow();
};
</script>

<template>
    <Head title="Pegawai OPD" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-800 text-white shadow-sm dark:bg-blue-600">
                    <UsersRound class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">Data OPD</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">Pegawai OPD</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Kelola pegawai dan jabatan aktif dalam satu tempat.</p>
                </div>
            </div>
            <Link
                v-if="can.manage"
                :href="route('master.pegawai.create')"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-900 dark:bg-blue-600 dark:hover:bg-blue-500"
            >
                <Plus class="size-4" /> Tambah Pegawai
            </Link>
        </header>

        <section class="grid overflow-hidden rounded-xl border bg-card sm:grid-cols-3">
            <div class="border-b p-4 sm:border-b-0 sm:border-r">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <UsersRound class="size-4" />Total
                </div>
                <p class="mt-2 text-2xl font-semibold">{{ stats.total }}</p>
            </div>
            <div class="border-b p-4 sm:border-b-0 sm:border-r">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <BadgeCheck class="size-4" />Aktif
                </div>
                <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-300">{{ stats.active }}</p>
            </div>
            <div class="p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <BriefcaseBusiness class="size-4" />Memiliki jabatan
                </div>
                <p class="mt-2 text-2xl font-semibold text-blue-800 dark:text-blue-300">{{ stats.withPlacement }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <form
                class="grid gap-3 border-b bg-muted/20 p-4 lg:grid-cols-[minmax(260px,1fr)_260px_180px_160px_auto]"
                @submit.prevent="applyFiltersNow"
            >
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm"
                        placeholder="Cari nama, NIP, atau jabatan"
                    />
                </div>
                <select v-model="filterForm.opd_id" class="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm">
                    <option value="">Semua perangkat daerah</option>
                    <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select v-model="filterForm.jenis_pegawai" class="h-10 rounded-lg border bg-background px-3 text-sm">
                    <option value="">Semua jenis</option>
                    <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm">
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <button type="button" class="h-10 rounded-lg px-3 text-sm font-medium text-muted-foreground hover:bg-muted" @click="resetFilters">
                    Reset
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="border-b bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3">Pegawai</th>
                            <th class="px-5 py-3">Perangkat daerah</th>
                            <th class="px-5 py-3">Jabatan saat ini</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in items.data" :key="item.id" class="transition-colors hover:bg-muted/25">
                            <td class="px-5 py-4 align-top">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200"
                                    >
                                        <CircleUserRound class="size-4" />
                                    </div>
                                    <div>
                                        <Link :href="route('master.pegawai.show', item.id)" class="font-semibold hover:text-blue-700">{{
                                            item.nama
                                        }}</Link>
                                        <p class="mt-0.5 text-xs text-muted-foreground">{{ item.nip ? `NIP ${item.nip}` : 'NIP belum diisi' }}</p>
                                        <p v-if="item.pangkat_golongan" class="text-xs text-muted-foreground">{{ item.pangkat_golongan }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <p class="font-medium">{{ item.opd?.singkatan || item.opd?.nama || 'Lingkup kabupaten' }}</p>
                                <p v-if="item.opd_unit" class="mt-1 text-xs text-muted-foreground">{{ item.opd_unit.nama }}</p>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div v-if="item.current_placements.length" class="space-y-1.5">
                                    <div v-for="placement in item.current_placements.slice(0, 2)" :key="placement.id">
                                        <p class="font-medium leading-5">{{ placement.jabatan?.nama }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ placement.jabatan?.level_label }}</p>
                                    </div>
                                    <p v-if="item.current_placements.length > 2" class="text-xs font-medium text-blue-700">
                                        +{{ item.current_placements.length - 2 }} jabatan lain
                                    </p>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Belum memiliki jabatan</span>
                            </td>
                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border px-2 py-1 text-[11px] font-semibold">{{
                                    item.jenis_pegawai_label
                                }}</span>
                                <p
                                    class="mt-2 text-[11px] font-medium"
                                    :class="item.status === 'active' ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'"
                                >
                                    {{ item.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-right align-top">
                                <Link
                                    :href="route('master.pegawai.show', item.id)"
                                    class="inline-flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                                    title="Buka detail"
                                    ><ChevronRight class="size-4"
                                /></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="items.data.length === 0" class="px-6 py-14 text-center">
                <CircleUserRound class="mx-auto size-9 text-muted-foreground/50" />
                <p class="mt-3 font-semibold">Belum ada pegawai yang sesuai</p>
                <p class="mt-1 text-sm text-muted-foreground">Ubah filter atau tambahkan pegawai baru.</p>
            </div>

            <footer class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>{{ items.from ?? 0 }}–{{ items.to ?? 0 }} dari {{ items.total }} pegawai</span>
                <div class="flex items-center gap-2">
                    <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-lg border px-3 py-1.5 hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="rounded-lg border px-3 py-1.5 opacity-40">Sebelumnya</span>
                    <span class="hidden sm:inline">{{ items.current_page }} / {{ items.last_page }}</span>
                    <Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-lg border px-3 py-1.5 hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="rounded-lg border px-3 py-1.5 opacity-40">Berikutnya</span>
                </div>
            </footer>
        </section>
    </div>
</template>
