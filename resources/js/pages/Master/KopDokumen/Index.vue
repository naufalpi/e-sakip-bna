<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import FileBadge2 from 'lucide-vue-next/dist/esm/icons/file-badge-2.js';
import ImageUp from 'lucide-vue-next/dist/esm/icons/image-up.js';
import Landmark from 'lucide-vue-next/dist/esm/icons/landmark.js';
import Save from 'lucide-vue-next/dist/esm/icons/save.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import Trash2 from 'lucide-vue-next/dist/esm/icons/trash-2.js';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type KopProfile = {
    id?: number | null;
    scope_key: string;
    label: string;
    opd?: { id: number; kode: string; nama: string; singkatan?: string | null } | null;
    is_configured: boolean;
    nama_pemerintah: string;
    nama_instansi: string;
    alamat?: string | null;
    telepon?: string | null;
    faksimile?: string | null;
    website?: string | null;
    email?: string | null;
    kota: string;
    kode_pos?: string | null;
    logo_path?: string | null;
    logo_url: string;
};

const props = defineProps<{
    items: KopProfile[];
    can: { manage: boolean };
}>();

const search = ref('');
const selectedScope = ref(props.items[0]?.scope_key ?? '');
const selected = computed(() => props.items.find((item) => item.scope_key === selectedScope.value) ?? props.items[0]);
const filteredItems = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase('id');
    if (!needle) return props.items;
    return props.items.filter((item) => `${item.label} ${item.opd?.nama ?? ''} ${item.opd?.kode ?? ''}`.toLocaleLowerCase('id').includes(needle));
});

const form = useForm({
    nama_pemerintah: '',
    nama_instansi: '',
    alamat: '',
    telepon: '',
    faksimile: '',
    website: '',
    email: '',
    kota: '',
    kode_pos: '',
    logo: null as File | null,
    hapus_logo: false,
});

const localLogoUrl = ref<string | null>(null);
const revokeLocalLogo = () => {
    if (localLogoUrl.value) URL.revokeObjectURL(localLogoUrl.value);
    localLogoUrl.value = null;
};

const loadSelected = () => {
    const profile = selected.value;
    if (!profile) return;
    revokeLocalLogo();
    form.nama_pemerintah = profile.nama_pemerintah;
    form.nama_instansi = profile.nama_instansi;
    form.alamat = profile.alamat ?? '';
    form.telepon = profile.telepon ?? '';
    form.faksimile = profile.faksimile ?? '';
    form.website = profile.website ?? '';
    form.email = profile.email ?? '';
    form.kota = profile.kota;
    form.kode_pos = profile.kode_pos ?? '';
    form.logo = null;
    form.hapus_logo = false;
    form.clearErrors();
};

watch(selectedScope, loadSelected, { immediate: true });
onBeforeUnmount(revokeLocalLogo);

const previewLogo = computed(() => {
    if (form.hapus_logo) return '/images/logo-banjarnegara.png';
    return localLogoUrl.value || selected.value?.logo_url || '/images/logo-banjarnegara.png';
});

const chooseLogo = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    revokeLocalLogo();
    form.logo = file;
    form.hapus_logo = false;
    if (file) localLogoUrl.value = URL.createObjectURL(file);
};

const removeLogo = () => {
    revokeLocalLogo();
    form.logo = null;
    form.hapus_logo = true;
};

const save = () => {
    if (!selected.value) return;
    form.post(route('master.kop-dokumen.update', { scopeKey: selected.value.scope_key }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: loadSelected,
    });
};
</script>

