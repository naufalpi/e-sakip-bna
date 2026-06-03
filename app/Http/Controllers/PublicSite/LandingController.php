<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\PublicSite\PublicLandingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(Request $request, PublicLandingService $landingService, ?string $section = null): Response
    {
        $tahun = $request->integer('tahun') ?: null;
        $tahun = $tahun && $tahun >= 2000 && $tahun <= 2100 ? $tahun : null;

        return Inertia::render('PublicSite/Landing', $landingService->payload($section, $tahun));
    }
}
