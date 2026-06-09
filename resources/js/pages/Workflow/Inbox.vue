<script setup lang="ts">
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Inbox, RotateCcw, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type Option = { value: string; label: string; description?: string };
type WorkflowContext = {
    title: string;
    subtitle?: string | null;
    opd?: { id: number; kode?: string | null; nama: string; singkatan?: string | null } | null;
    tahun?: string | null;
    status_data?: string | null;
    detail_url?: string | null;
    missing: boolean;
};
type WorkflowRow = {
    id: number;
    related_id: number;
    module: string;
    module_label: string;
    status: string;
    note?: string | null;
    submitted_at?: string | null;
    reviewed_at?: string | null;
    updated_at?: string | null;
    submitted_by?: { id: number; name: string } | null;
    current_reviewer?: { id: number; name: string } | null;
    context: WorkflowContext;
    can_manage: boolean;
    can_review: boolean;
    can_lock: boolean;
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
    items: Paginator<WorkflowRow>;
    filters: { search?: string; module?: string; status?: string; scope?: string };
    moduleOptions: Option[];
    statusOptions: Option[];
    scopeOptions: Option[];
    summary: {
        total: number;
        need_review: number;
        submitted: number;
        verified: number;
        revision: number;
        approved: number;
        rejected: number;
        locked: number;
    };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    module: props.filters.module ?? '',
    status: props.filters.status ?? '',
    scope: props.filters.scope ?? props.scopeOptions[0]?.value ?? 'mine',
});

const applyFilters = () => router.get(route('workflow.inbox'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.module = '';
    filterForm.status = '';
    filterForm.scope = props.scopeOptions[0]?.value ?? 'mine';
    applyFiltersNow();
};
const setScope = (scope: string) => {
    filterForm.scope = scope;
    applyFiltersNow();
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
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-blue-100 text-blue-800',
        revision: 'bg-amber-100 text-amber-800',
        verified: 'bg-cyan-100 text-cyan-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
        locked: 'bg-zinc-200 text-zinc-800',
    })[status] ?? 'bg-slate-100 text-slate-700';

const formatDate = (value?: string | null) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <Head title="Inbox Workflow" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Inbox Workflow</h1>
                <p class="mt-1 text-sm text-muted-foreground">Antrean pengajuan, verifikasi, persetujuan, dan revisi dokumen kinerja.</p>
            </div>
            <div class="inline-flex rounded-lg border bg-card p-1">
                <button
                    v-for="scope in scopeOptions"
                    :key="scope.value"
                    type="button"
                    class="h-8 rounded-md px-3 text-sm font-medium"
                    :class="filterForm.scope === scope.value ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-muted'"
                    @click="setScope(scope.value)"
                >
                    {{ scope.label }}
                </button>
            </div>
        </div>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-medium uppercase text-muted-foreground">Perlu Review</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.need_review }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-medium uppercase text-muted-foreground">Diajukan</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.submitted }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-medium uppercase text-muted-foreground">Terverifikasi</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.verified }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-medium uppercase text-muted-foreground">Revisi</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.revision }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-medium uppercase text-muted-foreground">Total Workflow</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.total }}</p>
            </div>
        </section>

        <form class="grid gap-3 rounded-lg border bg-card p-3 lg:grid-cols-[1fr_220px_180px_auto]" @submit.prevent="applyFiltersNow">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari dokumen, OPD, pengaju, atau reviewer"
                />
            </div>
            <select
                v-model="filterForm.module"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua modul</option>
                <option v-for="option in moduleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <select
                v-model="filterForm.status"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua status</option>
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted"
                @click="resetFilters"
            >
                <RotateCcw class="size-4" />
                Reset
            </button>
        </form>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Modul</th>
                            <th class="px-4 py-3">Dokumen</th>
                            <th class="px-4 py-3">OPD / Tahun</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Pengaju / Reviewer</th>
                            <th class="px-4 py-3">Update</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in items.data" :key="row.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800">
                                    <Inbox class="size-3.5" />
                                    {{ row.module_label }}
                                </div>
                            </td>
                            <td class="min-w-72 px-4 py-3">
                                <div class="font-medium">{{ row.context.title }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ row.context.subtitle || row.note || 'Tidak ada catatan tambahan.' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.context.opd?.singkatan || row.context.opd?.nama || 'Kabupaten' }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.context.tahun || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">{{
                                    statusLabel(row.status)
                                }}</span>
                                <div
                                    v-if="row.context.status_data && row.context.status_data !== row.status"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Data: {{ statusLabel(row.context.status_data) }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ row.submitted_by?.name || '-' }}</div>
                                <div class="text-xs text-muted-foreground">Reviewer: {{ row.current_reviewer?.name || 'Belum ditentukan' }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ formatDate(row.updated_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <WorkflowActionButtons
                                        :module="row.module"
                                        :model-id="row.related_id"
                                        :status="row.status"
                                        :can-manage="row.can_manage"
                                        :can-review="row.can_review"
                                        :can-lock="row.can_lock"
                                    />
                                    <Link
                                        v-if="row.context.detail_url"
                                        :href="row.context.detail_url"
                                        class="inline-flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs hover:bg-muted"
                                    >
                                        <ExternalLink class="size-3.5" />
                                        Detail
                                    </Link>
                                    <span v-else class="text-xs text-muted-foreground">Tidak tersedia</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Belum ada workflow sesuai filter.</td>
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
        </section>
    </div>
</template>
