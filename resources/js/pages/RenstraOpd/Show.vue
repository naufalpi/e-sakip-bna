<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronsRight,
    ClipboardList,
    Eye,
    FileText,
    Layers3,
    Link2,
    Network,
    Pencil,
    Plus,
    Save,
    Table2,
    Target,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

type Option = {
    id: number | string;
    label: string;
    description?: string | null;
    badge?: string | number | null;
    group?: string | null;
    disabled?: boolean;
    kode?: string | null;
    nama?: string | null;
    program_pemerintahan_id?: number | null;
    program_pemerintahan_ids?: number[];
    kegiatan_pemerintahan_id?: number | null;
    sub_kegiatan_pemerintahan_id?: number | null;
    bidang_urusan_id?: number | null;
    jenis_unit?: string | null;
};
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
    | 'indikator_kegiatan'
    | 'target_kegiatan'
    | 'sub_kegiatan'
    | 'indikator_sub_kegiatan'
    | 'target_sub_kegiatan';

type Target = {
    id: number;
    periode_tahun: { id: number; tahun: number; nama: string };
    target?: string | number | null;
    target_text?: string | null;
    pagu?: string | number | null;
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
};

type SubKegiatan = {
    id: number;
    sub_kegiatan_pemerintahan_id?: number | null;
    opd_unit_id?: number | null;
    kode?: string | null;
    nama: string;
    sasaran_sub_kegiatan?: string | null;
    pagu_indikatif?: string | number | null;
    urutan?: number | null;
    sub_kegiatan_pemerintahan?: { kode: string; nama: string; kegiatan_pemerintahan_id?: number | null } | null;
    opd_unit?: { kode: string; nama: string; jenis_unit?: string | null } | null;
    indikator: Indikator[];
};

type Kegiatan = {
    id: number;
    kegiatan_pemerintahan_id?: number | null;
    kode?: string | null;
    nama: string;
    sasaran_kegiatan?: string | null;
    pagu_indikatif?: string | number | null;
    urutan?: number | null;
    kegiatan_pemerintahan?: { kode: string; nama: string; program_pemerintahan_id?: number | null } | null;
    indikator: Indikator[];
    sub_kegiatan: SubKegiatan[];
};

type Program = {
    id: number;
    kode?: string | null;
    nama: string;
    sasaran_program?: string | null;
    pagu_indikatif?: string | number | null;
    program_rpjmd_id?: number | null;
    program_pemerintahan_id?: number | null;
    urutan?: number | null;
    linked: boolean;
    program_rpjmd?: { kode: string; nama: string; program_pemerintahan_id?: number | null; program_pemerintahan_ids?: number[] } | null;
    program_pemerintahan?: { kode: string; nama: string } | null;
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
type RpjmdContext = {
    visi: Array<{ id: number; visi: string; urutan?: number | null }>;
    misi: Array<{ id: number; kode?: string | null; misi: string; urutan?: number | null }>;
    program_groups: Array<{
        tujuan: {
            id: number;
            kode?: string | null;
            tujuan: string;
            misi: Array<{ id: number; kode?: string | null; misi: string }>;
        } | null;
        sasaran: { id: number; kode?: string | null; sasaran: string } | null;
        programs: Array<{ id: number; kode?: string | null; nama: string; rpjmd_kode?: string | null; rpjmd_nama?: string | null }>;
    }>;
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
    indikator_kegiatan: string;
    sub_kegiatan: string;
    indikator_sub_kegiatan: string;
    target_tahunan: string;
    pagu: string;
    status_keterhubungan: string;
};
type BulkSaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';
type BulkRow = {
    key: string;
    id: number | null;
    type: NodeType;
    level: string;
    parent_label: string;
    parent_id: number | string;
    kode: string;
    uraian: string;
    sasaran_level: string;
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
    program_pemerintahan_id: number | string;
    kegiatan_pemerintahan_id: number | string;
    sub_kegiatan_pemerintahan_id: number | string;
    opd_unit_id: number | string;
    saveState: BulkSaveState;
    savedAt: string;
    error: string;
    isNew: boolean;
};
type BulkAction = { type: NodeType; label: string; helper: string };
type BulkInputSection = {
    key: string;
    title: string;
    helper: string;
    emptyTitle: string;
    emptyDescription: string;
    primaryType: NodeType;
    actions: BulkAction[];
    rows: BulkRow[];
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
    rpjmdContext: RpjmdContext;
    nodeOptions: Record<string, Option[]>;
    rpjmdReferenceOptions: Record<string, Option[]>;
    masterReferenceOptions: Record<string, Option[]>;
    periodeOptions: Option[];
    satuanOptions: Option[];
    can: {
        manage: boolean;
        review: boolean;
        lock: boolean;
    };
    workflow: Workflow;
}>();

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
    { value: 'indikator_kegiatan', label: 'Indikator Kegiatan OPD' },
    { value: 'target_kegiatan', label: 'Target Indikator Kegiatan' },
    { value: 'sub_kegiatan', label: 'Sub Kegiatan OPD' },
    { value: 'indikator_sub_kegiatan', label: 'Indikator Sub Kegiatan' },
    { value: 'target_sub_kegiatan', label: 'Target Indikator Sub Kegiatan' },
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
    indikator_kegiatan: 'kegiatan',
    target_kegiatan: 'indikator_kegiatan',
    sub_kegiatan: 'kegiatan',
    indikator_sub_kegiatan: 'sub_kegiatan',
    target_sub_kegiatan: 'indikator_sub_kegiatan',
};

