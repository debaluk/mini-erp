<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exports\ReceiptExport;
use Maatwebsite\Excel\Facades\Excel;

class ReceiptController extends Controller
{
    private function entityId(): int
    {
        $entityId = (int) (auth()->user()->entity_id ?? 0);
        abort_unless($entityId > 0, 403, 'Entitas pengguna tidak valid.');
        return $entityId;
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get(['id','code','name']);

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name']);

        $warehouses = DB::table('warehouses')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name']);

        return view('inventori.pembelian.penerimaan.index', compact('businessUnits','suppliers','warehouses','startDate','endDate'));
    }

    public function data(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $query = DB::table('receipts as r')
            ->leftJoin('purchases as p', 'p.id', '=', 'r.purchase_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->leftJoin(DB::raw('(SELECT receipt_id, COUNT(*) as total_items FROM receipt_items GROUP BY receipt_id) ri'), 'ri.receipt_id', '=', 'r.id')
            ->where('r.entity_id', $this->entityId())
            ->whereDate('r.receipt_date', '>=', $startDate)
            ->whereDate('r.receipt_date', '<=', $endDate)
;
        if ($request->filled('business_unit_id')) $query->where('r.business_unit_id', $request->business_unit_id);
        if ($request->filled('supplier_id')) $query->where('r.supplier_id', $request->supplier_id);
        if ($request->filled('warehouse_id')) $query->where('r.warehouse_id', $request->warehouse_id);
        if ($request->filled('status')) $query->where('r.status', $request->status);

        $data = $query->select(
            'r.id', 'r.receipt_no', 'r.receipt_date', 'r.status',
            'po.id as purchase_order_id', 'po.po_no', 's.name as supplier_name', 'w.name as warehouse_name',
            'bu.name as business_unit_name', DB::raw('COALESCE(ri.total_items, 0) as total_items')
        )->orderByDesc('r.receipt_date')->orderByDesc('r.id')->get();

        foreach ($data as $row) {
            $row->formatted_date = \Carbon\Carbon::parse($row->receipt_date)->format('d/m/Y H:i');
        }

        return response()->json(['data' => $data]);
    }

    public function show(int $id)
    {
        $receipt = DB::table('receipts as r')
            ->leftJoin('purchases as p', 'p.id', '=', 'r.purchase_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->where('r.entity_id', $this->entityId())
            ->where('r.id', $id)
            ->select('r.*', 'po.po_no', 's.name as supplier_name', 'w.name as warehouse_name', 'bu.name as business_unit_name')
            ->firstOrFail();

        $items = DB::table('receipt_items as ri')
            ->join('products as p', 'p.id', '=', 'ri.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'ri.unit_id')
            ->where('ri.receipt_id', $id)
            ->select('ri.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('ri.id')
            ->get();

        foreach ($items as $item) {
            $item->line_value = (float) $item->base_qty * (float) $item->base_unit_cost;
        }

        $receipt->formatted_date = \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y H:i');

        return response()->json(['success' => true, 'receipt' => $receipt, 'items' => $items]);
    }

    public function print(int $id)
    {
        $response = $this->show($id);
        $payload = $response->getData();
        return view('inventori.pembelian.penerimaan.print', [
            'receipt' => $payload->receipt,
            'items' => $payload->items,
        ]);
    }

    public function export(Request $request)
    {
        $fileName = 'Laporan_Penerimaan_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new ReceiptExport($request), $fileName);
    }

    public function poModal(int $id)
    {
        $entity = $this->entityId();

        $po = DB::table('purchase_orders as po')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->where('po.entity_id', $entity)
            ->where('po.id', $id)
            ->whereNull('po.deleted_at')
            ->select(
                'po.id', 'po.po_no', 'po.po_date', 'po.status',
                'po.supplier_id', 'po.business_unit_id', 'po.warehouse_id',
                's.name as supplier_name',
                'w.code as warehouse_code', 'w.name as warehouse_name',
                'bu.name as business_unit_name'
            )
            ->first();

        abort_unless($po, 404, 'Purchase Order tidak ditemukan.');
        abort_unless(in_array($po->status, ['approved', 'partial'], true), 422, 'PO belum dapat diterima.');

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
            ->leftJoinSub(
                DB::table('receipt_items as ri')
                    ->join('receipts as r', 'r.id', '=', 'ri.receipt_id')
                    ->select('ri.purchase_order_item_id', DB::raw('SUM(ri.qty) as received_qty'))
                    ->whereNotNull('ri.purchase_order_item_id')
                    ->where('r.status', 'posted')
                    ->groupBy('ri.purchase_order_item_id'),
                'ri',
                'ri.purchase_order_item_id',
                '=',
                'poi.id'
            )
            ->where('poi.purchase_order_id', $po->id)
            ->select(
                'poi.id as purchase_order_item_id',
                'poi.product_id',
                'poi.unit_id',
                'poi.qty as ordered_qty',
                'p.code as product_code',
                'p.name as product_name',
                'u.code as unit_code',
                DB::raw('COALESCE(ri.received_qty, 0) as received_qty'),
                DB::raw('(poi.qty - COALESCE(ri.received_qty, 0)) as remaining_qty')
            )
            ->whereRaw('(poi.qty - COALESCE(ri.received_qty, 0)) > 0')
            ->orderBy('poi.id')
            ->get();

        return response()->json([
            'success' => true,
            'po' => $po,
            'items' => $items,
        ]);
    }

    public function create(Request $request)
    {
        $entity = $this->entityId();
        $poId = $request->integer('po_id');

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name','phone','address']);

        $warehouses = DB::table('warehouses')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name']);

        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)->where('p.is_active', 1)
            ->orderBy('p.name')
            ->get(['p.id','p.code','p.sku','p.name','u.code as unit_code','u.name as unit_name']);

        $po = null;
        $poItems = collect();

        if ($poId) {
            $po = DB::table('purchase_orders as po')
                ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
                ->join('warehouses as w', 'w.id', '=', 'po.warehouse_id')
                ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
                ->where('po.entity_id', $entity)
                ->where('po.id', $poId)
                ->whereNull('po.deleted_at')
                ->select('po.*', 's.name as supplier_name', 'w.name as warehouse_name', 'bu.name as business_unit_name')
                ->firstOrFail();

            abort_unless(in_array($po->status, ['approved', 'partial'], true), 422, 'PO belum dapat diterima.');

            $poItems = DB::table('purchase_order_items as poi')
                ->join('products as p', 'p.id', '=', 'poi.product_id')
                ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
                ->leftJoinSub(
                    DB::table('receipt_items')
                        ->select('purchase_order_item_id', DB::raw('SUM(qty) as received_qty'))
                        ->whereNotNull('purchase_order_item_id')
                        ->groupBy('purchase_order_item_id'),
                    'ri',
                    'ri.purchase_order_item_id',
                    '=',
                    'poi.id'
                )
                ->where('poi.purchase_order_id', $poId)
                ->select(
                    'poi.id as purchase_order_item_id',
                    'poi.product_id',
                    'poi.unit_id',
                    'poi.qty as ordered_qty',
                    'poi.conversion_factor',
                    'p.code as product_code',
                    'p.name as product_name',
                    'u.code as unit_code',
                    DB::raw('COALESCE(ri.received_qty, 0) as received_qty'),
                    DB::raw('(poi.qty - COALESCE(ri.received_qty, 0)) as remaining_qty')
                )
                ->whereRaw('(poi.qty - COALESCE(ri.received_qty, 0)) > 0')
                ->orderBy('poi.id')
                ->get();
        }

        return view('inventori.pembelian.penerimaan.create', compact(
            'suppliers','warehouses','products','po','poItems'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_id' => ['nullable', 'integer'],
            'po_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'receipt_date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['nullable', 'integer'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        abort_unless($request->filled('purchase_id') || $request->filled('po_id'), 422, 'Sumber penerimaan belum dipilih.');

        $entityId = $this->entityId();
        $userId = auth()->id();
        // Penerimaan hanya mencatat stok, moving average, dan mutasi stok.
        DB::transaction(function () use ($data, $entityId, $userId): void {
            $purchase = null;
            $po = null;

            if (!empty($data['po_id'])) {
                $po = DB::table('purchase_orders')
                    ->where('entity_id', $entityId)
                    ->where('id', $data['po_id'])
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                abort_unless($po, 404, 'Purchase Order tidak ditemukan.');
                abort_unless(in_array($po->status, ['approved', 'partial'], true), 422, 'PO belum dapat diterima.');

                $purchase = DB::table('purchases')
                    ->where('entity_id', $entityId)
                    ->where('purchase_order_id', $po->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$purchase) {
                    $date = Carbon::now();
                    $dateStr = $date->format('Ymd');
                    $monthStr = $date->format('Ym');
                    $lastPurchase = DB::table('purchases')
                        ->where('purchase_no', 'LIKE', "FB-{$monthStr}%")
                        ->orderByDesc('purchase_no')
                        ->first();
                    $nextSeq = $lastPurchase ? ((int) substr($lastPurchase->purchase_no, -5)) + 1 : 1;
                    $purchaseNo = 'FB-' . $dateStr . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);

                    $purchaseId = DB::table('purchases')->insertGetId([
                        'entity_id' => $entityId,
                        'business_unit_id' => $po->business_unit_id,
                        'supplier_id' => $po->supplier_id,
                        'purchase_order_id' => $po->id,
                        'user_id' => $userId ?? 1,
                        'document_type' => 'invoice',
                        'source_type' => 'po',
                        'goods_received' => false,
                        'posting_status' => 'draft',
                        'purchase_no' => $purchaseNo,
                        'supplier_invoice_no' => $purchaseNo,
                        'supplier_invoice_date' => $po->po_date,
                        'purchase_date' => $po->po_date,
                        'subtotal' => 0,
                        'discount' => 0,
                        'total' => 0,
                        'payment_method' => 'credit',
                        'due_date' => null,
                        'dpp' => 0,
                        'ppn_amount' => 0,
                        'tax_condition' => 'non_ppn',
                        'memo' => 'Purchase dari PO '.$po->po_no,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $poItems = DB::table('purchase_order_items')
                        ->where('purchase_order_id', $po->id)
                        ->orderBy('id')
                        ->get();

                    $subtotal = 0.0;
                    $discountTotal = 0.0;

                    foreach ($poItems as $poi) {
                        $qty = (float) $poi->qty;
                        $factor = (float) ($poi->conversion_factor ?: 1);
                        $baseQty = (float) ($poi->base_qty ?: ($qty * $factor));
                        $lineTotal = (float) $poi->total;
                        $unitCost = $qty > 0 ? $lineTotal / $qty : 0;
                        $baseUnitCost = $baseQty > 0 ? $lineTotal / $baseQty : 0;

                        DB::table('purchase_items')->insert([
                            'purchase_id' => $purchaseId,
                            'product_id' => $poi->product_id,
                            'unit_id' => $poi->unit_id,
                            'qty' => $qty,
                            'conversion_factor' => $factor,
                            'base_qty' => $baseQty,
                            'unit_cost' => $unitCost,
                            'base_unit_cost' => $baseUnitCost,
                            'discount' => (float) $poi->discount,
                            'total' => $lineTotal,
                            'line_subtotal' => $qty * (float) $poi->unit_price,
                            'line_discount' => (float) $poi->discount,
                            'taxable_amount' => $lineTotal,
                            'tax_rate' => 0,
                            'tax_amount' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $subtotal += $qty * (float) $poi->unit_price;
                        $discountTotal += (float) $poi->discount;
                    }

                    DB::table('purchases')->where('id', $purchaseId)->update([
                        'subtotal' => $subtotal,
                        'discount' => $discountTotal,
                        'total' => max(0, $subtotal - $discountTotal),
                        'dpp' => max(0, $subtotal - $discountTotal),
                        'updated_at' => now(),
                    ]);

                    $purchase = DB::table('purchases')->where('id', $purchaseId)->lockForUpdate()->first();
                }
            } else {
                $purchase = DB::table('purchases')
                    ->where('entity_id', $entityId)
                    ->where('id', $data['purchase_id'])
                    ->lockForUpdate()
                    ->first();

                abort_unless($purchase, 404, 'Faktur pembelian tidak ditemukan.');
                abort_if($purchase->status === 'cancelled', 422, 'Faktur pembelian sudah dibatalkan.');
            }

            $warehouseId = $po
                ? (int) $po->warehouse_id
                : (int) ($data['warehouse_id'] ?? 0);

            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entityId)
                ->where('id', $warehouseId)
                ->where('is_active', 1)
                ->first();

            abort_unless($warehouse, 422, 'Gudang tidak valid.');

            $businessUnit = DB::table('business_units')
                ->where('entity_id', $entityId)
                ->where('id', $purchase->business_unit_id)
                ->where('is_active', 1)
                ->first();
            abort_unless($businessUnit, 422, 'Unit Bisnis penerimaan tidak aktif atau bukan milik entitas pengguna.');

            if ((int) $warehouse->business_unit_id !== (int) $purchase->business_unit_id) {
                abort(422, 'Gudang harus berada pada Unit Bisnis pembelian.');
            }

            // Untuk PO, jurnal finansial dibuat saat Faktur Pembelian diposting.
            // Penerimaan hanya menangani stok/HPP agar tidak terjadi jurnal ganda.
            if ($po) {
                $createJournal = false;
            }

            $receiptDateBase = Carbon::now();
            $receiptDateStr = $receiptDateBase->format('Ymd');
            $receiptMonthStr = $receiptDateBase->format('Ym');

            $lastReceipt = DB::table('receipts')
                ->where('receipt_no', 'LIKE', "PB-{$receiptMonthStr}%")
                ->orderByDesc('receipt_no')
                ->first();

            $nextReceiptSeq = $lastReceipt ? ((int) substr($lastReceipt->receipt_no, -4)) + 1 : 1;
            $receiptNo = 'PB-' . $receiptDateStr . str_pad($nextReceiptSeq, 4, '0', STR_PAD_LEFT);
            $receiptDate = Carbon::parse($data['receipt_date'] ?? now()->toDateString())->format('Y-m-d H:i:s');
            $receiptPeriod = DB::table('accounting_periods')
                ->where('entity_id', $entityId)
                ->where('period_year', Carbon::parse($receiptDate)->year)
                ->where('period_month', Carbon::parse($receiptDate)->month)
                ->first();
            abort_if($receiptPeriod && in_array($receiptPeriod->status, ['closing', 'closed'], true), 422, 'Penerimaan tidak dapat diposting karena periode akuntansi sudah closing atau closed.');

            $receiptId = DB::table('receipts')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $purchase->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $purchase->supplier_id,
                'user_id' => $userId,
                'receipt_no' => $receiptNo,
                'receipt_date' => $receiptDate,
                'source_type' => $purchase->source_type ?? 'po',
                'purchase_id' => $purchase->id,
                'memo' => $data['memo'] ?? null,
                'status' => 'posted',
                'verification_status' => 'not_required',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalReceivedValue = 0.0;

            foreach ($data['items'] as $row) {
                $purchaseItem = null;
                $purchaseOrderItemId = $row['purchase_order_item_id'] ?? null;

                if ($purchaseOrderItemId) {
                    abort_unless($po && (int) $purchaseOrderItemId > 0, 422, 'Item PO tidak valid.');

                    $poi = DB::table('purchase_order_items')
                        ->where('id', $purchaseOrderItemId)
                        ->where('purchase_order_id', $po->id)
                        ->lockForUpdate()
                        ->first();

                    abort_unless($poi, 422, 'Item PO tidak valid.');

                    $purchaseItem = DB::table('purchase_items as pi')
                        ->join('products as p', 'p.id', '=', 'pi.product_id')
                        ->where('pi.purchase_id', $purchase->id)
                        ->where('pi.product_id', $poi->product_id)
                        ->orderBy('pi.id')
                        ->select('pi.*', 'p.base_unit_id')
                        ->first();

                    abort_unless($purchaseItem, 422, 'Item pembelian untuk PO tidak ditemukan.');
                } else {
                    $purchaseItem = DB::table('purchase_items as pi')
                        ->join('products as p', 'p.id', '=', 'pi.product_id')
                        ->where('pi.id', $row['purchase_item_id'] ?? 0)
                        ->where('pi.purchase_id', $purchase->id)
                        ->where('p.entity_id', $entityId)
                        ->lockForUpdate()
                        ->select('pi.*', 'p.base_unit_id')
                        ->first();
                }

                abort_unless($purchaseItem, 422, 'Item pembelian tidak valid.');

                $qty = (float) $row['qty'];
                $factor = (float) ($purchaseItem->conversion_factor ?: 1);
                $baseQtyTotal = (float) ($purchaseItem->base_qty ?: ($purchaseItem->qty * $factor));
                $baseQtyPerTransaction = (float) $purchaseItem->qty > 0 ? $baseQtyTotal / (float) $purchaseItem->qty : $factor;

                $receivedBase = DB::table('receipt_items')
                    ->where('purchase_item_id', $purchaseItem->id)
                    ->sum('base_qty');

                $remainingBase = $baseQtyTotal - (float) $receivedBase;
                $requestedBase = $qty * $baseQtyPerTransaction;

                abort_if($requestedBase > $remainingBase + 0.0000001, 422, 'Qty penerimaan melebihi sisa qty pembelian.');

                $baseUnitCost = (float) ($purchaseItem->base_unit_cost ?: ((float) $purchaseItem->unit_cost / $baseQtyPerTransaction));
                $value = $requestedBase * $baseUnitCost;
                $totalReceivedValue += $value;

                DB::table('receipt_items')->insert([
                    'receipt_id' => $receiptId,
                    'purchase_order_item_id' => $purchaseOrderItemId,
                    'purchase_item_id' => $purchaseItem->id,
                    'product_id' => $purchaseItem->product_id,
                    'unit_id' => $purchaseItem->unit_id ?: ($purchaseItem->base_unit_id ?? null),
                    'qty' => $qty,
                    'conversion_factor' => $baseQtyPerTransaction,
                    'base_qty' => $requestedBase,
                    'unit_cost' => $purchaseItem->unit_cost,
                    'base_unit_cost' => $baseUnitCost,
                    'valuation_status' => 'valued',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stock = DB::table('warehouses_stocks')
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $purchaseItem->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $oldQty = (float) $stock->qty;
                    $oldAvg = (float) $stock->avg_cost;
                    $newQty = $oldQty + $requestedBase;
                    $newAvg = $newQty > 0 ? (($oldQty * $oldAvg) + ($requestedBase * $baseUnitCost)) / $newQty : 0;

                    DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                        'qty' => $newQty,
                        'avg_cost' => round($newAvg, 9),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('warehouses_stocks')->insert([
                        'entity_id' => $entityId,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $purchaseItem->product_id,
                        'qty' => $requestedBase,
                        'avg_cost' => round($baseUnitCost, 9),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('stock_movements')->insert([
                    'entity_id' => $entityId,
                    'business_unit_id' => $purchase->business_unit_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $purchaseItem->product_id,
                    'unit_id' => $purchaseItem->base_unit_id,
                    'transaction_qty' => $qty,
                    'conversion_factor' => $baseQtyPerTransaction,
                    'movement_type' => 'purchase_in',
                    'qty' => $requestedBase,
                    'unit_cost' => $baseUnitCost,
                    'reference_type' => 'receipt',
                    'reference_id' => $receiptId,
                    'receipt_id' => $receiptId,
                    'occurred_at' => now(),
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('purchase_price_histories')->insert([
                    'entity_id' => $entityId,
                    'business_unit_id' => $purchase->business_unit_id,
                    'product_id' => $purchaseItem->product_id,
                    'unit_id' => $purchaseItem->unit_id ?: ($purchaseItem->base_unit_id ?? null),
                    'supplier_id' => $purchase->supplier_id,
                    'price_date' => $purchase->purchase_date,
                    'qty' => $qty,
                    'conversion_factor' => $baseQtyPerTransaction,
                    'base_qty' => $requestedBase,
                    'unit_price' => $purchaseItem->unit_cost,
                    'base_unit_price' => $baseUnitCost,
                    'source' => 'purchase',
                    'reference_id' => $purchase->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('receipt_invoice_allocations')->insert([
                    'receipt_item_id' => DB::table('receipt_items')->where('receipt_id', $receiptId)->where('purchase_item_id', $purchaseItem->id)->latest('id')->value('id'),
                    'purchase_item_id' => $purchaseItem->id,
                    'qty' => $qty,
                    'conversion_factor' => $baseQtyPerTransaction,
                    'base_qty' => $requestedBase,
                    'allocated_value' => round($value, 2),
                    'status' => 'posted',
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $totalBasePurchased = DB::table('receipt_items')->where('receipt_id', $receiptId)->sum('base_qty');
            $totalBaseExpected = DB::table('purchase_items')
                ->where('purchase_id', $purchase->id)
                ->sum(DB::raw('COALESCE(base_qty, qty * COALESCE(conversion_factor, 1))'));

            DB::table('purchases')->where('id', $purchase->id)->update([
                'goods_received' => true,
                'posting_status' => $totalBasePurchased + 0.0000001 >= $totalBaseExpected ? 'posted' : 'partial_received',
                'posted_at' => now(),
                'posted_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($po) {
                $totalOrdered = (float) DB::table('purchase_order_items')
                    ->where('purchase_order_id', $po->id)
                    ->sum('qty');

                $totalReceived = (float) DB::table('receipt_items')
                    ->where('purchase_order_item_id', '!=', null)
                    ->whereIn('purchase_order_item_id', function ($q) use ($po) {
                        $q->select('id')->from('purchase_order_items')->where('purchase_order_id', $po->id);
                    })
                    ->sum('qty');

                $newStatus = $totalReceived + 0.0000001 >= $totalOrdered
                    ? 'completed'
                    : ($totalReceived > 0 ? 'partial' : 'approved');

                DB::table('purchase_orders')->where('id', $po->id)->update([
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Penerimaan berhasil diposting; stok dan moving average diperbarui.',
            ]);
        }

        return redirect()
            ->route('inventori.penerimaan')
            ->with('success', 'Penerimaan berhasil diposting; stok dan moving average diperbarui.');
    }

    /**
     * Balik dan kosongkan item penerimaan Non-PO sebelum item faktur diperbarui.
     * Semua perubahan stok untuk edit faktur tetap berada di gerbang Penerimaan.
     */
    public function clearNonPoReceiptItemsForEdit(int $purchaseId, int $entityId): void
    {
        abort_unless($this->entityId() === $entityId, 403, 'Entitas pengguna tidak valid.');

        $purchase = DB::table('purchases')
            ->where('entity_id', $entityId)
            ->where('id', $purchaseId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
        abort_unless($purchase && $purchase->source_type !== 'po', 422, 'Penerimaan edit hanya berlaku untuk pembelian Non-PO.');

        $receipt = DB::table('receipts')
            ->where('entity_id', $entityId)
            ->where('purchase_id', $purchaseId)
            ->where('status', 'posted')
            ->lockForUpdate()
            ->first();
        abort_unless($receipt, 422, 'Penerimaan Non-PO tidak ditemukan.');
        $period = DB::table('accounting_periods')
            ->where('entity_id', $entityId)
            ->where('period_year', Carbon::parse($receipt->receipt_date)->year)
            ->where('period_month', Carbon::parse($receipt->receipt_date)->month)
            ->first();
        abort_if($period && in_array($period->status, ['closing', 'closed'], true), 422, 'Penerimaan tidak dapat diedit karena periode akuntansi sudah closing atau closed.');

        $items = DB::table('receipt_items')->where('receipt_id', $receipt->id)->lockForUpdate()->get();
        foreach ($items as $item) {
            $qty = (float) $item->base_qty;
            if ($qty <= 0) continue;

            $stock = DB::table('warehouses_stocks')
                ->where('warehouse_id', $receipt->warehouse_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();
            if (!$stock || (float) $stock->qty + 0.0000001 < $qty) {
                throw new \RuntimeException('Stok tidak mencukupi untuk mengubah faktur '.$purchase->purchase_no.'.');
            }

            $oldQty = (float) $stock->qty;
            $newQty = $oldQty - $qty;
            $newValue = ($oldQty * (float) $stock->avg_cost) - ($qty * (float) $item->base_unit_cost);
            if ($newValue < -0.01) {
                throw new \RuntimeException('Nilai stok tidak mencukupi untuk membalik penerimaan saat edit faktur.');
            }

            DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                'qty' => $newQty,
                'avg_cost' => round($newQty > 0 ? max(0, $newValue / $newQty) : 0, 9),
                'updated_at' => now(),
            ]);

            DB::table('stock_movements')->insert([
                'entity_id' => $purchase->entity_id,
                'business_unit_id' => $purchase->business_unit_id,
                'warehouse_id' => $receipt->warehouse_id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'transaction_qty' => -(float) $item->qty,
                'conversion_factor' => $item->conversion_factor,
                'movement_type' => 'purchase_edit_out',
                'qty' => -$qty,
                'unit_cost' => $item->base_unit_cost,
                'reference_type' => 'purchase_edit',
                'reference_id' => $purchase->id,
                'receipt_id' => $receipt->id,
                'occurred_at' => now(),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('receipt_invoice_allocations')->whereIn('receipt_item_id', function ($q) use ($receipt) {
            $q->select('id')->from('receipt_items')->where('receipt_id', $receipt->id);
        })->delete();
        DB::table('receipt_items')->where('receipt_id', $receipt->id)->delete();
    }

    /**
     * Terapkan item faktur Non-PO yang baru melalui mekanisme Penerimaan.
     * Pemanggil harus sudah berada dalam transaksi database.
     */
    public function replaceNonPoReceiptItems(int $purchaseId, int $entityId): void
    {
        abort_unless($this->entityId() === $entityId, 403, 'Entitas pengguna tidak valid.');

        $purchase = DB::table('purchases')
            ->where('entity_id', $entityId)
            ->where('id', $purchaseId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
        abort_unless($purchase && $purchase->source_type !== 'po', 422, 'Penerimaan edit hanya berlaku untuk pembelian Non-PO.');

        $receipt = DB::table('receipts')
            ->where('entity_id', $entityId)
            ->where('purchase_id', $purchaseId)
            ->where('status', 'posted')
            ->lockForUpdate()
            ->first();
        abort_unless($receipt, 422, 'Penerimaan Non-PO tidak ditemukan.');

        $businessUnit = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('id', $purchase->business_unit_id)
            ->where('is_active', 1)
            ->first();
        abort_unless($businessUnit, 422, 'Unit Bisnis penerimaan tidak aktif atau bukan milik entitas pengguna.');

        $warehouse = DB::table('warehouses')
            ->where('entity_id', $entityId)
            ->where('id', $receipt->warehouse_id)
            ->where('is_active', 1)
            ->first();
        abort_unless($warehouse && (int) $warehouse->business_unit_id === (int) $purchase->business_unit_id, 422, 'Gudang penerimaan tidak valid untuk Unit Bisnis.');

        $items = DB::table('purchase_items')
            ->where('purchase_id', $purchaseId)
            ->orderBy('id')
            ->get();
        abort_if($items->isEmpty(), 422, 'Faktur pembelian tidak memiliki item untuk diterima.');

        foreach ($items as $item) {
            $product = DB::table('products')
                ->where('entity_id', $entityId)
                ->where('id', $item->product_id)
                ->where('is_active', 1)
                ->first();
            abort_unless($product, 422, 'Produk penerimaan tidak aktif atau bukan milik entitas pengguna.');

            $qty = (float) $item->qty;
            $factor = (float) ($item->conversion_factor ?: 1);
            $baseQty = (float) ($item->base_qty ?: ($qty * $factor));
            $baseCost = (float) $item->base_unit_cost;
            abort_if($qty <= 0 || $baseQty <= 0 || $baseCost < 0, 422, 'Qty atau harga penerimaan tidak valid.');

            $receiptItemId = DB::table('receipt_items')->insertGetId([
                'receipt_id' => $receipt->id,
                'purchase_order_item_id' => null,
                'purchase_item_id' => $item->id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id ?: $product->base_unit_id,
                'qty' => $qty,
                'conversion_factor' => $factor,
                'base_qty' => $baseQty,
                'unit_cost' => $item->unit_cost,
                'base_unit_cost' => $baseCost,
                'valuation_status' => 'valued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('receipt_invoice_allocations')->insert([
                'receipt_item_id' => $receiptItemId,
                'purchase_item_id' => $item->id,
                'qty' => $qty,
                'conversion_factor' => $factor,
                'base_qty' => $baseQty,
                'allocated_value' => round((float) $item->total, 2),
                'status' => 'posted',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stock = DB::table('warehouses_stocks')
                ->where('warehouse_id', $receipt->warehouse_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $oldQty = (float) $stock->qty;
                $newQty = $oldQty + $baseQty;
                $newAvg = $newQty > 0 ? (($oldQty * (float) $stock->avg_cost) + ($baseQty * $baseCost)) / $newQty : 0;
                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => $newQty,
                    'avg_cost' => round($newAvg, 9),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('warehouses_stocks')->insert([
                    'entity_id' => $entityId,
                    'warehouse_id' => $receipt->warehouse_id,
                    'product_id' => $item->product_id,
                    'qty' => $baseQty,
                    'avg_cost' => round($baseCost, 9),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('stock_movements')->insert([
                'entity_id' => $entityId,
                'business_unit_id' => $purchase->business_unit_id,
                'warehouse_id' => $receipt->warehouse_id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id ?: $product->base_unit_id,
                'transaction_qty' => $qty,
                'conversion_factor' => $factor,
                'movement_type' => 'purchase_edit_in',
                'qty' => $baseQty,
                'unit_cost' => $baseCost,
                'reference_type' => 'purchase_edit',
                'reference_id' => $purchase->id,
                'receipt_id' => $receipt->id,
                'occurred_at' => now(),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('purchase_price_histories')->insert([
                'entity_id' => $entityId,
                'business_unit_id' => $purchase->business_unit_id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id ?: $product->base_unit_id,
                'supplier_id' => $purchase->supplier_id,
                'price_date' => $purchase->purchase_date,
                'qty' => $qty,
                'conversion_factor' => $factor,
                'base_qty' => $baseQty,
                'unit_price' => $item->unit_cost,
                'base_unit_price' => $baseCost,
                'source' => 'purchase',
                'reference_id' => $purchase->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Membalik dan membatalkan penerimaan yang terikat ke faktur pembelian.
     * Dipakai oleh pembatalan faktur agar stok tidak dimodifikasi di controller faktur.
     */
    public function cancelForPurchaseInvoice(int $purchaseId, int $entityId): void
    {
        abort_unless($this->entityId() === $entityId, 403, 'Entitas pengguna tidak valid.');

        $purchase = DB::table('purchases')
            ->where('entity_id', $entityId)
            ->where('id', $purchaseId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
        abort_unless($purchase, 404, 'Faktur pembelian tidak ditemukan.');

        $receipts = DB::table('receipts')
            ->where('entity_id', $entityId)
            ->where('purchase_id', $purchaseId)
            ->where('status', 'posted')
            ->lockForUpdate()
            ->get();

        foreach ($receipts as $receipt) {
            $period = DB::table('accounting_periods')
                ->where('entity_id', $entityId)
                ->where('period_year', Carbon::parse($receipt->receipt_date)->year)
                ->where('period_month', Carbon::parse($receipt->receipt_date)->month)
                ->first();
            abort_if($period && in_array($period->status, ['closing', 'closed'], true), 422, 'Penerimaan tidak dapat dibatalkan karena periode akuntansi sudah closing atau closed.');

            $items = DB::table('receipt_items')->where('receipt_id', $receipt->id)->lockForUpdate()->get();
            foreach ($items as $item) {
                $qty = (float) $item->base_qty;
                if ($qty <= 0) continue;

                $stock = DB::table('warehouses_stocks')
                    ->where('warehouse_id', $receipt->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();
                if (!$stock || (float) $stock->qty + 0.0000001 < $qty) {
                    throw new \RuntimeException('Faktur tidak dapat dibatalkan karena stok barang pada gudang tidak mencukupi untuk membalik penerimaan.');
                }

                $oldQty = (float) $stock->qty;
                $oldValue = $oldQty * (float) $stock->avg_cost;
                $removeValue = $qty * (float) $item->base_unit_cost;
                if ($oldValue + 0.01 < $removeValue) {
                    throw new \RuntimeException('Faktur tidak dapat dibatalkan karena nilai stok saat ini tidak mencukupi untuk membalik nilai penerimaan.');
                }

                $newQty = $oldQty - $qty;
                $newValue = max(0, $oldValue - $removeValue);
                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => $newQty,
                    'avg_cost' => round($newQty > 0 ? $newValue / $newQty : 0, 9),
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entityId,
                    'business_unit_id' => $purchase->business_unit_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'transaction_qty' => -(float) $item->qty,
                    'conversion_factor' => $item->conversion_factor,
                    'movement_type' => 'purchase_cancel',
                    'qty' => -$qty,
                    'unit_cost' => $item->base_unit_cost,
                    'reference_type' => 'purchase_cancel',
                    'reference_id' => $purchase->id,
                    'receipt_id' => $receipt->id,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $journalIds = DB::table('journals')
                ->where('entity_id', $entityId)
                ->where('source_type', 'receipt')
                ->where('source_id', $receipt->id)
                ->pluck('id');
            if ($journalIds->isNotEmpty()) {
                DB::table('journal_entries')->whereIn('journal_id', $journalIds)->delete();
                DB::table('journals')->whereIn('id', $journalIds)->delete();
            }

            DB::table('receipt_invoice_allocations')->whereIn('receipt_item_id', function ($q) use ($receipt) {
                $q->select('id')->from('receipt_items')->where('receipt_id', $receipt->id);
            })->delete();
            DB::table('receipt_items')->where('receipt_id', $receipt->id)->delete();
            DB::table('receipts')->where('entity_id', $entityId)->where('id', $receipt->id)->delete();
        }

        DB::table('purchase_price_histories')
            ->where('entity_id', $entityId)
            ->where('reference_id', $purchaseId)
            ->where('source', 'purchase')
            ->delete();
    }

    public function cancel(Request $request, int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $entityId = $this->entityId();
                $userId = auth()->id();

                $receipt = DB::table('receipts')
                    ->where('entity_id', $entityId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($receipt, 404, 'Penerimaan tidak ditemukan.');
                abort_if($receipt->status !== 'posted', 422, 'Penerimaan sudah dibatalkan.');

                // Jangan membatalkan penerimaan yang sudah dipakai sebagai sumber retur.
                $hasActiveReturn = DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->join('receipt_items as ri', 'ri.id', '=', 'pri.receipt_item_id')
                    ->where('ri.receipt_id', $receipt->id)
                    ->whereIn('pr.status', ['draft', 'posted'])
                    ->exists();

                abort_if(
                    $hasActiveReturn,
                    422,
                    'Penerimaan tidak dapat dibatalkan karena sudah memiliki Retur Pembelian.'
                );

                $purchase = DB::table('purchases')
                    ->where('entity_id', $entityId)
                    ->where('id', $receipt->purchase_id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($purchase, 422, 'Faktur pembelian penerimaan tidak ditemukan.');
                abort_unless($purchase->purchase_order_id, 422, 'Penerimaan Non-PO tidak dibatalkan dari menu ini.');

                $period = DB::table('accounting_periods')
                    ->where('entity_id', $receipt->entity_id)
                    ->where('period_year', Carbon::parse($receipt->receipt_date)->year)
                    ->where('period_month', Carbon::parse($receipt->receipt_date)->month)
                    ->first();

                if ($period && in_array($period->status, ['closing', 'closed'], true)) {
                    throw new \RuntimeException('Penerimaan tidak dapat dibatalkan karena periode akuntansi sudah dalam proses closing atau sudah closed.');
                }

                $items = DB::table('receipt_items')
                    ->where('receipt_id', $receipt->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($items as $item) {
                    $qty = (float) $item->base_qty;
                    if ($qty <= 0) continue;

                    $stock = DB::table('warehouses_stocks')
                        ->where('warehouse_id', $receipt->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || (float) $stock->qty + 0.0000001 < $qty) {
                        throw new \RuntimeException('Penerimaan tidak dapat dibatalkan karena stok barang tidak mencukupi untuk membalik penerimaan.');
                    }

                    $oldQty = (float) $stock->qty;
                    $oldValue = $oldQty * (float) $stock->avg_cost;
                    $removeValue = $qty * (float) ($item->base_unit_cost ?? 0);

                    if ($oldValue + 0.01 < $removeValue) {
                        throw new \RuntimeException('Penerimaan tidak dapat dibatalkan karena nilai stok saat ini tidak mencukupi untuk membalik nilai penerimaan.');
                    }

                    $newQty = $oldQty - $qty;
                    $newValue = max(0, $oldValue - $removeValue);

                    DB::table('warehouses_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty' => $newQty,
                            'avg_cost' => round($newQty > 0 ? $newValue / $newQty : 0, 9),
                            'updated_at' => now(),
                        ]);

                    DB::table('stock_movements')->insert([
                        'entity_id' => $receipt->entity_id,
                        'business_unit_id' => $receipt->business_unit_id,
                        'warehouse_id' => $receipt->warehouse_id,
                        'product_id' => $item->product_id,
                        'unit_id' => $item->unit_id,
                        'transaction_qty' => -(float) $item->qty,
                        'conversion_factor' => $item->conversion_factor,
                        'movement_type' => 'purchase_cancel',
                        'qty' => -$qty,
                        'unit_cost' => (float) ($item->base_unit_cost ?? 0),
                        'reference_type' => 'receipt_cancel',
                        'reference_id' => $receipt->id,
                        'receipt_id' => $receipt->id,
                        'occurred_at' => now(),
                        'created_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('receipt_invoice_allocations')
                        ->where('receipt_item_id', $item->id)
                        ->update([
                            'status' => 'cancelled',
                            'updated_at' => now(),
                        ]);
                }

                DB::table('receipts')
                    ->where('id', $receipt->id)
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' => now(),
                    ]);

                if ($purchase) {
                    $activeReceived = (float) DB::table('receipt_items as ri')
                        ->join('receipts as r', 'r.id', '=', 'ri.receipt_id')
                        ->where('r.purchase_id', $purchase->id)
                        ->where('r.status', 'posted')
                        ->sum('ri.base_qty');

                    DB::table('purchases')
                        ->where('id', $purchase->id)
                        ->update([
                            'goods_received' => $activeReceived > 0,
                            'updated_at' => now(),
                        ]);
                }

                if ($purchase && $purchase->purchase_order_id) {
                    $poId = (int) $purchase->purchase_order_id;
                    $totalOrdered = (float) DB::table('purchase_order_items')
                        ->where('purchase_order_id', $poId)
                        ->sum('qty');

                    $totalReceived = (float) DB::table('receipt_items as ri')
                        ->join('receipts as r', 'r.id', '=', 'ri.receipt_id')
                        ->join('purchase_order_items as poi', 'poi.id', '=', 'ri.purchase_order_item_id')
                        ->where('poi.purchase_order_id', $poId)
                        ->where('r.status', 'posted')
                        ->sum('ri.qty');

                    $newStatus = $totalReceived + 0.0000001 >= $totalOrdered
                        ? 'completed'
                        : ($totalReceived > 0 ? 'partial' : 'approved');

                    DB::table('purchase_orders')
                        ->where('id', $poId)
                        ->update([
                            'status' => $newStatus,
                            'updated_at' => now(),
                        ]);
                }
            });

            return response()->json(['success' => true, 'message' => 'Proses batal berhasil']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function edit(int $id)
    {
        abort(404);
    }
}
