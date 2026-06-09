<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type Opd = {
    id: number;
    kode: string;
    nama: string;
    singkatan?: string | null;
    jenis?: string | null;
    email?: string | null;
    nama_kepala?: string | null;
    status: string;
    urusan_pemerintahan?: { kode: string; nama: string } | null;
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
    opds: Paginator<Opd>;
    filters: {
        search?: string;
        status?: string;
    };
    can: {
        create: boolean;
    };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('master.opd.index'), filterForm, {
        preserveState: true,
        replace: true,
    });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const destroy = (opd: Opd) => {
    if (confirm(`Hapus OPD ${opd.nama}?`)) {
        router.delete(route('master.opd.destroy', opd.id));
    }
};
</script>

<template>
    <Head title="Master OPD" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Master OPD</h1>
                <p class="mt-1 text-sm text-muted-foreground">Data perangkat daerah dan unit penanggung jawab kinerja.</p>
            </div>
            <Link
                v-if="can.create"
                :href="route('master.opd.create')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
            >
                <Plus class="size-4" />
                Tambah OPD
            </Link>
        </div>

        <form class="flex flex-col gap-3 rounded-lg border bg-card p-3 md:flex-row md:items-center" @submit.prevent="applyFiltersNow">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari kode, nama, atau singkatan OPD"
                />
            </div>
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
                            <th class="px-4 py-3">Nama OPD</th>
                            <th class="px-4 py-3">Urusan</th>
                            <th class="px-4 py-3">Kepala OPD</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="opd in opds.data" :key="opd.id" class="border-b last:border-0">
                            <td class="px-4 py-3 font-medium">{{ opd.kode }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ opd.nama }}</div>
                                <div class="text-xs text-muted-foreground">{{ opd.singkatan || '-' }} · {{ opd.jenis || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="opd.urusan_pemerintahan">{{ opd.urusan_pemerintahan.kode }} - {{ opd.urusan_pemerintahan.nama }}</span>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3">{{ opd.nama_kepala || '-' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="opd.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                                >
                                    {{ opd.status === 'active' ? 'Aktif' : 'Tidak aktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="can.create" class="inline-flex gap-2">
                                    <Link :href="route('master.opd.edit', opd.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        >Edit</Link
                                    >
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="destroy(opd)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Read-only</span>
                            </td>
                        </tr>
                        <tr v-if="opds.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada data OPD.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ opds.from ?? 0 }}-{{ opds.to ?? 0 }} dari {{ opds.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="opds.prev_page_url" :href="opds.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ opds.current_page }} / {{ opds.last_page }}</span>
                    <Link v-if="opds.next_page_url" :href="opds.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Berikutnya</Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </div>
    </div>
</template>
