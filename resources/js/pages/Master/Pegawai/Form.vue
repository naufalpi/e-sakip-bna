<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import ChevronDown from 'lucide-vue-next/dist/esm/icons/chevron-down.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import UserRound from 'lucide-vue-next/dist/esm/icons/user-round.js';
import { computed, watch } from 'vue';

type Id = number | '';
type Option = {
    id?: number;
    value?: string;
    label: string;
    opd_id?: number | null;
    opd_unit_id?: number | null;
    level_label?: string;
    verification_status?: string;
};
type FormData = {
    opd_id: Id;
    opd_unit_id: Id;
    user_id: Id;
    nama: string;
    nip: string;
    pangkat_golongan: string;
    jenis_pegawai: string;
    status: string;
    jabatan_organisasi_id: Id;
    jenis_penugasan: string;
    nomor_sk: string;
    tanggal_sk: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (FormData & { id: number }) | null;
    opdOptions: Option[];
    unitOptions: Option[];
    userOptions: Option[];
    jenisOptions: Option[];
    jabatanOptions: Option[];
    penugasanOptions: Option[];
    scopeLocked: boolean;
    canManageJobs: boolean;
}>();

const form = useForm<FormData>({
    opd_id: props.item?.opd_id ?? (props.opdOptions.length === 1 ? Number(props.opdOptions[0].id) : ''),
    opd_unit_id: props.item?.opd_unit_id ?? '',
    user_id: props.item?.user_id ?? '',
    nama: props.item?.nama ?? '',
    nip: props.item?.nip ?? '',
    pangkat_golongan: props.item?.pangkat_golongan ?? '',
    jenis_pegawai: props.item?.jenis_pegawai ?? 'pns',
    status: props.item?.status ?? 'active',
    jabatan_organisasi_id: '',
    jenis_penugasan: 'definitif',
    nomor_sk: '',
    tanggal_sk: '',
    tanggal_mulai: new Date().toISOString().slice(0, 10),
    tanggal_selesai: '',
});

const filteredUnits = computed(() => props.unitOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));
const filteredUsers = computed(() => props.userOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));
const filteredJabatan = computed(() => props.jabatanOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));

watch(
    () => form.opd_id,
    () => {
        if (form.opd_unit_id && !filteredUnits.value.some((option) => Number(option.id) === Number(form.opd_unit_id))) form.opd_unit_id = '';
        if (form.user_id && !filteredUsers.value.some((option) => Number(option.id) === Number(form.user_id))) form.user_id = '';
        if (form.jabatan_organisasi_id && !filteredJabatan.value.some((option) => Number(option.id) === Number(form.jabatan_organisasi_id))) {
            form.jabatan_organisasi_id = '';
        }
    },
);

watch(
    () => form.jabatan_organisasi_id,
    (value) => {
        if (props.mode !== 'create' || !value) return;
        const jabatan = props.jabatanOptions.find((option) => Number(option.id) === Number(value));
        form.opd_unit_id = jabatan?.opd_unit_id ? Number(jabatan.opd_unit_id) : '';
    },
);

