<?php

namespace Database\Seeders;

use App\Services\SalesJournalService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesHppTestSeeder extends Seeder
{
    public function run(): void
    {
        $entityId = 1;
        $businessUnitId = 2; // PROD
        $productId = 3;      // BATAKO-001
        $customerId = 1;     // Customer Demo
        $userId = 3;         // Kasir

        $product = DB::table('products')
            ->where('id', $productId)
            ->where('entity_id', $entityId)
            ->where('code', 'BATAKO-001')
            ->where('is_active', 1)
            ->first();

        if (!$product) {
            throw new \RuntimeException('BATAKO-001 tidak ditemukan.');
        }

        $unitId = (int) $product->base_unit_id;
        $unitPrice = (float) $product->selling_price;

        if ($unitId <= 0) {
            throw new \RuntimeException('Base unit BATAKO-001 belum ditentukan.');
        }

        $stockCheck = DB::table('warehouses_stocks as ws')
            ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
            ->where('ws.entity_id', $entityId)
            ->where('ws.product_id', $productId)
            ->where('w.business_unit_id', $businessUnitId)
            ->select('ws.id', 'ws.warehouse_id', 'ws.qty', 'ws.avg_cost')
            ->first();

        if (!$stockCheck) {
            throw new \RuntimeException(
                'Stok BATAKO-001 untuk BU RET tidak ditemukan.'
            );
        }

        $initialStock = (float) $stockCheck->qty;

        $transactionCount = 10;
        $qtyPerTransaction = 2;
        $requiredQty = $transactionCount * $qtyPerTransaction;

        if ($initialStock < $requiredQty) {
            throw new \RuntimeException(
                "Stok tidak cukup. Stok saat ini: {$initialStock}, kebutuhan: {$requiredQty}."
            );
        }

        /*
         * Generate tanggal random, kemudian urutkan.
         * Ini penting untuk test HPP perpetual secara kronologis.
         */
        $dates = collect(range(1, $transactionCount))
            ->map(function () {
                return Carbon::now()
                    ->subDays(random_int(1, 10))
                    ->setTime(
                        random_int(8, 16),
                        random_int(0, 59),
                        random_int(0, 59)
                    );
            })
            ->sort()
            ->values();

        $journalService = app(SalesJournalService::class);

        $totalQty = 0;
        $totalSales = 0;
        $totalHpp = 0;
        $saleIds = [];

        foreach ($dates as $index => $saleDate) {
            $saleId = DB::transaction(function () use (
                $entityId,
                $businessUnitId,
                $productId,
                $customerId,
                $userId,
                $unitId,
                $unitPrice,
                $qtyPerTransaction,
                $saleDate,
                &$totalQty,
                &$totalSales,
                &$totalHpp
            ) {
                $stock = DB::table('warehouses_stocks as ws')
                    ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
                    ->where('ws.entity_id', $entityId)
                    ->where('ws.product_id', $productId)
                    ->where('w.business_unit_id', $businessUnitId)
                    ->lockForUpdate()
                    ->select('ws.*')
                    ->first();

                if (!$stock) {
                    throw new \RuntimeException(
                        'Stok BATAKO-001 tidak ditemukan.'
                    );
                }

                $stockQty = (float) $stock->qty;

                if ($stockQty < $qtyPerTransaction) {
                    throw new \RuntimeException(
                        "Stok BATAKO-001 tidak mencukupi. " .
                        "Stok: {$stockQty}, kebutuhan: {$qtyPerTransaction}."
                    );
                }

                /*
                 * HPP sama persis dengan SalesController:
                 * mengambil avg_cost dari warehouses_stocks.
                 */
                $hppUnit = (float) $stock->avg_cost;
                $hppTotal = round(
                    $qtyPerTransaction * $hppUnit,
                    2
                );

                $lineTotal = round(
                    $qtyPerTransaction * $unitPrice,
                    2
                );

                $invoiceNo =
                    'TEST-HPP-' .
                    $saleDate->format('YmdHis') .
                    '-' .
                    Str::upper(Str::random(4));

                $saleId = DB::table('sales')->insertGetId([
                    'entity_id' => $entityId,
                    'business_unit_id' => $businessUnitId,
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'invoice_no' => $invoiceNo,
                    'sale_date' => $saleDate,
                    'due_date' => null,
                    'subtotal' => $lineTotal,
                    'discount' => 0,
                    'total' => $lineTotal,
                    'status' => 'posted',
                    'memo' => 'TEST-HPP-BATAKO-001',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $productId,
                    'unit_id' => $unitId,
                    'qty' => $qtyPerTransaction,
                    'conversion_factor' => 1,
                    'base_qty' => $qtyPerTransaction,
                    'unit_price' => $unitPrice,
                    'base_unit_cost' => $hppUnit,
                    'discount' => 0,
                    'total' => $lineTotal,
                    'hpp_unit' => $hppUnit,
                    'hpp_total' => $hppTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('warehouses_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'qty' => $stockQty - $qtyPerTransaction,
                        'updated_at' => now(),
                    ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entityId,
                    'business_unit_id' => $businessUnitId,
                    'warehouse_id' => $stock->warehouse_id,
                    'product_id' => $productId,
                    'unit_id' => $unitId,
                    'transaction_qty' => $qtyPerTransaction,
                    'conversion_factor' => 1,
                    'movement_type' => 'sale_out',
                    'qty' => -$qtyPerTransaction,
                    'unit_cost' => $hppUnit,
                    'reference_type' => 'sale',
                    'reference_id' => $saleId,
                    'occurred_at' => $saleDate,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('payments')->insert([
                    'entity_id' => $entityId,
                    'business_unit_id' => $businessUnitId,
                    'sale_id' => $saleId,
                    'user_id' => $userId,
                    'payment_date' => $saleDate,
                    'method' => 'cash',
                    'amount' => $lineTotal,
                    'paid_amount' => $lineTotal,
                    'change_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $totalQty += $qtyPerTransaction;
                $totalSales += $lineTotal;
                $totalHpp += $hppTotal;

                return $saleId;
            });

            /*
             * Posting jurnal dilakukan setelah transaksi sales selesai,
             * sama seperti flow SalesController.
             */
            $journalService->post($saleId, $entityId);

            $saleIds[] = $saleId;

            $this->command->info(
                sprintf(
                    '%02d. Sale #%d | %s | Qty %d | Jual Rp%s',
                    $index + 1,
                    $saleId,
                    $saleDate->format('Y-m-d H:i:s'),
                    $qtyPerTransaction,
                    number_format(
                        $qtyPerTransaction * $unitPrice,
                        0,
                        ',',
                        '.'
                    )
                )
            );
        }

        $finalStock = DB::table('warehouses_stocks as ws')
            ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
            ->where('ws.entity_id', $entityId)
            ->where('ws.product_id', $productId)
            ->where('w.business_unit_id', $businessUnitId)
            ->value('ws.qty');

        $this->command->newLine();
        $this->command->info('======================================');
        $this->command->info(' SALES HPP TEST SELESAI');
        $this->command->info('======================================');
        $this->command->info("Transaksi     : {$transactionCount}");
        $this->command->info("Qty terjual   : {$totalQty}");
        $this->command->info(
            'Total jual    : Rp' .
            number_format($totalSales, 0, ',', '.')
        );
        $this->command->info(
            'Total HPP     : Rp' .
            number_format($totalHpp, 0, ',', '.')
        );
        $this->command->info(
            'Laba kotor    : Rp' .
            number_format(
                $totalSales - $totalHpp,
                0,
                ',',
                '.'
            )
        );
        $this->command->info("Stok akhir    : {$finalStock}");
        $this->command->info(
            'Sale IDs      : ' .
            implode(', ', $saleIds)
        );
        $this->command->info('======================================');
    }
}