<template>
    <Head title="Pengaturan Kop Dokumen" />

    <div class="flex flex-col gap-5 p-4 lg:p-6">
        <header class="border-b border-border pb-5">
            <div class="flex items-start gap-4">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#00336C] text-white shadow-sm">
                    <FileBadge2 class="size-5" />
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Referensi dokumen resmi</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Pengaturan Kop Dokumen</h1>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Atur identitas kop Kabupaten dan perangkat daerah. Pengaturan ini menjadi sumber awal untuk dokumen PK baru.
                    </p>
                </div>
            </div>
        </header>

        <div v-if="items.length" class="grid min-h-[680px] overflow-hidden rounded-2xl border bg-card shadow-sm lg:grid-cols-[290px_minmax(0,1fr)]">
            <aside class="border-b bg-muted/25 p-4 lg:border-b-0 lg:border-r">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="search" class="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm outline-none focus:border-primary" placeholder="Cari perangkat daerah" />
                </div>

                <div class="mt-3 space-y-1.5 lg:max-h-[600px] lg:overflow-y-auto lg:pr-1">
                    <button
                        v-for="item in filteredItems"
                        :key="item.scope_key"
                        type="button"
                        class="flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-left transition"
                        :class="selectedScope === item.scope_key ? 'border-blue-200 bg-blue-50 text-blue-950 shadow-sm dark:border-blue-900 dark:bg-blue-950/35 dark:text-blue-100' : 'border-transparent hover:border-border hover:bg-background'"
                        @click="selectedScope = item.scope_key"
                    >
                        <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-background text-primary ring-1 ring-border">
                            <Landmark v-if="item.scope_key === 'kabupaten'" class="size-4" />
                            <Building2 v-else class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-bold">{{ item.label }}</div>
                            <div class="mt-0.5 truncate text-[11px] text-muted-foreground">{{ item.opd?.kode || 'Pemerintah Kabupaten' }}</div>
                        </div>
                        <CheckCircle2 v-if="item.is_configured" class="mt-1 size-4 shrink-0 text-emerald-600" />
                    </button>
                </div>
            </aside>

            <main v-if="selected" class="min-w-0 p-4 sm:p-6">
                <div class="mb-5 flex flex-col gap-2 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">Profil aktif</p>
                        <h2 class="mt-1 text-lg font-bold">{{ selected.opd?.nama || 'Pemerintah Kabupaten Banjarnegara' }}</h2>
                    </div>
                    <span class="w-fit rounded-full border bg-muted/40 px-3 py-1 text-[11px] font-semibold text-muted-foreground">
                        {{ selected.is_configured ? 'Sudah dikonfigurasi' : 'Menggunakan nilai awal' }}
                    </span>
                </div>

                <section class="overflow-hidden rounded-xl border bg-white text-slate-950 shadow-[0_10px_35px_-25px_rgba(15,23,42,.5)]">
                    <div class="grid grid-cols-[74px_minmax(0,1fr)_34px] items-center gap-3 px-5 py-4">
                        <img :src="previewLogo" alt="Logo kop" class="mx-auto max-h-20 max-w-[70px] object-contain" />
                        <div class="text-center leading-tight">
                            <div class="text-sm font-medium uppercase">{{ form.nama_pemerintah || 'PEMERINTAH KABUPATEN BANJARNEGARA' }}</div>
                            <div class="mt-0.5 text-xl font-black uppercase tracking-tight">{{ form.nama_instansi || 'NAMA INSTANSI' }}</div>
                            <div class="mt-1 text-[11px] leading-4">
                                {{ form.alamat || 'Alamat instansi' }}<template v-if="form.telepon"> Telepon {{ form.telepon }}</template><template v-if="form.faksimile"> Faksimile {{ form.faksimile }}</template>
                                <template v-if="form.website || form.email"><br /><span v-if="form.website">Website {{ form.website }}</span><span v-if="form.website && form.email"> · </span><span v-if="form.email">Surel {{ form.email }}</span></template>
                            </div>
                            <div class="text-[11px] font-semibold uppercase">{{ form.kota || 'BANJARNEGARA' }} {{ form.kode_pos }}</div>
                        </div>
                    </div>
                    <div class="mx-5 border-b-2 border-slate-950"></div>
                </section>

                <form class="mt-6" @submit.prevent="save">
                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                        <div class="field md:col-span-2"><label for="nama_pemerintah">Nama pemerintah</label><input id="nama_pemerintah" v-model="form.nama_pemerintah" /><InputError :message="form.errors.nama_pemerintah" /></div>
                        <div class="field md:col-span-2"><label for="nama_instansi">Nama instansi / perangkat daerah</label><input id="nama_instansi" v-model="form.nama_instansi" /><InputError :message="form.errors.nama_instansi" /></div>
                        <div class="field md:col-span-2"><label for="alamat">Alamat</label><textarea id="alamat" v-model="form.alamat" rows="2"></textarea><InputError :message="form.errors.alamat" /></div>
                        <div class="field"><label for="telepon">Telepon</label><input id="telepon" v-model="form.telepon" placeholder="(0286) 591218" /><InputError :message="form.errors.telepon" /></div>
                        <div class="field"><label for="faksimile">Faksimile</label><input id="faksimile" v-model="form.faksimile" placeholder="(0286) 591187" /><InputError :message="form.errors.faksimile" /></div>
                        <div class="field"><label for="website">Website</label><input id="website" v-model="form.website" placeholder="www.banjarnegarakab.go.id" /><InputError :message="form.errors.website" /></div>
                        <div class="field"><label for="email">Surel</label><input id="email" v-model="form.email" type="email" placeholder="instansi@banjarnegarakab.go.id" /><InputError :message="form.errors.email" /></div>
                        <div class="field"><label for="kota">Kota</label><input id="kota" v-model="form.kota" /><InputError :message="form.errors.kota" /></div>
                        <div class="field"><label for="kode_pos">Kode pos</label><input id="kode_pos" v-model="form.kode_pos" /><InputError :message="form.errors.kode_pos" /></div>

                        <div class="md:col-span-2 rounded-xl border border-dashed p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div><div class="text-sm font-bold">Logo kop</div><p class="mt-0.5 text-xs text-muted-foreground">PNG/JPG/WebP, maksimal 2 MB. Jika dihapus, lambang Banjarnegara standar digunakan.</p></div>
                                <div class="flex gap-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold hover:bg-muted"><ImageUp class="size-4" /> Pilih Logo<input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="chooseLogo" /></label>
                                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 dark:hover:bg-red-950/30" @click="removeLogo"><Trash2 class="size-4" /> Gunakan standar</button>
                                </div>
                            </div>
                            <InputError :message="form.errors.logo" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end border-t pt-4">
                        <button v-if="can.manage" type="submit" :disabled="form.processing" class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#00336C] px-5 text-sm font-bold text-white shadow-sm hover:bg-[#002957] disabled:opacity-60"><Save class="size-4" />{{ form.processing ? 'Menyimpan...' : 'Simpan Kop' }}</button>
                    </div>
                </form>
            </main>
        </div>

        <div v-else class="rounded-2xl border border-dashed p-12 text-center text-sm text-muted-foreground">Tidak ada lingkup kop dokumen yang dapat dikelola.</div>
    </div>
</template>

<style scoped>
.field { display: grid; gap: 0.4rem; }
.field label { color: hsl(var(--muted-foreground)); font-size: 0.7rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.field input, .field textarea { width: 100%; border: 1px solid hsl(var(--border)); border-radius: 0.65rem; background: hsl(var(--background)); padding: 0.7rem 0.8rem; color: hsl(var(--foreground)); font-size: 0.875rem; outline: none; transition: border-color 150ms, box-shadow 150ms; }
.field input:focus, .field textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgb(59 130 246 / 0.12); }
</style>
