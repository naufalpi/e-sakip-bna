<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Option = { id: number; label: string };
type ProgramRow = {
    id: number;
    perjanjian_kinerja_item_id?: number | null;
    rencana_aksi_item_id?: number | null;
    opd_program_id?: number | null;
    indikator_opd_program_id?: number | null;
    indikator: string;
    tipe_indikator?: string | null;
    target?: string | number | null;
    target_text?: string | null;
    realisasi?: string | number | null;
    realisasi_text?: string | null;
    capaian_persen?: string | number | null;
    anggaran?: string | number | null;
    realisasi_anggaran?: string | number | null;
    analisis_efisiensi?: string | null;
    kendala?: string | null;
    tindak_lanjut?: string | null;
    urutan?: number | null;
    opd_program?: { nama: string } | null;
};
type Workflow = {
    histories: Array<{ id: number; from_status?: string | null; to_status: string; created_at: string; actor?: { name: string } | null }>;
} | null;

const props = defineProps<{
    item: {
        id: number;
        tahun: number;
        periode_realisasi: string;
        triwulan?: string | null;
        bulan?: number | null;
        semester?: number | null;
        status: string;
        catatan?: string | null;
        opd?: { nama: string; singkatan?: string | null } | null;
        periode_tahun?: { tahun: number; nama: string } | null;
        perjanjian_kinerja?: { judul: string; tahun: number } | null;
        rencana_aksi?: { judul: string; tahun: number } | null;
        programs: ProgramRow[];
    };
    nodeOptions: {
        opd_program?: Option[];
        indikator_opd_program?: Option[];
    };
    perjanjianKinerjaItemOptions: Option[];
    rencanaAksiItemOptions: Option[];
    workflow: Workflow;
    can: { manage: boolean; review: boolean; lock: boolean; export: boolean };
}>();

const form = useForm({
    perjanjian_kinerja_item_id: '',
    rencana_aksi_item_id: '',
    opd_program_id: '',
    indikator_opd_program_id: '',
    tipe_indikator: 'positif',
    indikator: '',
    target: '',
    target_text: '',
    realisasi: '',
    realisasi_text: '',
    capaian_persen: '',
    anggaran: '',
    realisasi_anggaran: '',
    analisis_efisiensi: '',
    kendala: '',
    tindak_lanjut: '',
    urutan: 1,
});

const editingProgramId = ref<number | null>(null);

const resetProgramForm = () => {
    editingProgramId.value = null;
    form.reset();
    form.clearErrors();
};

const editProgram = (row: ProgramRow) => {
    editingProgramId.value = row.id;
    form.perjanjian_kinerja_item_id = row.perjanjian_kinerja_item_id ? String(row.perjanjian_kinerja_item_id) : '';
    form.rencana_aksi_item_id = row.rencana_aksi_item_id ? String(row.rencana_aksi_item_id) : '';
    form.opd_program_id = row.opd_program_id ? String(row.opd_program_id) : '';
    form.indikator_opd_program_id = row.indikator_opd_program_id ? String(row.indikator_opd_program_id) : '';
    form.tipe_indikator = row.tipe_indikator || 'positif';
    form.indikator = row.indikator;
    form.target = row.target === null || row.target === undefined ? '' : String(row.target);
    form.target_text = row.target_text || '';
    form.realisasi = row.realisasi === null || row.realisasi === undefined ? '' : String(row.realisasi);
    form.realisasi_text = row.realisasi_text || '';
    form.capaian_persen = row.capaian_persen === null || row.capaian_persen === undefined ? '' : String(row.capaian_persen);
    form.anggaran = row.anggaran === null || row.anggaran === undefined ? '' : String(row.anggaran);
    form.realisasi_anggaran = row.realisasi_anggaran === null || row.realisasi_anggaran === undefined ? '' : String(row.realisasi_anggaran);
    form.analisis_efisiensi = row.analisis_efisiensi || '';
    form.kendala = row.kendala || '';
    form.tindak_lanjut = row.tindak_lanjut || '';
    form.urutan = row.urutan || 1;
};

const submitProgram = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetProgramForm(),
    };

    if (editingProgramId.value) {
        form.put(route('realisasi-kinerja.programs.update', { realisasi_kinerja: props.item.id, program: editingProgramId.value }), options);
        return;
    }

    form.post(route('realisasi-kinerja.programs.store', { realisasi_kinerja: props.item.id }), options);
};

const destroyProgram = async (row: ProgramRow) => {
    if (await confirmDelete('Hapus realisasi indikator ini?')) {
        router.delete(route('realisasi-kinerja.programs.destroy', { realisasi_kinerja: props.item.id, program: row.id }), { preserveScroll: true });
    }
};

