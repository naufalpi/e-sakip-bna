<?php

namespace App\Http\Controllers\Lkjip;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lkjip\StoreLkjipBabRequest;
use App\Models\Lkjip;
use App\Models\LkjipBab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class LkjipBabController extends Controller
{
    public function store(StoreLkjipBabRequest $request, Lkjip $lkjip): RedirectResponse
    {
        $this->authorize('update', $lkjip);

        $data = $request->validated();

        $lkjip->bab()->updateOrCreate(
            ['kode' => $data['kode']],
            $data,
        );

        return back()->with('success', 'BAB LKJIP berhasil disimpan.');
    }

    public function update(StoreLkjipBabRequest $request, Lkjip $lkjip, LkjipBab $bab): RedirectResponse
    {
        $this->authorize('update', $lkjip);
        abort_unless((int) $bab->lkjip_id === (int) $lkjip->id, 404);

        $data = $request->validated();

        $duplicate = LkjipBab::query()
            ->where('lkjip_id', $lkjip->id)
            ->where('kode', $data['kode'])
            ->whereKeyNot($bab->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['kode' => 'Kode BAB sudah dipakai pada LKJIP ini.']);
        }

        $bab->update($data);

        return back()->with('success', 'BAB LKJIP berhasil diperbarui.');
    }

    public function destroy(Lkjip $lkjip, LkjipBab $bab): RedirectResponse
    {
        $this->authorize('update', $lkjip);
        abort_unless((int) $bab->lkjip_id === (int) $lkjip->id, 404);

        $bab->delete();

        return back()->with('success', 'BAB LKJIP berhasil dihapus.');
    }
}
