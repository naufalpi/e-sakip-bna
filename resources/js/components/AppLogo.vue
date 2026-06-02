<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-10 shrink-0 items-center justify-center rounded-md bg-white p-1 shadow-sm ring-1 ring-emerald-900/10">
        <AppLogoIcon class="size-full" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">E-SAKIP Banjarnegara</span>
    </div>
</template>
