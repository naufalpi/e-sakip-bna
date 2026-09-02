<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { confirmAction } from '@/lib/sweetAlert';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type WorkflowAction = 'submit' | 'withdraw' | 'verify' | 'approve' | 'revision' | 'reject' | 'lock' | 'unlock' | 'correct';
type WorkflowActionItem = {
    action: WorkflowAction;
    label: string;
    className: string;
    title: string;
    description: string;
    noteLabel: string;
    notePlaceholder: string;
    noteRequired: boolean;
    referenceRequired?: boolean;
    confirmClassName: string;
};

const props = withDefaults(
    defineProps<{
        module: string;
        modelId: number;
        status: string;
        canManage: boolean;
        canReview: boolean;
        canLock?: boolean;
        canUnlock?: boolean;
        canWithdraw?: boolean;
        showVerify?: boolean;
        buttonClass?: string;
    }>(),
    {
        canLock: false,
        canUnlock: false,
        canWithdraw: false,
        showVerify: true,
        buttonClass: '',
    },
);

const isDialogOpen = ref(false);
const selectedAction = ref<WorkflowActionItem | null>(null);
const localNoteError = ref<string | null>(null);
const submissionConfirmationModules: Record<string, string> = {
    renstra_opd: 'RENSTRA',
    renja_opd: 'RENJA',
    rka_opd: 'RKA',
    dpa_opd: 'DPA',
};

const form = useForm<{
    action: WorkflowAction;
    note: string;
    correction_reference: string;
    current_reviewer_id: number | null;
}>({
    action: 'submit',
    note: '',
    correction_reference: '',
    current_reviewer_id: null,
});

const openTransitionDialog = (item: WorkflowActionItem) => {
    selectedAction.value = item;
    localNoteError.value = null;
    form.clearErrors();
    form.action = item.action;
    form.note = '';
    form.correction_reference = '';
    form.current_reviewer_id = null;
    isDialogOpen.value = true;
};

const submitTransition = async () => {
    if (!selectedAction.value) {
        return;
    }

    localNoteError.value = null;
    form.clearErrors('note', 'correction_reference', 'action');

    if (selectedAction.value.noteRequired && !form.note.trim()) {
        localNoteError.value = 'Catatan wajib diisi untuk aksi ini.';
        return;
    }

    if (selectedAction.value.referenceRequired && !form.correction_reference.trim()) {
        form.setError('correction_reference', 'Acuan dokumen resmi wajib diisi.');
        return;
    }

    const submissionModuleLabel = submissionConfirmationModules[props.module];

    if (selectedAction.value.action === 'submit' && submissionModuleLabel) {
        const confirmed = await confirmAction({
            title: `Pastikan data ${submissionModuleLabel} sudah valid`,
            text: `Periksa kembali seluruh data yang telah diinput. Setelah ${submissionModuleLabel} diajukan, dokumen masuk ke proses pemeriksaan dan tidak dapat diedit selama berstatus Diajukan atau Terverifikasi.`,
            icon: 'warning',
            confirmButtonText: 'Ya, ajukan data',
            cancelButtonText: 'Periksa kembali',
            focusCancel: true,
        });

        if (!confirmed) {
            isDialogOpen.value = false;
            selectedAction.value = null;
            form.reset();
            form.action = 'submit';

            return;
        }
    }

    form.post(route('workflow.transition', { module: props.module, id: props.modelId }), {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false;
            selectedAction.value = null;
            form.reset();
            form.action = 'submit';
        },
    });
};

