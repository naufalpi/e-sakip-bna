<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import CalendarDays from 'lucide-vue-next/dist/esm/icons/calendar-days.js';
import ChevronRight from 'lucide-vue-next/dist/esm/icons/chevron-right.js';
import CheckCircle2 from 'lucide-vue-next/dist/esm/icons/circle-check.js';
import Download from 'lucide-vue-next/dist/esm/icons/download.js';
import Eye from 'lucide-vue-next/dist/esm/icons/eye.js';
import FileText from 'lucide-vue-next/dist/esm/icons/file-text.js';
import Gauge from 'lucide-vue-next/dist/esm/icons/gauge.js';
import LogIn from 'lucide-vue-next/dist/esm/icons/log-in.js';
import Menu from 'lucide-vue-next/dist/esm/icons/menu.js';
import Network from 'lucide-vue-next/dist/esm/icons/network.js';
import Search from 'lucide-vue-next/dist/esm/icons/search.js';
import ShieldCheck from 'lucide-vue-next/dist/esm/icons/shield-check.js';
import X from 'lucide-vue-next/dist/esm/icons/x.js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type PublicDocument = {
    id: number;
    judul: string;
    filename: string;
    mime_type?: string | null;
    view_url: string;
    download_url: string;
};

type PublicCell = {
    kind: 'status' | 'metric' | 'file' | 'score';
    state: 'available' | 'data' | 'missing' | 'warning' | 'excellent';
    label: string;
    description?: string | null;
    dokumen?: PublicDocument | null;
};

type PublicRow = {
    no: number;
    opd: {
        id: number;
        kode?: string | null;
        nama: string;
        singkatan?: string | null;
        label: string;
    };
    is_ready: boolean;
    cells: Record<string, PublicCell>;
};

type Column = {
    key: string;
    label: string;
};

type SectionId = 'perencanaan' | 'pengukuran' | 'pelaporan' | 'evaluasi';

type SectionUrls = Record<'home' | SectionId, string>;

const props = defineProps<{
    active_section: SectionId | null;
    section_urls: SectionUrls;
    available_years: number[];
    filters: {
        tahun: number;
    };
    meta: {
        tahun: number;
        periode_label: string;
        generated_at: string;
    };
    stats: {
        opd_count: number;
        planning_ready_count: number;
        measurement_ready_count: number;
        report_ready_count: number;
        evaluation_count: number;
        public_document_count: number;
        average_sakip?: number | null;
    };
    tables: {
        perencanaan: PublicRow[];
        pengukuran: PublicRow[];
        pelaporan: PublicRow[];
        evaluasi: PublicRow[];
    };
}>();

const page = usePage<SharedData>();
const isMobileMenuOpen = ref(false);
const searchQuery = ref('');
const heroMeshCanvas = ref<HTMLCanvasElement | null>(null);

const user = computed(() => page.props.auth.user);
const entryUrl = computed(() => (user.value ? route('dashboard') : route('login')));
const entryLabel = computed(() => (user.value ? 'Dashboard' : 'Login'));
const currentYear = computed(() => new Date().getFullYear());

type MeshPoint = {
    index: number;
    x: number;
    y: number;
    z: number;
    phase: number;
    speed: number;
    ampX: number;
    ampY: number;
    ampZ: number;
};

type ProjectedMeshPoint = MeshPoint & {
    sx: number;
    sy: number;
    depth: number;
    scale: number;
    alpha: number;
    visible: boolean;
    pulse: number;
};

type MeshEdge = {
    from: number;
    to: number;
    strength: number;
};

type MeshTriangle = {
    a: number;
    b: number;
    c: number;
    strength: number;
};

type MeshRotation = {
    cosX: number;
    sinX: number;
    cosY: number;
    sinY: number;
    cosZ: number;
    sinZ: number;
};

let stopHeroMeshAnimation: (() => void) | null = null;
let heroMeshSetupTimer: number | null = null;

function createSeededRandom(seed = 42) {
    let value = seed;

    return () => {
        value = (value * 1664525 + 1013904223) % 4294967296;

        return value / 4294967296;
    };
}

