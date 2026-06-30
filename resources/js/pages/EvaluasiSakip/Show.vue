<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Option = { id?: number; value?: string; label: string; bobot?: string | number; nilai_maksimal?: string | number };
type EvaluasiItem = {
    id: number;
    nilai: string | number;
    skor: string | number;
    catatan?: string | null;
    rekomendasi_text?: string | null;
    kriteria?: {
        id: number;
        kode: string;
        nama: string;
        bobot: string | number;
        nilai_maksimal: string | number;
        sub_komponen?: { kode: string; nama: string; komponen?: { kode: string; nama: string } | null } | null;
    } | null;
};
type TindakLanjut = {
    id: number;
    uraian_tindak_lanjut: string;
    status_tindak_lanjut: string;
    tanggal_tindak_lanjut?: string | null;
    catatan_opd?: string | null;
    catatan_verifikator?: string | null;
    created_by?: { name: string } | null;
    diverifikasi_oleh?: { name: string } | null;
    diverifikasi_at?: string | null;
};
type TindakLanjutFormData = {
    rekomendasi_id: string;
    uraian_tindak_lanjut: string;
    status_tindak_lanjut: string;
    tanggal_tindak_lanjut: string;
    catatan_opd: string;
};
type Rekomendasi = {
    id: number;
    nomor?: string | null;
    rekomendasi: string;
    prioritas: string;
    status_tindak_lanjut: string;
    target_tanggal?: string | null;
    item?: { id: number; kriteria?: { kode: string; nama: string } | null } | null;
    tindak_lanjut: TindakLanjut[];
};
type Workflow = {
    status: string;
    histories: Array<{
        id: number;
        action: string;
        from_status?: string | null;
        to_status: string;
        notes?: string | null;
        created_at: string;
        actor?: { name: string } | null;
    }>;
} | null;
type LheDocument = {
    id: number;
    judul: string;
    status: string;
    original_filename: string;
    mime_type?: string | null;
    file_size: number;
    created_at?: string | null;
    uploaded_by?: { name: string } | null;
    can_download: boolean;
    download_url?: string | null;
};

const props = defineProps<{
    evaluasi: {
        id: number;
        tahun: number;
        tanggal_evaluasi?: string | null;
        status: string;
        nilai_akhir: string | number;
        predikat?: string | null;
        catatan_umum?: string | null;
        opd?: { nama: string; singkatan?: string | null; kode?: string | null } | null;
        periode_tahun?: { nama: string; tahun: number } | null;
        evaluator?: { name: string } | null;
        items: EvaluasiItem[];
        lhe?: {
            nomor_lhe?: string | null;
            tanggal_lhe?: string | null;
            ringkasan?: string | null;
            nilai_akhir: string | number;
            predikat?: string | null;
            status: string;
        } | null;
        rekomendasi: Rekomendasi[];
    };
    lheDocuments: LheDocument[];
    kriteriaOptions: Option[];
    itemOptions: Option[];
    statusOptions: Option[];
    can: { manage: boolean; export_lhe: boolean; tindak_lanjut: boolean; verify_tindak_lanjut: boolean; review: boolean; lock: boolean };
    workflow: Workflow;
}>();

const itemForm = useForm({
    kriteria_evaluasi_id: '',
    nilai: '',
    catatan: '',
    rekomendasi_text: '',
});

const lheForm = useForm({
    nomor_lhe: props.evaluasi.lhe?.nomor_lhe ?? '',
    tanggal_lhe: props.evaluasi.lhe?.tanggal_lhe ?? '',
    ringkasan: props.evaluasi.lhe?.ringkasan ?? '',
    status: props.evaluasi.lhe?.status ?? 'draft',
});

const rekomendasiForm = useForm({
    evaluasi_sakip_item_id: '',
    nomor: '',
    rekomendasi: '',
    prioritas: 'sedang',
    status_tindak_lanjut: 'belum',
    target_tanggal: '',
});

const tindakLanjutForm = useForm({
    rekomendasi_id: '',
    uraian_tindak_lanjut: '',
    status_tindak_lanjut: 'proses',
    tanggal_tindak_lanjut: '',
    catatan_opd: '',
});

const verifyTindakLanjutForm = useForm({
    status_tindak_lanjut: 'selesai',
    catatan_verifikator: '',
});

const editingItemId = ref<number | null>(null);
const editingRekomendasiId = ref<number | null>(null);
const editingTindakLanjutId = ref<number | null>(null);
const selectedTindakLanjut = ref<TindakLanjut | null>(null);
const isVerifyDialogOpen = ref(false);

