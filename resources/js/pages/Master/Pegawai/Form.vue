<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { confirmAction } from '@/lib/sweetAlert';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import BadgeCheck from 'lucide-vue-next/dist/esm/icons/badge-check.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import UserRoundX from 'lucide-vue-next/dist/esm/icons/user-round-x.js';
import UserRound from 'lucide-vue-next/dist/esm/icons/user-round.js';
import { computed, watch } from 'vue';

type Id = number | '';
type Option = {
    id?: number;
    value?: string;
    label: string;
    opd_id?: number | null;
    opd_unit_id?: number | null;
    level_jabatan?: string;
    level_label?: string;
    verification_status?: string;
};
type FormData = {
    opd_id: Id;
    user_id: Id;
    nama: string;
    nip: string;
    pangkat_golongan: string;
    jenis_pegawai: string;
    status: string;
    jabatan_organisasi_id: Id;
    jenis_penugasan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (FormData & { id: number }) | null;
    opdOptions: Option[];
    userOptions: Option[];
    jenisOptions: Option[];
    jabatanOptions: Option[];
    penugasanOptions: Option[];
    scopeLocked: boolean;
    isKepalaDaerah: boolean;
    canManageJobs: boolean;
}>();

const form = useForm<FormData>({
    opd_id: props.isKepalaDaerah ? '' : (props.item?.opd_id ?? (props.opdOptions.length === 1 ? Number(props.opdOptions[0].id) : '')),
    user_id: props.item?.user_id ?? '',
    nama: props.item?.nama ?? '',
    nip: props.item?.nip ?? '',
    pangkat_golongan: props.item?.pangkat_golongan ?? '',
    jenis_pegawai: props.item?.jenis_pegawai ?? 'pns',
    status: props.item?.status ?? 'active',
    jabatan_organisasi_id: props.item?.jabatan_organisasi_id ?? '',
    jenis_penugasan: props.item?.jenis_penugasan ?? 'definitif',
});

const filteredUsers = computed(() => props.userOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));
const selectedJabatan = computed(() => props.jabatanOptions.find((option) => Number(option.id) === Number(form.jabatan_organisasi_id)));
const isKepalaDaerahIdentity = computed(() => props.isKepalaDaerah || selectedJabatan.value?.level_jabatan === 'kepala_daerah');
const hasKepalaDaerahOption = computed(() => props.jabatanOptions.some((option) => option.level_jabatan === 'kepala_daerah'));
const filteredJabatan = computed(() =>
    props.jabatanOptions.filter((option) => option.level_jabatan === 'kepala_daerah' || Number(option.opd_id) === Number(form.opd_id)),
);

watch(
    () => form.opd_id,
    () => {
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
        if (jabatan?.level_jabatan === 'kepala_daerah') {
            form.opd_id = '';
        }
    },
);

watch(
    () => form.status,
    (status) => {
        if (props.mode === 'create' && status === 'inactive') form.jabatan_organisasi_id = '';
    },
);

