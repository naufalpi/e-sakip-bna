<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, LockKeyhole, Save, Search, ShieldCheck } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

type PermissionGroup = {
    module: string;
    items: Array<{
        id: number;
        name: string;
        label: string;
        description?: string | null;
    }>;
};

type RoleItem = {
    id: number;
    name: string;
    label: string;
    description?: string | null;
    permission_ids: number[];
    can_edit: boolean;
    permissions: PermissionGroup[];
};

const props = defineProps<{
    roles: RoleItem[];
    permissionGroups: PermissionGroup[];
    can: {
        manage: boolean;
    };
}>();

const selectedRoleId = ref<number | null>(props.roles[0]?.id ?? null);
const roleSearch = ref('');
const selectedPermissions = reactive<Record<number, number[]>>({});

props.roles.forEach((role) => {
    selectedPermissions[role.id] = [...role.permission_ids];
});

const moduleLabel = (module: string) => module.replaceAll('_', ' ');

const selectedRole = computed(() => props.roles.find((role) => role.id === selectedRoleId.value) ?? null);
const totalPermissions = computed(() => props.permissionGroups.reduce((total, group) => total + group.items.length, 0));
const selectedPermissionCount = computed(() => (selectedRole.value ? (selectedPermissions[selectedRole.value.id]?.length ?? 0) : 0));

const filteredRoles = computed(() => {
    const search = roleSearch.value.trim().toLowerCase();

    if (!search) {
        return props.roles;
    }

    return props.roles.filter((role) => `${role.label} ${role.name} ${role.description ?? ''}`.toLowerCase().includes(search));
});

const sameIds = (left: number[], right: number[]) => {
    if (left.length !== right.length) {
        return false;
    }

    const rightSet = new Set(right);

    return left.every((id) => rightSet.has(id));
};

const isDirty = computed(() => {
    if (!selectedRole.value) {
        return false;
    }

    return !sameIds(selectedPermissions[selectedRole.value.id] ?? [], selectedRole.value.permission_ids);
});

const isChecked = (roleId: number, permissionId: number) => selectedPermissions[roleId]?.includes(permissionId) ?? false;

const canEditSelectedRole = computed(() => Boolean(props.can.manage && selectedRole.value?.can_edit));

const selectRole = (roleId: number) => {
    selectedRoleId.value = roleId;
};

const togglePermission = (roleId: number, permissionId: number, checked: boolean) => {
    const current = selectedPermissions[roleId] ?? [];

    selectedPermissions[roleId] = checked ? Array.from(new Set([...current, permissionId])) : current.filter((id) => id !== permissionId);
};

const onTogglePermission = (roleId: number, permissionId: number, event: Event) => {
    togglePermission(roleId, permissionId, (event.target as HTMLInputElement).checked);
};

const groupPermissionIds = (group: PermissionGroup) => group.items.map((permission) => permission.id);

const isGroupFullyChecked = (roleId: number, group: PermissionGroup) => {
    const current = new Set(selectedPermissions[roleId] ?? []);

    return groupPermissionIds(group).every((id) => current.has(id));
};

const toggleGroup = (roleId: number, group: PermissionGroup) => {
    if (!canEditSelectedRole.value) {
        return;
    }

    const current = selectedPermissions[roleId] ?? [];
    const groupIds = groupPermissionIds(group);

    selectedPermissions[roleId] = isGroupFullyChecked(roleId, group)
        ? current.filter((id) => !groupIds.includes(id))
        : Array.from(new Set([...current, ...groupIds]));
};

