<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import InputError from '@/components/InputError.vue';
import { Dialog, DialogDescription, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, router, useForm } from '@inertiajs/vue3';
import Archive from 'lucide-vue-next/dist/esm/icons/archive.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import CalendarClock from 'lucide-vue-next/dist/esm/icons/calendar-clock.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check-big.js';
import Clock3 from 'lucide-vue-next/dist/esm/icons/clock-3.js';
import Link2 from 'lucide-vue-next/dist/esm/icons/link-2.js';
import LoaderCircle from 'lucide-vue-next/dist/esm/icons/loader-circle.js';
import Megaphone from 'lucide-vue-next/dist/esm/icons/megaphone.js';
import Pencil from 'lucide-vue-next/dist/esm/icons/pencil.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import RotateCcw from 'lucide-vue-next/dist/esm/icons/rotate-ccw.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import ShieldAlert from 'lucide-vue-next/dist/esm/icons/shield-alert.js';
import ToggleLeft from 'lucide-vue-next/dist/esm/icons/toggle-left.js';
import ToggleRight from 'lucide-vue-next/dist/esm/icons/toggle-right.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import UsersRound from 'lucide-vue-next/dist/esm/icons/users-round.js';
import { computed, reactive, ref } from 'vue';

type AnnouncementType = 'info' | 'warning' | 'important';
type Audience = 'all' | 'roles' | 'opds';
type DisplayStatus = 'active' | 'scheduled' | 'ended' | 'inactive';

type Announcement = {
    id: number;
    title: string;
    message: string;
    type: AnnouncementType;
    audience: Audience;
    target_roles: string[];
    target_opd_ids: number[];
    link_label: string | null;
    link_url: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    is_dismissible: boolean;
    display_status: DisplayStatus;
    created_at: string | null;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links?: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    items: Paginator<Announcement>;
    filters: { search?: string; status?: string; type?: string; audience?: string; per_page?: string };
    summary: Record<DisplayStatus, number>;
    roleOptions: Array<{ name: string; label: string }>;
    opdOptions: Array<{ id: number; kode: string; nama: string; singkatan?: string | null }>;
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    audience: props.filters.audience ?? '',
    per_page: props.filters.per_page ?? '10',
});
const isModalOpen = ref(false);
const editingItem = ref<Announcement | null>(null);
const targetSearch = ref('');

const form = useForm({
    title: '',
    message: '',
    type: 'info' as AnnouncementType,
    audience: 'all' as Audience,
    target_roles: [] as string[],
    target_opd_ids: [] as number[],
    link_label: '',
    link_url: '',
    starts_at: '',
    ends_at: '',
    is_active: true as boolean,
    is_dismissible: true as boolean,
});

const applyFilters = () =>
    router.get(route('master.system-announcements.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
const { applyFiltersNow, isFiltering } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.type = '';
    filterForm.audience = '';
    filterForm.per_page = '10';
    applyFiltersNow();
};

const filteredOpds = computed(() => {
    const needle = targetSearch.value.trim().toLocaleLowerCase('id-ID');
    if (!needle) return props.opdOptions;

    return props.opdOptions.filter((opd) => `${opd.kode} ${opd.nama} ${opd.singkatan ?? ''}`.toLocaleLowerCase('id-ID').includes(needle));
});

const clearForm = () => {
    form.reset();
    form.clearErrors();
    form.type = 'info';
    form.audience = 'all';
    form.target_roles = [];
    form.target_opd_ids = [];
    form.is_active = true;
    form.is_dismissible = true;
    editingItem.value = null;
    targetSearch.value = '';
};

const openCreate = () => {
    clearForm();
    isModalOpen.value = true;
};

const openEdit = (item: Announcement) => {
    clearForm();
    editingItem.value = item;
    form.title = item.title;
    form.message = item.message;
    form.type = item.type;
    form.audience = item.audience;
    form.target_roles = [...item.target_roles];
    form.target_opd_ids = [...item.target_opd_ids];
    form.link_label = item.link_label ?? '';
    form.link_url = item.link_url ?? '';
    form.starts_at = item.starts_at ?? '';
    form.ends_at = item.ends_at ?? '';
    form.is_active = item.is_active;
    form.is_dismissible = item.is_dismissible;
    isModalOpen.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isModalOpen.value = false;
            clearForm();
        },
    };

    if (editingItem.value) {
        form.put(route('master.system-announcements.update', { systemAnnouncement: editingItem.value.id }), options);
        return;
    }

    form.post(route('master.system-announcements.store'), options);
};

