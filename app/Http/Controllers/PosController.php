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
        $data = $request->validate([
            'product_id' => 'required|integer',
            'unit_id' => 'nullable|integer',
            'qty' => 'required|numeric|gt:0',
        ]);

        $entity = $this->entityId();
        $product = DB::table('products')->where('id',$data['product_id'])
            ->where('entity_id',$entity)->where('is_active',1)->where('manage_stock',1)->first();
        abort_unless($product,404,'Produk tidak ditemukan.');

        $unitId = isset($data['unit_id']) ? (int)$data['unit_id'] : (int)$product->base_unit_id;
        $factor = 1.0;
        $unit = DB::table('units')->where('id',$unitId)->first();
        abort_unless($unit,422,'Satuan transaksi tidak valid.');

        if ($unitId !== (int)$product->base_unit_id) {
            $conversion = DB::table('product_unit_conversions')
                ->where('product_id',$product->id)->where('unit_id',$unitId)->where('is_active',1)->first();
            abort_unless($conversion,422,'Satuan transaksi belum dikonfigurasi untuk item ini.');
            $factor = (float)$conversion->conversion_factor;
        }

        $cart = $this->cart($request);
        $id = $product->id . ':' . $unitId;

        if (isset($cart[$id])) {
            $cart[$id]['qty'] += (float)$data['qty'];
        } else {
            $cart[$id] = [
                'cart_key' => $id,
                'product_id' => (int)$product->id,
                'unit_id' => $unitId,
                'conversion_factor' => $factor,
                'base_unit_id' => (int)$product->base_unit_id,
                'code' => $product->sku ?: $product->code,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'selling_unit_code' => $unit->code,
                'selling_unit_name' => $unit->name,
                'price' => $unitId === (int)$product->base_unit_id
                    ? (float)$product->selling_price
                    : round((float)$product->selling_price * $factor, 2),
                'qty' => (float)$data['qty'],
            ];
        }

        $request->session()->put('pos_cart',$cart);
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
        abort_if(empty($cart),422,'Belum ada barang dalam transaksi.');

        $shift = DB::table('cash_shifts')->where('entity_id',$entity)
            ->where('user_id',auth()->id())->where('status','open')->latest('id')->first();
        abort_unless($shift,422,'Buka shift kasir terlebih dahulu.');

        $warehouse = DB::table('warehouses')
            ->where('entity_id',$entity)
            ->where('business_unit_id',$shift->business_unit_id)
            ->where('is_active',1)->orderBy('id')->first();
        abort_unless($warehouse,422,'Belum ada gudang aktif untuk Unit Bisnis POS.');

        $subtotal = 0;
        foreach($cart as $item) $subtotal += (float)$item['price'] * (float)$item['qty'];

        $discount = min((float)($data['discount'] ?? 0),$subtotal);
        $total = $subtotal - $discount;
        $paidAmount = (float)$data['payment_amount'];
        $changeAmount = $paidAmount - $total;

        abort_if($paidAmount < $total,422,'Nominal pembayaran kurang.');
        if($data['payment_method'] !== 'Tunai'){
            abort_if(round($paidAmount,2)!==round($total,2),422,'Transfer/QRIS harus dibayar tepat sesuai total.');
            $changeAmount=0;
        }

        $invoiceNo='POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        DB::transaction(function() use($cart,$entity,$shift,$warehouse,$subtotal,$discount,$total,$paidAmount,$changeAmount,$data,$invoiceNo){
            $prepared=[];
            foreach($cart as $item){
                $stock=DB::table('warehouses_stocks')->where('entity_id',$entity)
                    ->where('warehouse_id',$warehouse->id)->where('product_id',$item['product_id'])
                    ->lockForUpdate()->first();
                $qty=(float)$item['qty'];
                $baseQty=$qty*(float)$item['conversion_factor'];
                abort_if(!$stock || (float)$stock->qty < $baseQty-0.0000001,'Stok '.$item['name'].' tidak mencukupi.');
                $hppUnit=(float)$stock->avg_cost;
                $prepared[]=['item'=>$item,'stock'=>$stock,'base_qty'=>$baseQty,'hpp_unit'=>$hppUnit,'hpp_total'=>round($baseQty*$hppUnit,2)];
            }

            $customerId=DB::table('customers')->where('entity_id',$entity)->where('code','CUST-UMUM')->value('id');

            $sale=DB::table('sales')->insertGetId([
                'entity_id'=>$entity,
                'business_unit_id'=>$shift->business_unit_id,
                'customer_id'=>$customerId,
                'user_id'=>auth()->id(),
                'shift_id'=>$shift->id,
                'invoice_no'=>$invoiceNo,
                'sale_date'=>now(),
                'subtotal'=>$subtotal,
                'discount'=>$discount,
                'total'=>$total,
                'status'=>'posted',
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            foreach($prepared as $p){
                $item=$p['item'];
                DB::table('sale_items')->insert([
                    'sale_id'=>$sale,
                    'product_id'=>$item['product_id'],
                    'transaction_unit_id'=>$item['unit_id'],
                    'conversion_factor'=>$item['conversion_factor'],
                    'base_qty'=>$p['base_qty'],
                    'base_unit_id'=>$item['base_unit_id'],
                    'qty'=>$item['qty'],
                    'unit_price'=>$item['price'],
                    'discount'=>0,
                    'total'=>(float)$item['price']*(float)$item['qty'],
                    'hpp_unit'=>$p['hpp_unit'],
                    'hpp_total'=>$p['hpp_total'],
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);

                DB::table('warehouses_stocks')->where('id',$p['stock']->id)
                    ->update(['qty'=>(float)$p['stock']->qty-$p['base_qty'],'updated_at'=>now()]);

                DB::table('stock_movements')->insert([
                    'entity_id'=>$entity,
                    'business_unit_id'=>$shift->business_unit_id,
                    'warehouse_id'=>$warehouse->id,
                    'product_id'=>$item['product_id'],
                    'movement_type'=>'sale_out',
                    'qty'=>-$p['base_qty'],
                    'unit_cost'=>$p['hpp_unit'],
                    'reference_type'=>'sale',
                    'reference_id'=>$sale,
                    'occurred_at'=>now(),
                    'created_by'=>auth()->id(),
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }

            DB::table('payments')->insert([
                'entity_id'=>$entity,
                'business_unit_id'=>$shift->business_unit_id,
                'sale_id'=>$sale,
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

        if($request->expectsJson()){
            $entityRow=DB::table('entities')->where('id',$entity)->first();
            $customerName=DB::table('customers')->where('entity_id',$entity)->where('code','CUST-UMUM')->value('name')??'Umum';
            return response()->json([
                'ok'=>true,'invoice_no'=>$invoiceNo,'sale_date'=>now()->format('Y-m-d H:i:s'),
                'entity'=>['name'=>$entityRow?->name??'MINI ERP','address'=>$entityRow?->address??'','phone'=>$entityRow?->phone??''],
                'cashier'=>auth()->user()->name,'shift_id'=>$shift->id,'customer'=>$customerName,
                'subtotal'=>$subtotal,'discount'=>$discount,'total'=>$total,'payment_method'=>$data['payment_method'],
                'paid_amount'=>$paidAmount,'change_amount'=>$changeAmount,'items'=>array_values($cart),
            ]);
        }

        return back()->with('success','Transaksi POS berhasil diposting.');
    }

}
