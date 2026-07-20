<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CalendarRange, Eye, FilePlus2, FileText, Pencil, Plus, RefreshCw, Search, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

type RpjmdRow = {
    id: number;
    judul: string;
    nomor_perda?: string | null;
    tahun_awal: number;
    tahun_akhir: number;
    status: string;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
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
    rpjmds: Paginator<RpjmdRow>;
    filters: {
        search?: string;
        status?: string;
    };
    can: {
        manage: boolean;
    };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('rpjmd.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const hasActiveFilters = computed(() => Boolean(filterForm.search || filterForm.status));

const showingLabel = computed(() => {
    if (!props.rpjmds.total) {
        return 'Belum ada data';
    }

    return `${props.rpjmds.from ?? 0}-${props.rpjmds.to ?? 0} dari ${props.rpjmds.total} dokumen`;
});

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const destroy = async (rpjmd: RpjmdRow) => {
    if (await confirmDelete(`Hapus RPJMD ${rpjmd.tahun_awal}-${rpjmd.tahun_akhir}?`)) {
        router.delete(route('rpjmd.destroy', rpjmd.id));
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
        draft: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200',
        submitted: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-200',
        revision: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-200',
        verified: 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/15 dark:text-cyan-200',
        approved:
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-200',
        rejected: 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-200',
        locked: 'border-zinc-300 bg-zinc-100 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-200',
    })[status] ?? 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200';
</script>

<template>
    <Head title="RPJMD Kabupaten" />
    <div class="rpjmd-select-scope flex flex-col gap-5 p-4 md:p-5">
        <section class="overflow-hidden rounded-xl border bg-card/95 shadow-sm">
            <div class="flex flex-col gap-5 border-b bg-gradient-to-br from-white via-white to-blue-50/70 p-5 dark:from-card dark:via-card dark:to-blue-950/20 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-[#00336C] dark:border-blue-500/25 dark:bg-blue-500/10 dark:text-blue-200">
                        <FileText class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <div class="mb-1 inline-flex items-center gap-2 rounded-full border bg-background px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                            <ShieldCheck class="size-3.5 text-[#00336C] dark:text-blue-200" />
                            Perencanaan Kabupaten
                        </div>
                        <h1 class="text-2xl font-semibold tracking-normal text-foreground md:text-3xl">RPJMD Kabupaten</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola dokumen RPJMD dan buka cascading perencanaan dari visi sampai program penanggung jawab OPD.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="grid grid-cols-2 gap-2 text-sm sm:min-w-64">
                        <div class="rounded-lg border bg-background px-3 py-2">
                            <div class="text-xs font-medium uppercase text-muted-foreground">Total</div>
                            <div class="mt-0.5 text-lg font-semibold">{{ rpjmds.total }}</div>
                        </div>
                        <div class="rounded-lg border bg-background px-3 py-2">
                            <div class="text-xs font-medium uppercase text-muted-foreground">Tampil</div>
                            <div class="mt-0.5 text-lg font-semibold">{{ rpjmds.data.length }}</div>
                        </div>
                    </div>

                    <Link
                        v-if="can.manage"
                        :href="route('rpjmd.create')"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#002855] hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300"
                    >
                        <Plus class="size-4" />
                        Tambah RPJMD
                    </Link>
                </div>
            </div>

            <form class="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_220px_auto]" @submit.prevent="applyFiltersNow">
                <label class="relative block">
                    <span class="sr-only">Cari RPJMD</span>
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="h-11 w-full rounded-lg border bg-background pl-10 pr-3 text-sm outline-none transition placeholder:text-muted-foreground/75 focus:ring-2 focus:ring-[#00336C]/20"
                        placeholder="Cari judul RPJMD atau nomor perda"
                    />
                </label>
                <label class="block">
                    <span class="sr-only">Filter status RPJMD</span>
                    <select
                        v-model="filterForm.status"
                        class="h-11 w-full rounded-lg border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/20"
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
                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-medium text-muted-foreground transition hover:bg-muted disabled:pointer-events-none disabled:opacity-45"
                    :disabled="!hasActiveFilters"
                    @click="resetFilters"
                >
                    <RefreshCw class="size-4" />
                    Reset
                </button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-slate-100 text-[#00336C] dark:bg-slate-800 dark:text-blue-200">
                        <CalendarRange class="size-5" />
                    </div>
                    <div>
                        <h2 class="font-semibold text-foreground">Daftar Dokumen RPJMD</h2>
                        <p class="text-sm text-muted-foreground">{{ showingLabel }}</p>
                    </div>
                </div>
                <div class="text-sm text-muted-foreground">Halaman {{ rpjmds.current_page }} dari {{ rpjmds.last_page }}</div>
            </div>

            <div v-if="rpjmds.data.length === 0" class="flex flex-col items-center justify-center px-4 py-14 text-center">
                <div class="mb-4 flex size-14 items-center justify-center rounded-2xl border bg-muted/40 text-muted-foreground">
                    <FilePlus2 class="size-7" />
                </div>
                <h3 class="text-base font-semibold text-foreground">Belum ada RPJMD</h3>
                <p class="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                    Data belum tersedia untuk filter saat ini. Reset filter atau tambahkan dokumen RPJMD baru.
                </p>
                <Link
                    v-if="can.manage"
                    :href="route('rpjmd.create')"
                    class="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855]"
                >
                    <Plus class="size-4" />
                    Tambah RPJMD
                </Link>
            </div>

            <div v-else class="md:hidden">
                <article v-for="rpjmd in rpjmds.data" :key="rpjmd.id" class="border-b p-4 last:border-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold leading-6 text-foreground">{{ rpjmd.judul }}</h3>
                            <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ rpjmd.nomor_perda || 'Nomor perda belum diisi' }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass(rpjmd.status)">
                            <span class="size-1.5 rounded-full bg-current"></span>
                            {{ statusLabel(rpjmd.status) }}
                        </span>
                    </div>

                    <div class="mt-4 rounded-lg border bg-background px-3 py-2 text-sm">
                        <div class="text-xs font-medium uppercase text-muted-foreground">Periode</div>
                        <div class="mt-0.5 font-semibold">{{ rpjmd.tahun_awal }}-{{ rpjmd.tahun_akhir }}</div>
                        <div class="text-xs text-muted-foreground">{{ rpjmd.periode_tahun?.nama || '-' }}</div>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <Link
                            :href="route('rpjmd.show', rpjmd.id)"
                            class="col-span-3 inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-3 text-sm font-semibold text-white hover:bg-[#002855]"
                        >
                            <Eye class="size-4" />
                            Buka
                        </Link>
                        <Link
                            v-if="can.manage"
                            :href="route('rpjmd.edit', rpjmd.id)"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-medium hover:bg-muted"
                        >
                            <Pencil class="size-4" />
                            Edit
                        </Link>
                        <button
                            v-if="can.manage"
                            type="button"
                            class="col-span-2 inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-red-200 px-3 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-200 dark:hover:bg-red-500/10"
                            @click="destroy(rpjmd)"
                        >
                            <Trash2 class="size-4" />
                            Hapus
                        </button>
                    </div>
                </article>
            </div>

            <div v-if="rpjmds.data.length > 0" class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="w-[58%] px-5 py-3.5">Dokumen</th>
                            <th class="px-5 py-3.5">Periode</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rpjmd in rpjmds.data" :key="rpjmd.id" class="group border-b transition hover:bg-muted/35 last:border-0">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg border bg-background text-[#00336C] dark:text-blue-200">
                                        <FileText class="size-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold leading-6 text-foreground">{{ rpjmd.judul }}</div>
                                        <div class="mt-0.5 text-xs leading-5 text-muted-foreground">{{ rpjmd.nomor_perda || 'Nomor perda belum diisi' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold">{{ rpjmd.tahun_awal }}-{{ rpjmd.tahun_akhir }}</div>
                                <div class="text-xs text-muted-foreground">{{ rpjmd.periode_tahun?.nama || '-' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass(rpjmd.status)">
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                    {{ statusLabel(rpjmd.status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <Link
                                        :href="route('rpjmd.show', rpjmd.id)"
                                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-3 text-xs font-semibold text-white transition hover:bg-[#002855]"
                                    >
                                        Buka
                                        <ArrowRight class="size-3.5" />
                                    </Link>
                                    <Link
                                        v-if="can.manage"
                                        :href="route('rpjmd.edit', rpjmd.id)"
                                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border px-3 text-xs font-medium transition hover:bg-muted"
                                    >
                                        <Pencil class="size-3.5" />
                                        Edit
                                    </Link>
                                    <button
                                        v-if="can.manage"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-red-200 px-3 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-200 dark:hover:bg-red-500/10"
                                        @click="destroy(rpjmd)"
                                    >
                                        <Trash2 class="size-3.5" />
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>{{ showingLabel }}</span>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="rpjmds.prev_page_url"
                        :href="rpjmds.prev_page_url"
                        preserve-scroll
                        preserve-state
                        class="inline-flex h-9 items-center justify-center rounded-lg border px-3 text-sm font-medium text-foreground transition hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="inline-flex h-9 items-center justify-center rounded-lg border px-3 text-sm opacity-45">Sebelumnya</span>
                    <span class="rounded-lg bg-muted px-3 py-2 text-xs font-semibold text-foreground">Halaman {{ rpjmds.current_page }} / {{ rpjmds.last_page }}</span>
                    <Link
                        v-if="rpjmds.next_page_url"
                        :href="rpjmds.next_page_url"
                        preserve-scroll
                        preserve-state
                        class="inline-flex h-9 items-center justify-center rounded-lg border px-3 text-sm font-medium text-foreground transition hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="inline-flex h-9 items-center justify-center rounded-lg border px-3 text-sm opacity-45">Berikutnya</span>
                </div>
            </div>
        </section>
    </div>
</template>
