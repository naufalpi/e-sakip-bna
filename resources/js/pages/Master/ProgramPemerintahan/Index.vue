<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, ClipboardList, FileSpreadsheet, FolderTree, Layers3, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

type Option = {
    id: number;
    label: string;
    description?: string | null;
    group?: string | null;
};

type ReferenceType = 'program' | 'kegiatan' | 'sub_kegiatan';

type ReferenceItem = {
    id: number;
    type: ReferenceType;
    level: string;
    kode: string;
    nama: string;
    status: string;
    parent_id: number;
    parent_label: string;
    bidang_label?: string | null;
    urusan_label?: string | null;
    children_count: number;
    children_label: string;
    drilldown_url?: string | null;
};

type ContextItem = {
    id: number;
    kode: string;
    nama: string;
    label: string;
    bidang_label?: string | null;
    urusan_label?: string | null;
    program_label?: string | null;
    children_count: number;
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

type ReferenceForm = {
    type: ReferenceType;
    bidang_urusan_id: number | string;
    program_pemerintahan_id: number | string;
    kegiatan_pemerintahan_id: number | string;
    kode: string;
    nama: string;
    status: string;
    redirect_to: string;
};

type BulkForm = {
    type: ReferenceType;
    bidang_urusan_id: number | string;
    program_pemerintahan_id: number | string;
    kegiatan_pemerintahan_id: number | string;
    rows: string;
    status: string;
    redirect_to: string;
};

const props = defineProps<{
    items: Paginator<ReferenceItem>;
    filters: { search?: string; status?: string; bidang_urusan_id?: number | string };
    level: ReferenceType;
    context: {
        program?: ContextItem | null;
        kegiatan?: ContextItem | null;
    };
    summary: {
        program_count: number;
        kegiatan_count: number;
        sub_kegiatan_count: number;
    };
    options: {
        bidang: Option[];
        program: Option[];
        kegiatan: Option[];
    };
    can: { manage: boolean };
}>();

const levelMeta: Record<
    ReferenceType,
    {
        title: string;
        noun: string;
        parent: string;
        tableTitle: string;
        empty: string;
        childColumn: string;
        bulkPlaceholder: string;
    }
> = {
    program: {
        title: 'Program Pemerintahan',
        noun: 'Program',
        parent: 'Bidang Urusan',
        tableTitle: 'Daftar Program',
        empty: 'Belum ada program pada filter ini.',
        childColumn: 'Kegiatan',
        bulkPlaceholder: '2.16.03.2.01 | Program Informasi dan Komunikasi Publik\n2.16.03.2.02 | Program Aplikasi Informatika',
    },
    kegiatan: {
        title: 'Kegiatan Program',
        noun: 'Kegiatan',
        parent: 'Program',
        tableTitle: 'Daftar Kegiatan',
        empty: 'Belum ada kegiatan pada program ini.',
        childColumn: 'Sub Kegiatan',
        bulkPlaceholder: '2.16.03.2.01.0001 | Pengelolaan Informasi dan Komunikasi Publik\n2.16.03.2.01.0002 | Pengelolaan Media Komunikasi Publik',
    },
    sub_kegiatan: {
        title: 'Sub Kegiatan',
        noun: 'Sub Kegiatan',
        parent: 'Kegiatan',
        tableTitle: 'Daftar Sub Kegiatan',
        empty: 'Belum ada sub kegiatan pada kegiatan ini.',
        childColumn: 'Turunan',
        bulkPlaceholder: '2.16.03.2.01.0001.01 | Pelayanan Informasi Publik\n2.16.03.2.01.0001.02 | Diseminasi Informasi Publik',
    },
};

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    bidang_urusan_id: props.filters.bidang_urusan_id ?? '',
});

const singleForm = useForm<ReferenceForm>({
    type: props.level,
    bidang_urusan_id: '',
    program_pemerintahan_id: '',
    kegiatan_pemerintahan_id: '',
    kode: '',
    nama: '',
    status: 'active',
    redirect_to: '',
});

