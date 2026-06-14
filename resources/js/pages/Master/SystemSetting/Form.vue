<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import Info from 'lucide-vue-next/dist/esm/icons/info.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import ShieldAlert from 'lucide-vue-next/dist/esm/icons/shield-alert.js';
import SlidersHorizontal from 'lucide-vue-next/dist/esm/icons/sliders-horizontal.js';
import { computed } from 'vue';

type SettingForm = {
    group: string;
    key: string;
    label: string;
    type: string;
    value: string;
    is_public: boolean;
};

type GroupOption = {
    value: string;
    label: string;
    description?: string;
};

type TypeOption = {
    value: string;
    label: string;
    description?: string;
};

type CatalogSetting = {
    group: string;
    label: string;
    type: string;
    value: unknown;
    is_public: boolean;
    description?: string;
    placeholder?: string;
};

type SettingCatalog = {
    groups: Record<string, { label: string; description: string }>;
    settings: Record<string, CatalogSetting>;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (SettingForm & { id: number; description?: string | null; placeholder?: string | null }) | null;
    groupOptions: GroupOption[];
    typeOptions: TypeOption[];
    settingCatalog: SettingCatalog;
}>();

const stringifyValue = (value: unknown) => {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};

const form = useForm<SettingForm>({
    group: props.item?.group ?? 'identitas_aplikasi',
    key: props.item?.key ?? '',
    label: props.item?.label ?? '',
    type: props.item?.type ?? 'string',
    value: props.item?.value ?? '',
    is_public: props.item?.is_public ?? false,
});

const catalogOptions = computed(() =>
    Object.entries(props.settingCatalog.settings).map(([key, setting]) => ({
        key,
        ...setting,
        groupLabel: props.settingCatalog.groups[setting.group]?.label ?? setting.group,
    })),
);

const selectedGroup = computed(() => props.groupOptions.find((option) => option.value === form.group));
const selectedType = computed(() => props.typeOptions.find((option) => option.value === form.type));
const selectedCatalog = computed(() => props.settingCatalog.settings[form.key]);
const valuePlaceholder = computed(() => selectedCatalog.value?.placeholder ?? props.item?.placeholder ?? placeholderByType(form.type));
const settingDescription = computed(
    () => selectedCatalog.value?.description ?? props.item?.description ?? 'Pengaturan tambahan yang dapat digunakan oleh aplikasi.',
);

const placeholderByType = (type: string) => {
    if (type === 'boolean') {
        return '1 untuk ya, 0 untuk tidak';
    }

    if (type === 'integer') {
        return 'Contoh: 300';
    }

    if (type === 'json') {
        return '{\n  "key": "value"\n}';
    }

    return 'Masukkan nilai pengaturan';
};

const applyPreset = (key: string) => {
    const preset = props.settingCatalog.settings[key];

    if (!preset) {
        return;
    }

    form.group = preset.group;
    form.key = key;
    form.label = preset.label;
    form.type = preset.type;
    form.value = stringifyValue(preset.value);
    form.is_public = preset.is_public;
};

