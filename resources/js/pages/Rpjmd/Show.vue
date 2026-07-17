<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import RpjmdNodeTypePicker from '@/components/RpjmdNodeTypePicker.vue';
import RpjmdPerformanceTreeDiagram from '@/components/RpjmdPerformanceTreeDiagram.vue';
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import { confirmDelete, toast } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Eye, EyeOff, GitBranch, LoaderCircle, Network, Pencil, Plus, Rows3, Save, Table2, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

type Option = {
    id: number;
    label: string;
    description?: string | null;
    badge?: string | number | null;
    group?: string | null;
    sasaran_id?: number | null;
    program_pemerintahan_ids?: number[];
    tahun?: number;
    jenis_target?: 'tahunan' | 'prakiraan_maju';
};
type RichSelectOption = {
    id?: number | string;
    value?: number | string;
    label: string;
    description?: string;
    badge?: string | number;
    group?: string | null;
};
type NodeType =
    | 'visi'
    | 'misi'
    | 'tujuan'
    | 'indikator_tujuan'
    | 'target_tujuan'
    | 'sasaran'
    | 'indikator_sasaran'
    | 'target_sasaran'
    | 'program'
    | 'indikator_program'
    | 'target_program'
    | 'program_opd';

type Target = {
    id: number;
    periode_tahun: { id: number; tahun: number; nama: string };
    jenis_target?: 'tahunan' | 'prakiraan_maju' | string | null;
    target?: string | number | null;
    target_text?: string | null;
};

type TargetTriwulan = {
    id: number;
    periode_tahun: { id: number; tahun: number; nama: string };
    triwulan: string;
    target_angka?: string | number | null;
    target_text?: string | null;
};

type Indikator = {
    id: number;
    kode?: string | null;
    indikator: string;
    satuan_indikator_id?: number | null;
    opd_id?: number | null;
    definisi_operasional?: string | null;
    alasan_pemilihan?: string | null;
    formulasi_pengukuran?: string | null;
    tipe_perhitungan?: string | null;
    sumber_data?: string | null;
    urutan?: number | null;
    satuan?: { nama: string; simbol?: string | null } | null;
    opd?: { id: number; kode: string; nama: string; singkatan?: string | null } | null;
    targets: Target[];
    target_triwulan: TargetTriwulan[];
    programs: Program[];
};

type Program = {
    id: number;
    kode?: string | null;
    nama: string;
    status: string;
    indikator_sasaran_daerah_id?: number | null;
    strategi_daerah_id?: number | null;
    program_pemerintahan_id?: number | null;
    program_pemerintahan_ids?: number[];
    urusan_pemerintahan_id?: number | null;
    urutan?: number | null;
    strategi?: { id: number; kode?: string | null; strategi: string } | null;
    program_pemerintahan?: { id: number; kode: string; nama: string; bidang_urusan?: { kode: string; nama: string } | null } | null;
    program_pemerintahan_references?: Array<{ id: number; kode: string; nama: string; bidang_urusan?: { kode: string; nama: string } | null }>;
    urusan_pemerintahan?: { kode: string; nama: string } | null;
    opd_penanggung_jawab: Array<{ pivot_id: number; id: number; nama: string; singkatan?: string | null; peran: string; is_utama: boolean }>;
    indikator: Indikator[];
};

type Sasaran = {
    id: number;
    kode?: string | null;
    sasaran: string;
    urutan?: number | null;
    indikator_tujuan_ids: number[];
    indikator_tujuan_terkait: Array<{ id: number; indikator: string; urutan?: number | null }>;
    indikator: Indikator[];
};

type Tujuan = {
    id: number;
    kode?: string | null;
    tujuan: string;
    urutan?: number | null;
    misi_ids: number[];
    misi_terkait: Array<{ id: number; misi: string; urutan?: number | null }>;
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
    struktur_tujuan_mode: string;
    struktur_sasaran_mode: string;
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
    satuan_tujuan: string;
    target_tujuan_by_year: Record<number, string>;
    sasaran: string;
    indikator_sasaran: string;
    satuan_sasaran: string;
    target_sasaran_by_year: Record<number, string>;
    strategi: string;
    program: string;
    indikator_program: string;
    satuan_program: string;
    target_program_by_year: Record<number, string>;
    opd_penanggung_jawab: string;
    status_keterhubungan: string;
};
type IndicatorPreviewRow = {
    key: string;
    label: string;
    satuan: string;
    target_by_year: Record<number, string>;
};
type BulkRow = {
    client_id: string;
    existing_target_id?: number | null;
    parent_id: number | string;
    misi_ids: Array<number | string>;
    indikator_tujuan_ids: Array<number | string>;
    periode_tahun_id: number | string;
    satuan_indikator_id: number | string;
    opd_id: number | string;
    urusan_pemerintahan_id: number | string;
    strategi_daerah_id: number | string;
    program_pemerintahan_id: number | string;
    uraian: string;
    indikator: string;
    definisi_operasional: string;
    alasan_pemilihan: string;
    formulasi_pengukuran: string;
    tipe_perhitungan: 'kumulatif' | 'non_kumulatif';
    sumber_data: string;
    target: string;
    target_text: string;
    peran: string;
    is_utama: boolean;
    urutan: number | string;
};

type BulkExistingRow = {
    id: number;
    type: NodeType;
    parent_id?: number | null;
    parent_label?: string | null;
    misi_ids?: number[];
    misi_label?: string | null;
    indikator_tujuan_ids?: number[];
    indikator_tujuan_label?: string | null;
    uraian?: string | null;
    indikator?: string | null;
    satuan?: string | null;
    satuan_indikator_id?: number | null;
    definisi_operasional?: string | null;
    alasan_pemilihan?: string | null;
    formulasi_pengukuran?: string | null;
    tipe_perhitungan?: string | null;
    sumber_data?: string | null;
    urusan?: string | null;
    urusan_pemerintahan_id?: number | null;
    strategi_daerah_id?: number | null;
    strategi?: string | null;
    program_pemerintahan_id?: number | null;
    program_pemerintahan?: string | null;
    target?: string | number | null;
    target_text?: string | null;
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
    previewLoaded: boolean;
    nodeOptions: Record<string, Option[]>;
    targetTriwulanOptions: Record<string, Option[]>;
    periodeOptions: Option[];
    targetPeriodOptions: Option[];
    satuanOptions: Option[];
    opdOptions: Option[];
    urusanOptions: Option[];
    programPemerintahanOptions: Option[];
    can: {
        manage: boolean;
        review: boolean;
        lock: boolean;
    };
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
    { value: 'program', label: 'Program RPJMD' },
    { value: 'indikator_program', label: 'Indikator Program' },
    { value: 'target_program', label: 'Target Indikator Program' },
    { value: 'program_opd', label: 'OPD Penanggung Jawab Program' },
];

const nodeTypeLabel = (type: NodeType) => typeOptions.find((option) => option.value === type)?.label ?? 'Data Cascading';

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
        helper: 'Lengkapi satuan, formulasi, sumber data, dan PD penanggung jawab.',
    },
    target_tujuan: {
        description: 'Target tahunan untuk indikator tujuan daerah.',
        placeholder: 'Contoh: 14,79 atau 80 persen',
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
        placeholder: 'Contoh: 14,79 atau 80 persen',
        helper: 'Target ini akan dipakai untuk monitoring capaian per tahun.',
    },
    program: {
        description: 'Program RPJMD diturunkan dari indikator sasaran dan dapat diberi referensi strategi.',
        placeholder: 'Contoh: Program Penunjang Urusan Pemerintahan Daerah Kabupaten/Kota',
        helper: 'Lengkapi urusan pemerintahan agar program mudah disinkronkan ke perangkat daerah.',
    },
    indikator_program: {
        description: 'Indikator program mengukur hasil program RPJMD.',
        placeholder: 'Contoh: Persentase perangkat daerah dengan nilai SAKIP minimal BB',
        helper: 'Indikator program akan menjadi referensi sinkronisasi dengan Renstra OPD.',
    },
    target_program: {
        description: 'Target tahunan untuk indikator program RPJMD.',
        placeholder: 'Contoh: 14,79 atau 80 persen',
        helper: 'Target ini menjadi acuan sinkronisasi program dengan Renstra OPD.',
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
    program: 'indikator_sasaran',
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
    program: 'Program Induk',
    indikator_program: 'Indikator Program',
};

const form = useForm({
    type: 'visi' as NodeType,
    parent_id: '' as number | string,
    misi_ids: [] as Array<number | string>,
    indikator_tujuan_ids: [] as Array<number | string>,
    periode_tahun_id: '' as number | string,
    satuan_indikator_id: '' as number | string,
    opd_id: '' as number | string,
    urusan_pemerintahan_id: '' as number | string,
    strategi_daerah_id: '' as number | string,
    program_pemerintahan_id: '' as number | string,
    uraian: '',
    indikator: '',
    definisi_operasional: '',
    alasan_pemilihan: '',
    formulasi_pengukuran: '',
    tipe_perhitungan: 'non_kumulatif',
    sumber_data: '',
    target: '',
    target_text: '',
    peran: 'penanggung_jawab',
    is_utama: true,
    urutan: 1,
});

let bulkRowClientCounter = 0;
const makeBulkRowClientId = () => `bulk-row-${Date.now()}-${bulkRowClientCounter++}`;
const emptyBulkRow = (index = 0): BulkRow => ({
    client_id: makeBulkRowClientId(),
    existing_target_id: null,
    parent_id: '',
    misi_ids: [],
    indikator_tujuan_ids: [],
    periode_tahun_id: '',
    satuan_indikator_id: '',
    opd_id: '',
    urusan_pemerintahan_id: '',
    strategi_daerah_id: '',
    program_pemerintahan_id: '',
    uraian: '',
    indikator: '',
    definisi_operasional: '',
    alasan_pemilihan: '',
    formulasi_pengukuran: '',
    tipe_perhitungan: 'non_kumulatif',
    sumber_data: '',
    target: '',
    target_text: '',
    peran: 'penanggung_jawab',
    is_utama: true,
    urutan: index + 1,
});

const bulkForm = useForm({
    type: 'tujuan' as NodeType,
    parent_id: '' as number | string,
    misi_ids: [] as Array<number | string>,
    indikator_tujuan_ids: [] as Array<number | string>,
    periode_tahun_id: '' as number | string,
    satuan_indikator_id: '' as number | string,
    urusan_pemerintahan_id: '' as number | string,
    strategi_daerah_id: '' as number | string,
    program_pemerintahan_id: '' as number | string,
    peran: 'penanggung_jawab',
    is_utama: true,
    rows: [emptyBulkRow()],
});

const newBulkSaveState = reactive<Record<string, SavedBulkSaveState>>({});
const newBulkSaveErrors = reactive<Record<string, string>>({});
const newBulkBaselines = reactive<Record<string, string>>({});
const newBulkKey = (row: BulkRow) => row.client_id;
const newBulkSnapshot = (row: BulkRow) =>
    JSON.stringify({
        existing_target_id: row.existing_target_id ?? null,
        parent_id: valueText(row.parent_id),
        periode_tahun_id: valueText(row.periode_tahun_id),
        target: valueText(row.target),
    });
const clearNewBulkRowState = (row: BulkRow) => {
    const key = newBulkKey(row);

    delete newBulkSaveState[key];
    delete newBulkSaveErrors[key];
    delete newBulkBaselines[key];
};
const clearAllNewBulkRowState = () => {
    bulkForm.rows.forEach(clearNewBulkRowState);
    Object.keys(newBulkSaveState).forEach((key) => delete newBulkSaveState[key]);
    Object.keys(newBulkSaveErrors).forEach((key) => delete newBulkSaveErrors[key]);
    Object.keys(newBulkBaselines).forEach((key) => delete newBulkBaselines[key]);
};

const targetTriwulanRows = [
    { triwulan: 'tw1', label: 'TW I' },
    { triwulan: 'tw2', label: 'TW II' },
    { triwulan: 'tw3', label: 'TW III' },
    { triwulan: 'tw4', label: 'TW IV' },
];

