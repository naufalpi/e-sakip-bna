<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type Setting = {
    id: number;
    group: string;
    key: string;
    label: string;
    type: string;
    value: string;
    is_public: boolean;
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
    items: Paginator<Setting>;
    filters: { search?: string; group?: string; type?: string; is_public?: string };
    groupOptions: Array<{ value: string; label: string }>;
    typeOptions: Array<{ value: string; label: string }>;
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pengaturan Sistem', href: '/master/system-settings' },
];

const filterForm = reactive({
    search: props.filters.search ?? '',
    group: props.filters.group ?? '',
    type: props.filters.type ?? '',
    is_public: props.filters.is_public ?? '',
});

const applyFilters = () => router.get(route('master.system-settings.index'), filterForm, { preserveState: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.group = '';
    filterForm.type = '';
    filterForm.is_public = '';
    applyFiltersNow();
};
const destroy = (item: Setting) => {
    if (confirm(`Hapus pengaturan ${item.key}?`)) {
        router.delete(route('master.system-settings.destroy', item.id));
    }
};
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Pengaturan Sistem</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Konfigurasi aplikasi yang disimpan di database.</p>
                </div>
                <Link v-if="can.manage" :href="route('master.system-settings.create')" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800">
                    <Plus class="size-4" />
                    Tambah Setting
                </Link>
            </div>

            <form class="flex flex-col gap-3 rounded-lg border bg-card p-3 md:flex-row md:items-center" @submit.prevent="applyFiltersNow">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="filterForm.search" type="search" class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700" placeholder="Cari key, label, atau grup" />
                </div>
                <select v-model="filterForm.group" class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700">
                    <option value="">Semua grup</option>
                    <option v-for="option in groupOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select v-model="filterForm.type" class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700">
                    <option value="">Semua tipe</option>
                    <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select v-model="filterForm.is_public" class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700">
                    <option value="">Semua akses</option>
                    <option value="1">Publik</option>
                    <option value="0">Internal</option>
                </select>
                <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
            </form>

            <div class="overflow-hidden rounded-lg border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Key</th>
                                <th class="px-4 py-3">Label</th>
                                <th class="px-4 py-3">Tipe</th>
                                <th class="px-4 py-3">Akses</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items.data" :key="item.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ item.key }}</div>
                                    <div class="text-xs text-muted-foreground">{{ item.group }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ item.label }}</div>
                                    <div class="max-w-xl truncate text-xs text-muted-foreground">{{ item.value || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ item.type }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="item.is_public ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'">
                                        {{ item.is_public ? 'Publik' : 'Internal' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div v-if="can.manage" class="inline-flex gap-2">
                                        <Link :href="route('master.system-settings.edit', item.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted">Edit</Link>
                                        <button type="button" class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50" @click="destroy(item)">Hapus</button>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">Read-only</span>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada pengaturan sistem.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                    <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data</span>
                    <div class="flex gap-2">
                        <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                        <span class="px-2 py-1.5">Halaman {{ items.current_page }} / {{ items.last_page }}</span>
                        <Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Berikutnya</Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
