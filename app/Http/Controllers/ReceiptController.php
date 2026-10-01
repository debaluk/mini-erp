<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\ReceiptExport;
use Maatwebsite\Excel\Facades\Excel;

class ReceiptController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $businessUnits = DB::table('business_units')
            ->where('is_active', 1)->orderBy('code')->get(['id','code','name']);

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
            ->whereNull('r.deleted_at');

        if ($request->filled('business_unit_id')) $query->where('r.business_unit_id', $request->business_unit_id);
        if ($request->filled('supplier_id')) $query->where('r.supplier_id', $request->supplier_id);
        if ($request->filled('warehouse_id')) $query->where('r.warehouse_id', $request->warehouse_id);
        if ($request->filled('status')) $query->where('r.status', $request->status);

        $data = $query->select(
            'r.id', 'r.receipt_no', 'r.receipt_date', 'r.status',
            'po.po_no', 's.name as supplier_name', 'w.name as warehouse_name',
            'bu.name as business_unit_name', DB::raw('COALESCE(ri.total_items, 0) as total_items')
        )->orderByDesc('r.receipt_date')->orderByDesc('r.id')->get();

        foreach ($data as $row) {
            $row->formatted_date = CarbonCarbon::parse($row->receipt_date)->format('d/m/Y H:i');
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
            ->whereNull('r.deleted_at')
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

        $receipt->formatted_date = CarbonCarbon::parse($receipt->receipt_date)->format('d/m/Y H:i');

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

    public function create()
    {
        $entity = $this->entityId();

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

        return view('inventori.pembelian.penerimaan.create', compact('suppliers','warehouses','products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'receipt_date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $entityId = $this->entityId();
        $userId = auth()->id();

        DB::transaction(function () use ($data, $entityId, $userId): void {
            $purchase = DB::table('purchases')
                ->where('entity_id', $entityId)
                ->where('id', $data['purchase_id'])
                ->lockForUpdate()
                ->first();
            abort_unless($purchase, 404, 'Faktur pembelian tidak ditemukan.');
            abort_if($purchase->status === 'cancelled', 422, 'Faktur pembelian sudah dibatalkan.');

            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entityId)
                ->where('id', $data['warehouse_id'])
                ->where('is_active', 1)
                ->first();
            abort_unless($warehouse, 422, 'Gudang tidak valid.');
            abort_unless((int) $warehouse->business_unit_id === (int) $purchase->business_unit_id, 422, 'Gudang harus berada pada unit bisnis pembelian.');

            $receiptNo = 'GRN-'.now()->format('YmdHis').'-'.str()->upper(str()->random(3));
            $receiptDate = $data['receipt_date'] ?? now()->toDateString();
            $receiptDate = \Carbon\Carbon::parse($receiptDate)->format('Y-m-d H:i:s');

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
                $purchaseItem = DB::table('purchase_items as pi')
                    ->join('products as p', 'p.id', '=', 'pi.product_id')
                    ->where('pi.id', $row['purchase_item_id'])
                    ->where('pi.purchase_id', $purchase->id)
                    ->where('p.entity_id', $entityId)
                    ->lockForUpdate()
                    ->select('pi.*', 'p.base_unit_id')
                    ->first();
                abort_unless($purchaseItem, 422, 'Item pembelian tidak valid.');

                $qty = (float) $row['qty'];
                $factor = (float) ($purchaseItem->conversion_factor ?: 1);
                $baseQty = (float) ($purchaseItem->base_qty ?: ($purchaseItem->qty * $factor));
                $baseQtyPerTransaction = (float) $purchaseItem->qty > 0 ? $baseQty / (float) $purchaseItem->qty : $factor;

                $receivedBase = DB::table('receipt_items')
                    ->where('purchase_item_id', $purchaseItem->id)
                    ->sum('base_qty');
                $remainingBase = (float) $baseQty - (float) $receivedBase;
                $requestedBase = $qty * $baseQtyPerTransaction;
                abort_if($requestedBase > $remainingBase + 0.0000001, 422, 'Qty penerimaan melebihi sisa qty pembelian.');

                $baseUnitCost = (float) ($purchaseItem->base_unit_cost ?: ((float) $purchaseItem->unit_cost / $baseQtyPerTransaction));
                $value = $requestedBase * $baseUnitCost;
                $totalReceivedValue += $value;

                DB::table('receipt_items')->insert([
                    'receipt_id' => $receiptId,
                    'purchase_order_item_id' => null,
                    'purchase_item_id' => $purchaseItem->id,
                    'product_id' => $purchaseItem->product_id,
                    'unit_id' => $purchaseItem->unit_id ?: $purchaseItem->base_unit_id,
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
                    'unit_id' => $purchaseItem->unit_id ?: $purchaseItem->base_unit_id,
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

            $journalId = DB::table('journals')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $purchase->business_unit_id,
                'journal_no' => 'JRN-GRN-'.$receiptId,
                'journal_date' => $receiptDate,
                'source_type' => 'receipt',
                'source_id' => $receiptId,
                'description' => 'Penerimaan pembelian '.$purchase->purchase_no,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('journal_entries')->insert([
                ['journal_id' => $journalId, 'account_id' => $mapping['inventory'], 'debit' => round($totalReceivedValue, 2), 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_id' => $journalId, 'account_id' => $mapping[$creditKey], 'debit' => 0, 'credit' => round($totalReceivedValue, 2), 'created_at' => now(), 'updated_at' => now()],
            ]);

            $totalBasePurchased = DB::table('receipt_items')->where('receipt_id', $receiptId)->sum('base_qty');
            $totalBaseExpected = DB::table('purchase_items')
                ->where('purchase_id', $purchase->id)
                ->sum(DB::raw('COALESCE(base_qty, qty * COALESCE(conversion_factor, 1))'));

            if ($totalBasePurchased > 0) {
                DB::table('purchases')->where('id', $purchase->id)->update([
                    'goods_received' => true,
                    'posting_status' => $totalBasePurchased + 0.0000001 >= $totalBaseExpected ? 'posted' : 'partial_received',
                    'posted_at' => now(),
                    'posted_by' => $userId,
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Penerimaan berhasil diposting; stok dan moving average diperbarui.');
    }

    public function edit(int $id)
    {
        abort(404);
    }
}
