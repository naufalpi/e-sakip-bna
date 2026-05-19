<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

defineProps<{
    name?: string;
}>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal text-foreground">Dashboard E-SAKIP</h1>
                <p class="mt-1 text-sm text-muted-foreground">Ringkasan monitoring akan tersedia setelah master data dan modul kinerja dikonfigurasi.</p>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">OPD</p>
                    <p class="mt-3 text-2xl font-semibold">0</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Dokumen Kinerja</p>
                    <p class="mt-3 text-2xl font-semibold">0</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">Pengajuan Workflow</p>
                    <p class="mt-3 text-2xl font-semibold">0</p>
                </div>
            </div>
            <div class="min-h-96 flex-1 rounded-lg border border-dashed border-sidebar-border/70 bg-muted/20 p-6 dark:border-sidebar-border">
                <div class="flex h-full min-h-72 items-center justify-center text-center text-sm text-muted-foreground">
                    Belum ada data monitoring.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
