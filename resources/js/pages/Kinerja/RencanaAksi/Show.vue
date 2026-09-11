<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Database, Edit3, FileDown, Info, Save } from 'lucide-vue-next';
import { computed } from 'vue';

type Quarter = { triwulan: number; target?: string | number | null; target_text?: string | null };
type ItemRow = {
    id: number;
    level?: string | null;
    kode_snapshot?: string | null;
    uraian_snapshot?: string | null;
    aksi: string;
    indikator?: string | null;
    formula_snapshot?: string | null;
    formula?: string | null;
    satuan_snapshot?: string | null;
    tipe_perhitungan_snapshot?: string | null;
    target?: string | number | null;
    target_text?: string | null;
    anggaran?: string | number | null;
    penanggung_jawab?: string | null;
    target_triwulan: Quarter[];
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
        metadata?: { correction_reference?: string; source_correction?: { reference?: string } } | null;
    }>;
} | null;

const props = defineProps<{
    item: {
        id: number;
        judul: string;
        tahun: number;
        status: string;
        format_version?: number;
        snapshot_dibuat_pada?: string | null;
        catatan?: string | null;
        opd?: { nama: string; singkatan?: string | null } | null;
        periode_tahun?: { tahun: number; nama: string } | null;
        perjanjian_kinerja?: { judul: string; tahun: number } | null;
        renstra_opd?: { judul: string } | null;
        dpa_opd?: { label: string; judul: string } | null;
        items: ItemRow[];
    };
    workflow: Workflow;
    can: { manage: boolean; review: boolean; lock: boolean; export: boolean };
}>();

const isMatrix = computed(() => Number(props.item.format_version || 1) >= 2);
const editable = computed(() => props.can.manage && ['draft', 'revision', 'rejected'].includes(props.item.status));
const quarterValue = (row: ItemRow, quarter: number) => {
    const value = row.target_triwulan?.find((target) => Number(target.triwulan) === quarter);
    return value?.target_text ?? (value?.target == null ? '' : String(value.target));
};
const form = useForm({
    items: props.item.items.map((row) => ({
        id: row.id,
        formula: row.formula ?? row.formula_snapshot ?? '',
        penanggung_jawab: row.penanggung_jawab || '',
        target_triwulan: [1, 2, 3, 4].map((quarter) => ({ triwulan: quarter, target_text: quarterValue(row, quarter) })),
    })),
});

const completedRows = computed(
    () =>
        form.items.filter(
            (row) => row.formula.trim() && row.penanggung_jawab.trim() && row.target_triwulan.every((target) => target.target_text.trim()),
        ).length,
);
const completion = computed(() => (form.items.length ? Math.round((completedRows.value / form.items.length) * 100) : 0));
const saveMatrix = () => form.put(route('rencana-aksi.matrix.update', { rencana_aksi: props.item.id }), { preserveScroll: true });
const exportReport = (format: 'pdf' | 'word') =>
    router.post(route('rencana-aksi.export', { rencana_aksi: props.item.id }), { format }, { preserveScroll: true });
const fieldError = (key: string) => (form.errors as Record<string, string | undefined>)[key];

const targetLabel = (row: ItemRow) => row.target_text || row.target || '-';
const money = (value?: string | number | null) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value || 0));
const levelLabel = (level?: string | null) =>
    ({ tujuan_opd: 'Tujuan', sasaran_opd: 'Sasaran Strategis', program_opd: 'Program', kegiatan_opd: 'Kegiatan', sub_kegiatan_opd: 'Sub Kegiatan' })[
        level || ''
    ] || 'Item lama';
const levelClass = (level?: string | null) =>
    ({
        tujuan_opd: 'bg-blue-950 text-white',
        sasaran_opd: 'bg-blue-100 text-blue-900',
        program_opd: 'bg-cyan-50 text-cyan-900',
        kegiatan_opd: 'bg-slate-100 text-slate-800',
        sub_kegiatan_opd: 'bg-white text-slate-700',
    })[level || ''] || 'bg-slate-100 text-slate-700';
const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Perlu Perbaikan',
        verified: 'Terverifikasi',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] || status;
</script>

