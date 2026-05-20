<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Option = { id: number; label: string };

type RenstraForm = {
    opd_id: number | string | null;
    rpjmd_id: number | string | null;
    periode_tahun_id: number | string | null;
    judul: string;
    nomor_dokumen: string;
    tahun_awal: number | string;
    tahun_akhir: number | string;
    status: string;
    keterangan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    renstra: (RenstraForm & { id: number }) | null;
    opdOptions: Option[];
    rpjmdOptions: Option[];
    periodeOptions: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Renstra OPD', href: '/renstra-opd' },
    { title: props.mode === 'create' ? 'Tambah' : 'Edit', href: '#' },
];

const form = useForm<RenstraForm>({
    opd_id: props.renstra?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id : ''),
    rpjmd_id: props.renstra?.rpjmd_id ?? '',
    periode_tahun_id: props.renstra?.periode_tahun_id ?? '',
    judul: props.renstra?.judul ?? '',
    nomor_dokumen: props.renstra?.nomor_dokumen ?? '',
    tahun_awal: props.renstra?.tahun_awal ?? new Date().getFullYear(),
    tahun_akhir: props.renstra?.tahun_akhir ?? new Date().getFullYear() + 5,
    status: props.renstra?.status ?? 'draft',
    keterangan: props.renstra?.keterangan ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('renstra-opd.store'));
        return;
    }

    form.put(route('renstra-opd.update', props.renstra?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Renstra OPD' : 'Edit Renstra OPD'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Renstra OPD' : 'Edit Renstra OPD' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Hubungkan Renstra OPD dengan OPD dan RPJMD Kabupaten sebelum mengisi cascading.</p>
            </div>

            <section class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Identitas Renstra</h2>
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
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="rpjmd_id">RPJMD Kabupaten</label>
                        <select id="rpjmd_id" v-model="form.rpjmd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Pilih RPJMD</option>
                            <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.rpjmd_id" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="judul">Judul Renstra</label>
                        <input id="judul" v-model="form.judul" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.judul" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="nomor_dokumen">Nomor Dokumen</label>
                        <input id="nomor_dokumen" v-model="form.nomor_dokumen" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.nomor_dokumen" />
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
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="keterangan">Keterangan</label>
                        <textarea id="keterangan" v-model="form.keterangan" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.keterangan" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="route('renstra-opd.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
                <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    Simpan
                </button>
            </div>
        </form>
    </AppLayout>
</template>
