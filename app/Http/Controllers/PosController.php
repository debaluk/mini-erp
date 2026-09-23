<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 422, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

    private function cart(Request $request): array
    {
        return $request->session()->get('pos_cart', []);
    }

    public function add(Request $request)
    {
        $data = $request->validate(['product_id'=>'required|integer','qty'=>'required|numeric|gt:0']);
        $entity = $this->entityId();
        $businessUnitId = (int) (auth()->user()->default_business_unit_id ?? 0);
        abort_unless($businessUnitId > 0, 422, 'Unit Bisnis default belum ditentukan.');

        $businessUnit = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('id', $businessUnitId)
            ->where('is_active', 1)
            ->first();
        abort_unless($businessUnit, 422, 'Unit Bisnis POS tidak valid.');

        $warehouse = DB::table('warehouse_business_units as wbu')
            ->join('warehouses as w', 'w.id', '=', 'wbu.warehouse_id')
            ->where('wbu.entity_id', $entity)
            ->where('wbu.business_unit_id', $businessUnitId)
            ->where('w.is_active', 1)
            ->first(['w.id', 'w.code', 'w.name']);
        abort_unless($warehouse, 422, 'Gudang POS untuk Unit Bisnis ini belum dipetakan.');

        $product = DB::table('products as p')
            ->join('product_business_units as pu', function ($join) use ($businessUnitId) {
                $join->on('pu.product_id', '=', 'p.id')
                    ->where('pu.business_unit_id', $businessUnitId);
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->leftJoin('product_prices as pp', function ($join) use ($businessUnitId) {
                $join->on('pp.product_id', '=', 'p.id')
                    ->on('pp.unit_id', '=', 'p.base_unit_id')
                    ->where('pp.business_unit_id', $businessUnitId)
                    ->where('pp.price_type', 'retail');
            })
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('p.id', $data['product_id'])
            ->select(
                'p.*',
                'p.base_unit_id as selling_unit_id',
                'u.code as selling_unit_code',
                'u.name as selling_unit_name',
                'pp.selling_price as master_selling_price'
            )
            ->first();

        abort_unless($product, 404, 'Produk tidak ditemukan untuk Unit Bisnis POS.');
        abort_unless($product->master_selling_price !== null, 422, 'Harga jual item belum ditetapkan.');

        $cart = $this->cart($request);
        $id = (string) $product->id;

        if (isset($cart[$id])) {
            $cart[$id]['qty'] += (float) $data['qty'];
            $cart[$id]['business_unit_id'] = $businessUnitId;
            $cart[$id]['warehouse_id'] = (int) $warehouse->id;
            $cart[$id]['warehouse_code'] = $warehouse->code;
            $cart[$id]['warehouse_name'] = $warehouse->name;
        } else {
            $cart[$id] = [
                'product_id' => (int) $product->id,
                'code' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'selling_unit_code' => $product->selling_unit_code,
                'selling_unit_name' => $product->selling_unit_name,
                'price' => (float) $product->master_selling_price,
                'qty' => (float) $data['qty'],
                'business_unit_id' => $businessUnitId,
                'warehouse_id' => (int) $warehouse->id,
                'warehouse_code' => $warehouse->code,
                'warehouse_name' => $warehouse->name,
            ];
        }

        $request->session()->put('pos_cart', $cart);

        if ($request->expectsJson()) return response()->json(['ok'=>true,'cart'=>$cart]);
        return back();
    }

    public function updateItem(Request $request, string $id)
    {
        $data = $request->validate(['qty'=>'required|numeric|gt:0']);
        $cart = $this->cart($request);
        abort_unless(isset($cart[$id]), 404, 'Item transaksi tidak ditemukan.');

        $cart[$id]['qty'] = (float) $data['qty'];
        $request->session()->put('pos_cart', $cart);

        if ($request->expectsJson()) return response()->json(['ok'=>true,'cart'=>$cart]);
        return back()->with('success','Item transaksi berhasil diperbarui.');
    }

    public function removeItem(Request $request, string $id)
    {
        $cart = $this->cart($request);
        abort_unless(isset($cart[$id]), 404, 'Item transaksi tidak ditemukan.');

        unset($cart[$id]);
        $request->session()->put('pos_cart', $cart);

        if ($request->expectsJson()) return response()->json(['ok'=>true,'cart'=>$cart]);
        return back()->with('success','Barang dihapus dari transaksi.');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('pos_cart');
        if ($request->expectsJson()) return response()->json(['ok'=>true,'cart'=>[]]);
        return back()->with('success','Transaksi sementara dikosongkan.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_method' => 'required|in:Tunai,Transfer,QRIS',
            'discount' => 'nullable|numeric|min:0',
            'payment_amount' => 'required|numeric|min:0',
        ]);

        $entity = $this->entityId();
        $cart = $this->cart($request);
        abort_if(empty($cart), 422, 'Belum ada barang dalam transaksi.');

        $businessUnitId = (int) (auth()->user()->default_business_unit_id ?? 0);
        abort_unless($businessUnitId > 0, 422, 'Unit Bisnis default belum ditentukan.');

        $businessUnit = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('id', $businessUnitId)
            ->where('is_active', 1)
            ->first();
        abort_unless($businessUnit, 422, 'Unit Bisnis POS tidak valid.');

        $warehouse = DB::table('warehouse_business_units as wbu')
            ->join('warehouses as w', 'w.id', '=', 'wbu.warehouse_id')
            ->where('wbu.entity_id', $entity)
            ->where('wbu.business_unit_id', $businessUnitId)
            ->where('w.is_active', 1)
            ->first(['w.id', 'w.code', 'w.name']);
        abort_unless($warehouse, 422, 'Gudang POS untuk Unit Bisnis ini belum dipetakan.');

        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += (float) $item['price'] * (float) $item['qty'];
        }

        $discount = min((float) ($data['discount'] ?? 0), $subtotal);
        $total = $subtotal - $discount;
        $paidAmount = (float) $data['payment_amount'];
        $changeAmount = $paidAmount - $total;

        abort_if($paidAmount < $total, 422, 'Nominal pembayaran kurang.');
        if ($data['payment_method'] !== 'Tunai') {
            abort_if(round($paidAmount, 2) !== round($total, 2), 422, 'Transfer/QRIS harus dibayar tepat sesuai total.');
            $changeAmount = 0;
        }

        $invoiceNo = 'POS-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

        DB::transaction(function () use ($cart, $entity, $businessUnitId, $warehouse, $subtotal, $discount, $total, $paidAmount, $changeAmount, $data, $invoiceNo) {
            $stockRows = [];

            foreach ($cart as $item) {
                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entity)
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $warehouse->id)
                    ->lockForUpdate()
                    ->first();

                $qty = (float) $item['qty'];
                abort_if(!$stock || (float) $stock->qty < $qty, 422, 'Stok ' . $item['name'] . ' tidak mencukupi.');

                $stockRows[] = [
                    'stock_id' => $stock->id,
                    'warehouse_id' => $stock->warehouse_id,
                    'product_id' => $item['product_id'],
                    'unit_id' => $item['selling_unit_id'],
                    'qty' => $qty,
                    'avg_cost' => (float) $stock->avg_cost,
                ];
            }

            $customerId = DB::table('customers')
                ->where('entity_id', $entity)
                ->where('code', 'CUST-UMUM')
                ->value('id');

            $businessUnitId = (int) ($shift->business_unit_id ?? 1);
            $sale = DB::table('sales')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $businessUnitId,
                'customer_id' => $customerId,
                'user_id' => auth()->id(),
                'shift_id' => null,
                'invoice_no' => $invoiceNo,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($cart as $item) {
                DB::table('sale_items')->insert([
                    'sale_id' => $sale,
                    'product_id' => $item['product_id'],
                    'unit_id' => $item['selling_unit_id'],
                    'qty' => $item['qty'],
                    'conversion_factor' => 1,
                    'base_qty' => $item['qty'],
                    'unit_price' => $item['price'],
                    'base_unit_cost' => $stockRows[array_search($item['product_id'], array_column($stockRows, 'product_id'))]['avg_cost'] ?? 0,
                    'discount' => 0,
                    'total' => (float) $item['price'] * (float) $item['qty'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('payments')->insert([
                'entity_id' => $entity,
                'business_unit_id' => $businessUnitId,
                'sale_id' => $sale,
                'user_id' => auth()->id(),
                'payment_date' => now(),
                'method' => $data['payment_method'],
                'amount' => $total,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($stockRows as $row) {
                DB::table('warehouses_stocks')->where('id', $row['stock_id'])->decrement('qty', $row['qty']);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $businessUnitId,
                    'warehouse_id' => $row['warehouse_id'],
                    'product_id' => $row['product_id'],
                    'unit_id' => $row['unit_id'],
                    'transaction_qty' => $row['qty'],
                    'conversion_factor' => 1,
                    'movement_type' => 'sale_out',
                    'qty' => -$row['qty'],
                    'unit_cost' => $row['avg_cost'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $request->session()->forget('pos_cart');

        if ($request->expectsJson()) {
            $entityRow = DB::table('entities')->where('id', $entity)->first();
            $customerName = DB::table('customers')
                ->where('entity_id', $entity)
                ->where('code', 'CUST-UMUM')
                ->value('name') ?? 'Umum';

            return response()->json([
                'ok' => true,
                'invoice_no' => $invoiceNo,
                'sale_date' => now()->format('Y-m-d H:i:s'),
                'entity' => [
                    'name' => $entityRow?->name ?? 'MINI ERP',
                    'address' => $entityRow?->address ?? '',
                    'phone' => $entityRow?->phone ?? '',
                ],
                'cashier' => auth()->user()->name,
                'shift_id' => null,
                'business_unit' => [
                    'code' => $businessUnit->code,
                    'name' => $businessUnit->name,
                ],
                'warehouse' => [
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                ],
                'customer' => $customerName,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'items' => array_values($cart),
            ]);
        }

        return back()->with('success', 'Transaksi POS berhasil diposting.');
    }
}
