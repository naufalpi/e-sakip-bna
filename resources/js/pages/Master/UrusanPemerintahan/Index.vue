<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, Folder, FolderOpen, Pencil, Plus, Search, Trash2, UserRoundCheck, X } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

type OpdOption = {
    id: number;
    kode: string;
    nama: string;
    singkatan?: string | null;
    label: string;
};

type OpdPengampu = OpdOption & {
    peran: string;
    is_utama: boolean;
};

type BidangUrusan = {
    id: number;
    urusan_pemerintahan_id: number;
    kode: string;
    nama: string;
    status: string;
    program_count: number;
    opd_pengampu_count: number;
    opd_pengampu: OpdPengampu[];
};

type Urusan = {
    id: number;
    kode: string;
    nama: string;
    deskripsi?: string | null;
    status: string;
    opds_count?: number | null;
    bidang_count: number;
    program_count: number;
    bidang_urusan: BidangUrusan[];
};

type Option = {
    id: number;
    label: string;
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

type BidangForm = {
    urusan_pemerintahan_id: number | string;
    kode: string;
    nama: string;
    status: string;
};

const props = defineProps<{
    items: Paginator<Urusan>;
    filters: { search?: string; status?: string };
    stats: { total_urusan: number; total_bidang: number };
    options: { urusan: Option[]; opds: OpdOption[] };
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const bidangForm = useForm<BidangForm>({
    urusan_pemerintahan_id: '',
    kode: '',
    nama: '',
    status: 'active',
});
const pengampuForm = useForm<{ opd_ids: number[] }>({ opd_ids: [] });
const editingBidang = reactive<{ id: number | null }>({ id: null });
const editingPengampu = reactive<{ bidangId: number | null; search: string }>({ bidangId: null, search: '' });
const collapsedUrusan = reactive<Record<number, boolean>>({});

const selectedUrusan = computed(() => props.options.urusan.find((option) => String(option.id) === String(bidangForm.urusan_pemerintahan_id)));
const canSubmitBidang = computed(
    () => props.can.manage && Boolean(bidangForm.urusan_pemerintahan_id) && bidangForm.kode.trim() && bidangForm.nama.trim(),
);
const filteredOpdOptions = computed(() => {
    const search = editingPengampu.search.trim().toLocaleLowerCase('id-ID');

    if (!search) {
        return props.options.opds;
    }

    return props.options.opds.filter((opd) =>
        [opd.kode, opd.nama, opd.singkatan, opd.label].some((value) =>
            String(value ?? '')
                .toLocaleLowerCase('id-ID')
                .includes(search),
        ),
    );
});

const applyFilters = () =>
    router.get(route('master.urusan-pemerintahan.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const isUrusanCollapsed = (id: number) => collapsedUrusan[id] !== false;

const toggleUrusan = (id: number) => {
    collapsedUrusan[id] = !isUrusanCollapsed(id);
};

const resetBidangForm = () => {
    editingBidang.id = null;
    bidangForm.reset();
    bidangForm.status = 'active';
    bidangForm.clearErrors();
};

const addBidangFor = (urusan: Urusan) => {
    resetBidangForm();
    bidangForm.urusan_pemerintahan_id = urusan.id;
};

const editBidang = (bidang: BidangUrusan) => {
    editingBidang.id = bidang.id;
    bidangForm.urusan_pemerintahan_id = bidang.urusan_pemerintahan_id;
    bidangForm.kode = bidang.kode;
    bidangForm.nama = bidang.nama;
    bidangForm.status = bidang.status;
    bidangForm.clearErrors();
};

const closePengampuPanel = () => {
    editingPengampu.bidangId = null;
    editingPengampu.search = '';
    pengampuForm.reset();
    pengampuForm.clearErrors();
};

const isPengampuPanelOpen = (bidang: BidangUrusan) => editingPengampu.bidangId === bidang.id;

const openPengampuPanel = (bidang: BidangUrusan) => {
    if (isPengampuPanelOpen(bidang)) {
        closePengampuPanel();
        return;
    }

    editingPengampu.bidangId = bidang.id;
    editingPengampu.search = '';
    pengampuForm.clearErrors();
    pengampuForm.opd_ids = bidang.opd_pengampu.map((opd) => opd.id);
};

const isOpdPengampuSelected = (id: number) => pengampuForm.opd_ids.includes(id);

const toggleOpdPengampu = (id: number) => {
    if (isOpdPengampuSelected(id)) {
        pengampuForm.opd_ids = pengampuForm.opd_ids.filter((opdId) => opdId !== id);
        return;
    }

    pengampuForm.opd_ids = [...pengampuForm.opd_ids, id];
};

const submitPengampu = (bidang: BidangUrusan) => {
    pengampuForm.put(route('master.urusan-pemerintahan.bidang-pengampu.update', bidang.id), {
        preserveScroll: true,
        onSuccess: closePengampuPanel,
    });
};

const pengampuShortLabel = (opd: OpdPengampu) => opd.singkatan || opd.nama;

const visiblePengampu = (bidang: BidangUrusan) => bidang.opd_pengampu.slice(0, 3);

const remainingPengampuCount = (bidang: BidangUrusan) => Math.max(bidang.opd_pengampu.length - visiblePengampu(bidang).length, 0);

const submitBidang = () => {
    if (!canSubmitBidang.value) {
        return;
    }

    if (editingBidang.id) {
        bidangForm.put(route('master.urusan-pemerintahan.bidang.update', editingBidang.id), {
            preserveScroll: true,
            onSuccess: resetBidangForm,
        });
        return;
    }

    bidangForm.post(route('master.urusan-pemerintahan.bidang.store'), {
        preserveScroll: true,
        onSuccess: resetBidangForm,
    });
};

const destroyUrusan = async (item: Urusan) => {
    if (await confirmDelete(`Hapus urusan ${item.kode} - ${item.nama}?`)) {
        router.delete(route('master.urusan-pemerintahan.destroy', item.id));
    }
};

const destroyBidang = async (item: BidangUrusan) => {
    if (await confirmDelete(`Hapus bidang urusan ${item.kode} - ${item.nama}?`)) {
        router.delete(route('master.urusan-pemerintahan.bidang.destroy', item.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Urusan Pemerintahan" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Urusan Pemerintahan</h1>
                <p class="mt-1 text-sm text-muted-foreground">Urusan dan bidang urusan dalam struktur induk-anak.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="grid min-w-0 grid-cols-2 gap-2 sm:w-[360px]">
                    <div class="rounded-xl border bg-card px-3 py-2 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Jumlah Urusan</p>
                        <p class="mt-1 text-2xl font-semibold text-[#00336C]">{{ stats.total_urusan }}</p>
                    </div>
                    <div class="rounded-xl border bg-card px-3 py-2 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Jumlah Bidang Urusan</p>
                        <p class="mt-1 text-2xl font-semibold text-[#00336C]">{{ stats.total_bidang }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.manage"
                        :href="route('master.urusan-pemerintahan.create')"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-[#00336C] px-3 text-sm font-medium text-white hover:bg-[#002958]"
                    >
                        <Plus class="size-4" />
                        Tambah Urusan
                    </Link>
                </div>
            </div>
        </div>

        <form class="flex flex-col gap-3 rounded-xl border bg-card p-3 md:flex-row md:items-center" @submit.prevent="applyFiltersNow">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-10 w-full rounded-xl border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/25"
                    placeholder="Cari urusan atau bidang"
                />
            </div>
            <select
                v-model="filterForm.status"
                class="h-10 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/25"
                aria-label="Filter status"
            >
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak aktif</option>
            </select>
            <button type="button" class="h-10 rounded-xl px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
            <section class="space-y-3">
                <article
                    v-for="urusan in items.data"
                    :key="urusan.id"
                    class="overflow-hidden rounded-xl border bg-card shadow-sm transition hover:border-[#00336C]/25"
                >
                    <div class="flex flex-col gap-3 border-b bg-slate-50/70 p-3 md:flex-row md:items-start md:justify-between">
                        <div class="flex min-w-0 gap-2.5">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#00336C]">
                                <FolderOpen class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-white px-2 py-0.5 text-xs font-semibold text-[#00336C] ring-1 ring-slate-200">
                                        {{ urusan.kode }}
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="urusan.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ urusan.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                    </span>
                                </div>
                                <h2 class="mt-1.5 text-base font-semibold leading-snug text-foreground">{{ urusan.nama }}</h2>
                                <p v-if="urusan.deskripsi" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ urusan.deskripsi }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5 text-xs text-muted-foreground">
                                    <span class="rounded-full bg-white px-2 py-0.5 ring-1 ring-slate-200">{{ urusan.bidang_count }} bidang</span>
                                    <span class="rounded-full bg-white px-2 py-0.5 ring-1 ring-slate-200">{{ urusan.program_count }} program</span>
                                    <span class="rounded-full bg-white px-2 py-0.5 ring-1 ring-slate-200">{{ urusan.opds_count ?? 0 }} OPD</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border bg-white px-2.5 text-sm font-medium text-slate-700 transition hover:border-[#00336C]/30 hover:bg-blue-50 hover:text-[#00336C]"
                                :aria-expanded="!isUrusanCollapsed(urusan.id)"
                                :aria-label="isUrusanCollapsed(urusan.id) ? `Tampilkan bidang ${urusan.nama}` : `Sembunyikan bidang ${urusan.nama}`"
                                @click="toggleUrusan(urusan.id)"
                            >
                                <ChevronDown
                                    class="size-4 transition-transform duration-200"
                                    :class="isUrusanCollapsed(urusan.id) ? '-rotate-90' : 'rotate-0'"
                                />
                                <span class="hidden sm:inline">{{ isUrusanCollapsed(urusan.id) ? 'Tampilkan' : 'Sembunyikan' }}</span>
                            </button>
                            <button
                                v-if="can.manage"
                                type="button"
                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border bg-white px-2.5 text-sm font-medium hover:bg-blue-50"
                                @click="addBidangFor(urusan)"
                            >
                                <Plus class="size-4" />
                                Bidang
                            </button>
                            <Link
                                v-if="can.manage"
                                :href="route('master.urusan-pemerintahan.edit', urusan.id)"
                                class="inline-flex h-8 items-center justify-center rounded-lg border bg-white px-2.5 text-sm font-medium hover:bg-muted"
                            >
                                <Pencil class="size-4" />
                            </Link>
                            <button
                                v-if="can.manage"
                                type="button"
                                class="inline-flex h-8 items-center justify-center rounded-lg border bg-white px-2.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                @click="destroyUrusan(urusan)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="-translate-y-1 opacity-0"
                    >
                        <div v-if="!isUrusanCollapsed(urusan.id)" class="p-2.5">
                            <div v-if="urusan.bidang_urusan.length" class="space-y-2">
                                <div
                                    v-for="bidang in urusan.bidang_urusan"
                                    :key="bidang.id"
                                    class="group relative ml-3 rounded-lg border bg-white p-2.5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] before:absolute before:-left-3 before:top-0 before:h-1/2 before:w-3 before:border-b before:border-l before:border-slate-200"
                                >
                                    <div class="flex flex-col gap-2.5 md:flex-row md:items-start md:justify-between">
                                        <div class="flex min-w-0 items-start gap-2.5">
                                            <div
                                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"
                                            >
                                                <Folder class="size-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-semibold text-slate-900">{{ bidang.kode }}</span>
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                        :class="
                                                            bidang.status === 'active'
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : 'bg-slate-100 text-slate-700'
                                                        "
                                                    >
                                                        {{ bidang.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                                    </span>
                                                </div>
                                                <p class="mt-0.5 text-sm font-medium leading-snug text-slate-700">{{ bidang.nama }}</p>
                                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                    <span>{{ bidang.program_count }} program</span>
                                                    <span class="text-slate-300">/</span>
                                                    <span>{{ bidang.opd_pengampu_count }} PD pengampu</span>
                                                    <template v-if="bidang.opd_pengampu.length">
                                                        <span
                                                            v-for="opd in visiblePengampu(bidang)"
                                                            :key="opd.id"
                                                            class="rounded-full bg-blue-50 px-2 py-0.5 font-medium text-[#00336C] ring-1 ring-blue-100"
                                                        >
                                                            {{ pengampuShortLabel(opd) }}
                                                        </span>
                                                        <span
                                                            v-if="remainingPengampuCount(bidang)"
                                                            class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600"
                                                        >
                                                            +{{ remainingPengampuCount(bidang) }}
                                                        </span>
                                                    </template>
                                                    <span v-else class="rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-700">
                                                        Belum ada pengampu
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="can.manage"
                                            class="flex shrink-0 overflow-hidden rounded-lg border bg-white opacity-100 md:opacity-0 md:transition md:group-hover:opacity-100"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex size-9 items-center justify-center text-slate-600 hover:bg-blue-50 hover:text-[#00336C]"
                                                title="Atur PD pengampu"
                                                @click="openPengampuPanel(bidang)"
                                            >
                                                <UserRoundCheck class="size-4" />
                                            </button>
                                            <span class="h-9 w-px bg-slate-200" />
                                            <button
                                                type="button"
                                                class="inline-flex size-9 items-center justify-center text-slate-600 hover:bg-blue-50 hover:text-[#00336C]"
                                                title="Edit bidang"
                                                @click="editBidang(bidang)"
                                            >
                                                <Pencil class="size-4" />
                                            </button>
                                            <span class="h-9 w-px bg-slate-200" />
                                            <button
                                                type="button"
                                                class="inline-flex size-9 items-center justify-center text-red-600 hover:bg-red-50 hover:text-red-700"
                                                title="Hapus bidang"
                                                @click="destroyBidang(bidang)"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <Transition
                                        enter-active-class="transition duration-200 ease-out"
                                        enter-from-class="-translate-y-1 opacity-0"
                                        enter-to-class="translate-y-0 opacity-100"
                                        leave-active-class="transition duration-150 ease-in"
                                        leave-from-class="translate-y-0 opacity-100"
                                        leave-to-class="-translate-y-1 opacity-0"
                                    >
                                        <div v-if="isPengampuPanelOpen(bidang)" class="mt-3 rounded-xl border bg-slate-50 p-3">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">PD Pengampu Bidang</p>
                                                    <p class="text-xs text-muted-foreground">{{ bidang.kode }} - {{ bidang.nama }}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="inline-flex size-8 items-center justify-center rounded-lg border bg-white text-slate-500 hover:bg-muted"
                                                    title="Tutup"
                                                    @click="closePengampuPanel"
                                                >
                                                    <X class="size-4" />
                                                </button>
                                            </div>
                                            <div class="relative mt-3">
                                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                                <input
                                                    v-model="editingPengampu.search"
                                                    type="search"
                                                    class="h-10 w-full rounded-xl border bg-white pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/20"
                                                    placeholder="Cari nama atau kode OPD"
                                                />
                                            </div>
                                            <div class="mt-3 max-h-64 overflow-y-auto rounded-xl border bg-white p-2">
                                                <label
                                                    v-for="opd in filteredOpdOptions"
                                                    :key="opd.id"
                                                    class="flex cursor-pointer items-start gap-3 rounded-lg px-2.5 py-2 text-sm transition hover:bg-blue-50"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        class="mt-1 rounded border-slate-300 text-[#00336C] focus:ring-[#00336C]/30"
                                                        :checked="isOpdPengampuSelected(opd.id)"
                                                        @change="toggleOpdPengampu(opd.id)"
                                                    />
                                                    <span class="min-w-0">
                                                        <span class="block font-semibold leading-5 text-slate-900">{{
                                                            opd.singkatan || opd.nama
                                                        }}</span>
                                                        <span class="block text-xs leading-4 text-muted-foreground"
                                                            >{{ opd.kode }} - {{ opd.nama }}</span
                                                        >
                                                    </span>
                                                </label>
                                                <p v-if="filteredOpdOptions.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                                    OPD tidak ditemukan.
                                                </p>
                                            </div>
                                            <InputError :message="pengampuForm.errors.opd_ids" class="mt-2" />
                                            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <span class="text-xs font-medium text-muted-foreground"
                                                    >{{ pengampuForm.opd_ids.length }} OPD dipilih</span
                                                >
                                                <button
                                                    type="button"
                                                    :disabled="pengampuForm.processing"
                                                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002958] disabled:cursor-not-allowed disabled:opacity-60"
                                                    @click="submitPengampu(bidang)"
                                                >
                                                    <CheckCircle2 class="size-4" />
                                                    Simpan Pengampu
                                                </button>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                            <button
                                v-else-if="can.manage"
                                type="button"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed py-5 text-sm font-medium text-muted-foreground hover:border-[#00336C]/30 hover:bg-blue-50/40 hover:text-[#00336C]"
                                @click="addBidangFor(urusan)"
                            >
                                <Plus class="size-4" />
                                Tambah bidang urusan pertama
                            </button>
                            <div v-else class="rounded-xl border border-dashed py-5 text-center text-sm text-muted-foreground">
                                Belum ada bidang urusan.
                            </div>
                        </div>
                    </Transition>
                    <div v-if="isUrusanCollapsed(urusan.id)" class="border-t bg-slate-50/40 px-3 py-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 transition hover:text-[#00336C]"
                            @click="toggleUrusan(urusan.id)"
                        >
                            <ChevronDown class="size-4 -rotate-90" />
                            {{ urusan.bidang_count }} bidang disembunyikan
                        </button>
                    </div>
                </article>

                <div v-if="items.data.length === 0" class="rounded-2xl border bg-card py-16 text-center">
                    <FolderOpen class="mx-auto size-10 text-muted-foreground" />
                    <p class="mt-3 text-sm font-medium text-muted-foreground">Belum ada urusan pemerintahan.</p>
                </div>

                <div
                    class="flex flex-col gap-3 rounded-xl border bg-card px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between"
                >
                    <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data</span>
                    <div class="flex flex-wrap gap-2">
                        <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">
                            Sebelumnya
                        </Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                        <span class="px-2 py-1.5">Halaman {{ items.current_page }} / {{ items.last_page }}</span>
                        <Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">
                            Berikutnya
                        </Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                    </div>
                </div>
            </section>

            <aside v-if="can.manage" class="h-fit rounded-2xl border bg-card shadow-sm xl:sticky xl:top-20">
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="font-semibold">{{ editingBidang.id ? 'Edit Bidang' : 'Tambah Bidang' }}</h2>
                        <p class="text-sm text-muted-foreground">{{ selectedUrusan?.label ?? 'Pilih urusan induk' }}</p>
                    </div>
                    <button
                        v-if="editingBidang.id || bidangForm.urusan_pemerintahan_id"
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                        @click="resetBidangForm"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <form class="grid gap-4 p-4" @submit.prevent="submitBidang">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Urusan Induk</label>
                        <RpjmdRichSelect
                            v-model="bidangForm.urusan_pemerintahan_id"
                            :options="options.urusan"
                            placeholder="Pilih urusan"
                            empty-text="Urusan belum tersedia"
                        />
                        <InputError :message="bidangForm.errors.urusan_pemerintahan_id" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="kode-bidang">Kode Bidang</label>
                        <input id="kode-bidang" v-model="bidangForm.kode" class="h-10 rounded-xl border bg-background px-3 text-sm font-semibold" />
                        <InputError :message="bidangForm.errors.kode" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="nama-bidang">Nama Bidang</label>
                        <textarea id="nama-bidang" v-model="bidangForm.nama" rows="3" class="rounded-xl border bg-background px-3 py-2 text-sm" />
                        <InputError :message="bidangForm.errors.nama" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="status-bidang">Status</label>
                        <select id="status-bidang" v-model="bidangForm.status" class="h-10 rounded-xl border bg-background px-3 text-sm">
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak aktif</option>
                        </select>
                    </div>
                    <button
                        type="submit"
                        :disabled="!canSubmitBidang || bidangForm.processing"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002958] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Plus v-if="!editingBidang.id" class="size-4" />
                        <Pencil v-else class="size-4" />
                        {{ editingBidang.id ? 'Simpan Perubahan' : 'Simpan Bidang' }}
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
