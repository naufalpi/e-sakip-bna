<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Option = { id: number; label: string };
type ItemRow = {
    id: number;
    periode_realisasi: string;
    triwulan?: string | null;
    bulan?: number | null;
    aksi: string;
    indikator?: string | null;
    target?: string | number | null;
    target_text?: string | null;
    anggaran?: string | number | null;
    penanggung_jawab?: string | null;
    status?: string | null;
    opd_program?: { nama: string } | null;
};
type Workflow = {
    histories: Array<{ id: number; from_status?: string | null; to_status: string; created_at: string; actor?: { name: string } | null }>;
} | null;

const props = defineProps<{
    item: {
        id: number;
        judul: string;
        tahun: number;
        status: string;
        catatan?: string | null;
        opd?: { nama: string; singkatan?: string | null } | null;
        periode_tahun?: { tahun: number; nama: string } | null;
        perjanjian_kinerja?: { judul: string; tahun: number } | null;
        items: ItemRow[];
    };
    nodeOptions: {
        opd_program?: Option[];
        opd_kegiatan?: Option[];
        opd_sub_kegiatan?: Option[];
    };
    perjanjianKinerjaItemOptions: Option[];
    workflow: Workflow;
    can: { manage: boolean; review: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Rencana Aksi', href: '/rencana-aksi' },
    { title: props.item.judul, href: '#' },
];

const form = useForm({
    perjanjian_kinerja_item_id: '',
    opd_program_id: '',
    opd_kegiatan_id: '',
    opd_sub_kegiatan_id: '',
    periode_realisasi: 'triwulan',
    triwulan: '',
    bulan: '',
    aksi: '',
    indikator: '',
    target: '',
    target_text: '',
    anggaran: '',
    penanggung_jawab: '',
    status: 'draft',
    urutan: 1,
});

const storeItem = () => {
    form.post(route('rencana-aksi.items.store', { rencana_aksi: props.item.id }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const destroyItem = (row: ItemRow) => {
    if (confirm('Hapus item Rencana Aksi ini?')) {
        router.delete(route('rencana-aksi.items.destroy', { rencana_aksi: props.item.id, item: row.id }), { preserveScroll: true });
    }
};

const transition = (action: string) => {
    router.post(route('workflow.transition', { module: 'rencana_aksi', id: props.item.id }), { action }, { preserveScroll: true });
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
</script>

<template>
    <Head :title="item.judul" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">{{ item.judul }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm text-muted-foreground">
                        <span>{{ item.opd?.singkatan || item.opd?.nama || '-' }}</span>
                        <span>-</span>
                        <span>{{ item.tahun }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="can.manage" :href="route('rencana-aksi.edit', item.id)" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Edit</Link>
                    <button v-if="can.manage" type="button" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800" @click="transition('submit')">Ajukan</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="transition('verify')">Verifikasi</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="transition('approve')">Setujui</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm text-amber-700 hover:bg-amber-50" @click="transition('revision')">Revisi</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm text-red-700 hover:bg-red-50" @click="transition('reject')">Tolak</button>
                </div>
            </div>

            <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-3">
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Periode</div>
                    <div class="mt-1 font-medium">{{ item.periode_tahun?.nama || item.tahun }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Perjanjian Kinerja</div>
                    <div class="mt-1 font-medium">{{ item.perjanjian_kinerja?.judul || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Catatan</div>
                    <div class="mt-1 font-medium">{{ item.catatan || '-' }}</div>
                </div>
            </section>

            <section v-if="can.manage" class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Tambah Item Rencana Aksi</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeItem">
                    <select v-model="form.perjanjian_kinerja_item_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Referensi item PK</option>
                        <option v-for="option in perjanjianKinerjaItemOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <select v-model="form.opd_program_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Program OPD</option>
                        <option v-for="option in nodeOptions.opd_program" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <select v-model="form.opd_kegiatan_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Kegiatan OPD</option>
                        <option v-for="option in nodeOptions.opd_kegiatan" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <select v-model="form.opd_sub_kegiatan_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Sub kegiatan OPD</option>
                        <option v-for="option in nodeOptions.opd_sub_kegiatan" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <select v-model="form.periode_realisasi" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="bulanan">Bulanan</option>
                        <option value="triwulan">Triwulan</option>
                        <option value="semester">Semester</option>
                        <option value="tahunan">Tahunan</option>
                    </select>
                    <select v-model="form.triwulan" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Triwulan</option>
                        <option value="tw1">TW1</option>
                        <option value="tw2">TW2</option>
                        <option value="tw3">TW3</option>
                        <option value="tw4">TW4</option>
                    </select>
                    <input v-model="form.bulan" type="number" min="1" max="12" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Bulan" />
                    <input v-model="form.urutan" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Urutan" />
                    <div class="grid gap-1 md:col-span-2">
                        <textarea v-model="form.aksi" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" placeholder="Rencana aksi" />
                        <InputError :message="form.errors.aksi" />
                    </div>
                    <textarea v-model="form.indikator" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" placeholder="Indikator aksi" />
                    <input v-model="form.penanggung_jawab" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Penanggung jawab" />
                    <input v-model="form.target" type="number" step="0.0001" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Target angka" />
                    <input v-model="form.target_text" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Target teks" />
                    <input v-model="form.anggaran" type="number" step="0.01" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Anggaran" />
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Simpan Item</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">Item Rencana Aksi</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Aksi</th>
                                <th class="px-4 py-3">Periode</th>
                                <th class="px-4 py-3">Target</th>
                                <th class="px-4 py-3">Penanggung Jawab</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in item.items" :key="row.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ row.aksi }}</div>
                                    <div class="text-xs text-muted-foreground">{{ row.opd_program?.nama || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ row.periode_realisasi }} {{ row.triwulan || row.bulan || '' }}</td>
                                <td class="px-4 py-3">{{ row.target_text || row.target || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.penanggung_jawab || '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button v-if="can.manage" type="button" class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50" @click="destroyItem(row)">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="item.items.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">Belum ada item Rencana Aksi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Riwayat Workflow</h2>
                <div v-if="workflow?.histories?.length" class="mt-3 divide-y text-sm">
                    <div v-for="history in workflow.histories" :key="history.id" class="py-3">
                        <div class="font-medium">{{ statusLabel(history.from_status || 'draft') }} ke {{ statusLabel(history.to_status) }}</div>
                        <div class="text-xs text-muted-foreground">{{ history.actor?.name || '-' }} - {{ history.created_at }}</div>
                    </div>
                </div>
                <div v-else class="mt-3 text-sm text-muted-foreground">Belum ada riwayat workflow.</div>
            </section>
        </div>
    </AppLayout>
</template>