const resetItemForm = () => {
    editingItemId.value = null;
    itemForm.reset();
    itemForm.clearErrors();
};

const editItem = (item: EvaluasiItem) => {
    editingItemId.value = item.id;
    itemForm.kriteria_evaluasi_id = item.kriteria?.id ? String(item.kriteria.id) : '';
    itemForm.nilai = String(item.nilai ?? '');
    itemForm.catatan = item.catatan ?? '';
    itemForm.rekomendasi_text = item.rekomendasi_text ?? '';
};

const storeItem = () => {
    if (editingItemId.value) {
        itemForm.put(route('evaluasi-sakip.items.update', { evaluasi_sakip: props.evaluasi.id, item: editingItemId.value }), {
            preserveScroll: true,
            onSuccess: () => resetItemForm(),
        });

        return;
    }

    itemForm.post(route('evaluasi-sakip.items.store', { evaluasi_sakip: props.evaluasi.id }), {
        preserveScroll: true,
        onSuccess: () => resetItemForm(),
    });
};

const destroyItem = async (item: EvaluasiItem) => {
    if (await confirmDelete('Hapus nilai kriteria ini?')) {
        router.delete(route('evaluasi-sakip.items.destroy', { evaluasi_sakip: props.evaluasi.id, item: item.id }), { preserveScroll: true });
    }
};

const storeLhe = () => {
    lheForm.post(route('evaluasi-sakip.lhe.store', { evaluasi_sakip: props.evaluasi.id }), { preserveScroll: true });
};

const exportLhe = (format: 'pdf' | 'word') => {
    router.post(route('evaluasi-sakip.lhe.export', { evaluasi_sakip: props.evaluasi.id }), { format }, { preserveScroll: true });
};

const resetRekomendasiForm = () => {
    editingRekomendasiId.value = null;
    rekomendasiForm.reset();
    rekomendasiForm.clearErrors();
};

const editRekomendasi = (rekomendasi: Rekomendasi) => {
    editingRekomendasiId.value = rekomendasi.id;
    rekomendasiForm.evaluasi_sakip_item_id = rekomendasi.item?.id ? String(rekomendasi.item.id) : '';
    rekomendasiForm.nomor = rekomendasi.nomor ?? '';
    rekomendasiForm.rekomendasi = rekomendasi.rekomendasi;
    rekomendasiForm.prioritas = rekomendasi.prioritas;
    rekomendasiForm.status_tindak_lanjut = rekomendasi.status_tindak_lanjut;
    rekomendasiForm.target_tanggal = rekomendasi.target_tanggal ?? '';
};

const storeRekomendasi = () => {
    if (editingRekomendasiId.value) {
        rekomendasiForm.put(
            route('evaluasi-sakip.rekomendasi.update', { evaluasi_sakip: props.evaluasi.id, rekomendasi: editingRekomendasiId.value }),
            {
                preserveScroll: true,
                onSuccess: () => resetRekomendasiForm(),
            },
        );

        return;
    }

    rekomendasiForm.post(route('evaluasi-sakip.rekomendasi.store', { evaluasi_sakip: props.evaluasi.id }), {
        preserveScroll: true,
        onSuccess: () => resetRekomendasiForm(),
    });
};

const destroyRekomendasi = async (rekomendasi: Rekomendasi) => {
    if (await confirmDelete('Hapus rekomendasi ini?')) {
        router.delete(route('evaluasi-sakip.rekomendasi.destroy', { evaluasi_sakip: props.evaluasi.id, rekomendasi: rekomendasi.id }), {
            preserveScroll: true,
        });
    }
};

const resetTindakLanjutForm = () => {
    editingTindakLanjutId.value = null;
    tindakLanjutForm.reset();
    tindakLanjutForm.clearErrors();
};

const editTindakLanjut = (rekomendasi: Rekomendasi, tindakLanjut: TindakLanjut) => {
    editingTindakLanjutId.value = tindakLanjut.id;
    tindakLanjutForm.rekomendasi_id = String(rekomendasi.id);
    tindakLanjutForm.uraian_tindak_lanjut = tindakLanjut.uraian_tindak_lanjut;
    tindakLanjutForm.status_tindak_lanjut = tindakLanjut.status_tindak_lanjut;
    tindakLanjutForm.tanggal_tindak_lanjut = tindakLanjut.tanggal_tindak_lanjut ?? '';
    tindakLanjutForm.catatan_opd = tindakLanjut.catatan_opd ?? '';
};

