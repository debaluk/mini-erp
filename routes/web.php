<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\HppController;
use App\Http\Controllers\PurchaseReportController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SellingPriceController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\BusinessUnitAccountMappingController;
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

    Route::get('/master/unit-conversions', [UnitConversionController::class, 'index'])->middleware('access:master')->name('master.unit-conversions');
    Route::get('/master/produk/tambah', [ErpController::class, 'itemCreate'])->middleware('access:master')->name('master.item.create');
    Route::get('/master/produk/{id}/edit', [ErpController::class, 'itemEdit'])->middleware('access:master')->name('master.item.edit');
    Route::put('/master/produk/{id}/edit', [ErpController::class, 'itemUpdate'])->middleware('access:master')->name('master.item.update');
    Route::delete('/master/produk/{id}', [ErpController::class, 'itemDelete'])->middleware('access:master')->name('master.item.delete');
    Route::post('/master/produk/tambah', [ErpController::class, 'itemStore'])->middleware('access:master')->name('master.item.store');
    Route::post('/master/produk/inline-uom', [ErpController::class, 'itemInlineUomStore'])->middleware('access:master')->name('master.item.inline-uom.store');
    Route::post('/master/produk/inline-unit', [ErpController::class, 'itemInlineBusinessUnitStore'])->middleware('access:master')->name('master.item.inline-unit.store');
    Route::post('/master/unit-conversions', [UnitConversionController::class, 'store'])->middleware('access:master')->name('master.unit-conversions.store');
    Route::put('/master/unit-conversions/{id}', [UnitConversionController::class, 'update'])->middleware('access:master')->name('master.unit-conversions.update');
    Route::delete('/master/unit-conversions/{id}', [UnitConversionController::class, 'destroy'])->middleware('access:master')->name('master.unit-conversions.delete');

    $masterTypes = ['products','customers','suppliers','warehouses','units'];
    foreach ($masterTypes as $type) {
        if ($type === 'units') {
            Route::get('/master/units', [ErpController::class, 'unitMaster'])->middleware('access:master')->name('master.units');
            Route::post('/master/units', [ErpController::class, 'unitStore'])->middleware('access:master')->name('master.store.units');
            Route::put('/master/units/{id}', [ErpController::class, 'unitUpdate'])->middleware('access:master')->name('master.update.units');
            Route::delete('/master/units/{id}', [ErpController::class, 'unitDelete'])->middleware('access:master')->name('master.delete.units');
        } else {
            Route::get('/master/'.$type, function (Request $request) use ($type) {
                return app(ErpController::class)->master($request, $type);
            })->middleware('access:master')->name('master.'.$type);

            Route::post('/master/'.$type, function (Request $request) use ($type) {
                return app(ErpController::class)->masterStore($request, $type);
            })->middleware('access:master')->name('master.store.'.$type);

            Route::put('/master/'.$type.'/{id}', function (Request $request, int $id) use ($type) {
                return app(ErpController::class)->masterUpdate($request, $type, $id);
            })->middleware('access:master')->name('master.update.'.$type);

            Route::delete('/master/'.$type.'/{id}', function (Request $request, int $id) use ($type) {
                return app(ErpController::class)->masterDelete($request, $type, $id);
            })->middleware('access:master')->name('master.delete.'.$type);
        }
    }

    $kasirModules = ['sales','payments'];
    foreach ($kasirModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('access:inventori')->name('erp.'.$module);
    }
    $pembelianModules = ['purchases','receipts'];
    foreach ($pembelianModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('access:inventori')->name('erp.'.$module);
    }
    Route::get('/erp/payables', function () { return app(ModuleController::class)->show('payables'); })->middleware('access:keuangan')->name('erp.payables');
    Route::post('/erp/purchase', [ErpController::class, 'purchaseStore'])->middleware('access:inventori')->name('erp.purchase.store');

    $inventoryModules = ['stock','movements','opname'];
    foreach ($inventoryModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('access:inventori')->name('erp.'.$module);
    }
    Route::post('/erp/movement', [ModuleController::class, 'movementStore'])->middleware('access:inventori')->name('erp.movement.store');
    Route::post('/erp/opname', [ModuleController::class, 'opnameStore'])->middleware('access:inventori')->name('erp.opname.store');

    Route::get('/erp/bom', [BomController::class, 'show'])->middleware('access:master')->name('erp.bom');
    Route::post('/erp/bom', [BomController::class, 'store'])->middleware('access:master')->name('erp.bom.store');

    $productionModules = ['production','production-results','material-usage','production-cost'];
    foreach ($productionModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('access:inventori')->name('erp.'.$module);
    }
    Route::post('/erp/production', [ProductionController::class, 'store'])->middleware('access:inventori')->name('erp.production.store');

    $accountingModules = ['journals','ledger','receivables','cashbank','cogs','profit-loss','balance-sheet','cash-flow'];
    foreach ($accountingModules as $module) {
        Route::get('/erp/'.$module, function () use ($module) {
            return app(ModuleController::class)->show($module);
        })->middleware('access:keuangan')->name('erp.'.$module);
    }
    Route::post('/erp/journal', [ModuleController::class, 'journalStore'])->middleware('access:keuangan')->name('erp.journal.store');

    Route::get('/master/unit', function (Request $request) {
        return app(ErpController::class)->master($request, 'units');
    })->middleware('access:master')->name('master.menu.unit');

    $masterMenuPaths = [
        'produk' => 'products', 'customer' => 'customers', 'supplier' => 'suppliers',
        'gudang' => 'warehouses', 'satuan' => 'units',
        'konversi-satuan' => 'unit-conversions',
    ];
    Route::get('/master/harga-jual', [ProductPriceController::class, 'index'])->middleware('access:master')->name('master.menu.harga-jual');
    Route::get('/master/harga-jual/export', [ProductPriceController::class, 'export'])->middleware('access:master')->name('master.harga-jual.export');
    Route::post('/master/harga-jual', [ProductPriceController::class, 'store'])->middleware('access:master')->name('master.harga-jual.store');
    Route::get('/master/harga-jual/history', [ProductPriceController::class, 'history'])->middleware('access:master')->name('master.harga-jual.history');
    Route::put('/master/harga-jual/{id}', [ProductPriceController::class, 'update'])->middleware('access:master')->name('master.harga-jual.update');

    Route::get('/inventori/initial-setup', [SellingPriceController::class, 'index'])->middleware('access:inventori')->name('inventori.initial-setup');
    Route::post('/inventori/initial-setup', [SellingPriceController::class, 'storeInitial'])->middleware('access:inventori')->name('inventori.initial-setup.store');
    Route::put('/inventori/initial-setup/{product}', [SellingPriceController::class, 'update'])->middleware('access:inventori')->name('inventori.initial-setup.update');

    foreach ($masterMenuPaths as $path => $type) {
        if ($type === 'unit-conversions') {
            Route::get('/master/'.$path, [UnitConversionController::class, 'index'])->middleware('access:master')->name('master.menu.'.$path);
        } elseif ($path === 'satuan') {
            Route::get('/master/satuan', [ErpController::class, 'unitMaster'])->middleware('access:master')->name('master.menu.satuan');
        } else {
            if ($path === 'produk') {
                Route::get('/master/'.$path, [ErpController::class, 'itemMaster'])->middleware('access:master')->name('master.menu.'.$path);
            } else {
                Route::get('/master/'.$path, function (Request $request) use ($type) {
                    return app(ErpController::class)->master($request, $type);
                })->middleware('access:master')->name('master.menu.'.$path);
            }
        }
    }

    Route::get('/pos/penjualan', fn () => app(ModuleController::class)->show('sales'))->middleware('access:inventori')->name('pos.penjualan');
    Route::get('/inventori/penjualan', [SalesController::class, 'index'])->middleware('access:inventori')->name('inventori.penjualan');
    Route::get('/inventori/penjualan/create', [SalesController::class, 'create'])->middleware('access:inventori')->name('inventori.penjualan.create');
    Route::get('/inventori/penjualan/export-data', [SalesController::class, 'export'])->middleware('access:inventori')->name('inventori.penjualan.export-data');
    Route::post('/inventori/penjualan', [SalesController::class, 'store'])->middleware('access:inventori')->name('inventori.penjualan.store');
    Route::get('/inventori/penjualan/{id}', [SalesController::class, 'show'])->middleware('access:inventori')->name('inventori.penjualan.show');
    Route::get('/inventori/penjualan/{id}/print', [SalesController::class, 'print'])->middleware('access:inventori')->name('inventori.penjualan.print');
    Route::get('/pos/penjualan/data', [ModuleController::class, 'salesData'])->middleware('access:inventori')->name('pos.penjualan.data');
    Route::get('/pos/penjualan/export-excel', [ModuleController::class, 'exportSalesExcel'])->middleware('access:inventori')->name('pos.penjualan.export-excel');
    Route::get('/pos/penjualan/{id}/detail', [ModuleController::class, 'salesDetail'])->middleware('access:inventori')->name('pos.penjualan.detail');
    Route::get('/pos/pembayaran', fn () => app(ModuleController::class)->show('payments'))->middleware('access:inventori')->name('pos.pembayaran');
    Route::get('/pos/pembayaran/data', [ModuleController::class, 'paymentsData'])->middleware('access:inventori')->name('pos.pembayaran.data');
    Route::get('/pos/pembayaran/export-excel', [ModuleController::class, 'exportPaymentsExcel'])->middleware('access:inventori')->name('pos.pembayaran.export-excel');
    Route::get('/pos/pembayaran/{id}/detail', [ModuleController::class, 'paymentDetail'])->middleware('access:inventori')->name('pos.pembayaran.detail');
    Route::get('/pos/retur', [SalesReturnController::class, 'index'])->middleware('access:inventori')->name('pos.retur');
    Route::get('/pos/retur/data', [SalesReturnController::class, 'data'])->middleware('access:inventori')->name('pos.retur.data');
    Route::get('/pos/retur/export-excel', [SalesReturnController::class, 'exportExcel'])->middleware('access:inventori')->name('pos.retur.export-excel');
    Route::get('/pos/retur/lookup', [SalesReturnController::class, 'saleLookup'])->middleware('access:inventori')->name('pos.retur.lookup');
    Route::post('/pos/retur', [SalesReturnController::class, 'store'])->middleware('access:inventori')->name('pos.retur.store');


    Route::get('/penjualan', fn () => app(ModuleController::class)->show('sales'))->middleware('access:inventori')->name('penjualan');
    Route::get('/pembayaran', fn () => app(ModuleController::class)->show('payments'))->middleware('access:inventori')->name('pembayaran');

    Route::get('/inventori/pembelian', [PurchaseController::class, 'index'])->middleware('access:inventori')->name('inventori.pembelian');
    Route::get('/inventori/pembelian/create', [PurchaseController::class, 'create'])->middleware('access:inventori')->name('inventori.pembelian.create');
    Route::get('/inventori/pembelian/{id}/edit', [PurchaseController::class, 'edit'])->middleware('access:inventori')->name('inventori.pembelian.edit');
    Route::get('/inventori/penerimaan', [ReceiptController::class, 'index'])->middleware('access:inventori')->name('inventori.penerimaan');
    Route::get('/inventori/penerimaan/create', [ReceiptController::class, 'create'])->middleware('access:inventori')->name('inventori.penerimaan.create');
    Route::get('/inventori/penerimaan/{id}/edit', [ReceiptController::class, 'edit'])->middleware('access:inventori')->name('inventori.penerimaan.edit');
    Route::get('/inventori/stok', [StockController::class, 'index'])->middleware('access:inventori')->name('inventori.stok');
    Route::get('/inventori/stok/export', [StockController::class, 'export'])->middleware('access:inventori')->name('inventori.stok.export');
    Route::get('/inventori/stok/{product}/{warehouse}', [StockController::class, 'detail'])->middleware('access:inventori')->name('inventori.stok.detail');
    Route::get('/inventori/transfer', fn () => app(ModuleController::class)->show('movements'))->middleware('access:inventori')->name('inventori.transfer');
    Route::get('/inventori/adjustment', fn () => app(ModuleController::class)->show('movements'))->middleware('access:inventori')->name('inventori.adjustment');
    Route::get('/inventori/stock-opname', fn () => app(ModuleController::class)->show('opname'))->middleware('access:inventori')->name('inventori.stock-opname');
    Route::get('/produksi/bom', fn () => app(BomController::class)->show())->middleware('access:inventori')->name('produksi.bom');
    Route::get('/produksi', fn () => app(ModuleController::class)->show('production'))->middleware('access:inventori')->name('produksi');
    Route::get('/produksi/pemakaian-bahan', fn () => app(ModuleController::class)->show('material-usage'))->middleware('access:inventori')->name('produksi.pemakaian-bahan');
    Route::get('/produksi/hasil-produksi', fn () => app(ModuleController::class)->show('production-results'))->middleware('access:inventori')->name('produksi.hasil-produksi');
    Route::get('/produksi/reject', fn () => app(ModuleController::class)->show('production-results'))->middleware('access:inventori')->name('produksi.reject');
    Route::get('/produksi/hpp', [HppController::class, 'index'])->middleware('access:inventori')->name('produksi.hpp');
    Route::get('/master/akun', [AccountController::class, 'index'])->middleware('access:master')->name('master.akun');
    Route::post('/master/akun', [AccountController::class, 'store'])->middleware('access:master')->name('master.akun.store');
    Route::put('/master/akun/{id}', [AccountController::class, 'update'])->middleware('access:master')->name('master.akun.update');
    Route::delete('/master/akun/{id}', [AccountController::class, 'destroy'])->middleware('access:master')->name('master.akun.delete');
    Route::get('/master/akun/export-excel', [AccountController::class, 'exportExcel'])->middleware('access:master')->name('master.akun.export-excel');
    Route::get('/akuntansi/jurnal', fn () => app(ModuleController::class)->show('journals'))->middleware('access:keuangan')->name('akuntansi.jurnal');
    Route::get('/akuntansi/buku-besar', fn () => app(ModuleController::class)->show('ledger'))->middleware('access:keuangan')->name('akuntansi.buku-besar');
    Route::get('/akuntansi/kas-bank', fn () => app(ModuleController::class)->show('cashbank'))->middleware('access:keuangan')->name('akuntansi.kas-bank');
    Route::get('/akuntansi/laba-rugi', fn () => app(ModuleController::class)->show('profit-loss'))->middleware('access:keuangan')->name('akuntansi.laba-rugi');
    Route::get('/akuntansi/laba-rugi/export-excel', [ModuleController::class, 'exportProfitLossExcel'])->middleware('access:keuangan')->name('akuntansi.laba-rugi.export-excel');
    Route::get('/akuntansi/neraca-saldo', fn () => app(ModuleController::class)->show('trial-balance'))->middleware('access:keuangan')->name('akuntansi.neraca-saldo');
    Route::get('/akuntansi/neraca-saldo/export-excel', [ModuleController::class, 'exportTrialBalanceExcel'])->middleware('access:keuangan')->name('akuntansi.neraca-saldo.export-excel');
    Route::get('/akuntansi/neraca', fn () => app(ModuleController::class)->show('balance-sheet'))->middleware('access:keuangan')->name('akuntansi.neraca');
    Route::get('/akuntansi/neraca/export-excel', [ModuleController::class, 'exportBalanceSheetExcel'])->middleware('access:keuangan')->name('akuntansi.neraca.export-excel');
    Route::get('/akuntansi/arus-kas', fn () => app(ModuleController::class)->show('cash-flow'))->middleware('access:keuangan')->name('akuntansi.arus-kas');
    Route::get('/akuntansi/arus-kas/export-excel', [ModuleController::class, 'exportCashFlowExcel'])->middleware('access:keuangan')->name('akuntansi.arus-kas.export-excel');

    Route::get('/laporan/penjualan', fn () => app(ModuleController::class)->show('sales'))->middleware('access:inventori')->name('laporan.penjualan');
    Route::get('/laporan/pembelian', [PurchaseReportController::class, 'index'])->middleware('access:inventori')->name('laporan.pembelian');
    Route::get('/laporan/pembelian/export', [PurchaseReportController::class, 'export'])->middleware('access:inventori')->name('laporan.pembelian.export');
    Route::get('/laporan/persediaan', fn () => app(ModuleController::class)->show('stock'))->middleware('access:inventori')->name('laporan.persediaan');
    Route::get('/laporan/produksi', fn () => app(ModuleController::class)->show('production'))->middleware('access:inventori')->name('laporan.produksi');
    Route::get('/laporan/piutang', fn () => app(ModuleController::class)->show('receivables'))->middleware('access:keuangan')->name('laporan.piutang');
    Route::get('/laporan/hutang', fn () => app(ModuleController::class)->show('payables'))->middleware('access:keuangan')->name('laporan.hutang');
    Route::get('/laporan/keuangan', fn () => app(ModuleController::class)->show('profit-loss'))->middleware('access:keuangan')->name('laporan.keuangan');

    Route::get('/pengaturan/entitas', [SettingsController::class, 'entity'])->middleware('access:pengaturan')->name('pengaturan.entitas');
    Route::put('/pengaturan/entitas', [SettingsController::class, 'entityUpdate'])->middleware('access:pengaturan')->name('pengaturan.entitas.update');
    Route::get('/pengaturan/user', [SettingsController::class, 'users'])->middleware('access:pengaturan')->name('pengaturan.user');
    Route::post('/pengaturan/user', [SettingsController::class, 'userStore'])->middleware('access:pengaturan')->name('pengaturan.user.store');
    Route::put('/pengaturan/user/{id}', [SettingsController::class, 'userUpdate'])->middleware('access:pengaturan')->name('pengaturan.user.update');
    Route::patch('/pengaturan/user/{id}/toggle', [SettingsController::class, 'userToggle'])->middleware('access:pengaturan')->name('pengaturan.user.toggle');
    Route::get('/pengaturan/role', [SettingsController::class, 'roles'])->middleware('access:pengaturan')->name('pengaturan.role');
    Route::get('/pengaturan/konfigurasi', [SettingsController::class, 'configuration'])->middleware('access:pengaturan')->name('pengaturan.konfigurasi');
    Route::get('/pengaturan/konfigurasi/unit-bisnis', [BusinessUnitController::class, 'index'])->middleware('access:master')->name('pengaturan.unit-bisnis');
    Route::get('/pengaturan/konfigurasi/unit-bisnis/{id}/edit', [BusinessUnitController::class, 'edit'])->middleware('access:master')->name('pengaturan.unit-bisnis.edit');
    Route::post('/pengaturan/konfigurasi/unit-bisnis', [BusinessUnitController::class, 'store'])->middleware('access:master')->name('pengaturan.unit-bisnis.store');
    Route::put('/pengaturan/konfigurasi/unit-bisnis/{id}', [BusinessUnitController::class, 'update'])->middleware('access:master')->name('pengaturan.unit-bisnis.update');
    Route::delete('/pengaturan/konfigurasi/unit-bisnis/{id}', [BusinessUnitController::class, 'destroy'])->middleware('access:master')->name('pengaturan.unit-bisnis.destroy');
    Route::get('/pengaturan/konfigurasi/mapping-account', [BusinessUnitAccountMappingController::class, 'index'])->middleware('access:pengaturan')->name('pengaturan.account-mapping');
    Route::post('/pengaturan/konfigurasi/mapping-account', [BusinessUnitAccountMappingController::class, 'save'])->middleware('access:master')->name('pengaturan.account-mapping.save');
    Route::post('/pengaturan/konfigurasi/mapping-warehouse', [SettingsController::class, 'warehouseMappingSave'])->middleware('access:master')->name('pengaturan.warehouse-mapping.save');

    
    Route::get('/inventori/pembelian/po', [PurchaseOrderController::class, 'index'])
        ->middleware('access:inventori')
        ->name('inventori.pembelian-po');

    Route::get('/inventori/pembelian/po/create', [PurchaseOrderController::class, 'create'])
        ->middleware('access:inventori')
        ->name('inventori.pembelian-po.create');

    Route::get('/inventori/pembelian/retur', fn () => view('inventori.pembelian.retur.index'))
        ->middleware('access:inventori')
        ->name('inventori.retur-pembelian');

    Route::get('/produksi/work-order', fn () => view('produksi.work-order'))
        ->middleware('access:inventori')
        ->name('produksi.work-order');

    Route::get('/inventori/monitoring/margin-harga', fn () => view('inventori.laporan.analisa-margin'))
        ->middleware('access:inventori')
        ->name('inventori.margin-control');

    Route::get('/akuntansi/closing-periode', fn () => view('akuntansi.closing-periode'))
        ->middleware('access:keuangan')
        ->name('akuntansi.closing-periode');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/master/pekerja', function (Request $request) {
        return app(ErpController::class)->master($request, 'workers');
    })->middleware('access:master')->name('master.menu.pekerja');

    Route::post('/master/pekerja', function (Request $request) {
        return app(ErpController::class)->masterStore($request, 'workers');
    })->middleware('access:master')->name('master.pekerja.store');

    Route::put('/master/pekerja/{id}', function (Request $request, int $id) {
        return app(ErpController::class)->masterUpdate($request, 'workers', $id);
    })->middleware('access:master')->name('master.pekerja.update');

    Route::delete('/master/pekerja/{id}', function (Request $request, int $id) {
        return app(ErpController::class)->masterDelete($request, 'workers', $id);
    })->middleware('access:master')->name('master.pekerja.delete');
});
