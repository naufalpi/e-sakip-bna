<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CircleDot,
    ClipboardList,
    FileText,
    GitBranch,
    Layers3,
    Link2,
    Network,
    Pencil,
    Plus,
    Save,
    Table2,
    Target,
    Trash2,
    WalletCards,
} from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

type Option = { id: number; label: string };
type NodeType =
    | 'tujuan'
    | 'indikator_tujuan'
    | 'target_tujuan'
    | 'sasaran'
    | 'indikator_sasaran'
    | 'target_sasaran'
    | 'program'
    | 'indikator_program'
    | 'target_program'
    | 'kegiatan'
    | 'sub_kegiatan'
    | 'indikator_sub_kegiatan';

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
    indikator_tujuan_daerah_id?: number | null;
    indikator_sasaran_daerah_id?: number | null;
    indikator_program_rpjmd_id?: number | null;
    satuan_indikator_id?: number | null;
    tipe_indikator?: string | null;
    formula?: string | null;
    sumber_data?: string | null;
    urutan?: number | null;
    linked: boolean;
    satuan?: { nama: string; simbol?: string | null } | null;
    targets?: Target[];
    target_triwulan?: TargetTriwulan[];
};

type SubKegiatan = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    urutan?: number | null;
    indikator: Indikator[];
};

type Kegiatan = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    urutan?: number | null;
    sub_kegiatan: SubKegiatan[];
};

type Program = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    program_rpjmd_id?: number | null;
    urutan?: number | null;
    linked: boolean;
    indikator: Indikator[];
    kegiatan: Kegiatan[];
};

type Sasaran = {
    id: number;
    kode?: string | null;
    sasaran: string;
    sasaran_daerah_id?: number | null;
    urutan?: number | null;
    linked: boolean;
    indikator: Indikator[];
    programs: Program[];
};

type Tujuan = {
    id: number;
    kode?: string | null;
    tujuan: string;
    tujuan_daerah_id?: number | null;
    urutan?: number | null;
    linked: boolean;
    indikator: Indikator[];
    sasaran: Sasaran[];
};

type Renstra = {
    id: number;
    judul: string;
    nomor_dokumen?: string | null;
    tahun_awal: number;
    tahun_akhir: number;
    status: string;
    keterangan?: string | null;
    opd?: { id: number; kode: string; nama: string; singkatan?: string | null } | null;
    rpjmd?: { id: number; judul: string; tahun_awal: number; tahun_akhir: number } | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
    tujuan: Tujuan[];
};
type RenstraCascadingRow = {
    key: string;
    tujuan: string;
    tujuan_rpjmd: string;
    indikator_tujuan: string;
    sasaran: string;
    sasaran_rpjmd: string;
    indikator_sasaran: string;
    program: string;
    program_rpjmd: string;
    indikator_program: string;
    kegiatan: string;
    sub_kegiatan: string;
    indikator_sub_kegiatan: string;
    target_tahunan: string;
    target_triwulan: string;
    pagu: string;
    status_keterhubungan: string;
};
type BulkSaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';
type BulkRow = {
    key: string;
    id: number;
    type: NodeType;
    level: string;
    parent_label: string;
    parent_id: number | string;
    kode: string;
    uraian: string;
    indikator: string;
    satuan_indikator_id: number | string;
    tipe_indikator: string;
    formula: string;
    sumber_data: string;
    pagu_indikatif: number | string;
    periode_tahun_id: number | string;
    target: number | string;
    target_text: string;
    pagu: number | string;
    urutan: number | string;
    reference_field: string;
    reference_value: number | string;
    saveState: BulkSaveState;
    savedAt: string;
    error: string;
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
    renstra: Renstra;
    nodeOptions: Record<string, Option[]>;
    rpjmdReferenceOptions: Record<string, Option[]>;
    targetTriwulanOptions: Record<string, Option[]>;
    periodeOptions: Option[];
    satuanOptions: Option[];
    can: {
        manage: boolean;
        review: boolean;
        lock: boolean;
    };
    workflow: Workflow;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Renstra OPD', href: '/renstra-opd' },
    { title: props.renstra.opd?.singkatan || props.renstra.opd?.nama || 'Detail', href: '#' },
];

const typeOptions: Array<{ value: NodeType; label: string }> = [
    { value: 'tujuan', label: 'Tujuan OPD' },
    { value: 'indikator_tujuan', label: 'Indikator Tujuan OPD' },
    { value: 'target_tujuan', label: 'Target Indikator Tujuan' },
    { value: 'sasaran', label: 'Sasaran OPD' },
    { value: 'indikator_sasaran', label: 'Indikator Sasaran OPD' },
    { value: 'target_sasaran', label: 'Target Indikator Sasaran' },
    { value: 'program', label: 'Program OPD' },
    { value: 'indikator_program', label: 'Indikator Program OPD' },
    { value: 'target_program', label: 'Target Indikator Program' },
    { value: 'kegiatan', label: 'Kegiatan OPD' },
    { value: 'sub_kegiatan', label: 'Sub Kegiatan OPD' },
    { value: 'indikator_sub_kegiatan', label: 'Indikator Sub Kegiatan' },
];

const parentKeyByType: Partial<Record<NodeType, string>> = {
    indikator_tujuan: 'tujuan',
    target_tujuan: 'indikator_tujuan',
    sasaran: 'tujuan',
    indikator_sasaran: 'sasaran',
    target_sasaran: 'indikator_sasaran',
    program: 'sasaran',
    indikator_program: 'program',
    target_program: 'indikator_program',
    kegiatan: 'program',
    sub_kegiatan: 'kegiatan',
    indikator_sub_kegiatan: 'sub_kegiatan',
};

const parentLabels: Record<string, string> = {
    tujuan: 'Tujuan Induk',
    indikator_tujuan: 'Indikator Tujuan',
    sasaran: 'Sasaran Induk',
    indikator_sasaran: 'Indikator Sasaran',
    program: 'Program Induk',
    indikator_program: 'Indikator Program',
    kegiatan: 'Kegiatan Induk',
    sub_kegiatan: 'Sub Kegiatan Induk',
};

const form = useForm({
    type: 'tujuan' as NodeType,
    parent_id: '' as number | string,
    periode_tahun_id: '' as number | string,
    satuan_indikator_id: '' as number | string,
    tujuan_daerah_id: '' as number | string,
    indikator_tujuan_daerah_id: '' as number | string,
    sasaran_daerah_id: '' as number | string,
    indikator_sasaran_daerah_id: '' as number | string,
    program_rpjmd_id: '' as number | string,
    indikator_program_rpjmd_id: '' as number | string,
    kode: '',
    uraian: '',
    indikator: '',
    tipe_indikator: 'positif',
    formula: '',
    sumber_data: '',
    target: '',
    target_text: '',
    pagu: '',
    pagu_indikatif: '',
    urutan: 1,
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
    related_table: 'indikator_tujuan_opd',
    related_id: '' as number | string,
    periode_tahun_id: '' as number | string,
    targets: emptyTargetTriwulanRows(),
});

const selectedTypeLabel = computed(() => typeOptions.find((type) => type.value === form.type)?.label ?? 'Data Cascading');
const parentKey = computed(() => parentKeyByType[form.type]);
const parentOptions = computed(() => (parentKey.value ? (props.nodeOptions[parentKey.value] ?? []) : []));
const parentLabel = computed(() => (parentKey.value ? (parentLabels[parentKey.value] ?? 'Induk Data') : 'Induk Data'));
const needsParent = computed(() => Boolean(parentKey.value));
const isIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program', 'indikator_sub_kegiatan'].includes(form.type));
const isTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(form.type));
const isTextNodeType = computed(() => ['tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan'].includes(form.type));
const hasPaguIndikatif = computed(() => ['program', 'kegiatan', 'sub_kegiatan'].includes(form.type));
const targetTriwulanTypeOptions = [
    { value: 'indikator_tujuan_opd', label: 'Indikator Tujuan OPD' },
    { value: 'indikator_sasaran_opd', label: 'Indikator Sasaran OPD' },
    { value: 'indikator_opd_program', label: 'Indikator Program OPD' },
    { value: 'indikator_sub_kegiatan', label: 'Indikator Sub Kegiatan' },
];
const selectedTargetTriwulanOptions = computed(() => props.targetTriwulanOptions[targetTriwulanForm.related_table] ?? []);
const editingNode = ref<{ type: NodeType; id: number } | null>(null);
const viewMode = ref<'tree' | 'table' | 'bulk'>('tree');
const formPanel = ref<HTMLElement | null>(null);
const bulkRows = ref<BulkRow[]>([]);
const bulkSaveTimers = new Map<string, number>();
const bulkLastSavedAt = ref('');

