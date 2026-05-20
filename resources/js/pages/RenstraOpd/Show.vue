<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Layers3, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';

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

type Indikator = {
    id: number;
    kode?: string | null;
    indikator: string;
    linked: boolean;
    satuan?: { nama: string; simbol?: string | null } | null;
    targets?: Target[];
};

type SubKegiatan = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    indikator: Indikator[];
};

type Kegiatan = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    sub_kegiatan: SubKegiatan[];
};

type Program = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    linked: boolean;
    indikator: Indikator[];
    kegiatan: Kegiatan[];
};

type Sasaran = {
    id: number;
    kode?: string | null;
    sasaran: string;
    linked: boolean;
    indikator: Indikator[];
    programs: Program[];
};

type Tujuan = {
    id: number;
    kode?: string | null;
    tujuan: string;
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
type Workflow = {
    status: string;
    histories: Array<{ id: number; action: string; from_status?: string | null; to_status: string; notes?: string | null; created_at: string; actor?: { name: string } | null }>;
} | null;

const props = defineProps<{
    renstra: Renstra;
    nodeOptions: Record<string, Option[]>;
    rpjmdReferenceOptions: Record<string, Option[]>;
    periodeOptions: Option[];
    satuanOptions: Option[];
    can: {
        manage: boolean;
        review: boolean;
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
const parentOptions = computed(() => (parentKey.value ? props.nodeOptions[parentKey.value] ?? [] : []));
const parentLabel = computed(() => (parentKey.value ? parentLabels[parentKey.value] ?? 'Induk Data' : 'Induk Data'));
const needsParent = computed(() => Boolean(parentKey.value));
const isIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program', 'indikator_sub_kegiatan'].includes(form.type));
const isTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(form.type));
const isTextNodeType = computed(() => ['tujuan', 'sasaran', 'program', 'kegiatan', 'sub_kegiatan'].includes(form.type));
const hasPaguIndikatif = computed(() => ['program', 'kegiatan', 'sub_kegiatan'].includes(form.type));

watch(
    () => form.type,
    () => {
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
        form.formula = '';
        form.sumber_data = '';
        form.target = '';
        form.target_text = '';
        form.pagu = '';
        form.pagu_indikatif = '';
        form.urutan = 1;
        form.clearErrors();
    },
);

const submitNode = () => {
    form.post(route('renstra-opd.nodes.store', props.renstra.id), {
        preserveScroll: true,
        onSuccess: () => {
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
            form.formula = '';
            form.sumber_data = '';
            form.target = '';
            form.target_text = '';
            form.pagu = '';
            form.pagu_indikatif = '';
            form.urutan = 1;
        },
    });
};

const destroyNode = (type: NodeType, id: number, label: string) => {
    if (confirm(`Hapus ${label}? Data turunan juga dapat terpengaruh.`)) {
        router.delete(route('renstra-opd.nodes.destroy', [props.renstra.id, type, id]), {
            preserveScroll: true,
        });
    }
};

const transition = (action: string) => {
    router.post(route('workflow.transition', { module: 'renstra_opd', id: props.renstra.id }), { action }, { preserveScroll: true });
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
const targetDisplay = (target: Target) => target.target_text || target.target || '-';
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
                    <Link v-if="can.manage" :href="route('renstra-opd.edit', renstra.id)" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted">
                        <Pencil class="size-4" />
                        Edit
                    </Link>
                    <button v-if="can.manage" type="button" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800" @click="transition('submit')">Ajukan</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="transition('approve')">Setujui</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm text-amber-700 hover:bg-amber-50" @click="transition('revision')">Revisi</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm text-red-700 hover:bg-red-50" @click="transition('reject')">Tolak</button>
                    <button v-if="can.review" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="transition('lock')">Kunci</button>
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

            <section class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Riwayat Workflow</h2>
                <div v-if="workflow?.histories?.length" class="mt-3 divide-y text-sm">
                    <div v-for="history in workflow.histories" :key="history.id" class="py-3">
                        <div class="font-medium">{{ statusLabel(history.from_status || 'draft') }} ke {{ statusLabel(history.to_status) }}</div>
                        <div class="text-xs text-muted-foreground">{{ history.actor?.name || '-' }} - {{ history.created_at }}</div>
                        <div v-if="history.notes" class="mt-1 text-xs text-muted-foreground">{{ history.notes }}</div>
                    </div>
                </div>
                <div v-else class="mt-3 text-sm text-muted-foreground">Belum ada riwayat workflow.</div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[1fr_380px]">
                <section class="rounded-lg border bg-card">
                    <div class="flex items-center gap-2 border-b p-4">
                        <Layers3 class="size-5 text-emerald-700" />
                        <div>
                            <h2 class="text-base font-semibold">Tree Cascading OPD</h2>
                            <p class="text-sm text-muted-foreground">Tujuan, sasaran, program, kegiatan, sub kegiatan, indikator, dan target tahunan.</p>
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
                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(tujuan.linked)">{{ linkLabel(tujuan.linked) }}</span>
                                    </div>
                                    <div class="mt-1 text-sm font-medium">{{ tujuan.kode ? `${tujuan.kode} - ` : '' }}{{ tujuan.tujuan }}</div>
                                </div>
                                <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus tujuan" @click="destroyNode('tujuan', tujuan.id, 'tujuan')">
                                    <Trash2 class="size-4" />
                                </button>
                            </div>

                            <div class="space-y-3 p-3">
                                <div v-for="indikator in tujuan.indikator" :key="indikator.id" class="rounded-md border bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-semibold uppercase text-muted-foreground">Indikator Tujuan</span>
                                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(indikator.linked)">{{ linkLabel(indikator.linked) }}</span>
                                            </div>
                                            <div class="mt-1 text-sm">{{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}</div>
                                            <div class="mt-1 text-xs text-muted-foreground">{{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}</div>
                                        </div>
                                        <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus indikator" @click="destroyNode('indikator_tujuan', indikator.id, 'indikator tujuan')">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                    <div v-if="indikator.targets?.length" class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="target in indikator.targets" :key="target.id" class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800">
                                            {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
                                        </span>
                                    </div>
                                </div>

                                <div v-for="sasaran in tujuan.sasaran" :key="sasaran.id" class="rounded-md border bg-slate-50 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-semibold uppercase text-muted-foreground">Sasaran OPD</span>
                                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(sasaran.linked)">{{ linkLabel(sasaran.linked) }}</span>
                                            </div>
                                            <div class="mt-1 text-sm font-medium">{{ sasaran.kode ? `${sasaran.kode} - ` : '' }}{{ sasaran.sasaran }}</div>
                                        </div>
                                        <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus sasaran" @click="destroyNode('sasaran', sasaran.id, 'sasaran')">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>

                                    <div class="mt-3 space-y-3">
                                        <div v-for="indikator in sasaran.indikator" :key="indikator.id" class="rounded-md border bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Indikator Sasaran</span>
                                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(indikator.linked)">{{ linkLabel(indikator.linked) }}</span>
                                                    </div>
                                                    <div class="mt-1 text-sm">{{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}</div>
                                                    <div class="mt-1 text-xs text-muted-foreground">{{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}</div>
                                                </div>
                                                <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus indikator" @click="destroyNode('indikator_sasaran', indikator.id, 'indikator sasaran')">
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </div>
                                            <div v-if="indikator.targets?.length" class="mt-2 flex flex-wrap gap-2">
                                                <span v-for="target in indikator.targets" :key="target.id" class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800">
                                                    {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div v-for="program in sasaran.programs" :key="program.id" class="rounded-md border bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-xs font-semibold uppercase text-muted-foreground">Program OPD</span>
                                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(program.linked)">{{ linkLabel(program.linked) }}</span>
                                                    </div>
                                                    <div class="mt-1 text-sm font-medium">{{ program.kode ? `${program.kode} - ` : '' }}{{ program.nama }}</div>
                                                    <div class="mt-1 text-xs text-muted-foreground">Pagu indikatif: {{ program.pagu_indikatif || '-' }}</div>
                                                </div>
                                                <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus program" @click="destroyNode('program', program.id, 'program')">
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </div>

                                            <div class="mt-3 grid gap-2">
                                                <div v-for="indikator in program.indikator" :key="indikator.id" class="rounded-md border bg-slate-50 p-3">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <span class="text-xs font-semibold uppercase text-muted-foreground">Indikator Program</span>
                                                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="linkClass(indikator.linked)">{{ linkLabel(indikator.linked) }}</span>
                                                            </div>
                                                            <div class="mt-1 text-sm">{{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}</div>
                                                            <div class="mt-1 text-xs text-muted-foreground">{{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }}</div>
                                                        </div>
                                                        <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus indikator" @click="destroyNode('indikator_program', indikator.id, 'indikator program')">
                                                            <Trash2 class="size-4" />
                                                        </button>
                                                    </div>
                                                    <div v-if="indikator.targets?.length" class="mt-2 flex flex-wrap gap-2">
                                                        <span v-for="target in indikator.targets" :key="target.id" class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800">
                                                            {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }} - Pagu {{ target.pagu || '-' }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div v-for="kegiatan in program.kegiatan" :key="kegiatan.id" class="rounded-md border bg-slate-50 p-3">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-xs font-semibold uppercase text-muted-foreground">Kegiatan OPD</div>
                                                            <div class="mt-1 text-sm font-medium">{{ kegiatan.kode ? `${kegiatan.kode} - ` : '' }}{{ kegiatan.nama }}</div>
                                                        </div>
                                                        <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus kegiatan" @click="destroyNode('kegiatan', kegiatan.id, 'kegiatan')">
                                                            <Trash2 class="size-4" />
                                                        </button>
                                                    </div>
                                                    <div v-for="sub in kegiatan.sub_kegiatan" :key="sub.id" class="mt-3 rounded-md border bg-white p-3">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Sub Kegiatan</div>
                                                                <div class="mt-1 text-sm font-medium">{{ sub.kode ? `${sub.kode} - ` : '' }}{{ sub.nama }}</div>
                                                            </div>
                                                            <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus sub kegiatan" @click="destroyNode('sub_kegiatan', sub.id, 'sub kegiatan')">
                                                                <Trash2 class="size-4" />
                                                            </button>
                                                        </div>
                                                        <div v-if="sub.indikator.length" class="mt-2 grid gap-2">
                                                            <div v-for="indikator in sub.indikator" :key="indikator.id" class="rounded-md bg-slate-50 px-3 py-2 text-sm">
                                                                {{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}
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
                    <div class="mb-4 flex items-center gap-2">
                        <Plus class="size-5 text-emerald-700" />
                        <div>
                            <h2 class="text-base font-semibold">Tambah Data Cascading</h2>
                            <p class="text-sm text-muted-foreground">{{ selectedTypeLabel }}</p>
                        </div>
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
                                <option v-for="option in rpjmdReferenceOptions.tujuan_daerah || []" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </div>

                        <div v-if="form.type === 'indikator_tujuan'" class="grid gap-2">
                            <label class="text-sm font-medium" for="indikator_tujuan_daerah_id">Referensi Indikator Tujuan RPJMD</label>
                            <select id="indikator_tujuan_daerah_id" v-model="form.indikator_tujuan_daerah_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_tujuan_daerah || []" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </div>

                        <div v-if="form.type === 'sasaran'" class="grid gap-2">
                            <label class="text-sm font-medium" for="sasaran_daerah_id">Referensi Sasaran RPJMD</label>
                            <select id="sasaran_daerah_id" v-model="form.sasaran_daerah_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.sasaran_daerah || []" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </div>

                        <div v-if="form.type === 'indikator_sasaran'" class="grid gap-2">
                            <label class="text-sm font-medium" for="indikator_sasaran_daerah_id">Referensi Indikator Sasaran RPJMD</label>
                            <select id="indikator_sasaran_daerah_id" v-model="form.indikator_sasaran_daerah_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_sasaran_daerah || []" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </div>

                        <div v-if="form.type === 'program'" class="grid gap-2">
                            <label class="text-sm font-medium" for="program_rpjmd_id">Referensi Program RPJMD</label>
                            <select id="program_rpjmd_id" v-model="form.program_rpjmd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.program_rpjmd || []" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </div>

                        <div v-if="form.type === 'indikator_program'" class="grid gap-2">
                            <label class="text-sm font-medium" for="indikator_program_rpjmd_id">Referensi Indikator Program RPJMD</label>
                            <select id="indikator_program_rpjmd_id" v-model="form.indikator_program_rpjmd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                                <option value="">Tidak dihubungkan</option>
                                <option v-for="option in rpjmdReferenceOptions.indikator_program_rpjmd || []" :key="option.id" :value="option.id">{{ option.label }}</option>
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
                            <select id="satuan_indikator_id" v-model="form.satuan_indikator_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                                <option value="">Pilih satuan</option>
                                <option v-for="option in satuanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </div>

                        <div v-if="isIndicatorType" class="grid gap-2">
                            <label class="text-sm font-medium" for="sumber_data">Sumber Data</label>
                            <input id="sumber_data" v-model="form.sumber_data" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        </div>

                        <div v-if="hasPaguIndikatif" class="grid gap-2">
                            <label class="text-sm font-medium" for="pagu_indikatif">Pagu Indikatif</label>
                            <input id="pagu_indikatif" v-model="form.pagu_indikatif" type="number" step="0.01" class="h-9 rounded-md border bg-background px-3 text-sm" />
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
                            <input id="target" v-model="form.target" type="number" step="0.0001" class="h-9 rounded-md border bg-background px-3 text-sm" />
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

                        <button type="submit" :disabled="form.processing" class="mt-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                            Simpan Data Cascading
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
