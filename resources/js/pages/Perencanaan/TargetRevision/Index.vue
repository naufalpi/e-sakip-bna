<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Search, XCircle } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

type Revision = {
    id: number;
    module: string;
    target_table: string;
    target_id: number;
    status: string;
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    reason: string;
    document_number?: string | null;
    document_date?: string | null;
    review_note?: string | null;
    requested_by?: string | null;
    reviewed_by?: string | null;
    opd?: string | null;
    created_at?: string | null;
    reviewed_at?: string | null;
    applied_at?: string | null;
};

const props = defineProps<{
    revisions: {
        data: Revision[];
        links: Array<{ url?: string | null; label: string; active: boolean }>;
        prev_page_url?: string | null;
        next_page_url?: string | null;
    };
    filters: { status?: string; module?: string; search?: string };
    can: { review: boolean };
}>();

const filterForm = reactive({
    status: props.filters.status || '',
    module: props.filters.module || '',
    search: props.filters.search || '',
});

const noteForm = useForm({ note: '' });
const activeAction = ref<{ id: number; action: 'approve' | 'reject' } | null>(null);

const applyFilters = () => {
    router.get(route('target-revisions.index'), filterForm, { preserveState: true, replace: true });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.status = '';
    filterForm.module = '';
    filterForm.search = '';
    applyFiltersNow();
};

const openReview = (id: number, action: 'approve' | 'reject') => {
    activeAction.value = { id, action };
    noteForm.reset();
    noteForm.clearErrors();
};

const submitReview = () => {
    if (!activeAction.value) {
        return;
    }

    const routeName = activeAction.value.action === 'approve' ? 'target-revisions.approve' : 'target-revisions.reject';

    noteForm.patch(route(routeName, activeAction.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            activeAction.value = null;
            noteForm.reset();
        },
    });
};

const statusLabel = (status: string) =>
    ({
        submitted: 'Diajukan',
        approved: 'Disetujui',
        rejected: 'Ditolak',
    })[status] ?? status;

const statusClass = (status: string) =>
    ({
        submitted: 'bg-blue-100 text-blue-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
    })[status] ?? 'bg-slate-100 text-slate-700';

const moduleLabel = (module: string) =>
    ({
        rpjmd: 'RPJMD',
        renstra_opd: 'Renstra OPD',
    })[module] ?? module.replaceAll('_', ' ');

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const formatValues = (values: Record<string, unknown>) =>
    Object.entries(values)
        .map(([key, value]) => `${key}: ${value ?? '-'}`)
        .join(', ');
</script>

<template>
    <Head title="Revisi Target" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Revisi Target Formal</h1>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Riwayat pengajuan perubahan target tahunan/triwulan yang sudah disetujui atau terkunci. Perubahan nilai hanya diterapkan setelah
                    reviewer menyetujui pengajuan.
                </p>
            </div>

            <form class="grid gap-2 md:grid-cols-[160px_180px_1fr_auto]" @submit.prevent="applyFiltersNow">
                <select
                    v-model="filterForm.status"
                    class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    <option value="">Semua Status</option>
                    <option value="submitted">Diajukan</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                </select>
                <select
                    v-model="filterForm.module"
                    class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    <option value="">Semua Modul</option>
                    <option value="rpjmd">RPJMD</option>
                    <option value="renstra_opd">Renstra OPD</option>
                </select>
                <div class="relative">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                        placeholder="Cari alasan, dokumen, atau tabel target"
                    />
                </div>
                <button type="button" class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
            </form>
        </div>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3">Nilai Lama</th>
                            <th class="px-4 py-3">Nilai Baru</th>
                            <th class="px-4 py-3">Dasar Revisi</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in revisions.data" :key="row.id" class="border-b last:border-0">
                            <td class="min-w-[220px] px-4 py-3 align-top">
                                <div class="font-medium">{{ moduleLabel(row.module) }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.target_table }} #{{ row.target_id }}</div>
                                <div v-if="row.opd" class="mt-1 text-xs text-muted-foreground">{{ row.opd }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Diajukan {{ row.requested_by || '-' }} pada {{ formatDateTime(row.created_at) }}
                                </div>
                            </td>
                            <td class="min-w-[180px] px-4 py-3 align-top text-muted-foreground">{{ formatValues(row.old_values) }}</td>
                            <td class="min-w-[180px] px-4 py-3 align-top font-medium">{{ formatValues(row.new_values) }}</td>
                            <td class="min-w-[260px] px-4 py-3 align-top">
                                <div>{{ row.reason }}</div>
                                <div class="mt-2 text-xs text-muted-foreground">
                                    {{ row.document_number || 'Nomor dokumen belum diisi' }}
                                    <span v-if="row.document_date"> - {{ row.document_date }}</span>
                                </div>
                                <div v-if="row.review_note" class="mt-2 rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
                                    Catatan reviewer: {{ row.review_note }}
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(row.status)">
                                    {{ statusLabel(row.status) }}
                                </span>
                                <div v-if="row.reviewed_by" class="mt-2 text-xs text-muted-foreground">
                                    {{ row.reviewed_by }}<br />
                                    {{ formatDateTime(row.reviewed_at) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <div v-if="can.review && row.status === 'submitted'" class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs text-emerald-800 hover:bg-emerald-50"
                                        @click="openReview(row.id, 'approve')"
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                        Setujui
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="openReview(row.id, 'reject')"
                                    >
                                        <XCircle class="size-3.5" />
                                        Tolak
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">-</span>
                            </td>
                        </tr>
                        <tr v-if="revisions.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Belum ada pengajuan revisi target.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex items-center justify-between text-sm">
            <Link v-if="revisions.prev_page_url" :href="revisions.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                >Sebelumnya</Link
            >
            <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
            <Link v-if="revisions.next_page_url" :href="revisions.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                >Berikutnya</Link
            >
            <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
        </div>

        <div v-if="activeAction" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <form class="w-full max-w-lg rounded-lg bg-background p-4 shadow-lg" @submit.prevent="submitReview">
                <h2 class="text-base font-semibold">{{ activeAction.action === 'approve' ? 'Setujui Revisi Target' : 'Tolak Revisi Target' }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        activeAction.action === 'approve'
                            ? 'Nilai target akan langsung diperbarui setelah disetujui.'
                            : 'Catatan penolakan wajib diisi agar pengusul mengetahui alasan penolakan.'
                    }}
                </p>
                <textarea
                    v-model="noteForm.note"
                    rows="4"
                    class="mt-4 w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Catatan reviewer"
                />
                <p v-if="noteForm.errors.note" class="mt-1 text-sm text-red-700">{{ noteForm.errors.note }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="activeAction = null">Batal</button>
                    <button
                        type="submit"
                        :disabled="noteForm.processing"
                        class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        {{ activeAction.action === 'approve' ? 'Setujui' : 'Tolak' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
