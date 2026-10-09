<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PurchaseController;		//tidak terpakai
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\HppController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductionWorkOrderController;
use App\Http\Controllers\ProductionMaterialUsageController;
use App\Http\Controllers\PurchaseReportController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\SalesReturnReportController;
use App\Http\Controllers\SalesReceivableReportController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockInventoryController;
use App\Http\Controllers\InitialSetupController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\BusinessUnitAccountMappingController;
use App\Http\Controllers\UnitConversionController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\TrialBalanceController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\AccountingClosingController;
use App\Http\Controllers\CashReceiptController;
use App\Http\Controllers\CashDisbursementController;
use App\Http\Controllers\CashTransferController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\ArSubLedgerController;
use App\Http\Controllers\ApSubLedgerController;
use App\Http\Controllers\ArAgingController;
use App\Http\Controllers\ApAgingController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\PurchaseOperationalReportController;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.process');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ============================================================
    // MASTER
    // ============================================================
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

    Route::get('/master/harga-jual', [ProductPriceController::class, 'index'])->middleware('access:master')->name('master.menu.harga-jual');
    Route::get('/master/harga-jual/export', [ProductPriceController::class, 'export'])->middleware('access:master')->name('master.harga-jual.export');
    Route::post('/master/harga-jual', [ProductPriceController::class, 'store'])->middleware('access:master')->name('master.harga-jual.store');
    Route::post('/master/harga-jual/sync-initial-setup', [ProductPriceController::class, 'syncInitialSetup'])->middleware('access:master')->name('master.harga-jual.sync-initial-setup');
    Route::get('/master/harga-jual/history', [ProductPriceController::class, 'history'])->middleware('access:master')->name('master.harga-jual.history');
    Route::put('/master/harga-jual/{id}', [ProductPriceController::class, 'update'])->middleware('access:master')->name('master.harga-jual.update');

    Route::get('/master/bom', [BomController::class, 'show'])
        ->middleware('access:master')
        ->name('master.bom');
    Route::post('/master/bom', [BomController::class, 'store'])
        ->middleware('access:master')
        ->name('master.bom.store');
    Route::put('/master/bom/{id}', [BomController::class, 'update'])
        ->middleware('access:master')
        ->name('master.bom.update');
    Route::delete('/master/bom/{id}', [BomController::class, 'destroy'])
        ->middleware('access:master')
        ->name('master.bom.delete');
    Route::get('/master/bom/{id}/print', [BomController::class, 'print'])
        ->middleware('access:master')
        ->name('master.bom.print');
    
    Route::get('/master/akun', [AccountController::class, 'index'])->middleware('access:master')->name('master.akun');
    Route::post('/master/akun', [AccountController::class, 'store'])->middleware('access:master')->name('master.akun.store');
    Route::put('/master/akun/{id}', [AccountController::class, 'update'])->middleware('access:master')->name('master.akun.update');
    Route::delete('/master/akun/{id}', [AccountController::class, 'destroy'])->middleware('access:master')->name('master.akun.delete');
    Route::get('/master/akun/export-excel', [AccountController::class, 'exportExcel'])->middleware('access:master')->name('master.akun.export-excel');

    Route::get('/pengaturan/konfigurasi/unit-bisnis', [BusinessUnitController::class, 'index'])->middleware('access:master')->name('pengaturan.unit-bisnis');
    Route::get('/pengaturan/konfigurasi/unit-bisnis/{id}/edit', [BusinessUnitController::class, 'edit'])->middleware('access:master')->name('pengaturan.unit-bisnis.edit');
    Route::post('/pengaturan/konfigurasi/unit-bisnis', [BusinessUnitController::class, 'store'])->middleware('access:master')->name('pengaturan.unit-bisnis.store');
    Route::put('/pengaturan/konfigurasi/unit-bisnis/{id}', [BusinessUnitController::class, 'update'])->middleware('access:master')->name('pengaturan.unit-bisnis.update');
    Route::delete('/pengaturan/konfigurasi/unit-bisnis/{id}', [BusinessUnitController::class, 'destroy'])->middleware('access:master')->name('pengaturan.unit-bisnis.destroy');

    // ============================================================
    // INVENTORI
    // ============================================================
    Route::get('/inventori/initial-setup', [InitialSetupController::class, 'index'])->middleware('access:inventori')->name('inventori.initial-setup');
    Route::post('/inventori/initial-setup', [InitialSetupController::class, 'storeInitial'])->middleware('access:inventori')->name('inventori.initial-setup.store');
    Route::put('/inventori/initial-setup/{product}', [InitialSetupController::class, 'update'])->middleware('access:inventori')->name('inventori.initial-setup.update');

    // POS
    Route::get('/pos', [PosController::class, 'create'])->middleware('access:pos')->name('pos');
    Route::get('/pos/penjualan/create', [PosController::class, 'create'])->middleware('access:pos')->name('pos.penjualan.create');
    Route::post('/pos/penjualan', [PosController::class, 'store'])->middleware('access:pos')->name('pos.penjualan.store');
    Route::get('/pos/penjualan/{id}/print', [PosController::class, 'print'])->middleware('access:pos')->name('pos.penjualan.print');

    Route::get('/inventori/penjualan', [SalesController::class, 'index'])->middleware('access:inventori')->name('inventori.penjualan');
    Route::get('/inventori/penjualan/create', [SalesController::class, 'create'])->middleware('access:inventori')->name('inventori.penjualan.create');
    Route::get('/inventori/penjualan/export-data', [SalesController::class, 'export'])->middleware('access:inventori')->name('inventori.penjualan.export-data');
    Route::post('/inventori/penjualan', [SalesController::class, 'store'])->middleware('access:inventori')->name('inventori.penjualan.store');
    Route::get('/pos/penjualan/data', [PosController::class, 'salesData'])->middleware('access:pos')->name('pos.penjualan.data');
    Route::get('/pos/penjualan/export-excel', [PosController::class, 'exportSalesExcel'])->middleware('access:pos')->name('pos.penjualan.export-excel');
    Route::get('/pos/penjualan/{id}/detail', [PosController::class, 'salesDetail'])->middleware('access:pos')->name('pos.penjualan.detail');
    //Route::get('/inventori/penjualan/retur', [SalesReturnController::class, 'index'])->middleware('access:inventori')->name('inventori.penjualan.retur');
    //Route::get('/inventori/penjualan/retur/data', [SalesReturnController::class, 'data'])->middleware('access:inventori')->name('inventori.penjualan.retur.data');
    //Route::get('/inventori/penjualan/retur/{id}/print', [SalesReturnController::class, 'print'])->middleware('access:inventori')->name('inventori.penjualan.retur.print');
    //Route::get('/inventori/penjualan/retur/export-excel', [SalesReturnController::class, 'exportExcel'])->middleware('access:inventori')->name('inventori.penjualan.retur.export-excel');
    //Route::get('/inventori/penjualan/retur/lookup', [SalesReturnController::class, 'saleLookup'])->middleware('access:inventori')->name('inventori.penjualan.retur.lookup');
    //Route::post('/inventori/penjualan/retur', [SalesReturnController::class, 'store'])->middleware('access:inventori')->name('inventori.penjualan.retur.store');
