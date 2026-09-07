<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Menu, ShieldCheck, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import type { PublicNavItem } from '../types';
const props = defineProps<{ homeUrl: string; navItems: PublicNavItem[]; entryUrl: string; entryLabel: string }>();
const isMobileMenuOpen = ref(false);
watch(
    () => props.navItems,
    () => {
        isMobileMenuOpen.value = false;
    },
);
</script>
<template>
    <header class="portal-header" @keydown.esc="isMobileMenuOpen = false">
        <div class="portal-government-bar">
            <div class="portal-container">
                <span><ShieldCheck :size="13" /> Portal publik Pemerintah Kabupaten Banjarnegara</span
                ><span class="portal-government-motto">Transparan. Terukur. Akuntabel.</span>
            </div>
        </div>
        <div class="portal-container portal-header-main">
            <Link :href="homeUrl" class="portal-brand"
                ><img src="/images/logo-banjarnegara-96.webp" alt="Lambang Kabupaten Banjarnegara" width="40" height="48" />
                <div>
                    <strong>E-SAKIP<span>.</span></strong
                    ><small>Kabupaten Banjarnegara</small>
                </div></Link
            >
            <nav class="portal-desktop-nav" aria-label="Navigasi utama">
                <Link
                    v-for="item in navItems"
                    :key="item.id"
                    :href="item.href"
                    :aria-current="item.isActive ? 'page' : undefined"
                    :class="{ 'is-active': item.isActive }"
                    >{{ item.label }}</Link
                >
            </nav>
            <Link :href="entryUrl" class="portal-button portal-header-login"
                >{{ entryLabel === 'Login' ? 'Masuk aplikasi' : entryLabel }} <ArrowUpRight :size="17"
            /></Link>
            <button
                class="portal-mobile-toggle"
                type="button"
                :aria-label="isMobileMenuOpen ? 'Tutup navigasi' : 'Buka navigasi'"
                :aria-expanded="isMobileMenuOpen"
                aria-controls="portal-mobile-nav"
                @click="isMobileMenuOpen = !isMobileMenuOpen"
            >
                <X v-if="isMobileMenuOpen" :size="22" /><Menu v-else :size="22" />
            </button>
        </div>
        <nav v-if="isMobileMenuOpen" id="portal-mobile-nav" class="portal-mobile-nav" aria-label="Navigasi mobile">
            <Link
                v-for="item in navItems"
                :key="item.id"
                :href="item.href"
                :aria-current="item.isActive ? 'page' : undefined"
                :class="{ 'is-active': item.isActive }"
                @click="isMobileMenuOpen = false"
                >{{ item.label }}<ArrowUpRight :size="16" /></Link
            ><Link :href="entryUrl" class="portal-mobile-entry" @click="isMobileMenuOpen = false"
                >{{ entryLabel === 'Login' ? 'Masuk aplikasi' : entryLabel }}<ArrowUpRight :size="16"
            /></Link>
        </nav>
    </header>
</template>
