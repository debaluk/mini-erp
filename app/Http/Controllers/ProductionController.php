<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'bom_id' => ['required','integer'],
            'warehouse_id' => ['required','integer'],
            'qty' => ['required','numeric','gt:0'],
        ]);

        $entity = DB::table('entities')->first();
        abort_unless($entity,422,'Entitas belum tersedia.');

        DB::transaction(function () use ($data,$entity) {
            $warehouse = DB::table('warehouses')->where('entity_id',$entity->id)
                ->where('id',$data['warehouse_id'])->where('is_active',1)->first();
            abort_unless($warehouse,422,'Gudang tidak valid.');

            $bom = DB::table('boms')->where('entity_id',$entity->id)
                ->where('id',$data['bom_id'])->where('is_active',1)->first();
            abort_unless($bom,404,'BOM tidak ditemukan.');
            abort_unless((int)$bom->business_unit_id === (int)$warehouse->business_unit_id,422,'BOM bukan milik Unit Bisnis gudang tersebut.');

            $outputProduct = DB::table('products')->where('entity_id',$entity->id)
                ->where('id',$bom->product_id)->first();
            abort_unless($outputProduct && $outputProduct->base_unit_id,422,'Produk hasil belum memiliki Base Unit.');

            $items = DB::table('bom_items')->where('bom_id',$bom->id)->get();
            abort_if($items->isEmpty(),422,'BOM belum memiliki bahan.');

            $productionId = DB::table('productions')->insertGetId([
                'entity_id'=>$entity->id,
                'business_unit_id'=>$warehouse->business_unit_id,
                'warehouse_id'=>$warehouse->id,
                'bom_id'=>$bom->id,
                'user_id'=>auth()->id(),
                'production_no'=>'PROD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'production_date'=>now(),
                'qty'=>$data['qty'],
                'status'=>'posted',
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            $totalMaterialCost = 0;

            foreach ($items as $item) {
                $material = DB::table('products')->where('id',$item->product_id)->first();
                abort_unless($material && $material->base_unit_id,422,'Bahan BOM belum memiliki Base Unit.');

                $needBaseQty = (float)$item->qty * (float)$data['qty'];
                $stock = DB::table('warehouses_stocks')->where('entity_id',$entity->id)
                    ->where('warehouse_id',$warehouse->id)->where('product_id',$item->product_id)
                    ->lockForUpdate()->first();

                abort_if(!$stock || (float)$stock->qty < $needBaseQty-0.0000001,'Stok bahan baku '.$material->name.' tidak cukup.');

                $unitCost = (float)$stock->avg_cost;
                $materialCost = round($needBaseQty*$unitCost,2);
                $totalMaterialCost += $materialCost;

                DB::table('production_material_usages')->insert([
                    'production_id'=>$productionId,
                    'product_id'=>$item->product_id,
                    'warehouse_id'=>$warehouse->id,
                    'qty'=>$needBaseQty,
                    'unit_cost'=>$unitCost,
                    'total_cost'=>$materialCost,
                    'source'=>'stock',
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);

                DB::table('warehouses_stocks')->where('id',$stock->id)->update([
                    'qty'=>(float)$stock->qty-$needBaseQty,
                    'updated_at'=>now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id'=>$entity->id,
                    'business_unit_id'=>$warehouse->business_unit_id,
                    'warehouse_id'=>$warehouse->id,
                    'product_id'=>$item->product_id,
                    'movement_type'=>'production_out',
                    'qty'=>-$needBaseQty,
                    'unit_cost'=>$unitCost,
                    'reference_type'=>'production',
                    'reference_id'=>$productionId,
                    'occurred_at'=>now(),
                    'created_by'=>auth()->id(),
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }

            // BUASO costs are already stored in production_costs; include them in
            // the finished-good cost without changing the existing HPP method.
            $otherCosts = (float)DB::table('production_costs')
                ->where('production_id',$productionId)->sum('amount');
            $totalCost = round($totalMaterialCost + $otherCosts,2);

            $outputBaseQty = (float)$bom->output_qty * (float)$data['qty'];
            abort_if($outputBaseQty <= 0,422,'Qty output Base Unit harus lebih besar dari nol.');
            $outputUnitCost = $totalCost / $outputBaseQty;

            $outputStock = DB::table('warehouses_stocks')->where('entity_id',$entity->id)
                ->where('warehouse_id',$warehouse->id)->where('product_id',$bom->product_id)
                ->lockForUpdate()->first();

            if ($outputStock) {
                $oldQty=(float)$outputStock->qty;
                $oldAvg=(float)$outputStock->avg_cost;
                $newQty=$oldQty+$outputBaseQty;
                $newAvg=$newQty>0 ? (($oldQty*$oldAvg)+($outputBaseQty*$outputUnitCost))/$newQty : $outputUnitCost;
                DB::table('warehouses_stocks')->where('id',$outputStock->id)->update([
                    'qty'=>$newQty,'avg_cost'=>$newAvg,'updated_at'=>now()
                ]);
            } else {
                DB::table('warehouses_stocks')->insert([
                    'entity_id'=>$entity->id,
                    'warehouse_id'=>$warehouse->id,
                    'product_id'=>$bom->product_id,
                    'qty'=>$outputBaseQty,
                    'avg_cost'=>$outputUnitCost,
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }

            DB::table('production_outputs')->insert([
                'production_id'=>$productionId,
                'product_id'=>$bom->product_id,
                'warehouse_id'=>$warehouse->id,
                'qty'=>$outputBaseQty,
                'unit_cost'=>$outputUnitCost,
                'total_cost'=>$totalCost,
                'output_type'=>'good',
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            DB::table('stock_movements')->insert([
                'entity_id'=>$entity->id,
                'business_unit_id'=>$warehouse->business_unit_id,
                'warehouse_id'=>$warehouse->id,
                'product_id'=>$bom->product_id,
                'movement_type'=>'production_in',
                'qty'=>$outputBaseQty,
                'unit_cost'=>$outputUnitCost,
                'reference_type'=>'production',
                'reference_id'=>$productionId,
                'occurred_at'=>now(),
                'created_by'=>auth()->id(),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            DB::table('productions')->where('id',$productionId)->update([
                'total_cost'=>$totalCost,
                'good_output_qty'=>$outputBaseQty,
                'updated_at'=>now(),
            ]);
        });

        return $request->expectsJson()
            ? response()->json(['message'=>'Produksi berhasil diposting dan stok diperbarui.'])
            : back()->with('success','Produksi berhasil diposting dan stok diperbarui.');
    }
}
