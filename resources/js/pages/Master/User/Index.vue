<script setup lang="ts">
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive } from 'vue';

type UserRow = {
    id: number;
    username?: string | null;
    name: string;
    email: string;
    phone?: string | null;
    jabatan?: string | null;
    status: string;
    last_login_at?: string | null;
    opd?: { nama: string; singkatan?: string | null } | null;
    roles: Array<{ id: number; name: string; label: string }>;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    users: Paginator<UserRow>;
    filters: {
        search?: string;
        status?: string;
        role?: string;
    };
    roleOptions: Array<{ id: number; name: string; label: string }>;
    can: {
        create: boolean;
    };
}>();

const page = usePage<SharedData>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    role: props.filters.role ?? '',
});

const applyFilters = () => {
    router.get(route('master.users.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.role = '';
    applyFiltersNow();
};

const destroy = async (user: UserRow) => {
    if (await confirmDelete(`Hapus user ${user.name}?`)) {
        router.delete(route('master.users.destroy', user.id));
    }
};
</script>

<template>
    <Head title="Master User" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Master User</h1>
                <p class="mt-1 text-sm text-muted-foreground">Kelola akun, status, OPD, dan role pengguna aplikasi.</p>
            </div>
            <Link
                v-if="can.create"
                :href="route('master.users.create')"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
            >
                <Plus class="size-4" />
                Tambah User
            </Link>
        </div>

        <form class="grid gap-3 rounded-lg border bg-card p-3 md:grid-cols-[1fr_180px_240px_auto]" @submit.prevent="applyFiltersNow">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="h-9 w-full rounded-md border bg-background pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
                    placeholder="Cari nama, username, email, telepon, atau jabatan"
                />
            </div>
            <select
                v-model="filterForm.status"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak aktif</option>
                <option value="suspended">Suspended</option>
            </select>
            <select
                v-model="filterForm.role"
                class="h-9 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-emerald-700"
            >
                <option value="">Semua role</option>
                <option v-for="role in roleOptions" :key="role.id" :value="role.name">{{ role.label }}</option>
            </select>
            <button type="button" class="h-9 rounded-md px-3 text-sm text-muted-foreground hover:bg-muted" @click="resetFilters">Reset</button>
        </form>

        <div class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">OPD</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ user.name }}</div>
                                <div class="text-xs text-muted-foreground">@{{ user.username || '-' }} · {{ user.email }}</div>
                                <div class="text-xs text-muted-foreground">{{ user.jabatan || '-' }} · {{ user.phone || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="user.opd">{{ user.opd.singkatan || user.opd.nama }}</span>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex max-w-md flex-wrap gap-1">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"
                                    >
                                        {{ role.label }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800': user.status === 'active',
                                        'bg-slate-100 text-slate-700': user.status === 'inactive',
                                        'bg-red-100 text-red-800': user.status === 'suspended',
                                    }"
                                >
                                    {{ user.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="can.create" class="inline-flex gap-2">
                                    <Link :href="route('master.users.edit', user.id)" class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                        >Edit</Link
                                    >
                                    <button
                                        v-if="user.id !== page.props.auth.user?.id"
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                        @click="destroy(user)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Read-only</span>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada data user.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ users.from ?? 0 }}-{{ users.to ?? 0 }} dari {{ users.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ users.current_page }} / {{ users.last_page }}</span>
                    <Link v-if="users.next_page_url" :href="users.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </div>
    </div>
</template>
