<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dokumen\DokumenController;
use App\Http\Controllers\Kinerja\PerjanjianKinerjaController;
use App\Http\Controllers\Kinerja\PerjanjianKinerjaItemController;
use App\Http\Controllers\Kinerja\RealisasiKinerjaController;
use App\Http\Controllers\Kinerja\RealisasiProgramController;
use App\Http\Controllers\Kinerja\RencanaAksiController;
use App\Http\Controllers\Kinerja\RencanaAksiItemController;
use App\Http\Controllers\Kinerja\WorkflowController;
use App\Http\Controllers\Master\OpdController;
use App\Http\Controllers\Master\RolePermissionController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\RenstraOpd\RenstraOpdController;
use App\Http\Controllers\RenstraOpd\RenstraOpdNodeController;
use App\Http\Controllers\Rpjmd\RpjmdController;
use App\Http\Controllers\Rpjmd\RpjmdImportController;
use App\Http\Controllers\Rpjmd\RpjmdNodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('rpjmd/import', [RpjmdImportController::class, 'create'])->name('rpjmd.import.create');
    Route::post('rpjmd/import', [RpjmdImportController::class, 'store'])->name('rpjmd.import.store');
    Route::get('rpjmd/import/{importBatch}', [RpjmdImportController::class, 'show'])->name('rpjmd.import.show');
    Route::resource('rpjmd', RpjmdController::class);
    Route::post('rpjmd/{rpjmd}/nodes', [RpjmdNodeController::class, 'store'])->name('rpjmd.nodes.store');
    Route::delete('rpjmd/{rpjmd}/nodes/{type}/{id}', [RpjmdNodeController::class, 'destroy'])->name('rpjmd.nodes.destroy');

    Route::resource('renstra-opd', RenstraOpdController::class);
    Route::post('renstra-opd/{renstra_opd}/nodes', [RenstraOpdNodeController::class, 'store'])->name('renstra-opd.nodes.store');
    Route::delete('renstra-opd/{renstra_opd}/nodes/{type}/{id}', [RenstraOpdNodeController::class, 'destroy'])->name('renstra-opd.nodes.destroy');

    Route::resource('perjanjian-kinerja', PerjanjianKinerjaController::class);
    Route::post('perjanjian-kinerja/{perjanjian_kinerja}/items', [PerjanjianKinerjaItemController::class, 'store'])->name('perjanjian-kinerja.items.store');
    Route::delete('perjanjian-kinerja/{perjanjian_kinerja}/items/{item}', [PerjanjianKinerjaItemController::class, 'destroy'])->name('perjanjian-kinerja.items.destroy');

    Route::resource('rencana-aksi', RencanaAksiController::class);
    Route::post('rencana-aksi/{rencana_aksi}/items', [RencanaAksiItemController::class, 'store'])->name('rencana-aksi.items.store');
    Route::delete('rencana-aksi/{rencana_aksi}/items/{item}', [RencanaAksiItemController::class, 'destroy'])->name('rencana-aksi.items.destroy');

    Route::resource('realisasi-kinerja', RealisasiKinerjaController::class);
    Route::post('realisasi-kinerja/{realisasi_kinerja}/programs', [RealisasiProgramController::class, 'store'])->name('realisasi-kinerja.programs.store');
    Route::delete('realisasi-kinerja/{realisasi_kinerja}/programs/{program}', [RealisasiProgramController::class, 'destroy'])->name('realisasi-kinerja.programs.destroy');

    Route::post('workflow/{module}/{id}/transition', [WorkflowController::class, 'transition'])->name('workflow.transition');

    Route::get('dokumen/{dokumen}/download', [DokumenController::class, 'download'])->name('dokumen.download');
    Route::resource('dokumen', DokumenController::class)->parameters(['dokumen' => 'dokumen']);

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('opd', OpdController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('role-permission', RolePermissionController::class)->name('role-permission.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
