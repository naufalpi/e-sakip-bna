<script setup lang="ts">
import OrganizationWorkspaceTabs from '@/components/OrganizationWorkspaceTabs.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import BadgeCheck from 'lucide-vue-next/dist/esm/icons/badge-check.js';
import BookOpenCheck from 'lucide-vue-next/dist/esm/icons/book-open-check.js';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import { reactive } from 'vue';

type Reference = {
    id: number;
    kode?: string | null;
    nama: string;
    jenis_jabatan: string;
    jenis_label: string;
    jenjang?: string | null;
    kelas_jabatan?: number | null;
    dasar_hukum?: string | null;
    verification_status: 'draft' | 'verified';
    verification_label: string;
    status: string;
    placements_count: number;
};
type Paginator<T> = { data: T[]; current_page: number; last_page: number; from: number | null; to: number | null; total: number; prev_page_url: string | null; next_page_url: string | null };

const props = defineProps<{
    items: Paginator<Reference>;
    filters: { search?: string; jenis_jabatan?: string; verification_status?: string; status?: string };
    jenisOptions: Array<{ value: string; label: string }>;
    stats: { total: number; verified: number; draft: number; active: number };
    can: { manage: boolean };
}>();

const form = reactive({
    search: props.filters.search ?? '',
    jenis_jabatan: props.filters.jenis_jabatan ?? '',
    verification_status: props.filters.verification_status ?? '',
    status: props.filters.status ?? '',
});
const applyFilters = () => router.get(route('master.referensi-jabatan.index'), form, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(form, applyFilters);
const reset = () => { Object.assign(form, { search: '', jenis_jabatan: '', verification_status: '', status: '' }); applyFiltersNow(); };
const destroy = async (item: Reference) => {
    if (await confirmDelete(`Hapus referensi ${item.nama}?`)) router.delete(route('master.referensi-jabatan.destroy', item.id));
};
</script>

<template>
    <Head title="Referensi Jabatan" />
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6">
        <OrganizationWorkspaceTabs active="references" />

        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-800 text-white dark:bg-blue-600"><BookOpenCheck class="size-5" /></div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">Katalog global</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">Referensi Jabatan</h1>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">Nomenklatur baku jabatan fungsional dan pelaksana yang dapat digunakan seluruh OPD.</p>
                </div>
            </div>
            <Link v-if="can.manage" :href="route('master.referensi-jabatan.create')" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white hover:bg-blue-900 dark:bg-blue-600"><Plus class="size-4" /> Tambah Referensi</Link>
        </header>

        <section class="grid overflow-hidden rounded-xl border bg-card sm:grid-cols-4">
            <div v-for="stat in [{label:'Total',value:stats.total},{label:'Terverifikasi',value:stats.verified},{label:'Perlu validasi',value:stats.draft},{label:'Aktif',value:stats.active}]" :key="stat.label" class="border-b p-4 last:border-0 sm:border-b-0 sm:border-r sm:last:border-r-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ stat.label }}</p><p class="mt-2 text-2xl font-semibold tabular-nums">{{ stat.value }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <form class="grid gap-3 border-b bg-muted/20 p-4 lg:grid-cols-[minmax(260px,1fr)_220px_180px_160px_auto]" @submit.prevent="applyFiltersNow">
                <div class="relative"><Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><input v-model="form.search" type="search" class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm" placeholder="Cari kode, nama, jenjang, atau dasar hukum" /></div>
                <select v-model="form.jenis_jabatan" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">Semua jenis</option><option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <select v-model="form.verification_status" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">Semua validasi</option><option value="verified">Terverifikasi</option><option value="draft">Perlu validasi</option></select>
                <select v-model="form.status" class="h-10 rounded-lg border bg-background px-3 text-sm"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
                <button type="button" class="h-10 rounded-lg px-3 text-sm font-medium text-muted-foreground hover:bg-muted" @click="reset">Reset</button>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px] text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground"><tr><th class="px-4 py-3">Jabatan</th><th class="px-4 py-3">Jenis & Jenjang</th><th class="px-4 py-3">Kelas</th><th class="px-4 py-3">Pemakaian</th><th class="px-4 py-3">Validasi</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" class="border-b last:border-0">
                            <td class="px-4 py-4"><p class="font-semibold text-foreground">{{ item.nama }}</p><p class="mt-1 text-xs text-muted-foreground">{{ item.kode || 'Tanpa kode referensi' }}</p></td>
                            <td class="px-4 py-4"><p>{{ item.jenis_label }}</p><p class="mt-1 text-xs text-muted-foreground">{{ item.jenjang || 'Tanpa jenjang' }}</p></td>
                            <td class="px-4 py-4">{{ item.kelas_jabatan || '-' }}</td>
                            <td class="px-4 py-4">{{ item.placements_count }} penempatan</td>
                            <td class="px-4 py-4"><span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium" :class="item.verification_status === 'verified' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'"><BadgeCheck v-if="item.verification_status === 'verified'" class="size-3.5" />{{ item.verification_label }}</span></td>
                            <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="item.status === 'active' ? 'bg-blue-50 text-blue-800' : 'bg-slate-100 text-slate-600'">{{ item.status === 'active' ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="px-4 py-4 text-right"><div v-if="can.manage" class="inline-flex gap-1"><Link :href="route('master.referensi-jabatan.edit', item.id)" class="inline-flex size-9 items-center justify-center rounded-lg border hover:bg-muted" aria-label="Edit referensi"><Pencil class="size-4" /></Link><button type="button" class="inline-flex size-9 items-center justify-center rounded-lg border border-red-200 text-red-700 hover:bg-red-50 disabled:opacity-40" :disabled="item.placements_count > 0" aria-label="Hapus referensi" @click="destroy(item)"><Trash2 class="size-4" /></button></div><span v-else class="text-xs text-muted-foreground">Lihat saja</span></td>
                        </tr>
                        <tr v-if="items.data.length === 0"><td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Belum ada referensi yang sesuai filter.</td></tr>
                    </tbody>
                </table>
            </div>
            <footer class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"><span>Menampilkan {{ items.from ?? 0 }}–{{ items.to ?? 0 }} dari {{ items.total }} referensi</span><div class="flex items-center gap-2"><Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-lg border px-3 py-2 hover:bg-muted">Sebelumnya</Link><span>Halaman {{ items.current_page }} / {{ items.last_page }}</span><Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-lg border px-3 py-2 hover:bg-muted">Berikutnya</Link></div></footer>
        </section>
    </div>
</template>
