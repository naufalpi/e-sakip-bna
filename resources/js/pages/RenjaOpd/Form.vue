<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Building2, CalendarDays, Check, FileText, GitBranch, Link2, Save, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Option = {
    id: number;
    label: string;
    tahun?: number;
    tahun_awal?: number;
    tahun_akhir?: number;
    opd_id?: number;
    jenis_versi?: 'awal' | 'ditetapkan' | 'perubahan';
    status?: string;
    is_active_version?: boolean;
};
type Renja = {
    id: number;
    rkpd_id?: number | null;
    renstra_opd_id?: number | null;
    opd_id: number;
    opd_unit_id?: number | null;
    periode_tahun_id: number;
    tahun: number;
    judul: string;
    nomor_dokumen?: string | null;
    status: string;
    jenis_versi: 'awal' | 'ditetapkan' | 'perubahan';
    version_label: string;
    catatan?: string | null;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    renja: Renja | null;
    rkpdOptions: Option[];
    renstraOptions: Option[];
    opdOptions: Option[];
    opdUnitOptions: Option[];
    periodeOptions: Option[];
}>();

const form = useForm({
    rkpd_id: props.renja?.rkpd_id ?? '',
    renstra_opd_id: props.renja?.renstra_opd_id ?? '',
    opd_id: props.renja?.opd_id ?? props.opdOptions[0]?.id ?? '',
    opd_unit_id: props.renja?.opd_unit_id ?? '',
    periode_tahun_id: props.renja?.periode_tahun_id ?? '',
    tahun: props.renja?.tahun ?? new Date().getFullYear(),
    judul: props.renja?.judul ?? '',
    nomor_dokumen: props.renja?.nomor_dokumen ?? '',
    status: props.renja?.status ?? 'draft',
    catatan: props.renja?.catatan ?? '',
});

watch(
    () => form.tahun,
    (value) => {
        const periode = props.periodeOptions.find((option) => Number(option.tahun) === Number(value));
        form.periode_tahun_id = periode?.id ?? '';
    },
    { immediate: true },
);

watch(
    () => form.opd_id,
    () => {
        if (form.opd_unit_id && !filteredUnitOptions.value.some((unit) => String(unit.id) === String(form.opd_unit_id))) {
            form.opd_unit_id = '';
        }

        if (form.renstra_opd_id && !filteredRenstraOptions.value.some((renstra) => String(renstra.id) === String(form.renstra_opd_id))) {
            form.renstra_opd_id = '';
        }
    },
);

watch(
    () => form.judul,
    (value) => {
        const upper = String(value || '').toUpperCase();
        if (value !== upper) {
            form.judul = upper;
        }
    },
);

watch(
    () => form.nomor_dokumen,
    (value) => {
        const upper = String(value || '').toUpperCase();
        if (value !== upper) {
            form.nomor_dokumen = upper;
        }
    },
);

