<script setup lang="ts">
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import BookOpenCheck from 'lucide-vue-next/dist/esm/icons/book-open-check.js';
import BriefcaseBusiness from 'lucide-vue-next/dist/esm/icons/briefcase-business.js';
import Building2 from 'lucide-vue-next/dist/esm/icons/building-2.js';
import ClipboardList from 'lucide-vue-next/dist/esm/icons/clipboard-list.js';
import UsersRound from 'lucide-vue-next/dist/esm/icons/users-round.js';
import { computed } from 'vue';

type ActiveTab = 'units' | 'positions' | 'people' | 'proposals' | 'references';

const props = defineProps<{ active: ActiveTab }>();
const page = usePage<SharedData>();
const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const allowed = (permission: string) => permissions.value.includes(permission);

const tabs = computed(() => [
    allowed('opd.view') && { key: 'units', label: 'Unit Kerja', href: route('master.opd-units.index'), icon: Building2 },
    allowed('jabatan_organisasi.view') && {
        key: 'positions',
        label: 'Jabatan',
        href: route('master.jabatan-organisasi.index'),
        icon: BriefcaseBusiness,
    },
    allowed('pegawai.view') && { key: 'people', label: 'Penempatan Pegawai', href: route('master.pegawai.index'), icon: UsersRound },
    allowed('jabatan_organisasi.view') && {
        key: 'proposals',
        label: 'Usulan Perubahan',
        href: route('master.jabatan-organisasi.index', { verification_status: 'proposal' }),
        icon: ClipboardList,
    },
].filter(Boolean) as Array<{ key: ActiveTab; label: string; href: string; icon: typeof Building2 }>);
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border bg-card p-2 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <nav class="flex min-w-0 gap-1 overflow-x-auto" aria-label="Workspace Struktur Organisasi">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors"
                :class="props.active === tab.key ? 'bg-blue-800 text-white dark:bg-blue-600' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
            </Link>
        </nav>

        <Link
            v-if="allowed('referensi_jabatan.view')"
            :href="route('master.referensi-jabatan.index')"
            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors"
            :class="props.active === 'references' ? 'border-blue-800 bg-blue-50 text-blue-900 dark:border-blue-600 dark:bg-blue-950/40 dark:text-blue-100' : 'bg-background text-muted-foreground hover:bg-muted hover:text-foreground'"
        >
            <BookOpenCheck class="size-4" />
            Referensi Jabatan
        </Link>
    </div>
</template>
