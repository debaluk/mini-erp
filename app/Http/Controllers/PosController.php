<?php

namespace App\Http\Controllers;

use App\Services\Inventory\InventoryCostEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function __construct(
        protected InventoryCostEngine $inventoryCost,
    ) {}

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
            ->join('product_business_units as pbu', 'pbu.product_id', '=', 'p.id')
            ->join('business_units as bu', function ($join) use ($entity) {
                $join->on('bu.id', '=', 'pbu.business_unit_id')->where('bu.entity_id', $entity)->where('bu.is_active', 1);
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)->where('p.is_active', 1)->where('p.id', $data['product_id'])
            ->select('p.*', 'u.code as selling_unit_code', 'u.name as selling_unit_name')
            ->first();

        abort_unless($product, 404, 'Produk tidak ditemukan.');

        $cart = $this->cart($request);
        $id = (string) $product->id;
        if (isset($cart[$id])) {
            $cart[$id]['qty'] += (float) $data['qty'];
        } else {
            $cart[$id] = [
                'product_id' => (int) $product->id,
                'code' => $product->sku ?: $product->code,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'selling_unit_code' => $product->selling_unit_code,
                'selling_unit_name' => $product->selling_unit_name,
                'price' => (float) $product->selling_price,
                'qty' => (float) $data['qty'],
            ];
        }

        $request->session()->put('pos_cart', $cart);
        return $request->expectsJson() ? response()->json(['ok'=>true,'cart'=>$cart]) : back();
    }

    public function updateItem(Request $request, string $id)
    {
        $data = $request->validate(['qty'=>'required|numeric|gt:0','price'=>'required|numeric|min:0']);
        $cart = $this->cart($request);
        abort_unless(isset($cart[$id]), 404, 'Item transaksi tidak ditemukan.');
        $cart[$id]['qty'] = (float) $data['qty'];
        $cart[$id]['price'] = (float) $data['price'];
        $request->session()->put('pos_cart', $cart);
        return $request->expectsJson() ? response()->json(['ok'=>true,'cart'=>$cart]) : back()->with('success','Item transaksi berhasil diperbarui.');
    }

    public function removeItem(Request $request, string $id)
    {
        $cart = $this->cart($request);
        abort_unless(isset($cart[$id]), 404, 'Item transaksi tidak ditemukan.');
        unset($cart[$id]);
        $request->session()->put('pos_cart', $cart);
        return $request->expectsJson() ? response()->json(['ok'=>true,'cart'=>$cart]) : back()->with('success','Barang dihapus dari transaksi.');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('pos_cart');
        return $request->expectsJson() ? response()->json(['ok'=>true,'cart'=>[]]) : back()->with('success','Transaksi sementara dikosongkan.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_unit_id' => ['required','integer'],
            'payment_method' => ['required','in:Tunai,Transfer,QRIS'],
            'discount' => ['nullable','numeric','min:0'],
            'payment_amount' => ['required','numeric','min:0'],
        ]);

        $entity = $this->entityId();
        $businessUnit = DB::table('business_units')->where('id',$data['business_unit_id'])->where('entity_id',$entity)->where('is_active',1)->first();
        abort_unless($businessUnit, 422, 'Unit Bisnis tidak valid.');

        $cart = $this->cart($request);
        abort_if(empty($cart), 422, 'Belum ada barang dalam transaksi.');

        $shift = DB::table('cash_shifts')->where('entity_id',$entity)->where('business_unit_id',$businessUnit->id)->where('user_id',auth()->id())->where('status','open')->latest('id')->first();
        abort_unless($shift, 422, 'Buka shift kasir untuk Unit Bisnis tersebut terlebih dahulu.');

        $warehouse = DB::table('warehouses')->where('entity_id',$entity)->where('business_unit_id',$businessUnit->id)->where('is_active',1)->orderBy('id')->first();
        abort_unless($warehouse, 422, 'Gudang aktif untuk Unit Bisnis tersebut belum tersedia.');

        $subtotal = collect($cart)->sum(fn($item) => (float)$item['price'] * (float)$item['qty']);
        $discount = min((float)($data['discount'] ?? 0), $subtotal);
        $total = round($subtotal - $discount, 2);
        $paidAmount = (float)$data['payment_amount'];
        abort_if($paidAmount < $total, 422, 'Nominal pembayaran kurang.');
        $changeAmount = $data['payment_method'] === 'Tunai' ? round($paidAmount - $total,2) : 0;
        if ($data['payment_method'] !== 'Tunai') {
            abort_if(round($paidAmount,2) !== round($total,2), 422, 'Transfer/QRIS harus dibayar tepat sesuai total.');
        }

        $invoiceNo = 'POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        DB::transaction(function () use ($cart,$entity,$businessUnit,$warehouse,$shift,$subtotal,$discount,$total,$paidAmount,$changeAmount,$data,$invoiceNo) {
            $customerId = DB::table('customers')->where('entity_id',$entity)->where('code','CUST-UMUM')->value('id');

            $saleId = DB::table('sales')->insertGetId([
                'entity_id'=>$entity,
                'business_unit_id'=>$businessUnit->id,
                'customer_id'=>$customerId,
                'user_id'=>auth()->id(),
                'shift_id'=>$shift->id,
                'invoice_no'=>$invoiceNo,
                'sale_date'=>now(),
                'subtotal'=>round($subtotal,2),
                'discount'=>round($discount,2),
                'total'=>$total,
                'status'=>'posted',
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            foreach ($cart as $item) {
                $cost = $this->inventoryCost->issue($entity,$businessUnit->id,$warehouse->id,(int)$item['product_id'],(float)$item['qty'],'sale_out','sale',$saleId,auth()->id());
                $lineGross = round((float)$item['price'] * (float)$item['qty'],2);
                $lineDiscount = 0;
                $lineTotal = $lineGross;

                DB::table('sale_items')->insert([
                    'sale_id'=>$saleId,
                    'product_id'=>$item['product_id'],
                    'qty'=>$item['qty'],
                    'unit_price'=>$item['price'],
                    'discount'=>$lineDiscount,
                    'total'=>$lineTotal,
                    'hpp_unit'=>$cost['unit_cost'],
                    'hpp_total'=>$cost['total_cost'],
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }

            DB::table('payments')->insert([
                'entity_id'=>$entity,
                'business_unit_id'=>$businessUnit->id,
                'sale_id'=>$saleId,
                'user_id'=>auth()->id(),
                'payment_date'=>now(),
                'method'=>$data['payment_method'],
                'amount'=>$total,
                'paid_amount'=>$paidAmount,
                'change_amount'=>$changeAmount,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        });

        $request->session()->forget('pos_cart');

        return back()->with('success','Transaksi POS berhasil diposting.');
    }
}
