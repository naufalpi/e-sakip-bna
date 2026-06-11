import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';

export type InertiaPageModule = {
    default: DefineComponent & {
        layout?: unknown;
    };
};

const pages = import.meta.glob<InertiaPageModule>('../pages/**/*.vue');
const warmedPageComponents = new Set<string>();

const pagePath = (name: string) => `../pages/${name}.vue`;

export async function resolveAppPageComponent(name: string): Promise<InertiaPageModule> {
    return resolvePageComponent<InertiaPageModule>(pagePath(name), pages);
}

export function warmAppPageComponent(name: string | null | undefined): void {
    if (!name || warmedPageComponents.has(name)) {
        return;
    }

    const importer = pages[pagePath(name)];

    if (!importer) {
        return;
    }

    warmedPageComponents.add(name);
    void importer().catch(() => warmedPageComponents.delete(name));
}
