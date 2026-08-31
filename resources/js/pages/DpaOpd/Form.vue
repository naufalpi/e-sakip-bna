<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Building2, FileCheck2, Landmark, Save, Scale, WalletCards } from 'lucide-vue-next';
import { computed, watch } from 'vue';

type RkaOption = {
    id: number;
    label: string;
    tahun: number;
    jenis_anggaran: string;
    type_label: string;
    items_count: number;
    total_pagu: string | number;
    default_title: string;
    opd?: { kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode?: string | null; nama: string } | null;
};
type Dpa = {
    id: number;
    rka_opd_id: number;
    judul: string;
    nomor_dpa?: string | null;
    tanggal_pengesahan?: string | null;
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
    catatan?: string | null;
    catatan_verifikasi?: string | null;
    tahun: number;
    type_label: string;
    opd?: RkaOption['opd'];
    rka?: { judul: string; tahun: number } | null;
};

const props = defineProps<{ mode: 'create' | 'edit'; dpa: Dpa | null; rkaOptions: RkaOption[]; canVerify: boolean }>();
const form = useForm({
    rka_opd_id: props.dpa?.rka_opd_id ?? ('' as number | string),
    judul: props.dpa?.judul ?? '',
    nomor_dpa: props.dpa?.nomor_dpa ?? '',
    tanggal_pengesahan: props.dpa?.tanggal_pengesahan ?? '',
    nomor_perda_apbd: props.dpa?.nomor_perda_apbd ?? '',
    tanggal_perda_apbd: props.dpa?.tanggal_perda_apbd ?? '',
    nomor_perkada_penjabaran: props.dpa?.nomor_perkada_penjabaran ?? '',
    tanggal_perkada_penjabaran: props.dpa?.tanggal_perkada_penjabaran ?? '',
    nama_pengguna_anggaran: props.dpa?.nama_pengguna_anggaran ?? '',
    nip_pengguna_anggaran: props.dpa?.nip_pengguna_anggaran ?? '',
    nama_ppkd: props.dpa?.nama_ppkd ?? '',
    nip_ppkd: props.dpa?.nip_ppkd ?? '',
    nama_sekretaris_daerah: props.dpa?.nama_sekretaris_daerah ?? '',
    nip_sekretaris_daerah: props.dpa?.nip_sekretaris_daerah ?? '',
    catatan: props.dpa?.catatan ?? '',
    catatan_verifikasi: props.dpa?.catatan_verifikasi ?? '',
});
const selectedRka = computed(() => props.rkaOptions.find((item) => item.id === Number(form.rka_opd_id)) ?? null);
watch(selectedRka, (rka) => {
    if (props.mode === 'create' && rka) form.judul = rka.default_title;
});
const rupiah = (value?: string | number | null) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
const submit = () => (props.mode === 'create' ? form.post(route('dpa-opd.store')) : form.put(route('dpa-opd.update', props.dpa!.id)));
const backUrl = computed(() => (props.mode === 'edit' && props.dpa ? route('dpa-opd.show', props.dpa.id) : route('dpa-opd.index')));
</script>

