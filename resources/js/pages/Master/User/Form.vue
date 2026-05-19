<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

type UserForm = {
    opd_id: number | string | null;
    name: string;
    email: string;
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
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Master User', href: '/master/users' },
    { title: props.mode === 'create' ? 'Tambah' : 'Edit', href: '#' },
];

const form = useForm<UserForm>({
    opd_id: props.user?.opd_id ?? '',
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    status: props.user?.status ?? 'active',
    role_ids: props.user?.role_ids ?? [],
});

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

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex max-w-5xl flex-col gap-4 p-4" @submit.prevent="submit">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ mode === 'create' ? 'Tambah User' : 'Edit User' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Atur akun, status, OPD, dan role pengguna.</p>
            </div>

            <section class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Identitas Akun</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
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
                        <label class="text-sm font-medium" for="password">Kata sandi</label>
                        <input id="password" v-model="form.password" type="password" class="h-9 rounded-md border bg-background px-3 text-sm" />
                        <p v-if="mode === 'edit'" class="text-xs text-muted-foreground">Kosongkan jika tidak ingin mengubah kata sandi.</p>
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="password_confirmation">Konfirmasi kata sandi</label>
                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="h-9 rounded-md border bg-background px-3 text-sm" />
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
                    <div class="grid gap-2">
                        <label class="text-sm font-medium" for="opd">OPD</label>
                        <select id="opd" v-model="form.opd_id" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="">Tanpa OPD</option>
                            <option v-for="opd in opdOptions" :key="opd.id" :value="opd.id">{{ opd.label }}</option>
                        </select>
                        <InputError :message="form.errors.opd_id" />
                    </div>
                </div>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Role</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <label v-for="role in roleOptions" :key="role.id" class="flex items-start gap-3 rounded-md border p-3 text-sm">
                        <input
                            type="checkbox"
                            class="mt-1"
                            :checked="form.role_ids.includes(role.id)"
                            @change="toggleRoleFromEvent(role.id, $event)"
                        />
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
                <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    Simpan
                </button>
            </div>
        </form>
    </AppLayout>
</template>