Route::middleware(['auth', 'access:inventori'])->prefix('inventori/penjualan/retur')->group(function () {
    Route::get('/', [SalesReturnController::class, 'index'])->name('inventori.penjualan.retur');
    Route::get('/data', [SalesReturnController::class, 'data'])->name('inventori.penjualan.retur.data');
    Route::get('/lookup-invoices', [SalesReturnController::class, 'lookupInvoices'])->name('inventori.penjualan.retur.lookup-invoices');
    Route::get('/sale-items/{saleId}', [SalesReturnController::class, 'saleItems'])->name('inventori.penjualan.retur.sale-items');
    Route::post('/store', [SalesReturnController::class, 'store'])->name('inventori.penjualan.retur.store');
    Route::delete('/{id}', [SalesReturnController::class, 'destroy'])->name('inventori.penjualan.retur.destroy');
    Route::get('/{id}/edit', [SalesReturnController::class, 'edit'])->name('inventori.penjualan.retur.edit');
    Route::get('/{id}/print-data', [SalesReturnController::class, 'printData'])->name('inventori.penjualan.retur.print-data');
    Route::get('/export', [SalesReturnController::class, 'export'])->name('inventori.penjualan.retur.export');
});
    Route::get('/inventori/penjualan/{id}/edit', [SalesController::class, 'edit'])->middleware('access:inventori')->name('inventori.penjualan.edit');
    Route::put('/inventori/penjualan/{id}', [SalesController::class, 'update'])->middleware('access:inventori')->name('inventori.penjualan.update');
    Route::delete('/inventori/penjualan/{id}', [SalesController::class, 'destroy'])->middleware('access:inventori')->name('inventori.penjualan.destroy');
    Route::get('/inventori/penjualan/{id}', [SalesController::class, 'show'])->middleware('access:inventori')->name('inventori.penjualan.show');
    Route::get('/inventori/penjualan/{id}/print', [SalesController::class, 'print'])->middleware('access:inventori')->name('inventori.penjualan.print');

    Route::middleware(['auth', 'access:inventori'])->prefix('inventori/pembelian/retur')->name('inventori.pembelian.retur')->group(function () {
        Route::get('/', [PurchaseReturnController::class, 'index'])->name('');
        Route::get('/data', [PurchaseReturnController::class, 'data'])->name('.data');
        Route::get('/print-list', [PurchaseReturnController::class, 'printList'])->name('.print-list');
        Route::get('/from-receipt/{receiptId}', [PurchaseReturnController::class, 'createFromReceipt'])->name('.from-receipt');
        Route::post('/from-receipt/{receiptId}', [PurchaseReturnController::class, 'storeFromReceipt'])->name('.store-from-receipt');
        Route::get('/{id}', [PurchaseReturnController::class, 'show'])->name('.show');
        Route::get('/{id}/print', [PurchaseReturnController::class, 'printDetail'])->name('.print');
        Route::post('/{id}/post', [PurchaseReturnController::class, 'post'])->name('.post');
        Route::post('/{id}/cancel', [PurchaseReturnController::class, 'cancel'])->name('.cancel');
    });

    //Route::get('/inventori/pembelian', [PurchaseController::class, 'index'])->middleware('access:inventori')->name('inventori.pembelian');
    //Route::get('/inventori/pembelian/create', [PurchaseController::class, 'create'])->middleware('access:inventori')->name('inventori.pembelian.create');
    //Route::get('/inventori/pembelian/{id}/edit', [PurchaseController::class, 'edit'])->middleware('access:inventori')->name('inventori.pembelian.edit');
    Route::get('/inventori/penerimaan', [ReceiptController::class, 'index'])->middleware('access:inventori')->name('inventori.penerimaan');
    Route::get('/inventori/penerimaan/data', [ReceiptController::class, 'data'])->middleware('access:inventori')->name('inventori.penerimaan.data');
    Route::get('/inventori/penerimaan/export', [ReceiptController::class, 'export'])->middleware('access:inventori')->name('inventori.penerimaan.export');
    Route::get('/inventori/penerimaan/create', [ReceiptController::class, 'create'])->middleware('access:inventori')->name('inventori.penerimaan.create');
    Route::get('/inventori/penerimaan/po/{id}/modal', [ReceiptController::class, 'poModal'])->middleware('access:inventori')->name('inventori.penerimaan.po-modal');
    Route::post('/inventori/penerimaan', [ReceiptController::class, 'store'])->middleware('access:inventori')->name('inventori.penerimaan.store');
