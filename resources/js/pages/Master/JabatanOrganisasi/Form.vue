<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import Info from 'lucide-vue-next/dist/esm/icons/info.js';
import Network from 'lucide-vue-next/dist/esm/icons/network.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import { computed, watch } from 'vue';

type Id = number | '';
type Option = { id?: number; value?: string; label: string; opd_id?: number | null; level_jabatan?: string; rank?: number };
type JabatanForm = {
    opd_id: Id;
    opd_unit_id: Id;
    parent_id: Id;
    nama: string;
    level_jabatan: string;
    eselon: string;
    urutan: number | string;
    status: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (JabatanForm & { id: number }) | null;
    opdOptions: Option[];
    unitOptions: Option[];
    parentOptions: Option[];
    levelOptions: Option[];
    eselonOptions: Option[];
}>();

const form = useForm<JabatanForm>({
    opd_id: props.item?.opd_id ?? '',
    opd_unit_id: props.item?.opd_unit_id ?? '',
    parent_id: props.item?.parent_id ?? '',
    nama: props.item?.nama ?? '',
    level_jabatan: props.item?.level_jabatan ?? 'jpt_pratama',
    eselon: props.item?.eselon ?? '',
    urutan: props.item?.urutan ?? 0,
    status: props.item?.status ?? 'active',
});

const allowedParentLevels: Record<string, string[]> = {
    jpt_pratama: ['kepala_daerah'],
    administrator: ['jpt_pratama'],
    pengawas: ['jpt_pratama', 'administrator'],
    fungsional: ['jpt_pratama', 'administrator', 'pengawas', 'fungsional'],
    pelaksana: ['jpt_pratama', 'administrator', 'pengawas', 'fungsional'],
};

const isKepalaDaerah = computed(() => form.level_jabatan === 'kepala_daerah');
const filteredUnits = computed(() => props.unitOptions.filter((option) => Number(option.opd_id) === Number(form.opd_id)));
const filteredParents = computed(() =>
    props.parentOptions.filter((option) => {
        if (!allowedParentLevels[form.level_jabatan]?.includes(option.level_jabatan ?? '')) return false;
        if (option.level_jabatan === 'kepala_daerah') return true;
        return Number(option.opd_id) === Number(form.opd_id);
    }),
);

watch(
    () => form.level_jabatan,
    (level) => {
        if (level === 'kepala_daerah') {
            form.opd_id = '';
            form.opd_unit_id = '';
            form.parent_id = '';
            form.eselon = '';
        }
    },
);

watch(
    () => form.opd_id,
    () => {
        if (form.opd_unit_id && !filteredUnits.value.some((option) => Number(option.id) === Number(form.opd_unit_id))) form.opd_unit_id = '';
        if (form.parent_id && !filteredParents.value.some((option) => Number(option.id) === Number(form.parent_id))) form.parent_id = '';
    },
);

