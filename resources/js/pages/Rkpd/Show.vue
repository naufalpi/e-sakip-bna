<script setup lang="ts">
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, ClipboardList, DatabaseZap, FileSpreadsheet, Pencil, Plus, Save, Search, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';

type Option = {
    id: number | string;
    label: string;
    kode?: string;
    nama?: string;
    description?: string;
    group?: string;
    program_id?: number | null;
    program_pemerintahan_id?: number | null;
};
type Rkpd = {
    id: number;
    tahun: number;
    judul: string;
    nomor_dokumen?: string | null;
    status: string;
    rpjmd?: { id: number; judul: string; tahun_awal: number; tahun_akhir: number } | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
};
type Row = {
    id: number;
    opd_id: number;
    opd_unit_id?: number | null;
    sub_kegiatan_pemerintahan_id?: number | null;
    program_rpjmd_id?: number | null;
    kode?: string | null;
    nama_urusan_bidang_program_kegiatan_sub?: string | null;
    indikator?: string | null;
    target_akhir_renstra?: string | null;
    realisasi_capaian_renja_tahun_lalu?: string | null;
    prakiraan_capaian_target_renja_tahun_berjalan?: string | null;
    target?: string | null;
    pagu_indikatif?: string | number | null;
    lokasi?: string | null;
    sumber_dana?: string | null;
    prioritas_nasional?: string | null;
    prioritas_daerah?: string | null;
    kelompok_sasaran?: string | null;
    prakiraan_maju_target?: string | null;
    prakiraan_maju_pagu_indikatif?: string | number | null;
    perangkat_daerah_penanggung_jawab?: string | null;
    status: string;
    urutan: number;
    opd?: { id: number; kode?: string | null; nama: string; singkatan?: string | null } | null;
    urusan?: string;
    bidang?: string;
    program?: string;
    kegiatan?: string;
    sub_kegiatan?: string;
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
    rkpd: Rkpd;
    items: Paginator<Row>;
    filters: { search?: string; status?: string; opd_id?: string };
    summary: { items_count: number; opd_count: number; renja_count: number; total_pagu: number; total_prakiraan_maju: number };
    opdOptions: Option[];
    subKegiatanOptions: Option[];
    programRpjmdOptions: Option[];
    can: { manage: boolean; pullRenja: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
});

const form = useForm({
    opd_id: '',
    opd_unit_id: '',
    sub_kegiatan_pemerintahan_id: '',
    program_rpjmd_id: '',
    indikator: '',
    target_akhir_renstra: '',
    realisasi_capaian_renja_tahun_lalu: '',
    prakiraan_capaian_target_renja_tahun_berjalan: '',
    target: '',
    pagu_indikatif: '',
    lokasi: '',
    sumber_dana: '',
    prioritas_nasional: '',
    prioritas_daerah: '',
    kelompok_sasaran: '',
    prakiraan_maju_target: '',
    prakiraan_maju_pagu_indikatif: '',
    perangkat_daerah_penanggung_jawab: '',
    status: 'draft',
    urutan: '',
});

const editingId = ref<number | null>(null);
const isFormOpen = ref(false);
const formSection = ref<HTMLElement | null>(null);

