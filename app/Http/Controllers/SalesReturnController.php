<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesReturnController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 422, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

    private function accountId(int $entity, array $names, ?string $type = null): int
    {
        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->get(['id', 'name', 'code']);

        foreach ($names as $name) {
            $account = $accounts->first(fn ($a) => mb_strtolower(trim($a->name)) === mb_strtolower($name));
            if ($account) return (int) $account->id;
        }

        abort(422, 'COA terkunci tidak ditemukan: ' . implode(' / ', $names) . '.');
    }

    public function index()
    {
        $entity = $this->entityId();

        $rows = DB::table('sales_returns as r')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.entity_id', $entity)
            ->orderByDesc('r.id')
            ->select('r.*', 's.invoice_no', DB::raw("COALESCE(c.name, 'Umum') as customer_name"), 'u.name as user_name', 'w.name as warehouse_name')
            ->paginate(15)
            ->withQueryString();

        $warehouses = DB::table('warehouses')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get();

        return view('erp.retur', compact('rows', 'warehouses'));
    }

    public function saleLookup(Request $request)
    {
        $data = $request->validate(['invoice_no' => 'required|string']);
        $entity = $this->entityId();

        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.entity_id', $entity)
            ->where('s.invoice_no', $data['invoice_no'])
            ->select('s.*', DB::raw("COALESCE(c.name, 'Umum') as customer_name"))
            ->first();

        abort_unless($sale, 404, 'Nomor struk tidak ditemukan.');

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.sale_id', $sale->id)
            ->select('si.id as sale_item_id', 'si.product_id', 'p.sku', 'p.name', 'si.qty', 'si.unit_price', 'si.discount', 'si.total')
            ->orderBy('si.id')
            ->get();

        foreach ($items as $item) {
            $returned = (float) DB::table('sales_return_items as ri')
                ->join('sales_returns as rr', 'rr.id', '=', 'ri.sales_return_id')
                ->where('rr.sale_id', $sale->id)
                ->where('ri.sale_item_id', $item->sale_item_id)
                ->sum('ri.qty');

            $item->returned_qty = $returned;
            $item->available_qty = max(0, (float) $item->qty - $returned);
            $item->hpp_unit = $this->saleHppUnit($sale->id, $item->product_id);
        }

        $payment = DB::table('payments')->where('sale_id', $sale->id)->orderBy('id')->first(['method', 'amount']);
        $entityData = DB::table('entities')->where('id', $entity)->first(['name', 'address', 'phone']);

        return response()->json([
            'sale' => $sale,
            'items' => $items,
            'payment' => $payment,
            'entity' => $entityData,
        ]);
    }

    private function saleHppUnit(int $saleId, int $productId): float
    {
        $movement = DB::table('stock_movements')
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('product_id', $productId)
            ->selectRaw('SUM(ABS(qty)) qty, SUM(ABS(qty) * unit_cost) cost')
            ->first();

        if ($movement && (float) $movement->qty > 0) {
            return (float) $movement->cost / (float) $movement->qty;
        }

        return (float) (DB::table('products')->where('id', $productId)->value('cost_price') ?? 0);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'reason' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.condition' => 'required|in:good,reject',
        ]);

        $entity = $this->entityId();

        $returnId = DB::transaction(function () use ($data, $entity) {
            $sale = DB::table('sales')->where('entity_id', $entity)->where('id', $data['sale_id'])->lockForUpdate()->first();
            abort_unless($sale, 404, 'Penjualan tidak ditemukan.');

            $warehouse = DB::table('warehouses')->where('entity_id', $entity)->where('id', $data['warehouse_id'])->where('is_active', 1)->first();
            abort_unless($warehouse, 404, 'Gudang tidak ditemukan.');

            $saleItems = DB::table('sale_items')->where('sale_id', $sale->id)->get()->keyBy('id');
            $subtotal = (float) $sale->subtotal;
            $saleDiscount = (float) $sale->discount;
            $returnTotal = 0;
            $prepared = [];

            foreach ($data['items'] as $input) {
                $item = $saleItems->get((int) $input['sale_item_id']);
                abort_unless($item, 422, 'Detail penjualan tidak valid.');

                $returned = (float) DB::table('sales_return_items as ri')
                    ->join('sales_returns as rr', 'rr.id', '=', 'ri.sales_return_id')
                    ->where('rr.sale_id', $sale->id)
                    ->where('ri.sale_item_id', $item->id)
                    ->sum('ri.qty');

                $qty = (float) $input['qty'];
                abort_if($qty > max(0, (float) $item->qty - $returned), 422, 'Qty retur melebihi qty yang masih dapat diretur.');

                $grossItem = (float) $item->total;
                $allocatedDiscount = $subtotal > 0 ? ($grossItem / $subtotal) * $saleDiscount : 0;
                $netItem = max(0, $grossItem - $allocatedDiscount);
                $netUnitPrice = (float) $item->qty > 0 ? $netItem / (float) $item->qty : 0;
                $returnValue = round($netUnitPrice * $qty, 2);
                $hppUnit = $this->saleHppUnit($sale->id, (int) $item->product_id);
                $hppTotal = round($hppUnit * $qty, 2);

                $prepared[] = compact('item', 'qty', 'returnValue', 'hppUnit', 'hppTotal') + ['condition' => $input['condition']];
                $returnTotal += $returnValue;
            }

            abort_if($returnTotal <= 0, 422, 'Nilai retur harus lebih besar dari nol.');

            $returnNo = 'RET-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
            $returnId = DB::table('sales_returns')->insertGetId([
                'entity_id' => $entity,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'warehouse_id' => $warehouse->id,
                'user_id' => auth()->id(),
                'return_no' => $returnNo,
                'return_date' => now(),
                'total' => $returnTotal,
                'reason' => $data['reason'] ?? null,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($prepared as $p) {
                $item = $p['item'];
                DB::table('sales_return_items')->insert([
                    'sales_return_id' => $returnId,
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'qty' => $p['qty'],
                    'unit_price' => $p['returnValue'] / $p['qty'],
                    'return_value' => $p['returnValue'],
                    'hpp_unit' => $p['hppUnit'],
                    'hpp_total' => $p['hppTotal'],
                    'condition' => $p['condition'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entity)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($p['condition'] === 'good') {
                    if ($stock) {
                        $oldQty = (float) $stock->qty;
                        $oldCost = (float) $stock->avg_cost;
                        $newQty = $oldQty + $p['qty'];
                        $newAvg = $newQty > 0 ? (($oldQty * $oldCost) + ($p['qty'] * $p['hppUnit'])) / $newQty : $p['hppUnit'];
                        DB::table('warehouses_stocks')->where('id', $stock->id)->update(['qty' => $newQty, 'avg_cost' => $newAvg, 'updated_at' => now()]);
                    } else {
                        DB::table('warehouses_stocks')->insert([
                            'entity_id' => $entity,
                            'warehouse_id' => $warehouse->id,
                            'product_id' => $item->product_id,
                            'qty' => $p['qty'],
                            'avg_cost' => $p['hppUnit'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $item->product_id,
                    'movement_type' => $p['condition'] === 'good' ? 'sale_return_in' : 'sale_return_reject',
                    'qty' => $p['qty'],
                    'unit_cost' => $p['hppUnit'],
                    'reference_type' => 'sales_return',
                    'reference_id' => $returnId,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $payment = DB::table('payments')->where('sale_id', $sale->id)->orderBy('id')->first(['method', 'amount']);
            $paid = (float) DB::table('payments')->where('sale_id', $sale->id)->sum('amount');
            $refundAccount = $paid >= (float) $sale->total
                ? $this->accountId($entity, $payment && in_array(strtolower($payment->method), ['transfer', 'qris'], true) ? ['Bank', 'Bank & Giro'] : ['Kas'])
                : $this->accountId($entity, ['Piutang', 'Piutang Usaha', 'Piutang Dagang'], 'asset');

            $returnAccount = $this->accountId($entity, ['Retur Penjualan'], 'revenue');
            $inventoryAccount = $this->accountId($entity, ['Persediaan'], 'asset');
            $cogsAccount = $this->accountId($entity, ['HPP Penjualan', 'HPP'], 'cogs');

            $description = 'Retur atas penjualan #' . $sale->invoice_no;
            $journalNo = 'JRN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(3));
            $journalId = DB::table('journals')->insertGetId([
                'entity_id' => $entity,
                'journal_no' => $journalNo,
                'journal_date' => today(),
                'source_type' => 'sales_return',
                'source_id' => $returnId,
                'description' => $description,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalHpp = array_sum(array_column($prepared, 'hppTotal'));
            DB::table('journal_entries')->insert([
                ['journal_id' => $journalId, 'account_id' => $returnAccount, 'debit' => $returnTotal, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_id' => $journalId, 'account_id' => $refundAccount, 'debit' => 0, 'credit' => $returnTotal, 'created_at' => now(), 'updated_at' => now()],
                ['journal_id' => $journalId, 'account_id' => $inventoryAccount, 'debit' => $totalHpp, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_id' => $journalId, 'account_id' => $cogsAccount, 'debit' => 0, 'credit' => $totalHpp, 'created_at' => now(), 'updated_at' => now()],
            ]);

            return $returnId;
        });

        return redirect()->route('pos.retur')->with('success', 'Retur berhasil diproses dan jurnal otomatis dibuat.');
    }
}
