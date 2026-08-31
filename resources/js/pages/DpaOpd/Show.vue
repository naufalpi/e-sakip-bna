<script setup lang="ts">
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, FileCheck2, Landmark, Pencil, Save, ShieldCheck, WalletCards } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Dpa = {
    id: number;
    tahun: number;
    jenis_anggaran: 'murni' | 'perubahan';
    type_label: string;
    judul: string;
    nomor_dpa?: string | null;
    tanggal_pengesahan?: string | null;
    status: string;
    nomor_perda_apbd?: string | null;
    tanggal_perda_apbd?: string | null;
    nomor_perkada_penjabaran?: string | null;
    tanggal_perkada_penjabaran?: string | null;
    nama_pengguna_anggaran?: string | null;
    nip_pengguna_anggaran?: string | null;
    nama_ppkd?: string | null;
    nip_ppkd?: string | null;
    nama_sekretaris_daerah?: string | null;
    nip_sekretaris_daerah?: string | null;
    opd?: { kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode?: string | null; nama: string } | null;
    rka?: { judul: string; nomor_dokumen?: string | null } | null;
};
type Row = {
    id: number;
    kode_program?: string | null;
    nama_program?: string | null;
    kode_kegiatan?: string | null;
    nama_kegiatan?: string | null;
    kode_sub_kegiatan?: string | null;
    nama_sub_kegiatan?: string | null;
    tolok_ukur_kinerja?: string | null;
    target_kinerja?: string | null;
    satuan_kinerja?: string | null;
    sumber_pendanaan?: string | null;
    lokasi?: string | null;
    kelompok_sasaran?: string | null;
    bulan_mulai: number;
    bulan_selesai: number;
    alokasi_tahun_sebelumnya: string | number;
    pagu_rka: string | number;
    pagu_dpa: string | number;
    alokasi_tahun_berikutnya: string | number;
    alasan_penyesuaian?: string | null;
    catatan?: string | null;
    urutan: number;
};
type Readiness = { ready: boolean; issues: string[] };
type WorkflowHistory = {
    id: number;
    action: string;
    from_status?: string | null;
    to_status: string;
    notes?: string | null;
    created_at: string;
    actor?: { name: string } | null;
};
type Workflow = { submitted_by?: number | null; histories?: WorkflowHistory[] } | null;
type BudgetColumns = { previous: number; total: number; next: number };
type PreviewRow = {
    key: string;
    level: 'opd' | 'urusan' | 'bidang' | 'program' | 'kegiatan' | 'sub_kegiatan';
    code: string;
    description: string;
    source: string;
    location: string;
    budget: BudgetColumns;
};

const props = defineProps<{
    dpa: Dpa;
    items: Row[];
    summary: { items_count: number; pagu_rka: number; pagu_dpa: number };
    preview: { rows: PreviewRow[]; total: BudgetColumns };
    submissionReadiness: Readiness;
    approvalReadiness: Readiness;
    workflow: Workflow;
    can: { manage: boolean; verifyBudget: boolean; review: boolean; lock: boolean; unlock: boolean; withdraw: boolean };
}>();

const activeTab = ref<'rincian' | 'preview'>('rincian');
const editOpen = ref(false);
const editing = ref<Row | null>(null);
const itemForm = useForm({ pagu_dpa: 0 as string | number, alasan_penyesuaian: '', catatan: '' });

const groupedPrograms = computed(() => {
    const programs = new Map<
        string,
        { key: string; kode: string; nama: string; kegiatan: Map<string, { key: string; kode: string; nama: string; items: Row[] }> }
    >();
    props.items.forEach((item) => {
        const programKey = `${item.kode_program || '-'}|${item.nama_program || '-'}`;
        if (!programs.has(programKey)) {
            programs.set(programKey, {
                key: programKey,
                kode: item.kode_program || '-',
                nama: item.nama_program || 'Program belum terpetakan',
                kegiatan: new Map(),
            });
        }
        const program = programs.get(programKey)!;
        const activityKey = `${item.kode_kegiatan || '-'}|${item.nama_kegiatan || '-'}`;
        if (!program.kegiatan.has(activityKey)) {
            program.kegiatan.set(activityKey, {
                key: activityKey,
                kode: item.kode_kegiatan || '-',
                nama: item.nama_kegiatan || 'Kegiatan belum terpetakan',
                items: [],
            });
        }
        program.kegiatan.get(activityKey)!.items.push(item);
    });
    return [...programs.values()].map((program) => ({ ...program, kegiatan: [...program.kegiatan.values()] }));
});

