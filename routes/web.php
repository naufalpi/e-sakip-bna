<?php

use App\Http\Controllers\AuditLogController;
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
use App\Http\Controllers\Kinerja\WorkflowInboxController;
use App\Http\Controllers\Lkjip\LkjipBabController;
use App\Http\Controllers\Lkjip\LkjipController;
use App\Http\Controllers\Master\OpdController;
use App\Http\Controllers\Master\OpdUnitController;
use App\Http\Controllers\Master\PeriodeTahunController;
use App\Http\Controllers\Master\RolePermissionController;
use App\Http\Controllers\Master\SatuanIndikatorController;
use App\Http\Controllers\Master\SystemSettingController;
use App\Http\Controllers\Master\UrusanPemerintahanController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Perencanaan\PohonKinerjaController;
use App\Http\Controllers\Perencanaan\TargetTriwulanIndikatorController;
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
    Route::get('audit-log', AuditLogController::class)->name('audit-log.index');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::get('pohon-kinerja', [PohonKinerjaController::class, 'index'])->name('pohon-kinerja.index');
    Route::get('pohon-kinerja/kabupaten/{rpjmd}', [PohonKinerjaController::class, 'kabupaten'])->name('pohon-kinerja.kabupaten');
    Route::get('pohon-kinerja/opd/{renstra_opd}', [PohonKinerjaController::class, 'opd'])->name('pohon-kinerja.opd');
    Route::get('pohon-kinerja/cascading-opd/{renstra_opd}', [PohonKinerjaController::class, 'cascadingOpd'])->name('pohon-kinerja.cascading-opd');

    Route::get('rpjmd/import', [RpjmdImportController::class, 'create'])->name('rpjmd.import.create');
    Route::post('rpjmd/import', [RpjmdImportController::class, 'store'])->name('rpjmd.import.store');
    Route::post('rpjmd/import/{importBatch}/apply', [RpjmdImportController::class, 'apply'])->name('rpjmd.import.apply');
    Route::get('rpjmd/import/{importBatch}', [RpjmdImportController::class, 'show'])->name('rpjmd.import.show');
    Route::resource('rpjmd', RpjmdController::class);
    Route::post('rpjmd/{rpjmd}/nodes', [RpjmdNodeController::class, 'store'])->name('rpjmd.nodes.store');
    Route::put('rpjmd/{rpjmd}/nodes/{type}/{id}', [RpjmdNodeController::class, 'update'])->name('rpjmd.nodes.update');
    Route::delete('rpjmd/{rpjmd}/nodes/{type}/{id}', [RpjmdNodeController::class, 'destroy'])->name('rpjmd.nodes.destroy');

    Route::resource('renstra-opd', RenstraOpdController::class);
    Route::post('renstra-opd/{renstra_opd}/nodes', [RenstraOpdNodeController::class, 'store'])->name('renstra-opd.nodes.store');
    Route::put('renstra-opd/{renstra_opd}/nodes/{type}/{id}', [RenstraOpdNodeController::class, 'update'])->name('renstra-opd.nodes.update');
    Route::delete('renstra-opd/{renstra_opd}/nodes/{type}/{id}', [RenstraOpdNodeController::class, 'destroy'])->name('renstra-opd.nodes.destroy');
    Route::post('target-triwulan-indikator', [TargetTriwulanIndikatorController::class, 'store'])->name('target-triwulan-indikator.store');
    Route::delete('target-triwulan-indikator/{target}', [TargetTriwulanIndikatorController::class, 'destroy'])->name('target-triwulan-indikator.destroy');

    Route::resource('perjanjian-kinerja', PerjanjianKinerjaController::class);
    Route::post('perjanjian-kinerja/{perjanjian_kinerja}/items', [PerjanjianKinerjaItemController::class, 'store'])->name('perjanjian-kinerja.items.store');
    Route::put('perjanjian-kinerja/{perjanjian_kinerja}/items/{item}', [PerjanjianKinerjaItemController::class, 'update'])->name('perjanjian-kinerja.items.update');
    Route::delete('perjanjian-kinerja/{perjanjian_kinerja}/items/{item}', [PerjanjianKinerjaItemController::class, 'destroy'])->name('perjanjian-kinerja.items.destroy');

    Route::resource('rencana-aksi', RencanaAksiController::class);
    Route::post('rencana-aksi/{rencana_aksi}/items', [RencanaAksiItemController::class, 'store'])->name('rencana-aksi.items.store');
    Route::put('rencana-aksi/{rencana_aksi}/items/{item}', [RencanaAksiItemController::class, 'update'])->name('rencana-aksi.items.update');
    Route::delete('rencana-aksi/{rencana_aksi}/items/{item}', [RencanaAksiItemController::class, 'destroy'])->name('rencana-aksi.items.destroy');

    Route::resource('realisasi-kinerja', RealisasiKinerjaController::class);
    Route::post('realisasi-kinerja/{realisasi_kinerja}/programs', [RealisasiProgramController::class, 'store'])->name('realisasi-kinerja.programs.store');
    Route::put('realisasi-kinerja/{realisasi_kinerja}/programs/{program}', [RealisasiProgramController::class, 'update'])->name('realisasi-kinerja.programs.update');
    Route::delete('realisasi-kinerja/{realisasi_kinerja}/programs/{program}', [RealisasiProgramController::class, 'destroy'])->name('realisasi-kinerja.programs.destroy');

    Route::resource('lkjip', LkjipController::class);
    Route::post('lkjip/{lkjip}/generate-draft', [LkjipController::class, 'generateDraft'])->name('lkjip.generate-draft');
    Route::post('lkjip/{lkjip}/export', [LkjipController::class, 'export'])->name('lkjip.export');
    Route::post('lkjip/{lkjip}/bab', [LkjipBabController::class, 'store'])->name('lkjip.bab.store');
    Route::put('lkjip/{lkjip}/bab/{bab}', [LkjipBabController::class, 'update'])->name('lkjip.bab.update');
    Route::delete('lkjip/{lkjip}/bab/{bab}', [LkjipBabController::class, 'destroy'])->name('lkjip.bab.destroy');

    Route::get('workflow/inbox', WorkflowInboxController::class)->name('workflow.inbox');
    Route::post('workflow/{module}/{id}/transition', [WorkflowController::class, 'transition'])->name('workflow.transition');

    Route::get('dokumen/{dokumen}/download', [DokumenController::class, 'download'])->name('dokumen.download');
    Route::resource('dokumen', DokumenController::class)->parameters(['dokumen' => 'dokumen']);

    Route::get('evaluasi-sakip/kriteria', KriteriaEvaluasiController::class)->name('evaluasi-sakip.kriteria');
    Route::resource('evaluasi-sakip', EvaluasiSakipController::class)->parameters(['evaluasi-sakip' => 'evaluasi_sakip']);
    Route::post('evaluasi-sakip/{evaluasi_sakip}/items', [EvaluasiSakipItemController::class, 'store'])->name('evaluasi-sakip.items.store');
    Route::put('evaluasi-sakip/{evaluasi_sakip}/items/{item}', [EvaluasiSakipItemController::class, 'update'])->name('evaluasi-sakip.items.update');
    Route::delete('evaluasi-sakip/{evaluasi_sakip}/items/{item}', [EvaluasiSakipItemController::class, 'destroy'])->name('evaluasi-sakip.items.destroy');
    Route::post('evaluasi-sakip/{evaluasi_sakip}/lhe', [LheController::class, 'store'])->name('evaluasi-sakip.lhe.store');
    Route::post('evaluasi-sakip/{evaluasi_sakip}/lhe/export', [EvaluasiSakipController::class, 'exportLhe'])->name('evaluasi-sakip.lhe.export');
    Route::post('evaluasi-sakip/{evaluasi_sakip}/rekomendasi', [RekomendasiEvaluasiController::class, 'store'])->name('evaluasi-sakip.rekomendasi.store');
    Route::put('evaluasi-sakip/{evaluasi_sakip}/rekomendasi/{rekomendasi}', [RekomendasiEvaluasiController::class, 'update'])->name('evaluasi-sakip.rekomendasi.update');
    Route::delete('evaluasi-sakip/{evaluasi_sakip}/rekomendasi/{rekomendasi}', [RekomendasiEvaluasiController::class, 'destroy'])->name('evaluasi-sakip.rekomendasi.destroy');
    Route::post('rekomendasi-evaluasi/{rekomendasi}/tindak-lanjut', [TindakLanjutRekomendasiController::class, 'store'])->name('rekomendasi-evaluasi.tindak-lanjut.store');
    Route::put('tindak-lanjut-rekomendasi/{tindak_lanjut}', [TindakLanjutRekomendasiController::class, 'update'])->name('tindak-lanjut-rekomendasi.update');
    Route::patch('tindak-lanjut-rekomendasi/{tindak_lanjut}/verify', [TindakLanjutRekomendasiController::class, 'verify'])->name('tindak-lanjut-rekomendasi.verify');

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('opd', OpdController::class)->except(['show']);
        Route::resource('opd-units', OpdUnitController::class)->parameters(['opd-units' => 'opdUnit'])->except(['show']);
        Route::resource('periode-tahun', PeriodeTahunController::class)->parameters(['periode-tahun' => 'periodeTahun'])->except(['show']);
        Route::resource('satuan-indikator', SatuanIndikatorController::class)->parameters(['satuan-indikator' => 'satuanIndikator'])->except(['show']);
        Route::resource('urusan-pemerintahan', UrusanPemerintahanController::class)->parameters(['urusan-pemerintahan' => 'urusanPemerintahan'])->except(['show']);
        Route::resource('system-settings', SystemSettingController::class)->parameters(['system-settings' => 'systemSetting'])->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('role-permission', RolePermissionController::class)->name('role-permission.index');
        Route::patch('role-permission/{role}', [RolePermissionController::class, 'update'])->name('role-permission.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