watch(
    () => form.level_jabatan,
    () => {
        if (form.parent_id && !filteredParents.value.some((option) => Number(option.id) === Number(form.parent_id))) form.parent_id = '';
    },
);

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.jabatan-organisasi.store'));
        return;
    }
    form.put(route('master.jabatan-organisasi.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Jabatan Organisasi' : 'Edit Jabatan Organisasi'" />

    <form class="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6" @submit.prevent="submit">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-800 text-white shadow-sm dark:bg-blue-600">
                    <BriefcaseBusiness class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.17em] text-blue-700 dark:text-blue-300">Struktur organisasi</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ mode === 'create' ? 'Tambah Jabatan' : 'Edit Jabatan' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Tetapkan posisi, level, unit, dan atasan langsungnya.</p>
                </div>
            </div>
            <Link
                :href="item ? route('master.jabatan-organisasi.show', item.id) : route('master.jabatan-organisasi.index')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium hover:bg-muted"
                ><ArrowLeft class="size-4" /> Kembali</Link
            >
        </header>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_290px]">
            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="border-b px-5 py-4">
                    <h2 class="font-semibold">Identitas jabatan</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Gunakan nomenklatur jabatan dalam struktur organisasi resmi.</p>
                </div>
                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <label for="level_jabatan" class="text-sm font-medium">Level jabatan <span class="text-red-600">*</span></label>
                        <select
                            id="level_jabatan"
                            v-model="form.level_jabatan"
                            class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
                        >
                            <option v-for="option in levelOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.level_jabatan" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label for="nama" class="text-sm font-medium">Nama jabatan <span class="text-red-600">*</span></label>
                        <input
                            id="nama"
                            v-model="form.nama"
                            class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
                            placeholder="Contoh: Kepala Dinas Komunikasi dan Informatika"
                        />
                        <InputError :message="form.errors.nama" />
                    </div>

                    <template v-if="!isKepalaDaerah">
                        <div class="grid gap-2 md:col-span-2">
                            <label for="opd_id" class="text-sm font-medium">Perangkat daerah <span class="text-red-600">*</span></label>
                            <select
                                id="opd_id"
                                v-model="form.opd_id"
                                class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
                            >
                                <option value="">Pilih perangkat daerah</option>
                                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.opd_id" />
                        </div>
                        <div class="grid gap-2">
                            <label for="opd_unit_id" class="text-sm font-medium">Unit organisasi</label>
                            <select
                                id="opd_unit_id"
                                v-model="form.opd_unit_id"
                                :disabled="!form.opd_id"
                                class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Tanpa unit khusus</option>
                                <option v-for="option in filteredUnits" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.opd_unit_id" />
                        </div>
                        <div class="grid gap-2">
                            <label for="eselon" class="text-sm font-medium"
                                >Eselon <span class="font-normal text-muted-foreground">(metadata lama)</span></label
                            >
                            <select
                                id="eselon"
                                v-model="form.eselon"
                                class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600"
                            >
                                <option value="">Tidak diisi</option>
                                <option v-for="option in eselonOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.eselon" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <label for="parent_id" class="text-sm font-medium">Atasan langsung <span class="text-red-600">*</span></label>
                            <select
                                id="parent_id"
                                v-model="form.parent_id"
                                :disabled="!form.opd_id"
                                class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Pilih atasan langsung</option>
                                <option v-for="option in filteredParents" :key="option.id" :value="option.id">{{ option.label }}</option>
                            </select>
                            <InputError :message="form.errors.parent_id" />
                            <p v-if="form.opd_id && filteredParents.length === 0" class="text-xs text-amber-700 dark:text-amber-300">
                                Tambahkan dahulu jabatan atasan pada level di atasnya.
                            </p>
                        </div>
                    </template>

                    <div class="grid gap-2">
                        <label for="urutan" class="text-sm font-medium">Urutan tampil</label>
                        <input
                            id="urutan"
                            v-model="form.urutan"
                            type="number"
                            min="0"
                            max="65535"
                            class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600"
                        />
                        <InputError :message="form.errors.urutan" />
                    </div>
                    <div class="grid gap-2">
                        <label for="status" class="text-sm font-medium">Status</label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus:border-blue-600"
                        >
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-xl border bg-card p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <Network class="size-4 text-blue-700 dark:text-blue-300" /> Rantai PK
                    </div>
                    <ol class="mt-4 space-y-3 text-xs leading-5 text-muted-foreground">
                        <li><strong class="text-foreground">Kepala Daerah</strong><br />Puncak akuntabilitas kabupaten.</li>
                        <li><strong class="text-foreground">JPT Pratama</strong><br />Pimpinan perangkat daerah.</li>
                        <li>
                            <strong class="text-foreground">Administrator / Pengawas</strong><br />Penanggung jawab program, kegiatan, dan
                            subkegiatan.
                        </li>
                        <li><strong class="text-foreground">Fungsional / Pelaksana</strong><br />Ditempatkan sesuai pembagian kinerja aktual.</li>
                    </ol>
                </div>
                <div
                    class="flex gap-3 rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-xs leading-5 text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />
                    <p>
                        Nama pejabat tidak diisi di sini. Setelah jabatan disimpan, tambahkan pejabat beserta masa tugas dan dasar SK pada halaman
                        detail.
                    </p>
                </div>
            </aside>
        </div>

        <div class="flex justify-end gap-2 border-t pt-4">
            <Link
                :href="item ? route('master.jabatan-organisasi.show', item.id) : route('master.jabatan-organisasi.index')"
                class="inline-flex h-10 items-center rounded-lg border bg-card px-4 text-sm font-medium hover:bg-muted"
                >Batal</Link
            >
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-800 px-5 text-sm font-semibold text-white shadow-sm hover:bg-blue-900 disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
            >
                <Save class="size-4" />{{ form.processing ? 'Menyimpan...' : 'Simpan Jabatan' }}
            </button>
        </div>
    </form>
</template>
