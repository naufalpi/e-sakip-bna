<script setup lang="ts">
import { computed } from 'vue';

type TreeNode = {
    key?: string;
    type?: string;
    id: number | string;
    label?: string;
    children?: TreeNode[];
};

type RawIndicator = {
    id: number | string;
    label?: string;
    indikator?: string;
    urutan?: number | null;
};

type RawSasaran = {
    id: number | string;
    label?: string;
    sasaran?: string;
    urutan?: number | null;
    indikator?: RawIndicator[];
    children?: TreeNode[];
};

type RawTujuan = {
    id: number | string;
    label?: string;
    tujuan?: string;
    urutan?: number | null;
    indikator?: RawIndicator[];
    sasaran?: RawSasaran[];
    children?: TreeNode[];
};

type RawMisi = {
    id: number | string;
    label?: string;
    misi?: string;
    urutan?: number | null;
};

type RawVisi = {
    id: number | string;
    label?: string;
    visi?: string;
    urutan?: number | null;
    misi?: RawMisi[];
    tujuan?: RawTujuan[];
    children?: TreeNode[];
};

type DiagramIndicator = {
    id: number | string;
    label: string;
};

type DiagramSasaran = {
    id: number | string;
    label: string;
    indicators: DiagramIndicator[];
};

type DiagramTujuan = {
    id: number | string;
    label: string;
    indicators: DiagramIndicator[];
    sasaran: DiagramSasaran[];
};

type DiagramVisi = {
    id: number | string;
    label: string;
    misi: Array<{ id: number | string; label: string }>;
    tujuan: DiagramTujuan[];
};

const props = defineProps<{
    visi?: RawVisi[];
    tree?: TreeNode;
}>();

const sortByOrder = <T extends { urutan?: number | null; id: number | string }>(items: T[] = []) =>
    [...items].sort((a, b) => Number(a.urutan ?? a.id ?? 0) - Number(b.urutan ?? b.id ?? 0));

const cleanLabel = (value?: string | null) => {
    const label = String(value ?? '').trim();

    return label.length > 0 ? label : '-';
};

const compactLabel = (value: string, max = 92) => {
    if (value.length <= max) {
        return value;
    }

    return `${value.slice(0, max - 1).trim()}...`;
};

const childrenByType = (node: TreeNode | undefined, type: string) => (node?.children ?? []).filter((child) => child.type === type);

const normalizeDirectVisi = (visi: RawVisi): DiagramVisi => ({
    id: visi.id,
    label: cleanLabel(visi.visi ?? visi.label),
    misi: sortByOrder(visi.misi ?? []).map((misi) => ({
        id: misi.id,
        label: cleanLabel(misi.misi ?? misi.label),
    })),
    tujuan: sortByOrder(visi.tujuan ?? []).map((tujuan) => ({
        id: tujuan.id,
        label: cleanLabel(tujuan.tujuan ?? tujuan.label),
        indicators: sortByOrder(tujuan.indikator ?? []).map((indikator) => ({
            id: indikator.id,
            label: cleanLabel(indikator.indikator ?? indikator.label),
        })),
        sasaran: sortByOrder(tujuan.sasaran ?? []).map((sasaran) => ({
            id: sasaran.id,
            label: cleanLabel(sasaran.sasaran ?? sasaran.label),
            indicators: sortByOrder(sasaran.indikator ?? []).map((indikator) => ({
                id: indikator.id,
                label: cleanLabel(indikator.indikator ?? indikator.label),
            })),
        })),
    })),
});

const normalizeTreeVisi = (visi: TreeNode): DiagramVisi => ({
    id: visi.id,
    label: cleanLabel(visi.label),
    misi: childrenByType(visi, 'misi').map((misi) => ({
        id: misi.id,
        label: cleanLabel(misi.label),
    })),
    tujuan: childrenByType(visi, 'tujuan_daerah').map((tujuan) => ({
        id: tujuan.id,
        label: cleanLabel(tujuan.label),
        indicators: childrenByType(tujuan, 'indikator_tujuan_daerah').map((indikator) => ({
            id: indikator.id,
            label: cleanLabel(indikator.label),
        })),
        sasaran: childrenByType(tujuan, 'sasaran_daerah').map((sasaran) => ({
            id: sasaran.id,
            label: cleanLabel(sasaran.label),
            indicators: childrenByType(sasaran, 'indikator_sasaran_daerah').map((indikator) => ({
                id: indikator.id,
                label: cleanLabel(indikator.label),
            })),
        })),
    })),
});

