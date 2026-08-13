<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, LoaderCircle, Upload } from 'lucide-vue-next';

type Rkpd = { id: number; judul: string; tahun: number };
type RecentImport = { id: number; status: string; original_filename: string; total_rows: number; uploaded_by?: string | null };

const props = defineProps<{ rkpd: Rkpd; recentImports: RecentImport[] }>();
const form = useForm<{ file: File | null }>({ file: null });
const setFile = (event: Event) => { form.file = (event.target as HTMLInputElement).files?.[0] ?? null; };
const submit = () => form.post(route('rkpd.items.import.store', props.rkpd.id), { forceFormData: true });
const statusClass = (status: string) => ({ previewed: 'bg-emerald-100 text-emerald-800', imported: 'bg-emerald-100 text-emerald-800', processing: 'bg-blue-100 text-blue-800', failed: 'bg-red-100 text-red-800' })[status] ?? 'bg-slate-100 text-slate-700';
</script>

<template>
    <Head :title="`Import Baris RKPD - ${rkpd.tahun}`" />
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#00336C]">RKPD {{ rkpd.tahun }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal">Import Baris RKPD</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ rkpd.judul }} — file divalidasi lebih dulu dan belum mengubah data RKPD.</p>
            </div>
            <Link :href="route('rkpd.show', rkpd.id)" class="document-action document-action--secondary">Kembali ke RKPD</Link>
        </div>

        <form class="overflow-hidden rounded-xl border bg-card shadow-sm" @submit.prevent="submit">
            <div class="border-b bg-[linear-gradient(135deg,#eff6ff,#f8fbff)] px-5 py-4 dark:bg-none">
                <div class="flex items-start gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white"><FileSpreadsheet class="size-5" /></div>
                    <div>
                        <h2 class="text-base font-semibold">Upload untuk validasi</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Gunakan kode OPD dan kode sub kegiatan dari master. Kolom perangkat daerah boleh dikosongkan karena akan terisi otomatis saat preview.</p>
                    </div>
                </div>
            </div>
            <div class="grid gap-5 p-5">
                <div class="flex flex-wrap gap-3">
                    <a :href="route('rkpd.items.import.template', rkpd.id)" class="document-action document-action--secondary"><Download class="size-4" /> Download Template Excel</a>
                    <span class="self-center text-xs text-muted-foreground">Mendukung .xlsx, .csv, atau .txt (maks. 10 MB / 1.000 baris).</span>
                </div>
                <div class="grid gap-2">
                    <label for="rkpd-import-file" class="text-sm font-semibold">Pilih file template yang sudah diisi</label>
                    <input id="rkpd-import-file" type="file" accept=".xlsx,.xls,.csv,.txt" class="block w-full rounded-lg border bg-background text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-4 file:text-sm file:font-semibold dark:file:bg-slate-800" @change="setFile" />
                    <InputError :message="form.errors.file" />
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <Link :href="route('rkpd.show', rkpd.id)" class="document-action document-action--secondary">Batal</Link>
                    <button type="submit" :disabled="form.processing || !form.file" class="document-action document-action--primary disabled:cursor-not-allowed disabled:opacity-60">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        <Upload v-else class="size-4" />
                        {{ form.processing ? 'Memvalidasi file…' : 'Upload dan Preview' }}
                    </button>
                </div>
            </div>
        </form>

        <section v-if="recentImports.length" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b px-5 py-4"><h2 class="text-base font-semibold">Riwayat Import Dokumen Ini</h2></div>
            <Link v-for="item in recentImports" :key="item.id" :href="route('rkpd.items.import.show', [rkpd.id, item.id])" class="grid gap-2 border-b px-5 py-4 text-sm last:border-0 hover:bg-muted/50 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                <span><span class="block font-semibold">{{ item.original_filename }}</span><span class="text-xs text-muted-foreground">{{ item.uploaded_by || '—' }}</span></span>
                <span class="text-muted-foreground">{{ item.total_rows }} baris</span>
                <span class="inline-flex w-fit rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{ item.status }}</span>
            </Link>
        </section>
    </div>
</template>
