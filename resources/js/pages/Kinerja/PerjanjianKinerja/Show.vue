<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LockKeyhole, Printer, WalletCards } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Option = { id: number; label: string };
type ItemRow = {
    id: number;
    sasaran_opd_id?: number | null;
    indikator_sasaran_opd_id?: number | null;
    opd_program_id?: number | null;
    satuan_indikator_id?: number | null;
    satuan_snapshot?: string | null;
    jenis_item?: string | null;
    is_readonly?: boolean;
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

const props = defineProps<{
    item: {
        id: number;
        judul: string;
        nomor_dokumen?: string | null;
        tahun: number;
        status: string;
        tipe_pk: string;
        level_pk: string;
        tipe_pk_label: string;
        sumber_data?: string | null;
        tanggal_dokumen?: string | null;
        tempat_penandatanganan?: string | null;
        pegawai?: { nama: string; nip?: string | null; pangkat_golongan?: string | null } | null;
        penempatan_pegawai?: { jabatan?: { nama: string } | null } | null;
        atasan_pegawai?: { nama: string; nip?: string | null } | null;
        catatan?: string | null;
        opd?: { nama: string; singkatan?: string | null } | null;
        periode_tahun?: { tahun: number; nama: string } | null;
        renstra_opd?: { judul: string; tahun_awal: number; tahun_akhir: number } | null;
        rkpd?: { judul: string; tahun: number; jenis_versi: string; status: string } | null;
        dpa_opd?: { judul: string; tahun: number; nomor_dpa?: string | null; status: string } | null;
        items: ItemRow[];
        programs: Array<{ id: number; kode?: string | null; nama_program: string; anggaran: string | number; keterangan?: string | null }>;
    };
    nodeOptions: {
        sasaran_opd?: Option[];
        indikator_sasaran_opd?: Option[];
        opd_program?: Option[];
    };
    satuanOptions: Option[];
    workflow: Workflow;
    documentPreview: {
        level_label: string;
        source_label: string;
        place_date: string;
        first_party: { name: string; nip?: string | null; position: string };
        second_party?: { name: string; nip?: string | null; position: string } | null;
        performance_groups: Array<{
            number?: number | null;
            type: string;
            type_label: string;
            code?: string | null;
            performance: string;
            indicators: Array<{ id: number; name: string; target: string; unit: string }>;
        }>;
        programs: Array<{ id: number; code?: string | null; name: string; budget: number; budget_label: string; note: string }>;
        total_budget_label: string;
        missing_targets_count: number;
    };
    can: { manage: boolean; review: boolean; lock: boolean; export: boolean };
}>();

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

const editingItemId = ref<number | null>(null);
const canEditItems = computed(() => props.can.manage && !['bupati', 'kepala_opd'].includes(props.item.level_pk));
const openPrint = () => window.open(route('perjanjian-kinerja.print', props.item.id), '_blank', 'noopener,noreferrer');

const resetItemForm = () => {
    editingItemId.value = null;
    form.reset();
    form.clearErrors();
};

const editItem = (row: ItemRow) => {
    editingItemId.value = row.id;
    form.sasaran_opd_id = row.sasaran_opd_id ? String(row.sasaran_opd_id) : '';
    form.indikator_sasaran_opd_id = row.indikator_sasaran_opd_id ? String(row.indikator_sasaran_opd_id) : '';
    form.opd_program_id = row.opd_program_id ? String(row.opd_program_id) : '';
    form.satuan_indikator_id = row.satuan_indikator_id ? String(row.satuan_indikator_id) : '';
    form.kode = row.kode || '';
    form.sasaran = row.sasaran;
    form.indikator = row.indikator;
    form.target = row.target === null || row.target === undefined ? '' : String(row.target);
    form.target_text = row.target_text || '';
    form.urutan = row.urutan || 1;
};

const submitItem = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetItemForm(),
    };

    if (editingItemId.value) {
        form.put(route('perjanjian-kinerja.items.update', { perjanjian_kinerja: props.item.id, item: editingItemId.value }), options);
        return;
    }

    form.post(route('perjanjian-kinerja.items.store', { perjanjian_kinerja: props.item.id }), options);
};

