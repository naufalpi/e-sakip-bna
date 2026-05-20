<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): Response
    {
        return Inertia::render('Dashboard', $dashboardService->forUser(
            $request->user(),
            $request->only(['tahun', 'opd_id']),
        ));
    }
}
