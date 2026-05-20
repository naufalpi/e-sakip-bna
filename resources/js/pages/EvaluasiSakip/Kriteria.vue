<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

type Kriteria = { id: number; kode: string; nama: string; bobot: string | number; nilai_maksimal: string | number; status: string };
type SubKomponen = { id: number; kode: string; nama: string; bobot: string | number; status: string; kriteria: Kriteria[] };
type Komponen = { id: number; kode: string; nama: string; bobot: string | number; status: string; sub_komponen: SubKomponen[] };

defineProps<{
    komponen: Komponen[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Evaluasi SAKIP', href: '/evaluasi-sakip' },
    { title: 'Kriteria Evaluasi', href: '/evaluasi-sakip/kriteria' },
];
</script>

<template>
    <Head title="Kriteria Evaluasi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Kriteria Evaluasi SAKIP</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Master komponen, sub komponen, dan kriteria awal untuk penilaian SAKIP.</p>
                </div>
                <Link :href="route('evaluasi-sakip.index')" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Kembali</Link>
            </div>

            <div class="space-y-4">
                <section v-for="item in komponen" :key="item.id" class="rounded-lg border bg-card p-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold">{{ item.kode }}. {{ item.nama }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Bobot {{ item.bobot }} - Status {{ item.status }}</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="sub in item.sub_komponen" :key="sub.id" class="rounded-md border bg-background p-3">
                            <div class="font-medium">{{ sub.kode }}. {{ sub.nama }}</div>
                            <div class="text-xs text-muted-foreground">Bobot {{ sub.bobot }} - Status {{ sub.status }}</div>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b bg-muted/60 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="px-3 py-2">Kode</th>
                                            <th class="px-3 py-2">Kriteria</th>
                                            <th class="px-3 py-2">Bobot</th>
                                            <th class="px-3 py-2">Nilai Maks</th>
                                            <th class="px-3 py-2">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="kriteria in sub.kriteria" :key="kriteria.id" class="border-b last:border-0">
                                            <td class="px-3 py-2 font-medium">{{ kriteria.kode }}</td>
                                            <td class="px-3 py-2">{{ kriteria.nama }}</td>
                                            <td class="px-3 py-2">{{ kriteria.bobot }}</td>
                                            <td class="px-3 py-2">{{ kriteria.nilai_maksimal }}</td>
                                            <td class="px-3 py-2">{{ kriteria.status }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
