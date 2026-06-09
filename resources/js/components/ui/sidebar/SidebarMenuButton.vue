<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue';
import SidebarMenuButtonChild, { type SidebarMenuButtonProps } from './SidebarMenuButtonChild.vue';
import { useSidebar } from './utils';

const Tooltip = defineAsyncComponent(() => import('@/components/ui/tooltip/Tooltip.vue'));
const TooltipContent = defineAsyncComponent(() => import('@/components/ui/tooltip/TooltipContent.vue'));
const TooltipTrigger = defineAsyncComponent(() => import('@/components/ui/tooltip/TooltipTrigger.vue'));

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<
        SidebarMenuButtonProps & {
            tooltip?: string | Component;
        }
    >(),
    {
        as: 'button',
        variant: 'default',
        size: 'default',
    },
);

const { isMobile, state } = useSidebar();

const delegatedProps = computed(() => {
    const { tooltip, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <SidebarMenuButtonChild v-if="!tooltip" v-bind="{ ...delegatedProps, ...$attrs }">
        <slot />
    </SidebarMenuButtonChild>

    <Tooltip v-else>
        <TooltipTrigger as-child>
            <SidebarMenuButtonChild v-bind="{ ...delegatedProps, ...$attrs }">
                <slot />
            </SidebarMenuButtonChild>
        </TooltipTrigger>
        <TooltipContent side="right" align="center" :hidden="state !== 'collapsed' || isMobile">
            <template v-if="typeof tooltip === 'string'">
                {{ tooltip }}
            </template>
            <component :is="tooltip" v-else />
        </TooltipContent>
    </Tooltip>
</template>
