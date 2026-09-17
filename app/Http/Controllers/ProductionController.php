<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionController extends Controller
{
    public function store(Request $request)
    {
        $data=$request->validate(['bom_id'=>'required|integer','warehouse_id'=>'required|integer','qty'=>'required|numeric|min:0.001']);
        $entity=DB::table('entities')->first(); abort_unless($entity,422,'Entitas belum tersedia.');
        DB::transaction(function() use($data,$entity){
            $bom=DB::table('boms')->where('entity_id',$entity->id)->find($data['bom_id']); abort_unless($bom,404);
            $items=DB::table('bom_items')->where('bom_id',$bom->id)->get(); abort_if($items->isEmpty(),422,'BOM belum memiliki bahan.');
            $cost=0;
            foreach($items as $item){
                $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$data['warehouse_id'],'product_id'=>$item->product_id])->first();
                $need=(float)$item->qty*(float)$data['qty']; abort_if(!$stock||$stock->qty<$need,422,'Stok bahan baku tidak cukup.');
                $cost += $need*(float)$stock->avg_cost;
                DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$stock->qty-$need,'updated_at'=>now()]);
                DB::table('stock_movements')->insert(['entity_id'=>$entity->id,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$item->product_id,'movement_type'=>'production_out','qty'=>-$need,'unit_cost'=>$stock->avg_cost,'reference_type'=>'production','occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            }
            $output=(float)$bom->output_qty*(float)$data['qty']; $unitCost=$output>0?$cost/$output:0;
            $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$data['warehouse_id'],'product_id'=>$bom->product_id])->first();
            if($stock) DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$stock->qty+$output,'avg_cost'=>$unitCost,'updated_at'=>now()]);
            else DB::table('warehouses_stocks')->insert(['entity_id'=>$entity->id,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$bom->product_id,'qty'=>$output,'avg_cost'=>$unitCost,'created_at'=>now(),'updated_at'=>now()]);
            $production=DB::table('productions')->insertGetId(['entity_id'=>$entity->id,'warehouse_id'=>$data['warehouse_id'],'bom_id'=>$bom->id,'user_id'=>auth()->id(),'production_no'=>'PROD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),'production_date'=>now(),'qty'=>$data['qty'],'total_cost'=>$cost,'status'=>'posted','created_at'=>now(),'updated_at'=>now()]);
            DB::table('stock_movements')->insert(['entity_id'=>$entity->id,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$bom->product_id,'movement_type'=>'production_in','qty'=>$output,'unit_cost'=>$unitCost,'reference_type'=>'production','reference_id'=>$production,'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Produksi berhasil diposting dan stok diperbarui.');
    }
}