const toggle = (item: Announcement) => {
    router.patch(route('master.system-announcements.toggle', { systemAnnouncement: item.id }), {}, { preserveScroll: true });
};

const destroy = async (item: Announcement) => {
    if (await confirmDelete(`Hapus pengumuman “${item.title}”?`)) {
        router.delete(route('master.system-announcements.destroy', { systemAnnouncement: item.id }), { preserveScroll: true });
    }
};

const setStatusFilter = (status: DisplayStatus) => {
    filterForm.status = filterForm.status === status ? '' : status;
    applyFiltersNow();
};

const typeLabel = (type: AnnouncementType) => ({ info: 'Informasi', warning: 'Perhatian', important: 'Penting' })[type];
const typeClass = (type: AnnouncementType) =>
    ({
        info: 'bg-blue-50 text-blue-800 ring-blue-200',
        warning: 'bg-amber-50 text-amber-800 ring-amber-200',
        important: 'bg-red-50 text-red-800 ring-red-200',
    })[type];
const statusLabel = (status: DisplayStatus) => ({ active: 'Sedang tayang', scheduled: 'Terjadwal', ended: 'Selesai', inactive: 'Nonaktif' })[status];
const statusClass = (status: DisplayStatus) =>
    ({
        active: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        scheduled: 'bg-blue-50 text-blue-800 ring-blue-200',
        ended: 'bg-slate-100 text-slate-700 ring-slate-200',
        inactive: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
    })[status];
const audienceLabel = (audience: Audience) => ({ all: 'Semua pengguna', roles: 'Role tertentu', opds: 'OPD tertentu' })[audience];

