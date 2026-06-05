<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileSpreadsheet, Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type Option = { id: number; label: string };

type RenstraRow = {
    id: number;
    judul: string;
    nomor_dokumen?: string | null;
    tahun_awal: number;
    tahun_akhir: number;
    status: string;
    opd?: { id: number; kode: string; nama: string; singkatan?: string | null } | null;
    rpjmd?: { id: number; judul: string; tahun_awal: number; tahun_akhir: number } | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
    progress: {
        tujuan_count: number;
        program_count: number;
        status: string;
    };
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    renstras: Paginator<RenstraRow>;
    filters: {
        search?: string;
        status?: string;
        opd_id?: string;
        rpjmd_id?: string;
        periode_tahun_id?: string;
    };
    opdOptions: Option[];
    rpjmdOptions: Option[];
    periodeOptions: Option[];
    can: {
        manage: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Renstra OPD', href: '/renstra-opd' },
];

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    rpjmd_id: props.filters.rpjmd_id ?? '',
    periode_tahun_id: props.filters.periode_tahun_id ?? '',
});

const applyFilters = () => {
    router.get(route('renstra-opd.index'), filterForm, {
        preserveState: true,
        replace: true,
    });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    filterForm.rpjmd_id = '';
    filterForm.periode_tahun_id = '';
    applyFiltersNow();
};

const destroy = (renstra: RenstraRow) => {
    if (confirm(`Hapus Renstra ${renstra.opd?.singkatan || renstra.opd?.nama || ''} ${renstra.tahun_awal}-${renstra.tahun_akhir}?`)) {
        router.delete(route('renstra-opd.destroy', renstra.id));
    }
};

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Revisi',
        verified: 'Terverifikasi',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;

const statusClass = (status: string) =>
    ({
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-blue-100 text-blue-800',
        revision: 'bg-amber-100 text-amber-800',
        verified: 'bg-cyan-100 text-cyan-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
        locked: 'bg-zinc-200 text-zinc-800',
    })[status] ?? 'bg-slate-100 text-slate-700';

const progressClass = (status: string) => (status === 'terisi' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800');
</script>

<template>
    <Head title="Renstra OPD" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Renstra OPD</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Cascading tujuan, sasaran, program, kegiatan, dan target OPD yang terhubung ke RPJMD Kabupaten.
                    </p>
                </div>
                <div v-if="can.manage" class="flex flex-wrap gap-2">
                    <Link
                        :href="route('renstra-opd.import.create')"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        <FileSpreadsheet class="size-4" />
                        Import Renstra
                    </Link>
                    <Link
                        :href="route('renstra-opd.create')"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                    >
                        <Plus class="size-4" />
                        Tambah Renstra
                    </Link>
                </div>
            </div>

            <form
                class="grid gap-3 rounded-lg border bg-card p-3 lg:grid-cols-[1fr_190px_220px_220px_180px_auto]"
                @submit.prevent="applyFiltersNow"
            >
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                        placeholder="Cari judul, dokumen, atau OPD"
                    />
                </div>
                <select
                    v-model="filterForm.status"
                    class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    <option value="">Semua status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Diajukan</option>
                    <option value="revision">Revisi</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="locked">Terkunci</option>
                </select>
                <select
                    v-model="filterForm.opd_id"
                    class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    <option value="">Semua OPD</option>
                    <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select
                    v-model="filterForm.rpjmd_id"
                    class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    <option value="">Semua RPJMD</option>
                    <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select
                    v-model="filterForm.periode_tahun_id"
                    class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    <option value="">Semua tahun</option>
                    <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
            </form>

            <div class="overflow-hidden rounded-lg border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">OPD</th>
                                <th class="px-4 py-3">Renstra</th>
                                <th class="px-4 py-3">RPJMD</th>
                                <th class="px-4 py-3">Progress</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="renstra in renstras.data" :key="renstra.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ renstra.opd?.singkatan || renstra.opd?.nama || '-' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ renstra.opd?.kode || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ renstra.judul }}</div>
                                    <div class="text-xs text-muted-foreground">{{ renstra.nomor_dokumen || 'Nomor dokumen belum diisi' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ renstra.tahun_awal }}-{{ renstra.tahun_akhir }}</div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <span v-if="renstra.rpjmd"
                                        >{{ renstra.rpjmd.tahun_awal }}-{{ renstra.rpjmd.tahun_akhir }} - {{ renstra.rpjmd.judul }}</span
                                    >
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="progressClass(renstra.progress.status)">
                                            {{ renstra.progress.status === 'terisi' ? 'Terisi' : 'Belum lengkap' }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                            >{{ renstra.progress.tujuan_count }} tujuan</span
                                        >
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                            >{{ renstra.progress.program_count }} program</span
                                        >
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(renstra.status)">
                                        {{ statusLabel(renstra.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex gap-2">
                                        <Link :href="route('renstra-opd.show', renstra.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                            >Buka</Link
                                        >
                                        <Link
                                            v-if="can.manage"
                                            :href="route('renstra-opd.edit', renstra.id)"
                                            class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                            >Edit</Link
                                        >
                                        <button
                                            v-if="can.manage"
                                            type="button"
                                            class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                            @click="destroy(renstra)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="renstras.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada data Renstra OPD.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                    <span>Menampilkan {{ renstras.from ?? 0 }}-{{ renstras.to ?? 0 }} dari {{ renstras.total }} data</span>
                    <div class="flex gap-2">
                        <Link v-if="renstras.prev_page_url" :href="renstras.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                            >Sebelumnya</Link
                        >
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                        <span class="px-2 py-1.5">Halaman {{ renstras.current_page }} / {{ renstras.last_page }}</span>
                        <Link v-if="renstras.next_page_url" :href="renstras.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                            >Berikutnya</Link
                        >
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
