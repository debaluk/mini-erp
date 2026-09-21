<?php

namespace App\Services\Retail;

use App\Services\Inventory\InventoryCostEngine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RetailCostEngine
{
    public function __construct(
        protected InventoryCostEngine $inventoryCostEngine
    ) {
    }

    /**
     * Hitung HPP penjualan retail.
     *
     * Perpetual:
     * - ambil avg_cost saat transaksi
     * - issue stok melalui InventoryCostEngine
     * - simpan HPP pada sale_items
     *
     * Periodic:
     * - stok tetap dikeluarkan melalui InventoryCostEngine
     * - HPP transaksi = 0
     * - HPP final dihitung saat closing period
     */
    public function issueForSale(
        int $entityId,
        int $businessUnitId,
        int $warehouseId,
        int $productId,
        float $qty,
        string $hppMethod,
        ?int $saleId = null,
        ?int $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): array {
        if ($qty <= 0) {
            throw new RuntimeException('Qty penjualan harus lebih besar dari nol.');
        }

        if (!in_array($hppMethod, ['perpetual', 'periodic'], true)) {
            throw new RuntimeException('Metode HPP retail tidak valid.');
        }

        $result = $this->inventoryCostEngine->issue(
            entityId: $entityId,
            businessUnitId: $businessUnitId,
            warehouseId: $warehouseId,
            productId: $productId,
            qty: $qty,
            movementType: 'sale_out',
            referenceType: 'sale',
            referenceId: $saleId,
            userId: $userId,
            occurredAt: $occurredAt,
        );

        if ($hppMethod === 'periodic') {
            return [
                'method' => 'periodic',
                'qty' => $qty,
                'hpp_unit' => 0,
                'hpp_total' => 0,
                'balance_qty' => $result['balance_qty'],
                'balance_avg_cost' => $result['balance_avg_cost'],
            ];
        }

        return [
            'method' => 'perpetual',
            'qty' => $qty,
            'hpp_unit' => $result['unit_cost'],
            'hpp_total' => $result['total_cost'],
            'balance_qty' => $result['balance_qty'],
            'balance_avg_cost' => $result['balance_avg_cost'],
        ];
    }

    /**
     * HPP Periodic:
     *
     * HPP = Persediaan Awal + Pembelian Bersih - Persediaan Akhir
     */
    public function calculatePeriodicHpp(
        float $beginningInventory,
        float $netPurchases,
        float $endingInventory,
    ): array {
        $goodsAvailable = $beginningInventory + $netPurchases;
        $hpp = $goodsAvailable - $endingInventory;

        if ($hpp < 0) {
            throw new RuntimeException('HPP periodic tidak boleh negatif.');
        }

        return [
            'beginning_inventory' => round($beginningInventory, 2),
            'net_purchases' => round($netPurchases, 2),
            'goods_available' => round($goodsAvailable, 2),
            'ending_inventory' => round($endingInventory, 2),
            'hpp' => round($hpp, 2),
        ];
    }

    /**
     * Nilai persediaan berdasarkan saldo stock saat ini.
     */
    public function inventoryValue(
        int $entityId,
        int $warehouseId,
        int $productId
    ): float {
        $stock = DB::table('warehouses_stocks')
            ->where('entity_id', $entityId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();

        if (!$stock) {
            return 0;
        }

        return round(
            (float) $stock->qty * (float) $stock->avg_cost,
            2
        );
    }
}
