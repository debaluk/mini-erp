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
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));

        $query = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->whereNull('p.deleted_at')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate);

        if ($request->filled('business_unit_id')) $query->where('p.business_unit_id', $request->business_unit_id);
        if ($request->filled('supplier_id')) $query->where('p.supplier_id', $request->supplier_id);
        if ($request->filled('payment_type')) $query->where('p.payment_method', $request->payment_type);
        if ($request->filled('status')) $query->where('p.status', $request->status);

        $recordsTotal = (clone $query)->count();

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('p.purchase_no', 'like', "%{$search}%")
                  ->orWhere('s.name', 'like', "%{$search}%")
                  ->orWhere('bu.name', 'like', "%{$search}%")
                  ->orWhere('po.po_no', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columns = [
            0 => 'p.purchase_no',
            1 => 'p.purchase_date',
            2 => 'po.po_no',
            3 => 's.name',
            4 => 'bu.name',
            5 => 'p.payment_method',
            6 => 'p.due_date',
            7 => 'p.total',
            8 => 'p.status',
        ];
        $orderColumn = 'p.purchase_date';
        $orderDir = 'desc';

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 15);
        $length = $length > 0 ? min($length, 100) : 15;

        $rows = $query->select(
            'p.id', 'p.purchase_no', 'p.purchase_date', 'p.source_type', 'p.status', 'p.total',
            'p.payment_method', 'p.due_date',
            'bu.code as bu_code', 'bu.name as business_unit_name', 's.name as supplier_name', 'po.po_no'
        )->orderBy($orderColumn, $orderDir)->offset($start)->limit($length)->get();

        $data = $rows->map(function ($item) {
            $item->formatted_date = $item->purchase_date ? Carbon::parse($item->purchase_date)->format('d/m/Y') : '-';
            $item->formatted_due_date = $item->due_date ? Carbon::parse($item->due_date)->format('d/m/Y') : '-';
            $item->payment_method_label = match ($item->payment_method) {
                'credit' => 'Kredit',
                'cash' => 'Tunai',
                'transfer' => 'Transfer',
                'qris' => 'QRIS',
                default => $item->payment_method ?: '-',
            };
            $item->formatted_grand = 'Rp ' . number_format((float) $item->total, 0, ',', '.');
            return $item;
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
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
     * AJAX lookup produk untuk modal Faktur Pembelian Non-PO.
     * Produk dibatasi ke entity aktif dan memakai satuan pembelian default bila tersedia.
     */
    public function lookupProducts(Request $request)
    {
        $entityId = (int) (auth()->user()->entity_id ?? 1);
        $search = trim((string) $request->query('q', ''));

        $products = DB::table('products as p')
            ->leftJoin('units as base_u', 'base_u.id', '=', 'p.base_unit_id')
            ->leftJoin('product_unit_conversions as puc', function ($join) {
                $join->on('puc.product_id', '=', 'p.id')
                    ->where('puc.is_default_purchase', 1)
                    ->where('puc.is_active', 1);
            })
            ->leftJoin('units as purchase_u', 'purchase_u.id', '=', 'puc.unit_id')
            ->where('p.entity_id', $entityId)
            ->where('p.is_active', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('p.code', 'like', "%{$search}%")
                        ->orWhere('p.name', 'like', "%{$search}%")
                        ->orWhere('p.sku', 'like', "%{$search}%")
                        ->orWhere('p.barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('p.name')
            ->select(
                'p.id',
                'p.code',
                'p.name',
                DB::raw('COALESCE(purchase_u.id, base_u.id) as unit_id'),
                DB::raw('COALESCE(purchase_u.name, base_u.name, \'PCS\') as unit_name'),
                DB::raw('1 as purchase_cost')
            )
            ->limit(500)
            ->get();

        $products->each(function ($product) {
            // Harga tetap diinput user; 0 mencegah dropdown mengisi harga fiktif.
            $product->purchase_cost = 0;
        });

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * AJAX lookup PO yang sudah disetujui dan belum memiliki faktur pembelian.
     */
    public function lookupPurchaseOrders(Request $request)
    {
        $entityId = (int) (auth()->user()->entity_id ?? 1);
        $search = trim((string) $request->query('q', ''));

        $pos = DB::table('purchase_orders as po')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->where('po.entity_id', $entityId)
            ->where('po.status', 'approved')
            ->whereNull('po.deleted_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purchases as p')
                    ->whereColumn('p.purchase_order_id', 'po.id')
                    ->whereNull('p.deleted_at')
                    ->where('p.status', '!=', 'cancelled');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('po.po_no', 'like', "%{$search}%")
                        ->orWhere('s.name', 'like', "%{$search}%");
                });
            })
            ->select(
                'po.id',
                'po.po_no',
                'po.po_date',
                'po.supplier_id',
                'po.business_unit_id',
                'po.warehouse_id',
                's.name as supplier_name',
                'bu.code as bu_code',
                'bu.name as bu_name',
                'w.name as warehouse_name'
            )
            ->orderByDesc('po.id')
            ->limit(200)
            ->get();

        return response()->json(['success' => true, 'data' => $pos]);
    }

    /**
     * Pull Item PO via AJAX
     */
    public function getPoItems($poId)
    {
        $entityId = (int) (auth()->user()->entity_id ?? 1);

        $po = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->where('po.entity_id', $entityId)
            ->where('po.id', $poId)
            ->whereNull('po.deleted_at')
            ->select(
                'po.*',
                's.name as supplier_name',
                'bu.code as bu_code',
                'bu.name as bu_name',
                'w.name as warehouse_name'
            )
            ->first();

        if (!$po) {
            return response()->json(['success' => false, 'message' => 'PO tidak ditemukan'], 404);
        }

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
            ->where('poi.purchase_order_id', $poId)
            ->select(
                'poi.*',
                'poi.unit_price as unit_cost',
                'p.code as product_code',
                'p.name as product_name',
                'u.name as unit_name'
            )
            ->get();

        return response()->json(['success' => true, 'po' => $po, 'items' => $items]);
    }

    /**
     * Simpan Pembelian langsung posting
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'purchase_order_id'=> 'nullable|exists:purchase_orders,id',
            'warehouse_id'     => 'nullable|exists:warehouses,id',
            'purchase_date'    => 'required|date',
            'payment_method'   => 'required|in:cash,credit,transfer,qris',
            'products'         => 'required|array|min:1',
            'qty'              => 'required|array|min:1',
            'unit_price'       => 'required|array|min:1',
        ]);

        $isPo = $request->filled('purchase_order_id');

        if ($request->payment_method === 'credit' && !$request->filled('supplier_id')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'supplier_id' => 'Supplier wajib dipilih untuk pembelian kredit.',
            ]);
        }
        // Non-PO adalah pembelian langsung: barang otomatis diterima melalui prosedur Penerimaan Barang. 
        $goodsReceived = !$isPo;

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
                    'posting_status'      => 'posted',
                    'purchase_no'         => $purchaseNo,
                    'supplier_invoice_no' => $isPo
                        ? ($request->input('supplier_invoice_no') ?: null)
                        : ($request->input('supplier_invoice_no') ?: $purchaseNo),
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
                $discountTotal = (float) $request->input('document_discount', 0);

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

            // Tidak ada Draft/Approval. Faktur langsung diproses.
            return $this->executePosting($purchaseId, null, $request);

        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal menyimpan pembelian: ' . $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('swal_error', 'Gagal menyimpan pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Form Edit Faktur Pembelian
     */
    public function edit($id)
    {
        $p = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->where('p.id', $id)->whereNull('p.deleted_at')
            ->select('p.*', 'bu.code as bu_code', 'bu.name as bu_name', 's.name as supplier_name', 'po.po_no')
            ->firstOrFail();

        $items = DB::table('purchase_items as pi')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'pi.unit_id')
            ->where('pi.purchase_id', $id)
            ->select('pi.*', 'pr.code as product_code', 'pr.name as product_name', 'u.name as unit_name')
            ->orderBy('pi.id')->get();

        if (request()->expectsJson()) {
            return response()->json(['status' => 'success', 'data' => ['purchase' => $p, 'items' => $items]]);
        }

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', 1)->orderBy('name')->get();
        $products = Product::where('is_active', 1)->orderBy('name')->get();

        return view('inventori.pembelian.faktur.edit', compact('p','businessUnits','warehouses','suppliers','products','items'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'purchase_date' => 'required|date',
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_invoice_no' => 'nullable|string|max:100',
            'payment_method' => 'required|in:cash,credit,transfer,qris',
            'due_date' => 'nullable|date',
            'memo' => 'nullable|string',
            'products' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
        ]);

        if ($request->payment_method === 'credit' && !$request->filled('supplier_id')) {
            return response()->json(['status'=>'error','message'=>'Supplier wajib dipilih untuk pembelian kredit.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $id) {
                $purchase = DB::table('purchases')->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();
                if (!$purchase) throw new \RuntimeException('Faktur tidak ditemukan.');
                if ($purchase->status === 'cancelled') throw new \RuntimeException('Faktur yang sudah dibatalkan hanya dapat dicetak.');

                $period = DB::table('accounting_periods')
                    ->where('entity_id',$purchase->entity_id)
                    ->where('period_year',Carbon::parse($purchase->purchase_date)->year)
                    ->where('period_month',Carbon::parse($purchase->purchase_date)->month)->first();
                if ($period && in_array($period->status,['closing','closed'],true)) {
                    throw new \RuntimeException('Faktur tidak dapat diedit karena periode akuntansi sudah dalam proses closing atau sudah closed.');
                }

                $oldItems = DB::table('purchase_items')->where('purchase_id',$id)->orderBy('id')->lockForUpdate()->get();
                $products = array_values($request->input('products',[]));
                $qtys = array_values($request->input('qty',[]));
                $prices = array_values($request->input('unit_price',[]));
                $discounts = array_values($request->input('discount',[]));
                if (count($products)!==count($qtys) || count($products)!==count($prices)) {
                    throw new \RuntimeException('Rincian barang pembelian tidak lengkap.');
                }

                $receipt = DB::table('receipts')->where('purchase_id',$id)->orderBy('id')->lockForUpdate()->first();

                if ($purchase->source_type === 'po') {
                    if ($oldItems->count() !== count($products)) {
                        throw new \RuntimeException('Item faktur dari PO tidak boleh ditambah atau dikurangi karena penerimaan fisik sudah tercatat.');
                    }
                    foreach ($oldItems as $i=>$old) {
                        if ((int)$old->product_id !== (int)$products[$i]) {
                            throw new \RuntimeException('Barang pada faktur PO harus tetap sama dengan penerimaan fisik.');
                        }
                    }
                }

                // Non-PO: balik nilai penerimaan lama, lalu terapkan nilai baru pada penerimaan yang sama.
                if ($receipt && $purchase->source_type !== 'po') {
                    $receiptItems = DB::table('receipt_items')->where('receipt_id',$receipt->id)->lockForUpdate()->get();
                    foreach ($receiptItems as $ri) {
                        $stock = DB::table('warehouses_stocks')->where('warehouse_id',$receipt->warehouse_id)
                            ->where('product_id',$ri->product_id)->lockForUpdate()->first();
                        $q = (float)$ri->base_qty;
                        if (!$stock || (float)$stock->qty + 0.0000001 < $q) throw new \RuntimeException('Stok tidak mencukupi untuk mengubah faktur '.$purchase->purchase_no.'.');
                        $oldQty=(float)$stock->qty;
                        $newQty=$oldQty-$q;
                        $newValue=($oldQty*(float)$stock->avg_cost)-($q*(float)$ri->base_unit_cost);
                        DB::table('warehouses_stocks')->where('id',$stock->id)->update([
                            'qty'=>$newQty,'avg_cost'=>round($newQty>0?max(0,$newValue/$newQty):0,9),'updated_at'=>now()
                        ]);
                        DB::table('stock_movements')->insert([
                            'entity_id'=>$purchase->entity_id,'business_unit_id'=>$purchase->business_unit_id,'warehouse_id'=>$receipt->warehouse_id,
                            'product_id'=>$ri->product_id,'unit_id'=>$ri->unit_id,'transaction_qty'=>-$ri->qty,'conversion_factor'=>$ri->conversion_factor,
                            'movement_type'=>'purchase_edit_out','qty'=>-$q,'unit_cost'=>$ri->base_unit_cost,'reference_type'=>'purchase_edit',
                            'reference_id'=>$purchase->id,'receipt_id'=>$receipt->id,'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()
                        ]);
                    }
                    DB::table('receipt_invoice_allocations')->whereIn('receipt_item_id',function($q)use($receipt){
                        $q->select('id')->from('receipt_items')->where('receipt_id',$receipt->id);
                    })->delete();
                    DB::table('receipt_items')->where('receipt_id',$receipt->id)->delete();
                }

                if ($purchase->source_type !== 'po') {
                    DB::table('purchase_items')->where('purchase_id',$id)->delete();
                }
                if ((int)$request->business_unit_id !== (int)$purchase->business_unit_id) {
                    throw new \RuntimeException('Unit Bisnis faktur tidak dapat diubah setelah transaksi diposting.');
                }

                if ($purchase->source_type !== 'po') {
                    DB::table('purchase_price_histories')->where('reference_id',$purchase->id)->where('source','purchase')->delete();
                }

                $subtotal=0.0; $discountTotal=(float)$request->input('document_discount',0);

                foreach($products as $i=>$productId){
                    $qty=(float)($qtys[$i]??0); $price=(float)($prices[$i]??0); $discount=(float)($discounts[$i]??0);
                    if($qty<=0) throw new \RuntimeException('Qty item harus lebih dari 0.');
                    $product=Product::findOrFail($productId);
                    $factor=$purchase->source_type==='po'?(float)($oldItems[$i]->conversion_factor?:1):1.0;
                    $baseQty=$qty*$factor; $gross=$qty*$price; $net=max(0,$gross-$discount);
                    $subtotal += $gross; $discountTotal += $discount;
                    $itemData = [
                        'purchase_id'=>$id,'product_id'=>$productId,
                        'unit_id'=>$purchase->source_type==='po'?($oldItems[$i]->unit_id?:$product->base_unit_id):$product->base_unit_id,
                        'qty'=>$qty,'conversion_factor'=>$factor,'base_qty'=>$baseQty,
                        'unit_cost'=>$qty>0?$net/$qty:0,'base_unit_cost'=>$baseQty>0?$net/$baseQty:0,
                        'discount'=>$discount,'total'=>$net,'line_subtotal'=>$gross,'line_discount'=>$discount,
                        'taxable_amount'=>$net,'tax_rate'=>0,'tax_amount'=>0,'updated_at'=>now()
                    ];
                    if ($purchase->source_type === 'po') {
                        $piId = $oldItems[$i]->id;
                        DB::table('purchase_items')->where('id',$piId)->update($itemData);
                    } else {
                        $itemData['created_at'] = now();
                        $piId = DB::table('purchase_items')->insertGetId($itemData);
                    }

                    if($receipt && $purchase->source_type !== 'po'){
                        $baseCost=$baseQty>0?$net/$baseQty:0;
                        $riId=DB::table('receipt_items')->insertGetId([
                            'receipt_id'=>$receipt->id,'purchase_order_item_id'=>null,'purchase_item_id'=>$piId,'product_id'=>$productId,
                            'unit_id'=>$product->base_unit_id,'qty'=>$qty,'conversion_factor'=>$factor,'base_qty'=>$baseQty,
                            'unit_cost'=>$qty>0?$net/$qty:0,'base_unit_cost'=>$baseCost,'valuation_status'=>'valued','created_at'=>now(),'updated_at'=>now()
                        ]);
                        DB::table('receipt_invoice_allocations')->insert([
                            'receipt_item_id'=>$riId,'purchase_item_id'=>$piId,'qty'=>$qty,'conversion_factor'=>$factor,'base_qty'=>$baseQty,
                            'allocated_value'=>round($net,2),'status'=>'posted','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()
                        ]);
                        $stock=DB::table('warehouses_stocks')->where('warehouse_id',$receipt->warehouse_id)->where('product_id',$productId)->lockForUpdate()->first();
                        if($stock){
                            $sq=(float)$stock->qty; $nq=$sq+$baseQty;
                            $navg=$nq>0?(($sq*(float)$stock->avg_cost)+($baseQty*$baseCost))/$nq:0;
                            DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$nq,'avg_cost'=>round($navg,9),'updated_at'=>now()]);
                        }else{
                            DB::table('warehouses_stocks')->insert(['entity_id'=>$purchase->entity_id,'warehouse_id'=>$receipt->warehouse_id,'product_id'=>$productId,'qty'=>$baseQty,'avg_cost'=>round($baseCost,9),'created_at'=>now(),'updated_at'=>now()]);
                        }
                        DB::table('stock_movements')->insert([
                            'entity_id'=>$purchase->entity_id,'business_unit_id'=>$purchase->business_unit_id,'warehouse_id'=>$receipt->warehouse_id,'product_id'=>$productId,
                            'unit_id'=>$product->base_unit_id,'transaction_qty'=>$qty,'conversion_factor'=>$factor,'movement_type'=>'purchase_edit_in','qty'=>$baseQty,
                            'unit_cost'=>$baseCost,'reference_type'=>'purchase_edit','reference_id'=>$purchase->id,'receipt_id'=>$receipt->id,
                            'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()
                        ]);
                    }
                }

                $tax=(float)$request->input('tax_amount',$purchase->ppn_amount??0);
                $total=max(0,$subtotal-$discountTotal)+$tax;
                DB::table('purchases')->where('id',$id)->update([
                    'business_unit_id'=>$request->business_unit_id,'purchase_date'=>$request->purchase_date,
                    'supplier_id'=>$request->filled('supplier_id')?$request->supplier_id:null,
                    'supplier_invoice_no'=>$request->supplier_invoice_no,'supplier_invoice_date'=>$request->purchase_date,
                    'payment_method'=>$request->payment_method,
                    'due_date'=>$request->payment_method==='credit'?($request->input('due_date')?:now()->addDays(30)->toDateString()):null,
                    'memo'=>$request->memo,'subtotal'=>$subtotal,'discount'=>$discountTotal,'total'=>$total,
                    'dpp'=>max(0,$subtotal-$discountTotal),'ppn_amount'=>$tax,'updated_at'=>now()
                ]);

                // Jurnal yang sama: hanya isi jurnal diperbarui.
                $journal=DB::table('journals')->where('source_type','purchase_invoice')->where('source_id',$id)->orderBy('id')->first();
                if(!$journal && $receipt && $purchase->source_type!=='po'){
                    $journal=DB::table('journals')->where('source_type','receipt')->where('source_id',$receipt->id)->orderBy('id')->first();
                }
                if($journal){
                    $mapping=DB::table('business_unit_account_mappings')->where('business_unit_id',$request->business_unit_id)
                        ->whereIn('mapping_key',['inventory','payable','cash','bank'])->pluck('account_id','mapping_key');
                    $creditKey=match(strtolower((string)$request->payment_method)){ 'cash','tunai'=>'cash','bank','transfer','qris'=>'bank',default=>'payable' };
                    if(!isset($mapping['inventory'],$mapping[$creditKey])) throw new \RuntimeException('Mapping akun pembelian belum lengkap.');
                    $value=round($total,2);
                    DB::table('journal_entries')->where('journal_id',$journal->id)->delete();
                    DB::table('journal_entries')->insert([
                        ['journal_id'=>$journal->id,'account_id'=>$mapping['inventory'],'debit'=>$value,'credit'=>0,'created_at'=>now(),'updated_at'=>now()],
                        ['journal_id'=>$journal->id,'account_id'=>$mapping[$creditKey],'debit'=>0,'credit'=>$value,'created_at'=>now(),'updated_at'=>now()],
                    ]);
                    DB::table('journals')->where('id',$journal->id)->update([
                        'business_unit_id'=>$request->business_unit_id,'journal_date'=>$request->purchase_date,
                        'description'=>'Faktur pembelian '.$purchase->purchase_no,'updated_at'=>now()
                    ]);
                }

                return $purchase->purchase_no;
            });

            return response()->json([
                'status'=>'success',
                'message'=>"Faktur Pembelian [{$result}] berhasil diperbarui.",
                'data'=>['id'=>$id,'purchase_no'=>$result],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status'=>'error','message'=>'Gagal memperbarui pembelian: '.$e->getMessage()],422);
        }
    }

    /**
     * Action Post Faktur Pembelian
     */
    public function post(Request $request, $id)
    {
        return $this->executePosting($id, $request->input('warehouse_id'), $request);
    }

    private function executePosting($id, $warehouseId = null, ?Request $request = null)
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

                if ($purchase->source_type !== 'po' && (int) $purchase->goods_received !== 1) {
                    throw new \RuntimeException('Pembelian Non-PO wajib melalui Penerimaan Barang sebelum diposting.');
                }

                if ($purchase->source_type !== 'po' && (int) $purchase->goods_received === 1) {
                    // Non-PO tidak memilih gudang di UI. Gudang ditentukan dari mapping
                    // Unit Bisnis -> Gudang yang sudah dikonfigurasi.
                    if (!$warehouseId) {
                        $warehouseId = DB::table('warehouse_business_units')
                            ->where('entity_id', $purchase->entity_id)
                            ->where('business_unit_id', $purchase->business_unit_id)
                            ->value('warehouse_id');
                    }

                    if (!$warehouseId) {
                        throw new \RuntimeException('Gudang belum dipetakan untuk Unit Bisnis pembelian.');
                    }

                    $items = DB::table('purchase_items')
                        ->where('purchase_id', $purchase->id)
                        ->get();

                    $receiptRequest = Request::create('/inventori/penerimaan', 'POST', [
                        'purchase_id' => $purchase->id,
                        'warehouse_id' => $warehouseId,
                        'journal' => true,
                        'receipt_date' => $purchase->purchase_date,
                        'memo' => 'Penerimaan langsung dari Faktur '.$purchase->purchase_no,
                        'items' => $items->map(fn ($item) => [
                            'purchase_item_id' => $item->id,
                            'qty' => $item->qty,
                        ])->values()->all(),
                    ]);

                    app(ReceiptController::class)->store($receiptRequest);
                }

                // PO tidak bergantung pada Penerimaan Barang saat Faktur Pembelian disimpan.
                // Penerimaan adalah proses fisik terpisah yang menangani stok/HPP.
                // Faktur PO hanya mengakui sisi finansial agar tidak terjadi jurnal ganda.

                if ($purchase->source_type === 'po') {
                    $mapping = DB::table('business_unit_account_mappings')
                        ->where('business_unit_id', $purchase->business_unit_id)
                        ->whereIn('mapping_key', ['inventory', 'payable', 'cash', 'bank'])
                        ->pluck('account_id', 'mapping_key');

                    abort_unless(isset($mapping['inventory']), 422, 'Mapping akun inventory belum tersedia.');

                    $creditKey = match (strtolower((string) ($purchase->payment_method ?? 'credit'))) {
                        'cash', 'tunai' => 'cash',
                        'bank', 'transfer', 'qris' => 'bank',
                        default => 'payable',
                    };

                    abort_unless(isset($mapping[$creditKey]), 422, 'Mapping akun pembelian belum lengkap.');

                    $journalExists = DB::table('journals')
                        ->where('source_type', 'purchase_invoice')
                        ->where('source_id', $purchase->id)
                        ->exists();

                    if (!$journalExists) {
                        $journalId = DB::table('journals')->insertGetId([
                            'entity_id' => $purchase->entity_id,
                            'business_unit_id' => $purchase->business_unit_id,
                            'journal_no' => 'JRN-INV-'.$purchase->id,
                            'journal_date' => $purchase->purchase_date,
                            'source_type' => 'purchase_invoice',
                            'source_id' => $purchase->id,
                            'description' => 'Faktur pembelian '.$purchase->purchase_no,
                            'status' => 'posted',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $journalValue = round((float) $purchase->total, 2);

                        DB::table('journal_entries')->insert([
                            [
                                'journal_id' => $journalId,
                                'account_id' => $mapping['inventory'],
                                'debit' => $journalValue,
                                'credit' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                            [
                                'journal_id' => $journalId,
                                'account_id' => $mapping[$creditKey],
                                'debit' => 0,
                                'credit' => $journalValue,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        ]);
                    }
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

            $message = "Faktur Pembelian [{$purchaseNo}] berhasil diposting.";
            if ($request?->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => $message, 'data' => ['id' => $id, 'purchase_no' => $purchaseNo]]);
            }

            return redirect()->route('inventori.pembelian.show', $id)
                ->with('swal_success', $message);
        } catch (\Throwable $e) {
            if ($request?->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal memproses posting: ' . $e->getMessage(),
                ], 422);
            }

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

        $receiptIds = DB::table('receipts')
            ->where('purchase_id', $id)
            ->pluck('id');

        $journals = DB::table('journals')
            ->where(function ($query) use ($id, $receiptIds) {
                $query->where(function ($q) use ($id) {
                    $q->where('source_type', 'purchase_invoice')
                        ->where('source_id', $id);
                });

                if ($receiptIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($receiptIds) {
                        $q->where('source_type', 'receipt')
                            ->whereIn('source_id', $receiptIds);
                    });
                }
            })
            ->orderBy('id')
            ->get();

        foreach ($journals as $j) {
            $j->entries = DB::table('journal_entries as je')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                ->where('je.journal_id', $j->id)
                ->select('je.*', 'coa.code as account_code', 'coa.name as account_name')
                ->get();
        }

        foreach ($items as $item) {
            $receivedBase = (float) DB::table('receipt_items')
                ->where('purchase_item_id', $item->id)
                ->sum('base_qty');

            $returnedBase = (float) DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->join('purchase_items as rpi', 'rpi.id', '=', 'pri.purchase_item_id')
                ->where('rpi.purchase_id', $id)
                ->where('pri.purchase_item_id', $item->id)
                ->where('pr.status', 'posted')
                ->sum('pri.base_qty');

            $factor = (float) ($item->conversion_factor ?: 1);
            $basePerUnit = (float) $item->qty > 0
                ? (float) ($item->base_qty ?: ($item->qty * $factor)) / (float) $item->qty
                : $factor;

            $item->returnable_qty = max(0, ($receivedBase - $returnedBase) / max($basePerUnit, 0.0000001));
        }

        $purchaseReturns = DB::table('purchase_returns as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.entity_id', $p->entity_id)
            ->whereExists(function ($q) use ($id) {
                $q->select(DB::raw(1))
                    ->from('purchase_return_items as pri')
                    ->join('purchase_items as pi', 'pi.id', '=', 'pri.purchase_item_id')
                    ->whereColumn('pri.purchase_return_id', 'r.id')
                    ->where('pi.purchase_id', $id);
            })
            ->select(
                'r.id', 'r.return_no', 'r.return_date', 'r.status',
                DB::raw("(SELECT COALESCE(SUM(pri.return_value), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as total"),
                DB::raw("COALESCE(u.name, '-') as user_name"),
                DB::raw("(SELECT COALESCE(SUM(pri.qty), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as return_qty")
            )
            ->orderByDesc('r.id')
            ->get();

        return view('inventori.pembelian.faktur.show', compact('p', 'items', 'journals', 'warehouse', 'purchaseReturns'));
    }

    public function destroy(Request $request, $id)
    {
        try {
            $result = DB::transaction(function () use ($id) {
                $entityId = (int) (auth()->user()->entity_id ?? 1);

                $purchase = DB::table('purchases')
                    ->where('entity_id', $entityId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$purchase) {
                    throw new \RuntimeException('Faktur Pembelian tidak ditemukan.');
                }

                if ($purchase->status === 'cancelled') {
                    throw new \RuntimeException('Faktur Pembelian sudah dibatalkan.');
                }

                $period = DB::table('accounting_periods')
                    ->where('entity_id', $purchase->entity_id)
                    ->where('period_year', Carbon::parse($purchase->purchase_date)->year)
                    ->where('period_month', Carbon::parse($purchase->purchase_date)->month)
                    ->first();

                if ($period && in_array($period->status, ['closing', 'closed'], true)) {
                    throw new \RuntimeException('Faktur tidak dapat dibatalkan karena periode akuntansi sudah dalam proses closing atau sudah closed.');
                }

                $returnExists = DB::table('purchase_return_items as pri')
                    ->join('purchase_items as pi', 'pi.id', '=', 'pri.purchase_item_id')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->where('pi.purchase_id', $purchase->id)
                    ->where('pr.status', 'posted')
                    ->exists();

                if ($returnExists) {
                    throw new \RuntimeException('Faktur tidak dapat dibatalkan karena sudah memiliki retur pembelian yang POSTED.');
                }

                $receiptIds = DB::table('receipts')
                    ->where('purchase_id', $purchase->id)
                    ->where('status', 'posted')
                    ->lockForUpdate()
                    ->pluck('id');

                // Penerimaan yang terikat pada faktur dibalik saat faktur dibatalkan:
                // stok, movement, HPP history, dan dokumen/jurnal penerimaan harus ikut
                // kembali agar tidak meninggalkan stok atau nilai akuntansi yatim.
                if ($receiptIds->isNotEmpty()) {
                    foreach ($receiptIds as $receiptId) {
                        $receiptItems = DB::table('receipt_items')
                            ->where('receipt_id', $receiptId)
                            ->get();

                        foreach ($receiptItems as $item) {
                            $qty = (float) $item->base_qty;
                            if ($qty <= 0) {
                                continue;
                            }

                            $stock = DB::table('warehouses_stocks')
                                ->where('warehouse_id', $this->receiptWarehouseId($receiptId))
                                ->where('product_id', $item->product_id)
                                ->lockForUpdate()
                                ->first();

                            if (!$stock || (float) $stock->qty + 0.0000001 < $qty) {
                                throw new \RuntimeException(
                                    'Faktur tidak dapat dibatalkan karena stok ' . ($item->product_id) . ' pada gudang tidak mencukupi untuk membalik penerimaan.'
                                );
                            }

                            $oldQty = (float) $stock->qty;
                            $oldValue = $oldQty * (float) $stock->avg_cost;
                            $removeValue = $qty * (float) $item->base_unit_cost;
                            if ($oldValue + 0.01 < $removeValue) {
                                throw new \RuntimeException('Faktur tidak dapat dibatalkan karena nilai stok saat ini tidak mencukupi untuk membalik nilai penerimaan.');
                            }

                            $newQty = $oldQty - $qty;
                            $newValue = $oldValue - $removeValue;
                            $newAvg = $newQty > 0 ? $newValue / $newQty : 0;

                            DB::table('warehouses_stocks')
                                ->where('id', $stock->id)
                                ->update([
                                    'qty' => $newQty,
                                    'avg_cost' => round($newAvg, 9),
                                    'updated_at' => now(),
                                ]);

                            DB::table('stock_movements')->insert([
                                'entity_id' => $purchase->entity_id,
                                'business_unit_id' => $purchase->business_unit_id,
                                'warehouse_id' => $this->receiptWarehouseId($receiptId),
                                'product_id' => $item->product_id,
                                'unit_id' => $item->unit_id,
                                'transaction_qty' => -$item->qty,
                                'conversion_factor' => $item->conversion_factor,
                                'movement_type' => 'purchase_cancel',
                                'qty' => -$qty,
                                'unit_cost' => $item->base_unit_cost,
                                'reference_type' => 'purchase_cancel',
                                'reference_id' => $purchase->id,
                                'receipt_id' => $receiptId,
                                'occurred_at' => now(),
                                'created_by' => auth()->id(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $receiptJournalIds = DB::table('journals')
                            ->where('source_type', 'receipt')
                            ->where('source_id', $receiptId)
                            ->pluck('id');
                        if ($receiptJournalIds->isNotEmpty()) {
                            DB::table('journal_entries')->whereIn('journal_id', $receiptJournalIds)->delete();
                            DB::table('journals')->whereIn('id', $receiptJournalIds)->delete();
                        }

                        DB::table('receipt_invoice_allocations')
                            ->whereIn('receipt_item_id', function ($q) use ($receiptId) {
                                $q->select('id')->from('receipt_items')->where('receipt_id', $receiptId);
                            })
                            ->delete();

                        DB::table('receipt_items')->where('receipt_id', $receiptId)->delete();
                        DB::table('receipts')->where('id', $receiptId)->delete();
                    }

                    DB::table('purchase_price_histories')
                        ->where('reference_id', $purchase->id)
                        ->where('source', 'purchase')
                        ->delete();
                }

                $invoiceJournalIds = DB::table('journals')
                    ->where('source_type', 'purchase_invoice')
                    ->where('source_id', $purchase->id)
                    ->pluck('id');
                if ($invoiceJournalIds->isNotEmpty()) {
                    DB::table('journal_entries')->whereIn('journal_id', $invoiceJournalIds)->delete();
                    DB::table('journals')->whereIn('id', $invoiceJournalIds)->delete();
                }

                DB::table('purchases')
                    ->where('id', $purchase->id)
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' => now(),
                    ]);

                return $purchase->purchase_no;
            });

            $message = "Faktur Pembelian [{$result}] berhasil dibatalkan.";

            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => $message]);
            }

            return redirect()->route('inventori.pembelian.index')->with('swal_success', $message);
        } catch (\Throwable $e) {
            $message = 'Gagal membatalkan Faktur Pembelian: ' . $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 422);
            }

            return redirect()->back()->with('swal_error', $message);
        }
    }

    private function receiptWarehouseId($receiptId): int
    {
        return (int) DB::table('receipts')->where('id', $receiptId)->value('warehouse_id');
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
            ->leftJoin('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->whereNull('p.deleted_at')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate)
            ->select(
                'p.*',
                'p.purchase_no as invoice_no',
                'p.total as grand_total',
                'bu.name as business_unit_name',
                's.name as supplier_name',
                'po.po_no',
                'w.name as warehouse_name'
            )
            ->orderByDesc('p.created_at')
            ->get();

        return view('inventori.pembelian.faktur.print-list', compact('purchases', 'startDate', 'endDate'));
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new PurchaseInvoiceExport($request), 'Laporan_Rekap_Pembelian_' . date('Ymd_His') . '.xlsx');
    }

    private function generateInvoiceCode()
    {
        $date = Carbon::now();
        $dateStr = $date->format('Ymd');
        $monthStr = $date->format('Ym');

        $last = DB::table('purchases')
            ->where('purchase_no', 'LIKE', "FB-{$monthStr}%")
            ->orderByDesc('purchase_no')
            ->first();

        $nextSeq = $last ? ((int) substr($last->purchase_no, -5)) + 1 : 1;

        return 'FB-' . $dateStr . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
    }
}