<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, FileText, Link2, UploadCloud, X } from 'lucide-vue-next';
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

const selectedJenis = computed(() => props.jenisOptions.find((option) => option.value === form.jenis));
const selectedStatus = computed(() => props.statusOptions.find((option) => option.value === form.status));
const selectedOpd = computed(() => props.opdOptions.find((option) => String(option.id) === String(form.opd_id)));
const selectedPeriode = computed(() => props.periodeOptions.find((option) => String(option.id) === String(form.periode_tahun_id)));
const selectedRelationOptions = computed(() => (form.related_type ? props.relationOptions[form.related_type] || [] : []));
const selectedRelationType = computed(() => relationTypeOptions.find((option) => option.value === form.related_type));
const selectedRelation = computed(() => selectedRelationOptions.value.find((option) => String(option.id) === String(form.related_id)));
const fileName = computed(() => form.file?.name ?? '');
const fileSize = computed(() => (form.file ? formatSize(form.file.size) : ''));
const isCreate = computed(() => props.mode === 'create');

function handleFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    setFile(input.files?.[0] ?? null);
}

function handleDrop(event: DragEvent): void {
    setFile(event.dataTransfer?.files?.[0] ?? null);
}

function setFile(file: File | null): void {
    form.file = file;

    if (file && !form.judul) {
        form.judul = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
    }
}

function clearFile(): void {
    form.file = null;
}

