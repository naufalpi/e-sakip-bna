<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type UnitForm = {
    opd_id: number | string;
    parent_id: number | string | null;
    kode: string;
    nama: string;
    jenis_unit: string;
    nama_pimpinan: string;
    nip_pimpinan: string;
    status: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (UnitForm & { id: number }) | null;
    opdOptions: Array<{ id: number; label: string }>;
    parentOptions: Array<{ id: number; opd_id: number; label: string }>;
    jenisOptions: Array<{ value: string; label: string }>;
}>();

const form = useForm<UnitForm>({
    opd_id: props.item?.opd_id ?? '',
    parent_id: props.item?.parent_id ?? '',
    kode: props.item?.kode ?? '',
    nama: props.item?.nama ?? '',
    jenis_unit: props.item?.jenis_unit ?? '',
    nama_pimpinan: props.item?.nama_pimpinan ?? '',
    nip_pimpinan: props.item?.nip_pimpinan ?? '',
    status: props.item?.status ?? 'active',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.opd-units.store'));
        return;
    }

    form.put(route('master.opd-units.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Unit OPD' : 'Edit Unit OPD'" />
    <form class="flex max-w-4xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Unit OPD' : 'Edit Unit OPD' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Unit dipakai untuk struktur kerja, penanggung jawab rencana aksi, dan pembagian bukti dukung.
            </p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Identitas Unit</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="opd_id">OPD</label>
                    <select id="opd_id" v-model="form.opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="kode">Kode Unit</label>
                    <input id="kode" v-model="form.kode" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.kode" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="jenis_unit">Jenis Unit</label>
                    <select id="jenis_unit" v-model="form.jenis_unit" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih jenis</option>
                        <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.jenis_unit" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="nama">Nama Unit</label>
                    <input id="nama" v-model="form.nama" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nama" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="parent_id">Induk Unit</label>
                    <select id="parent_id" v-model="form.parent_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tanpa induk</option>
                        <option v-for="option in parentOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak aktif</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Pimpinan Unit</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="nama_pimpinan">Nama Pimpinan</label>
                    <input id="nama_pimpinan" v-model="form.nama_pimpinan" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nama_pimpinan" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="nip_pimpinan">NIP Pimpinan</label>
                    <input id="nip_pimpinan" v-model="form.nip_pimpinan" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nip_pimpinan" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('master.opd-units.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
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
