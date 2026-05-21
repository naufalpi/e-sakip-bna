<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

type WorkflowAction = 'submit' | 'verify' | 'approve' | 'revision' | 'reject' | 'lock';

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

const transition = (action: WorkflowAction) => {
    router.post(route('workflow.transition', { module: props.module, id: props.modelId }), { action }, { preserveScroll: true });
};

const actions = computed(() => {
    const items: Array<{ action: WorkflowAction; label: string; className: string }> = [];

    if (props.canManage && ['draft', 'revision', 'rejected'].includes(props.status)) {
        items.push({
            action: 'submit',
            label: 'Ajukan',
            className: 'bg-blue-700 text-white hover:bg-blue-800',
        });
    }

    if (props.canReview && props.status === 'submitted' && props.showVerify) {
        items.push({
            action: 'verify',
            label: 'Verifikasi',
            className: 'border hover:bg-muted',
        });
    }

    if (props.canReview && (props.status === 'verified' || (props.status === 'submitted' && !props.showVerify))) {
        items.push({
            action: 'approve',
            label: 'Setujui',
            className: 'border hover:bg-muted',
        });
    }

    if (props.canReview && ['submitted', 'verified'].includes(props.status)) {
        items.push(
            {
                action: 'revision',
                label: 'Revisi',
                className: 'border text-amber-700 hover:bg-amber-50',
            },
            {
                action: 'reject',
                label: 'Tolak',
                className: 'border text-red-700 hover:bg-red-50',
            },
        );
    }

    if (props.canLock && props.status === 'approved') {
        items.push({
            action: 'lock',
            label: 'Kunci',
            className: 'border hover:bg-muted',
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
        :class="item.className"
        @click="transition(item.action)"
    >
        {{ item.label }}
    </button>
</template>
