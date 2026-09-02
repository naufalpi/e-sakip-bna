import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark';

const savedAppearance = (): Appearance => (localStorage.getItem('appearance') === 'dark' ? 'dark' : 'light');

export function updateTheme(value: Appearance) {
    document.documentElement.classList.toggle('dark', value === 'dark');
}

export function initializeTheme() {
    const appearance = savedAppearance();

    // Migrate the previous "system" preference and all unknown values to light.
    localStorage.setItem('appearance', appearance);
    updateTheme(appearance);
}

export function useAppearance() {
    const appearance = ref<Appearance>('light');

    onMounted(() => {
        initializeTheme();
        appearance.value = savedAppearance();
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