const filteredUnitOptions = computed(() => props.opdUnitOptions.filter((unit) => String(unit.opd_id ?? '') === String(form.opd_id)));
const filteredRenstraOptions = computed(() =>
    props.renstraOptions.filter(
        (option) =>
            (!option.opd_id || String(option.opd_id) === String(form.opd_id)) &&
            (!option.tahun_awal || Number(option.tahun_awal) <= Number(form.tahun)) &&
            (!option.tahun_akhir || Number(option.tahun_akhir) >= Number(form.tahun)),
    ),
);
const expectedRkpdVersion = computed(() => (props.renja?.jenis_versi === 'perubahan' ? 'perubahan' : 'ditetapkan'));
const workingRkpdStatuses = ['draft', 'submitted', 'verified', 'revision', 'rejected'];
const filteredRkpdOptions = computed(() =>
    props.rkpdOptions.filter((option) => {
        const isCurrentLegacyReference = props.mode === 'edit' && String(option.id) === String(props.renja?.rkpd_id);
        const isWorkingReference =
            option.jenis_versi === ((props.renja?.jenis_versi ?? 'awal') === 'perubahan' ? 'perubahan' : 'awal') &&
            (!option.status || workingRkpdStatuses.includes(option.status));
        const isOfficialReference =
            (!option.jenis_versi || option.jenis_versi === expectedRkpdVersion.value) &&
            (!option.status || ['approved', 'locked'].includes(option.status)) &&
            option.is_active_version !== false;

        return (
            (!option.tahun || Number(option.tahun) === Number(form.tahun)) &&
            (isCurrentLegacyReference || isWorkingReference || isOfficialReference)
        );
    }),
);
const selectedPeriode = computed(() => props.periodeOptions.find((option) => Number(option.tahun) === Number(form.tahun)));
const selectedOpd = computed(() => props.opdOptions.find((option) => String(option.id) === String(form.opd_id)));
const selectedRenstra = computed(() => filteredRenstraOptions.value.find((option) => String(option.id) === String(form.renstra_opd_id)));
const frontendVersionLabel = computed(() =>
    props.renja?.jenis_versi === 'awal' ? 'RENJA Akhir Draft' : props.renja?.version_label || 'RENJA Akhir Draft',
);
const rkpdReferenceHelp = computed(() =>
    props.renja?.jenis_versi === 'perubahan'
        ? 'RENJA Perubahan dapat memakai RKPD Perubahan tahap kerja. Saat disetujui, sistem akan memastikan RKPD Perubahan Ditetapkan sudah resmi.'
        : 'RENJA Akhir Draft dapat memakai RKPD Awal tahap kerja. Saat disetujui, sistem akan mengikat RENJA Ditetapkan ke RKPD Ditetapkan yang resmi.',
);
const title = computed(() => (props.mode === 'create' ? 'Tambah RENJA Akhir Draft' : `Edit ${frontendVersionLabel.value}`));
const isCreateConfirmationOpen = ref(false);
const tahunOptions = computed(() =>
    props.periodeOptions
        .map((option) => ({ id: option.id, tahun: option.tahun, label: option.tahun ? String(option.tahun) : option.label }))
        .filter((option) => option.tahun),
);

watch(
    [() => form.opd_id, () => form.tahun],
    () => {
        if (props.mode !== 'create') {
            return;
        }

        const currentIsValid = filteredRenstraOptions.value.some((option) => String(option.id) === String(form.renstra_opd_id));
        if (!currentIsValid) {
            form.renstra_opd_id = filteredRenstraOptions.value[0]?.id ?? '';
        }
    },
    { immediate: true },
);

watch(
    () => form.tahun,
    () => {
        if (props.mode !== 'create') {
            return;
        }

        const currentIsValid = filteredRkpdOptions.value.some((option) => String(option.id) === String(form.rkpd_id));
        if (!currentIsValid) {
            form.rkpd_id = filteredRkpdOptions.value[0]?.id ?? '';
        }
    },
    { immediate: true },
);

const submit = () => {
    form.status = props.renja?.status ?? 'draft';
    form.judul = String(form.judul || '').toUpperCase();
    form.nomor_dokumen = String(form.nomor_dokumen || '').toUpperCase();
    form.periode_tahun_id = selectedPeriode.value?.id ?? '';

    if (props.mode === 'create') {
        isCreateConfirmationOpen.value = true;
        return;
    }

    form.put(route('renja-opd.update', props.renja?.id));
};

