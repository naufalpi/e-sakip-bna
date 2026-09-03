<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmAction } from '@/lib/sweetAlert';
import { Head, router } from '@inertiajs/vue3';
import { Activity, CalendarClock, Database, Eraser, Filter, Globe2, Monitor, Search, ShieldCheck, UserRound } from 'lucide-vue-next';
import { reactive } from 'vue';

type Change = { field: string; field_label: string; from: string; to: string };
type LogRow = {
    id: number;
    action: string;
    action_label: string;
    model_type?: string | null;
    model_label: string;
    model_id?: number | null;
    subject?: string | null;
    summary: string;
    changes: Change[];
    ip_address?: string | null;
    device_label: string;
    created_at?: string | null;
    user?: { name: string; email: string } | null;
};
type PaginationLink = { url: string | null; label: string; active: boolean };
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links?: PaginationLink[];
};
type FilterOption = { value: string; label: string };

const props = defineProps<{
    logs: Paginator<LogRow>;
    filters: { search?: string; action?: string; model_type?: string; per_page?: string | number };
    actions: FilterOption[];
    modelTypes: FilterOption[];
    stats: { total: number; today: number; users: number };
    canClear: boolean;
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    action: props.filters.action ?? '',
    model_type: props.filters.model_type ?? '',
    per_page: String(props.filters.per_page ?? '10'),
});
const applyFilters = () => router.get(route('audit-log.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.action = '';
    filterForm.model_type = '';
    filterForm.per_page = '10';
    applyFiltersNow();
};

const clearAll = async () => {
    const confirmed = await confirmAction({
        title: 'Hapus semua audit log?',
        text: `Seluruh ${props.stats.total.toLocaleString('id-ID')} catatan aktivitas akan dihapus permanen dan tidak dapat dipulihkan.`,
        icon: 'warning',
        confirmButtonText: 'Ya, hapus semua',
        cancelButtonText: 'Batal',
        focusCancel: true,
        destructive: true,
    });

    if (confirmed) router.delete(route('audit-log.destroy-all'));
};

const formatTime = (value?: string | null) => {
    if (!value) return 'Waktu tidak tersedia';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
};

const actionStyle = (action: string) => {
    if (action === 'deleted') return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300';
    if (action === 'created')
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (action.includes('sync'))
        return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300';

    return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300';
};
</script>

<template>
    <Head title="Audit Log" />
    <div class="mx-auto flex w-full max-w-[1500px] flex-col gap-5 p-4 sm:p-6">
        <header
            class="relative overflow-hidden rounded-2xl border border-slate-200 bg-[linear-gradient(135deg,#f8fafc_0%,#ffffff_55%,#ecfdf5_100%)] p-5 shadow-sm dark:border-slate-800 dark:bg-[linear-gradient(135deg,#0f172a_0%,#020617_55%,#052e2b_100%)] sm:p-6"
        >
            <div class="absolute -right-12 -top-20 size-56 rounded-full bg-emerald-200/30 blur-3xl dark:bg-emerald-500/10"></div>
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm shadow-emerald-900/20"
                    >
                        <ShieldCheck class="size-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[.15em] text-emerald-700 dark:text-emerald-300">Keamanan sistem</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Jejak Aktivitas</h1>
                    </div>
                </div>
                <button
                    v-if="canClear && stats.total > 0"
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-xl border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 shadow-sm transition hover:border-rose-300 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 dark:border-rose-900 dark:bg-slate-950 dark:text-rose-300 dark:hover:bg-rose-950/40"
                    @click="clearAll"
                >
                    <Eraser class="size-4" />Hapus semua log
                </button>
            </div>
            <div class="relative mt-5 grid gap-3 sm:grid-cols-3">
                <div
                    class="rounded-xl border border-white/80 bg-white/75 px-4 py-3 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500"><Database class="size-3.5" />Seluruh catatan</div>
                    <p class="mt-1 text-xl font-bold text-slate-950 dark:text-white">{{ stats.total.toLocaleString('id-ID') }}</p>
                </div>
                <div
                    class="rounded-xl border border-white/80 bg-white/75 px-4 py-3 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <CalendarClock class="size-3.5" />Aktivitas hari ini
                    </div>
                    <p class="mt-1 text-xl font-bold text-slate-950 dark:text-white">{{ stats.today.toLocaleString('id-ID') }}</p>
                </div>
                <div
                    class="rounded-xl border border-white/80 bg-white/75 px-4 py-3 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500"><UserRound class="size-3.5" />Pengguna tercatat</div>
                    <p class="mt-1 text-xl font-bold text-slate-950 dark:text-white">{{ stats.users.toLocaleString('id-ID') }}</p>
                </div>
            </div>
        </header>

        <form
            class="grid gap-3 rounded-2xl border border-slate-200 bg-card p-4 shadow-sm dark:border-slate-800 lg:grid-cols-[minmax(260px,1fr)_210px_260px_auto]"
            @submit.prevent="applyFiltersNow"
        >
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-10 w-full rounded-xl border bg-background pl-9 pr-3 text-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 dark:focus:ring-emerald-950"
                    placeholder="Cari pengguna atau aktivitas..."
                />
            </div>
            <select
                v-model="filterForm.action"
                class="h-10 rounded-xl border bg-background px-3 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
            >
                <option value="">Semua aktivitas</option>
                <option v-for="action in actions" :key="action.value" :value="action.value">{{ action.label }}</option>
            </select>
            <select
                v-model="filterForm.model_type"
                class="h-10 rounded-xl border bg-background px-3 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
            >
                <option value="">Semua jenis data</option>
                <option v-for="model in modelTypes" :key="model.value" :value="model.value">{{ model.label }}</option>
            </select>
            <button
                type="button"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white"
                @click="resetFilters"
            >
                <Filter class="size-4" />Reset
            </button>
        </form>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] table-fixed text-left text-sm">
                    <thead
                        class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold uppercase tracking-[.08em] text-slate-500 dark:border-slate-800 dark:bg-slate-900/70"
                    >
                        <tr>
                            <th class="w-[170px] px-4 py-3.5">Waktu</th>
                            <th class="w-[340px] px-4 py-3.5">Aktivitas</th>
                            <th class="w-[220px] px-4 py-3.5">Pengguna</th>
                            <th class="w-[290px] px-4 py-3.5">Perubahan Data</th>
                            <th class="w-[200px] px-4 py-3.5">Akses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <tr v-for="log in logs.data" :key="log.id" class="align-top transition hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                            <td class="px-4 py-4">
                                <time class="font-medium leading-5 text-slate-700 dark:text-slate-200" :datetime="log.created_at || undefined">
                                    {{ formatTime(log.created_at) }}
                                </time>
                                <p class="mt-1 text-[11px] text-slate-400">Log #{{ log.id }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-start gap-2.5">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg border" :class="actionStyle(log.action)">
                                        <Activity class="size-3.5" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span
                                                class="rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                                :class="actionStyle(log.action)"
                                            >
                                                {{ log.action_label }}
                                            </span>
                                            <span class="text-[11px] font-semibold text-slate-500">{{ log.model_label }}</span>
                                            <span v-if="log.model_id" class="text-[10px] text-slate-400">#{{ log.model_id }}</span>
                                        </div>
                                        <p class="mt-2 break-words font-semibold leading-5 text-slate-900 dark:text-slate-100">{{ log.summary }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-start gap-2">
                                    <UserRound class="mt-0.5 size-4 shrink-0 text-slate-400" />
                                    <div class="min-w-0">
                                        <p class="font-semibold leading-5 text-slate-800 dark:text-slate-100">
                                            {{ log.user?.name || 'Proses sistem' }}
                                        </p>
                                        <p class="mt-0.5 break-all text-xs leading-5 text-slate-500">
                                            {{ log.user?.email || 'Tanpa akun pengguna' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <details
                                    v-if="log.changes.length"
                                    class="rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-950/50"
                                >
                                    <summary
                                        class="cursor-pointer select-none px-3 py-2 text-xs font-semibold text-emerald-700 marker:text-slate-400 dark:text-emerald-300"
                                    >
                                        {{ log.changes.length }} bagian berubah · lihat detail
                                    </summary>
                                    <div class="max-h-64 space-y-2 overflow-y-auto border-t border-slate-200 p-3 dark:border-slate-700">
                                        <div
                                            v-for="change in log.changes"
                                            :key="change.field"
                                            class="rounded-md bg-slate-50 px-2.5 py-2 text-xs dark:bg-slate-900"
                                        >
                                            <p class="font-bold text-slate-700 dark:text-slate-200">{{ change.field_label }}</p>
                                            <p class="mt-1 break-words leading-5 text-slate-500">
                                                {{ change.from }} <span class="mx-1 text-slate-300">→</span>
                                                <b class="font-semibold text-slate-800 dark:text-slate-100">{{ change.to }}</b>
                                            </p>
                                        </div>
                                    </div>
                                </details>
                                <span v-else class="text-xs text-slate-400">Tidak ada rincian nilai.</span>
                            </td>
                            <td class="px-4 py-4">
                                <p class="flex items-center gap-1.5 font-medium text-slate-700 dark:text-slate-200">
                                    <Globe2 class="size-3.5 shrink-0 text-slate-400" />{{ log.ip_address || 'Tidak tersedia' }}
                                </p>
                                <p class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-slate-500">
                                    <Monitor class="mt-0.5 size-3.5 shrink-0" />{{ log.device_label }}
                                </p>
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="5" class="px-5 py-16 text-center">
                                <div
                                    class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"
                                >
                                    <ShieldCheck class="size-6" />
                                </div>
                                <p class="mt-4 font-semibold text-slate-800 dark:text-slate-100">Belum ada aktivitas yang cocok</p>
                                <p class="mt-1 text-sm text-slate-500">Ubah filter pencarian atau tunggu aktivitas baru tercatat.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <DataPagination v-model:per-page="filterForm.per_page" :paginator="logs" item-label="audit log" />
        </section>

        <p class="px-1 text-xs leading-5 text-slate-500">
            Alamat IP menunjukkan sumber koneksi yang diterima server. Pada instalasi dengan reverse proxy, IP pengguna akan tampil setelah alamat
            proxy didaftarkan sebagai proxy tepercaya.
        </p>
    </div>
</template>
