<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type UserForm = {
    opd_id: number | string | null;
    opd_unit_id: number | string | null;
    username: string;
    name: string;
    email: string;
    phone: string;
    jabatan: string;
    password: string;
    password_confirmation: string;
    status: string;
    role_ids: number[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    user: (Omit<UserForm, 'password' | 'password_confirmation'> & { id: number }) | null;
    roleOptions: Array<{ id: number; name: string; label: string }>;
    opdOptions: Array<{ id: number; label: string }>;
    opdUnitOptions: Array<{ id: number; opd_id: number; kode: string; nama: string; jenis_unit?: string | null; label: string; opd_label?: string | null }>;
}>();

const form = useForm<UserForm>({
    opd_id: props.user?.opd_id ?? '',
    opd_unit_id: props.user?.opd_unit_id ?? '',
    username: props.user?.username ?? '',
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    jabatan: props.user?.jabatan ?? '',
    password: '',
    password_confirmation: '',
    status: props.user?.status ?? 'active',
    role_ids: props.user?.role_ids ?? [],
});

const filteredOpdUnitOptions = computed(() => {
    if (!form.opd_id) {
        return [];
    }

    return props.opdUnitOptions.filter((unit) => String(unit.opd_id) === String(form.opd_id));
});

const selectedOpdLabel = computed(() => props.opdOptions.find((opd) => String(opd.id) === String(form.opd_id))?.label ?? null);

watch(
    () => form.opd_id,
    () => {
        if (!form.opd_unit_id) {
            return;
        }

        const unitStillAvailable = filteredOpdUnitOptions.value.some((unit) => String(unit.id) === String(form.opd_unit_id));

        if (!unitStillAvailable) {
            form.opd_unit_id = '';
        }
    },
);

const toggleRole = (roleId: number, checked: boolean) => {
    if (checked && !form.role_ids.includes(roleId)) {
        form.role_ids = [...form.role_ids, roleId];
    }

    if (!checked) {
        form.role_ids = form.role_ids.filter((id) => id !== roleId);
    }
};

const toggleRoleFromEvent = (roleId: number, event: Event) => {
    toggleRole(roleId, (event.target as HTMLInputElement).checked);
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('master.users.store'));
        return;
    }

    form.put(route('master.users.update', props.user?.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Tambah User' : 'Edit User'" />
    <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah User' : 'Edit User' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Atur akun, lingkup akses, dan role pengguna.</p>
        </div>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Identitas Akun</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="username">Username</label>
                    <input id="username" v-model="form.username" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.username" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="name">Nama</label>
                    <input id="name" v-model="form.name" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="email">Email</label>
                    <input id="email" v-model="form.email" type="email" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="phone">Telepon</label>
                    <input id="phone" v-model="form.phone" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <label class="text-sm font-medium" for="jabatan">Jabatan</label>
                    <input id="jabatan" v-model="form.jabatan" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <InputError :message="form.errors.jabatan" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="password">Kata sandi</label>
                    <input id="password" v-model="form.password" type="password" class="h-9 rounded-md border bg-background px-3 text-sm" />
                    <p v-if="mode === 'edit'" class="text-xs text-muted-foreground">Kosongkan jika tidak ingin mengubah kata sandi.</p>
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="password_confirmation">Konfirmasi kata sandi</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="status">Status</label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak aktif</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="border-b bg-muted/30 px-4 py-3">
                <h2 class="text-sm font-semibold">Lingkup Akses</h2>
            </div>
            <div class="grid gap-4 p-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="opd">OPD</label>
                    <select id="opd" v-model="form.opd_id" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">Tanpa OPD</option>
                        <option v-for="opd in opdOptions" :key="opd.id" :value="opd.id">{{ opd.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_id" />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="opd_unit">Unit / Sub-unit</label>
                    <select
                        id="opd_unit"
                        v-model="form.opd_unit_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!form.opd_id || filteredOpdUnitOptions.length === 0"
                    >
                        <option value="">{{ form.opd_id ? 'Seluruh OPD' : 'Pilih OPD terlebih dahulu' }}</option>
                        <option v-for="unit in filteredOpdUnitOptions" :key="unit.id" :value="unit.id">{{ unit.label }}</option>
                    </select>
                    <InputError :message="form.errors.opd_unit_id" />
                </div>
            </div>
            <div v-if="form.opd_id" class="border-t bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                <span class="font-medium text-foreground">{{ selectedOpdLabel }}</span>
                <span v-if="form.opd_unit_id"> dibatasi pada unit yang dipilih.</span>
                <span v-else> berlaku untuk seluruh unit dalam OPD.</span>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Role</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label v-for="role in roleOptions" :key="role.id" class="flex items-start gap-3 rounded-md border p-3 text-sm">
                    <input type="checkbox" class="mt-1" :checked="form.role_ids.includes(role.id)" @change="toggleRoleFromEvent(role.id, $event)" />
                    <span>
                        <span class="block font-medium">{{ role.label }}</span>
                        <span class="text-xs text-muted-foreground">{{ role.name }}</span>
                    </span>
                </label>
            </div>
            <InputError :message="form.errors.role_ids" />
        </section>

        <div class="flex justify-end gap-2">
            <Link :href="route('master.users.index')" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Batal</Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
            >
                Simpan
            </button>
        </div>
    </form>
</template>