const visibleDiagrams = computed(() => {
    if (props.visi?.length) {
        return sortByOrder(props.visi).map(normalizeDirectVisi);
    }

    if (!props.tree) {
        return [];
    }

    const rootVisi = props.tree.type === 'visi' ? [props.tree] : childrenByType(props.tree, 'visi');

    return rootVisi.map(normalizeTreeVisi);
});

const misiColumnWidth = 170;
const misiGap = 24;
const tujuanNodeWidth = 190;
const tujuanGap = 56;
const tujuanIndicatorGap = 48;
const tujuanIndicatorWidth = 176;
const sasaranNodeMinWidth = 188;
const sasaranGap = 34;
const indicatorColumnWidth = 150;
const indicatorGap = 16;

const indicatorRowWidth = (count: number) => (count > 0 ? count * indicatorColumnWidth + Math.max(0, count - 1) * indicatorGap : sasaranNodeMinWidth);

const sasaranBlockWidth = (sasaran: DiagramSasaran) => Math.max(sasaranNodeMinWidth, indicatorRowWidth(sasaran.indicators.length));

const sasaranRowWidth = (tujuan: DiagramTujuan) => {
    if (!tujuan.sasaran.length) {
        return sasaranNodeMinWidth;
    }

    return tujuan.sasaran.reduce((width, sasaran, index) => width + sasaranBlockWidth(sasaran) + (index > 0 ? sasaranGap : 0), 0);
};

const tujuanHeaderWidth = (tujuan: DiagramTujuan) => tujuanNodeWidth + (tujuan.indicators.length ? tujuanIndicatorGap + tujuanIndicatorWidth : 0);

const tujuanBlockWidth = (tujuan: DiagramTujuan) => Math.max(360, tujuanHeaderWidth(tujuan), sasaranRowWidth(tujuan));

const tujuanRowWidth = (diagram: DiagramVisi) => {
    if (!diagram.tujuan.length) {
        return 360;
    }

    return diagram.tujuan.reduce((width, tujuan, index) => width + tujuanBlockWidth(tujuan) + (index > 0 ? tujuanGap : 0), 0);
};

const misiRowWidth = (diagram: DiagramVisi) => {
    if (!diagram.misi.length) {
        return 240;
    }

    return diagram.misi.length * misiColumnWidth + Math.max(0, diagram.misi.length - 1) * misiGap;
};

const busStyle = (left: number, right: number) => ({
    left: `${left}px`,
    right: `${right}px`,
});

const misiBusStyle = () => busStyle(misiColumnWidth / 2, misiColumnWidth / 2);

const tujuanBusStyle = (diagram: DiagramVisi) => {
    const first = diagram.tujuan[0];
    const last = diagram.tujuan[diagram.tujuan.length - 1];

    return first && last ? busStyle(tujuanBlockWidth(first) / 2, tujuanBlockWidth(last) / 2) : {};
};

const sasaranBusStyle = (tujuan: DiagramTujuan) => {
    const first = tujuan.sasaran[0];
    const last = tujuan.sasaran[tujuan.sasaran.length - 1];

    return first && last ? busStyle(sasaranBlockWidth(first) / 2, sasaranBlockWidth(last) / 2) : {};
};

const indicatorBusStyle = () => busStyle(indicatorColumnWidth / 2, indicatorColumnWidth / 2);

const diagramWidth = (diagram: DiagramVisi) => {
    return Math.max(1040, misiRowWidth(diagram) + 220, tujuanRowWidth(diagram) + 220);
};

const hasData = computed(() => visibleDiagrams.value.some((diagram) => diagram.misi.length || diagram.tujuan.length));
</script>

