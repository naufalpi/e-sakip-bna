<script setup lang="ts">
import PohonKinerjaNode, { type TreeNode } from '@/components/PohonKinerjaNode.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileJson2, GitBranch, Layers3, Network } from 'lucide-vue-next';
import { computed } from 'vue';

type Option = {
    id: number;
    label: string;
};

type Stats = {
    total_nodes: number;
    indicator_nodes: number;
    target_tahunan_nodes: number;
    target_triwulan_nodes: number;
    linked_nodes: number;
    incomplete_nodes: number;
    opd_penanggung_jawab_nodes: number;
    total_pagu: number;
    total_target_anggaran_triwulan: number;
};

const props = defineProps<{
    mode: 'kabupaten' | 'opd' | 'cascading';
    title: string;
    description: string;
    tree: TreeNode;
    stats: Stats;
    filters: {
        mode: 'kabupaten' | 'opd' | 'cascading';
        rpjmd_id?: number | null;
        renstra_opd_id?: number | null;
    };
    rpjmdOptions: Option[];
    renstraOptions: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pohon Kinerja', href: '/pohon-kinerja' },
];

const form = useForm({
    mode: props.filters.mode,
    rpjmd_id: props.filters.rpjmd_id ?? '',
    renstra_opd_id: props.filters.renstra_opd_id ?? '',
});

const selectedModeNeedsRpjmd = computed(() => form.mode === 'kabupaten');
const selectedModeNeedsRenstra = computed(() => form.mode === 'opd' || form.mode === 'cascading');
const detailHref = computed(() => {
    if (props.mode === 'kabupaten' && props.filters.rpjmd_id) {
        return route('rpjmd.show', props.filters.rpjmd_id);
    }

    if ((props.mode === 'opd' || props.mode === 'cascading') && props.filters.renstra_opd_id) {
        return route('renstra-opd.show', props.filters.renstra_opd_id);
    }

    return null;
});

const jsonEndpoint = computed(() => {
    if (props.mode === 'kabupaten' && props.filters.rpjmd_id) {
        return `${route('pohon-kinerja.kabupaten', props.filters.rpjmd_id)}?format=json`;
    }

    if (props.mode === 'opd' && props.filters.renstra_opd_id) {
        return `${route('pohon-kinerja.opd', props.filters.renstra_opd_id)}?format=json`;
    }

    if (props.mode === 'cascading' && props.filters.renstra_opd_id) {
        return `${route('pohon-kinerja.cascading-opd', props.filters.renstra_opd_id)}?format=json`;
    }

    return `${route('pohon-kinerja.index')}?mode=${props.mode}&format=json`;
});

const modeIcon = computed(() => {
    if (props.mode === 'opd') {
        return Layers3;
    }

    if (props.mode === 'cascading') {
        return Network;
    }

    return GitBranch;
});

const statCards = computed(() => [
    { label: 'Total Node', value: props.stats.total_nodes },
    { label: 'Indikator', value: props.stats.indicator_nodes },
    { label: 'Target Tahunan', value: props.stats.target_tahunan_nodes },
    { label: 'Target Triwulan', value: props.stats.target_triwulan_nodes },
    { label: 'Node Terhubung', value: props.stats.linked_nodes },
    { label: 'Perlu Dilengkapi', value: props.stats.incomplete_nodes },
    { label: 'OPD PJ', value: props.stats.opd_penanggung_jawab_nodes },
]);

const submit = () => {
    router.get(
        route('pohon-kinerja.index'),
        {
            mode: form.mode,
            rpjmd_id: form.rpjmd_id || undefined,
            renstra_opd_id: form.renstra_opd_id || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const formatCurrency = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
};
</script>

<template>
    <Head title="Pohon Kinerja" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <section class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                        <component :is="modeIcon" class="size-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-normal">{{ title }}</h1>
                        <p class="mt-1 max-w-3xl text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="detailHref" :href="detailHref" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Buka Detail Data</Link>
                    <a :href="jsonEndpoint" target="_blank" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted">
                        <FileJson2 class="size-4" />
                        Endpoint JSON
                    </a>
                </div>
            </section>

            <form class="grid gap-3 rounded-lg border bg-card p-4 lg:grid-cols-[220px_1fr_1fr_auto]" @submit.prevent="submit">
                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="mode">Mode Pohon</label>
                    <select id="mode" v-model="form.mode" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="kabupaten">Kabupaten/RPJMD</option>
                        <option value="opd">OPD/Renstra</option>
                        <option value="cascading">Cascading OPD ke RPJMD</option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="rpjmd_id">RPJMD</label>
                    <select id="rpjmd_id" v-model="form.rpjmd_id" class="h-9 rounded-md border bg-background px-3 text-sm" :disabled="!selectedModeNeedsRpjmd">
                        <option value="">Pilih RPJMD</option>
                        <option v-for="option in rpjmdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium" for="renstra_opd_id">Renstra OPD</label>
                    <select id="renstra_opd_id" v-model="form.renstra_opd_id" class="h-9 rounded-md border bg-background px-3 text-sm" :disabled="!selectedModeNeedsRenstra">
                        <option value="">Pilih Renstra OPD</option>
                        <option v-for="option in renstraOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="h-9 rounded-md bg-emerald-700 px-4 text-sm font-medium text-white hover:bg-emerald-800">Tampilkan</button>
                </div>
            </form>

            <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-7">
                <div v-for="stat in statCards" :key="stat.label" class="rounded-lg border bg-card p-3">
                    <div class="text-xs uppercase text-muted-foreground">{{ stat.label }}</div>
                    <div class="mt-1 text-xl font-semibold">{{ stat.value }}</div>
                </div>
            </section>

            <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2">
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Total Pagu Indikatif/Tahunan</div>
                    <div class="mt-1 text-base font-semibold">{{ formatCurrency(stats.total_pagu) }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Total Target Anggaran Triwulan</div>
                    <div class="mt-1 text-base font-semibold">{{ formatCurrency(stats.total_target_anggaran_triwulan) }}</div>
                </div>
            </section>

            <section class="rounded-lg border bg-card">
                <div class="border-b p-4">
                    <h2 class="text-base font-semibold">Visualisasi Tree</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Node dapat dibuka dan ditutup untuk menelusuri cascading sampai target tahunan dan triwulan.</p>
                </div>
                <div class="space-y-2 overflow-x-auto p-4">
                    <PohonKinerjaNode :node="tree" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
