<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, Download, FileBadge2, FileText, LockKeyhole, Printer, RotateCcw, Save, WalletCards, X } from 'lucide-vue-next';
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
        letterhead: {
            nama_pemerintah: string;
            nama_instansi: string;
            alamat?: string | null;
            telepon?: string | null;
            faksimile?: string | null;
            website?: string | null;
            email?: string | null;
            kota: string;
            kode_pos?: string | null;
            logo_path?: string | null;
        };
        logo_data_uri?: string | null;
    };
    can: { manage: boolean; edit_kop: boolean; review: boolean; lock: boolean; export: boolean };
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
const showKopEditor = ref(false);
const canEditItems = computed(
    () => props.can.manage && (props.item.tipe_pk === 'individual' || ['manual', 'penugasan'].includes(props.item.sumber_data ?? 'manual')),
);
const kopForm = useForm({
    nama_pemerintah: props.documentPreview.letterhead.nama_pemerintah,
    nama_instansi: props.documentPreview.letterhead.nama_instansi,
    alamat: props.documentPreview.letterhead.alamat ?? '',
    telepon: props.documentPreview.letterhead.telepon ?? '',
    faksimile: props.documentPreview.letterhead.faksimile ?? '',
    website: props.documentPreview.letterhead.website ?? '',
    email: props.documentPreview.letterhead.email ?? '',
    kota: props.documentPreview.letterhead.kota,
    kode_pos: props.documentPreview.letterhead.kode_pos ?? '',
});

const openKopEditor = () => {
    const kop = props.documentPreview.letterhead;
    kopForm.nama_pemerintah = kop.nama_pemerintah;
    kopForm.nama_instansi = kop.nama_instansi;
    kopForm.alamat = kop.alamat ?? '';
    kopForm.telepon = kop.telepon ?? '';
    kopForm.faksimile = kop.faksimile ?? '';
    kopForm.website = kop.website ?? '';
    kopForm.email = kop.email ?? '';
    kopForm.kota = kop.kota;
    kopForm.kode_pos = kop.kode_pos ?? '';
    kopForm.clearErrors();
    showKopEditor.value = true;
};

const saveKop = () => {
    kopForm.patch(route('perjanjian-kinerja.kop.update', props.item.id), {
        preserveScroll: true,
        onSuccess: () => (showKopEditor.value = false),
    });
};

