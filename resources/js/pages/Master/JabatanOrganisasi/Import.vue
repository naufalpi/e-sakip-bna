<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import ArrowRight from 'lucide-vue-next/dist/esm/icons/arrow-right.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import Check from 'lucide-vue-next/dist/esm/icons/check.js';
import UploadCloud from 'lucide-vue-next/dist/esm/icons/cloud-upload.js';
import Download from 'lucide-vue-next/dist/esm/icons/download.js';
import FileSpreadsheet from 'lucide-vue-next/dist/esm/icons/file-spreadsheet.js';
import History from 'lucide-vue-next/dist/esm/icons/history.js';
import LoaderCircle from 'lucide-vue-next/dist/esm/icons/loader-circle.js';
import UserRound from 'lucide-vue-next/dist/esm/icons/user-round.js';
import { computed, ref } from 'vue';

type RecentImport = {
    id: number;
    status: string;
    original_filename: string;
    total_rows: number;
    uploaded_by?: string | null;
    created_at?: string | null;
};

defineProps<{ recentImports: RecentImport[] }>();

const form = useForm<{ file: File | null }>({ file: null });
const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

const fileSize = computed(() => {
    if (!form.file) return null;
    return form.file.size >= 1024 * 1024 ? `${(form.file.size / 1024 / 1024).toFixed(1)} MB` : `${Math.ceil(form.file.size / 1024)} KB`;
});

const selectFile = (files?: FileList | null) => {
    form.clearErrors('file');
    form.file = files?.[0] ?? null;
};

const dropFile = (event: DragEvent) => {
    dragging.value = false;
    selectFile(event.dataTransfer?.files);
};

const submit = () => {
    if (!form.file) return;
    form.post(route('master.jabatan-organisasi.import.store'), { forceFormData: true, preserveScroll: true });
};

const statusLabel = (status: string) =>
    ({ previewed: 'Siap ditinjau', imported: 'Diterapkan', processing: 'Memproses', failed: 'Gagal' })[status] ?? status;
const statusClass = (status: string) =>
    ({
        previewed: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-200',
        imported: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200',
        processing: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200',
        failed: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-200',
    })[status] ?? 'bg-muted text-muted-foreground';
</script>

