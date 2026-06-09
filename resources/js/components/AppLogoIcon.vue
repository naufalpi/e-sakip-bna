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
    <img
        src="/images/logo-banjarnegara-96.webp"
        alt="Lambang Kabupaten Banjarnegara"
        :class="className"
        v-bind="$attrs"
        class="block h-full w-full object-contain"
        loading="eager"
        decoding="async"
    />
</template>
