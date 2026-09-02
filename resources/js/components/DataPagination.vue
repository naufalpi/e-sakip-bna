<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Rows3 } from 'lucide-vue-next';
import { computed } from 'vue';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginator = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links?: PaginationLink[];
};

const props = withDefaults(
    defineProps<{
        paginator: Paginator;
        perPage?: string | number;
        itemLabel?: string;
    }>(),
    {
        perPage: 10,
        itemLabel: 'data',
    },
);

const emit = defineEmits<{
    'update:perPage': [value: string];
}>();

type PageToken = number | 'ellipsis-left' | 'ellipsis-right';

const pageTokens = computed<PageToken[]>(() => {
    const last = props.paginator.last_page;
    const current = props.paginator.current_page;

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const tokens: PageToken[] = [1];

    if (current > 4) {
        tokens.push('ellipsis-left');
    }

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let page = start; page <= end; page += 1) {
        tokens.push(page);
    }

    if (current < last - 3) {
        tokens.push('ellipsis-right');
    }

    tokens.push(last);

    return tokens;
});

const normalizedPerPage = computed({
    get: () => String(props.perPage || '10'),
    set: (value: string) => emit('update:perPage', value),
});

const numericPageUrl = (page: number) => props.paginator.links?.find((link) => Number(link.label) === page)?.url ?? null;
const firstPageUrl = computed(() => numericPageUrl(1));
const lastPageUrl = computed(() => numericPageUrl(props.paginator.last_page));
</script>

<template>
    <footer
        v-if="paginator.total > 0"
        class="border-t border-slate-200 bg-[linear-gradient(180deg,rgba(248,250,252,.65),rgba(255,255,255,.98))] px-4 py-4 dark:border-slate-800 dark:bg-[linear-gradient(180deg,rgba(15,23,42,.5),rgba(2,6,23,.72))] sm:px-5"
    >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="hidden size-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#00336C] ring-1 ring-blue-100 dark:bg-blue-950/50 dark:text-blue-300 dark:ring-blue-900 sm:flex"
                >
                    <Rows3 class="size-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <template v-if="String(perPage) === 'all'">Menampilkan seluruh {{ paginator.total }} {{ itemLabel }}</template>
                        <template v-else
                            >Menampilkan {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} dari {{ paginator.total }} {{ itemLabel }}</template
                        >
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Halaman {{ paginator.current_page }} dari {{ paginator.last_page }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between xl:justify-end">
                <nav class="flex items-center gap-1" aria-label="Navigasi halaman">
                    <Link
                        v-if="firstPageUrl && paginator.current_page > 1"
                        :href="firstPageUrl"
                        preserve-scroll
                        preserve-state
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-[#00336C] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-blue-800 dark:hover:bg-blue-950/50"
                        aria-label="Halaman pertama"
                    >
                        <ChevronsLeft class="size-4" />
                    </Link>
                    <span
                        v-else
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-700"
                    >
                        <ChevronsLeft class="size-4" />
                    </span>

                    <Link
                        v-if="paginator.prev_page_url"
                        :href="paginator.prev_page_url"
                        preserve-scroll
                        preserve-state
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-[#00336C] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-blue-800 dark:hover:bg-blue-950/50"
                        aria-label="Halaman sebelumnya"
                    >
                        <ChevronLeft class="size-4" />
                    </Link>
                    <span
                        v-else
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-700"
                    >
                        <ChevronLeft class="size-4" />
                    </span>

                    <template v-for="token in pageTokens" :key="token">
                        <span v-if="typeof token === 'string'" class="inline-flex size-8 items-center justify-center text-xs font-bold text-slate-400"
                            >•••</span
                        >
                        <span
                            v-else-if="token === paginator.current_page"
                            class="inline-flex size-9 items-center justify-center rounded-lg bg-[#00336C] text-sm font-bold text-white shadow-sm shadow-blue-950/15"
                            :aria-label="`Halaman ${token}`"
                            aria-current="page"
                        >
                            {{ token }}
                        </span>
                        <Link
                            v-else-if="numericPageUrl(token)"
                            :href="numericPageUrl(token) || '#'"
                            preserve-scroll
                            preserve-state
                            class="inline-flex size-9 items-center justify-center rounded-lg border border-transparent text-sm font-semibold text-slate-600 transition hover:border-blue-100 hover:bg-blue-50 hover:text-[#00336C] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-300 dark:hover:border-blue-900 dark:hover:bg-blue-950/50"
                            :aria-label="`Buka halaman ${token}`"
                        >
                            {{ token }}
                        </Link>
                    </template>

                    <Link
                        v-if="paginator.next_page_url"
                        :href="paginator.next_page_url"
                        preserve-scroll
                        preserve-state
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-[#00336C] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-blue-800 dark:hover:bg-blue-950/50"
                        aria-label="Halaman berikutnya"
                    >
                        <ChevronRight class="size-4" />
                    </Link>
                    <span
                        v-else
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-700"
                    >
                        <ChevronRight class="size-4" />
                    </span>

                    <Link
                        v-if="lastPageUrl && paginator.current_page < paginator.last_page"
                        :href="lastPageUrl"
                        preserve-scroll
                        preserve-state
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-[#00336C] focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-blue-800 dark:hover:bg-blue-950/50"
                        aria-label="Halaman terakhir"
                    >
                        <ChevronsRight class="size-4" />
                    </Link>
                    <span
                        v-else
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-700"
                    >
                        <ChevronsRight class="size-4" />
                    </span>
                </nav>

                <label
                    class="flex items-center justify-between gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:justify-start"
                >
                    <span class="whitespace-nowrap text-xs font-semibold text-slate-500 dark:text-slate-400">Baris per halaman</span>
                    <select
                        v-model="normalizedPerPage"
                        class="h-7 min-w-[76px] rounded-md border-0 bg-slate-100 px-2 py-0 text-sm font-bold text-slate-800 outline-none ring-0 focus:ring-2 focus:ring-blue-500 dark:bg-slate-800 dark:text-slate-100"
                        aria-label="Jumlah baris per halaman"
                    >
                        <option value="10">10</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Semua</option>
                    </select>
                </label>
            </div>
        </div>
    </footer>
</template>