const applyFilters = () => router.get(route('rkpd.show', props.rkpd.id), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const selectedSubKegiatan = computed(() =>
    props.subKegiatanOptions.find((option) => String(option.id) === String(form.sub_kegiatan_pemerintahan_id)),
);
const selectedOpd = computed(() => props.opdOptions.find((option) => String(option.id) === String(form.opd_id)));
const selectedProgramRpjmd = computed(() => props.programRpjmdOptions.find((option) => String(option.id) === String(form.program_rpjmd_id)));
const relatedProgramRpjmdOptions = computed(() => {
    const programId = selectedSubKegiatan.value?.program_id;

    if (!programId) {
        return props.programRpjmdOptions;
    }

    return props.programRpjmdOptions.filter((option) => Number(option.program_pemerintahan_id || 0) === Number(programId));
});
const programRpjmdSelectOptions = computed(() => [
    { id: '', label: 'Tidak dihubungkan', description: 'Opsional' },
    ...relatedProgramRpjmdOptions.value,
]);

const scrollToForm = () => {
    nextTick(() => {
        formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const openManualForm = () => {
    isFormOpen.value = true;
    scrollToForm();
};

watch(
    () => form.sub_kegiatan_pemerintahan_id,
    () => {
        const currentStillValid = relatedProgramRpjmdOptions.value.some((option) => String(option.id) === String(form.program_rpjmd_id));

        if (form.program_rpjmd_id && currentStillValid) {
            return;
        }

        form.program_rpjmd_id = relatedProgramRpjmdOptions.value.length === 1 ? String(relatedProgramRpjmdOptions.value[0].id) : '';
    },
);

watch(
    () => form.opd_id,
    () => {
        form.perangkat_daerah_penanggung_jawab = selectedOpd.value?.label ?? '';
    },
);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    applyFiltersNow();
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'draft';
};

const closeForm = () => {
    resetForm();
    isFormOpen.value = false;
};

const submitItem = () => {
    if (editingId.value) {
        form.put(route('rkpd.items.update', [props.rkpd.id, editingId.value]), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post(route('rkpd.items.store', props.rkpd.id), {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

const editItem = (row: Row) => {
    editingId.value = row.id;
    isFormOpen.value = true;
    form.opd_id = String(row.opd_id ?? '');
    form.opd_unit_id = String(row.opd_unit_id ?? '');
    form.sub_kegiatan_pemerintahan_id = String(row.sub_kegiatan_pemerintahan_id ?? '');
    form.program_rpjmd_id = String(row.program_rpjmd_id ?? '');
    form.indikator = row.indikator ?? '';
    form.target_akhir_renstra = row.target_akhir_renstra ?? '';
    form.realisasi_capaian_renja_tahun_lalu = row.realisasi_capaian_renja_tahun_lalu ?? '';
    form.prakiraan_capaian_target_renja_tahun_berjalan = row.prakiraan_capaian_target_renja_tahun_berjalan ?? '';
    form.target = row.target ?? '';
    form.pagu_indikatif = String(row.pagu_indikatif ?? '');
    form.lokasi = row.lokasi ?? '';
    form.sumber_dana = row.sumber_dana ?? '';
    form.prioritas_nasional = row.prioritas_nasional ?? '';
    form.prioritas_daerah = row.prioritas_daerah ?? '';
    form.kelompok_sasaran = row.kelompok_sasaran ?? '';
    form.prakiraan_maju_target = row.prakiraan_maju_target ?? '';
    form.prakiraan_maju_pagu_indikatif = String(row.prakiraan_maju_pagu_indikatif ?? '');
    form.perangkat_daerah_penanggung_jawab = row.perangkat_daerah_penanggung_jawab ?? '';
    form.status = row.status;
    form.urutan = String(row.urutan ?? '');
    scrollToForm();
};

const destroyItem = async (row: Row) => {
    if (await confirmDelete(`Hapus baris ${row.kode || row.nama_urusan_bidang_program_kegiatan_sub || 'RKPD'}?`)) {
        router.delete(route('rkpd.items.destroy', [props.rkpd.id, row.id]), { preserveScroll: true });
    }
};

const pullRenja = () => {
    router.post(route('rkpd.pull-renja', props.rkpd.id), {}, { preserveScroll: true });
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

const formatMoney = (value?: number | string | null) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
};
</script>

<template>
    <Head :title="`RKPD ${rkpd.tahun}`" />

    <div class="flex flex-col gap-5 p-4">
        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#edf7ff)] px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <Link :href="route('rkpd.index')" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                            <ArrowLeft class="size-4" />
                            Kembali
                        </Link>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold tracking-normal">{{ rkpd.judul }}</h1>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(rkpd.status)">{{ statusLabel(rkpd.status) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ rkpd.tahun }} - {{ rkpd.nomor_dokumen || 'Nomor dokumen belum diisi' }}
                        </p>
                        <p v-if="rkpd.rpjmd" class="mt-2 text-sm text-muted-foreground">
                            Acuan RPJMD {{ rkpd.rpjmd.tahun_awal }}-{{ rkpd.rpjmd.tahun_akhir }} - {{ rkpd.rpjmd.judul }}
                        </p>
                    </div>

                    <Link
                        v-if="can.manage"
                        :href="route('rkpd.edit', rkpd.id)"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-white px-4 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >
                        <Pencil class="size-4" />
                        Edit RKPD
                    </Link>
                </div>
            </div>

            <div class="grid gap-3 p-4 md:grid-cols-4">
                <article class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">OPD Terisi</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.opd_count }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">perangkat daerah</p>
                </article>
                <article class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">Renja Sumber</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.renja_count }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">dokumen OPD</p>
                </article>
                <article class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">Baris Matriks</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.items_count }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">program sampai sub kegiatan</p>
                </article>
                <article class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-[#00336C]">
                    <p class="text-xs font-semibold uppercase opacity-70">Pagu Indikatif</p>
                    <p class="mt-2 text-2xl font-semibold">{{ formatMoney(summary.total_pagu) }}</p>
                    <p class="mt-1 text-xs opacity-75">tahun {{ rkpd.tahun }}</p>
                </article>
            </div>
        </section>

        <section v-if="can.manage" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_26rem]">
            <article class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="border-b px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-blue-50 text-[#00336C]">
                            <ClipboardList class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold">Alur Pengisian RKPD</h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">RKPD adalah kompilasi Renja OPD tahunan.</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 p-4 md:grid-cols-3">
                    <div class="rounded-xl border bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-[#00336C] px-2.5 py-1 text-xs font-semibold text-white">1</span>
                            <CheckCircle2 class="size-5 text-emerald-600" />
                        </div>
                        <h3 class="mt-4 font-semibold">Renja OPD</h3>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">OPD mengisi Renja sampai sub kegiatan.</p>
                    </div>
                    <div class="rounded-xl border bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-[#00336C] px-2.5 py-1 text-xs font-semibold text-white">2</span>
                            <DatabaseZap class="size-5 text-[#00336C]" />
                        </div>
                        <h3 class="mt-4 font-semibold">Tarik Data</h3>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">Ambil Renja yang sudah disetujui ke RKPD.</p>
                    </div>
                    <div class="rounded-xl border bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-[#00336C] px-2.5 py-1 text-xs font-semibold text-white">3</span>
                            <FileSpreadsheet class="size-5 text-slate-700" />
                        </div>
                        <h3 class="mt-4 font-semibold">Matriks RKPD</h3>
                        <p class="mt-1 text-sm leading-5 text-muted-foreground">Cek target, pagu, prioritas, dan prakiraan maju.</p>
                    </div>
                </div>
            </article>

            <aside class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="text-base font-semibold">Aksi Input</h2>
                <p class="mt-1 text-sm text-muted-foreground">Gunakan tarik Renja sebagai jalur utama.</p>

                <div class="mt-4 grid gap-2">
                    <button
                        v-if="can.pullRenja"
                        type="button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#002855]"
                        @click="pullRenja"
                    >
                        <DatabaseZap class="size-4" />
                        Tarik Renja Approved
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border bg-white px-4 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                        @click="openManualForm"
                    >
                        <Plus class="size-4" />
                        Tambah Manual
                    </button>
                </div>
            </aside>
        </section>

        <section v-if="can.manage && isFormOpen" ref="formSection" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">{{ editingId ? 'Edit Baris RKPD' : 'Tambah Baris RKPD Manual' }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Isi manual hanya untuk data yang belum berasal dari Renja OPD.</p>
                    </div>
                    <button type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border bg-white px-3 text-sm font-semibold hover:bg-slate-50" @click="closeForm">
                        <X class="size-4" />
                        Tutup
                    </button>
                </div>
            </div>

            <form class="grid gap-5 p-5" @submit.prevent="submitItem">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
                    <div class="rounded-xl border bg-white p-4">
                        <div class="mb-4 flex items-center gap-2">
                            <span class="flex size-7 items-center justify-center rounded-full bg-[#00336C] text-sm font-semibold text-white">1</span>
                            <h3 class="font-semibold">Sumber Kegiatan</h3>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium">OPD Penanggung Jawab</span>
                                <RpjmdRichSelect
                                    v-model="form.opd_id"
                                    :options="opdOptions"
                                    placeholder="Pilih OPD"
                                    empty-text="OPD tidak tersedia"
                                    :invalid="Boolean(form.errors.opd_id)"
                                />
                                <span v-if="form.errors.opd_id" class="text-xs text-red-600">{{ form.errors.opd_id }}</span>
                            </label>

                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium">Program RPJMD Terkait</span>
                                <RpjmdRichSelect
                                    v-model="form.program_rpjmd_id"
                                    :options="programRpjmdSelectOptions"
                                    placeholder="Pilih program RPJMD"
                                    empty-text="Program RPJMD tidak tersedia"
                                />
                                <span v-if="selectedSubKegiatan && relatedProgramRpjmdOptions.length === 0" class="text-xs text-amber-700">
                                    Belum ada program RPJMD yang memakai program master ini.
                                </span>
                                <span v-else-if="selectedSubKegiatan && relatedProgramRpjmdOptions.length > 1" class="text-xs text-muted-foreground">
                                    Ada {{ relatedProgramRpjmdOptions.length }} program RPJMD terkait. Pilih yang paling sesuai.
                                </span>
                            </label>

                            <label class="grid gap-1.5 lg:col-span-2">
                                <span class="text-sm font-medium">Sub Kegiatan</span>
                                <RpjmdRichSelect
                                    v-model="form.sub_kegiatan_pemerintahan_id"
                                    :options="subKegiatanOptions"
                                    placeholder="Cari dan pilih sub kegiatan"
                                    empty-text="Sub kegiatan tidak tersedia"
                                    :invalid="Boolean(form.errors.sub_kegiatan_pemerintahan_id)"
                                />
                                <span v-if="form.errors.sub_kegiatan_pemerintahan_id" class="text-xs text-red-600">{{ form.errors.sub_kegiatan_pemerintahan_id }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-xl border bg-slate-50 p-4">
                        <h3 class="font-semibold">Ringkasan Pilihan</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase text-muted-foreground">OPD</p>
                                <p class="mt-1 font-medium">{{ selectedOpd?.label || 'Belum dipilih' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-muted-foreground">Sub Kegiatan</p>
                                <p class="mt-1 font-medium">{{ selectedSubKegiatan?.label || 'Belum dipilih' }}</p>
                                <p v-if="selectedSubKegiatan?.description" class="mt-1 text-xs text-muted-foreground">{{ selectedSubKegiatan.description }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-muted-foreground">Program Master</p>
                                <p class="mt-1 font-medium">{{ selectedSubKegiatan?.group || 'Belum dipilih' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-muted-foreground">Program RPJMD</p>
                                <p class="mt-1 font-medium">{{ selectedProgramRpjmd?.label || 'Tidak dihubungkan' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-full bg-[#00336C] text-sm font-semibold text-white">2</span>
                        <h3 class="font-semibold">Target dan Capaian</h3>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-4">
                        <label class="grid gap-1.5 lg:col-span-2">
                            <span class="text-sm font-medium">Indikator</span>
                            <textarea v-model="form.indikator" rows="3" class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target {{ rkpd.tahun }}</span>
                            <input v-model="form.target" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target Akhir Renstra</span>
                            <input v-model="form.target_akhir_renstra" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Realisasi Tahun Lalu</span>
                            <input v-model="form.realisasi_capaian_renja_tahun_lalu" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prakiraan Tahun Berjalan</span>
                            <input v-model="form.prakiraan_capaian_target_renja_tahun_berjalan" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prakiraan Maju Target</span>
                            <input v-model="form.prakiraan_maju_target" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Urutan</span>
                            <input v-model="form.urutan" type="number" min="1" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-full bg-[#00336C] text-sm font-semibold text-white">3</span>
                        <h3 class="font-semibold">Pendanaan dan Prioritas</h3>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-4">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Pagu Indikatif</span>
                            <input v-model="form.pagu_indikatif" type="number" min="0" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prakiraan Maju Pagu</span>
                            <input v-model="form.prakiraan_maju_pagu_indikatif" type="number" min="0" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Sumber Dana</span>
                            <input v-model="form.sumber_dana" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Status Baris</span>
                            <select v-model="form.status" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                                <option value="draft">Draft</option>
                                <option value="verified">Terverifikasi</option>
                                <option value="approved">Disetujui</option>
                                <option value="locked">Terkunci</option>
                            </select>
                        </label>
                        <label class="grid gap-1.5 lg:col-span-2">
                            <span class="text-sm font-medium">Lokasi</span>
                            <textarea v-model="form.lokasi" rows="3" class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prioritas Nasional</span>
                            <input v-model="form.prioritas_nasional" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prioritas Daerah</span>
                            <input v-model="form.prioritas_daerah" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5 lg:col-span-2">
                            <span class="text-sm font-medium">Kelompok Sasaran</span>
                            <input v-model="form.kelompok_sasaran" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                        <label class="grid gap-1.5 lg:col-span-2">
                            <span class="text-sm font-medium">Perangkat Daerah Penanggung Jawab</span>
                            <input v-model="form.perangkat_daerah_penanggung_jawab" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                            <span class="text-xs text-muted-foreground">Otomatis mengikuti OPD Penanggung Jawab, tetapi tetap bisa diubah jika lintas OPD.</span>
                        </label>
                    </div>
                </div>

                <div class="sticky bottom-0 -mx-5 -mb-5 flex justify-end gap-2 border-t bg-card/95 px-5 py-4 backdrop-blur">
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border bg-white px-4 text-sm font-semibold hover:bg-slate-50" @click="closeForm">
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855] disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{ editingId ? 'Simpan Perubahan' : 'Simpan Baris' }}
                    </button>
                </div>
            </form>
        </section>

        <section id="rkpd-matrix" class="rounded-xl border bg-card shadow-sm">
            <div class="border-b p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Matriks RKPD</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Format tabel resmi RKPD. Geser horizontal untuk melihat semua kolom.</p>
                    </div>
                    <form class="grid gap-2 lg:grid-cols-[260px_160px_220px_auto]" @submit.prevent="applyFiltersNow">
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="filterForm.search"
                                type="search"
                                class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Cari kode, indikator, OPD"
                            />
                        </label>
                        <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                            <option value="">Semua status</option>
                            <option value="draft">Draft</option>
                            <option value="verified">Terverifikasi</option>
                            <option value="approved">Disetujui</option>
                            <option value="locked">Terkunci</option>
                        </select>
                        <select v-model="filterForm.opd_id" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                            <option value="">Semua OPD</option>
                            <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <button type="button" class="h-10 rounded-lg px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[2200px] border-collapse text-left text-xs">
                    <thead class="bg-[#d8e9ff] text-[11px] uppercase text-slate-950">
                        <tr>
                            <th class="sticky left-0 z-10 border bg-[#d8e9ff] px-3 py-3 text-center">No</th>
                            <th class="border px-3 py-3">Kode</th>
                            <th class="border px-3 py-3">Urusan / Bidang / Program / Kegiatan / Sub Kegiatan</th>
                            <th class="border px-3 py-3">Indikator Program / Kegiatan / Sub Kegiatan</th>
                            <th class="border px-3 py-3">Target Akhir Renstra OPD</th>
                            <th class="border px-3 py-3">Realisasi Renja Lalu</th>
                            <th class="border px-3 py-3">Prakiraan Tahun Berjalan</th>
                            <th class="border px-3 py-3">Target {{ rkpd.tahun }}</th>
                            <th class="border px-3 py-3">Pagu Indikatif</th>
                            <th class="border px-3 py-3">Lokasi</th>
                            <th class="border px-3 py-3">Sumber Dana</th>
                            <th class="border px-3 py-3">Prioritas Nasional</th>
                            <th class="border px-3 py-3">Prioritas Daerah</th>
                            <th class="border px-3 py-3">Kelompok Sasaran</th>
                            <th class="border px-3 py-3">Prakiraan Maju Target</th>
                            <th class="border px-3 py-3">Prakiraan Maju Pagu</th>
                            <th class="border px-3 py-3">PD Penanggung Jawab</th>
                            <th class="border px-3 py-3">Status</th>
                            <th v-if="can.manage" class="border px-3 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in items.data" :key="row.id" class="align-top hover:bg-sky-50/60">
                            <td class="sticky left-0 z-10 border bg-white px-3 py-3 text-center font-semibold">{{ items.from ? items.from + index : index + 1 }}</td>
                            <td class="border px-3 py-3 font-semibold">{{ row.kode || '-' }}</td>
                            <td class="border px-3 py-3">
                                <div class="max-w-72 space-y-1">
                                    <p class="font-semibold">{{ row.nama_urusan_bidang_program_kegiatan_sub || row.sub_kegiatan || '-' }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ row.program }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ row.kegiatan }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ row.bidang }}</p>
                                </div>
                            </td>
                            <td class="border px-3 py-3">{{ row.indikator || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.target_akhir_renstra || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.realisasi_capaian_renja_tahun_lalu || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.prakiraan_capaian_target_renja_tahun_berjalan || '-' }}</td>
                            <td class="border px-3 py-3 font-semibold">{{ row.target || '-' }}</td>
                            <td class="border px-3 py-3 text-right font-semibold">{{ formatMoney(row.pagu_indikatif) }}</td>
                            <td class="border px-3 py-3">{{ row.lokasi || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.sumber_dana || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.prioritas_nasional || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.prioritas_daerah || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.kelompok_sasaran || '-' }}</td>
                            <td class="border px-3 py-3">{{ row.prakiraan_maju_target || '-' }}</td>
                            <td class="border px-3 py-3 text-right font-semibold">{{ formatMoney(row.prakiraan_maju_pagu_indikatif) }}</td>
                            <td class="border px-3 py-3">{{ row.perangkat_daerah_penanggung_jawab || row.opd?.nama || '-' }}</td>
                            <td class="border px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span>
                            </td>
                            <td v-if="can.manage" class="border px-3 py-3 text-right">
                                <div class="inline-flex overflow-hidden rounded-lg border bg-white">
                                    <button type="button" class="h-9 px-3 hover:bg-muted" @click="editItem(row)">
                                        <Pencil class="size-4" />
                                    </button>
                                    <button type="button" class="h-9 px-3 text-red-600 hover:bg-red-50" @click="destroyItem(row)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td :colspan="can.manage ? 19 : 18" class="border px-4 py-12 text-center text-sm text-muted-foreground">
                                Belum ada baris RKPD.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data</span>
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
