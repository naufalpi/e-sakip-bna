<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

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
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
    jenis_unit: props.filters.jenis_unit ?? '',
});

const applyFilters = () => router.get(route('master.opd-units.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
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
    <Head title="Unit OPD" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Unit OPD</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Struktur unit kerja internal OPD untuk penanggung jawab rencana aksi dan bukti dukung.
                </p>
            </div>
            <Link
                v-if="can.manage"
                :href="route('master.opd-units.create')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
            >
                <Plus class="size-4" />
                Tambah Unit
            </Link>
        </div>

        <form class="grid gap-3 rounded-lg border bg-card p-3 md:grid-cols-[1fr_auto_auto_auto_auto]" @submit.prevent="applyFiltersNow">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari kode, nama, jenis, atau pimpinan"
                />
            </div>
            <select
                v-model="filterForm.opd_id"
                class="h-9 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua OPD</option>
                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
            <select
                v-model="filterForm.jenis_unit"
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
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3">OPD</th>
                            <th class="px-4 py-3">Pimpinan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" class="border-b last:border-0">
                            <td class="px-4 py-3 font-medium">{{ item.kode }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ item.nama }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.jenis_unit || '-' }} - Induk: {{ item.parent ? `${item.parent.kode} - ${item.parent.nama}` : '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ item.opd?.singkatan || item.opd?.nama || '-' }}</td>
                            <td class="px-4 py-3">
                                <div>{{ item.nama_pimpinan || '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.nip_pimpinan || '-' }}</div>
                            </td>
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
                                    <Link :href="route('master.opd-units.edit', item.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
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
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada unit OPD.</td>
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
