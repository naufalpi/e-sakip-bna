<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type RpjmdForm = {
    periode_tahun_id: number | string | null;
    judul: string;
    nomor_perda: string;
    tahun_awal: number | string;
    tahun_akhir: number | string;
    struktur_tujuan_mode: string;
    struktur_sasaran_mode: string;
    keterangan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    rpjmd: (RpjmdForm & { id: number }) | null;
    periodeOptions: Array<{ id: number; label: string }>;
}>();

const form = useForm<RpjmdForm>({
    periode_tahun_id: props.rpjmd?.periode_tahun_id ?? '',
    judul: props.rpjmd?.judul ?? '',
    nomor_perda: props.rpjmd?.nomor_perda ?? '',
    tahun_awal: props.rpjmd?.tahun_awal ?? new Date().getFullYear(),
    tahun_akhir: props.rpjmd?.tahun_akhir ?? new Date().getFullYear() + 5,
    struktur_tujuan_mode: props.rpjmd?.struktur_tujuan_mode ?? 'tujuan_lintas_misi',
    struktur_sasaran_mode: props.rpjmd?.struktur_sasaran_mode ?? 'sasaran_langsung_tujuan',
    keterangan: props.rpjmd?.keterangan ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('rpjmd.store'));
        return;
    }

    form.put(route('rpjmd.update', props.rpjmd?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah RPJMD' : 'Edit RPJMD'" />
    <form class="rpjmd-select-scope flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah RPJMD' : 'Edit RPJMD' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Lengkapi identitas dokumen sebelum mengisi cascading perencanaan.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Identitas Dokumen</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="judul">Judul RPJMD</label>
                    <input id="judul" v-model="form.judul" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.judul" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="nomor_perda">Nomor Perda</label>
                    <input id="nomor_perda" v-model="form.nomor_perda" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nomor_perda" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="periode_tahun_id">Periode Referensi</label>
                    <select id="periode_tahun_id" v-model="form.periode_tahun_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih periode</option>
                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.periode_tahun_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tahun_awal">Tahun Awal</label>
                    <input id="tahun_awal" v-model="form.tahun_awal" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.tahun_awal" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tahun_akhir">Tahun Akhir</label>
                    <input id="tahun_akhir" v-model="form.tahun_akhir" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.tahun_akhir" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="struktur_tujuan_mode">Pola Tujuan</label>
                    <select id="struktur_tujuan_mode" v-model="form.struktur_tujuan_mode" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="tujuan_lintas_misi">Tujuan lintas misi</option>
                        <option value="tujuan_per_misi">Tujuan per misi</option>
                    </select>
                    <InputError :message="form.errors.struktur_tujuan_mode" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="struktur_sasaran_mode">Pola Sasaran</label>
                    <select id="struktur_sasaran_mode" v-model="form.struktur_sasaran_mode" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="sasaran_langsung_tujuan">Sasaran langsung ke tujuan</option>
                        <option value="sasaran_melalui_indikator_tujuan">Sasaran melalui indikator tujuan</option>
                        <option value="campuran">Campuran</option>
                    </select>
                    <InputError :message="form.errors.struktur_sasaran_mode" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="keterangan">Keterangan</label>
                    <textarea id="keterangan" v-model="form.keterangan" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.keterangan" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('rpjmd.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
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
