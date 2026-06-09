<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Option = { id: number; label: string; opd_id?: number };
type FormData = {
    opd_id: number | string | null;
    periode_tahun_id: number | string | null;
    perjanjian_kinerja_id: number | string | null;
    realisasi_kinerja_id: number | string | null;
    evaluasi_sakip_id: number | string | null;
    tahun: number | string;
    judul: string;
    nomor_dokumen: string;
    ringkasan_eksekutif: string;
    status: string;
    catatan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (FormData & { id: number }) | null;
    opdOptions: Option[];
    periodeOptions: Option[];
    perjanjianKinerjaOptions: Option[];
    realisasiOptions: Option[];
    evaluasiOptions: Option[];
}>();

const form = useForm<FormData>({
    opd_id: props.item?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id : ''),
    periode_tahun_id: props.item?.periode_tahun_id ?? '',
    perjanjian_kinerja_id: props.item?.perjanjian_kinerja_id ?? '',
    realisasi_kinerja_id: props.item?.realisasi_kinerja_id ?? '',
    evaluasi_sakip_id: props.item?.evaluasi_sakip_id ?? '',
    tahun: props.item?.tahun ?? new Date().getFullYear(),
    judul: props.item?.judul ?? '',
    nomor_dokumen: props.item?.nomor_dokumen ?? '',
    ringkasan_eksekutif: props.item?.ringkasan_eksekutif ?? '',
    status: props.item?.status ?? 'draft',
    catatan: props.item?.catatan ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('lkjip.store'));
        return;
    }

    form.put(route('lkjip.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah LKJIP' : 'Edit LKJIP'" />
    <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah LKJIP' : 'Edit LKJIP' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Isi identitas laporan dan kaitkan dengan data kinerja yang menjadi sumber penyusunan.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Identitas Laporan</h2>
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
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Keterkaitan Data Kinerja</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="perjanjian_kinerja_id">Perjanjian Kinerja</label>
                    <select id="perjanjian_kinerja_id" v-model="form.perjanjian_kinerja_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tidak terhubung</option>
                        <option v-for="option in perjanjianKinerjaOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.perjanjian_kinerja_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="realisasi_kinerja_id">Realisasi Kinerja</label>
                    <select id="realisasi_kinerja_id" v-model="form.realisasi_kinerja_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tidak terhubung</option>
                        <option v-for="option in realisasiOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.realisasi_kinerja_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="evaluasi_sakip_id">Evaluasi SAKIP</label>
                    <select id="evaluasi_sakip_id" v-model="form.evaluasi_sakip_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tidak terhubung</option>
                        <option v-for="option in evaluasiOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.evaluasi_sakip_id" />
                </div>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Narasi Ringkas</h2>
            <div class="mt-4 grid gap-4">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="ringkasan_eksekutif">Ringkasan Eksekutif</label>
                    <textarea
                        id="ringkasan_eksekutif"
                        v-model="form.ringkasan_eksekutif"
                        rows="5"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.ringkasan_eksekutif" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="catatan">Catatan Internal</label>
                    <textarea id="catatan" v-model="form.catatan" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.catatan" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('lkjip.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
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
