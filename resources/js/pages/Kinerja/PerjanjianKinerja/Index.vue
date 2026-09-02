<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { useAutoFilters } from '@/composables/useAutoFilters';
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CalendarDays,
    CheckCircle2,
    FileSignature,
    FileText,
    Pencil,
    Plus,
    Search,
    Trash2,
    UsersRound,
} from 'lucide-vue-next';
import { computed, reactive } from 'vue';

type Option = { id: number; label: string };
type Opd = { id: number; kode?: string; nama: string; singkatan?: string | null };
type Row = {
    id: number;
    judul: string;
    nomor_dokumen?: string | null;
    tahun: number;
    status: string;
    tipe_pk: string;
    level_pk: string;
    tipe_pk_label: string;
    sumber_data?: string | null;
    pegawai?: { id: number; nama: string; nip?: string | null } | null;
    items_count: number;
    programs_count: number;
    total_anggaran: number;
    opd?: Opd | null;
    periode_tahun?: { id: number; tahun: number; nama: string } | null;
};
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links?: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    items: Paginator<Row>;
    filters: { search?: string; status?: string; level_pk?: string; opd_id?: string; periode_tahun_id?: string; tahun?: string; per_page?: string };
    opdOptions: Option[];
    periodeOptions: Option[];
    can: { manage: boolean; manage_bupati: boolean };
}>();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    level_pk: props.filters.level_pk ?? '',
    opd_id: props.filters.opd_id ?? '',
    periode_tahun_id: props.filters.periode_tahun_id ?? '',
    tahun: props.filters.tahun ?? '',
    per_page: props.filters.per_page ?? '10',
});

const applyFilters = () => router.get(route('perjanjian-kinerja.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
const { applyFiltersNow } = useAutoFilters(filterForm, applyFilters);
const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.level_pk = '';
    filterForm.opd_id = '';
    filterForm.periode_tahun_id = '';
    filterForm.tahun = '';
    applyFiltersNow();
};

const destroy = async (row: Row) => {
    if (await confirmDelete(`Hapus Perjanjian Kinerja ${row.tahun} - ${row.judul}?`)) {
        router.delete(route('perjanjian-kinerja.destroy', row.id));
    }
};

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Perlu Perbaikan',
        verified: 'Terverifikasi',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;

const statusClass = (status: string) =>
    ({
        draft: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200',
        submitted: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/45 dark:text-blue-200',
        revision: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
        verified: 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200',
        approved: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
        rejected: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200',
        locked: 'border-zinc-300 bg-zinc-100 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
    })[status] ?? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200';

const levelClass = (level: string) =>
    ({
        bupati: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/35 dark:text-indigo-200',
        kepala_opd: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/35 dark:text-sky-200',
        struktural: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-800 dark:bg-teal-950/35 dark:text-teal-200',
        individu: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-800 dark:bg-orange-950/35 dark:text-orange-200',
    })[level] ?? 'border-border bg-muted/50 text-foreground';

const money = (value: number) => `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value || 0)}`;

type OpdGroup = {
    key: string;
    opd: Opd | null;
    name: string;
    shortName: string;
    code: string;
    rows: Row[];
    approvedCount: number;
};

const groupedItems = computed<OpdGroup[]>(() => {
    const groups = new Map<string, OpdGroup>();

    props.items.data.forEach((row) => {
        const key = row.opd ? `opd-${row.opd.id}` : 'kabupaten';
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                opd: row.opd ?? null,
                name: row.opd?.nama ?? 'Pemerintah Kabupaten Banjarnegara',
                shortName: row.opd?.singkatan || row.opd?.nama || 'Kabupaten',
                code: row.opd?.kode || 'Tingkat Kabupaten',
                rows: [],
                approvedCount: 0,
            });
        }

        const group = groups.get(key)!;
        group.rows.push(row);
        if (['approved', 'locked'].includes(row.status)) group.approvedCount += 1;
    });

    return Array.from(groups.values()).sort((a, b) => {
        if (a.key === 'kabupaten') return -1;
        if (b.key === 'kabupaten') return 1;
        return a.name.localeCompare(b.name, 'id-ID');
    });
});

