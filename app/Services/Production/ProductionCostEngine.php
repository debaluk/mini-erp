<?php

namespace App\Services\Production;

use App\Services\Inventory\InventoryCostEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionCostEngine
{
    public function __construct(
        protected InventoryCostEngine $inventory,
    ) {}

    public function post(
        int $entityId,
        int $businessUnitId,
        int $warehouseId,
        int $bomId,
        float $qty,
        int $userId,
        array $costs = [],
        array $rejects = [],
        ?\DateTimeInterface $productionDate = null,
    ): array {
        return DB::transaction(function () use (
            $entityId,
            $businessUnitId,
            $warehouseId,
            $bomId,
            $qty,
            $userId,
            $costs,
            $rejects,
            $productionDate
        ) {
            if ($qty <= 0) {
                throw new RuntimeException('Qty produksi harus lebih besar dari nol.');
            }

            $bom = DB::table('boms')
                ->where('id', $bomId)
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $businessUnitId)
                ->where('is_active', 1)
                ->first();

            if (!$bom) {
                throw new RuntimeException('BOM tidak ditemukan atau bukan milik Unit Bisnis yang dipilih.');
            }

            $warehouse = DB::table('warehouses')
                ->where('id', $warehouseId)
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $businessUnitId)
                ->where('is_active', 1)
                ->first();

            if (!$warehouse) {
                throw new RuntimeException('Gudang tidak sesuai dengan Unit Bisnis.');
            }

            $items = DB::table('bom_items')->where('bom_id', $bom->id)->get();
            if ($items->isEmpty()) {
                throw new RuntimeException('BOM belum memiliki bahan baku.');
            }

            $productionNo = 'PROD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
            $productionId = DB::table('productions')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $businessUnitId,
                'warehouse_id' => $warehouseId,
                'bom_id' => $bom->id,
                'user_id' => $userId,
                'production_no' => $productionNo,
                'production_date' => $productionDate ?? now(),
                'qty' => $qty,
                'total_cost' => 0,
                'good_output_qty' => 0,
                'reject_qty' => 0,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $materialCost = 0;
            $materialRows = [];

            foreach ($items as $item) {
                $requiredQty = round((float) $item->qty * $qty, 3);
                $issued = $this->inventory->issue(
                    $entityId,
                    $businessUnitId,
                    $warehouseId,
                    (int) $item->product_id,
                    $requiredQty,
                    'production_out',
                    'production',
                    $productionId,
                    $userId,
                    $productionDate
                );

                $materialCost += $issued['total_cost'];

                DB::table('production_material_usages')->insert([
                    'production_id' => $productionId,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                    'qty' => $requiredQty,
                    'unit_cost' => $issued['unit_cost'],
                    'total_cost' => $issued['total_cost'],
                    'source' => 'stock',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $materialRows[] = [
                    'product_id' => $item->product_id,
                    'qty' => $requiredQty,
                    'unit_cost' => $issued['unit_cost'],
                    'total_cost' => $issued['total_cost'],
                ];
            }

            $extraCost = 0;
            $costRows = [];

            foreach ($costs as $cost) {
                $amount = round((float) ($cost['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }

                $group = strtolower(trim((string) ($cost['cost_group'] ?? 'other')));
                if (!in_array($group, ['labor', 'overhead', 'other'], true)) {
                    $group = 'other';
                }

                DB::table('production_costs')->insert([
                    'production_id' => $productionId,
                    'cost_group' => $group,
                    'description' => trim((string) ($cost['description'] ?? ucfirst($group))),
                    'amount' => $amount,
                    'source' => $cost['source'] ?? 'manual',
                    'reference_type' => $cost['reference_type'] ?? null,
                    'reference_id' => $cost['reference_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $extraCost += $amount;
                $costRows[] = [
                    'cost_group' => $group,
                    'description' => $cost['description'] ?? ucfirst($group),
                    'amount' => $amount,
                ];
            }

            $plannedOutput = round((float) $bom->output_qty * $qty, 3);
            $rejectQty = 0;
            $recoverableValue = 0;

            foreach ($rejects as $reject) {
                $rejectQty += max(0, (float) ($reject['qty'] ?? 0));
                $recoverableValue += max(0, (float) ($reject['recoverable_value'] ?? 0));

                DB::table('production_rejects')->insert([
                    'production_id' => $productionId,
                    'product_id' => $reject['product_id'] ?? null,
                    'qty' => max(0, (float) ($reject['qty'] ?? 0)),
                    'reject_type' => $reject['reject_type'] ?? 'scrap',
                    'description' => $reject['description'] ?? null,
                    'recoverable_value' => max(0, (float) ($reject['recoverable_value'] ?? 0)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $goodOutputQty = max(0, round($plannedOutput - $rejectQty, 3));
            if ($goodOutputQty <= 0) {
                throw new RuntimeException('Output baik harus lebih besar dari nol.');
            }

            $totalCost = round($materialCost + $extraCost - $recoverableValue, 2);
            $totalCost = max(0, $totalCost);
            $unitCost = round($totalCost / $goodOutputQty, 4);

            $this->inventory->receive(
                $entityId,
                $businessUnitId,
                $warehouseId,
                (int) $bom->product_id,
                $goodOutputQty,
                $unitCost,
                'production_in',
                'production',
                $productionId,
                $userId,
                $productionDate
            );

            DB::table('production_outputs')->insert([
                'production_id' => $productionId,
                'product_id' => $bom->product_id,
                'warehouse_id' => $warehouseId,
                'qty' => $goodOutputQty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'output_type' => 'good',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('productions')->where('id', $productionId)->update([
                'total_cost' => $totalCost,
                'good_output_qty' => $goodOutputQty,
                'reject_qty' => $rejectQty,
                'updated_at' => now(),
            ]);

            return [
                'production_id' => $productionId,
                'production_no' => $productionNo,
                'bom_id' => $bom->id,
                'product_id' => $bom->product_id,
                'planned_output_qty' => $plannedOutput,
                'good_output_qty' => $goodOutputQty,
                'reject_qty' => $rejectQty,
                'material_cost' => round($materialCost, 2),
                'labor_cost' => round(collect($costRows)->where('cost_group', 'labor')->sum('amount'), 2),
                'overhead_cost' => round(collect($costRows)->where('cost_group', 'overhead')->sum('amount'), 2),
                'other_cost' => round(collect($costRows)->where('cost_group', 'other')->sum('amount'), 2),
                'recoverable_value' => round($recoverableValue, 2),
                'total_cost' => $totalCost,
                'unit_cost' => $unitCost,
                'materials' => $materialRows,
                'costs' => $costRows,
            ];
        }, attempts: 3);
    }
}