const storeTindakLanjut = () => {
    if (!tindakLanjutForm.rekomendasi_id) return;

    const payload = (data: TindakLanjutFormData) => ({
        uraian_tindak_lanjut: data.uraian_tindak_lanjut,
        status_tindak_lanjut: data.status_tindak_lanjut,
        tanggal_tindak_lanjut: data.tanggal_tindak_lanjut,
        catatan_opd: data.catatan_opd,
    });

    if (editingTindakLanjutId.value) {
        tindakLanjutForm.transform(payload).put(route('tindak-lanjut-rekomendasi.update', { tindak_lanjut: editingTindakLanjutId.value }), {
            preserveScroll: true,
            onSuccess: () => resetTindakLanjutForm(),
        });

        return;
    }

    tindakLanjutForm.transform(payload).post(route('rekomendasi-evaluasi.tindak-lanjut.store', { rekomendasi: tindakLanjutForm.rekomendasi_id }), {
        preserveScroll: true,
        onSuccess: () => resetTindakLanjutForm(),
    });
};

const openVerifyTindakLanjut = (tindakLanjut: TindakLanjut, status: string) => {
    selectedTindakLanjut.value = tindakLanjut;
    verifyTindakLanjutForm.reset();
    verifyTindakLanjutForm.clearErrors();
    verifyTindakLanjutForm.status_tindak_lanjut = status;
    verifyTindakLanjutForm.catatan_verifikator = '';
    isVerifyDialogOpen.value = true;
};

const submitVerifyTindakLanjut = () => {
    if (!selectedTindakLanjut.value) return;

    verifyTindakLanjutForm.patch(route('tindak-lanjut-rekomendasi.verify', { tindak_lanjut: selectedTindakLanjut.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isVerifyDialogOpen.value = false;
            selectedTindakLanjut.value = null;
            verifyTindakLanjutForm.reset();
        },
    });
};

const canEditTindakLanjut = (tindakLanjut: TindakLanjut) => {
    return props.can.tindak_lanjut && !(tindakLanjut.status_tindak_lanjut === 'selesai' && tindakLanjut.diverifikasi_oleh);
};

const statusLabel = (status: string) =>
    props.statusOptions.find((option) => option.value === status)?.label ??
    {
        belum: 'Belum',
        proses: 'Proses',
        selesai: 'Selesai',
        ditolak: 'Ditolak',
        perlu_perbaikan: 'Perlu Perbaikan',
    }[status] ??
    status;

const statusClass = (status: string) =>
    ({
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-blue-100 text-blue-800',
        revision: 'bg-amber-100 text-amber-800',
        verified: 'bg-cyan-100 text-cyan-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
        locked: 'bg-zinc-200 text-zinc-800',
        belum: 'bg-slate-100 text-slate-700',
        proses: 'bg-blue-100 text-blue-800',
        selesai: 'bg-emerald-100 text-emerald-800',
        ditolak: 'bg-red-100 text-red-800',
        perlu_perbaikan: 'bg-amber-100 text-amber-800',
    })[status] ?? 'bg-slate-100 text-slate-700';

