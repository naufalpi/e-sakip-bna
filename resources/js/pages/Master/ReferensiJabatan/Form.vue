<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import OrganizationWorkspaceTabs from '@/components/OrganizationWorkspaceTabs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import BookOpenCheck from 'lucide-vue-next/dist/esm/icons/book-open-check.js';
import Info from 'lucide-vue-next/dist/esm/icons/info.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';

type FormData = { kode: string; nama: string; jenis_jabatan: string; jenjang: string; kelas_jabatan: number | string; kualifikasi: string; dasar_hukum: string; berlaku_mulai: string; berlaku_sampai: string; verification_status: string; status: string };
const props = defineProps<{ mode: 'create' | 'edit'; item: (FormData & { id: number; placements_count?: number }) | null; jenisOptions: Array<{ value: string; label: string }> }>();
const form = useForm<FormData>({ kode: props.item?.kode ?? '', nama: props.item?.nama ?? '', jenis_jabatan: props.item?.jenis_jabatan ?? 'fungsional', jenjang: props.item?.jenjang ?? '', kelas_jabatan: props.item?.kelas_jabatan ?? '', kualifikasi: props.item?.kualifikasi ?? '', dasar_hukum: props.item?.dasar_hukum ?? '', berlaku_mulai: props.item?.berlaku_mulai ?? '', berlaku_sampai: props.item?.berlaku_sampai ?? '', verification_status: props.item?.verification_status ?? 'draft', status: props.item?.status ?? 'active' });
const submit = () => props.mode === 'create' ? form.post(route('master.referensi-jabatan.store')) : form.put(route('master.referensi-jabatan.update', props.item?.id));
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Referensi Jabatan' : 'Edit Referensi Jabatan'" />
    <form class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6" @submit.prevent="submit">
        <OrganizationWorkspaceTabs active="references" />
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="flex items-start gap-3"><div class="flex size-11 items-center justify-center rounded-xl bg-blue-800 text-white dark:bg-blue-600"><BookOpenCheck class="size-5" /></div><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Katalog global</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ mode === 'create' ? 'Tambah Referensi Jabatan' : 'Edit Referensi Jabatan' }}</h1><p class="mt-1 text-sm text-muted-foreground">Satu referensi dapat digunakan oleh banyak OPD tanpa mengetik ulang nomenklatur.</p></div></div><Link :href="route('master.referensi-jabatan.index')" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-medium hover:bg-muted"><ArrowLeft class="size-4" /> Kembali</Link></header>

        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-950"><Info class="mt-0.5 size-4 shrink-0" /><p>Gunakan nomenklatur dan dasar hukum resmi. Hanya referensi berstatus <strong>Terverifikasi</strong> yang dapat dipilih Admin OPD.</p></div>

        <section class="overflow-hidden rounded-xl border bg-card"><div class="border-b px-5 py-4"><h2 class="font-semibold">Identitas jabatan</h2><p class="mt-1 text-xs text-muted-foreground">Nama, jenis, dan jenjang membentuk identitas unik referensi.</p></div><div class="grid gap-5 p-5 md:grid-cols-2">
            <div class="grid gap-2 md:col-span-2"><label for="nama" class="text-sm font-medium">Nama jabatan <span class="text-red-600">*</span></label><input id="nama" v-model="form.nama" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: Pranata Komputer Ahli Pertama" /><InputError :message="form.errors.nama" /></div>
            <div class="grid gap-2"><label for="jenis" class="text-sm font-medium">Jenis jabatan <span class="text-red-600">*</span></label><select id="jenis" v-model="form.jenis_jabatan" class="h-10 rounded-lg border bg-background px-3 text-sm"><option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select><InputError :message="form.errors.jenis_jabatan" /></div>
            <div class="grid gap-2"><label for="kode" class="text-sm font-medium">Kode referensi</label><input id="kode" v-model="form.kode" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Jika tersedia" /><InputError :message="form.errors.kode" /></div>
            <div class="grid gap-2"><label for="jenjang" class="text-sm font-medium">Jenjang</label><input id="jenjang" v-model="form.jenjang" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Terampil, Ahli Pertama, dan lainnya" /><InputError :message="form.errors.jenjang" /></div>
            <div class="grid gap-2"><label for="kelas" class="text-sm font-medium">Kelas jabatan</label><input id="kelas" v-model="form.kelas_jabatan" type="number" min="1" max="17" class="h-10 rounded-lg border bg-background px-3 text-sm" /><InputError :message="form.errors.kelas_jabatan" /></div>
            <div class="grid gap-2 md:col-span-2"><label for="kualifikasi" class="text-sm font-medium">Kualifikasi</label><textarea id="kualifikasi" v-model="form.kualifikasi" rows="3" class="rounded-lg border bg-background px-3 py-2 text-sm" placeholder="Kualifikasi pendidikan atau kompetensi, jika perlu" /><InputError :message="form.errors.kualifikasi" /></div>
            <div class="grid gap-2 md:col-span-2"><label for="dasar_hukum" class="text-sm font-medium">Dasar hukum / sumber referensi</label><textarea id="dasar_hukum" v-model="form.dasar_hukum" rows="3" class="rounded-lg border bg-background px-3 py-2 text-sm" placeholder="Peraturan, keputusan, atau sumber resmi nomenklatur" /><InputError :message="form.errors.dasar_hukum" /></div>
        </div></section>

        <section class="overflow-hidden rounded-xl border bg-card"><div class="border-b px-5 py-4"><h2 class="font-semibold">Masa berlaku dan validasi</h2></div><div class="grid gap-5 p-5 md:grid-cols-2">
            <div class="grid gap-2"><label for="mulai" class="text-sm font-medium">Berlaku mulai</label><input id="mulai" v-model="form.berlaku_mulai" type="date" class="h-10 rounded-lg border bg-background px-3 text-sm" /><InputError :message="form.errors.berlaku_mulai" /></div>
            <div class="grid gap-2"><label for="sampai" class="text-sm font-medium">Berlaku sampai</label><input id="sampai" v-model="form.berlaku_sampai" type="date" class="h-10 rounded-lg border bg-background px-3 text-sm" /><InputError :message="form.errors.berlaku_sampai" /></div>
            <div class="grid gap-2"><label for="validasi" class="text-sm font-medium">Validasi <span class="text-red-600">*</span></label><select id="validasi" v-model="form.verification_status" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="draft">Perlu validasi</option><option value="verified">Terverifikasi</option></select><InputError :message="form.errors.verification_status" /></div>
            <div class="grid gap-2"><label for="status" class="text-sm font-medium">Status <span class="text-red-600">*</span></label><select id="status" v-model="form.status" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select><InputError :message="form.errors.status" /></div>
        </div></section>

        <div class="flex justify-end gap-2 border-t pt-4"><Link :href="route('master.referensi-jabatan.index')" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm font-medium hover:bg-muted">Batal</Link><button type="submit" :disabled="form.processing" class="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-800 px-5 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-60 dark:bg-blue-600"><Save class="size-4" />{{ form.processing ? 'Menyimpan...' : 'Simpan Referensi' }}</button></div>
    </form>
</template>
