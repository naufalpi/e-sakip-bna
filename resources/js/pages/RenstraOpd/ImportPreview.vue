<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, FileSpreadsheet, Upload } from 'lucide-vue-next';
import { computed } from 'vue';

type Batch = {
    id: number;
    module: string;
    import_type?: string | null;
    status: string;
    original_filename: string;
    mime_type?: string | null;
    file_size: number;
    total_rows: number;
    preview_rows: number;
    metadata?: {
        columns?: string[];
        note?: string;
        max_rows?: number;
        applied?: {
            imported_rows: number;
            failed_rows: number;
            skipped_rows: number;
            rolled_back?: boolean;
            applied_at?: string | null;
            renstra_opd_id?: number | null;
        };
    } | null;
    error_message?: string | null;
    created_at?: string | null;
    uploaded_by?: { id: number; name: string } | null;
};

type PreviewRow = {
    id: number;
    row_number: number;
    status: string;
    cells: Array<string | null>;
    mapped: Record<string, string | null>;
    is_header: boolean;
    error_message?: string | null;
};

const props = defineProps<{
    batch: Batch;
    rows: PreviewRow[];
    recentImports: Array<{
        id: number;
        status: string;
        original_filename: string;
        total_rows: number;
        preview_rows: number;
        created_at?: string | null;
        uploaded_by?: string | null;
    }>;
    can: {
        manage: boolean;
    };
}>();

const columns = computed(() => {
    if (props.batch.metadata?.columns?.length) {
        return props.batch.metadata.columns;
    }

    const maxCells = Math.max(0, ...props.rows.map((row) => row.cells.length));

    return Array.from({ length: maxCells }, (_, index) => `kolom_${index + 1}`);
});

const statusClass = (status: string) =>
    ({
        previewed: 'bg-emerald-100 text-emerald-800',
        imported: 'bg-emerald-100 text-emerald-800',
        imported_with_errors: 'bg-amber-100 text-amber-800',
        processing: 'bg-blue-100 text-blue-800',
        failed: 'bg-red-100 text-red-800',
        skipped: 'bg-slate-100 text-slate-700',
        uploaded: 'bg-slate-100 text-slate-700',
    })[status] ?? 'bg-slate-100 text-slate-700';

const formatBytes = (bytes: number) => {
    if (!bytes) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
};
</script>

<template>
    <Head title="Preview Import Renstra OPD" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-normal">Preview Import Renstra OPD</h1>
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(batch.status)">
                        {{ batch.status }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">{{ batch.original_filename }}</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('renstra-opd.index')" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Kembali</Link>
                <Link
                    v-if="can.manage && batch.status === 'previewed'"
                    :href="route('renstra-opd.import.apply', batch.id)"
                    method="post"
                    as="button"
                    class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    <CheckCircle2 class="size-4" />
                    Terapkan Import
                </Link>
                <Link
                    v-if="can.manage"
                    :href="route('renstra-opd.import.create')"
                    class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    <Upload class="size-4" />
                    Import Baru
                </Link>
            </div>
        </div>

        <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-4">
            <div>
                <div class="text-xs uppercase text-muted-foreground">Uploader</div>
                <div class="mt-1 text-sm font-medium">{{ batch.uploaded_by?.name || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Ukuran File</div>
                <div class="mt-1 text-sm font-medium">{{ formatBytes(batch.file_size) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Total Baris</div>
                <div class="mt-1 text-sm font-medium">{{ batch.total_rows }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Preview</div>
                <div class="mt-1 text-sm font-medium">{{ rows.length }} baris pertama</div>
            </div>
        </section>

        <section v-if="batch.error_message" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            {{ batch.error_message }}
        </section>

        <section v-if="batch.metadata?.applied" class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-5">
            <div>
                <div class="text-xs uppercase text-muted-foreground">Baris Valid</div>
                <div class="mt-1 text-sm font-medium">{{ batch.metadata.applied.imported_rows }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Baris Gagal</div>
                <div class="mt-1 text-sm font-medium">{{ batch.metadata.applied.failed_rows }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Baris Dilewati</div>
                <div class="mt-1 text-sm font-medium">{{ batch.metadata.applied.skipped_rows }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Rollback</div>
                <div class="mt-1 text-sm font-medium">{{ batch.metadata.applied.rolled_back ? 'Ya' : 'Tidak' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Renstra</div>
                <Link
                    v-if="batch.metadata.applied.renstra_opd_id"
                    :href="route('renstra-opd.show', batch.metadata.applied.renstra_opd_id)"
                    class="mt-1 inline-flex text-sm font-medium text-emerald-700 hover:underline"
                >
                    Buka Detail
                </Link>
                <div v-else class="mt-1 text-sm font-medium">-</div>
            </div>
        </section>

        <section class="rounded-lg border bg-card">
            <div class="flex items-center gap-2 border-b p-4">
                <FileSpreadsheet class="size-5 text-emerald-700" />
                <div>
                    <h2 class="text-base font-semibold">Raw Rows Preview</h2>
                    <p class="text-sm text-muted-foreground">
                        Status tiap baris diperbarui setelah import diterapkan. Jika ada error, seluruh import dibatalkan.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="w-20 px-4 py-3">Baris</th>
                            <th class="w-28 px-4 py-3">Status</th>
                            <th v-for="column in columns" :key="column" class="px-4 py-3">{{ column }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="border-b last:border-0" :class="row.is_header ? 'bg-slate-50 font-medium' : ''">
                            <td class="px-4 py-3 text-muted-foreground">{{ row.row_number }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">
                                    {{ row.status }}
                                </span>
                                <div v-if="row.error_message" class="mt-1 text-xs text-red-700">{{ row.error_message }}</div>
                            </td>
                            <td v-for="(column, index) in columns" :key="`${row.id}-${column}`" class="max-w-xs px-4 py-3 align-top">
                                <span class="line-clamp-3">{{ row.cells[index] || '-' }}</span>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="columns.length + 2" class="px-4 py-10 text-center text-muted-foreground">
                                Tidak ada baris yang bisa dipreview.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="recentImports.length" class="rounded-lg border bg-card">
            <div class="border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Batch Import Lainnya</h2>
            </div>
            <div class="divide-y">
                <Link
                    v-for="item in recentImports"
                    :key="item.id"
                    :href="route('renstra-opd.import.show', item.id)"
                    class="grid gap-2 px-4 py-3 text-sm hover:bg-muted md:grid-cols-[1fr_140px_120px]"
                >
                    <div>
                        <div class="font-medium">{{ item.original_filename }}</div>
                        <div class="text-xs text-muted-foreground">{{ item.uploaded_by || '-' }} - {{ item.created_at || '-' }}</div>
                    </div>
                    <div class="text-muted-foreground">{{ item.preview_rows }} / {{ item.total_rows }} baris</div>
                    <div>
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(item.status)">
                            {{ item.status }}
                        </span>
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
