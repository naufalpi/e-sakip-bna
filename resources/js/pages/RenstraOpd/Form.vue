<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, CheckCircle2, ClipboardList, FileText, GitBranch, Info, Save } from 'lucide-vue-next';
import { computed } from 'vue';

type Option = { id: number; label: string };

type RenstraForm = {
    opd_id: number | string | null;
    rpjmd_id: number | string | null;
    periode_tahun_id: number | string | null;
    judul: string;
    nomor_dokumen: string;
    tahun_awal: number | string;
    tahun_akhir: number | string;
    status: string;
    keterangan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    renstra: (RenstraForm & { id: number }) | null;
    opdOptions: Option[];
    rpjmdOptions: Option[];
    periodeOptions: Option[];
}>();

const form = useForm<RenstraForm>({
    opd_id: props.renstra?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id : ''),
    rpjmd_id: props.renstra?.rpjmd_id ?? '',
    periode_tahun_id: props.renstra?.periode_tahun_id ?? '',
    judul: props.renstra?.judul ?? '',
    nomor_dokumen: props.renstra?.nomor_dokumen ?? '',
    tahun_awal: props.renstra?.tahun_awal ?? new Date().getFullYear(),
    tahun_akhir: props.renstra?.tahun_akhir ?? new Date().getFullYear() + 5,
    status: props.renstra?.status ?? 'draft',
    keterangan: props.renstra?.keterangan ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('renstra-opd.store'));
        return;
    }

    form.put(route('renstra-opd.update', props.renstra?.id));
};

const pageTitle = computed(() => (props.mode === 'create' ? 'Tambah Renstra OPD' : 'Edit Renstra OPD'));
const submitLabel = computed(() => (props.mode === 'create' ? 'Simpan Renstra' : 'Simpan Perubahan'));
const selectedOpd = computed(() => props.opdOptions.find((option) => String(option.id) === String(form.opd_id)));
const selectedRpjmd = computed(() => props.rpjmdOptions.find((option) => String(option.id) === String(form.rpjmd_id)));
const selectedPeriode = computed(() => props.periodeOptions.find((option) => String(option.id) === String(form.periode_tahun_id)));
const errorCount = computed(() => Object.values(form.errors).filter(Boolean).length);

const yearRangeLabel = computed(() => {
    const start = Number(form.tahun_awal);
    const end = Number(form.tahun_akhir);

    if (!start || !end) {
        return 'Tahun belum lengkap';
    }

    if (end < start) {
        return 'Rentang tahun belum valid';
    }

    return `${start}-${end} (${end - start + 1} tahun)`;
});

