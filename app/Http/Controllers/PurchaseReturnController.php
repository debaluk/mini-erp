<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\BusinessUnit;
use App\Models\Warehouse;

class PurchaseReturnController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 422, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

    private function nextReturnNumber(int $entity, string $returnDate): string
    {
        $prefix = 'RB-'.date('Ymd', strtotime($returnDate));
        $lastNumber = DB::table('purchase_returns')
            ->where('entity_id', $entity)
            ->where('return_no', 'like', $prefix.'%')
            ->orderByDesc('return_no')
            ->lockForUpdate()
            ->value('return_no');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, 11)) + 1 : 1;

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());
        $businessUnitId = $request->query('business_unit_id');
        $warehouseId = $request->query('warehouse_id');
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses = Warehouse::where('is_active', 1)->orderBy('name')->get();

        return view('inventori.pembelian.retur.index', compact('businessUnits', 'warehouses', 'startDate', 'endDate', 'businessUnitId', 'warehouseId'));
    }

    public function data(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());
        $businessUnitId = $request->input('business_unit_id');
        $warehouseId = $request->input('warehouse_id');

        $query = DB::table('purchase_returns as r')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.entity_id', $entity)
            ->whereBetween('r.return_date', [$start.' 00:00:00', $end.' 23:59:59']);

        if ($businessUnitId) $query->where('r.business_unit_id', $businessUnitId);
        if ($warehouseId) $query->where('r.warehouse_id', $warehouseId);

        $recordsTotal = (clone $query)->count('r.id');

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('r.return_no', 'like', $like)
                    ->orWhere('s.name', 'like', $like)
                    ->orWhere('w.name', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->select(DB::raw(1))
                            ->from('purchase_return_items as pri')
                            ->join('purchase_items as pi', 'pi.id', '=', 'pri.purchase_item_id')
                            ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
                            ->whereColumn('pri.purchase_return_id', 'r.id')
                            ->where('p.purchase_no', 'like', $like);
                    });
            });
        }

        $recordsFiltered = (clone $query)->count('r.id');

        $rows = $query
            ->select(
                'r.id', 'r.return_no', 'r.return_date', 'r.status',
                DB::raw("(SELECT p.purchase_no FROM purchase_return_items pri JOIN purchase_items pi ON pi.id = pri.purchase_item_id JOIN purchases p ON p.id = pi.purchase_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as invoice_no"),
                DB::raw("(SELECT rc.receipt_no FROM purchase_return_items pri JOIN receipt_items ri ON ri.id = pri.receipt_item_id JOIN receipts rc ON rc.id = ri.receipt_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as source_receipt_no"),
                DB::raw("COALESCE(bu.name, '-') as business_unit_name"),
                DB::raw("COALESCE(s.name, '-') as supplier_name"),
                DB::raw("COALESCE(w.name, '-') as warehouse_name"),
                DB::raw("COALESCE(u.name, '-') as user_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT pr.name ORDER BY pr.name SEPARATOR ', ') FROM purchase_return_items pri JOIN products pr ON pr.id = pri.product_id WHERE pri.purchase_return_id = r.id) as product_names"),
                DB::raw("(SELECT COALESCE(SUM(pri.qty), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as return_qty"),
                DB::raw("(SELECT COALESCE(SUM(pri.return_value), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as total")
            )
            ->orderByDesc('r.return_date')
            ->skip(max(0, (int) $request->input('start', 0)))
            ->take(min(100, max(1, (int) $request->input('length', 15))))
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function createFromReceipt(Request $request, int $receiptId)
    {
        $entity = $this->entityId();
        $receipt = DB::table('receipts as r')
            ->join('purchases as p', 'p.id', '=', 'r.purchase_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->where('r.entity_id', $entity)
            ->where('r.id', $receiptId)
            ->where('r.status', 'posted')
            ->whereNull('p.deleted_at')
            ->select('r.*', 'p.purchase_no', 'po.po_no', 's.name as supplier_name', 'w.name as warehouse_name', 'bu.name as business_unit_name')
            ->first();

        abort_unless($receipt, 404, 'Penerimaan sumber tidak ditemukan atau sudah dibatalkan.');

        $items = DB::table('receipt_items as ri')
            ->join('products as p', 'p.id', '=', 'ri.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'ri.unit_id')
            ->where('ri.receipt_id', $receiptId)
            ->select('ri.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('ri.id')
            ->get();

        foreach ($items as $item) {
            $returned = (float) DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pri.receipt_item_id', $item->id)
                ->where('pr.status', 'posted')
                ->sum('pri.qty');
            $drafted = (float) DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pri.receipt_item_id', $item->id)
                ->where('pr.status', 'draft')
                ->sum('pri.qty');
            $item->returnable_qty = max(0, (float) $item->qty - $returned - $drafted);
        }

        if ($items->sum('returnable_qty') <= 0 && !$request->ajax()) {
            abort(422, 'Tidak ada qty penerimaan yang tersedia untuk diretur. Periksa apakah seluruh qty sudah diretur atau masih ada draft retur.');
        }

        if (request()->ajax()) {
            return view('inventori.pembelian.retur.partials.create-from-receipt-modal', compact('receipt', 'items'));
        }

        return view('inventori.pembelian.retur.create-from-receipt', compact('receipt', 'items'));
    }

    public function storeFromReceipt(Request $request, int $receiptId)
    {
        $data = $request->validate([
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.qty' => ['nullable', 'numeric', 'gt:0'],
            'items.*.condition' => ['nullable', 'in:good,reject'],
        ]);

        $entity = $this->entityId();
        $returnId = DB::transaction(function () use ($data, $entity, $receiptId) {
            $receipt = DB::table('receipts')
                ->where('entity_id', $entity)->where('id', $receiptId)
                ->lockForUpdate()->first();
            abort_unless($receipt && $receipt->status === 'posted', 422, 'Penerimaan sumber tidak ditemukan atau sudah dibatalkan.');

            $purchase = DB::table('purchases')
                ->where('entity_id', $entity)->where('id', $receipt->purchase_id)
                ->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($purchase && $purchase->status !== 'cancelled', 422, 'Faktur pembelian sumber tidak valid atau sudah dibatalkan.');

            $returnNo = $this->nextReturnNumber($entity, $data['return_date']);
            $returnId = DB::table('purchase_returns')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $receipt->business_unit_id,
                'warehouse_id' => $receipt->warehouse_id,
                'supplier_id' => $receipt->supplier_id,
                'user_id' => auth()->id(),
                'return_no' => $returnNo,
                'return_date' => $data['return_date'],
                'reason' => $data['reason'] ?? null,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted = 0;
            foreach ($data['items'] as $receiptItemId => $input) {
                if (!isset($input['qty']) || $input['qty'] === '') continue;
                $qty = (float) $input['qty'];
                $ri = DB::table('receipt_items')->where('id', (int) $receiptItemId)
                    ->where('receipt_id', $receiptId)->lockForUpdate()->first();
                abort_unless($ri, 422, 'Barang retur tidak sesuai dengan penerimaan sumber.');

                $alreadyReturned = (float) DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->where('pri.receipt_item_id', $ri->id)
                    ->whereIn('pr.status', ['draft', 'posted'])
                    ->sum('pri.qty');
                abort_if($qty > max(0, (float) $ri->qty - $alreadyReturned) + 0.0000001, 422, 'Qty retur melebihi sisa qty penerimaan yang belum diretur.');

                $factor = (float) ($ri->conversion_factor ?: 1);
                $basePerUnit = (float) $ri->qty > 0 ? (float) $ri->base_qty / (float) $ri->qty : $factor;
                $baseQty = round($qty * $basePerUnit, 6);

                DB::table('purchase_return_items')->insert([
                    'purchase_return_id' => $returnId,
                    'receipt_item_id' => $ri->id,
                    'purchase_item_id' => $ri->purchase_item_id,
                    'product_id' => $ri->product_id,
                    'unit_id' => $ri->unit_id,
                    'qty' => $qty,
                    'conversion_factor' => $factor,
                    'base_qty' => $baseQty,
                    'unit_value' => 0,
                    'return_value' => 0,
                    'tax_amount' => 0,
                    'condition' => $input['condition'] ?? 'good',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inserted++;
            }

            abort_if($inserted === 0, 422, 'Isi qty minimal satu barang yang akan diretur.');
            return $returnId;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Draft retur berhasil dibuat dari penerimaan sumber.',
                'redirect_url' => route('inventori.pembelian.retur.show', $returnId),
            ]);
        }

        return redirect()->route('inventori.pembelian.retur.show', $returnId)
            ->with('swal_success', 'Draft Retur Pembelian berhasil dibuat dari penerimaan sumber.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'integer'],
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer'],
            'items.*.qty' => ['nullable', 'numeric', 'gt:0'],
            'items.*.condition' => ['nullable', 'in:good,reject'],
        ]);

        $entity = $this->entityId();

        DB::transaction(function () use ($data, $entity) {
            $purchase = DB::table('purchases')
                ->where('entity_id', $entity)
                ->where('id', $data['purchase_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_unless($purchase, 404, 'Faktur pembelian tidak ditemukan.');
            abort_unless($purchase->status === 'posted', 422, 'Retur hanya dapat dibuat dari Faktur POSTED.');
            abort_unless((int) $purchase->goods_received === 1, 422, 'Barang pada faktur belum diterima di gudang.');

            $warehouse = DB::table('receipts')
                ->where('entity_id', $entity)
                ->where('purchase_id', $purchase->id)
                ->where('status', 'posted')
                ->orderBy('id')
                ->first(['warehouse_id']);

            abort_unless($warehouse, 422, 'Gudang penerimaan faktur tidak ditemukan.');

            $returnNo = $this->nextReturnNumber($entity, $data['return_date']);

            $returnId = DB::table('purchase_returns')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $purchase->business_unit_id,
                'warehouse_id' => $warehouse->warehouse_id,
                'supplier_id' => $purchase->supplier_id,
                'user_id' => auth()->id(),
                'return_no' => $returnNo,
                'return_date' => $data['return_date'],
                'reason' => $data['reason'] ?? null,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $purchaseItems = DB::table('purchase_items')
                ->where('purchase_id', $purchase->id)
                ->get()
                ->keyBy('id');

            $inserted = 0;

            foreach ($data['items'] as $input) {
                if ($input['qty'] === null || $input['qty'] === '') {
                    continue;
                }

                $item = $purchaseItems->get((int) $input['purchase_item_id']);
                abort_unless($item, 422, 'Item faktur tidak valid.');

                $qtyRequested = (float) $input['qty'];
                $factor = (float) ($item->conversion_factor ?: 1);
                $basePerUnit = (float) $item->qty > 0
                    ? (float) ($item->base_qty ?: ($item->qty * $factor)) / (float) $item->qty
                    : $factor;
                $requestedBase = round($qtyRequested * $basePerUnit, 6);

                $receiptItems = DB::table('receipt_items')
                    ->where('purchase_item_id', $item->id)
                    ->orderBy('id')
                    ->get();

                $remaining = $requestedBase;

                foreach ($receiptItems as $receiptItem) {
                    if ($remaining <= 0.0000001) {
                        break;
                    }

                    $alreadyReturned = (float) DB::table('purchase_return_items as pri')
                        ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                        ->where('pri.receipt_item_id', $receiptItem->id)
                        ->where('pr.status', 'posted')
                        ->sum('pri.base_qty');

                    $available = max(0, (float) $receiptItem->base_qty - $alreadyReturned);
                    if ($available <= 0.0000001) {
                        continue;
                    }

                    $baseQty = min($remaining, $available);
                    $receiptFactor = (float) ($receiptItem->conversion_factor ?: $factor);
                    $qty = $receiptFactor > 0 ? $baseQty / $receiptFactor : $baseQty;

                    DB::table('purchase_return_items')->insert([
                        'purchase_return_id' => $returnId,
                        'receipt_item_id' => $receiptItem->id,
                        'purchase_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'unit_id' => $receiptItem->unit_id ?: $item->unit_id,
                        'qty' => $qty,
                        'conversion_factor' => $receiptFactor,
                        'base_qty' => $baseQty,
                        'unit_value' => 0,
                        'return_value' => 0,
                        'tax_amount' => 0,
                        'condition' => $input['condition'] ?? 'good',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $inserted++;
                    $remaining -= $baseQty;
                }

                abort_if($remaining > 0.0000001, 422, 'Qty retur melebihi qty yang sudah diterima dan belum diretur.');
            }

            abort_if($inserted === 0, 422, 'Minimal satu item retur harus diisi.');
        });

        return redirect()
            ->route('inventori.pembelian.show', $data['purchase_id'])
            ->with('swal_success', 'Draft Retur Pembelian berhasil dibuat.');
    }

    public function post(int $id)
    {
        $entity = $this->entityId();

        DB::transaction(function () use ($id, $entity) {
            $return = DB::table('purchase_returns')
                ->where('entity_id', $entity)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            abort_unless($return, 404, 'Retur pembelian tidak ditemukan.');
            abort_unless($return->status === 'draft', 422, 'Retur sudah diposting.');

            $items = DB::table('purchase_return_items')
                ->where('purchase_return_id', $return->id)
                ->lockForUpdate()
                ->get();

            abort_if($items->isEmpty(), 422, 'Retur tidak memiliki item.');

            foreach ($items as $item) {
                $alreadyReturned = (float) DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->where('pri.receipt_item_id', $item->receipt_item_id)
                    ->whereIn('pr.status', ['draft', 'posted'])
                    ->where('pri.id', '<>', $item->id)
                    ->sum('pri.base_qty');

                $receiptItem = DB::table('receipt_items as ri')
                    ->join('receipts as r', 'r.id', '=', 'ri.receipt_id')
                    ->where('ri.id', $item->receipt_item_id)
                    ->where('r.entity_id', $entity)
                    ->where('r.status', 'posted')
                    ->select('ri.*', 'r.warehouse_id as receipt_warehouse_id')
                    ->lockForUpdate()
                    ->first();

                abort_unless($receiptItem, 422, 'Penerimaan sumber tidak ditemukan atau sudah dibatalkan.');
                abort_unless((int) $receiptItem->receipt_warehouse_id === (int) $return->warehouse_id, 422, 'Gudang retur harus sama dengan gudang penerimaan sumber.');
                abort_unless((int) $receiptItem->product_id === (int) $item->product_id, 422, 'Barang retur tidak sesuai dengan penerimaan sumber.');

                $receivedBase = (float) $receiptItem->base_qty;
                abort_if((float) $item->base_qty > max(0, $receivedBase - $alreadyReturned) + 0.0000001, 422, 'Qty retur sudah tidak tersedia.');

                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entity)
                    ->where('warehouse_id', $receiptItem->receipt_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($stock, 422, 'Stok produk tidak ditemukan.');
                abort_if((float) $stock->qty < (float) $item->base_qty - 0.0000001, 422, 'Stok tidak mencukupi untuk retur.');

                // Nilai retur memakai harga pokok pembelian saat penerimaan sumber.
                $unitCost = (float) $receiptItem->base_unit_cost;
                abort_if($unitCost < 0, 422, 'Harga pokok pembelian pada penerimaan sumber tidak valid.');
                $value = round((float) $item->base_qty * $unitCost, 2);

                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => (float) $stock->qty - (float) $item->base_qty,
                    'updated_at' => now(),
                ]);

                DB::table('purchase_return_items')->where('id', $item->id)->update([
                    'unit_value' => $unitCost,
                    'return_value' => $value,
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $return->business_unit_id,
                    'warehouse_id' => $return->warehouse_id,
                    'product_id' => $item->product_id,
                    'movement_type' => 'purchase_return',
                    'qty' => -abs((float) $item->base_qty),
                    'unit_cost' => $unitCost,
                    'reference_type' => 'purchase_return',
                    'reference_id' => $return->id,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            }

            DB::table('purchase_returns')->where('id', $return->id)->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('inventori.pembelian.retur')
            ->with('swal_success', 'Retur Pembelian berhasil diposting.');
    }

    public function cancel(int $id)
    {
        $entity = $this->entityId();

        DB::transaction(function () use ($id, $entity) {
            $return = DB::table('purchase_returns')
                ->where('entity_id', $entity)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            abort_unless($return, 404, 'Retur pembelian tidak ditemukan.');
            abort_unless($return->status === 'draft', 422, 'Retur yang sudah diposting tidak dapat dibatalkan.');

            DB::table('purchase_returns')->where('id', $return->id)->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('inventori.pembelian.retur')
            ->with('swal_success', 'Retur Pembelian berhasil dibatalkan. Stok dikembalikan jika retur sebelumnya sudah diposting; tidak ada jurnal akuntansi yang dibuat.');
    }

    public function printList(Request $request)
    {
        $entityId = $this->entityId();
        $entity = DB::table('entities')->where('id', $entityId)->first();
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());
        $businessUnitId = $request->query('business_unit_id');
        $warehouseId = $request->query('warehouse_id');
        $businessUnitName = $businessUnitId ? DB::table('business_units')->where('id', $businessUnitId)->value('name') : null;

        $query = DB::table('purchase_returns as r')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.entity_id', $entityId)
            ->whereBetween('r.return_date', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

        if ($businessUnitId) $query->where('r.business_unit_id', $businessUnitId);
        if ($warehouseId) $query->where('r.warehouse_id', $warehouseId);

        $returns = $query->select(
            'r.*', 'bu.name as business_unit_name', 's.name as supplier_name',
            'w.name as warehouse_name', 'u.name as user_name',
            DB::raw("(SELECT p.purchase_no FROM purchase_return_items pri JOIN purchase_items pi ON pi.id = pri.purchase_item_id JOIN purchases p ON p.id = pi.purchase_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as invoice_no"),
            DB::raw("(SELECT rc.receipt_no FROM purchase_return_items pri JOIN receipt_items ri ON ri.id = pri.receipt_item_id JOIN receipts rc ON rc.id = ri.receipt_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as source_receipt_no"),
            DB::raw("(SELECT COALESCE(SUM(pri.qty), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as return_qty"),
            DB::raw("(SELECT COALESCE(SUM(pri.return_value), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as total")
        )->orderByDesc('r.return_date')->orderByDesc('r.id')->get();

        return view('inventori.pembelian.retur.print-list', compact('entity', 'returns', 'startDate', 'endDate', 'businessUnitId', 'businessUnitName', 'warehouseId'));
    }

    public function printDetail(int $id)
    {
        $entity = DB::table('entities')->where('id', $this->entityId())->first();
        $return = DB::table('purchase_returns as r')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.entity_id', $entity->id)->where('r.id', $id)
            ->select('r.*', 'bu.name as business_unit_name', 's.name as supplier_name', 's.address as supplier_address', 's.phone as supplier_phone', 'w.name as warehouse_name', 'u.name as user_name',
                DB::raw("(SELECT p.purchase_no FROM purchase_return_items pri JOIN purchase_items pi ON pi.id = pri.purchase_item_id JOIN purchases p ON p.id = pi.purchase_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as invoice_no"),
                DB::raw("(SELECT rc.receipt_no FROM purchase_return_items pri JOIN receipt_items ri ON ri.id = pri.receipt_item_id JOIN receipts rc ON rc.id = ri.receipt_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as source_receipt_no"))
            ->firstOrFail();

        $items = DB::table('purchase_return_items as ri')
            ->join('products as p', 'p.id', '=', 'ri.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'ri.unit_id')
            ->where('ri.purchase_return_id', $id)
            ->select('ri.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('ri.id')->get();

        $return->total = (float) $items->sum('return_value');

        return view('inventori.pembelian.retur.print-detail', compact('entity', 'return', 'items'));
    }

    public function show(int $id)
    {
        $entity = $this->entityId();

        $purchaseId = DB::table('purchase_return_items as pri')
            ->join('purchase_items as pi', 'pi.id', '=', 'pri.purchase_item_id')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where('pr.entity_id', $entity)
            ->where('pr.id', $id)
            ->value('pi.purchase_id');

        abort_unless($purchaseId, 404, 'Retur pembelian tidak ditemukan.');

        $return = DB::table('purchase_returns as r')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.entity_id', $entity)
            ->where('r.id', $id)
            ->select(
                'r.*',
                DB::raw("(SELECT p.purchase_no FROM purchase_return_items pri JOIN purchase_items pi ON pi.id = pri.purchase_item_id JOIN purchases p ON p.id = pi.purchase_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as invoice_no"),
                DB::raw("(SELECT rc.receipt_no FROM purchase_return_items pri JOIN receipt_items ri ON ri.id = pri.receipt_item_id JOIN receipts rc ON rc.id = ri.receipt_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as source_receipt_no"),
                's.name as supplier_name',
                'w.name as warehouse_name'
            )
            ->first();

        abort_unless($return, 404, 'Retur pembelian tidak ditemukan.');

        $return->total = (float) DB::table('purchase_return_items')
            ->where('purchase_return_id', $id)
            ->sum('return_value');

        $items = DB::table('purchase_return_items as ri')
            ->join('products as p', 'p.id', '=', 'ri.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'ri.unit_id')
            ->where('ri.purchase_return_id', $id)
            ->select('ri.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('ri.id')
            ->get();

        return view('inventori.pembelian.retur.show', compact('return', 'items'));
    }
}
