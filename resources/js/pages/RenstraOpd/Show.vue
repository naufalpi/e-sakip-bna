<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { confirmDelete, promptTextArea } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    ChevronsRight,
    ClipboardList,
    Eye,
    FileText,
    Folder,
    FolderOpen,
    GitBranch,
    Layers3,
    Link2,
    Network,
    Pencil,
    Plus,
    Save,
    Search,
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
    periode_tahun_id?: number | null;
    program_pemerintahan_id?: number | null;
    program_pemerintahan_ids?: number[];
    tahun?: number | string | null;
    kegiatan_pemerintahan_id?: number | null;
    sub_kegiatan_pemerintahan_id?: number | null;
    bidang_urusan_id?: number | null;
    jenis_unit?: string | null;
    sasaran_sub_kegiatan?: string | null;
    indikator_sub_kegiatan?: string | null;
    satuan_indikator_id?: number | null;
    satuan_label?: string | null;
    definisi_operasional?: string | null;
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
    | 'target_sub_kegiatan'
    | 'anggaran_sub_kegiatan';

type Target = {
    id: number;
    periode_tahun: { id: number; tahun: number; nama: string };
    target?: string | number | null;
    target_text?: string | null;
    pagu?: string | number | null;
};

type BidangUrusanRef = {
    id: number;
    kode?: string | null;
    nama: string;
    urusan_pemerintahan?: { id: number; kode?: string | null; nama: string } | null;
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
    definisi_operasional?: string | null;
    formula?: string | null;
    formulasi_pengukuran?: string | null;
    tipe_perhitungan?: string | null;
    opd_penanggung_jawab_id?: number | null;
    pd_penanggung_jawab?: string | null;
    opd_penanggung_jawab?: { id: number; kode: string; nama: string; singkatan?: string | null } | null;
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
    anggaran?: Array<{
        id: number;
        periode_tahun: { id: number; tahun: number; nama: string };
        anggaran?: string | number | null;
    }>;
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
    program_rpjmd?: {
        kode: string;
        nama: string;
        program_pemerintahan_id?: number | null;
        program_pemerintahan_ids?: number[];
        program_pemerintahan?: { id?: number | null; kode?: string | null; nama: string; bidang_urusan?: BidangUrusanRef | null } | null;
    } | null;
    program_pemerintahan?: { id?: number | null; kode?: string | null; nama: string; bidang_urusan?: BidangUrusanRef | null } | null;
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
    jenis_versi: 'murni' | 'perubahan';
    nomor_versi: number;
    parent_version_id?: number | null;
    is_active_version: boolean;
    alasan_perubahan?: string | null;
    dasar_perubahan?: string | null;
    tanggal_berlaku?: string | null;
    version_label: string;
    perlu_penyesuaian_rpjmd?: boolean;
    rpjmd_perubahan_terbaru?: { id: number; judul: string; version_label: string } | null;
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
type RenstraOutputRowLevel = 'tujuan' | 'sasaran' | 'bidang' | 'program' | 'kegiatan' | 'sub_kegiatan';
type RenstraOutputRow = {
    key: string;
    level: RenstraOutputRowLevel;
    label: string;
    indicator: string;
    baseline: string;
    values: Array<{ year: number | string; target: string; pagu: string }>;
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
    definisi_operasional: string;
    formula: string;
    formulasi_pengukuran: string;
    tipe_perhitungan: string;
    opd_penanggung_jawab_id: number | string;
    pd_penanggung_jawab: string;
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
type TargetBatchRow = {
    key: string;
    periode_tahun_id: number | string;
    year: number | string;
    label: string;
    target: string;
    pagu: string;
    existingRow: BulkRow | null;
    saveState: BulkSaveState;
    error: string;
};
type BulkInputSection = {
    key: string;
    title: string;
    helper: string;
    emptyTitle: string;
    emptyDescription: string;
    primaryType: NodeType;
    primaryLabel: string;
    indicatorType: NodeType;
    indicatorLabel: string;
    actions: BulkAction[];
    rows: BulkRow[];
};
type RenstraManagementSection = 'tujuan' | 'sasaran' | 'program' | 'kegiatan' | 'sub-kegiatan';
type BulkSectionGroup = {
    key: string;
    label: string;
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
        createRevision: boolean;
        cancelRevision?: boolean;
        withdraw?: boolean;
    };
    workflow: Workflow;
    activeSection?: RenstraManagementSection | null;
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
    { value: 'anggaran_sub_kegiatan', label: 'Pagu Indikatif Sub Kegiatan' },
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
    anggaran_sub_kegiatan: 'sub_kegiatan',
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
    anggaran_sub_kegiatan: 'Sub Kegiatan',
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
    definisi_operasional: '',
    formula: '',
    formulasi_pengukuran: '',
    tipe_perhitungan: 'non_kumulatif',
    opd_penanggung_jawab_id: '' as number | string,
    pd_penanggung_jawab: '',
    sumber_data: '',
    target: '',
    target_text: '',
    pagu: '',
    pagu_indikatif: '',
    urutan: 1,
});

const revisionModalOpen = ref(false);
const revisionForm = useForm({
    alasan_perubahan: '',
    dasar_perubahan: '',
    tanggal_berlaku: '',
});

const openRevisionModal = () => {
    revisionForm.reset();
    revisionForm.clearErrors();
    revisionModalOpen.value = true;
};

const submitRevision = () => {
    revisionForm.post(route('renstra-opd.revisions.store', props.renstra.id), {
        preserveScroll: true,
        onSuccess: () => {
            revisionModalOpen.value = false;
        },
    });
};

const cancelRevision = async () => {
    const reason = await promptTextArea({
        title: 'Batalkan Perubahan Renstra?',
        text: 'Renstra sebelumnya akan aktif kembali.',
        inputLabel: 'Alasan pembatalan',
        inputPlaceholder: 'Tuliskan alasan pembatalan perubahan.',
        confirmButtonText: 'Batalkan Perubahan',
        minLength: 5,
        destructive: true,
    });

    if (!reason) {
        return;
    }

    router.post(route('renstra-opd.revisions.cancel', props.renstra.id), { alasan_pembatalan: reason });
};

