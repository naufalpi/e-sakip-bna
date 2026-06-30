<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import Bell from 'lucide-vue-next/dist/esm/icons/bell.js';
import BarChart3 from 'lucide-vue-next/dist/esm/icons/chart-column.js';
import ClipboardCheck from 'lucide-vue-next/dist/esm/icons/clipboard-check.js';
import Database from 'lucide-vue-next/dist/esm/icons/database.js';
import FileText from 'lucide-vue-next/dist/esm/icons/file-text.js';
import Globe2 from 'lucide-vue-next/dist/esm/icons/globe.js';
import LoaderCircle from 'lucide-vue-next/dist/esm/icons/loader-circle.js';
import LockKeyhole from 'lucide-vue-next/dist/esm/icons/lock-keyhole.js';
import Plus from 'lucide-vue-next/dist/esm/icons/plus.js';
import RotateCcw from 'lucide-vue-next/dist/esm/icons/rotate-ccw.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Settings from 'lucide-vue-next/dist/esm/icons/settings.js';
import ShieldCheck from 'lucide-vue-next/dist/esm/icons/shield-check.js';
import SlidersHorizontal from 'lucide-vue-next/dist/esm/icons/sliders-horizontal.js';
import Workflow from 'lucide-vue-next/dist/esm/icons/workflow.js';
import { reactive } from 'vue';

type Setting = {
    id: number;
    group: string;
    group_label: string;
    group_description: string | null;
    key: string;
    label: string;
    type: string;
    value: string;
    is_public: boolean;
    description: string | null;
};

type GroupSummary = {
    key: string;
    label: string;
    description: string;
    count: number;
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
};

const props = defineProps<{
    items: Paginator<Setting>;
    filters: { search?: string; group?: string; type?: string; is_public?: string };
    groupSummaries: GroupSummary[];
    groupOptions: Array<{ value: string; label: string; description?: string }>;
    typeOptions: Array<{ value: string; label: string; description?: string }>;
    can: { manage: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    group: props.filters.group ?? '',
    type: props.filters.type ?? '',
    is_public: props.filters.is_public ?? '',
});

