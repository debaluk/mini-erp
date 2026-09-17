<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.process');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    $masterTypes = ['products','customers','suppliers','warehouses','units','tariffs','vehicles','drivers'];
    foreach ($masterTypes as $type) {
        Route::get('/master/'.$type, function () use ($type) {
            return app(ErpController::class)->master($type);
        })->middleware('role:owner,admin')->name('master.'.$type);

        Route::post('/master/'.$type, function (\Illuminate\Http\Request $request) use ($type) {
            return app(ErpController::class)->masterStore($request, $type);
        })->middleware('role:owner,admin')->name('master.store.'.$type);
    }

    foreach (['pos','sales','payments','shifts'] as $module) {
        Route::get('/erp/'.$module, [ModuleController::class, 'show'])
            ->middleware('role:owner,kasir')->name('erp.'.$module);
    }
    Route::post('/erp/pos', [PosController::class, 'store'])->middleware('role:owner,kasir')->name('erp.pos.store');
    Route::post('/erp/shift', [ModuleController::class, 'shiftStore'])->middleware('role:owner,kasir')->name('erp.shift.store');

    foreach (['purchases','receipts'] as $module) {
        Route::get('/erp/'.$module, [ModuleController::class, 'show'])
            ->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::get('/erp/payables', [ModuleController::class, 'show'])->middleware('role:owner,inventori,akuntansi')->name('erp.payables');
    Route::post('/erp/purchase', [ModuleController::class, 'purchaseStore'])->middleware('role:owner,inventori')->name('erp.purchase.store');

    foreach (['stock','movements','opname'] as $module) {
        Route::get('/erp/'.$module, [ModuleController::class, 'show'])
            ->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/movement', [ModuleController::class, 'movementStore'])->middleware('role:owner,inventori')->name('erp.movement.store');
    Route::post('/erp/opname', [ModuleController::class, 'opnameStore'])->middleware('role:owner,inventori')->name('erp.opname.store');

    foreach (['bom','production','production-results','material-usage','production-cost'] as $module) {
        Route::get('/erp/'.$module, [ModuleController::class, 'show'])
            ->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/bom', [ModuleController::class, 'bomStore'])->middleware('role:owner,inventori')->name('erp.bom.store');
    Route::post('/erp/production', [ProductionController::class, 'store'])->middleware('role:owner,inventori')->name('erp.production.store');

    foreach (['fleet','deliveries','operations','fleet-costs'] as $module) {
        Route::get('/erp/'.$module, [ModuleController::class, 'show'])
            ->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/delivery', [ModuleController::class, 'deliveryStore'])->middleware('role:owner,inventori')->name('erp.delivery.store');
    Route::post('/erp/operation', [ModuleController::class, 'operationStore'])->middleware('role:owner,inventori')->name('erp.operation.store');
    Route::post('/erp/fleet-cost', [ModuleController::class, 'fleetCostStore'])->middleware('role:owner,inventori')->name('erp.fleet-cost.store');

    foreach (['journals','ledger','receivables','cashbank','cogs','profit-loss','balance-sheet','cash-flow'] as $module) {
        Route::get('/erp/'.$module, [ModuleController::class, 'show'])
            ->middleware('role:owner,akuntansi')->name('erp.'.$module);
    }
    Route::post('/erp/journal', [ModuleController::class, 'journalStore'])->middleware('role:owner,akuntansi')->name('erp.journal.store');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
