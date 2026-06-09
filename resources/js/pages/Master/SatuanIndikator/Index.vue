<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type Satuan = {
    id: number;
    nama: string;
    simbol?: string | null;
    jenis?: string | null;
    deskripsi?: string | null;
    status: string;
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
    items: Paginator<Satuan>;
    filters: { search?: string; status?: string; jenis?: string };
    jenisOptions: Array<{ value: string; label: string }>;
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    jenis: props.filters.jenis ?? '',
});

const applyFilters = () => router.get(route('master.satuan-indikator.index'), filterForm, { preserveState: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.jenis = '';
    applyFiltersNow();
};
const destroy = (item: Satuan) => {
    if (confirm(`Hapus satuan ${item.nama}?`)) {
        router.delete(route('master.satuan-indikator.destroy', item.id));
    }
};
</script>

<template>
    <Head title="Satuan Indikator" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Satuan Indikator</h1>
                <p class="mt-1 text-sm text-muted-foreground">Master satuan untuk target dan realisasi indikator kinerja.</p>
            </div>
            <Link
                v-if="can.manage"
                :href="route('master.satuan-indikator.create')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
            >
                <Plus class="size-4" />
                Tambah Satuan
            </Link>
        </div>

        <form class="flex flex-col gap-3 rounded-lg border bg-card p-3 md:flex-row md:items-center" @submit.prevent="applyFiltersNow">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari nama, simbol, atau deskripsi"
                />
            </div>
            <select
                v-model="filterForm.jenis"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua jenis</option>
                <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <select
                v-model="filterForm.status"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak aktif</option>
            </select>
            <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <div class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Simbol</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ item.nama }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.deskripsi || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ item.simbol || '-' }}</td>
                            <td class="px-4 py-3">{{ item.jenis || '-' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="item.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                                >
                                    {{ item.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="can.manage" class="inline-flex gap-2">
                                    <Link
                                        :href="route('master.satuan-indikator.edit', item.id)"
                                        class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        >Edit</Link
                                    >
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="destroy(item)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Read-only</span>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada satuan indikator.</td>
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
