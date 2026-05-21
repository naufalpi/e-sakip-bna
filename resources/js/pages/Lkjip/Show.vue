<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import WorkflowActionButtons from '@/components/WorkflowActionButtons.vue';
import WorkflowHistoryTimeline from '@/components/WorkflowHistoryTimeline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Bab = {
    id: number;
    kode: string;
    judul: string;
    jenis: string;
    konten?: string | null;
    urutan: number;
};
type Workflow = {
    status: string;
    histories: Array<{ id: number; action: string; from_status?: string | null; to_status: string; notes?: string | null; created_at: string; actor?: { name: string } | null }>;
} | null;
type GeneratedDocument = {
    id: number;
    judul: string;
    status: string;
    original_filename: string;
    mime_type?: string | null;
    file_size: number;
    created_at?: string | null;
    uploaded_by?: { name: string } | null;
    can_download: boolean;
    download_url?: string | null;
};

const props = defineProps<{
    item: {
        id: number;
        judul: string;
        nomor_dokumen?: string | null;
        tahun: number;
        status: string;
        ringkasan_eksekutif?: string | null;
        catatan?: string | null;
        opd?: { kode?: string | null; nama: string; singkatan?: string | null } | null;
        periode_tahun?: { tahun: number; nama: string } | null;
        perjanjian_kinerja?: { judul: string; tahun: number; status: string } | null;
        realisasi_kinerja?: { tahun: number; periode_realisasi: string; triwulan?: string | null; bulan?: number | null; semester?: number | null; status: string } | null;
        evaluasi_sakip?: { tahun: number; nilai_akhir?: string | number | null; predikat?: string | null; status: string } | null;
        bab: Bab[];
    };
    generatedDocuments: GeneratedDocument[];
    workflow: Workflow;
    can: { manage: boolean; export: boolean; review: boolean; lock: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'LKJIP', href: '/lkjip' },
    { title: props.item.judul, href: '#' },
];

const form = useForm({
    kode: '',
    judul: '',
    jenis: 'bab',
    konten: '',
    urutan: props.item.bab.length + 1,
});

const editingBabId = ref<number | null>(null);

const resetBabForm = () => {
    editingBabId.value = null;
    form.reset();
    form.clearErrors();
};

const editBab = (bab: Bab) => {
    editingBabId.value = bab.id;
    form.kode = bab.kode;
    form.judul = bab.judul;
    form.jenis = bab.jenis;
    form.konten = bab.konten ?? '';
    form.urutan = bab.urutan;
};

const storeBab = () => {
    if (editingBabId.value) {
        form.put(route('lkjip.bab.update', { lkjip: props.item.id, bab: editingBabId.value }), {
            preserveScroll: true,
            onSuccess: () => resetBabForm(),
        });

        return;
    }

    form.post(route('lkjip.bab.store', props.item.id), {
        preserveScroll: true,
        onSuccess: () => resetBabForm(),
    });
};

const destroyBab = (bab: Bab) => {
    if (confirm(`Hapus ${bab.kode} - ${bab.judul}?`)) {
        router.delete(route('lkjip.bab.destroy', { lkjip: props.item.id, bab: bab.id }), { preserveScroll: true });
    }
};

const generateDraft = () => {
    router.post(route('lkjip.generate-draft', props.item.id), {}, { preserveScroll: true });
};

