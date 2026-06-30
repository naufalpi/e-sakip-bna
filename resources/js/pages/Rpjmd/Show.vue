<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { confirmDelete, toast } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, CopyPlus, Eye, EyeOff, GitBranch, Network, Pencil, Plus, Rows3, Table2, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

type Option = { id: number; label: string };
type NodeType =
    | 'visi'
    | 'misi'
    | 'tujuan'
    | 'indikator_tujuan'
    | 'target_tujuan'
    | 'sasaran'
    | 'indikator_sasaran'
    | 'target_sasaran'
    | 'strategi'
    | 'program'
    | 'indikator_program'
    | 'target_program'
    | 'program_opd';

type Target = {
    id: number;
    periode_tahun: { id: number; tahun: number; nama: string };
    target?: string | number | null;
    target_text?: string | null;
    pagu?: string | number | null;
};

type TargetTriwulan = {
    id: number;
    periode_tahun: { id: number; tahun: number; nama: string };
    triwulan: string;
    target_angka?: string | number | null;
    target_text?: string | null;
    target_anggaran?: string | number | null;
};

type Indikator = {
    id: number;
    kode?: string | null;
    indikator: string;
    tipe_indikator?: string | null;
    satuan_indikator_id?: number | null;
    formula?: string | null;
    sumber_data?: string | null;
    urutan?: number | null;
    satuan?: { nama: string; simbol?: string | null } | null;
    targets: Target[];
    target_triwulan: TargetTriwulan[];
};

type Program = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    status: string;
    urusan_pemerintahan_id?: number | null;
    urutan?: number | null;
    urusan_pemerintahan?: { kode: string; nama: string } | null;
    opd_penanggung_jawab: Array<{ pivot_id: number; id: number; nama: string; singkatan?: string | null; peran: string; is_utama: boolean }>;
    indikator: Indikator[];
};

type Strategi = {
    id: number;
    kode?: string | null;
    strategi: string;
    arah_kebijakan?: string | null;
    urutan?: number | null;
    programs: Program[];
};

type Sasaran = {
    id: number;
    kode?: string | null;
    sasaran: string;
    urutan?: number | null;
    indikator: Indikator[];
    strategi: Strategi[];
};

type Tujuan = {
    id: number;
    kode?: string | null;
    tujuan: string;
    urutan?: number | null;
    indikator: Indikator[];
    sasaran: Sasaran[];
};

type Misi = {
    id: number;
    kode?: string | null;
    misi: string;
    urutan?: number | null;
};

type Visi = {
    id: number;
    visi: string;
    urutan?: number | null;
    misi: Misi[];
    tujuan: Tujuan[];
};

