<?php

namespace App\Http\Controllers\Dokumen;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Services\Dokumen\PublicDocumentChecklistService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokumenPublikChecklistController extends Controller
{
    public function __invoke(Request $request, PublicDocumentChecklistService $service): Response
    {
        $this->authorize('viewAny', Dokumen::class);

        return Inertia::render('Dokumen/PublikChecklist', $service->payload(
            $request->user(),
            $request->only(['tahun', 'opd_id']),
        ));
    }
}
