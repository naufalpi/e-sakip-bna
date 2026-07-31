<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, GitCompareArrows, Plus, RefreshCw, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type SyncPayload = {
    kode?: string | null;
    nama?: string | null;
    indikator?: string | null;
    target?: string | number | null;
    pagu_indikatif?: string | number | null;
    lokasi?: string | null;
    sumber_dana?: string | null;
    prioritas_nasional?: string | null;
    prioritas_daerah?: string | null;
    kelompok_sasaran?: string | null;
    labels?: {
        opd?: string | null;
        opd_unit?: string | null;
        program?: string | null;
        kegiatan?: string | null;
        sub_kegiatan?: string | null;
    };
};

type SyncDiff = {
    field: string;
    label: string;
    source?: string | number | null;
    target?: string | number | null;
};

type SyncRow = {
    id: number;
    action: 'create' | 'update' | 'unchanged' | 'target_only' | 'skipped';
    selected: boolean;
    status: string;
    message?: string | null;
    diff_values?: {
        source?: SyncPayload | null;
        target?: SyncPayload | null;
        fields?: SyncDiff[];
    } | null;
};

type SyncPreview = {
    id: number;
    source_module: string;
    target_module: string;
    tahun: number;
    status: string;
    summary: Record<string, number>;
    rows: SyncRow[];
};

const props = defineProps<{
    preview?: SyncPreview | null;
    canManage: boolean;
    title: string;
    description: string;
    previewRoute: string;
    applyRoute?: string | null;
    previewLabel: string;
    applyLabel: string;
}>();

const selectedRows = ref<number[]>([]);
const previewForm = useForm({});
const applyForm = useForm({
    selected_rows: [] as number[],
});

const selectableRows = computed(() => props.preview?.rows.filter((row) => ['create', 'update'].includes(row.action)) ?? []);
const selectedCount = computed(() => selectedRows.value.length);

watch(
    () => props.preview,
    (preview) => {
        selectedRows.value = preview?.rows.filter((row) => row.selected && ['create', 'update'].includes(row.action)).map((row) => row.id) ?? [];
    },
    { immediate: true },
);

const postPreview = () => {
    previewForm.post(props.previewRoute, {
        preserveScroll: true,
    });
};

const applySelected = () => {
    if (!props.applyRoute || selectedRows.value.length === 0) {
        return;
    }

    applyForm.selected_rows = [...selectedRows.value];
    applyForm.post(props.applyRoute, {
        preserveScroll: true,
    });
};

const toggleRow = (id: number) => {
    selectedRows.value = selectedRows.value.includes(id) ? selectedRows.value.filter((rowId) => rowId !== id) : [...selectedRows.value, id];
};

const toggleAll = () => {
    if (selectedRows.value.length === selectableRows.value.length) {
        selectedRows.value = [];
        return;
    }

    selectedRows.value = selectableRows.value.map((row) => row.id);
};

const actionLabel = (action: SyncRow['action']) =>
    ({
        create: 'Baru',
        update: 'Berbeda',
        unchanged: 'Sama',
        target_only: 'Hanya target',
        skipped: 'Dilewati',
    })[action];

const actionClass = (action: SyncRow['action']) =>
    ({
        create: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        update: 'bg-amber-50 text-amber-700 ring-amber-100',
        unchanged: 'bg-slate-100 text-slate-600 ring-slate-200',
        target_only: 'bg-rose-50 text-rose-700 ring-rose-100',
        skipped: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
    })[action];

const payloadFor = (row: SyncRow) => row.diff_values?.source ?? row.diff_values?.target ?? {};
const payloadTitle = (row: SyncRow) => {
    const payload = payloadFor(row);
    return payload.labels?.sub_kegiatan || payload.nama || payload.indikator || '-';
};
const payloadMeta = (row: SyncRow) => {
    const payload = payloadFor(row);
    return [payload.labels?.opd, payload.labels?.opd_unit, payload.labels?.program, payload.labels?.kegiatan].filter(Boolean).join(' / ') || '-';
};
const cellValue = (value?: string | number | null) => (value === null || value === undefined || String(value).trim() === '' ? '-' : value);
const visibleDiffs = (row: SyncRow) => row.diff_values?.fields?.slice(0, 4) ?? [];
const diffOverflow = (row: SyncRow) => Math.max((row.diff_values?.fields?.length ?? 0) - 4, 0);
</script>