type RpjmdDetail = {
    id: number;
    judul: string;
    nomor_perda?: string | null;
    tahun_awal: number;
    tahun_akhir: number;
    status: string;
    keterangan?: string | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
    visi: Visi[];
};
type RpjmdCascadingRow = {
    key: string;
    visi: string;
    misi: string;
    tujuan: string;
    indikator_tujuan: string;
    sasaran: string;
    indikator_sasaran: string;
    strategi: string;
    program: string;
    indikator_program: string;
    target_tahunan: string;
    target_triwulan: string;
    opd_penanggung_jawab: string;
    status_keterhubungan: string;
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

type BulkRow = {
    parent_id: number | string;
    periode_tahun_id: number | string;
    satuan_indikator_id: number | string;
    opd_id: number | string;
    urusan_pemerintahan_id: number | string;
    uraian: string;
    indikator: string;
    tipe_indikator: 'positif' | 'negatif';
    formula: string;
    sumber_data: string;
    arah_kebijakan: string;
    target: string;
    target_text: string;
    pagu: string;
    pagu_indikatif: string;
    peran: string;
    is_utama: boolean;
    urutan: number | string;
};

type BulkExistingRow = {
    id: number;
    type: NodeType;
    parent_id?: number | null;
    parent_label?: string | null;
    uraian?: string | null;
    indikator?: string | null;
    satuan?: string | null;
    satuan_indikator_id?: number | null;
    tipe_indikator?: string | null;
    formula?: string | null;
    sumber_data?: string | null;
    arah_kebijakan?: string | null;
    urusan?: string | null;
    urusan_pemerintahan_id?: number | null;
    target?: string | number | null;
    target_text?: string | null;
    pagu?: string | number | null;
    pagu_indikatif?: string | number | null;
    opd?: string | null;
    opd_id?: number | null;
    peran?: string | null;
    is_utama?: boolean | null;
    periode?: string | number | null;
    periode_tahun_id?: number | null;
    urutan?: number | null;
};

type SavedBulkSaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

const props = defineProps<{
    rpjmd: RpjmdDetail;
    nodeOptions: Record<string, Option[]>;
    targetTriwulanOptions: Record<string, Option[]>;
    periodeOptions: Option[];
    satuanOptions: Option[];
    opdOptions: Option[];
    urusanOptions: Option[];
    can: {
        manage: boolean;
        review: boolean;
        lock: boolean;
    };
    workflow: Workflow;
}>();

const typeOptions: Array<{ value: NodeType; label: string }> = [
    { value: 'visi', label: 'Visi' },
    { value: 'misi', label: 'Misi' },
    { value: 'tujuan', label: 'Tujuan Daerah' },
    { value: 'indikator_tujuan', label: 'Indikator Tujuan' },
    { value: 'target_tujuan', label: 'Target Indikator Tujuan' },
    { value: 'sasaran', label: 'Sasaran Daerah' },
    { value: 'indikator_sasaran', label: 'Indikator Sasaran' },
    { value: 'target_sasaran', label: 'Target Indikator Sasaran' },
    { value: 'strategi', label: 'Strategi Daerah' },
    { value: 'program', label: 'Program RPJMD' },
    { value: 'indikator_program', label: 'Indikator Program' },
    { value: 'target_program', label: 'Target Indikator Program' },
    { value: 'program_opd', label: 'OPD Penanggung Jawab Program' },
];

const typeMeta: Record<NodeType, { description: string; placeholder: string; helper: string }> = {
    visi: {
        description: 'Tuliskan rumusan visi RPJMD sebagai payung utama perencanaan daerah.',
        placeholder: 'Contoh: Banjarnegara yang maju, sejahtera, dan berdaya saing...',
        helper: 'Visi adalah titik awal. Setelah visi disimpan, misi dan tujuan dapat ditambahkan di bawah visi.',
    },
    misi: {
        description: 'Catat misi sebagai arah pembangunan di bawah visi. Di struktur Banjarnegara, misi tidak menjadi parent tujuan.',
        placeholder: 'Contoh: Meningkatkan kualitas pelayanan publik...',
        helper: 'Misi bersifat informatif untuk visi. Tujuan daerah tetap dipilih langsung dari visi.',
    },
    tujuan: {
        description: 'Tujuan daerah diturunkan langsung dari visi RPJMD.',
        placeholder: 'Contoh: Meningkatnya kualitas tata kelola pemerintahan...',
        helper: 'Pilih visi terlebih dahulu agar tujuan tersimpan pada cabang yang benar.',
    },
    indikator_tujuan: {
        description: 'Indikator tujuan mengukur keberhasilan tujuan daerah.',
        placeholder: 'Contoh: Indeks Reformasi Birokrasi',
        helper: 'Lengkapi satuan, tipe indikator, formula, dan sumber data agar perhitungan capaian siap digunakan.',
    },
    target_tujuan: {
        description: 'Target tahunan untuk indikator tujuan daerah.',
        placeholder: 'Contoh teks target jika target angka belum cukup menjelaskan.',
        helper: 'Target tahunan menjadi dasar pengukuran dan realisasi kinerja.',
    },
    sasaran: {
        description: 'Sasaran daerah menjabarkan tujuan menjadi hasil yang lebih operasional.',
        placeholder: 'Contoh: Meningkatnya akuntabilitas kinerja perangkat daerah...',
        helper: 'Pilih tujuan induk agar sasaran masuk ke jalur cascading yang tepat.',
    },
    indikator_sasaran: {
        description: 'Indikator sasaran mengukur pencapaian sasaran strategis daerah.',
        placeholder: 'Contoh: Nilai SAKIP Kabupaten',
        helper: 'Gunakan indikator yang bisa diukur dan memiliki sumber data yang jelas.',
    },
    target_sasaran: {
        description: 'Target tahunan untuk indikator sasaran daerah.',
        placeholder: 'Contoh teks target jika target angka belum cukup menjelaskan.',
        helper: 'Target ini akan dipakai untuk monitoring capaian per tahun.',
    },
    strategi: {
        description: 'Strategi daerah menjelaskan cara mencapai sasaran.',
        placeholder: 'Contoh: Penguatan sistem akuntabilitas kinerja perangkat daerah...',
        helper: 'Arah kebijakan dapat diisi untuk memperjelas fokus strategi.',
    },
    program: {
        description: 'Program RPJMD menjadi penghubung strategi dengan perangkat daerah penanggung jawab.',
        placeholder: 'Contoh: Program Penunjang Urusan Pemerintahan Daerah Kabupaten/Kota',
        helper: 'Lengkapi urusan pemerintahan dan pagu indikatif bila sudah tersedia.',
    },
    indikator_program: {
        description: 'Indikator program mengukur hasil program RPJMD.',
        placeholder: 'Contoh: Persentase perangkat daerah dengan nilai SAKIP minimal BB',
        helper: 'Indikator program akan menjadi referensi sinkronisasi dengan Renstra OPD.',
    },
    target_program: {
        description: 'Target tahunan dan pagu untuk indikator program RPJMD.',
        placeholder: 'Contoh teks target jika target angka belum cukup menjelaskan.',
        helper: 'Pagu tahunan membantu rekap anggaran dari perencanaan ke pengukuran.',
    },
    program_opd: {
        description: 'Tetapkan OPD penanggung jawab program RPJMD.',
        placeholder: '',
        helper: 'Satu program bisa memiliki lebih dari satu OPD. Tandai OPD utama jika menjadi koordinator.',
    },
};

const parentKeyByType: Partial<Record<NodeType, string>> = {
    misi: 'visi',
    tujuan: 'visi',
    indikator_tujuan: 'tujuan',
    target_tujuan: 'indikator_tujuan',
    sasaran: 'tujuan',
    indikator_sasaran: 'sasaran',
    target_sasaran: 'indikator_sasaran',
    strategi: 'sasaran',
    program: 'strategi',
    indikator_program: 'program',
    target_program: 'indikator_program',
    program_opd: 'program',
};

const parentLabels: Record<string, string> = {
    visi: 'Visi Induk',
    misi: 'Misi Induk',
    tujuan: 'Tujuan Induk',
    indikator_tujuan: 'Indikator Tujuan',
    sasaran: 'Sasaran Induk',
    indikator_sasaran: 'Indikator Sasaran',
    strategi: 'Strategi Induk',
    program: 'Program Induk',
    indikator_program: 'Indikator Program',
};

const form = useForm({
    type: 'visi' as NodeType,
    parent_id: '' as number | string,
    periode_tahun_id: '' as number | string,
    satuan_indikator_id: '' as number | string,
    opd_id: '' as number | string,
    urusan_pemerintahan_id: '' as number | string,
    uraian: '',
    indikator: '',
    tipe_indikator: 'positif',
    formula: '',
    sumber_data: '',
    target: '',
    target_text: '',
    pagu: '',
    pagu_indikatif: '',
    peran: 'penanggung_jawab',
    is_utama: true,
    urutan: 1,
    arah_kebijakan: '',
});

const emptyBulkRow = (index = 0): BulkRow => ({
    parent_id: '',
    periode_tahun_id: '',
    satuan_indikator_id: '',
    opd_id: '',
    urusan_pemerintahan_id: '',
    uraian: '',
    indikator: '',
    tipe_indikator: 'positif',
    formula: '',
    sumber_data: '',
    arah_kebijakan: '',
    target: '',
    target_text: '',
    pagu: '',
    pagu_indikatif: '',
    peran: 'penanggung_jawab',
    is_utama: true,
    urutan: index + 1,
});

const bulkForm = useForm({
    type: 'tujuan' as NodeType,
    parent_id: '' as number | string,
    periode_tahun_id: '' as number | string,
    satuan_indikator_id: '' as number | string,
    urusan_pemerintahan_id: '' as number | string,
    peran: 'penanggung_jawab',
    is_utama: true,
    rows: [emptyBulkRow()],
});

const targetTriwulanRows = [
    { triwulan: 'tw1', label: 'TW I' },
    { triwulan: 'tw2', label: 'TW II' },
    { triwulan: 'tw3', label: 'TW III' },
    { triwulan: 'tw4', label: 'TW IV' },
];

const emptyTargetTriwulanRows = () =>
    targetTriwulanRows.map((row) => ({ triwulan: row.triwulan, target_text: '', target_angka: '', target_anggaran: '' }));

const targetTriwulanForm = useForm({
    related_table: 'indikator_tujuan_daerah',
    related_id: '' as number | string,
    periode_tahun_id: '' as number | string,
    targets: emptyTargetTriwulanRows(),
});

const selectedTypeLabel = computed(() => typeOptions.find((type) => type.value === form.type)?.label ?? 'Data Cascading');
const selectedTypeMeta = computed(() => typeMeta[form.type]);
const parentKey = computed(() => parentKeyByType[form.type]);
const parentOptions = computed(() => (parentKey.value ? (props.nodeOptions[parentKey.value] ?? []) : []));
const parentLabel = computed(() => (parentKey.value ? (parentLabels[parentKey.value] ?? 'Induk Data') : 'Induk Data'));
const needsParent = computed(() => Boolean(parentKey.value));
const parentEmptyMessage = computed(() =>
    needsParent.value && parentOptions.value.length === 0
        ? `Tambahkan ${parentLabel.value.toLowerCase()} terlebih dahulu sebelum mengisi ${selectedTypeLabel.value}.`
        : '',
);
const canSubmitNode = computed(() => !form.processing && (!needsParent.value || Boolean(form.parent_id)));
const isIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program'].includes(form.type));
const isTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(form.type));
const isTextNodeType = computed(() => ['visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'program'].includes(form.type));
const isProgramType = computed(() => form.type === 'program');
const isProgramOpdType = computed(() => form.type === 'program_opd');
const isStrategiType = computed(() => form.type === 'strategi');
const bulkTypeMeta = computed(() => typeMeta[bulkForm.type]);
const bulkTypeLabel = computed(() => typeOptions.find((type) => type.value === bulkForm.type)?.label ?? 'Data Cascading');
const bulkParentKey = computed(() => parentKeyByType[bulkForm.type]);
const bulkParentOptions = computed(() => (bulkParentKey.value ? (props.nodeOptions[bulkParentKey.value] ?? []) : []));
const bulkParentLabel = computed(() => (bulkParentKey.value ? (parentLabels[bulkParentKey.value] ?? 'Induk Data') : 'Induk Data'));
const bulkNeedsParent = computed(() => Boolean(bulkParentKey.value));
const bulkIsIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program'].includes(bulkForm.type));
const bulkIsTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(bulkForm.type));
const bulkIsTextNodeType = computed(() => ['visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'program'].includes(bulkForm.type));
const bulkIsProgramType = computed(() => bulkForm.type === 'program');
const bulkIsProgramOpdType = computed(() => bulkForm.type === 'program_opd');
const bulkIsStrategiType = computed(() => bulkForm.type === 'strategi');
const bulkFilledRows = computed(
    () =>
        bulkForm.rows.filter((row) => {
            if (bulkIsIndicatorType.value) {
                return trimText(row.indikator).length > 0;
            }

            if (bulkIsTargetType.value) {
                return trimText(`${row.target}${row.target_text}${row.pagu}`).length > 0;
            }

            if (bulkIsProgramOpdType.value) {
                return Boolean(row.opd_id);
            }

            return trimText(row.uraian).length > 0;
        }).length,
);
const bulkCanSubmit = computed(() => !bulkForm.processing && (!bulkNeedsParent.value || Boolean(bulkForm.parent_id)) && bulkFilledRows.value > 0);
const bulkColumnCount = computed(() => {
    let count = 2; // No + aksi

    if (bulkIsTextNodeType.value) {
        count += 1;
    }

    if (bulkIsIndicatorType.value) {
        count += 5;
    }

    if (bulkIsStrategiType.value) {
        count += 1;
    }

    if (bulkIsProgramType.value) {
        count += 2;
    }

    if (bulkIsTargetType.value) {
        count += 2;
    }

    if (bulkForm.type === 'target_program') {
        count += 1;
    }

    if (bulkIsProgramOpdType.value) {
        count += 3;
    }

    if (!bulkIsTargetType.value && !bulkIsProgramOpdType.value) {
        count += 1;
    }

    return count;
});
const targetTriwulanTypeOptions = [
    { value: 'indikator_tujuan_daerah', label: 'Indikator Tujuan' },
    { value: 'indikator_sasaran_daerah', label: 'Indikator Sasaran' },
    { value: 'indikator_program_rpjmd', label: 'Indikator Program' },
];
const selectedTargetTriwulanOptions = computed(() => props.targetTriwulanOptions[targetTriwulanForm.related_table] ?? []);
const editingNode = ref<{ type: NodeType; id: number } | null>(null);
const activeInputMode = ref<'single' | 'bulk'>('single');
const viewMode = ref<'tree' | 'table'>('tree');
const showPreview = ref(!props.can.manage);

const nodeText = (_kode: string | null | undefined, text: string | null | undefined) => trimText(text ?? '') || '-';
const trimText = (value: string) => value.replace(/\s+/g, ' ').trim();
const joinItems = (items: string[]) => items.filter((item) => item && item !== '-').join('; ') || '-';
const toSelectedNumber = (value: number | string | null | undefined) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const selectedBulkParentId = computed(() => toSelectedNumber(bulkForm.parent_id));
const selectedBulkPeriodId = computed(() => toSelectedNumber(bulkForm.periode_tahun_id));
const bulkExistingRows = computed<BulkExistingRow[]>(() => {
    const rows: BulkExistingRow[] = [];

    const pushIndicator = (type: NodeType, indicator: Indikator, parentId: number, parentLabel: string) => {
        rows.push({
            id: indicator.id,
            type,
            parent_id: parentId,
            parent_label: parentLabel,
            kode: indicator.kode,
            indikator: indicator.indikator,
            satuan: indicator.satuan?.simbol || indicator.satuan?.nama || null,
            satuan_indikator_id: indicator.satuan_indikator_id ?? null,
            tipe_indikator: indicator.tipe_indikator || 'positif',
            formula: indicator.formula,
            sumber_data: indicator.sumber_data,
            urutan: indicator.urutan ?? null,
        });
    };

    const pushTarget = (type: NodeType, target: Target, parentId: number, parentLabel: string) => {
        rows.push({
            id: target.id,
            type,
            parent_id: parentId,
            parent_label: parentLabel,
            target: target.target,
            target_text: target.target_text,
            pagu: target.pagu,
            periode: target.periode_tahun?.tahun,
            periode_tahun_id: target.periode_tahun?.id ?? null,
        });
    };

    props.rpjmd.visi.forEach((visi) => {
        if (bulkForm.type === 'visi') {
            rows.push({
                id: visi.id,
                type: 'visi',
                uraian: visi.visi,
                urutan: visi.urutan ?? null,
            });
        }

        visi.misi.forEach((misi) => {
            if (bulkForm.type === 'misi') {
                rows.push({
                    id: misi.id,
                    type: 'misi',
                    parent_id: visi.id,
                    parent_label: nodeText(null, visi.visi),
                    kode: misi.kode,
                    uraian: misi.misi,
                    urutan: misi.urutan ?? null,
                });
            }
        });

        visi.tujuan.forEach((tujuan) => {
            if (bulkForm.type === 'tujuan') {
                rows.push({
                    id: tujuan.id,
                    type: 'tujuan',
                    parent_id: visi.id,
                    parent_label: nodeText(null, visi.visi),
                    kode: tujuan.kode,
                    uraian: tujuan.tujuan,
                    urutan: tujuan.urutan ?? null,
                });
            }

            if (bulkForm.type === 'indikator_tujuan') {
                tujuan.indikator.forEach((indikator) =>
                    pushIndicator('indikator_tujuan', indikator, tujuan.id, nodeText(tujuan.kode, tujuan.tujuan)),
                );
            }

            if (bulkForm.type === 'target_tujuan') {
                tujuan.indikator.forEach((indikator) =>
                    indikator.targets.forEach((target) =>
                        pushTarget('target_tujuan', target, indikator.id, nodeText(indikator.kode, indikator.indikator)),
                    ),
                );
            }

            tujuan.sasaran.forEach((sasaran) => {
                if (bulkForm.type === 'sasaran') {
                    rows.push({
                        id: sasaran.id,
                        type: 'sasaran',
                        parent_id: tujuan.id,
                        parent_label: nodeText(tujuan.kode, tujuan.tujuan),
                        kode: sasaran.kode,
                        uraian: sasaran.sasaran,
                        urutan: sasaran.urutan ?? null,
                    });
                }

                if (bulkForm.type === 'indikator_sasaran') {
                    sasaran.indikator.forEach((indikator) =>
                        pushIndicator('indikator_sasaran', indikator, sasaran.id, nodeText(sasaran.kode, sasaran.sasaran)),
                    );
                }

                if (bulkForm.type === 'target_sasaran') {
                    sasaran.indikator.forEach((indikator) =>
                        indikator.targets.forEach((target) =>
                            pushTarget('target_sasaran', target, indikator.id, nodeText(indikator.kode, indikator.indikator)),
                        ),
                    );
                }

                sasaran.strategi.forEach((strategi) => {
                    if (bulkForm.type === 'strategi') {
                        rows.push({
                            id: strategi.id,
                            type: 'strategi',
                            parent_id: sasaran.id,
                            parent_label: nodeText(sasaran.kode, sasaran.sasaran),
                            kode: strategi.kode,
                            uraian: strategi.strategi,
                            arah_kebijakan: strategi.arah_kebijakan,
                            urutan: strategi.urutan ?? null,
                        });
                    }

                    strategi.programs.forEach((program) => {
                        if (bulkForm.type === 'program') {
                            rows.push({
                                id: program.id,
                                type: 'program',
                                parent_id: strategi.id,
                                parent_label: nodeText(strategi.kode, strategi.strategi),
                                kode: program.kode,
                                uraian: program.nama,
                                urusan_pemerintahan_id: program.urusan_pemerintahan_id ?? null,
                                urusan: program.urusan_pemerintahan
                                    ? nodeText(program.urusan_pemerintahan.kode, program.urusan_pemerintahan.nama)
                                    : null,
                                pagu_indikatif: program.pagu_indikatif,
                                urutan: program.urutan ?? null,
                            });
                        }

                        if (bulkForm.type === 'indikator_program') {
                            program.indikator.forEach((indikator) =>
                                pushIndicator('indikator_program', indikator, program.id, nodeText(program.kode, program.nama)),
                            );
                        }

                        if (bulkForm.type === 'target_program') {
                            program.indikator.forEach((indikator) =>
                                indikator.targets.forEach((target) =>
                                    pushTarget('target_program', target, indikator.id, nodeText(indikator.kode, indikator.indikator)),
                                ),
                            );
                        }

                        if (bulkForm.type === 'program_opd') {
                            program.opd_penanggung_jawab.forEach((opd) => {
                                rows.push({
                                    id: opd.pivot_id,
                                    type: 'program_opd',
                                    parent_id: program.id,
                                    parent_label: nodeText(program.kode, program.nama),
                                    opd_id: opd.id,
                                    opd: opd.singkatan ? `${opd.singkatan} - ${opd.nama}` : opd.nama,
                                    peran: opd.peran,
                                    is_utama: opd.is_utama,
                                });
                            });
                        }
                    });
                });
            });
        });
    });

    return rows;
});
const bulkVisibleExistingRows = computed(() => {
    const selectedParentId = selectedBulkParentId.value;
    const selectedPeriodId = selectedBulkPeriodId.value;

    return bulkExistingRows.value.filter((row) => {
        if (selectedParentId && row.parent_id !== selectedParentId) {
            return false;
        }

        if (bulkIsTargetType.value && selectedPeriodId && row.periode_tahun_id !== selectedPeriodId) {
            return false;
        }

        return true;
    });
});
const indicatorSummary = (items: Indikator[]) => joinItems(items.map((item) => nodeText(item.kode, item.indikator)));
const targetSummary = (items: Indikator[]) =>
    joinItems(items.flatMap((item) => item.targets.map((target) => `${target.periode_tahun.tahun}: ${target.target_text || target.target || '-'}`)));
const targetTriwulanSummary = (items: Indikator[]) =>
    joinItems(
        items.flatMap((item) =>
            item.target_triwulan.map(
                (target) => `${target.periode_tahun.tahun} ${triwulanLabel(target.triwulan)}: ${target.target_text || target.target_angka || '-'}`,
            ),
        ),
    );

const rpjmdSummary = computed(() => {
    const summary = {
        visi: props.rpjmd.visi.length,
        misi: 0,
        tujuan: 0,
        sasaran: 0,
        program: 0,
        program_terhubung_opd: 0,
        indikator: 0,
        target_tahunan: 0,
        target_triwulan: 0,
        opd_penanggung_jawab: 0,
    };
    const opdIds = new Set<number>();

    props.rpjmd.visi.forEach((visi) => {
        summary.misi += visi.misi.length;
        summary.tujuan += visi.tujuan.length;
        visi.tujuan.forEach((tujuan) => {
            summary.indikator += tujuan.indikator.length;
            summary.target_tahunan += tujuan.indikator.reduce((total, indikator) => total + indikator.targets.length, 0);
            summary.target_triwulan += tujuan.indikator.reduce((total, indikator) => total + indikator.target_triwulan.length, 0);
            summary.sasaran += tujuan.sasaran.length;
            tujuan.sasaran.forEach((sasaran) => {
                summary.indikator += sasaran.indikator.length;
                summary.target_tahunan += sasaran.indikator.reduce((total, indikator) => total + indikator.targets.length, 0);
                summary.target_triwulan += sasaran.indikator.reduce((total, indikator) => total + indikator.target_triwulan.length, 0);
                sasaran.strategi.forEach((strategi) => {
                    summary.program += strategi.programs.length;
                    strategi.programs.forEach((program) => {
                        if (program.opd_penanggung_jawab.length > 0) {
                            summary.program_terhubung_opd += 1;
                        }
                        program.opd_penanggung_jawab.forEach((opd) => opdIds.add(opd.id));
                        summary.indikator += program.indikator.length;
                        summary.target_tahunan += program.indikator.reduce((total, indikator) => total + indikator.targets.length, 0);
                        summary.target_triwulan += program.indikator.reduce((total, indikator) => total + indikator.target_triwulan.length, 0);
                    });
                });
            });
        });
    });

    summary.opd_penanggung_jawab = opdIds.size;

    return summary;
});

const rpjmdCascadingRows = computed<RpjmdCascadingRow[]>(() => {
    const rows: RpjmdCascadingRow[] = [];

    props.rpjmd.visi.forEach((visi) => {
        if (visi.misi.length === 0 && visi.tujuan.length === 0) {
            rows.push(emptyRpjmdRow(`visi-${visi.id}`, { visi: visi.visi }));
        }

        visi.misi.forEach((misi) => {
            rows.push(emptyRpjmdRow(`misi-${misi.id}`, { visi: visi.visi, misi: nodeText(misi.kode, misi.misi) }));
        });

        visi.tujuan.forEach((tujuan) => {
            if (tujuan.sasaran.length === 0) {
                rows.push(
                    emptyRpjmdRow(`tujuan-${tujuan.id}`, {
                        visi: visi.visi,
                        tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                        indikator_tujuan: indicatorSummary(tujuan.indikator),
                        target_tahunan: targetSummary(tujuan.indikator),
                        target_triwulan: targetTriwulanSummary(tujuan.indikator),
                    }),
                );
            }

            tujuan.sasaran.forEach((sasaran) => {
                if (sasaran.strategi.length === 0) {
                    rows.push(
                        emptyRpjmdRow(`sasaran-${sasaran.id}`, {
                            visi: visi.visi,
                            tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                            indikator_tujuan: indicatorSummary(tujuan.indikator),
                            sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                            indikator_sasaran: indicatorSummary(sasaran.indikator),
                            target_tahunan: targetSummary(sasaran.indikator),
                            target_triwulan: targetTriwulanSummary(sasaran.indikator),
                        }),
                    );
                }

                sasaran.strategi.forEach((strategi) => {
                    if (strategi.programs.length === 0) {
                        rows.push(
                            emptyRpjmdRow(`strategi-${strategi.id}`, {
                                visi: visi.visi,
                                tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                indikator_tujuan: indicatorSummary(tujuan.indikator),
                                sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                indikator_sasaran: indicatorSummary(sasaran.indikator),
                                strategi: nodeText(strategi.kode, strategi.strategi),
                                target_tahunan: targetSummary(sasaran.indikator),
                                target_triwulan: targetTriwulanSummary(sasaran.indikator),
                            }),
                        );
                    }

                    strategi.programs.forEach((program) => {
                        rows.push(
                            emptyRpjmdRow(`program-${program.id}`, {
                                visi: visi.visi,
                                tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                indikator_tujuan: indicatorSummary(tujuan.indikator),
                                sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                indikator_sasaran: indicatorSummary(sasaran.indikator),
                                strategi: nodeText(strategi.kode, strategi.strategi),
                                program: nodeText(program.kode, program.nama),
                                indikator_program: indicatorSummary(program.indikator),
                                target_tahunan: targetSummary(program.indikator),
                                target_triwulan: targetTriwulanSummary(program.indikator),
                                opd_penanggung_jawab: joinItems(program.opd_penanggung_jawab.map((opd) => opd.singkatan || opd.nama)),
                                status_keterhubungan: program.opd_penanggung_jawab.length > 0 ? 'Terhubung OPD' : 'Belum ada OPD',
                            }),
                        );
                    });
                });
            });
        });
    });

    return rows;
});

const rpjmdCascadingTableRows = computed<RpjmdCascadingRow[]>(() => {
    const repeatedKeys: Array<keyof RpjmdCascadingRow> = [
        'visi',
        'tujuan',
        'indikator_tujuan',
        'sasaran',
        'indikator_sasaran',
        'strategi',
        'program',
        'indikator_program',
        'target_tahunan',
        'target_triwulan',
        'opd_penanggung_jawab',
    ];
    const previous = new Map<keyof RpjmdCascadingRow, string>();

    return rpjmdCascadingRows.value.map((row) => {
        const next = { ...row };

        repeatedKeys.forEach((key) => {
            const value = String(row[key] ?? '').trim();

            if (!value || value === '-') {
                next[key] = '';
                return;
            }

            if (previous.get(key) === value) {
                next[key] = '';
                return;
            }

            previous.set(key, value);
        });

        if (next.status_keterhubungan === '-') {
            next.status_keterhubungan = '';
        }

        return next;
    });
});

function emptyRpjmdRow(key: string, values: Partial<RpjmdCascadingRow>): RpjmdCascadingRow {
    return {
        key,
        visi: '-',
        misi: '-',
        tujuan: '-',
        indikator_tujuan: '-',
        sasaran: '-',
        indikator_sasaran: '-',
        strategi: '-',
        program: '-',
        indikator_program: '-',
        target_tahunan: '-',
        target_triwulan: '-',
        opd_penanggung_jawab: '-',
        status_keterhubungan: '-',
        ...values,
    };
}

const clearNodeForm = () => {
    form.parent_id = '';
    form.periode_tahun_id = '';
    form.satuan_indikator_id = '';
    form.opd_id = '';
    form.urusan_pemerintahan_id = '';
    form.uraian = '';
    form.indikator = '';
    form.tipe_indikator = 'positif';
    form.formula = '';
    form.sumber_data = '';
    form.target = '';
    form.target_text = '';
    form.pagu = '';
    form.pagu_indikatif = '';
    form.peran = 'penanggung_jawab';
    form.is_utama = true;
    form.urutan = 1;
    form.arah_kebijakan = '';
    form.clearErrors();
};

const resetNodeForm = () => {
    editingNode.value = null;
    clearNodeForm();
};

const resetBulkRows = (count = 1) => {
    bulkForm.rows = Array.from({ length: count }, (_, index) => emptyBulkRow(index));
    bulkForm.clearErrors();
};

const selectBulkNodeType = (type: NodeType) => {
    bulkForm.type = type;
    bulkForm.parent_id = '';
    bulkForm.periode_tahun_id = '';
    bulkForm.satuan_indikator_id = '';
    bulkForm.urusan_pemerintahan_id = '';
    bulkForm.peran = 'penanggung_jawab';
    bulkForm.is_utama = true;
    resetBulkRows();
};

const addBulkRow = () => {
    bulkForm.rows.push(emptyBulkRow(bulkForm.rows.length));
};

const removeBulkRow = (index: number) => {
    if (bulkForm.rows.length === 1) {
        bulkForm.rows = [emptyBulkRow()];
        return;
    }

    bulkForm.rows.splice(index, 1);
    bulkForm.rows.forEach((row, rowIndex) => {
        row.urutan = rowIndex + 1;
    });
};

const valueText = (value: unknown) => (value === null || value === undefined ? '' : String(value));
const savedBulkEdits = reactive<Record<string, BulkRow>>({});
const savedBulkSaving = ref<string | null>(null);
const savedBulkAutosaveEnabled = ref(false);
const savedBulkAutoSaveTimers = new Map<string, number>();
const savedBulkSaveState = reactive<Record<string, SavedBulkSaveState>>({});
const savedBulkSaveErrors = reactive<Record<string, string>>({});
const savedBulkBaselines = reactive<Record<string, string>>({});
const savedBulkSavedAt = reactive<Record<string, string>>({});
const savedBulkLastSavedAt = ref('');
const savedBulkKey = (row: BulkExistingRow) => `${row.type}-${row.id}`;

const bulkExistingToFormRow = (row: BulkExistingRow): BulkRow => ({
    parent_id: valueText(row.parent_id),
    periode_tahun_id: valueText(row.periode_tahun_id),
    satuan_indikator_id: valueText(row.satuan_indikator_id),
    opd_id: valueText(row.opd_id),
    urusan_pemerintahan_id: valueText(row.urusan_pemerintahan_id),
    uraian: valueText(row.uraian),
    indikator: valueText(row.indikator),
    tipe_indikator: row.tipe_indikator === 'negatif' ? 'negatif' : 'positif',
    formula: valueText(row.formula),
    sumber_data: valueText(row.sumber_data),
    arah_kebijakan: valueText(row.arah_kebijakan),
    target: valueText(row.target),
    target_text: valueText(row.target_text),
    pagu: valueText(row.pagu),
    pagu_indikatif: valueText(row.pagu_indikatif),
    peran: valueText(row.peran || 'penanggung_jawab'),
    is_utama: Boolean(row.is_utama ?? true),
    urutan: valueText(row.urutan),
});

const savedBulkSnapshot = (row: BulkRow) =>
    JSON.stringify({
        parent_id: valueText(row.parent_id),
        periode_tahun_id: valueText(row.periode_tahun_id),
        satuan_indikator_id: valueText(row.satuan_indikator_id),
        opd_id: valueText(row.opd_id),
        urusan_pemerintahan_id: valueText(row.urusan_pemerintahan_id),
        uraian: valueText(row.uraian),
        indikator: valueText(row.indikator),
        tipe_indikator: row.tipe_indikator,
        formula: valueText(row.formula),
        sumber_data: valueText(row.sumber_data),
        arah_kebijakan: valueText(row.arah_kebijakan),
        target: valueText(row.target),
        target_text: valueText(row.target_text),
        pagu: valueText(row.pagu),
        pagu_indikatif: valueText(row.pagu_indikatif),
        peran: valueText(row.peran),
        is_utama: Boolean(row.is_utama),
        urutan: valueText(row.urutan),
    });

const editableSavedBulkRow = (row: BulkExistingRow) => {
    const key = savedBulkKey(row);

    if (!savedBulkEdits[key]) {
        savedBulkEdits[key] = bulkExistingToFormRow(row);
    }

    return savedBulkEdits[key];
};

const resetSavedBulkBaseline = (row: BulkExistingRow) => {
    const key = savedBulkKey(row);
    savedBulkBaselines[key] = savedBulkSnapshot(editableSavedBulkRow(row));
    savedBulkSaveState[key] = 'saved';
    savedBulkSaveErrors[key] = '';
};

watch(
    bulkExistingRows,
    (rows) => {
        const keys = new Set(rows.map(savedBulkKey));

        Object.keys(savedBulkEdits).forEach((key) => {
            if (!keys.has(key)) {
                delete savedBulkEdits[key];
                delete savedBulkBaselines[key];
                delete savedBulkSaveState[key];
                delete savedBulkSaveErrors[key];
                delete savedBulkSavedAt[key];
                window.clearTimeout(savedBulkAutoSaveTimers.get(key));
                savedBulkAutoSaveTimers.delete(key);
            }
        });

        rows.forEach((row) => {
            const key = savedBulkKey(row);

            if (!savedBulkEdits[key]) {
                savedBulkEdits[key] = bulkExistingToFormRow(row);
            }

            if (!savedBulkBaselines[key]) {
                resetSavedBulkBaseline(row);
            }
        });
    },
    { immediate: true },
);

const editNode = (type: NodeType, id: number, parentId: number | null, node: any) => {
    editingNode.value = { type, id };
    form.type = type;
    clearNodeForm();
    form.parent_id = parentId ?? '';
    form.urutan = Number(node.urutan ?? 1);

    if (type === 'visi') {
        form.uraian = valueText(node.visi);
    } else if (type === 'misi') {
        form.uraian = valueText(node.misi);
    } else if (type === 'tujuan') {
        form.uraian = valueText(node.tujuan);
    } else if (type === 'sasaran') {
        form.uraian = valueText(node.sasaran);
    } else if (type === 'strategi') {
        form.uraian = valueText(node.strategi);
        form.arah_kebijakan = valueText(node.arah_kebijakan);
    } else if (type === 'program') {
        form.uraian = valueText(node.nama);
        form.urusan_pemerintahan_id = valueText(node.urusan_pemerintahan_id);
        form.pagu_indikatif = valueText(node.pagu_indikatif);
    } else if (isIndicatorType.value) {
        form.indikator = valueText(node.indikator);
        form.tipe_indikator = valueText(node.tipe_indikator || 'positif');
        form.satuan_indikator_id = valueText(node.satuan_indikator_id);
        form.formula = valueText(node.formula);
        form.sumber_data = valueText(node.sumber_data);
    } else if (isTargetType.value) {
        const target = node as unknown as Target;
        form.periode_tahun_id = target.periode_tahun?.id ?? '';
        form.target = valueText(target.target);
        form.target_text = valueText(target.target_text);
        form.pagu = valueText(target.pagu);
    } else if (type === 'program_opd') {
        form.opd_id = valueText(node.id);
        form.peran = valueText(node.peran || 'penanggung_jawab');
        form.is_utama = Boolean(node.is_utama ?? true);
    }
};

watch(
    () => form.type,
    () => {
        if (!editingNode.value) {
            clearNodeForm();
        }
    },
);

watch(
    () => bulkForm.type,
    () => {
        bulkForm.parent_id = '';
        bulkForm.periode_tahun_id = '';
        bulkForm.satuan_indikator_id = '';
        bulkForm.urusan_pemerintahan_id = '';
        bulkForm.peran = 'penanggung_jawab';
        bulkForm.is_utama = true;
        resetBulkRows();
    },
);

watch(
    () => targetTriwulanForm.related_table,
    () => {
        targetTriwulanForm.related_id = '';
        targetTriwulanForm.clearErrors();
    },
);

const submitNode = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetNodeForm();
        },
    };

    if (editingNode.value) {
        form.put(route('rpjmd.nodes.update', [props.rpjmd.id, editingNode.value.type, editingNode.value.id]), options);
        return;
    }

    form.post(route('rpjmd.nodes.store', props.rpjmd.id), options);
};

