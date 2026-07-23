<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, GitBranch, Layers3, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

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
        preserveScroll: true,
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

const destroy = async (renstra: RenstraRow) => {
    if (await confirmDelete(`Hapus Renstra ${renstra.opd?.singkatan || renstra.opd?.nama || ''} ${renstra.tahun_awal}-${renstra.tahun_akhir}?`)) {
        router.delete(route('renstra-opd.destroy', renstra.id));
    }
};

const activeFilterCount = computed(() => Object.values(filterForm).filter((value) => String(value || '').trim() !== '').length);

const selectedFilterLabels = computed(
    () =>
        [
            filterForm.search ? `Cari: ${filterForm.search}` : null,
            filterForm.status ? `Status: ${statusLabel(filterForm.status)}` : null,
            filterForm.opd_id
                ? `OPD: ${props.opdOptions.find((option) => String(option.id) === String(filterForm.opd_id))?.label ?? 'Terpilih'}`
                : null,
            filterForm.rpjmd_id
                ? `RPJMD: ${props.rpjmdOptions.find((option) => String(option.id) === String(filterForm.rpjmd_id))?.label ?? 'Terpilih'}`
                : null,
            filterForm.periode_tahun_id
                ? `Periode: ${props.periodeOptions.find((option) => String(option.id) === String(filterForm.periode_tahun_id))?.label ?? 'Terpilih'}`
                : null,
        ].filter(Boolean) as string[],
);

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
        draft: 'bg-slate-100 text-slate-700 ring-slate-200',
        submitted: 'bg-blue-100 text-blue-800 ring-blue-200',
        revision: 'bg-amber-100 text-amber-800 ring-amber-200',
        verified: 'bg-cyan-100 text-cyan-800 ring-cyan-200',
        approved: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        rejected: 'bg-red-100 text-red-800 ring-red-200',
        locked: 'bg-zinc-200 text-zinc-800 ring-zinc-300',
    })[status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';

const progressClass = (status: string) =>
    status === 'terisi' ? 'bg-blue-100 text-blue-800 ring-blue-200' : 'bg-amber-100 text-amber-800 ring-amber-200';

function rowProgressPercent(renstra: RenstraRow): number {
    let percent = 0;

    if (renstra.progress.tujuan_count > 0) {
        percent += 50;
    }

    if (renstra.progress.program_count > 0) {
        percent += 50;
    }

    return percent;
}
</script>

<template>
    <Head title="Renstra OPD" />
    <div class="flex flex-col gap-5 p-4">
        <section class="overflow-hidden rounded-lg border border-blue-100 bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#eaf4ff)] px-4 py-5 sm:px-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold uppercase text-[#00336C]"
                        >
                            <Layers3 class="size-3.5" />
                            Perencanaan OPD
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-normal text-slate-950">Renstra OPD</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Kelola Renstra OPD dan lanjutkan pengisian cascading tujuan, sasaran, program, kegiatan, sub kegiatan, indikator, serta
                            target.
                        </p>
                    </div>

                    <div v-if="can.manage" class="flex flex-col gap-2 sm:flex-row">
                        <Link
                            :href="route('renstra-opd.create')"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-[#00336C] px-3 text-sm font-semibold text-white shadow-sm hover:bg-[#0a4485]"
                        >
                            <Plus class="size-4" />
                            Tambah Renstra
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-blue-100 bg-card p-4 shadow-sm">
            <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Filter dan Pencarian</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Filter otomatis berjalan saat kolom diubah.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm text-muted-foreground hover:bg-muted"
                    @click="resetFilters"
                >
                    Reset filter
                </button>
            </div>

            <form
                class="grid gap-3 lg:grid-cols-[minmax(16rem,1.4fr)_170px_minmax(14rem,1fr)_minmax(14rem,1fr)_170px]"
                @submit.prevent="applyFiltersNow"
            >
                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Pencarian</span>
                    <span class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/30"
                            placeholder="Cari judul, nomor dokumen, atau OPD"
                        />
                    </span>
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Status</span>
                    <select
                        v-model="filterForm.status"
                        class="h-10 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/30"
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
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">OPD</span>
                    <select
                        v-model="filterForm.opd_id"
                        class="h-10 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/30"
                    >
                        <option value="">Semua OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">RPJMD</span>
                    <select
                        v-model="filterForm.rpjmd_id"
                        class="h-10 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/30"
                    >
                        <option value="">Semua RPJMD</option>
                        <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid min-w-0 gap-1.5">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Periode</span>
                    <select
                        v-model="filterForm.periode_tahun_id"
                        class="h-10 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]/30"
                    >
                        <option value="">Semua tahun</option>
                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>
            </form>

            <div v-if="activeFilterCount" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="label in selectedFilterLabels"
                    :key="label"
                    class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-[#00336C]"
                >
                    {{ label }}
                </span>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-blue-100 bg-card shadow-sm">
            <div class="flex flex-col gap-2 border-b px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Daftar Renstra</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Menampilkan {{ renstras.from ?? 0 }}-{{ renstras.to ?? 0 }} dari {{ renstras.total }} data.
                    </p>
                </div>
                <span class="rounded-full border bg-background px-3 py-1 text-xs font-semibold text-muted-foreground">
                    Halaman {{ renstras.current_page }} / {{ renstras.last_page }}
                </span>
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">OPD</th>
                            <th class="px-4 py-3">Dokumen Renstra</th>
                            <th class="px-4 py-3">Kelengkapan Cascading</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="renstra in renstras.data" :key="renstra.id" class="border-b align-top last:border-0 hover:bg-muted/40">
                            <td class="min-w-72 px-4 py-4">
                                <div class="font-semibold text-slate-950">{{ renstra.opd?.singkatan || renstra.opd?.nama || '-' }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">{{ renstra.opd?.kode || 'Kode belum diisi' }}</div>
                                <p v-if="renstra.rpjmd" class="mt-3 flex max-w-md items-start gap-1.5 text-xs leading-5 text-slate-600">
                                    <GitBranch class="mt-0.5 size-3.5 shrink-0 text-[#00336C]" />
                                    <span class="line-clamp-2">
                                        Terhubung RPJMD {{ renstra.rpjmd.tahun_awal }}-{{ renstra.rpjmd.tahun_akhir }}:
                                        {{ renstra.rpjmd.judul }}
                                    </span>
                                </p>
                                <p v-else class="mt-3 text-xs font-medium text-amber-700">Belum terhubung RPJMD</p>
                            </td>
                            <td class="min-w-80 px-4 py-4">
                                <div class="font-semibold text-slate-950">{{ renstra.judul }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">{{ renstra.nomor_dokumen || 'Nomor dokumen belum diisi' }}</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                        {{ renstra.tahun_awal }}-{{ renstra.tahun_akhir }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                        {{ renstra.periode_tahun?.nama || 'Periode belum diisi' }}
                                    </span>
                                </div>
                            </td>
                            <td class="min-w-72 px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-semibold uppercase text-muted-foreground">Progress</span>
                                    <span class="text-xs font-semibold text-slate-700">{{ rowProgressPercent(renstra) }}%</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#00336C]" :style="{ width: `${rowProgressPercent(renstra)}%` }"></div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium ring-1" :class="progressClass(renstra.progress.status)">
                                        {{ renstra.progress.status === 'terisi' ? 'Terisi' : 'Belum lengkap' }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                        {{ renstra.progress.tujuan_count }} tujuan
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                        {{ renstra.progress.program_count }} program
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(renstra.status)">
                                    {{ statusLabel(renstra.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="inline-flex gap-2">
                                    <Link
                                        :href="route('renstra-opd.show', renstra.id)"
                                        class="inline-flex h-9 items-center gap-2 rounded-md bg-[#00336C] px-3 text-xs font-semibold text-white hover:bg-[#0a4485]"
                                    >
                                        Buka
                                        <ArrowRight class="size-3.5" />
                                    </Link>
                                    <Link
                                        v-if="can.manage"
                                        :href="route('renstra-opd.edit', renstra.id)"
                                        class="inline-flex h-9 items-center justify-center rounded-md border px-2 text-muted-foreground hover:bg-muted"
                                        title="Edit Renstra"
                                        aria-label="Edit Renstra"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                    <button
                                        v-if="can.manage"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-md border px-2 text-red-700 hover:bg-red-50"
                                        title="Hapus Renstra"
                                        aria-label="Hapus Renstra"
                                        @click="destroy(renstra)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="renstras.data.length === 0">
                            <td colspan="5" class="px-4 py-14 text-center">
                                <div class="mx-auto max-w-md">
                                    <Layers3 class="mx-auto size-10 text-muted-foreground" />
                                    <p class="mt-3 font-semibold">Belum ada data Renstra OPD</p>
                                    <p class="mt-1 text-sm text-muted-foreground">Tambahkan Renstra atau ubah filter pencarian.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 p-4 lg:hidden">
                <article v-for="renstra in renstras.data" :key="`mobile-${renstra.id}`" class="rounded-lg border bg-background p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase text-muted-foreground">{{ renstra.opd?.kode || 'OPD' }}</p>
                            <h3 class="mt-1 text-base font-semibold leading-snug text-slate-950">
                                {{ renstra.opd?.singkatan || renstra.opd?.nama || '-' }}
                            </h3>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(renstra.status)">
                            {{ statusLabel(renstra.status) }}
                        </span>
                    </div>

                    <div class="mt-4">
                        <p class="font-medium">{{ renstra.judul }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ renstra.nomor_dokumen || 'Nomor dokumen belum diisi' }}</p>
                    </div>

                    <div class="mt-4 grid gap-3 rounded-md border bg-card p-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold uppercase text-muted-foreground">Cascading</span>
                            <span class="font-semibold">{{ rowProgressPercent(renstra) }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-[#00336C]" :style="{ width: `${rowProgressPercent(renstra)}%` }"></div>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ renstra.progress.tujuan_count }} tujuan</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                >{{ renstra.progress.program_count }} program</span
                            >
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                >{{ renstra.tahun_awal }}-{{ renstra.tahun_akhir }}</span
                            >
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="route('renstra-opd.show', renstra.id)"
                            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md bg-[#00336C] px-3 text-sm font-semibold text-white"
                        >
                            Buka
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            v-if="can.manage"
                            :href="route('renstra-opd.edit', renstra.id)"
                            class="inline-flex min-h-10 items-center justify-center rounded-md border px-3 text-sm"
                            aria-label="Edit Renstra"
                        >
                            <Pencil class="size-4" />
                        </Link>
                    </div>
                </article>

                <div v-if="renstras.data.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Belum ada data Renstra OPD.
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ renstras.from ?? 0 }}-{{ renstras.to ?? 0 }} dari {{ renstras.total }} data</span>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="renstras.prev_page_url" :href="renstras.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">
                        Sebelumnya
                    </Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ renstras.current_page }} / {{ renstras.last_page }}</span>
                    <Link v-if="renstras.next_page_url" :href="renstras.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">
                        Berikutnya
                    </Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </section>
    </div>
</template>