Route::post('/inventori/penerimaan/{id}/cancel', [ReceiptController::class, 'cancel'])->middleware('access:inventori')->name('inventori.penerimaan.cancel');
    Route::get('/inventori/penerimaan/{id}/print', [ReceiptController::class, 'print'])->middleware('access:inventori')->name('inventori.penerimaan.print');
    Route::get('/inventori/penerimaan/{id}', [ReceiptController::class, 'show'])->middleware('access:inventori')->name('inventori.penerimaan.show');
    Route::middleware(['auth', 'access:inventori'])->prefix('inventori/setok-persediaan')->name('inventori.setok-persediaan.')->group(function () {
        Route::get('/', [StockInventoryController::class, 'index'])->name('index');
        Route::get('/export', [StockInventoryController::class, 'export'])->name('export');
        Route::get('/{product}/{warehouse}/history', [StockInventoryController::class, 'history'])->name('history');
    });
    Route::get('/inventori/stok', [StockController::class, 'index'])->middleware('access:inventori')->name('inventori.stok');
    Route::get('/inventori/stok/export', [StockController::class, 'export'])->middleware('access:inventori')->name('inventori.stok.export');
    Route::get('/inventori/stok/{product}/{warehouse}', [StockController::class, 'detail'])->middleware('access:inventori')->name('inventori.stok.detail');
    //Route::get('/inventori/transfer', fn () => app(ModuleController::class)->show('movements'))->middleware('access:inventori')->name('inventori.transfer');
    //Route::get('/inventori/adjustment', fn () => app(ModuleController::class)->show('movements'))->middleware('access:inventori')->name('inventori.adjustment');
    Route::get('/inventori/stock-opname', fn () => app(ModuleController::class)->show('opname'))->middleware('access:inventori')->name('inventori.stock-opname');

 
