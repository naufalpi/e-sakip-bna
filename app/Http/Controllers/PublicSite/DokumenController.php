<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenController extends Controller
{
    private const PUBLIC_STATUSES = ['verified', 'approved', 'locked'];

    private const PUBLIC_TYPES = [
        'pohon_kinerja',
        'cascading',
        'iku',
        'renstra',
        'renja',
        'perjanjian_kinerja',
        'rencana_aksi',
        'realisasi_kinerja',
        'lkjip',
        'lhe',
        'tindak_lanjut',
    ];

    private const INLINE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'text/plain',
    ];

    public function view(Dokumen $dokumen): StreamedResponse
    {
        $this->assertPublic($dokumen);

        if (! in_array((string) $dokumen->mime_type, self::INLINE_MIME_TYPES, true)) {
            return $this->download($dokumen);
        }

        return Storage::disk($dokumen->storage_disk)->response(
            $dokumen->storage_path,
            $dokumen->original_filename,
            $this->headers($dokumen),
            'inline',
        );
    }

    public function download(Dokumen $dokumen): StreamedResponse
    {
        $this->assertPublic($dokumen);

        return Storage::disk($dokumen->storage_disk)->download(
            $dokumen->storage_path,
            $dokumen->original_filename,
            $this->headers($dokumen),
        );
    }

    private function assertPublic(Dokumen $dokumen): void
    {
        abort_unless(in_array($dokumen->status, self::PUBLIC_STATUSES, true), 404);
        abort_unless(in_array($dokumen->jenis, self::PUBLIC_TYPES, true), 404);
        abort_unless(Storage::disk($dokumen->storage_disk)->exists($dokumen->storage_path), 404);
    }

    /**
     * @return array<string, string>
     */
    private function headers(Dokumen $dokumen): array
    {
        return [
            'Content-Type' => $dokumen->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
