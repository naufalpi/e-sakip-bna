<?php

namespace App\Services\Kinerja;

use App\Models\KopDokumen;
use App\Models\Opd;
use App\Models\PerjanjianKinerja;

class KopDokumenService
{
    public function snapshotFor(PerjanjianKinerja $pk): array
    {
        return $this->forScope($pk->level_pk === 'bupati' ? null : $pk->opd, $pk->level_pk);
    }

    public function forScope(?Opd $opd, ?string $levelPk = null): array
    {
        $scopeKey = $opd ? 'opd:'.$opd->id : 'kabupaten';
        $profile = KopDokumen::query()
            ->where('scope_key', $scopeKey)
            ->where('is_active', true)
            ->first();

        return [
            'nama_pemerintah' => $profile?->nama_pemerintah ?: 'PEMERINTAH KABUPATEN BANJARNEGARA',
            'nama_instansi' => $profile?->nama_instansi ?: ($opd ? mb_strtoupper($opd->nama) : ($levelPk === 'bupati' ? 'BUPATI BANJARNEGARA' : 'PEMERINTAH KABUPATEN BANJARNEGARA')),
            'alamat' => $profile?->alamat ?: ($opd?->alamat ?: 'Jalan Ahmad Yani No. 16 Banjarnegara'),
            'telepon' => $profile?->telepon ?: $opd?->telepon,
            'faksimile' => $profile?->faksimile,
            'website' => $profile?->website,
            'email' => $profile?->email ?: $opd?->email,
            'kota' => $profile?->kota ?: 'BANJARNEGARA',
            'kode_pos' => $profile?->kode_pos,
            'logo_path' => $profile?->logo_path,
        ];
    }

    public function applySnapshot(PerjanjianKinerja $pk): void
    {
        $pk->unsetRelation('opd')->load('opd');
        $pk->forceFill(['kop_dokumen_snapshot' => $this->snapshotFor($pk)])->saveQuietly();
    }
}