const saveSelectedRole = () => {
    if (!selectedRole.value || !canEditSelectedRole.value) {
        return;
    }

    router.patch(
        route('master.role-permission.update', selectedRole.value.id),
        {
            permission_ids: selectedPermissions[selectedRole.value.id] ?? [],
        },
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Role Permission" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Role Permission</h1>
                <p class="mt-1 text-sm text-muted-foreground">Pilih satu role, lalu atur hak akses modul yang diperlukan.</p>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm sm:flex">
                <div class="rounded-md border bg-card px-3 py-2">
                    <span class="block text-xs text-muted-foreground">Role</span>
                    <span class="font-semibold">{{ roles.length }}</span>
                </div>
                <div class="rounded-md border bg-card px-3 py-2">
                    <span class="block text-xs text-muted-foreground">Permission</span>
                    <span class="font-semibold">{{ totalPermissions }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="rounded-lg border bg-card p-4">
                <div class="flex items-start gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-700 text-white">
                        <ShieldCheck class="size-4" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold">Pilih Role</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Cari role yang akan diatur tanpa harus membuka semua daftar.</p>
                    </div>
                </div>

                <div class="relative mt-4">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="roleSearch"
                        type="search"
                        class="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15"
                        placeholder="Cari role..."
                    />
                </div>

                <div class="mt-3 max-h-[520px] space-y-2 overflow-y-auto pr-1">
                    <button
                        v-for="role in filteredRoles"
                        :key="role.id"
                        type="button"
                        class="flex w-full items-start gap-3 rounded-md border p-3 text-left transition hover:border-emerald-700/40 hover:bg-emerald-50/50"
                        :class="role.id === selectedRoleId ? 'border-emerald-700 bg-emerald-50 shadow-sm' : 'border-border bg-background'"
                        @click="selectRole(role.id)"
                    >
                        <span
                            class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border"
                            :class="
                                role.id === selectedRoleId
                                    ? 'border-emerald-700 bg-emerald-700 text-white'
                                    : 'border-muted-foreground/30 text-transparent'
                            "
                        >
                            <Check class="size-3" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold">{{ role.label }}</span>
                            <span class="block truncate text-xs text-muted-foreground">{{ role.name }}</span>
                            <span class="mt-2 inline-flex rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
                                {{ selectedPermissions[role.id]?.length ?? 0 }} permission
                            </span>
                        </span>
                    </button>

                    <div v-if="filteredRoles.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                        Role tidak ditemukan.
                    </div>
                </div>
            </aside>

            <section v-if="selectedRole" class="min-w-0 overflow-hidden rounded-lg border bg-card">
                <div class="border-b p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold">{{ selectedRole.label }}</h2>
                                <span
                                    v-if="!selectedRole.can_edit"
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                >
                                    <LockKeyhole class="size-3" />
                                    Sistem
                                </span>
                                <span v-if="isDirty" class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800"
                                    >Belum disimpan</span
                                >
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ selectedRole.name }}</p>
                            <p v-if="selectedRole.description" class="mt-2 max-w-3xl text-sm text-muted-foreground">{{ selectedRole.description }}</p>
                        </div>
                        <div class="rounded-md border bg-background px-3 py-2 text-sm">
                            <span class="block text-xs text-muted-foreground">Aktif</span>
                            <span class="font-semibold">{{ selectedPermissionCount }} / {{ totalPermissions }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="!selectedRole.can_edit" class="border-b bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    Permission role sistem tidak dapat dikurangi dari halaman ini.
                </div>

                <div class="grid gap-3 p-4 md:grid-cols-2 2xl:grid-cols-3">
                    <div v-for="group in permissionGroups" :key="`${selectedRole.id}-${group.module}`" class="rounded-md border bg-background p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold capitalize">{{ moduleLabel(group.module) }}</h3>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ group.items.filter((permission) => isChecked(selectedRole.id, permission.id)).length }} dari
                                    {{ group.items.length }} aktif
                                </p>
                            </div>
                            <button
                                v-if="can.manage"
                                type="button"
                                :disabled="!selectedRole.can_edit"
                                class="rounded-md border px-2 py-1 text-xs hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                @click="toggleGroup(selectedRole.id, group)"
                            >
                                {{ isGroupFullyChecked(selectedRole.id, group) ? 'Kosongkan' : 'Pilih semua' }}
                            </button>
                        </div>

                        <div class="mt-3 grid gap-2">
                            <label
                                v-for="permission in group.items"
                                :key="permission.id"
                                class="flex items-start gap-2 rounded-md border border-transparent p-2 text-sm hover:bg-muted/60"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 size-4 rounded border-slate-300 text-emerald-700"
                                    :checked="isChecked(selectedRole.id, permission.id)"
                                    :disabled="!can.manage || !selectedRole.can_edit"
                                    @change="onTogglePermission(selectedRole.id, permission.id, $event)"
                                />
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ permission.label }}</span>
                                    <span class="block break-all text-xs text-muted-foreground">{{ permission.name }}</span>
                                    <span v-if="permission.description" class="mt-1 block text-xs text-muted-foreground">{{
                                        permission.description
                                    }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 flex flex-col gap-2 border-t bg-card/95 p-4 backdrop-blur md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-muted-foreground">
                        {{ isDirty ? 'Ada perubahan pada role terpilih.' : 'Permission role terpilih sudah tersimpan.' }}
                    </p>
                    <button
                        v-if="can.manage"
                        type="button"
                        :disabled="!canEditSelectedRole || !isDirty"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="saveSelectedRole"
                    >
                        <Save class="size-4" />
                        Simpan Role Terpilih
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>
