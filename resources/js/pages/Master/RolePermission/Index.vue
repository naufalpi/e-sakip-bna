<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { reactive } from 'vue';

type PermissionGroup = {
    module: string;
    items: Array<{
        id: number;
        name: string;
        label: string;
        description?: string | null;
    }>;
};

const props = defineProps<{
    roles: Array<{
        id: number;
        name: string;
        label: string;
        description?: string | null;
        permission_ids: number[];
        can_edit: boolean;
        permissions: PermissionGroup[];
    }>;
    permissionGroups: PermissionGroup[];
    can: {
        manage: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Role Permission', href: '/master/role-permission' },
];

const selectedPermissions = reactive<Record<number, number[]>>({});

props.roles.forEach((role) => {
    selectedPermissions[role.id] = [...role.permission_ids];
});

const isChecked = (roleId: number, permissionId: number) => selectedPermissions[roleId]?.includes(permissionId) ?? false;

const togglePermission = (roleId: number, permissionId: number, checked: boolean) => {
    const current = selectedPermissions[roleId] ?? [];

    selectedPermissions[roleId] = checked ? Array.from(new Set([...current, permissionId])) : current.filter((id) => id !== permissionId);
};

const onTogglePermission = (roleId: number, permissionId: number, event: Event) => {
    togglePermission(roleId, permissionId, (event.target as HTMLInputElement).checked);
};

const saveRole = (roleId: number) => {
    router.patch(
        route('master.role-permission.update', roleId),
        {
            permission_ids: selectedPermissions[roleId] ?? [],
        },
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Role Permission" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Role Permission</h1>
                <p class="mt-1 text-sm text-muted-foreground">Matriks hak akses aplikasi berdasarkan modul. Perubahan role tercatat di audit log.</p>
            </div>

            <div class="grid gap-4">
                <section v-for="role in roles" :key="role.id" class="rounded-lg border bg-card p-4">
                    <div class="flex flex-col gap-3 border-b pb-3 md:flex-row md:items-start md:justify-between">
                        <div class="flex flex-col gap-1">
                            <h2 class="text-base font-semibold">{{ role.label }}</h2>
                            <p class="text-xs text-muted-foreground">{{ role.name }}</p>
                            <p v-if="role.description" class="text-sm text-muted-foreground">{{ role.description }}</p>
                        </div>
                        <button
                            v-if="can.manage"
                            type="button"
                            :disabled="!role.can_edit"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="saveRole(role.id)"
                        >
                            <Save class="size-4" />
                            Simpan
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="group in permissionGroups" :key="`${role.id}-${group.module}`" class="rounded-md border p-3">
                            <h3 class="text-sm font-semibold capitalize">{{ group.module.replaceAll('_', ' ') }}</h3>
                            <div class="mt-3 grid gap-2">
                                <label v-for="permission in group.items" :key="permission.id" class="flex items-start gap-2 rounded-md border border-transparent p-2 text-sm hover:bg-muted/60">
                                    <input
                                        type="checkbox"
                                        class="mt-1 size-4 rounded border-slate-300 text-emerald-700"
                                        :checked="isChecked(role.id, permission.id)"
                                        :disabled="!can.manage || !role.can_edit"
                                        @change="onTogglePermission(role.id, permission.id, $event)"
                                    />
                                    <span>
                                        <span class="block font-medium">{{ permission.label }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ permission.name }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
