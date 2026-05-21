<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

type UrusanForm = {
    kode: string;
    nama: string;
    deskripsi: string;
    status: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (UrusanForm & { id: number }) | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Urusan Pemerintahan', href: '/master/urusan-pemerintahan' },
    { title: props.mode === 'create' ? 'Tambah' : 'Edit', href: '#' },
];

const form = useForm<UrusanForm>({
    kode: props.item?.kode ?? '',
    nama: props.item?.nama ?? '',
    deskripsi: props.item?.deskripsi ?? '',
    status: props.item?.status ?? 'active',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.urusan-pemerintahan.store'));
        return;
    }

    form.put(route('master.urusan-pemerintahan.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Urusan Pemerintahan' : 'Edit Urusan Pemerintahan'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex max-w-3xl flex-col gap-4 p-4" @submit.prevent="submit">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Urusan Pemerintahan' : 'Edit Urusan Pemerintahan' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Kode urusan harus mengikuti referensi resmi agar mudah disinkronkan dengan data perencanaan.</p>
            </div>

            <section class="rounded-lg border bg-card p-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="kode">Kode Urusan</label>
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
                        <label class="text-sm font-medium" for="nama">Nama Urusan</label>
                        <input id="nama" v-model="form.nama" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.nama" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="deskripsi">Deskripsi</label>
                        <textarea id="deskripsi" v-model="form.deskripsi" rows="4" class="rounded-md border bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.deskripsi" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="route('master.urusan-pemerintahan.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
                <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Simpan</button>
            </div>
        </form>
    </AppLayout>
</template>
