<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LogIn from 'lucide-vue-next/dist/esm/icons/log-in.js';
import Menu from 'lucide-vue-next/dist/esm/icons/menu.js';
import ShieldCheck from 'lucide-vue-next/dist/esm/icons/shield-check.js';
import X from 'lucide-vue-next/dist/esm/icons/x.js';
import { ref } from 'vue';

import type { PublicNavItem } from '../types';

defineProps<{
    homeUrl: string;
    navItems: PublicNavItem[];
    entryUrl: string;
    entryLabel: string;
}>();

const isMobileMenuOpen = ref(false);

function closeMobileMenu(): void {
    isMobileMenuOpen.value = false;
}
</script>

<template>
    <header class="fixed inset-x-0 top-0 z-50 border-b border-blue-100 bg-white shadow-sm shadow-blue-950/5">
        <div class="bg-[#00336C]">
            <div class="mx-auto flex min-h-9 max-w-7xl items-center gap-2 px-4 text-xs font-medium text-blue-50 sm:px-6 lg:px-8">
                <ShieldCheck class="h-3.5 w-3.5 text-blue-200" />
                Portal publik akuntabilitas kinerja Pemerintah Kabupaten Banjarnegara
            </div>
        </div>
        <div class="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link
                :href="homeUrl"
                class="flex min-h-11 items-center gap-3 rounded-md focus:outline-none focus:ring-2 focus:ring-[#00336C] focus:ring-offset-2"
            >
                <img src="/images/logo-banjarnegara.svg" alt="Lambang Kabupaten Banjarnegara" class="h-11 w-11 object-contain" />
                <div class="leading-tight">
                    <p class="text-sm font-bold uppercase text-[#00336C]">E-SAKIP</p>
                    <p class="text-sm font-medium text-slate-700">Kabupaten Banjarnegara</p>
                </div>
            </Link>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                <Link
                    v-for="item in navItems"
                    :key="item.id"
                    :href="item.href"
                    class="relative rounded-md px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-[#00336C] focus:ring-offset-2"
                    :class="
                        item.isActive
                            ? 'text-[#00336C] after:absolute after:inset-x-4 after:bottom-0 after:h-0.5 after:rounded-full after:bg-[#00336C]'
                            : 'text-slate-700 hover:bg-blue-50 hover:text-[#00336C]'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <Link
                    :href="entryUrl"
                    class="inline-flex min-h-11 items-center gap-2 rounded-md bg-[#00336C] px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-blue-950/15 transition hover:bg-[#002957] focus:outline-none focus:ring-2 focus:ring-[#00336C] focus:ring-offset-2"
                >
                    <LogIn class="h-4 w-4" />
                    {{ entryLabel }}
                </Link>
            </div>

            <button
                type="button"
                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-800 shadow-sm lg:hidden"
                aria-label="Buka menu navigasi"
                @click="isMobileMenuOpen = !isMobileMenuOpen"
            >
                <X v-if="isMobileMenuOpen" class="h-5 w-5" />
                <Menu v-else class="h-5 w-5" />
            </button>
        </div>

        <div v-if="isMobileMenuOpen" class="border-t border-slate-200 bg-white px-4 py-4 lg:hidden">
            <nav class="grid gap-2" aria-label="Navigasi mobile">
                <Link
                    v-for="item in navItems"
                    :key="item.id"
                    :href="item.href"
                    class="min-h-11 rounded-md px-3 py-3 text-sm font-semibold"
                    :class="item.isActive ? 'text-[#00336C]' : 'text-slate-700 hover:bg-blue-50'"
                    @click="closeMobileMenu"
                >
                    {{ item.label }}
                </Link>
                <Link
                    :href="entryUrl"
                    class="mt-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-[#00336C] px-4 py-2 text-sm font-semibold text-white"
                >
                    <LogIn class="h-4 w-4" />
                    {{ entryLabel }}
                </Link>
            </nav>
        </div>
    </header>
</template>
