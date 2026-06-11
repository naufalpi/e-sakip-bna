import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import AppLayout from './layouts/AppLayout.vue';
import { resolveAppPageComponent, warmAppPageComponent } from './lib/inertiaPages';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const shouldUsePersistentAppLayout = (name: string) => !name.startsWith('auth/') && !name.startsWith('PublicSite/') && name !== 'Welcome';

if (typeof document !== 'undefined') {
    document.addEventListener('inertia:prefetched', (event) => {
        const page = (event as CustomEvent<{ response?: { component?: string } }>).detail.response;
        warmAppPageComponent(page?.component);
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
