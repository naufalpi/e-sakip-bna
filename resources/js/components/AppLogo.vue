<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="app-sidebar-logo-mark flex aspect-square size-10 shrink-0 items-center justify-center rounded-md bg-white p-1 shadow-sm">
        <AppLogoIcon class="size-full" />
    </div>
    <div class="app-sidebar-brand-text ml-1 grid min-w-0 flex-1 text-left">
        <span class="truncate text-sm font-semibold leading-none">E-SAKIP</span>
        <span class="mt-1 truncate text-[11px] font-medium leading-none">Kabupaten Banjarnegara</span>
    </div>
</template>
