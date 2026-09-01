<script setup lang="ts">
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Banknote, Download, Landmark, Pencil, Rows3, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Rka = {
    id: number;
    tahun: number;
    jenis_anggaran: 'murni' | 'perubahan';
    type_label: string;
    judul: string;
    nomor_dokumen?: string | null;
    tanggal_dokumen?: string | null;
    nomor_kua?: string | null;
    tanggal_kua?: string | null;
    nomor_ppas?: string | null;
    tanggal_ppas?: string | null;
    status: string;
    catatan?: string | null;
    opd?: { kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode?: string | null; nama: string } | null;
    rkpd?: { judul: string; tahun: number; jenis_versi: string } | null;
    renja?: { judul: string; nomor_dokumen?: string | null; tahun: number; jenis_versi: string } | null;
};
type Row = {
    id: number;
    kode_urusan?: string | null;
    nama_urusan?: string | null;
    kode_bidang?: string | null;
    nama_bidang?: string | null;
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
    jenis_belanja?: string | null;
    alokasi_tahun_sebelumnya: string | number;
    pagu_renja: string | number;
    pagu_rka: string | number;
    alokasi_tahun_berikutnya: string | number;
    alasan_penyesuaian?: string | null;
    catatan?: string | null;
    urutan: number;
};
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
type BudgetColumns = {
    previous: number;
    total: number;
    next: number;
};
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
    rka: Rka;
    items: Row[];
    summary: { items_count: number; pagu_renja: number; pagu_rka: number };
    readiness: { ready: boolean; issues: string[]; incomplete_items: number };
    preview: { rows: PreviewRow[]; total: BudgetColumns };
    workflow: Workflow;
    can: { manage: boolean; review: boolean; lock: boolean; unlock: boolean; withdraw: boolean };
}>();

const activeTab = ref<'rincian' | 'preview'>('rincian');
const editOpen = ref(false);
const editing = ref<Row | null>(null);
const canDeleteItems = computed(() => props.can.manage && ['draft', 'revision', 'rejected'].includes(props.rka.status));
const itemForm = useForm({
    tolok_ukur_kinerja: '',
    target_kinerja: '',
    satuan_kinerja: '',
    sumber_pendanaan: '',
    lokasi: '',
    kelompok_sasaran: '',
    bulan_mulai: 1,
    bulan_selesai: 12,
    alokasi_tahun_sebelumnya: 0 as string | number,
    pagu_rka: 0 as string | number,
    alokasi_tahun_berikutnya: 0 as string | number,
    alasan_penyesuaian: '',
    catatan: '',
});

const openEditor = (item: Row) => {
    editing.value = item;
    itemForm.clearErrors();
    itemForm.tolok_ukur_kinerja = item.tolok_ukur_kinerja ?? '';
    itemForm.target_kinerja = item.target_kinerja ?? '';
    itemForm.satuan_kinerja = item.satuan_kinerja ?? '';
    itemForm.sumber_pendanaan = item.sumber_pendanaan ?? '';
    itemForm.lokasi = item.lokasi ?? '';
    itemForm.kelompok_sasaran = item.kelompok_sasaran ?? '';
    itemForm.bulan_mulai = item.bulan_mulai;
    itemForm.bulan_selesai = item.bulan_selesai;
    itemForm.alokasi_tahun_sebelumnya = formatMoneyInput(item.alokasi_tahun_sebelumnya);
    itemForm.pagu_rka = formatMoneyInput(item.pagu_rka);
    itemForm.alokasi_tahun_berikutnya = formatMoneyInput(item.alokasi_tahun_berikutnya);
    itemForm.alasan_penyesuaian = item.alasan_penyesuaian ?? '';
    itemForm.catatan = item.catatan ?? '';
    editOpen.value = true;
};

const saveItem = () => {
    if (!editing.value) return;
    itemForm.put(route('rka-opd.items.update', { rka_opd: props.rka.id, item: editing.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            editing.value = null;
        },
    });
};