const parentLabels: Record<string, string> = {
    tujuan: 'Tujuan Induk',
    indikator_tujuan: 'Indikator Tujuan',
    sasaran: 'Sasaran Induk',
    indikator_sasaran: 'Indikator Sasaran',
    program: 'Program Induk',
    indikator_program: 'Indikator Program',
    kegiatan: 'Kegiatan Induk',
    indikator_kegiatan: 'Indikator Kegiatan',
    sub_kegiatan: 'Sub Kegiatan Induk',
    indikator_sub_kegiatan: 'Indikator Sub Kegiatan',
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
    program_pemerintahan_id: '' as number | string,
    kegiatan_pemerintahan_id: '' as number | string,
    sub_kegiatan_pemerintahan_id: '' as number | string,
    opd_unit_id: '' as number | string,
    kode: '',
    uraian: '',
    sasaran_level: '',
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

const selectedTypeLabel = computed(() => typeOptions.find((type) => type.value === form.type)?.label ?? 'Data Cascading');
const parentKey = computed(() => parentKeyByType[form.type]);
const parentOptions = computed(() => (parentKey.value ? (props.nodeOptions[parentKey.value] ?? []) : []));
const parentLabel = computed(() => (parentKey.value ? (parentLabels[parentKey.value] ?? 'Induk Data') : 'Induk Data'));
const needsParent = computed(() => Boolean(parentKey.value));
const programMasterOptions = computed(() => props.masterReferenceOptions.program_pemerintahan ?? []);
const opdUnitOptions = computed(() => props.masterReferenceOptions.opd_units ?? []);
const withEmptyOption = (options: Option[], label = 'Tidak dipilih'): Option[] => [{ id: '', label }, ...options];
const indicatorNodeTypes: NodeType[] = ['indikator_tujuan', 'indikator_sasaran', 'indikator_program', 'indikator_kegiatan', 'indikator_sub_kegiatan'];
const targetNodeTypes: NodeType[] = ['target_tujuan', 'target_sasaran', 'target_program', 'target_kegiatan', 'target_sub_kegiatan'];
const textNodeTypes: NodeType[] = ['tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan'];
const isIndicatorType = computed(() => indicatorNodeTypes.includes(form.type));
const isTargetType = computed(() => targetNodeTypes.includes(form.type));
const isTextNodeType = computed(() => textNodeTypes.includes(form.type));
const hasPaguIndikatif = computed(() => ['program', 'kegiatan', 'sub_kegiatan'].includes(form.type));
const usesMasterReference = computed(() => ['program', 'kegiatan', 'sub_kegiatan'].includes(form.type));
const hasSelectedMasterReference = computed(() =>
    Boolean(form.program_pemerintahan_id || form.kegiatan_pemerintahan_id || form.sub_kegiatan_pemerintahan_id),
);
const editingNode = ref<{ type: NodeType; id: number } | null>(null);
const viewMode = ref<'tree' | 'table' | 'bulk'>('bulk');
const previewMode = ref<'tree' | 'table'>('tree');
const isNodeModalOpen = ref(false);
const formPanel = ref<HTMLElement | null>(null);
const bulkRows = ref<BulkRow[]>([]);
const bulkSaveTimers = new Map<string, number>();
const bulkLastSavedAt = ref('');
const bulkDraftCounter = ref(0);

const typeOptionMap = computed(() => new Map(typeOptions.map((option) => [option.value, option])));
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
        helper: 'Kegiatan OPD sebagai turunan program, dilengkapi sasaran kegiatan.',
        primaryField: 'Nama kegiatan',
    },
    indikator_kegiatan: {
        stage: 'Level 4A',
        helper: 'Indikator untuk mengukur sasaran kegiatan OPD.',
        primaryField: 'Nama indikator',
    },
    target_kegiatan: {
        stage: 'Target',
        helper: 'Target lima tahunan untuk indikator kegiatan OPD.',
        primaryField: 'Nilai target',
    },
    sub_kegiatan: {
        stage: 'Level 5',
        helper: 'Sub kegiatan dengan sasaran sub kegiatan dan pagu indikatif.',
        primaryField: 'Nama sub kegiatan',
    },
    indikator_sub_kegiatan: {
        stage: 'Level 5A',
        helper: 'Indikator teknis untuk mengukur sasaran sub kegiatan.',
        primaryField: 'Nama indikator',
    },
    target_sub_kegiatan: {
        stage: 'Target',
        helper: 'Target lima tahunan untuk indikator sub kegiatan.',
        primaryField: 'Nilai target',
    },
};
const tujuanNodeTypes: NodeType[] = ['tujuan', 'indikator_tujuan', 'target_tujuan'];
const sasaranNodeTypes: NodeType[] = ['sasaran', 'indikator_sasaran', 'target_sasaran'];
const programNodeTypes: NodeType[] = ['program', 'indikator_program', 'target_program'];
const kegiatanNodeTypes: NodeType[] = ['kegiatan', 'indikator_kegiatan', 'target_kegiatan'];
const subKegiatanNodeTypes: NodeType[] = ['sub_kegiatan', 'indikator_sub_kegiatan', 'target_sub_kegiatan'];
const directionNodeTypes: NodeType[] = [...tujuanNodeTypes, ...sasaranNodeTypes];
const implementationNodeTypes: NodeType[] = [...programNodeTypes, ...kegiatanNodeTypes, ...subKegiatanNodeTypes];
const tujuanActions: BulkAction[] = [
    { type: 'tujuan', label: 'Tujuan OPD', helper: 'Arah utama OPD' },
    { type: 'indikator_tujuan', label: 'Indikator Tujuan', helper: 'Ukuran tujuan' },
    { type: 'target_tujuan', label: 'Target Tujuan', helper: 'Target 5 tahunan' },
];
const sasaranActions: BulkAction[] = [
    { type: 'sasaran', label: 'Sasaran OPD', helper: 'Turunan tujuan' },
    { type: 'indikator_sasaran', label: 'Indikator Sasaran', helper: 'Ukuran sasaran' },
    { type: 'target_sasaran', label: 'Target Sasaran', helper: 'Target 5 tahunan' },
];
const programActions: BulkAction[] = [
    { type: 'program', label: 'Program OPD', helper: 'Turunan sasaran' },
    { type: 'indikator_program', label: 'Indikator Program', helper: 'Ukuran program' },
    { type: 'target_program', label: 'Target Program', helper: 'Target 5 tahunan' },
];
const kegiatanActions: BulkAction[] = [
    { type: 'kegiatan', label: 'Kegiatan', helper: 'Turunan program' },
    { type: 'indikator_kegiatan', label: 'Indikator Kegiatan', helper: 'Ukuran kegiatan' },
    { type: 'target_kegiatan', label: 'Target Kegiatan', helper: 'Target 5 tahunan' },
];
const subKegiatanActions: BulkAction[] = [
    { type: 'sub_kegiatan', label: 'Sub Kegiatan', helper: 'Turunan kegiatan' },
    { type: 'indikator_sub_kegiatan', label: 'Indikator Sub', helper: 'Ukuran sub kegiatan' },
    { type: 'target_sub_kegiatan', label: 'Target Sub', helper: 'Target 5 tahunan' },
];
const directionActions: BulkAction[] = [...tujuanActions, ...sasaranActions];
const implementationActions: BulkAction[] = [...programActions, ...kegiatanActions, ...subKegiatanActions];
const selectedTypeMeta = computed(() => typeMeta[form.type]);
const typeSelectOptions = computed<Option[]>(() =>
    typeOptions.map((option) => ({
        id: option.value,
        label: option.label,
        description: typeMeta[option.value].primaryField,
        group: ['tujuan', 'indikator_tujuan', 'target_tujuan', 'sasaran', 'indikator_sasaran', 'target_sasaran'].includes(option.value)
            ? 'Arah Kinerja'
            : 'Program dan Kegiatan',
    })),
);
const parentSelectOptions = computed<Option[]>(() => parentOptions.value);
const tujuanDaerahSelectOptions = computed(() => withEmptyOption(props.rpjmdReferenceOptions.tujuan_daerah ?? [], 'Tidak dihubungkan'));
const indikatorTujuanDaerahSelectOptions = computed(() =>
    withEmptyOption(props.rpjmdReferenceOptions.indikator_tujuan_daerah ?? [], 'Tidak dihubungkan'),
);
const sasaranDaerahSelectOptions = computed(() => withEmptyOption(props.rpjmdReferenceOptions.sasaran_daerah ?? [], 'Tidak dihubungkan'));
const indikatorSasaranDaerahSelectOptions = computed(() =>
    withEmptyOption(props.rpjmdReferenceOptions.indikator_sasaran_daerah ?? [], 'Tidak dihubungkan'),
);
const indikatorProgramRpjmdSelectOptions = computed(() =>
    withEmptyOption(props.rpjmdReferenceOptions.indikator_program_rpjmd ?? [], 'Tidak dihubungkan'),
);
const formModeLabel = computed(() => (editingNode.value ? 'Edit data' : 'Tambah data'));
const contentRequirementText = computed(() => {
    if (isTextNodeType.value) {
        return `${selectedTypeMeta.value.primaryField} wajib diisi.`;
    }

    if (isIndicatorType.value) {
        return 'Isi nama indikator, satuan, tipe indikator, dan sumber data bila ada.';
    }

    return 'Pilih periode dan isi target angka atau target teks.';
});
const onTypeSelected = (value: number | string | null | undefined) => {
    if (typeof value !== 'string') {
        return;
    }

    if (!typeOptionMap.value.has(value as NodeType)) {
        return;
    }

    selectNodeType(value as NodeType);
};

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

const toNumberOrNull = (value: number | string | null | undefined): number | null => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
};

const optionById = (options: Option[], value: number | string | null | undefined): Option | null => {
    const id = toNumberOrNull(value);

    if (!id) {
        return null;
    }

    return options.find((option) => Number(option.id) === id) ?? null;
};

const findProgram = (id: number | string | null | undefined): Program | null => {
    const programId = toNumberOrNull(id);

    if (!programId) {
        return null;
    }

    for (const tujuan of props.renstra.tujuan) {
        for (const sasaran of tujuan.sasaran) {
            const program = sasaran.programs.find((item) => Number(item.id) === programId);

            if (program) {
                return program;
            }
        }
    }

    return null;
};

const findKegiatan = (id: number | string | null | undefined): Kegiatan | null => {
    const kegiatanId = toNumberOrNull(id);

    if (!kegiatanId) {
        return null;
    }

    for (const tujuan of props.renstra.tujuan) {
        for (const sasaran of tujuan.sasaran) {
            for (const program of sasaran.programs) {
                const kegiatan = program.kegiatan.find((item) => Number(item.id) === kegiatanId);

                if (kegiatan) {
                    return kegiatan;
                }
            }
        }
    }

    return null;
};

const selectedProgramRpjmd = computed(() => optionById(props.rpjmdReferenceOptions.program_rpjmd ?? [], form.program_rpjmd_id));
const selectedProgramMaster = computed(() => optionById(programMasterOptions.value, form.program_pemerintahan_id));
const selectedKegiatanMaster = computed(() => optionById(props.masterReferenceOptions.kegiatan_pemerintahan ?? [], form.kegiatan_pemerintahan_id));
const selectedSubKegiatanMaster = computed(() =>
    optionById(props.masterReferenceOptions.sub_kegiatan_pemerintahan ?? [], form.sub_kegiatan_pemerintahan_id),
);
const selectedParentProgram = computed(() => (form.type === 'kegiatan' ? findProgram(form.parent_id) : null));
const selectedParentKegiatan = computed(() => (form.type === 'sub_kegiatan' ? findKegiatan(form.parent_id) : null));
const selectedProgramMasterId = computed(
    () =>
        toNumberOrNull(form.program_pemerintahan_id) ??
        toNumberOrNull(selectedProgramRpjmd.value?.program_pemerintahan_id) ??
        toNumberOrNull(selectedParentProgram.value?.program_pemerintahan_id),
);
const selectedKegiatanMasterId = computed(() => toNumberOrNull(selectedParentKegiatan.value?.kegiatan_pemerintahan_id));
const kegiatanMasterOptions = computed(() => {
    const options = props.masterReferenceOptions.kegiatan_pemerintahan ?? [];
    const programId = selectedProgramMasterId.value;

    if (!programId) {
        return options;
    }

    return options.filter((option) => Number(option.program_pemerintahan_id) === programId);
});
const subKegiatanMasterOptions = computed(() => {
    const options = props.masterReferenceOptions.sub_kegiatan_pemerintahan ?? [];
    const kegiatanId = selectedKegiatanMasterId.value;

    if (!kegiatanId) {
        return options;
    }

    return options.filter((option) => Number(option.kegiatan_pemerintahan_id) === kegiatanId);
});
const programRpjmdSelectOptions = computed(() => withEmptyOption(props.rpjmdReferenceOptions.program_rpjmd ?? [], 'Tidak dihubungkan'));
const programMasterSelectOptions = computed(() => withEmptyOption(programMasterOptions.value, 'Tidak memakai master'));
const kegiatanMasterSelectOptions = computed(() => withEmptyOption(kegiatanMasterOptions.value, 'Tidak memakai master'));
const subKegiatanMasterSelectOptions = computed(() => withEmptyOption(subKegiatanMasterOptions.value, 'Tidak memakai master'));
const opdUnitSelectOptions = computed(() => withEmptyOption(opdUnitOptions.value, 'Tidak ditentukan'));
const satuanSelectOptions = computed(() => withEmptyOption(props.satuanOptions, 'Pilih satuan'));
const periodeSelectOptions = computed(() => withEmptyOption(props.periodeOptions, 'Pilih periode'));

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
        pagu_indikatif: 0,
    };

    props.renstra.tujuan.forEach((tujuan) => {
        if (tujuan.linked) {
            summary.tujuan_terhubung += 1;
        }
        summary.indikator += tujuan.indikator.length;
        summary.indikator_terhubung += tujuan.indikator.filter((indikator) => indikator.linked).length;
        summary.target_tahunan += tujuan.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
        summary.sasaran += tujuan.sasaran.length;

        tujuan.sasaran.forEach((sasaran) => {
            if (sasaran.linked) {
                summary.sasaran_terhubung += 1;
            }
            summary.indikator += sasaran.indikator.length;
            summary.indikator_terhubung += sasaran.indikator.filter((indikator) => indikator.linked).length;
            summary.target_tahunan += sasaran.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
            summary.program += sasaran.programs.length;

            sasaran.programs.forEach((program) => {
                if (program.linked) {
                    summary.program_terhubung += 1;
                }
                summary.pagu_indikatif += Number(program.pagu_indikatif ?? 0);
                summary.indikator += program.indikator.length;
                summary.indikator_terhubung += program.indikator.filter((indikator) => indikator.linked).length;
                summary.target_tahunan += program.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
                summary.kegiatan += program.kegiatan.length;

                program.kegiatan.forEach((kegiatan) => {
                    summary.pagu_indikatif += Number(kegiatan.pagu_indikatif ?? 0);
                    summary.indikator += kegiatan.indikator.length;
                    summary.target_tahunan += kegiatan.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
                    summary.sub_kegiatan += kegiatan.sub_kegiatan.length;
                    kegiatan.sub_kegiatan.forEach((subKegiatan) => {
                        summary.pagu_indikatif += Number(subKegiatan.pagu_indikatif ?? 0);
                        summary.indikator += subKegiatan.indikator.length;
                        summary.target_tahunan += subKegiatan.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
                    });
                });
            });
        });
    });

    return summary;
});

