<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { confirmAction, confirmDelete, promptTextArea } from '@/lib/sweetAlert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import BadgeCheck from 'lucide-vue-next/dist/esm/icons/badge-check.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import CalendarRange from 'lucide-vue-next/dist/esm/icons/calendar-range.js';
import CircleUserRound from 'lucide-vue-next/dist/esm/icons/circle-user-round.js';
import CircleX from 'lucide-vue-next/dist/esm/icons/circle-x.js';
import Clock3 from 'lucide-vue-next/dist/esm/icons/clock-3.js';
import FileBadge2 from 'lucide-vue-next/dist/esm/icons/file-badge-2.js';
import Network from 'lucide-vue-next/dist/esm/icons/network.js';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import UserRoundCheck from 'lucide-vue-next/dist/esm/icons/user-round-check.js';
import X from 'lucide-vue-next/dist/esm/icons/x.js';
import { computed, ref } from 'vue';

type Id = number | '';
type Pejabat = {
    id: number;
    pegawai_id?: number | null;
    user_id: number | null;
    nama_pejabat: string;
    nip?: string | null;
    pangkat_golongan?: string | null;
    jenis_penugasan: string;
    jenis_penugasan_label: string;
    nomor_sk?: string | null;
    tanggal_sk?: string | null;
    tanggal_mulai: string;
    tanggal_selesai?: string | null;
};
type Item = {
    id: number;
    nama: string;
    level_label: string;
    eselon?: string | null;
    urutan: number;
    status: string;
    verification_status: 'pending' | 'verified' | 'rejected';
    verification_label: string;
    verification_note?: string | null;
    opd?: { nama: string; singkatan?: string | null } | null;
    opd_unit?: { kode: string; nama: string } | null;
    parent?: { id: number; nama: string } | null;
    current_pejabat?: Pejabat | null;
    children: Array<{ id: number; nama: string; level_label: string; status: string }>;
    riwayat_pejabat: Pejabat[];
};
type Option = { id?: number; value?: string; label: string; name?: string };
type PejabatForm = {
    user_id: Id;
    nama_pejabat: string;
    nip: string;
    pangkat_golongan: string;
    jenis_penugasan: string;
    nomor_sk: string;
    tanggal_sk: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
};

const props = defineProps<{
    item: Item;
    penugasanOptions: Option[];
    userOptions: Option[];
    can: {
        manage_structure: boolean;
        manage_officials: boolean;
        delete_officials: boolean;
        manage_people: boolean;
        verify: boolean;
    };
}>();

const editorOpen = ref(false);
const editingId = ref<number | null>(null);
const emptyForm = (): PejabatForm => ({
    user_id: '',
    nama_pejabat: '',
    nip: '',
    pangkat_golongan: '',
    jenis_penugasan: 'definitif',
    nomor_sk: '',
    tanggal_sk: '',
    tanggal_mulai: new Date().toISOString().slice(0, 10),
    tanggal_selesai: '',
});
const form = useForm<PejabatForm>(emptyForm());

const isCurrent = (pejabat: Pejabat) => {
    const today = new Date().toISOString().slice(0, 10);
    return pejabat.tanggal_mulai <= today && (!pejabat.tanggal_selesai || pejabat.tanggal_selesai >= today);
};
const currentPejabat = computed(() => props.item.current_pejabat);

