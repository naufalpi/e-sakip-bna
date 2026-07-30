<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Building2, CalendarDays, FileText, Link2, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

type Option = { id: number; label: string; tahun?: number; opd_id?: number };
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
        if (form.opd_unit_id && ! filteredUnitOptions.value.some((unit) => String(unit.id) === String(form.opd_unit_id))) {
            form.opd_unit_id = '';
        }

        if (form.renstra_opd_id && ! filteredRenstraOptions.value.some((renstra) => String(renstra.id) === String(form.renstra_opd_id))) {
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
const filteredRenstraOptions = computed(() => props.renstraOptions.filter((option) => !option.opd_id || String(option.opd_id) === String(form.opd_id)));
const filteredRkpdOptions = computed(() => props.rkpdOptions.filter((option) => !option.tahun || Number(option.tahun) === Number(form.tahun)));
const selectedPeriode = computed(() => props.periodeOptions.find((option) => Number(option.tahun) === Number(form.tahun)));
const selectedOpd = computed(() => props.opdOptions.find((option) => String(option.id) === String(form.opd_id)));
const title = computed(() => (props.mode === 'create' ? 'Tambah Renja OPD' : 'Edit Renja OPD'));
const tahunOptions = computed(() =>
    props.periodeOptions
        .map((option) => ({ id: option.id, tahun: option.tahun, label: option.tahun ? String(option.tahun) : option.label }))
        .filter((option) => option.tahun),
);

const submit = () => {
    form.status = props.renja?.status ?? 'draft';
    form.judul = String(form.judul || '').toUpperCase();
    form.nomor_dokumen = String(form.nomor_dokumen || '').toUpperCase();
    form.periode_tahun_id = selectedPeriode.value?.id ?? '';

    if (props.mode === 'create') {
        form.post(route('renja-opd.store'));
        return;
    }

    form.put(route('renja-opd.update', props.renja?.id));
};
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-5 p-4 pb-24">
        <section class="rounded-2xl border bg-gradient-to-br from-white via-white to-[#00336C]/5 p-5 shadow-sm">
            <Link :href="route('renja-opd.index')" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" />
                Kembali ke Renja
            </Link>
            <div class="mt-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#00336C]/15 bg-[#00336C]/5 px-3 py-1 text-xs font-semibold uppercase text-[#00336C]">
                        <CalendarDays class="size-3.5" />
                        Rencana kerja tahunan
                    </div>
                    <h1 class="mt-3 text-2xl font-semibold tracking-normal">{{ title }}</h1>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">Pilih OPD, tahun Renja, lalu hubungkan ke dokumen perencanaan yang sesuai.</p>
                </div>
                <div class="rounded-2xl border bg-white px-4 py-3 text-sm shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Status awal</div>
                    <div class="mt-1 font-semibold text-[#00336C]">{{ renja?.status || 'Draft' }}</div>
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
                            <select v-model="form.opd_id" class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25">
                                <option value="">Pilih OPD</option>
                                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <span v-if="form.errors.opd_id" class="text-xs text-red-600">{{ form.errors.opd_id }}</span>
                        </label>

                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Unit OPD</span>
                            <select v-model="form.opd_unit_id" class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25">
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
                            <span class="text-sm font-medium">RKPD Kabupaten</span>
                            <select v-model="form.rkpd_id" class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25">
                                <option value="">Belum dihubungkan</option>
                                <option v-for="option in filteredRkpdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </label>

                        <label class="grid min-w-0 gap-1.5">
                            <span class="text-sm font-medium">Renstra OPD Acuan</span>
                            <select v-model="form.renstra_opd_id" class="h-11 w-full min-w-0 truncate rounded-xl border bg-background px-3 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25">
                                <option value="">Belum dihubungkan</option>
                                <option v-for="option in filteredRenstraOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section class="grid gap-4 rounded-2xl border bg-background p-4">
                    <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium">Tahun Renja</span>
                            <select v-model="form.tahun" class="h-11 w-full rounded-xl border bg-background px-3 text-sm font-semibold outline-none transition focus:ring-2 focus:ring-[#00336C]/25">
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
                            <textarea v-model="form.catatan" rows="3" class="rounded-xl border bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-[#00336C]/25"></textarea>
                        </label>
                    </div>
                </section>
            </div>

            <div class="sticky bottom-0 flex justify-end gap-2 border-t bg-card/95 px-5 py-4 backdrop-blur supports-[backdrop-filter]:bg-card/80">
                <Link :href="route('renja-opd.index')" class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-medium hover:bg-muted">
                    Batal
                </Link>
                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855] disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Save class="size-4" />
                    Simpan
                </button>
            </div>
        </form>
    </div>
</template>
