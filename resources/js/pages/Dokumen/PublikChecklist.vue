<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import ArrowRight from 'lucide-vue-next/dist/esm/icons/arrow-right.js';
import AlertCircle from 'lucide-vue-next/dist/esm/icons/circle-alert.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import XCircle from 'lucide-vue-next/dist/esm/icons/circle-x.js';
import Clock3 from 'lucide-vue-next/dist/esm/icons/clock-3.js';
import UploadCloud from 'lucide-vue-next/dist/esm/icons/cloud-upload.js';
import Download from 'lucide-vue-next/dist/esm/icons/download.js';
import Eye from 'lucide-vue-next/dist/esm/icons/eye.js';
import FileUp from 'lucide-vue-next/dist/esm/icons/file-up.js';
import FolderCheck from 'lucide-vue-next/dist/esm/icons/folder-check.js';
import Globe2 from 'lucide-vue-next/dist/esm/icons/globe.js';
import { computed, reactive } from 'vue';

type Option = { id: number; label: string };
type Summary = {
    total: number;
    complete: number;
    pending: number;
    needs_upload: number;
    missing: number;
    percent: number;
};
type Opd = { id: number; kode?: string | null; nama: string; singkatan?: string | null; label?: string };
type PublicDocument = {
    id: number;
    judul: string;
    filename: string;
    view_url: string;
    download_url: string;
};
type InternalDocument = {
    id: number;
    judul: string;
    filename: string;
    status: string;
    status_label: string;
    show_url: string;
    download_url: string;
};
type Detail = {
    opd: Opd | null;
    state: string;
    label: string;
    description: string;
    source_label?: string | null;
    public_document?: PublicDocument | null;
    internal_document?: InternalDocument | null;
    upload_url?: string | null;
};
type ChecklistItem = {
    key: string;
    label: string;
    type: 'document' | 'data' | 'score';
    jenis?: string | null;
    state: string;
    summary: Summary;
    details: Detail[];
};
type ChecklistSection = {
    key: string;
    label: string;
    description: string;
    row_count: number;
    summary: Summary;
    items: ChecklistItem[];
};
type ChecklistRow = {
    id: string;
    sectionKey: string;
    sectionLabel: string;
    sectionDescription: string;
    item: ChecklistItem;
    detail: Detail | null;
    state: string;
};

const props = defineProps<{
    filters: { tahun: number; opd_id?: number | null };
    availableYears: number[];
    opdOptions: Option[];
    canSelectOpd: boolean;
    selectedOpd?: Opd | null;
    sections: ChecklistSection[];
    summary: Summary;
    isAggregate: boolean;
    publicUrl: string;
}>();

const filterForm = reactive({
    tahun: String(props.filters.tahun ?? ''),
    opd_id: props.filters.opd_id ? String(props.filters.opd_id) : '',
});

const applyFilters = () =>
    router.get(route('dokumen-publik.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['filters', 'availableYears', 'opdOptions', 'selectedOpd', 'sections', 'summary', 'isAggregate', 'publicUrl'],
    });
useAutoFilters(filterForm, applyFilters, 250);

const stateLabel = (state: string) =>
    ({
        complete: 'Siap publik',
        pending: 'Menunggu',
        draft: 'Draft',
        needs_upload: 'Perlu unggah',
        missing: 'Belum ada',
    })[state] ?? state;

const stateClass = (state: string) =>
    ({
        complete: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        pending: 'border-sky-200 bg-sky-50 text-sky-800',
        draft: 'border-slate-200 bg-slate-50 text-slate-700',
        needs_upload: 'border-amber-200 bg-amber-50 text-amber-900',
        missing: 'border-red-200 bg-red-50 text-red-800',
    })[state] ?? 'border-slate-200 bg-slate-50 text-slate-700';

const StateIcon = (state: string) =>
    ({
        complete: CheckCircle2,
        pending: Clock3,
        draft: FolderCheck,
        needs_upload: AlertCircle,
        missing: XCircle,
    })[state] ?? AlertCircle;

const progressStyle = (summary: Summary) => ({ width: `${Math.min(100, Math.max(0, summary.percent))}%` });
const progressClass = (summary: Summary) => (summary.percent >= 90 ? 'bg-emerald-600' : summary.percent >= 50 ? 'bg-amber-500' : 'bg-red-500');
const firstDetail = (item: ChecklistItem) => item.details[0] ?? null;
const checklistRows = computed<ChecklistRow[]>(() =>
    props.sections.flatMap((section) =>
        section.items.map((item) => {
            const detail = firstDetail(item);

            return {
                id: `${section.key}-${item.key}`,
                sectionKey: section.key,
                sectionLabel: section.label,
                sectionDescription: section.description,
                item,
                detail,
                state: props.isAggregate ? item.state : detail?.state || item.state,
            };
        }),
    ),
);
</script>

