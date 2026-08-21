<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import FileSpreadsheet from 'lucide-vue-next/dist/esm/icons/file-spreadsheet.js';
import LoaderCircle from 'lucide-vue-next/dist/esm/icons/loader-circle.js';
import RefreshCw from 'lucide-vue-next/dist/esm/icons/refresh-cw.js';
import AlertTriangle from 'lucide-vue-next/dist/esm/icons/triangle-alert.js';
import UserRound from 'lucide-vue-next/dist/esm/icons/user-round.js';
import { computed, ref } from 'vue';

type PreviewSummary = {
    total_rows?: number;
    valid_rows?: number;
    invalid_rows?: number;
    jabatan_rows?: number;
    pejabat_rows?: number;
    create_rows?: number;
    update_rows?: number;
};
type Batch = {
    id: number;
    status: string;
    original_filename: string;
    file_size: number;
    total_rows: number;
    error_message?: string | null;
    uploaded_by?: { name: string } | null;
    metadata?: { preview?: PreviewSummary; applied?: { jabatan_rows: number; pejabat_rows: number } } | null;
};
type Prepared = {
    action?: string | null;
    nama?: string | null;
    level_jabatan?: string | null;
    opd_label?: string | null;
    unit_label?: string | null;
    parent_label?: string | null;
    jabatan_label?: string | null;
    nama_pejabat?: string | null;
    nip?: string | null;
    jenis_pegawai?: string | null;
    jenis_penugasan?: string | null;
    tanggal_mulai?: string | null;
    tanggal_selesai?: string | null;
    account_label?: string | null;
};
type Row = {
    id: number;
    entity_type: 'jabatan' | 'pejabat';
    sheet: string;
    sheet_row: number;
    status: string;
    prepared: Prepared;
    error_message?: string | null;
};

const props = defineProps<{ batch: Batch; rows: Row[]; can: { manage: boolean } }>();
const applying = ref(false);
const activeType = ref<'semua' | 'jabatan' | 'pejabat'>('semua');
const preview = computed(() => props.batch.metadata?.preview ?? {});
const canApply = computed(
    () => props.can.manage && props.batch.status === 'previewed' && (preview.value.invalid_rows ?? 0) === 0 && (preview.value.valid_rows ?? 0) > 0,
);
const filteredRows = computed(() => (activeType.value === 'semua' ? props.rows : props.rows.filter((row) => row.entity_type === activeType.value)));

const apply = () => {
    applying.value = true;
    router.post(
        route('master.jabatan-organisasi.import.apply', props.batch.id),
        {},
        { preserveScroll: true, onFinish: () => (applying.value = false) },
    );
};

const levelLabel = (value?: string | null) =>
    ({
        kepala_daerah: 'Kepala Daerah',
        jpt_pratama: 'JPT Pratama',
        administrator: 'Administrator',
        pengawas: 'Pengawas',
        fungsional: 'Fungsional',
        pelaksana: 'Pelaksana',
    })[value ?? ''] ?? value;
const employeeTypeLabel = (value?: string | null) =>
    ({ pejabat_negara: 'Pejabat Negara', pns: 'PNS', pppk: 'PPPK', non_asn: 'Non-ASN' })[value ?? ''] ?? value;
const statusLabel = (status: string) =>
    ({ valid: 'Valid', invalid: 'Perlu diperbaiki', imported: 'Diterapkan', previewed: 'Siap ditinjau', failed: 'Gagal' })[status] ?? status;
const statusClass = (status: string) =>
    ({
        valid: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200',
        imported: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200',
        invalid: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-200',
        previewed: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-200',
        failed: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-200',
    })[status] ?? 'bg-muted text-muted-foreground';
</script>

