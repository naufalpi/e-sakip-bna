<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

type SettingForm = {
    group: string;
    key: string;
    label: string;
    type: string;
    value: string;
    is_public: boolean;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (SettingForm & { id: number }) | null;
    groupOptions: Array<{ value: string; label: string }>;
    typeOptions: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pengaturan Sistem', href: '/master/system-settings' },
    { title: props.mode === 'create' ? 'Tambah' : 'Edit', href: '#' },
];

const form = useForm<SettingForm>({
    group: props.item?.group ?? 'umum',
    key: props.item?.key ?? '',
    label: props.item?.label ?? '',
    type: props.item?.type ?? 'string',
    value: props.item?.value ?? '',
    is_public: props.item?.is_public ?? false,
});

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

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex max-w-3xl flex-col gap-4 p-4" @submit.prevent="submit">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah Pengaturan Sistem' : 'Edit Pengaturan Sistem' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Gunakan key stabil karena konfigurasi dapat dirujuk dari kode aplikasi.</p>
            </div>

            <section class="rounded-lg border bg-card p-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="group">Grup</label>
                        <input id="group" v-model="form.group" list="group-options" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <datalist id="group-options">
                            <option v-for="option in groupOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </datalist>
                        <InputError :message="form.errors.group" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="type">Tipe</label>
                        <select id="type" v-model="form.type" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="key">Key</label>
                        <input id="key" v-model="form.key" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="contoh: app.name" />
                        <InputError :message="form.errors.key" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="label">Label</label>
                        <input id="label" v-model="form.label" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.label" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium" for="value">Nilai</label>
                        <textarea v-if="form.type === 'text' || form.type === 'json'" id="value" v-model="form.value" rows="6" class="rounded-md border bg-background px-3 py-2 text-sm font-mono" />
                        <select v-else-if="form.type === 'boolean'" id="value" v-model="form.value" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                        <input v-else id="value" v-model="form.value" :type="form.type === 'integer' ? 'number' : 'text'" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <InputError :message="form.errors.value" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_public" type="checkbox" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-700" />
                        Dapat dibaca publik oleh frontend
                    </label>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="route('master.system-settings.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
                <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Simpan</button>
            </div>
        </form>
    </AppLayout>
</template>
