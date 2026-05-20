<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Master\OpdController;
use App\Http\Controllers\Master\RolePermissionController;
use App\Http\Controllers\Master\UserController;
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

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('opd', OpdController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('role-permission', RolePermissionController::class)->name('role-permission.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