Route::middleware(['auth', 'access:inventori'])->prefix('inventori/pembelian/po')->name('inventori.pembelian.po.')->group(function () {
    // 1. Endpoint AJAX DataTables & Info Supplier
    Route::get('/data', [PurchaseOrderController::class, 'data'])->name('data');
    Route::get('/supplier-info/{id}', [PurchaseOrderController::class, 'getSupplierInfo'])->name('supplier-info');
    Route::get('/products', [PurchaseOrderController::class, 'products'])->name('products');
    Route::get('/print-list', [PurchaseOrderController::class, 'printList'])->name('print-list');

    // 2. CRUD Utama
    Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
    Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
    Route::post('/{id}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
    Route::get('/{id}/edit-data', [PurchaseOrderController::class, 'getEditData'])->name('edit-data');
    Route::put('/{id}', [PurchaseOrderController::class, 'update'])->name('update');
    Route::delete('/{id}', [PurchaseOrderController::class, 'destroy'])->name('destroy');

    // 3. Export harus sebelum wildcard /{id}
    Route::get('/export-excel', [PurchaseOrderController::class, 'exportExcel'])->name('export-excel');

    // 4. Detail, Close PO, & Cetak PO
    Route::get('/{id}', [PurchaseOrderController::class, 'show'])->name('show');
    Route::post('/{id}/close', [PurchaseOrderController::class, 'closePo'])->name('close');
    Route::get('/{id}/print', [PurchaseOrderController::class, 'printPo'])->name('print');
});

Route::middleware(['auth', 'access:inventori'])->prefix('inventori/pembelian/faktur')->name('inventori.pembelian.faktur.')->group(function () {
    Route::get('/data', [PurchaseInvoiceController::class, 'data'])->name('data');
    Route::get('/export-excel', [PurchaseInvoiceController::class, 'exportExcel'])->name('export-excel');
    Route::get('/print-list', [PurchaseInvoiceController::class, 'printList'])->name('print-list');
    Route::get('/lookup/product', [PurchaseInvoiceController::class, 'lookupProducts'])->name('lookup-product');
    Route::get('/lookup/po', [PurchaseInvoiceController::class, 'lookupPurchaseOrders'])->name('lookup-po');
    Route::get('/po-items/{poId}', [PurchaseInvoiceController::class, 'getPoItems'])->name('po-items');
    Route::get('/', [PurchaseInvoiceController::class, 'index'])->name('index');
    Route::get('/create', [PurchaseInvoiceController::class, 'create'])->name('create');
    Route::post('/', [PurchaseInvoiceController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [PurchaseInvoiceController::class, 'edit'])->name('edit');
    Route::put('/{id}', [PurchaseInvoiceController::class, 'update'])->name('update');
    Route::delete('/{id}', [PurchaseInvoiceController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/print', [PurchaseInvoiceController::class, 'printInvoice'])->name('print');
    Route::get('/{id}', [PurchaseInvoiceController::class, 'show'])->name('show');
});

Route::middleware(['auth', 'access:inventori'])->prefix('inventori/pembelian')->name('inventori.pembelian.')->group(function () {
    // 1. Endpoint DataTables AJAX, Export Excel, & Cetak List Rekap
    Route::get('/data', [PurchaseInvoiceController::class, 'data'])->name('data');
    Route::get('/export-excel', [PurchaseInvoiceController::class, 'exportExcel'])->name('export-excel');
    Route::get('/print-list', [PurchaseInvoiceController::class, 'printList'])->name('print-list');
    Route::get('/po-items/{poId}', [PurchaseInvoiceController::class, 'getPoItems'])->name('po-items');

    // 2. CRUD Faktur Pembelian (URL Link: /inventori/pembelian)
    Route::get('/', [PurchaseInvoiceController::class, 'index'])->name('index');
    Route::get('/create', [PurchaseInvoiceController::class, 'create'])->name('create');
    Route::post('/', [PurchaseInvoiceController::class, 'store'])->name('store');
    Route::get('/{id}', [PurchaseInvoiceController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [PurchaseInvoiceController::class, 'edit'])->name('edit');
    Route::put('/{id}', [PurchaseInvoiceController::class, 'update'])->name('update');
    Route::delete('/{id}', [PurchaseInvoiceController::class, 'destroy'])->name('destroy');

    // 3. Action Posting & Cetak Nota Faktur Dotmatrix
    Route::get('/{id}/print-invoice', [PurchaseInvoiceController::class, 'printInvoice'])->name('print-invoice');
});

Route::middleware(['auth', 'access:inventori'])->prefix('inventori/transfer')->name('inventori.transfer.')->group(function () {
    Route::get('/', [StockTransferController::class, 'index'])->name('index');
    Route::get('/detail/{id}', [StockTransferController::class, 'getDetail'])->name('detail');
    Route::get('/warehouse-products/{warehouseId}', [StockTransferController::class, 'getWarehouseProducts'])->name('warehouse-products');
    Route::post('/store', [StockTransferController::class, 'store'])->name('store');
    Route::put('/update/{id}', [StockTransferController::class, 'update'])->name('update');
    Route::delete('/delete/{id}', [StockTransferController::class, 'destroy'])->name('delete');
    Route::post('/approve-sender/{id}', [StockTransferController::class, 'approveSender'])->name('approve-sender');
    Route::post('/approve-receiver/{id}', [StockTransferController::class, 'approveReceiver'])->name('approve-receiver');
    Route::get('/print-proof/{id}', [StockTransferController::class, 'printProof'])->name('print-proof');
    Route::get('/export', [StockTransferController::class, 'exportList'])->name('export');
});

Route::middleware(['auth', 'access:inventori'])->prefix('inventori/stock-opname')->name('inventori.stock-opname.')->group(function () {
    Route::get('/', [StockOpnameController::class, 'index'])->name('index');
    
    Route::get('/create', [StockOpnameController::class, 'create'])->name('create');
    Route::get('/{id}/edit', [StockOpnameController::class, 'edit'])->name('edit');
    Route::post('/store-snapshot', [StockOpnameController::class, 'storeSnapshot'])->name('store-snapshot');
    Route::put('/{id}', [StockOpnameController::class, 'update'])->name('update');
    Route::get('/{id}/input-count', [StockOpnameController::class, 'inputCount'])->name('input-count');
    Route::post('/{id}/store-count', [StockOpnameController::class, 'storeCount'])->name('store-count');
    Route::get('/{id}', [StockOpnameController::class, 'show'])->name('show');
    Route::delete('/{id}', [StockOpnameController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/print-sheet', [StockOpnameController::class, 'printSheet'])->name('print-sheet');
    Route::get('/{id}/print-report', [StockOpnameController::class, 'printReport'])->name('print-report');
    Route::get('/export-list', [StockOpnameController::class, 'exportList'])->name('export-list');
    Route::get('/{id}/export-detail', [StockOpnameController::class, 'exportDetail'])->name('export-detail');
});

Route::middleware(['auth', 'access:inventori'])->prefix('inventori/penyesuaian')->name('inventori.penyesuaian.')->group(function () {
    // 1. DataTables AJAX Endpoint & Cetak Rekap List (Harus di atas resource dengan ID)
    Route::get('/data', [StockAdjustmentController::class, 'data'])->name('data');
    Route::get('/print-list', [StockAdjustmentController::class, 'printList'])->name('print-list');

    // 2. CRUD Standard
    Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');
    Route::get('/create', [StockAdjustmentController::class, 'create'])->name('create');
    Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');
    Route::get('/{id}', [StockAdjustmentController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [StockAdjustmentController::class, 'edit'])->name('edit');
    Route::put('/{id}', [StockAdjustmentController::class, 'update'])->name('update');
    Route::delete('/{id}', [StockAdjustmentController::class, 'destroy'])->name('destroy');

    // 3. Action Posting & Cetak Detail
    Route::post('/{id}/post', [StockAdjustmentController::class, 'post'])->name('post');
    Route::get('/{id}/print-detail', [StockAdjustmentController::class, 'printDetail'])->name('print-detail');
});

    // ============================================================
    // PRODUKSI
    // ============================================================
    Route::get('/produksi', [ProductionController::class, 'index'])->middleware('access:inventori')->name('produksi');
    Route::post('/produksi', [ProductionController::class, 'store'])->middleware('access:inventori')->name('produksi.store');
    Route::middleware(['auth', 'access:inventori'])->prefix('produksi/pemakaian-bahan')->name('produksi.pemakaian-bahan')->group(function () {
    Route::get('/', [ProductionMaterialUsageController::class, 'index'])->name('');
    Route::get('/create/{workOrderId}', [ProductionMaterialUsageController::class, 'create'])->name('.create');
    Route::post('/{workOrderId}', [ProductionMaterialUsageController::class, 'store'])->name('.store');
    Route::get('/{id}', [ProductionMaterialUsageController::class, 'show'])->name('.show');
    Route::post('/{id}/submit', [ProductionMaterialUsageController::class, 'submit'])->name('.submit');
    Route::post('/{id}/approve', [ProductionMaterialUsageController::class, 'approve'])->name('.approve');
    Route::post('/{id}/reject', [ProductionMaterialUsageController::class, 'reject'])->name('.reject');
});
    Route::get('/produksi/hasil-produksi', fn () => redirect()->route('produksi.work-order.hasil-report'))->middleware('access:inventori')->name('produksi.hasil-produksi');
    Route::get('/produksi/reject', fn () => app(ModuleController::class)->show('production-results'))->middleware('access:inventori')->name('produksi.reject');
    Route::get('/produksi/hpp', [HppController::class, 'index'])->middleware('access:inventori')->name('produksi.hpp');

    Route::get('/produksi/work-order', [ProductionWorkOrderController::class, 'index'])->middleware('access:inventori')->name('produksi.work-order');
    Route::get('/produksi/work-order/create', [ProductionWorkOrderController::class, 'create'])->middleware('access:inventori')->name('produksi.work-order.create');
    Route::get('/produksi/work-order/{id}/edit', [ProductionWorkOrderController::class, 'edit'])->middleware('access:inventori')->name('produksi.work-order.edit');
    Route::post('/produksi/work-order', [ProductionWorkOrderController::class, 'store'])->middleware('access:inventori')->name('produksi.work-order.store');
    Route::put('/produksi/work-order/{id}', [ProductionWorkOrderController::class, 'update'])->middleware('access:inventori')->name('produksi.work-order.update');
    Route::delete('/produksi/work-order/{id}', [ProductionWorkOrderController::class, 'destroy'])->middleware('access:inventori')->name('produksi.work-order.destroy');
    Route::get('/produksi/work-order/bom/{bomId}/info', [ProductionWorkOrderController::class, 'bomInfo'])->middleware('access:inventori')->name('produksi.work-order.bom-info');
    Route::get('/produksi/work-order/{id}/print', [ProductionWorkOrderController::class, 'print'])->middleware('access:inventori')->name('produksi.work-order.print');
    Route::get('/produksi/work-order/export', [ProductionWorkOrderController::class, 'export'])->middleware('access:inventori')->name('produksi.work-order.export');
    Route::get('/produksi/work-order/hasil-report', [ProductionWorkOrderController::class, 'resultsReport'])->middleware('access:inventori')->name('produksi.work-order.hasil-report');
    Route::get('/produksi/work-order/hasil-report/export', [ProductionWorkOrderController::class, 'exportResultsReport'])->middleware('access:inventori')->name('produksi.work-order.hasil-report.export');
    Route::get('/produksi/work-order/{id}', [ProductionWorkOrderController::class, 'show'])->middleware('access:inventori')->name('produksi.work-order.show');
    Route::post('/produksi/work-order/{id}/start', [ProductionWorkOrderController::class, 'startWork'])->middleware('access:inventori')->name('produksi.work-order.start');
    Route::post('/produksi/work-order/{id}/hasil-produksi', [ProductionWorkOrderController::class, 'saveProductionResult'])->middleware('access:inventori')->name('produksi.work-order.hasil-produksi');

    // ============================================================
    // KEUANGAN & AKUNTANSI
    // ============================================================
    
    Route::get('/akuntansi/arus-kas', fn () => app(ModuleController::class)->show('cash-flow'))->middleware('access:keuangan')->name('akuntansi.arus-kas');
    Route::get('/akuntansi/arus-kas/export-excel', [ModuleController::class, 'exportCashFlowExcel'])->middleware('access:keuangan')->name('akuntansi.arus-kas.export-excel');

    
		
		
Route::middleware(['auth', 'access:keuangan'])->group(function () {
	
	// --- 1. Penerimaan Kas & Bank (Kas Masuk / FIN-01) ---
	Route::get('/akuntansi/kas-bank/masuk', [CashReceiptController::class, 'index'])->name('akuntansi.kas-masuk');
	Route::post('/akuntansi/kas-bank/masuk/ar-payment', [CashReceiptController::class, 'storeArPayment'])->name('akuntansi.kas-masuk.store-ar');
	Route::post('/akuntansi/kas-bank/masuk/other', [CashReceiptController::class, 'storeOtherReceipt'])->name('akuntansi.kas-masuk.store-other');
	Route::get('/akuntansi/kas-bank/masuk/export', [CashReceiptController::class, 'export'])->name('akuntansi.kas-masuk.export');
    Route::get('/akuntansi/kas-bank/masuk/unpaid-invoices/{customerId}', [CashReceiptController::class, 'getUnpaidInvoices'])->name('akuntansi.kas-masuk.unpaid-invoices');
	
	// --- 2. Pengeluaran Kas & Bank (Kas Keluar / FIN-02) ---
    Route::get('/akuntansi/kas-bank/keluar', [CashDisbursementController::class, 'index'])->name('akuntansi.kas-keluar');
	Route::post('/akuntansi/kas-bank/keluar/ap-payment', [CashDisbursementController::class, 'storeApPayment'])->name('akuntansi.kas-keluar.store-ap');
	Route::post('/akuntansi/kas-bank/keluar/other', [CashDisbursementController::class, 'storeOtherDisbursement'])->name('akuntansi.kas-keluar.store-other');
	Route::get('/akuntansi/kas-bank/keluar/export', [CashDisbursementController::class, 'export'])->name('akuntansi.kas-keluar.export');
	Route::get('/akuntansi/kas-bank/keluar/unpaid-bills/{supplierId}', [CashDisbursementController::class, 'getUnpaidBills'])->name('akuntansi.kas-keluar.unpaid-bills');
	
    // --- 3. Mutasi / Transfer Antar Kas & Bank (FIN-03) ---
	Route::get('/akuntansi/kas-bank/mutasi', [CashTransferController::class, 'index'])->name('akuntansi.kas-mutasi');
	Route::post('/akuntansi/kas-bank/mutasi', [CashTransferController::class, 'store'])->name('akuntansi.kas-mutasi.store');
	Route::get('/akuntansi/kas-bank/mutasi/export', [CashTransferController::class, 'export'])->name('akuntansi.kas-mutasi.export');   
   // 1. Route Halaman Utama & DataTables AJAX
    Route::get('/akuntansi/jurnal', [JournalController::class, 'index'])->name('akuntansi.jurnal');
    Route::get('/akuntansi/jurnal/data', [JournalController::class, 'data'])->name('akuntansi.jurnal.data');

    // 2. Route Export Excel (WAJIB DITARUH SEBELUM /{id})
    Route::get('/akuntansi/jurnal/export', [JournalController::class, 'export'])->name('akuntansi.jurnal.export');

    // 3. Route Wildcard /{id} (Harus ditaruh di bawah route spesifik)
    Route::get('/akuntansi/jurnal/{id}', [JournalController::class, 'show'])->name('akuntansi.jurnal.show');
    Route::post('/akuntansi/jurnal', [JournalController::class, 'store'])->name('akuntansi.jurnal.store');
    Route::put('/akuntansi/jurnal/{id}', [JournalController::class, 'update'])->name('akuntansi.jurnal.update');
    Route::delete('/akuntansi/jurnal/{id}', [JournalController::class, 'destroy'])->name('akuntansi.jurnal.destroy');
	//gl
	Route::get('/akuntansi/buku-besar', [GeneralLedgerController::class, 'index'])->name('akuntansi.buku-besar');
    Route::get('/akuntansi/buku-besar/export', [GeneralLedgerController::class, 'export'])->name('akuntansi.buku-besar.export');
	//N-Salso
	Route::get('/akuntansi/neraca-saldo', [TrialBalanceController::class, 'index'])->name('akuntansi.neraca-saldo');
    Route::get('/akuntansi/neraca-saldo/export', [TrialBalanceController::class, 'export'])->name('akuntansi.neraca-saldo.export');
	
	//PNL
	Route::get('/akuntansi/laba-rugi', [ProfitLossController::class, 'index'])->name('akuntansi.laba-rugi');
    Route::get('/akuntansi/laba-rugi/export', [ProfitLossController::class, 'export'])->name('akuntansi.laba-rugi.export');
	
	//NERACA
	Route::get('/akuntansi/neraca', [BalanceSheetController::class, 'index'])->name('akuntansi.neraca');
    Route::get('/akuntansi/neraca/export', [BalanceSheetController::class, 'export'])->name('akuntansi.neraca.export');
	
	//CF
	// Halaman Laporan Arus Kas
    Route::get('/akuntansi/arus-kas', [CashFlowController::class, 'index'])->name('akuntansi.arus-kas');
    Route::get('/akuntansi/arus-kas/export', [CashFlowController::class, 'export'])->name('akuntansi.arus-kas.export');
	//Tutup buku
	Route::get('/akuntansi/closing-periode', [AccountingClosingController::class, 'index'])
		->name('akuntansi.closing-periode');

	Route::get('/akuntansi/closing-periode/check', [AccountingClosingController::class, 'check'])
    ->name('akuntansi.closing-periode.check');
	//piutang
	Route::get('/akuntansi/buku-piutang', [ArSubLedgerController::class, 'index'])->name('akuntansi.buku-piutang');
    Route::get('/akuntansi/buku-piutang/customer-ledger/{customerId}', [ArSubLedgerController::class, 'getCustomerLedger'])->name('akuntansi.buku-piutang.customer-ledger');
    Route::post('/akuntansi/buku-piutang/store-initial', [ArSubLedgerController::class, 'storeInitialBalance'])->name('akuntansi.buku-piutang.store-initial');
    Route::put('/akuntansi/buku-piutang/update-initial/{id}', [ArSubLedgerController::class, 'updateInitialBalance'])->name('akuntansi.buku-piutang.update-initial');
    Route::get('/akuntansi/buku-piutang/export-list', [ArSubLedgerController::class, 'exportList'])->name('akuntansi.buku-piutang.export-list');
    Route::get('/akuntansi/buku-piutang/export-customer-ledger/{customerId}', [ArSubLedgerController::class, 'exportCustomerLedger'])->name('akuntansi.buku-piutang.export-customer-ledger');
	//hutang
	Route::get('/akuntansi/buku-hutang', [ApSubLedgerController::class, 'index'])->name('akuntansi.buku-hutang');
    Route::get('/akuntansi/buku-hutang/supplier-ledger/{supplierId}', [ApSubLedgerController::class, 'getSupplierLedger'])->name('supplier-ledger');
    Route::post('/akuntansi/buku-hutang/store-initial', [ApSubLedgerController::class, 'storeInitialBalance'])->name('store-initial');
    Route::put('/akuntansi/buku-hutang/update-initial/{id}', [ApSubLedgerController::class, 'updateInitialBalance'])->name('update-initial');
    Route::get('/akuntansi/buku-hutang/export-list', [ApSubLedgerController::class, 'exportList'])->name('export-list');
    Route::get('/akuntansi/buku-hutang/export-supplier-ledger/{supplierId}', [ApSubLedgerController::class, 'exportSupplierLedger'])->name('export-supplier-ledger');
	
});
Route::middleware(['auth', 'access:keuangan'])->prefix('akuntansi/aging-piutang')->name('akuntansi.aging-piutang.')->group(function () {
    Route::get('/', [ArAgingController::class, 'index'])->name('index');
    Route::get('/export', [ArAgingController::class, 'export'])->name('export');
});
Route::middleware(['auth', 'access:keuangan'])->prefix('akuntansi/aging-hutang')->name('akuntansi.aging-hutang.')->group(function () {
    Route::get('/', [ApAgingController::class, 'index'])->name('index');
    Route::get('/export', [ApAgingController::class, 'export'])->name('export');
});

    // ============================================================
    // LAPORAN
    // ============================================================
    Route::get('/laporan/penjualan', [SalesController::class, 'report'])->middleware('access:inventori')->name('laporan.penjualan');

Route::get('/laporan/retur-penjualan', [SalesReturnReportController::class, 'index'])
    ->middleware('access:inventori')
    ->name('laporan.retur-penjualan');

Route::get('/laporan/retur-penjualan/export', [SalesReturnReportController::class, 'exportExcel'])
    ->middleware('access:inventori')
    ->name('laporan.retur-penjualan.export');

    Route::get('/laporan/piutang-penjualan', [SalesReceivableReportController::class, 'index'])
        ->middleware('access:inventori')
        ->name('laporan.piutang-penjualan');
    Route::get('/laporan/piutang-penjualan/export', [SalesReceivableReportController::class, 'exportExcel'])
        ->middleware('access:inventori')
        ->name('laporan.piutang-penjualan.export');

    Route::get('/laporan/penjualan/export', [SalesController::class, 'exportExcel'])->middleware('access:inventori')->name('laporan.penjualan.export');
    Route::post('/laporan/penjualan/{id}/posting', [SalesController::class, 'postJournal'])->middleware('access:inventori')->name('laporan.penjualan.posting');
    Route::get('/laporan/pembelian', [PurchaseReportController::class, 'index'])->middleware('access:inventori')->name('laporan.pembelian');
    Route::get('/laporan/pembelian/export', [PurchaseReportController::class, 'export'])->middleware('access:inventori')->name('laporan.pembelian.export');
    Route::get('/laporan/retur-pembelian', [PurchaseOperationalReportController::class, 'returns'])->middleware('access:inventori')->name('laporan.retur-pembelian');
    Route::get('/laporan/retur-pembelian/export', [PurchaseOperationalReportController::class, 'exportReturns'])->middleware('access:inventori')->name('laporan.retur-pembelian.export');
    Route::get('/laporan/hutang-pembelian', [PurchaseOperationalReportController::class, 'payables'])->middleware('access:inventori')->name('laporan.hutang-pembelian');
    Route::get('/laporan/hutang-pembelian/export', [PurchaseOperationalReportController::class, 'exportPayables'])->middleware('access:inventori')->name('laporan.hutang-pembelian.export');
    Route::get('/laporan/persediaan', [InventoryReportController::class, 'index'])->middleware('access:inventori')->name('laporan.persediaan');
    Route::get('/laporan/persediaan/export', [InventoryReportController::class, 'export'])->middleware('access:inventori')->name('laporan.persediaan.export');
    Route::get('/laporan/produksi', fn () => view('inventori.laporan.produksi'))->middleware('access:inventori')->name('laporan.produksi');
    Route::get('/laporan/piutang', fn () => app(ModuleController::class)->show('receivables'))->middleware('access:keuangan')->name('laporan.piutang');
    Route::get('/laporan/hutang', fn () => app(ModuleController::class)->show('payables'))->middleware('access:keuangan')->name('laporan.hutang');
    Route::get('/laporan/keuangan', fn () => app(ModuleController::class)->show('profit-loss'))->middleware('access:keuangan')->name('laporan.keuangan');

    Route::get('/inventori/monitoring/margin-harga', fn () => view('inventori.laporan.analisa-margin'))
        ->name('inventori.margin-control');

    // ============================================================
    // PENGATURAN
    // ============================================================
    Route::get('/pengaturan/entitas', [SettingsController::class, 'entity'])->middleware('access:pengaturan')->name('pengaturan.entitas');
    Route::put('/pengaturan/entitas', [SettingsController::class, 'entityUpdate'])->middleware('access:pengaturan')->name('pengaturan.entitas.update');
    Route::get('/pengaturan/user', [SettingsController::class, 'users'])->middleware('access:pengaturan')->name('pengaturan.user');
    Route::post('/pengaturan/user', [SettingsController::class, 'userStore'])->middleware('access:pengaturan')->name('pengaturan.user.store');
    Route::put('/pengaturan/user/{id}', [SettingsController::class, 'userUpdate'])->middleware('access:pengaturan')->name('pengaturan.user.update');
    Route::patch('/pengaturan/user/{id}/toggle', [SettingsController::class, 'userToggle'])->middleware('access:pengaturan')->name('pengaturan.user.toggle');
    Route::get('/pengaturan/role', [SettingsController::class, 'roles'])->middleware('access:pengaturan')->name('pengaturan.role');
    Route::get('/pengaturan/konfigurasi', [SettingsController::class, 'configuration'])->middleware('access:pengaturan')->name('pengaturan.konfigurasi');
    Route::get('/pengaturan/konfigurasi/mapping-account', [BusinessUnitAccountMappingController::class, 'index'])->middleware('access:pengaturan')->name('pengaturan.account-mapping');
    Route::post('/pengaturan/konfigurasi/mapping-account', [BusinessUnitAccountMappingController::class, 'save'])->middleware('access:master')->name('pengaturan.account-mapping.save');
    Route::post('/pengaturan/konfigurasi/mapping-warehouse', [SettingsController::class, 'warehouseMappingSave'])->middleware('access:master')->name('pengaturan.warehouse-mapping.save');

    // ============================================================
    // BRIDGING — ErpController
    // ============================================================
    Route::get('/master/unit', function (Request $request) {
        return app(ErpController::class)->master($request, 'units');
    })->middleware('access:master')->name('master.menu.unit');
    
    $masterMenuPaths = [
        'produk' => 'products', 'customer' => 'customers', 'supplier' => 'suppliers',
        'gudang' => 'warehouses', 'satuan' => 'units',
        'konversi-satuan' => 'unit-conversions',
    ];

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

    // ============================================================
    // LOGOUT
    // ============================================================
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

});
