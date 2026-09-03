<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, FileCheck2, FileSpreadsheet, Landmark, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

type RenjaOption = {
    id: number;
    label: string;
    tahun: number;
    jenis_versi: 'ditetapkan' | 'perubahan';
    jenis_label: string;
    items_count: number;
    total_pagu?: string | number | null;
    default_title: string;
    opd?: { kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode?: string | null; nama: string } | null;
    rkpd?: { judul: string; jenis_versi: string } | null;
};
type Rka = {
    id: number;
    renja_opd_id: number;
    tahun: number;
    jenis_anggaran: 'murni' | 'perubahan';
    type_label: string;
    judul: string;
    catatan?: string | null;
    opd?: { kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode?: string | null; nama: string } | null;
    renja?: { judul: string; jenis_versi: string } | null;
};

const props = defineProps<{ mode: 'create' | 'edit'; rka: Rka | null; renjaOptions: RenjaOption[] }>();

const form = useForm({
    renja_opd_id: props.rka?.renja_opd_id ?? ('' as number | string),
    judul: props.rka?.judul ?? '',
    catatan: props.rka?.catatan ?? '',
});

const selectedRenja = computed(() => props.renjaOptions.find((option) => option.id === Number(form.renja_opd_id)) ?? null);
watch(selectedRenja, (option) => {
    if (props.mode === 'create' && option) form.judul = option.default_title;
});

const rupiah = (value?: string | number | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));

const submit = () => {
    if (props.mode === 'create') form.post(route('rka-opd.store'));
    else if (props.rka) form.put(route('rka-opd.update', props.rka.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Buat RKA OPD' : 'Edit RKA OPD'" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link
                    :href="rka ? route('rka-opd.show', rka.id) : route('rka-opd.index')"
                    class="inline-flex size-10 items-center justify-center rounded-xl border bg-card text-slate-600 shadow-sm hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                    ><ArrowLeft class="size-4"
                /></Link>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-blue-700 dark:text-blue-300">RKA-BELANJA SKPD</p>
                    <h1 class="text-xl font-bold text-slate-950 dark:text-white">
                        {{ mode === 'create' ? 'Buat RKA dari RENJA' : 'Informasi Dokumen RKA' }}
                    </h1>
                </div>
            </div>
        </div>

        <form class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]" @submit.prevent="submit">
            <div class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
                    <header class="flex items-start gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-[#00336C] dark:bg-blue-950/50 dark:text-blue-300"
                        >
                            <FileCheck2 class="size-4" />
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-white">Acuan RENJA</h2>
                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Sumber struktur dan pagu awal RKA.</p>
                        </div>
                    </header>
                    <div class="p-5 sm:p-6">
                        <template v-if="mode === 'create'">
                            <label class="block text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >RENJA Ditetapkan <span class="text-red-500">*</span></label
                            >
                            <select
                                v-model="form.renja_opd_id"
                                class="mt-2 h-11 w-full rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                :class="{ 'border-red-400': form.errors.renja_opd_id }"
                            >
                                <option value="">Pilih RENJA resmi</option>
                                <option v-for="option in renjaOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.renja_opd_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.renja_opd_id }}</p>
                            <div
                                v-if="selectedRenja"
                                class="mt-4 grid gap-3 rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-sm dark:border-blue-900/60 dark:bg-blue-950/25 sm:grid-cols-3"
                            >
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Organisasi</p>
                                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                                        {{ selectedRenja.opd?.singkatan || selectedRenja.opd?.nama }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ selectedRenja.opd_unit?.nama || 'Tanpa sub unit' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Dokumen sumber</p>
                                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ selectedRenja.jenis_label }}</p>
                                    <p class="text-xs text-slate-500">Tahun {{ selectedRenja.tahun }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Rincian</p>
                                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ selectedRenja.items_count }} sub kegiatan</p>
                                    <p class="text-xs text-slate-500">{{ rupiah(selectedRenja.total_pagu) }}</p>
                                </div>
                            </div>
                        </template>
                        <div v-else class="grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Organisasi</p>
                                <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ rka?.opd?.singkatan || rka?.opd?.nama }}</p>
                                <p class="text-xs text-slate-500">{{ rka?.opd_unit?.nama || 'Tanpa sub unit' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Jenis anggaran</p>
                                <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ rka?.type_label }}</p>
                                <p class="text-xs text-slate-500">Tahun {{ rka?.tahun }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">RENJA sumber</p>
                                <p class="mt-1 line-clamp-2 font-semibold text-slate-800 dark:text-slate-100">{{ rka?.renja?.judul }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <fieldset class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
                    <header class="flex items-start gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <FileSpreadsheet class="size-4" />
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-white">Informasi RKA-SKPD</h2>
                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Judul dan catatan penyusunan dokumen.</p>
                        </div>
                    </header>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                        <label class="sm:col-span-2"
                            ><span class="text-sm font-semibold text-slate-800 dark:text-slate-200">Judul dokumen <b class="text-red-500">*</b></span
                            ><input
                                v-model="form.judul"
                                type="text"
                                class="mt-2 h-11 w-full rounded-xl border bg-background px-3 text-sm uppercase outline-none focus:ring-2 focus:ring-[#00336C]"
                            /><span v-if="form.errors.judul" class="mt-1 block text-xs text-red-600">{{ form.errors.judul }}</span></label
                        >
                        <label class="sm:col-span-2"
                            ><span class="text-sm font-semibold text-slate-800 dark:text-slate-200">Catatan penyusunan</span
                            ><textarea
                                v-model="form.catatan"
                                rows="3"
                                class="mt-2 w-full rounded-xl border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                                placeholder="Catatan yang perlu menyertai penyusunan RKA."
                            ></textarea>
                        </label>
                    </div>
                </fieldset>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <section class="rounded-2xl border border-slate-200 bg-card p-5 shadow-sm dark:border-slate-800">
                    <div class="flex items-center gap-2 text-slate-900 dark:text-white">
                        <Landmark class="size-4 text-[#00336C] dark:text-blue-300" />
                        <h2 class="font-bold">Cakupan RKA</h2>
                    </div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Formulir</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200">RKA-BELANJA</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Tahun</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200">{{ selectedRenja?.tahun || rka?.tahun || '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Jenis</dt>
                            <dd class="text-right font-semibold text-slate-800 dark:text-slate-200">
                                {{ selectedRenja ? (selectedRenja.jenis_versi === 'perubahan' ? 'Perubahan APBD' : 'APBD') : rka?.type_label }}
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <p class="flex items-start gap-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            <CalendarDays class="mt-0.5 size-3.5 shrink-0" />Rincian sub kegiatan dan pagu awal disalin sebagai snapshot dari RENJA
                            sumber.
                        </p>
                    </div>
                </section>
                <button
                    type="submit"
                    :disabled="form.processing || (mode === 'create' && !form.renja_opd_id)"
                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#002855] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Save class="size-4" />{{ form.processing ? 'Menyimpan...' : mode === 'create' ? 'Buat dan Salin Rincian' : 'Simpan Perubahan' }}
                </button>
                <Link
                    :href="rka ? route('rka-opd.show', rka.id) : route('rka-opd.index')"
                    class="inline-flex min-h-10 w-full items-center justify-center rounded-xl border bg-card px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                    >Batal</Link
                >
            </aside>
        </form>
    </div>
</template>