<template>
    <Head :title="mode === 'create' ? 'Buat DPA OPD' : 'Edit DPA OPD'" />
    <div class="p-4 sm:p-5">
        <form class="mx-auto grid max-w-7xl gap-5 xl:grid-cols-[minmax(0,1fr)_330px]" @submit.prevent="submit">
            <div class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
                    <header
                        class="flex items-start gap-4 border-b border-slate-200 bg-[linear-gradient(120deg,#fff,#f2faf7)] px-5 py-5 dark:border-slate-800 dark:bg-[linear-gradient(120deg,#0f172a,#0b2928)] sm:px-6"
                    >
                        <Link
                            :href="backUrl"
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl border bg-white/80 text-slate-600 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300"
                            ><ArrowLeft class="size-4"
                        /></Link>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.16em] text-emerald-700 dark:text-emerald-300">
                                {{ canVerify ? 'Pengesahan dokumen' : 'Pelaksanaan anggaran' }}
                            </p>
                            <h1 class="mt-1 text-xl font-bold text-slate-950 dark:text-white">
                                {{ mode === 'create' ? 'Buat DPA dari RKA' : canVerify ? 'Lengkapi Pengesahan DPA' : 'Edit Informasi DPA' }}
                            </h1>
                            <p class="mt-1 text-sm text-slate-500">
                                DPA menjadi dasar pelaksanaan anggaran setelah proses verifikasi dan pengesahan.
                            </p>
                        </div>
                    </header>

                    <div v-if="mode === 'create'" class="p-5 sm:p-6">
                        <label
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500"
                                >RKA yang telah disetujui <b class="text-red-600">*</b></span
                            ><select
                                v-model="form.rka_opd_id"
                                class="mt-2 h-11 w-full rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                            >
                                <option value="">Pilih RKA resmi</option>
                                <option v-for="option in rkaOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select
                            ><span v-if="form.errors.rka_opd_id" class="mt-1.5 block text-xs text-red-600">{{ form.errors.rka_opd_id }}</span></label
                        >
                        <div
                            v-if="selectedRka"
                            class="mt-4 grid gap-px overflow-hidden rounded-xl border border-emerald-200 bg-emerald-200 dark:border-emerald-900 dark:bg-emerald-900 sm:grid-cols-3"
                        >
                            <div class="bg-emerald-50/80 p-4 dark:bg-emerald-950/40">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Perangkat Daerah</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                                    {{ selectedRka.opd?.singkatan || selectedRka.opd?.nama }}
                                </p>
                            </div>
                            <div class="bg-emerald-50/80 p-4 dark:bg-emerald-950/40">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Rincian</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ selectedRka.items_count }} sub kegiatan</p>
                            </div>
                            <div class="bg-emerald-50/80 p-4 dark:bg-emerald-950/40">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Pagu disetujui</p>
                                <p class="mt-1 text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ rupiah(selectedRka.total_pagu) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="grid gap-px bg-slate-200 dark:bg-slate-800 sm:grid-cols-2">
                        <div class="bg-card p-5 sm:p-6">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Perangkat Daerah</p>
                            <p class="mt-1 font-bold text-slate-900 dark:text-white">{{ dpa?.opd?.singkatan || dpa?.opd?.nama }}</p>
                        </div>
                        <div class="bg-card p-5 sm:p-6">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Acuan RKA</p>
                            <p class="mt-1 line-clamp-2 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ dpa?.rka?.judul }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-card p-5 shadow-sm dark:border-slate-800 sm:p-6">
                    <div class="mb-5 flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                        >
                            <FileCheck2 class="size-4" />
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-white">Identitas dokumen</h2>
                            <p class="text-xs text-slate-500">Judul dan identitas pengesahan DPA.</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="sm:col-span-2"
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Judul DPA <b class="text-red-600">*</b></span
                            ><input
                                v-model="form.judul"
                                :disabled="canVerify"
                                class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700 disabled:opacity-60"
                            /><span v-if="form.errors.judul" class="mt-1 block text-xs text-red-600">{{ form.errors.judul }}</span></label
                        >
                        <label
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Nomor DPA</span
                            ><input
                                v-model="form.nomor_dpa"
                                :disabled="!canVerify"
                                class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm disabled:opacity-60"
                                placeholder="Diisi saat pengesahan"
                        /></label>
                        <label
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Tanggal Pengesahan</span
                            ><input
                                v-model="form.tanggal_pengesahan"
                                :disabled="!canVerify"
                                type="date"
                                class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm disabled:opacity-60"
                        /></label>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-card p-5 shadow-sm dark:border-slate-800 sm:p-6">
                    <div class="mb-5 flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300"
                        >
                            <Scale class="size-4" />
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-white">Dasar Penetapan APBD</h2>
                            <p class="text-xs text-slate-500">Perda APBD dan Perkada tentang Penjabaran APBD.</p>
                        </div>
                    </div>
                    <fieldset :disabled="canVerify" class="grid gap-4 disabled:opacity-60 sm:grid-cols-2">
                        <label
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Nomor Perda APBD</span
                            ><input v-model="form.nomor_perda_apbd" class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm" /></label
                        ><label
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Tanggal Perda APBD</span
                            ><input
                                v-model="form.tanggal_perda_apbd"
                                type="date"
                                class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm"
                        /></label>
                        <label
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Nomor Perkada Penjabaran APBD</span
                            ><input
                                v-model="form.nomor_perkada_penjabaran"
                                class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm" /></label
                        ><label
                            ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Tanggal Perkada Penjabaran APBD</span
                            ><input
                                v-model="form.tanggal_perkada_penjabaran"
                                type="date"
                                class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm"
                        /></label>
                    </fieldset>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-card p-5 shadow-sm dark:border-slate-800 sm:p-6">
                    <div class="mb-5 flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <Building2 class="size-4" />
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-white">Pejabat penandatangan</h2>
                            <p class="text-xs text-slate-500">Pengguna Anggaran, PPKD, dan persetujuan Sekretaris Daerah.</p>
                        </div>
                    </div>
                    <div class="grid gap-5">
                        <fieldset :disabled="canVerify" class="grid gap-4 disabled:opacity-60 sm:grid-cols-2">
                            <label
                                ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Nama Pengguna Anggaran</span
                                ><input
                                    v-model="form.nama_pengguna_anggaran"
                                    class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm" /></label
                            ><label
                                ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">NIP Pengguna Anggaran</span
                                ><input v-model="form.nip_pengguna_anggaran" class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm"
                            /></label>
                        </fieldset>
                        <div v-if="canVerify" class="grid gap-4 border-t border-slate-200 pt-5 dark:border-slate-800 sm:grid-cols-2">
                            <label
                                ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Nama PPKD</span
                                ><input v-model="form.nama_ppkd" class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm" /></label
                            ><label
                                ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">NIP PPKD</span
                                ><input v-model="form.nip_ppkd" class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm" /></label
                            ><label
                                ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Nama Sekretaris Daerah</span
                                ><input
                                    v-model="form.nama_sekretaris_daerah"
                                    class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm" /></label
                            ><label
                                ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">NIP Sekretaris Daerah</span
                                ><input v-model="form.nip_sekretaris_daerah" class="mt-1.5 h-11 w-full rounded-xl border bg-background px-3 text-sm"
                            /></label>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-card p-5 shadow-sm dark:border-slate-800 sm:p-6">
                    <label
                        ><span class="text-xs font-bold uppercase tracking-wide text-slate-500">{{
                            canVerify ? 'Catatan Verifikasi' : 'Catatan Dokumen'
                        }}</span
                        ><textarea
                            v-if="canVerify"
                            v-model="form.catatan_verifikasi"
                            rows="4"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm"
                        ></textarea
                        ><textarea
                            v-else
                            v-model="form.catatan"
                            rows="4"
                            class="mt-1.5 w-full rounded-xl border bg-background p-3 text-sm"
                        ></textarea>
                    </label>
                </section>
            </div>

            <aside class="xl:sticky xl:top-24 xl:self-start">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
                    <div class="border-b border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                        <div class="flex items-center gap-2">
                            <WalletCards class="size-4 text-emerald-700" />
                            <h2 class="font-bold text-slate-900 dark:text-white">Ringkasan</h2>
                        </div>
                    </div>
                    <div class="space-y-4 p-5 text-sm">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Jenis dokumen</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-200">
                                {{ selectedRka?.jenis_anggaran === 'perubahan' || dpa?.type_label === 'DPPA-SKPD' ? 'DPPA-SKPD' : 'DPA-SKPD' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tahun anggaran</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-200">{{ selectedRka?.tahun || dpa?.tahun || '-' }}</p>
                        </div>
                        <div v-if="selectedRka">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Pagu RKA</p>
                            <p class="mt-1 font-bold tabular-nums text-slate-950 dark:text-white">{{ rupiah(selectedRka.total_pagu) }}</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-3 text-xs leading-5 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                            <Landmark class="mb-2 size-4" />Rincian, pagu, dan alokasi antar-tahun disalin sebagai snapshot dari RKA yang sudah resmi.
                        </div>
                    </div>
                    <div class="flex gap-2 border-t border-slate-200 p-4 dark:border-slate-800">
                        <Link
                            :href="backUrl"
                            class="inline-flex h-11 flex-1 items-center justify-center rounded-xl border text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800"
                            >Batal</Link
                        ><button
                            type="submit"
                            :disabled="form.processing || (mode === 'create' && !form.rka_opd_id)"
                            class="inline-flex h-11 flex-[1.4] items-center justify-center gap-2 rounded-xl bg-[#064e3b] px-4 text-sm font-semibold text-white hover:bg-[#043d2e] disabled:opacity-50"
                        >
                            <Save class="size-4" />{{ form.processing ? 'Menyimpan...' : mode === 'create' ? 'Buat DPA' : 'Simpan' }}
                        </button>
                    </div>
                </section>
            </aside>
        </form>
    </div>
</template>