const emptyTargetTriwulanRows = () => targetTriwulanRows.map((row) => ({ triwulan: row.triwulan, target_text: '', target_angka: '' }));

const targetTriwulanForm = useForm({
    related_table: 'indikator_tujuan_daerah',
    related_id: '' as number | string,
    periode_tahun_id: '' as number | string,
    targets: emptyTargetTriwulanRows(),
});

const selectedTypeLabel = computed(() => nodeTypeLabel(form.type));
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
const canSubmitNode = computed(
    () =>
        !form.processing &&
        (!needsParent.value || Boolean(form.parent_id)) &&
        (!isProgramType.value || Boolean(form.program_pemerintahan_id) || trimText(form.uraian).length > 0) &&
        (!shouldRequireTujuanMisi.value || form.misi_ids.length > 0) &&
        (!shouldRequireSasaranIndikatorTujuan.value || form.indikator_tujuan_ids.length > 0),
);
const isIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program'].includes(form.type));
const isTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(form.type));
const isTextNodeType = computed(() => ['visi', 'misi', 'tujuan', 'sasaran', 'program'].includes(form.type));
const isProgramType = computed(() => form.type === 'program');
const isProgramOpdType = computed(() => form.type === 'program_opd');
const bulkTypeMeta = computed(() => typeMeta[bulkForm.type]);
const bulkTypeLabel = computed(() => nodeTypeLabel(bulkForm.type));
const bulkParentKey = computed(() => parentKeyByType[bulkForm.type]);
const bulkParentOptions = computed(() => (bulkParentKey.value ? (props.nodeOptions[bulkParentKey.value] ?? []) : []));
const bulkParentLabel = computed(() => (bulkParentKey.value ? (parentLabels[bulkParentKey.value] ?? 'Induk Data') : 'Induk Data'));
const bulkNeedsParent = computed(() => Boolean(bulkParentKey.value));
const bulkHidesParentSelector = computed(() => ['misi', 'tujuan'].includes(bulkForm.type));
const bulkShouldShowParentSelector = computed(() => bulkNeedsParent.value && !bulkHidesParentSelector.value);
const bulkInputContextReady = computed(() => !bulkNeedsParent.value || Boolean(bulkForm.parent_id));
const bulkSelectedParentOption = computed(() => optionById(bulkParentOptions.value, bulkForm.parent_id));
const bulkHasAdditionalSettings = computed(
    () =>
        (bulkForm.type === 'tujuan' && bulkMisiOptions.value.length > 0) ||
        (bulkForm.type === 'sasaran' && bulkShouldShowSasaranIndikatorTujuan.value) ||
        bulkIsIndicatorType.value ||
        bulkIsProgramType.value,
);
const bulkIsIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program'].includes(bulkForm.type));
const bulkIsTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(bulkForm.type));
const bulkIsTextNodeType = computed(() => ['visi', 'misi', 'tujuan', 'sasaran', 'program'].includes(bulkForm.type));
const bulkIsProgramType = computed(() => bulkForm.type === 'program');
const bulkIsProgramOpdType = computed(() => bulkForm.type === 'program_opd');
const bulkFilledRows = computed(
    () =>
        bulkForm.rows.filter((row) => {
            if (bulkIsIndicatorType.value) {
                return trimText(row.indikator).length > 0;
            }

            if (bulkIsTargetType.value) {
                return Boolean(row.existing_target_id) || trimText(valueText(row.target)).length > 0;
            }

            if (bulkIsProgramType.value) {
                return Boolean(row.program_pemerintahan_id) || trimText(row.uraian).length > 0;
            }

            if (bulkIsProgramOpdType.value) {
                return Boolean(row.opd_id);
            }

            return trimText(row.uraian).length > 0;
        }).length,
);
const bulkCanSubmit = computed(
    () =>
        !bulkForm.processing &&
        (!bulkNeedsParent.value || Boolean(bulkForm.parent_id)) &&
        (!bulkShouldRequireTujuanMisi.value || bulkForm.misi_ids.length > 0) &&
        (!bulkShouldRequireSasaranIndikatorTujuan.value || bulkForm.indikator_tujuan_ids.length > 0) &&
        bulkFilledRows.value > 0,
);
const bulkTableCounterLabel = computed(() =>
    bulkIsTargetType.value
        ? `${targetPeriodOptionsForInput.value.length} tahun target`
        : `${bulkVisibleExistingRows.value.length} tersimpan - ${bulkFilledRows.value} baru`,
);
const bulkColumnCount = computed(() => {
    let count = bulkIsTargetType.value ? 1 : 2; // No + aksi bila bukan target

    if (bulkIsTextNodeType.value) {
        count += 1;
    }

    if (bulkIsIndicatorType.value) {
        count += 8;
    }

    if (bulkIsProgramType.value) {
        count += 1;
    }

    if (bulkIsTargetType.value) {
        count += 2; // Periode + target
    }

    if (bulkIsProgramOpdType.value) {
        count += 3;
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
const activeInputMode = ref<'single' | 'bulk'>('bulk');
const viewMode = ref<'tree' | 'table'>('tree');
const showPreview = ref(!props.can.manage && props.previewLoaded);
const previewLoading = ref(false);
const editorDataLoading = ref(false);

const nodeText = (_kode: string | null | undefined, text: string | null | undefined) => trimText(text ?? '') || '-';
const trimText = (value: string) => value.replace(/\s+/g, ' ').trim();
const joinItems = (items: string[]) => items.filter((item) => item && item !== '-').join('; ') || '-';
const asRichOptions = (options: Option[]): RichSelectOption[] =>
    options.map((option) => ({
        id: option.id,
        label: option.label,
        description: option.description ?? undefined,
        group: option.group,
    }));
const optionById = (options: Option[], id: number | string | null | undefined) => {
    const selectedId = toSelectedNumber(id);

    return selectedId ? (options.find((option) => Number(option.id) === selectedId) ?? null) : null;
};
const strategiOptions = computed(() => props.nodeOptions.strategi ?? []);
const bulkStrategiOptions = strategiOptions;
const formStrategiOptions = strategiOptions;
const programPemerintahanOptions = computed(() => props.programPemerintahanOptions ?? []);

const targetCountByType = computed<Record<'target_tujuan' | 'target_sasaran' | 'target_program', Map<number, number>>>(() => {
    const counts = {
        target_tujuan: new Map<number, number>(),
        target_sasaran: new Map<number, number>(),
        target_program: new Map<number, number>(),
    };

    props.rpjmd.visi.forEach((visi) => {
        visi.tujuan.forEach((tujuan) => {
            tujuan.indikator.forEach((indikator) => counts.target_tujuan.set(indikator.id, indikator.targets.length));
            tujuan.sasaran.forEach((sasaran) => {
                sasaran.indikator.forEach((indikator) => counts.target_sasaran.set(indikator.id, indikator.targets.length));
                sasaran.indikator.forEach((indikatorSasaran) => {
                    indikatorSasaran.programs.forEach((program) => {
                        program.indikator.forEach((indikator) => counts.target_program.set(indikator.id, indikator.targets.length));
                    });
                });
            });
        });
    });

    return counts;
});

const targetTriwulanCountByTable = computed<Record<string, Map<number, number>>>(() => {
    const counts = {
        indikator_tujuan_daerah: new Map<number, number>(),
        indikator_sasaran_daerah: new Map<number, number>(),
        indikator_program_rpjmd: new Map<number, number>(),
    };

    props.rpjmd.visi.forEach((visi) => {
        visi.tujuan.forEach((tujuan) => {
            tujuan.indikator.forEach((indikator) => counts.indikator_tujuan_daerah.set(indikator.id, indikator.target_triwulan.length));
            tujuan.sasaran.forEach((sasaran) => {
                sasaran.indikator.forEach((indikator) => counts.indikator_sasaran_daerah.set(indikator.id, indikator.target_triwulan.length));
                sasaran.indikator.forEach((indikatorSasaran) => {
                    indikatorSasaran.programs.forEach((program) => {
                        program.indikator.forEach((indikator) => counts.indikator_program_rpjmd.set(indikator.id, indikator.target_triwulan.length));
                    });
                });
            });
        });
    });

    return counts;
});

const targetTypeLabels: Record<'target_tujuan' | 'target_sasaran' | 'target_program', string> = {
    target_tujuan: 'target tujuan',
    target_sasaran: 'target sasaran',
    target_program: 'target program',
};

const isAnnualTargetType = (type: NodeType): type is 'target_tujuan' | 'target_sasaran' | 'target_program' =>
    ['target_tujuan', 'target_sasaran', 'target_program'].includes(type);
const targetPeriodOptionsForInput = computed(() => (props.targetPeriodOptions.length > 0 ? props.targetPeriodOptions : props.periodeOptions));
const isPrakiraanMajuYear = (year: number) => year > props.rpjmd.tahun_akhir;
const targetYearLabel = (year: number) => (isPrakiraanMajuYear(year) ? `${year} PM` : String(year));
const targetYearTitle = (year: number) => (isPrakiraanMajuYear(year) ? `${year} - Prakiraan Maju` : `${year} - Target RPJMD`);

const completeDataNodeTypes = new Set<NodeType>([
    'target_tujuan',
    'sasaran',
    'indikator_sasaran',
    'target_sasaran',
    'program',
    'indikator_program',
    'target_program',
    'program_opd',
]);

const decorateParentOptions = (type: NodeType, options: Option[]): RichSelectOption[] => {
    if (!isAnnualTargetType(type)) {
        return asRichOptions(options);
    }

    const totalPeriods = targetPeriodOptionsForInput.value.length;

    return options.map((option) => {
        const filledTargets = targetCountByType.value[type].get(Number(option.id)) ?? 0;

        return {
            id: option.id,
            label: option.label,
            group: option.group,
            description:
                filledTargets > 0 ? `Sudah terisi ${filledTargets} ${targetTypeLabels[type]} tahunan` : `Belum ada ${targetTypeLabels[type]} tahunan`,
            badge: totalPeriods > 0 ? `${filledTargets}/${totalPeriods}` : String(filledTargets),
        };
    });
};

const decoratedParentOptions = computed(() => decorateParentOptions(form.type, parentOptions.value));
const decoratedBulkParentOptions = computed(() => decorateParentOptions(bulkForm.type, bulkParentOptions.value));
const decoratedTargetTriwulanOptions = computed<RichSelectOption[]>(() => {
    const totalTargets = targetPeriodOptionsForInput.value.length * 4;
    const counts = targetTriwulanCountByTable.value[targetTriwulanForm.related_table] ?? new Map<number, number>();

    return selectedTargetTriwulanOptions.value.map((option) => {
        const filledTargets = counts.get(Number(option.id)) ?? 0;

        return {
            id: option.id,
            label: option.label,
            group: option.group,
            description: filledTargets > 0 ? `Sudah terisi ${filledTargets} target triwulan` : 'Belum ada target triwulan',
            badge: totalTargets > 0 ? `${filledTargets}/${totalTargets}` : String(filledTargets),
        };
    });
});
const toSelectedNumber = (value: number | string | null | undefined) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const rpjmdStrukturTujuanLabel = computed(() => (props.rpjmd.struktur_tujuan_mode === 'tujuan_per_misi' ? 'Tujuan per misi' : 'Tujuan lintas misi'));
const loadCompleteRpjmdData = (openPreview: boolean) => {
    if (props.previewLoaded) {
        if (openPreview) {
            showPreview.value = true;
        }

        return;
    }

    if (openPreview) {
        previewLoading.value = true;
    } else {
        editorDataLoading.value = true;
    }

    router.reload({
        data: { with_preview: 1 },
        only: ['rpjmd', 'previewLoaded'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (openPreview) {
                showPreview.value = true;
            }
        },
        onFinish: () => {
            previewLoading.value = false;
            editorDataLoading.value = false;
        },
    });
};

const togglePreview = () => {
    if (showPreview.value) {
        showPreview.value = false;

        return;
    }

    loadCompleteRpjmdData(true);
};
const misiOptionsForVisi = (visiId: number | string | null | undefined) => {
    const id = toSelectedNumber(visiId);
    const visi = props.rpjmd.visi.find((item) => item.id === id);

    return visi?.misi.map((misi) => ({ id: misi.id, label: misi.misi })) ?? [];
};
const tujuanById = (tujuanId: number | string | null | undefined) => {
    const id = toSelectedNumber(tujuanId);

    for (const visi of props.rpjmd.visi) {
        const tujuan = visi.tujuan.find((item) => item.id === id);

        if (tujuan) {
            return tujuan;
        }
    }

    return null;
};
const indikatorTujuanOptionsForTujuan = (tujuanId: number | string | null | undefined) =>
    tujuanById(tujuanId)?.indikator.map((indikator) => ({ id: indikator.id, label: indikator.indikator })) ?? [];
const formMisiOptions = computed(() => (form.type === 'tujuan' ? misiOptionsForVisi(form.parent_id) : []));
const formIndikatorTujuanOptions = computed(() => (form.type === 'sasaran' ? indikatorTujuanOptionsForTujuan(form.parent_id) : []));
const bulkMisiOptions = computed(() => (bulkForm.type === 'tujuan' ? misiOptionsForVisi(bulkForm.parent_id) : []));
const bulkIndikatorTujuanOptions = computed(() => (bulkForm.type === 'sasaran' ? indikatorTujuanOptionsForTujuan(bulkForm.parent_id) : []));
const shouldRequireTujuanMisi = computed(() => form.type === 'tujuan' && props.rpjmd.struktur_tujuan_mode === 'tujuan_per_misi');
const shouldShowSasaranIndikatorTujuan = computed(() => form.type === 'sasaran' && props.rpjmd.struktur_sasaran_mode !== 'sasaran_langsung_tujuan');
const shouldRequireSasaranIndikatorTujuan = computed(
    () => form.type === 'sasaran' && props.rpjmd.struktur_sasaran_mode === 'sasaran_melalui_indikator_tujuan',
);
const bulkShouldRequireTujuanMisi = computed(() => bulkForm.type === 'tujuan' && props.rpjmd.struktur_tujuan_mode === 'tujuan_per_misi');
const bulkShouldShowSasaranIndikatorTujuan = computed(
    () => bulkForm.type === 'sasaran' && props.rpjmd.struktur_sasaran_mode !== 'sasaran_langsung_tujuan',
);
const bulkShouldRequireSasaranIndikatorTujuan = computed(
    () => bulkForm.type === 'sasaran' && props.rpjmd.struktur_sasaran_mode === 'sasaran_melalui_indikator_tujuan',
);

const selectedBulkParentId = computed(() => toSelectedNumber(bulkForm.parent_id));
const periodeLabelById = computed(
    () => new Map([...props.periodeOptions, ...targetPeriodOptionsForInput.value].map((option) => [Number(option.id), option.label])),
);
const periodeYearById = computed(
    () => new Map([...props.periodeOptions, ...targetPeriodOptionsForInput.value].map((option) => [Number(option.id), option.tahun ?? null])),
);
const bulkPeriodLabel = (periodeId: number | string | null | undefined) => periodeLabelById.value.get(Number(periodeId)) ?? '-';
const isPrakiraanMajuPeriod = (periodeId: number | string | null | undefined) => {
    const year = periodeYearById.value.get(Number(periodeId));

    return typeof year === 'number' && isPrakiraanMajuYear(year);
};
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
            definisi_operasional: indicator.definisi_operasional,
            alasan_pemilihan: indicator.alasan_pemilihan,
            formulasi_pengukuran: indicator.formulasi_pengukuran,
            tipe_perhitungan: indicator.tipe_perhitungan || 'non_kumulatif',
            sumber_data: indicator.sumber_data,
            opd: indicator.opd?.singkatan || indicator.opd?.nama || null,
            opd_id: indicator.opd_id ?? null,
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
                    misi_ids: tujuan.misi_ids,
                    misi_label: joinItems(tujuan.misi_terkait.map((misi) => misi.misi)),
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
                        indikator_tujuan_ids: sasaran.indikator_tujuan_ids,
                        indikator_tujuan_label: joinItems(sasaran.indikator_tujuan_terkait.map((indikator) => indikator.indikator)),
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

                sasaran.indikator.forEach((indikatorSasaran) => {
                    indikatorSasaran.programs.forEach((program) => {
                        if (bulkForm.type === 'program') {
                            rows.push({
                                id: program.id,
                                type: 'program',
                                parent_id: indikatorSasaran.id,
                                parent_label: nodeText(indikatorSasaran.kode, indikatorSasaran.indikator),
                                kode: program.kode,
                                uraian: program.nama,
                                strategi_daerah_id: program.strategi_daerah_id ?? null,
                                strategi: program.strategi ? nodeText(program.strategi.kode, program.strategi.strategi) : null,
                                program_pemerintahan_id: program.program_pemerintahan_id ?? null,
                                program_pemerintahan: program.program_pemerintahan
                                    ? nodeText(program.program_pemerintahan.kode, program.program_pemerintahan.nama)
                                    : null,
                                urusan_pemerintahan_id: program.urusan_pemerintahan_id ?? null,
                                urusan: program.urusan_pemerintahan
                                    ? nodeText(program.urusan_pemerintahan.kode, program.urusan_pemerintahan.nama)
                                    : null,
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
    if (bulkIsTargetType.value) {
        return [];
    }

    const selectedParentId = selectedBulkParentId.value;

    return bulkExistingRows.value.filter((row) => {
        if (selectedParentId && row.parent_id !== selectedParentId) {
            return false;
        }

        return true;
    });
});
const misiSummary = (tujuan: Tujuan) => joinItems(tujuan.misi_terkait.map((misi) => misi.misi));
const emptyIndicatorPreview = (): IndicatorPreviewRow => ({ key: 'empty', label: '-', satuan: '-', target_by_year: {} });
const indicatorSatuan = (item: Indikator) => item.satuan?.simbol || item.satuan?.nama || '-';
const formatTargetNumber = (value?: string | number | null) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 4,
    }).format(numeric);
};
const targetValue = (target: Target) => formatTargetNumber(target.target) || valueText(target.target) || valueText(target.target_text);
const targetByYear = (item: Indikator) => {
    const byYear: Record<number, string> = {};

    item.targets.forEach((target) => {
        byYear[target.periode_tahun.tahun] = targetValue(target);
    });

    return byYear;
};
const indicatorPreviewRows = (items: Indikator[]): IndicatorPreviewRow[] =>
    items.length
        ? items.map((item) => ({
              key: String(item.id),
              label: nodeText(item.kode, item.indikator),
              satuan: indicatorSatuan(item),
              target_by_year: targetByYear(item),
          }))
        : [emptyIndicatorPreview()];
const relatedTujuanIndicators = (tujuan: Tujuan, sasaran: Sasaran) => {
    if (sasaran.indikator_tujuan_ids.length === 0) {
        return [];
    }

    const ids = new Set(sasaran.indikator_tujuan_ids);

    return tujuan.indikator.filter((indikator) => ids.has(indikator.id));
};

const allIndicators = computed(() => {
    const indicators: Indikator[] = [];

    props.rpjmd.visi.forEach((visi) => {
        visi.tujuan.forEach((tujuan) => {
            indicators.push(...tujuan.indikator);
            tujuan.sasaran.forEach((sasaran) => {
                indicators.push(...sasaran.indikator);
                sasaran.indikator.forEach((indikatorSasaran) => {
                    indikatorSasaran.programs.forEach((program) => {
                        indicators.push(...program.indikator);
                    });
                });
            });
        });
    });

    return indicators;
});
const rpjmdTargetYears = computed(() => {
    const years = new Set<number>();

    for (let year = props.rpjmd.tahun_awal; year <= props.rpjmd.tahun_akhir; year += 1) {
        years.add(year);
    }

    targetPeriodOptionsForInput.value.forEach((periode) => {
        if (periode.tahun) {
            years.add(periode.tahun);
        }
    });

    allIndicators.value.forEach((indicator) => {
        indicator.targets.forEach((target) => years.add(target.periode_tahun.tahun));
    });

    return [...years].sort((a, b) => a - b);
});
const rpjmdPreviewColspan = computed(() => 14 + rpjmdTargetYears.value.length * 3);
const rpjmdPreviewMinWidth = computed(() => `${2200 + rpjmdTargetYears.value.length * 210}px`);

const rpjmdCascadingRows = computed<RpjmdCascadingRow[]>(() => {
    const rows: RpjmdCascadingRow[] = [];
    const addAlignedRows = (
        keyPrefix: string,
        base: Partial<RpjmdCascadingRow>,
        indikatorTujuanRows: IndicatorPreviewRow[],
        indikatorSasaranRows: IndicatorPreviewRow[],
        indikatorProgramRows: IndicatorPreviewRow[],
    ) => {
        const rowCount = Math.max(indikatorTujuanRows.length, indikatorSasaranRows.length, indikatorProgramRows.length, 1);

        for (let index = 0; index < rowCount; index += 1) {
            const indikatorTujuan = indikatorTujuanRows[index] ?? emptyIndicatorPreview();
            const indikatorSasaran = indikatorSasaranRows[index] ?? emptyIndicatorPreview();
            const indikatorProgram = indikatorProgramRows[index] ?? emptyIndicatorPreview();

            rows.push(
                emptyRpjmdRow(`${keyPrefix}-${index}`, {
                    ...base,
                    indikator_tujuan: indikatorTujuan.label,
                    satuan_tujuan: indikatorTujuan.satuan,
                    target_tujuan_by_year: indikatorTujuan.target_by_year,
                    indikator_sasaran: indikatorSasaran.label,
                    satuan_sasaran: indikatorSasaran.satuan,
                    target_sasaran_by_year: indikatorSasaran.target_by_year,
                    indikator_program: indikatorProgram.label,
                    satuan_program: indikatorProgram.satuan,
                    target_program_by_year: indikatorProgram.target_by_year,
                }),
            );
        }
    };

    props.rpjmd.visi.forEach((visi) => {
        if (visi.misi.length === 0 && visi.tujuan.length === 0) {
            rows.push(emptyRpjmdRow(`visi-${visi.id}`, { visi: visi.visi }));
        }

        if (visi.tujuan.length === 0) {
            visi.misi.forEach((misi) => {
                rows.push(emptyRpjmdRow(`misi-${misi.id}`, { visi: visi.visi, misi: nodeText(misi.kode, misi.misi) }));
            });
        }

        visi.tujuan.forEach((tujuan) => {
            if (tujuan.indikator.length > 0 || tujuan.sasaran.length === 0) {
                addAlignedRows(
                    `tujuan-${tujuan.id}`,
                    {
                        visi: visi.visi,
                        misi: misiSummary(tujuan),
                        tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                    },
                    indicatorPreviewRows(tujuan.indikator),
                    [],
                    [],
                );
            }

            tujuan.sasaran.forEach((sasaran) => {
                const tujuanIndicatorsForSasaran = relatedTujuanIndicators(tujuan, sasaran);

                if (sasaran.indikator.length === 0) {
                    addAlignedRows(
                        `sasaran-${sasaran.id}`,
                        {
                            visi: visi.visi,
                            misi: misiSummary(tujuan),
                            tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                            sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                        },
                        indicatorPreviewRows(tujuanIndicatorsForSasaran),
                        [],
                        [],
                    );
                }

                sasaran.indikator.forEach((indikatorSasaran) => {
                    const sasaranIndicatorRows = indicatorPreviewRows([indikatorSasaran]);

                    if (indikatorSasaran.programs.length === 0) {
                        addAlignedRows(
                            `indikator-sasaran-${indikatorSasaran.id}`,
                            {
                                visi: visi.visi,
                                misi: misiSummary(tujuan),
                                tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                            },
                            indicatorPreviewRows(tujuanIndicatorsForSasaran),
                            sasaranIndicatorRows,
                            [],
                        );
                    }

                    indikatorSasaran.programs.forEach((program) => {
                        addAlignedRows(
                            `program-${program.id}`,
                            {
                                visi: visi.visi,
                                misi: misiSummary(tujuan),
                                tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                strategi: program.strategi ? nodeText(program.strategi.kode, program.strategi.strategi) : '-',
                                program: nodeText(program.kode, program.nama),
                                opd_penanggung_jawab: joinItems(program.opd_penanggung_jawab.map((opd) => opd.singkatan || opd.nama)),
                                status_keterhubungan: program.opd_penanggung_jawab.length > 0 ? 'Terhubung OPD' : 'Belum ada OPD',
                            },
                            indicatorPreviewRows(tujuanIndicatorsForSasaran),
                            sasaranIndicatorRows,
                            indicatorPreviewRows(program.indikator),
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
        'misi',
        'tujuan',
        'indikator_tujuan',
        'sasaran',
        'indikator_sasaran',
        'strategi',
        'program',
        'indikator_program',
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

        if (!next.indikator_tujuan) {
            next.satuan_tujuan = '';
            next.target_tujuan_by_year = {};
        }

        if (!next.indikator_sasaran) {
            next.satuan_sasaran = '';
            next.target_sasaran_by_year = {};
        }

        if (!next.indikator_program) {
            next.satuan_program = '';
            next.target_program_by_year = {};
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
        satuan_tujuan: '-',
        target_tujuan_by_year: {},
        sasaran: '-',
        indikator_sasaran: '-',
        satuan_sasaran: '-',
        target_sasaran_by_year: {},
        strategi: '-',
        program: '-',
        indikator_program: '-',
        satuan_program: '-',
        target_program_by_year: {},
        opd_penanggung_jawab: '-',
        status_keterhubungan: '-',
        ...values,
    };
}

const clearNodeForm = () => {
    form.parent_id = '';
    form.misi_ids = [];
    form.indikator_tujuan_ids = [];
    form.periode_tahun_id = '';
    form.satuan_indikator_id = '';
    form.opd_id = '';
    form.urusan_pemerintahan_id = '';
    form.strategi_daerah_id = '';
    form.program_pemerintahan_id = '';
    form.uraian = '';
    form.indikator = '';
    form.definisi_operasional = '';
    form.alasan_pemilihan = '';
    form.formulasi_pengukuran = '';
    form.tipe_perhitungan = 'non_kumulatif';
    form.sumber_data = '';
    form.target = '';
    form.target_text = '';
    form.peran = 'penanggung_jawab';
    form.is_utama = true;
    form.urutan = 1;
    form.clearErrors();
};

const resetNodeForm = () => {
    editingNode.value = null;
    clearNodeForm();
};

const valueText = (value: unknown) => (value === null || value === undefined ? '' : String(value));
const buildTargetBulkRowsFromPeriods = () => {
    const selectedParentId = selectedBulkParentId.value;

    return targetPeriodOptionsForInput.value.map((periode, index) => {
        const row = emptyBulkRow(index);
        const periodeId = toSelectedNumber(periode.id);
        const existing =
            selectedParentId && periodeId
                ? (bulkExistingRows.value.find((item) => item.parent_id === selectedParentId && item.periode_tahun_id === periodeId) ?? null)
                : null;

        row.existing_target_id = existing?.id ?? null;
        row.parent_id = selectedParentId ? String(selectedParentId) : '';
        row.periode_tahun_id = periode.id;
        row.target = valueText(existing?.target ?? existing?.target_text);
        row.target_text = '';
        row.urutan = index + 1;

        return row;
    });
};

const resetBulkTargetPeriodRows = () => {
    clearAllNewBulkRowState();
    bulkForm.rows = buildTargetBulkRowsFromPeriods();
    bulkForm.rows.forEach((row) => {
        const key = newBulkKey(row);
        newBulkBaselines[key] = newBulkSnapshot(row);
        newBulkSaveState[key] = row.existing_target_id ? 'saved' : 'idle';
    });
    bulkForm.clearErrors();
};

const resetBulkRows = (count = 1) => {
    if (bulkIsTargetType.value) {
        resetBulkTargetPeriodRows();
        return;
    }

    clearAllNewBulkRowState();
    bulkForm.rows = Array.from({ length: count }, (_, index) => emptyBulkRow(bulkVisibleExistingRows.value.length + index));
    bulkForm.clearErrors();
};

const renumberBulkRows = () => {
    bulkForm.rows.forEach((row, rowIndex) => {
        row.urutan = bulkVisibleExistingRows.value.length + rowIndex + 1;
    });
};

const addBulkRow = () => {
    if (bulkIsTargetType.value) {
        return;
    }

    bulkForm.rows.push(emptyBulkRow(bulkVisibleExistingRows.value.length + bulkForm.rows.length));
    renumberBulkRows();
};

const removeBulkRow = (index: number) => {
    if (bulkIsTargetType.value) {
        return;
    }

    const row = bulkForm.rows[index];

    if (row && !bulkRowCanRemove(row)) {
        return;
    }

    if (row) {
        clearNewBulkRowState(row);
    }

    if (bulkForm.rows.length === 1) {
        bulkForm.rows = [emptyBulkRow(bulkVisibleExistingRows.value.length)];
        return;
    }

    bulkForm.rows.splice(index, 1);
    renumberBulkRows();
};

const bulkRowHasInput = (row: BulkRow, type: NodeType = bulkForm.type) => {
    if (isAnnualTargetType(type)) {
        return Boolean(row.existing_target_id) || trimText(valueText(row.target)).length > 0;
    }

    return [
        row.uraian,
        row.indikator,
        row.definisi_operasional,
        row.alasan_pemilihan,
        row.formulasi_pengukuran,
        row.sumber_data,
        row.target,
        row.opd_id,
        row.program_pemerintahan_id,
    ].some((value) => trimText(valueText(value)).length > 0);
};
const bulkRowCanRemove = (row: BulkRow) => !bulkIsTargetType.value && (bulkForm.rows.length > 1 || bulkRowHasInput(row));
const targetRowHasUnsavedChanges = (row: BulkRow) => {
    const baseline = newBulkBaselines[newBulkKey(row)];

    if (baseline !== undefined) {
        return baseline !== newBulkSnapshot(row);
    }

    return !row.existing_target_id && trimText(valueText(row.target)).length > 0;
};
const hasUnsavedBulkRowsForType = (type: NodeType) =>
    isAnnualTargetType(type) ? bulkForm.rows.some(targetRowHasUnsavedChanges) : bulkForm.rows.some((row) => bulkRowHasInput(row, type));
const hasUnsavedNewBulkRows = computed(() => hasUnsavedBulkRowsForType(bulkForm.type));
const unsavedNewBulkRowsMessage = 'Ada perubahan yang belum disimpan. Jika lanjut, perubahan tersebut akan hilang.';
let allowUnsavedNewBulkVisit = false;
let stopInertiaBeforeGuard: VoidFunction | null = null;

const savedBulkEdits = reactive<Record<string, BulkRow>>({});
const savedBulkSaving = ref<string | null>(null);
const savedBulkAutosaveEnabled = ref(false);
const savedBulkAutoSaveTimers = new Map<string, number>();
const savedBulkSaveState = reactive<Record<string, SavedBulkSaveState>>({});
const savedBulkSaveErrors = reactive<Record<string, string>>({});
const savedBulkBaselines = reactive<Record<string, string>>({});
const savedBulkSavedAt = reactive<Record<string, string>>({});
const savedBulkShowSavedText = reactive<Record<string, boolean>>({});
const savedBulkSavedTextTimers = new Map<string, number>();
const savedBulkKey = (row: BulkExistingRow) => `${row.type}-${row.id}`;
const savedBulkDisplayNumber = (row: BulkExistingRow) => {
    const index = bulkVisibleExistingRows.value.findIndex((item) => savedBulkKey(item) === savedBulkKey(row));

    return index >= 0 ? index + 1 : Number(row.urutan ?? 1);
};
const clearSavedBulkSavedTextTimer = (key: string) => {
    const timer = savedBulkSavedTextTimers.get(key);

    if (timer) {
        window.clearTimeout(timer);
        savedBulkSavedTextTimers.delete(key);
    }
};
const flashSavedBulkSavedText = (key: string) => {
    clearSavedBulkSavedTextTimer(key);
    savedBulkShowSavedText[key] = true;
    savedBulkSavedTextTimers.set(
        key,
        window.setTimeout(() => {
            savedBulkShowSavedText[key] = false;
            savedBulkSavedTextTimers.delete(key);
        }, 2000),
    );
};
const clearAllSavedBulkSavedTextTimers = () => {
    savedBulkSavedTextTimers.forEach((timer) => window.clearTimeout(timer));
    savedBulkSavedTextTimers.clear();
    Object.keys(savedBulkShowSavedText).forEach((key) => {
        delete savedBulkShowSavedText[key];
    });
};

const bulkExistingToFormRow = (row: BulkExistingRow): BulkRow => ({
    parent_id: valueText(row.parent_id),
    misi_ids: [...(row.misi_ids ?? [])],
    indikator_tujuan_ids: [...(row.indikator_tujuan_ids ?? [])],
    periode_tahun_id: valueText(row.periode_tahun_id),
    satuan_indikator_id: valueText(row.satuan_indikator_id),
    opd_id: valueText(row.opd_id),
    urusan_pemerintahan_id: valueText(row.urusan_pemerintahan_id),
    strategi_daerah_id: valueText(row.strategi_daerah_id),
    program_pemerintahan_id: valueText(row.program_pemerintahan_id),
    uraian: valueText(row.uraian),
    indikator: valueText(row.indikator),
    definisi_operasional: valueText(row.definisi_operasional),
    alasan_pemilihan: valueText(row.alasan_pemilihan),
    formulasi_pengukuran: valueText(row.formulasi_pengukuran),
    tipe_perhitungan: row.tipe_perhitungan === 'kumulatif' ? 'kumulatif' : 'non_kumulatif',
    sumber_data: valueText(row.sumber_data),
    target: valueText(row.target),
    target_text: '',
    peran: valueText(row.peran || 'penanggung_jawab'),
    is_utama: Boolean(row.is_utama ?? true),
    urutan: savedBulkDisplayNumber(row),
});

const savedBulkSnapshot = (row: BulkRow) =>
    JSON.stringify({
        parent_id: valueText(row.parent_id),
        misi_ids: [...row.misi_ids],
        indikator_tujuan_ids: [...row.indikator_tujuan_ids],
        periode_tahun_id: valueText(row.periode_tahun_id),
        satuan_indikator_id: valueText(row.satuan_indikator_id),
        opd_id: valueText(row.opd_id),
        urusan_pemerintahan_id: valueText(row.urusan_pemerintahan_id),
        strategi_daerah_id: valueText(row.strategi_daerah_id),
        program_pemerintahan_id: valueText(row.program_pemerintahan_id),
        uraian: valueText(row.uraian),
        indikator: valueText(row.indikator),
        definisi_operasional: valueText(row.definisi_operasional),
        alasan_pemilihan: valueText(row.alasan_pemilihan),
        formulasi_pengukuran: valueText(row.formulasi_pengukuran),
        tipe_perhitungan: row.tipe_perhitungan,
        sumber_data: valueText(row.sumber_data),
        target: valueText(row.target),
        target_text: '',
        peran: valueText(row.peran),
        is_utama: Boolean(row.is_utama),
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
                delete savedBulkShowSavedText[key];
                window.clearTimeout(savedBulkAutoSaveTimers.get(key));
                savedBulkAutoSaveTimers.delete(key);
                clearSavedBulkSavedTextTimer(key);
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

const hasUnsavedSavedBulkRowsForType = (type: NodeType) =>
    bulkExistingRows.value
        .filter((row) => row.type === type)
        .some((row) => {
            const key = savedBulkKey(row);
            const baseline = savedBulkBaselines[key];
            const editable = savedBulkEdits[key];

            return baseline !== undefined && editable !== undefined && savedBulkSnapshot(editable) !== baseline;
        });
const hasUnsavedBulkChangesForType = (type: NodeType) => hasUnsavedBulkRowsForType(type) || hasUnsavedSavedBulkRowsForType(type);
const shouldGuardUnsavedNewBulkRows = (type: NodeType = bulkForm.type) =>
    props.can.manage &&
    (type === bulkForm.type ? hasUnsavedNewBulkRows.value || hasUnsavedSavedBulkRowsForType(type) : hasUnsavedBulkChangesForType(type));
const confirmDiscardNewBulkRows = (type: NodeType = bulkForm.type) =>
    !shouldGuardUnsavedNewBulkRows(type) || window.confirm(unsavedNewBulkRowsMessage);
const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!shouldGuardUnsavedNewBulkRows() || allowUnsavedNewBulkVisit) {
        return;
    }

    event.preventDefault();
    event.returnValue = unsavedNewBulkRowsMessage;
    return unsavedNewBulkRowsMessage;
};
const changeBulkNodeType = (type: NodeType) => {
    if (type === bulkForm.type || !confirmDiscardNewBulkRows(bulkForm.type)) {
        return;
    }

    bulkForm.type = type;
};

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
        form.misi_ids = [...(node.misi_ids ?? [])];
    } else if (type === 'sasaran') {
        form.uraian = valueText(node.sasaran);
        form.indikator_tujuan_ids = [...(node.indikator_tujuan_ids ?? [])];
    } else if (type === 'program') {
        form.uraian = valueText(node.nama);
        form.strategi_daerah_id = valueText(node.strategi_daerah_id);
        form.urusan_pemerintahan_id = valueText(node.urusan_pemerintahan_id);
        form.program_pemerintahan_id = valueText(node.program_pemerintahan_id);
    } else if (isIndicatorType.value) {
        form.indikator = valueText(node.indikator);
        form.satuan_indikator_id = valueText(node.satuan_indikator_id);
        form.opd_id = valueText(node.opd_id);
        form.definisi_operasional = valueText(node.definisi_operasional);
        form.alasan_pemilihan = valueText(node.alasan_pemilihan);
        form.formulasi_pengukuran = valueText(node.formulasi_pengukuran);
        form.tipe_perhitungan = valueText(node.tipe_perhitungan || 'non_kumulatif');
        form.sumber_data = valueText(node.sumber_data);
    } else if (isTargetType.value) {
        const target = node as unknown as Target;
        form.periode_tahun_id = target.periode_tahun?.id ?? '';
        form.target = valueText(target.target);
        form.target_text = '';
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
    () => form.parent_id,
    () => {
        if (editingNode.value) {
            return;
        }

        if (form.type === 'tujuan') {
            form.misi_ids = [];
        }

        if (form.type === 'sasaran') {
            form.indikator_tujuan_ids = [];
        }

        if (form.type === 'program') {
            form.strategi_daerah_id = '';
            form.program_pemerintahan_id = '';
        }
    },
);

watch(
    () => bulkForm.parent_id,
    () => {
        if (bulkIsTargetType.value) {
            resetBulkTargetPeriodRows();
        }

        if (bulkForm.type === 'tujuan') {
            bulkForm.misi_ids = [];
        }

        if (bulkForm.type === 'sasaran') {
            bulkForm.indikator_tujuan_ids = [];
        }

        if (bulkForm.type === 'program') {
            bulkForm.strategi_daerah_id = '';
            bulkForm.program_pemerintahan_id = '';
            bulkForm.rows.forEach((row) => {
                row.strategi_daerah_id = '';
                row.program_pemerintahan_id = '';
            });
        }
    },
);

watch(
    () => bulkForm.type,
    (type, previousType) => {
        if (type === previousType) {
            return;
        }

        bulkForm.parent_id = '';
        bulkForm.misi_ids = [];
        bulkForm.indikator_tujuan_ids = [];
        bulkForm.periode_tahun_id = '';
        bulkForm.satuan_indikator_id = '';
        bulkForm.urusan_pemerintahan_id = '';
        bulkForm.strategi_daerah_id = '';
        bulkForm.program_pemerintahan_id = '';
        bulkForm.peran = 'penanggung_jawab';
        bulkForm.is_utama = true;
        resetBulkRows();
    },
);

watch(
    () => bulkForm.type,
    (type) => {
        if (completeDataNodeTypes.has(type) && !props.previewLoaded && !editorDataLoading.value) {
            loadCompleteRpjmdData(false);
        }
    },
);

watch(
    [() => bulkForm.type, () => bulkParentOptions.value.map((option) => option.id).join(',')],
    () => {
        if (bulkHidesParentSelector.value) {
            bulkForm.parent_id = bulkParentOptions.value[0]?.id ?? '';
            return;
        }

        if (bulkForm.type === 'indikator_tujuan' && bulkParentOptions.value.length === 1 && !bulkForm.parent_id) {
            bulkForm.parent_id = bulkParentOptions.value[0].id;
        }
    },
    { immediate: true },
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

const normalizedBulkRowsForStore = (rows: BulkRow[]) =>
    rows.map((row, rowIndex) => {
        const currentIndex = bulkForm.rows.findIndex((item) => item.client_id === row.client_id);

        return {
            ...row,
            target_text: '',
            urutan: bulkVisibleExistingRows.value.length + (currentIndex >= 0 ? currentIndex : rowIndex) + 1,
        };
    });

const submitBulkNodes = () => {
    bulkForm.rows = normalizedBulkRowsForStore(bulkForm.rows);
    allowUnsavedNewBulkVisit = true;

    bulkForm.post(route('rpjmd.nodes.bulk-store', props.rpjmd.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetBulkRows();
        },
        onFinish: () => {
            allowUnsavedNewBulkVisit = false;
        },
    });
};

const savedBulkPayload = (row: BulkExistingRow) => {
    const editable = editableSavedBulkRow(row);

    return {
        type: row.type,
        parent_id: editable.parent_id,
        misi_ids: editable.misi_ids,
        indikator_tujuan_ids: editable.indikator_tujuan_ids,
        periode_tahun_id: editable.periode_tahun_id,
        satuan_indikator_id: editable.satuan_indikator_id,
        opd_id: editable.opd_id,
        urusan_pemerintahan_id: editable.urusan_pemerintahan_id,
        strategi_daerah_id: editable.strategi_daerah_id,
        program_pemerintahan_id: editable.program_pemerintahan_id,
        uraian: editable.uraian,
        indikator: editable.indikator,
        definisi_operasional: editable.definisi_operasional,
        alasan_pemilihan: editable.alasan_pemilihan,
        formulasi_pengukuran: editable.formulasi_pengukuran,
        tipe_perhitungan: editable.tipe_perhitungan,
        sumber_data: editable.sumber_data,
        target: editable.target,
        target_text: '',
        peran: editable.peran,
        is_utama: editable.is_utama,
        urutan: !bulkIsTargetType.value && !bulkIsProgramOpdType.value ? savedBulkDisplayNumber(row) : editable.urutan,
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

const markNewBulkChanged = (row: BulkRow) => {
    const key = newBulkKey(row);

    if (bulkIsTargetType.value && row.existing_target_id && newBulkBaselines[key] === newBulkSnapshot(row)) {
        newBulkSaveState[key] = 'saved';
        newBulkSaveErrors[key] = '';
        return;
    }

    if (!bulkRowHasInput(row)) {
        newBulkSaveState[key] = 'idle';
        newBulkSaveErrors[key] = '';
        return;
    }

    newBulkSaveState[key] = 'dirty';
    newBulkSaveErrors[key] = '';
};
const refreshNewBulkDirtyStates = () => {
    bulkForm.rows.forEach((row) => {
        const key = newBulkKey(row);

        if (bulkIsTargetType.value && row.existing_target_id && newBulkBaselines[key] === newBulkSnapshot(row)) {
            newBulkSaveState[key] = 'saved';
            newBulkSaveErrors[key] = '';
            return;
        }

        if (!bulkRowHasInput(row)) {
            newBulkSaveState[key] = 'idle';
            return;
        }

        newBulkSaveState[key] = 'dirty';
    });
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
    savedBulkShowSavedText[key] = false;
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
        if (latestSnapshot === currentSnapshot) {
            flashSavedBulkSavedText(key);
        }

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

watch(
    () => [
        bulkForm.parent_id,
        bulkForm.periode_tahun_id,
        bulkForm.satuan_indikator_id,
        bulkForm.urusan_pemerintahan_id,
        bulkForm.peran,
        bulkForm.is_utama,
        bulkForm.misi_ids.join(','),
        bulkForm.indikator_tujuan_ids.join(','),
    ],
    refreshNewBulkDirtyStates,
);

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    stopInertiaBeforeGuard = router.on('before', (event) => {
        if (allowUnsavedNewBulkVisit || event.detail.visit.prefetch) {
            return true;
        }

        return confirmDiscardNewBulkRows();
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    stopInertiaBeforeGuard?.();
    stopInertiaBeforeGuard = null;
    savedBulkAutoSaveTimers.forEach((timer) => window.clearTimeout(timer));
    savedBulkAutoSaveTimers.clear();
    clearAllSavedBulkSavedTextTimers();
    clearAllNewBulkRowState();
});

const savedBulkRowState = (row: BulkExistingRow) => savedBulkSaveState[savedBulkKey(row)] ?? 'saved';

const savedBulkRowIndicatorClass = (row: BulkExistingRow) =>
    (
        ({
            dirty: 'border-amber-200 bg-amber-50 text-amber-700',
            saving: 'border-slate-200 bg-white text-slate-700',
            saved: 'border-transparent bg-transparent text-emerald-600 shadow-none',
            error: 'border-red-200 bg-red-50 text-red-700',
            idle: 'border-transparent bg-transparent text-emerald-600 shadow-none',
        }) as Record<SavedBulkSaveState, string>
    )[savedBulkSaveState[savedBulkKey(row)] ?? 'saved'];

const savedBulkRowIndicatorText = (row: BulkExistingRow) => {
    const key = savedBulkKey(row);
    const state = savedBulkSaveState[key] ?? 'saved';

    if (state === 'saving') {
        return 'Saving...';
    }

    if (state === 'saved' && savedBulkShowSavedText[key]) {
        return 'Saved';
    }

    if (state === 'error') {
        return 'Gagal';
    }

    return '';
};

const newBulkRowState = (row: BulkRow) => {
    if (bulkIsTargetType.value) {
        const state = newBulkSaveState[newBulkKey(row)] ?? 'idle';

        if (state === 'saving') {
            return 'saving';
        }

        if (state === 'error' && targetRowHasUnsavedChanges(row)) {
            return 'error';
        }

        if (targetRowHasUnsavedChanges(row)) {
            return 'dirty';
        }

        return row.existing_target_id ? 'saved' : 'idle';
    }

    if (!bulkRowHasInput(row)) {
        return 'idle';
    }

    return newBulkSaveState[newBulkKey(row)] ?? 'dirty';
};
const newBulkRowIndicatorClass = (row: BulkRow) =>
    (
        ({
            dirty: 'border-transparent bg-transparent text-amber-600 shadow-none',
            saving: 'border-slate-200 bg-white text-slate-700',
            saved: 'border-transparent bg-transparent text-emerald-600 shadow-none',
            error: 'border-red-200 bg-red-50 text-red-700',
            idle: 'border-transparent bg-transparent text-transparent shadow-none',
        }) as Record<SavedBulkSaveState, string>
    )[newBulkRowState(row)];
const newBulkRowIndicatorText = (row: BulkRow) => {
    const state = newBulkRowState(row);

    if (state === 'error') {
        return 'Gagal';
    }

    return '';
};
const newBulkStatusHint = (row: BulkRow) => {
    const state = newBulkRowState(row);

    if (newBulkSaveErrors[newBulkKey(row)]) {
        return newBulkSaveErrors[newBulkKey(row)];
    }

    if (state === 'dirty') {
        return 'Belum disimpan. Klik Simpan Data untuk menyimpan baris baru.';
    }

    return '';
};

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

const targetDisplay = (target: Target) => formatTargetNumber(target.target) || '-';
const targetTriwulanDisplay = (target: TargetTriwulan) => formatTargetNumber(target.target_angka) || '-';
const targetTriwulanError = (index: number, field: 'triwulan' | 'target_text' | 'target_angka') =>
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
    <div class="rpjmd-select-scope flex min-w-0 max-w-full flex-col gap-4 p-4">
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

        <section
            v-if="can.manage && !showPreview"
            class="sticky top-14 z-30 overflow-visible rounded-xl border border-slate-200 bg-white shadow-[0_8px_24px_rgba(15,23,42,0.08)] md:top-16"
        >
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#00336C] text-white">
                        <Rows3 class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-950">Input RPJMD</h2>
                        <p class="text-xs text-slate-500">Pilih jenis data, lalu tentukan induknya.</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md border bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
                    :disabled="previewLoading"
                    @click="togglePreview"
                >
                    <Eye class="size-4" />
                    {{ previewLoading ? 'Memuat Preview' : 'Lihat Preview' }}
                </button>
            </div>

            <div class="grid gap-3 p-4 lg:grid-cols-2 lg:items-end">
                <div class="grid min-w-0 gap-2">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800" for="bulk_type">
                        <span class="flex size-6 items-center justify-center rounded-full bg-[#00336C] text-xs font-bold text-white">1</span>
                        Jenis Data
                    </label>
                    <RpjmdNodeTypePicker
                        id="bulk_type"
                        :model-value="bulkForm.type"
                        :options="typeOptions"
                        @update:model-value="changeBulkNodeType"
                    />
                    <InputError :message="bulkForm.errors.type" />
                </div>

                <div v-if="bulkNeedsParent" class="grid min-w-0 gap-2">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <span
                            class="flex size-6 items-center justify-center rounded-full text-xs font-bold"
                            :class="bulkInputContextReady ? 'bg-[#00336C] text-white' : 'bg-slate-100 text-slate-500'"
                        >
                            2
                        </span>
                        {{ bulkParentLabel }}
                    </div>

                    <RpjmdRichSelect
                        v-if="bulkShouldShowParentSelector"
                        id="bulk_parent_id"
                        v-model="bulkForm.parent_id"
                        :options="decoratedBulkParentOptions"
                        :placeholder="`Pilih ${bulkParentLabel.toLowerCase()}`"
                        :empty-text="`${bulkParentLabel} belum tersedia`"
                        :invalid="bulkParentOptions.length === 0"
                    />
                    <div
                        v-else
                        class="flex min-h-11 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm"
                    >
                        <span class="min-w-0 truncate font-semibold text-slate-700">
                            {{ bulkSelectedParentOption?.label || `${bulkParentLabel} belum tersedia` }}
                        </span>
                        <CheckCircle2 v-if="bulkInputContextReady" class="size-4 shrink-0 text-[#00336C]" />
                    </div>
                    <InputError :message="bulkForm.errors.parent_id" />
                </div>
            </div>
        </section>

        <section v-if="!can.manage || showPreview" class="rounded-xl border bg-card p-4 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-blue-50 p-2 text-blue-700">
                        <Rows3 class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold">Input RPJMD</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Kelola cascading RPJMD dalam tabel.</p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted disabled:cursor-wait disabled:opacity-60"
                        :disabled="previewLoading"
                        @click="togglePreview"
                    >
                        <EyeOff v-if="showPreview" class="size-4" />
                        <Eye v-else class="size-4" />
                        {{ previewLoading ? 'Memuat Preview' : showPreview ? 'Sembunyikan Preview' : 'Lihat Preview' }}
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

        <div class="grid min-w-0 gap-4">
            <section v-if="showPreview && viewMode === 'table'" class="min-w-0 overflow-hidden rounded-lg border bg-card">
                <div class="flex items-center gap-2 border-b p-4">
                    <Table2 class="size-5 text-emerald-700" />
                    <div>
                        <h2 class="text-base font-semibold">Tabel Cascading RPJMD</h2>
                    </div>
                </div>
                <div class="max-h-[72vh] w-full min-w-0 max-w-full overflow-auto overscroll-contain pb-3">
                    <table class="border-collapse text-left text-sm" :style="{ minWidth: rpjmdPreviewMinWidth }">
                        <thead class="sticky top-0 z-10 bg-[#b8d1f6] text-xs font-bold uppercase tracking-normal text-slate-950">
                            <tr>
                                <th rowspan="2" class="w-[190px] border border-slate-700 px-3 py-3 text-center align-middle">Visi</th>
                                <th rowspan="2" class="w-[190px] border border-slate-700 px-3 py-3 text-center align-middle">Misi</th>
                                <th rowspan="2" class="w-[210px] border border-slate-700 px-3 py-3 text-center align-middle">Tujuan</th>
                                <th rowspan="2" class="w-[210px] border border-slate-700 px-3 py-3 text-center align-middle">Indikator Tujuan</th>
                                <th rowspan="2" class="w-[90px] border border-slate-700 px-3 py-3 text-center align-middle">Satuan</th>
                                <th :colspan="rpjmdTargetYears.length" class="border border-slate-700 px-3 py-3 text-center align-middle">
                                    Target / Prakiraan Maju
                                </th>
                                <th rowspan="2" class="w-[220px] border border-slate-700 px-3 py-3 text-center align-middle">Sasaran Strategis</th>
                                <th rowspan="2" class="w-[230px] border border-slate-700 px-3 py-3 text-center align-middle">
                                    Indikator Kinerja Sasaran Strategis
                                </th>
                                <th rowspan="2" class="w-[90px] border border-slate-700 px-3 py-3 text-center align-middle">Satuan</th>
                                <th :colspan="rpjmdTargetYears.length" class="border border-slate-700 px-3 py-3 text-center align-middle">
                                    Target / Prakiraan Maju
                                </th>
                                <th rowspan="2" class="w-[220px] border border-slate-700 px-3 py-3 text-center align-middle">Strategi</th>
                                <th rowspan="2" class="w-[230px] border border-slate-700 px-3 py-3 text-center align-middle">Program RPJMD</th>
                                <th rowspan="2" class="w-[230px] border border-slate-700 px-3 py-3 text-center align-middle">Indikator Program</th>
                                <th rowspan="2" class="w-[90px] border border-slate-700 px-3 py-3 text-center align-middle">Satuan</th>
                                <th :colspan="rpjmdTargetYears.length" class="border border-slate-700 px-3 py-3 text-center align-middle">
                                    Target / Prakiraan Maju
                                </th>
                                <th rowspan="2" class="w-[170px] border border-slate-700 px-3 py-3 text-center align-middle">OPD</th>
                                <th rowspan="2" class="w-[150px] border border-slate-700 px-3 py-3 text-center align-middle">Status</th>
                            </tr>
                            <tr>
                                <th
                                    v-for="year in rpjmdTargetYears"
                                    :key="`target-tujuan-${year}`"
                                    class="w-[76px] border border-slate-700 px-2 py-2 text-center align-middle"
                                    :class="isPrakiraanMajuYear(year) ? 'bg-[#dcecff] text-[#00336C]' : ''"
                                    :title="targetYearTitle(year)"
                                >
                                    {{ targetYearLabel(year) }}
                                </th>
                                <th
                                    v-for="year in rpjmdTargetYears"
                                    :key="`target-sasaran-${year}`"
                                    class="w-[76px] border border-slate-700 px-2 py-2 text-center align-middle"
                                    :class="isPrakiraanMajuYear(year) ? 'bg-[#dcecff] text-[#00336C]' : ''"
                                    :title="targetYearTitle(year)"
                                >
                                    {{ targetYearLabel(year) }}
                                </th>
                                <th
                                    v-for="year in rpjmdTargetYears"
                                    :key="`target-program-${year}`"
                                    class="w-[76px] border border-slate-700 px-2 py-2 text-center align-middle"
                                    :class="isPrakiraanMajuYear(year) ? 'bg-[#dcecff] text-[#00336C]' : ''"
                                    :title="targetYearTitle(year)"
                                >
                                    {{ targetYearLabel(year) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rpjmdCascadingTableRows" :key="row.key" class="border-b align-top last:border-0 hover:bg-muted/20">
                                <td class="border border-slate-300 px-3 py-3 font-medium leading-6">{{ row.visi }}</td>
                                <td class="border border-slate-300 px-3 py-3 leading-6">{{ row.misi }}</td>
                                <td class="border border-slate-300 px-3 py-3 font-medium leading-6">{{ row.tujuan }}</td>
                                <td class="border border-slate-300 px-3 py-3 leading-6">{{ row.indikator_tujuan }}</td>
                                <td class="border border-slate-300 px-3 py-3 text-center leading-6">{{ row.satuan_tujuan }}</td>
                                <td
                                    v-for="year in rpjmdTargetYears"
                                    :key="`row-target-tujuan-${row.key}-${year}`"
                                    class="border border-slate-300 px-2 py-3 text-center leading-6"
                                    :class="isPrakiraanMajuYear(year) ? 'bg-blue-50/60 font-medium text-[#00336C]' : ''"
                                >
                                    {{ row.target_tujuan_by_year[year] || '' }}
                                </td>
                                <td class="border border-slate-300 px-3 py-3 font-medium leading-6">{{ row.sasaran }}</td>
                                <td class="border border-slate-300 px-3 py-3 leading-6">{{ row.indikator_sasaran }}</td>
                                <td class="border border-slate-300 px-3 py-3 text-center leading-6">{{ row.satuan_sasaran }}</td>
                                <td
                                    v-for="year in rpjmdTargetYears"
                                    :key="`row-target-sasaran-${row.key}-${year}`"
                                    class="border border-slate-300 px-2 py-3 text-center leading-6"
                                    :class="isPrakiraanMajuYear(year) ? 'bg-blue-50/60 font-medium text-[#00336C]' : ''"
                                >
                                    {{ row.target_sasaran_by_year[year] || '' }}
                                </td>
                                <td class="border border-slate-300 px-3 py-3 leading-6">{{ row.strategi }}</td>
                                <td class="border border-slate-300 px-3 py-3 font-medium leading-6">{{ row.program }}</td>
                                <td class="border border-slate-300 px-3 py-3 leading-6">{{ row.indikator_program }}</td>
                                <td class="border border-slate-300 px-3 py-3 text-center leading-6">{{ row.satuan_program }}</td>
                                <td
                                    v-for="year in rpjmdTargetYears"
                                    :key="`row-target-program-${row.key}-${year}`"
                                    class="border border-slate-300 px-2 py-3 text-center leading-6"
                                    :class="isPrakiraanMajuYear(year) ? 'bg-blue-50/60 font-medium text-[#00336C]' : ''"
                                >
                                    {{ row.target_program_by_year[year] || '' }}
                                </td>
                                <td class="border border-slate-300 px-3 py-3 leading-6">{{ row.opd_penanggung_jawab }}</td>
                                <td class="border border-slate-300 px-3 py-3">
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
                                <td :colspan="rpjmdPreviewColspan" class="border border-slate-300 px-4 py-10 text-center text-muted-foreground">
                                    Belum ada data cascading RPJMD.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-else-if="showPreview" class="rounded-lg border bg-card">
                <div class="flex items-center gap-2 border-b p-4">
                    <GitBranch class="size-5 text-emerald-700" />
                    <div>
                        <h2 class="text-base font-semibold">Diagram Pohon Kinerja RPJMD</h2>
                        <p class="text-sm text-muted-foreground">Alur visi, misi, tujuan, sasaran strategis, dan indikator.</p>
                    </div>
                </div>

                <div class="p-4">
                    <RpjmdPerformanceTreeDiagram :visi="rpjmd.visi" />
                </div>
            </section>

            <section v-if="false" class="rounded-lg border bg-card">
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
                                        <p class="mt-1 text-xs text-muted-foreground">{{ rpjmdStrukturTujuanLabel }}</p>
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
                                    <p class="mt-1 text-xs text-muted-foreground">{{ rpjmdStrukturTujuanLabel }}</p>
                                </div>

                                <div v-if="visi.tujuan.length" class="mt-3 space-y-3">
                                    <div v-for="tujuan in visi.tujuan" :key="tujuan.id" class="rounded-md border bg-slate-50 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Tujuan Daerah</div>
                                                <div class="mt-1 text-sm font-medium">
                                                    {{ tujuan.tujuan }}
                                                </div>
                                                <div v-if="tujuan.misi_terkait.length" class="mt-2 flex flex-wrap gap-1.5">
                                                    <span
                                                        v-for="misi in tujuan.misi_terkait"
                                                        :key="misi.id"
                                                        class="inline-flex rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800"
                                                    >
                                                        {{ misi.misi }}
                                                    </span>
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
                                                        {{ targetTriwulanDisplay(target) }}
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
                                                        <div v-if="sasaran.indikator_tujuan_terkait.length" class="mt-2 flex flex-wrap gap-1.5">
                                                            <span
                                                                v-for="indikator in sasaran.indikator_tujuan_terkait"
                                                                :key="indikator.id"
                                                                class="inline-flex rounded-full bg-cyan-50 px-2 py-1 text-xs text-cyan-800"
                                                            >
                                                                {{ indikator.indikator }}
                                                            </span>
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
                                                                {{ targetTriwulanDisplay(target) }}
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

                                                        <div v-if="indikator.programs.length" class="mt-3 space-y-3">
                                                            <div
                                                                v-for="program in indikator.programs"
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
                                                                        </div>
                                                                        <div v-if="program.strategi" class="mt-1 text-xs text-blue-800">
                                                                            Strategi: {{ program.strategi.strategi }}
                                                                        </div>
                                                                    </div>
                                                                    <div v-if="can.manage" class="flex items-center gap-1">
                                                                        <button
                                                                            type="button"
                                                                            class="rounded-md p-1 hover:bg-muted"
                                                                            title="Edit program"
                                                                            @click="editNode('program', program.id, indikator.id, program)"
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
                                                                                {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
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
                                                                                {{ targetTriwulanDisplay(target) }}
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

            <aside
                v-if="can.manage && !showPreview && bulkInputContextReady"
                class="relative z-10 overflow-visible rounded-xl border bg-card shadow-sm"
            >
                <div class="rounded-t-xl border-b bg-slate-50/70 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-emerald-50 p-2 text-emerald-700">
                                <Table2 class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Input Data</h2>
                                <p class="mt-1 text-sm text-muted-foreground">{{ bulkTypeLabel }}</p>
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
                                <RpjmdNodeTypePicker id="type" v-model="form.type" :options="typeOptions" />
                                <InputError :message="form.errors.type" />
                            </div>

                            <div v-if="needsParent" class="grid gap-2">
                                <label class="text-sm font-medium" for="parent_id">{{ parentLabel }}</label>
                                <RpjmdRichSelect
                                    id="parent_id"
                                    v-model="form.parent_id"
                                    :options="decoratedParentOptions"
                                    :placeholder="`Pilih ${parentLabel.toLowerCase()}`"
                                    :empty-text="`${parentLabel} belum tersedia`"
                                    :invalid="Boolean(parentEmptyMessage)"
                                />
                                <p v-if="parentEmptyMessage" class="text-xs font-medium text-amber-700">{{ parentEmptyMessage }}</p>
                                <InputError :message="form.errors.parent_id" />
                            </div>

                            <div v-if="form.type === 'tujuan' && formMisiOptions.length" class="grid gap-2 rounded-lg border bg-slate-50/70 p-3">
                                <div class="text-sm font-medium">Misi Terkait</div>
                                <div class="grid gap-2">
                                    <label
                                        v-for="option in formMisiOptions"
                                        :key="option.id"
                                        class="flex items-start gap-2 rounded-md border bg-white px-3 py-2 text-sm"
                                    >
                                        <input v-model="form.misi_ids" type="checkbox" class="mt-1 rounded border" :value="option.id" />
                                        <span>{{ option.label }}</span>
                                    </label>
                                </div>
                                <InputError :message="form.errors.misi_ids" />
                            </div>

                            <div
                                v-if="shouldShowSasaranIndikatorTujuan && formIndikatorTujuanOptions.length"
                                class="grid gap-2 rounded-lg border bg-slate-50/70 p-3"
                            >
                                <div class="text-sm font-medium">Indikator Tujuan Terkait</div>
                                <div class="grid gap-2">
                                    <label
                                        v-for="option in formIndikatorTujuanOptions"
                                        :key="option.id"
                                        class="flex items-start gap-2 rounded-md border bg-white px-3 py-2 text-sm"
                                    >
                                        <input v-model="form.indikator_tujuan_ids" type="checkbox" class="mt-1 rounded border" :value="option.id" />
                                        <span>{{ option.label }}</span>
                                    </label>
                                </div>
                                <InputError :message="form.errors.indikator_tujuan_ids" />
                            </div>

                            <div v-if="isTextNodeType && !isProgramType" class="grid gap-2">
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

                            <div v-if="isProgramType" class="grid gap-2">
                                <label class="text-sm font-medium" for="program_pemerintahan_id">Program</label>
                                <RpjmdRichSelect
                                    id="program_pemerintahan_id"
                                    v-model="form.program_pemerintahan_id"
                                    :options="programPemerintahanOptions"
                                    placeholder="Cari dan pilih program"
                                    empty-text="Master program belum tersedia"
                                />
                                <InputError :message="form.errors.program_pemerintahan_id" />
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
                                <label class="text-sm font-medium" for="definisi_operasional">Definisi Operasional Indikator</label>
                                <textarea
                                    id="definisi_operasional"
                                    v-model="form.definisi_operasional"
                                    rows="2"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Definisi operasional indikator"
                                />
                                <InputError :message="form.errors.definisi_operasional" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="alasan_pemilihan">Alasan Pemilihan Indikator</label>
                                <textarea
                                    id="alasan_pemilihan"
                                    v-model="form.alasan_pemilihan"
                                    rows="2"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Alasan indikator digunakan"
                                />
                                <InputError :message="form.errors.alasan_pemilihan" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="formulasi_pengukuran">Formulasi Pengukuran</label>
                                <textarea
                                    id="formulasi_pengukuran"
                                    v-model="form.formulasi_pengukuran"
                                    rows="2"
                                    class="rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Contoh: (Realisasi / Target) x 100"
                                />
                                <InputError :message="form.errors.formulasi_pengukuran" />
                            </div>

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="tipe_perhitungan">Tipe Perhitungan</label>
                                <select
                                    id="tipe_perhitungan"
                                    v-model="form.tipe_perhitungan"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="non_kumulatif">Non-kumulatif</option>
                                    <option value="kumulatif">Kumulatif</option>
                                </select>
                                <InputError :message="form.errors.tipe_perhitungan" />
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

                            <div v-if="isIndicatorType" class="grid gap-2">
                                <label class="text-sm font-medium" for="opd_id">OPD / PD Penanggung Jawab</label>
                                <select id="opd_id" v-model="form.opd_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                                    <option value="">Pilih OPD</option>
                                    <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.opd_id" />
                            </div>

                            <div v-if="isProgramType" class="grid gap-2">
                                <label class="text-sm font-medium" for="strategi_daerah_id">Strategi</label>
                                <select
                                    id="strategi_daerah_id"
                                    v-model="form.strategi_daerah_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Tidak diset</option>
                                    <option v-for="option in formStrategiOptions" :key="option.id" :value="option.id">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.strategi_daerah_id" />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="periode_tahun_id">Periode Target</label>
                                <select
                                    id="periode_tahun_id"
                                    v-model="form.periode_tahun_id"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Pilih periode</option>
                                    <option v-for="option in targetPeriodOptionsForInput" :key="option.id" :value="option.id">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.periode_tahun_id" />
                            </div>

                            <div v-if="isTargetType" class="grid gap-2">
                                <label class="text-sm font-medium" for="target">Target</label>
                                <input
                                    id="target"
                                    v-model="form.target"
                                    type="text"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                    placeholder="Contoh: 14,79 atau 80 persen"
                                />
                                <InputError :message="form.errors.target" />
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
                                <RpjmdRichSelect
                                    id="target_triwulan_related_id"
                                    v-model="targetTriwulanForm.related_id"
                                    :options="decoratedTargetTriwulanOptions"
                                    placeholder="Pilih indikator"
                                    empty-text="Belum ada indikator pada jenis ini"
                                    :invalid="selectedTargetTriwulanOptions.length === 0"
                                />
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
                                    <option v-for="option in targetPeriodOptionsForInput" :key="option.id" :value="option.id">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="targetTriwulanForm.errors.periode_tahun_id" />
                            </div>

                            <div class="overflow-x-auto rounded-md border">
                                <table class="min-w-[420px] text-sm">
                                    <thead class="bg-muted/60 text-left text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="px-3 py-2">Triwulan</th>
                                            <th class="px-3 py-2">Target Angka</th>
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
                            <section
                                v-if="bulkHasAdditionalSettings"
                                class="grid gap-3 rounded-lg border bg-slate-50/50 p-4 md:grid-cols-2 xl:grid-cols-4"
                            >
                                <div v-if="bulkForm.type === 'tujuan' && bulkMisiOptions.length" class="grid gap-2 xl:col-span-2">
                                    <div class="text-sm font-medium">Misi Terkait</div>
                                    <div class="grid gap-2 rounded-lg border bg-slate-50/70 p-2">
                                        <label
                                            v-for="option in bulkMisiOptions"
                                            :key="option.id"
                                            class="flex items-start gap-2 rounded-md bg-white px-3 py-2 text-sm"
                                        >
                                            <input v-model="bulkForm.misi_ids" type="checkbox" class="mt-1 rounded border" :value="option.id" />
                                            <span>{{ option.label }}</span>
                                        </label>
                                    </div>
                                    <InputError :message="bulkForm.errors.misi_ids" />
                                </div>

                                <div
                                    v-if="bulkShouldShowSasaranIndikatorTujuan && bulkIndikatorTujuanOptions.length"
                                    class="grid gap-2 xl:col-span-2"
                                >
                                    <div class="text-sm font-medium">Indikator Tujuan Terkait</div>
                                    <div class="grid gap-2 rounded-lg border bg-slate-50/70 p-2">
                                        <label
                                            v-for="option in bulkIndikatorTujuanOptions"
                                            :key="option.id"
                                            class="flex items-start gap-2 rounded-md bg-white px-3 py-2 text-sm"
                                        >
                                            <input
                                                v-model="bulkForm.indikator_tujuan_ids"
                                                type="checkbox"
                                                class="mt-1 rounded border"
                                                :value="option.id"
                                            />
                                            <span>{{ option.label }}</span>
                                        </label>
                                    </div>
                                    <InputError :message="bulkForm.errors.indikator_tujuan_ids" />
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
                                    <label class="text-sm font-medium" for="bulk_strategi_daerah_id">Strategi Default</label>
                                    <select
                                        id="bulk_strategi_daerah_id"
                                        v-model="bulkForm.strategi_daerah_id"
                                        class="h-10 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="">Tidak diset default</option>
                                        <option v-for="option in bulkStrategiOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="bulkForm.errors.strategi_daerah_id" />
                                </div>
                            </section>

                            <section class="overflow-hidden rounded-lg border bg-card">
                                <div class="flex flex-col gap-3 border-b bg-slate-50/70 p-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold">Tabel Input {{ bulkTypeLabel }}</h3>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex h-8 items-center rounded-full border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700"
                                        >
                                            {{ bulkTableCounterLabel }}
                                        </span>
                                        <button
                                            v-if="!bulkIsTargetType"
                                            type="button"
                                            class="inline-flex h-8 items-center gap-2 rounded-full border px-3 text-xs font-semibold shadow-sm transition"
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
                                            {{ savedBulkAutosaveEnabled ? 'Autosave aktif' : 'Autosave mati' }}
                                        </button>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table
                                        class="text-left text-sm"
                                        :class="
                                            bulkIsTargetType
                                                ? 'min-w-[760px]'
                                                : bulkIsIndicatorType
                                                  ? 'min-w-[2200px]'
                                                  : bulkIsProgramType
                                                    ? 'min-w-[1320px]'
                                                    : 'min-w-[1100px]'
                                        "
                                    >
                                        <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                                            <tr>
                                                <th class="w-14 px-3 py-2">No</th>
                                                <th v-if="bulkIsTextNodeType" class="min-w-[360px] px-3 py-2">
                                                    {{ bulkIsProgramType ? 'Program' : bulkTypeLabel }}
                                                </th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-[360px] px-3 py-2">Indikator</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-44 px-3 py-2">Satuan</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-72 px-3 py-2">Definisi Operasional</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-72 px-3 py-2">Alasan Pemilihan</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-72 px-3 py-2">Formulasi Pengukuran</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-44 px-3 py-2">Tipe Perhitungan</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-56 px-3 py-2">Sumber Data</th>
                                                <th v-if="bulkIsIndicatorType" class="min-w-72 px-3 py-2">OPD / PD Penanggung Jawab</th>
                                                <th v-if="bulkIsProgramType" class="min-w-56 px-3 py-2">Strategi</th>
                                                <th v-if="bulkIsTargetType" class="min-w-40 px-3 py-2">Periode</th>
                                                <th v-if="bulkIsTargetType" class="min-w-64 px-3 py-2">Target</th>
                                                <th v-if="bulkIsProgramOpdType" class="min-w-80 px-3 py-2">OPD</th>
                                                <th v-if="bulkIsProgramOpdType" class="min-w-44 px-3 py-2">Peran</th>
                                                <th v-if="bulkIsProgramOpdType" class="min-w-32 px-3 py-2">Utama</th>
                                                <th v-if="!bulkIsTargetType" class="w-[112px] px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(saved, savedIndex) in bulkVisibleExistingRows"
                                                :key="`saved-${saved.type}-${saved.id}`"
                                                class="border-b bg-white text-slate-800"
                                            >
                                                <td class="w-14 px-3 py-3 align-top">
                                                    <div class="flex flex-col items-center gap-2">
                                                        <div class="text-sm font-semibold leading-5 text-slate-900">{{ savedIndex + 1 }}</div>
                                                        <span
                                                            class="inline-flex h-7 items-center justify-center gap-1.5 rounded-full border text-[11px] font-semibold transition-all duration-200"
                                                            :class="[
                                                                savedBulkRowIndicatorClass(saved),
                                                                savedBulkRowIndicatorText(saved) ? 'px-2.5' : 'w-7 px-0',
                                                            ]"
                                                            :title="savedBulkSaveErrors[savedBulkKey(saved)] || savedBulkStatusHint(saved)"
                                                        >
                                                            <LoaderCircle
                                                                v-if="savedBulkRowState(saved) === 'saving'"
                                                                class="size-3.5 animate-spin"
                                                            />
                                                            <CheckCircle2
                                                                v-else-if="
                                                                    savedBulkRowState(saved) === 'saved' || savedBulkRowState(saved) === 'idle'
                                                                "
                                                                class="size-3.5"
                                                            />
                                                            <span
                                                                v-else
                                                                class="size-2 rounded-full"
                                                                :class="savedBulkRowState(saved) === 'error' ? 'bg-red-500' : 'bg-amber-500'"
                                                            />
                                                            <span v-if="savedBulkRowIndicatorText(saved)" class="whitespace-nowrap">
                                                                {{ savedBulkRowIndicatorText(saved) }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td v-if="bulkIsTextNodeType && !bulkIsProgramType" class="px-3 py-2 align-top">
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).uraian"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm font-semibold leading-5 text-slate-950"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramType" class="min-w-[360px] px-3 py-2 align-top">
                                                    <RpjmdRichSelect
                                                        v-model="editableSavedBulkRow(saved).program_pemerintahan_id"
                                                        :options="programPemerintahanOptions"
                                                        placeholder="Pilih program"
                                                        empty-text="Master program belum tersedia"
                                                        @update:model-value="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).indikator"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm font-medium leading-5 text-slate-950"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
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
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).definisi_operasional"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).alasan_pemilihan"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <textarea
                                                        v-model="editableSavedBulkRow(saved).formulasi_pengukuran"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <select
                                                        v-model="editableSavedBulkRow(saved).tipe_perhitungan"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markSavedBulkChanged(saved)"
                                                    >
                                                        <option value="non_kumulatif">Non-kumulatif</option>
                                                        <option value="kumulatif">Kumulatif</option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).sumber_data"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markSavedBulkChanged(saved)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2 align-top">
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
                                                </td>
                                                <td v-if="bulkIsProgramType" class="px-3 py-2 align-top">
                                                    <select
                                                        v-model="editableSavedBulkRow(saved).strategi_daerah_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markSavedBulkChanged(saved)"
                                                    >
                                                        <option value="">Tidak diset</option>
                                                        <option v-for="option in strategiOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2 align-top">
                                                    <div
                                                        class="inline-flex h-10 items-center rounded-md border px-3 text-sm font-semibold"
                                                        :class="
                                                            isPrakiraanMajuPeriod(saved.periode_tahun_id)
                                                                ? 'border-blue-200 bg-blue-50 text-[#00336C]'
                                                                : 'border-slate-200 bg-slate-50 text-slate-700'
                                                        "
                                                    >
                                                        {{ bulkPeriodLabel(saved.periode_tahun_id) }}
                                                    </div>
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2 align-top">
                                                    <input
                                                        v-model="editableSavedBulkRow(saved).target"
                                                        type="text"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        placeholder="Isi target"
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
                                                <td v-if="!bulkIsTargetType" class="w-[112px] px-3 py-3 text-center align-top">
                                                    <div
                                                        class="inline-flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.06)]"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-9 items-center justify-center text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500/30 disabled:cursor-not-allowed disabled:opacity-40"
                                                            title="Simpan perubahan"
                                                            aria-label="Simpan perubahan"
                                                            :disabled="savedBulkSaving === savedBulkKey(saved)"
                                                            @click="updateSavedBulkRow(saved)"
                                                        >
                                                            <Save class="size-4" />
                                                        </button>
                                                        <span class="h-5 w-px bg-slate-200" aria-hidden="true" />
                                                        <button
                                                            type="button"
                                                            class="inline-flex size-9 items-center justify-center text-red-600 transition hover:bg-red-50 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-500/30 disabled:cursor-not-allowed disabled:opacity-40"
                                                            title="Hapus data"
                                                            aria-label="Hapus data"
                                                            :disabled="savedBulkSaving === savedBulkKey(saved)"
                                                            @click="destroySavedBulkRow(saved)"
                                                        >
                                                            <Trash2 class="size-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="bulkVisibleExistingRows.length === 0 && !bulkIsTargetType" class="border-b bg-slate-50/60">
                                                <td :colspan="bulkColumnCount" class="px-3 py-4 text-sm text-muted-foreground">
                                                    Belum ada data tersimpan.
                                                </td>
                                            </tr>
                                            <tr v-for="(row, index) in bulkForm.rows" :key="row.client_id" class="border-b last:border-0">
                                                <td class="w-14 px-3 py-3 text-center align-top">
                                                    <div class="flex flex-col items-center gap-2">
                                                        <div class="text-sm font-semibold leading-5 text-slate-700">
                                                            {{ bulkVisibleExistingRows.length + index + 1 }}
                                                        </div>
                                                        <span
                                                            v-if="newBulkRowState(row) !== 'idle' || bulkRowHasInput(row)"
                                                            class="inline-flex h-7 items-center justify-center gap-1.5 rounded-full border text-[11px] font-semibold transition-all duration-200"
                                                            :class="[
                                                                newBulkRowIndicatorClass(row),
                                                                newBulkRowIndicatorText(row) ? 'px-2.5' : 'w-7 px-0',
                                                            ]"
                                                            :title="newBulkStatusHint(row)"
                                                        >
                                                            <LoaderCircle v-if="newBulkRowState(row) === 'saving'" class="size-3.5 animate-spin" />
                                                            <CheckCircle2 v-else-if="newBulkRowState(row) === 'saved'" class="size-3.5" />
                                                            <span
                                                                v-else
                                                                class="size-2 rounded-full"
                                                                :class="newBulkRowState(row) === 'error' ? 'bg-red-500' : 'bg-amber-500'"
                                                            />
                                                            <span v-if="newBulkRowIndicatorText(row)" class="whitespace-nowrap">
                                                                {{ newBulkRowIndicatorText(row) }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td v-if="bulkIsTextNodeType && !bulkIsProgramType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.uraian"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm font-semibold leading-5 text-slate-950"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramType" class="min-w-[360px] px-3 py-2">
                                                    <RpjmdRichSelect
                                                        v-model="row.program_pemerintahan_id"
                                                        :options="programPemerintahanOptions"
                                                        placeholder="Pilih program"
                                                        empty-text="Master program belum tersedia"
                                                        @update:model-value="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.indikator"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm font-medium leading-5 text-slate-950"
                                                        :placeholder="bulkTypeMeta.placeholder"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.satuan_indikator_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markNewBulkChanged(row)"
                                                    >
                                                        <option value="">Default</option>
                                                        <option v-for="option in satuanOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.definisi_operasional"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.alasan_pemilihan"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <textarea
                                                        v-model="row.formulasi_pengukuran"
                                                        rows="2"
                                                        class="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm leading-5"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.tipe_perhitungan"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markNewBulkChanged(row)"
                                                    >
                                                        <option value="non_kumulatif">Non-kumulatif</option>
                                                        <option value="kumulatif">Kumulatif</option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.sumber_data"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsIndicatorType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.opd_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markNewBulkChanged(row)"
                                                    >
                                                        <option value="">Pilih OPD</option>
                                                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsProgramType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.strategi_daerah_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markNewBulkChanged(row)"
                                                    >
                                                        <option value="">Ikuti default</option>
                                                        <option v-for="option in strategiOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2">
                                                    <div
                                                        class="inline-flex h-10 items-center rounded-md border px-3 text-sm font-semibold"
                                                        :class="
                                                            isPrakiraanMajuPeriod(row.periode_tahun_id)
                                                                ? 'border-blue-200 bg-blue-50 text-[#00336C]'
                                                                : 'border-slate-200 bg-slate-50 text-slate-700'
                                                        "
                                                    >
                                                        {{ bulkPeriodLabel(row.periode_tahun_id) }}
                                                    </div>
                                                </td>
                                                <td v-if="bulkIsTargetType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.target"
                                                        type="text"
                                                        :disabled="!bulkForm.parent_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                        placeholder="Isi target"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2">
                                                    <select
                                                        v-model="row.opd_id"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @change="markNewBulkChanged(row)"
                                                    >
                                                        <option value="">Pilih OPD</option>
                                                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2">
                                                    <input
                                                        v-model="row.peran"
                                                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                                        @input="markNewBulkChanged(row)"
                                                    />
                                                </td>
                                                <td v-if="bulkIsProgramOpdType" class="px-3 py-2">
                                                    <label class="inline-flex h-10 items-center gap-2 text-sm">
                                                        <input
                                                            v-model="row.is_utama"
                                                            type="checkbox"
                                                            class="rounded border"
                                                            @change="markNewBulkChanged(row)"
                                                        />
                                                        Utama
                                                    </label>
                                                </td>
                                                <td v-if="!bulkIsTargetType" class="w-[112px] px-3 py-3 text-center align-top">
                                                    <button
                                                        type="button"
                                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 shadow-[0_1px_2px_rgba(15,23,42,0.06)] transition hover:bg-red-50 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/30"
                                                        title="Hapus baris"
                                                        aria-label="Hapus baris"
                                                        :disabled="!bulkRowCanRemove(row)"
                                                        :class="
                                                            !bulkRowCanRemove(row)
                                                                ? 'cursor-not-allowed opacity-40 hover:bg-white hover:text-red-600'
                                                                : ''
                                                        "
                                                        @click="removeBulkRow(index)"
                                                    >
                                                        <Trash2 class="size-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-if="!bulkIsTargetType" class="flex items-center gap-3 border-t bg-slate-50/60 p-3">
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
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        bulkIsTargetType
                                            ? `${bulkFilledRows} tahun berisi target.`
                                            : `${bulkFilledRows} baris akan disimpan sebagai ${bulkTypeLabel}.`
                                    }}
                                </div>
                                <button
                                    type="submit"
                                    :disabled="!bulkCanSubmit"
                                    class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-medium text-white shadow-sm hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <CheckCircle2 class="size-4" />
                                    Simpan Data
                                </button>
                            </div>
                        </form>
                    </template>
                </div>
            </aside>
        </div>
    </div>
</template>
