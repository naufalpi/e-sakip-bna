<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type WorkflowAction = 'submit' | 'verify' | 'approve' | 'revision' | 'reject' | 'lock';
type WorkflowActionItem = {
    action: WorkflowAction;
    label: string;
    className: string;
    title: string;
    description: string;
    noteLabel: string;
    notePlaceholder: string;
    noteRequired: boolean;
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
        showVerify?: boolean;
    }>(),
    {
        canLock: false,
        showVerify: true,
    },
);

const isDialogOpen = ref(false);
const selectedAction = ref<WorkflowActionItem | null>(null);
const localNoteError = ref<string | null>(null);

const form = useForm<{
    action: WorkflowAction;
    note: string;
    current_reviewer_id: number | null;
}>({
    action: 'submit',
    note: '',
    current_reviewer_id: null,
});

const openTransitionDialog = (item: WorkflowActionItem) => {
    selectedAction.value = item;
    localNoteError.value = null;
    form.clearErrors();
    form.action = item.action;
    form.note = '';
    form.current_reviewer_id = null;
    isDialogOpen.value = true;
};

const submitTransition = () => {
    if (!selectedAction.value) {
        return;
    }

    localNoteError.value = null;

    if (selectedAction.value.noteRequired && !form.note.trim()) {
        localNoteError.value = 'Catatan wajib diisi untuk aksi ini.';
        return;
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

    if (props.canManage && ['draft', 'revision', 'rejected'].includes(props.status)) {
        items.push({
            action: 'submit',
            label: 'Ajukan',
            className: 'bg-blue-700 text-white hover:bg-blue-800',
            title: 'Ajukan Data',
            description: 'Data akan masuk ke alur review kabupaten sesuai modul terkait.',
            noteLabel: 'Catatan pengajuan',
            notePlaceholder: 'Contoh: Data sudah dilengkapi dan siap direview.',
            noteRequired: false,
            confirmClassName: 'bg-blue-700 text-white hover:bg-blue-800',
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
            notePlaceholder: 'Contoh: Data disetujui sesuai hasil review.',
            noteRequired: false,
            confirmClassName: 'bg-emerald-700 text-white hover:bg-emerald-800',
        });
    }

    if (props.canReview && ['submitted', 'verified'].includes(props.status)) {
        items.push(
            {
                action: 'revision',
                label: 'Revisi',
                className: 'border text-amber-700 hover:bg-amber-50',
                title: 'Minta Revisi',
                description: 'Status data akan kembali ke OPD/penginput untuk diperbaiki.',
                noteLabel: 'Catatan revisi',
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

    return items;
});
</script>

<template>
    <button v-for="item in actions" :key="item.action" type="button" class="rounded-md px-3 py-2 text-sm font-medium" :class="item.className" @click="openTransitionDialog(item)">
        {{ item.label }}
    </button>

    <Dialog v-model:open="isDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ selectedAction?.title }}</DialogTitle>
                <DialogDescription>{{ selectedAction?.description }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-3" @submit.prevent="submitTransition">
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