const bulkForm = useForm<BulkForm>({
    type: props.level,
    bidang_urusan_id: '',
    program_pemerintahan_id: '',
    kegiatan_pemerintahan_id: '',
    rows: '',
    status: 'active',
    redirect_to: '',
});

const editing = reactive<{ type: ReferenceType | null; id: number | null }>({ type: null, id: null });
const activePanel = ref<'single' | 'bulk' | null>(null);

const meta = computed(() => levelMeta[props.level]);
const pageTitle = computed(() => {
    if (props.level === 'kegiatan' && props.context.program) {
        return `Kegiatan - ${props.context.program.kode}`;
    }

    if (props.level === 'sub_kegiatan' && props.context.kegiatan) {
        return `Sub Kegiatan - ${props.context.kegiatan.kode}`;
    }

    return meta.value.title;
});
const pageDescription = computed(() => {
    if (props.level === 'kegiatan' && props.context.program) {
        return props.context.program.nama;
    }

    if (props.level === 'sub_kegiatan' && props.context.kegiatan) {
        return props.context.kegiatan.nama;
    }

    return 'Telusuri program, kegiatan, dan sub kegiatan dari tabel berjenjang.';
});

const redirectTo = computed(() => {
    if (typeof window === 'undefined') {
        return route('master.program-pemerintahan.index');
    }

    return `${window.location.pathname}${window.location.search}`;
});

const queryParams = () => {
    const params: Record<string, number | string> = { level: props.level };

    if (props.level === 'kegiatan' && props.context.program?.id) {
        params.program_id = props.context.program.id;
    }

    if (props.level === 'sub_kegiatan' && props.context.kegiatan?.id) {
        params.kegiatan_id = props.context.kegiatan.id;
    }

    if (filterForm.search) {
        params.search = filterForm.search;
    }

    if (filterForm.status) {
        params.status = filterForm.status;
    }

    if (props.level === 'program' && filterForm.bidang_urusan_id) {
        params.bidang_urusan_id = filterForm.bidang_urusan_id;
    }

    return params;
};

const backToProgramsHref = computed(() => route('master.program-pemerintahan.index'));
const backToKegiatanHref = computed(() =>
    props.context.program
        ? route('master.program-pemerintahan.index', {
              level: 'kegiatan',
              program_id: props.context.program.id,
          })
        : route('master.program-pemerintahan.index'),
);

const applyFilters = () =>
    router.get(route('master.program-pemerintahan.index'), queryParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.bidang_urusan_id = '';
    applyFiltersNow();
};

const clearParentFields = (form: ReferenceForm | BulkForm) => {
    form.bidang_urusan_id = '';
    form.program_pemerintahan_id = '';
    form.kegiatan_pemerintahan_id = '';
};

const syncContextToForm = (form: ReferenceForm | BulkForm) => {
    form.type = props.level;
    form.redirect_to = redirectTo.value;
    clearParentFields(form);

    if (props.level === 'kegiatan' && props.context.program?.id) {
        form.program_pemerintahan_id = props.context.program.id;
    }

    if (props.level === 'sub_kegiatan' && props.context.kegiatan?.id) {
        form.kegiatan_pemerintahan_id = props.context.kegiatan.id;
    }
};

const resetSingleForm = () => {
    editing.type = null;
    editing.id = null;
    singleForm.reset();
    singleForm.status = 'active';
    syncContextToForm(singleForm);
    singleForm.clearErrors();
};

const resetBulkForm = () => {
    bulkForm.rows = '';
    bulkForm.status = 'active';
    syncContextToForm(bulkForm);
    bulkForm.clearErrors();
};

const closePanels = () => {
    activePanel.value = null;
    resetSingleForm();
    resetBulkForm();
};

const openSinglePanel = () => {
    resetSingleForm();
    activePanel.value = 'single';
};

const openBulkPanel = () => {
    resetBulkForm();
    activePanel.value = 'bulk';
};

const parentValue = computed({
    get: () => singleForm.bidang_urusan_id,
    set: (value) => {
        singleForm.bidang_urusan_id = value ?? '';
    },
});