const applyFilters = () =>
    router.get(route('master.system-settings.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow, isFiltering } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.group = '';
    filterForm.type = '';
    filterForm.is_public = '';
    applyFiltersNow();
};

const selectGroup = (group: string) => {
    filterForm.group = filterForm.group === group ? '' : group;
    applyFiltersNow();
};

const destroy = async (item: Setting) => {
    if (await confirmDelete(`Hapus pengaturan ${item.key}?`)) {
        router.delete(route('master.system-settings.destroy', item.id));
    }
};

const previewValue = (value: string) => {
    if (!value) {
        return 'Belum diisi';
    }

    return value.length > 140 ? `${value.slice(0, 140)}...` : value;
};

const groupIcon = (group: string) => {
    const icons: Record<string, unknown> = {
        dashboard: BarChart3,
        dokumen: FileText,
        evaluasi: ClipboardCheck,
        identitas_aplikasi: ShieldCheck,
        integrasi: Database,
        keamanan: LockKeyhole,
        notifikasi: Bell,
        pelaporan: FileText,
        publik: Globe2,
        siklus_sakip: SlidersHorizontal,
        workflow: Workflow,
    };

    return icons[group] ?? Settings;
};
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <div class="flex flex-col gap-5 p-4 lg:p-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between">
            <div class="max-w-3xl">
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600"
                >
                    <Settings class="size-3.5" />
                    Konfigurasi aplikasi
                </div>
                <h1 class="mt-3 text-2xl font-semibold tracking-normal text-slate-950">Pengaturan Sistem</h1>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    Kelola identitas aplikasi, portal publik, dokumen, workflow, dashboard, evaluasi, integrasi, dan keamanan dari satu tempat.
                </p>
            </div>

            <Link
                v-if="can.manage"
                :href="route('master.system-settings.create')"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm shadow-blue-950/10 transition hover:bg-[#002957]"
            >
                <Plus class="size-4" />
                Tambah Pengaturan
            </Link>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
                v-for="group in groupSummaries"
                :key="group.key"
                type="button"
                class="group flex min-h-36 flex-col justify-between rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md"
                :class="filterForm.group === group.key ? 'border-[#00336C] ring-2 ring-blue-100' : 'border-slate-200'"
                @click="selectGroup(group.key)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-[#00336C] transition group-hover:bg-blue-50"
                        :class="filterForm.group === group.key ? 'bg-blue-50' : ''"
                    >
                        <component :is="groupIcon(group.key)" class="size-5" />
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ group.count }} setting</span>
                </div>
                <div>
                    <h2 class="mt-4 text-base font-semibold text-slate-950">{{ group.label }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm leading-5 text-slate-600">{{ group.description }}</p>
                </div>
            </button>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Daftar Pengaturan</h2>
                    <p class="mt-1 text-sm text-slate-600">Filter berjalan otomatis saat kata kunci atau pilihan berubah.</p>
                </div>
                <div v-if="isFiltering" class="inline-flex items-center gap-2 text-sm font-medium text-[#00336C]">
                    <LoaderCircle class="size-4 animate-spin" />
                    Memuat data
                </div>
            </div>

            <form class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_220px_180px_160px_auto]" @submit.prevent="applyFiltersNow">
                <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                    Pencarian
                    <span class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                            placeholder="Cari key, label, atau grup"
                        />
                    </span>
                </label>

                <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                    Grup
                    <select
                        v-model="filterForm.group"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Semua grup</option>
                        <option v-for="option in groupOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                    Tipe
                    <select
                        v-model="filterForm.type"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Semua tipe</option>
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                    Akses
                    <select
                        v-model="filterForm.is_public"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Semua akses</option>
                        <option value="1">Publik</option>
                        <option value="0">Internal</option>
                    </select>
                </label>

                <div class="flex items-end">
                    <button
                        type="button"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 lg:w-auto"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-4" />
                        Reset
                    </button>
                </div>
            </form>

            <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Pengaturan</th>
                                <th class="px-4 py-3">Grup</th>
                                <th class="px-4 py-3">Nilai Saat Ini</th>
                                <th class="px-4 py-3">Tipe</th>
                                <th class="px-4 py-3">Akses</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in items.data" :key="item.id" class="bg-white align-top transition hover:bg-slate-50/80">
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-950">{{ item.label }}</div>
                                    <div class="mt-1 font-mono text-xs text-slate-500">{{ item.key }}</div>
                                    <p v-if="item.description" class="mt-2 max-w-md text-sm leading-5 text-slate-600">{{ item.description }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#00336C]">
                                        {{ item.group_label }}
                                    </span>
                                    <p v-if="item.group_description" class="mt-2 max-w-xs text-xs leading-5 text-slate-500">
                                        {{ item.group_description }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <pre
                                        class="max-w-md whitespace-pre-wrap break-words rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs leading-5 text-slate-700"
                                        >{{ previewValue(item.value) }}</pre
                                    >
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                        {{ item.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="item.is_public ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ item.is_public ? 'Publik' : 'Internal' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div v-if="can.manage" class="inline-flex gap-2">
                                        <Link
                                            :href="route('master.system-settings.edit', item.id)"
                                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-white hover:shadow-sm"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50"
                                            @click="destroy(item)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-slate-500">Read-only</span>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                                        <Settings class="size-5" />
                                    </div>
                                    <div class="mt-3 font-semibold text-slate-900">Pengaturan tidak ditemukan</div>
                                    <p class="mt-1 text-sm text-slate-500">Ubah pencarian atau reset filter untuk melihat data lain.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 md:flex-row md:items-center md:justify-between"
                >
                    <span>Menampilkan {{ items.from ?? 0 }}-{{ items.to ?? 0 }} dari {{ items.total }} data</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="items.prev_page_url"
                            :href="items.prev_page_url"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50"
                        >
                            Sebelumnya
                        </Link>
                        <span v-else class="rounded-lg border border-slate-200 px-3 py-1.5 opacity-50">Sebelumnya</span>
                        <span class="px-2 py-1.5">Halaman {{ items.current_page }} / {{ items.last_page }}</span>
                        <Link
                            v-if="items.next_page_url"
                            :href="items.next_page_url"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50"
                        >
                            Berikutnya
                        </Link>
                        <span v-else class="rounded-lg border border-slate-200 px-3 py-1.5 opacity-50">Berikutnya</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
