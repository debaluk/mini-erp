<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function store(Request $request)
    {
        $data=$request->validate(['product_id'=>'required|integer','qty'=>'required|numeric|min:0.001','payment_method'=>'required|in:Tunai,Transfer,QRIS']);
        $entity=DB::table('entities')->first(); abort_unless($entity,422,'Entitas belum tersedia.');
        DB::transaction(function()use($data,$entity){
            $product=DB::table('products')->where('entity_id',$entity->id)->find($data['product_id']); abort_unless($product,404);
            $stock=DB::table('warehouses_stocks')->where('entity_id',$entity->id)->where('product_id',$product->id)->orderBy('id')->first();
            $qty=(float)$data['qty']; abort_if(!$stock||$stock->qty<$qty,422,'Stok produk tidak mencukupi.');
            $shift=DB::table('cash_shifts')->where('entity_id',$entity->id)->where('user_id',auth()->id())->where('status','open')->latest('id')->first();
            $total=(float)$product->selling_price*$qty;
            $sale=DB::table('sales')->insertGetId(['entity_id'=>$entity->id,'user_id'=>auth()->id(),'shift_id'=>$shift?->id,'invoice_no'=>'POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),'sale_date'=>now(),'subtotal'=>$total,'total'=>$total,'status'=>'posted','created_at'=>now(),'updated_at'=>now()]);
            DB::table('sale_items')->insert(['sale_id'=>$sale,'product_id'=>$product->id,'qty'=>$qty,'unit_price'=>$product->selling_price,'total'=>$total,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('payments')->insert(['entity_id'=>$entity->id,'sale_id'=>$sale,'user_id'=>auth()->id(),'payment_date'=>now(),'method'=>$data['payment_method'],'amount'=>$total,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$stock->qty-$qty,'updated_at'=>now()]);
            DB::table('stock_movements')->insert(['entity_id'=>$entity->id,'warehouse_id'=>$stock->warehouse_id,'product_id'=>$product->id,'movement_type'=>'sale_out','qty'=>-$qty,'unit_cost'=>$stock->avg_cost,'reference_type'=>'sale','reference_id'=>$sale,'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Penjualan berhasil diposting, pembayaran diterima, dan stok berkurang.');
    }
}
