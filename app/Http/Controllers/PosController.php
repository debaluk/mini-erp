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
        $product = DB::table('products as p')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->leftJoin('product_units as pu', function ($join) {
                $join->on('pu.product_id', '=', 'p.id')->where('pu.is_default', 1);
            })
            ->leftJoin('units as u', 'u.id', '=', 'pu.unit_id')
            ->select('p.*', 'u.code as selling_unit_code', 'u.name as selling_unit_name')
            ->where('p.id', $data['product_id'])
            ->first();

        abort_unless($product, 404, 'Produk tidak ditemukan.');

        $cart = $this->cart($request);
        $id = (string) $product->id;

        if (isset($cart[$id])) {
            $cart[$id]['qty'] += (float) $data['qty'];
        } else {
            $cart[$id] = [
                'product_id' => (int) $product->id,
                'code' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'selling_unit_code' => $product->selling_unit_code,
                'selling_unit_name' => $product->selling_unit_name,
                'price' => (float) $product->selling_price,
                'qty' => (float) $data['qty'],
            ];
        }

        $request->session()->put('pos_cart', $cart);

        if ($request->expectsJson()) return response()->json(['ok'=>true,'cart'=>$cart]);
        return back();
    }

    public function updateItem(Request $request, string $id)
    {
        $data = $request->validate(['qty'=>'required|numeric|gt:0','price'=>'required|numeric|min:0']);
        $cart = $this->cart($request);
        abort_unless(isset($cart[$id]), 404, 'Item transaksi tidak ditemukan.');

        $cart[$id]['qty'] = (float) $data['qty'];
        $cart[$id]['price'] = (float) $data['price'];
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

        $shift = DB::table('cash_shifts')
            ->where('entity_id', $entity)
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->latest('id')
            ->first();

        abort_unless($shift, 422, 'Buka shift kasir terlebih dahulu.');

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

        DB::transaction(function () use ($cart, $entity, $shift, $subtotal, $discount, $total, $paidAmount, $changeAmount, $data, $invoiceNo) {
            $stockRows = [];

            foreach ($cart as $item) {
                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entity)
                    ->where('product_id', $item['product_id'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                $qty = (float) $item['qty'];
                abort_if(!$stock || (float) $stock->qty < $qty, 422, 'Stok ' . $item['name'] . ' tidak mencukupi.');

                $stockRows[] = [
                    'stock_id' => $stock->id,
                    'warehouse_id' => $stock->warehouse_id,
                    'product_id' => $item['product_id'],
                    'qty' => $qty,
                    'avg_cost' => (float) $stock->avg_cost,
                ];
            }

            $customerId = DB::table('customers')
                ->where('entity_id', $entity)
                ->where('code', 'CUST-UMUM')
                ->value('id');

            $sale = DB::table('sales')->insertGetId([
                'entity_id' => $entity,
                'customer_id' => $customerId,
                'user_id' => auth()->id(),
                'shift_id' => $shift->id,
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
                    'qty' => $item['qty'],
                    'unit_price' => $item['price'],
                    'discount' => 0,
                    'total' => (float) $item['price'] * (float) $item['qty'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('payments')->insert([
                'entity_id' => $entity,
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
                    'warehouse_id' => $row['warehouse_id'],
                    'product_id' => $row['product_id'],
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
                'shift_id' => $shift->id,
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
