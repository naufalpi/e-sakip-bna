import { watch } from 'vue';

export function useAutoFilters<T extends object>(filters: T, submit: () => void, delay = 400) {
    let timer: ReturnType<typeof window.setTimeout> | null = null;

    const cancelPendingSubmit = () => {
        if (timer) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    const applyFiltersNow = () => {
        cancelPendingSubmit();
        submit();
    };

    watch(
        () => ({ ...filters }),
        () => {
            cancelPendingSubmit();
            timer = window.setTimeout(() => {
                submit();
                timer = null;
            }, delay);
        },
    );

    return { applyFiltersNow };
}