<template>
    <Head title="Kelengkapan Dokumen" />

    <div class="flex flex-col gap-5 p-4 lg:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                    <Globe2 class="size-3.5 text-emerald-700" />
                    Portal publik E-SAKIP
                </div>
                <h1 class="mt-3 text-2xl font-semibold tracking-normal text-foreground">Kelengkapan Dokumen</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                    Cek dokumen dan data OPD yang akan tampil pada halaman publik: perencanaan, pengukuran, pelaporan, dan evaluasi.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('dokumen.index')"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted"
                    prefetch="hover"
                    cache-for="2m"
                >
                    Arsip Dokumen
                    <ArrowRight class="size-4" />
                </Link>
                <Link
                    :href="publicUrl"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                    prefetch="hover"
                >
                    Lihat Publik
                    <Eye class="size-4" />
                </Link>
            </div>
        </div>

        <section class="rounded-lg border bg-card p-4 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-[160px_minmax(220px,360px)_1fr] lg:items-end">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tahun">Tahun</label>
                    <select
                        id="tahun"
                        v-model="filterForm.tahun"
                        class="h-10 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    >
                        <option v-for="tahun in availableYears" :key="tahun" :value="String(tahun)">{{ tahun }}</option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="opd_id">Perangkat Daerah</label>
                    <select
                        id="opd_id"
                        v-model="filterForm.opd_id"
                        :disabled="!canSelectOpd"
                        class="h-10 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-muted"
                    >
                        <option v-if="canSelectOpd" value="">Semua OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
                    </select>
                </div>

                <div class="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    Filter berjalan otomatis. Pilih satu OPD untuk melihat tombol unggah per dokumen.
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-lg border bg-card p-5 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">{{ selectedOpd?.label || 'Semua OPD' }}</p>
                        <div class="mt-2 flex items-end gap-3">
                            <span class="text-5xl font-semibold tracking-normal text-foreground">{{ summary.percent }}%</span>
                            <span class="pb-2 text-sm text-muted-foreground">siap tampil publik</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4 md:min-w-[26rem]">
                        <div class="rounded-md border bg-emerald-50 px-3 py-2 text-emerald-900">
                            <div class="text-lg font-semibold">{{ summary.complete }}</div>
                            <div class="text-xs">Siap</div>
                        </div>
                        <div class="rounded-md border bg-sky-50 px-3 py-2 text-sky-900">
                            <div class="text-lg font-semibold">{{ summary.pending }}</div>
                            <div class="text-xs">Menunggu</div>
                        </div>
                        <div class="rounded-md border bg-amber-50 px-3 py-2 text-amber-950">
                            <div class="text-lg font-semibold">{{ summary.needs_upload }}</div>
                            <div class="text-xs">Perlu unggah</div>
                        </div>
                        <div class="rounded-md border bg-red-50 px-3 py-2 text-red-900">
                            <div class="text-lg font-semibold">{{ summary.missing }}</div>
                            <div class="text-xs">Belum ada</div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 h-3 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full transition-all duration-500" :class="progressClass(summary)" :style="progressStyle(summary)" />
                </div>
            </div>

            <div class="rounded-lg border bg-card p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-800">
                        <UploadCloud class="size-5" />
                    </div>
                    <div>
                        <h2 class="font-semibold">Cara pakai untuk OPD</h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            Buka kategori yang berwarna kuning atau merah, lalu unggah dokumen dari tombol yang tersedia. Dokumen tampil publik
                            setelah statusnya terverifikasi, disetujui, atau terkunci.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="isAggregate" class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
            Mode semua OPD menampilkan rekap jumlah OPD yang sudah lengkap. Pilih satu OPD pada filter untuk melihat daftar dokumen yang harus
            diunggah dan tombol aksi langsung.
        </div>

        <section class="overflow-hidden rounded-lg border bg-card shadow-sm">
            <div class="border-b p-4">
                <h2 class="text-base font-semibold">Daftar Kelengkapan</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Tabel ini mengelompokkan komponen dokumen menurut siklus SAKIP agar kekurangan cepat terlihat.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="w-[220px] px-4 py-3">Siklus</th>
                            <th class="w-[220px] px-4 py-3">Komponen</th>
                            <th class="w-[150px] px-4 py-3">Status</th>
                            <th class="px-4 py-3">{{ isAggregate ? 'Progress OPD' : 'Keterangan' }}</th>
                            <th class="w-[180px] px-4 py-3">{{ isAggregate ? 'Rekap' : 'Dokumen Terakhir' }}</th>
                            <th class="w-[190px] px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in checklistRows" :key="row.id" class="border-b last:border-0">
                            <td class="px-4 py-4 align-top">
                                <div class="font-medium text-foreground">{{ row.sectionLabel }}</div>
                                <div class="mt-1 line-clamp-2 text-xs leading-5 text-muted-foreground">{{ row.sectionDescription }}</div>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="flex items-center gap-2 font-medium text-foreground">
                                    <component :is="StateIcon(row.state)" class="size-4 shrink-0" />
                                    {{ row.item.label }}
                                </div>
                                <div class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">
                                    {{ row.item.type === 'document' ? 'Dokumen' : row.item.type === 'score' ? 'Nilai' : 'Data' }}
                                </div>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium" :class="stateClass(row.state)">
                                    {{ stateLabel(row.state) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <template v-if="isAggregate">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm text-muted-foreground">
                                            {{ row.item.summary.complete }} dari {{ row.item.summary.total }} OPD siap publik
                                        </span>
                                        <span class="font-medium">{{ row.item.summary.percent }}%</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="progressClass(row.item.summary)"
                                            :style="progressStyle(row.item.summary)"
                                        />
                                    </div>
                                </template>
                                <template v-else>
                                    <p class="text-sm leading-6 text-muted-foreground">
                                        {{ row.detail?.description || 'Belum ada informasi kelengkapan.' }}
                                    </p>
                                    <p v-if="row.detail?.source_label" class="mt-1 text-xs text-muted-foreground">
                                        Status sumber: {{ row.detail.source_label }}
                                    </p>
                                </template>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <template v-if="isAggregate">
                                    <div class="grid grid-cols-2 gap-1 text-xs">
                                        <span class="rounded bg-emerald-50 px-2 py-1 text-emerald-800">Siap {{ row.item.summary.complete }}</span>
                                        <span class="rounded bg-sky-50 px-2 py-1 text-sky-800">Tunggu {{ row.item.summary.pending }}</span>
                                        <span class="rounded bg-amber-50 px-2 py-1 text-amber-900">Unggah {{ row.item.summary.needs_upload }}</span>
                                        <span class="rounded bg-red-50 px-2 py-1 text-red-800">Kosong {{ row.item.summary.missing }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div v-if="row.detail?.public_document" class="max-w-44">
                                        <div class="truncate font-medium">{{ row.detail.public_document.judul }}</div>
                                        <div class="truncate text-xs text-muted-foreground">{{ row.detail.public_document.filename }}</div>
                                    </div>
                                    <div v-else-if="row.detail?.internal_document" class="max-w-44">
                                        <div class="truncate font-medium">{{ row.detail.internal_document.judul }}</div>
                                        <div class="text-xs text-muted-foreground">{{ row.detail.internal_document.status_label }}</div>
                                    </div>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </template>
                            </td>
                            <td class="px-4 py-4 text-right align-top">
                                <span v-if="isAggregate" class="text-sm text-muted-foreground">Pilih OPD untuk aksi</span>
                                <div v-else-if="row.detail" class="inline-flex flex-wrap justify-end gap-2">
                                    <template v-if="row.detail.public_document">
                                        <a
                                            :href="row.detail.public_document.view_url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm hover:bg-muted"
                                            title="Lihat dokumen publik"
                                        >
                                            <Eye class="size-4" />
                                        </a>
                                        <a
                                            :href="row.detail.public_document.download_url"
                                            class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm hover:bg-muted"
                                            title="Download dokumen publik"
                                        >
                                            <Download class="size-4" />
                                        </a>
                                    </template>

                                    <template v-else-if="row.detail.internal_document">
                                        <Link
                                            :href="row.detail.internal_document.show_url || '#'"
                                            class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm hover:bg-muted"
                                            title="Buka dokumen internal"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                        <a
                                            :href="row.detail.internal_document.download_url"
                                            class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm hover:bg-muted"
                                            title="Download dokumen internal"
                                        >
                                            <Download class="size-4" />
                                        </a>
                                    </template>

                                    <Link
                                        v-if="row.detail.upload_url && row.detail.state !== 'complete'"
                                        :href="row.detail.upload_url || '#'"
                                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                                    >
                                        <FileUp class="size-4" />
                                        Unggah
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
