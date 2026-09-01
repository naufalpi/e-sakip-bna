<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, FileCheck2, Landmark, Layers3, LoaderCircle, Save, Search, Sparkles, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type BasicOption = { id: number; label: string };
type PeriodOption = BasicOption & { tahun: number };
type RenstraOption = BasicOption & { opd_id: number; periode_tahun_id: number; tahun_awal: number; tahun_akhir: number };
type RkpdOption = BasicOption & { periode_tahun_id: number; tahun: number };
type DpaOption = BasicOption & {
    opd_id: number;
    periode_tahun_id: number;
    renja_opd_id?: number | null;
    renstra_opd_id?: number | null;
    tahun: number;
};
type EmployeeOption = BasicOption & { opd_id?: number | null };
type PlacementOption = BasicOption & {
    pegawai_id: number;
    jabatan_organisasi_id: number;
    opd_id?: number | null;
    level_jabatan?: string | null;
    parent_jabatan_id?: number | null;
    tanggal_mulai?: string | null;
    tanggal_selesai?: string | null;
};
type CascadingScopeItem = { key: string; label: string; context?: string | null; indicator_count: number };
type CascadingScopeGroup = { type: string; label: string; items: CascadingScopeItem[] };
type FormData = {
    opd_id: number | string | null;
    pegawai_id: number | string | null;
    penempatan_pegawai_id: number | string | null;
    atasan_pegawai_id: number | string | null;
    tipe_pk: string;
    level_pk: string;
    renstra_opd_id: number | string | null;
    rkpd_id: number | string | null;
    dpa_opd_id: number | string | null;
    lingkup_kinerja_snapshot: string[];
    periode_tahun_id: number | string | null;
    tahun: number | string;
    judul: string;
    nomor_dokumen: string;
    tanggal_dokumen: string;
    tempat_penandatanganan: string;
    status: string;
    catatan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (FormData & { id: number }) | null;
    opdOptions: BasicOption[];
    periodeOptions: PeriodOption[];
    renstraOptions: RenstraOption[];
    rkpdOptions: RkpdOption[];
    dpaOptions: DpaOption[];
    pegawaiOptions: EmployeeOption[];
    placementOptions: PlacementOption[];
    can: { manage_bupati: boolean };
}>();

const initialLevel = props.item?.level_pk || 'kepala_opd';
const form = useForm<FormData>({
    opd_id: props.item?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id : ''),
    pegawai_id: props.item?.pegawai_id ?? '',
    penempatan_pegawai_id: props.item?.penempatan_pegawai_id ?? '',
    atasan_pegawai_id: props.item?.atasan_pegawai_id ?? '',
    tipe_pk: props.item?.tipe_pk ?? (initialLevel === 'individu' ? 'individual' : 'cascading'),
    level_pk: initialLevel,
    renstra_opd_id: props.item?.renstra_opd_id ?? '',
    rkpd_id: props.item?.rkpd_id ?? '',
    dpa_opd_id: props.item?.dpa_opd_id ?? '',
    lingkup_kinerja_snapshot: props.item?.lingkup_kinerja_snapshot ?? [],
    periode_tahun_id: props.item?.periode_tahun_id ?? '',
    tahun: props.item?.tahun ?? new Date().getFullYear(),
    judul: props.item?.judul ?? '',
    nomor_dokumen: props.item?.nomor_dokumen ?? '',
    tanggal_dokumen: props.item?.tanggal_dokumen ?? '',
    tempat_penandatanganan: props.item?.tempat_penandatanganan ?? 'Banjarnegara',
    status: props.item?.status ?? 'draft',
    catatan: props.item?.catatan ?? '',
});