const typeOptionMap = computed(() => new Map(typeOptions.map((option) => [option.value, option])));
const typeGroups: Array<{ label: string; helper: string; icon: unknown; items: NodeType[] }> = [
    {
        label: 'Arah Kinerja',
        helper: 'Mulai dari tujuan, sasaran, indikator, dan target tahunan.',
        icon: GitBranch,
        items: ['tujuan', 'indikator_tujuan', 'target_tujuan', 'sasaran', 'indikator_sasaran', 'target_sasaran'],
    },
    {
        label: 'Program dan Anggaran',
        helper: 'Turunkan sasaran menjadi program, kegiatan, sub kegiatan, dan pagu.',
        icon: WalletCards,
        items: ['program', 'indikator_program', 'target_program', 'kegiatan', 'sub_kegiatan', 'indikator_sub_kegiatan'],
    },
];
const typeMeta: Record<NodeType, { stage: string; helper: string; primaryField: string }> = {
    tujuan: {
        stage: 'Level 1',
        helper: 'Rumusan tujuan OPD yang dapat dihubungkan ke tujuan daerah RPJMD.',
        primaryField: 'Uraian tujuan',
    },
    indikator_tujuan: {
        stage: 'Level 1A',
        helper: 'Indikator untuk mengukur pencapaian tujuan OPD.',
        primaryField: 'Nama indikator',
    },
    target_tujuan: {
        stage: 'Target',
        helper: 'Target tahunan untuk indikator tujuan OPD.',
        primaryField: 'Nilai target',
    },
    sasaran: {
        stage: 'Level 2',
        helper: 'Sasaran strategis yang menjadi turunan dari tujuan OPD.',
        primaryField: 'Uraian sasaran',
    },
    indikator_sasaran: {
        stage: 'Level 2A',
        helper: 'Indikator untuk mengukur sasaran strategis OPD.',
        primaryField: 'Nama indikator',
    },
    target_sasaran: {
        stage: 'Target',
        helper: 'Target tahunan untuk indikator sasaran OPD.',
        primaryField: 'Nilai target',
    },
    program: {
        stage: 'Level 3',
        helper: 'Program OPD sebagai turunan sasaran dan bisa dihubungkan ke program RPJMD.',
        primaryField: 'Nama program',
    },
    indikator_program: {
        stage: 'Level 3A',
        helper: 'Indikator untuk mengukur keberhasilan program OPD.',
        primaryField: 'Nama indikator',
    },
    target_program: {
        stage: 'Target',
        helper: 'Target tahunan dan pagu untuk indikator program OPD.',
        primaryField: 'Nilai target',
    },
    kegiatan: {
        stage: 'Level 4',
        helper: 'Kegiatan OPD sebagai turunan program.',
        primaryField: 'Nama kegiatan',
    },
    sub_kegiatan: {
        stage: 'Level 5',
        helper: 'Sub kegiatan dengan pagu indikatif sebagai dasar pengukuran anggaran.',
        primaryField: 'Nama sub kegiatan',
    },
    indikator_sub_kegiatan: {
        stage: 'Level 5A',
        helper: 'Indikator teknis untuk sub kegiatan dan target triwulan.',
        primaryField: 'Nama indikator',
    },
};
const selectedTypeMeta = computed(() => typeMeta[form.type]);
const parentRequirementText = computed(() => {
    if (!needsParent.value) {
        return 'Tidak memerlukan induk.';
    }

    if (parentOptions.value.length === 0) {
        return `${parentLabel.value} belum tersedia. Buat data induknya terlebih dahulu.`;
    }

    return `Pilih ${parentLabel.value.toLowerCase()} agar data tersimpan pada posisi cascading yang benar.`;
});
const contentRequirementText = computed(() => {
    if (isTextNodeType.value) {
        return `${selectedTypeMeta.value.primaryField} wajib diisi.`;
    }

    if (isIndicatorType.value) {
        return 'Isi nama indikator, satuan, tipe indikator, dan sumber data bila ada.';
    }

    return 'Pilih periode dan isi target angka atau target teks.';
});
const nodeFormChecklist = computed(() => [
    {
        label: 'Jenis data',
        complete: Boolean(form.type),
    },
    {
        label: 'Induk cascading',
        complete: !needsParent.value || Boolean(form.parent_id),
    },
    {
        label: selectedTypeMeta.value.primaryField,
        complete:
            (isTextNodeType.value && Boolean(form.uraian)) ||
            (isIndicatorType.value && Boolean(form.indikator)) ||
            (isTargetType.value && Boolean(form.periode_tahun_id) && Boolean(form.target || form.target_text)),
    },
]);

const trimText = (value: string) => value.replace(/\s+/g, ' ').trim();
const nodeText = (kode: string | null | undefined, text: string | null | undefined) => trimText(`${kode ? `${kode} - ` : ''}${text ?? ''}`) || '-';
const joinItems = (items: string[]) => items.filter((item) => item && item !== '-').join('; ') || '-';
const indicatorSummary = (items: Indikator[]) => joinItems(items.map((item) => nodeText(item.kode, item.indikator)));
const targetSummary = (items: Indikator[]) =>
    joinItems(
        items.flatMap((item) =>
            (item.targets ?? []).map(
                (target) => `${item.kode ? `${item.kode} ` : ''}${target.periode_tahun.tahun}: ${target.target_text || target.target || '-'}`,
            ),
        ),
    );
const targetTriwulanSummary = (items: Indikator[]) =>
    joinItems(
        items.flatMap((item) =>
            (item.target_triwulan ?? []).map(
                (target) =>
                    `${item.kode ? `${item.kode} ` : ''}${target.periode_tahun.tahun} ${triwulanLabel(target.triwulan)}: ${target.target_text || target.target_angka || '-'}`,
            ),
        ),
    );

const renstraSummary = computed(() => {
    const summary = {
        tujuan: props.renstra.tujuan.length,
        tujuan_terhubung: 0,
        sasaran: 0,
        sasaran_terhubung: 0,
        program: 0,
        program_terhubung: 0,
        kegiatan: 0,
        sub_kegiatan: 0,
        indikator: 0,
        indikator_terhubung: 0,
        target_tahunan: 0,
        target_triwulan: 0,
        pagu_indikatif: 0,
    };

    props.renstra.tujuan.forEach((tujuan) => {
        if (tujuan.linked) {
            summary.tujuan_terhubung += 1;
        }
        summary.indikator += tujuan.indikator.length;
        summary.indikator_terhubung += tujuan.indikator.filter((indikator) => indikator.linked).length;
        summary.target_tahunan += tujuan.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
        summary.target_triwulan += tujuan.indikator.reduce((total, indikator) => total + (indikator.target_triwulan?.length ?? 0), 0);
        summary.sasaran += tujuan.sasaran.length;

        tujuan.sasaran.forEach((sasaran) => {
            if (sasaran.linked) {
                summary.sasaran_terhubung += 1;
            }
            summary.indikator += sasaran.indikator.length;
            summary.indikator_terhubung += sasaran.indikator.filter((indikator) => indikator.linked).length;
            summary.target_tahunan += sasaran.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
            summary.target_triwulan += sasaran.indikator.reduce((total, indikator) => total + (indikator.target_triwulan?.length ?? 0), 0);
            summary.program += sasaran.programs.length;

            sasaran.programs.forEach((program) => {
                if (program.linked) {
                    summary.program_terhubung += 1;
                }
                summary.pagu_indikatif += Number(program.pagu_indikatif ?? 0);
                summary.indikator += program.indikator.length;
                summary.indikator_terhubung += program.indikator.filter((indikator) => indikator.linked).length;
                summary.target_tahunan += program.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
                summary.target_triwulan += program.indikator.reduce((total, indikator) => total + (indikator.target_triwulan?.length ?? 0), 0);
                summary.kegiatan += program.kegiatan.length;

                program.kegiatan.forEach((kegiatan) => {
                    summary.pagu_indikatif += Number(kegiatan.pagu_indikatif ?? 0);
                    summary.sub_kegiatan += kegiatan.sub_kegiatan.length;
                    kegiatan.sub_kegiatan.forEach((subKegiatan) => {
                        summary.pagu_indikatif += Number(subKegiatan.pagu_indikatif ?? 0);
                        summary.indikator += subKegiatan.indikator.length;
                        summary.target_triwulan += subKegiatan.indikator.reduce(
                            (total, indikator) => total + (indikator.target_triwulan?.length ?? 0),
                            0,
                        );
                    });
                });
            });
        });
    });

    return summary;
});