const visibleApproved = computed(() => props.items.data.filter((row) => ['approved', 'locked'].includes(row.status)).length);
</script>

<template>
    <Head title="Perjanjian Kinerja" />
    <main class="pk-index p-4 lg:p-6">
        <header class="pk-hero">
            <div class="relative z-10 flex min-w-0 items-start gap-4">
                <div class="hero-mark"><FileSignature class="size-6" /></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-primary">Penetapan Kinerja</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight lg:text-[1.7rem]">Perjanjian Kinerja</h1>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground">
                        Dokumen PK ditata per perangkat daerah agar hubungan pejabat, level kinerja, dan status persetujuannya mudah ditelusuri.
                    </p>
                </div>
            </div>
            <div class="relative z-10 flex flex-wrap items-center gap-2 sm:gap-3">
                <div class="hero-stat">
                    <FileText class="size-4 text-primary" /><span
                        ><strong>{{ items.total }}</strong
                        ><small>Total PK</small></span
                    >
                </div>
                <div class="hero-stat">
                    <Building2 class="size-4 text-primary" /><span
                        ><strong>{{ groupedItems.length }}</strong
                        ><small>Grup tampil</small></span
                    >
                </div>
                <div class="hero-stat">
                    <CheckCircle2 class="size-4 text-emerald-600" /><span
                        ><strong>{{ visibleApproved }}</strong
                        ><small>Resmi tampil</small></span
                    >
                </div>
                <Link v-if="can.manage" :href="route('perjanjian-kinerja.create')" class="primary-action"><Plus class="size-4" /> Buat PK</Link>
            </div>
        </header>

        <form class="filter-panel" @submit.prevent="applyFiltersNow">
            <div class="filter-search">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="filterForm.search" type="search" placeholder="Cari dokumen, nomor, pegawai, atau OPD" />
            </div>
            <select v-model="filterForm.status">
                <option value="">Semua status</option>
                <option value="draft">Draft</option>
                <option value="submitted">Diajukan</option>
                <option value="revision">Perlu Perbaikan</option>
                <option value="verified">Terverifikasi</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
                <option value="locked">Terkunci</option>
            </select>
            <select v-model="filterForm.level_pk">
                <option value="">Semua level PK</option>
                <option v-if="can.manage_bupati" value="bupati">PK Bupati</option>
                <option value="kepala_opd">PK Kepala OPD</option>
                <option value="struktural">PK Sek/Kabid/Kabag</option>
                <option value="individu">PK Kasi/Kasubbag/JF/Pelaksana</option>
            </select>
            <select v-model="filterForm.opd_id">
                <option value="">Semua OPD</option>
                <option v-for="option in opdOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
            <select v-model="filterForm.periode_tahun_id">
                <option value="">Semua periode</option>
                <option v-for="option in periodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
            </select>
            <input v-model="filterForm.tahun" type="number" placeholder="Tahun" />
            <button type="button" class="reset-filter" @click="resetFilters">Reset</button>
        </form>

        <div v-if="groupedItems.length" class="space-y-4">
            <section v-for="group in groupedItems" :key="group.key" class="opd-section">
                <header class="opd-heading">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="opd-symbol"><Building2 class="size-5" /></div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <h2 class="truncate text-base font-bold">{{ group.shortName }}</h2>
                                <span class="opd-code">{{ group.code }}</span>
                            </div>
                            <p v-if="group.shortName !== group.name" class="mt-0.5 truncate text-xs text-muted-foreground">{{ group.name }}</p>
                        </div>
                    </div>
                    <div class="opd-summary">
                        <span
                            ><strong>{{ group.rows.length }}</strong> dokumen</span
                        ><i aria-hidden="true"></i>
                        <span
                            ><strong>{{ group.approvedCount }}</strong> resmi</span
                        >
                    </div>
                </header>

                <div class="document-list">
                    <article v-for="row in group.rows" :key="row.id" class="document-row">
                        <div class="document-main">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="level-badge" :class="levelClass(row.level_pk)">{{ row.tipe_pk_label }}</span>
                                <span class="status-badge" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span>
                            </div>
                            <h3>{{ row.judul }}</h3>
                            <p>{{ row.nomor_dokumen || 'Nomor dokumen belum diisi' }}</p>
                        </div>

                        <div class="document-meta">
                            <div class="meta-cell meta-owner">
                                <span class="meta-label"><UsersRound class="size-3.5" /> Pemilik PK</span>
                                <strong>{{ row.pegawai?.nama || 'PK organisasi lama' }}</strong>
                                <small v-if="row.pegawai?.nip">NIP {{ row.pegawai.nip }}</small>
                            </div>
                            <div class="meta-cell">
                                <span class="meta-label"><CalendarDays class="size-3.5" /> Tahun</span>
                                <strong>{{ row.tahun }}</strong>
                                <small>{{ row.periode_tahun?.nama || 'Periode tahunan' }}</small>
                            </div>
                            <div class="meta-cell">
                                <span class="meta-label"><FileText class="size-3.5" /> Isi dokumen</span>
                                <strong>{{ row.items_count }} indikator</strong>
                                <small>{{ row.programs_count }} program</small>
                            </div>
                            <div v-if="row.programs_count" class="meta-cell meta-budget">
                                <span class="meta-label">Anggaran</span>
                                <strong>{{ money(row.total_anggaran) }}</strong>
                                <small>Snapshot program</small>
                            </div>
                        </div>

                        <div class="document-actions">
                            <Link :href="route('perjanjian-kinerja.show', row.id)" class="open-action">Buka <ArrowRight class="size-4" /></Link>
                            <Link v-if="can.manage" :href="route('perjanjian-kinerja.edit', row.id)" class="icon-action" title="Edit PK">
                                <Pencil class="size-4" /><span class="sr-only">Edit</span>
                            </Link>
                            <button v-if="can.manage" type="button" class="icon-action icon-action--danger" title="Hapus PK" @click="destroy(row)">
                                <Trash2 class="size-4" /><span class="sr-only">Hapus</span>
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div v-else class="empty-state">
            <div class="empty-mark"><FileSignature class="size-7" /></div>
            <h2>Belum ada Perjanjian Kinerja</h2>
            <p>Ubah filter pencarian atau buat dokumen PK pertama untuk memulai.</p>
            <Link v-if="can.manage" :href="route('perjanjian-kinerja.create')" class="primary-action mt-4"><Plus class="size-4" /> Buat PK</Link>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-card shadow-sm dark:border-slate-800">
            <DataPagination v-model:per-page="filterForm.per_page" :paginator="items" item-label="dokumen PK" />
        </div>
    </main>