function setupHeroMeshCanvas(): (() => void) | null {
    const canvas = heroMeshCanvas.value;
    const context = canvas?.getContext('2d', {
        alpha: true,
        desynchronized: true,
    } as CanvasRenderingContext2DSettings);

    if (!canvas || !context || typeof window === 'undefined') {
        return null;
    }

    const random = createSeededRandom(2036);
    const reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const points: MeshPoint[] = [];
    const projectedPoints: ProjectedMeshPoint[] = [];
    let animationFrame = 0;
    let resizeFrame = 0;
    let lastRenderTime = 0;
    let width = 0;
    let height = 0;
    let deviceRatio = 1;
    let field = 0;
    let originX = 0;
    let originY = 0;
    let maxLineDistance = 0;
    let isDocumentVisible = document.visibilityState !== 'hidden';
    let isHeroVisible = true;
    let intersectionObserver: IntersectionObserver | null = null;
    let startTimer = 0;
    const isCompactViewport = window.innerWidth < 720;
    const isMediumViewport = window.innerWidth >= 720 && window.innerWidth < 1180;
    const targetFrameInterval = 1000 / (isCompactViewport ? 24 : 30);

    const addPoint = (x: number, y: number, z: number) => {
        const index = points.length;

        points.push({
            index,
            x,
            y,
            z,
            phase: random() * Math.PI * 2,
            speed: 0.22 + random() * 0.32,
            ampX: 0.012 + random() * 0.03,
            ampY: 0.01 + random() * 0.026,
            ampZ: 0.035 + random() * 0.08,
        });
    };

    const baseNodes = [
        [-1.22, -0.66, -0.28],
        [-1.08, -0.45, 0.22],
        [-0.94, -0.72, -0.04],
        [-0.82, -0.26, 0.36],
        [-0.72, -0.58, -0.34],
        [-0.58, -0.18, 0.1],
        [-0.42, -0.48, 0.42],
        [-0.26, -0.72, -0.08],
        [-0.18, -0.28, 0.32],
        [-0.02, -0.54, -0.24],
        [0.12, -0.16, 0.22],
        [0.28, -0.38, -0.34],
        [0.46, -0.08, 0.36],
        [0.64, -0.32, -0.1],
        [0.86, -0.02, 0.28],
        [1.06, -0.22, -0.22],
        [1.22, 0.02, 0.16],
        [-1.14, 0.04, 0.32],
        [-0.96, 0.28, -0.18],
        [-0.78, 0.08, 0.06],
        [-0.58, 0.36, 0.38],
        [-0.38, 0.1, -0.28],
        [-0.18, 0.34, 0.14],
        [0.04, 0.08, 0.42],
        [0.22, 0.38, -0.2],
        [0.44, 0.16, 0.08],
        [0.66, 0.44, 0.36],
        [0.86, 0.18, -0.24],
        [1.08, 0.42, 0.18],
        [1.28, 0.22, -0.06],
        [-1.2, 0.58, -0.24],
        [-0.96, 0.72, 0.28],
        [-0.66, 0.56, -0.02],
        [-0.36, 0.76, 0.22],
        [-0.08, 0.6, -0.3],
        [0.22, 0.78, 0.3],
        [0.54, 0.62, -0.08],
        [0.86, 0.76, 0.2],
        [1.18, 0.58, -0.26],
    ] as const;

    baseNodes.forEach(([x, y, z]) => addPoint(x, y, z));

    const extraNodeCount = isCompactViewport ? 12 : isMediumViewport ? 18 : 22;

    for (let index = 0; index < extraNodeCount; index += 1) {
        const leftBias = random() ** 1.8;
        const x = -1.24 + leftBias * 2.42;
        const y = -0.78 + random() * 1.58;
        const z = -0.38 + random() * 0.84;

        addPoint(x, y, z);
    }

    points.forEach((point) => {
        projectedPoints.push({
            ...point,
            sx: 0,
            sy: 0,
            depth: 0,
            scale: 1,
            alpha: 0,
            visible: true,
            pulse: 1,
        });
    });

    const squaredDistance3d = (a: MeshPoint, b: MeshPoint) => {
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        const dz = (a.z - b.z) * 0.7;

        return dx * dx + dy * dy + dz * dz;
    };

    const buildMeshGraph = () => {
        const edgeMap = new Map<string, MeshEdge>();
        const triangleMap = new Map<string, MeshTriangle>();
        const maxGraphDistance = 0.58;
        const maxGraphDistanceSq = maxGraphDistance * maxGraphDistance;

        for (let index = 0; index < points.length; index += 1) {
            const point = points[index];
            const nearest: Array<{ index: number; distanceSq: number }> = [];

            for (let candidateIndex = 0; candidateIndex < points.length; candidateIndex += 1) {
                if (candidateIndex === index) {
                    continue;
                }

                const distanceSq = squaredDistance3d(point, points[candidateIndex]);

                if (distanceSq <= maxGraphDistanceSq) {
                    nearest.push({ index: candidateIndex, distanceSq });
                }
            }

            nearest.sort((a, b) => a.distanceSq - b.distanceSq);

            const nearestEdges = nearest.slice(0, 3);

            nearestEdges.forEach((neighbor) => {
                const from = Math.min(index, neighbor.index);
                const to = Math.max(index, neighbor.index);
                const key = `${from}-${to}`;

                if (!edgeMap.has(key)) {
                    edgeMap.set(key, {
                        from,
                        to,
                        strength: Math.max(0.22, 1 - Math.sqrt(neighbor.distanceSq) / maxGraphDistance),
                    });
                }
            });

            if (nearest.length >= 2) {
                const triangleIndexes = [index, nearest[0].index, nearest[1].index].sort((a, b) => a - b);
                const key = triangleIndexes.join('-');

                if (!triangleMap.has(key)) {
                    const averageDistance = Math.sqrt((nearest[0].distanceSq + nearest[1].distanceSq) / 2);

                    triangleMap.set(key, {
                        a: triangleIndexes[0],
                        b: triangleIndexes[1],
                        c: triangleIndexes[2],
                        strength: Math.max(0.2, 1 - averageDistance / maxGraphDistance),
                    });
                }
            }
        }

        const edgeLimit = isCompactViewport ? 62 : isMediumViewport ? 84 : 104;
        const triangleLimit = isCompactViewport ? 18 : isMediumViewport ? 26 : 34;

        return {
            edges: Array.from(edgeMap.values())
                .sort((a, b) => b.strength - a.strength)
                .slice(0, edgeLimit),
            triangles: Array.from(triangleMap.values())
                .sort((a, b) => b.strength - a.strength)
                .slice(0, triangleLimit),
        };
    };

    const { edges, triangles } = buildMeshGraph();

    const resize = () => {
        const rect = canvas.getBoundingClientRect();
        width = Math.max(1, rect.width);
        height = Math.max(1, rect.height);
        deviceRatio = 1;
        canvas.width = Math.round(width * deviceRatio);
        canvas.height = Math.round(height * deviceRatio);
        context.setTransform(deviceRatio, 0, 0, deviceRatio, 0, 0);
        field = Math.min(width, height) * (width < 720 ? 0.72 : 0.62);
        originX = width * (width < 720 ? 0.5 : 0.58);
        originY = height * 0.48;
        maxLineDistance = Math.min(width, height) * (width < 720 ? 0.3 : 0.25);
    };

    const createRotation = (time: number): MeshRotation => {
        const rotationX = Math.sin(time * 0.18) * 0.18;
        const rotationY = Math.sin(time * 0.14) * 0.26 + time * 0.045;
        const rotationZ = Math.sin(time * 0.1) * 0.09;

        return {
            cosX: Math.cos(rotationX),
            sinX: Math.sin(rotationX),
            cosY: Math.cos(rotationY),
            sinY: Math.sin(rotationY),
            cosZ: Math.cos(rotationZ),
            sinZ: Math.sin(rotationZ),
        };
    };

    const updateProjectedPoints = (time: number) => {
        const rotation = createRotation(time);

        for (let index = 0; index < points.length; index += 1) {
            const point = points[index];
            const target = projectedPoints[index];
            const wave = time * point.speed + point.phase;
            const localX = point.x + Math.sin(wave) * point.ampX + Math.cos(time * 0.23 + point.phase) * point.ampX * 0.45;
            const localY = point.y + Math.cos(time * (point.speed + 0.16) + point.phase * 1.4) * point.ampY;
            const localZ = point.z + Math.sin(time * (point.speed * 0.7) + point.phase * 0.8) * point.ampZ;
            const y1 = localY * rotation.cosX - localZ * rotation.sinX;
            const z1 = localY * rotation.sinX + localZ * rotation.cosX;
            const x2 = localX * rotation.cosY + z1 * rotation.sinY;
            const z2 = -localX * rotation.sinY + z1 * rotation.cosY;
            const x3 = x2 * rotation.cosZ - y1 * rotation.sinZ;
            const y3 = x2 * rotation.sinZ + y1 * rotation.cosZ;
            const perspective = 1 / (1 + (z2 + 1.08) * 0.22);
            const sx = originX + x3 * field * perspective;
            const sy = originY + y3 * field * perspective;
            const depth = Math.max(0, Math.min(1, (z2 + 1.1) / 2.2));

            target.sx = sx;
            target.sy = sy;
            target.depth = depth;
            target.scale = perspective;
            target.alpha = 0.3 + depth * 0.54;
            target.visible = sx > -90 && sx < width + 90 && sy > -90 && sy < height + 90;
            target.pulse = 0.8 + Math.sin(time * 1.9 + point.phase) * 0.2;
        }
    };

    const draw = (time: number) => {
        context.clearRect(0, 0, width, height);
        updateProjectedPoints(time);

        context.save();
        context.globalCompositeOperation = 'source-over';
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.shadowBlur = 0;

        context.beginPath();
        for (let index = 0; index < triangles.length; index += 1) {
            const triangle = triangles[index];
            const a = projectedPoints[triangle.a];
            const b = projectedPoints[triangle.b];
            const c = projectedPoints[triangle.c];

            if (!a.visible || !b.visible || !c.visible) {
                continue;
            }

            const area = Math.abs((b.sx - a.sx) * (c.sy - a.sy) - (c.sx - a.sx) * (b.sy - a.sy)) / 2;

            if (area < 120 || area > width * height * 0.075) {
                continue;
            }

            context.moveTo(a.sx, a.sy);
            context.lineTo(b.sx, b.sy);
            context.lineTo(c.sx, c.sy);
            context.closePath();
        }
        context.fillStyle = 'rgba(103, 232, 249, 0.032)';
        context.fill();

        context.beginPath();
        for (let index = 0; index < edges.length; index += 1) {
            const edge = edges[index];
            const a = projectedPoints[edge.from];
            const b = projectedPoints[edge.to];

            if (!a.visible || !b.visible) {
                continue;
            }

            const dx = a.sx - b.sx;
            const dy = a.sy - b.sy;
            const distanceSq = dx * dx + dy * dy;
            const maxDistanceSq = maxLineDistance * maxLineDistance;

            if (distanceSq > maxDistanceSq) {
                continue;
            }

            context.moveTo(a.sx, a.sy);
            context.lineTo(b.sx, b.sy);
        }
        context.strokeStyle = 'rgba(125, 211, 252, 0.22)';
        context.lineWidth = width < 720 ? 0.58 : 0.68;
        context.stroke();

        context.beginPath();
        for (let index = 0; index < projectedPoints.length; index += 1) {
            const point = projectedPoints[index];

            if (!point.visible) {
                continue;
            }

            const radius = (1.35 + point.depth * 2.1) * point.pulse;

            if (index % 4 === 0 || point.depth > 0.68) {
                const haloRadius = radius * 3.6;

                context.moveTo(point.sx + haloRadius, point.sy);
                context.arc(point.sx, point.sy, haloRadius, 0, Math.PI * 2);
            }
        }
        context.fillStyle = 'rgba(56, 189, 248, 0.1)';
        context.fill();

        context.beginPath();
        for (let index = 0; index < projectedPoints.length; index += 1) {
            const point = projectedPoints[index];

            if (!point.visible) {
                continue;
            }

            const radius = (1.35 + point.depth * 2.1) * point.pulse;

            context.moveTo(point.sx + radius, point.sy);
            context.arc(point.sx, point.sy, radius, 0, Math.PI * 2);
        }
        context.fillStyle = 'rgba(186, 230, 253, 0.78)';
        context.fill();

        context.restore();
    };

    const canAnimate = () => !reduceMotionQuery.matches && isDocumentVisible && isHeroVisible;

    const animate = (timestamp: number) => {
        if (!canAnimate()) {
            animationFrame = 0;
            return;
        }

        if (!lastRenderTime || timestamp - lastRenderTime >= targetFrameInterval - 1) {
            draw(timestamp / 1000);
            lastRenderTime = timestamp;
        }

        animationFrame = window.requestAnimationFrame(animate);
    };

    const start = () => {
        if (reduceMotionQuery.matches && isDocumentVisible && isHeroVisible) {
            draw(performance.now() / 1000);
            return;
        }

        if (!isDocumentVisible || !isHeroVisible) {
            return;
        }

        if (animationFrame) {
            return;
        }

        lastRenderTime = 0;
        animationFrame = window.requestAnimationFrame(animate);
    };

    const stop = () => {
        if (animationFrame) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = 0;
        }
    };

    const handleVisibilityChange = () => {
        isDocumentVisible = document.visibilityState !== 'hidden';

        if (isDocumentVisible) {
            start();
            return;
        }

        stop();
    };

    const handleMotionPreferenceChange = () => {
        stop();
        start();
    };

    const handleResize = () => {
        if (resizeFrame) {
            return;
        }

        resizeFrame = window.requestAnimationFrame(() => {
            resizeFrame = 0;
            resize();

            if (isDocumentVisible && isHeroVisible) {
                draw(performance.now() / 1000);
            }
        });
    };

    resize();
    draw(performance.now() / 1000);
    startTimer = window.setTimeout(start, 450);

    if ('IntersectionObserver' in window) {
        intersectionObserver = new IntersectionObserver(
            ([entry]) => {
                isHeroVisible = entry.isIntersecting;

                if (isHeroVisible) {
                    start();
                    return;
                }

                stop();
            },
            {
                rootMargin: '96px 0px',
                threshold: 0.01,
            },
        );
        intersectionObserver.observe(canvas);
    }

    window.addEventListener('resize', handleResize, { passive: true });
    document.addEventListener('visibilitychange', handleVisibilityChange);
    reduceMotionQuery.addEventListener('change', handleMotionPreferenceChange);

    return () => {
        stop();
        if (startTimer) {
            window.clearTimeout(startTimer);
            startTimer = 0;
        }
        if (resizeFrame) {
            window.cancelAnimationFrame(resizeFrame);
            resizeFrame = 0;
        }
        intersectionObserver?.disconnect();
        window.removeEventListener('resize', handleResize);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        reduceMotionQuery.removeEventListener('change', handleMotionPreferenceChange);
    };
}

const navItems = computed(() => [
    { id: 'beranda', label: 'Beranda', href: props.section_urls.home, isActive: props.active_section === null },
    { id: 'perencanaan', label: 'Perencanaan', href: props.section_urls.perencanaan, isActive: props.active_section === 'perencanaan' },
    { id: 'pengukuran', label: 'Pengukuran', href: props.section_urls.pengukuran, isActive: props.active_section === 'pengukuran' },
    { id: 'pelaporan', label: 'Pelaporan', href: props.section_urls.pelaporan, isActive: props.active_section === 'pelaporan' },
    { id: 'evaluasi', label: 'Evaluasi', href: props.section_urls.evaluasi, isActive: props.active_section === 'evaluasi' },
]);

