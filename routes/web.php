<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Master\OpdController;
use App\Http\Controllers\Master\RolePermissionController;
use App\Http\Controllers\Master\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('opd', OpdController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('role-permission', RolePermissionController::class)->name('role-permission.index');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
