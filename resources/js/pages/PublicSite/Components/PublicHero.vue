<script setup lang="ts">
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import ShieldCheck from 'lucide-vue-next/dist/esm/icons/shield-check.js';

import { useHeroMeshCanvas } from '../composables/useHeroMeshCanvas';

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
                        Sistem Akuntabilitas Kinerja Instansi Pemerintah Kabupaten Banjarnegara
                    </h1>
                    <p class="mt-5 max-w-2xl text-base font-medium leading-8 text-blue-50/90 sm:text-lg">
                        Selamat datang di E-SAKIP Kabupaten Banjarnegara. Masyarakat dapat menelusuri dokumen perencanaan, pengukuran, pelaporan, dan
                        evaluasi kinerja perangkat daerah berdasarkan tahun berjalan.
                    </p>

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
