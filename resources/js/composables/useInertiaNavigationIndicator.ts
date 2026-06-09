import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

export function useInertiaNavigationIndicator(minVisibleMs = 180) {
    const isNavigating = ref(false);
    let hideNavigationTimer: ReturnType<typeof window.setTimeout> | undefined;
    let navigationStartedAt = 0;
    let stopStartListener: VoidFunction | undefined;
    let stopFinishListener: VoidFunction | undefined;

    onMounted(() => {
        stopStartListener = router.on('start', (event) => {
            if (event.detail.visit.prefetch) {
                return;
            }

            if (hideNavigationTimer) {
                window.clearTimeout(hideNavigationTimer);
            }

            navigationStartedAt = Date.now();
            isNavigating.value = true;
        });

        stopFinishListener = router.on('finish', (event) => {
            if (event.detail.visit.prefetch) {
                return;
            }

            const remainingVisibleMs = Math.max(0, minVisibleMs - (Date.now() - navigationStartedAt));
            hideNavigationTimer = window.setTimeout(() => {
                isNavigating.value = false;
            }, remainingVisibleMs);
        });
    });

    onBeforeUnmount(() => {
        stopStartListener?.();
        stopFinishListener?.();

        if (hideNavigationTimer) {
            window.clearTimeout(hideNavigationTimer);
        }
    });

    return { isNavigating };
}
