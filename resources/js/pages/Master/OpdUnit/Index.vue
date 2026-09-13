<script setup lang="ts">
import OrganizationWorkspaceTabs from '@/components/OrganizationWorkspaceTabs.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import { computed, reactive, watch } from 'vue';

type OpdUnit = {
    id: number;
    opd_id: number;
    parent_id?: number | null;
    kode: string;
    nama: string;
    jenis_unit?: string | null;
    nama_pimpinan?: string | null;
    nip_pimpinan?: string | null;
    status: string;
    children_count?: number | null;
    opd?: { id: number; kode: string; nama: string; singkatan?: string | null } | null;
    parent?: { id: number; kode: string; nama: string } | null;
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
    items: Paginator<OpdUnit>;
    filters: { search?: string; status?: string; opd_id?: string; jenis_unit?: string };
    opdOptions: Array<{ id: number; label: string }>;
    jenisOptions: Array<{ value: string; label: string }>;
    can: { manage: boolean; opd_scoped: boolean };
}>();

const jenisLabels = computed(() => new Map(props.jenisOptions.map((option) => [option.value, option.label])));

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    jenis_unit: props.filters.jenis_unit ?? '',
});

const filterPayload = () => ({ ...filterForm });
const applyFilters = () => router.get(route('master.opd-units.index'), filterPayload(), { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow, syncFilters } = useAutoFilters(filterForm, applyFilters);
watch(
    () => props.filters,
    (filters) =>
        syncFilters({
            search: filters.search ?? '',
            status: filters.status ?? '',
            opd_id: filters.opd_id ?? '',
            jenis_unit: filters.jenis_unit ?? '',
        }),
    { deep: true },
);

const activeFilterCount = computed(() => Object.values(filterForm).filter((value) => String(value).trim() !== '').length);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    filterForm.jenis_unit = '';
    applyFiltersNow();
};
const destroy = async (item: OpdUnit) => {
    if (await confirmDelete(`Hapus unit ${item.kode} - ${item.nama}?`)) {
        router.delete(route('master.opd-units.destroy', item.id));
    }
};
</script>

<template>
    <Head title="Unit Kerja" />
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6">
        <OrganizationWorkspaceTabs active="units" />

        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-800 text-white shadow-sm dark:bg-blue-600">
                    <Building2 class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">Struktur organisasi</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">Unit Kerja</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Susun bidang, bagian, subbagian, seksi, dan unit layanan dalam hierarki OPD.</p>
                </div>
            </div>
            <Link
                v-if="can.manage"
                :href="route('master.opd-units.create')"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-900 dark:bg-blue-600 dark:hover:bg-blue-500"
            >
                <Plus class="size-4" />
                Tambah Unit
            </Link>
        </header>

        <form
            class="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-2"
            :class="can.opd_scoped ? 'xl:grid-cols-4' : 'xl:grid-cols-5'"
            @submit.prevent="applyFiltersNow"
        >
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
                    placeholder="Cari unit, OPD, induk, atau pimpinan"
                    aria-label="Cari unit kerja"
                />
            </div>
            <select
                v-if="!can.opd_scoped"
                v-model="filterForm.opd_id"
                class="h-10 w-full min-w-0 truncate rounded-lg border bg-background px-3 text-sm"
                aria-label="Filter perangkat daerah"
            >
                <option value="">Semua OPD</option>
                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
            <select v-model="filterForm.jenis_unit" class="h-10 rounded-lg border bg-background px-3 text-sm" aria-label="Filter jenis unit">
                <option value="">Semua jenis</option>
                <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm" aria-label="Filter status unit">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak aktif</option>
            </select>
            <button
                type="button"
                class="h-10 rounded-lg px-3 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="activeFilterCount === 0"
                @click="resetFilters"
            >
                Reset<span v-if="activeFilterCount > 0"> ({{ activeFilterCount }})</span>
            </button>
        </form>

        <div class="overflow-hidden rounded-xl border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Unit Kerja</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">OPD</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" class="border-b last:border-0">
                            <td class="px-4 py-3 font-medium">{{ item.kode }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ item.nama }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Induk: {{ item.parent ? `${item.parent.kode} - ${item.parent.nama}` : 'Unit utama' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ jenisLabels.get(item.jenis_unit ?? '') ?? '-' }}</td>
                            <td class="px-4 py-3">{{ item.opd?.singkatan || item.opd?.nama || '-' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="item.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                                >
                                    {{ item.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="can.manage" class="inline-flex gap-1">
                                    <Link
                                        :href="route('master.opd-units.edit', item.id)"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border hover:bg-muted"
                                        aria-label="Edit unit"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-red-200 text-red-700 hover:bg-red-50"
                                        aria-label="Hapus unit"
                                        @click="destroy(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Read-only</span>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Belum ada unit kerja pada cakupan ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ items.current_page }} / {{ items.last_page }}</span>
                    <Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </div>
    </div>
</template>
