import type { PublicCell, PublicRow } from './types';

export function cellClass(cell?: PublicCell): string {
    return {
        available: 'border-blue-200 bg-blue-50 text-[#00336C]',
        excellent: 'border-sky-200 bg-sky-50 text-sky-800',
        data: 'border-cyan-200 bg-cyan-50 text-cyan-800',
        warning: 'border-indigo-200 bg-indigo-50 text-indigo-800',
        missing: 'border-slate-200 bg-slate-50 text-slate-500',
    }[cell?.state ?? 'missing'];
}

export function dotClass(cell?: PublicCell): string {
    return {
        available: 'bg-blue-600',
        excellent: 'bg-sky-500',
        data: 'bg-cyan-500',
        warning: 'bg-indigo-500',
        missing: 'bg-slate-300',
    }[cell?.state ?? 'missing'];
}

export function cycleCardClass(id: string): string {
    return (
        {
            perencanaan: 'cycle-card-planning',
            pengukuran: 'cycle-card-measurement',
            pelaporan: 'cycle-card-reporting',
            evaluasi: 'cycle-card-evaluation',
        }[id] ?? ''
    );
}

export function progressWidth(count: number, total: number): string {
    if (total <= 0) {
        return '0%';
    }

    return `${Math.min(100, Math.max(0, Math.round((count / total) * 100)))}%`;
}

export function rowSearchText(row: PublicRow): string {
    const cellText = Object.values(row.cells)
        .flatMap((cell) => [cell.label, cell.description, cell.dokumen?.judul, cell.dokumen?.filename])
        .filter(Boolean)
        .join(' ');

    return [row.opd.nama, row.opd.singkatan, row.opd.kode, row.opd.label, cellText].filter(Boolean).join(' ').toLowerCase();
}

export function filterRows(rows: PublicRow[], searchQuery: string): PublicRow[] {
    const query = searchQuery.trim().toLowerCase();

    if (!query) {
        return rows;
    }

    return rows.filter((row) => rowSearchText(row).includes(query));
}

export function emptyTableMessage(searchQuery: string, sectionRows: PublicRow[]): string {
    if (searchQuery.trim() && sectionRows.length === 0) {
        return 'Tidak ada data yang cocok dengan pencarian.';
    }

    return 'Data OPD belum tersedia.';
}
