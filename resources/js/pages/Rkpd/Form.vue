<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

type Option = { id: number; label: string; tahun?: number };
type Rkpd = {
    id: number;
    rpjmd_id?: number | null;
    periode_tahun_id: number;
    tahun: number;
    judul: string;
    nomor_dokumen?: string | null;
    status: string;
    catatan?: string | null;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    rkpd: Rkpd | null;
    rpjmdOptions: Option[];
    periodeOptions: Option[];
}>();

const form = useForm({
    rpjmd_id: props.rkpd?.rpjmd_id ?? '',
    periode_tahun_id: props.rkpd?.periode_tahun_id ?? '',
    tahun: props.rkpd?.tahun ?? new Date().getFullYear(),
    judul: props.rkpd?.judul ?? '',
    nomor_dokumen: props.rkpd?.nomor_dokumen ?? '',
    status: props.rkpd?.status ?? 'draft',
    catatan: props.rkpd?.catatan ?? '',
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

const title = computed(() => (props.mode === 'create' ? 'Tambah RKPD' : 'Edit RKPD'));

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('rkpd.store'));
        return;
    }

    form.put(route('rkpd.update', props.rkpd?.id));
};
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-5 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <Link :href="route('rkpd.index')" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />
                    Kembali ke RKPD
                </Link>
                <h1 class="mt-3 text-2xl font-semibold tracking-normal">{{ title }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Identitas dokumen RKPD tahunan sebagai wadah kompilasi Renja OPD.</p>
            </div>
        </div>

        <form class="overflow-hidden rounded-xl border bg-card shadow-sm" @submit.prevent="submit">
            <div class="border-b px-5 py-4">
                <h2 class="text-base font-semibold">Data RKPD</h2>
            </div>

            <div class="grid gap-4 p-5 lg:grid-cols-2">
                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">RPJMD Acuan</span>
                    <select v-model="form.rpjmd_id" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]">
                        <option value="">Pilih RPJMD</option>
                        <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <span v-if="form.errors.rpjmd_id" class="text-xs text-red-600">{{ form.errors.rpjmd_id }}</span>
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
                    <span class="text-sm font-medium">Tahun RKPD</span>
                    <input v-model="form.tahun" type="number" class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]" />
                    <span v-if="form.errors.tahun" class="text-xs text-red-600">{{ form.errors.tahun }}</span>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium">Nomor Dokumen</span>
                    <input
                        v-model="form.nomor_dokumen"
                        type="text"
                        class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        placeholder="Contoh: Perbup Nomor ..."
                    />
                    <span v-if="form.errors.nomor_dokumen" class="text-xs text-red-600">{{ form.errors.nomor_dokumen }}</span>
                </label>

                <label class="grid gap-1.5 lg:col-span-2">
                    <span class="text-sm font-medium">Judul</span>
                    <input
                        v-model="form.judul"
                        type="text"
                        class="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        placeholder="RKPD Kabupaten Banjarnegara Tahun ..."
                    />
                    <span v-if="form.errors.judul" class="text-xs text-red-600">{{ form.errors.judul }}</span>
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
                    <span v-if="form.errors.status" class="text-xs text-red-600">{{ form.errors.status }}</span>
                </label>

                <label class="grid gap-1.5 lg:col-span-2">
                    <span class="text-sm font-medium">Catatan</span>
                    <textarea
                        v-model="form.catatan"
                        rows="4"
                        class="rounded-lg border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#00336C]"
                        placeholder="Catatan internal RKPD"
                    ></textarea>
                    <span v-if="form.errors.catatan" class="text-xs text-red-600">{{ form.errors.catatan }}</span>
                </label>
            </div>

            <div class="sticky bottom-0 flex justify-end gap-2 border-t bg-card/95 px-5 py-4 backdrop-blur">
                <Link :href="route('rkpd.index')" class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-medium hover:bg-muted">
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