const destroyItem = async (row: ItemRow) => {
    if (await confirmDelete('Hapus item Perjanjian Kinerja ini?')) {
        router.delete(route('perjanjian-kinerja.items.destroy', { perjanjian_kinerja: props.item.id, item: row.id }), { preserveScroll: true });
    }
};

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Perlu Perbaikan',
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
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 border-b pb-5 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">{{ documentPreview.level_label }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ item.judul }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm text-muted-foreground">
                    <span>{{ item.opd?.singkatan || item.opd?.nama || 'Pemerintah Kabupaten Banjarnegara' }}</span>
                    <span>-</span>
                    <span>{{ item.tahun }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold">{{ item.tipe_pk_label }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold hover:bg-muted" @click="openPrint">
                    <Printer class="size-4" /> Cetak
                </button>
                <Link v-if="can.manage" :href="route('perjanjian-kinerja.edit', item.id)" class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >Edit</Link
                >
                <WorkflowActionButtons
                    module="perjanjian_kinerja"
                    :model-id="item.id"
                    :status="item.status"
                    :can-manage="can.manage"
                    :can-review="can.review"
                    :can-lock="can.lock"
                />
            </div>
        </div>

        <section class="grid gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="bg-card p-4 h-full"><div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Pihak Pertama</div><div class="mt-1 font-semibold">{{ documentPreview.first_party.name }}</div><div class="text-xs text-muted-foreground">{{ documentPreview.first_party.position }}</div></div>
            </div>
            <div>
                <div class="bg-card p-4 h-full"><div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Pihak Kedua</div><div class="mt-1 font-semibold">{{ documentPreview.second_party?.name || 'Tidak berlaku' }}</div><div class="text-xs text-muted-foreground">{{ documentPreview.second_party?.position || 'PK Bupati' }}</div></div>
            </div>
            <div>
                <div class="bg-card p-4 h-full"><div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Sumber Resmi</div><div class="mt-1 font-semibold">{{ documentPreview.source_label }}</div><div class="text-xs text-muted-foreground">Snapshot terkunci dari sumber</div></div>
            </div>
            <div>
                <div class="bg-card p-4 h-full"><div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Penandatanganan</div><div class="mt-1 font-semibold">{{ documentPreview.place_date }}</div><div class="text-xs text-muted-foreground">{{ item.nomor_dokumen || 'Nomor belum diisi' }}</div></div>
            </div>
        </section>

        <section v-if="['bupati', 'kepala_opd'].includes(item.level_pk)" class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50/60 p-4 text-sm text-blue-950 dark:border-blue-900/70 dark:bg-blue-950/25 dark:text-blue-100">
            <LockKeyhole class="mt-0.5 size-5 shrink-0" />
            <div><p class="font-bold">Matriks dibuat otomatis dan tidak dapat diedit dari PK</p><p class="mt-1 text-xs leading-5 opacity-80">Jika ada target atau program yang keliru, koreksi dokumen sumber resmi kemudian ubah sumber PK untuk membentuk ulang snapshot.</p></div>
        </section>

        <section v-if="canEditItems" class="rounded-lg border bg-card p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">
                    {{
                        editingItemId
                            ? 'Edit Item Sasaran dan Indikator'
                            : item.tipe_pk === 'individual'
                              ? 'Tambah Hasil Kerja Individu'
                              : 'Tambah Item Cascading'
                    }}
                </h2>
                <button v-if="editingItemId" type="button" class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted" @click="resetItemForm">
                    Batal edit
                </button>
            </div>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="submitItem">
                <div v-if="item.tipe_pk === 'cascading'" class="grid gap-1">
                    <select v-model="form.sasaran_opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Referensi sasaran OPD</option>
                        <option v-for="option in nodeOptions.sasaran_opd" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.sasaran_opd_id" />
                </div>
                <div v-if="item.tipe_pk === 'cascading'" class="grid gap-1">
                    <select v-model="form.indikator_sasaran_opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Referensi indikator sasaran</option>
                        <option v-for="option in nodeOptions.indikator_sasaran_opd" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.indikator_sasaran_opd_id" />
                </div>
                <div v-if="item.tipe_pk === 'cascading'" class="grid gap-1">
                    <select v-model="form.opd_program_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Referensi program OPD</option>
                        <option v-for="option in nodeOptions.opd_program" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_program_id" />
                </div>
                <div class="grid gap-1">
                    <select v-model="form.satuan_indikator_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Satuan indikator</option>
                        <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.satuan_indikator_id" />
                </div>
                <input v-model="form.kode" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Kode" />
                <input v-model="form.urutan" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Urutan" />
                <div class="grid gap-1 md:col-span-2">
                    <textarea
                        v-model="form.sasaran"
                        rows="2"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Sasaran kinerja"
                    />
                    <InputError :message="form.errors.sasaran" />
                </div>
                <div class="grid gap-1 md:col-span-2">
                    <textarea
                        v-model="form.indikator"
                        rows="2"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Indikator kinerja"
                    />
                    <InputError :message="form.errors.indikator" />
                </div>
                <input
                    v-model="form.target"
                    type="number"
                    step="0.0001"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    placeholder="Target angka"
                />
                <input v-model="form.target_text" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Target teks" />
                <div class="md:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        {{ editingItemId ? 'Perbarui Item' : 'Simpan Item' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="flex items-center justify-between border-b bg-muted/25 px-5 py-4"><div><h2 class="font-bold">Matriks Perjanjian Kinerja</h2><p class="mt-0.5 text-xs text-muted-foreground">Tujuan, sasaran, dan indikator sesuai level dokumen.</p></div><span class="rounded-full border bg-background px-3 py-1 text-xs font-semibold">{{ item.items.length }} indikator</span></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Tujuan / Sasaran Strategis</th>
                            <th class="px-4 py-3">Indikator</th>
                            <th class="px-4 py-3">Target</th>
                            <th v-if="canEditItems" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in item.items" :key="row.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="mb-1 text-[10px] font-bold uppercase tracking-wider text-primary">{{ row.jenis_item?.replaceAll('_', ' ') || 'Hasil kerja' }}</div><div class="font-medium">{{ row.sasaran }}</div><div v-if="row.kode" class="text-xs text-muted-foreground">{{ row.kode }}</div>
                            </td>
                            <td class="px-4 py-3">{{ row.indikator }}</td>
                            <td class="px-4 py-3 font-semibold">{{ row.target_text || row.target || '-' }} {{ row.satuan_snapshot || row.satuan?.simbol || '' }}</td>
                            <td v-if="canEditItems" class="px-4 py-3 text-right">
                                <button
                                    v-if="!row.is_readonly"
                                    type="button"
                                    class="mr-2 rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                    @click="editItem(row)"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="!row.is_readonly"
                                    type="button"
                                    class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                    @click="destroyItem(row)"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="item.items.length === 0">
                            <td :colspan="canEditItems ? 4 : 3" class="px-4 py-8 text-center text-muted-foreground">Belum ada item Perjanjian Kinerja.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="documentPreview.programs.length" class="overflow-hidden rounded-xl border bg-card">
            <div class="flex items-center justify-between border-b bg-muted/25 px-5 py-4"><div class="flex items-center gap-3"><div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-700 dark:text-emerald-400"><WalletCards class="size-5" /></div><div><h2 class="font-bold">Program dan Anggaran</h2><p class="text-xs text-muted-foreground">Anggaran final sesuai dokumen sumber.</p></div></div><div class="text-right"><div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Total Anggaran</div><div class="font-bold text-emerald-700 dark:text-emerald-400">{{ documentPreview.total_budget_label }}</div></div></div>
            <div class="divide-y">
                <div v-for="program in documentPreview.programs" :key="program.id" class="grid gap-2 px-5 py-3 text-sm md:grid-cols-[1fr_180px_130px] md:items-center"><div><span v-if="program.code" class="mr-2 font-mono text-xs font-bold text-primary">{{ program.code }}</span><span class="font-medium">{{ program.name }}</span></div><div class="font-semibold md:text-right">{{ program.budget_label }}</div><div class="text-xs text-muted-foreground md:text-center">{{ program.note }}</div></div>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />
    </div>
</template>
