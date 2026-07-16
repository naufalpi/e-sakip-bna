<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Save, Search, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

type Option = { id: number; label: string; kode?: string; nama?: string; description?: string; group?: string };
type Renja = {
    id: number;
    tahun: number;
    judul: string;
    nomor_dokumen?: string | null;
    status: string;
    opd?: { id: number; kode?: string | null; nama: string; singkatan?: string | null } | null;
    opd_unit?: { id: number; kode?: string | null; nama: string } | null;
    rkpd?: { id: number; judul: string; tahun: number } | null;
};
type Row = {
    id: number;
    sub_kegiatan_pemerintahan_id?: number | null;
    kode?: string | null;
    nama_sub_kegiatan?: string | null;
    indikator?: string | null;
    target_akhir_renstra?: string | null;
    realisasi_capaian_renja_tahun_lalu?: string | null;
    prakiraan_capaian_target_renja_tahun_berjalan?: string | null;
    target?: string | null;
    pagu_indikatif?: string | number | null;
    lokasi?: string | null;
    sumber_dana?: string | null;
    prioritas_nasional?: string | null;
    prioritas_daerah?: string | null;
    kelompok_sasaran?: string | null;
    prakiraan_maju_target?: string | null;
    prakiraan_maju_pagu_indikatif?: string | number | null;
    status: string;
    urutan: number;
    program?: string;
    kegiatan?: string;
    sub_kegiatan?: string;
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

const props = defineProps<{
    renja: Renja;
    items: Paginator<Row>;
    filters: { search?: string; status?: string };
    subKegiatanOptions: Option[];
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const form = useForm({
    sub_kegiatan_pemerintahan_id: '',
    indikator_sub_kegiatan_id: '',
    indikator: '',
    target_akhir_renstra: '',
    realisasi_capaian_renja_tahun_lalu: '',
    prakiraan_capaian_target_renja_tahun_berjalan: '',
    target: '',
    pagu_indikatif: '',
    lokasi: '',
    sumber_dana: '',
    prioritas_nasional: '',
    prioritas_daerah: '',
    kelompok_sasaran: '',
    prakiraan_maju_target: '',
    prakiraan_maju_pagu_indikatif: '',
    status: 'draft',
    urutan: '',
});

const editingId = ref<number | null>(null);
const selectedSubKegiatan = computed(() =>
    props.subKegiatanOptions.find((option) => String(option.id) === String(form.sub_kegiatan_pemerintahan_id)),
);

const applyFilters = () => router.get(route('renja-opd.show', props.renja.id), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFiltersNow();
};

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'draft';
};

const submitItem = () => {
    if (editingId.value) {
        form.put(route('renja-opd.items.update', [props.renja.id, editingId.value]), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post(route('renja-opd.items.store', props.renja.id), {
        preserveScroll: true,
        onSuccess: resetForm,
    });
};

const editItem = (row: Row) => {
    editingId.value = row.id;
    form.sub_kegiatan_pemerintahan_id = String(row.sub_kegiatan_pemerintahan_id ?? '');
    form.indikator = row.indikator ?? '';
    form.target_akhir_renstra = row.target_akhir_renstra ?? '';
    form.realisasi_capaian_renja_tahun_lalu = row.realisasi_capaian_renja_tahun_lalu ?? '';
    form.prakiraan_capaian_target_renja_tahun_berjalan = row.prakiraan_capaian_target_renja_tahun_berjalan ?? '';
    form.target = row.target ?? '';
    form.pagu_indikatif = String(row.pagu_indikatif ?? '');
    form.lokasi = row.lokasi ?? '';
    form.sumber_dana = row.sumber_dana ?? '';
    form.prioritas_nasional = row.prioritas_nasional ?? '';
    form.prioritas_daerah = row.prioritas_daerah ?? '';
    form.kelompok_sasaran = row.kelompok_sasaran ?? '';
    form.prakiraan_maju_target = row.prakiraan_maju_target ?? '';
    form.prakiraan_maju_pagu_indikatif = String(row.prakiraan_maju_pagu_indikatif ?? '');
    form.status = row.status;
    form.urutan = String(row.urutan ?? '');
};

const destroyItem = async (row: Row) => {
    if (await confirmDelete(`Hapus baris ${row.kode || row.nama_sub_kegiatan || 'Renja'}?`)) {
        router.delete(route('renja-opd.items.destroy', [props.renja.id, row.id]), { preserveScroll: true });
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

const formatMoney = (value?: number | string | null) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
};
</script>

<template>
    <Head :title="`Renja OPD ${renja.tahun}`" />

    <div class="flex flex-col gap-5 p-4">
        <section class="rounded-xl border bg-card p-5 shadow-sm">
            <Link :href="route('renja-opd.index')" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" />
                Kembali
            </Link>
            <div class="mt-3 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-normal">{{ renja.judul }}</h1>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(renja.status)">{{ statusLabel(renja.status) }}</span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ renja.opd?.singkatan || renja.opd?.nama || '-' }} · {{ renja.tahun }} · {{ renja.nomor_dokumen || 'Nomor belum diisi' }}
                    </p>
                    <p v-if="renja.rkpd" class="mt-2 text-sm text-muted-foreground">Terhubung RKPD {{ renja.rkpd.tahun }} · {{ renja.rkpd.judul }}</p>
                </div>
                <Link
                    v-if="can.manage"
                    :href="route('renja-opd.edit', renja.id)"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855]"
                >
                    <Pencil class="size-4" />
                    Edit Renja
                </Link>
            </div>
        </section>

        <section v-if="can.manage" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="flex items-center justify-between border-b px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold">{{ editingId ? 'Edit Baris Renja' : 'Tambah Baris Renja' }}</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Pilih sub kegiatan, lalu isi indikator, target, pagu, lokasi, sumber dana, dan prioritas.</p>
                </div>
                <button v-if="editingId" type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-sm hover:bg-muted" @click="resetForm">
                    <X class="size-4" />
                    Batal Edit
                </button>
            </div>

            <form class="grid gap-4 p-5" @submit.prevent="submitItem">
                <div class="grid gap-4 lg:grid-cols-3">
                    <label class="grid gap-1.5 lg:col-span-2">
                        <span class="text-sm font-medium">Sub Kegiatan</span>
                        <select
                            v-model="form.sub_kegiatan_pemerintahan_id"
                            class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        >
                            <option value="">Pilih sub kegiatan</option>
                            <option v-for="option in subKegiatanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <span v-if="selectedSubKegiatan" class="text-xs text-muted-foreground">
                            {{ selectedSubKegiatan.group }} · {{ selectedSubKegiatan.description }}
                        </span>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium">Target Renja</span>
                        <input v-model="form.target" type="text" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                    </label>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <label class="grid gap-1.5 lg:col-span-2">
                        <span class="text-sm font-medium">Indikator</span>
                        <textarea v-model="form.indikator" rows="3" class="rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium">Pagu Indikatif</span>
                        <input v-model="form.pagu_indikatif" type="number" min="0" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                    </label>
                </div>

                <div class="grid gap-4 lg:grid-cols-4">
                    <input v-model="form.target_akhir_renstra" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Target akhir Renstra" />
                    <input v-model="form.realisasi_capaian_renja_tahun_lalu" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Realisasi tahun lalu" />
                    <input v-model="form.prakiraan_capaian_target_renja_tahun_berjalan" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Prakiraan tahun berjalan" />
                    <input v-model="form.sumber_dana" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Sumber dana" />
                    <input v-model="form.prioritas_nasional" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Prioritas nasional" />
                    <input v-model="form.prioritas_daerah" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Prioritas daerah" />
                    <input v-model="form.kelompok_sasaran" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Kelompok sasaran" />
                    <input v-model="form.lokasi" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Lokasi" />
                    <input v-model="form.prakiraan_maju_target" type="text" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Prakiraan maju target" />
                    <input v-model="form.prakiraan_maju_pagu_indikatif" type="number" min="0" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Prakiraan maju pagu" />
                    <select v-model="form.status" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="draft">Draft</option>
                        <option value="verified">Terverifikasi</option>
                        <option value="approved">Disetujui</option>
                        <option value="locked">Terkunci</option>
                    </select>
                    <input v-model="form.urutan" type="number" min="1" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Urutan" />
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002855] disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{ editingId ? 'Simpan Perubahan' : 'Simpan Baris' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border bg-card shadow-sm">
            <div class="border-b p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Baris Renja OPD</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Data ini menjadi sumber kompilasi RKPD setelah status Renja diverifikasi.</p>
                    </div>
                    <form class="grid gap-2 lg:grid-cols-[260px_160px_auto]" @submit.prevent="applyFiltersNow">
                        <label class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="filterForm.search" type="search" class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" placeholder="Cari kode atau indikator" />
                        </label>
                        <select v-model="filterForm.status" class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                            <option value="">Semua status</option>
                            <option value="draft">Draft</option>
                            <option value="verified">Terverifikasi</option>
                            <option value="approved">Disetujui</option>
                            <option value="locked">Terkunci</option>
                        </select>
                        <button type="button" class="h-10 rounded-lg px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1600px] text-left text-xs">
                    <thead class="border-b bg-muted/60 uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Sub Kegiatan</th>
                            <th class="px-4 py-3">Indikator</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3">Pagu</th>
                            <th class="px-4 py-3">Lokasi</th>
                            <th class="px-4 py-3">Prioritas</th>
                            <th class="px-4 py-3">Prakiraan Maju</th>
                            <th class="px-4 py-3">Status</th>
                            <th v-if="can.manage" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in items.data" :key="row.id" class="border-b align-top last:border-0 hover:bg-muted/40">
                            <td class="px-4 py-3 font-semibold">{{ items.from ? items.from + index : index + 1 }}</td>
                            <td class="px-4 py-3 font-semibold">{{ row.kode || '-' }}</td>
                            <td class="min-w-96 px-4 py-3">
                                <div class="font-semibold">{{ row.nama_sub_kegiatan || row.sub_kegiatan || '-' }}</div>
                                <div class="mt-1 text-[11px] text-muted-foreground">{{ row.program }} · {{ row.kegiatan }}</div>
                            </td>
                            <td class="min-w-80 px-4 py-3">{{ row.indikator || '-' }}</td>
                            <td class="px-4 py-3">{{ row.target || '-' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ formatMoney(row.pagu_indikatif) }}</td>
                            <td class="px-4 py-3">{{ row.lokasi || '-' }}</td>
                            <td class="px-4 py-3">{{ row.prioritas_daerah || row.prioritas_nasional || '-' }}</td>
                            <td class="px-4 py-3">
                                <div>{{ row.prakiraan_maju_target || '-' }}</div>
                                <div class="text-muted-foreground">{{ formatMoney(row.prakiraan_maju_pagu_indikatif) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span>
                            </td>
                            <td v-if="can.manage" class="px-4 py-3 text-right">
                                <div class="inline-flex overflow-hidden rounded-lg border bg-background">
                                    <button type="button" class="h-9 px-3 hover:bg-muted" @click="editItem(row)">
                                        <Pencil class="size-4" />
                                    </button>
                                    <button type="button" class="h-9 px-3 text-red-600 hover:bg-red-50" @click="destroyItem(row)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td :colspan="can.manage ? 11 : 10" class="px-4 py-12 text-center text-sm text-muted-foreground">Belum ada baris Renja.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