const perencanaanColumns: Column[] = [
    { key: 'pohon_kinerja', label: 'Pohon Kinerja' },
    { key: 'cascading', label: 'Cascading' },
    { key: 'iku', label: 'IKU' },
    { key: 'renstra', label: 'Renstra' },
    { key: 'renja_rkt', label: 'Renja/RKT' },
    { key: 'rencana_aksi', label: 'Rencana Aksi' },
    { key: 'pk', label: 'PK' },
];

const pengukuranColumns: Column[] = [
    { key: 'tujuan', label: 'Tujuan' },
    { key: 'sasaran_strategis', label: 'Sasaran Strategis' },
    { key: 'program', label: 'Program' },
    { key: 'kegiatan', label: 'Kegiatan' },
    { key: 'sub_kegiatan', label: 'Sub Kegiatan' },
];

const pelaporanColumns = computed<Column[]>(() => [
    { key: 'lkjip', label: `LKJIP (tahun ${props.meta.tahun})` },
    { key: 'tw1', label: 'Laporan TW I' },
    { key: 'tw2', label: 'Laporan TW II' },
    { key: 'tw3', label: 'Laporan TW III' },
    { key: 'tw4', label: 'Laporan TW IV' },
]);

const evaluasiColumns: Column[] = [
    { key: 'nilai_sakip', label: 'Nilai SAKIP' },
    { key: 'lhe_internal', label: 'LHE Internal' },
    { key: 'tindak_lanjut_lhe', label: 'Tindak Lanjut LHE' },
];

const tableSections = computed(() => [
    {
        id: 'perencanaan',
        eyebrow: 'Perencanaan',
        title: 'Perencanaan Kinerja',
        summary: `${props.stats.planning_ready_count} dari ${props.stats.opd_count} OPD memiliki rangkaian perencanaan utama.`,
        icon: Network,
        columns: perencanaanColumns,
        rows: props.tables.perencanaan,
    },
    {
        id: 'pengukuran',
        eyebrow: 'Pengukuran',
        title: 'Pengukuran Kinerja',
        summary: `${props.stats.measurement_ready_count} OPD sudah memiliki struktur tujuan sampai sub kegiatan.`,
        icon: Gauge,
        columns: pengukuranColumns,
        rows: props.tables.pengukuran,
    },
    {
        id: 'pelaporan',
        eyebrow: 'Pelaporan',
        title: 'Pelaporan Kinerja',
        summary: `${props.stats.report_ready_count} OPD sudah memiliki data LKJIP pada periode berjalan.`,
        icon: FileText,
        columns: pelaporanColumns.value,
        rows: props.tables.pelaporan,
    },
    {
        id: 'evaluasi',
        eyebrow: 'Evaluasi',
        title: 'Evaluasi Kinerja',
        summary: `${props.stats.evaluation_count} OPD sudah memiliki nilai evaluasi SAKIP.`,
        icon: ShieldCheck,
        columns: evaluasiColumns,
        rows: props.tables.evaluasi,
    },
]);

const activeSection = computed(() => props.active_section);
const currentSection = computed(() => tableSections.value.find((section) => section.id === activeSection.value) ?? null);
const visibleTableSections = computed(() => (currentSection.value ? [currentSection.value] : []));
const homeModules = computed(() =>
    tableSections.value.map((section) => ({
        ...section,
        href: props.section_urls[section.id as SectionId],
        completeness: progressWidth(sectionReadyCount(section.id), props.stats.opd_count),
    })),
);

const filteredTableSections = computed(() =>
    visibleTableSections.value.map((section) => ({
        ...section,
        rows: filterRows(section.rows),
    })),
);
const currentRowsCount = computed(() => currentSection.value?.rows.length ?? 0);
const filteredRowsCount = computed(() => filteredTableSections.value[0]?.rows.length ?? 0);
const selectedYearLabel = computed(() => `Tahun ${props.filters.tahun}`);

function cellClass(cell?: PublicCell): string {
    return {
        available: 'border-blue-200 bg-blue-50 text-[#00336C]',
        excellent: 'border-sky-200 bg-sky-50 text-sky-800',
        data: 'border-cyan-200 bg-cyan-50 text-cyan-800',
        warning: 'border-indigo-200 bg-indigo-50 text-indigo-800',
        missing: 'border-slate-200 bg-slate-50 text-slate-500',
    }[cell?.state ?? 'missing'];
}

function dotClass(cell?: PublicCell): string {
    return {
        available: 'bg-blue-600',
        excellent: 'bg-sky-500',
        data: 'bg-cyan-500',
        warning: 'bg-indigo-500',
        missing: 'bg-slate-300',
    }[cell?.state ?? 'missing'];
}

function cycleCardClass(id: string): string {
    return (
        {
            perencanaan: 'cycle-card-planning',
            pengukuran: 'cycle-card-measurement',
            pelaporan: 'cycle-card-reporting',
            evaluasi: 'cycle-card-evaluation',
        }[id] ?? ''
    );
}

function sectionReadyCount(id: string): number {
    return (
        {
            perencanaan: props.stats.planning_ready_count,
            pengukuran: props.stats.measurement_ready_count,
            pelaporan: props.stats.report_ready_count,
            evaluasi: props.stats.evaluation_count,
        }[id] ?? 0
    );
}

function filterRows(rows: PublicRow[]): PublicRow[] {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return rows;
    }

    return rows.filter((row) => rowSearchText(row).includes(query));
}

function rowSearchText(row: PublicRow): string {
    const cellText = Object.values(row.cells)
        .flatMap((cell) => [cell.label, cell.description, cell.dokumen?.judul, cell.dokumen?.filename])
        .filter(Boolean)
        .join(' ');

    return [row.opd.nama, row.opd.singkatan, row.opd.kode, row.opd.label, cellText].filter(Boolean).join(' ').toLowerCase();
}

function changeYear(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const tahun = Number(target.value);
    const destination = currentSection.value ? route('public.section', { section: currentSection.value.id }) : route('home');

    router.get(
        destination,
        { tahun },
        {
            preserveScroll: false,
            preserveState: false,
        },
    );
}

function emptyTableMessage(sectionRows: PublicRow[]): string {
    if (searchQuery.value.trim() && sectionRows.length === 0) {
        return 'Tidak ada data yang cocok dengan pencarian.';
    }

    return 'Data OPD belum tersedia.';
}

function progressWidth(count: number, total: number): string {
    if (total <= 0) {
        return '0%';
    }

    return `${Math.min(100, Math.max(0, Math.round((count / total) * 100)))}%`;
}

function closeMobileMenu(): void {
    isMobileMenuOpen.value = false;
}

onMounted(() => {
    heroMeshSetupTimer = window.setTimeout(() => {
        heroMeshSetupTimer = null;
        stopHeroMeshAnimation = setupHeroMeshCanvas();
    }, 250);
});

onBeforeUnmount(() => {
    if (heroMeshSetupTimer) {
        window.clearTimeout(heroMeshSetupTimer);
        heroMeshSetupTimer = null;
    }

    stopHeroMeshAnimation?.();
    stopHeroMeshAnimation = null;
});
</script>