const applyPresetFromEvent = (event: Event) => {
    applyPreset((event.target as HTMLSelectElement).value);
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.system-settings.store'));
        return;
    }

    form.put(route('master.system-settings.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Pengaturan Sistem' : 'Edit Pengaturan Sistem'" />

    <form class="flex flex-col gap-5 p-4 lg:p-6" @submit.prevent="submit">
        <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between">
            <div>
                <Link
                    :href="route('master.system-settings.index')"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-[#00336C]"
                >
                    <ArrowLeft class="size-4" />
                    Kembali ke pengaturan
                </Link>
                <h1 class="mt-3 text-2xl font-semibold tracking-normal text-slate-950">
                    {{ mode === 'create' ? 'Tambah Pengaturan Sistem' : 'Edit Pengaturan Sistem' }}
                </h1>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    Gunakan key yang stabil karena konfigurasi dapat dirujuk controller, service, dashboard, dan portal publik.
                </p>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex items-start gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#00336C]">
                        <SlidersHorizontal class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Detail Pengaturan</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Isi grup, key, tipe, dan nilai. Untuk pengaturan bawaan, pilih preset agar formatnya konsisten.
                        </p>
                    </div>
                </div>

                <div v-if="mode === 'create'" class="mb-5 grid gap-2">
                    <label class="text-sm font-semibold text-slate-800" for="preset">Preset pengaturan</label>
                    <select
                        id="preset"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                        @change="applyPresetFromEvent"
                    >
                        <option value="">Pilih preset jika ingin memakai format bawaan</option>
                        <option v-for="preset in catalogOptions" :key="preset.key" :value="preset.key">
                            {{ preset.groupLabel }} - {{ preset.label }} ({{ preset.key }})
                        </option>
                    </select>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-semibold text-slate-800" for="group">Grup</label>
                        <input
                            id="group"
                            v-model="form.group"
                            list="group-options"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                            placeholder="contoh: dashboard"
                        />
                        <datalist id="group-options">
                            <option v-for="option in groupOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </datalist>
                        <p v-if="selectedGroup?.description" class="text-xs leading-5 text-slate-500">{{ selectedGroup.description }}</p>
                        <InputError :message="form.errors.group" />
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-semibold text-slate-800" for="type">Tipe Nilai</label>
                        <select
                            id="type"
                            v-model="form.type"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                        >
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <p v-if="selectedType?.description" class="text-xs leading-5 text-slate-500">{{ selectedType.description }}</p>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-semibold text-slate-800" for="key">Key</label>
                        <input
                            id="key"
                            v-model="form.key"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 font-mono text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                            placeholder="contoh: dashboard.cache_ttl_seconds"
                        />
                        <p class="text-xs leading-5 text-slate-500">
                            Gunakan format domain.subdomain, misalnya <span class="font-mono">dokumen.max_upload_mb</span>.
                        </p>
                        <InputError :message="form.errors.key" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-semibold text-slate-800" for="label">Label</label>
                        <input
                            id="label"
                            v-model="form.label"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                            placeholder="Nama yang mudah dipahami admin"
                        />
                        <InputError :message="form.errors.label" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-semibold text-slate-800" for="value">Nilai</label>
                        <textarea
                            v-if="form.type === 'text' || form.type === 'json'"
                            id="value"
                            v-model="form.value"
                            rows="9"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-sm leading-6 outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                            :placeholder="valuePlaceholder"
                        />
                        <select
                            v-else-if="form.type === 'boolean'"
                            id="value"
                            v-model="form.value"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="1">Ya / aktif</option>
                            <option value="0">Tidak / nonaktif</option>
                        </select>
                        <input
                            v-else
                            id="value"
                            v-model="form.value"
                            :type="form.type === 'integer' ? 'number' : 'text'"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[#00336C] focus:ring-2 focus:ring-blue-100"
                            :placeholder="valuePlaceholder"
                        />
                        <p class="text-xs leading-5 text-slate-500">{{ settingDescription }}</p>
                        <InputError :message="form.errors.value" />
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm md:col-span-2">
                        <input v-model="form.is_public" type="checkbox" class="mt-1 rounded border-slate-300 text-[#00336C] focus:ring-[#00336C]" />
                        <span>
                            <span class="block font-semibold text-slate-900">Dapat dibaca publik oleh frontend</span>
                            <span class="mt-1 block leading-5 text-slate-600">
                                Aktifkan hanya untuk data yang aman ditampilkan pada portal publik, misalnya nama aplikasi, tagline, atau teks footer.
                            </span>
                        </span>
                    </label>
                </div>
            </section>

            <aside class="flex flex-col gap-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                            <CheckCircle2 class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Pengaturan yang disarankan</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Prioritaskan setting identitas aplikasi, tahun default, batas upload, workflow, cache dashboard, dan portal publik.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-amber-700">
                            <ShieldAlert class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-amber-950">Catatan keamanan</h2>
                            <p class="mt-1 text-sm leading-6 text-amber-900">
                                Jangan jadikan token API, credential integrasi, atau setting internal sebagai publik. Nilai publik dapat dipakai oleh
                                halaman frontend.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#00336C]">
                            <Info class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Format nilai</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Boolean memakai Ya/Tidak. JSON harus valid, misalnya daftar MIME type atau bobot evaluasi. Angka disimpan sebagai
                                integer.
                            </p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <div
            class="sticky bottom-0 z-10 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur lg:-mx-6 lg:px-6"
        >
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <Link
                    :href="route('master.system-settings.index')"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#00336C] px-4 text-sm font-semibold text-white shadow-sm shadow-blue-950/10 transition hover:bg-[#002957] disabled:opacity-60"
                >
                    <Save class="size-4" />
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </button>
            </div>
        </div>
    </form>
</template>
