<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseReturnController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 422, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

    private function mappingAccountId(int $entity, int $businessUnitId, string $mappingKey): int
    {
        $account = DB::table('business_unit_account_mappings as m')
            ->join('chart_of_accounts as c', 'c.id', '=', 'm.account_id')
            ->where('m.entity_id', $entity)
            ->where('m.business_unit_id', $businessUnitId)
            ->where('m.mapping_key', $mappingKey)
            ->where('c.is_active', 1)
            ->where('c.is_postable', 1)
            ->first(['c.id']);

        abort_unless($account, 422, 'Mapping account tidak ditemukan: '.$mappingKey.'.');

        return (int) $account->id;
    }

    public function index()
    {
        return view('inventori.pembelian.retur.index');
    }

    public function data(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = DB::table('purchase_returns as r')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.entity_id', $entity)
            ->whereBetween('r.return_date', [$start.' 00:00:00', $end.' 23:59:59']);

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
                DB::raw("COALESCE(s.name, '-') as supplier_name"),
                DB::raw("COALESCE(w.name, '-') as warehouse_name"),
                DB::raw("COALESCE(u.name, '-') as user_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT pr.name ORDER BY pr.name SEPARATOR ', ') FROM purchase_return_items pri JOIN products pr ON pr.id = pri.product_id WHERE pri.purchase_return_id = r.id) as product_names"),
                DB::raw("(SELECT COALESCE(SUM(pri.qty), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as return_qty")
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

            $returnNo = 'PRT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

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

            $purchaseId = DB::table('purchase_return_items as pri')
                ->join('purchase_items as pi', 'pi.id', '=', 'pri.purchase_item_id')
                ->where('pri.purchase_return_id', $return->id)
                ->value('pi.purchase_id');

            abort_unless($purchaseId, 422, 'Faktur sumber retur tidak ditemukan.');

            $purchase = DB::table('purchases')
                ->where('entity_id', $entity)
                ->where('id', $purchaseId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_unless($purchase && $purchase->status === 'posted', 422, 'Faktur sumber tidak valid.');

            $items = DB::table('purchase_return_items')
                ->where('purchase_return_id', $return->id)
                ->lockForUpdate()
                ->get();

            abort_if($items->isEmpty(), 422, 'Retur tidak memiliki item.');

            $totalValue = 0.0;

            foreach ($items as $item) {
                $alreadyReturned = (float) DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->where('pri.receipt_item_id', $item->receipt_item_id)
                    ->where('pr.status', 'posted')
                    ->where('pri.id', '<>', $item->id)
                    ->sum('pri.base_qty');

                $receivedBase = (float) DB::table('receipt_items')
                    ->where('id', $item->receipt_item_id)
                    ->value('base_qty');

                abort_if((float) $item->base_qty > max(0, $receivedBase - $alreadyReturned) + 0.0000001, 422, 'Qty retur sudah tidak tersedia.');

                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entity)
                    ->where('warehouse_id', $return->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($stock, 422, 'Stok produk tidak ditemukan.');
                abort_if((float) $stock->qty < (float) $item->base_qty - 0.0000001, 422, 'Stok tidak mencukupi untuk retur.');

                $unitCost = (float) $stock->avg_cost;
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
                    'business_unit_id' => $purchase->business_unit_id,
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

                $totalValue += $value;
            }

            abort_if($totalValue <= 0, 422, 'Nilai retur harus lebih besar dari nol.');

            $paymentMethod = strtolower((string) ($purchase->payment_method ?? 'credit'));
            $debitKey = match ($paymentMethod) {
                'cash', 'tunai' => 'cash',
                'bank', 'transfer', 'qris' => 'bank',
                default => 'payable',
            };

            $debitAccount = $this->mappingAccountId($entity, (int) $purchase->business_unit_id, $debitKey);
            $inventoryAccount = $this->mappingAccountId($entity, (int) $purchase->business_unit_id, 'inventory');

            $journalId = DB::table('journals')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $purchase->business_unit_id,
                'journal_no' => 'JRN-PRT-'.$return->id,
                'journal_date' => $return->return_date,
                'source_type' => 'purchase_return',
                'source_id' => $return->id,
                'description' => 'Retur pembelian '.$return->return_no.' / '.$purchase->purchase_no,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('journal_entries')->insert([
                [
                    'journal_id' => $journalId,
                    'account_id' => $debitAccount,
                    'debit' => round($totalValue, 2),
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'journal_id' => $journalId,
                    'account_id' => $inventoryAccount,
                    'debit' => 0,
                    'credit' => round($totalValue, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

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
