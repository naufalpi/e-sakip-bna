<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

type PermissionGroup = {
    module: string;
    items: Array<{
        id: number;
        name: string;
        label: string;
    }>;
};

defineProps<{
    roles: Array<{
        id: number;
        name: string;
        label: string;
        description?: string | null;
        permissions: PermissionGroup[];
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Role Permission', href: '/master/role-permission' },
];
</script>

<template>
    <Head title="Role Permission" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Role Permission</h1>
                <p class="mt-1 text-sm text-muted-foreground">Matriks akses awal aplikasi. Pada tahap ini data dibuat read-only dari seeder.</p>
            </div>

            <div class="grid gap-4">
                <section v-for="role in roles" :key="role.id" class="rounded-lg border bg-card p-4">
                    <div class="flex flex-col gap-1 border-b pb-3">
                        <h2 class="text-base font-semibold">{{ role.label }}</h2>
                        <p class="text-xs text-muted-foreground">{{ role.name }}</p>
                        <p v-if="role.description" class="text-sm text-muted-foreground">{{ role.description }}</p>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="group in role.permissions" :key="group.module" class="rounded-md border p-3">
                            <h3 class="text-sm font-semibold capitalize">{{ group.module.replaceAll('_', ' ') }}</h3>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span v-for="permission in group.items" :key="permission.id" class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                    {{ permission.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
