<?php

namespace App\Http\Controllers\Penganggaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penganggaran\UpdateDpaOpdItemRequest;
use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class DpaOpdItemController extends Controller
{
    public function update(UpdateDpaOpdItemRequest $request, DpaOpd $dpaOpd, DpaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->dpa_opd_id === (int) $dpaOpd->id, 404);
        $payload = $request->validated();

        if (! $request->user()->can('verifyBudget', $dpaOpd)) {
            $payload['pagu_dpa'] = $item->pagu_dpa;
        }

        $item->update(Arr::only($payload, ['pagu_dpa', 'alasan_penyesuaian', 'catatan']));

        return back()->with('success', 'Data dan Pagu DPA berhasil diperbarui.');
    }
}
