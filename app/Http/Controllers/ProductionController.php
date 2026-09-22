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
            'bom_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $entity = DB::table('entities')->first();
        abort_unless($entity, 422, 'Entitas belum tersedia.');

        DB::transaction(function () use ($data, $entity): void {
            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entity->id)
                ->where('is_active', 1)
                ->where('id', $data['warehouse_id'])
                ->first();
            abort_unless($warehouse, 422, 'Gudang tidak valid.');

            $bom = DB::table('boms')
                ->where('entity_id', $entity->id)
                ->where('business_unit_id', $warehouse->business_unit_id)
                ->where('id', $data['bom_id'])
                ->first();
            abort_unless($bom, 404, 'BOM tidak valid.');

            $items = DB::table('bom_items')->where('bom_id', $bom->id)->get();
            abort_if($items->isEmpty(), 422, 'BOM belum memiliki bahan.');

            $productionNo = 'PROD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3));
            $production = DB::table('productions')->insertGetId([
                'entity_id' => $entity->id,
                'business_unit_id' => $warehouse->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'bom_id' => $bom->id,
                'user_id' => auth()->id(),
                'production_no' => $productionNo,
                'production_date' => now(),
                'qty' => $data['qty'],
                'total_cost' => 0,
                'good_output_qty' => 0,
                'reject_qty' => 0,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cost = 0.0;

            foreach ($items as $item) {
                $material = DB::table('products')
                    ->where('entity_id', $entity->id)
                    ->where('id', $item->product_id)
                    ->first();
                abort_unless($material, 422, 'Bahan BOM tidak valid.');

                // BOM quantities are stored in the material Base Unit.
                $need = (float) $item->qty * (float) $data['qty'];

                $stock = DB::table('warehouses_stocks')
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                abort_if(!$stock || (float) $stock->qty < $need, 422, 'Stok bahan baku tidak cukup.');

                $unitCost = (float) $stock->avg_cost;
                $materialCost = $need * $unitCost;
                $cost += $materialCost;

                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => (float) $stock->qty - $need,
                    'updated_at' => now(),
                ]);

                DB::table('production_material_usages')->insert([
                    'production_id' => $production,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouse->id,
                    'qty' => $need,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($materialCost, 2),
                    'source' => 'stock',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity->id,
                    'business_unit_id' => $warehouse->business_unit_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $item->product_id,
                    'unit_id' => $material->base_unit_id,
                    'transaction_qty' => $need,
                    'conversion_factor' => 1,
                    'movement_type' => 'production_out',
                    'qty' => -$need,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'production',
                    'reference_id' => $production,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $finished = DB::table('products')
                ->where('entity_id', $entity->id)
                ->where('id', $bom->product_id)
                ->first();
            abort_unless($finished, 422, 'Produk hasil BOM tidak valid.');

            // BOM output quantity is in the finished product Base Unit.
            $output = (float) $bom->output_qty * (float) $data['qty'];
            $unitCost = $output > 0 ? $cost / $output : 0;

            $stock = DB::table('warehouses_stocks')
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $bom->product_id)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $oldQty = (float) $stock->qty;
                $oldAvg = (float) $stock->avg_cost;
                $newQty = $oldQty + $output;
                $newAvg = $newQty > 0 ? (($oldQty * $oldAvg) + ($output * $unitCost)) / $newQty : 0;

                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => $newQty,
                    'avg_cost' => round($newAvg, 9),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('warehouses_stocks')->insert([
                    'entity_id' => $entity->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $bom->product_id,
                    'qty' => $output,
                    'avg_cost' => round($unitCost, 9),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('production_outputs')->insert([
                'production_id' => $production,
                'product_id' => $bom->product_id,
                'warehouse_id' => $warehouse->id,
                'qty' => $output,
                'unit_cost' => $unitCost,
                'total_cost' => round($cost, 2),
                'output_type' => 'good',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('stock_movements')->insert([
                'entity_id' => $entity->id,
                'business_unit_id' => $warehouse->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $bom->product_id,
                'unit_id' => $finished->base_unit_id,
                'transaction_qty' => $output,
                'conversion_factor' => 1,
                'movement_type' => 'production_in',
                'qty' => $output,
                'unit_cost' => $unitCost,
                'reference_type' => 'production',
                'reference_id' => $production,
                'occurred_at' => now(),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('productions')->where('id', $production)->update([
                'total_cost' => round($cost, 2),
                'good_output_qty' => $output,
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Produksi berhasil diposting dan stok diperbarui.');
    }
}
