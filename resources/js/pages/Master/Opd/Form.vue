<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type OpdForm = {
    urusan_pemerintahan_id: number | string | null;
    kode: string;
    nama: string;
    singkatan: string;
    jenis: string;
    alamat: string;
    telepon: string;
    email: string;
    nama_kepala: string;
    nip_kepala: string;
    status: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    opd: (OpdForm & { id: number }) | null;
    urusanOptions: Array<{ id: number; label: string }>;
}>();

const form = useForm<OpdForm>({
    urusan_pemerintahan_id: props.opd?.urusan_pemerintahan_id ?? '',
    kode: props.opd?.kode ?? '',
    nama: props.opd?.nama ?? '',
    singkatan: props.opd?.singkatan ?? '',
    jenis: props.opd?.jenis ?? '',
    alamat: props.opd?.alamat ?? '',
    telepon: props.opd?.telepon ?? '',
    email: props.opd?.email ?? '',
    nama_kepala: props.opd?.nama_kepala ?? '',
    nip_kepala: props.opd?.nip_kepala ?? '',
    status: props.opd?.status ?? 'active',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.opd.store'));
        return;
    }

    form.put(route('master.opd.update', props.opd?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah OPD' : 'Edit OPD'" />
    <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah OPD' : 'Edit OPD' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Lengkapi identitas perangkat daerah dengan data resmi.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Identitas OPD</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="kode">Kode OPD</label>
                    <input id="kode" v-model="form.kode" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.kode" />
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
                    <label class="text-sm font-medium" for="nama">Nama OPD</label>
                    <input id="nama" v-model="form.nama" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nama" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="singkatan">Singkatan</label>
                    <input id="singkatan" v-model="form.singkatan" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.singkatan" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="jenis">Jenis OPD</label>
                    <input
                        id="jenis"
                        v-model="form.jenis"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        placeholder="Dinas, Badan, Kecamatan"
                    />
                    <InputError :message="form.errors.jenis" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="urusan">Urusan Pemerintahan</label>
                    <select id="urusan" v-model="form.urusan_pemerintahan_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="">Pilih urusan</option>
                        <option v-for="option in urusanOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.urusan_pemerintahan_id" />
                </div>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Kontak dan Pimpinan</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="alamat">Alamat</label>
                    <textarea id="alamat" v-model="form.alamat" rows="3" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    <InputError :message="form.errors.alamat" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="telepon">Telepon</label>
                    <input id="telepon" v-model="form.telepon" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.telepon" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="email">Email</label>
                    <input id="email" v-model="form.email" type="email" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="nama_kepala">Nama Kepala OPD</label>
                    <input id="nama_kepala" v-model="form.nama_kepala" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nama_kepala" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="nip_kepala">NIP Kepala OPD</label>
                    <input id="nip_kepala" v-model="form.nip_kepala" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.nip_kepala" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('master.opd.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
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