const submitBulkNodes = () => {
    bulkForm.post(route('rpjmd.nodes.bulk-store', props.rpjmd.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetBulkRows();
        },
    });
};

const savedBulkPayload = (row: BulkExistingRow) => {
    const editable = editableSavedBulkRow(row);

    return {
        type: row.type,
        parent_id: editable.parent_id,
        periode_tahun_id: editable.periode_tahun_id,
        satuan_indikator_id: editable.satuan_indikator_id,
        opd_id: editable.opd_id,
        urusan_pemerintahan_id: editable.urusan_pemerintahan_id,
        uraian: editable.uraian,
        indikator: editable.indikator,
        tipe_indikator: editable.tipe_indikator,
        formula: editable.formula,
        sumber_data: editable.sumber_data,
        arah_kebijakan: editable.arah_kebijakan,
        target: editable.target,
        target_text: editable.target_text,
        pagu: editable.pagu,
        pagu_indikatif: editable.pagu_indikatif,
        peran: editable.peran,
        is_utama: editable.is_utama,
        urutan: editable.urutan,
    };
};

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const firstErrorMessage = (errors: Record<string, string[] | string> | undefined, fallback: string): string => {
    if (!errors) {
        return fallback;
    }

    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : first;
};

const saveSavedBulkRow = async (row: BulkExistingRow, options: { silent?: boolean } = {}) => {
    const key = savedBulkKey(row);
    const currentSnapshot = savedBulkSnapshot(editableSavedBulkRow(row));

    if (savedBulkBaselines[key] === currentSnapshot) {
        savedBulkSaveState[key] = 'saved';
        savedBulkSaveErrors[key] = '';
        return;
    }

    window.clearTimeout(savedBulkAutoSaveTimers.get(key));
    savedBulkAutoSaveTimers.delete(key);
    savedBulkSaving.value = key;
    savedBulkSaveState[key] = 'saving';
    savedBulkSaveErrors[key] = '';

    try {
        const response = await fetch(route('rpjmd.nodes.update', [props.rpjmd.id, row.type, row.id]), {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(savedBulkPayload(row)),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(firstErrorMessage(data.errors, data.message || 'Gagal menyimpan perubahan.'));
        }

        savedBulkBaselines[key] = currentSnapshot;
        const latestSnapshot = savedBulkSnapshot(editableSavedBulkRow(row));
        savedBulkSaveState[key] = latestSnapshot === currentSnapshot ? 'saved' : 'dirty';
        savedBulkSaveErrors[key] = '';
        const savedAt = new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        savedBulkSavedAt[key] = savedAt;
        savedBulkLastSavedAt.value = savedAt;

        if (latestSnapshot !== currentSnapshot) {
            scheduleSavedBulkAutosave(row);
        }

        if (!options.silent) {
            void toast('Perubahan tersimpan.');
        }
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Gagal menyimpan perubahan.';
        savedBulkSaveState[key] = 'error';
        savedBulkSaveErrors[key] = message;

        if (!options.silent) {
            void toast(message, 'error');
        }
    } finally {
        if (savedBulkSaving.value === key) {
            savedBulkSaving.value = null;
        }
    }
};

const updateSavedBulkRow = (row: BulkExistingRow) => {
    void saveSavedBulkRow(row);
};

const scheduleSavedBulkAutosave = (row: BulkExistingRow) => {
    if (!props.can.manage || !savedBulkAutosaveEnabled.value) {
        return;
    }

    const key = savedBulkKey(row);

    window.clearTimeout(savedBulkAutoSaveTimers.get(key));
    savedBulkSaveState[key] = 'dirty';
    savedBulkSaveErrors[key] = '';
    savedBulkAutoSaveTimers.set(
        key,
        window.setTimeout(() => {
            void saveSavedBulkRow(row, { silent: true });
        }, 900),
    );
};

const markSavedBulkChanged = (row: BulkExistingRow) => {
    const key = savedBulkKey(row);

    if (!savedBulkEdits[key]) {
        savedBulkEdits[key] = bulkExistingToFormRow(row);
    }

    if (!savedBulkBaselines[key]) {
        savedBulkBaselines[key] = savedBulkSnapshot(bulkExistingToFormRow(row));
    }

    const currentSnapshot = savedBulkSnapshot(editableSavedBulkRow(row));

    if (currentSnapshot === savedBulkBaselines[key]) {
        if (savedBulkSaveState[key] !== 'saving') {
            savedBulkSaveState[key] = 'saved';
            savedBulkSaveErrors[key] = '';
        }

        window.clearTimeout(savedBulkAutoSaveTimers.get(key));
        savedBulkAutoSaveTimers.delete(key);
        return;
    }

    if (savedBulkSaveState[key] === 'saving') {
        return;
    }

    savedBulkSaveState[key] = 'dirty';
    savedBulkSaveErrors[key] = '';

    scheduleSavedBulkAutosave(row);
};

const refreshSavedBulkDirtyStates = () => {
    bulkExistingRows.value.forEach((row) => {
        const key = savedBulkKey(row);

        if (!savedBulkEdits[key] || !savedBulkBaselines[key]) {
            return;
        }

        const currentSnapshot = savedBulkSnapshot(savedBulkEdits[key]);

        if (currentSnapshot === savedBulkBaselines[key]) {
            if (savedBulkSaveState[key] !== 'saving') {
                savedBulkSaveState[key] = 'saved';
                savedBulkSaveErrors[key] = '';
            }
            window.clearTimeout(savedBulkAutoSaveTimers.get(key));
            savedBulkAutoSaveTimers.delete(key);
            return;
        }

        if (savedBulkSaveState[key] !== 'saving') {
            savedBulkSaveState[key] = 'dirty';
            savedBulkSaveErrors[key] = '';
        } else {
            return;
        }

        scheduleSavedBulkAutosave(row);
    });
};

watch(savedBulkEdits, refreshSavedBulkDirtyStates, { deep: true });

watch(savedBulkAutosaveEnabled, (enabled) => {
    if (enabled) {
        refreshSavedBulkDirtyStates();
        return;
    }

    savedBulkAutoSaveTimers.forEach((timer) => window.clearTimeout(timer));
    savedBulkAutoSaveTimers.clear();
});

onBeforeUnmount(() => {
    savedBulkAutoSaveTimers.forEach((timer) => window.clearTimeout(timer));
    savedBulkAutoSaveTimers.clear();
});

const savedBulkStatusDotClass = (row: BulkExistingRow) =>
    (
        ({
            dirty: 'bg-amber-500',
            saving: 'bg-blue-500',
            saved: 'bg-emerald-500',
            error: 'bg-red-500',
            idle: 'bg-emerald-500',
        }) as Record<SavedBulkSaveState, string>
    )[savedBulkSaveState[savedBulkKey(row)] ?? 'saved'];

const savedBulkStatusHint = (row: BulkExistingRow) => {
    const key = savedBulkKey(row);
    const state = savedBulkSaveState[key] ?? 'saved';

    if (state === 'saving') {
        return 'Autosave berjalan';
    }

    if (state === 'dirty') {
        return savedBulkAutosaveEnabled.value ? 'Menunggu autosave' : 'Belum disimpan';
    }

    if (state === 'error') {
        return 'Periksa input';
    }

    return savedBulkSavedAt[key] ? `Tersimpan ${savedBulkSavedAt[key]}` : 'Tersimpan';
};

const destroySavedBulkRow = async (row: BulkExistingRow) => {
    await destroyNode(row.type, row.id, bulkTypeLabel.value);
};

const destroyNode = async (type: NodeType, id: number, label: string) => {
    if (await confirmDelete(`Hapus ${label}? Data turunan juga dapat terpengaruh.`)) {
        router.delete(route('rpjmd.nodes.destroy', [props.rpjmd.id, type, id]), {
            preserveScroll: true,
        });
    }
};

const submitTargetTriwulan = () => {
    targetTriwulanForm.post(route('target-triwulan-indikator.bulk-store'), {
        preserveScroll: true,
        onSuccess: () => {
            targetTriwulanForm.related_id = '';
            targetTriwulanForm.targets = emptyTargetTriwulanRows();
        },
    });
};

const destroyTargetTriwulan = async (target: TargetTriwulan) => {
    if (await confirmDelete('Hapus target triwulan indikator ini?')) {
        router.delete(route('target-triwulan-indikator.destroy', target.id), { preserveScroll: true });
    }
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

const formatCurrency = (value?: string | number | null) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
};

const targetDisplay = (target: Target) => target.target_text || target.target || '-';
const targetTriwulanDisplay = (target: TargetTriwulan) => target.target_text || target.target_angka || '-';
const targetTriwulanError = (index: number, field: 'triwulan' | 'target_text' | 'target_angka' | 'target_anggaran') =>
    targetTriwulanForm.errors[`targets.${index}.${field}`];
const triwulanLabel = (triwulan: string) =>
    ({
        tw1: 'TW I',
        tw2: 'TW II',
        tw3: 'TW III',
        tw4: 'TW IV',
    })[triwulan] ?? triwulan;
</script>

<template>
    <Head title="Cascading RPJMD" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-normal">{{ rpjmd.judul }}</h1>
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(rpjmd.status)">
                        {{ statusLabel(rpjmd.status) }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ rpjmd.tahun_awal }}-{{ rpjmd.tahun_akhir }} - {{ rpjmd.nomor_perda || 'Nomor perda belum diisi' }}
                </p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('rpjmd.index')" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Kembali</Link>
                <Link
                    v-if="can.manage"
                    :href="route('rpjmd.edit', rpjmd.id)"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                >
                    <Pencil class="size-4" />
                    Edit
                </Link>
                <WorkflowActionButtons
                    module="rpjmd"
                    :model-id="rpjmd.id"
                    :status="rpjmd.status"
                    :can-manage="can.manage"
                    :can-review="can.review"
                    :can-lock="can.lock"
                    :show-verify="false"
                />
            </div>
        </div>

        <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-4">
            <div>
                <div class="text-xs uppercase text-muted-foreground">Periode Referensi</div>
                <div class="mt-1 text-sm font-medium">{{ rpjmd.periode_tahun?.nama || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Jumlah Visi</div>
                <div class="mt-1 text-sm font-medium">{{ rpjmd.visi.length }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Status Workflow</div>
                <div class="mt-1 text-sm font-medium">{{ statusLabel(rpjmd.status) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Keterangan</div>
                <div class="mt-1 text-sm font-medium">{{ rpjmd.keterangan || '-' }}</div>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border bg-card p-4">
                <div class="text-xs uppercase text-muted-foreground">Node Perencanaan</div>
                <div class="mt-2 text-2xl font-semibold">{{ rpjmdSummary.misi + rpjmdSummary.tujuan + rpjmdSummary.sasaran }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                    {{ rpjmdSummary.misi }} misi, {{ rpjmdSummary.tujuan }} tujuan, {{ rpjmdSummary.sasaran }} sasaran
                </div>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <div class="text-xs uppercase text-muted-foreground">Indikator dan Target</div>
                <div class="mt-2 text-2xl font-semibold">{{ rpjmdSummary.indikator }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                    {{ rpjmdSummary.target_tahunan }} target tahunan, {{ rpjmdSummary.target_triwulan }} target triwulan
                </div>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <div class="text-xs uppercase text-muted-foreground">Program RPJMD</div>
                <div class="mt-2 text-2xl font-semibold">{{ rpjmdSummary.program }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                    {{ rpjmdSummary.program_terhubung_opd }} sudah punya OPD, {{ rpjmdSummary.program - rpjmdSummary.program_terhubung_opd }} belum
                </div>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <div class="text-xs uppercase text-muted-foreground">OPD Penanggung Jawab</div>
                <div class="mt-2 text-2xl font-semibold">{{ rpjmdSummary.opd_penanggung_jawab }}</div>
                <div class="mt-1 text-xs text-muted-foreground">OPD unik yang terhubung ke program RPJMD</div>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />

        <section class="rounded-xl border bg-card p-4 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-blue-50 p-2 text-blue-700">
                        <Rows3 class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold">Input RPJMD</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ activeInputMode === 'single' ? 'Input satu data.' : 'Input banyak data.' }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="inline-flex rounded-md border bg-background p-1">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded px-3 text-sm font-medium"
                            :class="activeInputMode === 'single' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-muted'"
                            @click="activeInputMode = 'single'"
                        >
                            <Plus class="size-4" />
                            Satuan
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded px-3 text-sm font-medium"
                            :class="activeInputMode === 'bulk' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-muted'"
                            @click="activeInputMode = 'bulk'"
                        >
                            <CopyPlus class="size-4" />
                            Bulk
                        </button>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                        @click="showPreview = !showPreview"
                    >
                        <EyeOff v-if="showPreview" class="size-4" />
                        <Eye v-else class="size-4" />
                        {{ showPreview ? 'Sembunyikan Preview' : 'Lihat Preview' }}
                    </button>
                </div>
            </div>

            <div v-if="showPreview" class="mt-4 flex flex-col gap-3 border-t pt-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <Network class="size-5 text-emerald-700" />
                    <div>
                        <h3 class="text-sm font-semibold">Preview</h3>
                    </div>
                </div>
                <div class="inline-flex rounded-md border bg-background p-1">
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-2 rounded px-3 text-sm"
                        :class="viewMode === 'tree' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-muted'"
                        @click="viewMode = 'tree'"
                    >
                        <GitBranch class="size-4" />
                        Tree
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-2 rounded px-3 text-sm"
                        :class="viewMode === 'table' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-muted'"
                        @click="viewMode = 'table'"
                    >
                        <Table2 class="size-4" />
                        Tabel
                    </button>
                </div>
            </div>
        </section>

        <div class="grid gap-4">
            <section v-if="showPreview && viewMode === 'table'" class="rounded-lg border bg-card">
                <div class="flex items-center gap-2 border-b p-4">
                    <Table2 class="size-5 text-emerald-700" />
                    <div>
                        <h2 class="text-base font-semibold">Tabel Cascading Melebar</h2>
                        <p class="text-sm text-muted-foreground">Setiap baris membawa konteks dari visi sampai OPD penanggung jawab.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1500px] text-left text-sm">
                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Visi</th>
                                <th class="px-4 py-3">Misi</th>
                                <th class="px-4 py-3">Tujuan</th>
                                <th class="px-4 py-3">Indikator Tujuan</th>
                                <th class="px-4 py-3">Sasaran</th>
                                <th class="px-4 py-3">Indikator Sasaran</th>
                                <th class="px-4 py-3">Strategi</th>
                                <th class="px-4 py-3">Program</th>
                                <th class="px-4 py-3">Indikator Program</th>
                                <th class="px-4 py-3">Target Tahunan</th>
                                <th class="px-4 py-3">Target Triwulan</th>
                                <th class="px-4 py-3">OPD</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rpjmdCascadingTableRows" :key="row.key" class="border-b align-top last:border-0 hover:bg-muted/20">
                                <td class="max-w-[240px] px-4 py-3 leading-6">{{ row.visi }}</td>
                                <td class="max-w-[240px] px-4 py-3 leading-6">{{ row.misi }}</td>
                                <td class="max-w-[240px] px-4 py-3 leading-6">{{ row.tujuan }}</td>
                                <td class="max-w-[260px] px-4 py-3 leading-6">{{ row.indikator_tujuan }}</td>
                                <td class="max-w-[240px] px-4 py-3 leading-6">{{ row.sasaran }}</td>
                                <td class="max-w-[260px] px-4 py-3 leading-6">{{ row.indikator_sasaran }}</td>
                                <td class="max-w-[240px] px-4 py-3 leading-6">{{ row.strategi }}</td>
                                <td class="max-w-[260px] px-4 py-3 font-medium leading-6">{{ row.program }}</td>
                                <td class="max-w-[260px] px-4 py-3 leading-6">{{ row.indikator_program }}</td>
                                <td class="max-w-[220px] px-4 py-3 leading-6">{{ row.target_tahunan }}</td>
                                <td class="max-w-[220px] px-4 py-3 leading-6">{{ row.target_triwulan }}</td>
                                <td class="max-w-[200px] px-4 py-3 leading-6">{{ row.opd_penanggung_jawab }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="row.status_keterhubungan"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        :class="
                                            row.status_keterhubungan === 'Terhubung OPD'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-amber-100 text-amber-800'
                                        "
                                    >
                                        {{ row.status_keterhubungan }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="rpjmdCascadingTableRows.length === 0">
                                <td colspan="13" class="px-4 py-10 text-center text-muted-foreground">Belum ada data cascading RPJMD.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-else-if="showPreview" class="rounded-lg border bg-card">
                <div class="flex items-center gap-2 border-b p-4">
                    <GitBranch class="size-5 text-emerald-700" />
                    <div>
                        <h2 class="text-base font-semibold">Tree Cascading RPJMD</h2>
                        <p class="text-sm text-muted-foreground">
                            Visi, misi, tujuan, sasaran, strategi, program, indikator, target, dan OPD penanggung jawab.
                        </p>
                    </div>
                </div>

                <div class="space-y-4 p-4">
                    <div v-if="rpjmd.visi.length === 0" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Belum ada cascading. Mulai dari menambahkan Visi.
                    </div>

                    <article v-for="visi in rpjmd.visi" :key="visi.id" class="rounded-md border bg-background">
                        <div class="flex items-start justify-between gap-3 border-b p-3">
                            <div>
                                <div class="text-xs font-semibold uppercase text-emerald-700">Visi</div>
                                <div class="mt-1 text-sm font-medium">{{ visi.visi }}</div>
                            </div>
                            <div v-if="can.manage" class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="rounded-md p-1 hover:bg-muted"
                                    title="Edit visi"
                                    @click="editNode('visi', visi.id, null, visi)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                    title="Hapus visi"
                                    @click="destroyNode('visi', visi.id, 'visi')"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3 p-3">
                            <div v-if="visi.misi.length" class="rounded-md border border-emerald-100 bg-emerald-50/40 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase text-emerald-700">Misi RPJMD</div>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Misi dicatat sebagai arah pembangunan dan tidak menjadi parent langsung tujuan.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-2">
                                    <div v-for="misi in visi.misi" :key="misi.id" class="rounded-md border bg-white p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Misi</div>
                                                <div class="mt-1 text-sm font-medium">{{ misi.misi }}</div>
                                            </div>
                                            <div v-if="can.manage" class="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    class="rounded-md p-1 hover:bg-muted"
                                                    title="Edit misi"
                                                    @click="editNode('misi', misi.id, visi.id, misi)"
                                                >
                                                    <Pencil class="size-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                    title="Hapus misi"
                                                    @click="destroyNode('misi', misi.id, 'misi')"
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-md border border-blue-100 bg-blue-50/40 p-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase text-blue-700">Tujuan Daerah</div>
                                    <p class="mt-1 text-xs text-muted-foreground">Tujuan daerah diturunkan langsung dari Visi.</p>
                                </div>

                                <div v-if="visi.tujuan.length" class="mt-3 space-y-3">
                                    <div v-for="tujuan in visi.tujuan" :key="tujuan.id" class="rounded-md border bg-slate-50 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Tujuan Daerah</div>
                                                <div class="mt-1 text-sm font-medium">
                                                    {{ tujuan.tujuan }}
                                                </div>
                                            </div>
                                            <div v-if="can.manage" class="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    class="rounded-md p-1 hover:bg-muted"
                                                    title="Edit tujuan"
                                                    @click="editNode('tujuan', tujuan.id, visi.id, tujuan)"
                                                >
                                                    <Pencil class="size-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                    title="Hapus tujuan"
                                                    @click="destroyNode('tujuan', tujuan.id, 'tujuan')"
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <div v-if="tujuan.indikator.length" class="mt-3 grid gap-2">
                                            <div v-for="indikator in tujuan.indikator" :key="indikator.id" class="rounded-md border bg-white p-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">Indikator Tujuan</div>
                                                        <div class="mt-1 text-sm">
                                                            {{ indikator.indikator }}
                                                        </div>
                                                        <div class="mt-1 text-xs text-muted-foreground">
                                                            {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }} -
                                                            {{ indikator.sumber_data || 'Sumber data belum diisi' }}
                                                        </div>
                                                    </div>
                                                    <div v-if="can.manage" class="flex items-center gap-1">
                                                        <button
                                                            type="button"
                                                            class="rounded-md p-1 hover:bg-muted"
                                                            title="Edit indikator"
                                                            @click="editNode('indikator_tujuan', indikator.id, tujuan.id, indikator)"
                                                        >
                                                            <Pencil class="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                            title="Hapus indikator"
                                                            @click="destroyNode('indikator_tujuan', indikator.id, 'indikator tujuan')"
                                                        >
                                                            <Trash2 class="size-4" />
                                                        </button>
                                                    </div>
                                                </div>
                                                <div v-if="indikator.targets.length" class="mt-2 flex flex-wrap gap-2">
                                                    <span
                                                        v-for="target in indikator.targets"
                                                        :key="target.id"
                                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800"
                                                    >
                                                        {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
                                                        <button
                                                            v-if="can.manage"
                                                            type="button"
                                                            class="font-semibold text-emerald-900 hover:text-slate-900"
                                                            @click="editNode('target_tujuan', target.id, indikator.id, target)"
                                                        >
                                                            Edit
                                                        </button>
                                                    </span>
                                                </div>
                                                <div v-if="indikator.target_triwulan.length" class="mt-2 flex flex-wrap gap-2">
                                                    <span
                                                        v-for="target in indikator.target_triwulan"
                                                        :key="target.id"
                                                        class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800"
                                                    >
                                                        {{ target.periode_tahun.tahun }} {{ triwulanLabel(target.triwulan) }}:
                                                        {{ targetTriwulanDisplay(target) }} - {{ formatCurrency(target.target_anggaran) }}
                                                        <button
                                                            v-if="can.manage"
                                                            type="button"
                                                            class="font-semibold text-blue-900 hover:text-red-700"
                                                            @click="destroyTargetTriwulan(target)"
                                                        >
                                                            x
                                                        </button>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="tujuan.sasaran.length" class="mt-3 space-y-3">
                                            <div v-for="sasaran in tujuan.sasaran" :key="sasaran.id" class="rounded-md border bg-white p-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">Sasaran Daerah</div>
                                                        <div class="mt-1 text-sm font-medium">
                                                            {{ sasaran.sasaran }}
                                                        </div>
                                                    </div>
                                                    <div v-if="can.manage" class="flex items-center gap-1">
                                                        <button
                                                            type="button"
                                                            class="rounded-md p-1 hover:bg-muted"
                                                            title="Edit sasaran"
                                                            @click="editNode('sasaran', sasaran.id, tujuan.id, sasaran)"
                                                        >
                                                            <Pencil class="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                            title="Hapus sasaran"
                                                            @click="destroyNode('sasaran', sasaran.id, 'sasaran')"
                                                        >
                                                            <Trash2 class="size-4" />
                                                        </button>
                                                    </div>
                                                </div>

                                                <div v-if="sasaran.indikator.length" class="mt-3 grid gap-2">
                                                    <div
                                                        v-for="indikator in sasaran.indikator"
                                                        :key="indikator.id"
                                                        class="rounded-md border bg-slate-50 p-3"
                                                    >
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">
                                                                    Indikator Sasaran
                                                                </div>
                                                                <div class="mt-1 text-sm">
                                                                    {{ indikator.indikator }}
                                                                </div>
                                                                <div class="mt-1 text-xs text-muted-foreground">
                                                                    {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }} -
                                                                    {{ indikator.sumber_data || 'Sumber data belum diisi' }}
                                                                </div>
                                                            </div>
                                                            <div v-if="can.manage" class="flex items-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 hover:bg-muted"
                                                                    title="Edit indikator"
                                                                    @click="editNode('indikator_sasaran', indikator.id, sasaran.id, indikator)"
                                                                >
                                                                    <Pencil class="size-4" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                    title="Hapus indikator"
                                                                    @click="destroyNode('indikator_sasaran', indikator.id, 'indikator sasaran')"
                                                                >
                                                                    <Trash2 class="size-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div v-if="indikator.targets.length" class="mt-2 flex flex-wrap gap-2">
                                                            <span
                                                                v-for="target in indikator.targets"
                                                                :key="target.id"
                                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800"
                                                            >
                                                                {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
                                                                <button
                                                                    v-if="can.manage"
                                                                    type="button"
                                                                    class="font-semibold text-emerald-900 hover:text-slate-900"
                                                                    @click="editNode('target_sasaran', target.id, indikator.id, target)"
                                                                >
                                                                    Edit
                                                                </button>
                                                            </span>
                                                        </div>
                                                        <div v-if="indikator.target_triwulan.length" class="mt-2 flex flex-wrap gap-2">
                                                            <span
                                                                v-for="target in indikator.target_triwulan"
                                                                :key="target.id"
                                                                class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800"
                                                            >
                                                                {{ target.periode_tahun.tahun }} {{ triwulanLabel(target.triwulan) }}:
                                                                {{ targetTriwulanDisplay(target) }} -
                                                                {{ formatCurrency(target.target_anggaran) }}
                                                                <button
                                                                    v-if="can.manage"
                                                                    type="button"
                                                                    class="font-semibold text-blue-900 hover:text-red-700"
                                                                    @click="destroyTargetTriwulan(target)"
                                                                >
                                                                    x
                                                                </button>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-if="sasaran.strategi.length" class="mt-3 space-y-3">
                                                    <div
                                                        v-for="strategi in sasaran.strategi"
                                                        :key="strategi.id"
                                                        class="rounded-md border bg-slate-50 p-3"
                                                    >
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">
                                                                    Strategi Daerah
                                                                </div>
                                                                <div class="mt-1 text-sm font-medium">
                                                                    {{ strategi.strategi }}
                                                                </div>
                                                                <div v-if="strategi.arah_kebijakan" class="mt-1 text-xs text-muted-foreground">
                                                                    Arah kebijakan: {{ strategi.arah_kebijakan }}
                                                                </div>
                                                            </div>
                                                            <div v-if="can.manage" class="flex items-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 hover:bg-muted"
                                                                    title="Edit strategi"
                                                                    @click="editNode('strategi', strategi.id, sasaran.id, strategi)"
                                                                >
                                                                    <Pencil class="size-4" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                    title="Hapus strategi"
                                                                    @click="destroyNode('strategi', strategi.id, 'strategi')"
                                                                >
                                                                    <Trash2 class="size-4" />
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div v-if="strategi.programs.length" class="mt-3 space-y-3">
                                                            <div
                                                                v-for="program in strategi.programs"
                                                                :key="program.id"
                                                                class="rounded-md border bg-white p-3"
                                                            >
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">
                                                                            Program RPJMD
                                                                        </div>
                                                                        <div class="mt-1 text-sm font-medium">
                                                                            {{ program.nama }}
                                                                        </div>
                                                                        <div class="mt-1 text-xs text-muted-foreground">
                                                                            {{
                                                                                program.urusan_pemerintahan
                                                                                    ? program.urusan_pemerintahan.nama
                                                                                    : 'Urusan belum diisi'
                                                                            }}
                                                                            - Pagu {{ formatCurrency(program.pagu_indikatif) }}
                                                                        </div>
                                                                    </div>
                                                                    <div v-if="can.manage" class="flex items-center gap-1">
                                                                        <button
                                                                            type="button"
                                                                            class="rounded-md p-1 hover:bg-muted"
                                                                            title="Edit program"
                                                                            @click="editNode('program', program.id, strategi.id, program)"
                                                                        >
                                                                            <Pencil class="size-4" />
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                            title="Hapus program"
                                                                            @click="destroyNode('program', program.id, 'program')"
                                                                        >
                                                                            <Trash2 class="size-4" />
                                                                        </button>
                                                                    </div>
                                                                </div>

                                                                <div v-if="program.opd_penanggung_jawab.length" class="mt-3 flex flex-wrap gap-2">
                                                                    <span
                                                                        v-for="opd in program.opd_penanggung_jawab"
                                                                        :key="`${program.id}-${opd.id}-${opd.peran}`"
                                                                        class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800"
                                                                    >
                                                                        {{ opd.singkatan || opd.nama }} -
                                                                        {{ opd.is_utama ? 'Utama' : opd.peran }}
                                                                        <button
                                                                            v-if="can.manage"
                                                                            type="button"
                                                                            class="font-semibold text-blue-900 hover:text-slate-900"
                                                                            @click="editNode('program_opd', opd.pivot_id, program.id, opd)"
                                                                        >
                                                                            Edit
                                                                        </button>
                                                                    </span>
                                                                </div>

                                                                <div v-if="program.indikator.length" class="mt-3 grid gap-2">
                                                                    <div
                                                                        v-for="indikator in program.indikator"
                                                                        :key="indikator.id"
                                                                        class="rounded-md border bg-slate-50 p-3"
                                                                    >
                                                                        <div class="flex items-start justify-between gap-3">
                                                                            <div>
                                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">
                                                                                    Indikator Program
                                                                                </div>
                                                                                <div class="mt-1 text-sm">
                                                                                    {{ indikator.indikator }}
                                                                                </div>
                                                                                <div class="mt-1 text-xs text-muted-foreground">
                                                                                    {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}
                                                                                    - {{ indikator.sumber_data || 'Sumber data belum diisi' }}
                                                                                </div>
                                                                            </div>
                                                                            <div v-if="can.manage" class="flex items-center gap-1">
                                                                                <button
                                                                                    type="button"
                                                                                    class="rounded-md p-1 hover:bg-muted"
                                                                                    title="Edit indikator"
                                                                                    @click="
                                                                                        editNode(
                                                                                            'indikator_program',
                                                                                            indikator.id,
                                                                                            program.id,
                                                                                            indikator,
                                                                                        )
                                                                                    "
                                                                                >
                                                                                    <Pencil class="size-4" />
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                                    title="Hapus indikator"
                                                                                    @click="
                                                                                        destroyNode(
                                                                                            'indikator_program',
                                                                                            indikator.id,
                                                                                            'indikator program',
                                                                                        )
                                                                                    "
                                                                                >
                                                                                    <Trash2 class="size-4" />
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                        <div v-if="indikator.targets.length" class="mt-2 flex flex-wrap gap-2">
                                                                            <span
                                                                                v-for="target in indikator.targets"
                                                                                :key="target.id"
                                                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800"
                                                                            >
                                                                                {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }} -
                                                                                {{ formatCurrency(target.pagu) }}
                                                                                <button
                                                                                    v-if="can.manage"
                                                                                    type="button"
                                                                                    class="font-semibold text-emerald-900 hover:text-slate-900"
                                                                                    @click="
                                                                                        editNode('target_program', target.id, indikator.id, target)
                                                                                    "
                                                                                >
                                                                                    Edit
                                                                                </button>
                                                                            </span>
                                                                        </div>
                                                                        <div
                                                                            v-if="indikator.target_triwulan.length"
                                                                            class="mt-2 flex flex-wrap gap-2"
                                                                        >
                                                                            <span
                                                                                v-for="target in indikator.target_triwulan"
                                                                                :key="target.id"
                                                                                class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800"
                                                                            >
                                                                                {{ target.periode_tahun.tahun }}
                                                                                {{ triwulanLabel(target.triwulan) }}:
                                                                                {{ targetTriwulanDisplay(target) }} -
                                                                                {{ formatCurrency(target.target_anggaran) }}
                                                                                <button
                                                                                    v-if="can.manage"
                                                                                    type="button"
                                                                                    class="font-semibold text-blue-900 hover:text-red-700"
                                                                                    @click="destroyTargetTriwulan(target)"
                                                                                >
                                                                                    x
                                                                                </button>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <aside v-if="can.manage && !showPreview" class="overflow-hidden rounded-xl border bg-card shadow-sm xl:sticky xl:top-4 xl:self-start">
                <div class="border-b bg-slate-50/70 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-emerald-50 p-2 text-emerald-700">
                                <Plus v-if="activeInputMode === 'single'" class="size-5" />
                                <CopyPlus v-else class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">
                                    {{ activeInputMode === 'single' ? (editingNode ? 'Edit Data' : 'Tambah Data') : 'Bulk Input' }}
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ activeInputMode === 'single' ? selectedTypeLabel : bulkTypeLabel }}
                                </p>
                            </div>
                        </div>
                        <button
                            v-if="editingNode"
                            type="button"
                            class="h-9 rounded-md border px-3 text-xs font-medium hover:bg-muted"
                            @click="resetNodeForm"
                        >
                            Batal
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 p-4">
                    <template v-if="activeInputMode === 'single'">
                        <form class="grid gap-3" @submit.prevent="submitNode">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="type">Jenis Data</label>
                                <select id="type" v-model="form.type" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.type" />
                            </div>

                            <div v-if="needsParent" class="grid gap-2">
                                <label class="text-sm font-medium" for="parent_id">{{ parentLabel }}</label>
                                <select
                                    id="parent_id"
                                    v-model="form.parent_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    :class="parentEmptyMessage ? 'border-amber-300 bg-amber-50/50' : ''"
                                >
                                    <option value="">Pilih {{ parentLabel.toLowerCase() }}</option>
                                    <option v-for="option in parentOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <p v-if="parentEmptyMessage" class="text-xs font-medium text-amber-700">{{ parentEmptyMessage }}</p>
                                <InputError :message="form.errors.parent_id" />
                            </div>

                            <div v-if="isTextNodeType" class="grid gap-2">
                                <label class="text-sm font-medium" for="uraian">{{ isProgramType ? 'Nama Program' : selectedTypeLabel }}</label>
                                <textarea
                                    id="uraian"
                                    v-model="form.uraian"
                                    rows="4"
                                    class="min-h-28 rounded-md border bg-background px-3 py-2 text-sm leading-6"
                                    :placeholder="selectedTypeMeta.placeholder"
                                />
                                <InputError :message="form.errors.uraian" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="indikator">Indikator</label>
                                <textarea
                                    id="indikator"
                                    v-model="form.indikator"
                                    rows="4"
                                    class="min-h-28 rounded-md border bg-background px-3 py-2 text-sm leading-6"
                                    :placeholder="selectedTypeMeta.placeholder"
                                />
                                <InputError :message="form.errors.indikator" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="satuan_indikator_id">Satuan Indikator</label>
                                <select
                                    id="satuan_indikator_id"
                                    v-model="form.satuan_indikator_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Pilih satuan</option>
                                    <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.satuan_indikator_id" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="tipe_indikator">Tipe Indikator</label>
                                <select id="tipe_indikator" v-model="form.tipe_indikator" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option value="positif">Positif</option>
                                    <option value="negatif">Negatif</option>
                                </select>
                                <InputError :message="form.errors.tipe_indikator" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="formula">Formula</label>
                                <textarea
                                    id="formula"
                                    v-model="form.formula"
                                    rows="2"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Contoh: (Realisasi / Target) x 100"
                                />
                                <InputError :message="form.errors.formula" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="sumber_data">Sumber Data</label>
                                <input
                                    id="sumber_data"
                                    v-model="form.sumber_data"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    placeholder="Contoh: LKJIP, BPS, SIPD, hasil evaluasi"
                                />
                                <InputError :message="form.errors.sumber_data" />
                            </div>

                            <div v-if="isStrategiType" class="grid gap-2">
                                <label class="text-sm font-medium" for="arah_kebijakan">Arah Kebijakan</label>
                                <textarea
                                    id="arah_kebijakan"
                                    v-model="form.arah_kebijakan"
                                    rows="2"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Contoh: Penguatan evaluasi kinerja triwulanan dan tindak lanjut rekomendasi"
                                />
                                <InputError :message="form.errors.arah_kebijakan" />
                            </div>

                            <div v-if="isProgramType" class="grid gap-2">
                                <label class="text-sm font-medium" for="urusan_pemerintahan_id">Urusan Pemerintahan</label>
                                <select
                                    id="urusan_pemerintahan_id"
                                    v-model="form.urusan_pemerintahan_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Pilih urusan</option>
                                    <option v-for="option in urusanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.urusan_pemerintahan_id" />
                            </div>

                            <div v-if="isProgramType" class="grid gap-2">
                                <label class="text-sm font-medium" for="pagu_indikatif">Pagu Indikatif</label>
                                <input
                                    id="pagu_indikatif"
                                    v-model="form.pagu_indikatif"
                                    type="number"
                                    step="0.01"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                />
                                <InputError :message="form.errors.pagu_indikatif" />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="periode_tahun_id">Periode Target</label>
                                <select
                                    id="periode_tahun_id"
                                    v-model="form.periode_tahun_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Pilih periode</option>
                                    <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.periode_tahun_id" />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="target">Target Angka</label>
                                <input
                                    id="target"
                                    v-model="form.target"
                                    type="number"
                                    step="0.0001"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                />
                                <InputError :message="form.errors.target" />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="target_text">Target Teks</label>
                                <input
                                    id="target_text"
                                    v-model="form.target_text"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    :placeholder="selectedTypeMeta.placeholder"
                                />
                                <InputError :message="form.errors.target_text" />
                            </div>

                            <div v-if="form.type === 'target_program'" class="grid gap-2">
                                <label class="text-sm font-medium" for="pagu">Pagu Tahunan</label>
                                <input
                                    id="pagu"
                                    v-model="form.pagu"
                                    type="number"
                                    step="0.01"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                />
                                <InputError :message="form.errors.pagu" />
                            </div>

                            <div v-if="isProgramOpdType" class="grid gap-2">
                                <label class="text-sm font-medium" for="opd_id">OPD Penanggung Jawab</label>
                                <select id="opd_id" v-model="form.opd_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option value="">Pilih OPD</option>
                                    <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.opd_id" />
                            </div>

                            <div v-if="isProgramOpdType" class="grid gap-2">
                                <label class="text-sm font-medium" for="peran">Peran</label>
                                <input id="peran" v-model="form.peran" class="h-10 rounded-md border bg-background px-3 text-sm" />
                                <InputError :message="form.errors.peran" />
                            </div>

                            <label v-if="isProgramOpdType" class="flex items-center gap-2 text-sm">
                                <input v-model="form.is_utama" type="checkbox" class="rounded border" />
                                Penanggung jawab utama
                            </label>

                            <div v-if="!isTargetType && !isProgramOpdType" class="grid gap-2">
                                <label class="text-sm font-medium" for="urutan">Urutan</label>
                                <input
                                    id="urutan"
                                    v-model="form.urutan"
                                    type="number"
                                    min="1"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                />
                                <InputError :message="form.errors.urutan" />
                            </div>

                            <div
                                class="sticky bottom-0 z-10 -mx-4 mt-2 flex items-center justify-between gap-3 border-t bg-card/95 px-4 py-3 backdrop-blur"
                            >
                                <button type="button" class="h-10 rounded-md border px-3 text-sm font-medium hover:bg-muted" @click="resetNodeForm">
                                    Bersihkan
                                </button>
                                <button
                                    type="submit"
                                    :disabled="!canSubmitNode"
                                    class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-medium text-white shadow-sm hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <CheckCircle2 class="size-4" />
                                    {{ editingNode ? 'Perbarui' : 'Simpan' }}
                                </button>
                            </div>
                        </form>

                        <form class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50/60 p-3" @submit.prevent="submitTargetTriwulan">
                            <div>
                                <h3 class="text-sm font-semibold">Target Triwulan Indikator</h3>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="target_triwulan_table">Jenis Indikator</label>
                                <select
                                    id="target_triwulan_table"
                                    v-model="targetTriwulanForm.related_table"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option v-for="option in targetTriwulanTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="targetTriwulanForm.errors.related_table" />
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="target_triwulan_related_id">Indikator</label>
                                <select
                                    id="target_triwulan_related_id"
                                    v-model="targetTriwulanForm.related_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Pilih indikator</option>
                                    <option v-for="option in selectedTargetTriwulanOptions" :key="option.id" :value="option.id">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <p v-if="selectedTargetTriwulanOptions.length === 0" class="text-xs font-medium text-amber-700">
                                    Belum ada indikator pada jenis ini. Tambahkan indikator terlebih dahulu.
                                </p>
                                <InputError :message="targetTriwulanForm.errors.related_id" />
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="target_triwulan_periode">Periode Tahun</label>
                                <select
                                    id="target_triwulan_periode"
                                    v-model="targetTriwulanForm.periode_tahun_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Pilih periode</option>
                                    <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="targetTriwulanForm.errors.periode_tahun_id" />
                            </div>

                            <div class="overflow-x-auto rounded-md border">
                                <table class="min-w-[680px] text-sm">
                                    <thead class="bg-muted/60 text-left text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="px-3 py-2">Triwulan</th>
                                            <th class="px-3 py-2">Target Angka</th>
                                            <th class="px-3 py-2">Target Teks</th>
                                            <th class="px-3 py-2">Target Anggaran</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, index) in targetTriwulanForm.targets" :key="row.triwulan" class="border-t">
                                            <td class="px-3 py-2 font-medium">{{ targetTriwulanRows[index].label }}</td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="row.target_angka"
                                                    type="number"
                                                    step="0.0001"
                                                    class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                />
                                                <InputError :message="targetTriwulanError(index, 'target_angka')" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="row.target_text"
                                                    class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    placeholder="Opsional"
                                                />
                                                <InputError :message="targetTriwulanError(index, 'target_text')" />
                                            </td>
                                            <td class="px-3 py-2">
                                                <input
                                                    v-model="row.target_anggaran"
                                                    type="number"
                                                    step="0.01"
                                                    class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                />
                                                <InputError :message="targetTriwulanError(index, 'target_anggaran')" />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <button
                                type="submit"
                                :disabled="targetTriwulanForm.processing || selectedTargetTriwulanOptions.length === 0"
                                class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Simpan Target TW I-IV
                            </button>
                        </form>
                    </template>

                    <template v-else>
                        <form class="grid gap-4" @submit.prevent="submitBulkNodes">
                            <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2 xl:grid-cols-4">
                                <div class="grid gap-2 xl:col-span-2">
                                    <label class="text-sm font-medium" for="bulk_type">Jenis Data Bulk</label>
                                    <select id="bulk_type" v-model="bulkForm.type" class="h-10 rounded-md border bg-background px-3 text-sm">
                                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="bulkForm.errors.type" />
                                </div>

                                <div v-if="bulkNeedsParent" class="grid gap-2 xl:col-span-2">
                                    <label class="text-sm font-medium" for="bulk_parent_id">{{ bulkParentLabel }}</label>
                                    <select
                                        id="bulk_parent_id"
                                        v-model="bulkForm.parent_id"
                                        class="h-10 rounded-md border bg-background px-3 text-sm"
                                        :class="bulkParentOptions.length === 0 ? 'border-amber-300 bg-amber-50/50' : ''"
                                    >
                                        <option value="">Pilih {{ bulkParentLabel.toLowerCase() }}</option>
                                        <option v-for="option in bulkParentOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                    </select>
                                    <p v-if="bulkParentOptions.length === 0" class="text-xs font-medium text-amber-700">
                                        {{ bulkParentLabel }} belum tersedia.
                                    </p>
                                    <InputError :message="bulkForm.errors.parent_id" />
                                </div>

                                <div v-if="bulkIsTargetType" class="grid gap-2">
                                    <label class="text-sm font-medium" for="bulk_periode_tahun_id">Periode Target</label>
                                    <select
                                        id="bulk_periode_tahun_id"
                                        v-model="bulkForm.periode_tahun_id"
                                        class="h-10 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="">Pilih periode</option>
                                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="bulkForm.errors.periode_tahun_id" />
                                </div>

                                <div v-if="bulkIsIndicatorType" class="grid gap-2">
                                    <label class="text-sm font-medium" for="bulk_satuan_indikator_id">Satuan Default</label>
                                    <select
                                        id="bulk_satuan_indikator_id"
                                        v-model="bulkForm.satuan_indikator_id"
                                        class="h-10 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="">Tidak diset default</option>
                                        <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="bulkForm.errors.satuan_indikator_id" />
                                </div>

                                <div v-if="bulkIsProgramType" class="grid gap-2">
                                    <label class="text-sm font-medium" for="bulk_urusan_pemerintahan_id">Urusan Default</label>
                                    <select
                                        id="bulk_urusan_pemerintahan_id"
                                        v-model="bulkForm.urusan_pemerintahan_id"
                                        class="h-10 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="">Tidak diset default</option>
                                        <option v-for="option in urusanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="bulkForm.errors.urusan_pemerintahan_id" />
                                </div>
                            </section>

                            <section class="overflow-hidden rounded-lg border bg-card">
                                <div class="flex flex-col gap-3 border-b bg-slate-50/70 p-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold">Tabel Bulk {{ bulkTypeLabel }}</h3>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-xs font-medium text-muted-foreground">
                                            {{ bulkVisibleExistingRows.length }} tersimpan - {{ bulkFilledRows }} baru
                                        </span>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 items-center gap-2 rounded-full border px-2.5 text-xs font-semibold transition"
                                            :class="
                                                savedBulkAutosaveEnabled
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                    : 'border-slate-200 bg-white text-slate-600'
                                            "
                                            :aria-pressed="savedBulkAutosaveEnabled"
                                            @click="savedBulkAutosaveEnabled = !savedBulkAutosaveEnabled"
                                        >
                                            <span
                                                class="relative inline-flex h-4 w-7 rounded-full transition"
                                                :class="savedBulkAutosaveEnabled ? 'bg-emerald-600' : 'bg-slate-300'"
                                            >
                                                <span
                                                    class="absolute top-0.5 size-3 rounded-full bg-white shadow-sm transition"
                                                    :class="savedBulkAutosaveEnabled ? 'left-3.5' : 'left-0.5'"
                                                />
                                            </span>
                                            Autosave {{ savedBulkAutosaveEnabled ? 'On' : 'Off' }}
                                        </button>
                                        <span v-if="savedBulkLastSavedAt" class="text-xs font-medium text-muted-foreground">
                                            {{ savedBulkLastSavedAt }}
                                        </span>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-[1100px] text-left text-sm">
                                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                                            <tr>
                                                <th class="w-14 px-3 py-2">No</th>
                                                <th v-if="bulkIsTextNodeType" class="min-w-[360px] px-3 py-2">
                                                    {{ bulkIsProgramType ? 'Nama Program' : bulkTypeLabel }}
                                                </th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-[360px] px-3 py-2">Indikator</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-44 px-3 py-2">Satuan</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-36 px-3 py-2">Tipe</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-64 px-3 py-2">Formula</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-56 px-3 py-2">Sumber Data</th>
                                                <th v-if="bulkIsStrategiType" class="min-w-72 px-3 py-2">Arah Kebijakan</th>
                                                <th v-if="bulkIsProgramType" class="min-w-48 px-3 py-2">Urusan</th>
                                                <th v-if="bulkIsProgramType" class="min-w-40 px-3 py-2">Pagu Indikatif</th>
                                                <th v-if="bulkIsTargetType" class="min-w-40 px-3 py-2">Target Angka</th>
                                                <th v-if="bulkIsTargetType" class="min-w-72 px-3 py-2">Target Teks</th>
                                                <th v-if="bulkForm.type === 'target_program'" class="min-w-40 px-3 py-2">Pagu Tahunan</th>
                                                <th v-if="bulkIsProgramOpdType" class="min-w-80 px-3 py-2">OPD</th>
                                                <th v-if="bulkIsProgramOpdType" class="min-w-44 px-3 py-2">Peran</th>
                                                <th v-if="bulkIsProgramOpdType" class="min-w-32 px-3 py-2">Utama</th>
                                                <th v-if="!bulkIsTargetType && !bulkIsProgramOpdType" class="min-w-28 px-3 py-2">Urutan</th>
                                                <th class="w-16 px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(saved, savedIndex) in bulkVisibleExistingRows"
                                                :key="`saved-${saved.type}-${saved.id}`"
                                                class="border-b bg-white text-slate-700"
                                            >
                                                <td class="px-3 py-2 align-top">
                                                    <div class="font-medium">{{ savedIndex + 1 }}</div>
                                                    <span
                                                        class="mt-2 block size-2 rounded-full"
                                                        :class="savedBulkStatusDotClass(saved)"
                                                        :title="savedBulkSaveErrors[savedBulkKey(saved)] || savedBulkStatusHint(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsTextNodeType" class="px-3 py-2 align-top">
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).uraian"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                    <div v-if="saved.parent_label" class="mt-1 text-xs text-muted-foreground">
                                                        {{ saved.parent_label }}
                                                    </div>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).indikator"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                    <div v-if="saved.parent_label" class="mt-1 text-xs text-muted-foreground">
                                                        {{ saved.parent_label }}
                                                    </div>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <select
                                                        v-model="editableSavedBulkRow(saved).satuan_indikator_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markSavedBulkChanged(saved)"
                                                    >
                                                        <option value="">Tidak diset</option>
                                                        <option v-for="option in satuanOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <select
                                                        v-model="editableSavedBulkRow(saved).tipe_indikator"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markSavedBulkChanged(saved)"
                                                    >
                                                        <option value="positif">Positif</option>
                                                        <option value="negatif">Negatif</option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).formula"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).sumber_data"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsStrategiType" class="px-3 py-2 align-top">
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).arah_kebijakan"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramType" class="px-3 py-2 align-top">
                                                    <select
                                                        v-model="editableSavedBulkRow(saved).urusan_pemerintahan_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markSavedBulkChanged(saved)"
                                                    >
                                                        <option value="">Tidak diset</option>
                                                        <option v-for="option in urusanOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsProgramType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).pagu_indikatif"
                                                        type="number"
                                                        step="0.01"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).target"
                                                        type="number"
                                                        step="0.0001"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).target_text"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                    <div v-if="saved.parent_label" class="mt-1 text-xs text-muted-foreground">
                                                        {{ saved.parent_label }}
                                                    </div>
                                                    <div v-if="saved.periode" class="mt-1 text-xs text-muted-foreground">
                                                        Tahun {{ saved.periode }}
                                                    </div>
                                                </td>
                                                <td v-if="bulkForm.type === 'target_program'" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).pagu"
                                                        type="number"
                                                        step="0.01"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2 align-top">
                                                    <select
                                                        v-model="editableSavedBulkRow(saved).opd_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markSavedBulkChanged(saved)"
                                                    >
                                                        <option value="">Pilih OPD</option>
                                                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                    <div v-if="saved.parent_label" class="mt-1 text-xs text-muted-foreground">
                                                        {{ saved.parent_label }}
                                                    </div>
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).peran"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2 align-top">
                                                    <label class="inline-flex h-10 items-center gap-2 text-sm">
                                                        <input
                                                            v-model="editableSavedBulkRow(saved).is_utama"
                                                            type="checkbox"
                                                            class="rounded border"
                                                            @change="markSavedBulkChanged(saved)"
                                                        />
                                                        Utama
                                                    </label>
                                                </td>
                                                <td v-if="!bulkIsTargetType && !bulkIsProgramOpdType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).urutan"
                                                        type="number"
                                                        min="1"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td class="px-3 py-2 align-top">
                                                    <div class="flex items-center justify-end gap-1">
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-9 items-center justify-center rounded-md text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                            title="Simpan perubahan"
                                                            :disabled="savedBulkSaving === savedBulkKey(saved)"
                                                            @click="updateSavedBulkRow(saved)"
                                                        >
                                                            <CheckCircle2 class="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-9 items-center justify-center rounded-md text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                            title="Hapus data"
                                                            :disabled="savedBulkSaving === savedBulkKey(saved)"
                                                            @click="destroySavedBulkRow(saved)"
                                                        >
                                                            <Trash2 class="size-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="bulkVisibleExistingRows.length === 0" class="border-b bg-slate-50/60">
                                                <td :colspan="bulkColumnCount" class="px-3 py-4 text-sm text-muted-foreground">
                                                    Belum ada data tersimpan.
                                                </td>
                                            </tr>
                                            <tr v-for="(row, index) in bulkForm.rows" :key="index" class="border-b last:border-0">
                                                <td class="px-3 py-2 font-medium text-muted-foreground">
                                                    {{ bulkVisibleExistingRows.length + index + 1 }}
                                                </td>
                                                <td v-if="bulkIsTextNodeType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.uraian"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.indikator"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.satuan_indikator_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    >
                                                        <option value="">Default</option>
                                                        <option v-for="option in satuanOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.tipe_indikator"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    >
                                                        <option value="positif">Positif</option>
                                                        <option value="negatif">Negatif</option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <input v-model="row.formula" class="h-10 w-full rounded-md border bg-background px-3 text-sm" />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.sumber_data"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    />
                                                </td>
                                                <td v-if="bulkIsStrategiType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.arah_kebijakan"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.urusan_pemerintahan_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    >
                                                        <option value="">Default</option>
                                                        <option v-for="option in urusanOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsProgramType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.pagu_indikatif"
                                                        type="number"
                                                        step="0.01"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    />
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.target"
                                                        type="number"
                                                        step="0.0001"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    />
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.target_text"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    />
                                                </td>
                                                <td v-if="bulkForm.type === 'target_program'" class="px-3 py-2">
                                                    <input
                                                        v-model="row.pagu"
                                                        type="number"
                                                        step="0.01"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2">
                                                    <select v-model="row.opd_id" class="h-10 w-full rounded-md border bg-background px-3 text-sm">
                                                        <option value="">Pilih OPD</option>
                                                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2">
                                                    <input v-model="row.peran" class="h-10 w-full rounded-md border bg-background px-3 text-sm" />
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2">
                                                    <label class="inline-flex h-10 items-center gap-2 text-sm">
                                                        <input v-model="row.is_utama" type="checkbox" class="rounded border" />
                                                        Utama
                                                    </label>
                                                </td>
                                                <td v-if="!bulkIsTargetType && !bulkIsProgramOpdType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.urutan"
                                                        type="number"
                                                        min="1"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                    />
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <button
                                                        type="button"
                                                        class="inline-flex size-9 items-center justify-center rounded-md text-red-700 hover:bg-red-50"
                                                        title="Hapus baris"
                                                        @click="removeBulkRow(index)"
                                                    >
                                                        <Trash2 class="size-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex items-center gap-3 border-t bg-slate-50/60 p-3">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center gap-2 rounded-md border bg-white px-3 text-sm font-medium hover:bg-muted"
                                        @click="addBulkRow"
                                    >
                                        <Plus class="size-4" />
                                        Tambah Baris
                                    </button>
                                </div>

                                <InputError class="px-3 pb-3" :message="bulkForm.errors.rows" />
                            </section>

                            <div class="sticky bottom-0 z-10 flex items-center justify-between gap-3 rounded-lg border bg-card/95 p-3 backdrop-blur">
                                <div class="text-xs text-muted-foreground">{{ bulkFilledRows }} baris akan disimpan sebagai {{ bulkTypeLabel }}.</div>
                                <button
                                    type="submit"
                                    :disabled="!bulkCanSubmit"
                                    class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-medium text-white shadow-sm hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <CheckCircle2 class="size-4" />
                                    Simpan Bulk
                                </button>
                            </div>
                        </form>
                    </template>
                </div>
            </aside>
        </div>
    </div>
</template>