<template>
    <Head :title="currentSection ? currentSection.title : 'Beranda Publik'" />

    <div class="public-site min-h-dvh bg-white text-slate-900">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-blue-100 bg-white shadow-sm shadow-blue-950/5">
            <div class="bg-[#00336C]">
                <div class="mx-auto flex min-h-9 max-w-7xl items-center gap-2 px-4 text-xs font-medium text-blue-50 sm:px-6 lg:px-8">
                    <ShieldCheck class="h-3.5 w-3.5 text-blue-200" />
                    Portal publik akuntabilitas kinerja Pemerintah Kabupaten Banjarnegara
                </div>
            </div>
            <div class="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link
                    :href="props.section_urls.home"
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

        <main>
            <section v-if="!currentSection" id="beranda" class="hero-section relative isolate overflow-hidden pt-28">
                <div class="hero-motion" aria-hidden="true">
                    <div class="hero-aurora hero-aurora-a"></div>
                    <div class="hero-aurora hero-aurora-b"></div>
                    <div class="hero-aurora hero-aurora-c"></div>
                    <div class="hero-scanline"></div>
                    <div class="hero-orbit hero-orbit-a"></div>
                    <div class="hero-orbit hero-orbit-b"></div>
                    <div class="hero-network-texture"></div>
                    <canvas ref="heroMeshCanvas" class="hero-mesh-canvas"></canvas>
                    <svg v-if="false" class="hero-network" viewBox="0 0 1440 620" preserveAspectRatio="xMidYMid slice" focusable="false">
                        <defs>
                            <linearGradient id="heroRouteGradient" x1="0" x2="1" y1="0" y2="1">
                                <stop offset="0%" stop-color="#7dd3fc" stop-opacity="0.15" />
                                <stop offset="46%" stop-color="#38bdf8" stop-opacity="0.9" />
                                <stop offset="100%" stop-color="#0ea5e9" stop-opacity="0.25" />
                            </linearGradient>
                            <linearGradient id="heroPanelGradient" x1="0" x2="1" y1="0" y2="1">
                                <stop offset="0%" stop-color="#93c5fd" stop-opacity="0.08" />
                                <stop offset="100%" stop-color="#22d3ee" stop-opacity="0.16" />
                            </linearGradient>
                            <radialGradient id="heroNodeGradient" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#e0f2fe" stop-opacity="1" />
                                <stop offset="46%" stop-color="#38bdf8" stop-opacity="0.95" />
                                <stop offset="100%" stop-color="#0284c7" stop-opacity="0.2" />
                            </radialGradient>
                            <linearGradient id="heroContourGradient" x1="0" x2="1" y1="0" y2="0">
                                <stop offset="0%" stop-color="#7dd3fc" stop-opacity="0.04" />
                                <stop offset="45%" stop-color="#38bdf8" stop-opacity="0.46" />
                                <stop offset="100%" stop-color="#7dd3fc" stop-opacity="0.08" />
                            </linearGradient>
                            <filter id="heroGlow" x="-30%" y="-30%" width="160%" height="160%">
                                <feGaussianBlur stdDeviation="4" result="blur" />
                                <feMerge>
                                    <feMergeNode in="blur" />
                                    <feMergeNode in="SourceGraphic" />
                                </feMerge>
                            </filter>
                            <filter id="heroHeavyGlow" x="-70%" y="-70%" width="240%" height="240%">
                                <feGaussianBlur stdDeviation="9" result="blur" />
                                <feColorMatrix
                                    in="blur"
                                    type="matrix"
                                    values="0 0 0 0 0.06 0 0 0 0 0.52 0 0 0 0 0.95 0 0 0 0.72 0"
                                    result="blueBlur"
                                />
                                <feMerge>
                                    <feMergeNode in="blueBlur" />
                                    <feMergeNode in="SourceGraphic" />
                                </feMerge>
                            </filter>
                        </defs>

                        <g class="hero-faint-mesh">
                            <path d="M38 126 146 82 286 126 410 78 558 138 704 92 854 154 1016 78 1172 126 1328 62 1460 108" />
                            <path d="M-24 286 110 214 264 276 428 206 604 286 776 202 944 274 1108 198 1274 260 1468 176" />
                            <path d="M46 476 202 394 368 458 536 372 710 448 888 362 1070 436 1248 344 1464 418" />
                            <path d="M146 82 264 276 410 78 604 286 704 92 944 274 1016 78 1274 260 1328 62" />
                            <path d="M110 214 286 126 428 206 558 138 776 202 854 154 1108 198 1172 126 1460 108" />
                            <path
                                d="M202 394 264 276 368 458 428 206 536 372 604 286 710 448 776 202 888 362 944 274 1070 436 1108 198 1248 344 1274 260"
                            />
                            <path
                                d="M38 126 110 214 146 82 286 126 264 276 410 78 428 206 558 138 604 286 704 92 776 202 854 154 944 274 1016 78 1108 198 1172 126 1274 260 1328 62 1460 108"
                            />
                        </g>

                        <g class="hero-triangle-fills">
                            <polygon points="0,232 82,128 176,214" />
                            <polygon points="82,128 146,82 176,214" />
                            <polygon points="146,82 286,126 176,214" />
                            <polygon points="176,214 286,126 264,276" />
                            <polygon points="264,276 410,78 428,206" />
                            <polygon points="286,126 410,78 428,206" />
                            <polygon points="428,206 558,138 604,286" />
                            <polygon points="428,206 604,286 536,372" />
                            <polygon points="558,138 704,92 776,202" />
                            <polygon points="604,286 776,202 710,448" />
                            <polygon points="710,448 776,202 888,362" />
                            <polygon points="776,202 944,274 888,362" />
                            <polygon points="944,274 1108,198 1070,436" />
                            <polygon points="888,362 1070,436 1248,344" />
                            <polygon points="1108,198 1274,260 1248,344" />
                            <polygon points="1248,344 1274,260 1464,418" />
                            <polygon points="202,394 368,458 264,276" />
                            <polygon points="368,458 536,372 710,448" />
                            <polygon points="536,372 604,286 710,448" />
                        </g>

                        <g class="hero-triangle-lines">
                            <path d="M0 232 82 128 146 82 286 126 410 78 558 138 704 92 854 154 1016 78 1172 126 1328 62 1460 108" />
                            <path d="M0 232 110 214 176 214 264 276 428 206 604 286 776 202 944 274 1108 198 1274 260 1468 176" />
                            <path d="M46 476 202 394 368 458 536 372 710 448 888 362 1070 436 1248 344 1464 418" />
                            <path d="M82 128 176 214 286 126 264 276 410 78 428 206 558 138 604 286 704 92 776 202 854 154 944 274 1016 78" />
                            <path
                                d="M110 214 146 82 176 214 264 276 286 126 428 206 410 78 558 138 776 202 704 92 944 274 854 154 1108 198 1172 126 1274 260"
                            />
                            <path
                                d="M202 394 264 276 368 458 428 206 536 372 604 286 710 448 776 202 888 362 944 274 1070 436 1108 198 1248 344 1274 260"
                            />
                            <path
                                d="M0 232 176 214 46 476 264 276 202 394 428 206 368 458 604 286 536 372 776 202 710 448 944 274 888 362 1108 198 1070 436 1274 260 1248 344 1464 418"
                            />
                            <path d="M146 82 264 276 428 206 604 286 710 448 888 362 1248 344" />
                            <path d="M410 78 604 286 776 202 1070 436 1464 418" />
                        </g>

                        <g class="hero-triangle-nodes">
                            <circle cx="0" cy="232" r="3.2" />
                            <circle cx="82" cy="128" r="4.2" />
                            <circle cx="146" cy="82" r="3.6" />
                            <circle cx="176" cy="214" r="5.2" />
                            <circle cx="286" cy="126" r="3.8" />
                            <circle cx="264" cy="276" r="4.4" />
                            <circle cx="410" cy="78" r="3.4" />
                            <circle cx="428" cy="206" r="4.6" />
                            <circle cx="558" cy="138" r="3.8" />
                            <circle cx="604" cy="286" r="5" />
                            <circle cx="704" cy="92" r="3.4" />
                            <circle cx="776" cy="202" r="4.8" />
                            <circle cx="854" cy="154" r="3.4" />
                            <circle cx="944" cy="274" r="4.4" />
                            <circle cx="1016" cy="78" r="3.4" />
                            <circle cx="1108" cy="198" r="4.2" />
                            <circle cx="1172" cy="126" r="3.2" />
                            <circle cx="1274" cy="260" r="4.2" />
                            <circle cx="1328" cy="62" r="3.2" />
                            <circle cx="202" cy="394" r="3.8" />
                            <circle cx="368" cy="458" r="4.4" />
                            <circle cx="536" cy="372" r="4" />
                            <circle cx="710" cy="448" r="5" />
                            <circle cx="888" cy="362" r="3.8" />
                            <circle cx="1070" cy="436" r="4.2" />
                            <circle cx="1248" cy="344" r="4" />
                            <circle cx="1464" cy="418" r="3.6" />
                        </g>

                        <g class="hero-faint-nodes">
                            <circle cx="38" cy="126" r="4" />
                            <circle cx="146" cy="82" r="5" />
                            <circle cx="286" cy="126" r="4" />
                            <circle cx="410" cy="78" r="5" />
                            <circle cx="558" cy="138" r="4" />
                            <circle cx="704" cy="92" r="5" />
                            <circle cx="854" cy="154" r="4" />
                            <circle cx="1016" cy="78" r="5" />
                            <circle cx="1172" cy="126" r="4" />
                            <circle cx="1328" cy="62" r="5" />
                            <circle cx="110" cy="214" r="4" />
                            <circle cx="264" cy="276" r="5" />
                            <circle cx="428" cy="206" r="4" />
                            <circle cx="604" cy="286" r="5" />
                            <circle cx="776" cy="202" r="4" />
                            <circle cx="944" cy="274" r="5" />
                            <circle cx="1108" cy="198" r="4" />
                            <circle cx="1274" cy="260" r="5" />
                            <circle cx="202" cy="394" r="4" />
                            <circle cx="368" cy="458" r="5" />
                            <circle cx="536" cy="372" r="4" />
                            <circle cx="710" cy="448" r="5" />
                            <circle cx="888" cy="362" r="4" />
                            <circle cx="1070" cy="436" r="5" />
                            <circle cx="1248" cy="344" r="4" />
                            <circle cx="1464" cy="418" r="5" />
                        </g>

                        <g class="hero-map-fills">
                            <path d="M742 82 948 42 1188 118 1374 82 1454 214 1298 316 1164 284 1014 382 826 318 704 206Z" />
                            <path d="M190 118 378 78 548 150 516 306 330 360 170 278Z" />
                            <path d="M548 462 716 388 882 452 1066 390 1232 476 1102 584 824 552 660 610Z" />
                        </g>

                        <g class="hero-polygons">
                            <path d="M56 466 212 336 386 392 488 246 644 314 760 188 934 266 1074 128 1258 204 1406 92" />
                            <path d="M-28 262 172 198 320 286 520 154 752 222 952 110 1162 190 1452 68" />
                            <path d="M92 604 274 500 476 560 660 444 844 520 1042 398 1232 462 1448 326" />
                        </g>

                        <g class="hero-contours">
                            <path d="M830 118c122-62 286-30 364 58 82 92 62 214-42 282-120 78-304 66-408-26-100-88-80-244 86-314Z" />
                            <path d="M882 164c88-42 206-22 262 42 58 66 42 154-34 204-88 58-222 48-298-18-74-66-58-178 70-228Z" />
                            <path d="M930 210c58-26 134-14 170 28 36 42 26 98-24 130-56 36-142 30-190-12-46-40-36-112 44-146Z" />
                            <path d="M162 390c118-72 274-56 368 28 94 84 88 202-12 264" />
                            <path d="M80 466c92-34 198-18 268 40 68 56 84 130 38 198" />
                        </g>

                        <g class="hero-terrain" filter="url(#heroGlow)">
                            <path
                                d="M58 354c72-56 126-52 188-18 72 40 112 28 174-32 82-78 158-78 238-8 74 64 142 64 224 8 86-58 156-44 226 22 58 54 128 56 206 10 54-32 98-38 154-16"
                            />
                            <path
                                d="M178 216c72 36 138 36 202-8 68-46 118-42 176 8 64 54 126 62 202 22 72-38 130-30 196 26 76 64 148 66 228 8 70-50 132-46 212 16"
                            />
                            <path d="M226 424c104-40 204-32 300 24 120 70 238 64 356-18 98-68 204-70 318-8 72 40 140 50 206 30" />
                        </g>

                        <g class="hero-rings">
                            <circle cx="1074" cy="128" r="44" />
                            <circle cx="1074" cy="128" r="82" />
                            <circle cx="760" cy="188" r="38" />
                            <circle cx="760" cy="188" r="70" />
                            <circle cx="520" cy="154" r="52" />
                            <circle cx="1232" cy="462" r="54" />
                        </g>

                        <g class="hero-routes">
                            <path d="M74 384 C252 276 404 488 566 302 S908 282 1104 170 1310 212 1438 134" />
                            <path d="M-26 514 C182 442 250 254 472 278 S796 496 1018 314 1266 210 1480 302" />
                            <path d="M188 128 C384 82 480 172 646 154 S900 30 1056 116 1276 152 1456 48" />
                        </g>

                        <g class="hero-packets" filter="url(#heroHeavyGlow)">
                            <circle r="4.5">
                                <animateMotion
                                    dur="8.5s"
                                    repeatCount="indefinite"
                                    path="M74 384 C252 276 404 488 566 302 S908 282 1104 170 1310 212 1438 134"
                                />
                            </circle>
                            <circle r="3.5">
                                <animateMotion
                                    begin="-2.4s"
                                    dur="11s"
                                    repeatCount="indefinite"
                                    path="M-26 514 C182 442 250 254 472 278 S796 496 1018 314 1266 210 1480 302"
                                />
                            </circle>
                            <circle r="4">
                                <animateMotion
                                    begin="-4.2s"
                                    dur="9.8s"
                                    repeatCount="indefinite"
                                    path="M188 128 C384 82 480 172 646 154 S900 30 1056 116 1276 152 1456 48"
                                />
                            </circle>
                            <circle r="2.8">
                                <animateMotion
                                    begin="-1.2s"
                                    dur="7.6s"
                                    repeatCount="indefinite"
                                    path="M226 424c104-40 204-32 300 24 120 70 238 64 356-18 98-68 204-70 318-8 72 40 140 50 206 30"
                                />
                            </circle>
                        </g>

                        <g class="hero-nodes">
                            <circle class="hero-node-halo" cx="214" cy="334" r="19" />
                            <circle class="hero-node-halo" cx="520" cy="154" r="22" />
                            <circle class="hero-node-halo" cx="760" cy="188" r="21" />
                            <circle class="hero-node-halo" cx="1074" cy="128" r="24" />
                            <circle class="hero-node-halo" cx="1232" cy="462" r="18" />
                            <circle cx="214" cy="334" r="6" />
                            <circle cx="386" cy="392" r="5" />
                            <circle cx="520" cy="154" r="7" />
                            <circle cx="660" cy="444" r="5" />
                            <circle cx="760" cy="188" r="6" />
                            <circle cx="934" cy="266" r="5" />
                            <circle cx="1074" cy="128" r="7" />
                            <circle cx="1232" cy="462" r="5" />
                            <circle cx="1258" cy="204" r="6" />
                        </g>

                        <g class="hero-data-bars">
                            <path d="M870 350v-92m44 92v-138m44 138v-76m44 76v-166m44 166v-118m44 118v-58" />
                            <path d="M870 350h270" />
                        </g>
                    </svg>
                </div>

                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="hero-shell grid min-h-[clamp(29rem,calc(100dvh-7rem),38rem)] items-center py-8 sm:py-10 lg:py-12">
                        <div class="max-w-3xl">
                            <p class="hero-kicker inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-normal">
                                <ShieldCheck class="h-3.5 w-3.5" />
                                Portal Publik Akuntabilitas Kinerja
                            </p>
                            <h1 class="hero-title mt-5 max-w-4xl text-3xl font-bold leading-tight text-white sm:text-5xl xl:text-6xl">
                                Transparansi Kinerja Pemerintah Kabupaten Banjarnegara
                            </h1>
                            <p class="mt-5 max-w-2xl text-base font-medium leading-8 text-blue-50/90 sm:text-lg">
                                Selamat datang di E-SAKIP Kabupaten Banjarnegara. Masyarakat dapat menelusuri dokumen perencanaan, pengukuran,
                                pelaporan, dan evaluasi kinerja perangkat daerah berdasarkan tahun berjalan.
                            </p>

                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    :href="props.section_urls.perencanaan"
                                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-bold text-[#00336C] shadow-lg shadow-blue-950/25 transition hover:-translate-y-0.5 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#00336C]"
                                >
                                    Lihat Data Publik
                                    <ChevronRight class="h-4 w-4" />
                                </Link>
                                <Link
                                    :href="entryUrl"
                                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-white/25 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#00336C]"
                                >
                                    <LogIn class="h-4 w-4" />
                                    {{ entryLabel }}
                                </Link>
                            </div>

                            <div class="hero-proofline mt-8 grid gap-3 text-sm font-semibold text-blue-50 sm:grid-cols-2 xl:grid-cols-4">
                                <span><CheckCircle2 class="h-4 w-4" /> Perencanaan</span>
                                <span><CheckCircle2 class="h-4 w-4" /> Pengukuran</span>
                                <span><CheckCircle2 class="h-4 w-4" /> Pelaporan</span>
                                <span><CheckCircle2 class="h-4 w-4" /> Evaluasi</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="module-header pt-28">
                <div class="mx-auto max-w-7xl px-4 pb-8 pt-8 sm:px-6 lg:px-8">
                    <div class="module-header-panel rounded-xl border border-blue-100 bg-white p-5 shadow-sm shadow-blue-950/5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex gap-4">
                                <div class="cycle-icon shrink-0" :class="cycleCardClass(currentSection.id)">
                                    <component :is="currentSection.icon" class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold uppercase text-[#00336C]">{{ currentSection.eyebrow }}</p>
                                    <h1 class="mt-1 text-2xl font-bold text-slate-950 sm:text-3xl">{{ currentSection.title }}</h1>
                                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                        {{ currentSection.summary }} Gunakan pencarian dan filter tahun untuk melihat data publik yang relevan.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[22rem]">
                                <div class="rounded-md border border-blue-100 bg-blue-50/60 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Periode</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ meta.periode_label }}</p>
                                </div>
                                <div class="rounded-md border border-blue-100 bg-blue-50/60 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Baris tampil</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ filteredRowsCount }} dari {{ currentRowsCount }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cycle-band border-b border-blue-100 bg-white">
                <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 md:grid-cols-4 lg:px-8">
                    <Link
                        v-for="(module, index) in homeModules"
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

            <section v-if="!currentSection" class="overview-section bg-white py-14 sm:py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase text-[#00336C]">Data Publik</p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">Pilih siklus SAKIP yang ingin dilihat</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">
                            Setiap siklus memiliki halaman sendiri supaya tabel perangkat daerah tetap nyaman dibuka saat data dan dokumen semakin
                            banyak.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <Link
                            v-for="module in homeModules"
                            :key="`overview-${module.id}`"
                            :href="module.href"
                            class="module-card group flex h-full flex-col rounded-xl border border-blue-100 bg-white p-5 shadow-sm shadow-blue-950/5 transition focus:outline-none focus:ring-2 focus:ring-[#00336C] focus:ring-offset-2"
                            :class="cycleCardClass(module.id)"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="cycle-icon shadow-sm shadow-blue-950/5">
                                    <component :is="module.icon" class="h-5 w-5" />
                                </div>
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-[#00336C]">
                                    {{ module.eyebrow }}
                                </span>
                            </div>
                            <h3 class="mt-6 text-xl font-bold leading-tight text-slate-950">{{ module.title }}</h3>
                            <p class="mt-3 min-h-[5rem] text-sm leading-7 text-slate-600">{{ module.summary }}</p>
                            <div class="mt-auto rounded-lg bg-blue-50/70 p-3">
                                <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
                                    <span>Kelengkapan</span>
                                    <span class="text-[#00336C]">{{ module.completeness }}</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <span
                                        class="block h-full rounded-full bg-[linear-gradient(90deg,var(--cycle-color),#38bdf8)]"
                                        :style="{ width: module.completeness }"
                                    ></span>
                                </div>
                            </div>
                            <div class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-[#00336C]">
                                Buka data
                                <ChevronRight class="h-4 w-4 transition group-hover:translate-x-1" />
                            </div>
                        </Link>
                    </div>
                </div>
            </section>

            <section
                v-for="section in filteredTableSections"
                :id="section.id"
                :key="section.id"
                class="scroll-mt-24 border-b border-blue-100 bg-blue-50/30 py-14 sm:py-16"
            >
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
                                    :value="props.filters.tahun"
                                    class="min-h-11 w-full rounded-md border border-blue-100 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:border-[#00336C] focus:outline-none focus:ring-2 focus:ring-[#00336C]/20"
                                    @change="changeYear"
                                >
                                    <option v-for="year in props.available_years" :key="year" :value="year">
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
                                    v-model="searchQuery"
                                    type="search"
                                    class="min-h-11 w-full rounded-md border border-blue-100 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#00336C] focus:outline-none focus:ring-2 focus:ring-[#00336C]/20"
                                    placeholder="Cari OPD, status, dokumen..."
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
                            @click="searchQuery = ''"
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
                                            <div class="space-y-2">
                                                <div v-if="row.cells[column.key]?.dokumen" class="flex flex-wrap gap-2">
                                                    <a
                                                        :href="row.cells[column.key].dokumen?.view_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        title="Lihat dokumen"
                                                        :aria-label="`Lihat ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                        class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-blue-100 bg-white p-2 text-slate-700 transition hover:border-blue-300 hover:text-[#00336C]"
                                                    >
                                                        <Eye class="h-4 w-4" />
                                                        <span class="sr-only">Lihat</span>
                                                    </a>
                                                    <a
                                                        :href="row.cells[column.key].dokumen?.download_url"
                                                        title="Download dokumen"
                                                        :aria-label="`Download ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                        class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-blue-100 bg-white p-2 text-slate-700 transition hover:border-blue-300 hover:text-[#00336C]"
                                                    >
                                                        <Download class="h-4 w-4" />
                                                        <span class="sr-only">Download</span>
                                                    </a>
                                                </div>
                                                <template v-else-if="row.cells[column.key]?.state === 'missing'">
                                                    <span
                                                        class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-400"
                                                        title="Belum tersedia"
                                                        aria-label="Belum tersedia"
                                                    >
                                                        <X class="h-4 w-4" />
                                                        <span class="sr-only">Belum tersedia</span>
                                                    </span>
                                                </template>
                                                <template v-else>
                                                    <span
                                                        class="inline-flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs font-semibold"
                                                        :class="cellClass(row.cells[column.key])"
                                                    >
                                                        <span class="h-2 w-2 rounded-full" :class="dotClass(row.cells[column.key])"></span>
                                                        {{ row.cells[column.key]?.label || 'Belum tersedia' }}
                                                    </span>
                                                    <p v-if="row.cells[column.key]?.description" class="text-xs text-slate-500">
                                                        {{ row.cells[column.key].description }}
                                                    </p>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="section.rows.length === 0">
                                        <td :colspan="section.columns.length + 2" class="px-4 py-10 text-center text-sm text-slate-500">
                                            {{ emptyTableMessage(section.rows) }}
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
                                    :class="
                                        row.is_ready ? 'border-blue-200 bg-blue-50 text-[#00336C]' : 'border-slate-200 bg-slate-50 text-slate-500'
                                    "
                                >
                                    {{ row.is_ready ? 'Ada data' : 'Belum lengkap' }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-3">
                                <div v-for="column in section.columns" :key="column.key" class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-xs font-semibold uppercase text-slate-500">{{ column.label }}</p>
                                    <div class="mt-2 space-y-2">
                                        <div v-if="row.cells[column.key]?.dokumen" class="flex flex-wrap gap-2">
                                            <a
                                                :href="row.cells[column.key].dokumen?.view_url"
                                                target="_blank"
                                                rel="noopener"
                                                title="Lihat dokumen"
                                                :aria-label="`Lihat ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-700"
                                            >
                                                <Eye class="h-4 w-4" />
                                                <span class="sr-only">Lihat</span>
                                            </a>
                                            <a
                                                :href="row.cells[column.key].dokumen?.download_url"
                                                title="Download dokumen"
                                                :aria-label="`Download ${row.cells[column.key].dokumen?.judul || column.label}`"
                                                class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-700"
                                            >
                                                <Download class="h-4 w-4" />
                                                <span class="sr-only">Download</span>
                                            </a>
                                        </div>
                                        <template v-else-if="row.cells[column.key]?.state === 'missing'">
                                            <span
                                                class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-400"
                                                title="Belum tersedia"
                                                aria-label="Belum tersedia"
                                            >
                                                <X class="h-4 w-4" />
                                                <span class="sr-only">Belum tersedia</span>
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span
                                                class="inline-flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs font-semibold"
                                                :class="cellClass(row.cells[column.key])"
                                            >
                                                <span class="h-2 w-2 rounded-full" :class="dotClass(row.cells[column.key])"></span>
                                                {{ row.cells[column.key]?.label || 'Belum tersedia' }}
                                            </span>
                                            <p v-if="row.cells[column.key]?.description" class="text-xs text-slate-500">
                                                {{ row.cells[column.key].description }}
                                            </p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div
                            v-if="section.rows.length === 0"
                            class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
                        >
                            {{ emptyTableMessage(section.rows) }}
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-[#00336C] py-8 text-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-sm text-blue-100 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="font-semibold text-white">E-SAKIP Kabupaten Banjarnegara</p>
                    <p>Data publik diperbarui dari status dokumen resmi yang sudah diverifikasi.</p>
                </div>
                <div class="border-t border-white/10 pt-3 text-xs text-blue-200">
                    &copy; {{ currentYear }} Dinas Komunikasi dan Informatika Kabupaten Banjarnegara.
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.public-site {
    --portal-ink: #0f172a;
    --portal-blue: #00336c;
    --portal-blue-dark: #002957;
    --portal-blue-soft: #eaf4ff;
    --portal-cyan: #0ea5e9;
    scroll-behavior: smooth;
    font-family:
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        sans-serif;
}

.public-site :is(h1, h2) {
    font-family:
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        sans-serif;
    letter-spacing: 0;
}

.hero-section {
    min-height: 100dvh;
    background:
        linear-gradient(90deg, rgba(0, 18, 48, 0.94) 0%, rgba(0, 51, 108, 0.78) 44%, rgba(0, 51, 108, 0.2) 100%),
        radial-gradient(circle at 78% 28%, rgba(125, 211, 252, 0.14), transparent 28rem), #00336c;
}

.hero-section::before,
.hero-section::after {
    position: absolute;
    inset: 0;
    z-index: -1;
    content: '';
    pointer-events: none;
}

.hero-section::before {
    background:
        linear-gradient(90deg, rgba(0, 20, 52, 0.84), rgba(0, 51, 108, 0.3) 48%, rgba(0, 51, 108, 0.04)),
        radial-gradient(circle at 18% 50%, rgba(14, 165, 233, 0.16), transparent 20rem),
        linear-gradient(180deg, rgba(0, 0, 0, 0), rgba(0, 23, 55, 0.28));
}

.hero-section::after {
    background-image:
        linear-gradient(rgba(125, 211, 252, 0.045) 1px, transparent 1px), linear-gradient(90deg, rgba(125, 211, 252, 0.045) 1px, transparent 1px),
        linear-gradient(rgba(255, 255, 255, 0.022) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.022) 1px, transparent 1px);
    background-position: 0 0;
    background-size:
        4rem 4rem,
        4rem 4rem,
        12rem 12rem,
        12rem 12rem;
    mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.78) 20%, rgba(0, 0, 0, 0.95) 100%);
}

.hero-title {
    text-wrap: balance;
    text-shadow: 0 1rem 3rem rgba(0, 15, 45, 0.34);
}

.hero-shell {
    position: relative;
    z-index: 1;
}

.hero-kicker {
    border: 1px solid rgba(191, 219, 254, 0.26);
    background: rgba(2, 132, 199, 0.18);
    color: #dbeafe;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(0.75rem);
}

.hero-motion {
    position: absolute;
    inset: 0;
    z-index: -2;
    overflow: hidden;
    contain: paint;
    background:
        radial-gradient(circle at 80% 18%, rgba(125, 211, 252, 0.16), transparent 28rem),
        radial-gradient(circle at 40% 72%, rgba(14, 165, 233, 0.09), transparent 24rem),
        linear-gradient(135deg, #001b45 0%, #00336c 46%, #001739 100%);
}

.hero-motion::before {
    position: absolute;
    inset: -18% -20%;
    content: '';
    background:
        linear-gradient(115deg, transparent 0 32%, rgba(14, 165, 233, 0.08) 42%, transparent 52% 100%),
        linear-gradient(62deg, transparent 0 60%, rgba(125, 211, 252, 0.07) 68%, transparent 76% 100%);
    filter: blur(0.35rem);
    opacity: 0.52;
}

.hero-motion::after {
    position: absolute;
    inset: 0;
    content: '';
    background-image:
        radial-gradient(circle, rgba(125, 211, 252, 0.28) 0 1px, transparent 1.8px),
        radial-gradient(circle, rgba(255, 255, 255, 0.14) 0 1px, transparent 1.8px),
        radial-gradient(circle, rgba(56, 189, 248, 0.18) 0 1.3px, transparent 2.2px);
    background-position:
        0 0,
        2rem 2rem,
        4rem 1rem;
    background-size:
        7.5rem 7.5rem,
        10rem 10rem,
        14rem 14rem;
    opacity: 0.2;
}

.hero-aurora {
    position: absolute;
    width: 38rem;
    height: 38rem;
    border-radius: 9999px;
    filter: blur(4rem);
    opacity: 0.18;
    transform: translate3d(0, 0, 0);
}

.hero-aurora-a {
    top: -13rem;
    right: 9%;
    background: #0ea5e9;
}

.hero-aurora-b {
    right: -9rem;
    bottom: -16rem;
    background: #2563eb;
}

.hero-aurora-c {
    top: 18%;
    left: 46%;
    width: 28rem;
    height: 28rem;
    background: #22d3ee;
    opacity: 0.2;
}

.hero-scanline {
    display: none;
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 0 44%, rgba(125, 211, 252, 0.12) 48%, rgba(255, 255, 255, 0.16) 50%, transparent 56% 100%);
    opacity: 0.55;
    transform: translateY(-72%);
    animation: heroScanline 8s cubic-bezier(0.45, 0, 0.22, 1) infinite;
}

.hero-orbit {
    display: none;
    position: absolute;
    border: 1px solid rgba(125, 211, 252, 0.18);
    border-radius: 9999px;
    box-shadow:
        inset 0 0 2rem rgba(14, 165, 233, 0.1),
        0 0 2.5rem rgba(14, 165, 233, 0.08);
}

.hero-orbit-a {
    top: 13%;
    right: 12%;
    width: 25rem;
    height: 25rem;
    animation: heroOrbitFloatA 18s ease-in-out infinite alternate;
}

.hero-orbit-b {
    right: 23%;
    bottom: 4%;
    width: 16rem;
    height: 16rem;
    opacity: 0.78;
    animation: heroOrbitFloatB 15s ease-in-out infinite alternate;
}

.hero-network {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0.22;
    transform: scale(1.06) translate3d(1.6rem, 0, 0);
    filter: saturate(1.08);
}

.hero-network-texture {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    opacity: 0.42;
    background-image:
        linear-gradient(
            118deg,
            transparent 0 16%,
            rgba(125, 211, 252, 0.12) 16.15%,
            transparent 16.35% 42%,
            rgba(56, 189, 248, 0.1) 42.2%,
            transparent 42.4%
        ),
        linear-gradient(
            42deg,
            transparent 0 24%,
            rgba(186, 230, 253, 0.1) 24.15%,
            transparent 24.35% 58%,
            rgba(56, 189, 248, 0.08) 58.2%,
            transparent 58.4%
        ),
        radial-gradient(circle at 18% 36%, rgba(186, 230, 253, 0.42) 0 1.5px, transparent 2px),
        radial-gradient(circle at 45% 22%, rgba(125, 211, 252, 0.28) 0 1.4px, transparent 2px),
        radial-gradient(circle at 72% 45%, rgba(186, 230, 253, 0.32) 0 1.6px, transparent 2.1px),
        radial-gradient(circle at 84% 68%, rgba(125, 211, 252, 0.24) 0 1.4px, transparent 2px);
    background-size:
        28rem 18rem,
        26rem 20rem,
        18rem 14rem,
        22rem 16rem,
        24rem 18rem,
        20rem 16rem;
    mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.88) 18%, rgba(0, 0, 0, 0.96) 100%);
}

.hero-mesh-canvas {
    position: absolute;
    inset: 0;
    z-index: 2;
    width: 100%;
    height: 100%;
    pointer-events: none;
    opacity: 0.98;
    contain: strict;
}

.hero-faint-mesh {
    opacity: 0.2;
    filter: drop-shadow(0 0 0.7rem rgba(125, 211, 252, 0.16));
    transform-origin: center;
    animation: heroMeshFloat 28s ease-in-out infinite alternate;
}

.hero-faint-mesh path {
    fill: none;
    stroke: rgba(147, 197, 253, 0.34);
    stroke-dasharray: 4 18;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.2;
    vector-effect: non-scaling-stroke;
}

.hero-faint-mesh path:nth-child(2n) {
    stroke: rgba(125, 211, 252, 0.26);
    stroke-dasharray: 2 14;
}

.hero-faint-mesh path:nth-child(3n) {
    opacity: 0.72;
}

.hero-triangle-fills {
    opacity: 0.14;
    transform-origin: center;
    animation: heroTriangleDrift 30s ease-in-out infinite alternate;
}

.hero-triangle-fills polygon {
    fill: rgba(56, 189, 248, 0.13);
    stroke: rgba(125, 211, 252, 0.34);
    stroke-linejoin: round;
    stroke-width: 0.9;
    vector-effect: non-scaling-stroke;
}

.hero-triangle-fills polygon:nth-child(3n) {
    fill: rgba(103, 232, 249, 0.18);
}

.hero-triangle-fills polygon:nth-child(4n) {
    fill: rgba(147, 197, 253, 0.1);
    opacity: 0.7;
}

.hero-triangle-lines {
    opacity: 0.16;
    filter: drop-shadow(0 0 0.55rem rgba(125, 211, 252, 0.18));
    transform-origin: center;
    animation: heroTriangleDrift 32s ease-in-out infinite alternate-reverse;
}

.hero-triangle-lines path {
    fill: none;
    stroke: rgba(125, 211, 252, 0.36);
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 0.9;
    vector-effect: non-scaling-stroke;
}

.hero-triangle-lines path:nth-child(2n) {
    stroke: rgba(103, 232, 249, 0.3);
    stroke-width: 0.7;
}

.hero-triangle-lines path:nth-child(3n) {
    stroke: rgba(191, 219, 254, 0.22);
}

.hero-triangle-nodes {
    opacity: 0.12;
    filter: drop-shadow(0 0 0.75rem rgba(125, 211, 252, 0.28));
}

.hero-triangle-nodes circle {
    fill: rgba(186, 230, 253, 0.86);
    stroke: rgba(56, 189, 248, 0.42);
    stroke-width: 3.5;
    transform-box: fill-box;
    transform-origin: center;
    vector-effect: non-scaling-stroke;
}

.hero-triangle-nodes circle:nth-child(5n) {
    fill: rgba(224, 242, 254, 0.96);
    stroke-width: 4.6;
}

.hero-faint-nodes {
    opacity: 0.46;
    filter: drop-shadow(0 0 0.65rem rgba(125, 211, 252, 0.24));
}

.hero-faint-nodes circle {
    fill: rgba(186, 230, 253, 0.72);
    stroke: rgba(125, 211, 252, 0.24);
    stroke-width: 5;
    transform-box: fill-box;
    transform-origin: center;
    vector-effect: non-scaling-stroke;
}

.hero-map-fills,
.hero-contours,
.hero-terrain,
.hero-rings,
.hero-data-bars {
    opacity: 0.12;
}

.hero-polygons,
.hero-routes,
.hero-nodes {
    opacity: 0.2;
}

.hero-packets {
    display: none;
}

.hero-map-fills path {
    fill: url(#heroPanelGradient);
    stroke: rgba(125, 211, 252, 0.12);
    stroke-width: 1;
    vector-effect: non-scaling-stroke;
    animation: heroPanelBreath 12s ease-in-out infinite alternate;
}

.hero-map-fills path:nth-child(2) {
    animation-delay: -4s;
}

.hero-map-fills path:nth-child(3) {
    animation-delay: -8s;
}

.hero-polygons path {
    fill: none;
    stroke: rgba(147, 197, 253, 0.24);
    stroke-width: 1;
    stroke-dasharray: 8 16;
    vector-effect: non-scaling-stroke;
    animation:
        heroPolygonFloat 16s ease-in-out infinite alternate,
        heroRouteFlow 28s linear infinite;
}

.hero-polygons path:nth-child(2) {
    animation-delay: -4s;
}

.hero-polygons path:nth-child(3) {
    animation-delay: -8s;
}

.hero-contours path {
    fill: none;
    stroke: url(#heroContourGradient);
    stroke-dasharray: 5 12;
    stroke-linecap: round;
    stroke-width: 1.5;
    vector-effect: non-scaling-stroke;
    animation:
        heroContourDrift 18s ease-in-out infinite alternate,
        heroRouteFlow 34s linear infinite reverse;
}

.hero-contours path:nth-child(2n) {
    animation-delay: -5s;
}

.hero-contours path:nth-child(3n) {
    animation-delay: -9s;
}

.hero-terrain path {
    fill: none;
    stroke: rgba(56, 189, 248, 0.48);
    stroke-linecap: round;
    stroke-width: 2.25;
    vector-effect: non-scaling-stroke;
    animation: heroTerrainGlow 7s ease-in-out infinite alternate;
}

.hero-terrain path:nth-child(2) {
    animation-delay: -2s;
}

.hero-terrain path:nth-child(3) {
    animation-delay: -4s;
}

.hero-rings circle {
    fill: none;
    stroke: rgba(125, 211, 252, 0.22);
    stroke-dasharray: 6 12;
    stroke-width: 1.5;
    vector-effect: non-scaling-stroke;
    transform-box: fill-box;
    transform-origin: center;
    animation:
        heroRingPulse 4.8s ease-in-out infinite,
        heroRouteFlow 22s linear infinite;
}

.hero-rings circle:nth-child(2n) {
    animation-delay: -1.8s;
}

.hero-routes path {
    fill: none;
    stroke: url(#heroRouteGradient);
    stroke-dasharray: 20 34;
    stroke-linecap: round;
    stroke-width: 1.4;
    vector-effect: non-scaling-stroke;
    filter: drop-shadow(0 0 0.45rem rgba(56, 189, 248, 0.22));
    animation:
        heroRouteFlow 19s linear infinite,
        heroRouteGlow 4.8s ease-in-out infinite alternate;
}

.hero-routes path:nth-child(2) {
    animation-duration: 25s;
    animation-direction: reverse;
}

.hero-routes path:nth-child(3) {
    animation-duration: 21s;
    animation-delay: -6s;
}

.hero-packets circle {
    fill: #e0f2fe;
    opacity: 0.95;
}

.hero-node-halo {
    fill: rgba(14, 165, 233, 0.08);
    stroke: rgba(125, 211, 252, 0.3);
    stroke-width: 1.2;
    transform-box: fill-box;
    transform-origin: center;
    animation: heroHaloPulse 4.2s ease-in-out infinite;
}

.hero-node-halo:nth-child(2n) {
    animation-delay: -1.6s;
}

.hero-nodes circle:not(.hero-node-halo) {
    fill: url(#heroNodeGradient);
    opacity: 0.76;
    transform-box: fill-box;
    transform-origin: center;
    animation: heroNodePulse 3.6s ease-in-out infinite;
}

.hero-nodes circle:not(.hero-node-halo):nth-child(2n) {
    animation-delay: -1.4s;
}

.hero-nodes circle:not(.hero-node-halo):nth-child(3n) {
    animation-delay: -2.2s;
}

.hero-data-bars path {
    fill: none;
    stroke: rgba(125, 211, 252, 0.34);
    stroke-dasharray: 9 13;
    stroke-linecap: round;
    stroke-width: 4;
    vector-effect: non-scaling-stroke;
    animation: heroRouteFlow 14s linear infinite reverse;
}

.hero-proofline span {
    display: flex;
    min-height: 1.75rem;
    align-items: center;
    gap: 0.45rem;
    color: rgba(239, 246, 255, 0.92);
}

.hero-proofline svg {
    color: #7dd3fc;
}

@keyframes heroGridDrift {
    from {
        background-position: 0 0;
    }

    to {
        background-position: 4rem 4rem;
    }
}

@keyframes heroLightSweep {
    from {
        transform: translate3d(-4%, -2%, 0) rotate(-4deg);
    }

    to {
        transform: translate3d(7%, 4%, 0) rotate(5deg);
    }
}

@keyframes heroParticleDrift {
    from {
        background-position:
            0 0,
            2rem 2rem,
            4rem 1rem;
    }

    to {
        background-position:
            7.5rem 7.5rem,
            12rem 12rem,
            18rem 15rem;
    }
}

@keyframes heroMeshFloat {
    from {
        transform: translate3d(-0.6rem, -0.2rem, 0);
    }

    to {
        transform: translate3d(0.8rem, 0.45rem, 0);
    }
}

@keyframes heroMeshFlow {
    from {
        stroke-dashoffset: 0;
    }

    to {
        stroke-dashoffset: -220;
    }
}

@keyframes heroFaintNodePulse {
    0%,
    100% {
        opacity: 0.34;
        transform: scale(0.82);
    }

    48% {
        opacity: 0.82;
        transform: scale(1.14);
    }
}

@keyframes heroTriangleDrift {
    from {
        transform: translate3d(-0.45rem, -0.3rem, 0) scale(1);
    }

    to {
        transform: translate3d(0.7rem, 0.45rem, 0) scale(1.008);
    }
}

@keyframes heroTriangleGlow {
    from {
        opacity: 0.48;
    }

    to {
        opacity: 0.92;
    }
}

@keyframes heroTriangleLineBreathe {
    from {
        opacity: 0.42;
    }

    to {
        opacity: 0.92;
    }
}

@keyframes heroTriangleNodePulse {
    0%,
    100% {
        opacity: 0.54;
        transform: scale(0.84);
    }

    45% {
        opacity: 1;
        transform: scale(1.18);
    }
}

@keyframes heroAuroraA {
    from {
        transform: translate3d(-2rem, 0, 0) scale(1);
    }

    to {
        transform: translate3d(5rem, 3rem, 0) scale(1.14);
    }
}

@keyframes heroAuroraB {
    from {
        transform: translate3d(0, 0, 0) scale(1.06);
    }

    to {
        transform: translate3d(-7rem, -2rem, 0) scale(0.94);
    }
}

@keyframes heroAuroraC {
    from {
        transform: translate3d(-3rem, 2rem, 0) scale(0.94);
    }

    to {
        transform: translate3d(4rem, -2rem, 0) scale(1.18);
    }
}

@keyframes heroScanline {
    0% {
        transform: translateY(-78%);
        opacity: 0;
    }

    16% {
        opacity: 0.58;
    }

    54% {
        opacity: 0.42;
    }

    100% {
        transform: translateY(78%);
        opacity: 0;
    }
}

@keyframes heroOrbitFloatA {
    from {
        transform: translate3d(1rem, -0.8rem, 0) rotate(-7deg) scale(0.96);
        opacity: 0.42;
    }

    to {
        transform: translate3d(-2rem, 1.5rem, 0) rotate(8deg) scale(1.06);
        opacity: 0.72;
    }
}

@keyframes heroOrbitFloatB {
    from {
        transform: translate3d(-1rem, 1rem, 0) scale(1);
        opacity: 0.32;
    }

    to {
        transform: translate3d(1.5rem, -1rem, 0) scale(1.12);
        opacity: 0.58;
    }
}

@keyframes heroPanelBreath {
    from {
        opacity: 0.22;
        transform: translate3d(-0.6rem, 0, 0);
    }

    to {
        opacity: 0.58;
        transform: translate3d(0.8rem, -0.7rem, 0);
    }
}

@keyframes heroPolygonFloat {
    from {
        transform: translate3d(-1.2rem, 0, 0);
        opacity: 0.2;
    }

    to {
        transform: translate3d(1.2rem, -0.7rem, 0);
        opacity: 0.42;
    }
}

@keyframes heroContourDrift {
    from {
        opacity: 0.3;
        transform: translate3d(0.8rem, 0, 0) scale(0.995);
    }

    to {
        opacity: 0.78;
        transform: translate3d(-1rem, -0.7rem, 0) scale(1.01);
    }
}

@keyframes heroTerrainGlow {
    from {
        opacity: 0.36;
    }

    to {
        opacity: 0.78;
    }
}

@keyframes heroRingPulse {
    0%,
    100% {
        opacity: 0.22;
        transform: scale(0.92);
    }

    48% {
        opacity: 0.72;
        transform: scale(1.06);
    }
}

@keyframes heroRouteFlow {
    from {
        stroke-dashoffset: 0;
    }

    to {
        stroke-dashoffset: -260;
    }
}

@keyframes heroRouteGlow {
    from {
        opacity: 0.54;
    }

    to {
        opacity: 0.95;
    }
}

@keyframes heroHaloPulse {
    0%,
    100% {
        opacity: 0.2;
        transform: scale(0.8);
    }

    45% {
        opacity: 0.72;
        transform: scale(1.18);
    }
}

@keyframes heroNodePulse {
    0%,
    100% {
        opacity: 0.42;
        transform: scale(0.86);
    }

    45% {
        opacity: 1;
        transform: scale(1.32);
    }
}

.cycle-band {
    position: relative;
    overflow: hidden;
}

.cycle-band::before {
    position: absolute;
    inset: 0;
    content: '';
    background:
        radial-gradient(circle at 10% 0%, rgba(37, 99, 235, 0.1), transparent 26rem),
        linear-gradient(90deg, rgba(219, 234, 254, 0.8), transparent 38%, rgba(239, 246, 255, 0.9));
    pointer-events: none;
}

.cycle-card {
    position: relative;
    display: flex;
    min-height: 5.75rem;
    align-items: center;
    gap: 0.75rem;
    border-radius: 1rem;
    border: 1px solid rgb(191 219 254);
    background: white;
    overflow: hidden;
    padding: 1.15rem 1.35rem 1.65rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: rgb(15 23 42);
    box-shadow: 0 0.6rem 1.6rem rgb(37 99 235 / 0.08);
    isolation: isolate;
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease;
}

.cycle-card::after {
    position: absolute;
    right: 1.35rem;
    bottom: 0.95rem;
    left: 1.35rem;
    height: 0.22rem;
    content: '';
    border-radius: 9999px;
    background: linear-gradient(90deg, var(--cycle-color, var(--portal-blue)), #7dd3fc);
    transform: scaleX(0.38);
    transform-origin: left;
    transition: transform 180ms ease;
}

.cycle-card:hover {
    border-color: color-mix(in srgb, var(--cycle-color, var(--portal-blue)) 38%, white);
    box-shadow: 0 1rem 2.2rem rgb(37 99 235 / 0.13);
}

.cycle-card:hover::after {
    transform: scaleX(1);
}

.cycle-card-active {
    border-color: color-mix(in srgb, var(--cycle-color, var(--portal-blue)) 52%, white);
    background: linear-gradient(180deg, white, color-mix(in srgb, var(--cycle-color, var(--portal-blue)) 8%, white));
}

.cycle-card-active::after {
    transform: scaleX(1);
}

.cycle-card p {
    margin-bottom: 0.15rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: color-mix(in srgb, var(--cycle-color, var(--portal-blue)) 88%, black);
}

.cycle-card span {
    font-size: 1rem;
    color: var(--portal-ink);
}

.cycle-icon {
    display: inline-flex;
    min-width: 3rem;
    min-height: 3rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.875rem;
    background: color-mix(in srgb, var(--cycle-color, var(--portal-blue)) 12%, white);
    color: color-mix(in srgb, var(--cycle-color, var(--portal-blue)) 86%, black);
}

.cycle-card-planning {
    --cycle-color: #00336c;
}

.cycle-card-measurement {
    --cycle-color: #07589f;
}

.cycle-card-reporting {
    --cycle-color: #00336c;
}

.cycle-card-evaluation {
    --cycle-color: #002957;
}

.overview-section {
    background: radial-gradient(circle at top left, rgba(37, 99, 235, 0.1), transparent 28rem), linear-gradient(180deg, #ffffff, #eff6ff);
}

.module-header {
    background: radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 26rem), linear-gradient(180deg, #ffffff, #eff6ff);
}

.module-header-panel {
    position: relative;
    overflow: hidden;
}

.module-header-panel::after {
    position: absolute;
    inset: auto -8rem -7rem auto;
    width: 16rem;
    height: 16rem;
    content: '';
    border-radius: 9999px;
    background: color-mix(in srgb, var(--portal-blue) 10%, transparent);
    pointer-events: none;
}

.module-card {
    position: relative;
    isolation: isolate;
    min-height: 22rem;
    overflow: hidden;
    padding-bottom: 2rem;
    transition:
        transform 200ms ease,
        border-color 200ms ease,
        box-shadow 200ms ease;
}

.module-card::before {
    position: absolute;
    inset: auto 1.25rem 1.25rem;
    z-index: -1;
    content: '';
    height: 0.3rem;
    border-radius: 9999px;
    background: linear-gradient(90deg, var(--cycle-color, var(--portal-blue)), #7dd3fc);
    opacity: 0.85;
}

.module-card:hover {
    transform: translateY(-0.18rem);
    border-color: color-mix(in srgb, var(--cycle-color, var(--portal-blue)) 42%, white);
    box-shadow: 0 1.1rem 2.6rem rgb(37 99 235 / 0.14);
}

@media (prefers-reduced-motion: reduce) {
    .public-site {
        scroll-behavior: auto;
    }

    .hero-motion,
    .hero-motion *,
    .hero-motion::before,
    .hero-motion::after,
    .hero-section::after {
        animation: none;
    }

    .hero-packets,
    .hero-scanline {
        display: none;
    }

    * {
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>
