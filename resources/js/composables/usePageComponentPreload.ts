import { warmAppPageComponent } from '@/lib/inertiaPages';
import { onBeforeUnmount, onMounted, type Ref, watch } from 'vue';

type PageComponentSource = Array<string | null | undefined> | Ref<Array<string | null | undefined>>;

type PageComponentPreloadOptions = {
    initialDelayMs?: number;
    staggerMs?: number;
};

export function usePageComponentPreload(components: PageComponentSource, options: PageComponentPreloadOptions = {}) {
    const preloadTimers: ReturnType<typeof window.setTimeout>[] = [];
    const initialDelayMs = options.initialDelayMs ?? 90;
    const staggerMs = options.staggerMs ?? 55;

    const currentComponents = () => (Array.isArray(components) ? components : components.value);

    const clearPreloadTimers = () => {
        while (preloadTimers.length > 0) {
            const timer = preloadTimers.pop();

            if (timer) {
                window.clearTimeout(timer);
            }
        }
    };

    const schedulePageComponentPreload = () => {
        if (typeof window === 'undefined' || document.visibilityState === 'hidden') {
            return;
        }

        clearPreloadTimers();

        [...new Set(currentComponents().filter((component): component is string => Boolean(component)))].forEach((component, index) => {
            preloadTimers.push(window.setTimeout(() => warmAppPageComponent(component), initialDelayMs + index * staggerMs));
        });
    };

    onMounted(() => {
        schedulePageComponentPreload();

        document.addEventListener('visibilitychange', schedulePageComponentPreload);
    });

    onBeforeUnmount(() => {
        clearPreloadTimers();
        document.removeEventListener('visibilitychange', schedulePageComponentPreload);
    });

    watch(currentComponents, () => schedulePageComponentPreload());

    return { schedulePageComponentPreload, warmPageComponent: warmAppPageComponent };
}
