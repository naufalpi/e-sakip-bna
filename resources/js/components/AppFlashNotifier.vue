<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';

const page = usePage<SharedData>();

async function showFlashNotification(flash: SharedData['flash'] | undefined): Promise<void> {
    if (!flash || !Object.values(flash).some((message) => typeof message === 'string' && message.trim().length > 0)) {
        return;
    }

    const { notifyFlash } = await import('@/lib/sweetAlert');

    notifyFlash(flash);
}

watch(
    () => page.props.flash,
    (flash) => {
        void showFlashNotification(flash);
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <span class="hidden" aria-hidden="true"></span>
</template>
