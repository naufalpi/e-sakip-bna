<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import CalendarRange from 'lucide-vue-next/dist/esm/icons/calendar-range.js';
import CircleUserRound from 'lucide-vue-next/dist/esm/icons/circle-user-round.js';
import GitBranch from 'lucide-vue-next/dist/esm/icons/git-branch.js';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import X from 'lucide-vue-next/dist/esm/icons/x.js';
import { computed, ref } from 'vue';

type Option = { id?: number; value?: string; label: string; level_label?: string; multiple?: boolean; tahun?: number };
type Placement = {
    id: number;
    jabatan_organisasi_id: number;
    jabatan?: { id: number; nama: string; level_label: string; multiple: boolean } | null;
    jenis_penugasan: string;
    jenis_penugasan_label: string;
    nomor_sk?: string | null;
    tanggal_sk?: string | null;
    tanggal_mulai: string;
    tanggal_selesai?: string | null;
};
type Assignment = {
    id: number;
    periode_tahun_id: number;
    tahun: number;
    sumber_kinerja_type: string;
    sumber_kinerja_label: string;
    peran: string;
    status: string;
    periode_label?: string | null;
    jabatan_label?: string | null;
};
type Item = {
    id: number;
    nama: string;
    nip?: string | null;
    pangkat_golongan?: string | null;
    jenis_pegawai_label: string;
    status: string;
    opd?: { nama: string; singkatan?: string | null } | null;
    opd_unit?: { nama: string } | null;
    user?: { name: string; username?: string | null; email?: string | null } | null;
    current_placements: Placement[];
    penempatan: Placement[];
    penugasan_kinerja: Assignment[];
};

const props = defineProps<{
    item: Item;
    jabatanOptions: Option[];
    penugasanOptions: Option[];
    periodeOptions: Option[];
    sourceTypeOptions: Option[];
    cascadingOptions: Record<string, Option[]>;
    can: { manage: boolean; delete: boolean };
}>();

const placementEditorOpen = ref(false);
const editingPlacementId = ref<number | null>(null);
const placementForm = useForm({
    jabatan_organisasi_id: '' as number | '',
    jenis_penugasan: 'definitif',
    nomor_sk: '',
    tanggal_sk: '',
    tanggal_mulai: new Date().toISOString().slice(0, 10),
    tanggal_selesai: '',
});
const assignmentOpen = ref(false);
const assignmentForm = useForm({
    penempatan_pegawai_id: '' as number | '',
    periode_tahun_id: '' as number | '',
    sumber_kinerja_type: 'sub_kegiatan',
    sumber_kinerja_id: '' as number | '',
    peran: 'penanggung_jawab',
});
const selectedSources = computed(() => props.cascadingOptions[assignmentForm.sumber_kinerja_type] ?? []);

const openPlacement = (placement?: Placement) => {
    editingPlacementId.value = placement?.id ?? null;
    placementForm.defaults({
        jabatan_organisasi_id: placement?.jabatan_organisasi_id ?? '',
        jenis_penugasan: placement?.jenis_penugasan ?? 'definitif',
        nomor_sk: placement?.nomor_sk ?? '',
        tanggal_sk: placement?.tanggal_sk ?? '',
        tanggal_mulai: placement?.tanggal_mulai ?? new Date().toISOString().slice(0, 10),
        tanggal_selesai: placement?.tanggal_selesai ?? '',
    });
    placementForm.reset();
    placementForm.clearErrors();
    placementEditorOpen.value = true;
};
const closePlacement = () => {
    placementEditorOpen.value = false;
    editingPlacementId.value = null;
    placementForm.clearErrors();
};
const submitPlacement = () => {
    if (editingPlacementId.value) {
        placementForm.put(route('master.pegawai.penempatan.update', [props.item.id, editingPlacementId.value]), {
            preserveScroll: true,
            onSuccess: closePlacement,
        });
        return;
    }
    placementForm.post(route('master.pegawai.penempatan.store', props.item.id), { preserveScroll: true, onSuccess: closePlacement });
};
const submitAssignment = () =>
    assignmentForm.post(route('master.pegawai.pengampu-kinerja.store', props.item.id), {
        preserveScroll: true,
        onSuccess: () => {
            assignmentForm.reset();
            assignmentOpen.value = false;
        },
    });