const exportReport = (format: 'pdf' | 'word') => {
    router.post(route('realisasi-kinerja.export', props.item.id), { format }, { preserveScroll: true });
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
    <Head :title="`Realisasi Kinerja ${item.tahun}`" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Realisasi Kinerja {{ item.tahun }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm text-muted-foreground">
                    <span>{{ item.opd?.singkatan || item.opd?.nama || '-' }}</span>
                    <span>-</span>
                    <span>{{ item.periode_realisasi }} {{ item.triwulan || item.bulan || item.semester || '' }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-if="can.export" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportReport('pdf')">
                    Export PDF
                </button>
                <button v-if="can.export" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportReport('word')">
                    Export Word
                </button>
                <Link v-if="can.manage" :href="route('realisasi-kinerja.edit', item.id)" class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >Edit</Link
                >
                <WorkflowActionButtons
                    module="realisasi_kinerja"
                    :model-id="item.id"
                    :status="item.status"
                    :can-manage="can.manage"
                    :can-review="can.review"
                    :can-lock="can.lock"
                />
            </div>
        </div>

        <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-3">
            <div>
                <div class="text-xs uppercase text-muted-foreground">Perjanjian Kinerja</div>
                <div class="mt-1 font-medium">{{ item.perjanjian_kinerja?.judul || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Rencana Aksi</div>
                <div class="mt-1 font-medium">{{ item.rencana_aksi?.judul || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Catatan</div>
                <div class="mt-1 font-medium">{{ item.catatan || '-' }}</div>
            </div>
        </section>

        <section v-if="can.manage" class="rounded-lg border bg-card p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">{{ editingProgramId ? 'Edit Realisasi Indikator' : 'Tambah Realisasi Indikator' }}</h2>
                <button v-if="editingProgramId" type="button" class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted" @click="resetProgramForm">
                    Batal edit
                </button>
            </div>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="submitProgram">
                <select v-model="form.perjanjian_kinerja_item_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                    <option value="">Referensi item PK</option>
                    <option v-for="option in perjanjianKinerjaItemOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select v-model="form.rencana_aksi_item_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                    <option value="">Referensi item Rencana Aksi</option>
                    <option v-for="option in rencanaAksiItemOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select v-model="form.opd_program_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                    <option value="">Program OPD</option>
                    <option v-for="option in nodeOptions.opd_program" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select v-model="form.indikator_opd_program_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                    <option value="">Indikator program OPD</option>
                    <option v-for="option in nodeOptions.indikator_opd_program" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
                <select v-model="form.tipe_indikator" class="h-9 rounded-md border bg-background px-3 text-sm">
                    <option value="positif">Indikator positif</option>
                    <option value="negatif">Indikator negatif</option>
                </select>
                <input v-model="form.urutan" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Urutan" />
                <div class="grid gap-1 md:col-span-2">
                    <textarea
                        v-model="form.indikator"
                        rows="2"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Indikator realisasi"
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
                <input
                    v-model="form.realisasi"
                    type="number"
                    step="0.0001"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    placeholder="Realisasi angka"
                />
                <input v-model="form.realisasi_text" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Realisasi teks" />
                <input
                    v-model="form.capaian_persen"
                    type="number"
                    step="0.01"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    placeholder="Capaian persen"
                />
                <input
                    v-model="form.anggaran"
                    type="number"
                    step="0.01"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    placeholder="Anggaran"
                />
                <input
                    v-model="form.realisasi_anggaran"
                    type="number"
                    step="0.01"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    placeholder="Realisasi anggaran"
                />
                <textarea
                    v-model="form.analisis_efisiensi"
                    rows="2"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                    placeholder="Analisis efisiensi"
                />
                <textarea v-model="form.kendala" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" placeholder="Kendala" />
                <textarea
                    v-model="form.tindak_lanjut"
                    rows="2"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                    placeholder="Tindak lanjut"
                />
                <div class="md:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        {{ editingProgramId ? 'Perbarui Realisasi' : 'Simpan Realisasi' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Realisasi Indikator</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Indikator</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3">Realisasi</th>
                            <th class="px-4 py-3">Capaian</th>
                            <th class="px-4 py-3">Kendala</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in item.programs" :key="row.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.indikator }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.opd_program?.nama || '-' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ row.target_text || row.target || '-' }}</td>
                            <td class="px-4 py-3">{{ row.realisasi_text || row.realisasi || '-' }}</td>
                            <td class="px-4 py-3">{{ row.capaian_persen || '-' }}%</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.kendala || '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="can.manage"
                                    type="button"
                                    class="mr-2 rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                    @click="editProgram(row)"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="can.manage"
                                    type="button"
                                    class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                    @click="destroyProgram(row)"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="item.programs.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Belum ada realisasi indikator.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />
    </div>
</template>
