<script setup lang="ts">
import type { PublicTableSection } from '../types';
import { cycleCardClass } from '../utils';

defineProps<{
    section: PublicTableSection;
    meta: {
        periode_label: string;
    };
    filteredRowsCount: number;
    currentRowsCount: number;
}>();
</script>

<template>
    <section class="module-header pt-28">
        <div class="mx-auto max-w-7xl px-4 pb-8 pt-8 sm:px-6 lg:px-8">
            <div class="module-header-panel rounded-xl border border-blue-100 bg-white p-5 shadow-sm shadow-blue-950/5 sm:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex gap-4">
                        <div class="cycle-icon shrink-0" :class="cycleCardClass(section.id)">
                            <component :is="section.icon" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold uppercase text-[#00336C]">{{ section.eyebrow }}</p>
                            <h1 class="mt-1 text-2xl font-bold text-slate-950 sm:text-3xl">{{ section.title }}</h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                {{ section.summary }} Gunakan pencarian dan filter tahun untuk melihat data publik yang relevan.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[22rem]">
                        <div class="rounded-md border border-blue-100 bg-blue-50/60 px-4 py-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Periode</p>
                            <p class="mt-1 text-sm font-semibold text-slate-950">{{ meta.periode_label }}</p>
                        </div>
                        <div class="rounded-md border border-blue-100 bg-blue-50/60 px-4 py-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Baris tampil</p>
                            <p class="mt-1 text-sm font-semibold text-slate-950">{{ filteredRowsCount }} dari {{ currentRowsCount }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
