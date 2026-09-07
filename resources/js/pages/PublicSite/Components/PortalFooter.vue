<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from 'lucide-vue-next';
import type { PublicNavItem } from '../types';
defineProps<{ year: number; navItems: PublicNavItem[]; homeUrl: string; entryUrl: string; entryLabel: string }>();
</script>
<template>
    <footer class="portal-footer">
        <div class="portal-container">
            <div class="portal-footer-top">
                <div>
                    <Link :href="homeUrl" class="portal-brand"
                        ><img src="/images/logo-banjarnegara-96.webp" alt="Lambang Kabupaten Banjarnegara" width="40" height="48" />
                        <div>
                            <strong>E-SAKIP<span>.</span></strong
                            ><small>Kabupaten Banjarnegara</small>
                        </div></Link
                    >
                    <p>
                        Sistem Akuntabilitas Kinerja Instansi Pemerintah.<br />Menghubungkan rencana, capaian, dan perbaikan<br />untuk pelayanan
                        publik yang lebih baik.
                    </p>
                </div>
                <nav aria-label="Navigasi footer">
                    <p>JELAJAHI DATA</p>
                    <Link v-for="item in navItems.filter((item) => item.id !== 'beranda')" :key="item.id" :href="item.href"
                        >{{ item.label }}<ArrowUpRight :size="14"
                    /></Link>
                </nav>
                <div class="portal-footer-access">
                    <p>AKSES PENGELOLA</p>
                    <h2>Bersama membangun<br />kinerja yang lebih baik.</h2>
                    <Link :href="entryUrl" class="portal-button"
                        >{{ entryLabel === 'Login' ? 'Masuk aplikasi' : entryLabel }}<ArrowUpRight :size="17"
                    /></Link>
                </div>
            </div>
            <div class="portal-footer-bottom">
                <span>&copy; {{ year }} Dinas Komunikasi dan Informatika Kabupaten Banjarnegara.</span><span>Portal Publik E-SAKIP</span>
            </div>
        </div>
    </footer>
</template>
