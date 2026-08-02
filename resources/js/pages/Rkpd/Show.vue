<script setup lang="ts">
import RpjmdRichSelect from '@/components/RpjmdRichSelect.vue';
import PlanningSyncPreview from '@/components/PlanningSyncPreview.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, Pencil, Plus, Save, Search, Target, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';

type Option = {
    id: number | string;
    label: string;
    kode?: string;
    nama?: string;
    description?: string;
    display_label?: string;
    badge?: string | number | null;
    reference_count?: number;
    is_program_penunjang?: boolean;
    group?: string;
    opd_ids?: number[];
    program_id?: number | null;
    kegiatan_id?: number | null;
    bidang_id?: number | null;
    urusan_id?: number | null;
    program_pemerintahan_id?: number | null;
    program_pemerintahan_ids?: number[];
    bidang_label?: string | null;
    urusan_label?: string | null;
    sasaran_sub_kegiatan?: string | null;
    indikator_sub_kegiatan?: string | null;
    satuan_indikator_id?: number | null;
    satuan_label?: string | null;
    definisi_operasional?: string | null;
};
type Rkpd = {
    id: number;
    tahun: number;
    judul: string;
    nomor_dokumen?: string | null;
    status: string;
    rpjmd?: { id: number; judul: string; tahun_awal: number; tahun_akhir: number } | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
};
type Row = {
    id: number;
    opd_id: number;
    opd_unit_id?: number | null;
    sub_kegiatan_pemerintahan_id?: number | null;
    program_rpjmd_id?: number | null;
    kode?: string | null;
    nama_urusan_bidang_program_kegiatan_sub?: string | null;
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
    perangkat_daerah_penanggung_jawab?: string | null;
    status: string;
    urutan: number;
    opd?: { id: number; kode?: string | null; nama: string; singkatan?: string | null } | null;
    urusan?: string;
    bidang?: string;
    program?: string;
    kegiatan?: string;
    sub_kegiatan?: string;
};
type IkuRow = {
    key: string;
    type: 'indikator_tujuan_daerah' | 'indikator_sasaran_daerah';
    target_id?: number | null;
    indikator_id: number;
    level: string;
    iku: string;
    parent?: string | null;
    satuan?: string | null;
    target_rpjmd?: string | null;
    target_rkpd?: string | null;
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
    rkpd: Rkpd;
    items: Paginator<Row>;
    previewItems: Row[];
    ikuRows: IkuRow[];
    filters: { search?: string; status?: string; opd_id?: string };
    summary: { items_count: number; opd_count: number; total_pagu: number; total_prakiraan_maju: number };
    opdOptions: Option[];
    subKegiatanOptions: Option[];
    programRpjmdOptions: Option[];
    syncPreview?: SyncPreview | null;
    workflow: Workflow;
    can: { manage: boolean; review: boolean; lock: boolean; unlock: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    opd_id: props.filters.opd_id ?? '',
});

const form = useForm({
    opd_id: '',
    opd_unit_id: '',
    sub_kegiatan_pemerintahan_id: '',
    program_rpjmd_id: '',
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
    perangkat_daerah_penanggung_jawab: '',
    urutan: '',
});

const editingId = ref<number | null>(null);
const isFormOpen = ref(false);
const formSection = ref<HTMLElement | null>(null);
const syncPanelRequested = new URLSearchParams(window.location.search).has('sync_panel');
const activeTab = ref<'iku' | 'matrix'>(syncPanelRequested ? 'matrix' : 'iku');
const rkpdItemView = ref<'input' | 'preview'>('input');
const selectedKegiatanPemerintahanId = ref('');
const isHydratingItemForm = ref(false);

const ikuForm = useForm({
    indikator_type: '',
    indikator_id: '',
    target_rkpd: '',
});
const editingIkuKey = ref<string | null>(null);

const ikuFilledCount = computed(() => props.ikuRows.filter((row) => row.target_rkpd && row.target_rkpd.trim() !== '').length);
const ikuTotalCount = computed(() => props.ikuRows.length);
const ikuCompletion = computed(() => (ikuTotalCount.value ? Math.round((ikuFilledCount.value / ikuTotalCount.value) * 100) : 0));

