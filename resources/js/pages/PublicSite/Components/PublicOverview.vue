<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';

import type { PublicHomeModule } from '../types';
import { cycleCardClass } from '../utils';

defineProps<{
    modules: PublicHomeModule[];
}>();
</script>

<template>
    <section class="overview-section bg-white py-14 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase text-[#00336C]">Data Publik</p>
                <h2 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">Pilih siklus SAKIP yang ingin dilihat</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Setiap siklus memiliki halaman sendiri supaya tabel perangkat daerah tetap nyaman dibuka saat data dan dokumen semakin banyak.
                </p>
            </div>

            <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="module in modules"
                    :key="`overview-${module.id}`"
                    :href="module.href"
                    class="module-card group flex h-full flex-col rounded-xl border border-blue-100 bg-white p-5 shadow-sm shadow-blue-950/5 transition focus:outline-none focus:ring-2 focus:ring-[#00336C] focus:ring-offset-2"
                    :class="cycleCardClass(module.id)"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="cycle-icon shadow-sm shadow-blue-950/5">
                            <component :is="module.icon" class="h-5 w-5" />
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-[#00336C]">
                            {{ module.eyebrow }}
                        </span>
                    </div>
                    <h3 class="mt-6 text-xl font-bold leading-tight text-slate-950">{{ module.title }}</h3>
                    <p class="mt-3 min-h-[5rem] text-sm leading-7 text-slate-600">{{ module.summary }}</p>
                    <div class="mt-auto rounded-lg bg-blue-50/70 p-3">
                        <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
                            <span>Kelengkapan</span>
                            <span class="text-[#00336C]">{{ module.completeness }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                            <span
                                class="block h-full rounded-full bg-[linear-gradient(90deg,var(--cycle-color),#38bdf8)]"
                                :style="{ width: module.completeness }"
                            ></span>
                        </div>
                    </div>
                    <div class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-[#00336C]">
                        Buka data
                        <ChevronRight class="h-4 w-4 transition group-hover:translate-x-1" />
                    </div>
                </Link>
            </div>
        </div>
    </section>
</template>
