<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuContent from '@/components/ui/dropdown-menu/DropdownMenuContent.vue';
import DropdownMenuTrigger from '@/components/ui/dropdown-menu/DropdownMenuTrigger.vue';
import SidebarMenu from '@/components/ui/sidebar/SidebarMenu.vue';
import SidebarMenuButton from '@/components/ui/sidebar/SidebarMenuButton.vue';
import SidebarMenuItem from '@/components/ui/sidebar/SidebarMenuItem.vue';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import ChevronsUpDown from 'lucide-vue-next/dist/esm/icons/chevrons-up-down.js';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton size="lg" class="app-sidebar-user-button data-[state=open]:bg-sidebar-accent">
                        <UserInfo :user="user" show-email />
                        <ChevronsUpDown class="app-sidebar-user-chevron ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="app-sidebar-user-dropdown w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-xl"
                    side="top"
                    align="start"
                    :side-offset="8"
                    :collision-padding="12"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
