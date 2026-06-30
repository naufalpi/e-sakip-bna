<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type { PublicHomeModule, SectionId } from '../types';
import { cycleCardClass } from '../utils';

defineProps<{
    modules: PublicHomeModule[];
    activeSection: SectionId | null;
}>();
</script>

<template>
    <section class="cycle-band border-b border-blue-100 bg-white">
        <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 md:grid-cols-4 lg:px-8">
            <Link
                v-for="(module, index) in modules"
                :key="module.id"
                :href="module.href"
                class="cycle-card"
                :class="[cycleCardClass(module.id), module.id === activeSection ? 'cycle-card-active' : '']"
            >
                <div class="cycle-icon">
                    <component :is="module.icon" class="h-5 w-5" />
                </div>
                <div>
                    <p>{{ String(index + 1).padStart(2, '0') }}</p>
                    <span>{{ module.eyebrow }}</span>
                </div>
            </Link>
        </div>
    </section>
</template>