const totalCascadingNodes = computed(
    () =>
        renstraSummary.value.tujuan +
        renstraSummary.value.sasaran +
        renstraSummary.value.program +
        renstraSummary.value.kegiatan +
        renstraSummary.value.sub_kegiatan,
);
const coreCompleteness = computed(() => {
    const checks = [
        renstraSummary.value.tujuan > 0,
        renstraSummary.value.sasaran > 0,
        renstraSummary.value.program > 0,
        renstraSummary.value.kegiatan > 0,
        renstraSummary.value.sub_kegiatan > 0,
        renstraSummary.value.indikator > 0,
    ];
    const done = checks.filter(Boolean).length;

    return Math.round((done / checks.length) * 100);
});
const compactPreviewRows = computed(() => renstraCascadingRows.value.slice(0, 6));
const hasRpjmdContext = computed(() => props.rpjmdContext.visi.length > 0 || props.rpjmdContext.program_groups.length > 0);
const rpjmdContextProgramCount = computed(() => props.rpjmdContext.program_groups.reduce((total, group) => total + group.programs.length, 0));
const directionRows = computed(() => bulkRows.value.filter((row) => directionNodeTypes.includes(row.type)));
const implementationRows = computed(() => bulkRows.value.filter((row) => implementationNodeTypes.includes(row.type)));
const tujuanRows = computed(() => bulkRows.value.filter((row) => tujuanNodeTypes.includes(row.type)));
const sasaranRows = computed(() => bulkRows.value.filter((row) => sasaranNodeTypes.includes(row.type)));
const programRows = computed(() => bulkRows.value.filter((row) => programNodeTypes.includes(row.type)));
const kegiatanRows = computed(() => bulkRows.value.filter((row) => kegiatanNodeTypes.includes(row.type)));
const subKegiatanRows = computed(() => bulkRows.value.filter((row) => subKegiatanNodeTypes.includes(row.type)));
const bulkInputSections = computed<BulkInputSection[]>(() => [
    {
        key: 'tujuan',
        title: 'Tujuan OPD',
        helper: 'Tujuan, indikator tujuan, dan target 5 tahunan.',
        emptyTitle: 'Belum ada tujuan OPD',
        emptyDescription: 'Mulai dari tujuan OPD, lalu isi indikator dan targetnya.',
        primaryType: 'tujuan',
        actions: tujuanActions,
        rows: tujuanRows.value,
    },
    {
        key: 'sasaran',
        title: 'Sasaran OPD',
        helper: 'Sasaran strategis, indikator sasaran, dan target 5 tahunan.',
        emptyTitle: 'Belum ada sasaran OPD',
        emptyDescription: 'Tambahkan sasaran setelah tujuan OPD tersedia.',
        primaryType: 'sasaran',
        actions: sasaranActions,
        rows: sasaranRows.value,
    },
    {
        key: 'program',
        title: 'Program OPD',
        helper: 'Sasaran program, indikator program, dan target 5 tahunan.',
        emptyTitle: 'Belum ada program OPD',
        emptyDescription: 'Tambahkan program setelah sasaran OPD tersedia.',
        primaryType: 'program',
        actions: programActions,
        rows: programRows.value,
    },
    {
        key: 'kegiatan',
        title: 'Kegiatan OPD',
        helper: 'Sasaran kegiatan, indikator kegiatan, dan target 5 tahunan.',
        emptyTitle: 'Belum ada kegiatan OPD',
        emptyDescription: 'Tambahkan kegiatan setelah program OPD tersedia.',
        primaryType: 'kegiatan',
        actions: kegiatanActions,
        rows: kegiatanRows.value,
    },
    {
        key: 'sub-kegiatan',
        title: 'Sub Kegiatan OPD',
        helper: 'Sasaran sub kegiatan, indikator sub kegiatan, dan target 5 tahunan.',
        emptyTitle: 'Belum ada sub kegiatan OPD',
        emptyDescription: 'Tambahkan sub kegiatan setelah kegiatan OPD tersedia.',
        primaryType: 'sub_kegiatan',
        actions: subKegiatanActions,
        rows: subKegiatanRows.value,
    },
]);

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
                                indikator_kegiatan: indicatorSummary(kegiatan.indikator),
                                target_tahunan: joinItems([
                                    targetSummary(tujuan.indikator),
                                    targetSummary(sasaran.indikator),
                                    targetSummary(program.indikator),
                                    targetSummary(kegiatan.indikator),
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
                                indikator_kegiatan: indicatorSummary(kegiatan.indikator),
                                sub_kegiatan: nodeText(subKegiatan.kode, subKegiatan.nama),
                                indikator_sub_kegiatan: indicatorSummary(subKegiatan.indikator),
                                target_tahunan: joinItems([
                                    targetSummary(tujuan.indikator),
                                    targetSummary(sasaran.indikator),
                                    targetSummary(program.indikator),
                                    targetSummary(kegiatan.indikator),
                                    targetSummary(subKegiatan.indikator),
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
        indikator_kegiatan: '-',
        sub_kegiatan: '-',
        indikator_sub_kegiatan: '-',
        target_tahunan: '-',
        pagu: '-',
        status_keterhubungan: '-',
        ...values,
    };
}

function makeBulkRow(values: Partial<BulkRow> & { id?: number | null; type: NodeType; level: string }): BulkRow {
    return {
        key: values.key ?? `${values.type}-${values.id ?? `draft-${bulkDraftCounter.value}`}`,
        id: values.id ?? null,
        parent_label: '-',
        parent_id: '',
        kode: '',
        uraian: '',
        sasaran_level: '',
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
        program_pemerintahan_id: '',
        kegiatan_pemerintahan_id: '',
        sub_kegiatan_pemerintahan_id: '',
        opd_unit_id: '',
        saveState: 'idle',
        savedAt: '',
        error: '',
        isNew: false,
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
                        sasaran_level: valueText(program.sasaran_program),
                        pagu_indikatif: valueText(program.pagu_indikatif),
                        program_pemerintahan_id: valueText(program.program_pemerintahan_id),
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
                            sasaran_level: valueText(kegiatan.sasaran_kegiatan),
                            pagu_indikatif: valueText(kegiatan.pagu_indikatif),
                            kegiatan_pemerintahan_id: valueText(kegiatan.kegiatan_pemerintahan_id),
                            urutan: kegiatan.urutan ?? 1,
                        }),
                    );

                    kegiatan.indikator.forEach((indikator) => {
                        rows.push(
                            makeBulkRow({
                                id: indikator.id,
                                type: 'indikator_kegiatan',
                                level: 'Indikator Kegiatan',
                                parent_label: nodeText(kegiatan.kode, kegiatan.nama),
                                parent_id: kegiatan.id,
                                kode: valueText(indikator.kode),
                                indikator: valueText(indikator.indikator),
                                satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                                tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                                formula: valueText(indikator.formula),
                                sumber_data: valueText(indikator.sumber_data),
                                urutan: indikator.urutan ?? 1,
                            }),
                        );

                        (indikator.targets ?? []).forEach((target) => {
                            rows.push(
                                makeBulkRow({
                                    id: target.id,
                                    type: 'target_kegiatan',
                                    level: 'Target Kegiatan',
                                    parent_label: nodeText(indikator.kode, indikator.indikator),
                                    parent_id: indikator.id,
                                    periode_tahun_id: target.periode_tahun.id,
                                    target: valueText(target.target),
                                    target_text: valueText(target.target_text),
                                }),
                            );
                        });
                    });

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
                                sasaran_level: valueText(subKegiatan.sasaran_sub_kegiatan),
                                pagu_indikatif: valueText(subKegiatan.pagu_indikatif),
                                sub_kegiatan_pemerintahan_id: valueText(subKegiatan.sub_kegiatan_pemerintahan_id),
                                opd_unit_id: valueText(subKegiatan.opd_unit_id),
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

                            (indikator.targets ?? []).forEach((target) => {
                                rows.push(
                                    makeBulkRow({
                                        id: target.id,
                                        type: 'target_sub_kegiatan',
                                        level: 'Target Sub Kegiatan',
                                        parent_label: nodeText(indikator.kode, indikator.indikator),
                                        parent_id: indikator.id,
                                        periode_tahun_id: target.periode_tahun.id,
                                        target: valueText(target.target),
                                        target_text: valueText(target.target_text),
                                    }),
                                );
                            });
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
    form.program_pemerintahan_id = '';
    form.kegiatan_pemerintahan_id = '';
    form.sub_kegiatan_pemerintahan_id = '';
    form.opd_unit_id = '';
    form.kode = '';
    form.uraian = '';
    form.sasaran_level = '';
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

const closeNodeModal = () => {
    isNodeModalOpen.value = false;
    editingNode.value = null;
    clearNodeForm();
};

const selectNodeType = (type: NodeType, parentId: number | string = '') => {
    editingNode.value = null;
    isNodeModalOpen.value = true;
    form.type = type;

    nextTick(() => {
        clearNodeForm();
        form.parent_id = parentId;
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

    if (!key) {
        return [];
    }

    const savedOptions = props.nodeOptions[key] ?? [];
    const sessionOptions = bulkRows.value
        .filter((bulkRow) => bulkRow.type === key && bulkRow.id && !savedOptions.some((option) => Number(option.id) === Number(bulkRow.id)))
        .map((bulkRow) => ({
            id: Number(bulkRow.id),
            label: bulkRowDisplayLabel(bulkRow),
        }));

    return [...savedOptions, ...sessionOptions];
};

const bulkRowDisplayLabel = (row: BulkRow): string => {
    if (isBulkIndicatorRow(row)) {
        return nodeText(row.kode, row.indikator);
    }

    if (isBulkTargetRow(row)) {
        return `${row.level} ${row.target_text || row.target || ''}`.trim();
    }

    return nodeText(row.kode, row.uraian);
};

const bulkReferenceOptions = (row: BulkRow): Option[] => {
    if (!row.reference_field) {
        return [];
    }

    const referenceKey = row.reference_field.replace('_id', '');

    return props.rpjmdReferenceOptions[referenceKey] ?? [];
};

const bulkProgramRow = (id: number | string | null | undefined): BulkRow | null => {
    const programId = toNumberOrNull(id);

    if (!programId) {
        return null;
    }

    return bulkRows.value.find((row) => row.type === 'program' && Number(row.id) === programId) ?? null;
};

const bulkKegiatanRow = (id: number | string | null | undefined): BulkRow | null => {
    const kegiatanId = toNumberOrNull(id);

    if (!kegiatanId) {
        return null;
    }

    return bulkRows.value.find((row) => row.type === 'kegiatan' && Number(row.id) === kegiatanId) ?? null;
};

const programMasterIdForBulkRow = (row: BulkRow): number | null => {
    if (row.type === 'program') {
        const programRpjmd = optionById(props.rpjmdReferenceOptions.program_rpjmd ?? [], row.reference_value);

        return toNumberOrNull(row.program_pemerintahan_id) ?? toNumberOrNull(programRpjmd?.program_pemerintahan_id);
    }

    const parentBulk = bulkProgramRow(row.parent_id);
    const parentSaved = findProgram(row.parent_id);

    return toNumberOrNull(parentBulk?.program_pemerintahan_id) ?? toNumberOrNull(parentSaved?.program_pemerintahan_id);
};

const kegiatanMasterIdForBulkRow = (row: BulkRow): number | null => {
    const parentBulk = bulkKegiatanRow(row.parent_id);
    const parentSaved = findKegiatan(row.parent_id);

    return toNumberOrNull(parentBulk?.kegiatan_pemerintahan_id) ?? toNumberOrNull(parentSaved?.kegiatan_pemerintahan_id);
};

const bulkProgramMasterOptions = () => programMasterOptions.value;
const bulkKegiatanMasterOptions = (row: BulkRow) => {
    const programId = programMasterIdForBulkRow(row);
    const options = props.masterReferenceOptions.kegiatan_pemerintahan ?? [];

    return programId ? options.filter((option) => Number(option.program_pemerintahan_id) === programId) : options;
};
const bulkSubKegiatanMasterOptions = (row: BulkRow) => {
    const kegiatanId = kegiatanMasterIdForBulkRow(row);
    const options = props.masterReferenceOptions.sub_kegiatan_pemerintahan ?? [];

    return kegiatanId ? options.filter((option) => Number(option.kegiatan_pemerintahan_id) === kegiatanId) : options;
};
const bulkMasterOptions = (row: BulkRow): Option[] => {
    if (row.type === 'program') {
        return bulkProgramMasterOptions();
    }

    if (row.type === 'kegiatan') {
        return bulkKegiatanMasterOptions(row);
    }

    if (row.type === 'sub_kegiatan') {
        return bulkSubKegiatanMasterOptions(row);
    }

    return [];
};
const bulkMasterValue = (row: BulkRow) => {
    if (row.type === 'program') {
        return row.program_pemerintahan_id;
    }

    if (row.type === 'kegiatan') {
        return row.kegiatan_pemerintahan_id;
    }

    if (row.type === 'sub_kegiatan') {
        return row.sub_kegiatan_pemerintahan_id;
    }

    return '';
};
const applyBulkMasterReference = (row: BulkRow, option: Option | null) => {
    if (!option) {
        return;
    }

    row.kode = valueText(option.kode);
    row.uraian = valueText(option.nama ?? option.label);
};
const setBulkMasterValue = (row: BulkRow, value: number | string | null | undefined) => {
    if (row.type === 'program') {
        row.program_pemerintahan_id = valueText(value);
    } else if (row.type === 'kegiatan') {
        row.kegiatan_pemerintahan_id = valueText(value);
    } else if (row.type === 'sub_kegiatan') {
        row.sub_kegiatan_pemerintahan_id = valueText(value);
    }

    applyBulkMasterReference(row, optionById(bulkMasterOptions(row), value));
    scheduleBulkAutosave(row);
};
const onBulkReferenceChanged = (row: BulkRow) => {
    if (row.type === 'program') {
        const programRpjmd = optionById(props.rpjmdReferenceOptions.program_rpjmd ?? [], row.reference_value);

        if (programRpjmd?.program_pemerintahan_id && !row.program_pemerintahan_id) {
            row.program_pemerintahan_id = valueText(programRpjmd.program_pemerintahan_id);
            applyBulkMasterReference(row, optionById(bulkMasterOptions(row), row.program_pemerintahan_id));
        }
    }

    scheduleBulkAutosave(row);
};

const isBulkTextRow = (row: BulkRow) => textNodeTypes.includes(row.type);
const isBulkIndicatorRow = (row: BulkRow) => indicatorNodeTypes.includes(row.type);
const isBulkTargetRow = (row: BulkRow) => targetNodeTypes.includes(row.type);
const hasBulkSasaranLevel = (row: BulkRow) => ['program', 'kegiatan', 'sub_kegiatan'].includes(row.type);
const hasBulkPaguIndikatif = (row: BulkRow) => ['program', 'kegiatan', 'sub_kegiatan'].includes(row.type);
const hasBulkPaguTahunan = (row: BulkRow) => row.type === 'target_program';
const bulkSasaranLabel = (row: BulkRow) =>
    ({
        program: 'Sasaran program',
        kegiatan: 'Sasaran kegiatan',
        sub_kegiatan: 'Sasaran sub kegiatan',
    })[row.type] ?? '-';
const bulkTypeNeedsParent = (type: NodeType) => Boolean(parentKeyByType[type]);
const bulkRowReadyToSave = (row: BulkRow) => {
    if (!row.type) {
        return false;
    }

    if (bulkTypeNeedsParent(row.type) && !row.parent_id) {
        return false;
    }

    if (isBulkTextRow(row)) {
        return Boolean(String(row.uraian || '').trim());
    }

    if (isBulkIndicatorRow(row)) {
        return Boolean(String(row.indikator || '').trim());
    }

    return Boolean(row.periode_tahun_id && (String(row.target || '').trim() || String(row.target_text || '').trim()));
};

const bulkRequirementMessage = (row: BulkRow): string => {
    if (bulkTypeNeedsParent(row.type) && !row.parent_id) {
        return 'Pilih induk terlebih dahulu.';
    }

    if (isBulkTextRow(row) && !String(row.uraian || '').trim()) {
        return 'Isi uraian/nama terlebih dahulu.';
    }

    if (isBulkIndicatorRow(row) && !String(row.indikator || '').trim()) {
        return 'Isi indikator terlebih dahulu.';
    }

    if (isBulkTargetRow(row) && !row.periode_tahun_id) {
        return 'Pilih periode target terlebih dahulu.';
    }

    if (isBulkTargetRow(row) && !(String(row.target || '').trim() || String(row.target_text || '').trim())) {
        return 'Isi target angka atau target teks terlebih dahulu.';
    }

    return '';
};

const bulkStatusLabel = (row: BulkRow) =>
    ({
        idle: row.isNew ? 'Baru' : 'Siap',
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

const addBulkRow = (type: NodeType = 'tujuan') => {
    bulkDraftCounter.value += 1;
    const row = makeBulkRow({
        key: `draft-${bulkDraftCounter.value}`,
        id: null,
        type,
        level: typeOptionMap.value.get(type)?.label ?? 'Data Baru',
        isNew: true,
        saveState: 'dirty',
    });
    row.error = bulkRequirementMessage(row);

    bulkRows.value = [row, ...bulkRows.value];
};

const removeBulkDraft = (row: BulkRow) => {
    if (!row.isNew) {
        return;
    }

    window.clearTimeout(bulkSaveTimers.get(row.key));
    bulkRows.value = bulkRows.value.filter((bulkRow) => bulkRow.key !== row.key);
};

const onBulkTypeChanged = (row: BulkRow) => {
    row.level = typeOptionMap.value.get(row.type)?.label ?? 'Data Baru';
    row.parent_id = '';
    row.parent_label = '-';
    row.reference_field =
        (
            {
                tujuan: 'tujuan_daerah_id',
                indikator_tujuan: 'indikator_tujuan_daerah_id',
                sasaran: 'sasaran_daerah_id',
                indikator_sasaran: 'indikator_sasaran_daerah_id',
                program: 'program_rpjmd_id',
                indikator_program: 'indikator_program_rpjmd_id',
            } as Partial<Record<NodeType, string>>
        )[row.type] ?? '';
    row.reference_value = '';
    row.program_pemerintahan_id = '';
    row.kegiatan_pemerintahan_id = '';
    row.sub_kegiatan_pemerintahan_id = '';
    row.opd_unit_id = '';
    row.kode = '';
    row.uraian = '';
    row.sasaran_level = '';
    row.indikator = '';
    row.satuan_indikator_id = '';
    row.tipe_indikator = 'positif';
    row.formula = '';
    row.sumber_data = '';
    row.pagu_indikatif = '';
    row.periode_tahun_id = '';
    row.target = '';
    row.target_text = '';
    row.pagu = '';
    row.urutan = 1;
    scheduleBulkAutosave(row);
};

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const bulkRowPayload = (row: BulkRow) => {
    const payload: Record<string, unknown> = {
        type: row.type,
        parent_id: row.parent_id || null,
        kode: row.kode || null,
        uraian: row.uraian || null,
        sasaran_level: row.sasaran_level || null,
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
        program_pemerintahan_id: row.program_pemerintahan_id || null,
        kegiatan_pemerintahan_id: row.kegiatan_pemerintahan_id || null,
        sub_kegiatan_pemerintahan_id: row.sub_kegiatan_pemerintahan_id || null,
        opd_unit_id: row.opd_unit_id || null,
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
    if (row.isNew && !bulkRowReadyToSave(row)) {
        row.saveState = 'dirty';
        row.error = bulkRequirementMessage(row);
        return;
    }

    row.saveState = 'saving';
    row.error = '';

    try {
        const response = await fetch(
            row.isNew
                ? route('renstra-opd.nodes.autosave-store', props.renstra.id)
                : route('renstra-opd.nodes.autosave', [props.renstra.id, row.type, row.id]),
            {
                method: row.isNew ? 'POST' : 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(bulkRowPayload(row)),
            },
        );

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(firstErrorMessage(data.errors, data.message || 'Autosave gagal.'));
        }

        if (row.isNew) {
            row.id = Number(data.id);
            row.isNew = false;
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
    row.error = row.isNew ? bulkRequirementMessage(row) : '';
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
    isNodeModalOpen.value = true;
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
        form.sasaran_level = valueText(node.sasaran_program ?? node.sasaran_kegiatan ?? node.sasaran_sub_kegiatan);
        form.pagu_indikatif = valueText(node.pagu_indikatif);
        form.program_rpjmd_id = valueText(node.program_rpjmd_id);
        form.program_pemerintahan_id = valueText(node.program_pemerintahan_id);
        form.kegiatan_pemerintahan_id = valueText(node.kegiatan_pemerintahan_id);
        form.sub_kegiatan_pemerintahan_id = valueText(node.sub_kegiatan_pemerintahan_id);
        form.opd_unit_id = valueText(node.opd_unit_id);
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

    nextTick(() => formPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
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
    () => form.program_rpjmd_id,
    () => {
        if (form.type !== 'program') {
            return;
        }

        const masterId = selectedProgramRpjmd.value?.program_pemerintahan_id;

        if (masterId && !form.program_pemerintahan_id) {
            form.program_pemerintahan_id = masterId;
        }
    },
);

watch(
    () => form.program_pemerintahan_id,
    () => {
        if (form.type !== 'program') {
            return;
        }

        const reference = selectedProgramMaster.value;

        if (!reference) {
            return;
        }

        form.kode = valueText(reference.kode);
        form.uraian = valueText(reference.nama ?? reference.label);
    },
);

watch(
    () => form.kegiatan_pemerintahan_id,
    () => {
        if (form.type !== 'kegiatan') {
            return;
        }

        const reference = selectedKegiatanMaster.value;

        if (!reference) {
            return;
        }

        form.kode = valueText(reference.kode);
        form.uraian = valueText(reference.nama ?? reference.label);
    },
);

watch(
    () => form.sub_kegiatan_pemerintahan_id,
    () => {
        if (form.type !== 'sub_kegiatan') {
            return;
        }

        const reference = selectedSubKegiatanMaster.value;

        if (!reference) {
            return;
        }

        form.kode = valueText(reference.kode);
        form.uraian = valueText(reference.nama ?? reference.label);
    },
);

const submitNode = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeNodeModal();
        },
    };

    if (editingNode.value) {
        form.put(route('renstra-opd.nodes.update', [props.renstra.id, editingNode.value.type, editingNode.value.id]), options);
        return;
    }

    form.post(route('renstra-opd.nodes.store', props.renstra.id), options);
};

const destroyNode = async (type: NodeType, id: number, label: string) => {
    if (await confirmDelete(`Hapus ${label}? Data turunan juga dapat terpengaruh.`)) {
        router.delete(route('renstra-opd.nodes.destroy', [props.renstra.id, type, id]), {
            preserveScroll: true,
        });
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
</script>

<template>
    <Head title="Cascading Renstra OPD" />
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
                    {{ renstraSummary.target_tahunan }} target 5 tahunan
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

        <section class="overflow-hidden rounded-xl border border-blue-100 bg-card shadow-sm">
            <div class="flex flex-col gap-3 border-b bg-[linear-gradient(135deg,#f8fbff,#eaf4ff)] p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white">
                        <Network class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-950">Konteks RPJMD untuk OPD</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ rpjmdContextProgramCount }} program RPJMD relevan untuk {{ renstra.opd?.singkatan || renstra.opd?.nama || 'OPD ini' }}.
                        </p>
                    </div>
                </div>
                <span class="w-fit rounded-full border border-blue-100 bg-white px-3 py-1.5 text-xs font-semibold text-[#00336C]">
                    {{ renstra.rpjmd ? `${renstra.rpjmd.tahun_awal}-${renstra.rpjmd.tahun_akhir}` : 'Belum terhubung RPJMD' }}
                </span>
            </div>

            <div v-if="hasRpjmdContext" class="grid gap-4 p-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
                <aside class="space-y-3">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/35 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]">Visi Kabupaten</p>
                        <div class="mt-3 space-y-2">
                            <p v-for="visi in rpjmdContext.visi" :key="visi.id" class="text-sm font-semibold leading-6 text-slate-950">
                                {{ visi.visi }}
                            </p>
                            <p v-if="rpjmdContext.visi.length === 0" class="text-sm text-slate-500">Visi belum diisi.</p>
                        </div>
                    </div>
                    <div class="rounded-xl border bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Misi</p>
                        <ol class="mt-3 space-y-2">
                            <li v-for="misi in rpjmdContext.misi" :key="misi.id" class="flex gap-2 text-sm leading-5 text-slate-700">
                                <span class="font-semibold text-[#00336C]">{{ misi.kode || misi.urutan }}</span>
                                <span>{{ misi.misi }}</span>
                            </li>
                            <li v-if="rpjmdContext.misi.length === 0" class="text-sm text-slate-500">Misi belum diisi.</li>
                        </ol>
                    </div>
                </aside>

                <div class="space-y-3">
                    <article
                        v-for="group in rpjmdContext.program_groups"
                        :key="`rpjmd-context-${group.sasaran?.id || group.programs[0]?.id}`"
                        class="rounded-xl border bg-white p-4"
                    >
                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tujuan Kabupaten</p>
                                <p class="mt-1 text-sm font-semibold leading-6 text-slate-950">
                                    {{ group.tujuan ? nodeText(group.tujuan.kode, group.tujuan.tujuan) : '-' }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sasaran Terhubung</p>
                                <p class="mt-1 text-sm font-semibold leading-6 text-[#00336C]">
                                    {{ group.sasaran ? nodeText(group.sasaran.kode, group.sasaran.sasaran) : '-' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 rounded-lg border border-blue-100 bg-blue-50/30 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]">Program RPJMD untuk OPD ini</p>
                            <div class="mt-2 grid gap-2 md:grid-cols-2">
                                <div v-for="program in group.programs" :key="program.id" class="rounded-lg border bg-white px-3 py-2">
                                    <p class="text-sm font-semibold leading-5 text-slate-950">{{ nodeText(program.kode, program.nama) }}</p>
                                    <p v-if="program.rpjmd_kode && program.rpjmd_kode !== program.kode" class="mt-1 text-xs leading-5 text-slate-500">
                                        RPJMD: {{ nodeText(program.rpjmd_kode, program.rpjmd_nama) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                    <div v-if="rpjmdContext.program_groups.length === 0" class="rounded-xl border border-dashed p-6 text-sm text-slate-500">
                        Belum ada program RPJMD yang relevan dengan OPD ini.
                    </div>
                </div>
            </div>
            <div v-else class="p-6 text-sm text-slate-500">Renstra belum terhubung ke struktur RPJMD.</div>
        </section>

        <section class="overflow-hidden rounded-xl border border-blue-100 bg-card shadow-sm">
            <div class="flex flex-col gap-3 bg-[linear-gradient(135deg,#f8fbff,#eaf4ff)] p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white">
                        <Table2 class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-slate-950">Input RENSTRA OPD</h2>
                        <p class="mt-1 text-sm text-slate-600">Isi arah OPD terlebih dahulu, lalu lanjutkan program dan kegiatan.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-blue-100 bg-white px-3 py-1.5 text-xs font-semibold text-[#00336C]">
                        Kelengkapan {{ coreCompleteness }}%
                    </span>
                    <span class="rounded-full border border-blue-100 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">
                        {{ totalCascadingNodes }} node
                    </span>
                    <span
                        v-if="bulkLastSavedAt"
                        class="rounded-full border border-blue-100 bg-white px-3 py-1.5 text-xs font-semibold text-[#00336C]"
                    >
                        Autosave {{ bulkLastSavedAt }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t bg-white p-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex w-full overflow-x-auto rounded-lg border bg-slate-50 p-1 lg:w-auto">
                    <button
                        v-if="can.manage"
                        type="button"
                        class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-semibold transition"
                        :class="viewMode === 'bulk' ? 'bg-[#00336C] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-[#00336C]'"
                        @click="viewMode = 'bulk'"
                    >
                        <Table2 class="size-4" />
                        Input Data
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-semibold transition"
                        :class="viewMode === 'tree' ? 'bg-[#00336C] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-[#00336C]'"
                        @click="viewMode = 'tree'"
                    >
                        <Layers3 class="size-4" />
                        Preview Tree
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-semibold transition"
                        :class="viewMode === 'table' ? 'bg-[#00336C] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-[#00336C]'"
                        @click="viewMode = 'table'"
                    >
                        <Eye class="size-4" />
                        Preview Tabel
                    </button>
                </div>
            </div>
        </section>

        <div :class="viewMode === 'bulk' ? 'grid gap-4' : 'grid gap-4 xl:grid-cols-[minmax(0,1fr)_34rem]'">
            <section v-if="viewMode === 'bulk' && can.manage" class="grid gap-4">
                <section
                    v-for="section in bulkInputSections"
                    :key="section.key"
                    class="overflow-hidden rounded-xl border border-blue-100 bg-card shadow-sm"
                >
                    <div class="flex flex-col gap-3 border-b bg-white p-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#00336C]">
                                <Table2 class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-slate-950">{{ section.title }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ section.helper }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="action in section.actions"
                                :key="action.type"
                                type="button"
                                class="inline-flex min-h-9 items-center gap-2 rounded-md border border-blue-100 bg-white px-3 text-xs font-semibold text-slate-800 transition hover:border-[#00336C]/35 hover:text-[#00336C]"
                                @click="addBulkRow(action.type)"
                            >
                                <Plus class="size-3.5" />
                                {{ action.label }}
                            </button>
                        </div>
                    </div>

                    <div v-if="section.rows.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                        <Target class="mx-auto size-10 text-muted-foreground" />
                        <p class="mt-3 font-semibold text-foreground">{{ section.emptyTitle }}</p>
                        <p class="mt-1">{{ section.emptyDescription }}</p>
                        <button
                            type="button"
                            class="mt-4 inline-flex min-h-10 items-center gap-2 rounded-md bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#0a4485]"
                            @click="addBulkRow(section.primaryType)"
                        >
                            <Plus class="size-4" />
                            Tambah Data
                        </button>
                    </div>

                    <div v-else class="max-w-full overflow-x-auto">
                        <table class="min-w-[2860px] text-left text-sm">
                            <thead class="sticky top-0 z-10 border-b bg-muted/80 text-xs uppercase text-muted-foreground backdrop-blur">
                                <tr>
                                    <th class="sticky left-0 z-20 min-w-44 border-r bg-muted/95 px-3 py-3 shadow-[8px_0_16px_rgba(15,23,42,0.06)]">
                                        Status
                                    </th>
                                    <th class="min-w-56 px-3 py-3">Jenis Data</th>
                                    <th class="min-w-80 px-3 py-3">Induk</th>
                                    <th class="min-w-72 px-3 py-3">Referensi RPJMD</th>
                                    <th class="min-w-80 px-3 py-3">Master Resmi</th>
                                    <th class="min-w-32 px-3 py-3">Kode</th>
                                    <th class="min-w-80 px-3 py-3">Sasaran Level</th>
                                    <th class="min-w-80 px-3 py-3">Uraian/Nama</th>
                                    <th class="min-w-80 px-3 py-3">Indikator</th>
                                    <th class="min-w-48 px-3 py-3">Satuan</th>
                                    <th class="min-w-72 px-3 py-3">Formula</th>
                                    <th class="min-w-56 px-3 py-3">Sumber Data</th>
                                    <th class="min-w-44 px-3 py-3">Pagu Indikatif</th>
                                    <th class="min-w-48 px-3 py-3">Periode Target</th>
                                    <th class="min-w-40 px-3 py-3">Target Angka</th>
                                    <th class="min-w-56 px-3 py-3">Target Teks</th>
                                    <th class="min-w-44 px-3 py-3">Pagu Tahunan</th>
                                    <th class="min-w-28 px-3 py-3">Urutan</th>
                                    <th class="min-w-28 px-3 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in section.rows" :key="`${section.key}-${row.key}`" class="border-b align-top last:border-0 hover:bg-muted/30">
                                    <td class="sticky left-0 z-10 border-r bg-card px-3 py-3 shadow-[8px_0_16px_rgba(15,23,42,0.05)]">
                                        <div class="grid gap-2">
                                            <span
                                                class="inline-flex w-fit rounded-full px-2 py-1 text-xs font-semibold"
                                                :class="bulkStatusClass(row)"
                                            >
                                                {{ bulkStatusLabel(row) }}
                                            </span>
                                            <p v-if="row.error" class="max-w-40 text-xs leading-5 text-red-700">{{ row.error }}</p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-if="row.isNew"
                                            v-model="row.type"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs font-semibold outline-none focus:ring-2 focus:ring-[#00336C]"
                                            @change="onBulkTypeChanged(row)"
                                        >
                                            <option v-for="action in section.actions" :key="action.type" :value="action.type">
                                                {{ action.label }}
                                            </option>
                                        </select>
                                        <div v-else>
                                            <div class="font-semibold text-slate-900">{{ row.level }}</div>
                                            <div class="mt-1 text-xs text-muted-foreground">{{ typeMeta[row.type].primaryField }}</div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="mb-2 text-xs leading-5 text-slate-600">{{ row.parent_label }}</p>
                                        <select
                                            v-if="bulkParentOptions(row).length"
                                            v-model="row.parent_id"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C]"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="">Pilih induk</option>
                                            <option v-for="option in bulkParentOptions(row)" :key="option.id" :value="option.id">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <span v-else class="text-xs text-muted-foreground">Tidak perlu induk</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-if="bulkReferenceOptions(row).length"
                                            v-model="row.reference_value"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C]"
                                            @change="onBulkReferenceChanged(row)"
                                        >
                                            <option value="">Tidak dihubungkan</option>
                                            <option v-for="option in bulkReferenceOptions(row)" :key="option.id" :value="option.id">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <RpjmdRichSelect
                                            v-if="bulkMasterOptions(row).length"
                                            :model-value="bulkMasterValue(row)"
                                            :options="bulkMasterOptions(row)"
                                            placeholder="Pilih master"
                                            empty-text="Master belum tersedia"
                                            @update:model-value="setBulkMasterValue(row, $event)"
                                        />
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.kode"
                                            :disabled="isBulkTargetRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-model="row.sasaran_level"
                                            :disabled="!hasBulkSasaranLevel(row)"
                                            rows="3"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            :placeholder="bulkSasaranLabel(row)"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-if="isBulkTextRow(row)"
                                            v-model="row.uraian"
                                            rows="2"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-[#00336C]"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-model="row.indikator"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            rows="3"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-model="row.satuan_indikator_id"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="">Pilih satuan</option>
                                            <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-model="row.formula"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            rows="3"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.sumber_data"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.pagu_indikatif"
                                            :disabled="!hasBulkPaguIndikatif(row)"
                                            type="number"
                                            step="0.01"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <select
                                            v-model="row.periode_tahun_id"
                                            :disabled="!isBulkTargetRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
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
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.target_text"
                                            :disabled="!isBulkTargetRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.pagu"
                                            :disabled="!hasBulkPaguTahunan(row)"
                                            type="number"
                                            step="0.01"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.urutan"
                                            :disabled="isBulkTargetRow(row)"
                                            type="number"
                                            min="1"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <button
                                            v-if="row.isNew"
                                            type="button"
                                            class="inline-flex min-h-9 items-center gap-2 rounded-md border border-red-100 px-2 text-xs font-medium text-red-700 hover:bg-red-50"
                                            @click="removeBulkDraft(row)"
                                        >
                                            <Trash2 class="size-3.5" />
                                            Hapus
                                        </button>
                                        <span v-else class="text-xs text-muted-foreground">Autosave</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-blue-100 bg-card p-4 shadow-sm">
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <Eye class="size-5 text-[#00336C]" />
                            <div>
                                <h3 class="text-base font-semibold text-slate-950">Preview Ringkas</h3>
                                <p class="text-sm text-slate-600">Cuplikan hasil cascading dari tabel kerja.</p>
                            </div>
                        </div>
                        <div class="inline-flex w-fit rounded-lg border bg-white p-1">
                            <button
                                type="button"
                                class="inline-flex min-h-8 items-center gap-2 rounded-md px-3 text-xs font-semibold transition"
                                :class="previewMode === 'tree' ? 'bg-[#00336C] text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-[#00336C]'"
                                @click="previewMode = 'tree'"
                            >
                                <Layers3 class="size-3.5" />
                                Tree
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-8 items-center gap-2 rounded-md px-3 text-xs font-semibold transition"
                                :class="previewMode === 'table' ? 'bg-[#00336C] text-white' : 'text-slate-600 hover:bg-blue-50 hover:text-[#00336C]'"
                                @click="previewMode = 'table'"
                            >
                                <Table2 class="size-3.5" />
                                Tabel
                            </button>
                        </div>
                    </div>

                    <div v-if="previewMode === 'table'" class="overflow-x-auto rounded-lg border bg-white">
                        <table class="min-w-[1180px] text-left text-xs">
                            <thead class="border-b bg-blue-50 text-[11px] uppercase text-[#00336C]">
                                <tr>
                                    <th class="px-3 py-2">Tujuan</th>
                                    <th class="px-3 py-2">Sasaran</th>
                                    <th class="px-3 py-2">Program</th>
                                    <th class="px-3 py-2">Kegiatan</th>
                                    <th class="px-3 py-2">Indikator Kegiatan</th>
                                    <th class="px-3 py-2">Sub Kegiatan</th>
                                    <th class="px-3 py-2">Indikator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in compactPreviewRows" :key="`compact-${row.key}`" class="border-b align-top last:border-0">
                                    <td class="max-w-52 px-3 py-3">{{ row.tujuan }}</td>
                                    <td class="max-w-52 px-3 py-3">{{ row.sasaran }}</td>
                                    <td class="max-w-52 px-3 py-3 font-medium">{{ row.program }}</td>
                                    <td class="max-w-52 px-3 py-3">{{ row.kegiatan }}</td>
                                    <td class="max-w-60 px-3 py-3">{{ row.indikator_kegiatan }}</td>
                                    <td class="max-w-52 px-3 py-3">{{ row.sub_kegiatan }}</td>
                                    <td class="max-w-60 px-3 py-3">
                                        {{
                                            row.indikator_program !== '-'
                                                ? row.indikator_program
                                                : row.indikator_sub_kegiatan !== '-'
                                                  ? row.indikator_sub_kegiatan
                                                  : row.indikator_kegiatan
                                        }}
                                    </td>
                                </tr>
                                <tr v-if="compactPreviewRows.length === 0">
                                    <td colspan="7" class="px-3 py-8 text-center text-slate-500">Belum ada data untuk dipreview.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="grid gap-3 rounded-lg border bg-white p-4">
                        <article v-for="tujuan in renstra.tujuan.slice(0, 3)" :key="`preview-tujuan-${tujuan.id}`" class="rounded-lg border p-3">
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-[#00336C]"
                                >
                                    T
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-950">{{ tujuan.kode ? `${tujuan.kode} - ` : '' }}{{ tujuan.tujuan }}</p>
                                    <div class="mt-2 grid gap-2">
                                        <div
                                            v-for="sasaran in tujuan.sasaran.slice(0, 4)"
                                            :key="`preview-sasaran-${sasaran.id}`"
                                            class="rounded-md bg-slate-50 px-3 py-2"
                                        >
                                            <div class="flex items-center gap-2">
                                                <ChevronsRight class="size-4 text-[#00336C]" />
                                                <span class="text-sm font-medium text-slate-800">{{ sasaran.sasaran }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ sasaran.programs.length }} program, {{ sasaran.indikator.length }} indikator sasaran
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <div v-if="renstra.tujuan.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-slate-500">
                            Belum ada struktur cascading.
                        </div>
                    </div>
                </section>
            </section>

            <section v-else-if="viewMode === 'table'" class="rounded-lg border bg-card">
                <div class="flex items-center gap-2 border-b p-4">
                    <Table2 class="size-5 text-emerald-700" />
                    <div>
                        <h2 class="text-base font-semibold">Tabel Cascading Melebar</h2>
                        <p class="text-sm text-muted-foreground">Setiap baris membawa konteks tujuan sampai sub kegiatan dan status link ke RPJMD.</p>
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
                                <th class="px-4 py-3">Indikator Kegiatan</th>
                                <th class="px-4 py-3">Sub Kegiatan</th>
                                <th class="px-4 py-3">Indikator Sub Kegiatan</th>
                                <th class="px-4 py-3">Target Tahunan</th>
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
                                <td class="max-w-[280px] px-4 py-3">{{ row.indikator_kegiatan }}</td>
                                <td class="max-w-[260px] px-4 py-3">{{ row.sub_kegiatan }}</td>
                                <td class="max-w-[280px] px-4 py-3">{{ row.indikator_sub_kegiatan }}</td>
                                <td class="max-w-[240px] px-4 py-3">{{ row.target_tahunan }}</td>
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
                                <td colspan="17" class="px-4 py-10 text-center text-muted-foreground">Belum ada data cascading Renstra OPD.</td>
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
                        <p class="text-sm text-muted-foreground">Tujuan, sasaran, program, kegiatan, sub kegiatan, indikator, dan target tahunan.</p>
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
                                        <div class="mt-1 text-sm">{{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}</div>
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
                                                    @click="selectNodeType('target_sasaran', indikator.id)"
                                                >
                                                    <Plus class="size-3.5" />
                                                    Target
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
                                                <div v-if="program.sasaran_program" class="mt-1 text-xs leading-5 text-slate-600">
                                                    Sasaran program: {{ program.sasaran_program }}
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
                                            <div v-for="indikator in program.indikator" :key="indikator.id" class="rounded-md border bg-slate-50 p-3">
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
                                            </div>

                                            <div v-for="kegiatan in program.kegiatan" :key="kegiatan.id" class="rounded-md border bg-slate-50 p-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">Kegiatan OPD</div>
                                                        <div class="mt-1 text-sm font-medium">
                                                            {{ kegiatan.kode ? `${kegiatan.kode} - ` : '' }}{{ kegiatan.nama }}
                                                        </div>
                                                        <div v-if="kegiatan.sasaran_kegiatan" class="mt-1 text-xs leading-5 text-slate-600">
                                                            Sasaran kegiatan: {{ kegiatan.sasaran_kegiatan }}
                                                        </div>
                                                    </div>
                                                    <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                        <button
                                                            type="button"
                                                            class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                            @click="selectNodeType('indikator_kegiatan', kegiatan.id)"
                                                        >
                                                            <Plus class="size-3.5" />
                                                            Indikator
                                                        </button>
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
                                                <div v-if="kegiatan.indikator.length" class="mt-2 grid gap-2">
                                                    <div
                                                        v-for="indikator in kegiatan.indikator"
                                                        :key="indikator.id"
                                                        class="rounded-md bg-white px-3 py-2 text-sm"
                                                    >
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Indikator Kegiatan</div>
                                                                <div class="mt-1">
                                                                    {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
                                                                </div>
                                                                <div class="mt-1 text-xs text-muted-foreground">
                                                                    {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}
                                                                </div>
                                                            </div>
                                                            <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-blue-800 hover:bg-blue-50"
                                                                    @click="selectNodeType('target_kegiatan', indikator.id)"
                                                                >
                                                                    <Plus class="size-3.5" />
                                                                    Target
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 hover:bg-muted"
                                                                    title="Edit indikator kegiatan"
                                                                    @click="editNode('indikator_kegiatan', indikator.id, kegiatan.id, indikator)"
                                                                >
                                                                    <Pencil class="size-4" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                    title="Hapus indikator kegiatan"
                                                                    @click="destroyNode('indikator_kegiatan', indikator.id, 'indikator kegiatan')"
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
                                                                    @click="editNode('target_kegiatan', target.id, indikator.id, target)"
                                                                >
                                                                    Edit
                                                                </button>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-for="sub in kegiatan.sub_kegiatan" :key="sub.id" class="mt-3 rounded-md border bg-white p-3">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-xs font-semibold uppercase text-muted-foreground">Sub Kegiatan</div>
                                                            <div class="mt-1 text-sm font-medium">
                                                                {{ sub.kode ? `${sub.kode} - ` : '' }}{{ sub.nama }}
                                                            </div>
                                                            <div v-if="sub.sasaran_sub_kegiatan" class="mt-1 text-xs leading-5 text-slate-600">
                                                                Sasaran sub kegiatan: {{ sub.sasaran_sub_kegiatan }}
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
                                                                        {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
                                                                    </div>
                                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                                        {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}
                                                                    </div>
                                                                </div>
                                                                <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-blue-800 hover:bg-blue-50"
                                                                        @click="selectNodeType('target_sub_kegiatan', indikator.id)"
                                                                    >
                                                                        <Plus class="size-3.5" />
                                                                        Target
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        class="rounded-md p-1 hover:bg-muted"
                                                                        title="Edit indikator"
                                                                        @click="editNode('indikator_sub_kegiatan', indikator.id, sub.id, indikator)"
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
                                                                        @click="editNode('target_sub_kegiatan', target.id, indikator.id, target)"
                                                                    >
                                                                        Edit
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

            <aside
                v-if="can.manage && isNodeModalOpen"
                ref="formPanel"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/45 p-3 backdrop-blur-sm sm:p-6"
            >
                <div class="w-full max-w-4xl space-y-4">
                    <section v-if="isNodeModalOpen" class="overflow-hidden rounded-2xl border border-blue-100 bg-card shadow-2xl shadow-slate-950/15">
                        <div class="border-b bg-card p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#00336C]">
                                        <ClipboardList class="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ formModeLabel }}</p>
                                        <h2 class="text-base font-semibold text-foreground">Input Renstra OPD</h2>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full border bg-background px-3 py-1 text-xs font-semibold text-foreground">
                                        {{ selectedTypeMeta.stage }}
                                    </span>
                                    <button
                                        v-if="editingNode"
                                        type="button"
                                        class="inline-flex min-h-9 items-center rounded-md border bg-background px-3 text-xs font-medium transition hover:bg-muted"
                                        @click="resetNodeForm"
                                    >
                                        Batal edit
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-md border bg-background text-slate-600 transition hover:bg-slate-50 hover:text-slate-950"
                                        aria-label="Tutup form"
                                        @click="closeNodeModal"
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <form class="grid gap-4 p-4" @submit.prevent="submitNode">
                            <div class="rounded-xl border bg-muted/20 p-3">
                                <div class="grid gap-3 lg:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-sm font-semibold text-foreground" for="type">Jenis Data</label>
                                        <RpjmdRichSelect
                                            id="type"
                                            :model-value="form.type"
                                            :options="typeSelectOptions"
                                            placeholder="Pilih jenis data"
                                            empty-text="Jenis data tidak tersedia"
                                            @update:model-value="onTypeSelected"
                                        />
                                        <InputError :message="form.errors.type" />
                                    </div>

                                    <div v-if="needsParent" class="grid gap-2">
                                        <label class="text-sm font-semibold text-foreground" for="parent_id">
                                            {{ parentLabel }} <span class="text-red-600">*</span>
                                        </label>
                                        <RpjmdRichSelect
                                            id="parent_id"
                                            v-model="form.parent_id"
                                            :options="parentSelectOptions"
                                            :placeholder="`Pilih ${parentLabel.toLowerCase()}`"
                                            :empty-text="`${parentLabel} belum tersedia`"
                                        />
                                        <InputError :message="form.errors.parent_id" />
                                    </div>

                                    <div v-else class="grid gap-2">
                                        <span class="text-sm font-semibold text-foreground">Induk Data</span>
                                        <div class="flex min-h-12 items-center rounded-lg border bg-background px-3 text-sm text-muted-foreground">
                                            Data ini berada di level awal Renstra.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="
                                    ['tujuan', 'indikator_tujuan', 'sasaran', 'indikator_sasaran', 'program', 'indikator_program'].includes(form.type)
                                "
                                class="grid gap-3 rounded-xl border bg-background p-3"
                            >
                                <div class="flex items-center gap-2">
                                    <Link2 class="size-4 text-primary" />
                                    <h3 class="text-sm font-semibold text-foreground">Koneksi RPJMD</h3>
                                </div>

                                <div v-if="form.type === 'tujuan'" class="grid gap-2">
                                    <label class="text-sm font-medium" for="tujuan_daerah_id">Referensi Tujuan RPJMD</label>
                                    <RpjmdRichSelect
                                        id="tujuan_daerah_id"
                                        v-model="form.tujuan_daerah_id"
                                        :options="tujuanDaerahSelectOptions"
                                        placeholder="Tidak dihubungkan"
                                        empty-text="Tujuan RPJMD belum tersedia"
                                    />
                                </div>

                                <div v-if="form.type === 'indikator_tujuan'" class="grid gap-2">
                                    <label class="text-sm font-medium" for="indikator_tujuan_daerah_id">Referensi Indikator Tujuan RPJMD</label>
                                    <RpjmdRichSelect
                                        id="indikator_tujuan_daerah_id"
                                        v-model="form.indikator_tujuan_daerah_id"
                                        :options="indikatorTujuanDaerahSelectOptions"
                                        placeholder="Tidak dihubungkan"
                                        empty-text="Indikator tujuan RPJMD belum tersedia"
                                    />
                                </div>

                                <div v-if="form.type === 'sasaran'" class="grid gap-2">
                                    <label class="text-sm font-medium" for="sasaran_daerah_id">Referensi Sasaran RPJMD</label>
                                    <RpjmdRichSelect
                                        id="sasaran_daerah_id"
                                        v-model="form.sasaran_daerah_id"
                                        :options="sasaranDaerahSelectOptions"
                                        placeholder="Tidak dihubungkan"
                                        empty-text="Sasaran RPJMD belum tersedia"
                                    />
                                </div>

                                <div v-if="form.type === 'indikator_sasaran'" class="grid gap-2">
                                    <label class="text-sm font-medium" for="indikator_sasaran_daerah_id">Referensi Indikator Sasaran RPJMD</label>
                                    <RpjmdRichSelect
                                        id="indikator_sasaran_daerah_id"
                                        v-model="form.indikator_sasaran_daerah_id"
                                        :options="indikatorSasaranDaerahSelectOptions"
                                        placeholder="Tidak dihubungkan"
                                        empty-text="Indikator sasaran RPJMD belum tersedia"
                                    />
                                </div>

                                <div v-if="form.type === 'program'" class="grid gap-2">
                                    <label class="text-sm font-medium" for="program_rpjmd_id">Referensi Program RPJMD</label>
                                    <RpjmdRichSelect
                                        id="program_rpjmd_id"
                                        v-model="form.program_rpjmd_id"
                                        :options="programRpjmdSelectOptions"
                                        placeholder="Tidak dihubungkan"
                                        empty-text="Program RPJMD belum tersedia"
                                    />
                                </div>

                                <div v-if="form.type === 'indikator_program'" class="grid gap-2">
                                    <label class="text-sm font-medium" for="indikator_program_rpjmd_id">Referensi Indikator Program RPJMD</label>
                                    <RpjmdRichSelect
                                        id="indikator_program_rpjmd_id"
                                        v-model="form.indikator_program_rpjmd_id"
                                        :options="indikatorProgramRpjmdSelectOptions"
                                        placeholder="Tidak dihubungkan"
                                        empty-text="Indikator program RPJMD belum tersedia"
                                    />
                                </div>
                            </div>

                            <div v-if="form.type === 'program'" class="grid gap-2 rounded-xl border bg-muted/20 p-3">
                                <label class="text-sm font-medium" for="program_pemerintahan_id">Program Master</label>
                                <RpjmdRichSelect
                                    id="program_pemerintahan_id"
                                    v-model="form.program_pemerintahan_id"
                                    :options="programMasterSelectOptions"
                                    placeholder="Pilih program master"
                                    empty-text="Master program belum tersedia"
                                />
                                <p class="text-xs leading-5 text-muted-foreground">Kode dan nama program mengikuti master resmi yang dipilih.</p>
                                <InputError :message="form.errors.program_pemerintahan_id" />
                            </div>

                            <div v-if="form.type === 'kegiatan'" class="grid gap-2 rounded-xl border bg-muted/20 p-3">
                                <label class="text-sm font-medium" for="kegiatan_pemerintahan_id">Kegiatan Master</label>
                                <RpjmdRichSelect
                                    id="kegiatan_pemerintahan_id"
                                    v-model="form.kegiatan_pemerintahan_id"
                                    :options="kegiatanMasterSelectOptions"
                                    :disabled="needsParent && !form.parent_id"
                                    placeholder="Pilih kegiatan master"
                                    empty-text="Kegiatan master belum tersedia"
                                />
                                <p class="text-xs leading-5 text-muted-foreground">
                                    Pilihan difilter mengikuti program induk jika program sudah terhubung ke master.
                                </p>
                                <InputError :message="form.errors.kegiatan_pemerintahan_id" />
                            </div>

                            <div v-if="form.type === 'sub_kegiatan'" class="grid gap-3 rounded-xl border bg-muted/20 p-3">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium" for="sub_kegiatan_pemerintahan_id">Sub Kegiatan Master</label>
                                    <RpjmdRichSelect
                                        id="sub_kegiatan_pemerintahan_id"
                                        v-model="form.sub_kegiatan_pemerintahan_id"
                                        :options="subKegiatanMasterSelectOptions"
                                        :disabled="needsParent && !form.parent_id"
                                        placeholder="Pilih sub kegiatan master"
                                        empty-text="Sub kegiatan master belum tersedia"
                                    />
                                    <InputError :message="form.errors.sub_kegiatan_pemerintahan_id" />
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium" for="opd_unit_id">Unit Pelaksana</label>
                                    <RpjmdRichSelect
                                        id="opd_unit_id"
                                        v-model="form.opd_unit_id"
                                        :options="opdUnitSelectOptions"
                                        placeholder="Tidak ditentukan"
                                        empty-text="Unit OPD belum tersedia"
                                    />
                                    <InputError :message="form.errors.opd_unit_id" />
                                </div>
                            </div>

                            <div class="flex items-start gap-2 rounded-xl border bg-background p-3">
                                <FileText class="mt-0.5 size-4 text-primary" />
                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">Data Utama</h3>
                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ contentRequirementText }}</p>
                                </div>
                            </div>

                            <div v-if="!isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="kode">Kode</label>
                                <input
                                    id="kode"
                                    v-model="form.kode"
                                    :readonly="usesMasterReference && hasSelectedMasterReference"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none read-only:bg-muted read-only:text-muted-foreground focus:ring-2 focus:ring-primary"
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
                                    :readonly="usesMasterReference && hasSelectedMasterReference"
                                    class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none read-only:bg-muted read-only:text-muted-foreground focus:ring-2 focus:ring-primary"
                                />
                                <InputError :message="form.errors.uraian" />
                            </div>

                            <div v-if="['program', 'kegiatan', 'sub_kegiatan'].includes(form.type)" class="grid gap-2">
                                <label class="text-sm font-medium" for="sasaran_level">
                                    {{ form.type === 'program' ? 'Sasaran Program' : form.type === 'kegiatan' ? 'Sasaran Kegiatan' : 'Sasaran Sub Kegiatan' }}
                                </label>
                                <textarea
                                    id="sasaran_level"
                                    v-model="form.sasaran_level"
                                    rows="3"
                                    class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-primary"
                                />
                                <InputError :message="form.errors.sasaran_level" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="indikator">Indikator</label>
                                <textarea
                                    id="indikator"
                                    v-model="form.indikator"
                                    rows="4"
                                    class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-primary"
                                    placeholder="Tuliskan indikator yang terukur."
                                />
                                <InputError :message="form.errors.indikator" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="satuan_indikator_id">Satuan Indikator</label>
                                <RpjmdRichSelect
                                    id="satuan_indikator_id"
                                    v-model="form.satuan_indikator_id"
                                    :options="satuanSelectOptions"
                                    placeholder="Pilih satuan"
                                    empty-text="Satuan belum tersedia"
                                />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="tipe_indikator">Tipe Indikator</label>
                                <select
                                    id="tipe_indikator"
                                    v-model="form.tipe_indikator"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
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
                                    class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-primary"
                                    placeholder="Opsional, contoh: jumlah realisasi / target x 100%"
                                />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="sumber_data">Sumber Data</label>
                                <input
                                    id="sumber_data"
                                    v-model="form.sumber_data"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
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
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="periode_tahun_id">Periode Target</label>
                                <RpjmdRichSelect
                                    id="periode_tahun_id"
                                    v-model="form.periode_tahun_id"
                                    :options="periodeSelectOptions"
                                    placeholder="Pilih periode"
                                    empty-text="Periode belum tersedia"
                                />
                                <InputError :message="form.errors.periode_tahun_id" />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="target">Target Angka</label>
                                <input
                                    id="target"
                                    v-model="form.target"
                                    type="number"
                                    step="0.0001"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="target_text">Target Teks</label>
                                <input
                                    id="target_text"
                                    v-model="form.target_text"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
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
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>

                            <div v-if="!isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="urutan">Urutan</label>
                                <input
                                    id="urutan"
                                    v-model="form.urutan"
                                    type="number"
                                    min="1"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="sticky bottom-3 z-10 mt-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/10 transition hover:bg-primary/90 disabled:opacity-60"
                            >
                                <Save class="size-4" />
                                {{ editingNode ? 'Perbarui Data' : 'Simpan Data' }}
                            </button>
                        </form>
                    </section>

                </div>
            </aside>
        </div>
    </div>
</template>
