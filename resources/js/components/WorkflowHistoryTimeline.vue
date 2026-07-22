<script setup lang="ts">
type WorkflowHistory = {
    id: number;
    action: string;
    from_status?: string | null;
    to_status: string;
    notes?: string | null;
    created_at: string;
    actor?: { name: string } | null;
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
        revision: 'Revisi',
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
        revision: 'Permintaan revisi',
        reject: 'Penolakan',
        lock: 'Penguncian',
        unlock: 'Pembukaan kunci',
    })[action] ?? action;
</script>

<template>
    <section class="rounded-lg border bg-card p-4">
        <h2 class="text-sm font-semibold">Riwayat Workflow</h2>
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
                        <div>{{ history.created_at }}</div>
                    </div>
                </div>
                <div v-if="history.notes" class="mt-3 rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                    {{ history.notes }}
                </div>
            </div>
        </div>
        <div v-else class="mt-3 text-sm text-muted-foreground">Belum ada riwayat workflow.</div>
    </section>
</template>