const canSubmitSingle = computed(() => {
    const hasParent =
        props.level === 'program'
            ? Boolean(singleForm.bidang_urusan_id)
            : props.level === 'kegiatan'
              ? Boolean(props.context.program?.id)
              : Boolean(props.context.kegiatan?.id);

    return props.can.manage && hasParent && singleForm.kode.trim().length > 0 && singleForm.nama.trim().length > 0;
});
const bulkValidRows = computed(() => bulkForm.rows.split(/\r\n|\r|\n/).filter((line) => line.trim().includes('|')).length);
const canSubmitBulk = computed(() => {
    const hasParent =
        props.level === 'program'
            ? Boolean(bulkForm.bidang_urusan_id)
            : props.level === 'kegiatan'
              ? Boolean(props.context.program?.id)
              : Boolean(props.context.kegiatan?.id);

    return props.can.manage && hasParent && bulkValidRows.value > 0;
});

watch(
    () => [props.level, props.context.program?.id, props.context.kegiatan?.id],
    () => {
        activePanel.value = null;
        resetSingleForm();
        resetBulkForm();
    },
    { immediate: true },
);

watch(
    () => props.filters,
    () => {
        filterForm.search = props.filters.search ?? '';
        filterForm.status = props.filters.status ?? '';
        filterForm.bidang_urusan_id = props.filters.bidang_urusan_id ?? '';
    },
);

const submitSingle = () => {
    if (!canSubmitSingle.value) {
        return;
    }

    singleForm.type = props.level;
    singleForm.redirect_to = redirectTo.value;
    if (props.level === 'kegiatan' && props.context.program?.id) {
        singleForm.program_pemerintahan_id = props.context.program.id;
    }
    if (props.level === 'sub_kegiatan' && props.context.kegiatan?.id) {
        singleForm.kegiatan_pemerintahan_id = props.context.kegiatan.id;
    }

    if (editing.type && editing.id) {
        singleForm.put(route('master.program-pemerintahan.update', [editing.type, editing.id]), {
            preserveScroll: true,
            onSuccess: closePanels,
        });
        return;
    }

    singleForm.post(route('master.program-pemerintahan.store'), {
        preserveScroll: true,
        onSuccess: closePanels,
    });
};

const submitBulk = () => {
    if (!canSubmitBulk.value) {
        return;
    }

    bulkForm.type = props.level;
    bulkForm.redirect_to = redirectTo.value;
    if (props.level === 'kegiatan' && props.context.program?.id) {
        bulkForm.program_pemerintahan_id = props.context.program.id;
    }
    if (props.level === 'sub_kegiatan' && props.context.kegiatan?.id) {
        bulkForm.kegiatan_pemerintahan_id = props.context.kegiatan.id;
    }

    bulkForm.post(route('master.program-pemerintahan.bulk-store'), {
        preserveScroll: true,
        onSuccess: closePanels,
    });
};

const editItem = (item: ReferenceItem) => {
    activePanel.value = 'single';
    editing.type = item.type;
    editing.id = item.id;
    singleForm.type = item.type;
    singleForm.bidang_urusan_id = item.type === 'program' ? item.parent_id : '';
    singleForm.program_pemerintahan_id = item.type === 'kegiatan' ? item.parent_id : '';
    singleForm.kegiatan_pemerintahan_id = item.type === 'sub_kegiatan' ? item.parent_id : '';
    singleForm.kode = item.kode;
    singleForm.nama = item.nama;
    singleForm.status = item.status;
    singleForm.redirect_to = redirectTo.value;
    singleForm.clearErrors();
};

