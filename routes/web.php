<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\UnitConversionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.process');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/master/unit-conversions', [UnitConversionController::class, 'index'])->middleware('role:owner,admin')->name('master.unit-conversions');
    Route::post('/master/unit-conversions', [UnitConversionController::class, 'store'])->middleware('role:owner,admin')->name('master.unit-conversions.store');
    Route::put('/master/unit-conversions/{id}', [UnitConversionController::class, 'update'])->middleware('role:owner,admin')->name('master.unit-conversions.update');
    Route::delete('/master/unit-conversions/{id}', [UnitConversionController::class, 'destroy'])->middleware('role:owner,admin')->name('master.unit-conversions.delete');

    $masterTypes = ['products','customers','suppliers','warehouses','units','tariffs','vehicles','drivers'];
    foreach ($masterTypes as $type) {
        Route::get('/master/'.$type, function (Request $request) use ($type) {
            return app(ErpController::class)->master($request, $type);
        })->middleware('role:owner,admin')->name('master.'.$type);

        Route::post('/master/'.$type, function (Request $request) use ($type) {
            return app(ErpController::class)->masterStore($request, $type);
        })->middleware('role:owner,admin')->name('master.store.'.$type);

        Route::put('/master/'.$type.'/{id}', function (Request $request, int $id) use ($type) {
            return app(ErpController::class)->masterUpdate($request, $type, $id);
        })->middleware('role:owner,admin')->name('master.update.'.$type);

        Route::delete('/master/'.$type.'/{id}', function (Request $request, int $id) use ($type) {
            return app(ErpController::class)->masterDelete($request, $type, $id);
        })->middleware('role:owner,admin')->name('master.delete.'.$type);
    }

    $kasirModules = ['pos','sales','payments','shifts'];
    foreach ($kasirModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware($module === 'sales' ? 'role:owner,kasir,akuntansi' : 'role:owner,kasir')->name('erp.'.$module);
    }
    Route::post('/erp/pos/add', [PosController::class, 'add'])->middleware('role:owner,kasir')->name('erp.pos.add');
    Route::put('/erp/pos/item/{id}', [PosController::class, 'updateItem'])->middleware('role:owner,kasir')->name('erp.pos.update');
    Route::post('/erp/pos/item/{id}/remove', [PosController::class, 'removeItem'])->middleware('role:owner,kasir')->name('erp.pos.remove');
    Route::post('/erp/pos/clear', [PosController::class, 'clear'])->middleware('role:owner,kasir')->name('erp.pos.clear');
    Route::post('/erp/pos', [PosController::class, 'store'])->middleware('role:owner,kasir')->name('erp.pos.store');
    Route::post('/erp/shift', [ModuleController::class, 'shiftStore'])->middleware('role:owner,kasir')->name('erp.shift.store');

    $pembelianModules = ['purchases','receipts'];
    foreach ($pembelianModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::get('/erp/payables', function () {
        return app(ModuleController::class)->show('payables');
    })->middleware('role:owner,inventori,akuntansi')->name('erp.payables');
    Route::post('/erp/purchase', [ErpController::class, 'purchaseStore'])->middleware('role:owner,inventori')->name('erp.purchase.store');

    $inventoryModules = ['stock','movements','opname'];
    foreach ($inventoryModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/movement', [ModuleController::class, 'movementStore'])->middleware('role:owner,inventori')->name('erp.movement.store');
    Route::post('/erp/opname', [ModuleController::class, 'opnameStore'])->middleware('role:owner,inventori')->name('erp.opname.store');

    Route::get('/erp/bom', [BomController::class, 'show'])->middleware('role:owner,inventori')->name('erp.bom');
    Route::post('/erp/bom', [BomController::class, 'store'])->middleware('role:owner,inventori')->name('erp.bom.store');

    $productionModules = ['production','production-results','material-usage','production-cost'];
    foreach ($productionModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/production', [ProductionController::class, 'store'])->middleware('role:owner,inventori')->name('erp.production.store');

    $fleetModules = ['fleet','deliveries','operations','fleet-costs'];
    foreach ($fleetModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('role:owner,inventori')->name('erp.'.$module);
    }
    Route::post('/erp/delivery', [ModuleController::class, 'deliveryStore'])->middleware('role:owner,inventori')->name('erp.delivery.store');
    Route::post('/erp/operation', [ModuleController::class, 'operationStore'])->middleware('role:owner,inventori')->name('erp.operation.store');
    Route::post('/erp/fleet-cost', [ModuleController::class, 'fleetCostStore'])->middleware('role:owner,inventori')->name('erp.fleet-cost.store');

    $accountingModules = ['journals','ledger','receivables','cashbank','cogs','profit-loss','balance-sheet','cash-flow'];
    foreach ($accountingModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('role:owner,akuntansi')->name('erp.'.$module);
    }
    Route::post('/erp/journal', [ModuleController::class, 'journalStore'])->middleware('role:owner,akuntansi')->name('erp.journal.store');


    // Clean menu URL aliases — existing /erp endpoints remain for backward compatibility.
    // Final menu paths use singular, business-readable URLs.
    $masterMenuPaths = [
        'produk' => 'products', 'customer' => 'customers', 'supplier' => 'suppliers',
        'gudang' => 'warehouses', 'satuan' => 'units', 'konversi-satuan' => 'unit-conversions',
        'tarif' => 'tariffs', 'kendaraan' => 'vehicles', 'driver' => 'drivers',
    ];
    foreach ($masterMenuPaths as $path => $type) {
        if ($type === 'unit-conversions') {
            Route::get('/master/'.$path, [UnitConversionController::class, 'index'])->middleware('role:owner,admin')->name('master.menu.'.$path);
        } else {
            Route::get('/master/'.$path, function (Request $request) use ($type) {
                return app(ErpController::class)->master($request, $type);
            })->middleware('role:owner,admin')->name('master.menu.'.$path);
        }
    }
    Route::get('/pos/pos', fn () => app(ModuleController::class)->show('pos'))->middleware('role:owner,kasir')->name('pos.pos');
    Route::get('/pos/penjualan', fn () => app(ModuleController::class)->show('sales'))->middleware('role:owner,kasir,akuntansi')->name('pos.penjualan');
    Route::get('/pos/penjualan/data', [ModuleController::class, 'salesData'])->middleware('role:owner,kasir,akuntansi')->name('pos.penjualan.data');
    Route::get('/pos/penjualan/export-excel', [ModuleController::class, 'exportSalesExcel'])->middleware('role:owner,kasir,akuntansi')->name('pos.penjualan.export-excel');
    Route::get('/pos/penjualan/{id}/detail', [ModuleController::class, 'salesDetail'])->middleware('role:owner,kasir,akuntansi')->name('pos.penjualan.detail');
    Route::get('/pos/pembayaran', fn () => app(ModuleController::class)->show('payments'))->middleware('role:owner,kasir')->name('pos.pembayaran');
    Route::get('/pos/pembayaran/data', [ModuleController::class, 'paymentsData'])->middleware('role:owner,kasir')->name('pos.pembayaran.data');
    Route::get('/pos/pembayaran/export-excel', [ModuleController::class, 'exportPaymentsExcel'])->middleware('role:owner,kasir')->name('pos.pembayaran.export-excel');
    Route::get('/pos/pembayaran/{id}/detail', [ModuleController::class, 'paymentDetail'])->middleware('role:owner,kasir')->name('pos.pembayaran.detail');
    Route::get('/pos/retur', [SalesReturnController::class, 'index'])->middleware('role:owner,kasir')->name('pos.retur');
    Route::get('/pos/retur/data', [SalesReturnController::class, 'data'])->middleware('role:owner,kasir')->name('pos.retur.data');
    Route::get('/pos/retur/export-excel', [SalesReturnController::class, 'exportExcel'])->middleware('role:owner,kasir')->name('pos.retur.export-excel');
    Route::get('/pos/retur/lookup', [SalesReturnController::class, 'saleLookup'])->middleware('role:owner,kasir')->name('pos.retur.lookup');
    Route::post('/pos/retur', [SalesReturnController::class, 'store'])->middleware('role:owner,kasir')->name('pos.retur.store');
    Route::get('/pos/shift', fn () => app(ModuleController::class)->show('shifts'))->middleware('role:owner,kasir')->name('pos.shift');
    Route::get('/pos', fn () => app(ModuleController::class)->show('pos'))->middleware('role:owner,kasir')->name('pos');
    Route::get('/penjualan', fn () => app(ModuleController::class)->show('sales'))->middleware('role:owner,kasir,akuntansi')->name('penjualan');
    Route::get('/pembayaran', fn () => app(ModuleController::class)->show('payments'))->middleware('role:owner,kasir')->name('pembayaran');
    Route::get('/shift', fn () => app(ModuleController::class)->show('shifts'))->middleware('role:owner,kasir')->name('shift');
    Route::get('/inventori/pembelian', fn () => app(ModuleController::class)->show('purchases'))->middleware('role:owner,inventori')->name('inventori.pembelian');
    Route::get('/inventori/penerimaan', fn () => app(ModuleController::class)->show('receipts'))->middleware('role:owner,inventori')->name('inventori.penerimaan');
    Route::get('/inventori/stok', fn () => app(ModuleController::class)->show('stock'))->middleware('role:owner,inventori')->name('inventori.stok');
    Route::get('/inventori/transfer', fn () => app(ModuleController::class)->show('movements'))->middleware('role:owner,inventori')->name('inventori.transfer');
    Route::get('/inventori/adjustment', fn () => app(ModuleController::class)->show('movements'))->middleware('role:owner,inventori')->name('inventori.adjustment');
    Route::get('/inventori/stock-opname', fn () => app(ModuleController::class)->show('opname'))->middleware('role:owner,inventori')->name('inventori.stock-opname');
    Route::get('/produksi/bom', fn () => app(BomController::class)->show())->middleware('role:owner,inventori')->name('produksi.bom');
    Route::get('/produksi', fn () => app(ModuleController::class)->show('production'))->middleware('role:owner,inventori')->name('produksi');
    Route::get('/produksi/pemakaian-bahan', fn () => app(ModuleController::class)->show('material-usage'))->middleware('role:owner,inventori')->name('produksi.pemakaian-bahan');
    Route::get('/produksi/hasil-produksi', fn () => app(ModuleController::class)->show('production-results'))->middleware('role:owner,inventori')->name('produksi.hasil-produksi');
    Route::get('/produksi/reject', fn () => app(ModuleController::class)->show('production-results'))->middleware('role:owner,inventori')->name('produksi.reject');
    Route::get('/produksi/hpp', fn () => app(ModuleController::class)->show('production-cost'))->middleware('role:owner,inventori')->name('produksi.hpp');
    Route::get('/armada/order-jasa', fn () => app(ModuleController::class)->show('deliveries'))->middleware('role:owner,inventori')->name('armada.order-jasa');
    Route::get('/armada/surat-jalan', fn () => app(ModuleController::class)->show('deliveries'))->middleware('role:owner,inventori')->name('armada.surat-jalan');
    Route::get('/armada/perjalanan', fn () => app(ModuleController::class)->show('operations'))->middleware('role:owner,inventori')->name('armada.perjalanan');
    Route::get('/akuntansi/akun', [AccountController::class, 'index'])->middleware('role:owner,akuntansi')->name('akuntansi.akun');
    Route::post('/akuntansi/akun', [AccountController::class, 'store'])->middleware('role:owner,akuntansi')->name('akuntansi.akun.store');
    Route::put('/akuntansi/akun/{id}', [AccountController::class, 'update'])->middleware('role:owner,akuntansi')->name('akuntansi.akun.update');
    Route::delete('/akuntansi/akun/{id}', [AccountController::class, 'destroy'])->middleware('role:owner,akuntansi')->name('akuntansi.akun.delete');
    Route::get('/akuntansi/akun/export-excel', [AccountController::class, 'exportExcel'])->middleware('role:owner,akuntansi')->name('akuntansi.akun.export-excel');
    Route::get('/akuntansi/jurnal', fn () => app(ModuleController::class)->show('journals'))->middleware('role:owner,akuntansi')->name('akuntansi.jurnal');
    Route::get('/akuntansi/buku-besar', fn () => app(ModuleController::class)->show('ledger'))->middleware('role:owner,akuntansi')->name('akuntansi.buku-besar');
    Route::get('/akuntansi/kas-bank', fn () => app(ModuleController::class)->show('cashbank'))->middleware('role:owner,akuntansi')->name('akuntansi.kas-bank');
    Route::get('/akuntansi/laba-rugi', fn () => app(ModuleController::class)->show('profit-loss'))->middleware('role:owner,akuntansi')->name('akuntansi.laba-rugi');
    Route::get('/akuntansi/laba-rugi/export-excel', [ModuleController::class, 'exportProfitLossExcel'])->middleware('role:owner,akuntansi')->name('akuntansi.laba-rugi.export-excel');
    Route::get('/akuntansi/neraca-saldo', fn () => app(ModuleController::class)->show('trial-balance'))->middleware('role:owner,akuntansi')->name('akuntansi.neraca-saldo');
    Route::get('/akuntansi/neraca-saldo/export-excel', [ModuleController::class, 'exportTrialBalanceExcel'])->middleware('role:owner,akuntansi')->name('akuntansi.neraca-saldo.export-excel');
    Route::get('/akuntansi/neraca', fn () => app(ModuleController::class)->show('balance-sheet'))->middleware('role:owner,akuntansi')->name('akuntansi.neraca');
    Route::get('/akuntansi/arus-kas', fn () => app(ModuleController::class)->show('cash-flow'))->middleware('role:owner,akuntansi')->name('akuntansi.arus-kas');
    Route::get('/laporan/penjualan', fn () => app(ModuleController::class)->show('sales'))->middleware('role:owner,kasir,akuntansi')->name('laporan.penjualan');
    Route::get('/laporan/pembelian', fn () => app(ModuleController::class)->show('purchases'))->middleware('role:owner,inventori,akuntansi')->name('laporan.pembelian');
    Route::get('/laporan/persediaan', fn () => app(ModuleController::class)->show('stock'))->middleware('role:owner,inventori,akuntansi')->name('laporan.persediaan');
    Route::get('/laporan/produksi', fn () => app(ModuleController::class)->show('production'))->middleware('role:owner,inventori,akuntansi')->name('laporan.produksi');
    Route::get('/laporan/armada-jasa', fn () => app(ModuleController::class)->show('operations'))->middleware('role:owner,inventori,akuntansi')->name('laporan.armada-jasa');
    Route::get('/laporan/piutang', fn () => app(ModuleController::class)->show('receivables'))->middleware('role:owner,akuntansi')->name('laporan.piutang');
    Route::get('/laporan/hutang', fn () => app(ModuleController::class)->show('payables'))->middleware('role:owner,inventori,akuntansi')->name('laporan.hutang');
    Route::get('/laporan/keuangan', fn () => app(ModuleController::class)->show('profit-loss'))->middleware('role:owner,akuntansi')->name('laporan.keuangan');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
