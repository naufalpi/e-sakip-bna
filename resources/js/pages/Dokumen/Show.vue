<script setup lang="ts">
import { confirmDelete } from '@/lib/sweetAlert';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';

type Relation = {
    id: number;
    related_type: string;
    related_type_label: string;
    related_id: number;
    label?: string | null;
};

const props = defineProps<{
    dokumen: {
        id: number;
        jenis: string;
        judul: string;
        nomor_dokumen?: string | null;
        deskripsi?: string | null;
        status: string;
        original_filename: string;
        mime_type?: string | null;
        file_size: number;
        file_hash: string;
        storage_disk: string;
        created_at?: string | null;
        download_url: string;
        metadata?: Record<string, unknown> | null;
        opd?: { kode?: string; nama: string; singkatan?: string | null } | null;
        periode_tahun?: { tahun: number; nama: string } | null;
        uploaded_by?: { name: string } | null;
        relations: Relation[];
    };
    can: { manage: boolean; download: boolean };
}>();

const destroy = async () => {
    if (await confirmDelete(`Hapus dokumen ${props.dokumen.judul}?`)) {
        router.delete(route('dokumen.destroy', props.dokumen.id));
    }
};

const formatSize = (size: number) => {
    if (size >= 1024 * 1024) return `${(size / 1024 / 1024).toFixed(2)} MB`;
    if (size >= 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${size} B`;
};

const statusLabel = (status: string) =>
    ({
        draft: 'Draft',
        submitted: 'Diajukan',
        revision: 'Revisi',
        verified: 'Terverifikasi',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        locked: 'Terkunci',
    })[status] ?? status;

const jenisLabel = (jenis: string) =>
    ({
        rpjmd: 'RPJMD',
        renstra: 'Renstra',
        renja: 'Renja',
        iku: 'IKU',
        ikd: 'IKD',
        perjanjian_kinerja: 'Perjanjian Kinerja',
        rencana_aksi: 'Rencana Aksi',
        realisasi_kinerja: 'Realisasi Kinerja',
        bukti_dukung: 'Bukti Dukung',
        lkjip: 'LKJIP',
        lke: 'LKE',
        lhe: 'LHE',
        rekomendasi: 'Rekomendasi',
        tindak_lanjut: 'Tindak Lanjut',
        lainnya: 'Lainnya',
    })[jenis] ?? jenis;

const statusClass = (status: string) =>
    ({
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-blue-100 text-blue-800',
        revision: 'bg-amber-100 text-amber-800',
        verified: 'bg-cyan-100 text-cyan-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
        locked: 'bg-zinc-200 text-zinc-800',
    })[status] ?? 'bg-slate-100 text-slate-700';
</script>

<template>
    <Head :title="dokumen.judul" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal">{{ dokumen.judul }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm text-muted-foreground">
                    <span>{{ jenisLabel(dokumen.jenis) }}</span>
                    <span>-</span>
                    <span>{{ dokumen.opd?.singkatan || dokumen.opd?.nama || 'Kabupaten' }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(dokumen.status)">{{
                        statusLabel(dokumen.status)
                    }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    v-if="can.download"
                    :href="dokumen.download_url"
                    class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    <Download class="size-4" />
                    Unduh
                </a>
                <Link v-if="can.manage" :href="route('dokumen.edit', dokumen.id)" class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >Edit</Link
                >
                <button v-if="can.manage" type="button" class="rounded-md border px-3 py-2 text-sm text-red-700 hover:bg-red-50" @click="destroy">
                    Hapus
                </button>
            </div>
        </div>

        <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-3">
            <div>
                <div class="text-xs uppercase text-muted-foreground">Nomor Dokumen</div>
                <div class="mt-1 font-medium">{{ dokumen.nomor_dokumen || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Periode</div>
                <div class="mt-1 font-medium">{{ dokumen.periode_tahun?.nama || '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-muted-foreground">Diunggah Oleh</div>
                <div class="mt-1 font-medium">{{ dokumen.uploaded_by?.name || '-' }}</div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1fr_360px]">
            <div class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Keterangan</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-muted-foreground">{{ dokumen.deskripsi || 'Tidak ada deskripsi.' }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <h2 class="text-sm font-semibold">Metadata File</h2>
                <dl class="mt-3 grid gap-3 text-sm">
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">Nama File</dt>
                        <dd class="mt-1 break-all font-medium">{{ dokumen.original_filename }}</dd>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <dt class="text-xs uppercase text-muted-foreground">Ukuran</dt>
                            <dd class="mt-1 font-medium">{{ formatSize(dokumen.file_size) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-muted-foreground">MIME</dt>
                            <dd class="mt-1 font-medium">{{ dokumen.mime_type || '-' }}</dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">Disk</dt>
                        <dd class="mt-1 font-medium">{{ dokumen.storage_disk }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">SHA-256</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ dokumen.file_hash }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <h2 class="text-sm font-semibold">Relasi Dokumen</h2>
            <div v-if="dokumen.relations.length" class="mt-3 divide-y text-sm">
                <div v-for="relation in dokumen.relations" :key="relation.id" class="py-3">
                    <div class="font-medium">{{ relation.label || '-' }}</div>
                    <div class="text-xs text-muted-foreground">{{ relation.related_type_label }} #{{ relation.related_id }}</div>
                </div>
            </div>
            <div v-else class="mt-3 text-sm text-muted-foreground">Dokumen belum dikaitkan ke data modul lain.</div>
        </section>
    </div>
</template>