<template>
    <Head title="Preview Import Jabatan Organisasi" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <Link
                    :href="route('master.jabatan-organisasi.import.create')"
                    class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Import Jabatan
                </Link>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">Preview data</h1>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(batch.status)">{{
                        statusLabel(batch.status)
                    }}</span>
                </div>
                <p class="mt-2 text-sm text-muted-foreground">{{ batch.original_filename }} · diunggah oleh {{ batch.uploaded_by?.name || '—' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('master.jabatan-organisasi.import.create')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-card px-4 text-sm font-semibold transition hover:bg-muted"
                >
                    <RefreshCw class="size-4" /> Import baru
                </Link>
                <button
                    v-if="batch.status === 'previewed'"
                    type="button"
                    :disabled="!canApply || applying"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500"
                    @click="apply"
                >
                    <LoaderCircle v-if="applying" class="size-4 animate-spin" />
                    <CheckCircle2 v-else class="size-4" />
                    {{ applying ? 'Menerapkan…' : 'Terapkan import' }}
                </button>
            </div>
        </header>

        <section class="grid gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-3 lg:grid-cols-6">
            <div class="bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total</p>
                <p class="mt-2 text-2xl font-semibold">{{ preview.total_rows ?? 0 }}</p>
            </div>
            <div class="bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Jabatan</p>
                <p class="mt-2 text-2xl font-semibold">{{ preview.jabatan_rows ?? 0 }}</p>
            </div>
            <div class="bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pegawai & penempatan</p>
                <p class="mt-2 text-2xl font-semibold">{{ preview.pejabat_rows ?? 0 }}</p>
            </div>
            <div class="bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Valid</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-300">{{ preview.valid_rows ?? 0 }}</p>
            </div>
            <div class="bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-red-700 dark:text-red-300">Invalid</p>
                <p class="mt-2 text-2xl font-semibold text-red-700 dark:text-red-300">{{ preview.invalid_rows ?? 0 }}</p>
            </div>
            <div class="bg-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Aksi data</p>
                <p class="mt-2 text-sm font-semibold">{{ preview.create_rows ?? 0 }} baru · {{ preview.update_rows ?? 0 }} pembaruan</p>
            </div>
        </section>

        <section
            v-if="batch.error_message || (batch.status === 'previewed' && !canApply)"
            class="flex gap-3 rounded-xl border border-amber-300/70 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <AlertTriangle class="mt-0.5 size-5 shrink-0" />
            <span>{{ batch.error_message || 'Import belum dapat diterapkan. Perbaiki semua baris invalid pada file, kemudian upload ulang.' }}</span>
        </section>
        <section
            v-if="batch.metadata?.applied"
            class="flex gap-3 rounded-xl border border-emerald-300/70 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
        >
            <CheckCircle2 class="size-5 shrink-0" />
            {{ batch.metadata.applied.jabatan_rows }} jabatan dan {{ batch.metadata.applied.pejabat_rows }} penempatan pegawai berhasil diterapkan.
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">Hasil validasi per baris</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Lokasi baris mengikuti nomor pada masing-masing sheet Excel.</p>
                </div>
                <div class="inline-flex w-fit rounded-lg bg-muted p-1 text-xs font-semibold">
                    <button
                        v-for="type in ['semua', 'jabatan', 'pejabat'] as const"
                        :key="type"
                        type="button"
                        class="rounded-md px-3 py-1.5 capitalize transition"
                        :class="activeType === type ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeType = type"
                    >
                        {{ type }}
                    </button>
                </div>
            </div>

            <div class="divide-y">
                <article
                    v-for="row in filteredRows"
                    :key="row.id"
                    class="grid gap-3 px-5 py-4 lg:grid-cols-[120px_minmax(0,1fr)_minmax(260px,0.65fr)] lg:items-start"
                >
                    <div class="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-muted">
                            <Building2 v-if="row.entity_type === 'jabatan'" class="size-4" />
                            <UserRound v-else class="size-4" />
                        </span>
                        <span>{{ row.sheet }}<br />baris {{ row.sheet_row }}</span>
                    </div>
                    <div v-if="row.entity_type === 'jabatan'" class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ row.prepared.nama || 'Data jabatan tidak terbaca' }}</p>
                            <span
                                v-if="row.prepared.action"
                                class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                >{{ row.prepared.action === 'create' ? 'baru' : 'perbarui' }}</span
                            >
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ levelLabel(row.prepared.level_jabatan) || '—' }} · {{ row.prepared.opd_label || '—' }}
                        </p>
                        <p v-if="row.prepared.unit_label || row.prepared.parent_label" class="mt-1 text-xs text-muted-foreground">
                            <template v-if="row.prepared.unit_label">{{ row.prepared.unit_label }}</template
                            ><template v-if="row.prepared.unit_label && row.prepared.parent_label"> · </template
                            ><template v-if="row.prepared.parent_label">Atasan: {{ row.prepared.parent_label }}</template>
                        </p>
                    </div>
                    <div v-else class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ row.prepared.nama_pejabat || 'Data pegawai tidak terbaca' }}</p>
                            <span
                                v-if="row.prepared.action"
                                class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                >{{ row.prepared.action === 'create' ? 'baru' : 'perbarui' }}</span
                            >
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ row.prepared.jabatan_label || '—'
                            }}<template v-if="row.prepared.jenis_pegawai"> · {{ employeeTypeLabel(row.prepared.jenis_pegawai) }}</template
                            ><template v-if="row.prepared.nip"> · NIP {{ row.prepared.nip }}</template>
                        </p>
                        <p v-if="row.prepared.tanggal_mulai" class="mt-1 text-xs text-muted-foreground">
                            {{ row.prepared.jenis_penugasan }} · TMT {{ row.prepared.tanggal_mulai }} s.d.
                            {{ row.prepared.tanggal_selesai || 'sekarang' }}
                        </p>
                    </div>
                    <div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(row.status)">{{
                            statusLabel(row.status)
                        }}</span>
                        <p v-if="row.error_message" class="mt-2 text-xs leading-5 text-red-700 dark:text-red-300">{{ row.error_message }}</p>
                    </div>
                </article>
                <div v-if="filteredRows.length === 0" class="px-5 py-12 text-center text-sm text-muted-foreground">
                    <FileSpreadsheet class="mx-auto mb-3 size-6" /> Tidak ada data pada bagian ini.
                </div>
            </div>
        </section>
    </div>
</template>
