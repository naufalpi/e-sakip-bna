<script setup lang="ts">
import { AlertTriangle, CheckCircle2, ChevronRight, Link2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export type TreeNode = {
    key: string;
    type: string;
    id: number | string;
    label: string;
    meta?: Record<string, unknown>;
    linked_to?: {
        type: string;
        id: number | string;
        label: string;
    } | null;
    children?: TreeNode[];
};

const props = withDefaults(
    defineProps<{
        node: TreeNode;
        level?: number;
    }>(),
    {
        level: 0,
    },
);

const isOpen = ref(props.level < 3);
const children = computed(() => props.node.children ?? []);
const hasChildren = computed(() => children.value.length > 0);

const typeLabel = computed(
    () =>
        ({
            rpjmd: 'RPJMD',
            visi: 'Visi',
            misi: 'Misi',
            tujuan_daerah: 'Tujuan Daerah',
            sasaran_daerah: 'Sasaran Daerah',
            strategi_daerah: 'Strategi Daerah',
            program_rpjmd: 'Program RPJMD',
            indikator_tujuan_daerah: 'Indikator Tujuan',
            indikator_sasaran_daerah: 'Indikator Sasaran',
            indikator_program_rpjmd: 'Indikator Program',
            opd_penanggung_jawab: 'OPD PJ',
            renstra_opd: 'Renstra OPD',
            tujuan_opd: 'Tujuan OPD',
            sasaran_opd: 'Sasaran OPD',
            opd_program: 'Program OPD',
            opd_kegiatan: 'Kegiatan',
            opd_sub_kegiatan: 'Sub Kegiatan',
            indikator_tujuan_opd: 'Indikator Tujuan OPD',
            indikator_sasaran_opd: 'Indikator Sasaran OPD',
            indikator_opd_program: 'Indikator Program OPD',
            indikator_sub_kegiatan: 'Indikator Sub Kegiatan',
            target_tahunan: 'Target Tahunan',
            target_triwulan: 'Target Triwulan',
            cascading_opd_rpjmd: 'Cascading',
            empty: 'Data',
        })[props.node.type] ?? props.node.type.replaceAll('_', ' '),
);

const badgeClass = computed(() => {
    if (props.node.type.includes('indikator')) {
        return 'bg-blue-50 text-blue-800';
    }

    if (props.node.type.includes('target')) {
        return 'bg-emerald-50 text-emerald-800';
    }

    if (props.node.type.includes('opd')) {
        return 'bg-cyan-50 text-cyan-800';
    }

    if (props.node.type.includes('rpjmd') || props.node.type.includes('renstra')) {
        return 'bg-slate-100 text-slate-800';
    }

    return 'bg-zinc-100 text-zinc-700';
});

const metaEntries = computed(() =>
    Object.entries(props.node.meta ?? {})
        .filter(([key]) => !key.startsWith('kelengkapan_'))
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([key, value]) => ({
            key,
            label: key.replaceAll('_', ' '),
            value: formatValue(key, value),
        })),
);

const completionStatus = computed(() => String(props.node.meta?.kelengkapan_status ?? 'lengkap'));
const completionNote = computed(() => String(props.node.meta?.kelengkapan_catatan ?? 'Struktur minimal tersedia.'));
const isIncomplete = computed(() => completionStatus.value === 'perlu_dilengkapi');
const completionLabel = computed(() => (isIncomplete.value ? 'Perlu dilengkapi' : 'Lengkap'));
const completionClass = computed(() =>
    isIncomplete.value ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
);
const nodeClass = computed(() =>
    isIncomplete.value
        ? 'border-amber-200 bg-amber-50/35 shadow-[inset_3px_0_0_#d97706]'
        : props.node.linked_to
          ? 'border-emerald-200 bg-emerald-50/20 shadow-[inset_3px_0_0_#047857]'
          : 'border-slate-200 bg-white',
);

const formatValue = (key: string, value: unknown) => {
    if (typeof value === 'boolean') {
        return value ? 'Ya' : 'Tidak';
    }

    if (typeof value === 'number' || (typeof value === 'string' && value !== '' && !Number.isNaN(Number(value)))) {
        if (key.includes('pagu') || key.includes('anggaran')) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value));
        }
    }

    return String(value);
};
</script>

<template>
    <div class="relative">
        <div class="flex gap-2">
            <div v-if="level > 0" class="w-4 shrink-0 border-l border-dashed border-slate-300" />
            <div class="min-w-0 flex-1 rounded-md border px-3 py-2" :class="nodeClass">
                <div class="flex items-start gap-2">
                    <button
                        type="button"
                        class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border text-slate-600 disabled:opacity-30"
                        :disabled="!hasChildren"
                        @click="isOpen = !isOpen"
                    >
                        <ChevronRight class="size-3 transition-transform" :class="{ 'rotate-90': isOpen && hasChildren }" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium uppercase" :class="badgeClass">{{ typeLabel }}</span>
                            <span v-if="hasChildren" class="text-[11px] text-muted-foreground">{{ children.length }} turunan</span>
                            <span
                                v-if="node.linked_to"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800"
                            >
                                <Link2 class="size-3" />
                                Terhubung
                            </span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="completionClass"
                                :title="completionNote"
                            >
                                <AlertTriangle v-if="isIncomplete" class="size-3" />
                                <CheckCircle2 v-else class="size-3" />
                                {{ completionLabel }}
                            </span>
                        </div>
                        <div class="mt-1 break-words text-sm font-medium leading-5">{{ node.label }}</div>
                        <div v-if="node.linked_to" class="mt-1 text-xs text-muted-foreground">Referensi: {{ node.linked_to.label }}</div>
                        <div v-if="isIncomplete" class="mt-1 text-xs text-amber-800">{{ completionNote }}</div>

                        <div v-if="metaEntries.length" class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="entry in metaEntries"
                                :key="entry.key"
                                class="rounded border bg-slate-50 px-2 py-0.5 text-[11px] text-slate-700"
                            >
                                {{ entry.label }}: {{ entry.value }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isOpen && hasChildren" class="mt-2 space-y-2">
            <PohonKinerjaNode v-for="child in children" :key="child.key" :node="child" :level="level + 1" />
        </div>
    </div>
</template>
