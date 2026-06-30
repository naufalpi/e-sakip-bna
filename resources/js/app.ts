import 'sweetalert2/dist/sweetalert2.min.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, Fragment, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import AppFlashNotifier from './components/AppFlashNotifier.vue';
import { initializeTheme } from './composables/useAppearance';
import AppLayout from './layouts/AppLayout.vue';
import { resolveAppPageComponent, warmAppPageComponent } from './lib/inertiaPages';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const shouldUsePersistentAppLayout = (name: string) => !name.startsWith('auth/') && !name.startsWith('PublicSite/') && name !== 'Welcome';

const showGlobalToast = async (message: string, icon: 'success' | 'error' | 'warning' | 'info' = 'info') => {
    const { toast } = await import('./lib/sweetAlert');

    return toast(message, icon);
};

if (typeof document !== 'undefined') {
    document.addEventListener('inertia:prefetched', (event) => {
        const page = (event as CustomEvent<{ response?: { component?: string } }>).detail.response;
        warmAppPageComponent(page?.component);
    });

    document.addEventListener('inertia:error', (event) => {
        const errors = (event as CustomEvent<{ errors?: Record<string, string | string[]> }>).detail.errors ?? {};
        const firstError = Object.values(errors)
            .flatMap((error) => (Array.isArray(error) ? error : [error]))
            .find((error) => typeof error === 'string' && error.trim().length > 0);

        void showGlobalToast(firstError ?? 'Periksa kembali isian form.', 'error');
    });

    document.addEventListener('inertia:exception', () => {
        void showGlobalToast('Terjadi kesalahan saat memproses permintaan.', 'error');
    });

    document.addEventListener('inertia:invalid', () => {
        void showGlobalToast('Respons server tidak valid. Silakan muat ulang halaman.', 'error');
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolveAppPageComponent(name);
        const component = page.default;

        if (shouldUsePersistentAppLayout(name)) {
            component.layout ??= AppLayout;
        }

        return component;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(Fragment, [h(App, props), h(AppFlashNotifier)]) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        delay: 120,
        color: '#047857',
        showSpinner: false,
    },
});

// This will set light / dark mode on page load...
initializeTheme();
