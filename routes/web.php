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

    // MASTER — Owner + Admin
    foreach (['products','customers','suppliers','warehouses','units','tariffs','vehicles','drivers'] as $type) {
        Route::get('/master/'.$type, [ErpController::class, 'master'])
            ->middleware('role:owner,admin')
            ->name('master.'.$type);
        Route::post('/master/'.$type, [ErpController::class, 'masterStore'])
            ->middleware('role:owner,admin')
            ->name('master.store.'.$type);
    }

    // PENJUALAN / POS — Owner + Kasir
    foreach (['pos','sales','payments','shifts'] as $module) {
        Route::get('/erp/'.$module, [ErpController::class, 'module'])
            ->middleware('role:owner,kasir')
            ->name('erp.'.$module);
    }
    Route::post('/erp/pos', [ErpController::class, 'posStore'])
        ->middleware('role:owner,kasir')->name('erp.pos.store');
    Route::post('/erp/shift', [ErpController::class, 'shiftStore'])
        ->middleware('role:owner,kasir')->name('erp.shift.store');

    // PEMBELIAN — Owner + Inventori; Hutang also Akuntansi
    foreach (['purchases','receipts'] as $module) {
        Route::get('/erp/'.$module, [ErpController::class, 'module'])
            ->middleware('role:owner,inventori')
            ->name('erp.'.$module);
    }
    Route::get('/erp/payables', [ErpController::class, 'module'])
        ->middleware('role:owner,inventori,akuntansi')->name('erp.payables');
    Route::post('/erp/purchase', [ErpController::class, 'purchaseStore'])
        ->middleware('role:owner,inventori')->name('erp.purchase.store');

    // INVENTORI — Owner + Inventori
    foreach (['stock','movements','opname'] as $module) {
        Route::get('/erp/'.$module, [ErpController::class, 'module'])
            ->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/movement', [ErpController::class, 'movementStore'])
        ->middleware('role:owner,inventori')->name('erp.movement.store');
    Route::post('/erp/opname', [ErpController::class, 'opnameStore'])
        ->middleware('role:owner,inventori')->name('erp.opname.store');

    // PRODUKSI BATAKO — Owner + Inventori
    foreach (['bom','production','production-results','material-usage','production-cost'] as $module) {
        Route::get('/erp/'.$module, [ErpController::class, 'module'])
            ->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/bom', [ErpController::class, 'bomStore'])
        ->middleware('role:owner,inventori')->name('erp.bom.store');
    Route::post('/erp/production', [ErpController::class, 'productionStore'])
        ->middleware('role:owner,inventori')->name('erp.production.store');

    // ARMADA — Owner + Inventori
    foreach (['fleet','deliveries','operations','fleet-costs'] as $module) {
        Route::get('/erp/'.$module, [ErpController::class, 'module'])
            ->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/delivery', [ErpController::class, 'deliveryStore'])
        ->middleware('role:owner,inventori')->name('erp.delivery.store');
    Route::post('/erp/operation', [ErpController::class, 'operationStore'])
        ->middleware('role:owner,inventori')->name('erp.operation.store');
    Route::post('/erp/fleet-cost', [ErpController::class, 'fleetCostStore'])
        ->middleware('role:owner,inventori')->name('erp.fleet-cost.store');

    // AKUNTANSI — Owner + Akuntansi
    foreach (['journals','ledger','receivables','cashbank','cogs','profit-loss','balance-sheet','cash-flow'] as $module) {
        Route::get('/erp/'.$module, [ErpController::class, 'module'])
            ->middleware('role:owner,akuntansi')->name('erp.'.$module);
    }
    Route::post('/erp/journal', [ErpController::class, 'journalStore'])
        ->middleware('role:owner,akuntansi')->name('erp.journal.store');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
