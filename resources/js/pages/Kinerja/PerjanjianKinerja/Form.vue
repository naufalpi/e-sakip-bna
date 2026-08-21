<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type Option = { id: number; label: string; opd_id?: number; pegawai_id?: number };
type FormData = {
    opd_id: number | string | null;
    pegawai_id: number | string | null;
    penempatan_pegawai_id: number | string | null;
    atasan_pegawai_id: number | string | null;
    tipe_pk: string;
    renstra_opd_id: number | string | null;
    periode_tahun_id: number | string | null;
    tahun: number | string;
    judul: string;
    nomor_dokumen: string;
    status: string;
    catatan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (FormData & { id: number }) | null;
    opdOptions: Option[];
    periodeOptions: Option[];
    renstraOptions: Option[];
    pegawaiOptions: Option[];
    placementOptions: Option[];
}>();

const form = useForm<FormData>({
    opd_id: props.item?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id : ''),
    pegawai_id: props.item?.pegawai_id ?? '',
    penempatan_pegawai_id: props.item?.penempatan_pegawai_id ?? '',
    atasan_pegawai_id: props.item?.atasan_pegawai_id ?? '',
    tipe_pk: props.item?.tipe_pk ?? 'cascading',
    renstra_opd_id: props.item?.renstra_opd_id ?? '',
    periode_tahun_id: props.item?.periode_tahun_id ?? '',
    tahun: props.item?.tahun ?? new Date().getFullYear(),
    judul: props.item?.judul ?? '',
    nomor_dokumen: props.item?.nomor_dokumen ?? '',
    status: props.item?.status ?? 'draft',
    catatan: props.item?.catatan ?? '',
});

const filteredEmployees = computed(() => props.pegawaiOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));
const filteredPlacements = computed(() => props.placementOptions.filter((option) => Number(option.pegawai_id) === Number(form.pegawai_id)));
const filteredSupervisors = computed(() => filteredEmployees.value.filter((option) => Number(option.id) !== Number(form.pegawai_id)));

watch(
    () => form.opd_id,
    () => {
        if (form.pegawai_id && !filteredEmployees.value.some((option) => Number(option.id) === Number(form.pegawai_id))) form.pegawai_id = '';
        if (form.atasan_pegawai_id && !filteredSupervisors.value.some((option) => Number(option.id) === Number(form.atasan_pegawai_id)))
            form.atasan_pegawai_id = '';
    },
);
watch(
    () => form.pegawai_id,
    () => {
        if (form.penempatan_pegawai_id && !filteredPlacements.value.some((option) => Number(option.id) === Number(form.penempatan_pegawai_id)))
            form.penempatan_pegawai_id = '';
        if (Number(form.atasan_pegawai_id) === Number(form.pegawai_id)) form.atasan_pegawai_id = '';
    },
);
watch(
    () => form.tipe_pk,
    (value) => {
        if (value === 'individual') form.renstra_opd_id = '';
    },
);

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('perjanjian-kinerja.store'));
        return;
    }

    form.put(route('perjanjian-kinerja.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Perjanjian Kinerja' : 'Edit Perjanjian Kinerja'" />
    <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Perjanjian Kinerja' : 'Edit Perjanjian Kinerja' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Isi identitas dokumen sebelum menambahkan item sasaran dan indikator.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Identitas Dokumen</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="opd_id">OPD</label>
                    <select id="opd_id" v-model="form.opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="draft">Draft</option>
                        <option value="submitted">Diajukan</option>
                        <option value="revision">Perlu Perbaikan</option>
                        <option value="verified">Terverifikasi</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                        <option value="locked">Terkunci</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="grid gap-2 border-t pt-4 md:col-span-2">
                    <span class="text-sm font-medium">Jenis Perjanjian Kinerja</span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="cursor-pointer rounded-lg border p-3 transition"
                            :class="form.tipe_pk === 'cascading' ? 'border-blue-600 bg-blue-50/70 dark:bg-blue-950/30' : 'hover:bg-muted/40'"
                        >
                            <input v-model="form.tipe_pk" type="radio" value="cascading" class="sr-only" />
                            <span class="font-semibold">PK Cascading</span>
                            <span class="mt-1 block text-xs leading-5 text-muted-foreground"
                                >Mengambil penugasan pengampu dan diteruskan ke Rencana Aksi serta Pengukuran.</span
                            >
                        </label>
                        <label
                            class="cursor-pointer rounded-lg border p-3 transition"
                            :class="form.tipe_pk === 'individual' ? 'border-cyan-600 bg-cyan-50/70 dark:bg-cyan-950/30' : 'hover:bg-muted/40'"
                        >
                            <input v-model="form.tipe_pk" type="radio" value="individual" class="sr-only" />
                            <span class="font-semibold">PK Individu</span>
                            <span class="mt-1 block text-xs leading-5 text-muted-foreground"
                                >Item diisi manual dan tidak masuk pengukuran organisasi atau Rencana Aksi.</span
                            >
                        </label>
                    </div>
                    <InputError :message="form.errors.tipe_pk" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="pegawai_id">Pemilik PK</label>
                    <select id="pegawai_id" v-model="form.pegawai_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih pegawai</option>
                        <option v-for="option in filteredEmployees" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.pegawai_id" />
                    <p v-if="form.tipe_pk === 'cascading'" class="text-xs text-muted-foreground">
                        Pegawai wajib memiliki penugasan pengampu pada periode yang dipilih.
                    </p>
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="penempatan_pegawai_id">Jabatan / penempatan</label>
                    <select id="penempatan_pegawai_id" v-model="form.penempatan_pegawai_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih penempatan</option>
                        <option v-for="option in filteredPlacements" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.penempatan_pegawai_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="atasan_pegawai_id">Atasan pihak PK</label>
                    <select id="atasan_pegawai_id" v-model="form.atasan_pegawai_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih atasan</option>
                        <option v-for="option in filteredSupervisors" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.atasan_pegawai_id" />
                </div>
                <div v-if="form.tipe_pk === 'cascading'" class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="renstra_opd_id">Renstra OPD</label>
                    <select id="renstra_opd_id" v-model="form.renstra_opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih Renstra OPD</option>
                        <option v-for="option in renstraOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.renstra_opd_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="periode_tahun_id">Periode</label>
                    <select id="periode_tahun_id" v-model="form.periode_tahun_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih periode</option>
                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.periode_tahun_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tahun">Tahun</label>
                    <input id="tahun" v-model="form.tahun" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.tahun" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="judul">Judul</label>
                    <input id="judul" v-model="form.judul" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.judul" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="nomor_dokumen">Nomor Dokumen</label>
                    <input id="nomor_dokumen" v-model="form.nomor_dokumen" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nomor_dokumen" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="catatan">Catatan</label>
                    <textarea id="catatan" v-model="form.catatan" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.catatan" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('perjanjian-kinerja.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
            >
                Simpan
            </button>
        </div>
    </form>
</template>
