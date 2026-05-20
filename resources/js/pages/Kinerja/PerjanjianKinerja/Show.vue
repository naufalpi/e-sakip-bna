<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Option = { id: number; label: string };
type ItemRow = {
    id: number;
    kode?: string | null;
    sasaran: string;
    indikator: string;
    target?: string | number | null;
    target_text?: string | null;
    urutan: number;
    satuan?: { nama: string; simbol?: string | null } | null;
    opd_program?: { kode?: string | null; nama: string } | null;
};
type Workflow = {
    status: string;
    histories: Array<{ id: number; action: string; from_status?: string | null; to_status: string; notes?: string | null; created_at: string; actor?: { name: string } | null }>;
} | null;

const props = defineProps<{
    item: {
        id: number;
        judul: string;
        nomor_dokumen?: string | null;
        tahun: number;
        status: string;
        catatan?: string | null;
        opd?: { nama: string; singkatan?: string | null } | null;
        periode_tahun?: { tahun: number; nama: string } | null;
        renstra_opd?: { judul: string; tahun_awal: number; tahun_akhir: number } | null;
        items: ItemRow[];
    };
    nodeOptions: {
        sasaran_opd?: Option[];
        indikator_sasaran_opd?: Option[];
        opd_program?: Option[];
    };
    satuanOptions: Option[];
    workflow: Workflow;
    can: { manage: boolean; review: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Perjanjian Kinerja', href: '/perjanjian-kinerja' },
    { title: props.item.judul, href: '#' },
];

const form = useForm({
    sasaran_opd_id: '',
    indikator_sasaran_opd_id: '',
    opd_program_id: '',
    satuan_indikator_id: '',
    kode: '',
    sasaran: '',
    indikator: '',
    target: '',
    target_text: '',
    urutan: 1,
});

const storeItem = () => {
    form.post(route('perjanjian-kinerja.items.store', { perjanjian_kinerja: props.item.id }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const destroyItem = (row: ItemRow) => {
    if (confirm('Hapus item Perjanjian Kinerja ini?')) {
        router.delete(route('perjanjian-kinerja.items.destroy', { perjanjian_kinerja: props.item.id, item: row.id }), { preserveScroll: true });
    }
};

const transition = (action: string) => {
    router.post(route('workflow.transition', { module: 'perjanjian_kinerja', id: props.item.id }), { action }, { preserveScroll: true });
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
                    <Link v-if="can.manage" :href="route('perjanjian-kinerja.edit', item.id)" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Edit</Link>
                    <button v-if="can.manage" type="button" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800" @click="transition('submit')">Ajukan</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="transition('verify')">Verifikasi</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="transition('approve')">Setujui</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm text-amber-700 hover:bg-amber-50" @click="transition('revision')">Revisi</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm text-red-700 hover:bg-red-50" @click="transition('reject')">Tolak</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="transition('lock')">Kunci</button>
                </div>
            </div>

            <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-3">
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Nomor Dokumen</div>
                    <div class="mt-1 font-medium">{{ item.nomor_dokumen || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Periode</div>
                    <div class="mt-1 font-medium">{{ item.periode_tahun?.nama || item.tahun }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Renstra</div>
                    <div class="mt-1 font-medium">{{ item.renstra_opd?.judul || '-' }}</div>
                </div>
            </section>

            <section v-if="can.manage" class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Tambah Item Sasaran dan Indikator</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeItem">
                    <select v-model="form.sasaran_opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Referensi sasaran OPD</option>
                        <option v-for="option in nodeOptions.sasaran_opd" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <select v-model="form.indikator_sasaran_opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Referensi indikator sasaran</option>
                        <option v-for="option in nodeOptions.indikator_sasaran_opd" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <select v-model="form.opd_program_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Referensi program OPD</option>
                        <option v-for="option in nodeOptions.opd_program" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <select v-model="form.satuan_indikator_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Satuan indikator</option>
                        <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <input v-model="form.kode" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Kode" />
                    <input v-model="form.urutan" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Urutan" />
                    <div class="grid gap-1 md:col-span-2">
                        <textarea v-model="form.sasaran" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" placeholder="Sasaran kinerja" />
                        <InputError :message="form.errors.sasaran" />
                    </div>
                    <div class="grid gap-1 md:col-span-2">
                        <textarea v-model="form.indikator" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" placeholder="Indikator kinerja" />
                        <InputError :message="form.errors.indikator" />
                    </div>
                    <input v-model="form.target" type="number" step="0.0001" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Target angka" />
                    <input v-model="form.target_text" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Target teks" />
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Simpan Item</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">Item Perjanjian Kinerja</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Sasaran</th>
                                <th class="px-4 py-3">Indikator</th>
                                <th class="px-4 py-3">Target</th>
                                <th class="px-4 py-3">Program</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in item.items" :key="row.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ row.sasaran }}</div>
                                    <div class="text-xs text-muted-foreground">{{ row.kode || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ row.indikator }}</td>
                                <td class="px-4 py-3">{{ row.target_text || row.target || '-' }} {{ row.satuan?.simbol || '' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.opd_program?.nama || '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button v-if="can.manage" type="button" class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50" @click="destroyItem(row)">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="item.items.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">Belum ada item Perjanjian Kinerja.</td>
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
