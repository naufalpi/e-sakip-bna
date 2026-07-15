<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import ChevronDown from 'lucide-vue-next/dist/esm/icons/chevron-down.js';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import Edit3 from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Layers3 from 'lucide-vue-next/dist/esm/icons/layers.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import UserRound from 'lucide-vue-next/dist/esm/icons/user-round.js';
import X from 'lucide-vue-next/dist/esm/icons/x.js';
import { reactive, ref } from 'vue';

type OpdUnit = {
    id: number;
    opd_id: number;
    parent_id?: number | null;
    kode: string;
    nama: string;
    jenis_unit?: string | null;
    nama_pimpinan?: string | null;
    nip_pimpinan?: string | null;
    status: string;
    parent?: {
        id: number;
        kode: string;
        nama: string;
    } | null;
};

type Opd = {
    id: number;
    kode: string;
    nama: string;
    singkatan?: string | null;
    jenis?: string | null;
    email?: string | null;
    nama_kepala?: string | null;
    status: string;
    units_count: number;
    units: OpdUnit[];
    urusan_pemerintahan?: { kode: string; nama: string } | null;
};

type UnitForm = {
    opd_id: number | string;
    parent_id: number | string | null;
    kode: string;
    nama: string;
    jenis_unit: string;
    nama_pimpinan: string;
    nip_pimpinan: string;
    status: string;
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
    opds: Paginator<Opd>;
    totalUnits: number;
    filters: {
        search?: string;
        status?: string;
    };
    can: {
        create: boolean;
        manageUnits: boolean;
    };
    jenisUnitOptions: Array<{
        value: string;
        label: string;
    }>;
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const expandedOpds = reactive<Record<number, boolean>>({});
const activeUnitOpdId = ref<number | null>(null);
const editingUnitId = ref<number | null>(null);

const unitForm = useForm<UnitForm>({
    opd_id: '',
    parent_id: '',
    kode: '',
    nama: '',
    jenis_unit: '',
    nama_pimpinan: '',
    nip_pimpinan: '',
    status: 'active',
});

const applyFilters = () => {
    router.get(route('master.opd.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const isExpanded = (opd: Opd) => expandedOpds[opd.id] === true;

const toggleUnits = (opd: Opd) => {
    expandedOpds[opd.id] = !isExpanded(opd);
};

const resetUnitForm = () => {
    editingUnitId.value = null;
    activeUnitOpdId.value = null;
    unitForm.reset();
    unitForm.clearErrors();
    unitForm.status = 'active';
};

const openCreateUnit = (opd: Opd) => {
    resetUnitForm();
    expandedOpds[opd.id] = true;
    activeUnitOpdId.value = opd.id;
    unitForm.opd_id = opd.id;
};

const openEditUnit = (opd: Opd, unit: OpdUnit) => {
    resetUnitForm();
    expandedOpds[opd.id] = true;
    activeUnitOpdId.value = opd.id;
    editingUnitId.value = unit.id;
    unitForm.opd_id = opd.id;
    unitForm.parent_id = unit.parent_id ?? '';
    unitForm.kode = unit.kode;
    unitForm.nama = unit.nama;
    unitForm.jenis_unit = unit.jenis_unit ?? '';
    unitForm.nama_pimpinan = unit.nama_pimpinan ?? '';
    unitForm.nip_pimpinan = unit.nip_pimpinan ?? '';
    unitForm.status = unit.status;
};

const unitParentOptions = (opd: Opd) => opd.units.filter((unit) => unit.id !== editingUnitId.value);

const jenisUnitLabel = (value?: string | null) => props.jenisUnitOptions.find((option) => option.value === value)?.label ?? value ?? '-';

const activeUnitsCount = (opd: Opd) => opd.units.filter((unit) => unit.status === 'active').length;

const submitUnit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetUnitForm(),
    };

    if (editingUnitId.value) {
        unitForm.put(route('master.opd-units.update', editingUnitId.value), options);
        return;
    }

    unitForm.post(route('master.opd-units.store'), options);
};

const destroyOpd = async (opd: Opd) => {
    if (await confirmDelete(`Hapus OPD ${opd.nama}?`)) {
        router.delete(route('master.opd.destroy', opd.id), { preserveScroll: true });
    }
};

const destroyUnit = async (unit: OpdUnit) => {
    if (await confirmDelete(`Hapus unit ${unit.kode} - ${unit.nama}?`)) {
        router.delete(route('master.opd-units.destroy', unit.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (editingUnitId.value === unit.id) {
                    resetUnitForm();
                }
            },
        });
    }
};
</script>

<template>
    <Head title="Master OPD" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#00336C] dark:text-sky-300">Referensi Perangkat Daerah</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal text-foreground">Master OPD</h1>
            </div>

            <Link
                v-if="can.create"
                :href="route('master.opd.create')"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0A4C92]"
            >
                <Plus class="size-4" />
                Tambah OPD
            </Link>
        </div>

        <section class="grid gap-3 md:grid-cols-2">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total OPD</p>
                        <p class="mt-2 text-3xl font-semibold text-foreground">{{ opds.total }}</p>
                    </div>
                    <span class="inline-flex size-11 items-center justify-center rounded-xl bg-blue-50 text-[#00336C] dark:bg-sky-400/10 dark:text-sky-300">
                        <Building2 class="size-5" />
                    </span>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total Unit</p>
                        <p class="mt-2 text-3xl font-semibold text-foreground">{{ totalUnits }}</p>
                    </div>
                    <span class="inline-flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <Layers3 class="size-5" />
                    </span>
                </div>
            </div>
        </section>

        <form class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-sm md:flex-row md:items-center" @submit.prevent="applyFiltersNow">
            <div class="relative flex-1">
                <label for="opd-search" class="sr-only">Cari OPD</label>
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    id="opd-search"
                    v-model="filterForm.search"
                    type="search"
                    class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                    placeholder="Cari kode, nama, atau singkatan OPD"
                />
            </div>
            <label for="opd-status-filter" class="sr-only">Filter status OPD</label>
            <select
                id="opd-status-filter"
                v-model="filterForm.status"
                class="h-10 rounded-lg border bg-background px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
            >
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak aktif</option>
            </select>
            <button type="button" class="h-10 rounded-lg px-3 text-sm font-medium text-muted-foreground transition hover:bg-muted" @click="resetFilters">
                Reset
            </button>
        </form>

        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="flex flex-col gap-1 border-b p-4">
                <h2 class="text-base font-semibold text-foreground">Daftar OPD dan Unit</h2>
                <p class="text-sm text-muted-foreground">Klik tombol unit untuk melihat atau mengelola unit pada OPD tersebut.</p>
            </div>

            <div class="divide-y">
                <article v-for="opd in opds.data" :key="opd.id" class="bg-card">
                    <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                        <button type="button" class="group flex min-w-0 items-start gap-3 text-left" @click="toggleUnits(opd)">
                            <span
                                class="mt-1 inline-flex size-10 shrink-0 items-center justify-center rounded-xl border bg-background text-[#00336C] transition group-hover:border-[#00336C]/40 group-hover:bg-blue-50 dark:text-sky-300 dark:group-hover:bg-sky-400/10"
                            >
                                <ChevronDown v-if="isExpanded(opd)" class="size-5" />
                                <ChevronRight v-else class="size-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-muted px-2 py-1 text-xs font-semibold text-muted-foreground">{{ opd.kode }}</span>
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            opd.status === 'active'
                                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20'
                                                : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'
                                        "
                                    >
                                        {{ opd.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                    </span>
                                </span>
                                <span class="mt-2 block text-lg font-semibold leading-snug text-foreground">{{ opd.nama }}</span>
                                <span class="mt-1 block text-sm text-muted-foreground">
                                    {{ opd.singkatan || '-' }}<span v-if="opd.jenis"> · {{ opd.jenis }}</span>
                                </span>
                                <span class="mt-2 block text-sm text-muted-foreground">
                                    <span v-if="opd.urusan_pemerintahan">{{ opd.urusan_pemerintahan.kode }} - {{ opd.urusan_pemerintahan.nama }}</span>
                                    <span v-else>Urusan belum diisi</span>
                                </span>
                            </span>
                        </button>

                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            <div class="mr-1 rounded-lg border bg-background px-3 py-2 text-sm">
                                <span class="font-semibold text-foreground">{{ opd.units_count }}</span>
                                <span class="text-muted-foreground"> unit</span>
                                <span class="ml-2 text-xs text-muted-foreground">({{ activeUnitsCount(opd) }} aktif)</span>
                            </div>
                            <button
                                v-if="can.manageUnits"
                                type="button"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-semibold text-[#00336C] transition hover:border-[#00336C]/40 hover:bg-blue-50 dark:text-sky-300 dark:hover:bg-sky-400/10"
                                @click="openCreateUnit(opd)"
                            >
                                <Plus class="size-4" />
                                Unit
                            </button>
                            <Link
                                v-if="can.create"
                                :href="route('master.opd.edit', opd.id)"
                                class="inline-flex size-10 items-center justify-center rounded-lg border text-muted-foreground transition hover:border-[#00336C]/40 hover:bg-muted hover:text-[#00336C]"
                                :aria-label="`Edit OPD ${opd.nama}`"
                            >
                                <Edit3 class="size-4" />
                            </Link>
                            <button
                                v-if="can.create"
                                type="button"
                                class="inline-flex size-10 items-center justify-center rounded-lg border text-red-600 transition hover:border-red-200 hover:bg-red-50 dark:hover:bg-red-400/10"
                                :aria-label="`Hapus OPD ${opd.nama}`"
                                @click="destroyOpd(opd)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="isExpanded(opd)" class="border-t bg-muted/20 px-4 py-4">
                        <div
                            class="grid gap-4"
                            :class="can.manageUnits && activeUnitOpdId === opd.id ? '2xl:grid-cols-[minmax(760px,1fr)_520px]' : 'grid-cols-1'"
                        >
                            <div class="overflow-hidden rounded-xl border bg-card">
                                <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-foreground">Unit pada {{ opd.singkatan || opd.nama }}</h3>
                                        <p class="text-xs text-muted-foreground">UPTD, puskesmas, sekolah, labkes, atau unit induk OPD.</p>
                                    </div>
                                </div>

                                <div v-if="opd.units.length > 0" class="overflow-x-auto">
                                    <table class="w-full min-w-[1040px] text-left text-sm">
                                        <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                            <tr>
                                                <th class="px-4 py-3">Unit</th>
                                                <th class="px-4 py-3">Jenis</th>
                                                <th class="px-4 py-3">Induk</th>
                                                <th class="px-4 py-3">Pimpinan</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="w-28 px-4 py-3 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="unit in opd.units" :key="unit.id" class="border-b last:border-0">
                                                <td class="px-4 py-3 align-top">
                                                    <div class="font-semibold text-foreground">{{ unit.kode }}</div>
                                                    <div class="mt-1 max-w-sm text-muted-foreground">{{ unit.nama }}</div>
                                                </td>
                                                <td class="px-4 py-3 align-top text-muted-foreground">{{ jenisUnitLabel(unit.jenis_unit) }}</td>
                                                <td class="px-4 py-3 align-top text-muted-foreground">
                                                    <span v-if="unit.parent">{{ unit.parent.kode }} - {{ unit.parent.nama }}</span>
                                                    <span v-else>-</span>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <div class="flex items-start gap-2 text-muted-foreground">
                                                        <UserRound class="mt-0.5 size-4 shrink-0" />
                                                        <div>
                                                            <div class="text-foreground">{{ unit.nama_pimpinan || '-' }}</div>
                                                            <div class="text-xs">{{ unit.nip_pimpinan || '' }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <span
                                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                        :class="
                                                            unit.status === 'active'
                                                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20'
                                                                : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'
                                                        "
                                                    >
                                                        {{ unit.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                                    </span>
                                                </td>
                                                <td class="w-28 px-4 py-3 align-top text-right">
                                                    <div v-if="can.manageUnits" class="inline-flex rounded-lg border bg-background shadow-sm">
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-10 items-center justify-center text-muted-foreground transition hover:bg-muted hover:text-[#00336C]"
                                                            :aria-label="`Edit unit ${unit.nama}`"
                                                            @click="openEditUnit(opd, unit)"
                                                        >
                                                            <Edit3 class="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-10 items-center justify-center border-l text-red-600 transition hover:bg-red-50 dark:hover:bg-red-400/10"
                                                            :aria-label="`Hapus unit ${unit.nama}`"
                                                            @click="destroyUnit(unit)"
                                                        >
                                                            <Trash2 class="size-4" />
                                                        </button>
                                                    </div>
                                                    <span v-else class="text-xs text-muted-foreground">Read-only</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else class="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Belum ada unit. Tambahkan unit induk OPD, UPTD, puskesmas, sekolah, atau labkes bila diperlukan.
                                </div>
                            </div>

                            <form
                                v-if="can.manageUnits && activeUnitOpdId === opd.id"
                                class="rounded-xl border bg-card shadow-sm 2xl:sticky 2xl:top-4"
                                @submit.prevent="submitUnit"
                            >
                                <div class="flex items-start justify-between gap-3 border-b px-4 py-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-foreground">{{ editingUnitId ? 'Edit Unit OPD' : 'Tambah Unit OPD' }}</h3>
                                        <p class="text-xs text-muted-foreground">{{ opd.singkatan || opd.nama }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border text-muted-foreground transition hover:bg-muted"
                                        aria-label="Tutup form unit"
                                        @click="resetUnitForm"
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>

                                <div class="grid gap-4 p-4">
                                    <div class="grid gap-3 sm:grid-cols-[160px_minmax(0,1fr)]">
                                        <div>
                                            <label :for="`unit-kode-${opd.id}`" class="text-sm font-medium text-foreground">Kode</label>
                                            <input
                                                :id="`unit-kode-${opd.id}`"
                                                v-model="unitForm.kode"
                                                class="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                                                placeholder="Contoh: 1.02.01"
                                            />
                                            <p v-if="unitForm.errors.kode" class="mt-1 text-xs text-red-600">{{ unitForm.errors.kode }}</p>
                                        </div>

                                        <div>
                                            <label :for="`unit-jenis-${opd.id}`" class="text-sm font-medium text-foreground">Jenis Unit</label>
                                            <select
                                                :id="`unit-jenis-${opd.id}`"
                                                v-model="unitForm.jenis_unit"
                                                class="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                                            >
                                                <option value="">Pilih jenis</option>
                                                <option v-for="option in jenisUnitOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <p v-if="unitForm.errors.jenis_unit" class="mt-1 text-xs text-red-600">{{ unitForm.errors.jenis_unit }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label :for="`unit-nama-${opd.id}`" class="text-sm font-medium text-foreground">Nama Unit</label>
                                        <textarea
                                            :id="`unit-nama-${opd.id}`"
                                            v-model="unitForm.nama"
                                            rows="3"
                                            class="mt-1 w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                                            placeholder="Contoh: Puskesmas Banjarnegara 1"
                                        />
                                        <p v-if="unitForm.errors.nama" class="mt-1 text-xs text-red-600">{{ unitForm.errors.nama }}</p>
                                    </div>

                                    <div>
                                        <label :for="`unit-parent-${opd.id}`" class="text-sm font-medium text-foreground">Induk Unit</label>
                                        <select
                                            :id="`unit-parent-${opd.id}`"
                                            v-model="unitForm.parent_id"
                                            class="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                                        >
                                            <option value="">Tidak ada induk</option>
                                            <option v-for="unit in unitParentOptions(opd)" :key="unit.id" :value="unit.id">
                                                {{ unit.kode }} - {{ unit.nama }}
                                            </option>
                                        </select>
                                        <p v-if="unitForm.errors.parent_id" class="mt-1 text-xs text-red-600">{{ unitForm.errors.parent_id }}</p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label :for="`unit-pimpinan-${opd.id}`" class="text-sm font-medium text-foreground">Nama Pimpinan</label>
                                            <input
                                                :id="`unit-pimpinan-${opd.id}`"
                                                v-model="unitForm.nama_pimpinan"
                                                class="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                                                placeholder="Opsional"
                                            />
                                            <p v-if="unitForm.errors.nama_pimpinan" class="mt-1 text-xs text-red-600">
                                                {{ unitForm.errors.nama_pimpinan }}
                                            </p>
                                        </div>

                                        <div>
                                            <label :for="`unit-nip-${opd.id}`" class="text-sm font-medium text-foreground">NIP Pimpinan</label>
                                            <input
                                                :id="`unit-nip-${opd.id}`"
                                                v-model="unitForm.nip_pimpinan"
                                                class="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                                                placeholder="Opsional"
                                            />
                                            <p v-if="unitForm.errors.nip_pimpinan" class="mt-1 text-xs text-red-600">
                                                {{ unitForm.errors.nip_pimpinan }}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <label :for="`unit-status-${opd.id}`" class="text-sm font-medium text-foreground">Status</label>
                                        <select
                                            :id="`unit-status-${opd.id}`"
                                            v-model="unitForm.status"
                                            class="mt-1 h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/20"
                                        >
                                            <option value="active">Aktif</option>
                                            <option value="inactive">Tidak aktif</option>
                                        </select>
                                        <p v-if="unitForm.errors.status" class="mt-1 text-xs text-red-600">{{ unitForm.errors.status }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 border-t bg-muted/20 px-4 py-3">
                                    <button
                                        type="button"
                                        class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-semibold text-muted-foreground transition hover:bg-muted"
                                        @click="resetUnitForm"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white transition hover:bg-[#0A4C92] disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="unitForm.processing"
                                    >
                                        <CheckCircle2 class="size-4" />
                                        {{ unitForm.processing ? 'Menyimpan...' : editingUnitId ? 'Simpan Perubahan' : 'Simpan Unit' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </article>

                <div v-if="opds.data.length === 0" class="px-4 py-12 text-center">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                        <Building2 class="size-6" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-foreground">Belum ada data OPD</p>
                    <p class="mt-1 text-sm text-muted-foreground">Ubah filter atau tambahkan OPD baru.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ opds.from ?? 0 }}-{{ opds.to ?? 0 }} dari {{ opds.total }} data</span>
                <div class="flex flex-wrap items-center gap-2">
                    <Link v-if="opds.prev_page_url" :href="opds.prev_page_url" class="rounded-lg border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link>
                    <span v-else class="rounded-lg border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ opds.current_page }} / {{ opds.last_page }}</span>
                    <Link v-if="opds.next_page_url" :href="opds.next_page_url" class="rounded-lg border px-3 py-1.5 hover:bg-muted">Berikutnya</Link>
                    <span v-else class="rounded-lg border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </section>
    </div>
</template>