const renstraCascadingRows = computed<RenstraCascadingRow[]>(() => {
    const rows: RenstraCascadingRow[] = [];

    props.renstra.tujuan.forEach((tujuan) => {
        if (tujuan.sasaran.length === 0) {
            rows.push(
                emptyRenstraRow(`tujuan-${tujuan.id}`, {
                    tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                    tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                    indikator_tujuan: indicatorSummary(tujuan.indikator),
                    target_tahunan: targetSummary(tujuan.indikator),
                    target_triwulan: targetTriwulanSummary(tujuan.indikator),
                    status_keterhubungan: tujuan.linked ? 'Terhubung RPJMD' : 'Belum terhubung',
                }),
            );
        }

        tujuan.sasaran.forEach((sasaran) => {
            if (sasaran.programs.length === 0) {
                rows.push(
                    emptyRenstraRow(`sasaran-${sasaran.id}`, {
                        tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                        tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                        indikator_tujuan: indicatorSummary(tujuan.indikator),
                        sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                        sasaran_rpjmd: sasaran.linked ? 'Terhubung' : 'Belum terhubung',
                        indikator_sasaran: indicatorSummary(sasaran.indikator),
                        target_tahunan: joinItems([targetSummary(tujuan.indikator), targetSummary(sasaran.indikator)]),
                        target_triwulan: joinItems([targetTriwulanSummary(tujuan.indikator), targetTriwulanSummary(sasaran.indikator)]),
                        status_keterhubungan: sasaran.linked ? 'Terhubung RPJMD' : 'Belum terhubung',
                    }),
                );
            }

            sasaran.programs.forEach((program) => {
                if (program.kegiatan.length === 0) {
                    rows.push(
                        emptyRenstraRow(`program-${program.id}`, {
                            tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                            tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                            indikator_tujuan: indicatorSummary(tujuan.indikator),
                            sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                            sasaran_rpjmd: sasaran.linked ? 'Terhubung' : 'Belum terhubung',
                            indikator_sasaran: indicatorSummary(sasaran.indikator),
                            program: nodeText(program.kode, program.nama),
                            program_rpjmd: program.linked ? 'Terhubung' : 'Belum terhubung',
                            indikator_program: indicatorSummary(program.indikator),
                            target_tahunan: joinItems([
                                targetSummary(tujuan.indikator),
                                targetSummary(sasaran.indikator),
                                targetSummary(program.indikator),
                            ]),
                            target_triwulan: joinItems([
                                targetTriwulanSummary(tujuan.indikator),
                                targetTriwulanSummary(sasaran.indikator),
                                targetTriwulanSummary(program.indikator),
                            ]),
                            pagu: formatCurrency(program.pagu_indikatif),
                            status_keterhubungan: program.linked ? 'Terhubung RPJMD' : 'Belum terhubung',
                        }),
                    );
                }

                program.kegiatan.forEach((kegiatan) => {
                    if (kegiatan.sub_kegiatan.length === 0) {
                        rows.push(
                            emptyRenstraRow(`kegiatan-${kegiatan.id}`, {
                                tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_tujuan: indicatorSummary(tujuan.indikator),
                                sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                sasaran_rpjmd: sasaran.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_sasaran: indicatorSummary(sasaran.indikator),
                                program: nodeText(program.kode, program.nama),
                                program_rpjmd: program.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_program: indicatorSummary(program.indikator),
                                kegiatan: nodeText(kegiatan.kode, kegiatan.nama),
                                target_tahunan: joinItems([
                                    targetSummary(tujuan.indikator),
                                    targetSummary(sasaran.indikator),
                                    targetSummary(program.indikator),
                                ]),
                                target_triwulan: joinItems([
                                    targetTriwulanSummary(tujuan.indikator),
                                    targetTriwulanSummary(sasaran.indikator),
                                    targetTriwulanSummary(program.indikator),
                                ]),
                                pagu: joinItems([formatCurrency(program.pagu_indikatif), formatCurrency(kegiatan.pagu_indikatif)]),
                                status_keterhubungan: program.linked ? 'Terhubung RPJMD' : 'Belum terhubung',
                            }),
                        );
                    }

                    kegiatan.sub_kegiatan.forEach((subKegiatan) => {
                        rows.push(
                            emptyRenstraRow(`sub-${subKegiatan.id}`, {
                                tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_tujuan: indicatorSummary(tujuan.indikator),
                                sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                sasaran_rpjmd: sasaran.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_sasaran: indicatorSummary(sasaran.indikator),
                                program: nodeText(program.kode, program.nama),
                                program_rpjmd: program.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_program: indicatorSummary(program.indikator),
                                kegiatan: nodeText(kegiatan.kode, kegiatan.nama),
                                sub_kegiatan: nodeText(subKegiatan.kode, subKegiatan.nama),
                                indikator_sub_kegiatan: indicatorSummary(subKegiatan.indikator),
                                target_tahunan: joinItems([
                                    targetSummary(tujuan.indikator),
                                    targetSummary(sasaran.indikator),
                                    targetSummary(program.indikator),
                                ]),
                                target_triwulan: joinItems([
                                    targetTriwulanSummary(tujuan.indikator),
                                    targetTriwulanSummary(sasaran.indikator),
                                    targetTriwulanSummary(program.indikator),
                                    targetTriwulanSummary(subKegiatan.indikator),
                                ]),
                                pagu: joinItems([
                                    formatCurrency(program.pagu_indikatif),
                                    formatCurrency(kegiatan.pagu_indikatif),
                                    formatCurrency(subKegiatan.pagu_indikatif),
                                ]),
                                status_keterhubungan: program.linked ? 'Terhubung RPJMD' : 'Belum terhubung',
                            }),
                        );
                    });
                });
            });
        });
    });

    return rows;
});

function emptyRenstraRow(key: string, values: Partial<RenstraCascadingRow>): RenstraCascadingRow {
    return {
        key,
        tujuan: '-',
        tujuan_rpjmd: '-',
        indikator_tujuan: '-',
        sasaran: '-',
        sasaran_rpjmd: '-',
        indikator_sasaran: '-',
        program: '-',
        program_rpjmd: '-',
        indikator_program: '-',
        kegiatan: '-',
        sub_kegiatan: '-',
        indikator_sub_kegiatan: '-',
        target_tahunan: '-',
        target_triwulan: '-',
        pagu: '-',
        status_keterhubungan: '-',
        ...values,
    };
}

function makeBulkRow(values: Partial<BulkRow> & { id: number; type: NodeType; level: string }): BulkRow {
    return {
        key: `${values.type}-${values.id}`,
        parent_label: '-',
        parent_id: '',
        kode: '',
        uraian: '',
        indikator: '',
        satuan_indikator_id: '',
        tipe_indikator: 'positif',
        formula: '',
        sumber_data: '',
        pagu_indikatif: '',
        periode_tahun_id: '',
        target: '',
        target_text: '',
        pagu: '',
        urutan: 1,
        reference_field: '',
        reference_value: '',
        saveState: 'idle',
        savedAt: '',
        error: '',
        ...values,
    };
}

function buildBulkRows(): BulkRow[] {
    const rows: BulkRow[] = [];

    props.renstra.tujuan.forEach((tujuan) => {
        rows.push(
            makeBulkRow({
                id: tujuan.id,
                type: 'tujuan',
                level: 'Tujuan OPD',
                kode: valueText(tujuan.kode),
                uraian: valueText(tujuan.tujuan),
                urutan: tujuan.urutan ?? 1,
                reference_field: 'tujuan_daerah_id',
                reference_value: valueText(tujuan.tujuan_daerah_id),
            }),
        );

        tujuan.indikator.forEach((indikator) => {
            rows.push(
                makeBulkRow({
                    id: indikator.id,
                    type: 'indikator_tujuan',
                    level: 'Indikator Tujuan',
                    parent_label: nodeText(tujuan.kode, tujuan.tujuan),
                    parent_id: tujuan.id,
                    kode: valueText(indikator.kode),
                    indikator: valueText(indikator.indikator),
                    satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                    tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                    formula: valueText(indikator.formula),
                    sumber_data: valueText(indikator.sumber_data),
                    urutan: indikator.urutan ?? 1,
                    reference_field: 'indikator_tujuan_daerah_id',
                    reference_value: valueText(indikator.indikator_tujuan_daerah_id),
                }),
            );

            (indikator.targets ?? []).forEach((target) => {
                rows.push(
                    makeBulkRow({
                        id: target.id,
                        type: 'target_tujuan',
                        level: 'Target Tujuan',
                        parent_label: nodeText(indikator.kode, indikator.indikator),
                        parent_id: indikator.id,
                        periode_tahun_id: target.periode_tahun.id,
                        target: valueText(target.target),
                        target_text: valueText(target.target_text),
                    }),
                );
            });
        });

        tujuan.sasaran.forEach((sasaran) => {
            rows.push(
                makeBulkRow({
                    id: sasaran.id,
                    type: 'sasaran',
                    level: 'Sasaran OPD',
                    parent_label: nodeText(tujuan.kode, tujuan.tujuan),
                    parent_id: tujuan.id,
                    kode: valueText(sasaran.kode),
                    uraian: valueText(sasaran.sasaran),
                    urutan: sasaran.urutan ?? 1,
                    reference_field: 'sasaran_daerah_id',
                    reference_value: valueText(sasaran.sasaran_daerah_id),
                }),
            );

            sasaran.indikator.forEach((indikator) => {
                rows.push(
                    makeBulkRow({
                        id: indikator.id,
                        type: 'indikator_sasaran',
                        level: 'Indikator Sasaran',
                        parent_label: nodeText(sasaran.kode, sasaran.sasaran),
                        parent_id: sasaran.id,
                        kode: valueText(indikator.kode),
                        indikator: valueText(indikator.indikator),
                        satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                        tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                        formula: valueText(indikator.formula),
                        sumber_data: valueText(indikator.sumber_data),
                        urutan: indikator.urutan ?? 1,
                        reference_field: 'indikator_sasaran_daerah_id',
                        reference_value: valueText(indikator.indikator_sasaran_daerah_id),
                    }),
                );

                (indikator.targets ?? []).forEach((target) => {
                    rows.push(
                        makeBulkRow({
                            id: target.id,
                            type: 'target_sasaran',
                            level: 'Target Sasaran',
                            parent_label: nodeText(indikator.kode, indikator.indikator),
                            parent_id: indikator.id,
                            periode_tahun_id: target.periode_tahun.id,
                            target: valueText(target.target),
                            target_text: valueText(target.target_text),
                        }),
                    );
                });
            });

            sasaran.programs.forEach((program) => {
                rows.push(
                    makeBulkRow({
                        id: program.id,
                        type: 'program',
                        level: 'Program OPD',
                        parent_label: nodeText(sasaran.kode, sasaran.sasaran),
                        parent_id: sasaran.id,
                        kode: valueText(program.kode),
                        uraian: valueText(program.nama),
                        pagu_indikatif: valueText(program.pagu_indikatif),
                        urutan: program.urutan ?? 1,
                        reference_field: 'program_rpjmd_id',
                        reference_value: valueText(program.program_rpjmd_id),
                    }),
                );

                program.indikator.forEach((indikator) => {
                    rows.push(
                        makeBulkRow({
                            id: indikator.id,
                            type: 'indikator_program',
                            level: 'Indikator Program',
                            parent_label: nodeText(program.kode, program.nama),
                            parent_id: program.id,
                            kode: valueText(indikator.kode),
                            indikator: valueText(indikator.indikator),
                            satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                            tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                            formula: valueText(indikator.formula),
                            sumber_data: valueText(indikator.sumber_data),
                            urutan: indikator.urutan ?? 1,
                            reference_field: 'indikator_program_rpjmd_id',
                            reference_value: valueText(indikator.indikator_program_rpjmd_id),
                        }),
                    );

                    (indikator.targets ?? []).forEach((target) => {
                        rows.push(
                            makeBulkRow({
                                id: target.id,
                                type: 'target_program',
                                level: 'Target Program',
                                parent_label: nodeText(indikator.kode, indikator.indikator),
                                parent_id: indikator.id,
                                periode_tahun_id: target.periode_tahun.id,
                                target: valueText(target.target),
                                target_text: valueText(target.target_text),
                                pagu: valueText(target.pagu),
                            }),
                        );
                    });
                });

                program.kegiatan.forEach((kegiatan) => {
                    rows.push(
                        makeBulkRow({
                            id: kegiatan.id,
                            type: 'kegiatan',
                            level: 'Kegiatan OPD',
                            parent_label: nodeText(program.kode, program.nama),
                            parent_id: program.id,
                            kode: valueText(kegiatan.kode),
                            uraian: valueText(kegiatan.nama),
                            pagu_indikatif: valueText(kegiatan.pagu_indikatif),
                            urutan: kegiatan.urutan ?? 1,
                        }),
                    );

                    kegiatan.sub_kegiatan.forEach((subKegiatan) => {
                        rows.push(
                            makeBulkRow({
                                id: subKegiatan.id,
                                type: 'sub_kegiatan',
                                level: 'Sub Kegiatan',
                                parent_label: nodeText(kegiatan.kode, kegiatan.nama),
                                parent_id: kegiatan.id,
                                kode: valueText(subKegiatan.kode),
                                uraian: valueText(subKegiatan.nama),
                                pagu_indikatif: valueText(subKegiatan.pagu_indikatif),
                                urutan: subKegiatan.urutan ?? 1,
                            }),
                        );

                        subKegiatan.indikator.forEach((indikator) => {
                            rows.push(
                                makeBulkRow({
                                    id: indikator.id,
                                    type: 'indikator_sub_kegiatan',
                                    level: 'Indikator Sub Kegiatan',
                                    parent_label: nodeText(subKegiatan.kode, subKegiatan.nama),
                                    parent_id: subKegiatan.id,
                                    kode: valueText(indikator.kode),
                                    indikator: valueText(indikator.indikator),
                                    satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                                    tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                                    formula: valueText(indikator.formula),
                                    sumber_data: valueText(indikator.sumber_data),
                                    urutan: indikator.urutan ?? 1,
                                }),
                            );
                        });
                    });
                });
            });
        });
    });

    return rows;
}