function formatSize(size: number): string {
    if (size >= 1024 * 1024) {
        return `${(size / 1024 / 1024).toFixed(2)} MB`;
    }

    if (size >= 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${size} B`;
}

function submit(): void {
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
}
</script>

<template>
    <Head :title="mode === 'create' ? 'Unggah Dokumen' : 'Edit Dokumen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto flex max-w-7xl flex-col gap-5 p-4" @submit.prevent="submit">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Unggah Dokumen' : 'Edit Dokumen' }}</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                        Pilih jenis dokumen, unggah file, lalu lengkapi OPD dan periode. File tetap disimpan di storage privat dan hanya dapat diakses
                        lewat otorisasi aplikasi.
                    </p>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="grid gap-5">
                    <section class="rounded-lg border bg-card p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-800">
                                <FileText class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">1. Pilih jenis dokumen</h2>
                                <p class="mt-1 text-sm text-muted-foreground">Tentukan kategori dokumen agar tampil di kolom publik yang sesuai.</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="jenis">Jenis dokumen</label>
                                <select id="jenis" v-model="form.jenis" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Untuk halaman publik: Pohon Kinerja, Cascading, IKU, Renstra, Renja/RKT, Rencana Aksi, PK, LKJIP, LHE, dan Tindak
                                    Lanjut akan muncul sesuai menu.
                                </p>
                                <InputError :message="form.errors.jenis" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="status">Status dokumen</label>
                                <select id="status" v-model="form.status" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Draft belum tampil publik. Diajukan berarti menunggu pengecekan admin kabupaten. Tampil publik jika statusnya
                                    terverifikasi, disetujui, atau terkunci.
                                </p>
                                <InputError :message="form.errors.status" />
                            </div>
                        </div>

                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                            <p class="font-semibold">Catatan alur verifikasi</p>
                            <p class="mt-1 leading-6">
                                Untuk sementara, status dokumen masih diubah manual oleh admin yang berwenang. Alur idealnya: OPD mengunggah sebagai
                                Draft/Diajukan, lalu admin kabupaten memverifikasi atau menyetujui sesuai jenis dokumen.
                            </p>
                        </div>
                    </section>

                    <section v-if="isCreate" class="rounded-lg border bg-card p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-sky-50 text-sky-800">
                                <UploadCloud class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">2. Unggah file</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Format umum seperti PDF, gambar, Word, dan spreadsheet dapat diunggah.
                                </p>
                            </div>
                        </div>

                        <label
                            for="file"
                            class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition hover:border-emerald-400 hover:bg-emerald-50/50"
                            @dragover.prevent
                            @drop.prevent="handleDrop"
                        >
                            <UploadCloud class="size-8 text-emerald-700" />
                            <span class="mt-3 text-sm font-semibold text-slate-950">Klik untuk memilih file atau tarik file ke sini</span>
                            <span class="mt-1 text-xs text-muted-foreground">Maksimal 20 MB per dokumen.</span>
                            <input id="file" type="file" class="sr-only" @change="handleFile" />
                        </label>
                        <InputError :message="form.errors.file" />

                        <div v-if="fileName" class="mt-4 flex items-center justify-between gap-3 rounded-lg border bg-background p-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-950">{{ fileName }}</p>
                                <p class="text-xs text-muted-foreground">{{ fileSize }}</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-md border p-2 text-muted-foreground hover:bg-muted"
                                aria-label="Hapus file"
                                @click="clearFile"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </section>

                    <section class="rounded-lg border bg-card p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-amber-50 text-amber-800">
                                <CheckCircle2 class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">{{ isCreate ? '3' : '2' }}. Lengkapi metadata</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Data ini membantu dokumen mudah dicari, difilter, dan ditampilkan di portal publik.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="judul">Judul dokumen</label>
                                <input
                                    id="judul"
                                    v-model="form.judul"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    placeholder="Contoh: Pohon Kinerja 2026"
                                />
                                <InputError :message="form.errors.judul" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="nomor_dokumen">Nomor dokumen</label>
                                <input
                                    id="nomor_dokumen"
                                    v-model="form.nomor_dokumen"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    placeholder="Opsional"
                                />
                                <InputError :message="form.errors.nomor_dokumen" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="opd_id">OPD</label>
                                <select id="opd_id" v-model="form.opd_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option value="">Tidak terkait OPD</option>
                                    <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.opd_id" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="periode_tahun_id">Periode/tahun</label>
                                <select
                                    id="periode_tahun_id"
                                    v-model="form.periode_tahun_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Tidak terkait periode</option>
                                    <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.periode_tahun_id" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <label class="text-sm font-medium" for="deskripsi">Catatan/deskripsi</label>
                                <textarea
                                    id="deskripsi"
                                    v-model="form.deskripsi"
                                    rows="4"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Tambahkan catatan singkat jika diperlukan."
                                />
                                <InputError :message="form.errors.deskripsi" />
                            </div>
                        </div>
                    </section>

                    <section v-if="isCreate" class="rounded-lg border bg-card p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-700">
                                <Link2 class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">4. Kaitkan ke data aplikasi</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Opsional. Gunakan jika dokumen harus menempel ke Renstra, PK, realisasi, atau LKJIP tertentu.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="related_type">Jenis relasi</label>
                                <select
                                    id="related_type"
                                    v-model="form.related_type"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    @change="form.related_id = ''"
                                >
                                    <option value="">Tidak dikaitkan</option>
                                    <option v-for="option in relationTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.related_type" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="related_id">Data terkait</label>
                                <select
                                    id="related_id"
                                    v-model="form.related_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    :disabled="!form.related_type"
                                >
                                    <option value="">Pilih data</option>
                                    <option v-for="option in selectedRelationOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.related_id" />
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="xl:sticky xl:top-24 xl:self-start">
                    <section class="rounded-lg border bg-card p-4 shadow-sm">
                        <h2 class="text-base font-semibold">Ringkasan</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs font-semibold uppercase text-muted-foreground">Jenis</dt>
                                <dd class="mt-1 font-medium">{{ selectedJenis?.label || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-muted-foreground">Status</dt>
                                <dd class="mt-1 font-medium">{{ selectedStatus?.label || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-muted-foreground">OPD</dt>
                                <dd class="mt-1 font-medium">{{ selectedOpd?.label || 'Tidak terkait OPD' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-muted-foreground">Periode</dt>
                                <dd class="mt-1 font-medium">{{ selectedPeriode?.label || 'Tidak terkait periode' }}</dd>
                            </div>
                            <div v-if="isCreate">
                                <dt class="text-xs font-semibold uppercase text-muted-foreground">File</dt>
                                <dd class="mt-1 break-all font-medium">{{ fileName || 'Belum dipilih' }}</dd>
                            </div>
                            <div v-if="isCreate">
                                <dt class="text-xs font-semibold uppercase text-muted-foreground">Relasi</dt>
                                <dd class="mt-1 font-medium">
                                    {{ selectedRelationType?.label || 'Tidak dikaitkan' }}
                                    <span v-if="selectedRelation"> - {{ selectedRelation.label }}</span>
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-5 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                            <p class="font-semibold">Tips publikasi</p>
                            <p class="mt-1 text-xs leading-5">
                                Untuk tampil di halaman publik, pilih jenis dokumen yang sesuai, isi OPD dan periode, lalu gunakan status
                                terverifikasi, disetujui, atau terkunci.
                            </p>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="sticky bottom-0 z-20 -mx-4 mt-1 border-t bg-background/95 px-4 py-3 shadow-[0_-14px_28px_rgba(15,23,42,0.08)] backdrop-blur">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">Pastikan jenis dokumen, OPD, periode, dan file sudah sesuai sebelum disimpan.</p>
                    <div class="flex gap-2">
                        <Link
                            :href="route('dokumen.index')"
                            class="inline-flex h-10 flex-1 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted sm:flex-none"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex h-10 flex-1 items-center justify-center rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60 sm:flex-none"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Dokumen' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