<template>
    <div class="rpjmd-performance-tree">
        <div v-if="!hasData" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">Belum ada data pohon kinerja.</div>

        <div v-else class="grid gap-5">
            <section
                v-for="diagram in visibleDiagrams"
                :key="diagram.id"
                class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
                aria-label="Diagram pohon kinerja RPJMD"
            >
                <div class="diagram-scroll">
                    <div class="diagram-canvas" :style="{ width: `${diagramWidth(diagram)}px` }">
                        <div class="diagram-level">
                            <div class="diagram-box diagram-box--vision" :title="diagram.label">
                                <div class="diagram-code">Visi</div>
                                <p>{{ compactLabel(diagram.label, 110) }}</p>
                            </div>
                        </div>

                        <div v-if="diagram.misi.length" class="misi-network">
                            <div class="line-vertical line-vertical--from-vision" />
                            <div class="misi-row-wrap" :style="{ width: `${misiRowWidth(diagram)}px` }">
                                <div class="line-horizontal line-horizontal--misi-top" :style="misiBusStyle()" />
                                <div class="misi-row">
                                    <div v-for="(misi, index) in diagram.misi" :key="misi.id" class="misi-column">
                                        <div class="diagram-box diagram-box--misi" :title="misi.label">
                                            <div class="diagram-code">Misi {{ index + 1 }}</div>
                                            <p>{{ compactLabel(misi.label, 72) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="line-horizontal line-horizontal--misi-bottom" :style="misiBusStyle()" />
                            </div>
                        </div>

                        <div v-if="diagram.tujuan.length" class="tujuan-layer" :class="{ 'tujuan-layer--without-misi': !diagram.misi.length }">
                            <div
                                class="line-vertical line-vertical--to-tujuan"
                                :class="{
                                    'line-vertical--to-tujuan-bus': diagram.tujuan.length > 1,
                                    'line-vertical--arrow': diagram.tujuan.length === 1,
                                }"
                            />
                            <div v-if="diagram.tujuan.length > 1" class="line-horizontal line-horizontal--tujuan" :style="tujuanBusStyle(diagram)" />
                            <div class="tujuan-row" :style="{ width: `${tujuanRowWidth(diagram)}px` }">
                                <div
                                    v-for="(tujuan, tujuanIndex) in diagram.tujuan"
                                    :key="tujuan.id"
                                    class="tujuan-block"
                                    :style="{ width: `${tujuanBlockWidth(tujuan)}px` }"
                                >
                                    <div v-if="diagram.tujuan.length > 1" class="tujuan-column-stem" />
                                    <div class="tujuan-main-row">
                                        <div class="diagram-box diagram-box--tujuan" :title="tujuan.label">
                                            <div class="diagram-code">Tujuan {{ tujuanIndex + 1 }}</div>
                                            <p>{{ compactLabel(tujuan.label, 96) }}</p>
                                        </div>

                                        <div v-if="tujuan.indicators.length" class="indicator-side">
                                            <div class="line-horizontal line-horizontal--indicator-side" />
                                            <div class="indicator-stack" :class="{ 'indicator-stack--multi': tujuan.indicators.length > 1 }">
                                                <div v-if="tujuan.indicators.length > 1" class="line-vertical indicator-stack-spine" />
                                                <div
                                                    v-for="(indikator, indikatorIndex) in tujuan.indicators"
                                                    :key="indikator.id"
                                                    class="indicator-stack-row"
                                                >
                                                    <div v-if="tujuan.indicators.length > 1" class="line-horizontal indicator-stack-stem" />
                                                    <div class="diagram-box diagram-box--indicator" :title="indikator.label">
                                                        <div class="diagram-code">Indikator Tujuan {{ indikatorIndex + 1 }}</div>
                                                        <p>{{ compactLabel(indikator.label, 72) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="tujuan.sasaran.length" class="sasaran-layer">
                                        <div
                                            class="line-vertical line-vertical--to-sasaran"
                                            :class="{
                                                'line-vertical--to-sasaran-bus': tujuan.sasaran.length > 1,
                                                'line-vertical--arrow': tujuan.sasaran.length === 1,
                                            }"
                                        />
                                        <div
                                            v-if="tujuan.sasaran.length > 1"
                                            class="line-horizontal line-horizontal--sasaran"
                                            :style="sasaranBusStyle(tujuan)"
                                        />
                                        <div class="sasaran-row" :style="{ width: `${sasaranRowWidth(tujuan)}px` }">
                                            <div
                                                v-for="(sasaran, sasaranIndex) in tujuan.sasaran"
                                                :key="sasaran.id"
                                                class="sasaran-block"
                                                :style="{ width: `${sasaranBlockWidth(sasaran)}px` }"
                                            >
                                                <div class="sasaran-column-stem" />
                                                <div class="diagram-box diagram-box--sasaran" :title="sasaran.label">
                                                    <div class="diagram-code">Sasaran {{ sasaranIndex + 1 }}</div>
                                                    <p>{{ compactLabel(sasaran.label, 92) }}</p>
                                                </div>

                                                <div v-if="sasaran.indicators.length" class="sasaran-indicator-layer">
                                                    <div
                                                        class="line-vertical line-vertical--to-indicator-sasaran"
                                                        :class="{
                                                            'line-vertical--to-indicator-sasaran-bus': sasaran.indicators.length > 1,
                                                            'line-vertical--arrow': sasaran.indicators.length === 1,
                                                        }"
                                                    />
                                                    <div
                                                        v-if="sasaran.indicators.length > 1"
                                                        class="line-horizontal line-horizontal--indicator-sasaran"
                                                        :style="indicatorBusStyle()"
                                                    />
                                                    <div
                                                        class="sasaran-indicator-row"
                                                        :style="{ width: `${indicatorRowWidth(sasaran.indicators.length)}px` }"
                                                    >
                                                        <div
                                                            v-for="(indikator, indikatorIndex) in sasaran.indicators"
                                                            :key="indikator.id"
                                                            class="indicator-column"
                                                            :style="{ width: `${indicatorColumnWidth}px` }"
                                                        >
                                                            <div class="indicator-column-stem" />
                                                            <div class="diagram-box diagram-box--indicator-small" :title="indikator.label">
                                                                <div class="diagram-code">Indikator Sasaran {{ indikatorIndex + 1 }}</div>
                                                                <p>{{ compactLabel(indikator.label, 66) }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.diagram-scroll {
    overflow-x: auto;
    overflow-y: visible;
    max-width: 100%;
    padding-bottom: 10px;
}

.diagram-canvas {
    position: relative;
    min-width: 100%;
    overflow: visible;
    padding: 26px 54px 46px;
    background-color: #fff;
    background-image:
        linear-gradient(rgba(15, 23, 42, 0.055) 1px, transparent 1px), linear-gradient(90deg, rgba(15, 23, 42, 0.055) 1px, transparent 1px),
        linear-gradient(rgba(15, 23, 42, 0.025) 1px, transparent 1px), linear-gradient(90deg, rgba(15, 23, 42, 0.025) 1px, transparent 1px);
    background-size:
        32px 32px,
        32px 32px,
        8px 8px,
        8px 8px;
}

.diagram-level,
.misi-network,
.tujuan-layer,
.sasaran-layer,
.sasaran-indicator-layer {
    position: relative;
    display: flex;
    justify-content: center;
}

.diagram-box {
    position: relative;
    z-index: 2;
    display: flex;
    min-height: 46px;
    min-width: 118px;
    max-width: 190px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #0f172a;
    background: #fff;
    padding: 7px 10px;
    text-align: center;
    color: #020617;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.12);
}

.diagram-box p {
    margin-top: 3px;
    max-height: 44px;
    overflow: hidden;
    font-size: 10.75px;
    font-weight: 600;
    line-height: 1.25;
}

.diagram-code {
    font-size: 10.75px;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}

.diagram-box--vision {
    min-width: 210px;
    max-width: 250px;
}

.diagram-box--misi {
    min-width: 160px;
    max-width: 160px;
}

.diagram-box--tujuan {
    min-width: 190px;
    max-width: 190px;
}

.diagram-box--sasaran {
    min-width: 176px;
    max-width: 188px;
}

.diagram-box--indicator,
.diagram-box--indicator-small {
    min-height: 42px;
    min-width: 142px;
    max-width: 150px;
}

.diagram-box--indicator p,
.diagram-box--indicator-small p {
    font-size: 10.5px;
}

.line-vertical,
.line-horizontal,
.misi-column::before,
.misi-column::after,
.tujuan-column-stem,
.sasaran-column-stem,
.indicator-column-stem,
.indicator-stack-spine,
.indicator-stack-stem {
    position: absolute;
    z-index: 1;
    background: #0f172a;
}

.line-vertical {
    width: 1.5px;
}

.line-horizontal {
    height: 1.5px;
}

.misi-column::before,
.misi-column::after,
.tujuan-column-stem::after,
.sasaran-column-stem::after,
.indicator-column-stem::after {
    content: '';
}

.line-vertical--arrow::after,
.tujuan-column-stem::after,
.sasaran-column-stem::after,
.indicator-column-stem::after {
    position: absolute;
    left: 50%;
    bottom: -1px;
    height: 0;
    width: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 6px solid #0f172a;
    transform: translateX(-50%);
}

.misi-network {
    margin-top: 0;
    padding-top: 34px;
}

.line-vertical--from-vision {
    top: 0;
    left: 50%;
    height: 34px;
}

.misi-row-wrap {
    position: relative;
    display: flex;
    justify-content: center;
    padding: 18px 0 24px;
}

.misi-row {
    display: flex;
    justify-content: center;
    gap: 24px;
}

.misi-column {
    position: relative;
    display: flex;
    width: 170px;
    justify-content: center;
}

.misi-column::before {
    top: -18px;
    left: 50%;
    height: 18px;
    width: 1.5px;
}

.misi-column::after {
    bottom: -24px;
    left: 50%;
    height: 24px;
    width: 1.5px;
}

.line-horizontal--misi-top {
    top: 0;
}

.line-horizontal--misi-bottom {
    bottom: 0;
}

.tujuan-layer {
    padding-top: 38px;
}

.tujuan-layer--without-misi {
    margin-top: 0;
}

.line-vertical--to-tujuan {
    top: 0;
    left: 50%;
    height: 76px;
}

.line-vertical--to-tujuan-bus {
    height: 20px;
}

.line-horizontal--tujuan {
    top: 20px;
}

.tujuan-row {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 56px;
    padding-top: 38px;
}

.tujuan-block {
    position: relative;
    display: flex;
    min-width: 360px;
    flex-direction: column;
    align-items: center;
    overflow: visible;
}

.tujuan-column-stem {
    top: -18px;
    left: 50%;
    height: 18px;
    width: 1.5px;
}

.tujuan-main-row {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 48px;
    width: 100%;
}

.indicator-side {
    position: relative;
    display: flex;
    align-items: center;
    align-self: center;
}

.line-horizontal--indicator-side {
    left: -48px;
    top: 50%;
    width: 48px;
}

.indicator-stack {
    position: relative;
    display: grid;
    gap: 8px;
}

.indicator-stack--multi {
    padding-left: 22px;
}

.indicator-stack-row {
    position: relative;
    display: flex;
    justify-content: flex-start;
}

.indicator-stack-spine {
    top: 22px;
    bottom: 22px;
    left: 0;
    width: 1.5px;
}

.indicator-stack-stem {
    top: 50%;
    left: 0;
    width: 22px;
    height: 1.5px;
}

.sasaran-layer {
    margin-top: 12px;
    padding-top: 58px;
    width: 100%;
}

.line-vertical--to-sasaran {
    top: 0;
    left: 50%;
    height: 58px;
}

.line-vertical--to-sasaran-bus {
    height: 38px;
}

.line-horizontal--sasaran {
    top: 38px;
}

.sasaran-row {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 34px;
}

.sasaran-block {
    position: relative;
    display: flex;
    min-width: 188px;
    flex-direction: column;
    align-items: center;
    overflow: visible;
}

.sasaran-column-stem {
    top: -20px;
    left: 50%;
    height: 20px;
    width: 1.5px;
}

.sasaran-indicator-layer {
    margin-top: 10px;
    padding-top: 42px;
    width: 100%;
}

.line-vertical--to-indicator-sasaran {
    top: 0;
    left: 50%;
    height: 42px;
}

.line-vertical--to-indicator-sasaran-bus {
    height: 26px;
}

.line-horizontal--indicator-sasaran {
    top: 26px;
}

.sasaran-indicator-row {
    display: flex;
    justify-content: center;
    gap: 16px;
}

.indicator-column {
    position: relative;
    display: flex;
    min-width: 150px;
    justify-content: center;
}

.indicator-column-stem {
    top: -16px;
    left: 50%;
    height: 16px;
    width: 1.5px;
}

@media (prefers-reduced-motion: no-preference) {
    .diagram-box {
        transition:
            transform 180ms ease,
            box-shadow 180ms ease;
    }

    .diagram-box:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.12);
    }
}
</style>
