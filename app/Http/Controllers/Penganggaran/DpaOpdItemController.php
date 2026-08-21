<?php

namespace App\Http\Controllers\Penganggaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penganggaran\UpdateDpaOpdItemRequest;
use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DpaOpdItemController extends Controller
{
    public function update(UpdateDpaOpdItemRequest $request, DpaOpd $dpaOpd, DpaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->dpa_opd_id === (int) $dpaOpd->id, 404);
        $payload = $request->validated();
        $plans = Arr::pull($payload, 'cash_plan');

        if (! $request->user()->can('verifyBudget', $dpaOpd)) {
            $payload['pagu_dpa'] = $item->pagu_dpa;
        }

        DB::transaction(function () use ($item, $payload, $plans): void {
            $item->update(Arr::only($payload, ['pagu_dpa', 'alasan_penyesuaian', 'catatan']));
            foreach ($plans as $plan) {
                $item->cashPlans()->updateOrCreate(['bulan' => $plan['bulan']], ['jumlah' => $plan['jumlah']]);
            }
        });

        return back()->with('success', 'Pagu dan rencana penarikan DPA berhasil diperbarui.');
    }
}