const submit = () => (props.mode === 'create' ? form.post(route('master.pegawai.store')) : form.put(route('master.pegawai.update', props.item?.id)));
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Pegawai' : 'Edit Pegawai'" />

    <form class="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6" @submit.prevent="submit">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-800 text-white shadow-sm dark:bg-blue-600">
                    <UserRound class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-blue-700 dark:text-blue-300">Pegawai OPD</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ mode === 'create' ? 'Tambah Pegawai' : 'Edit Pegawai' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ mode === 'create' ? 'Isi data pegawai dan pilih jabatannya dalam satu langkah.' : 'Perbarui data utama pegawai.' }}
                    </p>
                    <Link
                        v-if="mode === 'create' && canManageJobs"
                        :href="route('master.jabatan-organisasi.index')"
                        class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:text-blue-900 dark:text-blue-300"
                    >
                        <BriefcaseBusiness class="size-3.5" /> Jabatan belum tersedia? Kelola jabatan OPD
                    </Link>
                </div>
            </div>
            <Link
                :href="item ? route('master.pegawai.show', item.id) : route('master.pegawai.index')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium hover:bg-muted"
            >
                <ArrowLeft class="size-4" /> Kembali
            </Link>
        </header>

        <section class="overflow-hidden rounded-2xl border bg-card shadow-sm shadow-slate-200/40 dark:shadow-none">
            <div class="h-1 bg-gradient-to-r from-blue-800 via-blue-600 to-cyan-500 dark:from-blue-500 dark:to-cyan-400" />
            <div class="grid gap-5 p-5 md:grid-cols-2 md:p-7">
                <div class="grid gap-2 md:col-span-2">
                    <label for="nama" class="text-sm font-medium">Nama lengkap <span class="text-red-600">*</span></label>
                    <input
                        id="nama"
                        v-model="form.nama"
                        required
                        class="h-11 rounded-lg border bg-background px-3.5 text-sm"
                        placeholder="Nama lengkap pegawai"
                    />
                    <InputError :message="form.errors.nama" />
                </div>

                <div class="grid gap-2">
                    <label for="nip" class="text-sm font-medium">NIP</label>
                    <input
                        id="nip"
                        v-model="form.nip"
                        class="h-11 rounded-lg border bg-background px-3.5 text-sm"
                        placeholder="Kosongkan jika tidak ada"
                    />
                    <InputError :message="form.errors.nip" />
                </div>
                <div class="grid gap-2">
                    <label for="jenis_pegawai" class="text-sm font-medium">Jenis pegawai <span class="text-red-600">*</span></label>
                    <select id="jenis_pegawai" v-model="form.jenis_pegawai" required class="h-11 rounded-lg border bg-background px-3.5 text-sm">
                        <option v-for="option in jenisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.jenis_pegawai" />
                </div>

                <div class="grid gap-2 md:col-span-2">
                    <label for="opd_id" class="text-sm font-medium">Perangkat daerah <span class="text-red-600">*</span></label>
                    <select
                        id="opd_id"
                        v-model="form.opd_id"
                        required
                        :disabled="scopeLocked"
                        class="h-11 rounded-lg border bg-background px-3.5 text-sm disabled:opacity-70"
                    >
                        <option value="">Pilih perangkat daerah</option>
                        <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_id" />
                </div>

                <template v-if="mode === 'create'">
                    <div class="grid gap-2 md:col-span-2">
                        <label for="jabatan" class="flex items-center gap-2 text-sm font-medium">
                            <BriefcaseBusiness class="size-4 text-blue-700 dark:text-blue-300" />
                            Jabatan <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="jabatan"
                            v-model="form.jabatan_organisasi_id"
                            required
                            :disabled="!form.opd_id"
                            class="h-11 rounded-lg border bg-background px-3.5 text-sm disabled:opacity-60"
                        >
                            <option value="">{{ form.opd_id ? 'Pilih jabatan' : 'Pilih perangkat daerah dahulu' }}</option>
                            <option v-for="option in filteredJabatan" :key="option.id" :value="option.id">
                                {{ option.label }} · {{ option.level_label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.jabatan_organisasi_id" />
                    </div>
                    <div class="grid gap-2">
                        <label for="tanggal_mulai" class="text-sm font-medium">TMT jabatan <span class="text-red-600">*</span></label>
                        <input
                            id="tanggal_mulai"
                            v-model="form.tanggal_mulai"
                            required
                            type="date"
                            class="h-11 rounded-lg border bg-background px-3.5 text-sm"
                        />
                        <InputError :message="form.errors.tanggal_mulai" />
                    </div>
                    <div class="grid gap-2">
                        <label for="jenis_penugasan" class="text-sm font-medium">Status jabatan</label>
                        <select id="jenis_penugasan" v-model="form.jenis_penugasan" class="h-11 rounded-lg border bg-background px-3.5 text-sm">
                            <option v-for="option in penugasanOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.jenis_penugasan" />
                    </div>
                </template>

                <details class="group border-t pt-4 md:col-span-2">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between rounded-lg py-1 text-sm font-semibold text-muted-foreground hover:text-foreground"
                    >
                        <span>Data tambahan <span class="font-normal">(opsional)</span></span>
                        <ChevronDown class="size-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <label for="pangkat" class="text-sm font-medium">Pangkat / golongan</label>
                            <input
                                id="pangkat"
                                v-model="form.pangkat_golongan"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="Contoh: Penata Tk. I, III/d"
                            />
                            <InputError :message="form.errors.pangkat_golongan" />
                        </div>

                        <template v-if="mode === 'create'">
                            <div class="grid gap-2">
                                <label for="nomor_sk" class="text-sm font-medium">Nomor SK / surat perintah</label>
                                <input id="nomor_sk" v-model="form.nomor_sk" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                                <InputError :message="form.errors.nomor_sk" />
                            </div>
                            <div class="grid gap-2">
                                <label for="tanggal_sk" class="text-sm font-medium">Tanggal SK</label>
                                <input
                                    id="tanggal_sk"
                                    v-model="form.tanggal_sk"
                                    type="date"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                                />
                                <InputError :message="form.errors.tanggal_sk" />
                            </div>
                        </template>

                        <div v-if="mode === 'edit'" class="grid gap-2">
                            <label for="opd_unit_id" class="text-sm font-medium">Unit organisasi</label>
                            <select id="opd_unit_id" v-model="form.opd_unit_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option value="">Belum ditentukan</option>
                                <option v-for="option in filteredUnits" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.opd_unit_id" />
                        </div>
                        <div v-if="mode === 'edit'" class="grid gap-2">
                            <label for="status" class="text-sm font-medium">Status pegawai</label>
                            <select id="status" v-model="form.status" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <label for="user_id" class="text-sm font-medium">Akun aplikasi</label>
                            <select id="user_id" v-model="form.user_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option value="">Tidak dihubungkan ke akun</option>
                                <option v-for="option in filteredUsers" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.user_id" />
                        </div>
                    </div>
                </details>
            </div>
        </section>

        <div class="flex justify-end gap-2 border-t pt-4">
            <Link
                :href="item ? route('master.pegawai.show', item.id) : route('master.pegawai.index')"
                class="inline-flex h-10 items-center rounded-lg border bg-card px-4 text-sm font-medium hover:bg-muted"
            >
                Batal
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-800 px-5 text-sm font-semibold text-white shadow-sm hover:bg-blue-900 disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
            >
                <Save class="size-4" />{{ form.processing ? 'Menyimpan...' : mode === 'create' ? 'Simpan Pegawai' : 'Simpan Perubahan' }}
            </button>
        </div>
    </form>
</template>