const actions = computed(() => {
    const items: WorkflowActionItem[] = [];
    const correctionModules = ['rpjmd', 'rkpd', 'renstra_opd', 'renja_opd', 'rka_opd', 'dpa_opd'];
    const supportsCorrection = correctionModules.includes(props.module);

    if (props.canManage && ['draft', 'revision', 'rejected'].includes(props.status)) {
        items.push({
            action: 'submit',
            label: 'Ajukan',
            className: 'bg-blue-700 text-white hover:bg-blue-800',
            title: 'Ajukan Data',
            description: 'Data akan masuk ke daftar pengajuan untuk diperiksa sesuai modul terkait.',
            noteLabel: 'Catatan pengajuan',
            notePlaceholder: 'Contoh: Data sudah dilengkapi dan siap diperiksa.',
            noteRequired: false,
            confirmClassName: 'bg-blue-700 text-white hover:bg-blue-800',
        });
    }

    if (props.canWithdraw && props.status === 'submitted') {
        items.push({
            action: 'withdraw',
            label: 'Tarik Pengajuan',
            className: 'border border-amber-200 bg-white text-amber-700 hover:bg-amber-50',
            title: 'Tarik Pengajuan',
            description: 'Dokumen kembali ke Draft agar bisa diperbaiki atau dibatalkan.',
            noteLabel: 'Alasan penarikan',
            notePlaceholder: 'Tuliskan alasan penarikan pengajuan.',
            noteRequired: true,
            confirmClassName: 'bg-amber-600 text-white hover:bg-amber-700',
        });
    }

    if (props.canReview && props.status === 'submitted' && props.showVerify) {
        items.push({
            action: 'verify',
            label: 'Verifikasi',
            className: 'border hover:bg-muted',
            title: 'Verifikasi Data',
            description: 'Status data akan berubah menjadi terverifikasi dan dapat dilanjutkan ke persetujuan.',
            noteLabel: 'Catatan verifikasi',
            notePlaceholder: 'Contoh: Dokumen dan isian sudah sesuai.',
            noteRequired: false,
            confirmClassName: 'bg-emerald-700 text-white hover:bg-emerald-800',
        });
    }

    if (props.canReview && (props.status === 'verified' || (props.status === 'submitted' && !props.showVerify))) {
        items.push({
            action: 'approve',
            label: 'Setujui',
            className: 'border hover:bg-muted',
            title: 'Setujui Data',
            description: 'Status data akan berubah menjadi disetujui.',
            noteLabel: 'Catatan persetujuan',
            notePlaceholder: 'Contoh: Data disetujui sesuai hasil pemeriksaan.',
            noteRequired: false,
            confirmClassName: 'bg-emerald-700 text-white hover:bg-emerald-800',
        });
    }

    if (props.canReview && ['submitted', 'verified'].includes(props.status)) {
        items.push(
            {
                action: 'revision',
                label: 'Perbaiki',
                className: 'border text-amber-700 hover:bg-amber-50',
                title: 'Minta Perbaikan',
                description: 'Status data akan kembali ke OPD/penginput untuk diperbaiki.',
                noteLabel: 'Catatan perbaikan',
                notePlaceholder: 'Tuliskan bagian yang harus diperbaiki.',
                noteRequired: true,
                confirmClassName: 'bg-amber-600 text-white hover:bg-amber-700',
            },
            {
                action: 'reject',
                label: 'Tolak',
                className: 'border text-red-700 hover:bg-red-50',
                title: 'Tolak Data',
                description: 'Status data akan berubah menjadi ditolak dan alasan penolakan dicatat di riwayat.',
                noteLabel: 'Catatan penolakan',
                notePlaceholder: 'Tuliskan alasan penolakan.',
                noteRequired: true,
                confirmClassName: 'bg-red-700 text-white hover:bg-red-800',
            },
        );
    }

    if (props.canLock && props.status === 'approved') {
        items.push({
            action: 'lock',
            label: 'Kunci',
            className: 'border hover:bg-muted',
            title: 'Kunci Data',
            description: 'Data yang terkunci tidak dapat diubah oleh user biasa.',
            noteLabel: 'Catatan penguncian',
            notePlaceholder: 'Contoh: Dikunci setelah persetujuan final.',
            noteRequired: false,
            confirmClassName: 'bg-slate-900 text-white hover:bg-slate-800',
        });
    }

    if (props.canUnlock && supportsCorrection && ['approved', 'locked'].includes(props.status)) {
        items.push({
            action: 'correct',
            label: 'Koreksi Data',
            className: 'border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/65',
            title: 'Batalkan persetujuan untuk koreksi?',
            description: 'Gunakan hanya untuk menyamakan kesalahan input aplikasi dengan dokumen resmi. Aksi ini tidak membuat versi Perubahan.',
            noteLabel: 'Alasan koreksi',
            notePlaceholder: 'Contoh: Target salah input 25, seharusnya 20 sesuai dokumen resmi.',
            noteRequired: true,
            referenceRequired: true,
            confirmClassName: 'bg-amber-600 text-white hover:bg-amber-700',
        });
    }

    if (props.canUnlock && !supportsCorrection && props.status === 'locked') {
        items.push({
            action: 'unlock',
            label: 'Buka Kunci',
            className: 'border text-amber-700 hover:bg-amber-50',
            title: 'Buka Kunci Data',
            description: 'Status data akan kembali menjadi perlu perbaikan agar perubahan resmi dapat dilakukan dan tercatat di riwayat persetujuan.',
            noteLabel: 'Alasan buka kunci',
            notePlaceholder: 'Tuliskan dasar atau alasan resmi pembukaan kunci.',
            noteRequired: true,
            confirmClassName: 'bg-amber-600 text-white hover:bg-amber-700',
        });
    }

    return items;
});
</script>

