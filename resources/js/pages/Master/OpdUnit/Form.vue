<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import OrganizationWorkspaceTabs from '@/components/OrganizationWorkspaceTabs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import Info from 'lucide-vue-next/dist/esm/icons/info.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import { computed, watch } from 'vue';

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

const defaultOpdId = props.item?.opd_id ?? (props.opdOptions.length === 1 ? props.opdOptions[0].id : '');

const form = useForm<UnitForm>({
    opd_id: defaultOpdId,
    parent_id: props.item?.parent_id ?? '',
    kode: props.item?.kode ?? '',
    nama: props.item?.nama ?? '',
    jenis_unit: props.item?.jenis_unit ?? '',
    nama_pimpinan: props.item?.nama_pimpinan ?? '',
    nip_pimpinan: props.item?.nip_pimpinan ?? '',
    status: props.item?.status ?? 'active',
});

const filteredParents = computed(() => props.parentOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));

watch(
    () => form.opd_id,
    () => {
        if (form.parent_id && !filteredParents.value.some((option) => Number(option.id) === Number(form.parent_id))) form.parent_id = '';
    },
);

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.opd-units.store'));
        return;
    }

    form.put(route('master.opd-units.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Unit Kerja' : 'Edit Unit Kerja'" />
    <form class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6" @submit.prevent="submit">
        <OrganizationWorkspaceTabs active="units" />
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-800 text-white dark:bg-blue-600"><Building2 class="size-5" /></div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">Unit kerja</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ mode === 'create' ? 'Tambah Unit Kerja' : 'Edit Unit Kerja' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Tempatkan unit pada OPD dan induk yang tepat untuk membentuk hierarki.</p>
                </div>
            </div>
            <Link :href="route('master.opd-units.index')" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-card px-4 text-sm font-medium hover:bg-muted"><ArrowLeft class="size-4" /> Kembali</Link>
        </header>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b px-5 py-4"><h2 class="font-semibold">Identitas unit</h2><p class="mt-1 text-xs text-muted-foreground">Kode harus unik di dalam satu perangkat daerah.</p></div>
            <div class="grid gap-5 p-5 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="opd_id">OPD</label>
                    <select
                        id="opd_id"
                        v-model="form.opd_id"
                        :disabled="opdOptions.length === 1"
                        class="h-10 rounded-lg border bg-background px-3 text-sm disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground"
                    >
                        <option value="">Pilih OPD</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="kode">Kode Unit</label>
                    <input id="kode" v-model="form.kode" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: BID.01" />
                    <InputError :message="form.errors.kode" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="jenis_unit">Jenis Unit</label>
                    <select id="jenis_unit" v-model="form.jenis_unit" class="h-10 rounded-lg border bg-background px-3 text-sm">
                        <option value="">Pilih jenis</option>
                        <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.jenis_unit" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="nama">Nama Unit</label>
                    <input id="nama" v-model="form.nama" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: Bidang Pengelolaan Informasi" />
                    <InputError :message="form.errors.nama" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="parent_id">Induk Unit</label>
                    <select id="parent_id" v-model="form.parent_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                        <option value="">Tanpa induk</option>
                        <option v-for="option in filteredParents" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select id="status" v-model="form.status" class="h-10 rounded-lg border bg-background px-3 text-sm">
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak aktif</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
            </div>
        </section>

        <div class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50/60 p-4 text-sm text-blue-950 dark:border-blue-900 dark:bg-blue-950/25 dark:text-blue-100">
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>Pimpinan unit tidak diisi di sini. Tentukan <strong>jabatan struktural</strong> pada unit ini, lalu tempatkan pegawainya agar data pimpinan dan riwayat masa tugas tetap konsisten.</p>
        </div>

        <div class="flex justify-end gap-2">
            <Link :href="route('master.opd-units.index')" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm font-medium hover:bg-muted">Batal</Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-800 px-5 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-60 dark:bg-blue-600"
            >
                <Save class="size-4" /> {{ form.processing ? 'Menyimpan...' : 'Simpan Unit' }}
            </button>
        </div>
    </form>
</template>
