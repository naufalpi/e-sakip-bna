<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmAction, confirmDelete, promptTextArea } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import BadgeCheck from 'lucide-vue-next/dist/esm/icons/badge-check.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import CircleUserRound from 'lucide-vue-next/dist/esm/icons/circle-user-round.js';
import CircleX from 'lucide-vue-next/dist/esm/icons/circle-x.js';
import Clock3 from 'lucide-vue-next/dist/esm/icons/clock-3.js';
import FileSpreadsheet from 'lucide-vue-next/dist/esm/icons/file-spreadsheet.js';
import Network from 'lucide-vue-next/dist/esm/icons/network.js';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import UserRoundCheck from 'lucide-vue-next/dist/esm/icons/user-round-check.js';
import UserRoundX from 'lucide-vue-next/dist/esm/icons/user-round-x.js';
import UsersRound from 'lucide-vue-next/dist/esm/icons/users-round.js';
import { computed, reactive } from 'vue';

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
    opd?: { id: number; nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode: string; nama: string } | null;
    parent?: { nama: string } | null;
    current_pejabat?: Pejabat | null;
    current_pejabat_count: number;
    verification_status: 'pending' | 'verified' | 'rejected';
    verification_label: string;
    verification_note?: string | null;
    can_edit: boolean;
    can_delete: boolean;
    can_verify: boolean;
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
    filters: { search?: string; opd_id?: string; level_jabatan?: string; status?: string; keterisian?: string; verification_status?: string };
    opdOptions: Option[];
    levelOptions: Option[];
    stats: { total: number; active: number; occupied: number; vacant: number; pending: number };
    can: { create: boolean; import: boolean; verify: boolean; manage_people: boolean; opd_scoped: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    opd_id: props.filters.opd_id ?? '',
    level_jabatan: props.filters.level_jabatan ?? '',
    status: props.filters.status ?? '',
    keterisian: props.filters.keterisian ?? '',
    verification_status: props.filters.verification_status ?? '',
});

