import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, type Ref, watch } from 'vue';

type HrefSource = string[] | Ref<string[]>;

type NavigationPrefetchOptions = {
    cacheFor?: string;
    initialDelayMs?: number;
    staggerMs?: number;
    freshMs?: number;
};

export function useNavigationPrefetch(hrefs: HrefSource, options: NavigationPrefetchOptions = {}) {
    const prefetchedNavigationHrefs = new Map<string, number>();
    const prefetchTimers: ReturnType<typeof window.setTimeout>[] = [];
    const cacheFor = options.cacheFor ?? '2m';
    const initialDelayMs = options.initialDelayMs ?? 650;
    const staggerMs = options.staggerMs ?? 180;
    const freshMs = options.freshMs ?? 110_000;
    const prefetchVisitOptions = {
        method: 'get' as const,
        preserveScroll: true,
        preserveState: false,
        showProgress: false,
    };

    const currentHrefs = () => (Array.isArray(hrefs) ? hrefs : hrefs.value);

    const normalizedInternalHref = (href: string): string | null => {
        if (typeof window === 'undefined') {
            return null;
        }

        const url = new URL(href, window.location.origin);

        if (url.origin !== window.location.origin) {
            return null;
        }

        return `${url.pathname}${url.search}`;
    };

    const clearPrefetchTimers = () => {
        while (prefetchTimers.length > 0) {
            const timer = prefetchTimers.pop();

            if (timer) {
                window.clearTimeout(timer);
            }
        }
    };

    const prefetchNavigationHref = (href: string) => {
        const normalizedHref = normalizedInternalHref(href);

        if (!normalizedHref || normalizedHref === `${window.location.pathname}${window.location.search}`) {
            return;
        }

        const lastPrefetchedAt = prefetchedNavigationHrefs.get(normalizedHref);

        if (lastPrefetchedAt && Date.now() - lastPrefetchedAt < freshMs) {
            return;
        }

        if (router.getCached(normalizedHref, prefetchVisitOptions) || router.getPrefetching(normalizedHref, prefetchVisitOptions)) {
            prefetchedNavigationHrefs.set(normalizedHref, Date.now());
            return;
        }

        router.prefetch(normalizedHref, prefetchVisitOptions, { cacheFor });
        prefetchedNavigationHrefs.set(normalizedHref, Date.now());
    };

    const scheduleNavigationPrefetch = () => {
        if (typeof window === 'undefined' || document.visibilityState === 'hidden') {
            return;
        }

        clearPrefetchTimers();

        const uniqueHrefs = [...new Set(currentHrefs())]
            .map((href) => normalizedInternalHref(href))
            .filter((href): href is string => Boolean(href) && href !== `${window.location.pathname}${window.location.search}`);

        uniqueHrefs.forEach((href, index) => {
            prefetchTimers.push(window.setTimeout(() => prefetchNavigationHref(href), initialDelayMs + index * staggerMs));
        });
    };

    let stopNavigateListener: VoidFunction | undefined;

    onMounted(() => {
        scheduleNavigationPrefetch();
        stopNavigateListener = router.on('navigate', () => scheduleNavigationPrefetch());
    });

    onBeforeUnmount(() => {
        clearPrefetchTimers();
        stopNavigateListener?.();
    });

    watch(currentHrefs, () => scheduleNavigationPrefetch());

    return { scheduleNavigationPrefetch };
}
