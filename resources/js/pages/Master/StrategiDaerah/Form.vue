<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ArrowLeft from 'lucide-vue-next/dist/esm/icons/arrow-left.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';

type StrategiForm = {
    kode: string;
    strategi: string;
    status: 'active' | 'inactive';
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item: (StrategiForm & { id: number }) | null;
}>();

const form = useForm<StrategiForm>({
    kode: props.item?.kode ?? '',
    strategi: props.item?.strategi ?? '',
    status: props.item?.status ?? 'active',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.strategi-daerah.store'));
        return;
    }

    form.put(route('master.strategi-daerah.update', props.item?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah Strategi Daerah' : 'Edit Strategi Daerah'" />

    <form class="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4" @submit.prevent="submit">
        <header class="flex items-start gap-3">
            <Link
                :href="route('master.strategi-daerah.index')"
                class="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-md border bg-white text-slate-600 hover:bg-slate-50"
                title="Kembali"
            >
                <ArrowLeft class="size-4" />
            </Link>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#00336C]">Referensi Data</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-normal">
                    {{ mode === 'create' ? 'Tambah Strategi Daerah' : 'Edit Strategi Daerah' }}
                </h1>
            </div>
        </header>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="border-b bg-slate-50 px-5 py-4">
                <h2 class="font-semibold">Informasi Strategi</h2>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-12">
                <div class="grid gap-2 md:col-span-6">
                    <label class="text-sm font-medium" for="kode">Kode</label>
                    <input
                        id="kode"
                        v-model="form.kode"
                        class="h-10 rounded-md border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/15"
                        placeholder="Opsional"
                    />
                    <InputError :message="form.errors.kode" />
                </div>
                <div class="grid gap-2 md:col-span-6">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select
                        id="status"
                        v-model="form.status"
                        class="h-10 rounded-md border bg-background px-3 text-sm outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/15"
                    >
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-2 md:col-span-12">
                    <label class="text-sm font-medium" for="strategi">Strategi Daerah</label>
                    <textarea
                        id="strategi"
                        v-model="form.strategi"
                        rows="4"
                        class="rounded-md border bg-background px-3 py-2.5 text-sm leading-6 outline-none focus:border-[#00336C] focus:ring-2 focus:ring-[#00336C]/15"
                        placeholder="Tuliskan rumusan strategi daerah"
                    />
                    <InputError :message="form.errors.strategi" />
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('master.strategi-daerah.index')" class="inline-flex h-10 items-center rounded-md border px-4 text-sm font-medium hover:bg-muted">
                Batal
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-10 items-center gap-2 rounded-md bg-[#00336C] px-4 text-sm font-semibold text-white hover:bg-[#002650] disabled:opacity-60"
            >
                <Save class="size-4" />
                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
            </button>
        </div>
    </form>
</template>