const formatDateTime = (value: string | null) => {
    if (!value) return null;

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const scheduleText = (item: Announcement) => {
    if (!item.starts_at && !item.ends_at) return 'Tanpa batas waktu';
    if (item.starts_at && item.ends_at) return `${formatDateTime(item.starts_at)} – ${formatDateTime(item.ends_at)}`;
    if (item.starts_at) return `Mulai ${formatDateTime(item.starts_at)}`;

    return `Sampai ${formatDateTime(item.ends_at)}`;
};

const targetText = (item: Announcement) => {
    if (item.audience === 'all') return 'Seluruh pengguna aplikasi';
    if (item.audience === 'roles') {
        const labels = item.target_roles.map((name) => props.roleOptions.find((role) => role.name === name)?.label ?? name);
        return labels.join(', ') || 'Belum memilih role';
    }

    const labels = item.target_opd_ids.map((id) => {
        const opd = props.opdOptions.find((option) => option.id === id);
        return opd?.singkatan || opd?.nama || `OPD #${id}`;
    });
    return labels.join(', ') || 'Belum memilih OPD';
};

const summaryCards: Array<{
    key: DisplayStatus;
    label: string;
    description: string;
    icon: unknown;
    iconClass: string;
}> = [
    {
        key: 'active',
        label: 'Sedang tayang',
        description: 'Terlihat sesuai sasaran',
        icon: CheckCircle2,
        iconClass: 'bg-emerald-50 text-emerald-700',
    },
    { key: 'scheduled', label: 'Terjadwal', description: 'Menunggu waktu mulai', icon: Clock3, iconClass: 'bg-blue-50 text-blue-700' },
    { key: 'ended', label: 'Selesai', description: 'Melewati waktu tayang', icon: Archive, iconClass: 'bg-slate-100 text-slate-600' },
    { key: 'inactive', label: 'Nonaktif', description: 'Tidak ditampilkan', icon: ToggleLeft, iconClass: 'bg-zinc-100 text-zinc-600' },
];
</script>

<template>
    <Head title="Pengumuman Sistem" />

    <div class="flex flex-col gap-5 p-4 lg:p-6">
        <header class="overflow-hidden rounded-2xl border border-blue-100 bg-[linear-gradient(110deg,#f8fbff_0%,#eef6ff_58%,#f8fafc_100%)] shadow-sm">
            <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white shadow-sm shadow-blue-950/20">
                        <Megaphone class="size-5" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-700">Administrasi Sistem</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Pengumuman Sistem</h1>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                            Sampaikan informasi penting kepada seluruh pengguna, role tertentu, atau OPD tertentu pada waktu yang ditentukan.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#002957] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    @click="openCreate"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Tambah Pengumuman
                </button>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan pengumuman">
            <button
                v-for="card in summaryCards"
                :key="card.key"
                type="button"
                class="flex min-h-28 items-center gap-4 rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="filterForm.status === card.key ? 'border-[#00336C] ring-2 ring-blue-100' : 'border-slate-200'"
                @click="setStatusFilter(card.key)"
            >
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl" :class="card.iconClass">
                    <component :is="card.icon" class="size-5" aria-hidden="true" />
                </span>
                <span class="min-w-0">
                    <span class="block text-2xl font-bold tabular-nums text-slate-950">{{ summary[card.key] }}</span>
                    <span class="block text-sm font-semibold text-slate-800">{{ card.label }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ card.description }}</span>
                </span>
            </button>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-950">Daftar Pengumuman</h2>
                        <p class="mt-1 text-sm text-slate-500">Filter berjalan otomatis saat isian berubah.</p>
                    </div>
                    <span v-if="isFiltering" class="inline-flex items-center gap-2 text-sm font-semibold text-[#00336C]">
                        <LoaderCircle class="size-4 animate-spin" aria-hidden="true" /> Memuat
                    </span>
                </div>

                <form class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_170px_170px_180px_auto]" @submit.prevent="applyFiltersNow">
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Pencarian
                        <span class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input
                                v-model="filterForm.search"
                                type="search"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                placeholder="Cari judul atau isi"
                            />
                        </span>
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Status
                        <select
                            v-model="filterForm.status"
                            class="h-10 rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">Semua status</option>
                            <option value="active">Sedang tayang</option>
                            <option value="scheduled">Terjadwal</option>
                            <option value="ended">Selesai</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Jenis
                        <select v-model="filterForm.type" class="h-10 rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua jenis</option>
                            <option value="info">Informasi</option>
                            <option value="warning">Perhatian</option>
                            <option value="important">Penting</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Sasaran
                        <select
                            v-model="filterForm.audience"
                            class="h-10 rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">Semua sasaran</option>
                            <option value="all">Semua pengguna</option>
                            <option value="roles">Role tertentu</option>
                            <option value="opds">OPD tertentu</option>
                        </select>
                    </label>
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 lg:w-auto"
                            @click="resetFilters"
                        >
                            <RotateCcw class="size-4" aria-hidden="true" /> Reset
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1040px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="w-[38%] px-5 py-3">Pengumuman</th>
                            <th class="w-[20%] px-4 py-3">Sasaran</th>
                            <th class="w-[22%] px-4 py-3">Jadwal</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in items.data" :key="item.id" class="align-top transition hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                        :class="typeClass(item.type)"
                                    >
                                        {{ typeLabel(item.type) }}
                                    </span>
                                    <span v-if="!item.is_dismissible" class="text-xs font-medium text-slate-500">Wajib tampil</span>
                                </div>
                                <h3 class="mt-2 font-semibold leading-5 text-slate-950">{{ item.title }}</h3>
                                <p class="mt-1 line-clamp-2 max-w-xl leading-5 text-slate-600">{{ item.message }}</p>
                                <a
                                    v-if="item.link_url"
                                    :href="item.link_url"
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:underline"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Link2 class="size-3.5" aria-hidden="true" /> {{ item.link_label || 'Lihat tautan' }}
                                </a>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2 font-semibold text-slate-800">
                                    <UsersRound v-if="item.audience !== 'opds'" class="size-4 text-slate-400" aria-hidden="true" />
                                    <Building2 v-else class="size-4 text-slate-400" aria-hidden="true" />
                                    {{ audienceLabel(item.audience) }}
                                </div>
                                <p class="mt-1 line-clamp-3 text-xs leading-5 text-slate-500" :title="targetText(item)">{{ targetText(item) }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-start gap-2 text-slate-700">
                                    <CalendarClock class="mt-0.5 size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                    <span class="text-xs leading-5">{{ scheduleText(item) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="statusClass(item.display_status)"
                                >
                                    {{ statusLabel(item.display_status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800"
                                        :aria-label="item.is_active ? 'Nonaktifkan pengumuman' : 'Aktifkan pengumuman'"
                                        :title="item.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                        @click="toggle(item)"
                                    >
                                        <ToggleRight v-if="item.is_active" class="size-4 text-emerald-700" aria-hidden="true" />
                                        <ToggleLeft v-else class="size-4" aria-hidden="true" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800"
                                        aria-label="Edit pengumuman"
                                        title="Edit"
                                        @click="openEdit(item)"
                                    >
                                        <Pencil class="size-4" aria-hidden="true" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-red-100 text-red-600 transition hover:bg-red-50"
                                        aria-label="Hapus pengumuman"
                                        title="Hapus"
                                        @click="destroy(item)"
                                    >
                                        <Trash2 class="size-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="5" class="px-5 py-14 text-center">
                                <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                                    <Megaphone class="size-5" aria-hidden="true" />
                                </span>
                                <p class="mt-3 font-semibold text-slate-900">Pengumuman tidak ditemukan</p>
                                <p class="mt-1 text-sm text-slate-500">Ubah filter atau tambahkan pengumuman baru.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <DataPagination v-model:per-page="filterForm.per_page" :paginator="items" item-label="pengumuman" />
        </section>
    </div>

    <Dialog v-model:open="isModalOpen">
        <DialogScrollContent class="max-w-3xl border-slate-200 bg-white p-0">
            <DialogHeader class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 pr-12 text-left sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-[#00336C]">
                        <Megaphone class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <DialogTitle>{{ editingItem ? 'Edit Pengumuman' : 'Tambah Pengumuman' }}</DialogTitle>
                        <DialogDescription class="mt-1 text-sm text-slate-500">
                            Pengumuman hanya tampil jika aktif, sesuai jadwal, dan pengguna termasuk sasarannya.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="grid gap-6 px-5 py-5 sm:px-6" @submit.prevent="submit">
                <section class="grid gap-4">
                    <div>
                        <h3 class="font-semibold text-slate-950">Isi pengumuman</h3>
                        <p class="mt-1 text-sm text-slate-500">Gunakan judul singkat dan isi yang langsung menjelaskan tindakan pengguna.</p>
                    </div>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Judul <span class="text-red-600">*</span>
                        <input
                            v-model="form.title"
                            type="text"
                            maxlength="150"
                            class="h-11 rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Contoh: Pemeliharaan sistem malam ini"
                        />
                        <InputError :message="form.errors.title" />
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Isi pengumuman <span class="text-red-600">*</span>
                        <textarea
                            v-model="form.message"
                            rows="4"
                            maxlength="2000"
                            class="rounded-xl border-slate-200 text-sm leading-6 focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Tuliskan informasi yang perlu diketahui pengguna..."
                        />
                        <span class="flex items-start justify-between gap-3 text-xs font-normal text-slate-500">
                            <InputError :message="form.errors.message" />
                            <span class="ml-auto tabular-nums">{{ form.message.length }}/2000</span>
                        </span>
                    </label>

                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-semibold text-slate-700">Jenis pengumuman</legend>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label
                                v-for="option in [
                                    {
                                        value: 'info',
                                        label: 'Informasi',
                                        note: 'Informasi umum',
                                        class: 'border-blue-200 bg-blue-50/60 text-blue-900',
                                    },
                                    {
                                        value: 'warning',
                                        label: 'Perhatian',
                                        note: 'Perlu diperhatikan',
                                        class: 'border-amber-200 bg-amber-50/60 text-amber-900',
                                    },
                                    {
                                        value: 'important',
                                        label: 'Penting',
                                        note: 'Prioritas tertinggi',
                                        class: 'border-red-200 bg-red-50/60 text-red-900',
                                    },
                                ]"
                                :key="option.value"
                                class="cursor-pointer rounded-xl border p-3 transition"
                                :class="
                                    form.type === option.value
                                        ? `${option.class} ring-current/10 ring-2`
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                "
                            >
                                <input v-model="form.type" type="radio" name="type" :value="option.value" class="sr-only" />
                                <span class="block text-sm font-semibold">{{ option.label }}</span>
                                <span class="mt-0.5 block text-xs opacity-70">{{ option.note }}</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.type" />
                    </fieldset>
                </section>

                <section class="grid gap-4 border-t border-slate-200 pt-5">
                    <div>
                        <h3 class="font-semibold text-slate-950">Sasaran pengguna</h3>
                        <p class="mt-1 text-sm text-slate-500">Batasi penerima hanya jika informasi tidak berlaku untuk semua pengguna.</p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in [
                                { value: 'all', label: 'Semua pengguna', icon: UsersRound },
                                { value: 'roles', label: 'Role tertentu', icon: ShieldAlert },
                                { value: 'opds', label: 'OPD tertentu', icon: Building2 },
                            ]"
                            :key="option.value"
                            class="flex min-h-12 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                            :class="
                                form.audience === option.value
                                    ? 'border-blue-300 bg-blue-50 text-[#00336C] ring-2 ring-blue-100'
                                    : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                            "
                        >
                            <input v-model="form.audience" type="radio" name="audience" :value="option.value" class="sr-only" />
                            <component :is="option.icon" class="size-4" aria-hidden="true" />
                            {{ option.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.audience" />

                    <div v-if="form.audience === 'roles'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <p class="mb-3 text-sm font-semibold text-slate-800">Pilih role penerima</p>
                        <div class="grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2">
                            <label
                                v-for="roleOption in roleOptions"
                                :key="roleOption.name"
                                class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-lg bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 hover:ring-blue-200"
                            >
                                <input
                                    v-model="form.target_roles"
                                    type="checkbox"
                                    :value="roleOption.name"
                                    class="size-4 rounded border-slate-300 text-[#00336C] focus:ring-blue-500"
                                />
                                <span>{{ roleOption.label }}</span>
                            </label>
                        </div>
                        <InputError class="mt-2" :message="form.errors.target_roles" />
                    </div>

                    <div v-if="form.audience === 'opds'" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm font-semibold text-slate-800">Pilih OPD penerima</p>
                            <label class="relative sm:w-72">
                                <Search
                                    class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-slate-400"
                                    aria-hidden="true"
                                />
                                <input
                                    v-model="targetSearch"
                                    type="search"
                                    class="h-9 w-full rounded-lg border-slate-200 pl-8 text-xs focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Cari OPD"
                                />
                            </label>
                        </div>
                        <div class="grid max-h-60 gap-2 overflow-y-auto sm:grid-cols-2">
                            <label
                                v-for="opd in filteredOpds"
                                :key="opd.id"
                                class="flex min-h-11 cursor-pointer items-start gap-2.5 rounded-lg bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 hover:ring-blue-200"
                            >
                                <input
                                    v-model="form.target_opd_ids"
                                    type="checkbox"
                                    :value="opd.id"
                                    class="mt-0.5 size-4 rounded border-slate-300 text-[#00336C] focus:ring-blue-500"
                                />
                                <span class="min-w-0">
                                    <span class="block font-semibold">{{ opd.singkatan || opd.nama }}</span>
                                    <span v-if="opd.singkatan" class="mt-0.5 block text-xs leading-4 text-slate-500">{{ opd.nama }}</span>
                                </span>
                            </label>
                            <p v-if="filteredOpds.length === 0" class="py-6 text-center text-sm text-slate-500 sm:col-span-2">OPD tidak ditemukan.</p>
                        </div>
                        <InputError class="mt-2" :message="form.errors.target_opd_ids" />
                    </div>
                </section>

                <section class="grid gap-4 border-t border-slate-200 pt-5">
                    <div>
                        <h3 class="font-semibold text-slate-950">Jadwal dan tautan</h3>
                        <p class="mt-1 text-sm text-slate-500">Kosongkan jadwal bila pengumuman berlaku langsung tanpa batas waktu.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Mulai tayang
                            <input
                                v-model="form.starts_at"
                                type="datetime-local"
                                class="h-11 rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <InputError :message="form.errors.starts_at" />
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Selesai tayang
                            <input
                                v-model="form.ends_at"
                                type="datetime-local"
                                class="h-11 rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <InputError :message="form.errors.ends_at" />
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Teks tombol <span class="font-normal text-slate-400">(opsional)</span>
                            <input
                                v-model="form.link_label"
                                type="text"
                                maxlength="80"
                                class="h-11 rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Contoh: Lihat panduan"
                            />
                            <InputError :message="form.errors.link_label" />
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Tautan <span class="font-normal text-slate-400">(opsional)</span>
                            <input
                                v-model="form.link_url"
                                type="text"
                                maxlength="2048"
                                class="h-11 rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="https://... atau /halaman"
                            />
                            <InputError :message="form.errors.link_url" />
                        </label>
                    </div>
                </section>

                <section class="grid gap-3 border-t border-slate-200 pt-5 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-slate-300 text-[#00336C] focus:ring-blue-500"
                        />
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">Aktifkan pengumuman</span>
                            <span class="mt-0.5 block text-xs leading-5 text-slate-500">Tetap mengikuti batas waktu tayang yang dipilih.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50">
                        <input
                            v-model="form.is_dismissible"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-slate-300 text-[#00336C] focus:ring-blue-500"
                        />
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">Boleh ditutup pengguna</span>
                            <span class="mt-0.5 block text-xs leading-5 text-slate-500">Pilihan tutup berlaku selama sesi browser pengguna.</span>
                        </span>
                    </label>
                </section>

                <footer
                    class="sticky bottom-0 -mx-5 -mb-5 flex flex-col-reverse gap-2 border-t border-slate-200 bg-white/95 px-5 py-4 backdrop-blur sm:-mx-6 sm:-mb-5 sm:flex-row sm:justify-end sm:px-6"
                >
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="isModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white transition hover:bg-[#002957] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" aria-hidden="true" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengumuman' }}
                    </button>
                </footer>
            </form>
        </DialogScrollContent>
    </Dialog>
</template>