const currentReadiness = computed(() => (['submitted', 'verified'].includes(props.dpa.status) ? props.approvalReadiness : props.submissionReadiness));
const currentReadinessTitle = computed(() =>
    ['submitted', 'verified'].includes(props.dpa.status) ? 'DPA belum siap disahkan' : 'DPA belum siap diajukan',
);
const previewRows = computed(() => props.preview.rows);
const previewTotal = computed(() => props.preview.total);
const previewRowClass = (level: PreviewRow['level']) =>
    ({
        opd: 'bg-slate-200/90 font-bold dark:bg-slate-800',
        urusan: 'bg-blue-100/80 font-bold dark:bg-blue-950/60',
        bidang: 'bg-cyan-50 font-bold dark:bg-cyan-950/40',
        program: 'bg-amber-50 font-semibold dark:bg-amber-950/35',
        kegiatan: 'bg-emerald-50 font-semibold dark:bg-emerald-950/30',
        sub_kegiatan: 'bg-white dark:bg-slate-950',
    })[level];
const previewDescriptionClass = (level: PreviewRow['level']) =>
    ({
        opd: 'uppercase tracking-[.04em]',
        urusan: 'pl-2 uppercase tracking-[.03em]',
        bidang: 'pl-5 uppercase tracking-[.02em]',
        program: 'pl-8 uppercase',
        kegiatan: 'pl-11',
        sub_kegiatan: 'pl-14',
    })[level];