</template>

<style scoped>
.pk-index {
    margin-inline: auto;
    width: min(100%, 1680px);
}
.pk-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    overflow: hidden;
    border: 1px solid hsl(var(--border));
    border-radius: 1rem;
    background:
        radial-gradient(circle at 88% 10%, hsl(var(--primary) / 0.12), transparent 32%),
        linear-gradient(135deg, hsl(var(--card)), hsl(var(--muted) / 0.28));
    padding: 1.25rem;
}
.pk-hero::after {
    position: absolute;
    right: -3rem;
    bottom: -5rem;
    width: 17rem;
    height: 10rem;
    border: 1px solid hsl(var(--primary) / 0.12);
    border-radius: 50%;
    content: '';
    transform: rotate(-12deg);
}
.hero-mark,
.opd-symbol,
.empty-mark {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    color: hsl(var(--primary));
    background: hsl(var(--primary) / 0.09);
}
.hero-mark {
    width: 3rem;
    height: 3rem;
    border: 1px solid hsl(var(--primary) / 0.16);
    border-radius: 0.85rem;
}
.hero-stat {
    display: flex;
    min-width: 6.6rem;
    align-items: center;
    gap: 0.55rem;
    border-left: 1px solid hsl(var(--border));
    padding: 0.2rem 0.7rem;
}
.hero-stat span {
    display: grid;
}
.hero-stat strong {
    font-size: 0.94rem;
    line-height: 1.1;
}
.hero-stat small {
    margin-top: 0.2rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.66rem;
}
.primary-action {
    display: inline-flex;
    height: 2.55rem;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border-radius: 0.65rem;
    background: hsl(var(--primary));
    padding-inline: 1rem;
    color: hsl(var(--primary-foreground));
    font-size: 0.8rem;
    font-weight: 750;
    box-shadow: 0 8px 20px hsl(var(--primary) / 0.16);
    transition:
        transform 160ms ease,
        filter 160ms ease;
}
.primary-action:hover {
    filter: brightness(0.94);
    transform: translateY(-1px);
}
.filter-panel {
    display: grid;
    grid-template-columns: minmax(240px, 1.5fr) repeat(2, minmax(145px, 0.65fr)) minmax(190px, 0.9fr) minmax(165px, 0.7fr) 90px auto;
    gap: 0.55rem;
    margin-block: 1rem;
    border: 1px solid hsl(var(--border));
    border-radius: 0.85rem;
    background: hsl(var(--card));
    padding: 0.7rem;
}
.filter-panel input,
.filter-panel select {
    height: 2.4rem;
    min-width: 0;
    width: 100%;
    border: 1px solid hsl(var(--border));
    border-radius: 0.55rem;
    background: hsl(var(--background));
    padding-inline: 0.75rem;
    color: hsl(var(--foreground));
    font-size: 0.78rem;
    outline: none;
}
.filter-panel input:focus,
.filter-panel select:focus {
    border-color: hsl(var(--primary) / 0.7);
    box-shadow: 0 0 0 3px hsl(var(--primary) / 0.09);
}
.filter-search {
    position: relative;
}
.filter-search input {
    padding-left: 2.25rem;
}
.reset-filter {
    border-radius: 0.55rem;
    padding-inline: 0.65rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.78rem;
    font-weight: 650;
}
.reset-filter:hover {
    background: hsl(var(--muted));
    color: hsl(var(--foreground));
}
.opd-section {
    overflow: hidden;
    border: 1px solid hsl(var(--border));
    border-radius: 0.95rem;
    background: hsl(var(--card));
    box-shadow: 0 8px 28px hsl(220 35% 20% / 0.035);
}
.opd-heading {
    display: flex;
    min-height: 4.4rem;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid hsl(var(--border));
    background: linear-gradient(90deg, hsl(var(--primary) / 0.055), transparent 52%);
    padding: 0.85rem 1rem;
}
.opd-symbol {
    width: 2.55rem;
    height: 2.55rem;
    border-radius: 0.7rem;
}
.opd-code {
    border: 1px solid hsl(var(--border));
    border-radius: 999px;
    background: hsl(var(--background) / 0.8);
    padding: 0.15rem 0.5rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.64rem;
    font-weight: 700;
}
.opd-summary {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.65rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.72rem;
}
.opd-summary strong {
    color: hsl(var(--foreground));
}
.opd-summary i {
    width: 1px;
    height: 1rem;
    background: hsl(var(--border));
}
.document-row {
    display: flex;
    align-items: center;
    gap: 1.15rem;
    border-bottom: 1px solid hsl(var(--border));
    padding: 1rem;
    transition: background-color 150ms ease;
}
.document-row:last-child {
    border-bottom: 0;
}
.document-row:hover {
    background: hsl(var(--muted) / 0.22);
}
.document-main {
    min-width: 230px;
    flex: 1 1 31%;
}
.document-main h3 {
    font-size: 0.86rem;
    font-weight: 750;
    line-height: 1.45;
}
.document-main p {
    margin-top: 0.2rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.69rem;
}
.level-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    border: 1px solid;
    border-radius: 999px;
    padding: 0.2rem 0.52rem;
    font-size: 0.61rem;
    font-weight: 750;
    line-height: 1.2;
}
.document-meta {
    display: grid;
    min-width: 0;
    flex: 1 1 48%;
    grid-template-columns: minmax(150px, 1.35fr) minmax(80px, 0.6fr) minmax(100px, 0.75fr) minmax(115px, 0.9fr);
    gap: 0.45rem;
}
.meta-cell {
    display: grid;
    align-content: center;
    min-width: 0;
    min-height: 3.35rem;
    border-left: 1px solid hsl(var(--border));
    padding-left: 0.8rem;
}
.meta-label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.meta-cell strong,
.meta-cell small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.meta-cell strong {
    margin-top: 0.25rem;
    font-size: 0.73rem;
    font-weight: 700;
}
.meta-cell small {
    margin-top: 0.15rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.63rem;
}
.document-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.38rem;
}
.open-action,
.icon-action {
    display: inline-flex;
    height: 2.35rem;
    align-items: center;
    justify-content: center;
    border: 1px solid hsl(var(--border));
    border-radius: 0.55rem;
    background: hsl(var(--background));
    transition:
        border-color 150ms ease,
        background-color 150ms ease,
        color 150ms ease;
}
.open-action {
    gap: 0.4rem;
    border-color: hsl(var(--primary));
    background: hsl(var(--primary));
    padding-inline: 0.8rem;
    color: hsl(var(--primary-foreground));
    font-size: 0.74rem;
    font-weight: 750;
}
.icon-action {
    width: 2.35rem;
}
.icon-action:hover {
    border-color: hsl(var(--primary) / 0.45);
    background: hsl(var(--primary) / 0.07);
    color: hsl(var(--primary));
}
.icon-action--danger {
    color: hsl(var(--destructive));
}
.icon-action--danger:hover {
    border-color: hsl(var(--destructive) / 0.4);
    background: hsl(var(--destructive) / 0.07);
    color: hsl(var(--destructive));
}
.empty-state {
    display: grid;
    min-height: 20rem;
    place-items: center;
    align-content: center;
    border: 1px dashed hsl(var(--border));
    border-radius: 1rem;
    background: hsl(var(--card));
    padding: 2rem;
    text-align: center;
}
.empty-mark {
    width: 3.5rem;
    height: 3.5rem;
    margin-bottom: 0.9rem;
    border-radius: 1rem;
}
.empty-state h2 {
    font-size: 1rem;
    font-weight: 750;
}
.empty-state p {
    margin-top: 0.35rem;
    color: hsl(var(--muted-foreground));
    font-size: 0.8rem;
}
@media (max-width: 1320px) {
    .filter-panel {
        grid-template-columns: minmax(240px, 1.4fr) repeat(2, minmax(145px, 0.7fr)) minmax(180px, 1fr);
    }
    .document-row {
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .document-main {
        flex-basis: calc(42% - 1rem);
    }
    .document-meta {
        flex-basis: calc(58% - 1rem);
    }
    .document-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
@media (max-width: 900px) {
    .pk-hero {
        align-items: flex-start;
        flex-direction: column;
    }
    .filter-panel {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .filter-search {
        grid-column: 1 / -1;
    }
    .document-main,
    .document-meta {
        flex-basis: 100%;
    }
}
@media (max-width: 640px) {
    .pk-index {
        padding: 0.75rem;
    }
    .pk-hero {
        border-radius: 0.8rem;
        padding: 1rem;
    }
    .hero-stat {
        min-width: auto;
        border-left: 0;
        padding-inline: 0;
        padding-right: 0.65rem;
    }
    .filter-panel {
        grid-template-columns: 1fr;
    }
    .filter-search {
        grid-column: auto;
    }
    .opd-heading {
        align-items: flex-start;
        flex-direction: column;
    }
    .document-row {
        gap: 0.85rem;
        padding: 0.9rem;
    }
    .document-meta {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .meta-cell:nth-child(odd) {
        border-left: 0;
        padding-left: 0;
    }
    .document-actions {
        justify-content: flex-start;
    }
}
</style>
