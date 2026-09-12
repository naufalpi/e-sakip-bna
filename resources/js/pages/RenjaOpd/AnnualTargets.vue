<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, LockKeyhole, RotateCcw, Save, Search, Target, TriangleAlert } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

type HierarchyItem = { level: string; kode?: string | null; label: string };
type AnnualTarget = {
    id: number;
    level: 'tujuan_opd' | 'sasaran_opd' | 'program_opd' | 'kegiatan_opd';
    kode?: string | null;
    uraian: string;
    indikator: string;
    satuan?: string | null;
    formula?: string | null;
    hierarchy: HierarchyItem[];
    target_renstra?: string | null;
    target_renja?: string | null;
    is_adjusted: boolean;
    alasan_penyesuaian?: string | null;
    bootstrap_source: string;
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
type EditableTarget = { target_text: string; alasan_penyesuaian: string };

const props = defineProps<{
    renja: {
        id: number;
        judul: string;
        tahun: number;
        status: string;
        version_label: string;
        opd?: { nama: string; singkatan?: string | null } | null;
    };
    items: Paginator<AnnualTarget>;
    summary: { total: number; complete: number; missing: number; adjusted: number; following: number };
    levelCounts: Record<string, number>;
    filters: { search?: string | null; level?: string | null; status?: string | null };
    can: { manage: boolean };
}>();

const filters = reactive({
    search: props.filters.search ?? '',
    level: props.filters.level ?? '',
    status: props.filters.status ?? '',
});
const editingIds = ref(new Set<number>());
const initialValues = ref<Record<string, EditableTarget>>({});

const editableRows = () =>
    Object.fromEntries(
        props.items.data.map((row) => [
            String(row.id),
            {
                target_text: row.target_renja ?? '',
                alasan_penyesuaian: row.alasan_penyesuaian ?? '',
            },
        ]),
    );

const form = useForm<{ targets: Record<string, EditableTarget> }>({ targets: editableRows() });

const hydrate = () => {
    const values = editableRows();
    form.targets = structuredClone(values);
    initialValues.value = structuredClone(values);
    editingIds.value = new Set(props.items.data.filter((row) => row.is_adjusted || !row.target_renja).map((row) => row.id));
};

watch(() => props.items.data, hydrate);
hydrate();

const normalized = (value?: string | null) => (value ?? '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('id-ID');
const isCurrentlyAdjusted = (row: AnnualTarget) => normalized(form.targets[String(row.id)]?.target_text) !== normalized(row.target_renstra);
const unsavedCount = computed(
    () =>
        Object.entries(form.targets).filter(([id, value]) => {
            const initial = initialValues.value[id];
            return (
                normalized(value.target_text) !== normalized(initial?.target_text) ||
                normalized(value.alasan_penyesuaian) !== normalized(initial?.alasan_penyesuaian)
            );
        }).length,
);

const levelOptions = [
    { value: '', label: 'Semua' },
    { value: 'tujuan_opd', label: 'Tujuan' },
    { value: 'sasaran_opd', label: 'Sasaran Strategis' },
    { value: 'program_opd', label: 'Program' },
    { value: 'kegiatan_opd', label: 'Kegiatan' },
];

const levelLabel = (level: AnnualTarget['level']) =>
    ({ tujuan_opd: 'Tujuan OPD', sasaran_opd: 'Sasaran Strategis', program_opd: 'Program OPD', kegiatan_opd: 'Kegiatan OPD' })[level];

const applyFilters = () => {
    if (unsavedCount.value > 0 && !window.confirm('Perubahan target belum disimpan. Tetap berpindah tampilan?')) return;
    router.get(route('renja-opd.annual-targets.index', { renja_opd: props.renja.id }), filters, { preserveState: false, replace: true });
};

const selectLevel = (level: string) => {
    filters.level = level;
    applyFilters();
};

const openEditor = (id: number) => {
    editingIds.value = new Set(editingIds.value).add(id);
};

const restoreRenstra = (row: AnnualTarget) => {
    const target = form.targets[String(row.id)];
    if (!target) return;
    target.target_text = row.target_renstra ?? '';
    target.alasan_penyesuaian = '';
    openEditor(row.id);
};

const rowIndex = (id: number) => Object.keys(form.targets).indexOf(String(id));
const fieldError = (id: number, field: keyof EditableTarget) => {
    const index = rowIndex(id);
    return index >= 0 ? (form.errors[`targets.${index}.${field}` as keyof typeof form.errors] as string | undefined) : undefined;
};

const submit = () => {
    form.transform((data) => ({
        targets: Object.entries(data.targets).map(([id, value]) => ({ id: Number(id), ...value })),
    })).put(route('renja-opd.annual-targets.update', { renja_opd: props.renja.id }), {
        preserveScroll: true,
        onSuccess: () => {
            initialValues.value = structuredClone(form.targets);
            editingIds.value = new Set(props.items.data.filter((row) => row.is_adjusted || !row.target_renja).map((row) => row.id));
        },
    });
};

const beforeUnload = (event: BeforeUnloadEvent) => {
    if (unsavedCount.value === 0) return;
    event.preventDefault();
    event.returnValue = '';
};
onMounted(() => window.addEventListener('beforeunload', beforeUnload));
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
    <Head :title="`Target Kinerja Tahunan RENJA ${renja.tahun}`" />

    <div class="flex flex-col gap-5 p-4">
        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fbff,#edf7ff)] px-5 py-5">
                <Link
                    :href="route('renja-opd.show', { renja_opd: renja.id })"
                    class="inline-flex min-h-10 items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Kembali ke RENJA
                </Link>
                <div class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold tracking-normal">Target Kinerja Tahunan {{ renja.tahun }}</h1>
                            <span class="rounded-full border border-[#00336C]/20 bg-white px-2.5 py-1 text-xs font-semibold text-[#00336C]">
                                {{ renja.version_label }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ renja.opd?.nama }} · Target awal mengikuti RENSTRA dan menjadi dasar target PK Kepala OPD.
                        </p>
                    </div>
                    <div
                        v-if="!can.manage"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg border bg-white px-3 text-sm font-semibold text-slate-600"
                    >
                        <LockKeyhole class="size-4" /> Dokumen hanya dapat dilihat
                    </div>
                </div>
            </div>

            <div class="grid divide-y bg-white sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <div class="px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kelengkapan</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ summary.complete }}/{{ summary.total }}</p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Mengikuti RENSTRA</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ summary.following }}</p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Disesuaikan</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-amber-700">{{ summary.adjusted }}</p>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Belum Diisi</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums" :class="summary.missing ? 'text-red-600' : 'text-emerald-700'">
                        {{ summary.missing }}
                    </p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="border-b px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="bg-[#00336C]/8 grid size-10 shrink-0 place-items-center rounded-xl text-[#00336C]">
                        <Target class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-semibold text-slate-950">Matriks Target Tahunan</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Sesuaikan hanya bila target tahun berjalan berbeda. Nama indikator, satuan, dan formula tetap mengikuti RENSTRA.
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-b px-5 py-4">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter level target">
                    <button
                        v-for="option in levelOptions"
                        :key="option.value || 'all'"
                        type="button"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C] focus-visible:ring-offset-2"
                        :class="
                            filters.level === option.value
                                ? 'border-[#00336C] bg-[#00336C] text-white'
                                : 'bg-white text-slate-600 hover:border-[#00336C]/35 hover:text-[#00336C]'
                        "
                        @click="selectLevel(option.value)"
                    >
                        {{ option.label }}
                        <span class="rounded-md px-1.5 py-0.5 text-xs" :class="filters.level === option.value ? 'bg-white/15' : 'bg-slate-100'">
                            {{ option.value ? (levelCounts[option.value] ?? 0) : summary.total }}
                        </span>
                    </button>
                </div>

                <form class="mt-4 grid gap-3 md:grid-cols-[minmax(260px,1fr)_220px_auto]" @submit.prevent="applyFilters">
                    <label class="relative block">
                        <span class="sr-only">Cari indikator</span>
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="filters.search"
                            type="search"
                            class="h-11 w-full rounded-lg border-slate-200 pl-10 text-sm focus:border-[#00336C] focus:ring-[#00336C]"
                            placeholder="Cari kode, sasaran, atau indikator..."
                        />
                    </label>
                    <label>
                        <span class="sr-only">Filter status target</span>
                        <select
                            v-model="filters.status"
                            class="h-11 w-full rounded-lg border-slate-200 text-sm focus:border-[#00336C] focus:ring-[#00336C]"
                        >
                            <option value="">Semua status</option>
                            <option value="following">Mengikuti RENSTRA</option>
                            <option value="adjusted">Disesuaikan</option>
                            <option value="missing">Belum diisi</option>
                            <option value="complete">Sudah lengkap</option>
                        </select>
                    </label>
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg border bg-slate-50 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Terapkan
                    </button>
                </form>
            </div>

            <div v-if="items.data.length" class="divide-y">
                <article v-for="row in items.data" :key="row.id" class="px-5 py-5">
                    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_160px_220px_150px] xl:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#00336C]">{{ levelLabel(row.level) }}</span>
                                <span v-if="row.kode" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{
                                    row.kode
                                }}</span>
                            </div>
                            <p class="mt-2 font-semibold leading-6 text-slate-950">{{ row.indikator }}</p>
                            <p class="mt-1 text-sm leading-5 text-slate-500">{{ row.uraian }}</p>
                            <details v-if="row.hierarchy.length > 1" class="mt-2 text-xs text-slate-500">
                                <summary class="cursor-pointer font-medium text-[#00336C]">Lihat posisi cascading</summary>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 leading-5">
                                    <template v-for="(item, index) in row.hierarchy" :key="`${row.id}-${index}`">
                                        <span v-if="index" aria-hidden="true">›</span>
                                        <span>{{ item.kode ? `${item.kode} · ` : '' }}{{ item.label }}</span>
                                    </template>
                                </div>
                            </details>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Satuan</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ row.satuan || '-' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Target RENSTRA {{ renja.tahun }}</p>
                            <p class="mt-2 text-sm font-semibold tabular-nums text-slate-800">{{ row.target_renstra || 'Belum diisi' }}</p>
                        </div>

                        <div class="xl:text-right">
                            <span
                                class="inline-flex min-h-8 items-center gap-1.5 rounded-full px-2.5 text-xs font-semibold"
                                :class="
                                    !row.target_renja
                                        ? 'bg-red-50 text-red-700'
                                        : row.is_adjusted
                                          ? 'bg-amber-50 text-amber-800'
                                          : 'bg-emerald-50 text-emerald-700'
                                "
                            >
                                <TriangleAlert v-if="!row.target_renja" class="size-3.5" />
                                <CheckCircle2 v-else class="size-3.5" />
                                {{ !row.target_renja ? 'Belum diisi' : row.is_adjusted ? 'Disesuaikan' : 'Mengikuti RENSTRA' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-dashed pt-4">
                        <div
                            v-if="can.manage && editingIds.has(row.id)"
                            class="grid gap-4 lg:grid-cols-[minmax(220px,360px)_minmax(300px,1fr)_auto] lg:items-start"
                        >
                            <label class="grid gap-1.5">
                                <span class="text-sm font-semibold text-slate-700"
                                    >Target RENJA {{ renja.tahun }} <span class="text-red-500">*</span></span
                                >
                                <input
                                    v-model="form.targets[String(row.id)].target_text"
                                    type="text"
                                    class="h-11 rounded-lg border-slate-200 text-sm focus:border-[#00336C] focus:ring-[#00336C]"
                                    placeholder="Contoh: 95 atau NA"
                                />
                                <span v-if="fieldError(row.id, 'target_text')" class="text-xs text-red-600">{{
                                    fieldError(row.id, 'target_text')
                                }}</span>
                            </label>

                            <label v-if="isCurrentlyAdjusted(row)" class="grid gap-1.5">
                                <span class="text-sm font-semibold text-slate-700">Alasan penyesuaian <span class="text-red-500">*</span></span>
                                <textarea
                                    v-model="form.targets[String(row.id)].alasan_penyesuaian"
                                    rows="2"
                                    class="min-h-11 rounded-lg border-slate-200 text-sm focus:border-[#00336C] focus:ring-[#00336C]"
                                    placeholder="Jelaskan dasar perubahan target tahun berjalan..."
                                ></textarea>
                                <span v-if="fieldError(row.id, 'alasan_penyesuaian')" class="text-xs text-red-600">{{
                                    fieldError(row.id, 'alasan_penyesuaian')
                                }}</span>
                            </label>

                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 lg:mt-[26px]"
                                @click="restoreRenstra(row)"
                            >
                                <RotateCcw class="size-4" /> Gunakan RENSTRA
                            </button>
                        </div>

                        <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Target RENJA {{ renja.tahun }}</p>
                                <p class="mt-1 font-semibold tabular-nums text-slate-950">
                                    {{ row.target_renja || 'Belum diisi' }}
                                    <span v-if="row.satuan" class="font-normal text-slate-500">{{ row.satuan }}</span>
                                </p>
                                <p v-if="row.is_adjusted && row.alasan_penyesuaian" class="mt-1 text-xs leading-5 text-amber-800">
                                    Alasan: {{ row.alasan_penyesuaian }}
                                </p>
                            </div>
                            <button
                                v-if="can.manage"
                                type="button"
                                class="inline-flex min-h-10 items-center justify-center rounded-lg border px-3 text-sm font-semibold text-[#00336C] hover:bg-sky-50"
                                @click="openEditor(row.id)"
                            >
                                Sesuaikan target
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="px-6 py-14 text-center">
                <Target class="mx-auto size-9 text-slate-300" />
                <p class="mt-3 font-semibold text-slate-800">Target tidak ditemukan</p>
                <p class="mt-1 text-sm text-muted-foreground">Ubah filter atau pastikan indikator telah tersedia pada RENSTRA.</p>
            </div>

            <div v-if="items.last_page > 1" class="flex items-center justify-between border-t bg-slate-50/70 px-5 py-4 text-sm">
                <span class="text-muted-foreground">Menampilkan {{ items.from }}–{{ items.to }} dari {{ items.total }} indikator</span>
                <div class="flex gap-2">
                    <Link
                        v-if="items.prev_page_url"
                        :href="items.prev_page_url"
                        class="inline-flex min-h-10 items-center rounded-lg border bg-white px-3 font-semibold"
                        >Sebelumnya</Link
                    >
                    <Link
                        v-if="items.next_page_url"
                        :href="items.next_page_url"
                        class="inline-flex min-h-10 items-center rounded-lg border bg-white px-3 font-semibold"
                        >Berikutnya</Link
                    >
                </div>
            </div>
        </section>

        <div
            v-if="can.manage"
            class="sticky bottom-4 z-20 flex flex-col gap-3 rounded-xl border bg-white/95 px-4 py-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm font-medium" :class="unsavedCount ? 'text-amber-800' : 'text-slate-500'">
                {{ unsavedCount ? `${unsavedCount} perubahan belum disimpan` : 'Semua perubahan pada halaman ini sudah tersimpan' }}
            </p>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002855] disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="form.processing || unsavedCount === 0"
                @click="submit"
            >
                <Save class="size-4" /> {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
            </button>
        </div>
    </div>
</template>
