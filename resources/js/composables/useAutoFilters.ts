import { onBeforeUnmount, ref, watch } from 'vue';

export function useAutoFilters<T extends object>(filters: T, submit: () => void, delay = 400) {
    const isFiltering = ref(false);
    let timer: ReturnType<typeof window.setTimeout> | null = null;
    let filteringTimer: ReturnType<typeof window.setTimeout> | null = null;

    const cancelPendingSubmit = () => {
        if (timer) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    const markFiltering = () => {
        isFiltering.value = true;

        if (filteringTimer) {
            window.clearTimeout(filteringTimer);
        }

        filteringTimer = window.setTimeout(() => {
            isFiltering.value = false;
            filteringTimer = null;
        }, 240);
    };

    const applyFiltersNow = () => {
        cancelPendingSubmit();
        markFiltering();
        submit();
    };

    watch(
        () => ({ ...filters }),
        () => {
            cancelPendingSubmit();
            timer = window.setTimeout(() => {
                markFiltering();
                submit();
                timer = null;
            }, delay);
        },
    );

    onBeforeUnmount(() => {
        cancelPendingSubmit();

        if (filteringTimer) {
            window.clearTimeout(filteringTimer);
        }
    });

    return { applyFiltersNow, cancelPendingSubmit, isFiltering };
}