const exportDocument = (format: 'pdf' | 'word') => {
    router.post(route('lkjip.export', props.item.id), { format }, { preserveScroll: true });
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

const relationPeriod = () => {
    const realisasi = props.item.realisasi_kinerja;
    if (!realisasi) return '-';

    return [realisasi.tahun, realisasi.periode_realisasi, realisasi.triwulan || realisasi.bulan || realisasi.semester].filter(Boolean).join(' - ');
};

const formatFileSize = (bytes: number) => {
    if (!bytes) return '0 B';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <Head :title="item.judul" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">{{ item.judul }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm text-muted-foreground">
                        <span>{{ item.opd?.singkatan || item.opd?.nama || '-' }}</span>
                        <span>-</span>
                        <span>{{ item.tahun }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="can.export" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportDocument('pdf')">Export PDF</button>
                    <button v-if="can.export" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportDocument('word')">Export Word</button>
                    <button v-if="can.manage" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="generateDraft">Buat Draft Otomatis</button>
                    <Link v-if="can.manage" :href="route('lkjip.edit', item.id)" class="rounded-md border px-3 py-2 text-sm hover:bg-muted">Edit</Link>
                    <WorkflowActionButtons
                        module="lkjip"
                        :model-id="item.id"
                        :status="item.status"
                        :can-manage="can.manage"
                        :can-review="can.review"
                        :can-lock="can.lock"
                    />
                </div>
            </div>

            <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-4">
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Nomor Dokumen</div>
                    <div class="mt-1 font-medium">{{ item.nomor_dokumen || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Periode</div>
                    <div class="mt-1 font-medium">{{ item.periode_tahun?.nama || item.tahun }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Perjanjian Kinerja</div>
                    <div class="mt-1 font-medium">{{ item.perjanjian_kinerja?.judul || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-muted-foreground">Evaluasi SAKIP</div>
                    <div class="mt-1 font-medium">{{ item.evaluasi_sakip ? `${item.evaluasi_sakip.nilai_akhir ?? '-'} / ${item.evaluasi_sakip.predikat || '-'}` : '-' }}</div>
                </div>
            </section>

            <section class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-2">
                <div>
                    <h2 class="text-sm font-semibold">Ringkasan Eksekutif</h2>
                    <p class="mt-3 whitespace-pre-line text-sm text-muted-foreground">{{ item.ringkasan_eksekutif || 'Belum ada ringkasan eksekutif.' }}</p>
                </div>
                <div>
                    <h2 class="text-sm font-semibold">Sumber Data</h2>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Realisasi</dt>
                            <dd class="text-right font-medium">{{ relationPeriod() }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Catatan</dt>
                            <dd class="max-w-md text-right">{{ item.catatan || '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="flex flex-col gap-2 border-b px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold">Dokumen LKJIP Otomatis</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Draft dan export dibuat lewat queue dan disimpan sebagai dokumen privat.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="can.export" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportDocument('pdf')">PDF</button>
                        <button v-if="can.export" type="button" class="rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="exportDocument('word')">Word</button>
                        <button v-if="can.manage" type="button" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800" @click="generateDraft">Generate Draft</button>
                    </div>
                </div>
                <div v-if="generatedDocuments.length" class="divide-y">
                    <article v-for="document in generatedDocuments" :key="document.id" class="flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-medium">{{ document.judul }}</div>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                <span>{{ document.original_filename }}</span>
                                <span>-</span>
                                <span>{{ formatFileSize(document.file_size) }}</span>
                                <span>-</span>
                                <span>{{ document.created_at || '-' }}</span>
                            </div>
                        </div>
                        <a v-if="document.can_download && document.download_url" :href="document.download_url" class="rounded-md border px-3 py-2 text-center text-sm hover:bg-muted">Unduh</a>
                        <span v-else class="text-xs text-muted-foreground">Tidak ada akses unduh</span>
                    </article>
                </div>
                <div v-else class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada dokumen otomatis. Klik generate atau export untuk membuat dokumen melalui queue.</div>
            </section>

            <section v-if="can.manage" class="rounded-lg border bg-card p-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <h2 class="text-sm font-semibold">{{ editingBabId ? 'Edit BAB LKJIP' : 'Susun BAB LKJIP' }}</h2>
                    <button v-if="editingBabId" type="button" class="w-fit rounded-md border px-3 py-2 text-sm hover:bg-muted" @click="resetBabForm">Batal Edit</button>
                </div>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="storeBab">
                    <div class="grid gap-1">
                        <input v-model="form.kode" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Kode, contoh BAB I" />
                        <InputError :message="form.errors.kode" />
                    </div>
                    <div class="grid gap-1">
                        <input v-model="form.judul" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Judul BAB" />
                        <InputError :message="form.errors.judul" />
                    </div>
                    <select v-model="form.jenis" class="h-9 rounded-md border bg-background px-3 text-sm">
                        <option value="pendahuluan">Pendahuluan</option>
                        <option value="perencanaan">Perencanaan</option>
                        <option value="akuntabilitas">Akuntabilitas</option>
                        <option value="penutup">Penutup</option>
                        <option value="lampiran">Lampiran</option>
                        <option value="bab">BAB Lainnya</option>
                    </select>
                    <input v-model="form.urutan" type="number" class="h-9 rounded-md border bg-background px-3 text-sm" placeholder="Urutan" />
                    <div class="grid gap-1 md:col-span-2">
                        <textarea v-model="form.konten" rows="6" class="rounded-md border bg-background px-3 py-2 text-sm" placeholder="Narasi BAB" />
                        <InputError :message="form.errors.konten" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                            {{ editingBabId ? 'Update BAB' : 'Simpan BAB' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border bg-card">
                <div class="border-b px-4 py-3">
                    <h2 class="text-sm font-semibold">Struktur LKJIP</h2>
                </div>
                <div class="divide-y">
                    <article v-for="bab in item.bab" :key="bab.id" class="p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="text-xs uppercase text-muted-foreground">{{ bab.kode }} - {{ bab.jenis }}</div>
                                <h3 class="mt-1 font-semibold">{{ bab.judul }}</h3>
                            </div>
                            <div v-if="can.manage" class="flex gap-2">
                                <button type="button" class="rounded-md border px-2 py-1 text-xs hover:bg-muted" @click="editBab(bab)">Edit</button>
                                <button type="button" class="rounded-md border px-2 py-1 text-xs text-red-700 hover:bg-red-50" @click="destroyBab(bab)">Hapus</button>
                            </div>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm text-muted-foreground">{{ bab.konten || 'Konten BAB belum diisi.' }}</p>
                    </article>
                    <div v-if="item.bab.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada BAB LKJIP.</div>
                </div>
            </section>

            <WorkflowHistoryTimeline :workflow="workflow" />
        </div>
    </AppLayout>
</template>