<template>
    <button
        v-for="item in actions"
        :key="item.action"
        type="button"
        class="rounded-md px-3 py-2 text-sm font-medium"
        :class="[item.className, props.buttonClass, `workflow-action--${item.action}`]"
        @click="openTransitionDialog(item)"
    >
        {{ item.label }}
    </button>

    <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ selectedAction?.title }}</DialogTitle>
                <DialogDescription>{{ selectedAction?.description }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-3" @submit.prevent="submitTransition">
                <div
                    v-if="selectedAction?.action === 'correct'"
                    class="border-l-2 border-amber-400 bg-amber-50/70 px-3.5 py-3 text-xs leading-5 text-amber-950 dark:border-amber-600 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Dokumen akan kembali ke status Perlu Perbaikan. Turunan yang masih Draft tetap disimpan dan ikut ditandai perlu disesuaikan; turunan yang sudah diajukan atau disetujui akan memblokir koreksi.
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium" for="workflow-note">
                        {{ selectedAction?.noteLabel }}
                        <span v-if="selectedAction?.noteRequired" class="text-red-700">*</span>
                    </label>
                    <textarea
                        id="workflow-note"
                        v-model="form.note"
                        rows="4"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                        :placeholder="selectedAction?.notePlaceholder"
                    />
                    <p v-if="localNoteError || form.errors.note" class="text-sm text-red-700">{{ localNoteError || form.errors.note }}</p>
                </div>

                <div v-if="selectedAction?.referenceRequired" class="space-y-1.5">
                    <label class="text-sm font-medium" for="workflow-correction-reference">
                        Acuan dokumen resmi <span class="text-red-700">*</span>
                    </label>
                    <input
                        id="workflow-correction-reference"
                        v-model="form.correction_reference"
                        type="text"
                        class="h-10 w-full rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-amber-600"
                        placeholder="Contoh: RENSTRA 2025–2029 halaman 47"
                    />
                    <p v-if="form.errors.correction_reference" class="text-sm text-red-700">{{ form.errors.correction_reference }}</p>
                </div>

                <p v-if="form.errors.action" class="rounded-md bg-red-50 px-3 py-2 text-sm leading-5 text-red-700 dark:bg-red-950/35 dark:text-red-200">
                    {{ form.errors.action }}
                </p>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="isDialogOpen = false">Batal</Button>
                    <Button type="submit" :disabled="form.processing" :class="selectedAction?.confirmClassName">
                        {{ form.processing ? 'Memproses...' : selectedAction?.label }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
