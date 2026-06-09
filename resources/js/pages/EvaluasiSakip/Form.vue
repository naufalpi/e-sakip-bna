<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Option = { id?: number; value?: string; tahun?: number; label: string };
type FormData = {
    opd_id: number | string | null;
    periode_tahun_id: number | string | null;
    tahun: number | string;
    tanggal_evaluasi: string;
    status: string;
    catatan_umum: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    evaluasi: (FormData & { id: number }) | null;
    opdOptions: Option[];
    periodeOptions: Option[];
    statusOptions: Option[];
}>();

const form = useForm<FormData>({
    opd_id: props.evaluasi?.opd_id ?? '',
    periode_tahun_id: props.evaluasi?.periode_tahun_id ?? '',
    tahun: props.evaluasi?.tahun ?? new Date().getFullYear(),
    tanggal_evaluasi: props.evaluasi?.tanggal_evaluasi ?? '',
    status: props.evaluasi?.status ?? 'draft',
    catatan_umum: props.evaluasi?.catatan_umum ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('evaluasi-sakip.store'));
        return;
    }

    form.put(route('evaluasi-sakip.update', props.evaluasi?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Evaluasi SAKIP' : 'Edit Evaluasi SAKIP'" />
    <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Evaluasi SAKIP' : 'Edit Evaluasi SAKIP' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Tentukan OPD dan periode evaluasi sebelum mengisi nilai kriteria, LHE, dan rekomendasi.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Identitas Evaluasi</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="opd_id">OPD</label>
                    <select id="opd_id" v-model="form.opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="periode_tahun_id">Periode Tahun</label>
                    <select id="periode_tahun_id" v-model="form.periode_tahun_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih periode</option>
                        <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.periode_tahun_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tahun">Tahun Evaluasi</label>
                    <input id="tahun" v-model="form.tahun" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.tahun" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tanggal_evaluasi">Tanggal Evaluasi</label>
                    <input
                        id="tanggal_evaluasi"
                        v-model="form.tanggal_evaluasi"
                        type="date"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    />
                    <InputError :message="form.errors.tanggal_evaluasi" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="catatan_umum">Catatan Umum</label>
                    <textarea id="catatan_umum" v-model="form.catatan_umum" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.catatan_umum" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('evaluasi-sakip.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
            >
                Simpan
            </button>
        </div>
    </form>
</template>