const confirmCreate = () => {
    isCreateConfirmationOpen.value = false;
    form.post(route('renja-opd.store'));
};
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-5 p-4 pb-24">
        <section class="rounded-2xl border bg-gradient-to-br from-white via-white to-[#00336C]/5 p-5 shadow-sm">
            <Link
                :href="route('renja-opd.index')"
                class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Kembali ke Renja
            </Link>
            <div class="mt-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-[#00336C]/15 bg-[#00336C]/5 px-3 py-1 text-xs font-semibold uppercase text-[#00336C]"
                    >
                        <CalendarDays class="size-3.5" />
                        Rencana kerja tahunan
                    </div>
                    <h1 class="mt-3 text-2xl font-semibold tracking-normal">{{ title }}</h1>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Pilih OPD, tahun RENJA, lalu hubungkan dengan RKPD pada tahap yang sama.
                    </p>
                </div>
                <div class="rounded-2xl border bg-white px-4 py-3 text-sm shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Versi dokumen</div>
                    <div class="mt-1 font-semibold text-[#00336C]">{{ frontendVersionLabel }}</div>
                </div>
            </div>
        </section>

        <form class="overflow-hidden rounded-2xl border bg-card shadow-sm" @submit.prevent="submit">
            <input v-model="form.periode_tahun_id" type="hidden" />
            <input v-model="form.status" type="hidden" />

            <div class="flex flex-col gap-2 border-b bg-muted/25 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Data Renja OPD</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Periode mengikuti Tahun Renja secara otomatis.</p>
                </div>
                <div class="inline-flex w-fit items-center gap-2 rounded-full border bg-background px-3 py-1.5 text-xs font-semibold text-[#00336C]">
                    <FileText class="size-3.5" />
                    {{ selectedPeriode?.label || 'Tahun belum tersedia di master periode' }}
                </div>
            </div>

            <div class="grid gap-5 p-5">
                <section class="grid gap-4 rounded-2xl border bg-background p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-[#00336C]/10 text-[#00336C]">
                            <Building2 class="size-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold">Perangkat Daerah</h3>
                            <p class="text-sm text-muted-foreground">{{ selectedOpd?.label || 'Pilih OPD pemilik Renja.' }}</p>
                        </div>
                    </div>

                    <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.65fr)]">
                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">OPD</span>
                            <select
                                v-model="form.opd_id"
                                class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                            >
                                <option value="">Pilih OPD</option>
                                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <span v-if="form.errors.opd_id" class="text-xs text-red-600">{{ form.errors.opd_id }}</span>
                        </label>

                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Unit OPD</span>
                            <select
                                v-model="form.opd_unit_id"
                                class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                            >
                                <option value="">Tidak memilih unit</option>
                                <option v-for="option in filteredUnitOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <span v-if="form.errors.opd_unit_id" class="text-xs text-red-600">{{ form.errors.opd_unit_id }}</span>
                        </label>
                    </div>
                </section>

                <section class="grid gap-4 rounded-2xl border bg-background p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-sky-50 text-[#00336C]">
                            <Link2 class="size-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold">Acuan Perencanaan</h3>
                            <p class="text-sm text-muted-foreground">Pilih dokumen acuan jika sudah tersedia.</p>
                        </div>
                    </div>

                    <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Acuan RKPD</span>
                            <select
                                v-model="form.rkpd_id"
                                required
                                class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                            >
                                <option value="">Pilih RKPD acuan</option>
                                <option v-for="option in filteredRkpdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <span class="text-xs leading-5 text-muted-foreground">{{ rkpdReferenceHelp }}</span>
                            <span v-if="form.errors.rkpd_id" class="text-xs text-red-600">{{ form.errors.rkpd_id }}</span>
                        </label>

                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Renstra OPD Acuan</span>
                            <select
                                v-model="form.renstra_opd_id"
                                required
                                class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                            >
                                <option value="">RENSTRA resmi belum tersedia</option>
                                <option v-for="option in filteredRenstraOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <span v-if="mode === 'create' && !form.renstra_opd_id" class="text-xs leading-5 text-amber-700 dark:text-amber-300"
                                >RENJA hanya dapat dibuat dari RENSTRA aktif yang sudah disetujui atau dikunci.</span
                            >
                            <span v-if="mode === 'create' && form.renstra_opd_id" class="text-xs leading-5 text-muted-foreground"
                                >Sub kegiatan RENSTRA akan disalin satu kali saat RENJA dibuat. Program dan kegiatan yang sama akan dikelompokkan
                                otomatis.</span
                            >
                            <span v-if="form.errors.renstra_opd_id" class="text-xs text-red-600">{{ form.errors.renstra_opd_id }}</span>
                        </label>
                    </div>
                </section>

                <section class="grid gap-4 rounded-2xl border bg-background p-4">
                    <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Tahun Renja</span>
                            <select
                                v-model="form.tahun"
                                class="h-11 w-full rounded-xl border bg-background px-3 text-sm font-semibold outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                            >
                                <option v-for="option in tahunOptions" :key="option.id" :value="option.tahun">{{ option.label }}</option>
                            </select>
                            <span v-if="form.errors.tahun" class="text-xs text-red-600">{{ form.errors.tahun }}</span>
                            <span v-if="form.errors.periode_tahun_id" class="text-xs text-red-600">{{ form.errors.periode_tahun_id }}</span>
                        </label>

                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Judul Renja</span>
                            <input
                                v-model="form.judul"
                                type="text"
                                class="h-11 w-full rounded-xl border bg-background px-3 text-sm uppercase outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                                placeholder="RENJA OPD TAHUN ..."
                            />
                            <span v-if="form.errors.judul" class="text-xs text-red-600">{{ form.errors.judul }}</span>
                        </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(260px,0.55fr)_minmax(0,1fr)]">
                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Nomor Dokumen</span>
                            <input
                                v-model="form.nomor_dokumen"
                                type="text"
                                class="h-11 w-full rounded-xl border bg-background px-3 text-sm uppercase outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                                placeholder="NOMOR DOKUMEN"
                            />
                        </label>

                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Catatan</span>
                            <textarea
                                v-model="form.catatan"
                                rows="3"
                                class="rounded-xl border bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"
                            ></textarea>
                        </label>
                    </div>
                </section>
            </div>

            <div class="sticky bottom-0 flex justify-end gap-2 border-t bg-card/95 px-5 py-4 backdrop-blur supports-[backdrop-filter]:bg-card/80">
                <Link
                    :href="route('renja-opd.index')"
                    class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-medium hover:bg-muted"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855] disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Save class="size-4" />
                    {{ mode === 'create' ? 'Buat RENJA Akhir Draft' : 'Simpan Perubahan' }}
                </button>
            </div>
        </form>

        <Teleport to="body">
            <div
                v-if="isCreateConfirmationOpen"
                class="fixed inset-0 z-[100] grid place-items-center overflow-y-auto bg-slate-950/55 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="create-renja-title"
                @click.self="isCreateConfirmationOpen = false"
            >
                <section
                    class="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/40 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-950"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-5 dark:border-slate-800 sm:px-6">
                        <div class="flex min-w-0 items-start gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-[#00336C] text-white shadow-lg shadow-blue-950/15">
                                <GitBranch class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">
                                    Persiapan data tahunan
                                </p>
                                <h2 id="create-renja-title" class="mt-1 text-xl font-bold text-slate-950 dark:text-white">Buat RENJA Akhir Draft?</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {{
                                        selectedRenstra
                                            ? `Sub kegiatan dari RENSTRA akan disalin satu kali sebagai struktur awal RENJA tahun ${form.tahun}.`
                                            : 'RENJA akan dibuat tanpa salinan awal karena belum ada RENSTRA acuan yang dipilih.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white"
                            aria-label="Tutup"
                            @click="isCreateConfirmationOpen = false"
                        >
                            <X class="size-5" />
                        </button>
                    </header>

                    <div class="px-5 py-5 sm:px-6">
                        <div class="rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/35">
                            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-700 dark:text-blue-300">Acuan RENSTRA</p>
                            <p class="mt-1 text-sm font-semibold leading-6 text-slate-900 dark:text-white">
                                {{ selectedRenstra?.label || 'Belum ada RENSTRA yang dipilih' }}
                            </p>
                        </div>

                        <ul v-if="selectedRenstra" class="mt-5 grid gap-3 text-sm leading-6 text-slate-700 dark:text-slate-200">
                            <li class="flex gap-3">
                                <Check class="mt-1 size-4 shrink-0 text-emerald-600" /><span
                                    >Program dan kegiatan akan dikelompokkan; setiap sub kegiatan hanya disalin satu kali.</span
                                >
                            </li>
                            <li class="flex gap-3">
                                <Check class="mt-1 size-4 shrink-0 text-emerald-600" /><span
                                    >Identitas struktur, indikator, dan target akhir RENSTRA menjadi acuan terkunci.</span
                                >
                            </li>
                            <li class="flex gap-3">
                                <Check class="mt-1 size-4 shrink-0 text-emerald-600" /><span
                                    >Setelah dibuat, hapus sub kegiatan yang tidak dilaksanakan tahun ini lalu lengkapi target tahunan, pagu, lokasi,
                                    sumber dana, dan prioritas. Tambah manual tetap tersedia.</span
                                >
                            </li>
                        </ul>
                        <p
                            v-else
                            class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                        >
                            Anda tetap dapat menambahkan sub kegiatan secara manual setelah dokumen dibuat.
                        </p>
                    </div>

                    <footer
                        class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/70 sm:flex-row sm:justify-end sm:px-6"
                    >
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="isCreateConfirmationOpen = false"
                        >
                            Periksa kembali
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white transition hover:bg-[#002855] disabled:opacity-60"
                            :disabled="form.processing"
                            @click="confirmCreate"
                        >
                            <GitBranch class="size-4" />
                            Buat RENJA Akhir Draft
                        </button>
                    </footer>
                </section>
            </div>
        </Teleport>
    </div>
</template>
