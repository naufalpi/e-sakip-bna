<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Upload } from 'lucide-vue-next';

type RecentImport = {
    id: number;
    status: string;
    original_filename: string;
    total_rows: number;
    preview_rows: number;
    created_at?: string | null;
    uploaded_by?: string | null;
};

defineProps<{
    recentImports: RecentImport[];
}>();

const form = useForm<{ file: File | null }>({
    file: null,
});

const setFile = (event: Event) => {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const submit = () => {
    form.post(route('rpjmd.import.store'), {
        forceFormData: true,
    });
};

const statusClass = (status: string) =>
    ({
        previewed: 'bg-emerald-100 text-emerald-800',
        processing: 'bg-blue-100 text-blue-800',
        failed: 'bg-red-100 text-red-800',
        uploaded: 'bg-slate-100 text-slate-700',
    })[status] ?? 'bg-slate-100 text-slate-700';
</script>

<template>
    <Head title="Import RPJMD" />
    <div class="flex max-w-5xl flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">Import Excel Cascading RPJMD</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Upload file untuk membaca preview baris dan menyimpan log import. Data tidak otomatis masuk ke tabel RPJMD sebelum mapping disepakati.
            </p>
        </div>

        <form class="rounded-lg border bg-card p-4" @submit.prevent="submit">
            <div class="flex items-start gap-3">
                <div class="rounded-md bg-emerald-50 p-2 text-emerald-800">
                    <FileSpreadsheet class="size-5" />
                </div>
                <div class="grid flex-1 gap-4">
                    <div>
                        <h2 class="text-sm font-semibold">File Import</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Gunakan template resmi agar kolom level, uraian, target, anggaran, dan OPD penanggung jawab terbaca konsisten. Format yang
                            didukung: `.xlsx`, `.csv`, atau `.txt` delimited.
                        </p>
                    </div>
                    <div>
                        <a
                            :href="route('rpjmd.import.template')"
                            class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                        >
                            <Download class="size-4" />
                            Download Template RPJMD
                        </a>
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="file">Pilih File</label>
                        <input
                            id="file"
                            type="file"
                            accept=".xlsx,.xls,.csv,.txt"
                            class="block w-full rounded-md border bg-background text-sm file:mr-3 file:h-9 file:border-0 file:bg-muted file:px-3 file:text-sm"
                            @change="setFile"
                        />
                        <InputError :message="form.errors.file" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Link :href="route('rpjmd.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                        >
                            <Upload class="size-4" />
                            Upload dan Preview
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <section class="rounded-lg border bg-card">
            <div class="border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Riwayat Import RPJMD</h2>
            </div>
            <div v-if="recentImports.length" class="divide-y">
                <Link
                    v-for="item in recentImports"
                    :key="item.id"
                    :href="route('rpjmd.import.show', item.id)"
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
            <div v-else class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada batch import RPJMD.</div>
        </section>
    </div>
</template>
