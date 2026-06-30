<script setup lang="ts">
import Download from 'lucide-vue-next/dist/esm/icons/download.js';
import Eye from 'lucide-vue-next/dist/esm/icons/eye.js';
import X from 'lucide-vue-next/dist/esm/icons/x.js';
import { computed } from 'vue';

import type { PublicCell } from '../types';
import { cellClass, dotClass } from '../utils';

const props = withDefaults(
    defineProps<{
        cell?: PublicCell;
        columnLabel: string;
        mobile?: boolean;
    }>(),
    {
        mobile: false,
    },
);

const actionClass = computed(() =>
    props.mobile
        ? 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-700'
        : 'inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-blue-100 bg-white p-2 text-slate-700 transition hover:border-blue-300 hover:text-[#00336C]',
);

const emptyClass = computed(() =>
    props.mobile
        ? 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-400'
        : 'inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-400',
);
</script>

<template>
    <div class="space-y-2">
        <div v-if="cell?.dokumen" class="flex flex-wrap gap-2">
            <a
                :href="cell.dokumen.view_url"
                target="_blank"
                rel="noopener"
                title="Lihat dokumen"
                :aria-label="`Lihat ${cell.dokumen.judul || columnLabel}`"
                :class="actionClass"
            >
                <Eye class="h-4 w-4" />
                <span class="sr-only">Lihat</span>
            </a>
            <a
                :href="cell.dokumen.download_url"
                title="Download dokumen"
                :aria-label="`Download ${cell.dokumen.judul || columnLabel}`"
                :class="actionClass"
            >
                <Download class="h-4 w-4" />
                <span class="sr-only">Download</span>
            </a>
        </div>
        <template v-else-if="cell?.state === 'missing'">
            <span :class="emptyClass" title="Belum tersedia" aria-label="Belum tersedia">
                <X class="h-4 w-4" />
                <span class="sr-only">Belum tersedia</span>
            </span>
        </template>
        <template v-else>
            <span class="inline-flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs font-semibold" :class="cellClass(cell)">
                <span class="h-2 w-2 rounded-full" :class="dotClass(cell)"></span>
                {{ cell?.label || 'Belum tersedia' }}
            </span>
            <p v-if="cell?.description" class="text-xs text-slate-500">
                {{ cell.description }}
            </p>
        </template>
    </div>
</template>
