<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Option = { id: number; label: string; opd_id?: number };
type FormData = {
    opd_id: number | string | null;
    perjanjian_kinerja_id: number | string | null;
    rencana_aksi_id: number | string | null;
    periode_tahun_id: number | string | null;
    tahun: number | string;
    periode_realisasi: string;
    triwulan: string;
    bulan: number | string;
    semester: number | string;
    status: string;
    catatan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (FormData & { id: number }) | null;
    opdOptions: Option[];
    periodeOptions: Option[];
    perjanjianKinerjaOptions: Option[];
    rencanaAksiOptions: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Realisasi Kinerja', href: '/realisasi-kinerja' },
    { title: props.mode === 'create' ? 'Tambah' : 'Edit', href: '#' },
];

const form = useForm<FormData>({
    opd_id: props.item?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id : ''),
    perjanjian_kinerja_id: props.item?.perjanjian_kinerja_id ?? '',
    rencana_aksi_id: props.item?.rencana_aksi_id ?? '',
    periode_tahun_id: props.item?.periode_tahun_id ?? '',
    tahun: props.item?.tahun ?? new Date().getFullYear(),
    periode_realisasi: props.item?.periode_realisasi ?? 'triwulan',
    triwulan: props.item?.triwulan ?? '',
    bulan: props.item?.bulan ?? '',
    semester: props.item?.semester ?? '',
    status: props.item?.status ?? 'draft',
    catatan: props.item?.catatan ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('realisasi-kinerja.store'));
        return;
    }

    form.put(route('realisasi-kinerja.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Realisasi Kinerja' : 'Edit Realisasi Kinerja'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Realisasi Kinerja' : 'Edit Realisasi Kinerja' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Isi periode pelaporan sebelum menambahkan realisasi indikator.</p>
            </div>

            <section class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Identitas Realisasi</h2>
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
                            <option value="revision">Revisi</option>
                            <option value="verified">Terverifikasi</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                            <option value="locked">Terkunci</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="perjanjian_kinerja_id">Perjanjian Kinerja</label>
                        <select id="perjanjian_kinerja_id" v-model="form.perjanjian_kinerja_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Tidak terhubung</option>
                            <option v-for="option in perjanjianKinerjaOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.perjanjian_kinerja_id" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="rencana_aksi_id">Rencana Aksi</label>
                        <select id="rencana_aksi_id" v-model="form.rencana_aksi_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Tidak terhubung</option>
                            <option v-for="option in rencanaAksiOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.rencana_aksi_id" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="periode_tahun_id">Periode Tahun</label>
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
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="periode_realisasi">Jenis Periode</label>
                        <select id="periode_realisasi" v-model="form.periode_realisasi" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="bulanan">Bulanan</option>
                            <option value="triwulan">Triwulan</option>
                            <option value="semester">Semester</option>
                            <option value="tahunan">Tahunan</option>
                        </select>
                        <InputError :message="form.errors.periode_realisasi" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="triwulan">Triwulan</label>
                        <select id="triwulan" v-model="form.triwulan" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Tidak ada</option>
                            <option value="tw1">TW1</option>
                            <option value="tw2">TW2</option>
                            <option value="tw3">TW3</option>
                            <option value="tw4">TW4</option>
                        </select>
                        <InputError :message="form.errors.triwulan" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="bulan">Bulan</label>
                        <input id="bulan" v-model="form.bulan" type="number" min="1" max="12" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.bulan" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="semester">Semester</label>
                        <input id="semester" v-model="form.semester" type="number" min="1" max="2" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.semester" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="catatan">Catatan</label>
                        <textarea id="catatan" v-model="form.catatan" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.catatan" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="route('realisasi-kinerja.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
                <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    Simpan
                </button>
            </div>
        </form>
    </AppLayout>
</template>
