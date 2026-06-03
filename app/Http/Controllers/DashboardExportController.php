<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardExportService;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardExportController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService, DashboardExportService $exportService): StreamedResponse
    {
        $dashboard = $dashboardService->forUser(
            $request->user(),
            $request->only(['tahun', 'opd_id']),
        );

        return $exportService->csv($dashboard);
    }
}
