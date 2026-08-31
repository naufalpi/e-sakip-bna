<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, FileCheck2, Landmark, Save, Sparkles, UserRound } from 'lucide-vue-next';
import { computed, watch } from 'vue';

type BasicOption = { id: number; label: string };
type RenstraOption = BasicOption & { opd_id: number; periode_tahun_id: number; tahun_awal: number; tahun_akhir: number };
type RkpdOption = BasicOption & { periode_tahun_id: number; tahun: number };
type DpaOption = BasicOption & { opd_id: number; periode_tahun_id: number; renja_opd_id?: number | null; renstra_opd_id?: number | null; tahun: number };
type EmployeeOption = BasicOption & { opd_id?: number | null };
type PlacementOption = BasicOption & {
    pegawai_id: number;
    opd_id?: number | null;
    level_jabatan?: string | null;
    parent_jabatan_id?: number | null;
};
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
    periodeOptions: BasicOption[];
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
    { value: 'struktural', label: 'PK Struktural', description: 'Item cascading dipilih sesuai penugasan pegawai.', icon: UserRound },
    { value: 'individu', label: 'PK JF / Pelaksana', description: 'Hasil kerja dibuat manual dan tidak mengambil cascading.', icon: FileCheck2 },
]);
const isAutomatic = computed(() => ['bupati', 'kepala_opd'].includes(form.level_pk));
const filteredRenstra = computed(() => props.renstraOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));
const filteredDpa = computed(() =>
    props.dpaOptions.filter(
        (option) =>
            Number(option.opd_id) === Number(form.opd_id) &&
            (!form.renstra_opd_id || Number(option.renstra_opd_id) === Number(form.renstra_opd_id)),
    ),
);
const employeeHasLevel = (employeeId: number, level: string) =>
    props.placementOptions.some((placement) => Number(placement.pegawai_id) === employeeId && placement.level_jabatan === level);
const filteredEmployees = computed(() => {
    if (form.level_pk === 'bupati') return props.pegawaiOptions.filter((employee) => employeeHasLevel(employee.id, 'kepala_daerah'));
    if (form.level_pk === 'kepala_opd')
        return props.pegawaiOptions.filter(
            (employee) => Number(employee.opd_id) === Number(form.opd_id) && employeeHasLevel(employee.id, 'jpt_pratama'),
        );
    return props.pegawaiOptions.filter((employee) => Number(employee.opd_id) === Number(form.opd_id));
});
const filteredPlacements = computed(() =>
    props.placementOptions.filter((placement) => {
        if (Number(placement.pegawai_id) !== Number(form.pegawai_id)) return false;
        if (form.level_pk === 'bupati') return placement.level_jabatan === 'kepala_daerah';
        if (form.level_pk === 'kepala_opd') return placement.level_jabatan === 'jpt_pratama';
        return placement.level_jabatan !== 'kepala_daerah';
    }),
);
const filteredSupervisors = computed(() => {
    if (form.level_pk === 'bupati') return [];
    if (form.level_pk === 'kepala_opd') return props.pegawaiOptions.filter((employee) => employeeHasLevel(employee.id, 'kepala_daerah'));
    return props.pegawaiOptions.filter(
        (employee) => Number(employee.opd_id) === Number(form.opd_id) && Number(employee.id) !== Number(form.pegawai_id),
    );
});
const selectedLevel = computed(() => levels.value.find((level) => level.value === form.level_pk));

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
        }
        if (level === 'bupati') form.opd_id = '';
        else if (!form.opd_id && props.opdOptions.length === 1) form.opd_id = props.opdOptions[0].id;
        selectOnlyOption();
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
    () => form.rkpd_id,
    (id) => {
        const source = props.rkpdOptions.find((option) => Number(option.id) === Number(id));
        if (!source) return;
        if (source.renstra_opd_id) form.renstra_opd_id = source.renstra_opd_id;
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
    () => [form.level_pk, form.tahun, form.opd_id] as const,
    () => {
        if (props.mode === 'edit' || form.judul.trim()) return;
        const opd = props.opdOptions.find((option) => Number(option.id) === Number(form.opd_id));
        const owner = form.level_pk === 'bupati' ? 'BUPATI BANJARNEGARA' : opd?.label || selectedLevel.value?.label.toUpperCase();
        form.judul = `PERJANJIAN KINERJA ${owner} TAHUN ${form.tahun}`;
    },
    { immediate: true },
);