const levels = computed(() => [
    ...(props.can.manage_bupati
        ? [{ value: 'bupati', label: 'PK Bupati', description: 'Tujuan, sasaran, target, dan program ditarik dari RKPD resmi.', icon: Landmark }]
        : []),
    { value: 'kepala_opd', label: 'PK Kepala OPD', description: 'Tujuan, sasaran, dan program OPD ditarik dari Renstra serta DPA.', icon: Building2 },
    {
        value: 'struktural',
        label: 'PK Sek/Kabid/Kabag',
        description: 'Kinerja struktural dari sasaran program atau sasaran kegiatan yang diampu.',
        icon: UserRound,
    },
    {
        value: 'individu',
        label: 'PK Kasi/Kasubbag/JF/Pelaksana',
        description: 'Kinerja kegiatan atau sub kegiatan dari cascading, atau hasil kerja manual.',
        icon: FileCheck2,
    },
]);
const isAutomatic = computed(() => ['bupati', 'kepala_opd'].includes(form.level_pk));
const usesCascadingSelection = computed(() => form.level_pk === 'struktural' || (form.level_pk === 'individu' && form.tipe_pk === 'cascading'));
const isLowerCascading = computed(() => form.level_pk === 'individu' && form.tipe_pk === 'cascading');
const scopeGroups = ref<CascadingScopeGroup[]>([]);
const scopeLoading = ref(false);
const scopeLoadError = ref('');
const scopeSearch = ref('');
let scopeRequest = 0;
let scopeWatcherInitialized = false;
const filteredScopeGroups = computed(() => {
    const query = scopeSearch.value.trim().toLocaleLowerCase('id-ID');
    const availableGroups = isLowerCascading.value
        ? scopeGroups.value.filter((group) => ['opd_kegiatan', 'opd_sub_kegiatan'].includes(group.type))
        : scopeGroups.value;
    if (!query) return availableGroups;

    return availableGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => `${item.label} ${item.context ?? ''}`.toLocaleLowerCase('id-ID').includes(query)),
        }))
        .filter((group) => group.items.length > 0);
});
const filteredRenstra = computed(() => props.renstraOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));
const filteredDpa = computed(() =>
    props.dpaOptions.filter(
        (option) =>
            Number(option.opd_id) === Number(form.opd_id) && (!form.renstra_opd_id || Number(option.renstra_opd_id) === Number(form.renstra_opd_id)),
    ),
);
const placementIsActive = (placement: PlacementOption) => {
    const referenceDate = form.tanggal_dokumen || new Date().toISOString().slice(0, 10);

    return (
        (!placement.tanggal_mulai || placement.tanggal_mulai <= referenceDate) &&
        (!placement.tanggal_selesai || placement.tanggal_selesai >= referenceDate)
    );
};
const employeeHasLevel = (employeeId: number, level: string, opdId?: number | string | null) =>
    props.placementOptions.some(
        (placement) =>
            Number(placement.pegawai_id) === employeeId &&
            placement.level_jabatan === level &&
            placementIsActive(placement) &&
            (opdId === undefined || Number(placement.opd_id) === Number(opdId)),
    );
const employeeHasPlacementInOpd = (employeeId: number, opdId: number | string | null) =>
    props.placementOptions.some(
        (placement) =>
            Number(placement.pegawai_id) === employeeId && Number(placement.opd_id) === Number(opdId) && placementIsActive(placement),
    );
const filteredEmployees = computed(() => {
    if (form.level_pk === 'bupati') return props.pegawaiOptions.filter((employee) => employeeHasLevel(employee.id, 'kepala_daerah'));
    if (form.level_pk === 'kepala_opd')
        return props.pegawaiOptions.filter((employee) => employeeHasLevel(employee.id, 'jpt_pratama', form.opd_id));
    return props.pegawaiOptions.filter(
        (employee) => employeeHasPlacementInOpd(employee.id, form.opd_id) || Number(employee.opd_id) === Number(form.opd_id),
    );
});
const filteredPlacements = computed(() =>
    props.placementOptions.filter((placement) => {
        if (Number(placement.pegawai_id) !== Number(form.pegawai_id)) return false;
        if (!placementIsActive(placement)) return false;
        if (form.level_pk === 'bupati') return placement.level_jabatan === 'kepala_daerah';
        if (Number(placement.opd_id) !== Number(form.opd_id)) return false;
        if (form.level_pk === 'kepala_opd') return placement.level_jabatan === 'jpt_pratama';
        return placement.level_jabatan !== 'kepala_daerah';
    }),
);
const selectedPlacement = computed(() => filteredPlacements.value.find((placement) => Number(placement.id) === Number(form.penempatan_pegawai_id)));
const selectedEmployee = computed(() => props.pegawaiOptions.find((employee) => Number(employee.id) === Number(form.pegawai_id)));
const filteredSupervisors = computed(() => {
    if (form.level_pk === 'bupati') return [];
    const parentJabatanId = Number(selectedPlacement.value?.parent_jabatan_id || 0);

    if (!parentJabatanId) {
        return form.level_pk === 'kepala_opd' ? props.pegawaiOptions.filter((employee) => employeeHasLevel(employee.id, 'kepala_daerah')) : [];
    }

    const supervisorIds = new Set(
        props.placementOptions
            .filter((placement) => placementIsActive(placement) && Number(placement.jabatan_organisasi_id) === parentJabatanId)
            .map((placement) => Number(placement.pegawai_id)),
    );

    return props.pegawaiOptions.filter((employee) => supervisorIds.has(Number(employee.id)) && Number(employee.id) !== Number(form.pegawai_id));
});
const selectedLevel = computed(() => levels.value.find((level) => level.value === form.level_pk));
const automaticTitle = computed(() => {
    const placementName = selectedPlacement.value?.label.split(' · TMT ')[0]?.trim();
    const employeeName = selectedEmployee.value?.label.split(' · NIP ')[0]?.trim();
    const subject = form.level_pk === 'bupati' ? 'Bupati Banjarnegara' : placementName || employeeName || selectedLevel.value?.label || 'Pegawai';

    return `PK ${subject} Tahun ${form.tahun}`;
});

