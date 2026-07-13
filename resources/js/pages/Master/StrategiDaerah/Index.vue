<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import { reactive } from 'vue';

type Strategi = {
    id: number;
    kode: string | null;
    strategi: string;
    status: 'active' | 'inactive';
    programs_count: number;
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
    items: Paginator<Strategi>;
    filters: { search?: string; status?: string };
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});
const applyFilters = () =>
    router.get(route('master.strategi-daerah.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};
const destroy = async (item: Strategi) => {
    if (await confirmDelete(`Hapus strategi ${item.kode ? `${item.kode} - ` : ''}${item.strategi}?`)) {
        router.delete(route('master.strategi-daerah.destroy', item.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Strategi Daerah" />

    <div class="flex flex-col gap-4 p-4">
        <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#00336C]">Referensi Data</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal">Strategi Daerah</h1>
                <p class="mt-1 text-sm text-muted-foreground">Daftar strategi yang dapat dipilih pada Program RPJMD.</p>
            </div>
            <Link
                v-if="can.manage"
                :href="route('master.strategi-daerah.create')"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-[#00336C] px-4 text-sm font-semibold text-white transition hover:bg-[#002650]"
            >
                <Plus class="size-4" />
                Tambah Strategi
            </Link>
        </header>

        <form class="flex flex-col gap-3 rounded-lg border bg-card p-3 md:flex-row md:items-center" @submit.prevent="applyFiltersNow">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/15"
                    placeholder="Cari kode atau strategi"
                />
            </div>
            <select
                v-model="filterForm.status"
                aria-label="Filter status strategi"
                class="h-10 rounded-md border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/15"
            >
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak aktif</option>
            </select>
            <button type="button" class="h-10 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b bg-slate-50 text-xs uppercase text-slate-600">
                        <tr>
                            <th class="w-28 px-4 py-3">Kode</th>
                            <th class="min-w-72 px-4 py-3">Strategi</th>
                            <th class="w-28 px-4 py-3 text-center">Program</th>
                            <th class="w-24 px-4 py-3">Status</th>
                            <th class="w-24 px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" class="border-b align-top last:border-0 hover:bg-slate-50/60">
                            <td class="px-4 py-4 font-semibold text-[#00336C]">{{ item.kode || '-' }}</td>
                            <td class="px-4 py-4 font-medium leading-6">{{ item.strategi }}</td>
                            <td class="px-4 py-4 text-center">{{ item.programs_count ?? 0 }}</td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="item.status === 'active' ? 'bg-blue-50 text-[#00336C]' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ item.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div v-if="can.manage" class="inline-flex items-center gap-1">
                                    <Link
                                        :href="route('master.strategi-daerah.edit', item.id)"
                                        class="inline-flex size-9 items-center justify-center rounded-md border text-slate-600 hover:border-[#00336C]/30 hover:bg-blue-50 hover:text-[#00336C]"
                                        title="Edit strategi"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-md border text-red-600 hover:border-red-200 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-35"
                                        :disabled="item.programs_count > 0"
                                        :title="item.programs_count > 0 ? 'Strategi masih digunakan program' : 'Hapus strategi'"
                                        @click="destroy(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Lihat</span>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Belum ada strategi daerah.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} strategi</span>
                <div class="flex items-center gap-2">
                    <Link v-if="items.prev_page_url" :href="items.prev_page_url" preserve-scroll class="rounded-md border px-3 py-1.5 hover:bg-muted">
                        Sebelumnya
                    </Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-45">Sebelumnya</span>
                    <span class="px-1">Halaman {{ items.current_page }} / {{ items.last_page }}</span>
                    <Link v-if="items.next_page_url" :href="items.next_page_url" preserve-scroll class="rounded-md border px-3 py-1.5 hover:bg-muted">
                        Berikutnya
                    </Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-45">Berikutnya</span>
                </div>
            </footer>
        </section>
    </div>
</template>
