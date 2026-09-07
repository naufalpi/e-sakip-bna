<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, FileSearch, MousePointer2, Search } from 'lucide-vue-next';
import type { PublicHomeModule } from '../types';
defineProps<{ modules: PublicHomeModule[]; year: number; opdCount: number }>();
const details: Record<string, string[]> = {
    perencanaan: ['RENSTRA', 'RENJA / RKT', 'Perjanjian Kinerja'],
    pengukuran: ['Tujuan & sasaran', 'Program', 'Kegiatan'],
    pelaporan: ['LKjIP', 'Laporan triwulan'],
    evaluasi: ['Nilai SAKIP', 'LHE', 'Tindak lanjut'],
};
</script>
<template>
    <section id="jelajahi" class="portal-explore">
        <div class="portal-container">
            <div class="portal-section-heading">
                <div>
                    <p class="portal-eyebrow">JELAJAHI AKUNTABILITAS</p>
                    <h2>Satu siklus.<br /><span>Empat perspektif kinerja.</span></h2>
                </div>
                <p>
                    Mulai dari rencana hingga evaluasi.<br />Temukan informasi yang Anda butuhkan<br class="hidden sm:block" />
                    untuk tahun {{ year }}.
                </p>
            </div>
            <div class="portal-module-grid">
                <Link
                    v-for="(module, index) in modules"
                    :key="module.id"
                    :href="module.href"
                    class="portal-module"
                    :class="`portal-module--${module.id}`"
                >
                    <div class="portal-module-top">
                        <span class="portal-module-icon"><component :is="module.icon" :size="24" :stroke-width="1.6" /></span
                        ><span class="portal-module-index">0{{ index + 1 }} / SIKLUS SAKIP</span
                        ><ArrowUpRight class="portal-module-arrow" :size="24" />
                    </div>
                    <div class="portal-module-copy">
                        <h3>{{ module.title }}</h3>
                        <p>{{ module.summary }}</p>
                    </div>
                    <div class="portal-module-tags">
                        <span v-for="detail in details[module.id]" :key="detail">{{ detail }}</span>
                    </div>
                    <div class="portal-module-bottom">
                        <div>
                            <span>OPD dengan data</span
                            ><strong
                                >{{ module.readyCount }} <small>/ {{ opdCount }}</small></strong
                            >
                        </div>
                        <div class="portal-module-progress" :aria-label="`${module.completeness} OPD memiliki data`">
                            <span :style="{ width: module.completeness }"></span>
                        </div>
                        <span class="portal-module-cta">Lihat data <ArrowUpRight :size="16" /></span>
                    </div>
                </Link>
            </div>
            <div class="portal-guide">
                <div>
                    <p class="portal-eyebrow">AKSES MUDAH</p>
                    <h2>Informasi yang Anda cari,<br />selangkah lebih dekat.</h2>
                </div>
                <ol>
                    <li>
                        <MousePointer2 :size="19" />
                        <div>
                            <strong>Pilih kategori kinerja</strong>
                            <p>Buka salah satu dari empat siklus di atas.</p>
                        </div>
                    </li>
                    <li>
                        <Search :size="19" />
                        <div>
                            <strong>Temukan perangkat daerah</strong>
                            <p>Sesuaikan tahun dan cari OPD yang ingin dilihat.</p>
                        </div>
                    </li>
                    <li>
                        <FileSearch :size="19" />
                        <div>
                            <strong>Baca data dan dokumennya</strong>
                            <p>Lihat informasi atau unduh dokumen yang tersedia.</p>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>
