<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErpController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.process');
});
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/master/{type}', [ErpController::class, 'master'])->name('master.module');
    Route::post('/master/{type}', [ErpController::class, 'masterStore'])->name('master.store');
    Route::get('/erp/{module}', [ErpController::class, 'module'])->name('erp.module');
    Route::post('/erp/pos', [ErpController::class, 'posStore'])->name('erp.pos.store');
    Route::post('/erp/purchase', [ErpController::class, 'purchaseStore'])->name('erp.purchase.store');
    Route::post('/erp/production', [ErpController::class, 'productionStore'])->name('erp.production.store');
    Route::post('/erp/delivery', [ErpController::class, 'deliveryStore'])->name('erp.delivery.store');
    Route::post('/erp/journal', [ErpController::class, 'journalStore'])->name('erp.journal.store');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