const useStandardKop = () => {
    router.patch(
        route('perjanjian-kinerja.kop.update', props.item.id),
        { gunakan_default: true },
        {
            preserveScroll: true,
            onSuccess: () => (showKopEditor.value = false),
        },
    );
};

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
                <button
                    v-if="can.edit_kop"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold hover:bg-muted"
                    @click="openKopEditor"
                >
                    <FileBadge2 class="size-4" /> Atur Kop
                </button>
                <details class="group relative">
                    <summary
                        class="inline-flex cursor-pointer list-none items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm font-semibold transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-details-marker]:hidden"
                    >
                        <Printer class="size-4" />
                        Cetak
                        <ChevronDown class="size-3.5 transition-transform duration-200 group-open:rotate-180" />
                    </summary>
                    <div
                        class="absolute right-0 z-30 mt-2 w-72 overflow-hidden rounded-xl border bg-popover p-1.5 text-popover-foreground shadow-xl shadow-slate-950/10"
                    >
                        <a
                            :href="route('perjanjian-kinerja.print', item.id)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-start gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline-none dark:hover:bg-red-950/30"
                        >
                            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                <FileText class="size-4" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold">Cetak PDF</span>
                                <span class="mt-0.5 block text-xs leading-4 text-muted-foreground">Buka berkas PDF siap cetak di tab baru.</span>
                            </span>
                        </a>
                        <a
                            :href="route('perjanjian-kinerja.download.docx', item.id)"
                            class="mt-1 flex items-start gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-blue-50 focus-visible:bg-blue-50 focus-visible:outline-none dark:hover:bg-blue-950/30"
                        >
                            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                                <Download class="size-4" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold">Unduh DOCX</span>
                                <span class="mt-0.5 block text-xs leading-4 text-muted-foreground">Simpan dokumen yang dapat dibuka di Word.</span>
                            </span>
                        </a>
                    </div>
                </details>
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
                <div class="h-full bg-card p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Pihak Pertama</div>
                    <div class="mt-1 font-semibold">{{ documentPreview.first_party.name }}</div>
                    <div class="text-xs text-muted-foreground">{{ documentPreview.first_party.position }}</div>
                </div>
            </div>
            <div>
                <div class="h-full bg-card p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Pihak Kedua</div>
                    <div class="mt-1 font-semibold">{{ documentPreview.second_party?.name || 'Tidak berlaku' }}</div>
                    <div class="text-xs text-muted-foreground">{{ documentPreview.second_party?.position || 'PK Bupati' }}</div>
                </div>
            </div>
            <div>
                <div class="h-full bg-card p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Sumber Resmi</div>
                    <div class="mt-1 font-semibold">{{ documentPreview.source_label }}</div>
                    <div class="text-xs text-muted-foreground">Snapshot terkunci dari sumber</div>
                </div>
            </div>
            <div>
                <div class="h-full bg-card p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Penandatanganan</div>
                    <div class="mt-1 font-semibold">{{ documentPreview.place_date }}</div>
                    <div class="text-xs text-muted-foreground">{{ item.nomor_dokumen || 'Nomor belum diisi' }}</div>
                </div>
            </div>
        </section>

        <section
            v-if="item.tipe_pk === 'cascading'"
            class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50/60 p-4 text-sm text-blue-950 dark:border-blue-900/70 dark:bg-blue-950/25 dark:text-blue-100"
        >
            <LockKeyhole class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-bold">Matriks cascading dibekukan sebagai snapshot</p>
                <p class="mt-1 text-xs leading-5 opacity-80">
                    Lingkup kinerja dipilih melalui Edit PK. Indikator dan target tetap mengikuti dokumen sumber resmi dan tidak dapat diubah langsung
                    pada matriks.
                </p>
            </div>
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
            <div class="flex items-center justify-between border-b bg-muted/25 px-5 py-4">
                <div>
                    <h2 class="font-bold">Matriks Perjanjian Kinerja</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">Tujuan, sasaran, dan indikator sesuai level dokumen.</p>
                </div>
                <span class="rounded-full border bg-background px-3 py-1 text-xs font-semibold">{{ item.items.length }} indikator</span>
            </div>
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
                                <div class="mb-1 text-[10px] font-bold uppercase tracking-wider text-primary">
                                    {{ row.jenis_item?.replaceAll('_', ' ') || 'Hasil kerja' }}
                                </div>
                                <div class="font-medium">{{ row.sasaran }}</div>
                                <div v-if="row.kode" class="text-xs text-muted-foreground">{{ row.kode }}</div>
                            </td>
                            <td class="px-4 py-3">{{ row.indikator }}</td>
                            <td class="px-4 py-3 font-semibold">
                                {{ row.target_text || row.target || '-' }} {{ row.satuan_snapshot || row.satuan?.simbol || '' }}
                            </td>
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
                            <td :colspan="canEditItems ? 4 : 3" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada item Perjanjian Kinerja.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="documentPreview.programs.length" class="overflow-hidden rounded-xl border bg-card">
            <div class="flex items-center justify-between border-b bg-muted/25 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-700 dark:text-emerald-400"><WalletCards class="size-5" /></div>
                    <div>
                        <h2 class="font-bold">Program dan Anggaran</h2>
                        <p class="text-xs text-muted-foreground">Anggaran final sesuai dokumen sumber.</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Total Anggaran</div>
                    <div class="font-bold text-emerald-700 dark:text-emerald-400">{{ documentPreview.total_budget_label }}</div>
                </div>
            </div>
            <div class="divide-y">
                <div
                    v-for="program in documentPreview.programs"
                    :key="program.id"
                    class="grid gap-2 px-5 py-3 text-sm md:grid-cols-[1fr_180px_130px] md:items-center"
                >
                    <div>
                        <span v-if="program.code" class="mr-2 font-mono text-xs font-bold text-primary">{{ program.code }}</span
                        ><span class="font-medium">{{ program.name }}</span>
                    </div>
                    <div class="font-semibold md:text-right">{{ program.budget_label }}</div>
                    <div class="text-xs text-muted-foreground md:text-center">{{ program.note }}</div>
                </div>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />

        <div
            v-if="showKopEditor"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
            @click.self="showKopEditor = false"
        >
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl border bg-background shadow-2xl">
                <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b bg-background/95 px-5 py-4 backdrop-blur">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">Khusus dokumen ini</p>
                        <h2 class="mt-1 text-lg font-bold">Kop dan Identitas Dokumen</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Perubahan hanya berlaku pada PK ini dan tidak mengubah kop standar OPD.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="Tutup"
                        @click="showKopEditor = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <form class="p-5" @submit.prevent="saveKop">
                    <section class="mb-5 overflow-hidden rounded-xl border bg-white text-slate-950 shadow-sm">
                        <div class="grid grid-cols-[68px_minmax(0,1fr)_30px] items-center gap-3 px-5 py-4">
                            <img
                                :src="documentPreview.logo_data_uri || '/images/logo-banjarnegara.png'"
                                alt="Logo kop"
                                class="mx-auto max-h-20 max-w-[64px] object-contain"
                            />
                            <div class="text-center leading-tight">
                                <div class="text-sm font-medium uppercase">{{ kopForm.nama_pemerintah }}</div>
                                <div class="mt-0.5 text-xl font-black uppercase tracking-tight">{{ kopForm.nama_instansi }}</div>
                                <div class="mt-1 text-[11px] leading-4">
                                    {{ kopForm.alamat }}<template v-if="kopForm.telepon"> Telepon {{ kopForm.telepon }}</template
                                    ><template v-if="kopForm.faksimile"> Faksimile {{ kopForm.faksimile }}</template
                                    ><template v-if="kopForm.website || kopForm.email"
                                        ><br /><span v-if="kopForm.website">Website {{ kopForm.website }}</span
                                        ><span v-if="kopForm.website && kopForm.email"> · </span
                                        ><span v-if="kopForm.email">Surel {{ kopForm.email }}</span></template
                                    >
                                </div>
                                <div class="text-[11px] font-semibold uppercase">{{ kopForm.kota }} {{ kopForm.kode_pos }}</div>
                            </div>
                        </div>
                        <div class="mx-5 border-b-2 border-slate-950"></div>
                    </section>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="kop-field md:col-span-2">
                            <label>Nama pemerintah</label><input v-model="kopForm.nama_pemerintah" /><InputError
                                :message="kopForm.errors.nama_pemerintah"
                            />
                        </div>
                        <div class="kop-field md:col-span-2">
                            <label>Nama instansi / perangkat daerah</label><input v-model="kopForm.nama_instansi" /><InputError
                                :message="kopForm.errors.nama_instansi"
                            />
                        </div>
                        <div class="kop-field md:col-span-2">
                            <label>Alamat</label><textarea v-model="kopForm.alamat" rows="2"></textarea
                            ><InputError :message="kopForm.errors.alamat" />
                        </div>
                        <div class="kop-field">
                            <label>Telepon</label><input v-model="kopForm.telepon" /><InputError :message="kopForm.errors.telepon" />
                        </div>
                        <div class="kop-field">
                            <label>Faksimile</label><input v-model="kopForm.faksimile" /><InputError :message="kopForm.errors.faksimile" />
                        </div>
                        <div class="kop-field">
                            <label>Website</label><input v-model="kopForm.website" /><InputError :message="kopForm.errors.website" />
                        </div>
                        <div class="kop-field">
                            <label>Surel</label><input v-model="kopForm.email" type="email" /><InputError :message="kopForm.errors.email" />
                        </div>
                        <div class="kop-field"><label>Kota</label><input v-model="kopForm.kota" /><InputError :message="kopForm.errors.kota" /></div>
                        <div class="kop-field">
                            <label>Kode pos</label><input v-model="kopForm.kode_pos" /><InputError :message="kopForm.errors.kode_pos" />
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-between">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-bold hover:bg-muted"
                            @click="useStandardKop"
                        >
                            <RotateCcw class="size-4" /> Gunakan Kop Standar
                        </button>
                        <button
                            type="submit"
                            :disabled="kopForm.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                        >
                            <Save class="size-4" />{{ kopForm.processing ? 'Menyimpan...' : 'Simpan Kop PK' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.kop-field {
    display: grid;
    gap: 0.35rem;
}
.kop-field label {
    color: hsl(var(--muted-foreground));
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.kop-field input,
.kop-field textarea {
    width: 100%;
    border: 1px solid hsl(var(--border));
    border-radius: 0.65rem;
    background: hsl(var(--background));
    padding: 0.7rem 0.8rem;
    color: hsl(var(--foreground));
    font-size: 0.875rem;
    outline: none;
}
.kop-field input:focus,
.kop-field textarea:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgb(59 130 246 / 0.12);
}
</style>