const selectedTypeLabel = computed(() => typeOptions.find((type) => type.value === form.type)?.label ?? 'Data Cascading');
const parentKey = computed(() => parentKeyByType[form.type]);
const parentOptions = computed(() => (parentKey.value ? (props.nodeOptions[parentKey.value] ?? []) : []));
const parentLabel = computed(() => (parentKey.value ? (parentLabels[parentKey.value] ?? 'Data terkait') : 'Data terkait'));
const needsParent = computed(() => Boolean(parentKey.value));
const programMasterOptions = computed(() => props.masterReferenceOptions.program_pemerintahan ?? []);
const opdUnitOptions = computed(() => props.masterReferenceOptions.opd_units ?? []);
const satuanOptions = computed(() => props.satuanOptions);
const withEmptyOption = (options: Option[], label = 'Tidak dipilih'): Option[] => [{ id: '', label }, ...options];
const uniqueOptions = (options: Option[], keyResolver: (option: Option) => string): Option[] => {
    const seen = new Set<string>();

    return options.filter((option) => {
        const key = keyResolver(option);

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
};
const indicatorNodeTypes: NodeType[] = ['indikator_tujuan', 'indikator_sasaran', 'indikator_program', 'indikator_kegiatan', 'indikator_sub_kegiatan'];
const targetNodeTypes: NodeType[] = ['target_tujuan', 'target_sasaran', 'target_program', 'target_kegiatan', 'target_sub_kegiatan'];
const budgetNodeTypes: NodeType[] = ['anggaran_sub_kegiatan'];
const textNodeTypes: NodeType[] = ['tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan'];
const orderableNodeTypes: NodeType[] = [...textNodeTypes, ...indicatorNodeTypes];
const isIndicatorType = computed(() => indicatorNodeTypes.includes(form.type));
const isTargetType = computed(() => targetNodeTypes.includes(form.type));
const isBudgetType = computed(() => budgetNodeTypes.includes(form.type));
const isTextNodeType = computed(() => textNodeTypes.includes(form.type));
const isOrderableNodeType = computed(() => orderableNodeTypes.includes(form.type));
const hasPaguIndikatif = computed(() => false);
const usesMasterReference = computed(() => ['program', 'kegiatan', 'sub_kegiatan'].includes(form.type));
const hasSelectedMasterReference = computed(() =>
    Boolean(form.program_pemerintahan_id || form.kegiatan_pemerintahan_id || form.sub_kegiatan_pemerintahan_id),
);
const editingNode = ref<{ type: NodeType; id: number } | null>(null);
const viewMode = ref<'table' | 'bulk'>(props.can.manage ? 'bulk' : 'table');
const isNodeModalOpen = ref(false);
const formPanel = ref<HTMLElement | null>(null);
const bulkRows = ref<BulkRow[]>([]);
const expandedBulkSections = ref<string[]>([]);
const expandedProgramSasaranIds = ref<number[]>([]);
const selectedProgramFocusId = ref<number | null>(null);
const programDetailRef = ref<HTMLElement | HTMLElement[] | null>(null);
const programFocusSearch = ref('');
const expandedKegiatanProgramIds = ref<number[]>([]);
const selectedKegiatanFocusId = ref<number | null>(null);
const kegiatanDetailRef = ref<HTMLElement | HTMLElement[] | null>(null);
const kegiatanFocusSearch = ref('');
const expandedSubKegiatanProgramIds = ref<number[]>([]);
const selectedSubKegiatanKegiatanId = ref<number | null>(null);
const subKegiatanDetailRef = ref<HTMLElement | HTMLElement[] | null>(null);
const subKegiatanFocusSearch = ref('');
const bulkSaveTimers = new Map<string, number>();
const bulkLastSavedAt = ref('');
const bulkDraftCounter = ref(0);
const bulkAutosaveEnabled = ref(false);
const targetBatchRows = ref<TargetBatchRow[]>([]);
const isTargetBatchSaving = ref(false);

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
        helper: 'Target kinerja dan target keuangan 5 tahunan untuk indikator program OPD.',
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
        helper: 'Target kinerja dan target keuangan 5 tahunan untuk indikator kegiatan OPD.',
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
        helper: 'Target kinerja 5 tahunan untuk indikator sub kegiatan.',
        primaryField: 'Nilai target',
    },
    anggaran_sub_kegiatan: {
        stage: 'Anggaran',
        helper: 'Pagu indikatif 5 tahunan pada level sub kegiatan.',
        primaryField: 'Nilai anggaran',
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
    { type: 'anggaran_sub_kegiatan', label: 'Pagu Indikatif', helper: 'Anggaran 5 tahunan' },
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
        return 'Isi indikator, satuan, cara ukur, PD penanggung jawab, dan sumber data bila ada.';
    }

    return ['target_program', 'target_kegiatan', 'target_sub_kegiatan'].includes(form.type)
        ? 'Pilih periode, isi target kinerja, lalu isi target keuangan bila ada.'
        : 'Pilih periode dan isi target angka atau target teks.';
});
const shownParentSelectorTypes: NodeType[] = ['program', 'kegiatan', 'sub_kegiatan'];
const showParentSelector = computed(() => shownParentSelectorTypes.includes(form.type));
const parentContextTitle = computed(() => {
    const titleMap: Partial<Record<NodeType, string>> = {
        indikator_tujuan: 'Tujuan OPD',
        sasaran: 'Tujuan OPD',
        indikator_sasaran: 'Sasaran OPD',
        indikator_program: 'Program OPD',
        indikator_kegiatan: 'Kegiatan OPD',
        target_tujuan: 'Indikator Tujuan',
        target_sasaran: 'Indikator Sasaran',
        target_program: 'Indikator Program',
        target_kegiatan: 'Indikator Kegiatan',
        target_sub_kegiatan: 'Indikator Sub Kegiatan',
        anggaran_sub_kegiatan: 'Sub Kegiatan',
    };

    return titleMap[form.type] ?? 'Konteks';
});
const parentContextRow = computed(() => {
    const key = parentKeyByType[form.type];
    const parentId = toNumberOrNull(form.parent_id);

    if (!key || !parentId) {
        return null;
    }

    return bulkRows.value.find((row) => row.type === key && Number(row.id) === parentId) ?? null;
});
const rpjmdContextTujuanTexts = computed(() =>
    [
        ...new Set(
            props.rpjmdContext.program_groups
                .map((group) => plainNodeText(group.tujuan?.tujuan))
                .filter((item) => item && item !== '-'),
        ),
    ].slice(0, 4),
);
const rpjmdContextSasaranTexts = computed(() =>
    [
        ...new Set(
            props.rpjmdContext.program_groups
                .map((group) => plainNodeText(group.sasaran?.sasaran))
                .filter((item) => item && item !== '-'),
        ),
    ].slice(0, 4),
);
const formContextDescription = computed(() => {
    if (form.type === 'tujuan') {
        return 'Tujuan dan sasaran kabupaten yang relevan dengan OPD ini otomatis menjadi konteks pengisian.';
    }

    if (parentContextRow.value) {
        return bulkRowPrimaryText(parentContextRow.value);
    }

    if (isTargetType.value) {
        return targetBatchIndicatorRow.value?.indikator || 'Indikator belum tersedia.';
    }

    if (isBudgetType.value) {
        return targetBatchSubKegiatan.value?.nama || 'Sub kegiatan belum tersedia.';
    }

    return selectedTypeMeta.value.helper;
});
const pdPenanggungJawabPlaceholder = computed(() =>
    form.type === 'indikator_tujuan'
        ? 'Contoh: Dinas Komunikasi dan Informatika'
        : 'Contoh: Kepala Bidang Penyelenggaraan E-Government',
);
const sumberDataPlaceholder = computed(() =>
    form.type === 'indikator_tujuan'
        ? 'Contoh: Bidang Penyelenggaraan E-Government, Bidang IKP'
        : 'Contoh: Bidang Penyelenggaraan E-Government',
);
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
const plainNodeText = (text: string | null | undefined) => trimText(`${text ?? ''}`) || '-';
const joinItems = (items: string[]) => items.filter((item) => item && item !== '-').join('; ') || '-';
const indicatorSummary = (items: Indikator[]) => joinItems(items.map((item) => plainNodeText(item.indikator)));
const targetSummary = (items: Indikator[]) =>
    joinItems(
        items.flatMap((item) =>
            (item.targets ?? []).map((target) => `${target.periode_tahun.tahun}: ${target.target_text || target.target || '-'}`),
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

const firstReferenceValue = (key: string): number | string => props.rpjmdReferenceOptions[key]?.[0]?.id ?? '';

const applyImplicitReferences = (type: NodeType) => {
    if (type === 'tujuan' && !form.tujuan_daerah_id) {
        form.tujuan_daerah_id = firstReferenceValue('tujuan_daerah');
    }

    if (type === 'sasaran' && !form.sasaran_daerah_id) {
        form.sasaran_daerah_id = firstReferenceValue('sasaran_daerah');
    }
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

const findSubKegiatan = (id: number | string | null | undefined): SubKegiatan | null => {
    const subKegiatanId = toNumberOrNull(id);

    if (!subKegiatanId) {
        return null;
    }

    for (const tujuan of props.renstra.tujuan) {
        for (const sasaran of tujuan.sasaran) {
            for (const program of sasaran.programs) {
                for (const kegiatan of program.kegiatan) {
                    const subKegiatan = kegiatan.sub_kegiatan.find((item) => Number(item.id) === subKegiatanId);

                    if (subKegiatan) {
                        return subKegiatan;
                    }
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
const selectedParentSubKegiatan = computed(() => (form.type === 'anggaran_sub_kegiatan' ? findSubKegiatan(form.parent_id) : null));
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

    const filteredOptions = programId ? options.filter((option) => Number(option.program_pemerintahan_id) === programId) : options;

    return uniqueOptions(filteredOptions, (option) => `${option.program_pemerintahan_id ?? ''}|${option.kode ?? option.label}`);
});
const subKegiatanMasterOptions = computed(() => {
    const options = props.masterReferenceOptions.sub_kegiatan_pemerintahan ?? [];
    const kegiatanId = selectedKegiatanMasterId.value;

    const filteredOptions = kegiatanId ? options.filter((option) => Number(option.kegiatan_pemerintahan_id) === kegiatanId) : options;

    return uniqueOptions(filteredOptions, (option) => `${option.kegiatan_pemerintahan_id ?? ''}|${option.kode ?? option.label}`);
});
const selectedParentSubKegiatanMasterIds = computed(() => {
    if (form.type !== 'sub_kegiatan') {
        return new Set<number>();
    }

    const editingSubKegiatanId = editingNode.value?.type === 'sub_kegiatan' ? editingNode.value.id : null;
    const usedIds = (selectedParentKegiatan.value?.sub_kegiatan ?? [])
        .filter((subKegiatan) => editingSubKegiatanId === null || Number(subKegiatan.id) !== editingSubKegiatanId)
        .map((subKegiatan) => toNumberOrNull(subKegiatan.sub_kegiatan_pemerintahan_id))
        .filter((id): id is number => id !== null);

    return new Set(usedIds);
});
const programRpjmdSelectOptions = computed(() => withEmptyOption(props.rpjmdReferenceOptions.program_rpjmd ?? [], 'Tidak dihubungkan'));
const programMasterSelectOptions = computed(() => withEmptyOption(programMasterOptions.value, 'Tidak memakai master'));
const kegiatanMasterSelectOptions = computed(() => kegiatanMasterOptions.value);
const subKegiatanMasterSelectOptions = computed(() =>
    subKegiatanMasterOptions.value.map((option) => {
        const optionId = toNumberOrNull(option.id);

        if (!optionId || !selectedParentSubKegiatanMasterIds.value.has(optionId)) {
            return option;
        }

        const description = option.description?.includes('Sudah dipilih di kegiatan ini')
            ? option.description
            : [option.description, 'Sudah dipilih di kegiatan ini'].filter(Boolean).join(' - ');

        return {
            ...option,
            badge: 'Sudah dipilih',
            description,
            disabled: true,
        };
    }),
);
const opdUnitSelectOptions = computed(() => withEmptyOption(opdUnitOptions.value, 'Tidak ditentukan'));
const satuanSelectOptions = computed(() => withEmptyOption(props.satuanOptions, 'Pilih satuan'));
const isRequiredMasterMissing = computed(
    () =>
        (form.type === 'kegiatan' && !form.kegiatan_pemerintahan_id) ||
        (form.type === 'sub_kegiatan' && !form.sub_kegiatan_pemerintahan_id),
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
        target_keuangan: 0,
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
                summary.indikator += program.indikator.length;
                summary.indikator_terhubung += program.indikator.filter((indikator) => indikator.linked).length;
                summary.target_tahunan += program.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
                summary.kegiatan += program.kegiatan.length;

                program.kegiatan.forEach((kegiatan) => {
                    summary.indikator += kegiatan.indikator.length;
                    summary.target_tahunan += kegiatan.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
                    summary.sub_kegiatan += kegiatan.sub_kegiatan.length;
                    kegiatan.sub_kegiatan.forEach((subKegiatan) => {
                        summary.indikator += subKegiatan.indikator.length;
                        summary.target_tahunan += subKegiatan.indikator.reduce((total, indikator) => total + (indikator.targets?.length ?? 0), 0);
                        summary.target_keuangan += Number(subKegiatan.pagu_indikatif ?? 0);
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
const mappedPeriodOptions = computed(() =>
    props.periodeOptions
        .map((option) => {
            const year = Number(String(option.label).match(/\b(20\d{2})\b/)?.[1]);

            return {
                id: option.id,
                label: option.label,
                year: Number.isFinite(year) ? String(year) : String(option.label),
                yearNumber: Number.isFinite(year) ? year : null,
            };
        })
        .filter((option) => Boolean(option.yearNumber))
        .sort((a, b) => Number(a.yearNumber) - Number(b.yearNumber)),
);
const baselineYear = computed(() => props.renstra.tahun_awal - 1);
const baselinePeriod = computed(() => mappedPeriodOptions.value.find((option) => option.yearNumber === baselineYear.value) ?? null);
const periodColumns = computed(() =>
    mappedPeriodOptions.value.filter(
        (option) =>
            Number(option.yearNumber) >= props.renstra.tahun_awal && Number(option.yearNumber) <= props.renstra.tahun_akhir + 1,
    ),
);
const targetInputPeriods = computed(() =>
    baselinePeriod.value ? [baselinePeriod.value, ...periodColumns.value] : periodColumns.value,
);
const hasRpjmdContext = computed(() => props.rpjmdContext.visi.length > 0 || props.rpjmdContext.program_groups.length > 0);
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
        primaryLabel: 'Tujuan OPD',
        indicatorType: 'indikator_tujuan',
        indicatorLabel: 'Indikator Tujuan',
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
        primaryLabel: 'Sasaran OPD',
        indicatorType: 'indikator_sasaran',
        indicatorLabel: 'Indikator Sasaran',
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
        primaryLabel: 'Program OPD',
        indicatorType: 'indikator_program',
        indicatorLabel: 'Indikator Program',
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
        primaryLabel: 'Kegiatan OPD',
        indicatorType: 'indikator_kegiatan',
        indicatorLabel: 'Indikator Kegiatan',
        actions: kegiatanActions,
        rows: kegiatanRows.value,
    },
    {
        key: 'sub-kegiatan',
        title: 'Sub Kegiatan OPD',
        helper: 'Sub kegiatan, indikator, target, dan pagu.',
        emptyTitle: 'Belum ada sub kegiatan OPD',
        emptyDescription: 'Tambahkan sub kegiatan setelah kegiatan OPD tersedia.',
        primaryType: 'sub_kegiatan',
        primaryLabel: 'Sub Kegiatan OPD',
        indicatorType: 'indikator_sub_kegiatan',
        indicatorLabel: 'Indikator Sub Kegiatan',
        actions: subKegiatanActions,
        rows: subKegiatanRows.value,
    },
]);

const renstraManagementSectionKeys: RenstraManagementSection[] = ['tujuan', 'sasaran', 'program', 'kegiatan', 'sub-kegiatan'];
const activeManagementSection = computed<RenstraManagementSection | null>(() =>
    props.activeSection && renstraManagementSectionKeys.includes(props.activeSection) ? props.activeSection : null,
);
const isDedicatedManagementPage = computed(() => activeManagementSection.value !== null);
const activeBulkInputSections = computed(() =>
    activeManagementSection.value
        ? bulkInputSections.value.filter((section) => section.key === activeManagementSection.value)
        : [],
);
const activeManagementSectionTitle = computed(
    () => activeBulkInputSections.value[0]?.title ?? 'RENSTRA OPD',
);

watch(
    activeManagementSection,
    (section) => {
        if (section && !expandedBulkSections.value.includes(section)) {
            expandedBulkSections.value = [section];
        }
    },
    { immediate: true },
);

const isBulkSectionExpanded = (sectionKey: string) => expandedBulkSections.value.includes(sectionKey);

const toggleBulkSection = (sectionKey: string) => {
    expandedBulkSections.value = isBulkSectionExpanded(sectionKey)
        ? expandedBulkSections.value.filter((key) => key !== sectionKey)
        : [...expandedBulkSections.value, sectionKey];
};

const bulkSectionStats = (section: BulkInputSection) => {
    const primaryCount = section.rows.filter((row) => row.type === section.primaryType && !isBulkTargetRow(row)).length;
    const indicatorCount = section.rows.filter((row) => row.type === section.indicatorType).length;
    const targetCount = section.rows.filter((row) => isBulkTargetRow(row)).length;
    const dirtyCount = section.rows.filter((row) => ['dirty', 'saving', 'error'].includes(row.saveState)).length;
    const savedCount = section.rows.filter((row) => row.id).length;

    return {
        primaryCount,
        indicatorCount,
        targetCount,
        dirtyCount,
        savedCount,
    };
};

const bulkSectionStatus = (section: BulkInputSection): { label: string; className: string } => {
    const stats = bulkSectionStats(section);

    if (stats.dirtyCount > 0) {
        return {
            label: `${stats.dirtyCount} perlu disimpan`,
            className: 'border-amber-200 bg-amber-50 text-amber-800',
        };
    }

    if (stats.primaryCount === 0) {
        return {
            label: 'Belum diisi',
            className: 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    if (section.key !== 'sub-kegiatan' && stats.indicatorCount === 0) {
        return {
            label: 'Perlu indikator',
            className: 'border-blue-100 bg-blue-50 text-[#00336C]',
        };
    }

    return {
        label: 'Terisi',
        className: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    };
};

const bulkSectionSummary = (section: BulkInputSection): string => {
    const stats = bulkSectionStats(section);

    if (section.key === 'sub-kegiatan') {
        return `${stats.primaryCount} sub kegiatan, ${stats.indicatorCount} indikator`;
    }

    return `${stats.primaryCount} data utama, ${stats.indicatorCount} indikator, ${stats.targetCount} target`;
};

const renstraCascadingRows = computed<RenstraCascadingRow[]>(() => {
    const rows: RenstraCascadingRow[] = [];

    props.renstra.tujuan.forEach((tujuan) => {
        if (tujuan.sasaran.length === 0) {
            rows.push(
                emptyRenstraRow(`tujuan-${tujuan.id}`, {
                    tujuan: plainNodeText(tujuan.tujuan),
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
                        tujuan: plainNodeText(tujuan.tujuan),
                        tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                        indikator_tujuan: indicatorSummary(tujuan.indikator),
                        sasaran: plainNodeText(sasaran.sasaran),
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
                            tujuan: plainNodeText(tujuan.tujuan),
                            tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                            indikator_tujuan: indicatorSummary(tujuan.indikator),
                            sasaran: plainNodeText(sasaran.sasaran),
                            sasaran_rpjmd: sasaran.linked ? 'Terhubung' : 'Belum terhubung',
                            indikator_sasaran: indicatorSummary(sasaran.indikator),
                            program: plainNodeText(program.nama),
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
                                tujuan: plainNodeText(tujuan.tujuan),
                                tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_tujuan: indicatorSummary(tujuan.indikator),
                                sasaran: plainNodeText(sasaran.sasaran),
                                sasaran_rpjmd: sasaran.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_sasaran: indicatorSummary(sasaran.indikator),
                                program: plainNodeText(program.nama),
                                program_rpjmd: program.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_program: indicatorSummary(program.indikator),
                                kegiatan: plainNodeText(kegiatan.nama),
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
                                tujuan: plainNodeText(tujuan.tujuan),
                                tujuan_rpjmd: tujuan.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_tujuan: indicatorSummary(tujuan.indikator),
                                sasaran: plainNodeText(sasaran.sasaran),
                                sasaran_rpjmd: sasaran.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_sasaran: indicatorSummary(sasaran.indikator),
                                program: plainNodeText(program.nama),
                                program_rpjmd: program.linked ? 'Terhubung' : 'Belum terhubung',
                                indikator_program: indicatorSummary(program.indikator),
                                kegiatan: plainNodeText(kegiatan.nama),
                                indikator_kegiatan: indicatorSummary(kegiatan.indikator),
                                sub_kegiatan: plainNodeText(subKegiatan.nama),
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

const renstraOutputRows = computed<RenstraOutputRow[]>(() => {
    const rows: RenstraOutputRow[] = [];

    props.renstra.tujuan.forEach((tujuan) => {
        appendRenstraOutputRows(rows, {
            level: 'tujuan',
            keyPrefix: `tujuan-${tujuan.id}`,
            label: `Tujuan OPD: ${plainNodeText(tujuan.tujuan)}`,
            indicators: tujuan.indikator,
            budgetResolver: () => 0,
        });

        tujuan.sasaran.forEach((sasaran) => {
            appendRenstraOutputRows(rows, {
                level: 'sasaran',
                keyPrefix: `sasaran-${sasaran.id}`,
                label: `Sasaran OPD: ${plainNodeText(sasaran.sasaran)}`,
                indicators: sasaran.indikator,
                budgetResolver: () => 0,
            });

            const groups = new Map<string, { label: string; programs: Program[] }>();

            sasaran.programs.forEach((program) => {
                const bidang = programBidangUrusan(program);
                const key = bidang?.id ? `sasaran-${sasaran.id}-bidang-${bidang.id}` : `sasaran-${sasaran.id}-bidang-none`;
                const label = bidang?.nama || 'Bidang urusan belum dipilih';

                if (!groups.has(key)) {
                    groups.set(key, { label, programs: [] });
                }

                groups.get(key)?.programs.push(program);
            });

            groups.forEach((group, key) => {
                rows.push({
                    key,
                    level: 'bidang',
                    label: group.label,
                    indicator: '',
                    baseline: '',
                    values: blankRenstraOutputValues(),
                });

                group.programs.forEach((program) => {
                    appendRenstraOutputRows(rows, {
                        level: 'program',
                        keyPrefix: `program-${program.id}`,
                        label: renstraNodeName(program.nama),
                        indicators: program.indikator,
                        budgetResolver: (year) => programBudgetByYear(program, year),
                    });

                    program.kegiatan.forEach((kegiatan) => {
                        appendRenstraOutputRows(rows, {
                            level: 'kegiatan',
                            keyPrefix: `kegiatan-${kegiatan.id}`,
                            label: renstraNodeName(kegiatan.nama),
                            indicators: kegiatan.indikator,
                            budgetResolver: (year) => kegiatanBudgetByYear(kegiatan, year),
                        });

                        kegiatan.sub_kegiatan.forEach((subKegiatan) => {
                            appendRenstraOutputRows(rows, {
                                level: 'sub_kegiatan',
                                keyPrefix: `sub-${subKegiatan.id}`,
                                label: renstraNodeName(subKegiatan.nama),
                                indicators: subKegiatan.indikator,
                                budgetResolver: (year) => subKegiatanBudgetByYear(subKegiatan, year),
                            });
                        });
                    });
                });
            });
        });
    });

    return rows;
});

function appendRenstraOutputRows(
    rows: RenstraOutputRow[],
    options: {
        level: RenstraOutputRowLevel;
        keyPrefix: string;
        label: string;
        indicators: Indikator[];
        budgetResolver: (year: number) => number;
    },
) {
    if (options.indicators.length === 0) {
        rows.push({
            key: options.keyPrefix,
            level: options.level,
            label: options.label,
            indicator: '',
            baseline: '',
            values: renstraOutputValues(null, options.budgetResolver),
        });

        return;
    }

    options.indicators.forEach((indicator, index) => {
        rows.push({
            key: `${options.keyPrefix}-indikator-${indicator.id}`,
            level: options.level,
            label: index === 0 ? options.label : '',
            indicator: plainNodeText(indicator.indikator),
            baseline: targetForIndicatorYear(indicator, baselineYear.value),
            values: renstraOutputValues(indicator, options.budgetResolver, index === 0),
        });
    });
}

const programBidangUrusan = (program: Program): BidangUrusanRef | null =>
    program.program_pemerintahan?.bidang_urusan ?? program.program_rpjmd?.program_pemerintahan?.bidang_urusan ?? null;

const renstraNodeLabel = (kode?: string | null, nama?: string | null) => joinItems([kode, nama]) || '-';
const renstraNodeName = (nama?: string | null) => nama?.trim() || '-';

const blankRenstraOutputValues = () =>
    periodColumns.value.map((period) => ({
        year: period.year,
        target: '',
        pagu: '',
    }));

const renstraOutputValues = (indicator: Indikator | null, budgetResolver: (year: number) => number, showBudget = true) =>
    periodColumns.value.map((period) => {
        const year = Number(period.yearNumber ?? period.year);

        return {
            year: period.year,
            target: indicator ? targetForIndicatorYear(indicator, year) : '',
            pagu: showBudget ? formatPreviewPagu(budgetResolver(year)) : '',
        };
    });

const targetForIndicatorYear = (indicator: Indikator, year: number) => {
    const target = (indicator.targets ?? []).find((item) => Number(item.periode_tahun?.tahun) === year);

    return normalizedTargetText(target?.target_text || target?.target) || '-';
};

const subKegiatanBudgetByYear = (subKegiatan: SubKegiatan, year: number) => {
    const budget = (subKegiatan.anggaran ?? []).find((item) => Number(item.periode_tahun?.tahun) === year);

    return numericPreviewValue(budget?.anggaran);
};

const kegiatanBudgetByYear = (kegiatan: Kegiatan, year: number) =>
    kegiatan.sub_kegiatan.reduce((total, subKegiatan) => total + subKegiatanBudgetByYear(subKegiatan, year), 0);

const programBudgetByYear = (program: Program, year: number) =>
    program.kegiatan.reduce((total, kegiatan) => total + kegiatanBudgetByYear(kegiatan, year), 0);

const numericPreviewValue = (value?: string | number | null) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    let normalized = String(value).trim().replace(/\s/g, '');

    if (/^-?\d+(\.\d+)?$/.test(normalized)) {
        return Number(normalized);
    }

    if (normalized.includes(',') && normalized.includes('.')) {
        normalized = normalized.replace(/\./g, '').replace(',', '.');
    } else if (normalized.includes(',')) {
        normalized = normalized.replace(',', '.');
    } else if (/^\d{1,3}(\.\d{3})+$/.test(normalized)) {
        normalized = normalized.replace(/\./g, '');
    }

    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
};

const formatPreviewPagu = (value: number) =>
    value > 0 ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value) : '-';

const renstraOutputRowClass = (level: RenstraOutputRowLevel) =>
    ({
        tujuan: 'bg-[#dcecff] font-semibold text-slate-950',
        sasaran: 'bg-[#eaf4ff] font-semibold text-slate-950',
        bidang: 'bg-[#fff2cc] font-semibold text-slate-950',
        program: 'bg-[#e2f0d9] text-slate-950',
        kegiatan: 'bg-[#fce4d6] text-slate-950',
        sub_kegiatan: 'bg-white text-slate-950',
    })[level];

const renstraOutputYearLabel = (year: number | string) => (Number(year) > props.renstra.tahun_akhir ? `${year} PM` : String(year));

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
        definisi_operasional: '',
        formula: '',
        formulasi_pengukuran: '',
        tipe_perhitungan: 'non_kumulatif',
        opd_penanggung_jawab_id: '',
        pd_penanggung_jawab: '',
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
                    parent_label: plainNodeText(tujuan.tujuan),
                    parent_id: tujuan.id,
                    kode: valueText(indikator.kode),
                    indikator: valueText(indikator.indikator),
                    satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                    tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                    definisi_operasional: valueText(indikator.definisi_operasional),
                    formula: valueText(indikator.formula),
                    formulasi_pengukuran: valueText(indikator.formulasi_pengukuran || indikator.formula),
                    tipe_perhitungan: valueText(indikator.tipe_perhitungan || 'non_kumulatif'),
                    opd_penanggung_jawab_id: valueText(indikator.opd_penanggung_jawab_id),
                    pd_penanggung_jawab: indikatorPdText(indikator),
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
                        parent_label: plainNodeText(indikator.indikator),
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
                    parent_label: plainNodeText(tujuan.tujuan),
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
                        parent_label: plainNodeText(sasaran.sasaran),
                        parent_id: sasaran.id,
                        kode: valueText(indikator.kode),
                        indikator: valueText(indikator.indikator),
                        satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                        tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                        definisi_operasional: valueText(indikator.definisi_operasional),
                        formula: valueText(indikator.formula),
                        formulasi_pengukuran: valueText(indikator.formulasi_pengukuran || indikator.formula),
                        tipe_perhitungan: valueText(indikator.tipe_perhitungan || 'non_kumulatif'),
                        opd_penanggung_jawab_id: valueText(indikator.opd_penanggung_jawab_id),
                        pd_penanggung_jawab: indikatorPdText(indikator),
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
                            parent_label: plainNodeText(indikator.indikator),
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
                        parent_label: plainNodeText(sasaran.sasaran),
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
                            parent_label: plainNodeText(program.nama),
                            parent_id: program.id,
                            kode: valueText(indikator.kode),
                            indikator: valueText(indikator.indikator),
                            satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                            tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                            definisi_operasional: valueText(indikator.definisi_operasional),
                            formula: valueText(indikator.formula),
                            formulasi_pengukuran: valueText(indikator.formulasi_pengukuran || indikator.formula),
                            tipe_perhitungan: valueText(indikator.tipe_perhitungan || 'non_kumulatif'),
                            opd_penanggung_jawab_id: valueText(indikator.opd_penanggung_jawab_id),
                            pd_penanggung_jawab: indikatorPdText(indikator),
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
                                parent_label: plainNodeText(indikator.indikator),
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
                            parent_label: plainNodeText(program.nama),
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
                                parent_label: plainNodeText(kegiatan.nama),
                                parent_id: kegiatan.id,
                                kode: valueText(indikator.kode),
                                indikator: valueText(indikator.indikator),
                                satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                                tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                                definisi_operasional: valueText(indikator.definisi_operasional),
                                formula: valueText(indikator.formula),
                                formulasi_pengukuran: valueText(indikator.formulasi_pengukuran || indikator.formula),
                                tipe_perhitungan: valueText(indikator.tipe_perhitungan || 'non_kumulatif'),
                                opd_penanggung_jawab_id: valueText(indikator.opd_penanggung_jawab_id),
                                pd_penanggung_jawab: indikatorPdText(indikator),
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
                                    parent_label: plainNodeText(indikator.indikator),
                                    parent_id: indikator.id,
                                    periode_tahun_id: target.periode_tahun.id,
                                    target: valueText(target.target),
                                    target_text: valueText(target.target_text),
                                    pagu: valueText(target.pagu),
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
                                parent_label: plainNodeText(kegiatan.nama),
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
                                    parent_label: plainNodeText(subKegiatan.nama),
                                    parent_id: subKegiatan.id,
                                    kode: valueText(indikator.kode),
                                    indikator: valueText(indikator.indikator),
                                    satuan_indikator_id: valueText(indikator.satuan_indikator_id),
                                    tipe_indikator: valueText(indikator.tipe_indikator || 'positif'),
                                    definisi_operasional: valueText(indikator.definisi_operasional),
                                    formula: valueText(indikator.formula),
                                    formulasi_pengukuran: valueText(indikator.formulasi_pengukuran || indikator.formula),
                                    tipe_perhitungan: valueText(indikator.tipe_perhitungan || 'non_kumulatif'),
                                    opd_penanggung_jawab_id: valueText(indikator.opd_penanggung_jawab_id),
                                    pd_penanggung_jawab: indikatorPdText(indikator),
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
                                        parent_label: plainNodeText(indikator.indikator),
                                        parent_id: indikator.id,
                                        periode_tahun_id: target.periode_tahun.id,
                                        target: valueText(target.target),
                                        target_text: valueText(target.target_text),
                                        pagu: valueText(target.pagu),
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
    form.definisi_operasional = '';
    form.formula = '';
    form.formulasi_pengukuran = '';
    form.tipe_perhitungan = 'non_kumulatif';
    form.opd_penanggung_jawab_id = '';
    form.pd_penanggung_jawab = '';
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
    targetBatchRows.value = [];
    clearNodeForm();
};

const defaultParentIdForType = (type: NodeType): number | string => {
    const key = parentKeyByType[type];

    if (!key) {
        return '';
    }

    const savedParent = props.nodeOptions[key]?.[0]?.id;

    if (savedParent) {
        return savedParent;
    }

    return bulkRows.value.find((row) => row.type === key && row.id)?.id ?? '';
};

const orderableBulkRows = (type: NodeType, parentId: number | string = ''): BulkRow[] => {
    if (!orderableNodeTypes.includes(type)) {
        return [];
    }

    const parentKey = parentKeyByType[type];
    const normalizedParentId = toNumberOrNull(parentId);

    return bulkRows.value.filter((row) => {
        if (row.type !== type || !row.id) {
            return false;
        }

        if (!parentKey) {
            return true;
        }

        return normalizedParentId ? Number(row.parent_id) === normalizedParentId : !row.parent_id;
    });
};

const nextOrderForType = (type: NodeType, parentId: number | string = ''): number => {
    const orders = orderableBulkRows(type, parentId)
        .map((row) => Number(row.urutan || 0))
        .filter((order) => Number.isFinite(order));

    return Math.max(0, ...orders) + 1;
};

const selectNodeType = (type: NodeType, parentId: number | string = '') => {
    editingNode.value = null;
    isNodeModalOpen.value = true;
    form.type = type;

    nextTick(() => {
        clearNodeForm();
        const resolvedParentId = parentId || (shownParentSelectorTypes.includes(type) ? '' : defaultParentIdForType(type));
        form.parent_id = resolvedParentId;
        form.urutan = nextOrderForType(type, resolvedParentId);
        applyImplicitReferences(type);

        if (targetNodeTypes.includes(type) || budgetNodeTypes.includes(type)) {
            prepareTargetBatchRows();
        }
    });
};

watch(
    () => form.parent_id,
    (parentId) => {
        if (editingNode.value || !isOrderableNodeType.value) {
            return;
        }

        form.urutan = nextOrderForType(form.type, parentId);
    },
);

const valueText = (value: unknown) => (value === null || value === undefined ? '' : String(value));
const normalizedTargetText = (value?: string | number | null) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(value) : '';
    }

    const raw = valueText(value).trim();

    if (!raw) {
        return '';
    }

    if (/^-?\d+\.\d{4,}$/.test(raw)) {
        const parsed = Number(raw);

        return Number.isFinite(parsed) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(parsed) : raw;
    }

    return raw;
};
const indikatorPdText = (indikator: Indikator) =>
    valueText(indikator.pd_penanggung_jawab || indikator.opd_penanggung_jawab?.singkatan || indikator.opd_penanggung_jawab?.nama);

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
        return plainNodeText(row.indikator);
    }

    if (isBulkTargetRow(row)) {
        return `${row.level} ${row.target_text || row.target || ''}`.trim();
    }

    return plainNodeText(row.uraian);
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
const hasBulkPaguIndikatif = (_row: BulkRow) => false;
const hasBulkPaguTahunan = (_row: BulkRow) => false;
const visibleBulkRows = (rows: BulkRow[]) => rows.filter((row) => !isBulkTargetRow(row));
const targetTypeByIndicatorType: Partial<Record<NodeType, NodeType>> = {
    indikator_tujuan: 'target_tujuan',
    indikator_sasaran: 'target_sasaran',
    indikator_program: 'target_program',
    indikator_kegiatan: 'target_kegiatan',
    indikator_sub_kegiatan: 'target_sub_kegiatan',
};
const indicatorTypeByTargetType: Partial<Record<NodeType, NodeType>> = {
    target_tujuan: 'indikator_tujuan',
    target_sasaran: 'indikator_sasaran',
    target_program: 'indikator_program',
    target_kegiatan: 'indikator_kegiatan',
    target_sub_kegiatan: 'indikator_sub_kegiatan',
};
const canEditTargetColumns = (row: BulkRow) => isBulkIndicatorRow(row) && Boolean(row.id);
const targetRowsForIndicator = (row: BulkRow): BulkRow[] => {
    const targetType = targetTypeByIndicatorType[row.type];

    if (!targetType || !row.id) {
        return [];
    }

    return bulkRows.value.filter((targetRow) => targetRow.type === targetType && Number(targetRow.parent_id) === Number(row.id));
};
const bulkRowsForSingleSave = (row: BulkRow): BulkRow[] => [row, ...targetRowsForIndicator(row)];
const targetRowForIndicator = (row: BulkRow, periodeId: number | string): BulkRow | null => {
    const targetType = targetTypeByIndicatorType[row.type];

    if (!targetType || !row.id) {
        return null;
    }

    return (
        bulkRows.value.find(
            (targetRow) =>
                targetRow.type === targetType &&
                Number(targetRow.parent_id) === Number(row.id) &&
                Number(targetRow.periode_tahun_id) === Number(periodeId),
        ) ?? null
    );
};
const ensureTargetRowForIndicator = (row: BulkRow, periodeId: number | string): BulkRow | null => {
    const existingTarget = targetRowForIndicator(row, periodeId);

    if (existingTarget) {
        return existingTarget;
    }

    const targetType = targetTypeByIndicatorType[row.type];

    if (!targetType || !row.id) {
        return null;
    }

    bulkDraftCounter.value += 1;
    const targetRow = makeBulkRow({
        key: `draft-target-${row.key}-${periodeId}-${bulkDraftCounter.value}`,
        id: null,
        type: targetType,
        level: typeOptionMap.value.get(targetType)?.label ?? 'Target',
        parent_label: bulkRowDisplayLabel(row),
        parent_id: row.id,
        periode_tahun_id: periodeId,
        isNew: true,
        saveState: 'dirty',
    });

    bulkRows.value = [...bulkRows.value, targetRow];

    return targetRow;
};
const targetValueForIndicator = (row: BulkRow, periodeId: number | string) => {
    const targetRow = targetRowForIndicator(row, periodeId);

    return normalizedTargetText(targetRow?.target_text || targetRow?.target);
};
const targetPaguForIndicator = (row: BulkRow, periodeId: number | string) => currencyInputText(targetRowForIndicator(row, periodeId)?.pagu);
const inputEventValue = (event: Event) => (event.target as HTMLInputElement).value;
const setIndicatorTargetValue = (row: BulkRow, periodeId: number | string, value: string) => {
    const targetRow = ensureTargetRowForIndicator(row, periodeId);

    if (!targetRow) {
        return;
    }

    if (!value.trim() && targetRow.isNew && !String(targetRow.pagu || '').trim()) {
        bulkRows.value = bulkRows.value.filter((bulkRow) => bulkRow.key !== targetRow.key);
        return;
    }

    targetRow.target_text = value;
    targetRow.target = '';
    scheduleBulkAutosave(targetRow);
};
const setIndicatorTargetPagu = (row: BulkRow, periodeId: number | string, value: string) => {
    const targetRow = ensureTargetRowForIndicator(row, periodeId);

    if (!targetRow) {
        return;
    }

    if (!value.trim() && targetRow.isNew && !String(targetRow.target_text || targetRow.target || '').trim()) {
        bulkRows.value = bulkRows.value.filter((bulkRow) => bulkRow.key !== targetRow.key);
        return;
    }

    targetRow.pagu = currencyTypingInputText(value);
    scheduleBulkAutosave(targetRow);
};
const showTargetFinance = (_row: BulkRow) => false;
const targetButtonLabel = (_row: BulkRow) => 'Target';
const targetBatchShowsFinance = computed(() => isBudgetType.value);
const targetBatchTitle = computed(() => (isBudgetType.value ? 'Pagu Indikatif 5 Tahunan' : 'Baseline dan Target 5 Tahunan'));
const targetBatchSubmitLabel = computed(() => (isBudgetType.value ? 'Simpan Anggaran' : 'Simpan Target'));
const targetBatchIndicatorRow = computed(() => {
    const indicatorType = indicatorTypeByTargetType[form.type];
    const parentId = toNumberOrNull(form.parent_id);

    if (!indicatorType || !parentId) {
        return null;
    }

    return bulkRows.value.find((row) => row.type === indicatorType && Number(row.id) === parentId) ?? null;
});
const targetBatchSubKegiatan = computed(() => (isBudgetType.value ? findSubKegiatan(form.parent_id) : null));
const prepareTargetBatchRows = () => {
    const indicatorRow = targetBatchIndicatorRow.value;
    const subKegiatan = targetBatchSubKegiatan.value;
    const periods = isBudgetType.value ? periodColumns.value : targetInputPeriods.value;

    targetBatchRows.value = periods.map((period) => {
        if (isBudgetType.value) {
            const existingAnggaran = subKegiatan?.anggaran?.find((anggaran) => Number(anggaran.periode_tahun.id) === Number(period.id)) ?? null;

            return {
                key: `budget-batch-${period.id}`,
                periode_tahun_id: period.id,
                year: period.year,
                label: period.label,
                target: '',
                pagu: currencyInputText(existingAnggaran?.anggaran),
                existingRow: existingAnggaran
                    ? makeBulkRow({
                          id: existingAnggaran.id,
                          type: 'anggaran_sub_kegiatan',
                          level: 'Pagu Indikatif Sub Kegiatan',
                          parent_label: subKegiatan ? plainNodeText(subKegiatan.nama) : '-',
                          parent_id: subKegiatan?.id ?? '',
                          periode_tahun_id: period.id,
                          pagu: currencyInputText(existingAnggaran.anggaran),
                      })
                    : null,
                saveState: existingAnggaran ? 'saved' : 'idle',
                error: '',
            };
        }

        const existingRow = indicatorRow ? targetRowForIndicator(indicatorRow, period.id) : null;

        return {
            key: `target-batch-${period.id}`,
            periode_tahun_id: period.id,
            year: period.year,
            label: period.label,
            target: normalizedTargetText(existingRow?.target_text || existingRow?.target),
            pagu: currencyInputText(existingRow?.pagu),
            existingRow,
            saveState: existingRow ? 'saved' : 'idle',
            error: '',
        };
    });
};
const onTargetBatchInput = (row: TargetBatchRow) => {
    row.saveState = 'dirty';
    row.error = '';
};
const onTargetBatchPaguInput = (row: TargetBatchRow) => {
    row.pagu = currencyTypingInputText(row.pagu);
    onTargetBatchInput(row);
};
const saveTargetBatchRows = async () => {
    if (isBudgetType.value) {
        await saveBudgetBatchRows();

        return;
    }

    const indicatorRow = targetBatchIndicatorRow.value;

    if (!indicatorRow) {
        return;
    }

    isTargetBatchSaving.value = true;

    try {
        for (const batchRow of targetBatchRows.value) {
            const existingTarget = batchRow.existingRow;
            const currentTarget = normalizedTargetText(existingTarget?.target_text || existingTarget?.target);
            const nextTarget = batchRow.target.trim();
            const hasValue = Boolean(nextTarget);
            const changed = nextTarget !== currentTarget;

            if (!changed || (!hasValue && !existingTarget)) {
                continue;
            }

            const targetRow = ensureTargetRowForIndicator(indicatorRow, batchRow.periode_tahun_id);

            if (!targetRow) {
                continue;
            }

            batchRow.saveState = 'saving';
            batchRow.error = '';
            targetRow.target = '';
            targetRow.target_text = nextTarget;
            targetRow.pagu = '';
            targetRow.saveState = 'dirty';

            await saveBulkRow(targetRow);

            batchRow.existingRow = targetRow;
            batchRow.saveState = targetRow.saveState;
            batchRow.error = targetRow.error;
        }
    } finally {
        isTargetBatchSaving.value = false;
    }

    if (targetBatchRows.value.every((row) => !row.error)) {
        router.reload({ only: ['renstra'], preserveScroll: true, preserveState: true });
        closeNodeModal();
    }
};
const saveBudgetBatchRows = async () => {
    const subKegiatan = targetBatchSubKegiatan.value;

    if (!subKegiatan) {
        return;
    }

    isTargetBatchSaving.value = true;

    try {
        for (const batchRow of targetBatchRows.value) {
            const existingBudget = batchRow.existingRow;
            const currentPagu = normalizedCurrencyComparable(existingBudget?.pagu);
            const nextPagu = normalizedCurrencyComparable(batchRow.pagu);
            const hasValue = Boolean(nextPagu);
            const changed = nextPagu !== currentPagu;

            if (!changed || (!hasValue && !existingBudget)) {
                continue;
            }

            const budgetRow =
                existingBudget ??
                makeBulkRow({
                    key: `draft-budget-${subKegiatan.id}-${batchRow.periode_tahun_id}`,
                    id: null,
                    type: 'anggaran_sub_kegiatan',
                    level: 'Pagu Indikatif Sub Kegiatan',
                    parent_label: plainNodeText(subKegiatan.nama),
                    parent_id: subKegiatan.id,
                    periode_tahun_id: batchRow.periode_tahun_id,
                    isNew: true,
                    saveState: 'dirty',
                });

            batchRow.saveState = 'saving';
            batchRow.error = '';
            budgetRow.pagu = currencyInputText(nextPagu);
            budgetRow.target = '';
            budgetRow.target_text = '';
            budgetRow.saveState = 'dirty';

            await saveBulkRow(budgetRow);

            batchRow.existingRow = budgetRow.id ? budgetRow : null;
            batchRow.saveState = budgetRow.saveState;
            batchRow.error = budgetRow.error;
        }
    } finally {
        isTargetBatchSaving.value = false;
    }

    if (targetBatchRows.value.every((row) => !row.error)) {
        router.reload({ only: ['renstra'], preserveScroll: true, preserveState: true });
        closeNodeModal();
    }
};
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

    return Boolean(row.periode_tahun_id && (String(row.target || '').trim() || String(row.target_text || '').trim() || String(row.pagu || '').trim()));
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

const bulkStatusLabel = (row: BulkRow) => {
    if (row.saveState === 'idle') {
        return row.isNew ? 'Baru' : 'Siap';
    }

    if (row.saveState === 'dirty') {
        return bulkAutosaveEnabled.value ? 'Menunggu autosave' : 'Belum disimpan';
    }

    if (row.saveState === 'saving') {
        return 'Menyimpan';
    }

    if (row.saveState === 'saved') {
        return row.savedAt ? `Tersimpan ${row.savedAt}` : 'Tersimpan';
    }

    return row.error || 'Gagal simpan';
};

const bulkStatusClass = (row: BulkRow) =>
    ({
        idle: 'bg-slate-100 text-slate-700',
        dirty: 'bg-amber-100 text-amber-800',
        saving: 'bg-blue-100 text-blue-800',
        saved: 'bg-emerald-100 text-emerald-800',
        error: 'bg-red-100 text-red-800',
    })[row.saveState];

const bulkRowReferenceLabel = (row: BulkRow): string => {
    if (!row.reference_field) {
        return '-';
    }

    return optionById(bulkReferenceOptions(row), row.reference_value)?.label ?? 'Belum dihubungkan';
};

const bulkRowMasterLabel = (row: BulkRow): string => {
    if (!bulkMasterOptions(row).length) {
        return '-';
    }

    return optionById(bulkMasterOptions(row), bulkMasterValue(row))?.label ?? 'Belum dipilih';
};

const bulkRowPrimaryText = (row: BulkRow): string => {
    if (isBulkIndicatorRow(row)) {
        return plainNodeText(row.indikator) || 'Indikator belum diisi';
    }

    return plainNodeText(row.uraian) || 'Data belum diisi';
};

const bulkRowSecondaryText = (row: BulkRow): string => {
    if (isBulkIndicatorRow(row)) {
        const satuan = optionById(satuanOptions.value, row.satuan_indikator_id)?.label ?? 'Satuan belum dipilih';
        const tipe = row.tipe_perhitungan === 'kumulatif' ? 'Kumulatif' : 'Non-kumulatif';

        return `${satuan} - ${tipe}`;
    }

    if (hasBulkSasaranLevel(row) && row.sasaran_level) {
        return row.sasaran_level;
    }

    return typeMeta[row.type].primaryField;
};

const bulkTargetSummaries = (row: BulkRow) =>
    periodColumns.value.map((period) => ({
        id: period.id,
        year: period.year,
        target: targetValueForIndicator(row, period.id) || '-',
    }));
const baselineTargetSummary = (row: BulkRow) => {
    if (!baselinePeriod.value) {
        return null;
    }

    return {
        id: baselinePeriod.value.id,
        year: baselinePeriod.value.year,
        target: targetValueForIndicator(row, baselinePeriod.value.id) || '-',
    };
};
const targetBatchPeriodLabel = (row: TargetBatchRow) =>
    Number(row.year) === baselineYear.value ? `${row.year} - Baseline` : row.label;

const bulkRowChildActions = (row: BulkRow): Array<{ type: NodeType; label: string }> => {
    if (!row.id) {
        return [];
    }

    const actions: Partial<Record<NodeType, Array<{ type: NodeType; label: string }>>> = {
        tujuan: [
            { type: 'indikator_tujuan', label: 'Indikator' },
            { type: 'sasaran', label: 'Sasaran' },
        ],
        indikator_tujuan: [{ type: 'target_tujuan', label: 'Target' }],
        sasaran: [
            { type: 'indikator_sasaran', label: 'Indikator' },
            { type: 'program', label: 'Program' },
        ],
        indikator_sasaran: [{ type: 'target_sasaran', label: 'Target' }],
        program: [
            { type: 'indikator_program', label: 'Indikator' },
            { type: 'kegiatan', label: 'Kegiatan' },
        ],
        indikator_program: [{ type: 'target_program', label: 'Target' }],
        kegiatan: [
            { type: 'indikator_kegiatan', label: 'Indikator' },
            { type: 'sub_kegiatan', label: 'Sub Kegiatan' },
        ],
        indikator_kegiatan: [{ type: 'target_kegiatan', label: 'Target' }],
        sub_kegiatan: [{ type: 'anggaran_sub_kegiatan', label: 'Pagu Indikatif' }],
        indikator_sub_kegiatan: [{ type: 'target_sub_kegiatan', label: 'Target' }],
    };

    return actions[row.type] ?? [];
};

const sortBulkRowsByOrder = (rows: BulkRow[]): BulkRow[] =>
    [...rows].sort((a, b) => {
        const orderDiff = Number(a.urutan || 9999) - Number(b.urutan || 9999);

        if (orderDiff !== 0) {
            return orderDiff;
        }

        return bulkRowPrimaryText(a).localeCompare(bulkRowPrimaryText(b), 'id');
    });

const sectionParentRows = (section: BulkInputSection): BulkRow[] =>
    sortBulkRowsByOrder(section.rows.filter((row) => row.type === section.primaryType && !isBulkTargetRow(row)));

const shouldGroupSection = (section: BulkInputSection): boolean => ['program', 'kegiatan'].includes(section.key);

const sectionParentContextLabel = (section: BulkInputSection): string =>
    ({
        program: 'Sasaran',
        kegiatan: 'Program',
        'sub-kegiatan': 'Kegiatan',
    })[section.key] ?? '';

const sectionParentContextFallback = (section: BulkInputSection): string =>
    ({
        program: 'Belum memilih sasaran',
        kegiatan: 'Belum memilih program',
        'sub-kegiatan': 'Belum memilih kegiatan',
    })[section.key] ?? '';

const sectionGroupItemLabel = (section: BulkInputSection): string =>
    ({
        program: 'program',
        kegiatan: 'kegiatan',
        'sub-kegiatan': 'sub kegiatan',
    })[section.key] ?? 'data';

const sectionIndentClass = (section: BulkInputSection): string =>
    ({
        tujuan: '',
        sasaran: 'md:pl-2',
        program: 'md:pl-4',
        kegiatan: 'md:pl-6',
        'sub-kegiatan': '',
    })[section.key] ?? '';

const sectionArticleClass = (section: BulkInputSection): string =>
    ({
        tujuan: 'border-l-[5px] border-l-[#00336C]',
        sasaran: 'border-l-[5px] border-l-sky-500',
        program: 'border-l-[5px] border-l-blue-600',
        kegiatan: 'border-l-[5px] border-l-cyan-600',
        'sub-kegiatan': 'border-l-[5px] border-l-emerald-600',
    })[section.key] ?? 'border-l-[5px] border-l-blue-200';

const sectionHeaderTintClass = (section: BulkInputSection): string =>
    ({
        tujuan: 'from-blue-50/90 via-white to-white',
        sasaran: 'from-sky-50/90 via-white to-white',
        program: 'from-blue-50/90 via-white to-white',
        kegiatan: 'from-cyan-50/90 via-white to-white',
        'sub-kegiatan': 'from-emerald-50/80 via-white to-white',
    })[section.key] ?? 'from-slate-50 to-white';

const sectionGroupClass = (section: BulkInputSection): string =>
    ({
        program: 'border-sky-200 bg-sky-50/70',
        kegiatan: 'border-blue-200 bg-blue-50/70',
        'sub-kegiatan': 'border-cyan-200 bg-cyan-50/70',
    })[section.key] ?? 'border-blue-100 bg-blue-50/60';

const sectionIndexBadgeClass = (section: BulkInputSection): string =>
    ({
        tujuan: 'bg-[#00336C] text-white',
        sasaran: 'bg-sky-100 text-sky-800',
        program: 'bg-blue-100 text-blue-800',
        kegiatan: 'bg-cyan-100 text-cyan-800',
        'sub-kegiatan': 'bg-emerald-100 text-emerald-800',
    })[section.key] ?? 'bg-blue-50 text-[#00336C]';

const sectionPrimaryButtonClass = (): string =>
    'border-[#00336C] bg-[#00336C] px-5 text-white shadow-md shadow-blue-950/10 hover:bg-[#0a4485] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C]/30';

const sectionParentContextValue = (section: BulkInputSection, row: BulkRow): string => {
    const label = plainNodeText(row.parent_label);

    return label && label !== '-' ? label : sectionParentContextFallback(section);
};

const groupedSectionParentRows = (section: BulkInputSection): BulkSectionGroup[] => {
    const rows = sectionParentRows(section);

    if (!shouldGroupSection(section)) {
        return [{ key: `${section.key}-all`, label: '', rows }];
    }

    const groups = new Map<string, BulkSectionGroup>();

    rows.forEach((row) => {
        const label = sectionParentContextValue(section, row);
        const key = `${section.key}-${row.parent_id || 'none'}-${label}`;

        if (!groups.has(key)) {
            groups.set(key, { key, label, rows: [] });
        }

        groups.get(key)?.rows.push(row);
    });

    return Array.from(groups.values());
};

const sectionGroupProgramLabel = (section: BulkInputSection, group: BulkSectionGroup): string => {
    if (section.key !== 'sub-kegiatan') {
        return '';
    }

    const kegiatanId = group.rows[0]?.parent_id;
    const kegiatanRow = bulkRows.value.find((row) => row.type === 'kegiatan' && Number(row.id) === Number(kegiatanId));
    const label = plainNodeText(kegiatanRow?.parent_label);

    return label && label !== '-' ? label : '';
};

const sectionIndicatorRows = (section: BulkInputSection, parentRow: BulkRow): BulkRow[] =>
    sortBulkRowsByOrder(section.rows.filter((row) => row.type === section.indicatorType && Number(row.parent_id) === Number(parentRow.id)));

type ProgramSasaranItem = {
    key: string;
    sasaranId: number;
    programId: number;
    programName: string;
    indicatorCount: number;
};

type ProgramSasaranFolder = {
    key: string;
    sasaranId: number;
    sasaranName: string;
    programCount: number;
    indicatorCount: number;
    programs: ProgramSasaranItem[];
};

type KegiatanProgramItem = {
    key: string;
    programId: number;
    kegiatanId: number;
    kegiatanName: string;
    indicatorCount: number;
};

type KegiatanProgramFolder = {
    key: string;
    programId: number;
    programName: string;
    kegiatanCount: number;
    indicatorCount: number;
    kegiatan: KegiatanProgramItem[];
};

const programSasaranFolders = computed<ProgramSasaranFolder[]>(() =>
    sortBulkRowsByOrder(sasaranRows.value.filter((row) => row.type === 'sasaran' && Boolean(row.id))).map((sasaranRow) => {
        const programs = sortBulkRowsByOrder(
            programRows.value.filter((row) => row.type === 'program' && Number(row.parent_id) === Number(sasaranRow.id) && Boolean(row.id)),
        ).map((programRow) => {
            const indicatorCount = programRows.value.filter(
                (indicatorRow) => indicatorRow.type === 'indikator_program' && Number(indicatorRow.parent_id) === Number(programRow.id),
            ).length;

            return {
                key: `program-folder-program-${programRow.id}`,
                sasaranId: Number(sasaranRow.id),
                programId: Number(programRow.id),
                programName: bulkRowPrimaryText(programRow),
                indicatorCount,
            };
        });

        return {
            key: `program-folder-sasaran-${sasaranRow.id}`,
            sasaranId: Number(sasaranRow.id),
            sasaranName: bulkRowPrimaryText(sasaranRow),
            programCount: programs.length,
            indicatorCount: programs.reduce((total, item) => total + item.indicatorCount, 0),
            programs,
        };
    }),
);

const filteredProgramFocusItems = computed(() => {
    const keyword = programFocusSearch.value.trim().toLowerCase();

    if (!keyword) {
        return programSasaranFolders.value;
    }

    return programSasaranFolders.value
        .map((sasaran) => {
            const sasaranMatches = sasaran.sasaranName.toLowerCase().includes(keyword);

            return {
                ...sasaran,
                programs: sasaranMatches
                    ? sasaran.programs
                    : sasaran.programs.filter((item) => `${sasaran.sasaranName} ${item.programName}`.toLowerCase().includes(keyword)),
            };
        })
        .filter((sasaran) => sasaran.sasaranName.toLowerCase().includes(keyword) || sasaran.programs.length > 0);
});

const activeProgramFocus = computed(
    () =>
        programSasaranFolders.value
            .flatMap((sasaran) => sasaran.programs.map((program) => ({ ...program, sasaranName: sasaran.sasaranName })))
            .find((item) => item.programId === selectedProgramFocusId.value) ?? null,
);

const kegiatanProgramFolders = computed<KegiatanProgramFolder[]>(() =>
    sortBulkRowsByOrder(programRows.value.filter((row) => row.type === 'program' && Boolean(row.id))).map((programRow) => {
        const kegiatan = sortBulkRowsByOrder(
            kegiatanRows.value.filter((row) => row.type === 'kegiatan' && Number(row.parent_id) === Number(programRow.id) && Boolean(row.id)),
        ).map((kegiatanRow) => {
            const indicatorCount = kegiatanRows.value.filter(
                (indicatorRow) => indicatorRow.type === 'indikator_kegiatan' && Number(indicatorRow.parent_id) === Number(kegiatanRow.id),
            ).length;

            return {
                key: `kegiatan-folder-kegiatan-${kegiatanRow.id}`,
                programId: Number(programRow.id),
                kegiatanId: Number(kegiatanRow.id),
                kegiatanName: bulkRowPrimaryText(kegiatanRow),
                indicatorCount,
            };
        });

        return {
            key: `kegiatan-folder-program-${programRow.id}`,
            programId: Number(programRow.id),
            programName: bulkRowPrimaryText(programRow),
            kegiatanCount: kegiatan.length,
            indicatorCount: kegiatan.reduce((total, item) => total + item.indicatorCount, 0),
            kegiatan,
        };
    }),
);

const filteredKegiatanFocusItems = computed(() => {
    const keyword = kegiatanFocusSearch.value.trim().toLowerCase();

    if (!keyword) {
        return kegiatanProgramFolders.value;
    }

    return kegiatanProgramFolders.value
        .map((program) => {
            const programMatches = program.programName.toLowerCase().includes(keyword);

            return {
                ...program,
                kegiatan: programMatches
                    ? program.kegiatan
                    : program.kegiatan.filter((item) => `${program.programName} ${item.kegiatanName}`.toLowerCase().includes(keyword)),
            };
        })
        .filter((program) => program.programName.toLowerCase().includes(keyword) || program.kegiatan.length > 0);
});

const activeKegiatanFocus = computed(
    () =>
        kegiatanProgramFolders.value
            .flatMap((program) => program.kegiatan.map((kegiatan) => ({ ...kegiatan, programName: program.programName })))
            .find((item) => item.kegiatanId === selectedKegiatanFocusId.value) ?? null,
);

type SubKegiatanKegiatanFolder = {
    key: string;
    programId: number;
    kegiatanId: number;
    kegiatanName: string;
    subKegiatanCount: number;
    indicatorCount: number;
};

type SubKegiatanProgramFolder = {
    key: string;
    programId: number;
    programName: string;
    kegiatanCount: number;
    subKegiatanCount: number;
    indicatorCount: number;
    kegiatan: SubKegiatanKegiatanFolder[];
};

const subKegiatanProgramFolders = computed<SubKegiatanProgramFolder[]>(() =>
    sortBulkRowsByOrder(programRows.value.filter((row) => row.type === 'program' && Boolean(row.id))).map((programRow) => {
        const kegiatan = sortBulkRowsByOrder(
            kegiatanRows.value.filter((row) => row.type === 'kegiatan' && Number(row.parent_id) === Number(programRow.id) && Boolean(row.id)),
        ).map((kegiatanRow) => {
            const subRows = subKegiatanRows.value.filter(
                (subRow) => subRow.type === 'sub_kegiatan' && Number(subRow.parent_id) === Number(kegiatanRow.id),
            );
            const indicatorCount = subRows.reduce(
                (total, subRow) =>
                    total +
                    subKegiatanRows.value.filter(
                        (indicatorRow) =>
                            indicatorRow.type === 'indikator_sub_kegiatan' && Number(indicatorRow.parent_id) === Number(subRow.id),
                    ).length,
                0,
            );

            return {
                key: `sub-kegiatan-folder-kegiatan-${kegiatanRow.id}`,
                programId: Number(programRow.id),
                kegiatanId: Number(kegiatanRow.id),
                kegiatanName: bulkRowPrimaryText(kegiatanRow),
                subKegiatanCount: subRows.length,
                indicatorCount,
            };
        });

        return {
            key: `sub-kegiatan-folder-program-${programRow.id}`,
            programId: Number(programRow.id),
            programName: bulkRowPrimaryText(programRow),
            kegiatanCount: kegiatan.length,
            subKegiatanCount: kegiatan.reduce((total, item) => total + item.subKegiatanCount, 0),
            indicatorCount: kegiatan.reduce((total, item) => total + item.indicatorCount, 0),
            kegiatan,
        };
    }),
);

const filteredSubKegiatanFocusItems = computed(() => {
    const keyword = subKegiatanFocusSearch.value.trim().toLowerCase();

    if (!keyword) {
        return subKegiatanProgramFolders.value;
    }

    return subKegiatanProgramFolders.value
        .map((program) => {
            const programMatches = program.programName.toLowerCase().includes(keyword);

            return {
                ...program,
                kegiatan: programMatches
                    ? program.kegiatan
                    : program.kegiatan.filter((item) =>
                          `${program.programName} ${item.kegiatanName}`.toLowerCase().includes(keyword),
                      ),
            };
        })
        .filter((program) => program.programName.toLowerCase().includes(keyword) || program.kegiatan.length > 0);
});

const activeSubKegiatanFocus = computed(
    () =>
        subKegiatanProgramFolders.value
            .flatMap((program) => program.kegiatan.map((kegiatan) => ({ ...kegiatan, programName: program.programName })))
            .find((item) => item.kegiatanId === selectedSubKegiatanKegiatanId.value) ?? null,
);

watch(
    programSasaranFolders,
    (items) => {
        const programIds = items.flatMap((sasaran) => sasaran.programs.map((program) => program.programId));

        expandedProgramSasaranIds.value = expandedProgramSasaranIds.value.filter((id) =>
            items.some((sasaran) => sasaran.sasaranId === id),
        );

        if (programIds.length === 0 || !programIds.includes(Number(selectedProgramFocusId.value))) {
            selectedProgramFocusId.value = null;
        }
    },
    { immediate: true },
);

watch(
    kegiatanProgramFolders,
    (items) => {
        const kegiatanIds = items.flatMap((program) => program.kegiatan.map((kegiatan) => kegiatan.kegiatanId));

        expandedKegiatanProgramIds.value = expandedKegiatanProgramIds.value.filter((id) =>
            items.some((program) => program.programId === id),
        );

        if (kegiatanIds.length === 0 || !kegiatanIds.includes(Number(selectedKegiatanFocusId.value))) {
            selectedKegiatanFocusId.value = null;
        }
    },
    { immediate: true },
);

watch(
    subKegiatanProgramFolders,
    (items) => {
        const kegiatanIds = items.flatMap((program) => program.kegiatan.map((kegiatan) => kegiatan.kegiatanId));

        expandedSubKegiatanProgramIds.value = expandedSubKegiatanProgramIds.value.filter((id) =>
            items.some((program) => program.programId === id),
        );

        if (kegiatanIds.length === 0) {
            selectedSubKegiatanKegiatanId.value = null;
            return;
        }

        if (!kegiatanIds.includes(Number(selectedSubKegiatanKegiatanId.value))) {
            selectedSubKegiatanKegiatanId.value = null;
        }
    },
    { immediate: true },
);

const isSubKegiatanProgramOpen = (programId: number): boolean => expandedSubKegiatanProgramIds.value.includes(programId);

const resolveTemplateRefElement = (value: HTMLElement | HTMLElement[] | null): HTMLElement | null => {
    if (Array.isArray(value)) {
        return value[0] ?? null;
    }

    return value;
};

const findScrollableParent = (element: HTMLElement): HTMLElement | Window => {
    let parent = element.parentElement;

    while (parent) {
        const style = window.getComputedStyle(parent);
        const canScroll = /(auto|scroll|overlay)/.test(style.overflowY) && parent.scrollHeight > parent.clientHeight;

        if (canScroll) {
            return parent;
        }

        parent = parent.parentElement;
    }

    return window;
};

const waitForAnimationFrame = () =>
    new Promise<void>((resolve) => {
        window.requestAnimationFrame(() => resolve());
    });

const scrollToDetail = async (resolveDetailRef: () => HTMLElement | HTMLElement[] | null) => {
    await nextTick();
    await waitForAnimationFrame();
    await waitForAnimationFrame();

    const target = resolveTemplateRefElement(resolveDetailRef());

    if (!target) {
        return;
    }

    const topbarHeight = document.querySelector<HTMLElement>('.admin-topbar')?.getBoundingClientRect().height ?? 64;
    const scrollOffset = topbarHeight + 18;
    const scrollParent = findScrollableParent(target);

    if (scrollParent === window) {
        const top = target.getBoundingClientRect().top + window.scrollY - scrollOffset;

        window.scrollTo({
            top: Math.max(top, 0),
            behavior: 'smooth',
        });

        return;
    }

    const parent = scrollParent as HTMLElement;
    const parentRect = parent.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();
    const top = parent.scrollTop + targetRect.top - parentRect.top - scrollOffset;

    parent.scrollTo({
        top: Math.max(top, 0),
        behavior: 'smooth',
    });
};

const scrollToProgramDetail = async () => scrollToDetail(() => programDetailRef.value);
const scrollToKegiatanDetail = async () => scrollToDetail(() => kegiatanDetailRef.value);
const scrollToSubKegiatanDetail = async () => scrollToDetail(() => subKegiatanDetailRef.value);

const isProgramSasaranOpen = (sasaranId: number): boolean => expandedProgramSasaranIds.value.includes(sasaranId);

const toggleProgramSasaran = (sasaranId: number) => {
    if (isProgramSasaranOpen(sasaranId)) {
        expandedProgramSasaranIds.value = expandedProgramSasaranIds.value.filter((id) => id !== sasaranId);

        if (activeProgramFocus.value?.sasaranId === sasaranId) {
            selectedProgramFocusId.value = null;
        }

        return;
    }

    expandedProgramSasaranIds.value = [...expandedProgramSasaranIds.value, sasaranId];
};

const selectProgramFocus = async (sasaranId: number, programId: number) => {
    if (!isProgramSasaranOpen(sasaranId)) {
        expandedProgramSasaranIds.value = [...expandedProgramSasaranIds.value, sasaranId];
    }

    selectedProgramFocusId.value = programId;
    await scrollToProgramDetail();
};

const isKegiatanProgramOpen = (programId: number): boolean => expandedKegiatanProgramIds.value.includes(programId);

const toggleKegiatanProgram = (programId: number) => {
    if (isKegiatanProgramOpen(programId)) {
        expandedKegiatanProgramIds.value = expandedKegiatanProgramIds.value.filter((id) => id !== programId);

        if (activeKegiatanFocus.value?.programId === programId) {
            selectedKegiatanFocusId.value = null;
        }

        return;
    }

    expandedKegiatanProgramIds.value = [...expandedKegiatanProgramIds.value, programId];
};

const selectKegiatanFocus = async (programId: number, kegiatanId: number) => {
    if (!isKegiatanProgramOpen(programId)) {
        expandedKegiatanProgramIds.value = [...expandedKegiatanProgramIds.value, programId];
    }

    selectedKegiatanFocusId.value = kegiatanId;
    await scrollToKegiatanDetail();
};

const toggleSubKegiatanProgram = (programId: number) => {
    if (isSubKegiatanProgramOpen(programId)) {
        expandedSubKegiatanProgramIds.value = expandedSubKegiatanProgramIds.value.filter((id) => id !== programId);

        if (activeSubKegiatanFocus.value?.programId === programId) {
            selectedSubKegiatanKegiatanId.value = null;
        }

        return;
    }

    expandedSubKegiatanProgramIds.value = [...expandedSubKegiatanProgramIds.value, programId];
};

const selectSubKegiatanKegiatan = async (programId: number, kegiatanId: number) => {
    if (!isSubKegiatanProgramOpen(programId)) {
        expandedSubKegiatanProgramIds.value = [...expandedSubKegiatanProgramIds.value, programId];
    }

    selectedSubKegiatanKegiatanId.value = kegiatanId;
    await scrollToSubKegiatanDetail();
};

const focusedSubKegiatanRows = (section: BulkInputSection): BulkRow[] => {
    const focus = activeSubKegiatanFocus.value;

    if (!focus) {
        return [];
    }

    return sectionParentRows(section).filter((row) => Number(row.parent_id) === focus.kegiatanId);
};

const focusedSubKegiatanIndicatorCount = (section: BulkInputSection): number =>
    focusedSubKegiatanRows(section).reduce((total, row) => total + sectionIndicatorRows(section, row).length, 0);

const focusedSubKegiatanGroups = (section: BulkInputSection): BulkSectionGroup[] => {
    const focus = activeSubKegiatanFocus.value;

    if (!focus) {
        return [];
    }

    return [
        {
            key: `sub-kegiatan-focused-${focus.kegiatanId}`,
            label: focus.kegiatanName,
            rows: focusedSubKegiatanRows(section),
        },
    ];
};

const focusedProgramGroups = (section: BulkInputSection): BulkSectionGroup[] => {
    const focus = activeProgramFocus.value;

    if (!focus) {
        return [];
    }

    const row = sectionParentRows(section).find((item) => Number(item.id) === focus.programId);

    if (!row) {
        return [];
    }

    return [
        {
            key: `program-focused-${focus.programId}`,
            label: focus.sasaranName,
            rows: [row],
        },
    ];
};

const focusedKegiatanGroups = (section: BulkInputSection): BulkSectionGroup[] => {
    const focus = activeKegiatanFocus.value;

    if (!focus) {
        return [];
    }

    const row = sectionParentRows(section).find((item) => Number(item.id) === focus.kegiatanId);

    if (!row) {
        return [];
    }

    return [
        {
            key: `kegiatan-focused-${focus.kegiatanId}`,
            label: focus.programName,
            rows: [row],
        },
    ];
};

const openFocusedSubKegiatanModal = () => {
    const focus = activeSubKegiatanFocus.value;

    if (!focus) {
        return;
    }

    selectNodeType('sub_kegiatan', focus.kegiatanId);
};

const indicatorTargetType = (row: BulkRow): NodeType | null => targetTypeByIndicatorType[row.type] ?? null;

const openBulkIndicatorModal = (section: BulkInputSection, parentRow: BulkRow) => {
    if (!parentRow.id) {
        return;
    }

    selectNodeType(section.indicatorType, parentRow.id);
};

const openSubKegiatanBudgetModal = (parentRow: BulkRow) => {
    if (!parentRow.id) {
        return;
    }

    selectNodeType('anggaran_sub_kegiatan', parentRow.id);
};

const openBulkTargetModal = (row: BulkRow) => {
    const targetType = indicatorTargetType(row);

    if (!targetType || !row.id) {
        return;
    }

    selectNodeType(targetType, row.id);
};

const parentRowSubtext = (row: BulkRow): string => {
    if (hasBulkSasaranLevel(row) && row.sasaran_level) {
        return row.sasaran_level;
    }

    return '';
};

const parentRowCanAddIndicator = (parentRow: BulkRow) => Boolean(parentRow.id);

const bulkRowToNode = (row: BulkRow) => ({
    kode: row.kode,
    urutan: row.urutan,
    tujuan: row.uraian,
    sasaran: row.uraian,
    nama: row.uraian,
    sasaran_program: row.sasaran_level,
    sasaran_kegiatan: row.sasaran_level,
    sasaran_sub_kegiatan: row.sasaran_level,
    indikator: row.indikator,
    tipe_indikator: row.tipe_indikator,
    satuan_indikator_id: row.satuan_indikator_id,
    definisi_operasional: row.definisi_operasional,
    formula: row.formula,
    formulasi_pengukuran: row.formulasi_pengukuran,
    tipe_perhitungan: row.tipe_perhitungan,
    opd_penanggung_jawab_id: row.opd_penanggung_jawab_id,
    pd_penanggung_jawab: row.pd_penanggung_jawab,
    sumber_data: row.sumber_data,
    target: row.target,
    target_text: row.target_text,
    pagu: row.pagu,
    pagu_indikatif: row.pagu_indikatif,
    tujuan_daerah_id: row.reference_field === 'tujuan_daerah_id' ? row.reference_value : '',
    indikator_tujuan_daerah_id: row.reference_field === 'indikator_tujuan_daerah_id' ? row.reference_value : '',
    sasaran_daerah_id: row.reference_field === 'sasaran_daerah_id' ? row.reference_value : '',
    indikator_sasaran_daerah_id: row.reference_field === 'indikator_sasaran_daerah_id' ? row.reference_value : '',
    program_rpjmd_id: row.reference_field === 'program_rpjmd_id' ? row.reference_value : '',
    indikator_program_rpjmd_id: row.reference_field === 'indikator_program_rpjmd_id' ? row.reference_value : '',
    program_pemerintahan_id: row.program_pemerintahan_id,
    kegiatan_pemerintahan_id: row.kegiatan_pemerintahan_id,
    sub_kegiatan_pemerintahan_id: row.sub_kegiatan_pemerintahan_id,
    opd_unit_id: row.opd_unit_id,
});

const openBulkChildModal = (row: BulkRow, type: NodeType) => {
    if (!row.id) {
        return;
    }

    selectNodeType(type, row.id);
};

const editBulkRow = (row: BulkRow) => {
    if (!row.id) {
        selectNodeType(row.type, row.parent_id);
        return;
    }

    editNode(row.type, Number(row.id), toNumberOrNull(row.parent_id), bulkRowToNode(row));
};

const deleteBulkRow = (row: BulkRow) => {
    if (row.isNew) {
        removeBulkDraft(row);
        return;
    }

    if (row.id) {
        void destroyNode(row.type, Number(row.id), row.level.toLowerCase());
    }
};

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
    row.definisi_operasional = '';
    row.formula = '';
    row.formulasi_pengukuran = '';
    row.tipe_perhitungan = 'non_kumulatif';
    row.opd_penanggung_jawab_id = '';
    row.pd_penanggung_jawab = '';
    row.sumber_data = '';
    row.pagu_indikatif = '';
    row.periode_tahun_id = '';
    row.target = '';
    row.target_text = '';
    row.pagu = '';
    row.urutan = nextOrderForType(row.type, row.parent_id);
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
        definisi_operasional: row.definisi_operasional || null,
        formula: row.formulasi_pengukuran || row.formula || null,
        formulasi_pengukuran: row.formulasi_pengukuran || row.formula || null,
        tipe_perhitungan: row.tipe_perhitungan || 'non_kumulatif',
        opd_penanggung_jawab_id: row.opd_penanggung_jawab_id || null,
        pd_penanggung_jawab: row.pd_penanggung_jawab || null,
        sumber_data: row.sumber_data || null,
        pagu_indikatif: normalizedCurrencyPayload(row.pagu_indikatif),
        periode_tahun_id: row.periode_tahun_id || null,
        target: row.target || null,
        target_text: row.target_text || null,
        pagu: normalizedCurrencyPayload(row.pagu),
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
    window.clearTimeout(bulkSaveTimers.get(row.key));

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

    if (!bulkAutosaveEnabled.value || (row.isNew && !bulkRowReadyToSave(row))) {
        return;
    }

    bulkSaveTimers.set(
        row.key,
        window.setTimeout(() => {
            void saveBulkRow(row);
        }, 900),
    );
};

const bulkRowsToSave = (rows: BulkRow[]) => rows.filter((row) => ['dirty', 'error'].includes(row.saveState));
const hasBulkRowsToSave = (rows: BulkRow[]) => bulkRowsToSave(rows).length > 0;
const isBulkRowsSaving = (rows: BulkRow[]) => rows.some((row) => row.saveState === 'saving');
const canSaveBulkRow = (row: BulkRow) => row.saveState !== 'saving' && (row.isNew || ['dirty', 'error'].includes(row.saveState));

const saveBulkRows = async (rows: BulkRow[]) => {
    for (const row of bulkRowsToSave(rows)) {
        await saveBulkRow(row);
    }
};

const toggleBulkAutosave = () => {
    bulkAutosaveEnabled.value = !bulkAutosaveEnabled.value;

    if (!bulkAutosaveEnabled.value) {
        bulkSaveTimers.forEach((timer) => window.clearTimeout(timer));
        bulkSaveTimers.clear();
        return;
    }

    bulkRowsToSave(bulkRows.value)
        .filter((row) => !row.isNew || bulkRowReadyToSave(row))
        .forEach((row) => scheduleBulkAutosave(row));
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
        form.pagu_indikatif = currencyInputText(node.pagu_indikatif);
        form.program_rpjmd_id = valueText(node.program_rpjmd_id);
        form.program_pemerintahan_id = valueText(node.program_pemerintahan_id);
        form.kegiatan_pemerintahan_id = valueText(node.kegiatan_pemerintahan_id);
        form.sub_kegiatan_pemerintahan_id = valueText(node.sub_kegiatan_pemerintahan_id);
        form.opd_unit_id = valueText(node.opd_unit_id);
    } else if (isIndicatorType.value) {
        form.indikator = valueText(node.indikator);
        form.tipe_indikator = valueText(node.tipe_indikator || 'positif');
        form.satuan_indikator_id = valueText(node.satuan_indikator_id);
        form.definisi_operasional = valueText(node.definisi_operasional);
        form.formula = valueText(node.formula);
        form.formulasi_pengukuran = valueText(node.formulasi_pengukuran || node.formula);
        form.tipe_perhitungan = valueText(node.tipe_perhitungan || 'non_kumulatif');
        form.opd_penanggung_jawab_id = valueText(node.opd_penanggung_jawab_id);
        form.pd_penanggung_jawab = valueText(node.pd_penanggung_jawab ?? node.opd_penanggung_jawab?.singkatan ?? node.opd_penanggung_jawab?.nama);
        form.sumber_data = valueText(node.sumber_data);
        form.indikator_tujuan_daerah_id = valueText(node.indikator_tujuan_daerah_id);
        form.indikator_sasaran_daerah_id = valueText(node.indikator_sasaran_daerah_id);
        form.indikator_program_rpjmd_id = valueText(node.indikator_program_rpjmd_id);
    } else if (isTargetType.value) {
        const target = node as unknown as Target;
        form.periode_tahun_id = target.periode_tahun?.id ?? '';
        form.target = valueText(target.target);
        form.target_text = valueText(target.target_text);
        form.pagu = currencyInputText(target.pagu);
        prepareTargetBatchRows();
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

        if (selectedProgramRpjmd.value) {
            form.uraian = valueText(selectedProgramRpjmd.value.nama ?? selectedProgramRpjmd.value.label);
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

        if (!form.sasaran_level && reference.sasaran_sub_kegiatan) {
            form.sasaran_level = valueText(reference.sasaran_sub_kegiatan);
        }
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
        form.sasaran_level = valueText(reference.sasaran_sub_kegiatan);
    },
);

const submitNode = () => {
    if (isTargetType.value || isBudgetType.value) {
        void saveTargetBatchRows();
        return;
    }

    if (isIndicatorType.value) {
        form.formula = form.formulasi_pengukuran;
    }

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

const isAwaitingApproval = computed(() => !props.renstra.is_active_version && ['submitted', 'verified'].includes(props.renstra.status));

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
const normalizedCurrencyPayload = (value?: string | number | null) => {
    const raw = valueText(value).trim();

    if (!raw) {
        return null;
    }

    let normalized = raw.replace(/\s/g, '');

    if (normalized.includes(',') && normalized.includes('.')) {
        normalized = normalized.replace(/\./g, '').replace(',', '.');
    } else if (normalized.includes(',')) {
        normalized = normalized.replace(',', '.');
    } else if (/^\d{1,3}(\.\d{3})+$/.test(normalized)) {
        normalized = normalized.replace(/\./g, '');
    }

    return normalized;
};
const normalizedCurrencyComparable = (value?: string | number | null) => {
    const normalized = normalizedCurrencyPayload(value);

    if (!normalized) {
        return '';
    }

    const asNumber = Number(normalized);

    return Number.isFinite(asNumber) ? String(asNumber) : normalized;
};
const currencyInputText = (value?: string | number | null) => {
    const normalized = normalizedCurrencyPayload(value);

    if (!normalized) {
        return '';
    }

    const asNumber = Number(normalized);

    return Number.isFinite(asNumber) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(asNumber) : valueText(value);
};
const currencyTypingInputText = (value?: string | number | null) => {
    let raw = valueText(value).trim().replace(/\s/g, '');

    if (/^\d{4,}\.\d{1,2}$/.test(raw)) {
        raw = raw.split('.')[0] ?? '';
    }

    const digits = raw.replace(/\D/g, '').replace(/^0+(?=\d)/, '');

    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};
const targetDisplay = (target: Target) => normalizedTargetText(target.target_text || target.target) || '-';
</script>

<template>
    <Head :title="isDedicatedManagementPage ? `Kelola ${activeManagementSectionTitle} - RENSTRA OPD` : 'Cascading Renstra OPD'" />
    <div class="flex flex-col gap-4 p-4">
        <template v-if="!isDedicatedManagementPage">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-normal">{{ renstra.judul }}</h1>
                    <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-800">
                        {{ renstra.version_label }}
                    </span>
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(renstra.status)">
                        {{ statusLabel(renstra.status) }}
                    </span>
                    <span v-if="isAwaitingApproval" class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">
                        Menunggu disetujui
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ renstra.opd?.singkatan || renstra.opd?.nama || '-' }} - {{ renstra.tahun_awal }}-{{ renstra.tahun_akhir }}
                </p>
            </div>
            <div class="document-actions">
                <Link :href="route('renstra-opd.index')" class="document-action document-action--secondary">
                    <ArrowLeft class="size-4" />
                    Kembali
                </Link>
                <Link
                    v-if="can.manage"
                    :href="route('renstra-opd.edit', renstra.id)"
                    class="document-action document-action--secondary"
                >
                    <Pencil class="size-4" />
                    Edit
                </Link>
                <button
                    v-if="can.createRevision"
                    type="button"
                    class="document-action document-action--primary"
                    @click="openRevisionModal"
                >
                    <GitBranch class="size-4" />
                    Buat Perubahan
                </button>
                <button
                    v-if="renstra.jenis_versi === 'perubahan' && can.cancelRevision"
                    type="button"
                    class="document-action document-action--warning"
                    @click="cancelRevision"
                >
                    Batalkan Perubahan
                </button>
                <WorkflowActionButtons
                    module="renstra_opd"
                    :model-id="renstra.id"
                    :status="renstra.status"
                    :can-manage="can.manage"
                    :can-review="can.review"
                    :can-lock="can.lock"
                    :can-withdraw="can.withdraw"
                    :show-verify="false"
                    button-class="document-action document-action--workflow"
                />
            </div>
        </div>

        <div v-if="renstra.perlu_penyesuaian_rpjmd" class="flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-semibold">Penyesuaian RPJMD diperlukan</p>
                <p class="mt-0.5 text-xs leading-5 text-amber-800">
                    RPJMD {{ renstra.rpjmd_perubahan_terbaru?.version_label || 'Perubahan' }} telah disahkan. Buat Perubahan Renstra sebelum mengubah acuan dan cascading.
                </p>
            </div>
            <button
                v-if="can.createRevision"
                type="button"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100"
                @click="openRevisionModal"
            >
                <GitBranch class="size-4" />
                Buat Perubahan
            </button>
            <button
                v-else-if="renstra.jenis_versi === 'perubahan' && can.cancelRevision"
                type="button"
                class="inline-flex shrink-0 items-center justify-center rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100"
                @click="cancelRevision"
            >
                Batalkan Perubahan
            </button>
        </div>

        <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-3">
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
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />

        <section class="overflow-hidden rounded-xl border border-blue-100 bg-card shadow-sm">
            <div class="flex flex-col gap-3 border-b bg-[linear-gradient(135deg,#f8fbff,#eaf4ff)] p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white">
                        <Network class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-950">Acuan RPJMD</h2>
                    </div>
                </div>
                <span class="w-fit rounded-full border border-blue-100 bg-white px-3 py-1.5 text-xs font-semibold text-[#00336C]">
                    {{ renstra.rpjmd ? `${renstra.rpjmd.tahun_awal}-${renstra.rpjmd.tahun_akhir}` : 'Belum terhubung RPJMD' }}
                </span>
            </div>

            <div v-if="hasRpjmdContext" class="grid lg:grid-cols-[23rem_minmax(0,1fr)]">
                <aside class="border-b p-5 lg:border-b-0 lg:border-r">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]">Visi Kabupaten</p>
                        <div class="mt-3 space-y-2">
                            <p v-for="visi in rpjmdContext.visi" :key="visi.id" class="text-sm font-semibold leading-6 text-slate-950">
                                {{ visi.visi }}
                            </p>
                            <p v-if="rpjmdContext.visi.length === 0" class="text-sm text-slate-500">Visi belum diisi.</p>
                        </div>
                    </div>
                    <div class="mt-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Misi</p>
                        <ol class="mt-3 space-y-2.5">
                            <li v-for="misi in rpjmdContext.misi" :key="misi.id" class="grid grid-cols-[1.75rem_minmax(0,1fr)] gap-2 text-sm leading-5 text-slate-700">
                                <span class="font-semibold text-[#00336C]">{{ misi.kode || misi.urutan }}</span>
                                <span>{{ misi.misi }}</span>
                            </li>
                            <li v-if="rpjmdContext.misi.length === 0" class="text-sm text-slate-500">Misi belum diisi.</li>
                        </ol>
                    </div>
                </aside>

                <div class="divide-y p-5">
                    <article
                        v-for="group in rpjmdContext.program_groups"
                        :key="`rpjmd-context-${group.sasaran?.id || group.programs[0]?.id}`"
                        class="py-4 first:pt-0 last:pb-0"
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
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="program in group.programs"
                                :key="program.id"
                                class="inline-flex max-w-full rounded-lg border border-blue-100 bg-blue-50/45 px-3 py-2 text-sm font-semibold leading-5 text-slate-950"
                            >
                                {{ nodeText(program.kode, program.nama) }}
                            </span>
                        </div>
                    </article>
                    <div v-if="rpjmdContext.program_groups.length === 0" class="rounded-xl border border-dashed p-6 text-sm text-slate-500">
                        Belum ada program RPJMD yang relevan dengan OPD ini.
                    </div>
                </div>
            </div>
            <div v-else class="p-6 text-sm text-slate-500">Renstra belum terhubung ke struktur RPJMD.</div>
        </section>
        </template>

        <section class="overflow-hidden rounded-xl border border-blue-100 bg-card shadow-sm">
            <div class="flex flex-col gap-3 bg-[linear-gradient(135deg,#f8fbff,#eaf4ff)] p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white">
                        <Table2 class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-slate-950">
                            {{ isDedicatedManagementPage ? `Kelola ${activeManagementSectionTitle}` : 'Kelola RENSTRA OPD' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{
                                isDedicatedManagementPage
                                    ? 'Lengkapi data, indikator, dan target pada bagian ini.'
                                    : 'Pilih bagian yang akan diisi. Setiap bagian dibuka pada halaman tersendiri.'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="bulkLastSavedAt"
                        class="rounded-full border border-blue-100 bg-white px-3 py-1.5 text-xs font-semibold text-[#00336C]"
                    >
                        Tersimpan {{ bulkLastSavedAt }}
                    </span>
                    <button
                        v-if="can.manage"
                        type="button"
                        class="inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-xs font-semibold transition"
                        :class="
                            bulkAutosaveEnabled
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-[#00336C]/30 hover:text-[#00336C]'
                        "
                        :aria-pressed="bulkAutosaveEnabled"
                        @click="toggleBulkAutosave"
                    >
                        <span
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition"
                            :class="bulkAutosaveEnabled ? 'bg-emerald-600' : 'bg-slate-300'"
                        >
                            <span
                                class="size-4 rounded-full bg-white shadow transition"
                                :class="bulkAutosaveEnabled ? 'translate-x-4' : 'translate-x-0.5'"
                            />
                        </span>
                        Autosave {{ bulkAutosaveEnabled ? 'aktif' : 'mati' }}
                    </button>
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
                        {{ isDedicatedManagementPage ? `Kelola ${activeManagementSectionTitle}` : 'Kelola Data' }}
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

        <div class="grid min-w-0 gap-4 pb-10">
            <section v-if="viewMode === 'bulk' && can.manage && !isDedicatedManagementPage" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="section in bulkInputSections"
                    :key="`manage-${section.key}`"
                    :href="route('renstra-opd.manage', { renstra_opd: renstra.id, section: section.key })"
                    class="group flex min-h-40 flex-col justify-between rounded-xl border border-blue-100 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-[#00336C]/35 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C]/30"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-slate-950">{{ section.title }}</h3>
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="bulkSectionStatus(section).className"
                                >
                                    {{ bulkSectionStatus(section).label }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ bulkSectionSummary(section) }}</p>
                        </div>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#00336C] transition group-hover:bg-[#00336C] group-hover:text-white">
                            <ChevronsRight class="size-4" />
                        </span>
                    </div>
                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#00336C]">
                        Kelola {{ section.title }}
                        <ChevronsRight class="size-4 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </Link>
            </section>

            <section v-else-if="viewMode === 'bulk' && can.manage && isDedicatedManagementPage" class="grid gap-4">
                <section
                    v-for="section in activeBulkInputSections"
                    :key="section.key"
                    class="overflow-hidden rounded-2xl border border-blue-100 bg-card shadow-sm transition"
                    :class="isBulkSectionExpanded(section.key) ? 'border-[#00336C]/30 shadow-md' : 'hover:border-blue-200 hover:shadow-md'"
                >
                    <button
                        v-if="!isDedicatedManagementPage"
                        type="button"
                        class="group grid w-full gap-4 p-5 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C]/30 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                        :class="isBulkSectionExpanded(section.key) ? 'bg-blue-50/55' : 'bg-white hover:bg-blue-50/30'"
                        :aria-expanded="isBulkSectionExpanded(section.key)"
                        @click="toggleBulkSection(section.key)"
                    >
                        <span class="flex min-w-0 items-start gap-4">
                            <span class="mt-1 h-12 w-1.5 shrink-0 rounded-full bg-[#00336C]" />
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-semibold text-slate-950">{{ section.title }}</span>
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :class="bulkSectionStatus(section).className"
                                    >
                                        {{ bulkSectionStatus(section).label }}
                                    </span>
                                </span>
                                <span class="mt-1 block text-sm text-slate-600">{{ bulkSectionSummary(section) }}</span>
                            </span>
                        </span>
                        <span
                            class="inline-flex min-h-11 w-fit items-center gap-2 rounded-lg border border-blue-100 bg-white px-4 text-sm font-semibold text-[#00336C] shadow-sm transition group-hover:border-[#00336C]/35 group-hover:bg-[#00336C] group-hover:text-white"
                        >
                            {{ isBulkSectionExpanded(section.key) ? 'Tutup Form' : 'Buka Form' }}
                            <ChevronDown class="size-4 transition duration-200" :class="isBulkSectionExpanded(section.key) ? 'rotate-180' : ''" />
                        </span>
                    </button>

                    <div v-else class="flex flex-col gap-3 bg-blue-50/55 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold text-slate-950">{{ section.title }}</h3>
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="bulkSectionStatus(section).className"
                                >
                                    {{ bulkSectionStatus(section).label }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ bulkSectionSummary(section) }}</p>
                        </div>
                        <Link
                            :href="route('renstra-opd.show', renstra.id)"
                            class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-[#00336C] bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0a4485] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C]/30"
                        >
                            <ArrowLeft class="size-4" />
                            Kembali
                        </Link>
                    </div>

                    <div v-show="isDedicatedManagementPage || isBulkSectionExpanded(section.key)" class="border-t border-blue-50">
                        <div class="flex flex-col gap-3 border-b border-blue-50 bg-slate-50/70 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm font-medium text-slate-700">
                                Kelola {{ section.title }}
                            </div>
                            <div class="flex flex-wrap gap-2 sm:justify-end">
                            <button
                                v-if="hasBulkRowsToSave(section.rows)"
                                type="button"
                                class="inline-flex min-h-9 items-center gap-2 rounded-md bg-[#00336C] px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-[#0a4485] disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none"
                                :disabled="!hasBulkRowsToSave(section.rows) || isBulkRowsSaving(section.rows)"
                                @click="saveBulkRows(section.rows)"
                            >
                                <Save class="size-3.5" />
                                Simpan Data
                            </button>
                            <button
                                v-if="section.key !== 'sub-kegiatan'"
                                type="button"
                                class="inline-flex min-h-10 items-center gap-2 rounded-md border text-xs font-semibold transition"
                                :class="sectionPrimaryButtonClass()"
                                @click="selectNodeType(section.primaryType)"
                            >
                                <Plus class="size-3.5" />
                                {{ section.primaryLabel }}
                            </button>
                            </div>
                        </div>

                        <div
                            v-if="section.key === 'sub-kegiatan' && subKegiatanProgramFolders.length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            <Target class="mx-auto size-10 text-muted-foreground" />
                            <p class="mt-3 font-semibold text-foreground">Belum ada kegiatan OPD</p>
                            <p class="mt-1">Tambahkan kegiatan terlebih dahulu sebelum mengisi sub kegiatan.</p>
                        </div>

                        <div
                            v-else-if="section.key !== 'sub-kegiatan' && sectionParentRows(section).length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            <Target class="mx-auto size-10 text-muted-foreground" />
                            <p class="mt-3 font-semibold text-foreground">{{ section.emptyTitle }}</p>
                            <p class="mt-1">{{ section.emptyDescription }}</p>
                        </div>

                        <div v-else class="grid gap-4 p-4 sm:p-5">
                            <div v-if="section.key === 'program'" class="space-y-4">
                                <div class="rounded-2xl border border-blue-100 bg-white shadow-sm">
                                    <div class="flex flex-col gap-3 border-b border-blue-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                                        <p class="text-sm font-semibold text-slate-950">Pilih sasaran OPD</p>
                                        <div class="relative w-full lg:w-80">
                                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                            <input
                                                v-model="programFocusSearch"
                                                type="search"
                                                class="h-10 w-full rounded-lg border border-blue-100 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-[#00336C]/50 focus:ring-2 focus:ring-[#00336C]/15"
                                                placeholder="Cari sasaran atau program"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2 p-3">
                                        <div
                                            v-for="sasaran in filteredProgramFocusItems"
                                            :key="sasaran.key"
                                            class="overflow-hidden rounded-xl border border-blue-100 bg-white"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-blue-50/60"
                                                @click="toggleProgramSasaran(sasaran.sasaranId)"
                                            >
                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#00336C]">
                                                    <FolderOpen v-if="isProgramSasaranOpen(sasaran.sasaranId)" class="size-4" />
                                                    <Folder v-else class="size-4" />
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="line-clamp-2 text-sm font-semibold leading-5 text-slate-950">
                                                        {{ sasaran.sasaranName }}
                                                    </span>
                                                    <span class="mt-1 flex flex-wrap gap-1.5 text-[11px] font-semibold text-slate-500">
                                                        <span>{{ sasaran.programCount }} program</span>
                                                        <span class="text-slate-300">/</span>
                                                        <span>{{ sasaran.indicatorCount }} indikator</span>
                                                    </span>
                                                </span>
                                                <ChevronDown
                                                    class="size-4 shrink-0 text-slate-500 transition"
                                                    :class="isProgramSasaranOpen(sasaran.sasaranId) ? 'rotate-180' : ''"
                                                />
                                            </button>

                                            <div v-if="isProgramSasaranOpen(sasaran.sasaranId)" class="border-t border-blue-100 bg-slate-50/60 px-4 py-3">
                                                <div v-if="sasaran.programs.length > 0" class="ml-6 border-l border-blue-200 pl-5 sm:ml-11">
                                                    <button
                                                        v-for="(item, itemIndex) in sasaran.programs"
                                                        :key="item.key"
                                                        type="button"
                                                        class="relative mb-2 flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition last:mb-0"
                                                        :class="
                                                            activeProgramFocus?.programId === item.programId
                                                                ? 'border-[#00336C] bg-[#00336C] text-white shadow-sm'
                                                                : 'border-blue-100 bg-white text-slate-800 hover:border-blue-200 hover:bg-blue-50/50'
                                                        "
                                                        @click="selectProgramFocus(sasaran.sasaranId, item.programId)"
                                                    >
                                                        <span
                                                            class="pointer-events-none absolute -left-5 top-1/2 hidden h-px w-5 -translate-y-1/2 bg-blue-200 sm:block"
                                                        />
                                                        <span
                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-xs font-bold"
                                                            :class="
                                                                activeProgramFocus?.programId === item.programId
                                                                    ? 'bg-white/15 text-white'
                                                                    : 'bg-blue-50 text-[#00336C]'
                                                            "
                                                        >
                                                            {{ itemIndex + 1 }}
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="line-clamp-2 text-sm font-semibold leading-5">
                                                                {{ item.programName }}
                                                            </span>
                                                            <span
                                                                class="mt-1 block text-[11px] font-semibold"
                                                                :class="activeProgramFocus?.programId === item.programId ? 'text-blue-100' : 'text-slate-500'"
                                                            >
                                                                {{ item.indicatorCount }} indikator
                                                            </span>
                                                        </span>
                                                    </button>
                                                </div>

                                                <div v-else class="ml-6 border-l border-blue-200 px-5 py-3 text-sm text-slate-500 sm:ml-11">
                                                    Belum ada program pada sasaran ini.
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-if="filteredProgramFocusItems.length === 0"
                                            class="rounded-xl border border-dashed border-blue-200 p-6 text-center text-sm text-slate-500"
                                        >
                                            Sasaran atau program tidak ditemukan.
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="activeProgramFocus"
                                    ref="programDetailRef"
                                    class="rounded-xl border border-blue-100 bg-blue-50/40 px-4 py-3"
                                >
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]/70">Sasaran OPD</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ activeProgramFocus.sasaranName }}</p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-[#00336C]/70">Program OPD</p>
                                    <p class="mt-1 text-base font-semibold leading-6 text-slate-950">{{ activeProgramFocus.programName }}</p>
                                </div>
                                <div
                                    v-else
                                    class="rounded-xl border border-dashed border-blue-200 bg-blue-50/30 p-6 text-center text-sm text-slate-600"
                                >
                                    Buka sasaran, lalu pilih program untuk melihat rinciannya.
                                </div>
                            </div>

                            <div v-else-if="section.key === 'kegiatan'" class="space-y-4">
                                <div class="rounded-2xl border border-blue-100 bg-white shadow-sm">
                                    <div class="flex flex-col gap-3 border-b border-blue-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                                        <p class="text-sm font-semibold text-slate-950">Pilih program OPD</p>
                                        <div class="relative w-full lg:w-80">
                                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                            <input
                                                v-model="kegiatanFocusSearch"
                                                type="search"
                                                class="h-10 w-full rounded-lg border border-blue-100 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-[#00336C]/50 focus:ring-2 focus:ring-[#00336C]/15"
                                                placeholder="Cari program atau kegiatan"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2 p-3">
                                        <div
                                            v-for="program in filteredKegiatanFocusItems"
                                            :key="program.key"
                                            class="overflow-hidden rounded-xl border border-blue-100 bg-white"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-blue-50/60"
                                                @click="toggleKegiatanProgram(program.programId)"
                                            >
                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#00336C]">
                                                    <FolderOpen v-if="isKegiatanProgramOpen(program.programId)" class="size-4" />
                                                    <Folder v-else class="size-4" />
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="line-clamp-2 text-sm font-semibold leading-5 text-slate-950">
                                                        {{ program.programName }}
                                                    </span>
                                                    <span class="mt-1 flex flex-wrap gap-1.5 text-[11px] font-semibold text-slate-500">
                                                        <span>{{ program.kegiatanCount }} kegiatan</span>
                                                        <span class="text-slate-300">/</span>
                                                        <span>{{ program.indicatorCount }} indikator</span>
                                                    </span>
                                                </span>
                                                <ChevronDown
                                                    class="size-4 shrink-0 text-slate-500 transition"
                                                    :class="isKegiatanProgramOpen(program.programId) ? 'rotate-180' : ''"
                                                />
                                            </button>

                                            <div v-if="isKegiatanProgramOpen(program.programId)" class="border-t border-blue-100 bg-slate-50/60 px-4 py-3">
                                                <div v-if="program.kegiatan.length > 0" class="ml-6 border-l border-blue-200 pl-5 sm:ml-11">
                                                    <button
                                                        v-for="(item, itemIndex) in program.kegiatan"
                                                        :key="item.key"
                                                        type="button"
                                                        class="relative mb-2 flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition last:mb-0"
                                                        :class="
                                                            activeKegiatanFocus?.kegiatanId === item.kegiatanId
                                                                ? 'border-[#00336C] bg-[#00336C] text-white shadow-sm'
                                                                : 'border-blue-100 bg-white text-slate-800 hover:border-blue-200 hover:bg-blue-50/50'
                                                        "
                                                        @click="selectKegiatanFocus(program.programId, item.kegiatanId)"
                                                    >
                                                        <span
                                                            class="pointer-events-none absolute -left-5 top-1/2 hidden h-px w-5 -translate-y-1/2 bg-blue-200 sm:block"
                                                        />
                                                        <span
                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-xs font-bold"
                                                            :class="
                                                                activeKegiatanFocus?.kegiatanId === item.kegiatanId
                                                                    ? 'bg-white/15 text-white'
                                                                    : 'bg-blue-50 text-[#00336C]'
                                                            "
                                                        >
                                                            {{ itemIndex + 1 }}
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="line-clamp-2 text-sm font-semibold leading-5">
                                                                {{ item.kegiatanName }}
                                                            </span>
                                                            <span
                                                                class="mt-1 block text-[11px] font-semibold"
                                                                :class="activeKegiatanFocus?.kegiatanId === item.kegiatanId ? 'text-blue-100' : 'text-slate-500'"
                                                            >
                                                                {{ item.indicatorCount }} indikator
                                                            </span>
                                                        </span>
                                                    </button>
                                                </div>

                                                <div v-else class="ml-6 border-l border-blue-200 px-5 py-3 text-sm text-slate-500 sm:ml-11">
                                                    Belum ada kegiatan pada program ini.
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-if="filteredKegiatanFocusItems.length === 0"
                                            class="rounded-xl border border-dashed border-blue-200 p-6 text-center text-sm text-slate-500"
                                        >
                                            Program atau kegiatan tidak ditemukan.
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="activeKegiatanFocus"
                                    ref="kegiatanDetailRef"
                                    class="rounded-xl border border-blue-100 bg-blue-50/40 px-4 py-3"
                                >
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]/70">Program OPD</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ activeKegiatanFocus.programName }}</p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-[#00336C]/70">Kegiatan OPD</p>
                                    <p class="mt-1 text-base font-semibold leading-6 text-slate-950">{{ activeKegiatanFocus.kegiatanName }}</p>
                                </div>
                                <div
                                    v-else
                                    class="rounded-xl border border-dashed border-blue-200 bg-blue-50/30 p-6 text-center text-sm text-slate-600"
                                >
                                    Buka program, lalu pilih kegiatan untuk melihat rinciannya.
                                </div>
                            </div>

                            <div v-else-if="section.key === 'sub-kegiatan'" class="space-y-4">
                                <div class="rounded-2xl border border-blue-100 bg-white shadow-sm">
                                    <div class="flex flex-col gap-3 border-b border-blue-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-950">Pilih program dan kegiatan</p>
                                        </div>
                                        <div class="relative w-full lg:w-80">
                                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                            <input
                                                v-model="subKegiatanFocusSearch"
                                                type="search"
                                                class="h-10 w-full rounded-lg border border-blue-100 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-[#00336C]/50 focus:ring-2 focus:ring-[#00336C]/15"
                                                placeholder="Cari program atau kegiatan"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2 p-3">
                                        <div
                                            v-for="program in filteredSubKegiatanFocusItems"
                                            :key="program.key"
                                            class="overflow-hidden rounded-xl border border-blue-100 bg-white"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-blue-50/60"
                                                @click="toggleSubKegiatanProgram(program.programId)"
                                            >
                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#00336C]">
                                                    <FolderOpen v-if="isSubKegiatanProgramOpen(program.programId)" class="size-4" />
                                                    <Folder v-else class="size-4" />
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="line-clamp-2 text-sm font-semibold leading-5 text-slate-950">
                                                        {{ program.programName }}
                                                    </span>
                                                    <span class="mt-1 flex flex-wrap gap-1.5 text-[11px] font-semibold text-slate-500">
                                                        <span>{{ program.kegiatanCount }} kegiatan</span>
                                                        <span class="text-slate-300">/</span>
                                                        <span>{{ program.subKegiatanCount }} sub kegiatan</span>
                                                        <span class="text-slate-300">/</span>
                                                        <span>{{ program.indicatorCount }} indikator</span>
                                                    </span>
                                                </span>
                                                <ChevronDown
                                                    class="size-4 shrink-0 text-slate-500 transition"
                                                    :class="isSubKegiatanProgramOpen(program.programId) ? 'rotate-180' : ''"
                                                />
                                            </button>

                                            <div v-if="isSubKegiatanProgramOpen(program.programId)" class="border-t border-blue-100 bg-slate-50/60 px-4 py-3">
                                                <div v-if="program.kegiatan.length > 0" class="ml-6 border-l border-blue-200 pl-5 sm:ml-11">
                                                    <button
                                                        v-for="(item, itemIndex) in program.kegiatan"
                                                        :key="item.key"
                                                        type="button"
                                                        class="relative mb-2 flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition last:mb-0"
                                                        :class="
                                                            activeSubKegiatanFocus?.kegiatanId === item.kegiatanId
                                                                ? 'border-[#00336C] bg-[#00336C] text-white shadow-sm'
                                                                : 'border-blue-100 bg-white text-slate-800 hover:border-blue-200 hover:bg-blue-50/50'
                                                        "
                                                        @click="selectSubKegiatanKegiatan(program.programId, item.kegiatanId)"
                                                    >
                                                        <span
                                                            class="pointer-events-none absolute -left-5 top-1/2 hidden h-px w-5 -translate-y-1/2 bg-blue-200 sm:block"
                                                        />
                                                        <span
                                                            class="flex size-7 shrink-0 items-center justify-center rounded-md text-xs font-bold"
                                                            :class="
                                                                activeSubKegiatanFocus?.kegiatanId === item.kegiatanId
                                                                    ? 'bg-white/15 text-white'
                                                                    : 'bg-blue-50 text-[#00336C]'
                                                            "
                                                        >
                                                            {{ itemIndex + 1 }}
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="line-clamp-2 text-sm font-semibold leading-5">
                                                                {{ item.kegiatanName }}
                                                            </span>
                                                            <span
                                                                class="mt-1 flex flex-wrap gap-1.5 text-[11px] font-semibold"
                                                                :class="
                                                                    activeSubKegiatanFocus?.kegiatanId === item.kegiatanId
                                                                        ? 'text-blue-100'
                                                                        : 'text-slate-500'
                                                                "
                                                            >
                                                                <span>{{ item.subKegiatanCount }} sub kegiatan</span>
                                                                <span class="opacity-60">/</span>
                                                                <span>{{ item.indicatorCount }} indikator</span>
                                                            </span>
                                                        </span>
                                                    </button>
                                                </div>

                                                <div v-else class="ml-6 border-l border-blue-200 px-5 py-3 text-sm text-slate-500 sm:ml-11">
                                                    Belum ada kegiatan pada program ini.
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-if="filteredSubKegiatanFocusItems.length === 0"
                                            class="rounded-xl border border-dashed border-blue-200 p-6 text-center text-sm text-slate-500"
                                        >
                                            Program atau kegiatan tidak ditemukan.
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="activeSubKegiatanFocus"
                                    ref="subKegiatanDetailRef"
                                    class="flex flex-col gap-3 rounded-xl border border-blue-100 bg-blue-50/40 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]/70">
                                            {{ activeSubKegiatanFocus.programName }}
                                        </p>
                                        <h3 class="mt-1 line-clamp-2 text-base font-semibold leading-6 text-slate-950">
                                            {{ activeSubKegiatanFocus.kegiatanName }}
                                        </h3>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0a4485]"
                                        @click="openFocusedSubKegiatanModal"
                                    >
                                        <Plus class="size-4" />
                                        Sub Kegiatan OPD
                                    </button>
                                </div>
                                <div
                                    v-else
                                    class="rounded-xl border border-dashed border-blue-200 bg-blue-50/30 p-6 text-center text-sm text-slate-600"
                                >
                                    Buka program, lalu pilih kegiatan untuk melihat sub kegiatan.
                                </div>
                            </div>

                            <div
                                v-for="group in section.key === 'sub-kegiatan'
                                    ? focusedSubKegiatanGroups(section)
                                    : section.key === 'program'
                                      ? focusedProgramGroups(section)
                                      : section.key === 'kegiatan'
                                        ? focusedKegiatanGroups(section)
                                        : groupedSectionParentRows(section)"
                                :key="`section-group-${group.key}`"
                                class="relative grid gap-3"
                                :class="sectionIndentClass(section)"
                            >
                                <span
                                    v-if="shouldGroupSection(section)"
                                    class="pointer-events-none absolute bottom-2 left-2 top-14 hidden w-px bg-gradient-to-b from-blue-300 via-blue-100 to-transparent md:block"
                                />
                                <div
                                    v-if="shouldGroupSection(section)"
                                    class="relative overflow-hidden rounded-xl border border-l-4 px-4 py-3 shadow-sm"
                                    :class="sectionGroupClass(section)"
                                >
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <div
                                                v-if="sectionGroupProgramLabel(section, group)"
                                                class="mb-2 border-b border-cyan-100 pb-2"
                                            >
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#00336C]/70">Program</p>
                                                <p class="mt-1 line-clamp-2 text-sm font-semibold leading-5 text-slate-950">
                                                    {{ sectionGroupProgramLabel(section, group) }}
                                                </p>
                                            </div>
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#00336C]/70">
                                                {{ sectionParentContextLabel(section) }}
                                            </p>
                                            <p class="mt-1 line-clamp-2 text-sm font-semibold leading-5 text-slate-950">
                                                {{ group.label }}
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex w-fit items-center rounded-full border border-blue-100 bg-white px-3 py-1 text-xs font-semibold text-[#00336C]"
                                        >
                                            {{ group.rows.length }} {{ sectionGroupItemLabel(section) }}
                                        </span>
                                    </div>
                                </div>

                                <article
                                    v-for="(parentRow, parentIndex) in group.rows"
                                    :key="`grouped-${section.key}-${parentRow.key}`"
                                    class="relative overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm transition hover:border-blue-200 hover:shadow-md"
                                    :class="sectionArticleClass(section)"
                                >
                            <div
                                class="grid gap-3 border-b bg-gradient-to-r p-4 lg:grid-cols-[4rem_minmax(0,1fr)_auto] lg:items-start"
                                :class="sectionHeaderTintClass(section)"
                            >
                                <div class="flex items-center gap-2 lg:justify-center">
                                    <span
                                        class="flex size-10 items-center justify-center rounded-full text-sm font-bold shadow-sm ring-1 ring-white/70"
                                        :class="sectionIndexBadgeClass(section)"
                                    >
                                        {{ parentIndex + 1 }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]">{{ section.primaryLabel }}</p>
                                    <h3 class="mt-1 whitespace-pre-line text-base font-semibold leading-6 text-slate-950">
                                        {{ bulkRowPrimaryText(parentRow) }}
                                    </h3>
                                    <p
                                        v-if="section.key !== 'sub-kegiatan' && parentRowSubtext(parentRow)"
                                        class="mt-2 max-w-4xl text-sm leading-6 text-slate-600"
                                    >
                                        {{ parentRowSubtext(parentRow) }}
                                    </p>
                                    <p v-if="parentRow.error" class="mt-2 text-xs font-medium text-red-600">{{ parentRow.error }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2 lg:justify-end">
                                    <button
                                        v-if="hasBulkRowsToSave(bulkRowsForSingleSave(parentRow))"
                                        type="button"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-md bg-[#00336C] px-3 text-xs font-semibold text-white transition hover:bg-[#0a4485] disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500"
                                        :disabled="isBulkRowsSaving(bulkRowsForSingleSave(parentRow))"
                                        @click="saveBulkRows(bulkRowsForSingleSave(parentRow))"
                                    >
                                        <Save class="size-3.5" />
                                        Simpan
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-blue-100 bg-white px-3 text-xs font-semibold text-[#00336C] transition hover:border-[#00336C]/40 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-400 disabled:hover:bg-white"
                                        :disabled="!parentRowCanAddIndicator(parentRow)"
                                        @click="section.key === 'sub-kegiatan' ? openSubKegiatanBudgetModal(parentRow) : openBulkIndicatorModal(section, parentRow)"
                                    >
                                        <Plus class="size-3.5" />
                                        {{ section.key === 'sub-kegiatan' ? 'Pagu Indikatif' : section.indicatorLabel }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-blue-100 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                        @click="editBulkRow(parentRow)"
                                    >
                                        <Pencil class="size-3.5" />
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-red-100 bg-white px-3 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                        @click="deleteBulkRow(parentRow)"
                                    >
                                        <Trash2 class="size-3.5" />
                                        Hapus
                                    </button>
                                </div>
                            </div>

                            <div class="border-t border-blue-100 bg-white p-4 sm:p-5">
                                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-950">{{ section.indicatorLabel }}</h4>
                                        <p class="text-xs text-slate-500">Indikator, satuan, dan target 5 tahunan.</p>
                                    </div>
                                    <span class="w-fit rounded-full border border-blue-100 bg-white px-3 py-1 text-xs font-semibold text-[#00336C]">
                                        {{ sectionIndicatorRows(section, parentRow).length }} indikator
                                    </span>
                                </div>

                                <div class="overflow-x-auto rounded-xl border border-blue-100 bg-white shadow-inner shadow-blue-950/5">
                                    <table class="w-full min-w-[1120px] table-fixed text-left text-sm">
                                        <colgroup>
                                            <col style="width: 56px" />
                                            <col />
                                            <col style="width: 112px" />
                                            <col v-if="baselinePeriod" style="width: 76px" />
                                            <col
                                                v-for="period in periodColumns"
                                                :key="`indicator-target-col-${section.key}-${parentRow.key}-${period.id}`"
                                                style="width: 72px"
                                            />
                                            <col style="width: 120px" />
                                        </colgroup>
                                        <thead class="border-b bg-blue-50 text-xs uppercase text-[#00336C]">
                                            <tr>
                                                <th rowspan="2" class="border-b border-r border-blue-100 px-3 py-3 text-center">No</th>
                                                <th rowspan="2" class="border-b border-r border-blue-100 px-3 py-3">Indikator</th>
                                                <th rowspan="2" class="border-b border-r border-blue-100 px-3 py-3 text-center">Satuan</th>
                                                <th v-if="baselinePeriod" class="border-b border-r border-amber-100 bg-amber-50/70 px-2 py-3 text-center">
                                                    Baseline
                                                </th>
                                                <th :colspan="periodColumns.length" class="border-b border-r border-blue-100 px-2 py-3 text-center">
                                                    Target
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="sticky right-0 z-20 border-b border-l border-blue-100 bg-blue-50 px-2 py-3 text-center shadow-[-10px_0_18px_rgba(15,23,42,0.08)]"
                                                >
                                                    Aksi
                                                </th>
                                            </tr>
                                            <tr>
                                                <th v-if="baselinePeriod" class="border-r border-amber-100 bg-amber-50/70 px-2 py-3 text-center">
                                                    {{ baselinePeriod.year }}
                                                </th>
                                                <th
                                                    v-for="period in periodColumns"
                                                    :key="`indicator-target-head-${section.key}-${parentRow.key}-${period.id}`"
                                                    class="border-r border-blue-100 px-2 py-3 text-center last:border-r-0"
                                                >
                                                    {{ period.year }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(indicatorRow, indicatorIndex) in sectionIndicatorRows(section, parentRow)"
                                                :key="`indicator-${section.key}-${indicatorRow.key}`"
                                                class="border-b align-top last:border-0 hover:bg-blue-50/30"
                                            >
                                                <td class="border-r border-blue-50 px-3 py-4 text-center">
                                                    <span class="text-sm font-semibold text-slate-900">{{ indicatorIndex + 1 }}</span>
                                                </td>
                                                <td class="border-r border-blue-50 px-3 py-4">
                                                    <p class="whitespace-pre-line font-semibold leading-6 text-slate-950">
                                                        {{ bulkRowPrimaryText(indicatorRow) }}
                                                    </p>
                                                    <p v-if="indicatorRow.definisi_operasional" class="mt-2 line-clamp-2 text-xs leading-5 text-slate-600">
                                                        DO: {{ indicatorRow.definisi_operasional }}
                                                    </p>
                                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                                        <span
                                                            v-if="indicatorRow.tipe_perhitungan"
                                                            class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600"
                                                        >
                                                            {{ indicatorRow.tipe_perhitungan === 'kumulatif' ? 'Kumulatif' : 'Non-kumulatif' }}
                                                        </span>
                                                        <span
                                                            v-if="indicatorRow.sumber_data"
                                                            class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-medium text-[#00336C]"
                                                        >
                                                            {{ indicatorRow.sumber_data }}
                                                        </span>
                                                    </div>
                                                    <p v-if="indicatorRow.error" class="mt-2 text-xs font-medium text-red-600">{{ indicatorRow.error }}</p>
                                                </td>
                                                <td class="border-r border-blue-50 px-3 py-4 text-center">
                                                    <span class="font-semibold text-slate-900">
                                                        {{ optionById(satuanOptions, indicatorRow.satuan_indikator_id)?.label ?? '-' }}
                                                    </span>
                                                </td>
                                                <td
                                                    v-if="baselinePeriod"
                                                    class="border-r border-amber-100 bg-amber-50/30 px-2 py-4 text-center text-sm font-semibold tabular-nums"
                                                    :class="baselineTargetSummary(indicatorRow)?.target !== '-' ? 'text-slate-950' : 'text-slate-400'"
                                                >
                                                    {{ baselineTargetSummary(indicatorRow)?.target ?? '-' }}
                                                </td>
                                                <td
                                                    v-for="target in bulkTargetSummaries(indicatorRow)"
                                                    :key="`target-summary-${indicatorRow.key}-${target.id}`"
                                                    class="border-r border-blue-50 px-2 py-4 text-center text-sm font-semibold tabular-nums"
                                                    :class="target.target !== '-' ? 'text-slate-950' : 'text-slate-400'"
                                                >
                                                    {{ target.target }}
                                                </td>
                                                <td class="sticky right-0 border-l border-blue-50 bg-white px-2 py-4 shadow-[-10px_0_18px_rgba(15,23,42,0.06)]">
                                                    <div class="grid justify-items-stretch gap-1.5">
                                                        <button
                                                            v-if="hasBulkRowsToSave(bulkRowsForSingleSave(indicatorRow))"
                                                            type="button"
                                                            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md bg-[#00336C] px-2 text-xs font-semibold text-white transition hover:bg-[#0a4485] disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500"
                                                            :disabled="isBulkRowsSaving(bulkRowsForSingleSave(indicatorRow))"
                                                            @click="saveBulkRows(bulkRowsForSingleSave(indicatorRow))"
                                                        >
                                                            <Save class="size-3.5" />
                                                            Simpan
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md border border-blue-100 bg-white px-2 text-xs font-semibold text-[#00336C] transition hover:border-[#00336C]/40 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-400 disabled:hover:bg-white"
                                                            :disabled="!indicatorRow.id"
                                                            @click="openBulkTargetModal(indicatorRow)"
                                                        >
                                                            <Plus class="size-3.5" />
                                                            {{ targetButtonLabel(indicatorRow) }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md border border-blue-100 bg-white px-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                                            @click="editBulkRow(indicatorRow)"
                                                        >
                                                            <Pencil class="size-3.5" />
                                                            Edit
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md border border-red-100 bg-white px-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                                            @click="deleteBulkRow(indicatorRow)"
                                                        >
                                                            <Trash2 class="size-3.5" />
                                                            Hapus
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="sectionIndicatorRows(section, parentRow).length === 0">
                                                <td
                                                    :colspan="periodColumns.length + 4 + (baselinePeriod ? 1 : 0)"
                                                    class="px-3 py-6 text-center text-sm text-slate-500"
                                                >
                                                    Belum ada indikator untuk {{ bulkRowPrimaryText(parentRow) }}.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                                </article>
                                <div
                                    v-if="section.key === 'sub-kegiatan' && group.rows.length === 0"
                                    class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/35 p-8 text-center text-sm text-slate-600"
                                >
                                    <Target class="mx-auto size-9 text-[#00336C]/50" />
                                    <p class="mt-3 font-semibold text-slate-950">Belum ada sub kegiatan</p>
                                    <p class="mt-1">Tambahkan sub kegiatan untuk kegiatan yang dipilih.</p>
                                </div>
                            </div>

                            <div v-if="false" class="hidden">
                        <table class="w-full min-w-[2900px] border-separate border-spacing-0 text-left text-sm">
                            <thead class="sticky top-0 z-10 border-b bg-blue-50 text-xs uppercase text-[#00336C] backdrop-blur">
                                <tr>
                                    <th
                                        rowspan="2"
                                        class="sticky left-0 z-20 min-w-20 border-b border-r border-blue-100 bg-blue-50 px-3 py-3 text-center shadow-[8px_0_16px_rgba(15,23,42,0.06)]"
                                    >
                                        No
                                    </th>
                                    <th colspan="7" class="border-b border-blue-100 px-3 py-3 text-center">Data Utama</th>
                                    <th colspan="7" class="border-b border-blue-100 px-3 py-3 text-center">Indikator Kinerja</th>
                                    <th :colspan="periodColumns.length + 1" class="border-b border-blue-100 px-3 py-3 text-center">
                                        Target 5 Tahunan
                                    </th>
                                    <th
                                        rowspan="2"
                                        class="min-w-36 border-b border-blue-100 px-3 py-3 text-center"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                                <tr>
                                    <th class="min-w-52 border-b border-blue-100 px-3 py-3">Jenis Data</th>
                                    <th class="min-w-28 border-b border-blue-100 px-3 py-3">Urutan</th>
                                    <th class="min-w-72 border-b border-blue-100 px-3 py-3">Induk</th>
                                    <th class="min-w-64 border-b border-blue-100 px-3 py-3">Referensi RPJMD</th>
                                    <th class="min-w-80 border-b border-blue-100 px-3 py-3">Master Resmi</th>
                                    <th class="min-w-72 border-b border-blue-100 px-3 py-3">Sasaran Level</th>
                                    <th class="min-w-80 border-b border-blue-100 px-3 py-3">Uraian/Nama</th>
                                    <th class="min-w-80 border-b border-blue-100 px-3 py-3">Indikator</th>
                                    <th class="min-w-80 border-b border-blue-100 px-3 py-3">Definisi Operasional</th>
                                    <th class="min-w-44 border-b border-blue-100 px-3 py-3">Satuan</th>
                                    <th class="min-w-48 border-b border-blue-100 px-3 py-3">Tipe Perhitungan</th>
                                    <th class="min-w-72 border-b border-blue-100 px-3 py-3">Formulasi Pengukuran</th>
                                    <th class="min-w-72 border-b border-blue-100 px-3 py-3">PD Penanggung Jawab</th>
                                    <th class="min-w-52 border-b border-blue-100 px-3 py-3">Sumber Data</th>
                                    <th
                                        v-for="period in periodColumns"
                                        :key="`bulk-period-head-${section.key}-${period.id}`"
                                        class="min-w-40 border-b border-blue-100 px-3 py-3 text-center"
                                    >
                                        {{ period.year }}
                                    </th>
                                    <th class="min-w-56 border-b border-blue-100 px-3 py-3">Keuangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, rowIndex) in visibleBulkRows(section.rows)"
                                    :key="`${section.key}-${row.key}`"
                                    class="border-b align-top last:border-0 hover:bg-blue-50/30"
                                >
                                    <td
                                        class="sticky left-0 z-10 border-b border-r bg-card px-3 py-3 text-center shadow-[8px_0_16px_rgba(15,23,42,0.05)]"
                                    >
                                        <div class="grid justify-items-center gap-1">
                                            <span class="text-sm font-semibold text-slate-900">{{ rowIndex + 1 }}</span>
                                            <span
                                                v-if="row.saveState !== 'idle'"
                                                class="inline-flex w-fit rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                :class="bulkStatusClass(row)"
                                            >
                                                {{ bulkStatusLabel(row) }}
                                            </span>
                                            <p v-if="row.error" class="max-w-32 text-[11px] leading-4 text-red-700">{{ row.error }}</p>
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
                                        <input
                                            v-model="row.urutan"
                                            :disabled="!isBulkTextRow(row) && !isBulkIndicatorRow(row)"
                                            type="number"
                                            min="1"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
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
                                        <textarea
                                            v-model="row.definisi_operasional"
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
                                        <select
                                            v-model="row.tipe_perhitungan"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @change="scheduleBulkAutosave(row)"
                                        >
                                            <option value="non_kumulatif">Non-kumulatif</option>
                                            <option value="kumulatif">Kumulatif</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <textarea
                                            v-model="row.formulasi_pengukuran"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            rows="3"
                                            class="w-full rounded-md border bg-background px-2 py-2 text-xs leading-5 outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-if="isBulkIndicatorRow(row)"
                                            v-model="row.pd_penanggung_jawab"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C]"
                                            placeholder="Ketik PD"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="row.sumber_data"
                                            :disabled="!isBulkIndicatorRow(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            @input="scheduleBulkAutosave(row)"
                                        />
                                    </td>
                                    <td
                                        v-for="period in periodColumns"
                                        :key="`target-cell-${section.key}-${row.key}-${period.id}`"
                                        class="px-2 py-3"
                                    >
                                        <input
                                            v-if="isBulkIndicatorRow(row)"
                                            :value="targetValueForIndicator(row, period.id)"
                                            :disabled="!canEditTargetColumns(row)"
                                            class="min-h-10 w-full rounded-md border bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                            :placeholder="canEditTargetColumns(row) ? '-' : 'Simpan dulu'"
                                            @input="setIndicatorTargetValue(row, period.id, inputEventValue($event))"
                                        />
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div v-if="showTargetFinance(row)" class="grid gap-2">
                                            <label
                                                v-for="period in periodColumns"
                                                :key="`pagu-cell-${section.key}-${row.key}-${period.id}`"
                                                class="grid gap-1 text-[11px] font-semibold text-slate-500"
                                            >
                                                <span>{{ period.year }}</span>
                                                <input
                                                    :value="targetPaguForIndicator(row, period.id)"
                                                    :disabled="!canEditTargetColumns(row)"
                                                    type="text"
                                                    inputmode="numeric"
                                                    class="min-h-9 rounded-md border bg-background px-2 text-xs font-normal text-slate-900 outline-none focus:ring-2 focus:ring-[#00336C] disabled:bg-slate-100 disabled:text-slate-400"
                                                    @input="setIndicatorTargetPagu(row, period.id, inputEventValue($event))"
                                                />
                                            </label>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="inline-flex overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                                            <button
                                                type="button"
                                                class="inline-flex min-h-9 items-center gap-2 px-3 text-xs font-semibold text-[#00336C] transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-400 disabled:hover:bg-white"
                                                :disabled="!hasBulkRowsToSave(bulkRowsForSingleSave(row)) || isBulkRowsSaving(bulkRowsForSingleSave(row))"
                                                @click="saveBulkRows(bulkRowsForSingleSave(row))"
                                            >
                                                <Save class="size-3.5" />
                                                Simpan
                                            </button>
                                            <button
                                                v-if="row.isNew"
                                                type="button"
                                                class="inline-flex min-h-9 items-center justify-center border-l border-blue-100 px-3 text-red-600 transition hover:bg-red-50"
                                                @click="removeBulkDraft(row)"
                                                aria-label="Hapus baris"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            <section v-else-if="viewMode === 'table'" class="mb-12 w-full min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-[#00336C]">
                            <Table2 class="size-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-semibold">Preview Tabel Renstra OPD</h2>
                            <p class="text-sm text-muted-foreground">Tujuan, sasaran, bidang urusan, program, kegiatan, sub kegiatan, indikator, target, dan pagu indikatif.</p>
                        </div>
                    </div>
                    <a
                        :href="route('renstra-opd.preview.export', renstra.id)"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-[#00336C] shadow-sm transition hover:border-[#00336C]/40 hover:bg-blue-50"
                    >
                        <FileText class="size-4" />
                        Export Excel
                    </a>
                </div>
                <div
                    class="w-full overflow-x-auto overscroll-x-contain pb-6 [scrollbar-width:thin] [&::-webkit-scrollbar]:h-2 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300 [&::-webkit-scrollbar-thumb]:bg-clip-padding [&::-webkit-scrollbar-track]:bg-slate-100"
                >
                    <table class="w-max min-w-[1720px] border-collapse text-left text-[13px] leading-5">
                        <thead class="text-xs uppercase text-slate-950">
                            <tr class="bg-white">
                                <th rowspan="3" class="w-[290px] border border-slate-900 px-3 py-3 text-center align-middle font-bold">
                                    TUJUAN / SASARAN / BIDANG URUSAN / PROGRAM / KEGIATAN / SUB. KEGIATAN OUTPUT
                                </th>
                                <th rowspan="3" class="w-[260px] border border-slate-900 px-3 py-3 text-center align-middle font-bold">
                                    INDIKATOR OUTCOME / OUTPUT
                                </th>
                                <th rowspan="3" class="w-[110px] border border-slate-900 px-3 py-3 text-center align-middle font-bold">
                                    BASE LINE {{ baselineYear }}
                                </th>
                                <th :colspan="periodColumns.length * 2" class="border border-slate-900 px-3 py-2 text-center align-middle font-bold">
                                    TARGET DAN PAGU INDIKATIF TAHUN
                                </th>
                            </tr>
                            <tr class="bg-white">
                                <th
                                    v-for="period in periodColumns"
                                    :key="`renstra-preview-year-${period.id}`"
                                    colspan="2"
                                    class="border border-slate-900 px-3 py-2 text-center align-middle font-bold"
                                >
                                    {{ renstraOutputYearLabel(period.year) }}
                                </th>
                            </tr>
                            <tr class="bg-white">
                                <template v-for="period in periodColumns" :key="`renstra-preview-year-sub-${period.id}`">
                                    <th class="w-[90px] border border-slate-900 px-2 py-2 text-center align-middle font-semibold">Target</th>
                                    <th class="w-[130px] border border-slate-900 px-2 py-2 text-center align-middle font-semibold">Pagu</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in renstraOutputRows" :key="row.key" class="align-top" :class="renstraOutputRowClass(row.level)">
                                <td class="border border-slate-900 px-3 py-3 align-top" :class="row.level === 'bidang' ? 'uppercase' : ''">
                                    {{ row.label }}
                                </td>
                                <td class="border border-slate-900 px-3 py-3 align-top">{{ row.indicator }}</td>
                                <td class="border border-slate-900 px-3 py-3 text-center align-top tabular-nums">{{ row.baseline }}</td>
                                <template v-for="value in row.values" :key="`${row.key}-${value.year}`">
                                    <td class="border border-slate-900 px-2 py-3 text-center align-top tabular-nums">{{ value.target }}</td>
                                    <td class="border border-slate-900 px-2 py-3 align-top tabular-nums">{{ value.pagu }}</td>
                                </template>
                            </tr>
                            <tr v-if="renstraOutputRows.length === 0">
                                <td :colspan="3 + periodColumns.length * 2" class="border border-slate-900 px-4 py-10 text-center text-muted-foreground">
                                    Belum ada data Renstra OPD.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-else-if="false" class="rounded-lg border bg-card">
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
                                <div class="mt-1 text-sm font-medium">{{ tujuan.tujuan }}</div>
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
                                        <div class="mt-1 text-sm">{{ indikator.indikator }}</div>
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
                                            {{ sasaran.sasaran }}
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
                                                    {{ indikator.indikator }}
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
                                                    {{ program.nama }}
                                                </div>
                                                <div v-if="program.sasaran_program" class="mt-1 text-xs leading-5 text-slate-600">
                                                    Sasaran program: {{ program.sasaran_program }}
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
                                                            {{ indikator.indikator }}
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
                                                        {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
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
                                                            {{ kegiatan.nama }}
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
                                                                    {{ indikator.indikator }}
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
                                                                {{ sub.nama }}
                                                            </div>
                                                        </div>
                                                        <div v-if="can.manage" class="flex flex-wrap items-center justify-end gap-1.5">
                                                            <button
                                                                type="button"
                                                                class="inline-flex min-h-8 items-center gap-1 rounded-md border px-2 text-xs font-medium text-emerald-800 hover:bg-emerald-50"
                                                                @click="selectNodeType('anggaran_sub_kegiatan', sub.id)"
                                                            >
                                                                <Plus class="size-3.5" />
                                                                Pagu Indikatif
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
                                                                        {{ indikator.indikator }}
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

            <Teleport to="body">
                <aside
                    v-if="can.manage && isNodeModalOpen"
                    ref="formPanel"
                    class="fixed inset-0 z-[100] flex items-center justify-center overflow-hidden bg-slate-950/45 p-3 backdrop-blur-sm sm:p-5"
                >
                <div :class="['w-full space-y-4', isTargetType || isBudgetType ? 'max-w-4xl' : 'max-w-3xl']">
                    <section
                        v-if="isNodeModalOpen"
                        class="flex max-h-[calc(100vh-2rem)] flex-col overflow-hidden rounded-2xl border border-blue-100 bg-card shadow-2xl shadow-slate-950/15"
                    >
                        <div class="shrink-0 border-b bg-card p-4">
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

                        <form
                            :class="[
                                'overflow-y-auto p-4 sm:p-5',
                                isTargetType || isBudgetType ? 'flex flex-col gap-4' : 'grid grid-cols-1 gap-4',
                            ]"
                            @submit.prevent="submitNode"
                        >
                            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#00336C] shadow-sm">
                                        <Layers3 class="size-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-[#00336C]">{{ selectedTypeLabel }}</p>
                                        <h3 class="mt-1 text-sm font-semibold text-slate-950">{{ parentContextTitle }}</h3>
                                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ formContextDescription }}</p>

                                        <div v-if="form.type === 'tujuan'" class="mt-3 grid gap-2 md:grid-cols-2">
                                            <div class="rounded-lg border border-blue-100 bg-white p-3">
                                                <p class="text-xs font-semibold uppercase text-slate-500">Tujuan Kabupaten</p>
                                                <p class="mt-1 text-sm font-semibold leading-6 text-slate-900">
                                                    {{ rpjmdContextTujuanTexts.join('; ') || 'Belum ada tujuan kabupaten terkait.' }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg border border-blue-100 bg-white p-3">
                                                <p class="text-xs font-semibold uppercase text-slate-500">Sasaran Kabupaten</p>
                                                <p class="mt-1 text-sm font-semibold leading-6 text-slate-900">
                                                    {{ rpjmdContextSasaranTexts.join('; ') || 'Belum ada sasaran kabupaten terkait.' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="showParentSelector" class="grid gap-2 rounded-xl border bg-background p-3">
                                <label class="text-sm font-semibold text-foreground" for="parent_id">
                                    {{
                                        form.type === 'program'
                                            ? 'Sasaran'
                                            : form.type === 'kegiatan'
                                              ? 'Program'
                                              : 'Kegiatan'
                                    }}
                                    <span class="text-red-600">*</span>
                                </label>
                                <RpjmdRichSelect
                                    id="parent_id"
                                    v-model="form.parent_id"
                                    :options="parentSelectOptions"
                                    :placeholder="
                                        form.type === 'program'
                                            ? 'Pilih sasaran'
                                            : form.type === 'kegiatan'
                                              ? 'Pilih program'
                                              : 'Pilih kegiatan'
                                    "
                                    :empty-text="`${parentLabel} belum tersedia`"
                                />
                                <InputError :message="form.errors.parent_id" />
                            </div>

                            <div v-if="form.type === 'program'" class="grid gap-3 rounded-xl border bg-muted/20 p-3">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium" for="program_rpjmd_id">Pilih Program</label>
                                    <RpjmdRichSelect
                                        id="program_rpjmd_id"
                                        v-model="form.program_rpjmd_id"
                                        :options="programRpjmdSelectOptions"
                                        placement="bottom"
                                        placeholder="Pilih program"
                                        empty-text="Program RPJMD belum tersedia"
                                    />
                                    <p class="text-xs leading-5 text-muted-foreground">Program berasal dari RPJMD yang terhubung dengan Renstra ini.</p>
                                </div>
                                <div class="rounded-lg border bg-white px-3 py-2 text-sm text-slate-700">
                                    <span class="text-xs font-semibold uppercase text-slate-500">Program OPD</span>
                                    <p class="mt-1 font-semibold text-slate-950">
                                        {{ selectedProgramRpjmd?.nama || selectedProgramRpjmd?.label || 'Terisi otomatis setelah program dipilih.' }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="form.type === 'kegiatan'" class="grid gap-2 rounded-xl border bg-muted/20 p-3">
                                <label class="text-sm font-medium" for="kegiatan_pemerintahan_id">Pilih Kegiatan</label>
                                <RpjmdRichSelect
                                    id="kegiatan_pemerintahan_id"
                                    v-model="form.kegiatan_pemerintahan_id"
                                    :options="kegiatanMasterSelectOptions"
                                    :disabled="needsParent && !form.parent_id"
                                    placeholder="Pilih kegiatan"
                                    empty-text="Kegiatan belum tersedia"
                                />
                                <InputError :message="form.errors.kegiatan_pemerintahan_id" />
                                <div v-if="selectedKegiatanMaster" class="rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-sm">
                                    <span class="text-xs font-semibold uppercase text-slate-500">Kegiatan OPD</span>
                                    <p class="mt-1 font-semibold leading-6 text-slate-950">
                                        {{ selectedKegiatanMaster.nama || selectedKegiatanMaster.label }}
                                    </p>
                                </div>
                                <p v-else class="text-xs leading-5 text-slate-500">
                                    Kegiatan wajib dipilih dari master. Jika belum tersedia, hubungi admin kabupaten.
                                </p>
                            </div>

                            <div v-if="form.type === 'sub_kegiatan'" class="grid gap-2 rounded-xl border bg-muted/20 p-3">
                                <label class="text-sm font-medium" for="sub_kegiatan_pemerintahan_id">Pilih Sub Kegiatan</label>
                                <RpjmdRichSelect
                                    id="sub_kegiatan_pemerintahan_id"
                                    v-model="form.sub_kegiatan_pemerintahan_id"
                                    :options="subKegiatanMasterSelectOptions"
                                    :disabled="needsParent && !form.parent_id"
                                    placement="bottom"
                                    placeholder="Pilih sub kegiatan"
                                    empty-text="Sub kegiatan belum tersedia"
                                />
                                <InputError :message="form.errors.sub_kegiatan_pemerintahan_id" />
                                <div v-if="selectedSubKegiatanMaster" class="grid gap-3 rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-3 text-sm">
                                    <div>
                                        <span class="text-xs font-semibold uppercase text-slate-500">Sub Kegiatan OPD</span>
                                        <p class="mt-1 font-semibold leading-6 text-slate-950">
                                            {{ selectedSubKegiatanMaster.nama || selectedSubKegiatanMaster.label }}
                                        </p>
                                    </div>
                                    <div class="rounded-md border border-white/80 bg-white/80 px-3 py-2">
                                        <span class="text-xs font-semibold uppercase text-slate-500">Sasaran Sub Kegiatan</span>
                                        <p
                                            class="mt-1 leading-6"
                                            :class="selectedSubKegiatanMaster.sasaran_sub_kegiatan ? 'text-slate-800' : 'text-slate-500'"
                                        >
                                            {{ selectedSubKegiatanMaster.sasaran_sub_kegiatan || 'Belum ada sasaran sub kegiatan pada master.' }}
                                        </p>
                                    </div>
                                </div>
                                <p v-else class="text-xs leading-5 text-slate-500">
                                    Sub kegiatan wajib dipilih dari master. Jika belum tersedia, hubungi admin kabupaten.
                                </p>
                            </div>

                            <div v-if="isTextNodeType && !['program', 'kegiatan', 'sub_kegiatan'].includes(form.type)" class="grid gap-2">
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

                            <div v-if="['program', 'kegiatan'].includes(form.type)" class="grid gap-2">
                                <label class="text-sm font-medium" for="sasaran_level">
                                    {{ form.type === 'program' ? 'Sasaran Program' : 'Sasaran Kegiatan' }}
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

                            <div v-if="isOrderableNodeType" class="grid max-w-40 gap-2">
                                <label class="text-sm font-medium" for="urutan">Urutan</label>
                                <input
                                    id="urutan"
                                    v-model="form.urutan"
                                    type="number"
                                    min="1"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                />
                                <InputError :message="form.errors.urutan" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="definisi_operasional">Definisi Operasional</label>
                                <textarea
                                    id="definisi_operasional"
                                    v-model="form.definisi_operasional"
                                    rows="3"
                                    class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-primary"
                                />
                                <InputError :message="form.errors.definisi_operasional" />
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
                                <label class="text-sm font-medium" for="tipe_perhitungan">Tipe Perhitungan</label>
                                <select
                                    id="tipe_perhitungan"
                                    v-model="form.tipe_perhitungan"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                >
                                    <option value="non_kumulatif">Non-kumulatif</option>
                                    <option value="kumulatif">Kumulatif</option>
                                </select>
                                <InputError :message="form.errors.tipe_perhitungan" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="formulasi_pengukuran">Formulasi Pengukuran</label>
                                <textarea
                                    id="formulasi_pengukuran"
                                    v-model="form.formulasi_pengukuran"
                                    rows="3"
                                    class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-primary"
                                />
                                <InputError :message="form.errors.formulasi_pengukuran" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="pd_penanggung_jawab">PD Penanggung Jawab</label>
                                <input
                                    id="pd_penanggung_jawab"
                                    v-model="form.pd_penanggung_jawab"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                    :placeholder="pdPenanggungJawabPlaceholder"
                                />
                                <InputError :message="form.errors.pd_penanggung_jawab" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="sumber_data">Sumber Data</label>
                                <input
                                    id="sumber_data"
                                    v-model="form.sumber_data"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                                    :placeholder="sumberDataPlaceholder"
                                />
                            </div>

                            <div v-if="isTargetType || isBudgetType" class="grid gap-3">
                                <div class="rounded-xl border bg-white shadow-sm">
                                    <div class="flex flex-wrap items-start justify-between gap-3 border-b px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ targetBatchTitle }}</p>
                                            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
                                                {{
                                                    isBudgetType
                                                        ? targetBatchSubKegiatan?.nama || 'Sub kegiatan belum tersedia.'
                                                        : targetBatchIndicatorRow?.indikator || 'Indikator belum tersedia.'
                                                }}
                                            </p>
                                        </div>
                                        <span class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-semibold text-[#00336C]">
                                            {{ targetBatchRows.length }} periode
                                        </span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-[760px] w-full text-left text-sm">
                                            <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                                                <tr>
                                                    <th class="w-20 px-4 py-3">No</th>
                                                    <th class="min-w-52 px-4 py-3">Periode</th>
                                                    <th v-if="!isBudgetType" class="min-w-72 px-4 py-3">Target</th>
                                                    <th v-if="targetBatchShowsFinance" class="min-w-60 px-4 py-3">Anggaran</th>
                                                    <th class="w-28 px-4 py-3">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y">
                                                <tr v-for="(targetRow, index) in targetBatchRows" :key="targetRow.key" class="align-top">
                                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ index + 1 }}</td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex min-h-10 items-center rounded-lg border bg-slate-50 px-3 font-semibold text-slate-700">
                                                            {{ targetBatchPeriodLabel(targetRow) }}
                                                        </span>
                                                    </td>
                                                    <td v-if="!isBudgetType" class="px-4 py-3">
                                                        <input
                                                            v-model="targetRow.target"
                                                            class="min-h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/15"
                                                            placeholder="Contoh: 100 dokumen, 90%, atau <= 15"
                                                            @input="onTargetBatchInput(targetRow)"
                                                        />
                                                    </td>
                                                    <td v-if="targetBatchShowsFinance" class="px-4 py-3">
                                                        <input
                                                            v-model="targetRow.pagu"
                                                            inputmode="numeric"
                                                            class="min-h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/15"
                                                            placeholder="0"
                                                            @input="onTargetBatchPaguInput(targetRow)"
                                                        />
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span
                                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                            :class="
                                                                targetRow.saveState === 'saved'
                                                                    ? 'bg-emerald-50 text-emerald-700'
                                                                    : targetRow.saveState === 'saving'
                                                                      ? 'bg-blue-50 text-[#00336C]'
                                                                      : targetRow.saveState === 'error'
                                                                        ? 'bg-red-50 text-red-700'
                                                                        : 'bg-slate-100 text-slate-600'
                                                            "
                                                        >
                                                            {{
                                                                targetRow.saveState === 'saved'
                                                                    ? 'Tersimpan'
                                                                    : targetRow.saveState === 'saving'
                                                                      ? 'Menyimpan'
                                                                      : targetRow.saveState === 'error'
                                                                        ? 'Gagal'
                                                                        : 'Siap'
                                                            }}
                                                        </span>
                                                        <p v-if="targetRow.error" class="mt-1 text-xs text-red-600">{{ targetRow.error }}</p>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 flex w-full items-center justify-end border-t border-slate-200 bg-card pt-4">
                                <button
                                    type="submit"
                                    :disabled="form.processing || isTargetBatchSaving || isRequiredMasterMissing"
                                    class="inline-flex min-h-11 min-w-40 items-center justify-center gap-2 rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/10 transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <Save class="size-4" />
                                    {{ isTargetType || isBudgetType ? targetBatchSubmitLabel : editingNode ? 'Perbarui Data' : 'Simpan Data' }}
                                </button>
                            </div>
                        </form>
                    </section>

                </div>
                </aside>
            </Teleport>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="revisionModalOpen" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/45 p-4" @click.self="revisionModalOpen = false">
            <form class="w-full max-w-xl rounded-xl bg-white shadow-2xl" @submit.prevent="submitRevision">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Buat Versi Perubahan Renstra</p>
                        <p class="mt-1 text-sm text-slate-500">Cascading dari {{ renstra.version_label }} akan disalin ke dokumen baru berstatus Draft.</p>
                    </div>
                    <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup" @click="revisionModalOpen = false">&times;</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label for="renstra-revision-reason" class="text-sm font-medium text-slate-800">Alasan Perubahan</label>
                        <textarea id="renstra-revision-reason" v-model="revisionForm.alasan_perubahan" rows="4" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100" placeholder="Jelaskan alasan perubahan dokumen" />
                        <InputError :message="revisionForm.errors.alasan_perubahan" class="mt-1" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="renstra-revision-basis" class="text-sm font-medium text-slate-800">Dasar Perubahan</label>
                            <input id="renstra-revision-basis" v-model="revisionForm.dasar_perubahan" type="text" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100" placeholder="Contoh: Perkada Perubahan" />
                            <InputError :message="revisionForm.errors.dasar_perubahan" class="mt-1" />
                        </div>
                        <div>
                            <label for="renstra-revision-date" class="text-sm font-medium text-slate-800">Tanggal Berlaku</label>
                            <input id="renstra-revision-date" v-model="revisionForm.tanggal_berlaku" type="date" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100" />
                            <InputError :message="revisionForm.errors.tanggal_berlaku" class="mt-1" />
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="revisionModalOpen = false">Batal</button>
                    <button type="submit" :disabled="revisionForm.processing" class="inline-flex items-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <GitBranch class="size-4" />
                        Buat Perubahan
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