const formatDate = (value?: string | null) =>
    value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`)) : 'Sekarang';

const openCreate = () => {
    editingId.value = null;
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
    editorOpen.value = true;
};

const openEdit = (pejabat: Pejabat) => {
    editingId.value = pejabat.id;
    form.defaults({
        user_id: pejabat.user_id ?? '',
        nama_pejabat: pejabat.nama_pejabat,
        nip: pejabat.nip ?? '',
        pangkat_golongan: pejabat.pangkat_golongan ?? '',
        jenis_penugasan: pejabat.jenis_penugasan,
        nomor_sk: pejabat.nomor_sk ?? '',
        tanggal_sk: pejabat.tanggal_sk ?? '',
        tanggal_mulai: pejabat.tanggal_mulai,
        tanggal_selesai: pejabat.tanggal_selesai ?? '',
    });
    form.reset();
    form.clearErrors();
    editorOpen.value = true;
};

const closeEditor = () => {
    editorOpen.value = false;
    editingId.value = null;
    form.clearErrors();
};

const syncUserName = () => {
    const selected = props.userOptions.find((option) => Number(option.id) === Number(form.user_id));
    if (selected?.name) form.nama_pejabat = selected.name;
};

const submit = () => {
    if (editingId.value) {
        form.put(route('master.jabatan-organisasi.pejabat.update', [props.item.id, editingId.value]), {
            preserveScroll: true,
            onSuccess: closeEditor,
        });
        return;
    }

    form.post(route('master.jabatan-organisasi.pejabat.store', props.item.id), {
        preserveScroll: true,
        onSuccess: closeEditor,
    });
};

const destroyPejabat = async (pejabat: Pejabat) => {
    if (await confirmDelete(`Hapus riwayat masa tugas ${pejabat.nama_pejabat}?`)) {
        router.delete(route('master.jabatan-organisasi.pejabat.destroy', [props.item.id, pejabat.id]), { preserveScroll: true });
    }
};

const destroyJabatan = async () => {
    if (await confirmDelete(`Hapus jabatan ${props.item.nama}? Jabatan dengan turunan atau riwayat pejabat akan ditolak sistem.`)) {
        router.delete(route('master.jabatan-organisasi.destroy', props.item.id));
    }
};

const approve = async () => {
    if (
        await confirmAction({
            title: 'Verifikasi jabatan?',
            text: `${props.item.nama} akan menjadi bagian struktur organisasi resmi.`,
            confirmButtonText: 'Ya, verifikasi',
        })
    ) {
        router.patch(
            route('master.jabatan-organisasi.verify', props.item.id),
            { verification_status: 'verified', verification_note: null },
            { preserveScroll: true },
        );
    }
};

const reject = async () => {
    const note = await promptTextArea({
        title: 'Kembalikan usulan jabatan',
        text: `Tuliskan bagian yang perlu diperbaiki pada ${props.item.nama}.`,
        inputLabel: 'Catatan perbaikan',
        inputPlaceholder: 'Jelaskan nomenklatur atau hierarki yang perlu diperbaiki.',
        confirmButtonText: 'Kirim untuk diperbaiki',
        minLength: 5,
    });

    if (note !== null) {
        router.patch(
            route('master.jabatan-organisasi.verify', props.item.id),
            { verification_status: 'rejected', verification_note: note },
            { preserveScroll: true },
        );
    }
};
</script>

<template>
    <Head :title="item.nama" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Link
                :href="route('master.jabatan-organisasi.index')"
                class="inline-flex h-9 items-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium hover:bg-muted"
                ><ArrowLeft class="size-4" /> Struktur Jabatan</Link
            >
            <div v-if="can.manage_structure || can.verify" class="flex flex-wrap items-center gap-2">
                <button
                    v-if="can.verify"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-700 px-3 text-sm font-semibold text-white hover:bg-emerald-800"
                    @click="approve"
                >
                    <BadgeCheck class="size-4" /> Verifikasi
                </button>
                <button
                    v-if="can.verify && item.verification_status === 'pending'"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-lg border border-rose-200 bg-card px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/30"
                    @click="reject"
                >
                    <CircleX class="size-4" /> Perlu perbaikan
                </button>
                <Link
                    v-if="can.manage_structure"
                    :href="route('master.jabatan-organisasi.edit', item.id)"
                    class="inline-flex h-9 items-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium hover:bg-muted"
                    ><Pencil class="size-3.5" /> Edit</Link
                >
                <button
                    v-if="can.manage_structure"
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg border text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                    title="Hapus jabatan"
                    @click="destroyJabatan"
                >
                    <Trash2 class="size-3.5" />
                </button>
            </div>
        </div>

        <div
            v-if="item.verification_status !== 'verified'"
            class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
            :class="
                item.verification_status === 'pending'
                    ? 'border-amber-200 bg-amber-50/70 text-amber-950 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-100'
                    : 'border-rose-200 bg-rose-50/70 text-rose-950 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-100'
            "
        >
            <Clock3 v-if="item.verification_status === 'pending'" class="mt-0.5 size-4 shrink-0" />
            <CircleX v-else class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-semibold">{{ item.verification_label }}</p>
                <p class="mt-0.5 text-xs leading-5 opacity-85">
                    {{ item.verification_note || 'Jabatan sudah dapat dipakai untuk penempatan pegawai, tetapi belum menjadi struktur resmi.' }}
                </p>
            </div>
        </div>

        <header class="overflow-hidden rounded-2xl border bg-card">
            <div class="h-1 bg-gradient-to-r from-blue-800 via-cyan-600 to-emerald-500 dark:from-blue-500 dark:via-cyan-400 dark:to-emerald-400" />
            <div class="grid gap-6 p-5 md:p-7 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-800 text-white shadow-sm dark:bg-blue-600">
                        <BriefcaseBusiness class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">
                            <span>{{ item.level_label }}</span
                            ><span>•</span
                            ><span
                                :class="item.status === 'active' ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'"
                                >{{ item.status === 'active' ? 'Aktif' : 'Nonaktif' }}</span
                            >
                            <span>•</span>
                            <span
                                :class="
                                    item.verification_status === 'verified'
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : item.verification_status === 'pending'
                                          ? 'text-amber-700 dark:text-amber-300'
                                          : 'text-rose-700 dark:text-rose-300'
                                "
                            >
                                {{ item.verification_label }}
                            </span>
                        </div>
                        <h1 class="mt-2 text-2xl font-semibold leading-tight tracking-tight md:text-3xl">{{ item.nama }}</h1>
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span v-if="item.eselon">{{ item.eselon.replace('_', '.').toUpperCase() }}</span
                            ><span>Urutan {{ item.urutan }}</span>
                        </div>
                    </div>
                </div>
                <div class="border-t pt-5 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Pejabat saat ini</p>
                    <div v-if="currentPejabat" class="mt-3 flex items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                        >
                            <UserRoundCheck class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold">{{ currentPejabat.nama_pejabat }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ currentPejabat.jenis_penugasan_label
                                }}<template v-if="currentPejabat.nip"> · NIP {{ currentPejabat.nip }}</template>
                            </p>
                            <p class="mt-1.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
                                TMT {{ formatDate(currentPejabat.tanggal_mulai) }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-3 rounded-lg border border-dashed border-amber-400/70 px-3 py-2.5 text-sm text-amber-800 dark:text-amber-200"
                    >
                        Posisi ini belum memiliki pejabat aktif.
                    </div>
                </div>
            </div>
        </header>

        <section class="grid gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <Building2 class="size-4" /> Perangkat daerah
                </div>
                <p class="mt-2 text-sm font-medium leading-5">{{ item.opd?.singkatan || item.opd?.nama || 'Pemerintah Kabupaten Banjarnegara' }}</p>
            </div>
            <div class="bg-card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <Network class="size-4" /> Unit organisasi
                </div>
                <p class="mt-2 text-sm font-medium leading-5">
                    {{ item.opd_unit ? `${item.opd_unit.kode} · ${item.opd_unit.nama}` : 'Tidak terikat unit khusus' }}
                </p>
            </div>
            <div class="bg-card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <CircleUserRound class="size-4" /> Atasan langsung
                </div>
                <Link
                    v-if="item.parent"
                    :href="route('master.jabatan-organisasi.show', item.parent.id)"
                    class="mt-2 block text-sm font-medium leading-5 text-blue-800 hover:underline dark:text-blue-300"
                    >{{ item.parent.nama }}</Link
                >
                <p v-else class="mt-2 text-sm font-medium">Puncak hierarki</p>
            </div>
            <div class="bg-card p-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <BriefcaseBusiness class="size-4" /> Jabatan bawahan
                </div>
                <p class="mt-2 text-sm font-medium">{{ item.children.length }} jabatan langsung</p>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_330px]">
            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold">Pegawai & riwayat penempatan</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Identitas pegawai dan masa tugas dikelola terpisah dari struktur jabatan.</p>
                    </div>
                    <Link
                        v-if="can.manage_people"
                        :href="route('master.pegawai.index')"
                        class="inline-flex h-9 items-center justify-center rounded-lg border bg-card px-3 text-sm font-semibold hover:bg-muted"
                    >
                        Kelola Pegawai
                    </Link>
                    <button
                        v-if="can.manage_officials && !editorOpen"
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-blue-800 px-3 text-sm font-semibold text-white hover:bg-blue-900 dark:bg-blue-600 dark:hover:bg-blue-500"
                        @click="openCreate"
                    >
                        <Plus class="size-4" /> Tambah Pejabat
                    </button>
                </div>

                <form v-if="editorOpen" class="border-b bg-muted/25 p-5" @submit.prevent="submit">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold">{{ editingId ? 'Edit masa tugas' : 'Catat pejabat baru' }}</h3>
                            <p class="mt-1 text-xs text-muted-foreground">Akun pengguna dan dasar penugasan dapat dikosongkan.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-lg border bg-card hover:bg-muted"
                            @click="closeEditor"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-700 dark:text-blue-300">Identitas pejabat</p>
                            <p class="mt-1 text-xs text-muted-foreground">Nama dan NIP menjadi identitas pihak dalam dokumen PK.</p>
                        </div>
                        <div class="grid gap-2">
                            <label for="nama_pejabat" class="text-sm font-medium">Nama lengkap <span class="text-red-600">*</span></label
                            ><input
                                id="nama_pejabat"
                                v-model="form.nama_pejabat"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            /><InputError :message="form.errors.nama_pejabat" />
                        </div>
                        <div class="grid gap-2">
                            <label for="nip" class="text-sm font-medium"
                                >NIP <span class="font-normal text-muted-foreground">(disarankan untuk ASN)</span></label
                            ><input
                                id="nip"
                                v-model="form.nip"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="18 digit tanpa spasi"
                            /><InputError :message="form.errors.nip" />
                        </div>
                        <div class="grid gap-2">
                            <label for="pangkat_golongan" class="text-sm font-medium"
                                >Pangkat / golongan <span class="font-normal text-muted-foreground">(opsional)</span></label
                            ><input
                                id="pangkat_golongan"
                                v-model="form.pangkat_golongan"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="Contoh: Pembina Utama Muda, IV/c"
                            /><InputError :message="form.errors.pangkat_golongan" />
                        </div>
                        <div class="grid gap-2">
                            <label for="jenis_penugasan" class="text-sm font-medium">Jenis penugasan <span class="text-red-600">*</span></label
                            ><select id="jenis_penugasan" v-model="form.jenis_penugasan" class="h-10 rounded-lg border bg-background px-3 text-sm">
                                <option v-for="option in penugasanOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option></select
                            ><InputError :message="form.errors.jenis_penugasan" />
                        </div>

                        <div class="border-t pt-4 md:col-span-2">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-700 dark:text-blue-300">Masa tugas</p>
                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                Pejabat definitif tidak perlu diberi tanggal selesai sampai benar-benar diganti, mutasi, pensiun, atau diberhentikan.
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <label for="tanggal_mulai" class="text-sm font-medium">TMT Jabatan <span class="text-red-600">*</span></label
                            ><input
                                id="tanggal_mulai"
                                v-model="form.tanggal_mulai"
                                type="date"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            /><InputError :message="form.errors.tanggal_mulai" />
                            <p class="text-[11px] text-muted-foreground">Tanggal efektif mulai menjalankan jabatan, bukan selalu tanggal SK.</p>
                        </div>
                        <div class="grid gap-2">
                            <label for="tanggal_selesai" class="text-sm font-medium"
                                >Tanggal selesai <span class="font-normal text-muted-foreground">(opsional)</span></label
                            ><input
                                id="tanggal_selesai"
                                v-model="form.tanggal_selesai"
                                type="date"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            /><InputError :message="form.errors.tanggal_selesai" />
                            <p class="text-[11px] text-muted-foreground">Kosong berarti masih aktif. Isi sebelum mencatat pejabat pengganti.</p>
                        </div>

                        <div class="border-t pt-4 md:col-span-2">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-700 dark:text-blue-300">
                                Dasar penugasan <span class="font-normal normal-case tracking-normal text-muted-foreground">(opsional)</span>
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">Isi bila SK pengangkatan atau surat perintah tersedia.</p>
                        </div>
                        <div class="grid gap-2">
                            <label for="nomor_sk" class="text-sm font-medium">Nomor SK / surat perintah</label
                            ><input id="nomor_sk" v-model="form.nomor_sk" class="h-10 rounded-lg border bg-background px-3 text-sm" /><InputError
                                :message="form.errors.nomor_sk"
                            />
                        </div>
                        <div class="grid gap-2">
                            <label for="tanggal_sk" class="text-sm font-medium">Tanggal SK / surat perintah</label
                            ><input
                                id="tanggal_sk"
                                v-model="form.tanggal_sk"
                                type="date"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            /><InputError :message="form.errors.tanggal_sk" />
                        </div>

                        <div class="border-t pt-4 md:col-span-2">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-700 dark:text-blue-300">
                                Akses aplikasi <span class="font-normal normal-case tracking-normal text-muted-foreground">(opsional)</span>
                            </p>
                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                Hubungkan akun hanya jika pejabat akan login atau menyetujui dokumen sendiri. Admin OPD tetap dapat menyusun PK tanpa
                                akun pejabat.
                            </p>
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <label for="user_id" class="text-sm font-medium">Akun pengguna</label
                            ><select
                                id="user_id"
                                v-model="form.user_id"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                @change="syncUserName"
                            >
                                <option value="">Tidak dihubungkan ke akun</option>
                                <option v-for="option in userOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select
                            ><InputError :message="form.errors.user_id" />
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="h-9 rounded-lg border bg-card px-3 text-sm font-medium hover:bg-muted" @click="closeEditor">
                            Batal</button
                        ><button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-blue-800 px-4 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                        >
                            <Save class="size-3.5" />{{ form.processing ? 'Menyimpan...' : 'Simpan Riwayat' }}
                        </button>
                    </div>
                </form>

                <div class="divide-y">
                    <article v-for="pejabat in item.riwayat_pejabat" :key="pejabat.id" class="grid gap-4 p-5 sm:grid-cols-[42px_minmax(0,1fr)_auto]">
                        <div
                            class="flex size-10 items-center justify-center rounded-full"
                            :class="
                                isCurrent(pejabat)
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <CircleUserRound class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    v-if="pejabat.pegawai_id"
                                    :href="route('master.pegawai.show', pejabat.pegawai_id)"
                                    class="font-semibold hover:text-blue-700 dark:hover:text-blue-300"
                                    >{{ pejabat.nama_pejabat }}</Link
                                >
                                <h3 v-else class="font-semibold">{{ pejabat.nama_pejabat }}</h3>
                                <span
                                    v-if="isCurrent(pejabat)"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                                    >Aktif saat ini</span
                                ><span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold">{{ pejabat.jenis_penugasan_label }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <template v-if="pejabat.nip">NIP {{ pejabat.nip }}</template
                                ><template v-if="pejabat.nip && pejabat.pangkat_golongan"> · </template>{{ pejabat.pangkat_golongan }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                                <span class="inline-flex items-center gap-1.5"
                                    ><CalendarRange class="size-3.5" />TMT {{ formatDate(pejabat.tanggal_mulai) }} ·
                                    {{ pejabat.tanggal_selesai ? `selesai ${formatDate(pejabat.tanggal_selesai)}` : 'masih menjabat' }}</span
                                ><span v-if="pejabat.nomor_sk" class="inline-flex items-center gap-1.5"
                                    ><FileBadge2 class="size-3.5" />{{ pejabat.nomor_sk
                                    }}<template v-if="pejabat.tanggal_sk"> · {{ formatDate(pejabat.tanggal_sk) }}</template></span
                                >
                            </div>
                        </div>
                        <div v-if="can.manage_officials || can.delete_officials" class="flex items-start gap-1">
                            <button
                                v-if="can.manage_officials"
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-lg border hover:bg-muted"
                                title="Edit riwayat"
                                @click="openEdit(pejabat)"
                            >
                                <Pencil class="size-3.5" /></button
                            ><button
                                v-if="can.delete_officials"
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-lg border text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                                title="Hapus riwayat"
                                @click="destroyPejabat(pejabat)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </article>
                    <div v-if="item.riwayat_pejabat.length === 0 && !editorOpen" class="px-5 py-12 text-center">
                        <CircleUserRound class="mx-auto size-8 text-muted-foreground/60" />
                        <p class="mt-3 font-medium">Belum ada riwayat pejabat</p>
                        <p class="mt-1 text-sm text-muted-foreground">Catat pejabat definitif atau penugasan sementara pada jabatan ini.</p>
                    </div>
                </div>
            </section>

            <aside class="h-fit overflow-hidden rounded-xl border bg-card">
                <div class="border-b px-4 py-3.5">
                    <h2 class="font-semibold">Jabatan bawahan langsung</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Turunan satu tingkat dari posisi ini.</p>
                </div>
                <div class="divide-y">
                    <Link
                        v-for="child in item.children"
                        :key="child.id"
                        :href="route('master.jabatan-organisasi.show', child.id)"
                        class="block p-4 transition hover:bg-muted/50"
                        ><p class="text-sm font-semibold leading-5">{{ child.nama }}</p>
                        <p class="mt-1.5 text-[11px] text-muted-foreground">{{ child.level_label }}</p></Link
                    >
                    <div v-if="item.children.length === 0" class="p-5 text-sm leading-6 text-muted-foreground">
                        Belum ada jabatan yang ditempatkan langsung di bawah posisi ini.
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