<template>
    <Head :title="item.judul" />
    <main class="mx-auto flex w-full max-w-[1600px] flex-col gap-5 p-4 sm:p-6">
        <header
            class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-950 via-blue-900 to-blue-800 px-5 py-6 text-white shadow-sm sm:px-7"
        >
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wider text-blue-200">
                        <span>Rencana Aksi OPD</span><span class="size-1 rounded-full bg-blue-300"></span><span>{{ item.tahun }}</span>
                    </div>
                    <h1 class="max-w-4xl text-2xl font-bold tracking-tight">{{ item.judul }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-blue-100">
                        <span>{{ item.opd?.singkatan || item.opd?.nama || '-' }}</span
                        ><span class="rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold text-white">{{ statusLabel(item.status) }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="can.export"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-white/25 px-3 text-sm font-semibold hover:bg-white/10"
                        @click="exportReport('pdf')"
                    >
                        <FileDown class="size-4" /> PDF
                    </button>
                    <button
                        v-if="can.export"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-white/25 px-3 text-sm font-semibold hover:bg-white/10"
                        @click="exportReport('word')"
                    >
                        <FileDown class="size-4" /> Word
                    </button>
                    <Link
                        v-if="can.manage"
                        :href="route('rencana-aksi.edit', { rencana_aksi: item.id })"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-blue-950 hover:bg-blue-50"
                        ><Edit3 class="size-4" /> Edit identitas</Link
                    >
                    <WorkflowActionButtons
                        module="rencana_aksi"
                        :model-id="item.id"
                        :status="item.status"
                        :can-manage="can.manage"
                        :can-review="can.review"
                        :can-lock="can.lock"
                    />
                </div>
            </div>
        </header>

        <section v-if="isMatrix" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    <Database class="size-4" /> PK Kepala OPD
                </div>
                <p class="mt-2 text-sm font-semibold leading-5">{{ item.perjanjian_kinerja?.judul || '-' }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">RENSTRA sumber</div>
                <p class="mt-2 text-sm font-semibold leading-5">{{ item.renstra_opd?.judul || '-' }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Anggaran resmi</div>
                <p class="mt-2 text-sm font-semibold leading-5">{{ item.dpa_opd ? `${item.dpa_opd.label} — ${item.dpa_opd.judul}` : '-' }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kelengkapan isian</span
                    ><strong class="text-blue-900">{{ completion }}%</strong>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-blue-800 transition-all" :style="{ width: `${completion}%` }"></div>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">{{ completedRows }}/{{ form.items.length }} baris lengkap</p>
            </div>
        </section>

        <form v-if="isMatrix" class="flex flex-col gap-4" @submit.prevent="saveMatrix">
            <section class="rounded-xl border border-blue-200 bg-blue-50/60 px-4 py-3 text-sm text-blue-950">
                <div class="flex gap-3">
                    <Info class="mt-0.5 size-5 shrink-0" />
                    <p>
                        <strong>Formula diisi otomatis dari RENSTRA dan dapat disesuaikan di sini.</strong> Satuan, target tahunan, serta anggaran tetap
                        mengikuti snapshot sumber resmi. Lengkapi formula yang kosong, target Triwulan I–IV, dan penanggung jawab pada setiap baris.
                    </p>
                </div>
            </section>
            <InputError :message="form.errors.items" />

            <section class="hidden overflow-hidden rounded-xl border bg-card shadow-sm lg:block">
                <div class="max-h-[68vh] overflow-auto">
                    <table class="min-w-[1550px] table-fixed border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-20 bg-blue-950 text-white">
                            <tr>
                                <th rowspan="2" class="w-14 border-r border-blue-800 px-3 py-3 text-center">No</th>
                                <th rowspan="2" class="w-[310px] border-r border-blue-800 px-3 py-3">
                                    Tujuan / Sasaran Strategis / Program / Kegiatan / Sub Kegiatan
                                </th>
                                <th rowspan="2" class="w-[260px] border-r border-blue-800 px-3 py-3">Indikator Kinerja</th>
                                <th rowspan="2" class="w-[220px] border-r border-blue-800 px-3 py-3">Formula</th>
                                <th rowspan="2" class="w-28 border-r border-blue-800 px-3 py-3">Satuan</th>
                                <th rowspan="2" class="w-28 border-r border-blue-800 px-3 py-3">Target</th>
                                <th rowspan="2" class="w-36 border-r border-blue-800 px-3 py-3">Anggaran (Rp)</th>
                                <th colspan="4" class="border-r border-blue-800 px-3 py-2 text-center">Target Pelaksanaan per Triwulan</th>
                                <th rowspan="2" class="w-56 px-3 py-3">Penanggung Jawab</th>
                            </tr>
                            <tr>
                                <th
                                    v-for="quarter in ['I', 'II', 'III', 'IV']"
                                    :key="quarter"
                                    class="w-28 border-r border-t border-blue-800 px-3 py-2 text-center"
                                >
                                    {{ quarter }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in item.items"
                                :key="row.id"
                                class="border-b align-top last:border-0"
                                :class="levelClass(row.level)"
                            >
                                <td class="border-r px-3 py-3 text-center font-semibold">{{ index + 1 }}</td>
                                <td class="border-r px-3 py-3">
                                    <span
                                        class="mb-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                        :class="row.level === 'tujuan_opd' ? 'bg-white/20' : 'bg-blue-950/10 text-current'"
                                        >{{ levelLabel(row.level) }}</span
                                    >
                                    <div class="font-semibold leading-5">
                                        <span v-if="row.kode_snapshot">{{ row.kode_snapshot }} — </span>{{ row.uraian_snapshot || row.aksi }}
                                    </div>
                                </td>
                                <td class="border-r px-3 py-3 leading-5">{{ row.indikator || '-' }}</td>
                                <td class="border-r bg-sky-50/80 px-2 py-2 text-slate-900">
                                    <textarea
                                        v-model="form.items[index].formula"
                                        :disabled="!editable"
                                        :aria-label="`Formula ${row.indikator}`"
                                        rows="3"
                                        class="min-h-20 w-full resize-y rounded-md border border-sky-300 bg-white px-2.5 py-2 text-xs leading-5 focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:resize-none disabled:bg-slate-100 disabled:text-slate-600"
                                        placeholder="Lengkapi formula perhitungan"
                                    ></textarea>
                                    <InputError :message="fieldError(`items.${index}.formula`)" />
                                </td>
                                <td class="border-r px-3 py-3">{{ row.satuan_snapshot || '-' }}</td>
                                <td class="border-r px-3 py-3 font-semibold">{{ targetLabel(row) }}</td>
                                <td class="border-r px-3 py-3 text-right tabular-nums">{{ money(row.anggaran) }}</td>
                                <td
                                    v-for="(target, targetIndex) in form.items[index].target_triwulan"
                                    :key="target.triwulan"
                                    class="border-r bg-amber-50/80 px-2 py-2 text-slate-900"
                                >
                                    <input
                                        v-model="target.target_text"
                                        :disabled="!editable"
                                        :aria-label="`Target Triwulan ${target.triwulan} untuk ${row.indikator}`"
                                        class="min-h-10 w-full rounded-md border border-amber-300 bg-white px-2 text-xs focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100 disabled:text-slate-600"
                                        placeholder="Wajib diisi"
                                    />
                                    <InputError :message="fieldError(`items.${index}.target_triwulan.${targetIndex}.target_text`)" />
                                </td>
                                <td class="bg-blue-50/80 px-2 py-2 text-slate-900">
                                    <input
                                        v-model="form.items[index].penanggung_jawab"
                                        :disabled="!editable"
                                        :aria-label="`Penanggung jawab ${row.indikator}`"
                                        class="min-h-10 w-full rounded-md border border-blue-300 bg-white px-2 text-xs focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100 disabled:text-slate-600"
                                        placeholder="Bidang/Bagian/Pejabat"
                                    /><InputError :message="fieldError(`items.${index}.penanggung_jawab`)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-4 lg:hidden">
                <article v-for="(row, index) in item.items" :key="row.id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="px-4 py-3" :class="levelClass(row.level)">
                        <div class="text-[10px] font-bold uppercase tracking-wide">{{ index + 1 }}. {{ levelLabel(row.level) }}</div>
                        <div class="mt-1 text-sm font-semibold leading-5">
                            <span v-if="row.kode_snapshot">{{ row.kode_snapshot }} — </span>{{ row.uraian_snapshot || row.aksi }}
                        </div>
                    </div>
                    <dl class="grid gap-3 border-b p-4 text-sm">
                        <div>
                            <dt class="text-xs font-semibold text-muted-foreground">Indikator</dt>
                            <dd class="mt-1">{{ row.indikator || '-' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <dt class="text-xs font-semibold text-muted-foreground">Satuan</dt>
                                <dd class="mt-1">{{ row.satuan_snapshot || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-muted-foreground">Target</dt>
                                <dd class="mt-1 font-semibold">{{ targetLabel(row) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-muted-foreground">Anggaran</dt>
                                <dd class="mt-1 font-semibold">Rp {{ money(row.anggaran) }}</dd>
                            </div>
                        </div>
                    </dl>
                    <div class="grid grid-cols-2 gap-3 bg-amber-50/60 p-4">
                        <label class="col-span-2 grid gap-1 text-xs font-semibold"
                            >Formula
                            <span class="font-normal text-muted-foreground">Nilai awal diambil dari RENSTRA dan dapat disesuaikan.</span>
                            <textarea
                                v-model="form.items[index].formula"
                                :disabled="!editable"
                                rows="3"
                                class="min-h-24 resize-y rounded-md border border-sky-300 bg-white px-3 py-2 text-sm font-normal leading-6 focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:resize-none disabled:bg-slate-100"
                                placeholder="Lengkapi formula perhitungan"
                            ></textarea>
                            <InputError :message="fieldError(`items.${index}.formula`)" />
                        </label>
                        <label v-for="target in form.items[index].target_triwulan" :key="target.triwulan" class="grid gap-1 text-xs font-semibold"
                            >Triwulan {{ ['I', 'II', 'III', 'IV'][target.triwulan - 1]
                            }}<input
                                v-model="target.target_text"
                                :disabled="!editable"
                                class="min-h-11 rounded-md border border-amber-300 bg-white px-3 text-sm font-normal disabled:bg-slate-100"
                                placeholder="Wajib diisi" /></label
                        ><label class="col-span-2 grid gap-1 text-xs font-semibold"
                            >Penanggung Jawab<input
                                v-model="form.items[index].penanggung_jawab"
                                :disabled="!editable"
                                class="min-h-11 rounded-md border border-blue-300 bg-white px-3 text-sm font-normal disabled:bg-slate-100"
                                placeholder="Bidang/Bagian/Pejabat"
                        /></label>
                    </div>
                </article>
            </section>

            <div v-if="editable" class="sticky bottom-4 z-10 flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-blue-800 px-5 text-sm font-semibold text-white shadow-lg hover:bg-blue-900 disabled:opacity-50"
                >
                    <Save class="size-4" /> {{ form.processing ? 'Menyimpan…' : 'Simpan Isian Matriks' }}
                </button>
            </div>
            <div v-else class="flex items-center gap-2 rounded-xl border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                <CheckCircle2 class="size-4" /> Isian tidak dapat diubah pada status {{ statusLabel(item.status) }}.
            </div>
        </form>

        <section v-else class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b px-4 py-3">
                <h2 class="font-semibold">Item Rencana Aksi versi lama</h2>
                <p class="mt-1 text-sm text-muted-foreground">Data lama tetap ditampilkan tanpa perubahan struktur.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Aksi</th>
                            <th class="px-4 py-3">Indikator</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3">Anggaran</th>
                            <th class="px-4 py-3">Penanggung Jawab</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in item.items" :key="row.id" class="border-t">
                            <td class="px-4 py-3 font-medium">{{ row.aksi }}</td>
                            <td class="px-4 py-3">{{ row.indikator || '-' }}</td>
                            <td class="px-4 py-3">{{ targetLabel(row) }}</td>
                            <td class="px-4 py-3">Rp {{ money(row.anggaran) }}</td>
                            <td class="px-4 py-3">{{ row.penanggung_jawab || '-' }}</td>
                        </tr>
                        <tr v-if="!item.items.length">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada item.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <WorkflowHistoryTimeline :workflow="workflow" />
    </main>
</template>
