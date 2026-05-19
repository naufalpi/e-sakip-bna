<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <span :class="className" v-bind="$attrs" class="inline-flex items-center justify-center text-xs font-semibold leading-none">ES</span>
</template>
