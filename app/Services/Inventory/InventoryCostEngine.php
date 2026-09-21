<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryCostEngine
{
    public function receive(
        int $entityId,
        int $businessUnitId,
        int $warehouseId,
        int $productId,
        float $qty,
        float $unitCost,
        string $movementType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): array {
        if ($qty <= 0) {
            throw new RuntimeException('Qty penerimaan stok harus lebih besar dari nol.');
        }

        $stock = DB::table('warehouses_stocks')
            ->where('entity_id', $entityId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        $oldQty = (float) ($stock->qty ?? 0);
        $oldAvg = (float) ($stock->avg_cost ?? 0);
        $newQty = $oldQty + $qty;

        if ($newQty <= 0) {
            throw new RuntimeException('Saldo stok tidak valid.');
        }

        $newAvg = $oldQty > 0
            ? (($oldQty * $oldAvg) + ($qty * $unitCost)) / $newQty
            : $unitCost;

        if ($stock) {
            DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                'qty' => $newQty,
                'avg_cost' => round($newAvg, 4),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('warehouses_stocks')->insert([
                'entity_id' => $entityId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'qty' => $newQty,
                'avg_cost' => round($newAvg, 4),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('stock_movements')->insert([
            'entity_id' => $entityId,
            'business_unit_id' => $businessUnitId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'qty' => $qty,
            'unit_cost' => round($unitCost, 4),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'occurred_at' => $occurredAt ?? now(),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'qty' => $newQty,
            'avg_cost' => round($newAvg, 4),
            'value' => round($newQty * $newAvg, 2),
        ];
    }

    public function issue(
        int $entityId,
        int $businessUnitId,
        int $warehouseId,
        int $productId,
        float $qty,
        string $movementType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): array {
        if ($qty <= 0) {
            throw new RuntimeException('Qty pengeluaran stok harus lebih besar dari nol.');
        }

        $stock = DB::table('warehouses_stocks')
            ->where('entity_id', $entityId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        $available = (float) ($stock->qty ?? 0);
        if (!$stock || $available < $qty) {
            $productName = DB::table('products')->where('id', $productId)->value('name') ?? ('#'.$productId);
            throw new RuntimeException("Stok {$productName} tidak mencukupi.");
        }

        $businessUnit = DB::table('business_units')
            ->where('id', $businessUnitId)
            ->where('entity_id', $entityId)
            ->first();

        $product = DB::table('products')
            ->where('id', $productId)
            ->where('entity_id', $entityId)
            ->first();

        $method = $businessUnit->hpp_method ?? 'perpetual';
        $unitCost = (float) $stock->avg_cost;

        if ($method === 'direct_cost' && (float) ($product->cost_price ?? 0) > 0) {
            $unitCost = (float) $product->cost_price;
        }

        $newQty = $available - $qty;

        DB::table('warehouses_stocks')->where('id', $stock->id)->update([
            'qty' => $newQty,
            'updated_at' => now(),
        ]);

        DB::table('stock_movements')->insert([
            'entity_id' => $entityId,
            'business_unit_id' => $businessUnitId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'qty' => -$qty,
            'unit_cost' => round($unitCost, 4),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'occurred_at' => $occurredAt ?? now(),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'qty' => $qty,
            'unit_cost' => round($unitCost, 4),
            'total_cost' => round($qty * $unitCost, 2),
            'balance_qty' => $newQty,
            'balance_avg_cost' => round((float) $stock->avg_cost, 4),
            'method' => $method,
        ];
    }

    public function currentUnitCost(int $entityId, int $warehouseId, int $productId): float
    {
        return (float) (
            DB::table('warehouses_stocks')
                ->where('entity_id', $entityId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->value('avg_cost') ?? 0
        );
    }
}
