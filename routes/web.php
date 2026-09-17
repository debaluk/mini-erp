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

    foreach (['products','customers','suppliers','warehouses','units','tariffs','vehicles','drivers'] as $type) {
        Route::get('/master/'.$type, [ErpController::class, 'master'])->middleware('role:owner,admin,inventori')->name('master.module');
        Route::post('/master/'.$type, [ErpController::class, 'masterStore'])->middleware('role:owner,admin')->name('master.store');
    }

    $cashier = ['pos','sales','payments','shifts'];
    $inventory = ['purchases','receipts','stock','movements','opname','bom','production','production-results','material-usage','production-cost','fleet','deliveries','operations','fleet-costs'];
    $accounting = ['journals','ledger','receivables','cashbank','cogs','profit-loss','balance-sheet','cash-flow'];
    foreach ($cashier as $module) Route::get('/erp/'.$module, [ErpController::class, 'module'])->middleware('role:owner,kasir')->name('erp.module');
    foreach ($inventory as $module) Route::get('/erp/'.$module, [ErpController::class, 'module'])->middleware('role:owner,inventori')->name('erp.module');
    foreach ($accounting as $module) Route::get('/erp/'.$module, [ErpController::class, 'module'])->middleware('role:owner,akuntansi')->name('erp.module');
    Route::get('/erp/payables', [ErpController::class, 'module'])->middleware('role:owner,inventori,akuntansi')->name('erp.module');

    Route::post('/erp/pos', [ErpController::class, 'posStore'])->middleware('role:owner,kasir')->name('erp.pos.store');
    Route::post('/erp/purchase', [ErpController::class, 'purchaseStore'])->middleware('role:owner,inventori')->name('erp.purchase.store');
    Route::post('/erp/production', [ErpController::class, 'productionStore'])->middleware('role:owner,inventori')->name('erp.production.store');
    Route::post('/erp/delivery', [ErpController::class, 'deliveryStore'])->middleware('role:owner,inventori')->name('erp.delivery.store');
    Route::post('/erp/journal', [ErpController::class, 'journalStore'])->middleware('role:owner,akuntansi')->name('erp.journal.store');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
