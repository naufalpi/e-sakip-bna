<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import LogIn from 'lucide-vue-next/dist/esm/icons/log-in.js';
import ShieldCheck from 'lucide-vue-next/dist/esm/icons/shield-check.js';

import { useHeroMeshCanvas } from '../composables/useHeroMeshCanvas';

defineProps<{
    planningUrl: string;
    entryUrl: string;
    entryLabel: string;
}>();

const { heroMeshCanvas } = useHeroMeshCanvas();
</script>

<template>
    <section id="beranda" class="hero-section relative isolate overflow-hidden pt-28">
        <div class="hero-motion" aria-hidden="true">
            <div class="hero-aurora hero-aurora-a"></div>
            <div class="hero-aurora hero-aurora-b"></div>
            <div class="hero-aurora hero-aurora-c"></div>
            <div class="hero-scanline"></div>
            <div class="hero-orbit hero-orbit-a"></div>
            <div class="hero-orbit hero-orbit-b"></div>
            <div class="hero-network-texture"></div>
            <canvas ref="heroMeshCanvas" class="hero-mesh-canvas"></canvas>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="hero-shell grid min-h-[clamp(29rem,calc(100dvh-7rem),38rem)] items-center py-8 sm:py-10 lg:py-12">
                <div class="max-w-3xl">
                    <p class="hero-kicker inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-normal">
                        <ShieldCheck class="h-3.5 w-3.5" />
                        Portal Publik Akuntabilitas Kinerja
                    </p>
                    <h1 class="hero-title mt-5 max-w-4xl text-3xl font-bold leading-tight text-white sm:text-5xl xl:text-6xl">
                        Transparansi Kinerja Pemerintah Kabupaten Banjarnegara
                    </h1>
                    <p class="mt-5 max-w-2xl text-base font-medium leading-8 text-blue-50/90 sm:text-lg">
                        Selamat datang di E-SAKIP Kabupaten Banjarnegara. Masyarakat dapat menelusuri dokumen perencanaan, pengukuran, pelaporan, dan
                        evaluasi kinerja perangkat daerah berdasarkan tahun berjalan.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="planningUrl"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-bold text-[#00336C] shadow-lg shadow-blue-950/25 transition hover:-translate-y-0.5 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#00336C]"
                        >
                            Lihat Data Publik
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                        <Link
                            :href="entryUrl"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-white/25 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#00336C]"
                        >
                            <LogIn class="h-4 w-4" />
                            {{ entryLabel }}
                        </Link>
                    </div>

                    <div class="hero-proofline mt-8 grid gap-3 text-sm font-semibold text-blue-50 sm:grid-cols-2 xl:grid-cols-4">
                        <span><CheckCircle2 class="h-4 w-4" /> Perencanaan</span>
                        <span><CheckCircle2 class="h-4 w-4" /> Pengukuran</span>
                        <span><CheckCircle2 class="h-4 w-4" /> Pelaporan</span>
                        <span><CheckCircle2 class="h-4 w-4" /> Evaluasi</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
