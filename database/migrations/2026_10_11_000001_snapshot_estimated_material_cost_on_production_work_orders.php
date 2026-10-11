<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_work_orders', function (Blueprint $table) {
            $table->decimal('estimated_material_cost', 15, 2)->default(0)->after('target_output_qty');
        });

        DB::table('production_work_orders')
            ->orderBy('id')
            ->chunkById(100, function ($workOrders): void {
                foreach ($workOrders as $workOrder) {
                    $estimatedMaterial = 0.0;

                    $items = DB::table('bom_items as bi')
                        ->join('products as p', 'p.id', '=', 'bi.product_id')
                        ->where('bi.bom_id', $workOrder->bom_id)
                        ->get(['bi.product_id', 'bi.unit_id', 'bi.qty', 'p.base_unit_id']);

                    foreach ($items as $item) {
                        $factor = 1.0;
                        if ((int) $item->unit_id !== (int) $item->base_unit_id) {
                            $factor = (float) (DB::table('product_unit_conversions')
                                ->where('product_id', $item->product_id)
                                ->where('unit_id', $item->unit_id)
                                ->where('is_active', 1)
                                ->value('conversion_factor') ?? 0);
                        }

                        $stockCost = DB::table('warehouses_stocks')
                            ->where('entity_id', $workOrder->entity_id)
                            ->where('warehouse_id', $workOrder->warehouse_id)
                            ->where('product_id', $item->product_id)
                            ->value('avg_cost');

                        if ($stockCost === null) {
                            $stockCost = DB::table('warehouses_stocks')
                                ->where('entity_id', $workOrder->entity_id)
                                ->where('product_id', $item->product_id)
                                ->where('qty', '>', 0)
                                ->avg('avg_cost');
                        }

                        $baseQty = round((float) $item->qty * $factor * (float) $workOrder->batch_qty, 3);
                        $estimatedMaterial += round($baseQty * (float) ($stockCost ?? 0), 2);
                    }

                    DB::table('production_work_orders')
                        ->where('id', $workOrder->id)
                        ->update(['estimated_material_cost' => round($estimatedMaterial, 2)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('production_work_orders', function (Blueprint $table) {
            $table->dropColumn('estimated_material_cost');
        });
    }
};