const submit = async () => {
    if (props.mode === 'edit' && props.item?.status === 'active' && form.status === 'inactive') {
        const confirmed = await confirmAction({
            title: 'Nonaktifkan pegawai?',
            text: 'Jabatan aktif akan ditutup. Riwayat dan dokumen lama tetap tersimpan. Akun aplikasi tidak ikut dinonaktifkan.',
            icon: 'warning',
            confirmButtonText: 'Ya, nonaktifkan',
            destructive: true,
        });

        if (!confirmed) return;
    }

    if (props.mode === 'create') {
        form.post(route('master.pegawai.store'));
        return;
    }

    form.put(route('master.pegawai.update', props.item?.id));
};
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

                <div class="grid gap-2">
                    <label for="pangkat" class="text-sm font-medium">Pangkat / golongan</label>
                    <input
                        id="pangkat"
                        v-model="form.pangkat_golongan"
                        class="h-11 rounded-lg border bg-background px-3.5 text-sm"
                        placeholder="Contoh: Penata Tk. I, III/d"
                    />
                    <InputError :message="form.errors.pangkat_golongan" />
                </div>

                <div v-if="!isKepalaDaerahIdentity" class="grid gap-2 md:col-span-2">
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

                <div class="grid gap-2 md:col-span-2">
                    <span class="text-sm font-medium">Status pegawai <span class="text-red-600">*</span></span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                            :class="
                                form.status === 'active'
                                    ? 'border-emerald-400 bg-emerald-50/70 ring-2 ring-emerald-100 dark:bg-emerald-950/25 dark:ring-emerald-900/50'
                                    : 'hover:border-emerald-300 hover:bg-emerald-50/30'
                            "
                        >
                            <input v-model="form.status" class="sr-only" type="radio" value="active" />
                            <BadgeCheck class="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-300" />
                            <span>
                                <span class="block text-sm font-semibold">Aktif</span>
                                <span class="mt-1 block text-xs leading-5 text-muted-foreground"
                                    >Dapat diberi jabatan dan dipilih pada PK atau dokumen baru.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                            :class="
                                form.status === 'inactive'
                                    ? 'border-slate-400 bg-slate-100/80 ring-2 ring-slate-200 dark:bg-slate-900/60 dark:ring-slate-800'
                                    : 'hover:border-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900/30'
                            "
                        >
                            <input v-model="form.status" class="sr-only" type="radio" value="inactive" />
                            <UserRoundX class="mt-0.5 size-5 shrink-0 text-slate-600 dark:text-slate-300" />
                            <span>
                                <span class="block text-sm font-semibold">Nonaktif</span>
                                <span class="mt-1 block text-xs leading-5 text-muted-foreground"
                                    >Tidak tersedia untuk data baru; riwayat dan snapshot dokumen tetap tersimpan.</span
                                >
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.status" />
                </div>

                <template v-if="form.status === 'active'">
                    <div class="grid gap-2 md:col-span-2">
                        <label for="jabatan" class="flex items-center gap-2 text-sm font-medium">
                            <BriefcaseBusiness class="size-4 text-blue-700 dark:text-blue-300" />
                            Jabatan <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="jabatan"
                            v-model="form.jabatan_organisasi_id"
                            required
                            :disabled="!form.opd_id && !hasKepalaDaerahOption"
                            class="h-11 rounded-lg border bg-background px-3.5 text-sm disabled:opacity-60"
                        >
                            <option value="">
                                {{ form.opd_id || hasKepalaDaerahOption ? 'Pilih jabatan' : 'Pilih perangkat daerah dahulu' }}
                            </option>
                            <option v-for="option in filteredJabatan" :key="option.id" :value="option.id">
                                {{ option.label }} · {{ option.level_label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.jabatan_organisasi_id" />
                        <p v-if="mode === 'edit'" class="text-xs leading-5 text-muted-foreground">
                            Jika jabatan diganti, jabatan lama akan ditutup dan tetap tersimpan sebagai riwayat.
                        </p>
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label for="jenis_penugasan" class="text-sm font-medium">Status jabatan</label>
                        <select id="jenis_penugasan" v-model="form.jenis_penugasan" class="h-11 rounded-lg border bg-background px-3.5 text-sm">
                            <option v-for="option in penugasanOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.jenis_penugasan" />
                    </div>
                </template>

                <div class="grid gap-2 border-t pt-5 md:col-span-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="user_id" class="text-sm font-medium">Akun aplikasi</label>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                            >Opsional</span
                        >
                    </div>
                    <select id="user_id" v-model="form.user_id" class="h-11 rounded-lg border bg-background px-3.5 text-sm">
                        <option value="">Tidak dihubungkan ke akun</option>
                        <option v-for="option in filteredUsers" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                    <p class="text-xs leading-5 text-muted-foreground">
                        Hubungkan hanya jika pegawai perlu masuk ke aplikasi atau menyetujui dokumen sendiri. Pegawai tetap dapat dikelola Admin OPD
                        tanpa akun.
                    </p>
                    <p v-if="form.status === 'inactive' && form.user_id" class="text-xs font-medium text-amber-700 dark:text-amber-300">
                        Menonaktifkan data pegawai tidak otomatis menonaktifkan akun login. Kelola akses akun secara terpisah pada Master Pengguna.
                    </p>
                    <InputError :message="form.errors.user_id" />
                </div>
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