const clearNodeForm = () => {
    form.parent_id = '';
    form.periode_tahun_id = '';
    form.satuan_indikator_id = '';
    form.tujuan_daerah_id = '';
    form.indikator_tujuan_daerah_id = '';
    form.sasaran_daerah_id = '';
    form.indikator_sasaran_daerah_id = '';
    form.program_rpjmd_id = '';
    form.indikator_program_rpjmd_id = '';
    form.kode = '';
    form.uraian = '';
    form.indikator = '';
    form.tipe_indikator = 'positif';
    form.formula = '';
    form.sumber_data = '';
    form.target = '';
    form.target_text = '';
    form.pagu = '';
    form.pagu_indikatif = '';
    form.urutan = 1;
    form.clearErrors();
};

const resetNodeForm = () => {
    editingNode.value = null;
    clearNodeForm();
};

const selectNodeType = (type: NodeType, parentId: number | string = '') => {
    editingNode.value = null;
    form.type = type;

    nextTick(() => {
        clearNodeForm();
        form.parent_id = parentId;
        formPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const selectTargetTriwulan = (relatedTable: string, relatedId: number | string = '') => {
    targetTriwulanForm.related_table = relatedTable;

    nextTick(() => {
        targetTriwulanForm.related_id = relatedId;
        formPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const valueText = (value: unknown) => (value === null || value === undefined ? '' : String(value));

watch(
    () => props.renstra,
    () => {
        bulkRows.value = buildBulkRows();
    },
    { immediate: true },
);

const bulkParentOptions = (row: BulkRow): Option[] => {
    const key = parentKeyByType[row.type];

    return key ? (props.nodeOptions[key] ?? []) : [];
};

const bulkReferenceOptions = (row: BulkRow): Option[] => {
    if (!row.reference_field) {
        return [];
    }

    const referenceKey = row.reference_field.replace('_id', '');

    return props.rpjmdReferenceOptions[referenceKey] ?? [];
};

const isBulkTextRow = (row: BulkRow) => ['tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan'].includes(row.type);
const isBulkIndicatorRow = (row: BulkRow) =>
    ['indikator_tujuan', 'indikator_sasaran', 'indikator_program', 'indikator_sub_kegiatan'].includes(row.type);
const isBulkTargetRow = (row: BulkRow) => ['target_tujuan', 'target_sasaran', 'target_program'].includes(row.type);
const hasBulkPaguIndikatif = (row: BulkRow) => ['program', 'kegiatan', 'sub_kegiatan'].includes(row.type);
const hasBulkPaguTahunan = (row: BulkRow) => row.type === 'target_program';

const bulkStatusLabel = (row: BulkRow) =>
    ({
        idle: 'Siap',
        dirty: 'Menunggu autosave',
        saving: 'Menyimpan',
        saved: row.savedAt ? `Tersimpan ${row.savedAt}` : 'Tersimpan',
        error: row.error || 'Gagal simpan',
    })[row.saveState];

const bulkStatusClass = (row: BulkRow) =>
    ({
        idle: 'bg-slate-100 text-slate-700',
        dirty: 'bg-amber-100 text-amber-800',
        saving: 'bg-blue-100 text-blue-800',
        saved: 'bg-emerald-100 text-emerald-800',
        error: 'bg-red-100 text-red-800',
    })[row.saveState];

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const bulkRowPayload = (row: BulkRow) => {
    const payload: Record<string, unknown> = {
        type: row.type,
        parent_id: row.parent_id || null,
        kode: row.kode || null,
        uraian: row.uraian || null,
        indikator: row.indikator || null,
        satuan_indikator_id: row.satuan_indikator_id || null,
        tipe_indikator: row.tipe_indikator || 'positif',
        formula: row.formula || null,
        sumber_data: row.sumber_data || null,
        pagu_indikatif: row.pagu_indikatif || null,
        periode_tahun_id: row.periode_tahun_id || null,
        target: row.target || null,
        target_text: row.target_text || null,
        pagu: row.pagu || null,
        urutan: row.urutan || null,
    };

    if (row.reference_field) {
        payload[row.reference_field] = row.reference_value || null;
    }

    return payload;
};

const firstErrorMessage = (errors: Record<string, string[] | string> | undefined, fallback: string): string => {
    if (!errors) {
        return fallback;
    }

    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : first;
};

const saveBulkRow = async (row: BulkRow) => {
    row.saveState = 'saving';
    row.error = '';

    try {
        const response = await fetch(route('renstra-opd.nodes.autosave', [props.renstra.id, row.type, row.id]), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(bulkRowPayload(row)),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(firstErrorMessage(data.errors, data.message || 'Autosave gagal.'));
        }

        row.saveState = 'saved';
        row.savedAt = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        bulkLastSavedAt.value = row.savedAt;
    } catch (error) {
        row.saveState = 'error';
        row.error = error instanceof Error ? error.message : 'Autosave gagal.';
    }
};

const scheduleBulkAutosave = (row: BulkRow) => {
    if (!props.can.manage) {
        return;
    }

    window.clearTimeout(bulkSaveTimers.get(row.key));
    row.saveState = 'dirty';
    row.error = '';
    bulkSaveTimers.set(
        row.key,
        window.setTimeout(() => {
            void saveBulkRow(row);
        }, 900),
    );
};

onUnmounted(() => {
    bulkSaveTimers.forEach((timer) => window.clearTimeout(timer));
});

const editNode = (type: NodeType, id: number, parentId: number | null, node: any) => {
    editingNode.value = { type, id };
    form.type = type;
    clearNodeForm();
    form.parent_id = parentId ?? '';
    form.kode = valueText(node.kode);
    form.urutan = Number(node.urutan ?? 1);

    if (type === 'tujuan') {
        form.uraian = valueText(node.tujuan);
        form.tujuan_daerah_id = valueText(node.tujuan_daerah_id);
    } else if (type === 'sasaran') {
        form.uraian = valueText(node.sasaran);
        form.sasaran_daerah_id = valueText(node.sasaran_daerah_id);
    } else if (['program', 'kegiatan', 'sub_kegiatan'].includes(type)) {
        form.uraian = valueText(node.nama);
        form.pagu_indikatif = valueText(node.pagu_indikatif);
        form.program_rpjmd_id = valueText(node.program_rpjmd_id);
    } else if (isIndicatorType.value) {
        form.indikator = valueText(node.indikator);
        form.tipe_indikator = valueText(node.tipe_indikator || 'positif');
        form.satuan_indikator_id = valueText(node.satuan_indikator_id);
        form.formula = valueText(node.formula);
        form.sumber_data = valueText(node.sumber_data);
        form.indikator_tujuan_daerah_id = valueText(node.indikator_tujuan_daerah_id);
        form.indikator_sasaran_daerah_id = valueText(node.indikator_sasaran_daerah_id);
        form.indikator_program_rpjmd_id = valueText(node.indikator_program_rpjmd_id);
    } else if (isTargetType.value) {
        const target = node as unknown as Target;
        form.periode_tahun_id = target.periode_tahun?.id ?? '';
        form.target = valueText(target.target);
        form.target_text = valueText(target.target_text);
        form.pagu = valueText(target.pagu);
    }

    nextTick(() => {
        formPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
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
        form.put(route('renstra-opd.nodes.update', [props.renstra.id, editingNode.value.type, editingNode.value.id]), options);
        return;
    }

    form.post(route('renstra-opd.nodes.store', props.renstra.id), options);
};

const destroyNode = (type: NodeType, id: number, label: string) => {
    if (confirm(`Hapus ${label}? Data turunan juga dapat terpengaruh.`)) {
        router.delete(route('renstra-opd.nodes.destroy', [props.renstra.id, type, id]), {
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

const destroyTargetTriwulan = (target: TargetTriwulan) => {
    if (confirm('Hapus target triwulan indikator ini?')) {
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

const linkClass = (linked: boolean) => (linked ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800');
const linkLabel = (linked: boolean) => (linked ? 'Terhubung RPJMD' : 'Belum terhubung');
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
    <Head title="Cascading Renstra OPD" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-normal">{{ renstra.judul }}</h1>
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(renstra.status)">
                            {{ statusLabel(renstra.status) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ renstra.opd?.singkatan || renstra.opd?.nama || '-' }} - {{ renstra.tahun_awal }}-{{ renstra.tahun_akhir }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('renstra-opd.index')" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted">
                        <ArrowLeft class="size-4" />
                        Kembali
                    </Link>
                    <Link
                        v-if="can.manage"
                        :href="route('renstra-opd.edit', renstra.id)"
                        class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        <Pencil class="size-4" />
                        Edit
                    </Link>
                    <WorkflowActionButtons
                        module="renstra_opd"
                        :model-id="renstra.id"
                        :status="renstra.status"
                        :can-manage="can.manage"
                        :can-review="can.review"
                        :can-lock="can.lock"
                        :show-verify="false"
                    />
                </div>
            </div>

            <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-4">
                <div>
                    <div class="text-xs uppercase text-muted-foreground">OPD</div>
                    <div class="mt-1 text-sm font-medium">{{ renstra.opd?.nama || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">RPJMD Terhubung</div>
                    <div class="mt-1 text-sm font-medium">{{ renstra.rpjmd ? `${renstra.rpjmd.tahun_awal}-${renstra.rpjmd.tahun_akhir}` : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Periode</div>
                    <div class="mt-1 text-sm font-medium">{{ renstra.periode_tahun?.nama || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Jumlah Tujuan</div>
                    <div class="mt-1 text-sm font-medium">{{ renstra.tujuan.length }}</div>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border bg-card p-4">
                    <div class="text-xs uppercase text-muted-foreground">Tujuan dan Sasaran</div>
                    <div class="mt-2 text-2xl font-semibold">{{ renstraSummary.tujuan + renstraSummary.sasaran }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ renstraSummary.tujuan_terhubung }}/{{ renstraSummary.tujuan }} tujuan, {{ renstraSummary.sasaran_terhubung }}/{{
                            renstraSummary.sasaran
                        }}
                        sasaran terhubung
                    </div>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <div class="text-xs uppercase text-muted-foreground">Program OPD</div>
                    <div class="mt-2 text-2xl font-semibold">{{ renstraSummary.program }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ renstraSummary.program_terhubung }} terhubung RPJMD, {{ renstraSummary.program - renstraSummary.program_terhubung }} belum
                    </div>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <div class="text-xs uppercase text-muted-foreground">Indikator dan Target</div>
                    <div class="mt-2 text-2xl font-semibold">{{ renstraSummary.indikator }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ renstraSummary.target_tahunan }} target tahunan, {{ renstraSummary.target_triwulan }} target triwulan
                    </div>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <div class="text-xs uppercase text-muted-foreground">Pagu Berjenjang</div>
                    <div class="mt-2 text-2xl font-semibold">{{ formatCurrency(renstraSummary.pagu_indikatif) }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ renstraSummary.kegiatan }} kegiatan, {{ renstraSummary.sub_kegiatan }} sub kegiatan
                    </div>
                </div>
            </section>

            <WorkflowHistoryTimeline :workflow="workflow" />

            <section class="flex flex-col gap-3 rounded-lg border bg-card p-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <Network class="size-5 text-emerald-700" />
                    <div>
                        <h2 class="text-base font-semibold">Cascading Renstra OPD</h2>
                        <p class="text-sm text-muted-foreground">Pilih tampilan tree atau tabel melebar untuk membaca hubungan Renstra ke RPJMD.</p>
                    </div>
                </div>
                <div class="inline-flex rounded-md border bg-background p-1">
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-2 rounded px-3 text-sm"
                        :class="viewMode === 'tree' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-muted'"
                        @click="viewMode = 'tree'"
                    >
                        <Layers3 class="size-4" />
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
                    <button
                        v-if="can.manage"
                        type="button"
                        class="inline-flex h-8 items-center gap-2 rounded px-3 text-sm"
                        :class="viewMode === 'bulk' ? 'bg-emerald-700 text-white' : 'text-muted-foreground hover:bg-muted'"
                        @click="viewMode = 'bulk'"
                    >
                        <Save class="size-4" />
                        Bulk
                    </button>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_28rem]">
                <section v-if="viewMode === 'bulk' && can.manage" class="rounded-lg border bg-card">
                    <div class="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-2">
                            <Save class="mt-0.5 size-5 text-emerald-700" />
                            <div>
                                <h2 class="text-base font-semibold">Bulk Mode Autosave</h2>
                                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                    Edit data cascading dalam tabel lebar. Perubahan disimpan otomatis sekitar 1 detik setelah input berhenti.
                                </p>
                            </div>
                        </div>
                        <div class="rounded-md border bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-900">
                            {{ bulkLastSavedAt ? `Terakhir autosave ${bulkLastSavedAt}` : 'Belum ada perubahan bulk' }}
                        </div>
                    </div>

                    <div v-if="bulkRows.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                        <Layers3 class="mx-auto size-10 text-muted-foreground" />
                        <p class="mt-3 font-semibold text-slate-900">Belum ada data untuk bulk mode</p>
                        <p class="mt-1">Buat Tujuan OPD terlebih dahulu melalui panel pengisian atau tombol pada tree.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-[2500px] text-left text-sm">
                            <thead class="sticky top-0 z-10 border-b bg-muted/80 text-xs uppercase text-muted-foreground backdrop-blur">
                                <tr>
                                    <th class="sticky left-0 z-20 min-w-44 bg-muted/90 px-3 py-3">Status</th>
                                    <th class="min-w-44 px-3 py-3">Level</th>
                                    <th class="min-w-72 px-3 py-3">Induk</th>
                                    <th class="min-w-40 px-3 py-3">Ubah Induk</th>
                                    <th class="min-w-32 px-3 py-3">Kode</th>
                                    <th class="min-w-80 px-3 py-3">Uraian/Nama</th>
                                    <th class="min-w-80 px-3 py-3">Indikator</th>
                                    <th class="min-w-72 px-3 py-3">Referensi RPJMD</th>
                                    <th class="min-w-56 px-3 py-3">Satuan</th>
                                    <th class="min-w-40 px-3 py-3">Tipe</th>
                                    <th class="min-w-72 px-3 py-3">Formula</th>
                                    <th class="min-w-60 px-3 py-3">Sumber Data</th>
                                    <th class="min-w-44 px-3 py-3">Pagu Indikatif</th>
                                    <th class="min-w-52 px-3 py-3">Periode Target</th>
                                    <th class="min-w-40 px-3 py-3">Target Angka</th>
                                    <th class="min-w-56 px-3 py-3">Target Teks</th>
                                    <th class="min-w-44 px-3 py-3">Pagu Tahunan</th>
                                    <th class="min-w-28 px-3 py-3">Urutan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in bulkRows" :key="row.key" class="border-b align-top last:border-0 hover:bg-muted/30">
                                    <td class="sticky left-0 z-10 bg-card px-3 py-3">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="bulkStatusClass(row)">
                                            {{ bulkStatusLabel(row) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-semibold text-slate-900">{{ row.level }}</div>
                                        <div class="mt-1 text-xs text-muted-foreground">{{ typeOptionMap.get(row.type)?.label }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-xs leading-5 text-slate-700">{{ row.parent_label }}</td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-if="bulkParentOptions(row).length"
                                            v-model="row.parent_id"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="">Pilih induk</option>
                                            <option v-for="option in bulkParentOptions(row)" :key="option.id" :value="option.id">{{ option.label }}</option>
                                        </select>
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.kode"
                                            :disabled="isBulkTargetRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-model="row.uraian"
                                            :disabled="!isBulkTextRow(row)"
                                            rows="3"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-model="row.indikator"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            rows="3"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-if="bulkReferenceOptions(row).length"
                                            v-model="row.reference_value"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="">Tidak dihubungkan</option>
                                            <option v-for="option in bulkReferenceOptions(row)" :key="option.id" :value="option.id">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-model="row.satuan_indikator_id"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="">Pilih satuan</option>
                                            <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-model="row.tipe_indikator"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="positif">Positif</option>
                                            <option value="negatif">Negatif</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-model="row.formula"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            rows="3"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.sumber_data"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.pagu_indikatif"
                                            :disabled="!hasBulkPaguIndikatif(row)"
                                            type="number"
                                            step="0.01"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-model="row.periode_tahun_id"
                                            :disabled="!isBulkTargetRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="">Pilih periode</option>
                                            <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.target"
                                            :disabled="!isBulkTargetRow(row)"
                                            type="number"
                                            step="0.0001"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.target_text"
                                            :disabled="!isBulkTargetRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.pagu"
                                            :disabled="!hasBulkPaguTahunan(row)"
                                            type="number"
                                            step="0.01"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.urutan"
                                            :disabled="isBulkTargetRow(row)"
                                            type="number"
                                            min="1"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-emerald-700 disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-else-if="viewMode === 'table'" class="rounded-lg border bg-card">
                    <div class="flex items-center gap-2 border-b p-4">
                        <Table2 class="size-5 text-emerald-700" />
                        <div>
                            <h2 class="text-base font-semibold">Tabel Cascading Melebar</h2>
                            <p class="text-sm text-muted-foreground">
                                Setiap baris membawa konteks tujuan sampai sub kegiatan dan status link ke RPJMD.
                            </p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[1800px] text-left text-sm">
                            <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3">Tujuan OPD</th>
                                    <th class="px-4 py-3">Link Tujuan</th>
                                    <th class="px-4 py-3">Indikator Tujuan</th>
                                    <th class="px-4 py-3">Sasaran OPD</th>
                                    <th class="px-4 py-3">Link Sasaran</th>
                                    <th class="px-4 py-3">Indikator Sasaran</th>
                                    <th class="px-4 py-3">Program</th>
                                    <th class="px-4 py-3">Link Program</th>
                                    <th class="px-4 py-3">Indikator Program</th>
                                    <th class="px-4 py-3">Kegiatan</th>
                                    <th class="px-4 py-3">Sub Kegiatan</th>
                                    <th class="px-4 py-3">Indikator Sub Kegiatan</th>
                                    <th class="px-4 py-3">Target Tahunan</th>
                                    <th class="px-4 py-3">Target Triwulan</th>
                                    <th class="px-4 py-3">Pagu</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in renstraCascadingRows" :key="row.key" class="border-b align-top last:border-0">
                                    <td class="max-w-[260px] px-4 py-3 font-medium">{{ row.tujuan }}</td>
                                    <td class="px-4 py-3">{{ row.tujuan_rpjmd }}</td>
                                    <td class="max-w-[280px] px-4 py-3">{{ row.indikator_tujuan }}</td>
                                    <td class="max-w-[260px] px-4 py-3">{{ row.sasaran }}</td>
                                    <td class="px-4 py-3">{{ row.sasaran_rpjmd }}</td>
                                    <td class="max-w-[280px] px-4 py-3">{{ row.indikator_sasaran }}</td>
                                    <td class="max-w-[260px] px-4 py-3 font-medium">{{ row.program }}</td>
                                    <td class="px-4 py-3">{{ row.program_rpjmd }}</td>
                                    <td class="max-w-[280px] px-4 py-3">{{ row.indikator_program }}</td>
                                    <td class="max-w-[260px] px-4 py-3">{{ row.kegiatan }}</td>
                                    <td class="max-w-[260px] px-4 py-3">{{ row.sub_kegiatan }}</td>
                                    <td class="max-w-[280px] px-4 py-3">{{ row.indikator_sub_kegiatan }}</td>
                                    <td class="max-w-[240px] px-4 py-3">{{ row.target_tahunan }}</td>
                                    <td class="max-w-[240px] px-4 py-3">{{ row.target_triwulan }}</td>
                                    <td class="max-w-[240px] px-4 py-3">{{ row.pagu }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                            :class="
                                                row.status_keterhubungan === 'Terhubung RPJMD'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-amber-100 text-amber-800'
                                            "
                                        >
                                            {{ row.status_keterhubungan }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="renstraCascadingRows.length === 0">
                                    <td colspan="16" class="px-4 py-10 text-center text-muted-foreground">Belum ada data cascading Renstra OPD.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-else class="rounded-lg border bg-card">
                    <div class="flex items-center gap-2 border-b p-4">
                        <Layers3 class="size-5 text-emerald-700" />
                        <div>
                            <h2 class="text-base font-semibold">Tree Cascading OPD</h2>
                            <p class="text-sm text-muted-foreground">
                                Tujuan, sasaran, program, kegiatan, sub kegiatan, indikator, dan target tahunan.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4 p-4">
                        <div v-if="renstra.tujuan.length === 0" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                            <Layers3 class="mx-auto size-10 text-muted-foreground" />
                            <p class="mt-3 font-semibold text-slate-900">Belum ada cascading Renstra OPD</p>
                            <p class="mt-1">Mulai dengan membuat Tujuan OPD sebagai level pertama pohon kinerja.</p>
                            <button
                                v-if="can.manage"
                                type="button"
                                class="mt-4 inline-flex min-h-10 items-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800"
                                @click="selectNodeType('tujuan')"
                            >
                                <Plus class="size-4" />
                                Tambah Tujuan OPD
                            </button>
                        </div>

                        <article v-for="tujuan in renstra.tujuan" :key="tujuan.id" class="rounded-md border bg-background">
                            <div class="flex items-start justify-between gap-3 border-b p-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-semibold uppercase text-emerald-700">Tujuan OPD</span>
                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(tujuan.linked)">{{
                                            linkLabel(tujuan.linked)
                                        }}</span>
                                    </div>
                                    <div class="mt-1 text-sm font-medium">{{ tujuan.kode ? `${tujuan.kode} - ` : '' }}{{ tujuan.tujuan }}</div>
                                </div>
                                <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                        @click="selectNodeType('indikator_tujuan', tujuan.id)"
                                    >
                                        <Plus class="size-3.5" />
                                        Indikator
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-sky-800 hover:bg-sky-50"
                                        @click="selectNodeType('sasaran', tujuan.id)"
                                    >
                                        <Plus class="size-3.5" />
                                        Sasaran
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md p-1 hover:bg-muted"
                                        title="Edit tujuan"
                                        @click="editNode('tujuan', tujuan.id, null, tujuan)"
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

                            <div class="space-y-3 p-3">
                                <div v-for="indikator in tujuan.indikator" :key="indikator.id" class="rounded-md border bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-semibold uppercase text-muted-foreground">Indikator Tujuan</span>
                                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(indikator.linked)">{{
                                                    linkLabel(indikator.linked)
                                                }}</span>
                                            </div>
                                            <div class="mt-1 text-sm">
                                                {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
                                            </div>
                                            <div class="mt-1 text-xs text-muted-foreground">
                                                {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}
                                            </div>
                                        </div>
                                        <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                @click="selectNodeType('target_tujuan', indikator.id)"
                                            >
                                                <Plus class="size-3.5" />
                                                Target
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-blue-800 hover:bg-blue-50"
                                                @click="selectTargetTriwulan('indikator_tujuan_opd', indikator.id)"
                                            >
                                                <Plus class="size-3.5" />
                                                TW
                                            </button>
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
                                    <div v-if="indikator.targets?.length" class="mt-2 flex flex-wrap gap-2">
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
                                    <div v-if="indikator.target_triwulan?.length" class="mt-2 flex flex-wrap gap-2">
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

                                <div v-for="sasaran in tujuan.sasaran" :key="sasaran.id" class="rounded-md border bg-slate-50 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-semibold uppercase text-muted-foreground">Sasaran OPD</span>
                                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(sasaran.linked)">{{
                                                    linkLabel(sasaran.linked)
                                                }}</span>
                                            </div>
                                            <div class="mt-1 text-sm font-medium">
                                                {{ sasaran.kode ? `${sasaran.kode} - ` : '' }}{{ sasaran.sasaran }}
                                            </div>
                                        </div>
                                        <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                @click="selectNodeType('indikator_sasaran', sasaran.id)"
                                            >
                                                <Plus class="size-3.5" />
                                                Indikator
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-sky-800 hover:bg-sky-50"
                                                @click="selectNodeType('program', sasaran.id)"
                                            >
                                                <Plus class="size-3.5" />
                                                Program
                                            </button>
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

                                    <div class="mt-3 space-y-3">
                                        <div v-for="indikator in sasaran.indikator" :key="indikator.id" class="rounded-md border bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Indikator Sasaran</span>
                                                        <span
                                                            class="rounded-full px-2 py-1 text-xs font-medium"
                                                            :class="linkClass(indikator.linked)"
                                                            >{{ linkLabel(indikator.linked) }}</span
                                                        >
                                                    </div>
                                                    <div class="mt-1 text-sm">
                                                        {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                        {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}
                                                    </div>
                                                </div>
                                                <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                        @click="selectNodeType('target_sasaran', indikator.id)"
                                                    >
                                                        <Plus class="size-3.5" />
                                                        Target
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-blue-800 hover:bg-blue-50"
                                                        @click="selectTargetTriwulan('indikator_sasaran_opd', indikator.id)"
                                                    >
                                                        <Plus class="size-3.5" />
                                                        TW
                                                    </button>
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
                                            <div v-if="indikator.targets?.length" class="mt-2 flex flex-wrap gap-2">
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
                                            <div v-if="indikator.target_triwulan?.length" class="mt-2 flex flex-wrap gap-2">
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

                                        <div v-for="program in sasaran.programs" :key="program.id" class="rounded-md border bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Program OPD</span>
                                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(program.linked)">{{
                                                            linkLabel(program.linked)
                                                        }}</span>
                                                    </div>
                                                    <div class="mt-1 text-sm font-medium">
                                                        {{ program.kode ? `${program.kode} - ` : '' }}{{ program.nama }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                        Pagu indikatif: {{ program.pagu_indikatif || '-' }}
                                                    </div>
                                                </div>
                                                <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                        @click="selectNodeType('indikator_program', program.id)"
                                                    >
                                                        <Plus class="size-3.5" />
                                                        Indikator
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-sky-800 hover:bg-sky-50"
                                                        @click="selectNodeType('kegiatan', program.id)"
                                                    >
                                                        <Plus class="size-3.5" />
                                                        Kegiatan
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="rounded-md p-1 hover:bg-muted"
                                                        title="Edit program"
                                                        @click="editNode('program', program.id, sasaran.id, program)"
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

                                            <div class="mt-3 grid gap-2">
                                                <div
                                                    v-for="indikator in program.indikator"
                                                    :key="indikator.id"
                                                    class="rounded-md border bg-slate-50 p-3"
                                                >
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <span class="text-xs font-semibold uppercase text-muted-foreground"
                                                                    >Indikator Program</span
                                                                >
                                                                <span
                                                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                                                    :class="linkClass(indikator.linked)"
                                                                    >{{ linkLabel(indikator.linked) }}</span
                                                                >
                                                            </div>
                                                            <div class="mt-1 text-sm">
                                                                {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
                                                            </div>
                                                            <div class="mt-1 text-xs text-muted-foreground">
                                                                {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}
                                                            </div>
                                                        </div>
                                                        <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                            <button
                                                                type="button"
                                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                                @click="selectNodeType('target_program', indikator.id)"
                                                            >
                                                                <Plus class="size-3.5" />
                                                                Target
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-blue-800 hover:bg-blue-50"
                                                                @click="selectTargetTriwulan('indikator_opd_program', indikator.id)"
                                                            >
                                                                <Plus class="size-3.5" />
                                                                TW
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="rounded-md p-1 hover:bg-muted"
                                                                title="Edit indikator"
                                                                @click="editNode('indikator_program', indikator.id, program.id, indikator)"
                                                            >
                                                                <Pencil class="size-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                title="Hapus indikator"
                                                                @click="destroyNode('indikator_program', indikator.id, 'indikator program')"
                                                            >
                                                                <Trash2 class="size-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div v-if="indikator.targets?.length" class="mt-2 flex flex-wrap gap-2">
                                                        <span
                                                            v-for="target in indikator.targets"
                                                            :key="target.id"
                                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800"
                                                        >
                                                            {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }} - Pagu
                                                            {{ target.pagu || '-' }}
                                                            <button
                                                                v-if="can.manage"
                                                                type="button"
                                                                class="font-semibold text-emerald-900 hover:text-slate-900"
                                                                @click="editNode('target_program', target.id, indikator.id, target)"
                                                            >
                                                                Edit
                                                            </button>
                                                        </span>
                                                    </div>
                                                    <div v-if="indikator.target_triwulan?.length" class="mt-2 flex flex-wrap gap-2">
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

                                                <div
                                                    v-for="kegiatan in program.kegiatan"
                                                    :key="kegiatan.id"
                                                    class="rounded-md border bg-slate-50 p-3"
                                                >
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-xs font-semibold uppercase text-muted-foreground">Kegiatan OPD</div>
                                                            <div class="mt-1 text-sm font-medium">
                                                                {{ kegiatan.kode ? `${kegiatan.kode} - ` : '' }}{{ kegiatan.nama }}
                                                            </div>
                                                        </div>
                                                        <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                            <button
                                                                type="button"
                                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-sky-800 hover:bg-sky-50"
                                                                @click="selectNodeType('sub_kegiatan', kegiatan.id)"
                                                            >
                                                                <Plus class="size-3.5" />
                                                                Sub Kegiatan
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="rounded-md p-1 hover:bg-muted"
                                                                title="Edit kegiatan"
                                                                @click="editNode('kegiatan', kegiatan.id, program.id, kegiatan)"
                                                            >
                                                                <Pencil class="size-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                title="Hapus kegiatan"
                                                                @click="destroyNode('kegiatan', kegiatan.id, 'kegiatan')"
                                                            >
                                                                <Trash2 class="size-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-for="sub in kegiatan.sub_kegiatan"
                                                        :key="sub.id"
                                                        class="mt-3 rounded-md border bg-white p-3"
                                                    >
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Sub Kegiatan</div>
                                                                <div class="mt-1 text-sm font-medium">
                                                                    {{ sub.kode ? `${sub.kode} - ` : '' }}{{ sub.nama }}
                                                                </div>
                                                            </div>
                                                            <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                                    @click="selectNodeType('indikator_sub_kegiatan', sub.id)"
                                                                >
                                                                    <Plus class="size-3.5" />
                                                                    Indikator
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 hover:bg-muted"
                                                                    title="Edit sub kegiatan"
                                                                    @click="editNode('sub_kegiatan', sub.id, kegiatan.id, sub)"
                                                                >
                                                                    <Pencil class="size-4" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                    title="Hapus sub kegiatan"
                                                                    @click="destroyNode('sub_kegiatan', sub.id, 'sub kegiatan')"
                                                                >
                                                                    <Trash2 class="size-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div v-if="sub.indikator.length" class="mt-2 grid gap-2">
                                                            <div
                                                                v-for="indikator in sub.indikator"
                                                                :key="indikator.id"
                                                                class="rounded-md bg-slate-50 px-3 py-2 text-sm"
                                                            >
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <div>
                                                                            {{ indikator.kode ? `${indikator.kode} - ` : ''
                                                                            }}{{ indikator.indikator }}
                                                                        </div>
                                                                        <div class="mt-1 text-xs text-muted-foreground">
                                                                            {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}
                                                                        </div>
                                                                    </div>
                                                                    <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                                        <button
                                                                            type="button"
                                                                            class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-blue-800 hover:bg-blue-50"
                                                                            @click="selectTargetTriwulan('indikator_sub_kegiatan', indikator.id)"
                                                                        >
                                                                            <Plus class="size-3.5" />
                                                                            TW
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            class="rounded-md p-1 hover:bg-muted"
                                                                            title="Edit indikator"
                                                                            @click="
                                                                                editNode('indikator_sub_kegiatan', indikator.id, sub.id, indikator)
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
                                                                                    'indikator_sub_kegiatan',
                                                                                    indikator.id,
                                                                                    'indikator sub kegiatan',
                                                                                )
                                                                            "
                                                                        >
                                                                            <Trash2 class="size-4" />
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div v-if="indikator.target_triwulan?.length" class="mt-2 flex flex-wrap gap-2">
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

                <aside v-if="can.manage" ref="formPanel" class="grid gap-4 xl:sticky xl:top-4 xl:self-start">
                    <section class="overflow-hidden rounded-lg border bg-card shadow-sm">
                        <div class="border-b bg-[linear-gradient(135deg,#f8fafc,#ecfdf5)] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1 text-xs font-semibold uppercase text-emerald-800">
                                        <ClipboardList class="size-3.5" />
                                        Panel Pengisian
                                    </div>
                                    <h2 class="mt-3 text-base font-semibold text-slate-950">
                                        {{ editingNode ? 'Edit Data Cascading' : 'Tambah Data Cascading' }}
                                    </h2>
                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                        {{ selectedTypeMeta.helper }}
                                    </p>
                                </div>
                                <button
                                    v-if="editingNode"
                                    type="button"
                                    class="inline-flex min-h-9 shrink-0 items-center rounded-md border bg-white px-3 text-xs font-medium hover:bg-muted"
                                    @click="resetNodeForm"
                                >
                                    Batal edit
                                </button>
                            </div>

                            <div class="mt-4 grid gap-2">
                                <div v-for="item in nodeFormChecklist" :key="item.label" class="flex items-center gap-2 text-xs">
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full"
                                        :class="item.complete ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                                    >
                                        <CheckCircle2 v-if="item.complete" class="size-3.5" />
                                        <CircleDot v-else class="size-3" />
                                    </span>
                                    <span :class="item.complete ? 'text-slate-700' : 'text-muted-foreground'">{{ item.label }}</span>
                                </div>
                            </div>
                        </div>

                        <form class="grid gap-4 p-4" @submit.prevent="submitNode">
                            <div class="grid gap-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-950">1. Pilih jenis data</h3>
                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                            Pilih dari urutan kerja, atau klik tombol tambah langsung dari tree di kiri.
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                        {{ selectedTypeMeta.stage }}
                                    </span>
                                </div>

                                <div class="grid gap-3">
                                    <div v-for="group in typeGroups" :key="group.label" class="rounded-md border bg-background p-3">
                                        <div class="mb-2 flex items-start gap-2">
                                            <component :is="group.icon" class="mt-0.5 size-4 text-emerald-700" />
                                            <div>
                                                <p class="text-xs font-semibold uppercase text-slate-800">{{ group.label }}</p>
                                                <p class="mt-0.5 text-xs leading-5 text-muted-foreground">{{ group.helper }}</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button
                                                v-for="type in group.items"
                                                :key="type"
                                                type="button"
                                                class="min-h-10 rounded-md border px-2 text-left text-xs font-medium transition hover:border-emerald-300 hover:bg-emerald-50"
                                                :class="
                                                    form.type === type
                                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-600'
                                                        : 'bg-white text-slate-700'
                                                "
                                                @click="selectNodeType(type)"
                                            >
                                                {{ typeOptionMap.get(type)?.label }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium" for="type">Jenis Data Terpilih</label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                    >
                                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="form.errors.type" />
                                </div>
                            </div>

                            <div class="grid gap-3 rounded-md border bg-background p-3">
                                <div class="flex items-start gap-2">
                                    <Link2 class="mt-0.5 size-4 text-sky-700" />
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-950">2. Induk dan referensi</h3>
                                        <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ parentRequirementText }}</p>
                                    </div>
                                </div>

                                <div v-if="needsParent" class="grid gap-2">
                                    <label class="text-sm font-medium" for="parent_id">{{ parentLabel }} <span class="text-red-600">*</span></label>
                                    <select
                                        id="parent_id"
                                        v-model="form.parent_id"
                                        class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                    >
                                        <option value="">Pilih {{ parentLabel.toLowerCase() }}</option>
                                        <option v-for="option in parentOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="form.errors.parent_id" />
                                </div>
                            </div>

                        <div v-if="form.type === 'tujuan'" class="grid gap-2">
                            <label class="text-sm font-medium" for="tujuan_daerah_id">Referensi Tujuan RPJMD</label>
                            <select
                                id="tujuan_daerah_id"
                                v-model="form.tujuan_daerah_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.tujuan_daerah || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.type === 'indikator_tujuan'" class="grid gap-2">
                            <label class="text-sm font-medium" for="indikator_tujuan_daerah_id">Referensi Indikator Tujuan RPJMD</label>
                            <select
                                id="indikator_tujuan_daerah_id"
                                v-model="form.indikator_tujuan_daerah_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_tujuan_daerah || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.type === 'sasaran'" class="grid gap-2">
                            <label class="text-sm font-medium" for="sasaran_daerah_id">Referensi Sasaran RPJMD</label>
                            <select
                                id="sasaran_daerah_id"
                                v-model="form.sasaran_daerah_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.sasaran_daerah || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.type === 'indikator_sasaran'" class="grid gap-2">
                            <label class="text-sm font-medium" for="indikator_sasaran_daerah_id">Referensi Indikator Sasaran RPJMD</label>
                            <select
                                id="indikator_sasaran_daerah_id"
                                v-model="form.indikator_sasaran_daerah_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_sasaran_daerah || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.type === 'program'" class="grid gap-2">
                            <label class="text-sm font-medium" for="program_rpjmd_id">Referensi Program RPJMD</label>
                            <select
                                id="program_rpjmd_id"
                                v-model="form.program_rpjmd_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.program_rpjmd || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.type === 'indikator_program'" class="grid gap-2">
                            <label class="text-sm font-medium" for="indikator_program_rpjmd_id">Referensi Indikator Program RPJMD</label>
                            <select
                                id="indikator_program_rpjmd_id"
                                v-model="form.indikator_program_rpjmd_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_program_rpjmd || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-start gap-2 rounded-md border bg-background p-3">
                            <FileText class="mt-0.5 size-4 text-amber-700" />
                            <div>
                                <h3 class="text-sm font-semibold text-slate-950">3. Isi data</h3>
                                <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ contentRequirementText }}</p>
                            </div>
                        </div>

                        <div v-if="!isTargetType" class="grid gap-2">
                            <label class="text-sm font-medium" for="kode">Kode</label>
                            <input
                                id="kode"
                                v-model="form.kode"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                placeholder="Contoh: T.1, SS.1, PR.1"
                            />
                            <InputError :message="form.errors.kode" />
                        </div>

                        <div v-if="isTextNodeType" class="grid gap-2">
                            <label class="text-sm font-medium" for="uraian">{{ selectedTypeLabel }}</label>
                            <textarea
                                id="uraian"
                                v-model="form.uraian"
                                rows="4"
                                class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <InputError :message="form.errors.uraian" />
                        </div>

                        <div v-if="isIndicatorType" class="grid gap-2">
                            <label class="text-sm font-medium" for="indikator">Indikator</label>
                            <textarea
                                id="indikator"
                                v-model="form.indikator"
                                rows="4"
                                class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-emerald-700"
                                placeholder="Tuliskan indikator yang terukur."
                            />
                            <InputError :message="form.errors.indikator" />
                        </div>

                        <div v-if="isIndicatorType" class="grid gap-2">
                            <label class="text-sm font-medium" for="satuan_indikator_id">Satuan Indikator</label>
                            <select
                                id="satuan_indikator_id"
                                v-model="form.satuan_indikator_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Pilih satuan</option>
                                <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </div>

                        <div v-if="isIndicatorType" class="grid gap-2">
                            <label class="text-sm font-medium" for="tipe_indikator">Tipe Indikator</label>
                            <select
                                id="tipe_indikator"
                                v-model="form.tipe_indikator"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="positif">Positif</option>
                                <option value="negatif">Negatif</option>
                            </select>
                            <span class="text-xs leading-5 text-muted-foreground">
                                Positif berarti semakin tinggi semakin baik. Negatif berarti semakin rendah semakin baik.
                            </span>
                            <InputError :message="form.errors.tipe_indikator" />
                        </div>

                        <div v-if="isIndicatorType" class="grid gap-2">
                            <label class="text-sm font-medium" for="formula">Formula Indikator</label>
                            <textarea
                                id="formula"
                                v-model="form.formula"
                                rows="3"
                                class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-emerald-700"
                                placeholder="Opsional, contoh: jumlah realisasi / target x 100%"
                            />
                        </div>

                        <div v-if="isIndicatorType" class="grid gap-2">
                            <label class="text-sm font-medium" for="sumber_data">Sumber Data</label>
                            <input
                                id="sumber_data"
                                v-model="form.sumber_data"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                placeholder="Contoh: Bidang IKP, SIPD, laporan bidang"
                            />
                        </div>

                        <div v-if="hasPaguIndikatif" class="grid gap-2">
                            <label class="text-sm font-medium" for="pagu_indikatif">Pagu Indikatif</label>
                            <input
                                id="pagu_indikatif"
                                v-model="form.pagu_indikatif"
                                type="number"
                                step="0.01"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                        </div>

                        <div v-if="isTargetType" class="grid gap-2">
                            <label class="text-sm font-medium" for="periode_tahun_id">Periode Target</label>
                            <select
                                id="periode_tahun_id"
                                v-model="form.periode_tahun_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
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
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                        </div>

                        <div v-if="isTargetType" class="grid gap-2">
                            <label class="text-sm font-medium" for="target_text">Target Teks</label>
                            <input
                                id="target_text"
                                v-model="form.target_text"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                placeholder="Opsional, contoh: 100 dokumen"
                            />
                        </div>

                        <div v-if="form.type === 'target_program'" class="grid gap-2">
                            <label class="text-sm font-medium" for="pagu">Pagu Tahunan</label>
                            <input
                                id="pagu"
                                v-model="form.pagu"
                                type="number"
                                step="0.01"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                        </div>

                        <div v-if="!isTargetType" class="grid gap-2">
                            <label class="text-sm font-medium" for="urutan">Urutan</label>
                            <input
                                id="urutan"
                                v-model="form.urutan"
                                type="number"
                                min="1"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="sticky bottom-3 z-10 mt-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-lg shadow-emerald-900/10 hover:bg-emerald-800 disabled:opacity-60"
                        >
                            <Save class="size-4" />
                            {{ editingNode ? 'Perbarui Data Cascading' : 'Simpan Data Cascading' }}
                        </button>
                    </form>

                    </section>

                    <section class="rounded-lg border bg-card p-4 shadow-sm">
                        <form class="grid gap-4" @submit.prevent="submitTargetTriwulan">
                            <div class="flex items-start gap-2">
                                <Target class="mt-0.5 size-5 text-blue-700" />
                                <div>
                                    <h3 class="text-base font-semibold text-slate-950">Target Triwulan Indikator</h3>
                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                        Isi target kinerja dan target anggaran TW I sampai TW IV tanpa scroll horizontal.
                                    </p>
                                </div>
                            </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium" for="target_triwulan_table">Jenis Indikator</label>
                            <select
                                id="target_triwulan_table"
                                v-model="targetTriwulanForm.related_table"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-blue-700"
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
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-blue-700"
                            >
                                <option value="">Pilih indikator</option>
                                <option v-for="option in selectedTargetTriwulanOptions" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="targetTriwulanForm.errors.related_id" />
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium" for="target_triwulan_periode">Periode Tahun</label>
                            <select
                                id="target_triwulan_periode"
                                v-model="targetTriwulanForm.periode_tahun_id"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-blue-700"
                            >
                                <option value="">Pilih periode</option>
                                <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="targetTriwulanForm.errors.periode_tahun_id" />
                        </div>

                        <div class="grid gap-3">
                            <article v-for="(row, index) in targetTriwulanForm.targets" :key="row.triwulan" class="rounded-md border bg-background p-3">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-slate-950">{{ targetTriwulanRows[index].label }}</h4>
                                    <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-800">Triwulan</span>
                                </div>

                                <div class="grid gap-3">
                                    <label class="grid gap-1.5">
                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Target Angka</span>
                                        <input
                                            v-model="row.target_angka"
                                            type="number"
                                            step="0.0001"
                                            class="min-h-11 w-full rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-blue-700"
                                        />
                                        <InputError :message="targetTriwulanError(index, 'target_angka')" />
                                    </label>

                                    <label class="grid gap-1.5">
                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Target Teks</span>
                                        <input
                                            v-model="row.target_text"
                                            class="min-h-11 w-full rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-blue-700"
                                            placeholder="Opsional"
                                        />
                                        <InputError :message="targetTriwulanError(index, 'target_text')" />
                                    </label>

                                    <label class="grid gap-1.5">
                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Target Anggaran</span>
                                        <input
                                            v-model="row.target_anggaran"
                                            type="number"
                                            step="0.01"
                                            class="min-h-11 w-full rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-blue-700"
                                        />
                                        <InputError :message="targetTriwulanError(index, 'target_anggaran')" />
                                    </label>
                                </div>
                            </article>
                        </div>

                        <button
                            type="submit"
                            :disabled="targetTriwulanForm.processing || selectedTargetTriwulanOptions.length === 0"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60"
                        >
                            <Save class="size-4" />
                            Simpan Target TW I-IV
                        </button>
                        </form>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
