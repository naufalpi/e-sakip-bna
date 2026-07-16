<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
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
    () => form.periode_tahun_id,
    (value) => {
        const periode = props.periodeOptions.find((option) => String(option.id) === String(value));
        if (periode?.tahun) {
            form.tahun = periode.tahun;
        }
    },
);

const filteredUnitOptions = computed(() => props.opdUnitOptions.filter((unit) => String(unit.opd_id ?? '') === String(form.opd_id)));
const title = computed(() => (props.mode === 'create' ? 'Tambah Renja OPD' : 'Edit Renja OPD'));

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('renja-opd.store'));
        return;
    }

    form.put(route('renja-opd.update', props.renja?.id));
};
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-5 p-4">
        <div>
            <Link :href="route('renja-opd.index')" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" />
                Kembali ke Renja
            </Link>
            <h1 class="mt-3 text-2xl font-semibold tracking-normal">{{ title }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Identitas Renja OPD sebagai sumber kompilasi RKPD.</p>
        </div>

        <form class="overflow-hidden rounded-xl border bg-card shadow-sm" @submit.prevent="submit">
            <div class="border-b px-5 py-4">
                <h2 class="text-base font-semibold">Data Renja OPD</h2>
            </div>

            <div class="grid gap-4 p-5 lg:grid-cols-2">
                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">OPD</span>
                    <select v-model="form.opd_id" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="">Pilih OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <span v-if="form.errors.opd_id" class="text-xs text-red-600">{{ form.errors.opd_id }}</span>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Unit OPD</span>
                    <select v-model="form.opd_unit_id" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="">Tidak memilih unit</option>
                        <option v-for="option in filteredUnitOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <span v-if="form.errors.opd_unit_id" class="text-xs text-red-600">{{ form.errors.opd_unit_id }}</span>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">RKPD Kabupaten</span>
                    <select v-model="form.rkpd_id" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="">Belum dihubungkan</option>
                        <option v-for="option in rkpdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Renstra OPD Acuan</span>
                    <select v-model="form.renstra_opd_id" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="">Belum dihubungkan</option>
                        <option v-for="option in renstraOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Periode Tahun</span>
                    <select v-model="form.periode_tahun_id" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="">Pilih periode</option>
                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <span v-if="form.errors.periode_tahun_id" class="text-xs text-red-600">{{ form.errors.periode_tahun_id }}</span>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Tahun Renja</span>
                    <input v-model="form.tahun" type="number" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                    <span v-if="form.errors.tahun" class="text-xs text-red-600">{{ form.errors.tahun }}</span>
                </label>

                <label class="grid gap-1.5 lg:col-span-2">
                    <span class="text-sm font-medium">Judul</span>
                    <input
                        v-model="form.judul"
                        type="text"
                        class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        placeholder="Renja OPD Tahun ..."
                    />
                    <span v-if="form.errors.judul" class="text-xs text-red-600">{{ form.errors.judul }}</span>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Nomor Dokumen</span>
                    <input v-model="form.nomor_dokumen" type="text" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Status</span>
                    <select v-model="form.status" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="draft">Draft</option>
                        <option value="submitted">Diajukan</option>
                        <option value="revision">Revisi</option>
                        <option value="verified">Terverifikasi</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                        <option value="locked">Terkunci</option>
                    </select>
                </label>

                <label class="grid gap-1.5 lg:col-span-2">
                    <span class="text-sm font-medium">Catatan</span>
                    <textarea v-model="form.catatan" rows="4" class="rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"></textarea>
                </label>
            </div>

            <div class="sticky bottom-0 flex justify-end gap-2 border-t bg-card/95 px-5 py-4 backdrop-blur">
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
