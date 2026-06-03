<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\PublicSite\PublicLandingService;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(PublicLandingService $landingService): Response
    {
        return Inertia::render('PublicSite/Landing', $landingService->payload());
    }
}