const submit = () => {
    if (props.mode === 'create') form.post(route('perjanjian-kinerja.store'));
    else form.put(route('perjanjian-kinerja.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Buat Perjanjian Kinerja' : 'Edit Perjanjian Kinerja'" />
    <form class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 lg:p-6" @submit.prevent="submit">
        <header class="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">Penetapan Kinerja</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ mode === 'create' ? 'Buat Perjanjian Kinerja' : 'Edit Perjanjian Kinerja' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Pilih level PK dan sumber resmi; sistem menyiapkan matriks serta format dokumennya.</p>
            </div>
            <span class="w-fit rounded-full border bg-muted/40 px-3 py-1 text-xs font-semibold">Status awal: Draft</span>
        </header>

        <section>
            <div class="mb-3 flex items-center gap-2"><Sparkles class="size-4 text-primary" /><h2 class="text-sm font-bold">Jenis Perjanjian Kinerja</h2></div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label v-for="level in levels" :key="level.value" class="group cursor-pointer rounded-xl border p-4 transition-all" :class="form.level_pk === level.value ? 'border-primary bg-primary/[0.06] shadow-sm ring-1 ring-primary/20' : 'bg-card hover:border-primary/35 hover:bg-muted/30'">
                    <input v-model="form.level_pk" type="radio" :value="level.value" class="sr-only" />
                    <component :is="level.icon" class="size-5" :class="form.level_pk === level.value ? 'text-primary' : 'text-muted-foreground'" />
                    <span class="mt-3 block text-sm font-bold">{{ level.label }}</span><span class="mt-1 block text-xs leading-5 text-muted-foreground">{{ level.description }}</span>
                </label>
            </div>
            <InputError :message="form.errors.level_pk" class="mt-2" />
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b bg-muted/25 px-5 py-4"><h2 class="text-sm font-bold">Sumber dan Pemilik Dokumen</h2><p class="mt-0.5 text-xs text-muted-foreground">Data sumber hanya menampilkan dokumen resmi yang sudah disetujui atau dikunci.</p></div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                <div v-if="form.level_pk !== 'bupati'" class="field"><label for="opd_id">Perangkat Daerah</label><select id="opd_id" v-model="form.opd_id"><option value="">Pilih perangkat daerah</option><option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.opd_id" /></div>
                <div v-if="form.level_pk === 'bupati'" class="field md:col-span-2"><label for="rkpd_id">RKPD sebagai sumber PK Bupati</label><select id="rkpd_id" v-model="form.rkpd_id"><option value="">Pilih RKPD resmi aktif</option><option v-for="option in rkpdOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.rkpd_id" /></div>
                <div v-if="['kepala_opd', 'struktural'].includes(form.level_pk)" class="field" :class="form.level_pk === 'struktural' ? 'md:col-span-2' : ''"><label for="renstra_opd_id">Renstra OPD</label><select id="renstra_opd_id" v-model="form.renstra_opd_id"><option value="">Pilih Renstra resmi aktif</option><option v-for="option in filteredRenstra" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.renstra_opd_id" /></div>
                <div v-if="form.level_pk === 'kepala_opd'" class="field"><label for="dpa_opd_id">DPA / DPPA sumber anggaran</label><select id="dpa_opd_id" v-model="form.dpa_opd_id"><option value="">Pilih DPA/DPPA resmi</option><option v-for="option in filteredDpa" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.dpa_opd_id" /></div>
                <div class="field"><label for="pegawai_id">{{ form.level_pk === 'bupati' ? 'Bupati' : 'Pemilik PK' }}</label><select id="pegawai_id" v-model="form.pegawai_id"><option value="">Pilih pegawai</option><option v-for="option in filteredEmployees" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.pegawai_id" /></div>
                <div class="field"><label for="penempatan_pegawai_id">Jabatan penandatangan</label><select id="penempatan_pegawai_id" v-model="form.penempatan_pegawai_id"><option value="">Pilih jabatan aktif</option><option v-for="option in filteredPlacements" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.penempatan_pegawai_id" /></div>
                <div v-if="form.level_pk !== 'bupati'" class="field md:col-span-2"><label for="atasan_pegawai_id">Pihak Kedua / Atasan</label><select id="atasan_pegawai_id" v-model="form.atasan_pegawai_id"><option value="">Pilih atasan</option><option v-for="option in filteredSupervisors" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.atasan_pegawai_id" /></div>
                <div v-if="isAutomatic" class="md:col-span-2 flex gap-3 rounded-lg border border-blue-200 bg-blue-50/70 p-3 text-xs leading-5 text-blue-900 dark:border-blue-900/70 dark:bg-blue-950/30 dark:text-blue-200"><FileCheck2 class="mt-0.5 size-4 shrink-0" /><p>Matriks tujuan, sasaran, indikator, target, program, dan anggaran akan disalin sebagai snapshot. Data tersebut tidak dapat diedit dari PK sehingga dokumen sumber tetap menjadi satu-satunya acuan.</p></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b bg-muted/25 px-5 py-4"><h2 class="text-sm font-bold">Identitas Dokumen</h2></div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                <div class="field"><label for="periode_tahun_id">Periode</label><select id="periode_tahun_id" v-model="form.periode_tahun_id" :disabled="isAutomatic"><option value="">Pilih periode</option><option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select><InputError :message="form.errors.periode_tahun_id" /></div>
                <div class="field"><label for="tahun">Tahun PK</label><input id="tahun" v-model="form.tahun" type="number" :readonly="isAutomatic" /><InputError :message="form.errors.tahun" /></div>
                <div class="field md:col-span-2"><label for="judul">Judul dokumen</label><input id="judul" v-model="form.judul" /><InputError :message="form.errors.judul" /></div>
                <div class="field"><label for="nomor_dokumen">Nomor dokumen <span>(opsional)</span></label><input id="nomor_dokumen" v-model="form.nomor_dokumen" placeholder="Nomor Perjanjian Kinerja" /><InputError :message="form.errors.nomor_dokumen" /></div>
                <div class="field"><label for="tanggal_dokumen">Tanggal penandatanganan</label><input id="tanggal_dokumen" v-model="form.tanggal_dokumen" type="date" /><InputError :message="form.errors.tanggal_dokumen" /></div>
                <div class="field"><label for="tempat_penandatanganan">Tempat penandatanganan</label><input id="tempat_penandatanganan" v-model="form.tempat_penandatanganan" /><InputError :message="form.errors.tempat_penandatanganan" /></div>
                <div class="field md:col-span-2"><label for="catatan">Catatan <span>(opsional)</span></label><textarea id="catatan" v-model="form.catatan" rows="3" /><InputError :message="form.errors.catatan" /></div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-2 border-t pt-4"><Link :href="route('perjanjian-kinerja.index')" class="rounded-lg border px-4 py-2.5 text-sm font-semibold hover:bg-muted">Batal</Link><button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground shadow-sm hover:bg-primary/90 disabled:opacity-60"><Save class="size-4" />{{ form.processing ? 'Menyimpan...' : 'Simpan PK' }}</button></div>
    </form>
</template>

<style scoped>
.field { display: grid; gap: 0.4rem; }
.field label { font-size: 0.8rem; font-weight: 700; }
.field label span { font-weight: 400; color: hsl(var(--muted-foreground)); }
.field input, .field select, .field textarea { width: 100%; border: 1px solid hsl(var(--border)); border-radius: 0.55rem; background: hsl(var(--background)); padding: 0.65rem 0.75rem; font-size: 0.875rem; outline: none; }
.field select, .field input { min-height: 2.65rem; }
.field input:focus, .field select:focus, .field textarea:focus { border-color: hsl(var(--primary)); box-shadow: 0 0 0 3px hsl(var(--primary) / 0.12); }
.field input:disabled, .field select:disabled, .field input[readonly] { background: hsl(var(--muted) / 0.55); color: hsl(var(--muted-foreground)); }
</style>