const formatFileSize = (bytes: number) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <Head :title="`Evaluasi SAKIP ${evaluasi.tahun}`" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Evaluasi SAKIP {{ evaluasi.tahun }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm text-muted-foreground">
                    <span>{{ evaluasi.opd?.singkatan || evaluasi.opd?.nama || '-' }}</span>
                    <span>-</span>
                    <span>{{ evaluasi.periode_tahun?.nama || evaluasi.tahun }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(evaluasi.status)">{{
                        statusLabel(evaluasi.status)
                    }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-if="can.export_lhe" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportLhe('pdf')">
                    Export LHE PDF
                </button>
                <button v-if="can.export_lhe" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportLhe('word')">
                    Export LHE Word
                </button>
                <Link v-if="can.manage" :href="route('evaluasi-sakip.edit', evaluasi.id)" class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >Edit</Link
                >
                <WorkflowActionButtons
                    module="evaluasi_sakip"
                    :model-id="evaluasi.id"
                    :status="evaluasi.status"
                    :can-manage="can.manage"
                    :can-review="can.review"
                    :can-lock="can.lock"
                    :show-verify="false"
                />
                <Link :href="route('evaluasi-sakip.index')" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Kembali</Link>
            </div>
        </div>

        <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-4">
            <div>
                <div class="text-xs uppercase text-muted-foreground">Nilai Akhir</div>
                <div class="mt-1 text-2xl font-semibold">{{ evaluasi.nilai_akhir }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Predikat</div>
                <div class="mt-1 text-2xl font-semibold">{{ evaluasi.predikat || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Evaluator</div>
                <div class="mt-1 font-medium">{{ evaluasi.evaluator?.name || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Tanggal Evaluasi</div>
                <div class="mt-1 font-medium">{{ evaluasi.tanggal_evaluasi || '-' }}</div>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />

        <section v-if="can.manage" class="rounded-lg border bg-card p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <h2 class="text-sm font-semibold">{{ editingItemId ? 'Edit Nilai Kriteria' : 'Input Nilai Kriteria' }}</h2>
                <button v-if="editingItemId" type="button" class="w-fit rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="resetItemForm">
                    Batal Edit
                </button>
            </div>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeItem">
                <div class="grid gap-1 md:col-span-2">
                    <select v-model="itemForm.kriteria_evaluasi_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih kriteria</option>
                        <option v-for="option in kriteriaOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="itemForm.errors.kriteria_evaluasi_id" />
                </div>
                <div class="grid gap-1">
                    <input
                        v-model="itemForm.nilai"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        placeholder="Nilai 0-100"
                    />
                    <InputError :message="itemForm.errors.nilai" />
                </div>
                <input
                    v-model="itemForm.rekomendasi_text"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    placeholder="Catatan rekomendasi ringkas"
                />
                <textarea
                    v-model="itemForm.catatan"
                    rows="3"
                    class="rounded-md border bg-background px-3 py-2 text-sm md:col-span-2"
                    placeholder="Catatan evaluator"
                />
                <div class="md:col-span-2">
                    <button
                        type="submit"
                        :disabled="itemForm.processing"
                        class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        {{ editingItemId ? 'Update Nilai' : 'Simpan Nilai' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Nilai per Kriteria</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Kriteria</th>
                            <th class="px-4 py-3">Nilai</th>
                            <th class="px-4 py-3">Skor</th>
                            <th class="px-4 py-3">Catatan</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in evaluasi.items" :key="item.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ item.kriteria?.kode }} - {{ item.kriteria?.nama }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.kriteria?.sub_komponen?.komponen?.nama }} / {{ item.kriteria?.sub_komponen?.nama }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ item.nilai }}</td>
                            <td class="px-4 py-3">{{ item.skor }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ item.catatan || '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="can.manage" class="flex justify-end gap-2">
                                    <button type="button" class="rounded-md border px-2 py-1 text-xs hover:bg-muted" @click="editItem(item)">
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="destroyItem(item)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="evaluasi.items.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">Belum ada nilai kriteria.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Laporan Hasil Evaluasi</h2>
                <form v-if="can.manage" class="mt-4 grid gap-3" @submit.prevent="storeLhe">
                    <input v-model="lheForm.nomor_lhe" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Nomor LHE" />
                    <input v-model="lheForm.tanggal_lhe" type="date" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <select v-model="lheForm.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <textarea
                        v-model="lheForm.ringkasan"
                        rows="4"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Ringkasan LHE"
                    />
                    <button
                        type="submit"
                        :disabled="lheForm.processing"
                        class="w-fit rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        Simpan LHE
                    </button>
                </form>
                <div v-else class="mt-3 text-sm text-muted-foreground">{{ evaluasi.lhe?.ringkasan || 'LHE belum tersedia.' }}</div>

                <div class="mt-5 border-t pt-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <h3 class="text-sm font-semibold">Dokumen LHE Otomatis</h3>
                        <div v-if="can.export_lhe" class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportLhe('pdf')">PDF</button>
                            <button type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportLhe('word')">Word</button>
                        </div>
                    </div>
                    <div v-if="lheDocuments.length" class="mt-3 divide-y rounded-md border text-sm">
                        <article
                            v-for="document in lheDocuments"
                            :key="document.id"
                            class="flex flex-col gap-3 p-3 md:flex-row md:items-center md:justify-between"
                        >
                            <div>
                                <div class="font-medium">{{ document.judul }}</div>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>{{ document.original_filename }}</span>
                                    <span>-</span>
                                    <span>{{ formatFileSize(document.file_size) }}</span>
                                    <span>-</span>
                                    <span>{{ document.created_at || '-' }}</span>
                                </div>
                            </div>
                            <a
                                v-if="document.can_download && document.download_url"
                                :href="document.download_url"
                                class="rounded-md border px-3 py-2 text-center text-sm hover:bg-muted"
                                >Unduh</a
                            >
                            <span v-else class="text-xs text-muted-foreground">Tidak ada akses unduh</span>
                        </article>
                    </div>
                    <div v-else class="mt-3 rounded-md border px-3 py-6 text-center text-sm text-muted-foreground">
                        Belum ada dokumen LHE otomatis.
                    </div>
                </div>
            </div>

            <div v-if="can.manage" class="rounded-lg border bg-card p-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <h2 class="text-sm font-semibold">{{ editingRekomendasiId ? 'Edit Rekomendasi' : 'Tambah Rekomendasi' }}</h2>
                    <button
                        v-if="editingRekomendasiId"
                        type="button"
                        class="w-fit rounded-md border px-3 py-2 text-sm hover:bg-muted"
                        @click="resetRekomendasiForm"
                    >
                        Batal Edit
                    </button>
                </div>
                <form class="mt-4 grid gap-3" @submit.prevent="storeRekomendasi">
                    <select v-model="rekomendasiForm.evaluasi_sakip_item_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tidak terkait kriteria tertentu</option>
                        <option v-for="option in itemOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <div class="grid gap-3 md:grid-cols-3">
                        <input v-model="rekomendasiForm.nomor" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Nomor" />
                        <select v-model="rekomendasiForm.prioritas" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="rendah">Rendah</option>
                            <option value="sedang">Sedang</option>
                            <option value="tinggi">Tinggi</option>
                        </select>
                        <input v-model="rekomendasiForm.target_tanggal" type="date" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    </div>
                    <div class="grid gap-1">
                        <textarea
                            v-model="rekomendasiForm.rekomendasi"
                            rows="4"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="Uraian rekomendasi"
                        />
                        <InputError :message="rekomendasiForm.errors.rekomendasi" />
                    </div>
                    <button
                        type="submit"
                        :disabled="rekomendasiForm.processing"
                        class="w-fit rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        {{ editingRekomendasiId ? 'Update Rekomendasi' : 'Simpan Rekomendasi' }}
                    </button>
                </form>
            </div>
        </section>

        <section v-if="can.tindak_lanjut" class="rounded-lg border bg-card p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <h2 class="text-sm font-semibold">{{ editingTindakLanjutId ? 'Edit Tindak Lanjut Rekomendasi' : 'Tindak Lanjut Rekomendasi' }}</h2>
                <button
                    v-if="editingTindakLanjutId"
                    type="button"
                    class="w-fit rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    @click="resetTindakLanjutForm"
                >
                    Batal Edit
                </button>
            </div>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeTindakLanjut">
                <div class="grid gap-1 md:col-span-2">
                    <select
                        v-model="tindakLanjutForm.rekomendasi_id"
                        :disabled="Boolean(editingTindakLanjutId)"
                        class="h-9 rounded-md border bg-background px-3 text-sm disabled:opacity-70"
                    >
                        <option value="">Pilih rekomendasi</option>
                        <option v-for="rekomendasi in evaluasi.rekomendasi" :key="rekomendasi.id" :value="rekomendasi.id">
                            {{ rekomendasi.nomor || `Rekomendasi #${rekomendasi.id}` }} - {{ rekomendasi.rekomendasi }}
                        </option>
                    </select>
                </div>
                <select v-model="tindakLanjutForm.status_tindak_lanjut" class="h-9 rounded-md border bg-background px-3 text-sm">
                    <option value="proses">Proses</option>
                    <option value="selesai">Selesai</option>
                </select>
                <input v-model="tindakLanjutForm.tanggal_tindak_lanjut" type="date" class="h-9 rounded-md border bg-background px-3 text-sm" />
                <div class="grid gap-1 md:col-span-2">
                    <textarea
                        v-model="tindakLanjutForm.uraian_tindak_lanjut"
                        rows="4"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Uraian tindak lanjut"
                    />
                    <InputError :message="tindakLanjutForm.errors.uraian_tindak_lanjut" />
                </div>
                <textarea
                    v-model="tindakLanjutForm.catatan_opd"
                    rows="3"
                    class="rounded-md border bg-background px-3 py-2 text-sm md:col-span-2"
                    placeholder="Catatan OPD"
                />
                <button
                    type="submit"
                    :disabled="tindakLanjutForm.processing"
                    class="w-fit rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                >
                    {{ editingTindakLanjutId ? 'Update Tindak Lanjut' : 'Kirim Tindak Lanjut' }}
                </button>
            </form>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Rekomendasi dan Tindak Lanjut</h2>
            <div v-if="evaluasi.rekomendasi.length" class="mt-3 space-y-3">
                <article v-for="rekomendasi in evaluasi.rekomendasi" :key="rekomendasi.id" class="rounded-md border bg-background p-3">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div class="font-medium">{{ rekomendasi.nomor || `Rekomendasi #${rekomendasi.id}` }}</div>
                            <p class="mt-1 text-sm text-muted-foreground">{{ rekomendasi.rekomendasi }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">Prioritas {{ rekomendasi.prioritas }}</span>
                                <span class="rounded-full px-2 py-1 font-medium" :class="statusClass(rekomendasi.status_tindak_lanjut)">{{
                                    statusLabel(rekomendasi.status_tindak_lanjut)
                                }}</span>
                            </div>
                        </div>
                        <div v-if="can.manage" class="flex gap-2">
                            <button type="button" class="rounded-md border px-2 py-1 text-xs hover:bg-muted" @click="editRekomendasi(rekomendasi)">
                                Edit
                            </button>
                            <button
                                type="button"
                                class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                @click="destroyRekomendasi(rekomendasi)"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                    <div v-if="rekomendasi.tindak_lanjut.length" class="mt-3 divide-y rounded-md border bg-card text-sm">
                        <div v-for="tl in rekomendasi.tindak_lanjut" :key="tl.id" class="p-3">
                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="font-medium">{{ tl.uraian_tindak_lanjut }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ tl.created_by?.name || '-' }} - {{ tl.tanggal_tindak_lanjut || '-' }}
                                    </div>
                                    <div v-if="tl.catatan_opd" class="mt-2 text-xs text-muted-foreground">Catatan OPD: {{ tl.catatan_opd }}</div>
                                    <div v-if="tl.catatan_verifikator" class="mt-2 text-xs text-muted-foreground">
                                        Catatan verifikator: {{ tl.catatan_verifikator }}
                                    </div>
                                    <div v-if="tl.diverifikasi_oleh" class="mt-2 text-xs text-muted-foreground">
                                        Diverifikasi oleh {{ tl.diverifikasi_oleh.name }} pada {{ tl.diverifikasi_at || '-' }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(tl.status_tindak_lanjut)">{{
                                        statusLabel(tl.status_tindak_lanjut)
                                    }}</span>
                                    <button
                                        v-if="canEditTindakLanjut(tl)"
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        @click="editTindakLanjut(rekomendasi, tl)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="can.verify_tindak_lanjut"
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        @click="openVerifyTindakLanjut(tl, 'selesai')"
                                    >
                                        Verifikasi
                                    </button>
                                    <button
                                        v-if="can.verify_tindak_lanjut"
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                                        @click="openVerifyTindakLanjut(tl, 'perlu_perbaikan')"
                                    >
                                        Perbaikan
                                    </button>
                                    <button
                                        v-if="can.verify_tindak_lanjut"
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="openVerifyTindakLanjut(tl, 'ditolak')"
                                    >
                                        Tolak
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            <div v-else class="mt-3 text-sm text-muted-foreground">Belum ada rekomendasi evaluasi.</div>
        </section>

        <Dialog v-model:open="isVerifyDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Verifikasi Tindak Lanjut</DialogTitle>
                    <DialogDescription>
                        Status akan diubah menjadi {{ statusLabel(verifyTindakLanjutForm.status_tindak_lanjut) }} dan catatan verifikator disimpan
                        pada riwayat tindak lanjut.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-3" @submit.prevent="submitVerifyTindakLanjut">
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium" for="catatan-verifikator">
                            Catatan verifikator
                            <span v-if="['ditolak', 'perlu_perbaikan'].includes(verifyTindakLanjutForm.status_tindak_lanjut)" class="text-red-700"
                                >*</span
                            >
                        </label>
                        <textarea
                            id="catatan-verifikator"
                            v-model="verifyTindakLanjutForm.catatan_verifikator"
                            rows="4"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            placeholder="Tuliskan catatan hasil verifikasi tindak lanjut."
                        />
                        <InputError :message="verifyTindakLanjutForm.errors.catatan_verifikator" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" :disabled="verifyTindakLanjutForm.processing" @click="isVerifyDialogOpen = false"
                            >Batal</Button
                        >
                        <Button type="submit" :disabled="verifyTindakLanjutForm.processing" class="bg-emerald-700 text-white hover:bg-emerald-800">
                            {{ verifyTindakLanjutForm.processing ? 'Memproses...' : 'Simpan Verifikasi' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