const moneyNumber = (value?: string | number | null) => {
    if (value === null || value === undefined || value === '') return 0;
    if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
    const raw = value.trim();
    if (/^\d+(\.\d+)?$/.test(raw)) return Number(raw);
    return Number(raw.replace(/[^\d]/g, '')) || 0;
};
const formatMoneyInput = (value?: string | number | null) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(moneyNumber(value));
const updateMoneyInput = (event: Event) => {
    const digits = (event.target as HTMLInputElement).value.replace(/\D/g, '');
    itemForm.pagu_dpa = digits === '' ? '' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(digits));
};
const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthRange = (start: number, end: number) => (start === end ? months[start - 1] : `${months[start - 1]}–${months[end - 1]}`);
const rupiah = (value?: string | number | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
const rupiahTable = (value?: string | number | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
const formatDate = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(
              new Date(`${value}T00:00:00+07:00`),
          )
        : '-';
const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Perlu Perbaikan',
        verified: 'Terverifikasi',
        approved: 'Disahkan',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;

const openEditor = (item: Row) => {
    editing.value = item;
    itemForm.clearErrors();
    itemForm.pagu_dpa = formatMoneyInput(item.pagu_dpa);
    itemForm.alasan_penyesuaian = item.alasan_penyesuaian ?? '';
    itemForm.catatan = item.catatan ?? '';
    editOpen.value = true;
};
const saveItem = () => {
    if (!editing.value) return;
    itemForm.put(route('dpa-opd.items.update', { dpa_opd: props.dpa.id, item: editing.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            editing.value = null;
        },
    });
};
</script>

<template>
    <Head :title="dpa.judul" />
    <div class="flex flex-col gap-5 p-4 sm:p-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <div
                class="relative border-b border-slate-200 bg-[linear-gradient(116deg,#fff_0%,#f2faf7_62%,#e9f7f1_100%)] px-5 py-5 dark:border-slate-800 dark:bg-[linear-gradient(116deg,#0f172a_0%,#0b2427_62%,#0a302e_100%)] sm:px-6"
            >
                <div class="absolute -right-12 -top-28 size-72 rounded-full bg-emerald-300/20 blur-3xl dark:bg-emerald-500/10"></div>
                <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="flex min-w-0 items-start gap-3.5">
                        <Link
                            :href="route('dpa-opd.index')"
                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white/80 text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300"
                        >
                            <ArrowLeft class="size-4" />
                        </Link>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-[.1em] text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200"
                                    >{{ dpa.type_label }}</span
                                >
                                <span
                                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
                                    >{{ statusLabel(dpa.status) }}</span
                                >
                            </div>
                            <h1 class="mt-2 max-w-4xl text-xl font-bold leading-tight tracking-tight text-slate-950 dark:text-white sm:text-2xl">
                                {{ dpa.judul }}
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ dpa.nomor_dpa || 'Nomor DPA diisi saat pengesahan' }} · Tahun Anggaran {{ dpa.tahun }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="can.manage || can.verifyBudget"
                            :href="route('dpa-opd.edit', dpa.id)"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border bg-white/80 px-3.5 text-sm font-semibold text-slate-700 hover:bg-white dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-200"
                        >
                            <Pencil class="size-4" />{{ can.verifyBudget && !can.manage ? 'Pengesahan' : 'Edit dokumen' }}
                        </Link>
                        <WorkflowActionButtons
                            module="dpa_opd"
                            :model-id="dpa.id"
                            :status="dpa.status"
                            :can-manage="can.manage"
                            :can-review="can.review"
                            :can-lock="can.lock"
                            :can-unlock="can.unlock"
                            :can-withdraw="can.withdraw"
                            :show-verify="true"
                            button-class="h-10 rounded-lg px-3.5"
                        />
                    </div>
                </div>
            </div>
            <div class="grid divide-y divide-slate-200 dark:divide-slate-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="flex items-center gap-3 px-5 py-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <FileCheck2 class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Sub kegiatan</p>
                        <p class="font-bold tabular-nums">{{ summary.items_count }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-5 py-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                        <Landmark class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pagu RKA</p>
                        <p class="font-bold tabular-nums">{{ rupiah(summary.pagu_rka) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-5 py-4">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <WalletCards class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pagu DPA</p>
                        <p class="font-bold tabular-nums">{{ rupiah(summary.pagu_dpa) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="!currentReadiness.ready && !['approved', 'locked'].includes(dpa.status)"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3.5 text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100"
        >
            <div
                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300"
            >
                <AlertTriangle class="size-4" />
            </div>
            <div>
                <p class="text-sm font-bold">{{ currentReadinessTitle }}</p>
                <ul class="mt-1 grid gap-x-8 text-xs leading-5 text-amber-800 dark:text-amber-200 sm:grid-cols-2">
                    <li v-for="issue in currentReadiness.issues" :key="issue" class="before:mr-2 before:content-['—']">{{ issue }}</li>
                </ul>
            </div>
        </section>

        <section
            class="grid gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 shadow-sm dark:border-slate-800 dark:bg-slate-800 lg:grid-cols-[1.15fr_.85fr]"
        >
            <div class="bg-card p-5 sm:p-6">
                <div class="flex items-center gap-2">
                    <Landmark class="size-4 text-emerald-700 dark:text-emerald-300" />
                    <h2 class="font-bold">Dasar Pelaksanaan Anggaran</h2>
                </div>
                <p class="mt-3 font-bold">{{ dpa.opd?.kode }} · {{ dpa.opd?.nama }}</p>
                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Perda APBD</p>
                        <p class="mt-1 text-slate-700 dark:text-slate-200">{{ dpa.nomor_perda_apbd || 'Belum diisi' }}</p>
                        <p class="text-xs text-slate-500">{{ formatDate(dpa.tanggal_perda_apbd) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Perkada Penjabaran APBD</p>
                        <p class="mt-1 text-slate-700 dark:text-slate-200">{{ dpa.nomor_perkada_penjabaran || 'Belum diisi' }}</p>
                        <p class="text-xs text-slate-500">{{ formatDate(dpa.tanggal_perkada_penjabaran) }}</p>
                    </div>
                </div>
                <p class="mt-4 border-l-2 border-emerald-300 pl-3 text-xs leading-5 text-slate-500">Acuan: {{ dpa.rka?.judul || '-' }}</p>
            </div>
            <div class="bg-card p-5 sm:p-6">
                <div class="flex items-center gap-2">
                    <ShieldCheck class="size-4 text-emerald-700 dark:text-emerald-300" />
                    <h2 class="font-bold">Pengesahan</h2>
                </div>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Pengguna Anggaran</dt>
                        <dd class="mt-1 font-medium">{{ dpa.nama_pengguna_anggaran || 'Belum diisi' }}</dd>
                        <dd class="text-xs text-slate-500">NIP {{ dpa.nip_pengguna_anggaran || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">PPKD</dt>
                        <dd class="mt-1 font-medium">{{ dpa.nama_ppkd || 'Belum diisi' }}</dd>
                        <dd class="text-xs text-slate-500">NIP {{ dpa.nip_ppkd || '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Persetujuan Sekretaris Daerah</dt>
                        <dd class="mt-1 font-medium">{{ dpa.nama_sekretaris_daerah || 'Belum diisi' }}</dd>
                        <dd class="text-xs text-slate-500">NIP {{ dpa.nip_sekretaris_daerah || '-' }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <header
                class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <h2 class="font-bold">Rincian DPA-BELANJA SKPD</h2>
                    <p class="text-xs text-slate-500">Pagu pelaksanaan per sub kegiatan.</p>
                </div>
                <div class="inline-flex self-start rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold"
                        :class="
                            activeTab === 'rincian' ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-950 dark:text-emerald-300' : 'text-slate-500'
                        "
                        @click="activeTab = 'rincian'"
                    >
                        Rincian
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold"
                        :class="
                            activeTab === 'preview' ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-950 dark:text-emerald-300' : 'text-slate-500'
                        "
                        @click="activeTab = 'preview'"
                    >
                        Preview tabel
                    </button>
                </div>
            </header>

            <div v-if="activeTab === 'rincian'" class="divide-y divide-slate-200 dark:divide-slate-800">
                <div v-for="program in groupedPrograms" :key="program.key" class="p-5 sm:p-6">
                    <div class="flex items-start gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
                        <span class="mt-0.5 rounded-md bg-[#064e3b] px-2 py-1 font-mono text-[11px] font-bold text-white">{{ program.kode }}</span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.14em] text-slate-400">Program</p>
                            <h3 class="mt-0.5 font-bold leading-6">{{ program.nama }}</h3>
                        </div>
                    </div>
                    <div
                        v-for="activity in program.kegiatan"
                        :key="activity.key"
                        class="mt-5 border-l border-emerald-200 pl-4 dark:border-emerald-900"
                    >
                        <div class="flex items-start gap-2">
                            <span class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300">{{ activity.kode }}</span>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ activity.nama }}</p>
                        </div>
                        <div
                            class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800"
                        >
                            <article
                                v-for="item in activity.items"
                                :key="item.id"
                                class="grid gap-4 bg-card px-4 py-4 lg:grid-cols-[minmax(14rem,1.3fr)_minmax(11rem,.8fr)_minmax(10rem,.7fr)_auto] lg:items-center"
                            >
                                <div>
                                    <p class="font-mono text-[11px] font-bold text-emerald-700 dark:text-emerald-300">{{ item.kode_sub_kegiatan }}</p>
                                    <p class="mt-1 text-sm font-semibold leading-5">{{ item.nama_sub_kegiatan }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ monthRange(item.bulan_mulai, item.bulan_selesai) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Target Kinerja</p>
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-700 dark:text-slate-200">{{ item.tolok_ukur_kinerja || '-' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                        {{ item.target_kinerja || '-' }} {{ item.satuan_kinerja || '' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Pagu DPA</p>
                                    <p class="mt-1 text-sm font-bold tabular-nums">{{ rupiah(item.pagu_dpa) }}</p>
                                    <p class="mt-1 text-[10px] text-slate-500">RKA {{ rupiah(item.pagu_rka) }}</p>
                                </div>
                                <button
                                    v-if="can.manage || can.verifyBudget"
                                    type="button"
                                    class="inline-flex size-9 items-center justify-center rounded-lg border text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                    title="Edit rincian DPA"
                                    @click="openEditor(item)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                            </article>
                        </div>
                    </div>
                </div>
                <div v-if="!items.length" class="px-6 py-16 text-center text-sm text-slate-500">Tidak ada rincian sub kegiatan.</div>
            </div>

            <div v-else class="bg-slate-50/60 p-3 dark:bg-slate-950/30 sm:p-5">
                <div class="overflow-x-auto rounded-xl border border-slate-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <div class="min-w-[900px] text-slate-950 dark:text-slate-100">
                        <div class="grid grid-cols-[1fr_200px] border-b border-slate-400/70 text-center dark:border-slate-600">
                            <div class="border-r border-slate-400/70 px-6 py-4 dark:border-slate-600">
                                <p class="text-sm font-extrabold uppercase leading-5 tracking-[.05em]">Dokumen Pelaksanaan Anggaran</p>
                                <p class="text-sm font-extrabold uppercase leading-5 tracking-[.05em]">Satuan Kerja Perangkat Daerah</p>
                                <p class="mt-2 border-t border-slate-300 pt-2 text-[11px] font-semibold dark:border-slate-700">
                                    Pemerintah Kabupaten Banjarnegara Tahun Anggaran {{ dpa.tahun }}
                                </p>
                            </div>
                            <div class="flex items-center justify-center px-4 py-3 text-[11px] font-extrabold uppercase leading-4">
                                Formulir<br />{{ dpa.type_label === 'DPPA-SKPD' ? 'DPPA-Belanja' : 'DPA-Belanja' }}<br />SKPD
                            </div>
                        </div>
                        <dl
                            class="grid grid-cols-[110px_16px_1fr] items-start gap-y-1 border-b border-slate-400/70 px-4 py-3 text-[11px] dark:border-slate-600"
                        >
                            <dt class="font-bold">Nomor DPA</dt>
                            <dd>:</dd>
                            <dd class="font-semibold">{{ dpa.nomor_dpa || 'Belum diisi' }}</dd>
                            <dt class="font-bold">SKPD</dt>
                            <dd>:</dd>
                            <dd class="font-semibold">{{ dpa.opd?.kode ? `${dpa.opd.kode} · ` : '' }}{{ dpa.opd?.nama || '-' }}</dd>
                            <template v-if="dpa.opd_unit"
                                ><dt class="font-bold">Unit Organisasi</dt>
                                <dd>:</dd>
                                <dd>{{ dpa.opd_unit.kode ? `${dpa.opd_unit.kode} · ` : '' }}{{ dpa.opd_unit.nama }}</dd></template
                            >
                        </dl>
                        <div class="border-b border-slate-400/70 px-6 py-3 text-center dark:border-slate-600">
                            <p class="text-xs font-extrabold">Rekapitulasi Dokumen Pelaksanaan Belanja</p>
                            <p class="text-xs font-extrabold">Berdasarkan Program, Kegiatan, dan Sub Kegiatan</p>
                        </div>
                        <table class="w-full table-fixed border-collapse text-[10px] leading-[1.35]">
                            <colgroup>
                                <col class="w-[145px]" />
                                <col class="w-[330px]" />
                                <col class="w-[95px]" />
                                <col class="w-[115px]" />
                                <col class="w-[100px]" />
                                <col class="w-[100px]" />
                                <col class="w-[100px]" />
                            </colgroup>
                            <thead class="bg-slate-100 text-center font-extrabold dark:bg-slate-900">
                                <tr>
                                    <th rowspan="2" class="border-b border-r border-slate-400/70 px-2 py-2 dark:border-slate-600">Kode</th>
                                    <th rowspan="2" class="border-b border-r border-slate-400/70 px-2 py-2 dark:border-slate-600">Uraian</th>
                                    <th rowspan="2" class="border-b border-r border-slate-400/70 px-2 py-2 dark:border-slate-600">Sumber Dana</th>
                                    <th rowspan="2" class="border-b border-r border-slate-400/70 px-2 py-2 dark:border-slate-600">Lokasi</th>
                                    <th colspan="3" class="border-b border-slate-400/70 px-2 py-2 dark:border-slate-600">Jumlah Anggaran</th>
                                </tr>
                                <tr>
                                    <th class="border-b border-r border-slate-400/70 px-2 py-2 dark:border-slate-600">
                                        <span class="block">Alokasi Tahun-1</span
                                        ><span class="mt-0.5 block font-semibold text-slate-500">Tahun {{ dpa.tahun - 1 }}</span>
                                    </th>
                                    <th class="border-b border-r border-slate-400/70 px-2 py-2 dark:border-slate-600">
                                        <span class="block">Total Pagu DPA</span
                                        ><span class="mt-0.5 block font-semibold text-slate-500">Tahun {{ dpa.tahun }}</span>
                                    </th>
                                    <th class="border-b border-slate-400/70 px-2 py-2 dark:border-slate-600">
                                        <span class="block">Alokasi Tahun+1</span
                                        ><span class="mt-0.5 block font-semibold text-slate-500">Tahun {{ dpa.tahun + 1 }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in previewRows" :key="row.key" class="align-top transition-colors" :class="previewRowClass(row.level)">
                                    <td
                                        class="border-b border-r border-slate-300 px-2.5 py-2 font-mono font-semibold tabular-nums dark:border-slate-700"
                                    >
                                        {{ row.code }}
                                    </td>
                                    <td
                                        class="border-b border-r border-slate-300 px-2.5 py-2 dark:border-slate-700"
                                        :class="previewDescriptionClass(row.level)"
                                    >
                                        {{ row.description }}
                                    </td>
                                    <td class="whitespace-pre-line break-words border-b border-r border-slate-300 px-2 py-2 dark:border-slate-700">
                                        {{ row.source }}
                                    </td>
                                    <td class="whitespace-pre-line break-words border-b border-r border-slate-300 px-2 py-2 dark:border-slate-700">
                                        {{ row.location }}
                                    </td>
                                    <td class="border-b border-r border-slate-300 px-2 py-2 text-right tabular-nums dark:border-slate-700">
                                        {{ rupiahTable(row.budget.previous) }}
                                    </td>
                                    <td
                                        class="border-b border-r border-slate-300 px-2 py-2 text-right font-semibold tabular-nums dark:border-slate-700"
                                    >
                                        {{ rupiahTable(row.budget.total) }}
                                    </td>
                                    <td class="border-b border-slate-300 px-2 py-2 text-right tabular-nums dark:border-slate-700">
                                        {{ rupiahTable(row.budget.next) }}
                                    </td>
                                </tr>
                                <tr v-if="!previewRows.length">
                                    <td colspan="7" class="px-6 py-14 text-center text-sm text-slate-500">Belum ada rincian sub kegiatan.</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-200 font-extrabold dark:bg-slate-800">
                                <tr>
                                    <td colspan="4" class="border-r border-slate-400/70 px-3 py-3 text-right dark:border-slate-600">
                                        Jumlah Anggaran Belanja
                                    </td>
                                    <td class="border-r border-slate-400/70 px-2 py-3 text-right tabular-nums dark:border-slate-600">
                                        {{ rupiahTable(previewTotal.previous) }}
                                    </td>
                                    <td class="border-r border-slate-400/70 px-2 py-3 text-right tabular-nums dark:border-slate-600">
                                        {{ rupiahTable(previewTotal.total) }}
                                    </td>
                                    <td class="px-2 py-3 text-right tabular-nums">{{ rupiahTable(previewTotal.next) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">
                    Preview menampilkan total Pagu DPA final per struktur urusan sampai sub kegiatan.
                </p>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />
    </div>

    <Dialog v-model:open="editOpen">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader
                ><DialogTitle>Edit Rincian DPA</DialogTitle
                ><DialogDescription>{{ editing?.kode_sub_kegiatan }} · {{ editing?.nama_sub_kegiatan }}</DialogDescription></DialogHeader
            >
            <form class="mt-2 grid gap-5" @submit.prevent="saveItem">
                <section class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/20 sm:p-5">
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(250px,.8fr)] sm:items-center">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Pagu RKA disetujui</p>
                            <p class="mt-1 text-base font-bold tabular-nums">{{ rupiah(editing?.pagu_rka) }}</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                Pagu DPA hanya disesuaikan bila terdapat perbedaan pada dokumen pelaksanaan resmi.
                            </p>
                        </div>
                        <label>
                            <span class="text-[10px] font-bold uppercase tracking-[.1em] text-emerald-700 dark:text-emerald-300">Total Pagu DPA</span>
                            <span class="relative mt-1.5 block">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-bold text-slate-500"
                                    >Rp</span
                                >
                                <input
                                    :value="itemForm.pagu_dpa"
                                    :disabled="!can.verifyBudget"
                                    inputmode="numeric"
                                    class="h-11 w-full rounded-xl border border-emerald-200 bg-white pl-10 pr-3 text-right text-base font-bold tabular-nums outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-70 dark:border-emerald-800 dark:bg-slate-950 dark:focus:ring-emerald-950"
                                    @input="updateMoneyInput"
                                />
                            </span>
                            <span v-if="itemForm.errors.pagu_dpa" class="mt-1 block text-xs text-red-600">{{ itemForm.errors.pagu_dpa }}</span>
                        </label>
                    </div>
                </section>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Alasan Penyesuaian Pagu</span>
                        <textarea
                            v-model="itemForm.alasan_penyesuaian"
                            rows="3"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm"
                            placeholder="Wajib jika Pagu DPA berbeda dari RKA."
                        ></textarea>
                        <span v-if="itemForm.errors.alasan_penyesuaian" class="mt-1 block text-xs text-red-600">{{
                            itemForm.errors.alasan_penyesuaian
                        }}</span>
                    </label>
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Keterangan</span
                        ><textarea v-model="itemForm.catatan" rows="3" class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm"></textarea>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button
                        type="button"
                        class="h-10 rounded-lg border px-4 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800"
                        @click="editOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="itemForm.processing"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#064e3b] px-4 text-sm font-semibold text-white hover:bg-[#043d2e] disabled:opacity-50"
                    >
                        <Save class="size-4" />{{ itemForm.processing ? 'Menyimpan...' : 'Simpan Data DPA' }}
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
