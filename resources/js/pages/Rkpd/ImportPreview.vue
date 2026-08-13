<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, FileSpreadsheet, LoaderCircle, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Rkpd = { id: number; judul: string; tahun: number };
type Preview = { valid_rows?: number; invalid_rows?: number; skipped_rows?: number };
type Batch = { id: number; status: string; original_filename: string; file_size: number; total_rows: number; metadata?: { columns?: string[]; preview?: Preview; applied?: { imported_rows: number } } | null; error_message?: string | null; uploaded_by?: { name: string } | null };
type Row = { id: number; row_number: number; status: string; cells: Array<string | null>; is_header: boolean; resolved?: { perangkat_daerah_penanggung_jawab?: string | null }; error_message?: string | null };
const props = defineProps<{ rkpd: Rkpd; batch: Batch; rows: Row[]; recentImports: Array<{ id: number; status: string; original_filename: string }>; can: { manage: boolean } }>();
const applying = ref(false);
const columns = computed(() => props.batch.metadata?.columns?.length ? props.batch.metadata.columns : Array.from({ length: Math.max(0, ...props.rows.map(row => row.cells.length)) }, (_, index) => `kolom_${index + 1}`));
const preview = computed(() => props.batch.metadata?.preview ?? {});
const canApply = computed(() => props.can.manage && props.batch.status === 'previewed' && (preview.value.invalid_rows ?? 0) === 0 && (preview.value.valid_rows ?? 0) > 0);
const apply = () => { applying.value = true; router.post(route('rkpd.items.import.apply', [props.rkpd.id, props.batch.id]), {}, { preserveScroll: true, onFinish: () => { applying.value = false; } }); };
const statusClass = (status: string) => ({ valid: 'bg-emerald-100 text-emerald-800', imported: 'bg-emerald-100 text-emerald-800', invalid: 'bg-red-100 text-red-800', skipped: 'bg-slate-100 text-slate-700', previewed: 'bg-blue-100 text-blue-800', processing: 'bg-blue-100 text-blue-800', failed: 'bg-red-100 text-red-800' })[status] ?? 'bg-slate-100 text-slate-700';
</script>

<template>
    <Head :title="`Preview Import RKPD - ${rkpd.tahun}`" />
    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#00336C]">RKPD {{ rkpd.tahun }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-2"><h1 class="text-2xl font-semibold tracking-normal">Preview Import Baris RKPD</h1><span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(batch.status)">{{ batch.status }}</span></div>
                <p class="mt-1 text-sm text-muted-foreground">{{ batch.original_filename }} — periksa seluruh baris sebelum menerapkan.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('rkpd.items.import.create', rkpd.id)" class="document-action document-action--secondary"><Upload class="size-4" /> Import Baru</Link>
                <button v-if="batch.status === 'previewed'" type="button" :disabled="!canApply || applying" class="document-action document-action--primary disabled:cursor-not-allowed disabled:opacity-50" @click="apply"><LoaderCircle v-if="applying" class="size-4 animate-spin" /><CheckCircle2 v-else class="size-4" />{{ applying ? 'Menerapkan…' : 'Terapkan Import' }}</button>
            </div>
        </div>

        <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><span class="text-xs uppercase text-muted-foreground">Total Data</span><strong class="mt-1 block text-xl">{{ Math.max(0, batch.total_rows - 1) }}</strong></div>
            <div><span class="text-xs uppercase text-muted-foreground">Valid</span><strong class="mt-1 block text-xl text-emerald-700">{{ preview.valid_rows ?? 0 }}</strong></div>
            <div><span class="text-xs uppercase text-muted-foreground">Perlu Diperbaiki</span><strong class="mt-1 block text-xl text-red-700">{{ preview.invalid_rows ?? 0 }}</strong></div>
            <div><span class="text-xs uppercase text-muted-foreground">Pengunggah</span><strong class="mt-1 block text-sm">{{ batch.uploaded_by?.name || '—' }}</strong></div>
        </section>

        <section v-if="batch.error_message || (batch.status === 'previewed' && !canApply)" class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"><AlertTriangle class="mt-0.5 size-5 shrink-0" /><span>{{ batch.error_message || 'Import belum dapat diterapkan. Perbaiki seluruh baris yang ditandai tidak valid, lalu unggah ulang file.' }}</span></section>
        <section v-if="batch.metadata?.applied" class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"><CheckCircle2 class="size-5 shrink-0" />{{ batch.metadata.applied.imported_rows }} baris berhasil diterapkan ke RKPD.</section>

        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="flex items-center gap-3 border-b px-5 py-4"><FileSpreadsheet class="size-5 text-[#00336C]" /><div><h2 class="font-semibold">Data yang akan diimport</h2><p class="text-sm text-muted-foreground">Menampilkan hingga 100 baris pertama, beserta hasil validasinya.</p></div></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground"><tr><th class="w-16 px-4 py-3">Baris</th><th class="w-28 px-4 py-3">Status</th><th v-for="column in columns" :key="column" class="px-4 py-3">{{ column }}</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id" class="border-b align-top last:border-0" :class="row.is_header ? 'bg-muted/40 font-semibold' : row.status === 'invalid' ? 'bg-red-50/60 dark:bg-red-950/10' : ''"><td class="px-4 py-3 text-muted-foreground">{{ row.row_number }}</td><td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(row.status)">{{ row.status }}</span><p v-if="row.error_message" class="mt-2 max-w-xs text-xs leading-5 text-red-700 dark:text-red-300">{{ row.error_message }}</p></td><td v-for="(column, index) in columns" :key="`${row.id}-${column}`" class="max-w-xs px-4 py-3"><span class="line-clamp-3">{{ column === 'perangkat_daerah_penanggung_jawab' ? (row.resolved?.perangkat_daerah_penanggung_jawab || row.cells[index] || '—') : (row.cells[index] || '—') }}</span><span v-if="column === 'perangkat_daerah_penanggung_jawab' && !row.cells[index] && row.resolved?.perangkat_daerah_penanggung_jawab" class="mt-1 inline-flex rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-200">Otomatis dari OPD</span></td></tr></tbody></table></div>
        </section>
    </div>
</template>
