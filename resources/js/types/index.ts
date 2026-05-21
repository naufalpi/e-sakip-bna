import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    username?: string | null;
    name: string;
    email: string;
    phone?: string | null;
    jabatan?: string | null;
    status: string;
    last_login_at?: string | null;
    opd_id?: number | null;
    opd?: {
        id: number;
        nama: string;
        singkatan?: string | null;
    } | null;
    roles?: Array<{
        id: number;
        name: string;
        label: string;
    }>;
    permissions?: string[];
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
