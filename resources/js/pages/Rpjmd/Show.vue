<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { GitBranch, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';

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

type Indikator = {
    id: number;
    kode?: string | null;
    indikator: string;
    formula?: string | null;
    sumber_data?: string | null;
    satuan?: { nama: string; simbol?: string | null } | null;
    targets: Target[];
};

type Program = {
    id: number;
    kode?: string | null;
    nama: string;
    pagu_indikatif?: string | number | null;
    status: string;
    urusan_pemerintahan?: { kode: string; nama: string } | null;
    opd_penanggung_jawab: Array<{ id: number; nama: string; singkatan?: string | null; peran: string; is_utama: boolean }>;
    indikator: Indikator[];
};

type Strategi = {
    id: number;
    kode?: string | null;
    strategi: string;
    arah_kebijakan?: string | null;
    programs: Program[];
};

type Sasaran = {
    id: number;
    kode?: string | null;
    sasaran: string;
    indikator: Indikator[];
    strategi: Strategi[];
};

type Tujuan = {
    id: number;
    kode?: string | null;
    tujuan: string;
    indikator: Indikator[];
    sasaran: Sasaran[];
};

type Misi = {
    id: number;
    kode?: string | null;
    misi: string;
    tujuan: Tujuan[];
};

type Visi = {
    id: number;
    visi: string;
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

const props = defineProps<{
    rpjmd: RpjmdDetail;
    nodeOptions: Record<string, Option[]>;
    periodeOptions: Option[];
    satuanOptions: Option[];
    opdOptions: Option[];
    urusanOptions: Option[];
    can: {
        manage: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RPJMD Kabupaten', href: '/rpjmd' },
    { title: `${props.rpjmd.tahun_awal}-${props.rpjmd.tahun_akhir}`, href: '#' },
];

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

const selectedTypeLabel = computed(() => typeOptions.find((type) => type.value === form.type)?.label ?? 'Data Cascading');
const parentKey = computed(() => parentKeyByType[form.type]);
const parentOptions = computed(() => (parentKey.value ? props.nodeOptions[parentKey.value] ?? [] : []));
const parentLabel = computed(() => (parentKey.value ? parentLabels[parentKey.value] ?? 'Induk Data' : 'Induk Data'));
const needsParent = computed(() => Boolean(parentKey.value));
const isIndicatorType = computed(() => ['indikator_tujuan', 'indikator_sasaran', 'indikator_program'].includes(form.type));
const isTargetType = computed(() => ['target_tujuan', 'target_sasaran', 'target_program'].includes(form.type));
const isTextNodeType = computed(() => ['visi', 'misi', 'tujuan', 'sasaran', 'strategi', 'program'].includes(form.type));
const isProgramType = computed(() => form.type === 'program');
const isProgramOpdType = computed(() => form.type === 'program_opd');
const isStrategiType = computed(() => form.type === 'strategi');

watch(
    () => form.type,
    () => {
        form.parent_id = '';
        form.periode_tahun_id = '';
        form.satuan_indikator_id = '';
        form.opd_id = '';
        form.urusan_pemerintahan_id = '';
        form.kode = '';
        form.uraian = '';
        form.indikator = '';
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
    },
);

const submitNode = () => {
    form.post(route('rpjmd.nodes.store', props.rpjmd.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.parent_id = '';
            form.periode_tahun_id = '';
            form.satuan_indikator_id = '';
            form.opd_id = '';
            form.urusan_pemerintahan_id = '';
            form.kode = '';
            form.uraian = '';
            form.indikator = '';
            form.formula = '';
            form.sumber_data = '';
            form.target = '';
            form.target_text = '';
            form.pagu = '';
            form.pagu_indikatif = '';
            form.arah_kebijakan = '';
            form.urutan = 1;
        },
    });
};

const destroyNode = (type: NodeType, id: number, label: string) => {
    if (confirm(`Hapus ${label}? Data turunan juga dapat terpengaruh.`)) {
        router.delete(route('rpjmd.nodes.destroy', [props.rpjmd.id, type, id]), {
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
    <Head title="Cascading RPJMD" />

    <AppLayout :breadcrumbs="breadcrumbs">
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

            <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
                <section class="rounded-lg border bg-card">
                    <div class="flex items-center gap-2 border-b p-4">
                        <GitBranch class="size-5 text-emerald-700" />
                        <div>
                            <h2 class="text-base font-semibold">Tree Cascading RPJMD</h2>
                            <p class="text-sm text-muted-foreground">Visi, misi, tujuan, sasaran, strategi, program, indikator, target, dan OPD penanggung jawab.</p>
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
                                <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus visi" @click="destroyNode('visi', visi.id, 'visi')">
                                    <Trash2 class="size-4" />
                                </button>
                            </div>

                            <div class="space-y-3 p-3">
                                <div v-for="misi in visi.misi" :key="misi.id" class="border-l-2 border-emerald-200 pl-3">
                                    <div class="rounded-md border bg-white p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Misi</div>
                                                <div class="mt-1 text-sm font-medium">{{ misi.kode ? `${misi.kode} - ` : '' }}{{ misi.misi }}</div>
                                            </div>
                                            <button v-if="can.manage" type="button" class="rounded-md p-1 text-red-700 hover:bg-red-50" title="Hapus misi" @click="destroyNode('misi', misi.id, 'misi')">
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>

                                        <div class="mt-3 space-y-3">
                                            <div v-for="tujuan in misi.tujuan" :key="tujuan.id" class="rounded-md border bg-slate-50 p-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">Tujuan Daerah</div>
                                                        <div class="mt-1 text-sm font-medium">{{ tujuan.kode ? `${tujuan.kode} - ` : '' }}{{ tujuan.tujuan }}</div>
                                                    </div>
                                                    <button
                                                        v-if="can.manage"
                                                        type="button"
                                                        class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                        title="Hapus tujuan"
                                                        @click="destroyNode('tujuan', tujuan.id, 'tujuan')"
                                                    >
                                                        <Trash2 class="size-4" />
                                                    </button>
                                                </div>

                                                <div v-if="tujuan.indikator.length" class="mt-3 grid gap-2">
                                                    <div v-for="indikator in tujuan.indikator" :key="indikator.id" class="rounded-md border bg-white p-3">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Indikator Tujuan</div>
                                                                <div class="mt-1 text-sm">{{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}</div>
                                                                <div class="mt-1 text-xs text-muted-foreground">
                                                                    {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }} - {{ indikator.sumber_data || 'Sumber data belum diisi' }}
                                                                </div>
                                                            </div>
                                                            <button
                                                                v-if="can.manage"
                                                                type="button"
                                                                class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                title="Hapus indikator"
                                                                @click="destroyNode('indikator_tujuan', indikator.id, 'indikator tujuan')"
                                                            >
                                                                <Trash2 class="size-4" />
                                                            </button>
                                                        </div>
                                                        <div v-if="indikator.targets.length" class="mt-2 flex flex-wrap gap-2">
                                                            <span v-for="target in indikator.targets" :key="target.id" class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800">
                                                                {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-if="tujuan.sasaran.length" class="mt-3 space-y-3">
                                                    <div v-for="sasaran in tujuan.sasaran" :key="sasaran.id" class="rounded-md border bg-white p-3">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Sasaran Daerah</div>
                                                                <div class="mt-1 text-sm font-medium">{{ sasaran.kode ? `${sasaran.kode} - ` : '' }}{{ sasaran.sasaran }}</div>
                                                            </div>
                                                            <button
                                                                v-if="can.manage"
                                                                type="button"
                                                                class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                title="Hapus sasaran"
                                                                @click="destroyNode('sasaran', sasaran.id, 'sasaran')"
                                                            >
                                                                <Trash2 class="size-4" />
                                                            </button>
                                                        </div>

                                                        <div v-if="sasaran.indikator.length" class="mt-3 grid gap-2">
                                                            <div v-for="indikator in sasaran.indikator" :key="indikator.id" class="rounded-md border bg-slate-50 p-3">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">Indikator Sasaran</div>
                                                                        <div class="mt-1 text-sm">{{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}</div>
                                                                        <div class="mt-1 text-xs text-muted-foreground">
                                                                            {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }} - {{ indikator.sumber_data || 'Sumber data belum diisi' }}
                                                                        </div>
                                                                    </div>
                                                                    <button
                                                                        v-if="can.manage"
                                                                        type="button"
                                                                        class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                        title="Hapus indikator"
                                                                        @click="destroyNode('indikator_sasaran', indikator.id, 'indikator sasaran')"
                                                                    >
                                                                        <Trash2 class="size-4" />
                                                                    </button>
                                                                </div>
                                                                <div v-if="indikator.targets.length" class="mt-2 flex flex-wrap gap-2">
                                                                    <span v-for="target in indikator.targets" :key="target.id" class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800">
                                                                        {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div v-if="sasaran.strategi.length" class="mt-3 space-y-3">
                                                            <div v-for="strategi in sasaran.strategi" :key="strategi.id" class="rounded-md border bg-slate-50 p-3">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">Strategi Daerah</div>
                                                                        <div class="mt-1 text-sm font-medium">{{ strategi.kode ? `${strategi.kode} - ` : '' }}{{ strategi.strategi }}</div>
                                                                        <div v-if="strategi.arah_kebijakan" class="mt-1 text-xs text-muted-foreground">Arah kebijakan: {{ strategi.arah_kebijakan }}</div>
                                                                    </div>
                                                                    <button
                                                                        v-if="can.manage"
                                                                        type="button"
                                                                        class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                        title="Hapus strategi"
                                                                        @click="destroyNode('strategi', strategi.id, 'strategi')"
                                                                    >
                                                                        <Trash2 class="size-4" />
                                                                    </button>
                                                                </div>

                                                                <div v-if="strategi.programs.length" class="mt-3 space-y-3">
                                                                    <div v-for="program in strategi.programs" :key="program.id" class="rounded-md border bg-white p-3">
                                                                        <div class="flex items-start justify-between gap-3">
                                                                            <div>
                                                                                <div class="text-xs font-semibold uppercase text-muted-foreground">Program RPJMD</div>
                                                                                <div class="mt-1 text-sm font-medium">{{ program.kode ? `${program.kode} - ` : '' }}{{ program.nama }}</div>
                                                                                <div class="mt-1 text-xs text-muted-foreground">
                                                                                    {{ program.urusan_pemerintahan ? `${program.urusan_pemerintahan.kode} - ${program.urusan_pemerintahan.nama}` : 'Urusan belum diisi' }}
                                                                                    - Pagu {{ formatCurrency(program.pagu_indikatif) }}
                                                                                </div>
                                                                            </div>
                                                                            <button
                                                                                v-if="can.manage"
                                                                                type="button"
                                                                                class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                                title="Hapus program"
                                                                                @click="destroyNode('program', program.id, 'program')"
                                                                            >
                                                                                <Trash2 class="size-4" />
                                                                            </button>
                                                                        </div>

                                                                        <div v-if="program.opd_penanggung_jawab.length" class="mt-3 flex flex-wrap gap-2">
                                                                            <span
                                                                                v-for="opd in program.opd_penanggung_jawab"
                                                                                :key="`${program.id}-${opd.id}-${opd.peran}`"
                                                                                class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-800"
                                                                            >
                                                                                {{ opd.singkatan || opd.nama }} - {{ opd.is_utama ? 'Utama' : opd.peran }}
                                                                            </span>
                                                                        </div>

                                                                        <div v-if="program.indikator.length" class="mt-3 grid gap-2">
                                                                            <div v-for="indikator in program.indikator" :key="indikator.id" class="rounded-md border bg-slate-50 p-3">
                                                                                <div class="flex items-start justify-between gap-3">
                                                                                    <div>
                                                                                        <div class="text-xs font-semibold uppercase text-muted-foreground">Indikator Program</div>
                                                                                        <div class="mt-1 text-sm">{{ indikator.kode ? `${indikator.kode} - ` : '' }}{{ indikator.indikator }}</div>
                                                                                        <div class="mt-1 text-xs text-muted-foreground">
                                                                                            {{ indikator.satuan?.simbol || indikator.satuan?.nama || '-' }} - {{ indikator.sumber_data || 'Sumber data belum diisi' }}
                                                                                        </div>
                                                                                    </div>
                                                                                    <button
                                                                                        v-if="can.manage"
                                                                                        type="button"
                                                                                        class="rounded-md p-1 text-red-700 hover:bg-red-50"
                                                                                        title="Hapus indikator"
                                                                                        @click="destroyNode('indikator_program', indikator.id, 'indikator program')"
                                                                                    >
                                                                                        <Trash2 class="size-4" />
                                                                                    </button>
                                                                                </div>
                                                                                <div v-if="indikator.targets.length" class="mt-2 flex flex-wrap gap-2">
                                                                                    <span v-for="target in indikator.targets" :key="target.id" class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800">
                                                                                        {{ target.periode_tahun.tahun }}: {{ targetDisplay(target) }} - {{ formatCurrency(target.pagu) }}
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
                            <textarea id="arah_kebijakan" v-model="form.arah_kebijakan" rows="2" class="rounded-md border bg-background px-3 py-2 text-sm" />
                            <InputError :message="form.errors.arah_kebijakan" />
                        </div>

                        <div v-if="isProgramType" class="grid gap-2">
                            <label class="text-sm font-medium" for="urusan_pemerintahan_id">Urusan Pemerintahan</label>
                            <select id="urusan_pemerintahan_id" v-model="form.urusan_pemerintahan_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                                <option value="">Pilih urusan</option>
                                <option v-for="option in urusanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.urusan_pemerintahan_id" />
                        </div>

                        <div v-if="isProgramType" class="grid gap-2">
                            <label class="text-sm font-medium" for="pagu_indikatif">Pagu Indikatif</label>
                            <input id="pagu_indikatif" v-model="form.pagu_indikatif" type="number" step="0.01" class="h-9 rounded-md border bg-background px-3 text-sm" />
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
                            <input id="target" v-model="form.target" type="number" step="0.0001" class="h-9 rounded-md border bg-background px-3 text-sm" />
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

                        <button type="submit" :disabled="form.processing" class="mt-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                            Simpan Data Cascading
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
