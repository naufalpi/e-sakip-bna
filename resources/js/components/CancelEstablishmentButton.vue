<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, RotateCcw, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    module: 'rkpd' | 'renja-opd';
    modelId: number;
}>();

const isOpen = ref(false);
const form = useForm({
    alasan_pembatalan: '',
    konfirmasi: '',
});

const isRkpd = computed(() => props.module === 'rkpd');
const documentLabel = computed(() => (isRkpd.value ? 'RKPD' : 'RENJA'));
const endpoint = computed(() =>
    isRkpd.value ? route('rkpd.establishment.cancel', props.modelId) : route('renja-opd.establishment.cancel', props.modelId),
);

const close = () => {
    if (form.processing) return;

    isOpen.value = false;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    form.post(endpoint.value, {
        preserveScroll: true,
        onSuccess: close,
    });
};
</script>

<template>
    <button
        type="button"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 text-sm font-semibold text-red-700 shadow-sm transition-colors hover:border-red-300 hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
        @click="isOpen = true"
    >
        <RotateCcw class="size-4" />
        Batalkan Penetapan
    </button>

    <Teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="`Batalkan penetapan ${documentLabel}`"
            @click.self="close"
        >
            <form class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" @submit.prevent="submit">
                <div class="flex items-start gap-4 border-b border-slate-200 px-5 py-5">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 ring-1 ring-red-100">
                        <AlertTriangle class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-red-600">Tindakan khusus Super Admin</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-950">Batalkan Penetapan {{ documentLabel }}?</h2>
                        <p class="mt-1.5 text-sm leading-6 text-slate-600">
                            Versi {{ documentLabel }} Ditetapkan akan diarsipkan dan versi {{ documentLabel }} Awal dipulihkan ke Draft beserta seluruh isi sebelumnya.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                        aria-label="Tutup"
                        @click="close"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                        <template v-if="isRkpd">
                            Batalkan penetapan seluruh RENJA terlebih dahulu. RENJA Awal yang sudah kembali Draft dapat diajukan lagi, lalu disinkronkan ke RKPD Draft.
                        </template>
                        <template v-else>
                            Pembatalan akan ditolak bila RENJA ini sudah memiliki RKA atau DPA. Selesaikan dokumen turunan tersebut terlebih dahulu.
                        </template>
                    </div>

                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold text-slate-800">Alasan pembatalan <span class="text-red-600">*</span></span>
                        <textarea
                            v-model="form.alasan_pembatalan"
                            rows="4"
                            class="rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-100"
                            placeholder="Contoh: Penetapan dilakukan sebelum proses sinkronisasi RENJA selesai."
                        ></textarea>
                        <span v-if="form.errors.alasan_pembatalan" class="text-xs font-medium text-red-600">{{ form.errors.alasan_pembatalan }}</span>
                    </label>

                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold text-slate-800">Ketik <strong>BATALKAN</strong> untuk melanjutkan</span>
                        <input
                            v-model="form.konfirmasi"
                            type="text"
                            autocomplete="off"
                            class="h-11 rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold uppercase text-slate-900 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-100"
                            placeholder="BATALKAN"
                        />
                        <span v-if="form.errors.konfirmasi" class="text-xs font-medium text-red-600">{{ form.errors.konfirmasi }}</span>
                    </label>

                    <p v-if="form.errors.document" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700">
                        {{ form.errors.document }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                        @click="close"
                    >
                        Kembali
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white shadow-sm hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || form.konfirmasi !== 'BATALKAN' || form.alasan_pembatalan.trim().length < 10"
                    >
                        <RotateCcw class="size-4" />
                        {{ form.processing ? 'Memproses...' : 'Ya, Batalkan Penetapan' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
