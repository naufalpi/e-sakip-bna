<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { GitBranch, Network, Pencil, Plus, Table2, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    tujuan: Tujuan[];
};

type Visi = {
    id: number;
    visi: string;
    urutan?: number | null;
    misi: Misi[];
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

const parentKeyByType: Partial<Record<NodeType, string>> = {
    misi: 'visi',
    tujuan: 'misi',
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
    peran: 'penanggung_jawab',
    is_utama: true,
    urutan: 1,
    arah_kebijakan: '',
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
const parentKey = computed(() => parentKeyByType[form.type]);
const parentOptions = computed(() => (parentKey.value ? (props.nodeOptions[parentKey.value] ?? []) : []));
const parentLabel = computed(() => (parentKey.value ? (parentLabels[parentKey.value] ?? 'Induk Data') : 'Induk Data'));
const needsParent = computed(() => Boolean(parentKey.value));
const isIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program'].includes(form.type));
const isTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(form.type));
const isTextNodeType = computed(() => ['visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'program'].includes(form.type));
const isProgramType = computed(() => form.type === 'program');
const isProgramOpdType = computed(() => form.type === 'program_opd');
const isStrategiType = computed(() => form.type === 'strategi');
const targetTriwulanTypeOptions = [
    { value: 'indikator_tujuan_daerah', label: 'Indikator Tujuan' },
    { value: 'indikator_sasaran_daerah', label: 'Indikator Sasaran' },
    { value: 'indikator_program_rpjmd', label: 'Indikator Program' },
];
const selectedTargetTriwulanOptions = computed(() => props.targetTriwulanOptions[targetTriwulanForm.related_table] ?? []);
const editingNode = ref<{ type: NodeType; id: number } | null>(null);
const viewMode = ref<'tree' | 'table'>('tree');

const nodeText = (kode: string | null | undefined, text: string | null | undefined) => trimText(`${kode ? `${kode} - ` : ''}${text ?? ''}`) || '-';
const trimText = (value: string) => value.replace(/\s+/g, ' ').trim();
const joinItems = (items: string[]) => items.filter((item) => item && item !== '-').join('; ') || '-';
const indicatorSummary = (items: Indikator[]) => joinItems(items.map((item) => nodeText(item.kode, item.indikator)));
const targetSummary = (items: Indikator[]) =>
    joinItems(
        items.flatMap((item) =>
            item.targets.map(
                (target) => `${item.kode ? `${item.kode} ` : ''}${target.periode_tahun.tahun}: ${target.target_text || target.target || '-'}`,
            ),
        ),
    );
const targetTriwulanSummary = (items: Indikator[]) =>
    joinItems(
        items.flatMap((item) =>
            item.target_triwulan.map(
                (target) =>
                    `${item.kode ? `${item.kode} ` : ''}${target.periode_tahun.tahun} ${triwulanLabel(target.triwulan)}: ${target.target_text || target.target_angka || '-'}`,
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
        visi.misi.forEach((misi) => {
            summary.tujuan += misi.tujuan.length;
            misi.tujuan.forEach((tujuan) => {
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
    });

    summary.opd_penanggung_jawab = opdIds.size;

    return summary;
});

const rpjmdCascadingRows = computed<RpjmdCascadingRow[]>(() => {
    const rows: RpjmdCascadingRow[] = [];

    props.rpjmd.visi.forEach((visi) => {
        if (visi.misi.length === 0) {
            rows.push(emptyRpjmdRow(`visi-${visi.id}`, { visi: visi.visi }));
        }

        visi.misi.forEach((misi) => {
            if (misi.tujuan.length === 0) {
                rows.push(emptyRpjmdRow(`misi-${misi.id}`, { visi: visi.visi, misi: nodeText(misi.kode, misi.misi) }));
            }

            misi.tujuan.forEach((tujuan) => {
                if (tujuan.sasaran.length === 0) {
                    rows.push(
                        emptyRpjmdRow(`tujuan-${tujuan.id}`, {
                            visi: visi.visi,
                            misi: nodeText(misi.kode, misi.misi),
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
                                misi: nodeText(misi.kode, misi.misi),
                                tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                indikator_tujuan: indicatorSummary(tujuan.indikator),
                                sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                indikator_sasaran: indicatorSummary(sasaran.indikator),
                                target_tahunan: joinItems([targetSummary(tujuan.indikator), targetSummary(sasaran.indikator)]),
                                target_triwulan: joinItems([targetTriwulanSummary(tujuan.indikator), targetTriwulanSummary(sasaran.indikator)]),
                            }),
                        );
                    }

                    sasaran.strategi.forEach((strategi) => {
                        if (strategi.programs.length === 0) {
                            rows.push(
                                emptyRpjmdRow(`strategi-${strategi.id}`, {
                                    visi: visi.visi,
                                    misi: nodeText(misi.kode, misi.misi),
                                    tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                    indikator_tujuan: indicatorSummary(tujuan.indikator),
                                    sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                    indikator_sasaran: indicatorSummary(sasaran.indikator),
                                    strategi: nodeText(strategi.kode, strategi.strategi),
                                    target_tahunan: joinItems([targetSummary(tujuan.indikator), targetSummary(sasaran.indikator)]),
                                    target_triwulan: joinItems([targetTriwulanSummary(tujuan.indikator), targetTriwulanSummary(sasaran.indikator)]),
                                }),
                            );
                        }

                        strategi.programs.forEach((program) => {
                            rows.push(
                                emptyRpjmdRow(`program-${program.id}`, {
                                    visi: visi.visi,
                                    misi: nodeText(misi.kode, misi.misi),
                                    tujuan: nodeText(tujuan.kode, tujuan.tujuan),
                                    indikator_tujuan: indicatorSummary(tujuan.indikator),
                                    sasaran: nodeText(sasaran.kode, sasaran.sasaran),
                                    indikator_sasaran: indicatorSummary(sasaran.indikator),
                                    strategi: nodeText(strategi.kode, strategi.strategi),
                                    program: nodeText(program.kode, program.nama),
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
                                    opd_penanggung_jawab: joinItems(program.opd_penanggung_jawab.map((opd) => opd.singkatan || opd.nama)),
                                    status_keterhubungan: program.opd_penanggung_jawab.length > 0 ? 'Terhubung OPD' : 'Belum ada OPD',
                                }),
                            );
                        });
                    });
                });
            });
        });
    });

    return rows;
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

const valueText = (value: unknown) => (value === null || value === undefined ? '' : String(value));

const editNode = (type: NodeType, id: number, parentId: number | null, node: any) => {
    editingNode.value = { type, id };
    form.type = type;
    clearNodeForm();
    form.parent_id = parentId ?? '';
    form.kode = valueText(node.kode);
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

const destroyNode = (type: NodeType, id: number, label: string) => {
    if (confirm(`Hapus ${label}? Data turunan juga dapat terpengaruh.`)) {
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

        <section class="flex flex-col gap-3 rounded-lg border bg-card p-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-2">
                <Network class="size-5 text-emerald-700" />
                <div>
                    <h2 class="text-base font-semibold">Cascading RPJMD</h2>
                    <p class="text-sm text-muted-foreground">Pilih tampilan tree atau tabel melebar sesuai kebutuhan monitoring.</p>
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
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <section v-if="viewMode === 'table'" class="rounded-lg border bg-card">
                <div class="flex items-center gap-2 border-b p-4">
                    <Table2 class="size-5 text-emerald-700" />
                    <div>
                        <h2 class="text-base font-semibold">Tabel Cascading Melebar</h2>
                        <p class="text-sm text-muted-foreground">Setiap baris membawa konteks dari visi sampai OPD penanggung jawab.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1600px] text-left text-sm">
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
                            <tr v-for="row in rpjmdCascadingRows" :key="row.key" class="border-b align-top last:border-0">
                                <td class="max-w-[260px] px-4 py-3">{{ row.visi }}</td>
                                <td class="max-w-[260px] px-4 py-3">{{ row.misi }}</td>
                                <td class="max-w-[260px] px-4 py-3">{{ row.tujuan }}</td>
                                <td class="max-w-[280px] px-4 py-3">{{ row.indikator_tujuan }}</td>
                                <td class="max-w-[260px] px-4 py-3">{{ row.sasaran }}</td>
                                <td class="max-w-[280px] px-4 py-3">{{ row.indikator_sasaran }}</td>
                                <td class="max-w-[260px] px-4 py-3">{{ row.strategi }}</td>
                                <td class="max-w-[280px] px-4 py-3 font-medium">{{ row.program }}</td>
                                <td class="max-w-[280px] px-4 py-3">{{ row.indikator_program }}</td>
                                <td class="max-w-[240px] px-4 py-3">{{ row.target_tahunan }}</td>
                                <td class="max-w-[240px] px-4 py-3">{{ row.target_triwulan }}</td>
                                <td class="max-w-[220px] px-4 py-3">{{ row.opd_penanggung_jawab }}</td>
                                <td class="px-4 py-3">
                                    <span
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
                            <tr v-if="rpjmdCascadingRows.length === 0">
                                <td colspan="13" class="px-4 py-10 text-center text-muted-foreground">Belum ada data cascading RPJMD.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-else class="rounded-lg border bg-card">
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
                            <div v-for="misi in visi.misi" :key="misi.id" class="border-l-2 border-emerald-200 pl-3">
                                <div class="rounded-md border bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-xs font-semibold uppercase text-muted-foreground">Misi</div>
                                            <div class="mt-1 text-sm font-medium">{{ misi.kode ? `${misi.kode} - ` : '' }}{{ misi.misi }}</div>
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

                                    <div class="mt-3 space-y-3">
                                        <div v-for="tujuan in misi.tujuan" :key="tujuan.id" class="rounded-md border bg-slate-50 p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="text-xs font-semibold uppercase text-muted-foreground">Tujuan Daerah</div>
                                                    <div class="mt-1 text-sm font-medium">
                                                        {{ tujuan.kode ? `${tujuan.kode} - ` : '' }}{{ tujuan.tujuan }}
                                                    </div>
                                                </div>
                                                <div v-if="can.manage" class="flex items-center gap-1">
                                                    <button
                                                        type="button"
                                                        class="rounded-md p-1 hover:bg-muted"
                                                        title="Edit tujuan"
                                                        @click="editNode('tujuan', tujuan.id, misi.id, tujuan)"
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
                                                                {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
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
                                                                {{ sasaran.kode ? `${sasaran.kode} - ` : '' }}{{ sasaran.sasaran }}
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
                                                                        {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
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
                                                                        {{ strategi.kode ? `${strategi.kode} - ` : '' }}{{ strategi.strategi }}
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
                                                                                {{ program.kode ? `${program.kode} - ` : '' }}{{ program.nama }}
                                                                            </div>
                                                                            <div class="mt-1 text-xs text-muted-foreground">
                                                                                {{
                                                                                    program.urusan_pemerintahan
                                                                                        ? `${program.urusan_pemerintahan.kode} - ${program.urusan_pemerintahan.nama}`
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
                                                                                    <div
                                                                                        class="text-xs font-semibold uppercase text-muted-foreground"
                                                                                    >
                                                                                        Indikator Program
                                                                                    </div>
                                                                                    <div class="mt-1 text-sm">
                                                                                        {{ indikator.kode ? `${indikator.kode} - ` : ''
                                                                                        }}{{ indikator.indikator }}
                                                                                    </div>
                                                                                    <div class="mt-1 text-xs text-muted-foreground">
                                                                                        {{
                                                                                            indikator.satuan?.simbol || indikator.satuan?.nama || '-'
                                                                                        }}
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
                                                                                            editNode(
                                                                                                'target_program',
                                                                                                target.id,
                                                                                                indikator.id,
                                                                                                target,
                                                                                            )
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
                        </div>
                    </article>
                </div>
            </section>

            <aside v-if="can.manage" class="rounded-lg border bg-card p-4 xl:sticky xl:top-4 xl:self-start">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Plus class="size-5 text-emerald-700" />
                        <div>
                            <h2 class="text-base font-semibold">{{ editingNode ? 'Edit Data Cascading' : 'Tambah Data Cascading' }}</h2>
                            <p class="text-sm text-muted-foreground">{{ selectedTypeLabel }}</p>
                        </div>
                    </div>
                    <button v-if="editingNode" type="button" class="rounded-md border px-3 py-1.5 text-xs hover:bg-muted" @click="resetNodeForm">
                        Batal
                    </button>
                </div>

                <form class="grid gap-3" @submit.prevent="submitNode">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="type">Jenis Data</label>
                        <select id="type" v-model="form.type" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div v-if="needsParent" class="grid gap-2">
                        <label class="text-sm font-medium" for="parent_id">{{ parentLabel }}</label>
                        <select id="parent_id" v-model="form.parent_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Pilih {{ parentLabel.toLowerCase() }}</option>
                            <option v-for="option in parentOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.parent_id" />
                    </div>

                    <div v-if="!isTargetType && !isProgramOpdType" class="grid gap-2">
                        <label class="text-sm font-medium" for="kode">Kode</label>
                        <input id="kode" v-model="form.kode" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.kode" />
                    </div>

                    <div v-if="isTextNodeType" class="grid gap-2">
                        <label class="text-sm font-medium" for="uraian">{{ isProgramType ? 'Nama Program' : selectedTypeLabel }}</label>
                        <textarea id="uraian" v-model="form.uraian" rows="3" class="rounded-md border bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.uraian" />
                    </div>

                    <div v-if="isIndicatorType" class="grid gap-2">
                        <label class="text-sm font-medium" for="indikator">Indikator</label>
                        <textarea id="indikator" v-model="form.indikator" rows="3" class="rounded-md border bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.indikator" />
                    </div>

                    <div v-if="isIndicatorType" class="grid gap-2">
                        <label class="text-sm font-medium" for="satuan_indikator_id">Satuan Indikator</label>
                        <select id="satuan_indikator_id" v-model="form.satuan_indikator_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Pilih satuan</option>
                            <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.satuan_indikator_id" />
                    </div>

                    <div v-if="isIndicatorType" class="grid gap-2">
                        <label class="text-sm font-medium" for="tipe_indikator">Tipe Indikator</label>
                        <select id="tipe_indikator" v-model="form.tipe_indikator" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="positif">Positif</option>
                            <option value="negatif">Negatif</option>
                        </select>
                        <InputError :message="form.errors.tipe_indikator" />
                    </div>

                    <div v-if="isIndicatorType" class="grid gap-2">
                        <label class="text-sm font-medium" for="formula">Formula</label>
                        <textarea id="formula" v-model="form.formula" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.formula" />
                    </div>

                    <div v-if="isIndicatorType" class="grid gap-2">
                        <label class="text-sm font-medium" for="sumber_data">Sumber Data</label>
                        <input id="sumber_data" v-model="form.sumber_data" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.sumber_data" />
                    </div>

                    <div v-if="isStrategiType" class="grid gap-2">
                        <label class="text-sm font-medium" for="arah_kebijakan">Arah Kebijakan</label>
                        <textarea
                            id="arah_kebijakan"
                            v-model="form.arah_kebijakan"
                            rows="2"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.arah_kebijakan" />
                    </div>

                    <div v-if="isProgramType" class="grid gap-2">
                        <label class="text-sm font-medium" for="urusan_pemerintahan_id">Urusan Pemerintahan</label>
                        <select
                            id="urusan_pemerintahan_id"
                            v-model="form.urusan_pemerintahan_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        />
                        <InputError :message="form.errors.pagu_indikatif" />
                    </div>

                    <div v-if="isTargetType" class="grid gap-2">
                        <label class="text-sm font-medium" for="periode_tahun_id">Periode Target</label>
                        <select id="periode_tahun_id" v-model="form.periode_tahun_id" class="h-9 rounded-md border bg-background px-3 text-sm">
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
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        />
                        <InputError :message="form.errors.target" />
                    </div>

                    <div v-if="isTargetType" class="grid gap-2">
                        <label class="text-sm font-medium" for="target_text">Target Teks</label>
                        <input id="target_text" v-model="form.target_text" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.target_text" />
                    </div>

                    <div v-if="form.type === 'target_program'" class="grid gap-2">
                        <label class="text-sm font-medium" for="pagu">Pagu Tahunan</label>
                        <input id="pagu" v-model="form.pagu" type="number" step="0.01" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.pagu" />
                    </div>

                    <div v-if="isProgramOpdType" class="grid gap-2">
                        <label class="text-sm font-medium" for="opd_id">OPD Penanggung Jawab</label>
                        <select id="opd_id" v-model="form.opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Pilih OPD</option>
                            <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.opd_id" />
                    </div>

                    <div v-if="isProgramOpdType" class="grid gap-2">
                        <label class="text-sm font-medium" for="peran">Peran</label>
                        <input id="peran" v-model="form.peran" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.peran" />
                    </div>

                    <label v-if="isProgramOpdType" class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_utama" type="checkbox" class="rounded border" />
                        Penanggung jawab utama
                    </label>

                    <div v-if="!isTargetType && !isProgramOpdType" class="grid gap-2">
                        <label class="text-sm font-medium" for="urutan">Urutan</label>
                        <input id="urutan" v-model="form.urutan" type="number" min="1" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.urutan" />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    >
                        {{ editingNode ? 'Perbarui Data Cascading' : 'Simpan Data Cascading' }}
                    </button>
                </form>

                <form class="mt-6 grid gap-3 border-t pt-4" @submit.prevent="submitTargetTriwulan">
                    <div>
                        <h3 class="text-sm font-semibold">Target Triwulan Indikator</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Isi target kinerja dan anggaran per triwulan untuk indikator yang sudah tersedia.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="target_triwulan_table">Jenis Indikator</label>
                        <select
                            id="target_triwulan_table"
                            v-model="targetTriwulanForm.related_table"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                                            class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                        />
                                        <InputError :message="targetTriwulanError(index, 'target_angka')" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="row.target_text"
                                            class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                            placeholder="Opsional"
                                        />
                                        <InputError :message="targetTriwulanError(index, 'target_text')" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="row.target_anggaran"
                                            type="number"
                                            step="0.01"
                                            class="h-9 w-full rounded-md border bg-background px-3 text-sm"
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
                        class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                    >
                        Simpan Target TW I-IV
                    </button>
                </form>
            </aside>
        </div>
    </div>
</template>
