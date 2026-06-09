<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type PeriodeForm = {
    tahun: number | string;
    nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    status: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (PeriodeForm & { id: number }) | null;
}>();

const form = useForm<PeriodeForm>({
    tahun: props.item?.tahun ?? new Date().getFullYear(),
    nama: props.item?.nama ?? '',
    tanggal_mulai: props.item?.tanggal_mulai ?? '',
    tanggal_selesai: props.item?.tanggal_selesai ?? '',
    status: props.item?.status ?? 'draft',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.periode-tahun.store'));
        return;
    }

    form.put(route('master.periode-tahun.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Periode Tahun' : 'Edit Periode Tahun'" />
    <form class="flex max-w-3xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Periode Tahun' : 'Edit Periode Tahun' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Gunakan status terkunci saat periode tidak boleh lagi diubah oleh user biasa.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tahun">Tahun</label>
                    <input
                        id="tahun"
                        v-model="form.tahun"
                        type="number"
                        min="2000"
                        max="2100"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    />
                    <InputError :message="form.errors.tahun" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="draft">Draft</option>
                        <option value="active">Aktif</option>
                        <option value="locked">Terkunci</option>
                        <option value="archived">Arsip</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="nama">Nama Periode</label>
                    <input id="nama" v-model="form.nama" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nama" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tanggal_mulai">Tanggal Mulai</label>
                    <input id="tanggal_mulai" v-model="form.tanggal_mulai" type="date" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.tanggal_mulai" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="tanggal_selesai">Tanggal Selesai</label>
                    <input id="tanggal_selesai" v-model="form.tanggal_selesai" type="date" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.tanggal_selesai" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('master.periode-tahun.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
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
