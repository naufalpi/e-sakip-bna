<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, CheckCircle2, FileCheck2, Layers3 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Readiness = { ready: boolean; issues: string[]; warnings: string[]; counts: Record<string, number> };
type PkOption = {
    id: number;
    label: string;
    opd_id: number;
    periode_tahun_id: number;
    tahun: number;
    opd_label?: string | null;
    renstra_label?: string | null;
    dpa_label?: string | null;
    readiness: Readiness | null;
};
type FormData = {
    opd_id: number | string | null;
    perjanjian_kinerja_id: number | string | null;
    periode_tahun_id: number | string | null;
    tahun: number | string;
    judul: string;
    status: string;
    catatan: string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    item:
        | (FormData & {
              id: number;
              perjanjian_kinerja_label?: string | null;
              opd_label?: string | null;
              renstra_label?: string | null;
              dpa_label?: string | null;
          })
        | null;
    perjanjianKinerjaOptions: PkOption[];
}>();

const form = useForm<FormData>({
    opd_id: props.item?.opd_id ?? '',
    perjanjian_kinerja_id: props.item?.perjanjian_kinerja_id ?? '',
    periode_tahun_id: props.item?.periode_tahun_id ?? '',
    tahun: props.item?.tahun ?? new Date().getFullYear(),
    judul: props.item?.judul ?? '',
    status: props.item?.status ?? 'draft',
    catatan: props.item?.catatan ?? '',
});

const selectedPk = computed(() => props.perjanjianKinerjaOptions.find((option) => Number(option.id) === Number(form.perjanjian_kinerja_id)));
const selectedReadiness = ref<Readiness | null>(null);
const readinessLoading = ref(false);
let readinessRequest = 0;