const readinessItems = computed(() => [
    {
        label: 'OPD',
        value: selectedOpd.value?.label ?? 'Belum dipilih',
        complete: Boolean(form.opd_id),
    },
    {
        label: 'RPJMD',
        value: selectedRpjmd.value?.label ?? 'Belum dipilih',
        complete: Boolean(form.rpjmd_id),
    },
    {
        label: 'Periode',
        value: selectedPeriode.value?.label ?? yearRangeLabel.value,
        complete: Boolean(form.tahun_awal && form.tahun_akhir),
    },
    {
        label: 'Judul',
        value: form.judul || 'Belum diisi',
        complete: Boolean(form.judul),
    },
]);

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
        draft: 'bg-slate-100 text-slate-700 ring-slate-200',
        submitted: 'bg-blue-100 text-blue-800 ring-blue-200',
        revision: 'bg-amber-100 text-amber-800 ring-amber-200',
        verified: 'bg-cyan-100 text-cyan-800 ring-cyan-200',
        approved: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        rejected: 'bg-red-100 text-red-800 ring-red-200',
        locked: 'bg-zinc-200 text-zinc-800 ring-zinc-300',
    })[status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
</script>

<template>
    <Head :title="pageTitle" />
    <form class="flex flex-col gap-5 p-4 pb-24" @submit.prevent="submit">
        <section class="overflow-hidden rounded-lg border bg-card shadow-sm">
            <div class="border-b bg-[linear-gradient(135deg,#f8fafc,#ecfdf5)] px-4 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <Link
                            :href="route('renstra-opd.index')"
                            class="inline-flex min-h-9 items-center gap-2 rounded-md border bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <ArrowLeft class="size-4" />
                            Kembali
                        </Link>
                        <div
                            class="mt-4 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1 text-xs font-semibold uppercase text-emerald-800"
                        >
                            <ClipboardList class="size-3.5" />
                            Identitas Renstra OPD
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-normal text-slate-950">{{ pageTitle }}</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Isi data dasar Renstra terlebih dahulu. Setelah disimpan, lanjutkan pengisian cascading tujuan, sasaran, program,
                            kegiatan, indikator, dan target pada halaman detail.
                        </p>
                    </div>

                    <div class="rounded-lg border bg-white p-4 text-sm shadow-sm lg:w-80">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold text-slate-900">Status saat ini</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(form.status)">
                                {{ statusLabel(form.status) }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-muted-foreground">
                            Untuk input awal, status biasanya tetap Draft. Pengajuan dan verifikasi dilakukan melalui workflow setelah data cascading
                            lengkap.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="errorCount" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:px-6" role="alert">
                Ada {{ errorCount }} bagian yang perlu diperbaiki. Pesan error tampil di bawah field terkait.
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid gap-5">
                <section class="rounded-lg border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-100 text-sm font-semibold text-emerald-800"
                        >
                            1
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-slate-950">Pilih OPD dan RPJMD</h2>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                Bagian ini menentukan Renstra milik perangkat daerah mana dan disinkronkan ke RPJMD kabupaten yang mana.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        <label class="grid min-w-0 gap-2" for="opd_id">
                            <span class="text-sm font-medium text-slate-900">Perangkat Daerah <span class="text-red-600">*</span></span>
                            <select
                                id="opd_id"
                                v-model="form.opd_id"
                                class="min-h-11 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                :disabled="opdOptions.length === 1"
                            >
                                <option value="">Pilih OPD</option>
                                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <span class="text-xs leading-5 text-muted-foreground"> Admin OPD otomatis menggunakan OPD sesuai akun. </span>
                            <InputError :message="form.errors.opd_id" />
                        </label>

                        <label class="grid min-w-0 gap-2" for="rpjmd_id">
                            <span class="text-sm font-medium text-slate-900">RPJMD Kabupaten <span class="text-red-600">*</span></span>
                            <select
                                id="rpjmd_id"
                                v-model="form.rpjmd_id"
                                class="min-h-11 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Pilih RPJMD</option>
                                <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <span class="text-xs leading-5 text-muted-foreground">
                                Koneksi ini dipakai untuk mengaitkan tujuan, sasaran, dan program OPD ke cascading kabupaten.
                            </span>
                            <InputError :message="form.errors.rpjmd_id" />
                        </label>
                    </div>
                </section>

                <section class="rounded-lg border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-sky-100 text-sm font-semibold text-sky-800">
                            2
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-slate-950">Tentukan periode Renstra</h2>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                Periode membantu sistem membaca target tahunan dan laporan monitoring pada tahun yang tepat.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <label class="grid gap-2" for="tahun_awal">
                            <span class="text-sm font-medium text-slate-900">Tahun Awal <span class="text-red-600">*</span></span>
                            <input
                                id="tahun_awal"
                                v-model="form.tahun_awal"
                                type="number"
                                min="2000"
                                max="2100"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <InputError :message="form.errors.tahun_awal" />
                        </label>

                        <label class="grid gap-2" for="tahun_akhir">
                            <span class="text-sm font-medium text-slate-900">Tahun Akhir <span class="text-red-600">*</span></span>
                            <input
                                id="tahun_akhir"
                                v-model="form.tahun_akhir"
                                type="number"
                                min="2000"
                                max="2100"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            />
                            <InputError :message="form.errors.tahun_akhir" />
                        </label>

                        <label class="grid min-w-0 gap-2" for="periode_tahun_id">
                            <span class="text-sm font-medium text-slate-900">Periode Referensi</span>
                            <select
                                id="periode_tahun_id"
                                v-model="form.periode_tahun_id"
                                class="min-h-11 w-full min-w-0 truncate rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Pilih periode</option>
                                <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.periode_tahun_id" />
                        </label>
                    </div>

                    <div class="mt-4 flex items-start gap-2 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950">
                        <CalendarDays class="mt-0.5 size-4 shrink-0" />
                        <span>Ringkasan periode: {{ yearRangeLabel }}</span>
                    </div>
                </section>

                <section class="rounded-lg border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-amber-100 text-sm font-semibold text-amber-800">
                            3
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-slate-950">Lengkapi identitas dokumen</h2>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                Gunakan judul yang mudah dikenali agar dokumen cepat ditemukan di monitoring kabupaten.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <label class="grid gap-2" for="judul">
                            <span class="text-sm font-medium text-slate-900">Judul Renstra <span class="text-red-600">*</span></span>
                            <input
                                id="judul"
                                v-model="form.judul"
                                class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                placeholder="Contoh: Renstra Dinas Komunikasi dan Informatika 2025-2029"
                            />
                            <InputError :message="form.errors.judul" />
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2" for="nomor_dokumen">
                                <span class="text-sm font-medium text-slate-900">Nomor Dokumen</span>
                                <input
                                    id="nomor_dokumen"
                                    v-model="form.nomor_dokumen"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                    placeholder="Opsional"
                                />
                                <InputError :message="form.errors.nomor_dokumen" />
                            </label>

                            <label class="grid gap-2" for="status">
                                <span class="text-sm font-medium text-slate-900">Status Alur Kerja</span>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="min-h-11 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="submitted">Diajukan</option>
                                    <option value="revision">Revisi</option>
                                    <option value="verified">Terverifikasi</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                    <option value="locked">Terkunci</option>
                                </select>
                                <span class="text-xs leading-5 text-muted-foreground">
                                    Jika ragu, biarkan Draft. Status final sebaiknya berubah lewat workflow.
                                </span>
                                <InputError :message="form.errors.status" />
                            </label>
                        </div>

                        <label class="grid gap-2" for="keterangan">
                            <span class="text-sm font-medium text-slate-900">Catatan atau Keterangan</span>
                            <textarea
                                id="keterangan"
                                v-model="form.keterangan"
                                rows="5"
                                class="rounded-md border bg-background px-3 py-2 text-sm leading-6 outline-none focus:ring-2 focus:ring-emerald-700"
                                placeholder="Tambahkan catatan internal jika ada, misalnya dasar perubahan, sumber dokumen, atau keterangan periode."
                            />
                            <InputError :message="form.errors.keterangan" />
                        </label>
                    </div>
                </section>
            </div>

            <aside class="grid gap-4 xl:sticky xl:top-20 xl:self-start">
                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="size-5 text-emerald-700" />
                        <h2 class="text-base font-semibold text-slate-950">Ringkasan input</h2>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div v-for="item in readinessItems" :key="item.label" class="flex gap-3 rounded-md border bg-background p-3">
                            <span
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                :class="item.complete ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                            >
                                <CheckCircle2 v-if="item.complete" class="size-3.5" />
                                <span v-else>-</span>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase text-muted-foreground">{{ item.label }}</p>
                                <p class="mt-1 line-clamp-2 text-sm font-medium text-slate-900">{{ item.value }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <div class="flex items-center gap-2">
                        <GitBranch class="size-5 text-sky-700" />
                        <h2 class="text-base font-semibold text-slate-950">Setelah disimpan</h2>
                    </div>

                    <ol class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700"
                                >1</span
                            >
                            <span>Buka detail Renstra.</span>
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700"
                                >2</span
                            >
                            <span>Isi tujuan, sasaran, program, kegiatan, dan sub kegiatan.</span>
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700"
                                >3</span
                            >
                            <span>Lengkapi indikator, target tahunan, dan target triwulan.</span>
                        </li>
                    </ol>
                </section>

                <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm">
                    <div class="flex gap-2">
                        <Info class="mt-0.5 size-5 shrink-0" />
                        <div>
                            <h2 class="font-semibold">Tips pengisian</h2>
                            <p class="mt-1 leading-6">
                                Form ini hanya menyimpan identitas Renstra. Struktur cascading diisi setelah Renstra berhasil dibuat.
                            </p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <div
            class="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur supports-[backdrop-filter]:bg-background/85 lg:left-[var(--sidebar-width,0px)]"
        >
            <div class="mx-auto flex max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-2 text-sm text-muted-foreground">
                    <FileText class="size-4 shrink-0" />
                    <span class="truncate">{{ form.judul || 'Identitas Renstra belum diberi judul' }}</span>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('renstra-opd.index')"
                        class="inline-flex min-h-11 flex-1 items-center justify-center rounded-md border px-4 text-sm font-medium hover:bg-muted sm:flex-none"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60 sm:flex-none"
                    >
                        <Save class="size-4" />
                        {{ form.processing ? 'Menyimpan...' : submitLabel }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
