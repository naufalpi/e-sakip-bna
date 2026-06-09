<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

type NotificationRow = {
    id: number;
    type: string;
    title: string;
    message?: string | null;
    data?: Record<string, unknown> | null;
    read_at?: string | null;
    created_at?: string | null;
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

defineProps<{
    notifications: Paginator<NotificationRow>;
}>();

const markRead = (id: number) => router.patch(route('notifications.read', id), {}, { preserveScroll: true });
const markAllRead = () => router.patch(route('notifications.read-all'), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Notifikasi" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">Notifikasi</h1>
                <p class="mt-1 text-sm text-muted-foreground">Pemberitahuan workflow dan tindak lanjut.</p>
            </div>
            <button type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="markAllRead">Tandai Semua Dibaca</button>
        </div>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="divide-y">
                <article
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="flex flex-col gap-2 p-4 md:flex-row md:items-start md:justify-between"
                    :class="notification.read_at ? '' : 'bg-emerald-50/50'"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-semibold">{{ notification.title }}</h2>
                            <span v-if="!notification.read_at" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                                >Baru</span
                            >
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ notification.message || '-' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ notification.created_at }}</p>
                    </div>
                    <button
                        v-if="!notification.read_at"
                        type="button"
                        class="w-fit rounded-md border px-3 py-1.5 text-xs hover:bg-muted"
                        @click="markRead(notification.id)"
                    >
                        Tandai Dibaca
                    </button>
                </article>
                <div v-if="notifications.data.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">Belum ada notifikasi.</div>
            </div>
            <div class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>Menampilkan {{ notifications.from ?? 0 }}-{{ notifications.to ?? 0 }} dari {{ notifications.total }} data</span>
                <div class="flex gap-2">
                    <Link v-if="notifications.prev_page_url" :href="notifications.prev_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Sebelumnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Sebelumnya</span>
                    <span class="px-2 py-1.5">Halaman {{ notifications.current_page }} / {{ notifications.last_page }}</span>
                    <Link v-if="notifications.next_page_url" :href="notifications.next_page_url" class="rounded-md border px-3 py-1.5 hover:bg-muted"
                        >Berikutnya</Link
                    >
                    <span v-else class="rounded-md border px-3 py-1.5 opacity-50">Berikutnya</span>
                </div>
            </div>
        </section>
    </div>
</template>
