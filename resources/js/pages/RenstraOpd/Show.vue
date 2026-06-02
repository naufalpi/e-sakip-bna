<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Layers3, Network, Pencil, Plus, Table2, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
const viewMode = ref<'tree' | 'table'>('tree');

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

const valueText = (value: unknown) => (value === null || value === undefined ? '' : String(value));

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
                    <Link :href="route('renstra-opd.index')" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Kembali</Link>
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
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[1fr_380px]">
                <section v-if="viewMode === 'table'" class="rounded-lg border bg-card">
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
                            Belum ada cascading Renstra OPD.
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
                                <div v-if="can.manage" class="flex items-center gap-1">
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
                                                <div v-if="can.manage" class="flex items-center gap-1">
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
                                                        <div v-if="can.manage" class="flex items-center gap-1">
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
                                                        <div v-if="can.manage" class="flex items-center gap-1">
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
                                                            <div v-if="can.manage" class="flex items-center gap-1">
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
                                                                    <div v-if="can.manage" class="flex items-center gap-1">
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

                        <div v-if="form.type === 'tujuan'" class="grid gap-2">
                            <label class="text-sm font-medium" for="tujuan_daerah_id">Referensi Tujuan RPJMD</label>
                            <select id="tujuan_daerah_id" v-model="form.tujuan_daerah_id" class="h-9 rounded-md border bg-background px-3 text-sm">
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
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_tujuan_daerah || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.type === 'sasaran'" class="grid gap-2">
                            <label class="text-sm font-medium" for="sasaran_daerah_id">Referensi Sasaran RPJMD</label>
                            <select id="sasaran_daerah_id" v-model="form.sasaran_daerah_id" class="h-9 rounded-md border bg-background px-3 text-sm">
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
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_sasaran_daerah || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.type === 'program'" class="grid gap-2">
                            <label class="text-sm font-medium" for="program_rpjmd_id">Referensi Program RPJMD</label>
                            <select id="program_rpjmd_id" v-model="form.program_rpjmd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
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
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_program_rpjmd || []" :key="option.id" :value="option.id">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="!isTargetType" class="grid gap-2">
                            <label class="text-sm font-medium" for="kode">Kode</label>
                            <input id="kode" v-model="form.kode" class="h-9 rounded-md border bg-background px-3 text-sm" />
                            <InputError :message="form.errors.kode" />
                        </div>

                        <div v-if="isTextNodeType" class="grid gap-2">
                            <label class="text-sm font-medium" for="uraian">{{ selectedTypeLabel }}</label>
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
                            <select
                                id="satuan_indikator_id"
                                v-model="form.satuan_indikator_id"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Pilih satuan</option>
                                <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
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
                            <label class="text-sm font-medium" for="sumber_data">Sumber Data</label>
                            <input id="sumber_data" v-model="form.sumber_data" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        </div>

                        <div v-if="hasPaguIndikatif" class="grid gap-2">
                            <label class="text-sm font-medium" for="pagu_indikatif">Pagu Indikatif</label>
                            <input
                                id="pagu_indikatif"
                                v-model="form.pagu_indikatif"
                                type="number"
                                step="0.01"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            />
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
                        </div>

                        <div v-if="isTargetType" class="grid gap-2">
                            <label class="text-sm font-medium" for="target_text">Target Teks</label>
                            <input id="target_text" v-model="form.target_text" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        </div>

                        <div v-if="form.type === 'target_program'" class="grid gap-2">
                            <label class="text-sm font-medium" for="pagu">Pagu Tahunan</label>
                            <input id="pagu" v-model="form.pagu" type="number" step="0.01" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        </div>

                        <div v-if="!isTargetType" class="grid gap-2">
                            <label class="text-sm font-medium" for="urutan">Urutan</label>
                            <input id="urutan" v-model="form.urutan" type="number" min="1" class="h-9 rounded-md border bg-background px-3 text-sm" />
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
                            <p class="mt-1 text-xs text-muted-foreground">Isi target kinerja dan target anggaran per triwulan untuk Renstra OPD.</p>
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
    </AppLayout>
</template>
