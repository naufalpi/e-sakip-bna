<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dokumen\DokumenController;
use App\Http\Controllers\Evaluasi\EvaluasiSakipController;
use App\Http\Controllers\Evaluasi\EvaluasiSakipItemController;
use App\Http\Controllers\Evaluasi\KriteriaEvaluasiController;
use App\Http\Controllers\Evaluasi\LheController;
use App\Http\Controllers\Evaluasi\RekomendasiEvaluasiController;
use App\Http\Controllers\Evaluasi\TindakLanjutRekomendasiController;
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

    Route::get('evaluasi-sakip/kriteria', KriteriaEvaluasiController::class)->name('evaluasi-sakip.kriteria');
    Route::resource('evaluasi-sakip', EvaluasiSakipController::class)->parameters(['evaluasi-sakip' => 'evaluasi_sakip']);
    Route::post('evaluasi-sakip/{evaluasi_sakip}/items', [EvaluasiSakipItemController::class, 'store'])->name('evaluasi-sakip.items.store');
    Route::delete('evaluasi-sakip/{evaluasi_sakip}/items/{item}', [EvaluasiSakipItemController::class, 'destroy'])->name('evaluasi-sakip.items.destroy');
    Route::post('evaluasi-sakip/{evaluasi_sakip}/lhe', [LheController::class, 'store'])->name('evaluasi-sakip.lhe.store');
    Route::post('evaluasi-sakip/{evaluasi_sakip}/rekomendasi', [RekomendasiEvaluasiController::class, 'store'])->name('evaluasi-sakip.rekomendasi.store');
    Route::delete('evaluasi-sakip/{evaluasi_sakip}/rekomendasi/{rekomendasi}', [RekomendasiEvaluasiController::class, 'destroy'])->name('evaluasi-sakip.rekomendasi.destroy');
    Route::post('rekomendasi-evaluasi/{rekomendasi}/tindak-lanjut', [TindakLanjutRekomendasiController::class, 'store'])->name('rekomendasi-evaluasi.tindak-lanjut.store');
    Route::patch('tindak-lanjut-rekomendasi/{tindak_lanjut}/verify', [TindakLanjutRekomendasiController::class, 'verify'])->name('tindak-lanjut-rekomendasi.verify');

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('opd', OpdController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('role-permission', RolePermissionController::class)->name('role-permission.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