watch(selectedPk, async (pk) => {
    const requestId = ++readinessRequest;
    selectedReadiness.value = null;
    if (!pk || props.mode === 'edit') {
        readinessLoading.value = false;
        return;
    }
    form.opd_id = pk.opd_id;
    form.periode_tahun_id = pk.periode_tahun_id;
    form.tahun = pk.tahun;
    form.status = 'draft';
    form.judul = `RENCANA AKSI ${pk.opd_label || 'OPD'} TAHUN ${pk.tahun}`;
    readinessLoading.value = true;
    try {
        const response = await fetch(route('rencana-aksi.pk-readiness', { perjanjianKinerja: pk.id }), {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('Gagal memeriksa sumber Rencana Aksi.');
        const result = (await response.json()) as Readiness;
        if (requestId === readinessRequest) selectedReadiness.value = result;
    } catch {
        if (requestId === readinessRequest) {
            selectedReadiness.value = {
                ready: false,
                issues: ['Pemeriksaan sumber gagal. Muat ulang halaman lalu coba kembali.'],
                warnings: [],
                counts: {},
            };
        }
    } finally {
        if (requestId === readinessRequest) readinessLoading.value = false;
    }
});

const submit = () => {
    if (props.mode === 'create') form.post(route('rencana-aksi.store'));
    else if (props.item) form.put(route('rencana-aksi.update', { rencana_aksi: props.item.id }));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Buat Rencana Aksi' : 'Edit Rencana Aksi'" />
    <main class="mx-auto flex w-full max-w-[1400px] flex-col gap-5 p-4 sm:p-6">
        <header class="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-950 to-blue-800 px-5 py-6 text-white shadow-sm sm:px-7">
            <Link
                :href="route('rencana-aksi.index')"
                class="mb-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-white/25 px-3 text-sm font-medium hover:bg-white/10"
            >
                <ArrowLeft class="size-4" /> Kembali
            </Link>
            <h1 class="text-2xl font-bold tracking-tight">{{ mode === 'create' ? 'Buat Rencana Aksi OPD' : 'Edit Rencana Aksi OPD' }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-blue-100">
                Matriks dibentuk dari PK Kepala OPD yang sudah resmi, lalu dilengkapi dengan cascading RENSTRA dan anggaran DPA/DPPA.
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <section v-if="mode === 'create'" class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-800"><FileCheck2 class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">Pilih PK Kepala OPD</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Hanya PK berstatus disetujui atau terkunci yang dapat digunakan.</p>
                    </div>
                </div>
                <div class="mt-5 grid max-w-4xl gap-2">
                    <label for="perjanjian_kinerja_id" class="text-sm font-semibold">Perjanjian Kinerja <span class="text-red-600">*</span></label>
                    <select
                        id="perjanjian_kinerja_id"
                        v-model="form.perjanjian_kinerja_id"
                        class="min-h-11 rounded-lg border bg-background px-3 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Pilih PK Kepala OPD</option>
                        <option v-for="option in perjanjianKinerjaOptions" :key="option.id" :value="option.id">
                            {{ option.label }} — {{ option.opd_label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.perjanjian_kinerja_id" />
                </div>
                <div
                    v-if="perjanjianKinerjaOptions.length === 0"
                    class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    Belum ada PK Kepala OPD resmi yang siap dipilih, atau seluruh PK yang tersedia sudah memiliki Rencana Aksi.
                </div>

                <div
                    v-if="selectedPk && readinessLoading"
                    class="mt-5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-4 text-sm font-medium text-blue-900"
                >
                    Memeriksa keterhubungan PK, RENSTRA, RENJA, dan DPA/DPPA…
                </div>
                <div
                    v-else-if="selectedPk && selectedReadiness"
                    class="mt-5 overflow-hidden rounded-xl border"
                    :class="selectedReadiness.ready ? 'border-emerald-200' : 'border-red-200'"
                >
                    <div
                        class="flex items-center gap-3 px-4 py-3"
                        :class="selectedReadiness.ready ? 'bg-emerald-50 text-emerald-900' : 'bg-red-50 text-red-900'"
                    >
                        <CheckCircle2 v-if="selectedReadiness.ready" class="size-5 shrink-0" /><AlertCircle v-else class="size-5 shrink-0" />
                        <div>
                            <div class="font-semibold">
                                {{ selectedReadiness.ready ? 'Sumber data siap digunakan' : 'Sumber data belum lengkap' }}
                            </div>
                            <div class="text-xs opacity-80">
                                {{ selectedReadiness.counts.baris || 0 }} baris indikator akan dibuat sebagai snapshot.
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 p-4 text-sm sm:grid-cols-3">
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">OPD</span
                            ><span class="mt-1 block font-medium">{{ selectedPk.opd_label || '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">RENSTRA</span
                            ><span class="mt-1 block font-medium">{{ selectedPk.renstra_label || '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">DPA/DPPA</span
                            ><span class="mt-1 block font-medium">{{ selectedPk.dpa_label || '-' }}</span>
                        </div>
                    </div>
                    <ul v-if="selectedReadiness.issues.length" class="space-y-1 border-t border-red-200 bg-red-50/50 px-8 py-3 text-sm text-red-800">
                        <li v-for="issue in selectedReadiness.issues" :key="issue" class="list-disc">{{ issue }}</li>
                    </ul>
                    <ul
                        v-if="selectedReadiness.warnings.length"
                        class="space-y-1 border-t border-amber-200 bg-amber-50 px-8 py-3 text-sm text-amber-900"
                    >
                        <li v-for="warning in selectedReadiness.warnings" :key="warning" class="list-disc">{{ warning }}</li>
                    </ul>
                </div>
            </section>

            <section v-else class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-700"><Layers3 class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">Sumber snapshot</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Sumber tidak dapat diganti setelah matriks dibentuk.</p>
                    </div>
                </div>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">PK</dt>
                        <dd class="mt-1 font-medium">{{ item?.perjanjian_kinerja_label || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">RENSTRA</dt>
                        <dd class="mt-1 font-medium">{{ item?.renstra_label || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">DPA/DPPA</dt>
                        <dd class="mt-1 font-medium">{{ item?.dpa_label || '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
                <h2 class="font-semibold">Identitas dokumen</h2>
                <div class="mt-5 grid max-w-4xl gap-5">
                    <div class="grid gap-2">
                        <label for="judul" class="text-sm font-semibold">Judul <span class="text-red-600">*</span></label
                        ><input
                            id="judul"
                            v-model="form.judul"
                            class="min-h-11 rounded-lg border bg-background px-3 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                        /><InputError :message="form.errors.judul" />
                    </div>
                    <div class="grid gap-2">
                        <label for="catatan" class="text-sm font-semibold"
                            >Catatan <span class="font-normal text-muted-foreground">(opsional)</span></label
                        ><textarea
                            id="catatan"
                            v-model="form.catatan"
                            rows="4"
                            class="rounded-lg border bg-background px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                        /><InputError :message="form.errors.catatan" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-3">
                <Link
                    :href="route('rencana-aksi.index')"
                    class="inline-flex min-h-11 items-center rounded-lg border px-4 text-sm font-semibold hover:bg-muted"
                    >Batal</Link
                >
                <button
                    type="submit"
                    :disabled="form.processing || readinessLoading || (mode === 'create' && !selectedReadiness?.ready)"
                    class="min-h-11 rounded-lg bg-blue-800 px-5 text-sm font-semibold text-white hover:bg-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan…' : mode === 'create' ? 'Buat Matriks Rencana Aksi' : 'Simpan Perubahan' }}
                </button>
            </div>
        </form>
    </main>
</template>