const removePlacement = async (placement: Placement) => {
    if (await confirmDelete(`Hapus riwayat jabatan ${placement.jabatan?.nama}?`))
        router.delete(route('master.pegawai.penempatan.destroy', [props.item.id, placement.id]), { preserveScroll: true });
};
const removeAssignment = async (assignment: Assignment) => {
    if (await confirmDelete(`Hapus penugasan ${assignment.sumber_kinerja_label}?`))
        router.delete(route('master.pegawai.pengampu-kinerja.destroy', [props.item.id, assignment.id]), { preserveScroll: true });
};
const removeEmployee = async () => {
    if (await confirmDelete(`Hapus pegawai ${props.item.nama}? Data yang sudah memiliki riwayat akan ditolak sistem.`))
        router.delete(route('master.pegawai.destroy', props.item.id));
};
const formatDate = (value?: string | null) =>
    value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`)) : 'Sekarang';
const isCurrent = (placement: Placement) => {
    const today = new Date().toISOString().slice(0, 10);
    return placement.tanggal_mulai <= today && (!placement.tanggal_selesai || placement.tanggal_selesai >= today);
};
const sourceTypeLabel = (value: string) => props.sourceTypeOptions.find((option) => option.value === value)?.label ?? value;
</script>

<template>
    <Head :title="item.nama" />
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Link
                :href="route('master.pegawai.index')"
                class="inline-flex h-9 items-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium hover:bg-muted"
                ><ArrowLeft class="size-4" /> Pegawai OPD</Link
            >
            <div v-if="can.manage" class="flex items-center gap-2">
                <Link
                    :href="route('master.pegawai.edit', item.id)"
                    class="inline-flex h-9 items-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium hover:bg-muted"
                    ><Pencil class="size-3.5" /> Edit Identitas</Link
                >
                <button
                    v-if="can.delete"
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg border text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                    title="Hapus pegawai"
                    @click="removeEmployee"
                >
                    <Trash2 class="size-3.5" />
                </button>
            </div>
        </div>

        <header class="overflow-hidden rounded-2xl border bg-card">
            <div class="h-1 bg-gradient-to-r from-blue-800 via-cyan-600 to-emerald-500 dark:from-blue-500 dark:via-cyan-400 dark:to-emerald-400" />
            <div class="grid gap-6 p-5 md:p-7 lg:grid-cols-[minmax(0,1fr)_330px]">
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200"
                    >
                        <CircleUserRound class="size-7" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold tracking-tight">{{ item.nama }}</h1>
                            <span
                                class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                :class="
                                    item.status === 'active'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >{{ item.status === 'active' ? 'Aktif' : 'Nonaktif' }}</span
                            >
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ item.nip ? `NIP ${item.nip}` : 'NIP belum diisi'
                            }}<template v-if="item.pangkat_golongan"> · {{ item.pangkat_golongan }}</template>
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="rounded-full border px-2.5 py-1 text-xs font-medium">{{ item.jenis_pegawai_label }}</span
                            ><span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium">{{
                                item.opd?.singkatan || item.opd?.nama || 'Lingkup kabupaten'
                            }}</span>
                        </div>
                    </div>
                </div>
                <div class="grid content-start gap-3 rounded-xl border bg-muted/20 p-4 text-sm">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Unit organisasi</p>
                        <p class="mt-1 font-medium">{{ item.opd_unit?.nama || 'Belum ditentukan' }}</p>
                    </div>
                    <div class="border-t pt-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Akun aplikasi</p>
                        <p class="mt-1 font-medium">{{ item.user?.name || 'Tidak dihubungkan' }}</p>
                        <p v-if="item.user" class="text-xs text-muted-foreground">{{ item.user.username || item.user.email }}</p>
                    </div>
                </div>
            </div>
        </header>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <BriefcaseBusiness class="size-4 text-blue-700 dark:text-blue-300" />
                        <h2 class="font-semibold">Riwayat Jabatan</h2>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Jabatan struktural hanya dapat memiliki satu pemegang aktif; JF dan Pelaksana dapat memiliki banyak pegawai.
                    </p>
                </div>
                <button
                    v-if="can.manage && !placementEditorOpen"
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-blue-800 px-3 text-sm font-semibold text-white hover:bg-blue-900 dark:bg-blue-600"
                    @click="openPlacement()"
                >
                    <Plus class="size-4" /> Tambah Jabatan
                </button>
            </div>

            <form v-if="placementEditorOpen" class="border-b bg-muted/20 p-5" @submit.prevent="submitPlacement">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold">{{ editingPlacementId ? 'Edit jabatan' : 'Jabatan baru' }}</h3>
                        <p class="mt-1 text-xs text-muted-foreground">TMT menjadi dasar posisi pegawai pada periode PK.</p>
                    </div>
                    <button type="button" class="inline-flex size-8 items-center justify-center rounded-lg border bg-card" @click="closePlacement">
                        <X class="size-4" />
                    </button>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <label for="jabatan" class="text-sm font-medium">Jabatan <span class="text-red-600">*</span></label
                        ><select id="jabatan" v-model="placementForm.jabatan_organisasi_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                            <option value="">Pilih jabatan</option>
                            <option v-for="option in jabatanOptions" :key="option.id" :value="option.id">
                                {{ option.label }} · {{ option.level_label }}{{ option.multiple ? ' · dapat diisi beberapa pegawai' : '' }}
                            </option></select
                        ><InputError :message="placementForm.errors.jabatan_organisasi_id" />
                    </div>
                    <div class="grid gap-2">
                        <label for="jenis_penugasan" class="text-sm font-medium">Jenis penugasan</label
                        ><select
                            id="jenis_penugasan"
                            v-model="placementForm.jenis_penugasan"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option v-for="option in penugasanOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select
                        ><InputError :message="placementForm.errors.jenis_penugasan" />
                    </div>
                    <div class="grid gap-2">
                        <label for="tmt" class="text-sm font-medium">TMT Jabatan <span class="text-red-600">*</span></label
                        ><input
                            id="tmt"
                            v-model="placementForm.tanggal_mulai"
                            type="date"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        /><InputError :message="placementForm.errors.tanggal_mulai" />
                    </div>
                    <div class="grid gap-2">
                        <label for="selesai" class="text-sm font-medium">Tanggal selesai</label
                        ><input
                            id="selesai"
                            v-model="placementForm.tanggal_selesai"
                            type="date"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        /><InputError :message="placementForm.errors.tanggal_selesai" />
                    </div>
                    <div class="grid gap-2">
                        <label for="nomor_sk" class="text-sm font-medium">Nomor SK / surat perintah</label
                        ><input id="nomor_sk" v-model="placementForm.nomor_sk" class="h-10 rounded-lg border bg-background px-3 text-sm" /><InputError
                            :message="placementForm.errors.nomor_sk"
                        />
                    </div>
                    <div class="grid gap-2">
                        <label for="tanggal_sk" class="text-sm font-medium">Tanggal SK</label
                        ><input
                            id="tanggal_sk"
                            v-model="placementForm.tanggal_sk"
                            type="date"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        /><InputError :message="placementForm.errors.tanggal_sk" />
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="h-9 rounded-lg border bg-card px-3 text-sm font-medium" @click="closePlacement">Batal</button
                    ><button
                        type="submit"
                        :disabled="placementForm.processing"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white disabled:opacity-60 dark:bg-blue-600"
                    >
                        <Save class="size-3.5" />{{ placementForm.processing ? 'Menyimpan...' : 'Simpan Jabatan' }}
                    </button>
                </div>
            </form>

            <div class="divide-y">
                <article v-for="placement in item.penempatan" :key="placement.id" class="grid gap-4 p-5 sm:grid-cols-[44px_minmax(0,1fr)_auto]">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl"
                        :class="
                            isCurrent(placement)
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <BriefcaseBusiness class="size-4" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="placement.jabatan"
                                :href="route('master.jabatan-organisasi.show', placement.jabatan.id)"
                                class="font-semibold hover:text-blue-700"
                                >{{ placement.jabatan.nama }}</Link
                            ><span
                                v-if="isCurrent(placement)"
                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                                >Aktif</span
                            ><span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold">{{ placement.jenis_penugasan_label }}</span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ placement.jabatan?.level_label
                            }}<template v-if="placement.jabatan?.multiple"> · dapat ditempati lebih dari satu pegawai</template>
                        </p>
                        <p class="mt-3 inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <CalendarRange class="size-3.5" />TMT {{ formatDate(placement.tanggal_mulai) }} ·
                            {{ placement.tanggal_selesai ? `selesai ${formatDate(placement.tanggal_selesai)}` : 'masih aktif' }}
                        </p>
                    </div>
                    <div v-if="can.manage" class="flex items-start gap-1">
                        <button
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-lg border hover:bg-muted"
                            title="Edit jabatan"
                            @click="openPlacement(placement)"
                        >
                            <Pencil class="size-3.5" /></button
                        ><button
                            v-if="can.delete"
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-lg border text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                            title="Hapus riwayat jabatan"
                            @click="removePlacement(placement)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </article>
                <div v-if="item.penempatan.length === 0 && !placementEditorOpen" class="px-5 py-12 text-center text-sm text-muted-foreground">
                    Belum ada riwayat jabatan.
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <GitBranch class="size-4 text-cyan-700 dark:text-cyan-300" />
                        <h2 class="font-semibold">Pengampu Kinerja Tahunan</h2>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Hanya penugasan ini yang ditarik ke PK Cascading, Rencana Aksi, dan Pengukuran.</p>
                </div>
                <button
                    v-if="can.manage && !assignmentOpen"
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border bg-card px-3 text-sm font-semibold hover:bg-muted"
                    @click="assignmentOpen = true"
                >
                    <Plus class="size-4" /> Tambah Pengampu
                </button>
            </div>
            <form v-if="assignmentOpen" class="border-b bg-cyan-50/40 p-5 dark:bg-cyan-950/10" @submit.prevent="submitAssignment">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold">Hubungkan ke cascading</h3>
                        <p class="mt-1 text-xs text-muted-foreground">Penugasan berlaku per periode dan dapat berubah pada tahun berikutnya.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border bg-card"
                        @click="assignmentOpen = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label for="periode" class="text-sm font-medium">Periode tahun <span class="text-red-600">*</span></label
                        ><select id="periode" v-model="assignmentForm.periode_tahun_id" class="h-10 rounded-lg border bg-background px-3 text-sm">
                            <option value="">Pilih periode</option>
                            <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select
                        ><InputError :message="assignmentForm.errors.periode_tahun_id" />
                    </div>
                    <div class="grid gap-2">
                        <label for="penempatan" class="text-sm font-medium">Jabatan yang digunakan</label
                        ><select
                            id="penempatan"
                            v-model="assignmentForm.penempatan_pegawai_id"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Gunakan jabatan aktif</option>
                            <option v-for="placement in item.penempatan" :key="placement.id" :value="placement.id">
                                {{ placement.jabatan?.nama }} · TMT {{ placement.tanggal_mulai }}
                            </option></select
                        ><InputError :message="assignmentForm.errors.penempatan_pegawai_id" />
                    </div>
                    <div class="grid gap-2">
                        <label for="source_type" class="text-sm font-medium">Level cascading <span class="text-red-600">*</span></label
                        ><select
                            id="source_type"
                            v-model="assignmentForm.sumber_kinerja_type"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            @change="assignmentForm.sumber_kinerja_id = ''"
                        >
                            <option v-for="option in sourceTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select
                        ><InputError :message="assignmentForm.errors.sumber_kinerja_type" />
                    </div>
                    <div class="grid gap-2">
                        <label for="peran" class="text-sm font-medium">Peran</label
                        ><select id="peran" v-model="assignmentForm.peran" class="h-10 rounded-lg border bg-background px-3 text-sm">
                            <option value="penanggung_jawab">Penanggung Jawab</option>
                            <option value="anggota">Anggota Pendukung</option></select
                        ><InputError :message="assignmentForm.errors.peran" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label for="source_id" class="text-sm font-medium"
                            >Data {{ sourceTypeLabel(assignmentForm.sumber_kinerja_type) }} <span class="text-red-600">*</span></label
                        ><select id="source_id" v-model="assignmentForm.sumber_kinerja_id" class="h-11 rounded-lg border bg-background px-3 text-sm">
                            <option value="">Pilih data cascading</option>
                            <option v-for="option in selectedSources" :key="option.id" :value="option.id">{{ option.label }}</option></select
                        ><InputError :message="assignmentForm.errors.sumber_kinerja_id" />
                        <p v-if="selectedSources.length === 0" class="text-xs text-amber-700 dark:text-amber-300">
                            Belum ada data cascading pada level ini untuk OPD pegawai.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        :disabled="assignmentForm.processing"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-cyan-800 px-4 text-sm font-semibold text-white disabled:opacity-60 dark:bg-cyan-600"
                    >
                        <Save class="size-3.5" />Simpan Pengampu
                    </button>
                </div>
            </form>
            <div class="divide-y">
                <article
                    v-for="assignment in item.penugasan_kinerja"
                    :key="assignment.id"
                    class="grid gap-3 px-5 py-4 sm:grid-cols-[100px_minmax(0,1fr)_auto]"
                >
                    <div>
                        <p class="text-lg font-semibold text-blue-800 dark:text-blue-300">{{ assignment.tahun }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-muted-foreground">
                            {{ sourceTypeLabel(assignment.sumber_kinerja_type) }}
                        </p>
                    </div>
                    <div>
                        <p class="font-medium leading-6">{{ assignment.sumber_kinerja_label }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ assignment.peran === 'penanggung_jawab' ? 'Penanggung jawab' : 'Anggota pendukung'
                            }}<template v-if="assignment.jabatan_label"> · {{ assignment.jabatan_label }}</template>
                        </p>
                    </div>
                    <button
                        v-if="can.manage"
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                        title="Hapus penugasan"
                        @click="removeAssignment(assignment)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </article>
                <div v-if="item.penugasan_kinerja.length === 0 && !assignmentOpen" class="px-5 py-12 text-center">
                    <GitBranch class="mx-auto size-8 text-muted-foreground/50" />
                    <p class="mt-3 font-medium">Belum menjadi pengampu cascading</p>
                    <p class="mt-1 text-sm text-muted-foreground">Pegawai tetap dapat memiliki PK Individu yang diisi manual.</p>
                </div>
            </div>
        </section>
    </div>
</template>
