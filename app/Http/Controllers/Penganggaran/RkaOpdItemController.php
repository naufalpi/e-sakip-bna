<?php

namespace App\Http\Controllers\Penganggaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penganggaran\UpdateRkaOpdItemRequest;
use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class RkaOpdItemController extends Controller
{
    public function update(UpdateRkaOpdItemRequest $request, RkaOpd $rkaOpd, RkaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->rka_opd_id === (int) $rkaOpd->id, 404);

        $payload = $request->validated();
        if ($request->user()->can('update', $rkaOpd)) {
            $payload['jenis_belanja'] = $this->legacyBudgetType($payload);

            if (! $request->user()->can('verifyBudget', $rkaOpd)) {
                $payload['pagu_hasil_verifikasi'] = $payload['pagu_usulan'];
                foreach (['operasi', 'modal', 'tidak_terduga', 'transfer'] as $type) {
                    $payload["pagu_belanja_{$type}_hasil_verifikasi"] = $payload["pagu_belanja_{$type}_usulan"];
                }
            }
        } else {
            $payload = Arr::only($payload, [
                'pagu_hasil_verifikasi',
                'pagu_belanja_operasi_hasil_verifikasi',
                'pagu_belanja_modal_hasil_verifikasi',
                'pagu_belanja_tidak_terduga_hasil_verifikasi',
                'pagu_belanja_transfer_hasil_verifikasi',
                'alasan_penyesuaian',
                'catatan',
            ]);
        }

        $item->update($payload);

        return back()->with('success', 'Rincian RKA berhasil diperbarui.');
    }

    /** @param array<string, mixed> $payload */
    private function legacyBudgetType(array $payload): ?string
    {
        $activeTypes = collect(['operasi', 'modal', 'tidak_terduga', 'transfer'])
            ->filter(fn (string $type) => (float) $payload["pagu_belanja_{$type}_usulan"] > 0)
            ->values();

        return $activeTypes->count() === 1 ? $activeTypes->first() : null;
    }
}
