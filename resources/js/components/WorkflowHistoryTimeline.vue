<script setup lang="ts">
type WorkflowHistory = {
    id: number;
    action: string;
    from_status?: string | null;
    to_status: string;
    notes?: string | null;
    created_at: string;
    actor?: { name: string } | null;
    metadata?: {
        correction_reference?: string;
        source_correction?: { reference?: string };
    } | null;
};

type Workflow = {
    histories?: WorkflowHistory[];
} | null;

defineProps<{
    workflow: Workflow;
}>();

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Perlu Perbaikan',
        verified: 'Terverifikasi',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;

const actionLabel = (action: string) =>
    ({
        submit: 'Pengajuan',
        verify: 'Verifikasi',
        approve: 'Persetujuan',
        revision: 'Permintaan perbaikan',
        reject: 'Penolakan',
        lock: 'Penguncian',
        unlock: 'Pembukaan kunci',
        correct: 'Koreksi data',
        source_correction: 'Penyesuaian acuan',
    })[action] ?? action;

const wibDateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Asia/Jakarta',
});

const formatWibDateTime = (value?: string | null) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return `${wibDateTimeFormatter.format(date).replace(' pukul ', ', ')} WIB`;
};

const correctionReference = (history: WorkflowHistory) =>
    history.metadata?.correction_reference || history.metadata?.source_correction?.reference || null;
</script>

<template>
    <section class="rounded-lg border bg-card p-4">
        <h2 class="text-sm font-semibold">Riwayat Persetujuan</h2>
        <div v-if="workflow?.histories?.length" class="mt-3 space-y-3 text-sm">
            <div v-for="history in workflow.histories" :key="history.id" class="rounded-md border bg-background p-3">
                <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="font-medium">{{ actionLabel(history.action) }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">
                            {{ statusLabel(history.from_status || 'draft') }} ke {{ statusLabel(history.to_status) }}
                        </div>
                    </div>
                    <div class="text-xs text-muted-foreground md:text-right">
                        <div>{{ history.actor?.name || '-' }}</div>
                        <time :datetime="history.created_at">{{ formatWibDateTime(history.created_at) }}</time>
                    </div>
                </div>
                <div v-if="history.notes" class="mt-3 rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                    {{ history.notes }}
                </div>
                <div v-if="correctionReference(history)" class="mt-2 text-xs text-muted-foreground">
                    <span class="font-medium text-foreground">Acuan resmi:</span> {{ correctionReference(history) }}
                </div>
            </div>
        </div>
        <div v-else class="mt-3 text-sm text-muted-foreground">Belum ada riwayat persetujuan.</div>
    </section>
</template>
