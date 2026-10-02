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
            ->join('purchases as p', 'p.id', '=', 'r.purchase_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.entity_id', $entity)
            ->whereNull('p.deleted_at')
            ->whereBetween('r.return_date', [$start.' 00:00:00', $end.' 23:59:59']);

        $recordsTotal = (clone $query)->count('r.id');

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('r.return_no', 'like', $like)
                    ->orWhere('p.purchase_no', 'like', $like)
                    ->orWhere('s.name', 'like', $like)
                    ->orWhere('w.name', 'like', $like);
            });
        }

        $recordsFiltered = (clone $query)->count('r.id');

        $rows = $query
            ->select(
                'r.id', 'r.return_no', 'r.return_date', 'r.total', 'r.status',
                'p.purchase_no as invoice_no',
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
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.condition' => ['required', 'in:good,reject'],
        ]);

        $entity = $this->entityId();

        $returnId = DB::transaction(function () use ($data, $entity) {
            $purchase = DB::table('purchases')
                ->where('entity_id', $entity)
                ->where('id', $data['purchase_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_unless($purchase, 404, 'Faktur pembelian tidak ditemukan.');
            abort_unless($purchase->status === 'posted', 422, 'Retur hanya dapat dibuat dari Faktur POSTED.');
            abort_unless((int) $purchase->goods_received === 1, 422, 'Barang pada faktur belum diterima di gudang.');

            $warehouse = DB::table('receipts as r')
                ->join('warehouses as w', 'w.id', '=', 'r.warehouse_id')
                ->where('r.entity_id', $entity)
                ->where('r.purchase_id', $purchase->id)
                ->where('r.status', 'posted')
                ->orderBy('r.id')
                ->first(['w.id', 'w.business_unit_id']);

            abort_unless($warehouse, 422, 'Gudang penerimaan faktur tidak ditemukan.');
            abort_unless((int) $warehouse->business_unit_id === (int) $purchase->business_unit_id, 422, 'Gudang tidak sesuai dengan Business Unit faktur.');

            $purchaseItems = DB::table('purchase_items')
                ->where('purchase_id', $purchase->id)
                ->get()
                ->keyBy('id');

            $prepared = [];

            foreach ($data['items'] as $input) {
                $item = $purchaseItems->get((int) $input['purchase_item_id']);
                abort_unless($item, 422, 'Item faktur tidak valid.');

                $factor = (float) ($item->conversion_factor ?: 1);
                $basePerUnit = (float) $item->qty > 0
                    ? (float) ($item->base_qty ?: ($item->qty * $factor)) / (float) $item->qty
                    : $factor;
                $qty = (float) $input['qty'];
                $baseQty = round($qty * $basePerUnit, 6);

                $receivedBase = (float) DB::table('receipt_items')
                    ->where('purchase_item_id', $item->id)
                    ->sum('base_qty');

                $returnedBase = (float) DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->where('pr.purchase_id', $purchase->id)
                    ->where('pri.purchase_item_id', $item->id)
                    ->where('pr.status', 'posted')
                    ->sum('pri.base_qty');

                abort_if($baseQty > max(0, $receivedBase - $returnedBase) + 0.0000001, 422, 'Qty retur melebihi qty yang sudah diterima dan belum diretur.');

                $prepared[] = [
                    'item' => $item,
                    'qty' => $qty,
                    'factor' => $basePerUnit,
                    'base_qty' => $baseQty,
                    'condition' => $input['condition'],
                ];
            }

            $returnNo = 'PRT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

            $returnId = DB::table('purchase_returns')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $purchase->business_unit_id,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'warehouse_id' => $warehouse->id,
                'user_id' => auth()->id(),
                'return_no' => $returnNo,
                'return_date' => $data['return_date'],
                'total' => 0,
                'reason' => $data['reason'] ?? null,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($prepared as $p) {
                $item = $p['item'];

                DB::table('purchase_return_items')->insert([
                    'purchase_return_id' => $returnId,
                    'purchase_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'qty' => $p['qty'],
                    'conversion_factor' => $p['factor'],
                    'base_qty' => $p['base_qty'],
                    'unit_cost' => 0,
                    'total' => 0,
                    'condition' => $p['condition'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $returnId;
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

            $purchase = DB::table('purchases')
                ->where('entity_id', $entity)
                ->where('id', $return->purchase_id)
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
                $receivedBase = (float) DB::table('receipt_items')
                    ->where('purchase_item_id', $item->purchase_item_id)
                    ->sum('base_qty');

                $returnedBase = (float) DB::table('purchase_return_items as pri')
                    ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                    ->where('pr.purchase_id', $purchase->id)
                    ->where('pri.purchase_item_id', $item->purchase_item_id)
                    ->where('pr.status', 'posted')
                    ->sum('pri.base_qty');

                abort_if((float) $item->base_qty > max(0, $receivedBase - $returnedBase) + 0.0000001, 422, 'Qty retur sudah tidak tersedia.');

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
                $newQty = (float) $stock->qty - (float) $item->base_qty;

                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => $newQty,
                    'updated_at' => now(),
                ]);

                DB::table('purchase_return_items')->where('id', $item->id)->update([
                    'unit_cost' => $unitCost,
                    'total' => $value,
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $purchase->business_unit_id,
                    'warehouse_id' => $return->warehouse_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'transaction_qty' => $item->qty,
                    'conversion_factor' => $item->conversion_factor,
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
                'total' => round($totalValue, 2),
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

        $return = DB::table('purchase_returns as r')
            ->join('purchases as p', 'p.id', '=', 'r.purchase_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.entity_id', $entity)
            ->where('r.id', $id)
            ->select('r.*', 'p.purchase_no as invoice_no', 's.name as supplier_name', 'w.name as warehouse_name')
            ->first();

        abort_unless($return, 404, 'Retur pembelian tidak ditemukan.');

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
