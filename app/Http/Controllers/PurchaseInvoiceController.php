<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReceiptController;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Exports\PurchaseInvoiceExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();

        return view('inventori.pembelian.faktur.index', compact(
            'businessUnits', 'warehouses', 'suppliers', 'startDate', 'endDate'
        ));
    }

    /**
     * AJAX DataTables Server-Side / JSON Data
     */
    public function data(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $supplierId     = $request->query('supplier_id');
        $paymentType    = $request->query('payment_type');
        $statusFilter   = $request->query('status');

        $query = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->whereNull('p.deleted_at')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate);

        if ($businessUnitId) $query->where('p.business_unit_id', $businessUnitId);
        if ($supplierId) $query->where('p.supplier_id', $supplierId);
        if ($paymentType) $query->where('p.payment_method', $paymentType);
        if ($statusFilter) $query->where('p.status', $statusFilter);

        $data = $query->select(
            'p.*',
            'p.purchase_no as invoice_no',
            'p.payment_method as payment_type',
            'p.total as grand_total',
            'bu.name as business_unit_name',
            's.name as supplier_name',
            'po.po_no',
            'u.name as creator_name'
        )->orderBy('p.created_at', 'desc')->get();

        foreach ($data as $item) {
            $item->formatted_date = Carbon::parse($item->purchase_date)->format('d/m/Y');
            $item->formatted_due  = $item->due_date ? Carbon::parse($item->due_date)->format('d/m/Y') : '-';
            $item->formatted_grand = 'Rp ' . number_format($item->grand_total, 0, ',', '.');
            $item->warehouse_name = '-';
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Form Buat Faktur Pembelian (Full-Page)
     */
    public function create(Request $request)
    {
        $entityId = (int) (auth()->user()->entity_id ?? 1);
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get();
        $suppliers     = Supplier::where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get();
        $products      = Product::where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get();
        $autoInvNo     = $this->generateInvoiceCode();

        $fromPoId = $request->query('from_po');
        $selectedPo = null;
        $poItems = collect();

        if ($fromPoId) {
            $selectedPo = DB::table('purchase_orders')
                ->where('entity_id', $entityId)
                ->where('id', $fromPoId)
                ->first();

            if ($selectedPo) {
                $poItems = DB::table('purchase_order_items as poi')
                    ->join('products as p', 'p.id', '=', 'poi.product_id')
                    ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
                    ->where('poi.purchase_order_id', $fromPoId)
                    ->select('poi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
                    ->get();
            }
        }

        $approvedPos = DB::table('purchase_orders as po')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->where('po.entity_id', $entityId)
            ->whereIn('po.status', ['approved', 'partial'])
            ->whereNull('po.deleted_at')
            ->select('po.id', 'po.po_no', 'po.po_date', 'po.supplier_id', 'po.business_unit_id', 'po.warehouse_id', 's.name as supplier_name')
            ->orderByDesc('po.id')
            ->get();

        return view('inventori.pembelian.faktur.create', compact(
            'businessUnits', 'warehouses', 'suppliers', 'products', 'autoInvNo',
            'fromPoId', 'selectedPo', 'poItems', 'approvedPos'
        ));
    }

    /**
     * Pull Item PO via AJAX
     */
    public function getPoItems($poId)
    {
        $po = DB::table('purchase_orders')->where('id', $poId)->first();
        if (!$po) return response()->json(['success' => false, 'message' => 'PO tidak ditemukan'], 404);

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
            ->where('poi.purchase_order_id', $poId)
            ->select('poi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get();

        return response()->json(['success' => true, 'po' => $po, 'items' => $items]);
    }

    /**
     * Simpan Pembelian (Draft / Post Direct)
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id'      => 'required|exists:suppliers,id',
            'purchase_order_id'=> 'nullable|exists:purchase_orders,id',
            'warehouse_id'     => 'nullable|exists:warehouses,id',
            'purchase_date'    => 'required|date',
            'payment_method'   => 'required|in:cash,credit',
            'products'         => 'required|array|min:1',
            'qty'              => 'required|array|min:1',
            'unit_price'       => 'required|array|min:1',
        ]);

        $isPo = $request->filled('purchase_order_id');
        $goodsReceived = !$isPo && $request->boolean('goods_received');

        if (!$isPo && $goodsReceived && !$request->filled('warehouse_id')) {
            return back()->withInput()->with('swal_error', 'Gudang wajib dipilih jika barang langsung diterima.');
        }

        try {
            $purchaseId = DB::transaction(function () use ($request, $isPo, $goodsReceived) {
                $entityId = (int) (auth()->user()->entity_id ?? 1);
                $purchaseNo = $this->generateInvoiceCode();
                $dueDate = $request->payment_method === 'credit'
                    ? ($request->input('due_date') ?: now()->addDays(30)->toDateString())
                    : null;

                $purchaseOrder = null;
                if ($isPo) {
                    $purchaseOrder = DB::table('purchase_orders')
                        ->where('entity_id', $entityId)
                        ->where('id', $request->purchase_order_id)
                        ->first();
                    abort_unless($purchaseOrder, 422, 'PO tidak ditemukan.');
                    abort_unless((int) $purchaseOrder->supplier_id === (int) $request->supplier_id, 422, 'Supplier faktur harus sama dengan supplier PO.');
                    abort_unless((int) $purchaseOrder->business_unit_id === (int) $request->business_unit_id, 422, 'Unit bisnis faktur harus sama dengan PO.');
                }

                $purchaseId = DB::table('purchases')->insertGetId([
                    'entity_id'           => $entityId,
                    'business_unit_id'    => $request->business_unit_id,
                    'supplier_id'         => $request->supplier_id,
                    'purchase_order_id'   => $isPo ? $request->purchase_order_id : null,
                    'user_id'             => auth()->id() ?? 1,
                    'document_type'       => 'invoice',
                    'source_type'         => $isPo ? 'po' : 'direct',
                    'goods_received'      => $goodsReceived,
                    'posting_status'      => 'draft',
                    'purchase_no'         => $purchaseNo,
                    'supplier_invoice_no' => $request->input('supplier_invoice_no') ?: $purchaseNo,
                    'supplier_invoice_date'=> $request->purchase_date,
                    'purchase_date'       => $request->purchase_date,
                    'subtotal'            => 0,
                    'discount'            => 0,
                    'total'               => 0,
                    'payment_method'      => $request->payment_method,
                    'due_date'            => $dueDate,
                    'dpp'                 => 0,
                    'ppn_amount'          => (float) $request->input('tax_amount', 0),
                    'tax_condition'       => $request->input('tax_condition', 'non_ppn'),
                    'memo'                => $request->input('memo'),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $subtotal = 0.0;
                $discountTotal = 0.0;

                foreach ($request->products as $idx => $productId) {
                    $qty = (float) ($request->qty[$idx] ?? 0);
                    $price = (float) ($request->unit_price[$idx] ?? 0);
                    $discount = (float) ($request->discount[$idx] ?? 0);
                    abort_if($qty <= 0, 422, 'Qty item harus lebih dari 0.');

                    $product = Product::findOrFail($productId);
                    $factor = $isPo
                        ? (float) ($request->input("conversion_factor.$idx") ?: 1)
                        : 1.0;
                    $baseQty = $qty * $factor;
                    $lineGross = $qty * $price;
                    $lineNet = max(0, $lineGross - $discount);

                    $subtotal += $lineGross;
                    $discountTotal += $discount;

                    DB::table('purchase_items')->insert([
                        'purchase_id'       => $purchaseId,
                        'product_id'        => $productId,
                        'unit_id'           => $isPo ? ($request->input("unit_id.$idx") ?: $product->base_unit_id) : $product->base_unit_id,
                        'qty'               => $qty,
                        'conversion_factor' => $factor,
                        'base_qty'          => $baseQty,
                        'unit_cost'         => $qty > 0 ? $lineNet / $qty : 0,
                        'base_unit_cost'    => $baseQty > 0 ? $lineNet / $baseQty : 0,
                        'discount'          => $discount,
                        'total'             => $lineNet,
                        'line_subtotal'     => $lineGross,
                        'line_discount'     => $discount,
                        'taxable_amount'    => $lineNet,
                        'tax_rate'          => 0,
                        'tax_amount'        => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }

                $taxAmount = (float) $request->input('tax_amount', 0);
                $total = max(0, $subtotal - $discountTotal) + $taxAmount;

                DB::table('purchases')->where('id', $purchaseId)->update([
                    'subtotal' => $subtotal,
                    'discount' => $discountTotal,
                    'total' => $total,
                    'dpp' => max(0, $subtotal - $discountTotal),
                    'ppn_amount' => $taxAmount,
                    'updated_at' => now(),
                ]);

                return $purchaseId;
            });

            if ($request->boolean('post_now') || $goodsReceived) {
                return $this->executePosting($purchaseId, $goodsReceived ? $request->input('warehouse_id') : null);
            }

            return redirect()->route('inventori.pembelian.index')
                ->with('swal_success', "Draft Faktur Pembelian [{$purchaseId}] berhasil disimpan.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('swal_error', 'Gagal menyimpan pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Form Edit Draft Pembelian
     */
    public function edit($id)
    {
        $p = DB::table('purchases')->where('id', $id)->whereNull('deleted_at')->firstOrFail();

        if ($p->status === 'posted') {
            return redirect()->route('inventori.pembelian.show', $id)
                ->with('swal_error', 'Transaksi POSTED bersifat permanen dan tidak dapat diedit!');
        }

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();
        $products      = Product::where('is_active', 1)->orderBy('name')->get();

        $items = DB::table('purchase_items as pi')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'pi.unit_id')
            ->where('pi.purchase_id', $id)
            ->select('pi.*', 'pr.code as product_code', 'pr.name as product_name', 'u.name as unit_name')
            ->get();

        return view('inventori.pembelian.faktur.edit', compact('p', 'businessUnits', 'warehouses', 'suppliers', 'products', 'items'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'purchase_date' => 'required|date',
            'supplier_invoice_no' => 'nullable|string|max:100',
            'payment_method' => 'required|in:cash,credit',
            'memo' => 'nullable|string',
        ]);

        $purchase = DB::table('purchases')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$purchase) {
            return redirect()->route('inventori.pembelian.index')
                ->with('swal_error', 'Faktur tidak ditemukan.');
        }

        if ($purchase->status === 'posted') {
            return redirect()->route('inventori.pembelian.show', $id)
                ->with('swal_error', 'Faktur POSTED tidak dapat diedit.');
        }

        $dueDate = $request->payment_method === 'credit'
            ? ($purchase->due_date ?: now()->addDays(30)->toDateString())
            : null;

        DB::table('purchases')->where('id', $id)->update([
            'purchase_date' => $request->purchase_date,
            'supplier_invoice_no' => $request->supplier_invoice_no,
            'supplier_invoice_date' => $request->purchase_date,
            'payment_method' => $request->payment_method,
            'due_date' => $dueDate,
            'memo' => $request->memo,
            'updated_at' => now(),
        ]);

        return redirect()->route('inventori.pembelian.show', $id)
            ->with('swal_success', 'Draft Faktur Pembelian berhasil diperbarui.');
    }

    /**
     * Action Post Faktur Pembelian
     */
    public function post(Request $request, $id)
    {
        return $this->executePosting($id, $request->input('warehouse_id'));
    }

    private function executePosting($id, $warehouseId = null)
    {
        try {
            $purchaseNo = DB::transaction(function () use ($id, $warehouseId) {
                $purchase = DB::table('purchases')
                    ->whereNull('deleted_at')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$purchase) {
                    throw new \RuntimeException('Faktur tidak ditemukan.');
                }

                if ($purchase->status === 'posted') {
                    throw new \RuntimeException('Faktur sudah POSTED.');
                }

                if ($purchase->source_type !== 'po' && (int) $purchase->goods_received === 1) {
                    if (!$warehouseId) {
                        throw new \RuntimeException('Gudang wajib dipilih untuk penerimaan barang langsung.');
                    }

                    $items = DB::table('purchase_items')
                        ->where('purchase_id', $purchase->id)
                        ->get();

                    $receiptRequest = Request::create('/inventori/penerimaan', 'POST', [
                        'purchase_id' => $purchase->id,
                        'warehouse_id' => $warehouseId,
                        'receipt_date' => $purchase->purchase_date,
                        'memo' => 'Penerimaan langsung dari Faktur '.$purchase->purchase_no,
                        'items' => $items->map(fn ($item) => [
                            'purchase_item_id' => $item->id,
                            'qty' => $item->qty,
                        ])->values()->all(),
                    ]);

                    app(ReceiptController::class)->store($receiptRequest);
                }

                DB::table('purchases')->where('id', $id)->update([
                    'status' => 'posted',
                    'posting_status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id() ?? 1,
                    'updated_at' => now(),
                ]);

                return $purchase->purchase_no;
            });

            return redirect()->route('inventori.pembelian.show', $id)
                ->with('swal_success', "Faktur Pembelian [{$purchaseNo}] BERHASIL DIPOSTING.");
        } catch (\Throwable $e) {
            return back()->with('swal_error', 'Gagal memproses posting: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $p = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.id', $id)
            ->whereNull('p.deleted_at')
            ->select(
                'p.*',
                'p.purchase_no as invoice_no',
                'p.total as grand_total',
                'p.payment_method as payment_type',
                'bu.name as business_unit_name',
                's.name as supplier_name',
                's.address as supplier_address',
                'po.po_no',
                'u.name as creator_name'
            )
            ->firstOrFail();

        $items = DB::table('purchase_items as pi')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'pi.unit_id')
            ->where('pi.purchase_id', $id)
            ->select('pi.*', 'pr.code as product_code', 'pr.name as product_name', 'u.name as unit_name')
            ->get();

        $warehouse = DB::table('receipts as r')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.purchase_id', $id)
            ->orderBy('r.id')
            ->select('w.name as warehouse_name')
            ->first();

        $journals = DB::table('journals')->where('source_id', $id)->where('source_type', 'receipt')->get();

        foreach ($journals as $j) {
            $j->entries = DB::table('journal_entries as je')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                ->where('je.journal_id', $j->id)
                ->select('je.*', 'coa.code as account_code', 'coa.name as account_name')
                ->get();
        }

        return view('inventori.pembelian.faktur.show', compact('p', 'items', 'journals', 'warehouse'));
    }

    public function destroy($id)
    {
        $p = DB::table('purchases')->where('id', $id)->first();
        if ($p && $p->status === 'posted') {
            return redirect()->back()->with('swal_error', 'Faktur POSTED bersifat permanen dan tidak dapat dihapus!');
        }

        DB::table('purchases')->where('id', $id)->update(['deleted_at' => now()]);
        return redirect()->route('inventori.pembelian.index')->with('swal_success', 'Draft Faktur Pembelian berhasil dihapus.');
    }

    public function printInvoice($id)
    {
        $p = DB::table('purchases as p')
            ->leftJoin('entities as e', 'e.id', '=', 'p.entity_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.id', $id)
            ->whereNull('p.deleted_at')
            ->select(
                'p.*',
                'p.purchase_no as invoice_no',
                'p.total as grand_total',
                'e.name as entity_name',
                's.name as supplier_name',
                's.address as supplier_address',
                's.phone as supplier_phone',
                'po.po_no',
                'u.name as creator_name'
            )
            ->addSelect([
                'warehouse_name' => DB::table('receipts as r')
                    ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
                    ->whereColumn('r.purchase_id', 'p.id')
                    ->orderBy('r.id')
                    ->limit(1)
                    ->select('w.name')
            ])
            ->firstOrFail();

        $items = DB::table('purchase_items as pi')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'pi.unit_id')
            ->where('pi.purchase_id', $id)
            ->select('pi.*', 'pr.code as product_code', 'pr.name as product_name', 'u.name as unit_name')
            ->get();

        return view('inventori.pembelian.faktur.print-invoice', compact('p', 'items'));
    }

    public function printList(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));

        $purchases = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->whereNull('p.deleted_at')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate)
            ->select(
                'p.*',
                'p.purchase_no as invoice_no',
                'p.total as grand_total',
                'bu.name as business_unit_name',
                's.name as supplier_name',
                'po.po_no'
            )
            ->orderByDesc('p.created_at')
            ->get();

        foreach ($purchases as $purchase) {
            $purchase->warehouse_name = '-';
        }

        return view('inventori.pembelian.faktur.print-list', compact('purchases', 'startDate', 'endDate'));
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new PurchaseInvoiceExport($request), 'Laporan_Rekap_Pembelian_' . date('Ymd_His') . '.xlsx');
    }

    private function generateInvoiceCode()
    {
        $dateStr = date('Ymd');
        $last = DB::table('purchases')
            ->where('purchase_no', 'LIKE', "INV-{$dateStr}-%")
            ->orderByDesc('id')
            ->first();

        $nextSeq = $last ? ((int) substr($last->purchase_no, -3)) + 1 : 1;
        return 'INV-' . $dateStr . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }
}