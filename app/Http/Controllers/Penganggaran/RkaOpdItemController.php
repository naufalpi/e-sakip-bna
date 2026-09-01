<?php

namespace App\Http\Controllers\Penganggaran;

use App\Http\Controllers\Controller;
use App\Http\Requests\Penganggaran\UpdateRkaOpdItemRequest;
use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RkaOpdItemController extends Controller
{
    public function update(UpdateRkaOpdItemRequest $request, RkaOpd $rkaOpd, RkaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->rka_opd_id === (int) $rkaOpd->id, 404);

        $payload = $request->validated();

        // Sinkronkan kolom lama sementara agar rollback dan integrasi lama tetap aman.
        $payload['pagu_usulan'] = $payload['pagu_rka'];
        $payload['pagu_hasil_verifikasi'] = $payload['pagu_rka'];

        $item->update($payload);

        return back()->with('success', 'Data dan Pagu RKA berhasil diperbarui.');
    }

    public function destroy(Request $request, RkaOpd $rkaOpd, RkaOpdItem $item): RedirectResponse
    {
        abort_unless((int) $item->rka_opd_id === (int) $rkaOpd->id, 404);
        abort_unless(in_array($rkaOpd->status, ['draft', 'revision', 'rejected'], true), 403);
        abort_unless($request->user()->can('update', $rkaOpd), 403);

        if ($item->dpaItems()->exists()) {
            return back()->with('error', 'Sub kegiatan RKA tidak dapat dihapus karena sudah digunakan pada dokumen DPA.');
        }

        $item->delete();

        return back()->with('success', 'Sub kegiatan RKA berhasil dihapus.');
    }
}