const destroyItem = async (item: Row) => {
    const label = [item.kode_sub_kegiatan, item.nama_sub_kegiatan].filter(Boolean).join(' - ');

    if (await confirmDelete(`Hapus sub kegiatan ${label || 'ini'} dari RKA? Data sumber RENJA tidak akan ikut terhapus.`)) {
        router.delete(route('rka-opd.items.destroy', { rka_opd: props.rka.id, item: item.id }), { preserveScroll: true });
    }
};

const groupedPrograms = computed(() => {
    const programs = new Map<
        string,
        { key: string; kode: string; nama: string; kegiatan: Map<string, { key: string; kode: string; nama: string; items: Row[] }> }
    >();
    props.items.forEach((item) => {
        const programKey = `${item.kode_program || '-'}|${item.nama_program || '-'}`;
        if (!programs.has(programKey))
            programs.set(programKey, {
                key: programKey,
                kode: item.kode_program || '-',
                nama: item.nama_program || 'Program belum terpetakan',
                kegiatan: new Map(),
            });
        const program = programs.get(programKey)!;
        const kegiatanKey = `${item.kode_kegiatan || '-'}|${item.nama_kegiatan || '-'}`;
        if (!program.kegiatan.has(kegiatanKey))
            program.kegiatan.set(kegiatanKey, {
                key: kegiatanKey,
                kode: item.kode_kegiatan || '-',
                nama: item.nama_kegiatan || 'Kegiatan belum terpetakan',
                items: [],
            });
        program.kegiatan.get(kegiatanKey)!.items.push(item);
    });
    return [...programs.values()].map((program) => ({ ...program, kegiatan: [...program.kegiatan.values()] }));
});

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
type MoneyFormField = 'alokasi_tahun_sebelumnya' | 'pagu_rka' | 'alokasi_tahun_berikutnya';
const formatMoneyInput = (value?: string | number | null) => {
    if (value === null || value === undefined || value === '') return '0';
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(moneyNumber(value));
};
const updateMoneyInput = (field: MoneyFormField, event: Event) => {
    const digits = (event.target as HTMLInputElement).value.replace(/\D/g, '');
    itemForm[field] = digits === '' ? '' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(digits));
};

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthRange = (start: number, end: number) => (start === end ? months[start - 1] : `${months[start - 1]}–${months[end - 1]}`);
const rupiah = (value?: string | number | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
const rupiahTable = (value?: string | number | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
        Number(value || 0),
    );
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
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;
</script>

<template>
    <Head :title="rka.judul" />

    <div class="flex flex-col gap-5 p-4 sm:p-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <div
                class="relative border-b border-slate-200 bg-[linear-gradient(115deg,#fff_0%,#f5f9ff_63%,#edf6ff_100%)] px-5 py-5 dark:border-slate-800 dark:bg-[linear-gradient(115deg,#0f172a_0%,#0b1d34_63%,#0a2746_100%)] sm:px-6"
            >
                <div class="absolute -right-12 -top-28 size-72 rounded-full bg-blue-300/20 blur-3xl dark:bg-blue-500/10"></div>
                <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="flex min-w-0 items-start gap-3.5">
                        <Link
                            :href="route('rka-opd.index')"
                            class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white/80 text-slate-600 shadow-sm hover:bg-white dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300 dark:hover:bg-slate-900"
                            ><ArrowLeft class="size-4"
                        /></Link>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-bold uppercase tracking-[.1em] text-blue-700 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-200"
                                    >{{ rka.type_label }}</span
                                ><span
                                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
                                    >{{ statusLabel(rka.status) }}</span
                                >
                            </div>
                            <h1 class="mt-2 max-w-4xl text-xl font-bold leading-tight tracking-tight text-slate-950 dark:text-white sm:text-2xl">
                                {{ rka.judul }}
                            </h1>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ rka.nomor_dokumen || 'Nomor dokumen belum diisi' }} · Tahun Anggaran {{ rka.tahun }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="can.manage"
                            :href="route('rka-opd.edit', rka.id)"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            ><Pencil class="size-4" />Edit dokumen</Link
                        >
                        <WorkflowActionButtons
                            module="rka_opd"
                            :model-id="rka.id"
                            :status="rka.status"
                            :can-manage="can.manage"
                            :can-review="can.review"
                            :can-lock="can.lock"
                            :can-unlock="can.unlock"
                            :can-withdraw="can.withdraw"
                            :show-verify="false"
                            button-class="h-10 rounded-lg px-3.5"
                        />
                    </div>
                </div>
            </div>

            <div class="grid divide-y divide-slate-200 dark:divide-slate-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="flex items-center gap-3 px-5 py-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <Rows3 class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Sub kegiatan</p>
                        <p class="font-bold tabular-nums text-slate-950 dark:text-white">{{ summary.items_count }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-5 py-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                        <Landmark class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pagu RENJA</p>
                        <p class="font-bold tabular-nums text-slate-950 dark:text-white">{{ rupiah(summary.pagu_renja) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-5 py-4">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <Banknote class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pagu RKA</p>
                        <p class="font-bold tabular-nums text-slate-950 dark:text-white">{{ rupiah(summary.pagu_rka) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="!readiness.ready && ['draft', 'revision', 'rejected'].includes(rka.status)"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3.5 text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100"
        >
            <div
                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300"
            >
                <AlertTriangle class="size-4" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold">RKA belum siap diajukan</p>
                <ul class="mt-1 grid gap-x-8 gap-y-0.5 text-xs leading-5 text-amber-800 dark:text-amber-200 sm:grid-cols-2">
                    <li v-for="issue in readiness.issues" :key="issue" class="before:mr-2 before:content-['—']">{{ issue }}</li>
                </ul>
            </div>
        </section>

        <section
            class="grid gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 shadow-sm dark:border-slate-800 dark:bg-slate-800 lg:grid-cols-[1.1fr_.9fr]"
        >
            <div class="bg-card p-5 sm:p-6">
                <div class="flex items-center gap-2">
                    <Landmark class="size-4 text-[#00336C] dark:text-blue-300" />
                    <h2 class="font-bold text-slate-900 dark:text-white">Organisasi</h2>
                </div>
                <p class="mt-3 text-base font-bold text-slate-950 dark:text-white">{{ rka.opd?.kode }} · {{ rka.opd?.nama }}</p>
                <p v-if="rka.opd_unit" class="mt-1 text-sm text-slate-500">Unit Organisasi: {{ rka.opd_unit.kode }} · {{ rka.opd_unit.nama }}</p>
                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Acuan RENJA</p>
                        <p class="mt-1 line-clamp-2 text-slate-700 dark:text-slate-200">{{ rka.renja?.judul || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Acuan RKPD</p>
                        <p class="mt-1 line-clamp-2 text-slate-700 dark:text-slate-200">{{ rka.rkpd?.judul || '-' }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-card p-5 sm:p-6">
                <h2 class="font-bold text-slate-900 dark:text-white">Dasar KUA–PPAS</h2>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">KUA</dt>
                        <dd class="mt-1 font-medium text-slate-800 dark:text-slate-200">{{ rka.nomor_kua || 'Belum diisi' }}</dd>
                        <dd class="text-xs text-slate-500">{{ formatDate(rka.tanggal_kua) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">PPAS</dt>
                        <dd class="mt-1 font-medium text-slate-800 dark:text-slate-200">{{ rka.nomor_ppas || 'Belum diisi' }}</dd>
                        <dd class="text-xs text-slate-500">{{ formatDate(rka.tanggal_ppas) }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <div
                class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <h2 class="font-bold text-slate-900 dark:text-white">Rincian RKA-BELANJA SKPD</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Struktur kinerja dan kerangka anggaran per sub kegiatan.</p>
                </div>
                <div class="flex items-center gap-2 self-start">
                    <a
                        v-if="activeTab === 'preview'"
                        :href="route('rka-opd.preview.export', rka.id)"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-800 transition hover:border-emerald-300 hover:bg-emerald-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200 dark:hover:bg-emerald-900/60"
                    >
                        <Download class="size-4" />
                        Export Excel
                    </a>
                    <div class="inline-flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                            :class="
                                activeTab === 'rincian' ? 'bg-white text-[#00336C] shadow-sm dark:bg-slate-950 dark:text-blue-300' : 'text-slate-500'
                            "
                            @click="activeTab = 'rincian'"
                        >
                            Rincian</button
                        ><button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                            :class="
                                activeTab === 'preview' ? 'bg-white text-[#00336C] shadow-sm dark:bg-slate-950 dark:text-blue-300' : 'text-slate-500'
                            "
                            @click="activeTab = 'preview'"
                        >
                            Preview tabel
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'rincian'" class="divide-y divide-slate-200 dark:divide-slate-800">
                <div v-for="program in groupedPrograms" :key="program.key" class="p-5 sm:p-6">
                    <div class="flex items-start gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
                        <span class="mt-0.5 rounded-md bg-[#00336C] px-2 py-1 font-mono text-[11px] font-bold text-white">{{ program.kode }}</span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.14em] text-slate-400">Program</p>
                            <h3 class="mt-0.5 font-bold leading-6 text-slate-900 dark:text-white">{{ program.nama }}</h3>
                        </div>
                    </div>
                    <div v-for="kegiatan in program.kegiatan" :key="kegiatan.key" class="mt-5 border-l border-blue-200 pl-4 dark:border-blue-900">
                        <div class="flex items-start gap-2">
                            <span class="font-mono text-xs font-bold text-blue-700 dark:text-blue-300">{{ kegiatan.kode }}</span>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ kegiatan.nama }}</p>
                        </div>
                        <div
                            class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800"
                        >
                            <article
                                v-for="item in kegiatan.items"
                                :key="item.id"
                                class="grid gap-4 bg-card px-4 py-4 lg:grid-cols-[minmax(13rem,1.35fr)_minmax(11rem,.9fr)_minmax(9rem,.65fr)_minmax(10rem,.7fr)_auto] lg:items-center"
                            >
                                <div class="min-w-0">
                                    <p class="font-mono text-[11px] font-bold text-blue-700 dark:text-blue-300">{{ item.kode_sub_kegiatan }}</p>
                                    <p class="mt-1 text-sm font-semibold leading-5 text-slate-900 dark:text-white">{{ item.nama_sub_kegiatan }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Pelaksanaan {{ monthRange(item.bulan_mulai, item.bulan_selesai) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tolok ukur kinerja</p>
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-700 dark:text-slate-200">{{ item.tolok_ukur_kinerja || '-' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-[#00336C] dark:text-blue-300">
                                        {{ item.target_kinerja || '-' }} {{ item.satuan_kinerja || '' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Pagu RENJA</p>
                                    <p class="mt-1 text-sm font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                                        {{ rupiah(item.pagu_renja) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Pagu RKA</p>
                                    <p class="mt-1 text-sm font-bold tabular-nums text-slate-950 dark:text-white">{{ rupiah(item.pagu_rka) }}</p>
                                </div>
                                <div v-if="can.manage" class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                                        title="Edit rincian RKA"
                                        :aria-label="`Edit ${item.nama_sub_kegiatan || 'sub kegiatan'}`"
                                        @click="openEditor(item)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        v-if="canDeleteItems"
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50 hover:text-red-700 dark:border-red-900/70 dark:text-red-400 dark:hover:bg-red-950/50 dark:hover:text-red-300"
                                        title="Hapus sub kegiatan dari RKA"
                                        :aria-label="`Hapus ${item.nama_sub_kegiatan || 'sub kegiatan'} dari RKA`"
                                        @click="destroyItem(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
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
                                <p class="text-sm font-extrabold uppercase leading-5 tracking-[.05em]">Rencana Kerja dan Anggaran</p>
                                <p class="text-sm font-extrabold uppercase leading-5 tracking-[.05em]">Satuan Kerja Perangkat Daerah</p>
                                <p class="mt-2 border-t border-slate-300 pt-2 text-[11px] font-semibold dark:border-slate-700">
                                    Pemerintah Kabupaten Banjarnegara Tahun Anggaran {{ rka.tahun }}
                                </p>
                            </div>
                            <div class="flex items-center justify-center px-4 py-3 text-[11px] font-extrabold uppercase leading-4">
                                Rekapitulasi<br />RKA-Belanja<br />SKPD
                            </div>
                        </div>

                        <dl
                            class="grid grid-cols-[110px_16px_1fr] items-start gap-y-1 border-b border-slate-400/70 px-4 py-3 text-[11px] dark:border-slate-600"
                        >
                            <dt class="font-bold">Organisasi</dt>
                            <dd>:</dd>
                            <dd class="font-semibold">{{ rka.opd?.kode ? `${rka.opd.kode} · ` : '' }}{{ rka.opd?.nama || '-' }}</dd>
                            <template v-if="rka.opd_unit">
                                <dt class="font-bold">Unit Organisasi</dt>
                                <dd>:</dd>
                                <dd>{{ rka.opd_unit.kode ? `${rka.opd_unit.kode} · ` : '' }}{{ rka.opd_unit.nama }}</dd>
                            </template>
                        </dl>

                        <div class="border-b border-slate-400/70 px-6 py-3 text-center dark:border-slate-600">
                            <p class="text-xs font-extrabold">Rincian Anggaran Belanja</p>
                            <p class="text-xs font-extrabold">Berdasarkan Program dan Kegiatan</p>
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
                                        <span class="block">Alokasi Tahun-1</span>
                                        <span class="mt-0.5 block font-semibold text-slate-500 dark:text-slate-400">Tahun {{ rka.tahun - 1 }}</span>
                                    </th>
                                    <th class="border-b border-r border-slate-400/70 px-2 py-2 dark:border-slate-600">
                                        <span class="block">Total Pagu RKA</span>
                                        <span class="mt-0.5 block font-semibold text-slate-500 dark:text-slate-400">Tahun {{ rka.tahun }}</span>
                                    </th>
                                    <th class="border-b border-slate-400/70 px-2 py-2 dark:border-slate-600">
                                        <span class="block">Alokasi Tahun+1</span>
                                        <span class="mt-0.5 block font-semibold text-slate-500 dark:text-slate-400">Tahun {{ rka.tahun + 1 }}</span>
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
                    Preview menampilkan total Pagu RKA final tanpa rincian jenis belanja.
                </p>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />
    </div>

    <Dialog v-model:open="editOpen">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-5xl">
            <DialogHeader
                ><DialogTitle>Edit Rincian RKA</DialogTitle
                ><DialogDescription>{{ editing?.kode_sub_kegiatan }} · {{ editing?.nama_sub_kegiatan }}</DialogDescription></DialogHeader
            >
            <form class="mt-2 grid gap-5" @submit.prevent="saveItem">
                <fieldset class="grid gap-4 sm:grid-cols-2">
                    <label class="sm:col-span-2"
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Indikator dan Tolok Ukur Kinerja</span
                        ><textarea
                            v-model="itemForm.tolok_ukur_kinerja"
                            rows="2"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        ></textarea>
                    </label>
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Target Kinerja</span
                        ><input
                            v-model="itemForm.target_kinerja"
                            class="mt-1.5 h-10 w-full rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                    /></label>
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Satuan</span
                        ><input
                            v-model="itemForm.satuan_kinerja"
                            class="mt-1.5 h-10 w-full rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                    /></label>
                    <label class="sm:col-span-2"
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Sumber Pendanaan</span
                        ><textarea
                            v-model="itemForm.sumber_pendanaan"
                            rows="2"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        ></textarea>
                    </label>
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Lokasi</span
                        ><textarea
                            v-model="itemForm.lokasi"
                            rows="2"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        ></textarea>
                    </label>
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Kelompok Sasaran</span
                        ><textarea
                            v-model="itemForm.kelompok_sasaran"
                            rows="2"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        ></textarea>
                    </label>
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Mulai Pelaksanaan</span
                        ><select v-model="itemForm.bulan_mulai" class="mt-1.5 h-10 w-full rounded-xl border bg-background px-3 text-sm">
                            <option v-for="(month, index) in months" :key="month" :value="index + 1">{{ month }}</option>
                        </select></label
                    >
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Selesai Pelaksanaan</span
                        ><select v-model="itemForm.bulan_selesai" class="mt-1.5 h-10 w-full rounded-xl border bg-background px-3 text-sm">
                            <option v-for="(month, index) in months" :key="month" :value="index + 1">{{ month }}</option></select
                        ><span v-if="itemForm.errors.bulan_selesai" class="mt-1 block text-xs text-red-600">{{
                            itemForm.errors.bulan_selesai
                        }}</span></label
                    >
                </fieldset>

                <section class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/20 sm:p-5">
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(280px,.72fr)] sm:items-center">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-800"
                            >
                                <Banknote class="size-5" />
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pagu RKA Tahun {{ rka.tahun }}</h3>
                                <p class="mt-1 max-w-xl text-xs leading-5 text-slate-600 dark:text-slate-300">
                                    Masukkan total pagu final untuk sub kegiatan ini tanpa rincian jenis belanja.
                                </p>
                            </div>
                        </div>
                        <label>
                            <span class="text-[10px] font-bold uppercase tracking-[.1em] text-emerald-700 dark:text-emerald-300">Total Pagu RKA</span>
                            <span class="relative mt-1.5 block">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-bold text-slate-500 dark:text-slate-400"
                                    >Rp</span
                                >
                                <input
                                    :value="itemForm.pagu_rka"
                                    inputmode="numeric"
                                    class="h-11 w-full rounded-xl border border-emerald-200 bg-white pl-10 pr-3 text-right text-base font-bold tabular-nums text-slate-950 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:border-emerald-800 dark:bg-slate-950 dark:text-white dark:focus:ring-emerald-950"
                                    @input="updateMoneyInput('pagu_rka', $event)"
                                />
                            </span>
                            <span v-if="itemForm.errors.pagu_rka" class="mt-1 block text-xs text-red-600 dark:text-red-400">{{
                                itemForm.errors.pagu_rka
                            }}</span>
                        </label>
                    </div>
                </section>

                <div class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/60 sm:grid-cols-3">
                    <label
                        ><span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Alokasi Tahun-1</span
                        ><input
                            :value="itemForm.alokasi_tahun_sebelumnya"
                            inputmode="numeric"
                            class="mt-1.5 h-10 w-full rounded-lg border bg-background px-3 text-right text-sm tabular-nums"
                            @input="updateMoneyInput('alokasi_tahun_sebelumnya', $event)"
                    /></label>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Pagu Acuan RENJA</span
                        ><span
                            class="mt-1.5 flex h-10 items-center justify-end rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold tabular-nums text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                            >{{ rupiah(editing?.pagu_renja) }}</span
                        >
                    </div>
                    <label
                        ><span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Alokasi Tahun+1</span
                        ><input
                            :value="itemForm.alokasi_tahun_berikutnya"
                            inputmode="numeric"
                            class="mt-1.5 h-10 w-full rounded-lg border bg-background px-3 text-right text-sm tabular-nums"
                            @input="updateMoneyInput('alokasi_tahun_berikutnya', $event)"
                    /></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Catatan Perbedaan dengan RENJA</span
                        ><textarea
                            v-model="itemForm.alasan_penyesuaian"
                            rows="3"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                            placeholder="Wajib jika Pagu RKA berbeda dari Pagu RENJA."
                        ></textarea
                        ><span v-if="itemForm.errors.alasan_penyesuaian" class="mt-1 block text-xs text-red-600">{{
                            itemForm.errors.alasan_penyesuaian
                        }}</span></label
                    ><label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Keterangan</span
                        ><textarea
                            v-model="itemForm.catatan"
                            rows="3"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        ></textarea>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button
                        type="button"
                        class="h-10 rounded-lg border px-4 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800"
                        @click="editOpen = false"
                    >
                        Batal</button
                    ><button
                        type="submit"
                        :disabled="itemForm.processing"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855] disabled:opacity-50"
                    >
                        <Save class="size-4" />{{ itemForm.processing ? 'Menyimpan...' : 'Simpan Data RKA' }}
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
