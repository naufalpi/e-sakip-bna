<?php

namespace App\Http\Controllers\Penganggaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penganggaran\UpdateRkaOpdItemRequest;
use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use Illuminate\Http\RedirectResponse;

class RkaOpdItemController extends Controller
{
    public function update(UpdateRkaOpdItemRequest $request, RkaOpd $rkaOpd, RkaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->rka_opd_id === (int) $rkaOpd->id, 404);

        $payload = $request->validated();
        $payload['jenis_belanja'] = $this->legacyBudgetType($payload);

        // Sinkronkan kolom lama sementara agar rollback dan integrasi lama tetap aman.
        $payload['pagu_usulan'] = $payload['pagu_rka'];
        $payload['pagu_hasil_verifikasi'] = $payload['pagu_rka'];
        foreach (['operasi', 'modal', 'tidak_terduga', 'transfer'] as $type) {
            $payload["pagu_belanja_{$type}_usulan"] = $payload["pagu_belanja_{$type}"];
            $payload["pagu_belanja_{$type}_hasil_verifikasi"] = $payload["pagu_belanja_{$type}"];
        }

        $item->update($payload);

        return back()->with('success', 'Rincian RKA berhasil diperbarui.');
    }

    /** @param array<string, mixed> $payload */
    private function legacyBudgetType(array $payload): ?string
    {
        $activeTypes = collect(['operasi', 'modal', 'tidak_terduga', 'transfer'])
            ->filter(fn (string $type) => (float) $payload["pagu_belanja_{$type}"] > 0)
            ->values();

        return $activeTypes->count() === 1 ? $activeTypes->first() : null;
    }
}
