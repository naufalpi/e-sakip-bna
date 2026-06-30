import { onBeforeUnmount, onMounted, ref } from 'vue';

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

function createSeededRandom(seed = 42) {
    let value = seed;

    return () => {
        value = (value * 1664525 + 1013904223) % 4294967296;

        return value / 4294967296;
    };
}

function setupHeroMeshCanvas(canvas: HTMLCanvasElement): (() => void) | null {
    const context = canvas.getContext('2d', {
        alpha: true,
        desynchronized: true,
    } as CanvasRenderingContext2DSettings);

    if (!context || typeof window === 'undefined') {
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
    const finePointerQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
    const interactionTarget = canvas.closest('.hero-section') as HTMLElement | null;
    const pointer = {
        x: 0,
        y: 0,
        targetX: 0,
        targetY: 0,
        active: false,
        influence: 0,
        targetInfluence: 0,
        hasPosition: false,
    };
    const pulses: Array<{ x: number; y: number; startedAt: number }> = [];

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

    const extraNodeCount = isCompactViewport ? 16 : isMediumViewport ? 24 : 30;

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
        const maxGraphDistance = 0.64;
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

        const edgeLimit = isCompactViewport ? 84 : isMediumViewport ? 116 : 148;
        const triangleLimit = isCompactViewport ? 24 : isMediumViewport ? 36 : 48;

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
        maxLineDistance = Math.min(width, height) * (width < 720 ? 0.32 : 0.28);

        if (!pointer.hasPosition) {
            pointer.x = width * 0.62;
            pointer.y = height * 0.48;
            pointer.targetX = pointer.x;
            pointer.targetY = pointer.y;
        }
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
        pointer.x += (pointer.targetX - pointer.x) * 0.16;
        pointer.y += (pointer.targetY - pointer.y) * 0.16;
        pointer.influence += (pointer.targetInfluence - pointer.influence) * 0.14;

        const pointerRadius = Math.min(width, height) * (isCompactViewport ? 0.19 : 0.24);
        const pointerRadiusSq = pointerRadius * pointerRadius;

        for (let index = pulses.length - 1; index >= 0; index -= 1) {
            if (time - pulses[index].startedAt > 1.15) {
                pulses.splice(index, 1);
            }
        }

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
            let sx = originX + x3 * field * perspective;
            let sy = originY + y3 * field * perspective;
            let depth = Math.max(0, Math.min(1, (z2 + 1.1) / 2.2));

            if (pointer.influence > 0.01) {
                const dx = sx - pointer.x;
                const dy = sy - pointer.y;
                const distanceSq = dx * dx + dy * dy;

                if (distanceSq < pointerRadiusSq) {
                    const distance = Math.sqrt(distanceSq) || 1;
                    const force = (1 - distance / pointerRadius) ** 2 * pointer.influence;
                    const push = force * (isCompactViewport ? 16 : 28);
                    const swirl = force * (isCompactViewport ? 5 : 8);

                    sx += (dx / distance) * push - (dy / distance) * swirl;
                    sy += (dy / distance) * push + (dx / distance) * swirl;
                    depth = Math.min(1, depth + force * 0.16);
                }
            }

            for (let pulseIndex = 0; pulseIndex < pulses.length; pulseIndex += 1) {
                const pulse = pulses[pulseIndex];
                const age = time - pulse.startedAt;
                const dx = sx - pulse.x;
                const dy = sy - pulse.y;
                const distance = Math.sqrt(dx * dx + dy * dy) || 1;
                const waveRadius = age * (isCompactViewport ? 220 : 320);
                const waveWidth = isCompactViewport ? 52 : 72;
                const wave = 1 - Math.abs(distance - waveRadius) / waveWidth;

                if (wave > 0) {
                    const force = wave * Math.max(0, 1 - age / 1.15) * 12;

                    sx += (dx / distance) * force;
                    sy += (dy / distance) * force;
                    depth = Math.min(1, depth + wave * 0.05);
                }
            }

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
        context.fillStyle = 'rgba(103, 232, 249, 0.052)';
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
        context.strokeStyle = 'rgba(125, 211, 252, 0.32)';
        context.lineWidth = width < 720 ? 0.64 : 0.78;
        context.stroke();

        if (pulses.length > 0) {
            context.beginPath();
            for (let index = 0; index < pulses.length; index += 1) {
                const pulse = pulses[index];
                const age = time - pulse.startedAt;

                if (age < 0 || age > 1.15) {
                    continue;
                }

                const radius = age * (isCompactViewport ? 220 : 320);

                context.moveTo(pulse.x + radius, pulse.y);
                context.arc(pulse.x, pulse.y, radius, 0, Math.PI * 2);
            }
            context.strokeStyle = 'rgba(186, 230, 253, 0.2)';
            context.lineWidth = width < 720 ? 0.9 : 1.15;
            context.stroke();
        }

        context.beginPath();
        for (let index = 0; index < projectedPoints.length; index += 1) {
            const point = projectedPoints[index];

            if (!point.visible) {
                continue;
            }

            const radius = (1.35 + point.depth * 2.1) * point.pulse;

            if (index % 3 === 0 || point.depth > 0.6) {
                const haloRadius = radius * 3.9;

                context.moveTo(point.sx + haloRadius, point.sy);
                context.arc(point.sx, point.sy, haloRadius, 0, Math.PI * 2);
            }
        }
        context.fillStyle = 'rgba(56, 189, 248, 0.17)';
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
        context.fillStyle = 'rgba(224, 242, 254, 0.9)';
        context.fill();

        context.restore();
    };

    const canAnimate = () => !reduceMotionQuery.matches && isDocumentVisible && isHeroVisible;

    const stop = () => {
        if (animationFrame) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = 0;
        }
    };

    const render = (timestamp: number) => {
        if (!canAnimate()) {
            stop();

            return;
        }

        if (timestamp - lastRenderTime >= targetFrameInterval) {
            lastRenderTime = timestamp;
            draw(timestamp / 1000);
        }

        animationFrame = window.requestAnimationFrame(render);
    };

    const start = () => {
        if (!canAnimate() || animationFrame) {
            return;
        }

        lastRenderTime = 0;
        animationFrame = window.requestAnimationFrame(render);
    };

    const handleResize = () => {
        if (resizeFrame) {
            return;
        }

        resizeFrame = window.requestAnimationFrame(() => {
            resizeFrame = 0;
            resize();
            draw(performance.now() / 1000);
            start();
        });
    };

    const handleVisibilityChange = () => {
        isDocumentVisible = document.visibilityState !== 'hidden';

        if (canAnimate()) {
            start();
        } else {
            stop();
        }
    };

    const handleMotionPreferenceChange = () => {
        draw(performance.now() / 1000);

        if (canAnimate()) {
            start();
        } else {
            stop();
        }
    };

    const updatePointerFromEvent = (event: PointerEvent) => {
        const rect = canvas.getBoundingClientRect();

        pointer.targetX = event.clientX - rect.left;
        pointer.targetY = event.clientY - rect.top;
        pointer.hasPosition = true;
    };

    const handlePointerMove = (event: PointerEvent) => {
        if (!finePointerQuery.matches || reduceMotionQuery.matches) {
            return;
        }

        updatePointerFromEvent(event);
        pointer.active = true;
        pointer.targetInfluence = 1;
        start();
    };

    const handlePointerLeave = () => {
        pointer.active = false;
        pointer.targetInfluence = 0;
    };

    const handlePointerDown = (event: PointerEvent) => {
        if (reduceMotionQuery.matches) {
            return;
        }

        updatePointerFromEvent(event);
        pulses.push({
            x: pointer.targetX,
            y: pointer.targetY,
            startedAt: performance.now() / 1000,
        });

        if (pulses.length > 3) {
            pulses.splice(0, pulses.length - 3);
        }

        pointer.active = finePointerQuery.matches;
        pointer.targetInfluence = finePointerQuery.matches ? 1 : 0;
        start();
    };

    resize();
    draw(performance.now() / 1000);
    startTimer = window.setTimeout(start, 450);

    window.addEventListener('resize', handleResize, { passive: true });
    if ('IntersectionObserver' in window) {
        intersectionObserver = new IntersectionObserver(
            ([entry]) => {
                isHeroVisible = entry.isIntersecting;

                if (canAnimate()) {
                    start();
                } else {
                    stop();
                }
            },
            { threshold: 0.08 },
        );
        intersectionObserver.observe(canvas);
    }
    interactionTarget?.addEventListener('pointermove', handlePointerMove, { passive: true });
    interactionTarget?.addEventListener('pointerleave', handlePointerLeave, { passive: true });
    interactionTarget?.addEventListener('pointerdown', handlePointerDown, { passive: true });
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
        interactionTarget?.removeEventListener('pointermove', handlePointerMove);
        interactionTarget?.removeEventListener('pointerleave', handlePointerLeave);
        interactionTarget?.removeEventListener('pointerdown', handlePointerDown);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        reduceMotionQuery.removeEventListener('change', handleMotionPreferenceChange);
    };
}

export function useHeroMeshCanvas() {
    const heroMeshCanvas = ref<HTMLCanvasElement | null>(null);
    let stopHeroMeshAnimation: (() => void) | null = null;
    let heroMeshSetupTimer: number | null = null;

    onMounted(() => {
        heroMeshSetupTimer = window.setTimeout(() => {
            heroMeshSetupTimer = null;

            if (heroMeshCanvas.value) {
                stopHeroMeshAnimation = setupHeroMeshCanvas(heroMeshCanvas.value);
            }
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

    return {
        heroMeshCanvas,
    };
}