const selectOnlyOption = () => {
    if (filteredEmployees.value.length === 1) form.pegawai_id = filteredEmployees.value[0].id;
    if (filteredSupervisors.value.length === 1) form.atasan_pegawai_id = filteredSupervisors.value[0].id;
};

watch(
    () => form.level_pk,
    (level, previous) => {
        form.tipe_pk = level === 'individu' ? 'individual' : 'cascading';
        if (props.mode === 'create' && level !== previous) {
            form.pegawai_id = '';
            form.penempatan_pegawai_id = '';
            form.atasan_pegawai_id = '';
            form.renstra_opd_id = '';
            form.rkpd_id = '';
            form.dpa_opd_id = '';
            form.lingkup_kinerja_snapshot = [];
        }
        if (level === 'bupati') form.opd_id = '';
        else if (!form.opd_id && props.opdOptions.length === 1) form.opd_id = props.opdOptions[0].id;
        selectOnlyOption();
    },
);
watch(
    () => form.tipe_pk,
    (type, previous) => {
        if (form.level_pk !== 'individu' || type === previous) return;
        form.lingkup_kinerja_snapshot = [];
        if (type === 'individual') form.renstra_opd_id = '';
    },
);
watch(
    () => form.opd_id,
    () => {
        if (!filteredEmployees.value.some((option) => Number(option.id) === Number(form.pegawai_id))) form.pegawai_id = '';
        if (!filteredRenstra.value.some((option) => Number(option.id) === Number(form.renstra_opd_id))) form.renstra_opd_id = '';
        if (!filteredDpa.value.some((option) => Number(option.id) === Number(form.dpa_opd_id))) form.dpa_opd_id = '';
        selectOnlyOption();
    },
);
watch(
    () => form.pegawai_id,
    () => {
        if (!filteredPlacements.value.some((option) => Number(option.id) === Number(form.penempatan_pegawai_id))) form.penempatan_pegawai_id = '';
        if (filteredPlacements.value.length === 1) form.penempatan_pegawai_id = filteredPlacements.value[0].id;
    },
);
watch(
    () => [form.penempatan_pegawai_id, form.tanggal_dokumen] as const,
    () => {
        if (!filteredSupervisors.value.some((option) => Number(option.id) === Number(form.atasan_pegawai_id))) form.atasan_pegawai_id = '';
        if (filteredSupervisors.value.length === 1) form.atasan_pegawai_id = filteredSupervisors.value[0].id;
    },
);
watch(
    () => form.rkpd_id,
    (id) => {
        const source = props.rkpdOptions.find((option) => Number(option.id) === Number(id));
        if (!source) return;
        form.periode_tahun_id = source.periode_tahun_id;
        form.tahun = source.tahun;
    },
);
watch(
    () => form.dpa_opd_id,
    (id) => {
        const source = props.dpaOptions.find((option) => Number(option.id) === Number(id));
        if (!source) return;
        form.periode_tahun_id = source.periode_tahun_id;
        form.tahun = source.tahun;
    },
);
watch(
    () => form.periode_tahun_id,
    (id) => {
        if (isAutomatic.value) return;
        const period = props.periodeOptions.find((option) => Number(option.id) === Number(id));
        if (period) form.tahun = period.tahun;
    },
);
watch(
    () => form.renstra_opd_id,
    async (id, previous) => {
        const requestId = ++scopeRequest;
        if (scopeWatcherInitialized && id !== previous) form.lingkup_kinerja_snapshot = [];
        scopeWatcherInitialized = true;
        scopeGroups.value = [];
        scopeLoadError.value = '';
        scopeLoading.value = false;
        if (!id || !usesCascadingSelection.value) return;

        scopeLoading.value = true;
        try {
            const response = await fetch(route('perjanjian-kinerja.cascading-scope-options', { renstra_opd_id: id }), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Tidak dapat memuat data cascading.');
            const payload = (await response.json()) as { groups?: CascadingScopeGroup[] };
            if (requestId === scopeRequest) scopeGroups.value = payload.groups ?? [];
        } catch (error) {
            if (requestId === scopeRequest) scopeLoadError.value = error instanceof Error ? error.message : 'Tidak dapat memuat data cascading.';
        } finally {
            if (requestId === scopeRequest) scopeLoading.value = false;
        }
    },
    { immediate: true },
);
watch(
    automaticTitle,
    () => {
        form.judul = automaticTitle.value;
    },
    { immediate: true },
);

const submit = () => {
    if (props.mode === 'create') form.post(route('perjanjian-kinerja.store'));
    else if (props.item) form.put(route('perjanjian-kinerja.update', { perjanjian_kinerja: props.item.id }));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Buat Perjanjian Kinerja' : 'Edit Perjanjian Kinerja'" />
    <form class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 lg:p-6" @submit.prevent="submit">
        <header class="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">Penetapan Kinerja</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    {{ mode === 'create' ? 'Buat Perjanjian Kinerja' : 'Edit Perjanjian Kinerja' }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">Pilih level PK dan sumber resmi; sistem menyiapkan matriks serta format dokumennya.</p>
            </div>
            <span class="w-fit rounded-full border bg-muted/40 px-3 py-1 text-xs font-semibold">Status awal: Draft</span>
        </header>

        <section>
            <div class="mb-3 flex items-center gap-2">
                <Sparkles class="size-4 text-primary" />
                <h2 class="text-sm font-bold">Jenis Perjanjian Kinerja</h2>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label
                    v-for="level in levels"
                    :key="level.value"
                    class="group cursor-pointer rounded-xl border p-4 transition-all"
                    :class="
                        form.level_pk === level.value
                            ? 'border-primary bg-primary/[0.06] shadow-sm ring-1 ring-primary/20'
                            : 'bg-card hover:border-primary/35 hover:bg-muted/30'
                    "
                >
                    <input v-model="form.level_pk" type="radio" :value="level.value" class="sr-only" />
                    <component :is="level.icon" class="size-5" :class="form.level_pk === level.value ? 'text-primary' : 'text-muted-foreground'" />
                    <span class="mt-3 block text-sm font-bold">{{ level.label }}</span
                    ><span class="mt-1 block text-xs leading-5 text-muted-foreground">{{ level.description }}</span>
                </label>
            </div>
            <InputError :message="form.errors.level_pk" class="mt-2" />
        </section>

        <section v-if="form.level_pk === 'individu'" class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b bg-muted/25 px-5 py-4">
                <h2 class="text-sm font-bold">Sumber Kinerja Kasi/Kasubbag/JF/Pelaksana</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">Pilih sesuai tugas nyata pegawai pada tahun PK.</p>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                <label class="source-choice" :class="form.tipe_pk === 'cascading' ? 'source-choice--active' : ''">
                    <input v-model="form.tipe_pk" class="sr-only" type="radio" value="cascading" />
                    <Layers3 class="size-5 text-primary" />
                    <span><strong>Cascading</strong><small>Pilih Kegiatan atau Sub Kegiatan Renstra yang memang diampu.</small></span>
                </label>
                <label class="source-choice" :class="form.tipe_pk === 'individual' ? 'source-choice--active' : ''">
                    <input v-model="form.tipe_pk" class="sr-only" type="radio" value="individual" />
                    <FileCheck2 class="size-5 text-primary" />
                    <span><strong>Manual</strong><small>Hasil kerja individu disusun setelah dokumen PK dibuat.</small></span>
                </label>
            </div>
            <InputError :message="form.errors.tipe_pk" class="px-5 pb-4" />
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b bg-muted/25 px-5 py-4">
                <h2 class="text-sm font-bold">Sumber dan Pemilik Dokumen</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">Data sumber hanya menampilkan dokumen resmi yang sudah disetujui atau dikunci.</p>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                <div v-if="form.level_pk !== 'bupati'" class="field">
                    <label for="opd_id">Perangkat Daerah</label
                    ><select id="opd_id" v-model="form.opd_id">
                        <option value="">Pilih perangkat daerah</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.opd_id" />
                </div>
                <div v-if="form.level_pk === 'bupati'" class="field md:col-span-2">
                    <label for="rkpd_id">RKPD sebagai sumber PK Bupati</label
                    ><select id="rkpd_id" v-model="form.rkpd_id">
                        <option value="">Pilih RKPD resmi aktif</option>
                        <option v-for="option in rkpdOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.rkpd_id" />
                </div>
                <div
                    v-if="['kepala_opd', 'struktural'].includes(form.level_pk) || usesCascadingSelection"
                    class="field"
                    :class="usesCascadingSelection ? 'md:col-span-2' : ''"
                >
                    <label for="renstra_opd_id">Renstra OPD</label
                    ><select id="renstra_opd_id" v-model="form.renstra_opd_id">
                        <option value="">Pilih Renstra resmi aktif</option>
                        <option v-for="option in filteredRenstra" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.renstra_opd_id" />
                </div>
                <div v-if="form.level_pk === 'kepala_opd'" class="field">
                    <label for="dpa_opd_id">DPA / DPPA sumber anggaran</label
                    ><select id="dpa_opd_id" v-model="form.dpa_opd_id">
                        <option value="">Pilih DPA/DPPA resmi</option>
                        <option v-for="option in filteredDpa" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.dpa_opd_id" />
                </div>
                <div class="field">
                    <label for="pegawai_id">{{ form.level_pk === 'bupati' ? 'Bupati' : 'Pemilik PK' }}</label
                    ><select id="pegawai_id" v-model="form.pegawai_id">
                        <option value="">Pilih pegawai</option>
                        <option v-for="option in filteredEmployees" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.pegawai_id" />
                </div>
                <div class="field">
                    <label for="penempatan_pegawai_id">Jabatan penandatangan</label
                    ><select id="penempatan_pegawai_id" v-model="form.penempatan_pegawai_id">
                        <option value="">Pilih jabatan aktif</option>
                        <option v-for="option in filteredPlacements" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.penempatan_pegawai_id" />
                </div>
                <div v-if="form.level_pk !== 'bupati'" class="field md:col-span-2">
                    <label for="atasan_pegawai_id">Pihak Kedua / Atasan</label
                    ><select id="atasan_pegawai_id" v-model="form.atasan_pegawai_id">
                        <option value="">Pilih atasan</option>
                        <option v-for="option in filteredSupervisors" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.atasan_pegawai_id" />
                </div>
                <div
                    v-if="isAutomatic"
                    class="flex gap-3 rounded-lg border border-blue-200 bg-blue-50/70 p-3 text-xs leading-5 text-blue-900 dark:border-blue-900/70 dark:bg-blue-950/30 dark:text-blue-200 md:col-span-2"
                >
                    <FileCheck2 class="mt-0.5 size-4 shrink-0" />
                    <p>
                        Matriks tujuan, sasaran, indikator, target, program, dan anggaran akan disalin sebagai snapshot. Data tersebut tidak dapat
                        diedit dari PK sehingga dokumen sumber tetap menjadi satu-satunya acuan.
                    </p>
                </div>
            </div>
        </section>

        <section v-if="usesCascadingSelection" class="overflow-hidden rounded-xl border bg-card">
            <div class="flex flex-col gap-3 border-b bg-muted/25 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold">Lingkup Kinerja yang Diampu</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{
                            isLowerCascading
                                ? 'Pilih Kegiatan atau Sub Kegiatan yang menjadi tanggung jawab pegawai. Indikator dan targetnya akan dibekukan sebagai snapshot PK.'
                                : 'Pilih beberapa item dari Renstra. Indikator dan target tahun berjalan akan dibekukan sebagai snapshot PK.'
                        }}
                    </p>
                </div>
                <label class="relative block sm:w-72">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="scopeSearch"
                        type="search"
                        class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                        placeholder="Cari lingkup kinerja"
                    />
                </label>
            </div>
            <div v-if="!form.renstra_opd_id" class="p-8 text-center text-sm text-muted-foreground">Pilih Renstra OPD terlebih dahulu.</div>
            <div v-else-if="scopeLoading" class="flex items-center justify-center gap-2 p-8 text-sm text-muted-foreground">
                <LoaderCircle class="size-4 animate-spin" /> Memuat cascading Renstra...
            </div>
            <div v-else-if="scopeLoadError" class="p-6 text-center text-sm text-red-600">{{ scopeLoadError }}</div>
            <div v-else-if="filteredScopeGroups.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                Tidak ada item yang sesuai pencarian.
            </div>
            <div v-else class="grid gap-px bg-border lg:grid-cols-2">
                <div v-for="group in filteredScopeGroups" :key="group.type" class="bg-card p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-[0.13em] text-primary">{{ group.label }}</h3>
                        <span class="text-[11px] text-muted-foreground">{{ group.items.length }} item</span>
                    </div>
                    <div class="max-h-72 space-y-1.5 overflow-y-auto pr-1">
                        <label
                            v-for="option in group.items"
                            :key="option.key"
                            class="scope-option"
                            :class="{ 'scope-option--disabled': option.indicator_count === 0 }"
                        >
                            <input
                                v-model="form.lingkup_kinerja_snapshot"
                                type="checkbox"
                                :value="option.key"
                                :disabled="option.indicator_count === 0"
                            />
                            <span class="min-w-0">
                                <strong>{{ option.label }}</strong>
                                <small v-if="option.context">{{ option.context }}</small>
                                <small v-if="option.indicator_count === 0" class="text-amber-700 dark:text-amber-300">Belum memiliki indikator</small>
                            </span>
                            <em>{{ option.indicator_count }} indikator</em>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 border-t bg-muted/15 px-5 py-3 text-xs">
                <span class="text-muted-foreground">Pilihan tersimpan pada PK, bukan pada profil pegawai.</span>
                <strong class="text-primary">{{ form.lingkup_kinerja_snapshot.length }} dipilih</strong>
            </div>
            <InputError :message="form.errors.lingkup_kinerja_snapshot" class="px-5 pb-4" />
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b bg-muted/25 px-5 py-4"><h2 class="text-sm font-bold">Identitas Dokumen</h2></div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                <div class="field">
                    <label for="periode_tahun_id">Periode</label
                    ><select id="periode_tahun_id" v-model="form.periode_tahun_id" :disabled="isAutomatic">
                        <option value="">Pilih periode</option>
                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select
                    ><InputError :message="form.errors.periode_tahun_id" />
                </div>
                <div class="field">
                    <label for="tahun">Tahun PK</label><input id="tahun" v-model="form.tahun" type="number" :readonly="isAutomatic" /><InputError
                        :message="form.errors.tahun"
                    />
                </div>
                <div class="field md:col-span-2">
                    <label for="judul">Judul dokumen <span>(dibuat otomatis)</span></label
                    ><input id="judul" v-model="form.judul" readonly class="cursor-not-allowed bg-muted/35" /><small class="field-hint"
                        >Judul mengikuti jabatan penandatangan dan tahun PK.</small
                    ><InputError :message="form.errors.judul" />
                </div>
                <div class="field">
                    <label for="nomor_dokumen">Nomor dokumen <span>(opsional)</span></label
                    ><input id="nomor_dokumen" v-model="form.nomor_dokumen" placeholder="Nomor Perjanjian Kinerja" /><InputError
                        :message="form.errors.nomor_dokumen"
                    />
                </div>
                <div class="field">
                    <label for="tanggal_dokumen">Tanggal penandatanganan</label
                    ><input id="tanggal_dokumen" v-model="form.tanggal_dokumen" type="date" /><InputError :message="form.errors.tanggal_dokumen" />
                </div>
                <div class="field">
                    <label for="tempat_penandatanganan">Tempat penandatanganan</label
                    ><input id="tempat_penandatanganan" v-model="form.tempat_penandatanganan" /><InputError
                        :message="form.errors.tempat_penandatanganan"
                    />
                </div>
                <div class="field md:col-span-2">
                    <label for="catatan">Catatan <span>(opsional)</span></label
                    ><textarea id="catatan" v-model="form.catatan" rows="3" /><InputError :message="form.errors.catatan" />
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-2 border-t pt-4">
            <Link :href="route('perjanjian-kinerja.index')" class="rounded-lg border px-4 py-2.5 text-sm font-semibold hover:bg-muted">Batal</Link
            ><button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground shadow-sm hover:bg-primary/90 disabled:opacity-60"
            >
                <Save class="size-4" />{{ form.processing ? 'Menyimpan...' : 'Simpan PK' }}
            </button>
        </div>
    </form>
</template>

<style scoped>
.field {
    display: grid;
    gap: 0.4rem;
}
.field label {
    font-size: 0.8rem;
    font-weight: 700;
}
.field label span {
    font-weight: 400;
    color: hsl(var(--muted-foreground));
}
.field-hint {
    color: hsl(var(--muted-foreground));
    font-size: 0.72rem;
    line-height: 1.25rem;
}
.field input,
.field select,
.field textarea {
    width: 100%;
    border: 1px solid hsl(var(--border));
    border-radius: 0.55rem;
    background: hsl(var(--background));
    padding: 0.65rem 0.75rem;
    font-size: 0.875rem;
    outline: none;
}
.field select,
.field input {
    min-height: 2.65rem;
}
.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: hsl(var(--primary));
    box-shadow: 0 0 0 3px hsl(var(--primary) / 0.12);
}
.field input:disabled,
.field select:disabled,
.field input[readonly] {
    background: hsl(var(--muted) / 0.55);
    color: hsl(var(--muted-foreground));
}
.source-choice {
    display: flex;
    min-height: 5rem;
    cursor: pointer;
    align-items: flex-start;
    gap: 0.75rem;
    border: 1px solid hsl(var(--border));
    border-radius: 0.7rem;
    padding: 1rem;
    transition: 160ms ease;
}
.source-choice:hover {
    border-color: hsl(var(--primary) / 0.45);
    background: hsl(var(--muted) / 0.3);
}
.source-choice--active {
    border-color: hsl(var(--primary));
    background: hsl(var(--primary) / 0.06);
    box-shadow: 0 0 0 1px hsl(var(--primary) / 0.18);
}
.source-choice span {
    display: grid;
    gap: 0.2rem;
}
.source-choice strong {
    font-size: 0.875rem;
}
.source-choice small {
    color: hsl(var(--muted-foreground));
    font-size: 0.75rem;
    line-height: 1.25rem;
}
.scope-option {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: start;
    gap: 0.65rem;
    cursor: pointer;
    border: 1px solid transparent;
    border-radius: 0.55rem;
    padding: 0.65rem;
    transition: 140ms ease;
}
.scope-option:hover {
    border-color: hsl(var(--border));
    background: hsl(var(--muted) / 0.35);
}
.scope-option:has(input:checked) {
    border-color: hsl(var(--primary) / 0.45);
    background: hsl(var(--primary) / 0.07);
}
.scope-option input {
    margin-top: 0.18rem;
    accent-color: hsl(var(--primary));
}
.scope-option span {
    display: grid;
    min-width: 0;
    gap: 0.15rem;
}
.scope-option strong {
    font-size: 0.76rem;
    line-height: 1.15rem;
}
.scope-option small {
    overflow: hidden;
    color: hsl(var(--muted-foreground));
    font-size: 0.68rem;
    line-height: 1rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.scope-option em {
    border-radius: 999px;
    background: hsl(var(--muted));
    padding: 0.15rem 0.45rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.62rem;
    font-style: normal;
    white-space: nowrap;
}
.scope-option--disabled {
    cursor: not-allowed;
    opacity: 0.55;
}
@media (max-width: 640px) {
    .scope-option {
        grid-template-columns: auto minmax(0, 1fr);
    }
    .scope-option em {
        grid-column: 2;
        width: fit-content;
    }
}
</style>
