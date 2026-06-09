<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type Periode = {
    id: number;
    tahun: number;
    nama: string;
    tanggal_mulai?: string | null;
    tanggal_selesai?: string | null;
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
    items: Paginator<Periode>;
    filters: { search?: string; status?: string };
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('master.periode-tahun.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const destroy = (item: Periode) => {
    if (confirm(`Hapus periode ${item.nama}?`)) {
        router.delete(route('master.periode-tahun.destroy', item.id));
    }
};

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        active: 'Aktif',
        locked: 'Terkunci',
        archived: 'Arsip',
    })[status] ?? status;

const statusClass = (status: string) =>
    ({
        draft: 'bg-slate-100 text-slate-700',
        active: 'bg-emerald-100 text-emerald-800',
        locked: 'bg-amber-100 text-amber-800',
        archived: 'bg-zinc-100 text-zinc-700',
    })[status] ?? 'bg-slate-100 text-slate-700';
</script>

<template>
    <Head title="Periode Tahun" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Periode Tahun</h1>
                <p class="mt-1 text-sm text-muted-foreground">Kelola tahun perencanaan, pelaporan, dan status kunci periode.</p>
            </div>
            <Link
                v-if="can.manage"
                :href="route('master.periode-tahun.create')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
            >
                <Plus class="size-4" />
                Tambah Periode
            </Link>
        </div>

        <form class="flex flex-col gap-3 rounded-lg border bg-card p-3 md:flex-row md:items-center" @submit.prevent="applyFiltersNow">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari tahun atau nama periode"
                />
            </div>
            <select
                v-model="filterForm.status"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua status</option>
                <option value="draft">Draft</option>
                <option value="active">Aktif</option>
                <option value="locked">Terkunci</option>
                <option value="archived">Arsip</option>
            </select>
            <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <div class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Tahun</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Rentang</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" class="border-b last:border-0">
                            <td class="px-4 py-3 font-medium">{{ item.tahun }}</td>
                            <td class="px-4 py-3">{{ item.nama }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ item.tanggal_mulai || '-' }} s.d. {{ item.tanggal_selesai || '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(item.status)">{{
                                    statusLabel(item.status)
                                }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="can.manage" class="inline-flex gap-2">
                                    <Link
                                        :href="route('master.periode-tahun.edit', item.id)"
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
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada periode tahun.</td>
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
