<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowUpRight, BookOpen, Building2, CalendarDays, MapPin, ShieldCheck } from 'lucide-vue-next';
defineProps<{
    year: number;
    availableYears: number[];
    planningUrl: string;
    stats: { opd_count: number; public_document_count: number; evaluation_count: number };
}>();
defineEmits<{ 'change-year': [event: Event] }>();
const number = (value: number) => new Intl.NumberFormat('id-ID').format(value);
</script>
<template>
    <section id="beranda" class="portal-hero">
        <div class="portal-container portal-hero-grid">
            <div class="portal-hero-copy">
                <p class="portal-eyebrow"><span class="portal-dot"></span> E-SAKIP KABUPATEN BANJARNEGARA</p>
                <h1>Kinerja terbuka.<br />Pembangunan<br /><span>terarah.</span></h1>
                <p class="portal-hero-description">
                    Kenali rencana, ikuti capaian, dan telusuri akuntabilitas kinerja Pemerintah Kabupaten Banjarnegara dalam satu portal.
                </p>
                <div class="portal-hero-actions">
                    <Link :href="planningUrl" class="portal-button portal-button-primary">Jelajahi data kinerja <ArrowUpRight :size="18" /></Link
                    ><a href="#jelajahi" class="portal-text-link">Kenali siklus SAKIP <ArrowDown :size="17" /></a>
                </div>
                <div class="portal-hero-note"><ShieldCheck :size="18" /><span>Akses informasi publik, tanpa perlu masuk akun.</span></div>
            </div>
            <div class="portal-hero-visual">
                <div class="portal-photo-frame">
                    <img
                        src="/images/hero-dieng-banjarnegara.webp"
                        alt="Lanskap pegunungan dan danau"
                        width="1672"
                        height="941"
                        fetchpriority="high"
                    />
                    <div class="portal-photo-caption"><MapPin :size="15" /> Banjarnegara, Jawa Tengah</div>
                    <div class="portal-photo-statement">
                        <span>UNTUK PEMBANGUNAN YANG</span><strong>terukur &amp;<br />berkelanjutan.</strong>
                    </div>
                </div>
                <div class="portal-visual-label"><span>TRANSPARANSI</span><span>AKUNTABILITAS</span><span>PELAYANAN PUBLIK</span></div>
                <div class="portal-visual-seal">
                    <ShieldCheck :size="24" />
                    <div><strong>Terbuka untuk publik</strong><span>Rencana hingga evaluasi kinerja</span></div>
                </div>
            </div>
        </div>
        <div class="portal-container">
            <div class="portal-stat-strip">
                <label class="portal-year-control"
                    ><span><CalendarDays :size="15" /> PERIODE DATA</span
                    ><select :value="year" aria-label="Tahun data publik" @change="$emit('change-year', $event)">
                        <option v-for="option in availableYears" :key="option" :value="option">Tahun {{ option }}</option>
                    </select></label
                >
                <div class="portal-stat">
                    <Building2 :size="22" />
                    <div>
                        <strong>{{ number(stats.opd_count) }}</strong
                        ><span>Perangkat daerah</span>
                    </div>
                </div>
                <div class="portal-stat">
                    <BookOpen :size="22" />
                    <div>
                        <strong>{{ number(stats.public_document_count) }}</strong
                        ><span>Dokumen publik</span>
                    </div>
                </div>
                <div class="portal-stat">
                    <ShieldCheck :size="22" />
                    <div>
                        <strong>{{ number(stats.evaluation_count) }}</strong
                        ><span>OPD dengan evaluasi</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