<template>
    <section v-if="canManage" class="overflow-hidden rounded-xl border bg-card shadow-sm">
        <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#eef7ff)] px-5 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white">
                        <GitCompareArrows class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold">{{ title }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#b8cbe0] bg-white px-4 text-sm font-semibold text-[#00336C] shadow-sm transition hover:bg-sky-50 disabled:opacity-60"
                    :disabled="previewForm.processing"
                    @click="postPreview"
                >
                    <RefreshCw class="size-4" :class="{ 'animate-spin': previewForm.processing }" />
                    {{ previewLabel }}
                </button>
            </div>
        </div>

        <div v-if="preview" class="grid gap-4 p-5">
            <div class="grid gap-2 md:grid-cols-4">
                <article class="rounded-xl border border-emerald-100 bg-emerald-50 p-3">
                    <p class="text-xs font-semibold uppercase text-emerald-700">Baru</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ preview.summary.create ?? 0 }}</p>
                </article>
                <article class="rounded-xl border border-amber-100 bg-amber-50 p-3">
                    <p class="text-xs font-semibold uppercase text-amber-700">Berbeda</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ preview.summary.update ?? 0 }}</p>
                </article>
                <article class="rounded-xl border bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase text-slate-600">Sama</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ preview.summary.unchanged ?? 0 }}</p>
                </article>
                <article class="rounded-xl border border-rose-100 bg-rose-50 p-3">
                    <p class="text-xs font-semibold uppercase text-rose-700">Hanya target</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-900">{{ preview.summary.target_only ?? 0 }}</p>
                </article>
            </div>

            <div class="overflow-hidden rounded-xl border">
                <div class="flex flex-col gap-3 border-b bg-slate-50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        :disabled="selectableRows.length === 0"
                        @click="toggleAll"
                    >
                        <CheckCircle2 class="size-4 text-[#00336C]" />
                        {{ selectedRows.length === selectableRows.length && selectableRows.length > 0 ? 'Kosongkan pilihan' : 'Pilih semua perubahan' }}
                    </button>
                    <div class="text-sm text-muted-foreground">
                        <span class="font-semibold text-[#00336C]">{{ selectedCount }}</span> dari {{ selectableRows.length }} perubahan dipilih
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1180px] text-left text-sm">
                        <thead class="bg-[#eaf4ff] text-xs uppercase text-[#00336C]">
                            <tr>
                                <th class="w-14 px-4 py-3 text-center">Pilih</th>
                                <th class="w-28 px-4 py-3">Status</th>
                                <th class="w-[420px] px-4 py-3">Baris</th>
                                <th class="px-4 py-3">Perbedaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in preview.rows" :key="row.id" class="border-t align-top hover:bg-sky-50/40">
                                <td class="px-4 py-4 text-center">
                                    <input
                                        v-if="['create', 'update'].includes(row.action)"
                                        type="checkbox"
                                        class="size-4 rounded border-slate-300 text-[#00336C] focus:ring-[#00336C]"
                                        :checked="selectedRows.includes(row.id)"
                                        @change="toggleRow(row.id)"
                                    />
                                    <XCircle v-else-if="row.action === 'target_only'" class="mx-auto size-4 text-rose-500" />
                                    <CheckCircle2 v-else class="mx-auto size-4 text-slate-400" />
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="actionClass(row.action)">
                                        {{ actionLabel(row.action) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-950">{{ payloadTitle(row) }}</p>
                                    <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ payloadMeta(row) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div v-if="row.action === 'create'" class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">
                                        <Plus class="size-4" />
                                        Akan dibuat dari sumber
                                    </div>
                                    <div v-else-if="row.action === 'target_only'" class="inline-flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800">
                                        <AlertTriangle class="size-4" />
                                        {{ row.message || 'Baris hanya ada di target' }}
                                    </div>
                                    <div v-else-if="row.action === 'unchanged'" class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">
                                        <CheckCircle2 class="size-4" />
                                        Tidak ada perubahan
                                    </div>
                                    <div v-else class="grid gap-2">
                                        <div v-for="diff in visibleDiffs(row)" :key="`${row.id}-${diff.field}`" class="rounded-lg border bg-white px-3 py-2">
                                            <p class="text-xs font-semibold uppercase text-muted-foreground">{{ diff.label }}</p>
                                            <div class="mt-1 grid gap-2 text-xs sm:grid-cols-2">
                                                <div>
                                                    <span class="text-muted-foreground">Sumber:</span>
                                                    <span class="ml-1 font-semibold text-slate-900">{{ cellValue(diff.source) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-muted-foreground">Target:</span>
                                                    <span class="ml-1 font-semibold text-slate-900">{{ cellValue(diff.target) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <p v-if="diffOverflow(row) > 0" class="text-xs font-semibold text-muted-foreground">
                                            +{{ diffOverflow(row) }} kolom lain berbeda
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="preview.rows.length === 0">
                                <td colspan="4" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                    Tidak ada baris sumber yang bisa disinkronkan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end rounded-xl border bg-slate-50 px-4 py-3">
                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002855] disabled:opacity-60"
                    :disabled="!applyRoute || selectedRows.length === 0 || applyForm.processing"
                    @click="applySelected"
                >
                    <CheckCircle2 class="size-4" />
                    {{ applyLabel }}
                </button>
            </div>
        </div>
    </section>
</template>