<template>
    <Head title="Import Jabatan Organisasi" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <Link
                    :href="route('master.jabatan-organisasi.index')"
                    class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Master Jabatan Organisasi
                </Link>
                <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">Import struktur dan pegawai</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                    Gunakan template sistem agar struktur atasan dan riwayat masa tugas dapat diperiksa sebelum disimpan.
                </p>
            </div>
            <a
                :href="route('master.jabatan-organisasi.import.template')"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-card px-4 text-sm font-semibold shadow-sm transition hover:bg-muted"
            >
                <Download class="size-4 text-blue-700 dark:text-blue-300" />
                Unduh template Excel
            </a>
        </header>

        <section class="grid overflow-hidden rounded-2xl border bg-card lg:grid-cols-[0.9fr_1.4fr]">
            <div class="border-b bg-muted/30 p-5 lg:border-b-0 lg:border-r lg:p-6">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">
                    <FileSpreadsheet class="size-4" />
                    Isi workbook
                </div>
                <div class="mt-6 space-y-5">
                    <div class="flex gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-800 dark:bg-blue-500/15 dark:text-blue-200"
                        >
                            <Building2 class="size-4" />
                        </span>
                        <div>
                            <p class="font-semibold">Sheet Jabatan</p>
                            <p class="mt-1 text-sm leading-5 text-muted-foreground">
                                Nomenklatur, level, OPD/unit, atasan langsung, eselon, dan urutan.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200"
                        >
                            <UserRound class="size-4" />
                        </span>
                        <div>
                            <p class="font-semibold">Sheet Pegawai</p>
                            <p class="mt-1 text-sm leading-5 text-muted-foreground">
                                Pejabat/JF/Pelaksana, jenis pegawai, penugasan, TMT Jabatan, dan riwayat masa tugas.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-6 border-t pt-5 text-sm text-muted-foreground">
                    <p class="flex gap-2"><Check class="mt-0.5 size-4 shrink-0 text-emerald-600" /> Jabatan dicocokkan dari nama + OPD + unit.</p>
                    <p class="mt-3 flex gap-2"><Check class="mt-0.5 size-4 shrink-0 text-emerald-600" /> Data belum tersimpan pada tahap preview.</p>
                    <p class="mt-3 flex gap-2">
                        <Check class="mt-0.5 size-4 shrink-0 text-emerald-600" /> Akun pengguna dan dasar SK bersifat opsional.
                    </p>
                </div>
            </div>

            <form class="p-5 lg:p-6" @submit.prevent="submit">
                <div
                    class="group flex min-h-64 cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center transition"
                    :class="
                        dragging
                            ? 'border-blue-600 bg-blue-50/70 dark:bg-blue-500/10'
                            : 'border-border bg-background hover:border-blue-500 hover:bg-muted/35'
                    "
                    role="button"
                    tabindex="0"
                    @click="input?.click()"
                    @keydown.enter="input?.click()"
                    @dragenter.prevent="dragging = true"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dropFile"
                >
                    <input ref="input" class="hidden" type="file" accept=".xlsx" @change="selectFile(($event.target as HTMLInputElement).files)" />
                    <span class="flex size-12 items-center justify-center rounded-xl border bg-card shadow-sm">
                        <UploadCloud class="size-5 text-blue-700 transition group-hover:-translate-y-0.5 dark:text-blue-300" />
                    </span>
                    <template v-if="form.file">
                        <p class="mt-4 max-w-full truncate font-semibold">{{ form.file.name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ fileSize }} · klik untuk mengganti file</p>
                    </template>
                    <template v-else>
                        <p class="mt-4 font-semibold">Letakkan file Excel di sini</p>
                        <p class="mt-1 text-sm text-muted-foreground">atau klik untuk memilih · .xlsx maksimal 10 MB</p>
                    </template>
                </div>
                <p v-if="form.errors.file" class="mt-2 text-sm font-medium text-red-600 dark:text-red-300">{{ form.errors.file }}</p>

                <div class="mt-5 flex items-center justify-between gap-4">
                    <p class="hidden text-xs leading-5 text-muted-foreground sm:block">
                        Sistem akan menampilkan setiap kesalahan pada baris terkait.
                    </p>
                    <button
                        type="submit"
                        :disabled="!form.file || form.processing"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500"
                    >
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        <FileSpreadsheet v-else class="size-4" />
                        {{ form.processing ? 'Memvalidasi…' : 'Upload dan preview' }}
                    </button>
                </div>
            </form>
        </section>

        <section v-if="recentImports.length" class="overflow-hidden rounded-xl border bg-card">
            <div class="flex items-center gap-2 border-b px-5 py-4">
                <History class="size-4 text-muted-foreground" />
                <h2 class="font-semibold">Import terakhir</h2>
            </div>
            <Link
                v-for="item in recentImports"
                :key="item.id"
                :href="route('master.jabatan-organisasi.import.show', item.id)"
                class="grid gap-2 border-b px-5 py-3.5 text-sm transition last:border-0 hover:bg-muted/40 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto] sm:items-center"
            >
                <span class="min-w-0">
                    <span class="block truncate font-medium">{{ item.original_filename }}</span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">{{ item.uploaded_by || '—' }} · {{ item.created_at || '—' }}</span>
                </span>
                <span class="text-xs text-muted-foreground">{{ item.total_rows }} data</span>
                <span class="w-fit rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{
                    statusLabel(item.status)
                }}</span>
                <ArrowRight class="hidden size-4 text-muted-foreground sm:block" />
            </Link>
        </section>
    </div>
</template>
