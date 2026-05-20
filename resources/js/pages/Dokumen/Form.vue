<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Option = { id?: number; value?: string; label: string };
type RelationOptions = Record<string, Option[]>;
type FormData = {
    opd_id: number | string | null;
    periode_tahun_id: number | string | null;
    jenis: string;
    judul: string;
    nomor_dokumen: string;
    deskripsi: string;
    status: string;
    file: File | null;
    related_type: string;
    related_id: number | string | null;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    dokumen: (Omit<FormData, 'file' | 'related_type' | 'related_id'> & { id: number }) | null;
    jenisOptions: Option[];
    statusOptions: Option[];
    opdOptions: Option[];
    periodeOptions: Option[];
    relationOptions: RelationOptions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Dokumen', href: '/dokumen' },
    { title: props.mode === 'create' ? 'Unggah' : 'Edit', href: '#' },
];

const form = useForm<FormData>({
    opd_id: props.dokumen?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id || '' : ''),
    periode_tahun_id: props.dokumen?.periode_tahun_id ?? '',
    jenis: props.dokumen?.jenis ?? 'bukti_dukung',
    judul: props.dokumen?.judul ?? '',
    nomor_dokumen: props.dokumen?.nomor_dokumen ?? '',
    deskripsi: props.dokumen?.deskripsi ?? '',
    status: props.dokumen?.status ?? 'draft',
    file: null,
    related_type: '',
    related_id: '',
});

const relationTypeOptions = [
    { value: 'rpjmd', label: 'RPJMD' },
    { value: 'renstra_opd', label: 'Renstra OPD' },
    { value: 'perjanjian_kinerja', label: 'Perjanjian Kinerja' },
    { value: 'rencana_aksi', label: 'Rencana Aksi' },
    { value: 'realisasi_kinerja', label: 'Realisasi Kinerja' },
    { value: 'lkjip', label: 'LKJIP' },
];

const selectedRelationOptions = computed(() => (form.related_type ? props.relationOptions[form.related_type] || [] : []));

const handleFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.file = input.files?.[0] ?? null;
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('dokumen.store'), { forceFormData: true });
        return;
    }

    form.transform((data) => ({
        opd_id: data.opd_id,
        periode_tahun_id: data.periode_tahun_id,
        jenis: data.jenis,
        judul: data.judul,
        nomor_dokumen: data.nomor_dokumen,
        deskripsi: data.deskripsi,
        status: data.status,
    })).put(route('dokumen.update', props.dokumen?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Unggah Dokumen' : 'Edit Dokumen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Unggah Dokumen' : 'Edit Dokumen' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">File disimpan di storage privat dan hanya bisa diunduh lewat otorisasi aplikasi.</p>
            </div>

            <section class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Metadata Dokumen</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="judul">Judul</label>
                        <input id="judul" v-model="form.judul" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.judul" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="nomor_dokumen">Nomor Dokumen</label>
                        <input id="nomor_dokumen" v-model="form.nomor_dokumen" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.nomor_dokumen" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="jenis">Jenis Dokumen</label>
                        <select id="jenis" v-model="form.jenis" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.jenis" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="status">Status</label>
                        <select id="status" v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="opd_id">OPD</label>
                        <select id="opd_id" v-model="form.opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Tidak terkait OPD</option>
                            <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.opd_id" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="periode_tahun_id">Periode</label>
                        <select id="periode_tahun_id" v-model="form.periode_tahun_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Tidak terkait periode</option>
                            <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.periode_tahun_id" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="deskripsi">Deskripsi</label>
                        <textarea id="deskripsi" v-model="form.deskripsi" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.deskripsi" />
                    </div>
                </div>
            </section>

            <section v-if="mode === 'create'" class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">File dan Relasi</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="file">File</label>
                        <input id="file" type="file" class="rounded-md border bg-background px-3 py-2 text-sm" @change="handleFile" />
                        <InputError :message="form.errors.file" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="related_type">Jenis Relasi</label>
                        <select id="related_type" v-model="form.related_type" class="h-9 rounded-md border bg-background px-3 text-sm" @change="form.related_id = ''">
                            <option value="">Tidak dikaitkan</option>
                            <option v-for="option in relationTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.related_type" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="related_id">Data Terkait</label>
                        <select id="related_id" v-model="form.related_id" class="h-9 rounded-md border bg-background px-3 text-sm" :disabled="!form.related_type">
                            <option value="">Pilih data</option>
                            <option v-for="option in selectedRelationOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.related_id" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="route('dokumen.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
                <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    Simpan
                </button>
            </div>
        </form>
    </AppLayout>
</template>
