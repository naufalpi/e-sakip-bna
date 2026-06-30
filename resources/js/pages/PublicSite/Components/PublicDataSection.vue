<script setup lang="ts">
import CalendarDays from 'lucide-vue-next/dist/esm/icons/calendar-days.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';

import type { PublicTableSection } from '../types';
import { emptyTableMessage } from '../utils';
import PublicDataCell from './PublicDataCell.vue';

defineProps<{
    section: PublicTableSection;
    filters: {
        tahun: number;
    };
    availableYears: number[];
    selectedYearLabel: string;
    currentRowsCount: number;
    searchQuery: string;
}>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'change-year': [event: Event];
    'reset-search': [];
}>();

function updateSearch(event: Event): void {
    emit('update:searchQuery', (event.target as HTMLInputElement).value);
}
</script>

<template>
    <section :id="section.id" class="scroll-mt-24 border-b border-blue-100 bg-blue-50/30 py-14 sm:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-7 flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold uppercase text-[#00336C]">
                        <component :is="section.icon" class="h-4 w-4" />
                        {{ section.eyebrow }}
                    </p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">{{ section.title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ section.summary }}</p>
                </div>

                <div
                    class="grid gap-3 rounded-xl border border-blue-100 bg-white p-3 shadow-sm shadow-blue-950/5 sm:grid-cols-[13rem_minmax(16rem,24rem)]"
                >
                    <label class="block">
                        <span class="mb-1.5 flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                            <CalendarDays class="h-3.5 w-3.5" />
                            Tahun
                        </span>
                        <select
                            :value="filters.tahun"
                            class="min-h-11 w-full rounded-md border border-blue-100 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:border-[#00336C] focus:outline-none focus:ring-2 focus:ring-[#00336C]/20"
                            @change="emit('change-year', $event)"
                        >
                            <option v-for="year in availableYears" :key="year" :value="year">
                                {{ year }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                            <Search class="h-3.5 w-3.5" />
                            Cari tabel
                        </span>
                        <input
                            :value="searchQuery"
                            type="search"
                            class="min-h-11 w-full rounded-md border border-blue-100 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#00336C] focus:outline-none focus:ring-2 focus:ring-[#00336C]/20"
                            placeholder="Cari OPD, status, dokumen..."
                            @input="updateSearch"
                        />
                    </label>
                </div>
            </div>

            <div class="mb-4 flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                <p>
                    Menampilkan <span class="font-semibold text-slate-950">{{ section.rows.length }}</span> dari
                    <span class="font-semibold text-slate-950">{{ currentRowsCount }}</span> OPD untuk
                    <span class="font-semibold text-slate-950">{{ selectedYearLabel }}</span
                    >.
                </p>
                <button
                    v-if="searchQuery"
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-md border border-blue-100 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-blue-300 hover:text-[#00336C]"
                    @click="emit('reset-search')"
                >
                    Reset pencarian
                </button>
            </div>

            <div class="hidden overflow-hidden rounded-xl border border-blue-100 bg-white shadow-sm shadow-blue-950/5 lg:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left">
                        <thead class="bg-blue-50/80">
                            <tr>
                                <th class="w-16 px-4 py-4 text-xs font-semibold uppercase text-slate-500">No</th>
                                <th class="min-w-72 px-4 py-4 text-xs font-semibold uppercase text-slate-500">Perangkat Daerah</th>
                                <th
                                    v-for="column in section.columns"
                                    :key="column.key"
                                    class="min-w-36 px-4 py-4 text-xs font-semibold uppercase text-slate-500"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in section.rows" :key="`${section.id}-${row.opd.id}`" class="transition hover:bg-slate-50">
                                <td class="px-4 py-4 text-sm font-medium text-slate-500">{{ row.no }}</td>
                                <td class="px-4 py-4">
                                    <p class="text-sm font-semibold leading-6 text-slate-950">{{ row.opd.nama }}</p>
                                </td>
                                <td v-for="column in section.columns" :key="column.key" class="px-4 py-4 align-top">
                                    <PublicDataCell :cell="row.cells[column.key]" :column-label="column.label" />
                                </td>
                            </tr>
                            <tr v-if="section.rows.length === 0">
                                <td :colspan="section.columns.length + 2" class="px-4 py-10 text-center text-sm text-slate-500">
                                    {{ emptyTableMessage(searchQuery, section.rows) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-4 lg:hidden">
                <article
                    v-for="row in section.rows"
                    :key="`${section.id}-mobile-${row.opd.id}`"
                    class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm shadow-blue-950/5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">No {{ row.no }}</p>
                            <h3 class="mt-1 text-base font-bold leading-snug text-slate-950">{{ row.opd.nama }}</h3>
                        </div>
                        <span
                            class="shrink-0 rounded-md border px-2.5 py-1 text-xs font-semibold"
                            :class="row.is_ready ? 'border-blue-200 bg-blue-50 text-[#00336C]' : 'border-slate-200 bg-slate-50 text-slate-500'"
                        >
                            {{ row.is_ready ? 'Ada data' : 'Belum lengkap' }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <div v-for="column in section.columns" :key="column.key" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">{{ column.label }}</p>
                            <div class="mt-2">
                                <PublicDataCell :cell="row.cells[column.key]" :column-label="column.label" mobile />
                            </div>
                        </div>
                    </div>
                </article>

                <div
                    v-if="section.rows.length === 0"
                    class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
                >
                    {{ emptyTableMessage(searchQuery, section.rows) }}
                </div>
            </div>
        </div>
    </section>
</template>
