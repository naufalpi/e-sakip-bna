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
            if (! $request->user()->isSuperAdmin()) {
                $payload['pagu_hasil_verifikasi'] = $payload['pagu_usulan'];
            }
        } else {
            $payload = Arr::only($payload, ['pagu_hasil_verifikasi', 'alasan_penyesuaian', 'catatan']);
        }

        $item->update($payload);

        return back()->with('success', 'Rincian RKA berhasil diperbarui.');
    }
}