const applyFilters = () => router.get(route('rkpd.show', props.rkpd.id), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const selectedOpd = computed(() => props.opdOptions.find((option) => String(option.id) === String(form.opd_id)));
const selectedProgramRpjmd = computed(() => props.programRpjmdOptions.find((option) => String(option.id) === String(form.program_rpjmd_id)));
const selectedSubKegiatan = computed(() =>
    props.subKegiatanOptions.find((option) => String(option.id) === String(form.sub_kegiatan_pemerintahan_id)),
);
const selectedKegiatan = computed(() =>
    kegiatanOptions.value.find((option) => String(option.id) === String(selectedKegiatanPemerintahanId.value)),
);
const previousRealisasiYear = computed(() => props.rkpd.tahun - 2);
const previousTargetYear = computed(() => props.rkpd.tahun - 1);
const nextPlanYear = computed(() => props.rkpd.tahun + 1);
const rkpdSyncApplyRoute = computed(() => (props.syncPreview ? route('rkpd.sync-renja.apply', [props.rkpd.id, props.syncPreview.id]) : null));

const programOptionsForSelectedOpd = computed(() => {
    if (!form.opd_id) {
        return [];
    }

    const opdId = Number(form.opd_id);

    return props.programRpjmdOptions
        .filter((option) => option.opd_ids?.includes(opdId))
        .map((option) => ({
            ...option,
            label: option.display_label || option.label,
            description:
                option.is_program_penunjang
                    ? 'Otomatis mengikuti kode bidang utama OPD'
                    : option.reference_count && option.reference_count > 1
                      ? `${option.reference_count} kode program master`
                      : option.description || 'Program dari RPJMD',
        }));
});

const bidangCodesFromOpdCode = (code?: string | null): string[] => {
    const parts = String(code || '').split('.');
    const codes: string[] = [];

    for (let index = 0; index + 1 < parts.length; index += 2) {
        if (/^[1-8]$/.test(parts[index]) && /^\d{2}$/.test(parts[index + 1])) {
            codes.push(`${parts[index]}.${parts[index + 1]}`);
        }
    }

    return [...new Set(codes)];
};

const codeFromLabel = (label?: string | null) => String(label || '').match(/^([0-9.]+)\s+-\s+/)?.[1] || '';
const bidangCodeFromProgramLabel = (label?: string | null) => {
    const parts = codeFromLabel(label).split('.');

    return parts.length >= 2 ? `${parts[0]}.${parts[1]}` : '';
};
const nameFromLabel = (label?: string | null) => String(label || '').replace(/^[0-9.]+\s+-\s+/, '') || '-';
const programReferenceIds = (option?: Option | null) =>
    (option?.program_pemerintahan_ids?.length ? option.program_pemerintahan_ids : [option?.program_pemerintahan_id])
        .map((id) => Number(id || 0))
        .filter(Boolean);

const effectiveProgramReferenceIds = computed(() => {
    const ids = programReferenceIds(selectedProgramRpjmd.value);

    if (ids.length <= 1) {
        return ids;
    }

    const opdBidangCodes = bidangCodesFromOpdCode(selectedOpd.value?.kode);

    if (opdBidangCodes.length === 0) {
        return ids;
    }

    const matchedReferences = props.subKegiatanOptions
        .filter((option) => ids.includes(Number(option.program_id || 0)))
        .map((option) => {
            const bidangCode = bidangCodeFromProgramLabel(option.group);

            return {
                id: Number(option.program_id || 0),
                programCode: codeFromLabel(option.group),
                position: opdBidangCodes.indexOf(bidangCode),
            };
        })
        .filter((reference) => reference.id && reference.position >= 0)
        .sort((a, b) => a.position - b.position || a.programCode.localeCompare(b.programCode, 'id-ID'));

    if (selectedProgramRpjmd.value?.is_program_penunjang && matchedReferences.length > 0) {
        return [matchedReferences[0].id];
    }

    const matchedIds = matchedReferences.map((reference) => reference.id);

    return [...new Set(matchedIds)].length ? [...new Set(matchedIds)] : ids;
});

const kegiatanOptions = computed<Option[]>(() => {
    if (effectiveProgramReferenceIds.value.length === 0) {
        return [];
    }

    const options = new Map<string, Option>();

    props.subKegiatanOptions.forEach((subKegiatan) => {
        if (!subKegiatan.kegiatan_id || !effectiveProgramReferenceIds.value.includes(Number(subKegiatan.program_id || 0))) {
            return;
        }

        const key = String(subKegiatan.kegiatan_id);

        if (!options.has(key)) {
            options.set(key, {
                id: subKegiatan.kegiatan_id,
                label: subKegiatan.description || 'Kegiatan belum bernama',
                description: subKegiatan.group,
                group: selectedProgramRpjmd.value?.display_label || selectedProgramRpjmd.value?.label,
            });
        }
    });

    return Array.from(options.values()).sort((a, b) => String(a.label).localeCompare(String(b.label), 'id-ID'));
});

const subKegiatanOptionsForSelectedKegiatan = computed<Option[]>(() => {
    if (!selectedKegiatanPemerintahanId.value) {
        return [];
    }

    return props.subKegiatanOptions
        .filter((option) => String(option.kegiatan_id || '') === String(selectedKegiatanPemerintahanId.value))
        .map((option) => ({
            ...option,
            group: selectedKegiatan.value?.label || option.description,
            description: option.indikator_sub_kegiatan || option.description,
            badge: option.satuan_label,
        }));
});

const scrollToForm = () => {
    nextTick(() => {
        formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const openManualForm = () => {
    activeTab.value = 'matrix';
    rkpdItemView.value = 'input';
    isFormOpen.value = true;
    scrollToForm();
};

watch(
    () => form.opd_id,
    () => {
        form.perangkat_daerah_penanggung_jawab = selectedOpd.value?.label ?? '';

        if (isHydratingItemForm.value) {
            return;
        }

        form.program_rpjmd_id = '';
        selectedKegiatanPemerintahanId.value = '';
        form.sub_kegiatan_pemerintahan_id = '';
        form.indikator = '';
    },
);

watch(
    () => form.program_rpjmd_id,
    () => {
        if (isHydratingItemForm.value) {
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
        if (isHydratingItemForm.value) {
            return;
        }

        form.sub_kegiatan_pemerintahan_id = '';
        form.indikator = '';
    },
);

watch(
    () => form.sub_kegiatan_pemerintahan_id,
    () => {
        if (selectedSubKegiatan.value?.indikator_sub_kegiatan) {
            form.indikator = selectedSubKegiatan.value.indikator_sub_kegiatan;
        } else if (!isHydratingItemForm.value) {
            form.indikator = '';
        }
    },
);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.opd_id = '';
    applyFiltersNow();
};

const resetForm = () => {
    editingId.value = null;
    selectedKegiatanPemerintahanId.value = '';
    form.reset();
    form.clearErrors();
};

const closeForm = () => {
    resetForm();
    isFormOpen.value = false;
};

const submitItem = () => {
    if (editingId.value) {
        form.put(route('rkpd.items.update', [props.rkpd.id, editingId.value]), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post(route('rkpd.items.store', props.rkpd.id), {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

const editItem = (row: Row) => {
    const subKegiatan = props.subKegiatanOptions.find((option) => String(option.id) === String(row.sub_kegiatan_pemerintahan_id ?? ''));

    isHydratingItemForm.value = true;
    editingId.value = row.id;
    activeTab.value = 'matrix';
    rkpdItemView.value = 'input';
    isFormOpen.value = true;
    form.opd_id = String(row.opd_id ?? '');
    form.opd_unit_id = String(row.opd_unit_id ?? '');
    form.program_rpjmd_id = String(row.program_rpjmd_id ?? '');
    selectedKegiatanPemerintahanId.value = String(subKegiatan?.kegiatan_id ?? '');
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
    form.perangkat_daerah_penanggung_jawab = row.perangkat_daerah_penanggung_jawab ?? '';
    form.urutan = String(row.urutan ?? '');
    nextTick(() => {
        isHydratingItemForm.value = false;
    });
    scrollToForm();
};

const destroyItem = async (row: Row) => {
    if (await confirmDelete(`Hapus baris ${row.kode || row.nama_urusan_bidang_program_kegiatan_sub || 'RKPD'}?`)) {
        router.delete(route('rkpd.items.destroy', [props.rkpd.id, row.id]), { preserveScroll: true });
    }
};

const startIkuEdit = (row: IkuRow) => {
    editingIkuKey.value = row.key;
    ikuForm.indikator_type = row.type;
    ikuForm.indikator_id = String(row.indikator_id);
    ikuForm.target_rkpd = row.target_rkpd ?? '';
    ikuForm.clearErrors();
};

const cancelIkuEdit = () => {
    editingIkuKey.value = null;
    ikuForm.reset();
    ikuForm.clearErrors();
};

const saveIkuTarget = (row: IkuRow) => {
    ikuForm.indikator_type = row.type;
    ikuForm.indikator_id = String(row.indikator_id);

    const options = {
        preserveScroll: true,
        onSuccess: cancelIkuEdit,
    };

    if (row.target_id) {
        ikuForm.put(route('rkpd.iku-targets.update', [props.rkpd.id, row.target_id]), options);
        return;
    }

    ikuForm.post(route('rkpd.iku-targets.store', props.rkpd.id), options);
};

const destroyIkuTarget = async (row: IkuRow) => {
    if (!row.target_id) {
        return;
    }

    if (await confirmDelete(`Hapus target RKPD untuk ${row.iku}?`)) {
        router.delete(route('rkpd.iku-targets.destroy', [props.rkpd.id, row.target_id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (editingIkuKey.value === row.key) {
                    cancelIkuEdit();
                }
            },
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

const formatMoney = (value?: number | string | null) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
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
const formatMoneyPlain = (value?: number | string | null) => {
    const amount = moneyValue(value);

    return amount > 0 ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount) : '-';
};
const cellValue = (value?: string | number | null) => (value === null || value === undefined || String(value).trim() === '' ? '-' : value);

type PreviewSum = { pagu: number; prakiraanMajuPagu: number };
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
        addPreviewSum(sums.opd, previewKey(item.opd_id), item);
        addPreviewSum(sums.urusan, previewKey(item.opd_id, item.urusan), item);
        addPreviewSum(sums.bidang, previewKey(item.opd_id, item.bidang), item);
        addPreviewSum(sums.program, previewKey(item.opd_id, item.program), item);
        addPreviewSum(sums.kegiatan, previewKey(item.opd_id, item.kegiatan), item);
    });

    return sums;
});

const previewSum = (map: Map<string, PreviewSum>, key: string) => map.get(key) ?? { pagu: 0, prakiraanMajuPagu: 0 };
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
        const opdKey = String(item.opd_id ?? '');
        const urusanKey = item.urusan || '-';
        const bidangKey = item.bidang || '-';
        const programKey = item.program || '-';
        const kegiatanKey = item.kegiatan || '-';
        const subKey = item.sub_kegiatan || item.nama_urusan_bidang_program_kegiatan_sub || '-';

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
                label: item.opd?.nama || '-',
                ...previewSum(previewGroupSums.value.opd, previewKey(item.opd_id)),
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
                ...previewSum(previewGroupSums.value.urusan, previewKey(item.opd_id, item.urusan)),
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
                ...previewSum(previewGroupSums.value.bidang, previewKey(item.opd_id, item.bidang)),
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
                ...previewSum(previewGroupSums.value.program, previewKey(item.opd_id, item.program)),
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
                ...previewSum(previewGroupSums.value.kegiatan, previewKey(item.opd_id, item.kegiatan)),
                prioritasNasional: item.prioritas_nasional,
                prioritasDaerah: item.prioritas_daerah,
                pdPenanggungJawab: item.perangkat_daerah_penanggung_jawab || item.opd?.nama,
            });
        }

        if (seen.sub !== subKey) {
            seen.sub = subKey;
            const parsed = codeName(item.sub_kegiatan || item.kode);

            rows.push({
                key: `sub:${opdKey}:${subKey}`,
                kind: 'sub',
                kode: parsed.kode || item.kode || '',
                label: parsed.nama === '-' ? item.nama_urusan_bidang_program_kegiatan_sub || item.sub_kegiatan : parsed.nama,
            });
        }

        rows.push({
            key: `item:${item.id}`,
            kind: 'item',
            no: `${itemNumber}.`,
            kode: '',
            label: item.nama_urusan_bidang_program_kegiatan_sub || nameFromLabel(item.sub_kegiatan) || '-',
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
            pdPenanggungJawab: item.perangkat_daerah_penanggung_jawab || item.opd?.nama,
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
    <Head :title="`RKPD ${rkpd.tahun}`" />

    <div class="flex flex-col gap-5 p-4">
        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#edf7ff)] px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <Link :href="route('rkpd.index')" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                            <ArrowLeft class="size-4" />
                            Kembali
                        </Link>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold tracking-normal">{{ rkpd.judul }}</h1>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(rkpd.status)">{{ statusLabel(rkpd.status) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ rkpd.tahun }} - {{ rkpd.nomor_dokumen || 'Nomor dokumen belum diisi' }}
                        </p>
                        <p v-if="rkpd.rpjmd" class="mt-2 text-sm text-muted-foreground">
                            Acuan RPJMD {{ rkpd.rpjmd.tahun_awal }}-{{ rkpd.rpjmd.tahun_akhir }} - {{ rkpd.rpjmd.judul }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="can.manage"
                            :href="route('rkpd.edit', rkpd.id)"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-white px-4 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                        >
                            <Pencil class="size-4" />
                            Edit RKPD
                        </Link>
                        <WorkflowActionButtons
                            module="rkpd"
                            :model-id="rkpd.id"
                            :status="rkpd.status"
                            :can-manage="can.manage"
                            :can-review="can.review"
                            :can-lock="can.lock"
                            :can-unlock="can.unlock"
                            :show-verify="false"
                        />
                    </div>
                </div>
            </div>

            <div class="grid gap-3 p-4 md:grid-cols-4">
                <article class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">OPD Terisi</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.opd_count }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">perangkat daerah</p>
                </article>
                <article class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">Baris Matriks</p>
                    <p class="mt-2 text-2xl font-semibold">{{ summary.items_count }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">program sampai sub kegiatan</p>
                </article>
                <article class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-[#00336C]">
                    <p class="text-xs font-semibold uppercase opacity-70">Pagu Indikatif</p>
                    <p class="mt-2 text-2xl font-semibold">{{ formatMoney(summary.total_pagu) }}</p>
                    <p class="mt-1 text-xs opacity-75">tahun {{ rkpd.tahun }}</p>
                </article>
                <article class="rounded-xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-muted-foreground">Prakiraan Maju</p>
                    <p class="mt-2 text-2xl font-semibold">{{ formatMoney(summary.total_prakiraan_maju) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">tahun berikutnya</p>
                </article>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />

        <section class="rounded-xl border bg-card p-2 shadow-sm">
            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    class="flex items-center justify-between rounded-lg px-4 py-3 text-left transition"
                    :class="activeTab === 'iku' ? 'bg-[#00336C] text-white shadow-sm' : 'text-slate-700 hover:bg-slate-50'"
                    @click="activeTab = 'iku'"
                >
                    <span>
                        <span class="block text-sm font-semibold">IKU Kabupaten</span>
                        <span class="mt-0.5 block text-xs opacity-75">{{ ikuFilledCount }} dari {{ ikuTotalCount }} target RKPD terisi</span>
                    </span>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="activeTab === 'iku' ? 'bg-white/15' : 'bg-slate-100 text-slate-600'">
                        {{ ikuCompletion }}%
                    </span>
                </button>
                <button
                    type="button"
                    class="flex items-center justify-between rounded-lg px-4 py-3 text-left transition"
                    :class="activeTab === 'matrix' ? 'bg-[#00336C] text-white shadow-sm' : 'text-slate-700 hover:bg-slate-50'"
                    @click="
                        activeTab = 'matrix';
                        rkpdItemView = 'input';
                    "
                >
                    <span>
                        <span class="block text-sm font-semibold">Baris RKPD</span>
                        <span class="mt-0.5 block text-xs opacity-75">Sub kegiatan, pendanaan, prioritas</span>
                    </span>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="activeTab === 'matrix' ? 'bg-white/15' : 'bg-slate-100 text-slate-600'">
                        {{ summary.items_count }}
                    </span>
                </button>
            </div>
        </section>

        <section v-if="activeTab === 'iku'" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#eef7ff)] px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white">
                            <Target class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold">IKU Kabupaten Tahun {{ rkpd.tahun }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Target RPJMD otomatis, target RKPD diisi untuk tahun dokumen ini.</p>
                        </div>
                    </div>
                    <span class="inline-flex h-9 items-center rounded-full border bg-white px-3 text-sm font-semibold text-[#00336C]">
                        {{ ikuFilledCount }}/{{ ikuTotalCount }} terisi
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1150px] border-collapse text-left text-sm">
                    <thead class="bg-[#eaf4ff] text-xs uppercase text-[#00336C]">
                        <tr>
                            <th class="w-16 border px-4 py-3 text-center">No</th>
                            <th class="w-52 border px-4 py-3">Jenis IKU</th>
                            <th class="border px-4 py-3">IKU</th>
                            <th class="w-28 border px-4 py-3 text-center">Satuan</th>
                            <th class="w-44 border px-4 py-3 text-center">Target RPJMD {{ rkpd.tahun }}</th>
                            <th class="w-72 border px-4 py-3">Target RKPD {{ rkpd.tahun }}</th>
                            <th v-if="can.manage" class="w-40 border px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in ikuRows" :key="row.key" class="align-top hover:bg-sky-50/60">
                            <td class="border px-4 py-4 text-center font-semibold">{{ index + 1 }}</td>
                            <td class="border px-4 py-4">
                                <p class="font-semibold text-slate-900">{{ row.level }}</p>
                                <p v-if="row.parent" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ row.parent }}</p>
                            </td>
                            <td class="border px-4 py-4">
                                <p class="font-semibold text-slate-950">{{ row.iku }}</p>
                            </td>
                            <td class="border px-4 py-4 text-center">{{ row.satuan || '-' }}</td>
                            <td class="border bg-sky-50/60 px-4 py-4 text-center font-semibold text-[#00336C]">
                                {{ row.target_rpjmd || '-' }}
                            </td>
                            <td class="border px-4 py-4">
                                <div v-if="editingIkuKey === row.key" class="grid gap-1.5">
                                    <input
                                        v-model="ikuForm.target_rkpd"
                                        type="text"
                                        class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                        placeholder="Isi target RKPD"
                                    />
                                    <span v-if="ikuForm.errors.target_rkpd" class="text-xs text-red-600">{{ ikuForm.errors.target_rkpd }}</span>
                                </div>
                                <span v-else-if="row.target_rkpd" class="font-semibold text-slate-900">
                                    {{ row.target_rkpd }}
                                </span>
                                <span v-else class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-700">Belum diisi</span>
                            </td>
                            <td v-if="can.manage" class="border px-4 py-4 text-right">
                                <div v-if="editingIkuKey === row.key" class="inline-flex gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#00336C] px-3 text-xs font-semibold text-white hover:bg-[#002855] disabled:opacity-60"
                                        :disabled="ikuForm.processing"
                                        @click="saveIkuTarget(row)"
                                    >
                                        <Save class="size-4" />
                                        Simpan
                                    </button>
                                    <button type="button" class="h-9 rounded-lg border bg-white px-3 text-xs font-semibold hover:bg-slate-50" @click="cancelIkuEdit">
                                        Batal
                                    </button>
                                </div>
                                <div v-else class="inline-flex overflow-hidden rounded-lg border bg-white shadow-sm">
                                    <button type="button" class="h-9 px-3 text-[#00336C] hover:bg-sky-50" @click="startIkuEdit(row)">
                                        <Plus v-if="!row.target_rkpd" class="size-4" />
                                        <Pencil v-else class="size-4" />
                                    </button>
                                    <button
                                        v-if="row.target_id"
                                        type="button"
                                        class="h-9 border-l px-3 text-red-600 hover:bg-red-50"
                                        @click="destroyIkuTarget(row)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="ikuRows.length === 0">
                            <td :colspan="can.manage ? 7 : 6" class="border px-4 py-12 text-center text-sm text-muted-foreground">
                                Belum ada indikator tujuan atau sasaran daerah pada RPJMD ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeTab === 'matrix'" class="rounded-xl border bg-card p-3 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#00336C]">
                        <ClipboardList class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold">Baris RKPD</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Input final dan preview format resmi.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="inline-flex rounded-xl border bg-slate-50 p-1">
                        <button
                            type="button"
                            class="h-9 rounded-lg px-3 text-sm font-semibold transition"
                            :class="rkpdItemView === 'input' ? 'bg-white text-[#00336C] shadow-sm' : 'text-slate-600 hover:text-slate-950'"
                            @click="rkpdItemView = 'input'"
                        >
                            Input Baris
                        </button>
                        <button
                            type="button"
                            class="h-9 rounded-lg px-3 text-sm font-semibold transition"
                            :class="rkpdItemView === 'preview' ? 'bg-white text-[#00336C] shadow-sm' : 'text-slate-600 hover:text-slate-950'"
                            @click="rkpdItemView = 'preview'"
                        >
                            Preview Tabel
                        </button>
                    </div>
                    <button
                        v-if="can.manage && rkpdItemView === 'input'"
                        type="button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002855]"
                        @click="openManualForm"
                    >
                        <Plus class="size-4" />
                        Tambah Baris RKPD
                    </button>
                </div>
            </div>
        </section>

        <PlanningSyncPreview
            v-if="activeTab === 'matrix' && rkpdItemView === 'input'"
            :can-manage="can.manage"
            title="Sinkronisasi RENJA ke RKPD"
            description="Tarik baris dari RENJA OPD untuk tahun ini. Cek baris baru dan perbedaan sebelum diterapkan."
            :preview-route="route('rkpd.sync-renja.preview', rkpd.id)"
            :apply-route="rkpdSyncApplyRoute"
            :preview="syncPreview"
            preview-label="Preview RENJA"
            apply-label="Terapkan ke RKPD"
        />

        <section v-if="activeTab === 'matrix' && rkpdItemView === 'input' && can.manage && isFormOpen" ref="formSection" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">{{ editingId ? 'Edit Baris RKPD' : 'Tambah Baris RKPD' }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Isi sesuai matriks RKPD final.</p>
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
                        <p class="mt-1 text-xs text-muted-foreground">Pilih berurutan dari OPD sampai sub kegiatan.</p>
                    </div>

                    <div class="grid gap-4">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Perangkat Daerah Penanggung Jawab</span>
                            <RpjmdRichSelect
                                v-model="form.opd_id"
                                :options="opdOptions"
                                placeholder="Pilih perangkat daerah"
                                empty-text="OPD tidak tersedia"
                                :invalid="Boolean(form.errors.opd_id)"
                            />
                            <span v-if="form.errors.opd_id" class="text-xs text-red-600">{{ form.errors.opd_id }}</span>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Program</span>
                            <RpjmdRichSelect
                                v-model="form.program_rpjmd_id"
                                :options="programOptionsForSelectedOpd"
                                :disabled="!form.opd_id"
                                placeholder="Pilih program"
                                empty-text="Program belum tersedia untuk OPD ini"
                                :invalid="Boolean(form.errors.program_rpjmd_id)"
                            />
                            <span v-if="!form.opd_id" class="text-xs text-muted-foreground">Pilih perangkat daerah terlebih dahulu.</span>
                            <span v-else-if="programOptionsForSelectedOpd.length === 0" class="text-xs text-amber-700">Belum ada program RPJMD untuk OPD ini.</span>
                            <span v-if="form.errors.program_rpjmd_id" class="text-xs text-red-600">{{ form.errors.program_rpjmd_id }}</span>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Kegiatan</span>
                            <RpjmdRichSelect
                                v-model="selectedKegiatanPemerintahanId"
                                :options="kegiatanOptions"
                                :disabled="!form.program_rpjmd_id"
                                placeholder="Pilih kegiatan"
                                empty-text="Kegiatan belum tersedia untuk program ini"
                            />
                            <span v-if="!form.program_rpjmd_id" class="text-xs text-muted-foreground">Pilih program terlebih dahulu.</span>
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
                    <h3 class="font-semibold">Indikator Program / Kegiatan / Sub Kegiatan</h3>
                    <label class="mt-4 grid gap-1.5">
                        <span class="text-sm font-medium">Indikator Program / Kegiatan / Sub Kegiatan</span>
                        <textarea
                            v-model="form.indikator"
                            rows="4"
                            class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                            placeholder="Otomatis dari master sub kegiatan bila tersedia"
                        ></textarea>
                    </label>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <h3 class="font-semibold">Target dan Capaian</h3>
                    <div class="mt-4 grid gap-4">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target Akhir Periode Renstra OPD</span>
                            <input
                                v-model="form.target_akhir_renstra"
                                type="text"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: 100%"
                            />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Realisasi Capaian Renja OPD Tahun {{ previousRealisasiYear }}</span>
                            <input
                                v-model="form.realisasi_capaian_renja_tahun_lalu"
                                type="text"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: 95%"
                            />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prakiraan Capaian Target Renja OPD Tahun {{ previousTargetYear }}</span>
                            <input
                                v-model="form.prakiraan_capaian_target_renja_tahun_berjalan"
                                type="text"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: 98%"
                            />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target {{ rkpd.tahun }}</span>
                            <input
                                v-model="form.target"
                                type="text"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: 3 Dokumen"
                            />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <h3 class="font-semibold">Capaian Kinerja dan Kerangka Pendanaan</h3>
                    <div class="mt-4 grid gap-4">
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
                            <textarea
                                v-model="form.lokasi"
                                rows="3"
                                class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: Kab. Banjarnegara, semua kecamatan"
                            ></textarea>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Sumber Dana</span>
                            <input
                                v-model="form.sumber_dana"
                                type="text"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Contoh: Pendapatan Asli Daerah (PAD)"
                            />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prioritas Nasional</span>
                            <textarea v-model="form.prioritas_nasional" rows="2" class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Prioritas Daerah</span>
                            <textarea v-model="form.prioritas_daerah" rows="2" class="rounded-xl border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Kelompok Sasaran</span>
                            <input v-model="form.kelompok_sasaran" type="text" class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-4">
                    <h3 class="font-semibold">Prakiraan Maju Rencana Tahun {{ nextPlanYear }}</h3>
                    <div class="mt-4 grid gap-4">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Target</span>
                            <input
                                v-model="form.prakiraan_maju_target"
                                type="text"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Target tahun berikutnya"
                            />
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
                            <span class="text-sm font-medium">Perangkat Daerah Penanggung Jawab</span>
                            <input
                                v-model="form.perangkat_daerah_penanggung_jawab"
                                type="text"
                                class="h-11 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Otomatis mengikuti OPD yang dipilih"
                            />
                        </label>
                    </div>
                </div>

                <div class="sticky bottom-0 -mx-5 -mb-5 flex justify-end gap-2 border-t bg-card/95 px-5 py-4 backdrop-blur">
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border bg-white px-4 text-sm font-semibold hover:bg-slate-50" @click="closeForm">
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855] disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{ editingId ? 'Simpan Perubahan' : 'Simpan Baris' }}
                    </button>
                </div>
            </form>
        </section>

        <section v-if="activeTab === 'matrix' && rkpdItemView === 'preview'" id="rkpd-matrix" class="rounded-xl border bg-card shadow-sm">
            <div class="border-b p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Preview Tabel RKPD</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Format matriks resmi. Geser horizontal untuk melihat semua kolom.</p>
                    </div>
                    <form class="grid gap-2 lg:grid-cols-[260px_160px_220px_auto]" @submit.prevent="applyFiltersNow">
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="filterForm.search"
                                type="search"
                                class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Cari kode, indikator, OPD"
                            />
                        </label>
                        <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                            <option value="">Semua status</option>
                            <option value="draft">Draft</option>
                            <option value="verified">Terverifikasi</option>
                            <option value="approved">Disetujui</option>
                            <option value="locked">Terkunci</option>
                        </select>
                        <select v-model="filterForm.opd_id" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                            <option value="">Semua OPD</option>
                            <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <button type="button" class="h-10 rounded-lg px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[2400px] border-collapse text-left text-[11px] leading-tight text-slate-950">
                    <thead class="bg-slate-100 text-center text-[10px] uppercase">
                        <tr>
                            <th rowspan="3" class="w-14 border border-slate-700 px-2 py-3">No</th>
                            <th rowspan="3" class="w-36 border border-slate-700 px-2 py-3">Kode</th>
                            <th rowspan="3" class="w-64 border border-slate-700 px-2 py-3">Urusan / Bidang Urusan / Program / Kegiatan / Sub Kegiatan</th>
                            <th rowspan="3" class="w-56 border border-slate-700 px-2 py-3">Indikator Program / Kegiatan / Sub Kegiatan</th>
                            <th rowspan="3" class="w-28 border border-slate-700 px-2 py-3">Target Akhir Periode Renstra OPD</th>
                            <th rowspan="3" class="w-28 border border-slate-700 px-2 py-3">Realisasi Capaian Renja OPD Tahun {{ previousRealisasiYear }}</th>
                            <th rowspan="3" class="w-32 border border-slate-700 px-2 py-3">Prakiraan Capaian Target Renja OPD Tahun {{ previousTargetYear }}</th>
                            <th colspan="6" class="border border-slate-700 px-2 py-3">Capaian Kinerja dan Kerangka Pendanaan</th>
                            <th rowspan="3" class="w-36 border border-slate-700 px-2 py-3">Kelompok Sasaran</th>
                            <th colspan="2" class="border border-slate-700 px-2 py-3">Prakiraan Maju Rencana Tahun {{ nextPlanYear }}</th>
                            <th rowspan="3" class="w-44 border border-slate-700 px-2 py-3">Perangkat Daerah Penanggung Jawab</th>
                            <th v-if="can.manage" rowspan="3" class="w-20 border border-slate-700 px-2 py-3">Aksi</th>
                        </tr>
                        <tr>
                            <th rowspan="2" class="w-28 border border-slate-700 px-2 py-2">Target {{ rkpd.tahun }}</th>
                            <th rowspan="2" class="w-36 border border-slate-700 px-2 py-2">Pagu Indikatif (Rp)</th>
                            <th rowspan="2" class="w-36 border border-slate-700 px-2 py-2">Lokasi</th>
                            <th rowspan="2" class="w-36 border border-slate-700 px-2 py-2">Sumber Dana</th>
                            <th colspan="2" class="border border-slate-700 px-2 py-2">Prioritas</th>
                            <th rowspan="2" class="w-28 border border-slate-700 px-2 py-2">Target</th>
                            <th rowspan="2" class="w-36 border border-slate-700 px-2 py-2">Pagu Indikatif (Rp)</th>
                        </tr>
                        <tr>
                            <th class="w-28 border border-slate-700 px-2 py-2">Nasional</th>
                            <th class="w-28 border border-slate-700 px-2 py-2">Daerah</th>
                        </tr>
                        <tr class="bg-white font-normal">
                            <th v-for="number in can.manage ? 18 : 17" :key="number" class="border border-slate-700 px-2 py-1 text-center">
                                {{ number }}
                            </th>
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
                                Belum ada baris RKPD.
                            </td>
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
    </div>
</template>