const applyFilters = () =>
    router.get(route('master.jabatan-organisasi.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    Object.assign(filterForm, { search: '', opd_id: '', level_jabatan: '', status: '', keterisian: '', verification_status: '' });
    applyFiltersNow();
};

const approve = async (item: Jabatan) => {
    const confirmed = await confirmAction({
        title: 'Verifikasi jabatan?',
        text: `${item.nama} akan ditetapkan sebagai bagian struktur organisasi resmi.`,
        icon: 'question',
        confirmButtonText: 'Ya, verifikasi',
    });

    if (confirmed) {
        router.patch(
            route('master.jabatan-organisasi.verify', item.id),
            { verification_status: 'verified', verification_note: null },
            { preserveScroll: true },
        );
    }
};

const reject = async (item: Jabatan) => {
    const note = await promptTextArea({
        title: 'Kembalikan usulan jabatan',
        text: `Tuliskan bagian yang perlu diperbaiki pada ${item.nama}.`,
        inputLabel: 'Catatan perbaikan',
        inputPlaceholder: 'Contoh: sesuaikan nomenklatur jabatan dengan struktur organisasi terbaru.',
        confirmButtonText: 'Kirim untuk diperbaiki',
        minLength: 5,
    });

    if (note !== null) {
        router.patch(
            route('master.jabatan-organisasi.verify', item.id),
            { verification_status: 'rejected', verification_note: note },
            { preserveScroll: true },
        );
    }
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

const verificationClass = (status: Jabatan['verification_status']) =>
    ({
        verified: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200',
        pending: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200',
        rejected: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200',
    })[status];

const groupedItems = computed(() => {
    const groups = new Map<string, { key: string; label: string; name: string; items: Jabatan[] }>();

    props.items.data.forEach((item) => {
        const key = item.opd ? `opd-${item.opd.id}` : 'kabupaten';
        const group = groups.get(key) ?? {
            key,
            label: item.opd?.singkatan || item.opd?.nama || 'Pemerintah Kabupaten',
            name: item.opd?.nama || 'Struktur tingkat kabupaten',
            items: [],
        };

        group.items.push(item);
        groups.set(key, group);
    });

    return Array.from(groups.values());
});
</script>

<template>
    <Head :title="can.opd_scoped ? 'Jabatan di OPD' : 'Struktur Organisasi'" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <nav
            v-if="can.opd_scoped && can.manage_people"
            class="inline-flex w-fit rounded-lg border bg-card p-1 text-sm shadow-sm"
            aria-label="Kelola jabatan dan pegawai"
        >
            <Link
                :href="route('master.pegawai.index')"
                class="rounded-md px-4 py-2 font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
            >
                <UsersRound class="mr-1.5 inline size-4" /> Pegawai
            </Link>
            <Link :href="route('master.jabatan-organisasi.index')" class="rounded-md bg-blue-800 px-4 py-2 font-semibold text-white dark:bg-blue-600">
                <BriefcaseBusiness class="mr-1.5 inline size-4" /> Jabatan di OPD
            </Link>
        </nav>
        <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300">
                    <Network class="size-4" />
                    Struktur akuntabilitas
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-foreground md:text-3xl">
                    {{ can.opd_scoped ? 'Jabatan di OPD' : 'Struktur Organisasi' }}
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{
                        can.opd_scoped
                            ? 'Kelola kebutuhan jabatan OPD dan rantai atasannya. Usulan baru diverifikasi oleh Admin Kabupaten.'
                            : 'Kendalikan struktur resmi, rantai atasan–bawahan, dan verifikasi usulan jabatan dari OPD.'
                    }}
                </p>
            </div>
            <div v-if="can.create || can.import" class="flex flex-wrap gap-2">
                <Link
                    v-if="can.import"
                    :href="route('master.jabatan-organisasi.import.create')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-card px-4 text-sm font-semibold shadow-sm transition hover:bg-muted"
                >
                    <FileSpreadsheet class="size-4 text-blue-700 dark:text-blue-300" />
                    Import Excel
                </Link>
                <Link
                    v-if="can.create"
                    :href="route('master.jabatan-organisasi.create')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 dark:bg-blue-600 dark:hover:bg-blue-500"
                >
                    <Plus class="size-4" />
                    Tambah Jabatan
                </Link>
            </div>
        </header>

        <div
            v-if="can.verify && stats.pending > 0"
            class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm dark:border-amber-900 dark:bg-amber-950/20"
        >
            <div class="flex items-center gap-2 text-amber-900 dark:text-amber-100">
                <Clock3 class="size-4" /> <strong>{{ stats.pending }} usulan jabatan</strong> menunggu pemeriksaan.
            </div>
            <button
                type="button"
                class="text-xs font-semibold text-amber-800 hover:underline dark:text-amber-200"
                @click="
                    filterForm.verification_status = 'pending';
                    applyFiltersNow();
                "
            >
                Tampilkan usulan
            </button>
        </div>

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
            class="grid gap-3 rounded-xl border bg-card p-3 md:grid-cols-2 xl:grid-cols-[minmax(230px,1.5fr)_1.1fr_0.9fr_0.75fr_0.9fr_0.75fr_auto]"
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
            <select
                v-model="filterForm.verification_status"
                class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600"
            >
                <option value="">Semua verifikasi</option>
                <option value="verified">Terverifikasi</option>
                <option value="pending">Menunggu verifikasi</option>
                <option value="rejected">Perlu perbaikan</option>
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
                            <th class="w-[22%] px-4 py-3.5">Unit organisasi</th>
                            <th class="w-[20%] px-4 py-3.5">Atasan langsung</th>
                            <th class="w-[18%] px-4 py-3.5">Pegawai aktif</th>
                            <th class="w-[9%] px-4 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in groupedItems" :key="group.key">
                            <tr class="border-y border-blue-100 bg-blue-50/70 dark:border-blue-900/60 dark:bg-blue-950/25">
                                <td colspan="5" class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-800 text-white dark:bg-blue-600"
                                        >
                                            <Building2 class="size-4" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-blue-950 dark:text-blue-100">{{ group.label }}</p>
                                            <p v-if="group.name !== group.label" class="truncate text-xs text-blue-800/70 dark:text-blue-200/70">
                                                {{ group.name }}
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full border border-blue-200 bg-background/80 px-2.5 py-1 text-[11px] font-semibold tabular-nums text-blue-800 dark:border-blue-800 dark:text-blue-200"
                                        >
                                            {{ group.items.length }} jabatan
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="item in group.items"
                                :key="item.id"
                                class="group border-b align-top transition last:border-b-0 hover:bg-muted/35"
                            >
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
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                                    :class="verificationClass(item.verification_status)"
                                                >
                                                    <BadgeCheck v-if="item.verification_status === 'verified'" class="size-3" />
                                                    <Clock3 v-else-if="item.verification_status === 'pending'" class="size-3" />
                                                    <CircleX v-else class="size-3" />
                                                    {{ item.verification_label }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="item.verification_status === 'rejected' && item.verification_note"
                                                class="mt-2 line-clamp-2 text-xs leading-5 text-rose-700 dark:text-rose-300"
                                            >
                                                {{ item.verification_note }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p v-if="item.opd_unit" class="line-clamp-2 font-medium leading-5 text-foreground">
                                        {{ item.opd_unit.kode }} · {{ item.opd_unit.nama }}
                                    </p>
                                    <span v-else class="text-xs text-muted-foreground">Lingkup utama OPD</span>
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
                                            v-if="item.can_edit"
                                            :href="route('master.jabatan-organisasi.edit', item.id)"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border text-muted-foreground hover:bg-muted hover:text-foreground"
                                            title="Edit"
                                            ><Pencil class="size-3.5"
                                        /></Link>
                                        <button
                                            v-if="item.can_delete"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                                            title="Hapus"
                                            @click="destroy(item)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                        <button
                                            v-if="item.can_verify"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                                            title="Verifikasi jabatan"
                                            @click="approve(item)"
                                        >
                                            <BadgeCheck class="size-3.5" />
                                        </button>
                                        <button
                                            v-if="item.can_verify && item.verification_status === 'pending'"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                            title="Kembalikan untuk diperbaiki"
                                            @click="reject(item)"
                                        >
                                            <CircleX class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="lg:hidden">
                <section v-for="group in groupedItems" :key="group.key" class="border-b last:border-b-0">
                    <div class="flex items-center gap-3 border-b border-blue-100 bg-blue-50/70 px-4 py-3 dark:border-blue-900/60 dark:bg-blue-950/25">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-800 text-white dark:bg-blue-600">
                            <Building2 class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-blue-950 dark:text-blue-100">{{ group.label }}</p>
                            <p v-if="group.name !== group.label" class="truncate text-xs text-blue-800/70 dark:text-blue-200/70">{{ group.name }}</p>
                        </div>
                        <span class="text-xs font-semibold tabular-nums text-blue-800 dark:text-blue-200">{{ group.items.length }}</span>
                    </div>
                    <article v-for="item in group.items" :key="item.id" class="border-b p-4 last:border-b-0">
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
                            }}</span>
                            <span
                                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                :class="verificationClass(item.verification_status)"
                                >{{ item.verification_label }}</span
                            >
                        </div>
                        <div class="mt-3 border-l-2 border-border pl-3 text-xs leading-5">
                            <span class="text-muted-foreground">Pegawai: </span
                            ><span class="font-medium">{{ item.current_pejabat?.nama_pejabat || 'Belum terisi' }}</span
                            ><span v-if="item.current_pejabat_count > 1" class="text-blue-700"> +{{ item.current_pejabat_count - 1 }}</span>
                        </div>
                    </article>
                </section>
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