const destroy = async (item: ReferenceItem) => {
    if (await confirmDelete(`Hapus ${item.level.toLowerCase()} ${item.kode} - ${item.nama}?`)) {
        router.delete(route('master.program-pemerintahan.destroy', [item.type, item.id]), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="flex flex-col gap-4 p-4">
        <section class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <div class="flex flex-col gap-4 border-b bg-gradient-to-r from-background via-background to-[#00336C]/5 p-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <Link :href="backToProgramsHref" class="font-semibold text-[#00336C] transition hover:text-[#002958]">
                            Program
                        </Link>
                        <template v-if="props.level !== 'program'">
                            <ChevronRight class="size-4 text-muted-foreground" />
                            <Link
                                v-if="props.level === 'sub_kegiatan'"
                                :href="backToKegiatanHref"
                                class="font-semibold text-[#00336C] transition hover:text-[#002958]"
                            >
                                Kegiatan
                            </Link>
                            <span v-else class="font-semibold text-foreground">Kegiatan</span>
                        </template>
                        <template v-if="props.level === 'sub_kegiatan'">
                            <ChevronRight class="size-4 text-muted-foreground" />
                            <span class="font-semibold text-foreground">Sub Kegiatan</span>
                        </template>
                    </div>
                    <h1 class="mt-3 text-2xl font-semibold tracking-normal text-foreground">{{ pageTitle }}</h1>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">{{ pageDescription }}</p>
                </div>

                <Link
                    v-if="props.level !== 'program'"
                    :href="props.level === 'sub_kegiatan' ? backToKegiatanHref : backToProgramsHref"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border bg-background px-3 text-sm font-semibold transition hover:bg-muted"
                >
                    <ArrowLeft class="size-4" />
                    Kembali
                </Link>
            </div>

            <div class="grid gap-3 p-4 md:grid-cols-3">
                <div class="rounded-xl border bg-background p-4">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Program</div>
                    <div class="mt-2 text-2xl font-semibold">{{ summary.program_count }}</div>
                </div>
                <div class="rounded-xl border bg-background p-4">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Kegiatan</div>
                    <div class="mt-2 text-2xl font-semibold">{{ summary.kegiatan_count }}</div>
                </div>
                <div class="rounded-xl border bg-background p-4">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Sub Kegiatan</div>
                    <div class="mt-2 text-2xl font-semibold">{{ summary.sub_kegiatan_count }}</div>
                </div>
            </div>
        </section>

        <div class="overflow-visible rounded-2xl border bg-card shadow-sm">
            <div class="relative z-30 flex flex-col gap-4 border-b bg-card p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#00336C]/10 text-[#00336C]">
                            <FolderTree class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold">{{ meta.tableTitle }}</h2>
                            <p class="text-sm text-muted-foreground">Menampilkan {{ items.total }} data.</p>
                        </div>
                    </div>
                    <div v-if="can.manage" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002958]"
                            @click="openSinglePanel"
                        >
                            <Plus class="size-4" />
                            Tambah {{ meta.noun }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border bg-background px-4 text-sm font-semibold transition hover:bg-muted"
                            @click="openBulkPanel"
                        >
                            <FileSpreadsheet class="size-4" />
                            Input Cepat
                        </button>
                    </div>
                </div>

                <form
                    class="relative z-40 grid gap-3"
                    :class="
                        props.level === 'program'
                            ? 'lg:grid-cols-[minmax(220px,1fr)_minmax(280px,420px)_190px_auto]'
                            : 'lg:grid-cols-[minmax(0,1fr)_220px_auto]'
                    "
                    @submit.prevent="applyFiltersNow"
                >
                    <div class="relative min-w-0">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="h-11 w-full rounded-xl border bg-background pl-9 pr-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                            placeholder="Cari kode atau nama"
                            aria-label="Cari program, kegiatan, atau sub kegiatan"
                        />
                    </div>
                    <RpjmdRichSelect
                        v-if="props.level === 'program'"
                        v-model="filterForm.bidang_urusan_id"
                        :options="options.bidang"
                        placeholder="Filter bidang urusan"
                        empty-text="Bidang urusan belum tersedia"
                        placement="bottom"
                    />
                    <select
                        v-model="filterForm.status"
                        class="h-11 min-w-0 rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                        aria-label="Filter status"
                    >
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak aktif</option>
                    </select>
                    <button
                        type="button"
                        class="h-11 rounded-xl px-3 text-sm font-medium text-muted-foreground transition hover:bg-muted"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </form>
            </div>

            <Transition name="fade">
                <section v-if="can.manage && activePanel" class="relative z-20 border-b bg-muted/20 p-4">
                    <form
                        v-if="activePanel === 'single'"
                        class="overflow-visible rounded-2xl border bg-card shadow-sm"
                        @submit.prevent="submitSingle"
                    >
                        <div class="flex items-center justify-between border-b bg-background p-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#00336C]/10 text-[#00336C]">
                                    <Layers3 class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <h2 class="font-semibold">{{ editing.id ? `Edit ${meta.noun}` : `Tambah ${meta.noun}` }}</h2>
                                    <p class="truncate text-sm text-muted-foreground">
                                        {{ meta.parent }}:
                                        {{ props.level === 'program' ? 'pilih bidang urusan' : props.context.program?.label || props.context.kegiatan?.label }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex size-10 items-center justify-center rounded-xl border bg-background transition hover:bg-muted"
                                aria-label="Tutup form"
                                @click="closePanels"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <div class="grid gap-4 p-4 lg:grid-cols-[180px_minmax(0,1fr)]">
                            <div v-if="props.level === 'program'" class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-medium">Bidang Urusan</label>
                                <RpjmdRichSelect
                                    v-model="parentValue"
                                    :options="options.bidang"
                                    placeholder="Pilih bidang urusan"
                                    empty-text="Bidang urusan belum tersedia"
                                    placement="bottom"
                                />
                                <InputError :message="singleForm.errors.bidang_urusan_id" />
                            </div>
                            <div v-else class="rounded-xl border bg-background p-3 lg:col-span-2">
                                <div class="text-xs font-semibold uppercase text-muted-foreground">{{ meta.parent }}</div>
                                <div class="mt-1 text-sm font-semibold">{{ props.context.program?.label || props.context.kegiatan?.label }}</div>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="single-kode">Kode</label>
                                <input
                                    id="single-kode"
                                    v-model="singleForm.kode"
                                    class="h-11 rounded-xl border bg-background px-3 text-sm font-semibold"
                                />
                                <InputError :message="singleForm.errors.kode" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="single-status">Status</label>
                                <select id="single-status" v-model="singleForm.status" class="h-11 rounded-xl border bg-background px-3 text-sm">
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Tidak aktif</option>
                                </select>
                                <InputError :message="singleForm.errors.status" />
                            </div>
                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-medium" for="single-nama">Nama {{ meta.noun }}</label>
                                <textarea id="single-nama" v-model="singleForm.nama" rows="3" class="rounded-xl border bg-background px-3 py-2 text-sm" />
                                <InputError :message="singleForm.errors.nama" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t bg-background p-4">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center rounded-xl border bg-background px-4 text-sm font-semibold transition hover:bg-muted"
                                @click="closePanels"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="!canSubmitSingle || singleForm.processing"
                                class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002958] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Plus v-if="!editing.id" class="size-4" />
                                <Pencil v-else class="size-4" />
                                {{ editing.id ? 'Simpan Perubahan' : 'Simpan Data' }}
                            </button>
                        </div>
                    </form>

                    <form v-else class="overflow-visible rounded-2xl border bg-card shadow-sm" @submit.prevent="submitBulk">
                        <div class="flex items-center justify-between border-b bg-background p-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                >
                                    <FileSpreadsheet class="size-5" />
                                </div>
                                <div>
                                    <h2 class="font-semibold">Input Cepat {{ meta.noun }}</h2>
                                    <p class="text-sm text-muted-foreground">Format: kode | nama</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full border bg-background px-3 py-1 text-xs font-semibold text-muted-foreground">
                                    {{ bulkValidRows }} baris
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex size-10 items-center justify-center rounded-xl border bg-background transition hover:bg-muted"
                                    aria-label="Tutup input cepat"
                                    @click="closePanels"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4">
                            <div v-if="props.level === 'program'" class="grid gap-2">
                                <label class="text-sm font-medium">Bidang Urusan</label>
                                <RpjmdRichSelect
                                    v-model="bulkForm.bidang_urusan_id"
                                    :options="options.bidang"
                                    placeholder="Pilih bidang urusan"
                                    empty-text="Bidang urusan belum tersedia"
                                    placement="bottom"
                                />
                                <InputError :message="bulkForm.errors.bidang_urusan_id" />
                            </div>
                            <div v-else class="rounded-xl border bg-background p-3">
                                <div class="text-xs font-semibold uppercase text-muted-foreground">{{ meta.parent }}</div>
                                <div class="mt-1 text-sm font-semibold">{{ props.context.program?.label || props.context.kegiatan?.label }}</div>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="bulk-status">Status</label>
                                <select id="bulk-status" v-model="bulkForm.status" class="h-11 rounded-xl border bg-background px-3 text-sm">
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Tidak aktif</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="bulk-rows">Data {{ meta.noun }}</label>
                                <textarea
                                    id="bulk-rows"
                                    v-model="bulkForm.rows"
                                    rows="8"
                                    class="rounded-xl border bg-background px-3 py-2 font-mono text-sm leading-6"
                                    :placeholder="meta.bulkPlaceholder"
                                />
                                <InputError :message="bulkForm.errors.rows" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t bg-background p-4">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center rounded-xl border bg-background px-4 text-sm font-semibold transition hover:bg-muted"
                                @click="closePanels"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="!canSubmitBulk || bulkForm.processing"
                                class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <ClipboardList class="size-4" />
                                Simpan Banyak
                            </button>
                        </div>
                    </form>
                </section>
            </Transition>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Nama {{ meta.noun }}</th>
                            <th class="px-4 py-3">{{ meta.parent }}</th>
                            <th class="px-4 py-3">{{ meta.childColumn }}</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="`${item.type}-${item.id}`" class="group border-b transition last:border-0 hover:bg-muted/35">
                            <td class="whitespace-nowrap px-4 py-3 align-top font-semibold text-foreground">{{ item.kode }}</td>
                            <td class="min-w-[360px] px-4 py-3 align-top">
                                <div class="font-semibold text-foreground">{{ item.nama }}</div>
                                <div v-if="props.level === 'program'" class="mt-1 text-xs leading-5 text-muted-foreground">{{ item.urusan_label || '-' }}</div>
                            </td>
                            <td class="min-w-[280px] px-4 py-3 align-top">
                                <div class="font-medium text-foreground">{{ item.parent_label }}</div>
                                <div v-if="item.bidang_label && props.level !== 'program'" class="mt-1 text-xs text-muted-foreground">{{ item.bidang_label }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <Link
                                    v-if="item.drilldown_url"
                                    :href="item.drilldown_url"
                                    preserve-scroll
                                    class="inline-flex h-10 items-center gap-2 rounded-xl border bg-background px-3 text-sm font-semibold text-[#00336C] transition group-hover:border-[#00336C]/30 group-hover:bg-[#00336C]/5"
                                >
                                    {{ item.children_label }}
                                    <ChevronRight class="size-4" />
                                </Link>
                                <span v-else class="text-sm text-muted-foreground">{{ item.children_label }}</span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="item.status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ item.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <div v-if="can.manage" class="inline-flex overflow-hidden rounded-xl border bg-background">
                                    <button
                                        type="button"
                                        class="inline-flex size-10 items-center justify-center text-muted-foreground transition hover:bg-[#00336C]/5 hover:text-[#00336C]"
                                        title="Edit"
                                        aria-label="Edit data"
                                        @click="editItem(item)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <span class="h-10 w-px bg-border" />
                                    <button
                                        type="button"
                                        class="inline-flex size-10 items-center justify-center text-red-600 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-500/10"
                                        title="Hapus"
                                        aria-label="Hapus data"
                                        @click="destroy(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Read-only</span>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">{{ meta.empty }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data</span>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="items.prev_page_url" :href="items.prev_page_url" preserve-scroll class="rounded-md border px-3 py-1.5 transition hover:bg-muted">
                        Sebelumnya
                    </Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ items.current_page }} / {{ items.last_page }}</span>
                    <Link v-if="items.next_page_url" :href="items.next_page_url" preserve-scroll class="rounded-md border px-3 py-1.5 transition hover:bg-muted">
                        Berikutnya
                    </Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </div>
    </div>
</template>
