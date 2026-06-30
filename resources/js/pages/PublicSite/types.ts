import type { Component } from 'vue';

export type PublicDocument = {
    id: number;
    judul: string;
    filename: string;
    mime_type?: string | null;
    view_url: string;
    download_url: string;
};

export type PublicCell = {
    kind: 'status' | 'metric' | 'file' | 'score';
    state: 'available' | 'data' | 'missing' | 'warning' | 'excellent';
    label: string;
    description?: string | null;
    dokumen?: PublicDocument | null;
};

export type PublicRow = {
    no: number;
    opd: {
        id: number;
        kode?: string | null;
        nama: string;
        singkatan?: string | null;
        label: string;
    };
    is_ready: boolean;
    cells: Record<string, PublicCell>;
};

export type Column = {
    key: string;
    label: string;
};

export type SectionId = 'perencanaan' | 'pengukuran' | 'pelaporan' | 'evaluasi';

export type SectionUrls = Record<'home' | SectionId, string>;

export type PublicNavItem = {
    id: string;
    label: string;
    href: string;
    isActive: boolean;
};

export type PublicTableSection = {
    id: SectionId;
    eyebrow: string;
    title: string;
    summary: string;
    icon: Component;
    columns: Column[];
    rows: PublicRow[];
};

export type PublicHomeModule = PublicTableSection & {
    href: string;
    completeness: string;
};
