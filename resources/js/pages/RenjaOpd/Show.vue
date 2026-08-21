<script setup lang="ts">
import PlanningSyncPreview from '@/components/PlanningSyncPreview.vue';
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, ClipboardList, GitBranch, Pencil, Plus, Save, Search, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';

type Option = {
    id: number;
    value?: number | string;
    label: string;
    kode?: string;
    nama?: string;
    description?: string;
    group?: string;
    badge?: string | number | null;
    program_id?: number | null;
    kegiatan_id?: number | null;
    bidang_id?: number | null;
    urusan_id?: number | null;
    sasaran_sub_kegiatan?: string | null;
    indikator_sub_kegiatan?: string | null;
    satuan_indikator_id?: number | null;
    satuan_label?: string | null;
    definisi_operasional?: string | null;
};

type Renja = {
    id: number;
    tahun: number;
    judul: string;
    nomor_dokumen?: string | null;
    status: string;
    jenis_versi: 'awal' | 'ditetapkan' | 'perubahan';
    version_label: string;
    nomor_versi: number;
    is_active_version: boolean;
    alasan_perubahan?: string | null;
    dasar_perubahan?: string | null;
    tanggal_berlaku?: string | null;
    disahkan_pada?: string | null;
    opd_id?: number | null;
    opd_unit_id?: number | null;
    opd?: { id: number; kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { id: number; kode?: string | null; nama: string } | null;
    rkpd?: { id: number; judul: string; tahun: number; jenis_versi: string; version_label: string } | null;
};

type RenjaVersion = {
    id: number;
    jenis_versi: 'awal' | 'ditetapkan' | 'perubahan';
    version_label: string;
    status: string;
    is_active_version: boolean;
    disahkan_pada?: string | null;
};

type Row = {
    id: number;
    opd_id?: number | null;
    opd_unit_id?: number | null;
    sub_kegiatan_pemerintahan_id?: number | null;
    kode?: string | null;
    nama_sub_kegiatan?: string | null;
    indikator?: string | null;
    target_akhir_renstra?: string | null;
    realisasi_capaian_renja_tahun_lalu?: string | null;
    prakiraan_capaian_target_renja_tahun_berjalan?: string | null;
    target?: string | null;
    pagu_indikatif?: string | number | null;
    lokasi?: string | null;
    sumber_dana?: string | null;
    prioritas_nasional?: string | null;
    prioritas_daerah?: string | null;
    kelompok_sasaran?: string | null;
    prakiraan_maju_target?: string | null;
    prakiraan_maju_pagu_indikatif?: string | number | null;
    status: string;
    urutan: number;
    opd?: { id: number; kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { id: number; kode?: string | null; nama: string } | null;
    urusan?: string;
    bidang?: string;
    program?: string;
    kegiatan?: string;
    sub_kegiatan?: string;
    perangkat_daerah_penanggung_jawab?: string | null;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type OfficialPreviewRow = {
    key: string;
    kind: 'opd' | 'urusan' | 'bidang' | 'program' | 'kegiatan' | 'sub' | 'item';
    no?: string;
    kode?: string;
    label?: string;
    indikator?: string | null;
    targetAkhir?: string | null;
    realisasiLalu?: string | null;
    prakiraanBerjalan?: string | null;
    target?: string | null;
    pagu?: number | string | null;
    lokasi?: string | null;
    sumberDana?: string | null;
    prioritasNasional?: string | null;
    prioritasDaerah?: string | null;
    kelompokSasaran?: string | null;
    prakiraanMajuTarget?: string | null;
    prakiraanMajuPagu?: number | string | null;
    pdPenanggungJawab?: string | null;
    source?: Row;
};

type PreviewSum = { pagu: number; prakiraanMajuPagu: number };
type SyncPayload = {
    kode?: string | null;
    nama?: string | null;
    indikator?: string | null;
    target?: string | number | null;
    pagu_indikatif?: string | number | null;
    lokasi?: string | null;
    sumber_dana?: string | null;
    prioritas_nasional?: string | null;
    prioritas_daerah?: string | null;
    kelompok_sasaran?: string | null;
    labels?: {
        opd?: string | null;
        opd_unit?: string | null;
        program?: string | null;
        kegiatan?: string | null;
        sub_kegiatan?: string | null;
    };
};
type SyncDiff = {
    field: string;
    label: string;
    source?: string | number | null;
    target?: string | number | null;
};
type SyncRow = {
    id: number;
    action: 'create' | 'update' | 'unchanged' | 'target_only' | 'skipped';
    selected: boolean;
    status: string;
    message?: string | null;
    diff_values?: {
        source?: SyncPayload | null;
        target?: SyncPayload | null;
        fields?: SyncDiff[];
    } | null;
};
type SyncPreview = {
    id: number;
    source_module: string;
    target_module: string;
    tahun: number;
    status: string;
    summary: Record<string, number>;
    rows: SyncRow[];
};
type Workflow = {
    histories?: Array<{
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
    renja: Renja;
    items: Paginator<Row>;
    previewItems: Row[];
    summary: {
        items_count: number;
        total_pagu: number;
        total_prakiraan_maju_pagu: number;
    };
    filters: { search?: string; status?: string };
    subKegiatanOptions: Option[];
    existingSubKegiatanRows: Array<{ id: number; sub_kegiatan_pemerintahan_id: number | null }>;
    syncPreview?: SyncPreview | null;
    workflow: Workflow;
    versionHistory: RenjaVersion[];
    can: { manage: boolean; review: boolean; lock: boolean; unlock: boolean; createRevision: boolean };
}>();

const renjaItemView = ref<'input' | 'preview'>('input');
const isFormOpen = ref(false);
const editingId = ref<number | null>(null);
const formSection = ref<HTMLElement | null>(null);
const selectedProgramId = ref<string | number>('');
const selectedKegiatanPemerintahanId = ref<string | number>('');
const isHydratingForm = ref(false);
const isRevisionDialogOpen = ref(false);
const revisionForm = useForm({
    alasan_perubahan: '',
    dasar_perubahan: '',
    tanggal_berlaku: '',
});

const submitRevision = () => {
    revisionForm.post(route('renja-opd.revisions.store', props.renja.id), {
        preserveScroll: true,
        onSuccess: () => {
            isRevisionDialogOpen.value = false;
            revisionForm.reset();
        },
    });
};

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const form = useForm({
    sub_kegiatan_pemerintahan_id: '',
    indikator_sub_kegiatan_id: '',
    indikator: '',
    target_akhir_renstra: '',
    realisasi_capaian_renja_tahun_lalu: '',
    prakiraan_capaian_target_renja_tahun_berjalan: '',
    target: '',
    pagu_indikatif: '',
    lokasi: '',
    sumber_dana: '',
    prioritas_nasional: '',
    prioritas_daerah: '',
    kelompok_sasaran: '',
    prakiraan_maju_target: '',
    prakiraan_maju_pagu_indikatif: '',
    status: 'draft',
    urutan: '',
});

const previousRealisasiYear = computed(() => props.renja.tahun - 2);
const previousTargetYear = computed(() => props.renja.tahun - 1);
const nextPlanYear = computed(() => props.renja.tahun + 1);
const opdLabel = computed(() => props.renja.opd?.singkatan || props.renja.opd?.nama || '-');
const opdFullName = computed(() => props.renja.opd?.nama || opdLabel.value);
const renjaSyncApplyRoute = computed(() => (props.syncPreview ? route('renja-opd.sync-rkpd.apply', [props.renja.id, props.syncPreview.id]) : null));

const uniqueBy = <T,>(items: T[], keyOf: (item: T) => string) => {
    const seen = new Set<string>();

    return items.filter((item) => {
        const key = keyOf(item);

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);
        return true;
    });
};

const programOptions = computed<Option[]>(() =>
    uniqueBy(
        props.subKegiatanOptions
            .filter((option) => option.program_id)
            .map((option) => ({
                id: Number(option.program_id),
                value: Number(option.program_id),
                label: option.group || 'Program belum tersedia',
                description: 'Program',
            })),
        (option) => String(option.id),
    ),
);

const kegiatanOptions = computed<Option[]>(() =>
    uniqueBy(
        props.subKegiatanOptions
            .filter((option) => String(option.program_id ?? '') === String(selectedProgramId.value))
            .filter((option) => option.kegiatan_id)
            .map((option) => ({
                id: Number(option.kegiatan_id),
                value: Number(option.kegiatan_id),
                label: option.description || 'Kegiatan belum tersedia',
                description: option.group || null,
            })),
        (option) => String(option.id),
    ),
);

const existingSubKegiatanRowById = computed(() => new Map(
    props.existingSubKegiatanRows.map((row) => [String(row.sub_kegiatan_pemerintahan_id), row.id]),
));
const subKegiatanOptionsForSelectedKegiatan = computed(() =>
    props.subKegiatanOptions
        .filter((option) => String(option.kegiatan_id ?? '') === String(selectedKegiatanPemerintahanId.value))
        .map((option) => {
            const existingRowId = existingSubKegiatanRowById.value.get(String(option.id));
            const alreadyAdded = Boolean(existingRowId && existingRowId !== editingId.value);

            return {
                ...option,
                description: alreadyAdded ? 'Sudah diinput pada Renja' : option.description,
                badge: alreadyAdded ? 'Sudah ada' : option.satuan_label,
                disabled: alreadyAdded,
            };
        }),
);

const selectedSubKegiatan = computed(() =>
    props.subKegiatanOptions.find((option) => String(option.id) === String(form.sub_kegiatan_pemerintahan_id)),
);

const applyFilters = () =>
    router.get(route('renja-opd.show', props.renja.id), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

watch(
    () => selectedProgramId.value,
    () => {
        if (isHydratingForm.value) {
            return;
        }

        selectedKegiatanPemerintahanId.value = '';
        form.sub_kegiatan_pemerintahan_id = '';
        form.indikator = '';
    },
);

watch(
    () => selectedKegiatanPemerintahanId.value,
    () => {
        if (isHydratingForm.value) {
            return;
        }

        form.sub_kegiatan_pemerintahan_id = '';
        form.indikator = '';
    },
);

watch(
    () => form.sub_kegiatan_pemerintahan_id,
    () => {
        if (!form.indikator && selectedSubKegiatan.value?.indikator_sub_kegiatan) {
            form.indikator = selectedSubKegiatan.value.indikator_sub_kegiatan;
        }
    },
);

const openManualForm = () => {
    resetForm();
    isFormOpen.value = true;
    renjaItemView.value = 'input';

    nextTick(() => formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
};

const closeForm = () => {
    resetForm();
    isFormOpen.value = false;
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const resetForm = () => {
    editingId.value = null;
    isHydratingForm.value = true;
    selectedProgramId.value = '';
    selectedKegiatanPemerintahanId.value = '';
    form.reset();
    form.clearErrors();
    form.status = 'draft';
    nextTick(() => {
        isHydratingForm.value = false;
    });
};

const submitItem = () => {
    if (editingId.value) {
        form.put(route('renja-opd.items.update', [props.renja.id, editingId.value]), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post(route('renja-opd.items.store', props.renja.id), {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

const editItem = (row: Row) => {
    const option = props.subKegiatanOptions.find((item) => String(item.id) === String(row.sub_kegiatan_pemerintahan_id));

    editingId.value = row.id;
    isHydratingForm.value = true;
    selectedProgramId.value = option?.program_id ?? '';
    selectedKegiatanPemerintahanId.value = option?.kegiatan_id ?? '';
    form.sub_kegiatan_pemerintahan_id = String(row.sub_kegiatan_pemerintahan_id ?? '');
    form.indikator = row.indikator ?? '';
    form.target_akhir_renstra = row.target_akhir_renstra ?? '';
    form.realisasi_capaian_renja_tahun_lalu = row.realisasi_capaian_renja_tahun_lalu ?? '';
    form.prakiraan_capaian_target_renja_tahun_berjalan = row.prakiraan_capaian_target_renja_tahun_berjalan ?? '';
    form.target = row.target ?? '';
    form.pagu_indikatif = moneyInputText(row.pagu_indikatif);
    form.lokasi = row.lokasi ?? '';
    form.sumber_dana = row.sumber_dana ?? '';
    form.prioritas_nasional = row.prioritas_nasional ?? '';
    form.prioritas_daerah = row.prioritas_daerah ?? '';
    form.kelompok_sasaran = row.kelompok_sasaran ?? '';
    form.prakiraan_maju_target = row.prakiraan_maju_target ?? '';
    form.prakiraan_maju_pagu_indikatif = moneyInputText(row.prakiraan_maju_pagu_indikatif);
    form.status = row.status || 'draft';
    form.urutan = String(row.urutan ?? '');
    isFormOpen.value = true;
    renjaItemView.value = 'input';

    nextTick(() => {
        isHydratingForm.value = false;
        formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const destroyItem = async (row: Row) => {
    if (await confirmDelete(`Hapus baris ${row.kode || row.nama_sub_kegiatan || 'Renja'}?`)) {
        router.delete(route('renja-opd.items.destroy', [props.renja.id, row.id]), { preserveScroll: true });
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

const moneyValue = (value?: number | string | null) => Number(String(value ?? '').replace(/[^0-9.-]/g, '')) || 0;
const moneyInputText = (value?: number | string | null) => {
    let raw = String(value ?? '').trim().replace(/\s/g, '');

    if (!raw) {
        return '';
    }

    if (raw.includes(',') && raw.includes('.')) {
        raw = raw.replace(/\./g, '').split(',')[0] ?? '';
    } else if (/^\d{1,3}(\.\d{3})+$/.test(raw)) {
        raw = raw.replace(/\./g, '');
    } else if (/^\d+\.\d+$/.test(raw)) {
        raw = raw.split('.')[0] ?? '';
    } else if (raw.includes(',')) {
        raw = raw.split(',')[0] ?? '';
    }

    const digits = raw.replace(/\D/g, '').replace(/^0+(?=\d)/, '');

    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};
const moneyTypingInputText = (value?: number | string | null) => {
    let raw = String(value ?? '').trim().replace(/\s/g, '');

    if (/^\d{4,}\.\d{1,2}$/.test(raw)) {
        raw = raw.split('.')[0] ?? '';
    }

    const digits = raw.replace(/\D/g, '').replace(/^0+(?=\d)/, '');

    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};
const formatMoneyField = (field: 'pagu_indikatif' | 'prakiraan_maju_pagu_indikatif') => {
    form[field] = moneyTypingInputText(form[field]);
};
const formatMoney = (value?: number | string | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(moneyValue(value));
const formatMoneyPlain = (value?: number | string | null) => {
    const amount = moneyValue(value);

    return amount > 0 ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount) : '-';
};
const cellValue = (value?: string | number | null) => (value === null || value === undefined || String(value).trim() === '' ? '-' : value);
const previewKey = (...parts: Array<string | number | null | undefined>) => parts.map((part) => String(part ?? '-')).join('::');
const addPreviewSum = (map: Map<string, PreviewSum>, key: string, item: Row) => {
    const current = map.get(key) ?? { pagu: 0, prakiraanMajuPagu: 0 };

    current.pagu += moneyValue(item.pagu_indikatif);
    current.prakiraanMajuPagu += moneyValue(item.prakiraan_maju_pagu_indikatif);
    map.set(key, current);
};

const previewGroupSums = computed(() => {
    const sums = {
        opd: new Map<string, PreviewSum>(),
        urusan: new Map<string, PreviewSum>(),
        bidang: new Map<string, PreviewSum>(),
        program: new Map<string, PreviewSum>(),
        kegiatan: new Map<string, PreviewSum>(),
    };

    props.previewItems.forEach((item) => {
        const opdId = item.opd_id ?? props.renja.opd_id;

        addPreviewSum(sums.opd, previewKey(opdId), item);
        addPreviewSum(sums.urusan, previewKey(opdId, item.urusan), item);
        addPreviewSum(sums.bidang, previewKey(opdId, item.bidang), item);
        addPreviewSum(sums.program, previewKey(opdId, item.program), item);
        addPreviewSum(sums.kegiatan, previewKey(opdId, item.kegiatan), item);
    });

    return sums;
});

const previewSum = (map: Map<string, PreviewSum>, key: string) => map.get(key) ?? { pagu: 0, prakiraanMajuPagu: 0 };
const codeFromLabel = (value?: string | null) => {
    const label = String(value ?? '').trim();
    const match = label.match(/^([0-9.]+)\s+-\s+/);

    return match?.[1] ?? '';
};
const nameFromLabel = (value?: string | null) => {
    const label = String(value ?? '').trim();

    return label.replace(/^[0-9.]+\s+-\s+/, '') || '-';
};
const codeName = (value?: string | null) => ({
    kode: codeFromLabel(value),
    nama: nameFromLabel(value),
});

const officialPreviewRows = computed<OfficialPreviewRow[]>(() => {
    const rows: OfficialPreviewRow[] = [];
    const seen = {
        opd: '',
        urusan: '',
        bidang: '',
        program: '',
        kegiatan: '',
        sub: '',
    };
    let itemNumber = 1;

    props.previewItems.forEach((item) => {
        const opdId = item.opd_id ?? props.renja.opd_id;
        const opdKey = String(opdId ?? '');
        const urusanKey = item.urusan || '-';
        const bidangKey = item.bidang || '-';
        const programKey = item.program || '-';
        const kegiatanKey = item.kegiatan || '-';
        const subKey = item.sub_kegiatan || item.nama_sub_kegiatan || '-';

        if (seen.opd !== opdKey) {
            seen.opd = opdKey;
            seen.urusan = '';
            seen.bidang = '';
            seen.program = '';
            seen.kegiatan = '';
            seen.sub = '';
            rows.push({
                key: `opd:${opdKey}`,
                kind: 'opd',
                label: item.opd?.nama || props.renja.opd?.nama || '-',
                ...previewSum(previewGroupSums.value.opd, previewKey(opdId)),
            });
        }

        if (seen.urusan !== urusanKey) {
            seen.urusan = urusanKey;
            seen.bidang = '';
            seen.program = '';
            seen.kegiatan = '';
            seen.sub = '';
            const parsed = codeName(item.urusan);

            rows.push({
                key: `urusan:${opdKey}:${urusanKey}`,
                kind: 'urusan',
                kode: parsed.kode,
                label: parsed.nama,
                ...previewSum(previewGroupSums.value.urusan, previewKey(opdId, item.urusan)),
            });
        }

        if (seen.bidang !== bidangKey) {
            seen.bidang = bidangKey;
            seen.program = '';
            seen.kegiatan = '';
            seen.sub = '';
            const parsed = codeName(item.bidang);

            rows.push({
                key: `bidang:${opdKey}:${bidangKey}`,
                kind: 'bidang',
                kode: parsed.kode,
                label: parsed.nama,
                ...previewSum(previewGroupSums.value.bidang, previewKey(opdId, item.bidang)),
            });
        }

        if (seen.program !== programKey) {
            seen.program = programKey;
            seen.kegiatan = '';
            seen.sub = '';
            const parsed = codeName(item.program);

            rows.push({
                key: `program:${opdKey}:${programKey}`,
                kind: 'program',
                kode: parsed.kode,
                label: parsed.nama,
                ...previewSum(previewGroupSums.value.program, previewKey(opdId, item.program)),
            });
        }

        if (seen.kegiatan !== kegiatanKey) {
            seen.kegiatan = kegiatanKey;
            seen.sub = '';
            const parsed = codeName(item.kegiatan);

            rows.push({
                key: `kegiatan:${opdKey}:${kegiatanKey}`,
                kind: 'kegiatan',
                kode: parsed.kode,
                label: parsed.nama,
                ...previewSum(previewGroupSums.value.kegiatan, previewKey(opdId, item.kegiatan)),
                prioritasNasional: item.prioritas_nasional,
                prioritasDaerah: item.prioritas_daerah,
                pdPenanggungJawab: item.perangkat_daerah_penanggung_jawab || item.opd?.nama || props.renja.opd?.nama,
            });
        }

        if (seen.sub !== subKey) {
            seen.sub = subKey;
            const parsed = codeName(item.sub_kegiatan || item.kode);

            rows.push({
                key: `sub:${opdKey}:${subKey}`,
                kind: 'sub',
                kode: parsed.kode || item.kode || '',
                label: parsed.nama === '-' ? item.nama_sub_kegiatan || item.sub_kegiatan : parsed.nama,
            });
        }

        rows.push({
            key: `item:${item.id}`,
            kind: 'item',
            no: `${itemNumber}.`,
            kode: '',
            label: nameFromLabel(item.sub_kegiatan) || item.nama_sub_kegiatan || '-',
            indikator: item.indikator,
            targetAkhir: item.target_akhir_renstra,
            realisasiLalu: item.realisasi_capaian_renja_tahun_lalu,
            prakiraanBerjalan: item.prakiraan_capaian_target_renja_tahun_berjalan,
            target: item.target,
            pagu: item.pagu_indikatif,
            lokasi: item.lokasi,
            sumberDana: item.sumber_dana,
            prioritasNasional: item.prioritas_nasional,
            prioritasDaerah: item.prioritas_daerah,
            kelompokSasaran: item.kelompok_sasaran,
            prakiraanMajuTarget: item.prakiraan_maju_target,
            prakiraanMajuPagu: item.prakiraan_maju_pagu_indikatif,
            pdPenanggungJawab: item.perangkat_daerah_penanggung_jawab || item.opd?.nama || props.renja.opd?.nama,
            source: item,
        });
        itemNumber += 1;
    });

    return rows;
});

const officialRowClass = (kind: OfficialPreviewRow['kind']) =>
    ({
        opd: 'bg-[#d7b9f3] font-bold uppercase',
        urusan: 'bg-[#f3c9ed] font-bold uppercase',
        bidang: 'bg-[#d3f6e8] font-bold uppercase',
        program: 'bg-white font-semibold',
        kegiatan: 'bg-white',
        sub: 'bg-slate-50',
        item: 'bg-white',
    })[kind];
</script>

<template>
    <Head :title="`Renja OPD ${renja.tahun}`" />

    <div class="flex flex-col gap-5 p-4">
        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#edf7ff)] px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <Link :href="route('renja-opd.index')" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                            <ArrowLeft class="size-4" />
                            Kembali
                        </Link>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold tracking-normal">{{ renja.judul }}</h1>
                            <span class="rounded-full border border-[#00336C]/20 bg-[#00336C]/5 px-2.5 py-1 text-xs font-semibold text-[#00336C] dark:border-blue-700 dark:bg-blue-950/50 dark:text-blue-200">
                                {{ renja.version_label }}
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(renja.status)">{{ statusLabel(renja.status) }}</span>
                            <span v-if="renja.is_active_version" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                <Check class="size-3.5" /> Versi aktif
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ opdLabel }} - Tahun {{ renja.tahun }} - {{ renja.nomor_dokumen || 'Nomor belum diisi' }}
                        </p>
                        <p v-if="renja.rkpd" class="mt-2 text-sm text-muted-foreground">Acuan {{ renja.rkpd.version_label }} Tahun {{ renja.rkpd.tahun }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="can.createRevision"
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200 dark:hover:bg-amber-950"
                            @click="isRevisionDialogOpen = true"
                        >
                            <GitBranch class="size-4" />
                            Buat RENJA Perubahan
                        </button>
                        <Link
                            v-if="can.manage"
                            :href="route('renja-opd.edit', renja.id)"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-white px-4 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                        >
                            <Pencil class="size-4" />
                            Edit Renja
                        </Link>
                        <WorkflowActionButtons
                            module="renja_opd"
                            :model-id="renja.id"
                            :status="renja.status"
                            :can-manage="can.manage"
                            :can-review="can.review"
                            :can-lock="can.lock"
                            :can-unlock="can.unlock"
                            :show-verify="false"
                        />
                    </div>
                </div>
            </div>

            <div class="border-b bg-white px-5 py-3 dark:bg-slate-950">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Riwayat versi</span>
                    <Link
                        v-for="version in versionHistory"
                        :key="version.id"
                        :href="route('renja-opd.show', version.id)"
                        class="inline-flex min-h-9 items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                        :class="version.id === renja.id ? 'border-[#00336C] bg-[#00336C] text-white' : 'bg-background text-foreground hover:border-[#00336C]/40 hover:bg-sky-50 dark:hover:bg-slate-900'"
                    >
                        <span>{{ version.version_label }}</span>
                        <span v-if="version.is_active_version" class="size-1.5 rounded-full bg-emerald-400"></span>
                    </Link>
                </div>
            </div>

            <div v-if="renja.jenis_versi === 'perubahan'" class="grid gap-2 border-b bg-amber-50/70 px-5 py-3 text-sm md:grid-cols-[1fr_auto] dark:bg-amber-950/25">
                <div>
                    <span class="font-semibold text-amber-950 dark:text-amber-200">Alasan perubahan:</span>
                    <span class="ml-1 text-amber-900 dark:text-amber-300">{{ renja.alasan_perubahan || '-' }}</span>
                </div>
                <div v-if="renja.dasar_perubahan" class="text-amber-800 dark:text-amber-300">Dasar: {{ renja.dasar_perubahan }}</div>
            </div>

            <div class="grid gap-3 p-5 md:grid-cols-3">
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">Perangkat Daerah</p>
                    <p class="mt-2 font-semibold text-slate-950">{{ opdFullName }}</p>
                    <p v-if="renja.opd_unit" class="mt-1 text-sm text-muted-foreground">{{ renja.opd_unit.nama }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">Sub Kegiatan</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.items_count }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">sub kegiatan final</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">Pagu Indikatif</p>
                    <p class="mt-2 text-2xl font-semibold">{{ formatMoney(summary.total_pagu) }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">tahun {{ renja.tahun }}</p>
                </div>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />

        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#eef7ff)] px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white">
                            <ClipboardList class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold">Sub Kegiatan RENJA OPD</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Input final dan preview format resmi.</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="inline-flex rounded-xl border bg-slate-50 p-1">
                            <button
                                type="button"
                                class="h-9 rounded-lg px-3 text-sm font-semibold transition"
                                :class="renjaItemView === 'input' ? 'bg-white text-[#00336C] shadow-sm' : 'text-slate-600 hover:text-slate-950'"
                                @click="renjaItemView = 'input'"
                            >
                                Input Data
                            </button>
                            <button
                                type="button"
                                class="h-9 rounded-lg px-3 text-sm font-semibold transition"
                                :class="renjaItemView === 'preview' ? 'bg-white text-[#00336C] shadow-sm' : 'text-slate-600 hover:text-slate-950'"
                                @click="renjaItemView = 'preview'"
                            >
                                Preview Tabel
                            </button>
                        </div>
                        <button
                            v-if="can.manage && renjaItemView === 'input'"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002855]"
                            @click="openManualForm"
                        >
                            <Plus class="size-4" />
                            Tambah Sub Kegiatan
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <PlanningSyncPreview
            v-if="renjaItemView === 'input'"
            :can-manage="can.manage"
            title="Sinkronisasi RKPD ke RENJA"
            description="Tarik baris RKPD yang terkait OPD ini. Cek baris baru dan perbedaan sebelum diterapkan."
            :preview-route="route('renja-opd.sync-rkpd.preview', renja.id)"
            :apply-route="renjaSyncApplyRoute"
            :preview="syncPreview"
            preview-label="Preview RKPD"
            apply-label="Terapkan ke RENJA"
        />

        <section v-if="renjaItemView === 'input' && can.manage && isFormOpen" ref="formSection" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">{{ editingId ? 'Edit Sub Kegiatan' : 'Tambah Sub Kegiatan' }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Isi sesuai matriks RENJA final.</p>
                    </div>
                    <button type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border bg-white px-3 text-sm font-semibold hover:bg-slate-50" @click="closeForm">
                        <X class="size-4" />
                        Tutup
                    </button>
                </div>
            </div>

            <form class="grid gap-4 p-5" @submit.prevent="submitItem">
                <div class="rounded-xl border bg-white p-4">
                    <div class="mb-4">
                        <h3 class="font-semibold">Identitas Baris</h3>
                        <p class="mt-1 text-xs text-muted-foreground">Pilih berurutan dari program sampai sub kegiatan.</p>
                    </div>

                    <div class="grid gap-4">
                        <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-sm text-[#00336C]">
                            <p class="text-xs font-semibold uppercase text-slate-600">Perangkat Daerah Penanggung Jawab</p>
                            <p class="mt-1 font-semibold">{{ opdFullName }}</p>
                        </div>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Program</span>
                            <RpjmdRichSelect
                                v-model="selectedProgramId"
                                :options="programOptions"
                                placeholder="Pilih program"
                                empty-text="Program belum tersedia"
                            />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Kegiatan</span>
                            <RpjmdRichSelect
                                v-model="selectedKegiatanPemerintahanId"
                                :options="kegiatanOptions"
                                :disabled="!selectedProgramId"
                                placeholder="Pilih kegiatan"
                                empty-text="Kegiatan belum tersedia untuk program ini"
                            />
                            <span v-if="!selectedProgramId" class="text-xs text-muted-foreground">Pilih program terlebih dahulu.</span>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Sub Kegiatan</span>
                            <RpjmdRichSelect
                                v-model="form.sub_kegiatan_pemerintahan_id"
                                :options="subKegiatanOptionsForSelectedKegiatan"
                                :disabled="!selectedKegiatanPemerintahanId"
                                placeholder="Pilih sub kegiatan"
                                empty-text="Sub kegiatan belum tersedia untuk kegiatan ini"
                                :invalid="Boolean(form.errors.sub_kegiatan_pemerintahan_id)"
                            />
                            <span v-if="!selectedKegiatanPemerintahanId" class="text-xs text-muted-foreground">Pilih kegiatan terlebih dahulu.</span>
                            <span v-if="form.errors.sub_kegiatan_pemerintahan_id" class="text-xs text-red-600">{{ form.errors.sub_kegiatan_pemerintahan_id }}</span>
                        </label>

                        <div v-if="selectedSubKegiatan" class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-sm text-[#00336C]">
                            <p class="font-semibold">{{ selectedSubKegiatan.label }}</p>
                            <p v-if="selectedSubKegiatan.sasaran_sub_kegiatan" class="mt-2 text-xs font-semibold uppercase text-slate-600">Sasaran Sub Kegiatan</p>
                            <p v-if="selectedSubKegiatan.sasaran_sub_kegiatan" class="mt-1">{{ selectedSubKegiatan.sasaran_sub_kegiatan }}</p>
                            <p v-if="selectedSubKegiatan.definisi_operasional" class="mt-2 text-xs font-semibold uppercase text-slate-600">Definisi Operasional</p>
                            <p v-if="selectedSubKegiatan.definisi_operasional" class="mt-1">{{ selectedSubKegiatan.definisi_operasional }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <div class="mb-4">
                        <h3 class="font-semibold">Target dan Capaian</h3>
                    </div>
                    <div class="grid gap-4">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Indikator Program / Kegiatan / Sub Kegiatan</span>
                            <textarea
                                v-model="form.indikator"
                                rows="3"
                                class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: Jumlah dokumen perencanaan perangkat daerah"
                            ></textarea>
                            <span v-if="form.errors.indikator" class="text-xs text-red-600">{{ form.errors.indikator }}</span>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target Akhir Periode Renstra OPD</span>
                            <input v-model="form.target_akhir_renstra" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Realisasi Capaian Renja OPD Tahun {{ previousRealisasiYear }}</span>
                            <input v-model="form.realisasi_capaian_renja_tahun_lalu" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prakiraan Capaian Target Renja OPD Tahun {{ previousTargetYear }}</span>
                            <input v-model="form.prakiraan_capaian_target_renja_tahun_berjalan" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target {{ renja.tahun }}</span>
                            <input v-model="form.target" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <div class="mb-4">
                        <h3 class="font-semibold">Capaian Kinerja dan Kerangka Pendanaan</h3>
                    </div>
                    <div class="grid gap-4">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Pagu Indikatif (Rp)</span>
                            <input
                                v-model="form.pagu_indikatif"
                                type="text"
                                inputmode="numeric"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: 75.000.000"
                                @input="formatMoneyField('pagu_indikatif')"
                            />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Lokasi</span>
                            <textarea v-model="form.lokasi" rows="3" class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Sumber Dana</span>
                            <input v-model="form.sumber_dana" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prioritas Nasional</span>
                            <input v-model="form.prioritas_nasional" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prioritas Daerah</span>
                            <textarea v-model="form.prioritas_daerah" rows="3" class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Kelompok Sasaran</span>
                            <input v-model="form.kelompok_sasaran" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <div class="mb-4">
                        <h3 class="font-semibold">Prakiraan Maju Rencana Tahun {{ nextPlanYear }}</h3>
                    </div>
                    <div class="grid gap-4">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target</span>
                            <input v-model="form.prakiraan_maju_target" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Pagu Indikatif (Rp)</span>
                            <input
                                v-model="form.prakiraan_maju_pagu_indikatif"
                                type="text"
                                inputmode="numeric"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: 75.000.000"
                                @input="formatMoneyField('prakiraan_maju_pagu_indikatif')"
                            />
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Urutan</span>
                            <input v-model="form.urutan" type="number" min="1" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                    </div>
                </div>

                <div class="flex justify-end rounded-xl border bg-slate-50 px-4 py-3">
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#002855] disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{ editingId ? 'Simpan Perubahan' : 'Simpan Baris' }}
                    </button>
                </div>
            </form>
        </section>

        <section v-if="renjaItemView === 'input'" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Daftar Sub Kegiatan RENJA</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Menampilkan {{ items.total }} data.</p>
                    </div>
                    <form class="grid gap-2 lg:grid-cols-[320px_180px_auto]" @submit.prevent="applyFiltersNow">
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="filterForm.search" type="search" class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Cari kode, sub kegiatan, atau indikator" />
                        </label>
                        <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                            <option value="">Semua status</option>
                            <option value="draft">Draft</option>
                            <option value="verified">Terverifikasi</option>
                            <option value="approved">Disetujui</option>
                            <option value="locked">Terkunci</option>
                        </select>
                        <button type="button" class="h-10 rounded-lg px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1700px] text-left text-xs">
                    <thead class="border-b bg-muted/60 uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Urusan / Bidang / Program / Kegiatan / Sub Kegiatan</th>
                            <th class="px-4 py-3">Indikator Program / Kegiatan / Sub Kegiatan</th>
                            <th class="px-4 py-3 text-center">Target {{ renja.tahun }}</th>
                            <th class="px-4 py-3 text-right">Pagu Indikatif</th>
                            <th class="px-4 py-3">Lokasi</th>
                            <th class="px-4 py-3">Sumber Dana</th>
                            <th class="px-4 py-3">Prioritas</th>
                            <th class="px-4 py-3">Prakiraan Maju {{ nextPlanYear }}</th>
                            <th v-if="can.manage" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in items.data" :key="row.id" class="border-b align-top last:border-0 hover:bg-muted/40">
                            <td class="px-4 py-3 font-semibold">{{ items.from ? items.from + index : index + 1 }}</td>
                            <td class="px-4 py-3 font-semibold">{{ row.kode || '-' }}</td>
                            <td class="min-w-[28rem] px-4 py-3">
                                <div class="font-semibold">{{ row.nama_sub_kegiatan || row.sub_kegiatan || '-' }}</div>
                                <div class="mt-1 text-[11px] text-muted-foreground">{{ row.program || '-' }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ row.kegiatan || '-' }}</div>
                            </td>
                            <td class="min-w-80 px-4 py-3">{{ row.indikator || '-' }}</td>
                            <td class="px-4 py-3 text-center">{{ row.target || '-' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ formatMoney(row.pagu_indikatif) }}</td>
                            <td class="px-4 py-3">{{ row.lokasi || '-' }}</td>
                            <td class="px-4 py-3">{{ row.sumber_dana || '-' }}</td>
                            <td class="px-4 py-3">{{ row.prioritas_daerah || row.prioritas_nasional || '-' }}</td>
                            <td class="px-4 py-3">
                                <div>{{ row.prakiraan_maju_target || '-' }}</div>
                                <div class="text-muted-foreground">{{ formatMoney(row.prakiraan_maju_pagu_indikatif) }}</div>
                            </td>
                            <td v-if="can.manage" class="px-4 py-3 text-right">
                                <div class="inline-flex overflow-hidden rounded-lg border bg-background">
                                    <button type="button" class="h-9 px-3 hover:bg-muted" title="Edit" @click="editItem(row)">
                                        <Pencil class="size-4" />
                                    </button>
                                    <button type="button" class="h-9 border-l px-3 text-red-600 hover:bg-red-50" title="Hapus" @click="destroyItem(row)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td :colspan="can.manage ? 11 : 10" class="px-4 py-12 text-center text-sm text-muted-foreground">Belum ada sub kegiatan RENJA.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <Link v-if="items.next_page_url" :href="items.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted">Berikutnya</Link>
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </section>

        <section v-if="renjaItemView === 'preview'" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Preview Tabel Renja</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Format mengikuti matriks RKPD resmi.</p>
                    </div>
                    <div class="rounded-full border bg-white px-3 py-1 text-xs font-semibold text-[#00336C]">
                        {{ previewItems.length }} baris
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[2400px] border-collapse text-left text-[11px] leading-tight text-slate-950">
                    <thead class="text-center font-bold uppercase">
                        <tr class="bg-slate-100">
                            <th rowspan="2" class="w-14 border border-slate-700 px-2 py-2">No</th>
                            <th rowspan="2" class="w-32 border border-slate-700 px-2 py-2">Kode</th>
                            <th rowspan="2" class="w-72 border border-slate-700 px-2 py-2">Urusan / Bidang Urusan / Program / Kegiatan / Sub Kegiatan</th>
                            <th rowspan="2" class="w-64 border border-slate-700 px-2 py-2">Indikator Program / Kegiatan / Sub Kegiatan</th>
                            <th rowspan="2" class="w-32 border border-slate-700 px-2 py-2">Target Akhir Periode Renstra OPD</th>
                            <th rowspan="2" class="w-32 border border-slate-700 px-2 py-2">Realisasi Capaian Renja OPD Tahun {{ previousRealisasiYear }}</th>
                            <th rowspan="2" class="w-36 border border-slate-700 px-2 py-2">Prakiraan Capaian Target Renja OPD Tahun {{ previousTargetYear }}</th>
                            <th colspan="6" class="border border-slate-700 px-2 py-2">Capaian Kinerja dan Kerangka Pendanaan</th>
                            <th rowspan="2" class="w-36 border border-slate-700 px-2 py-2">Kelompok Sasaran</th>
                            <th colspan="2" class="border border-slate-700 px-2 py-2">Prakiraan Maju Rencana Tahun {{ nextPlanYear }}</th>
                            <th rowspan="2" class="w-44 border border-slate-700 px-2 py-2">Perangkat Daerah Penanggung Jawab</th>
                            <th v-if="can.manage" rowspan="2" class="w-24 border border-slate-700 px-2 py-2">Aksi</th>
                        </tr>
                        <tr class="bg-slate-100">
                            <th class="w-28 border border-slate-700 px-2 py-2">Target {{ renja.tahun }}</th>
                            <th class="w-36 border border-slate-700 px-2 py-2">Pagu Indikatif (Rp)</th>
                            <th class="w-32 border border-slate-700 px-2 py-2">Lokasi</th>
                            <th class="w-32 border border-slate-700 px-2 py-2">Sumber Dana</th>
                            <th class="w-28 border border-slate-700 px-2 py-2">Prioritas Nasional</th>
                            <th class="w-28 border border-slate-700 px-2 py-2">Prioritas Daerah</th>
                            <th class="w-28 border border-slate-700 px-2 py-2">Target</th>
                            <th class="w-36 border border-slate-700 px-2 py-2">Pagu Indikatif (Rp)</th>
                        </tr>
                        <tr class="bg-white text-[10px] font-normal">
                            <th class="border border-slate-700 px-2 py-1">1</th>
                            <th class="border border-slate-700 px-2 py-1">2</th>
                            <th class="border border-slate-700 px-2 py-1">3</th>
                            <th class="border border-slate-700 px-2 py-1">4</th>
                            <th class="border border-slate-700 px-2 py-1">5</th>
                            <th class="border border-slate-700 px-2 py-1">6</th>
                            <th class="border border-slate-700 px-2 py-1">7</th>
                            <th class="border border-slate-700 px-2 py-1">8</th>
                            <th class="border border-slate-700 px-2 py-1">9</th>
                            <th class="border border-slate-700 px-2 py-1">10</th>
                            <th class="border border-slate-700 px-2 py-1">11</th>
                            <th class="border border-slate-700 px-2 py-1">12</th>
                            <th class="border border-slate-700 px-2 py-1">13</th>
                            <th class="border border-slate-700 px-2 py-1">14</th>
                            <th class="border border-slate-700 px-2 py-1">15</th>
                            <th class="border border-slate-700 px-2 py-1">16</th>
                            <th class="border border-slate-700 px-2 py-1">17</th>
                            <th v-if="can.manage" class="border border-slate-700 px-2 py-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in officialPreviewRows" :key="row.key" class="align-top" :class="officialRowClass(row.kind)">
                            <td class="border border-slate-700 px-2 py-2 text-center">{{ row.no || '' }}</td>
                            <td class="border border-slate-700 px-2 py-2 font-semibold">{{ row.kode || '' }}</td>
                            <td class="border border-slate-700 px-2 py-2" :class="row.kind === 'item' ? '' : 'font-bold uppercase'">
                                {{ row.label || '-' }}
                            </td>
                            <td class="border border-slate-700 px-2 py-2">{{ cellValue(row.indikator) }}</td>
                            <td class="border border-slate-700 px-2 py-2 text-center">{{ cellValue(row.targetAkhir) }}</td>
                            <td class="border border-slate-700 px-2 py-2 text-center">{{ cellValue(row.realisasiLalu) }}</td>
                            <td class="border border-slate-700 px-2 py-2 text-center">{{ cellValue(row.prakiraanBerjalan) }}</td>
                            <td class="border border-slate-700 px-2 py-2 text-center">{{ cellValue(row.target) }}</td>
                            <td class="border border-slate-700 px-2 py-2 text-right font-semibold">{{ formatMoneyPlain(row.pagu) }}</td>
                            <td class="border border-slate-700 px-2 py-2">{{ cellValue(row.lokasi) }}</td>
                            <td class="border border-slate-700 px-2 py-2">{{ cellValue(row.sumberDana) }}</td>
                            <td class="border border-slate-700 px-2 py-2">{{ cellValue(row.prioritasNasional) }}</td>
                            <td class="border border-slate-700 px-2 py-2">{{ cellValue(row.prioritasDaerah) }}</td>
                            <td class="border border-slate-700 px-2 py-2">{{ cellValue(row.kelompokSasaran) }}</td>
                            <td class="border border-slate-700 px-2 py-2 text-center">{{ cellValue(row.prakiraanMajuTarget) }}</td>
                            <td class="border border-slate-700 px-2 py-2 text-right font-semibold">{{ formatMoneyPlain(row.prakiraanMajuPagu) }}</td>
                            <td class="border border-slate-700 px-2 py-2">{{ cellValue(row.pdPenanggungJawab) }}</td>
                            <td v-if="can.manage" class="border border-slate-700 px-2 py-2 text-center">
                                <div v-if="row.source" class="inline-flex overflow-hidden rounded-md border bg-white">
                                    <button type="button" class="h-8 px-2 text-[#00336C] hover:bg-sky-50" title="Edit" @click="editItem(row.source)">
                                        <Pencil class="size-3.5" />
                                    </button>
                                    <button type="button" class="h-8 border-l px-2 text-red-600 hover:bg-red-50" title="Hapus" @click="destroyItem(row.source)">
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="previewItems.length === 0">
                            <td :colspan="can.manage ? 18 : 17" class="border border-slate-700 px-4 py-12 text-center text-sm text-muted-foreground">
                                Belum ada sub kegiatan RENJA.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="isRevisionDialogOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="isRevisionDialogOpen = false">
            <form class="w-full max-w-xl overflow-hidden rounded-2xl border bg-card shadow-2xl" @submit.prevent="submitRevision">
                <div class="flex items-start justify-between border-b px-5 py-4">
                    <div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-300"><GitBranch class="size-4" /> Versi tahunan</div>
                        <h2 class="mt-1 text-xl font-semibold">Buat RENJA Perubahan</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Data RENJA Ditetapkan akan disalin dan otomatis memakai RKPD Perubahan.</p>
                    </div>
                    <button type="button" class="inline-flex size-10 items-center justify-center rounded-lg hover:bg-muted" aria-label="Tutup" @click="isRevisionDialogOpen = false">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="grid gap-4 p-5">
                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold">Alasan perubahan <span class="text-red-600">*</span></span>
                        <textarea v-model="revisionForm.alasan_perubahan" rows="4" class="rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Jelaskan alasan perubahan RENJA"></textarea>
                        <span v-if="revisionForm.errors.alasan_perubahan" class="text-xs text-red-600">{{ revisionForm.errors.alasan_perubahan }}</span>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold">Dasar perubahan</span>
                        <input v-model="revisionForm.dasar_perubahan" type="text" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Contoh: Perubahan RKPD Tahun 2027" />
                        <span v-if="revisionForm.errors.dasar_perubahan" class="text-xs text-red-600">{{ revisionForm.errors.dasar_perubahan }}</span>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold">Tanggal berlaku</span>
                        <input v-model="revisionForm.tanggal_berlaku" type="date" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        <span v-if="revisionForm.errors.tanggal_berlaku" class="text-xs text-red-600">{{ revisionForm.errors.tanggal_berlaku }}</span>
                    </label>
                    <p v-if="revisionForm.errors.rkpd_id" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ revisionForm.errors.rkpd_id }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 border-t bg-muted/30 px-5 py-4">
                    <button type="button" class="inline-flex h-10 items-center rounded-lg border bg-background px-4 text-sm font-semibold hover:bg-muted" @click="isRevisionDialogOpen = false">Batal</button>
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855] disabled:opacity-60" :disabled="revisionForm.processing">
                        <GitBranch class="size-4" /> Buat Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
