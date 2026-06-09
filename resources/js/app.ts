import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import AppLayout from './layouts/AppLayout.vue';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
type InertiaPageModule = {
    default: DefineComponent & {
        layout?: DefineComponent;
    };
};

const pages = import.meta.glob<InertiaPageModule>('./pages/**/*.vue');
const shouldUsePersistentAppLayout = (name: string) => !name.startsWith('auth/') && !name.startsWith('PublicSite/') && name !== 'Welcome';
const warmedPageComponents = new Set<string>();

const warmPageComponent = (name: string | undefined) => {
    if (!name || warmedPageComponents.has(name)) {
        return;
    }

    const importer = pages[`./pages/${name}.vue`];

    if (!importer) {
        return;
    }

    warmedPageComponents.add(name);
    void importer().catch(() => warmedPageComponents.delete(name));
};

if (typeof document !== 'undefined') {
    document.addEventListener('inertia:prefetched', (event) => {
        const page = (event as CustomEvent<{ response?: { component?: string } }>).detail.response;
        warmPageComponent(page?.component);
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent<InertiaPageModule>(`./pages/${name}.vue`, pages);

        if (shouldUsePersistentAppLayout(name)) {
            page.default.layout ??= AppLayout;
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
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
