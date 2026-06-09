<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type SatuanForm = {
    nama: string;
    simbol: string;
    jenis: string;
    deskripsi: string;
    status: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (SatuanForm & { id: number }) | null;
    jenisOptions: Array<{ value: string; label: string }>;
}>();

const form = useForm<SatuanForm>({
    nama: props.item?.nama ?? '',
    simbol: props.item?.simbol ?? '',
    jenis: props.item?.jenis ?? '',
    deskripsi: props.item?.deskripsi ?? '',
    status: props.item?.status ?? 'active',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.satuan-indikator.store'));
        return;
    }

    form.put(route('master.satuan-indikator.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Satuan Indikator' : 'Edit Satuan Indikator'" />
    <form class="flex max-w-3xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Satuan Indikator' : 'Edit Satuan Indikator' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Satuan dipakai pada indikator RPJMD, Renstra, PK, dan realisasi.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="nama">Nama Satuan</label>
                    <input id="nama" v-model="form.nama" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nama" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="simbol">Simbol</label>
                    <input id="simbol" v-model="form.simbol" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.simbol" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="jenis">Jenis</label>
                    <select id="jenis" v-model="form.jenis" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih jenis</option>
                        <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.jenis" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak aktif</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" v-model="form.deskripsi" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.deskripsi" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('master.satuan-indikator.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
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
