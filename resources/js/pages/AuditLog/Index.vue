<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import { reactive } from 'vue';

type LogRow = {
    id: number;
    action: string;
    model_type?: string | null;
    model_type_full?: string | null;
    model_id?: number | null;
    description?: string | null;
    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;
    ip_address?: string | null;
    created_at?: string | null;
    user?: { name: string; email: string } | null;
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
    logs: Paginator<LogRow>;
    filters: { search?: string; action?: string; model_type?: string };
    actions: string[];
    modelTypes: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Audit Log', href: '/audit-log' },
];

const filterForm = reactive({
    search: props.filters.search ?? '',
    action: props.filters.action ?? '',
    model_type: props.filters.model_type ?? '',
});

const applyFilters = () => router.get(route('audit-log.index'), filterForm, { preserveState: true, replace: true });
const resetFilters = () => {
    filterForm.search = '';
    filterForm.action = '';
    filterForm.model_type = '';
    applyFilters();
};

const shortJson = (value?: Record<string, unknown> | null) => {
    if (!value || Object.keys(value).length === 0) return '-';

    return JSON.stringify(value).slice(0, 180);
};
</script>

<template>
    <Head title="Audit Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Audit Log</h1>
                <p class="mt-1 text-sm text-muted-foreground">Catatan perubahan data penting aplikasi.</p>
            </div>

            <form class="grid gap-3 rounded-lg border bg-card p-3 lg:grid-cols-[1fr_180px_260px_auto_auto]" @submit.prevent="applyFilters">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="filterForm.search" type="search" class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700" placeholder="Cari deskripsi, user, model" />
                </div>
                <select v-model="filterForm.action" class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700">
                    <option value="">Semua aksi</option>
                    <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                </select>
                <select v-model="filterForm.model_type" class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700">
                    <option value="">Semua model</option>
                    <option v-for="model in modelTypes" :key="model" :value="model">{{ model.split('\\').pop() }}</option>
                </select>
                <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                    <SlidersHorizontal class="size-4" />
                    Filter
                </button>
                <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
            </form>

            <div class="overflow-hidden rounded-lg border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Aksi</th>
                                <th class="px-4 py-3">Model</th>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Perubahan</th>
                                <th class="px-4 py-3">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs.data" :key="log.id" class="border-b align-top last:border-0">
                                <td class="whitespace-nowrap px-4 py-3">{{ log.created_at || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ log.action }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ log.model_type || '-' }}</div>
                                    <div class="text-xs text-muted-foreground">ID {{ log.model_id || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ log.user?.name || 'System' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ log.user?.email || '-' }}</div>
                                </td>
                                <td class="max-w-xl px-4 py-3">
                                    <div class="text-xs text-muted-foreground">Old: {{ shortJson(log.old_values) }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">New: {{ shortJson(log.new_values) }}</div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ log.ip_address || '-' }}</td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada audit log.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                    <span>Menampilkan {{ logs.from ?? 0 }}-{{ logs.to ?? 0 }} dari {{ logs.total }} data</span>
                    <div class="flex gap-2">
                        <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                        <span class="px-2 py-1.5">Halaman {{ logs.current_page }} / {{ logs.last_page }}</span>
                        <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Berikutnya</Link>
                        <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
