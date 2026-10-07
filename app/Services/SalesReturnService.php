<?php

namespace App\Services;

use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Support\Facades\DB;
use Exception;

class SalesReturnService
{
    private SalesReturnJournalService $journalService;

    public function __construct(SalesReturnJournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * Simpan / edit retur penjualan.
     *
     * Aturan:
     * - selama periode masih open, retur boleh dibuat dan dikoreksi;
     * - koreksi menghapus efek lama lalu membangun ulang transaksi pada jurnal
     *   yang sama, tanpa membuat jurnal reversal;
     * - periode closing/closed tidak boleh diubah.
     */
    public function processReturn(array $data, int $userId): SalesReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $isEdit = !empty($data['return_id']);
            $existingReturn = null;

            if ($isEdit) {
                $existingReturn = SalesReturn::whereKey((int) $data['return_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($existingReturn->status === 'cancelled') {
                    throw new Exception('Retur penjualan yang sudah cancelled tidak dapat diedit.');
                }

                $this->assertPeriodOpen($existingReturn->return_date);
                $this->assertPeriodOpen($data['return_date'] ?? $existingReturn->return_date);

                if ((int) $existingReturn->sale_id !== (int) $data['sale_id']) {
                    throw new Exception('Invoice asal retur tidak boleh diganti saat edit.');
                }

                // Simpan pasangan gudang/item lama sebelum mutasi dihapus.
                // Ini penting bila saat edit gudang atau item berubah.
                $oldStockKeys = DB::table('stock_movements')
                    ->where('reference_type', 'sales_return')
                    ->where('reference_id', $existingReturn->id)
                    ->where('qty', '>', 0)
                    ->select('warehouse_id', 'product_id')
                    ->distinct()
                    ->get();

                // Hapus efek stok lama. Saldo stok kemudian dihitung ulang
                // dari seluruh mutasi agar moving average tetap konsisten.
                DB::table('stock_movements')
                    ->where('reference_type', 'sales_return')
                    ->where('reference_id', $existingReturn->id)
                    ->delete();
            }

            $sale = DB::table('sales')
                ->where('id', $data['sale_id'])
                ->where('status', 'posted')
                ->lockForUpdate()
                ->first();

            abort_unless($sale, 404);

            // Sumber gudang retur wajib mengikuti gudang yang dipakai invoice.
            $saleWarehouseIds = DB::table('stock_movements')
                ->where('reference_type', 'sale')
                ->where('reference_id', $sale->id)
                ->where('movement_type', 'sale_out')
                ->distinct()
                ->pluck('warehouse_id')
                ->filter()
                ->values();

            if ($saleWarehouseIds->count() !== 1) {
                throw new Exception($saleWarehouseIds->isEmpty()
                    ? 'Gudang asal invoice tidak ditemukan dari mutasi stok penjualan.'
                    : 'Invoice penjualan menggunakan lebih dari satu gudang. Retur harus diproses per gudang asal.');
            }

            $data['warehouse_id'] = (int) $saleWarehouseIds->first();

            if (!$isEdit) {
                $this->assertPeriodOpen($data['return_date'] ?? null);
            }

            $returnNo = $isEdit
                ? $existingReturn->return_no
                : $this->generateReturnNo((int) $data['business_unit_id']);

            $returnHeader = SalesReturn::updateOrCreate(
                ['id' => $data['return_id'] ?? null],
                [
                    'entity_id'        => $sale->entity_id,
                    'business_unit_id' => $data['business_unit_id'],
                    'sale_id'          => $sale->id,
                    'customer_id'      => $sale->customer_id,
                    'warehouse_id'     => $data['warehouse_id'],
                    'user_id'          => $userId,
                    'return_no'        => $returnNo,
                    'return_date'      => $isEdit
                        ? $existingReturn->return_date
                        : $this->normalizeReturnDate($data['return_date'] ?? null),
                    'total'            => 0.00,
                    'reason'           => $data['reason'] ?? null,
                    'status'           => 'posted',
                ]
            );

            if ($isEdit) {
                SalesReturnItem::where('sales_return_id', $returnHeader->id)->delete();
            }

            $totalReturnValue = 0.0;
            $totalHppValue = 0.0;
            $stockKeys = [];

            if ($isEdit) {
                foreach ($oldStockKeys as $oldStock) {
                    $stockKeys[$oldStock->warehouse_id . ':' . $oldStock->product_id] = [
                        'warehouse_id' => (int) $oldStock->warehouse_id,
                        'product_id' => (int) $oldStock->product_id,
                    ];
                }
            }

            foreach ($data['items'] as $itemData) {
                $saleItem = DB::table('sale_items')
                    ->where('id', $itemData['sale_item_id'])
                    ->where('sale_id', $sale->id)
                    ->first();

                abort_unless($saleItem, 422, 'Item retur tidak sesuai dengan invoice asal.');

                $qty = (float) $itemData['qty'];
                $returnedQty = (float) DB::table('sales_return_items as sri')
                    ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
                    ->where('sri.sale_item_id', $saleItem->id)
                    ->where('sr.sale_id', $sale->id)
                    ->when($isEdit, fn ($q) => $q->where('sr.id', '<>', $returnHeader->id))
                    ->sum('sri.qty');

                if ($returnedQty + $qty > (float) $saleItem->qty + 0.000001) {
                    throw new Exception('Jumlah retur melebihi sisa item pada invoice.');
                }

                $unitPrice = (float) $saleItem->unit_price;
                $hppUnit = (float) $saleItem->hpp_unit;
                $conversionFactor = (float) ($saleItem->conversion_factor ?? 1.0);
                $baseQty = $qty * $conversionFactor;
                $returnValue = $qty * $unitPrice;
                $hppTotal = $qty * $hppUnit;
                $condition = $itemData['condition'] ?? 'good';

                SalesReturnItem::create([
                    'sales_return_id'   => $returnHeader->id,
                    'sale_item_id'      => $saleItem->id,
                    'product_id'        => $saleItem->product_id,
                    'unit_id'           => $saleItem->unit_id,
                    'qty'               => $qty,
                    'conversion_factor' => $conversionFactor,
                    'base_qty'          => $baseQty,
                    'unit_price'        => $unitPrice,
                    'return_value'      => $returnValue,
                    'hpp_unit'          => $hppUnit,
                    'hpp_total'         => $hppTotal,
                    'condition'         => $condition,
                ]);

                $this->createStockMovement(
                    $returnHeader,
                    $saleItem->product_id,
                    $saleItem->unit_id,
                    $qty,
                    $conversionFactor,
                    $baseQty,
                    $hppUnit,
                    $condition,
                    $userId
                );

                if ($condition === 'good') {
                    $stockKeys[$returnHeader->warehouse_id . ':' . $saleItem->product_id] = [
                        'warehouse_id' => (int) $returnHeader->warehouse_id,
                        'product_id' => (int) $saleItem->product_id,
                    ];
                }

                $totalReturnValue += $returnValue;
                $totalHppValue += $hppTotal;
            }

            $returnHeader->update(['total' => $totalReturnValue]);

            foreach ($stockKeys as $key) {
                $this->rebuildStock((int) $returnHeader->entity_id, $key['warehouse_id'], $key['product_id']);
            }

            // Journal header dipertahankan saat edit; hanya journal_entries yang
            // dibangun ulang oleh Journal Service.
            $this->journalService->post(
                $returnHeader,
                $sale,
                $totalReturnValue,
                $totalHppValue,
                $data['items']
            );

            return $returnHeader->fresh();
        });
    }

    /**
     * Cancel retur sebelum closing.
     * Data header/item tetap dipertahankan untuk audit; efek stok dan jurnal dihapus.
     */
    public function deleteReturn(int $returnId): void
    {
        DB::transaction(function () use ($returnId) {
            $return = SalesReturn::whereKey($returnId)->lockForUpdate()->firstOrFail();

            if ($return->status === 'cancelled') {
                throw new Exception('Retur penjualan sudah cancelled.');
            }

            $this->assertPeriodOpen($return->return_date);

            $stockKeys = DB::table('sales_return_items')
                ->where('sales_return_id', $return->id)
                ->where('condition', 'good')
                ->select('product_id')
                ->distinct()
                ->get()
                ->mapWithKeys(fn ($row) => [
                    $return->warehouse_id . ':' . $row->product_id => [
                        'warehouse_id' => (int) $return->warehouse_id,
                        'product_id' => (int) $row->product_id,
                    ],
                ])
                ->all();

            DB::table('stock_movements')
                ->where('reference_type', 'sales_return')
                ->where('reference_id', $return->id)
                ->delete();

            $journal = DB::table('journals')
                ->where('entity_id', $return->entity_id)
                ->where('source_type', 'sales_return')
                ->where('source_id', $return->id)
                ->lockForUpdate()
                ->first();

            if ($journal) {
                DB::table('journal_entries')->where('journal_id', $journal->id)->delete();
                DB::table('journals')->where('id', $journal->id)->delete();
            }

            DB::table('sales_returns')->where('id', $return->id)->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

            foreach ($stockKeys as $key) {
                $this->rebuildStock($return->entity_id, $key['warehouse_id'], $key['product_id']);
            }
        });
    }

    private function assertPeriodOpen($date): void
    {
        if (!$date) {
            throw new Exception('Tanggal retur wajib diisi.');
        }

        $date = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : date('Y-m-d', strtotime((string) $date));

        $period = DB::table('accounting_periods')
            ->where('entity_id', (int) (DB::table('entities')->value('id') ?? 1))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('id')
            ->first();

        if ($period && $period->status !== 'open') {
            throw new Exception('Periode akuntansi sudah ditutup atau sedang dalam proses closing. Retur penjualan tidak dapat diubah.');
        }
    }

    private function normalizeReturnDate(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new Exception('Tanggal retur wajib diisi.');
        }

        $value = str_replace('T', ' ', $value);

        try {
            $date = new \DateTime($value);
        } catch (\Throwable $e) {
            throw new Exception('Tanggal dan waktu retur tidak valid. Silakan pilih ulang tanggal dan waktu retur.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function createStockMovement(
        SalesReturn $return,
        int $productId,
        int $unitId,
        float $qty,
        float $conversionFactor,
        float $baseQty,
        float $hppUnit,
        string $condition,
        int $userId
    ): void {
        DB::table('stock_movements')->insert([
            'entity_id'         => $return->entity_id,
            'business_unit_id'  => $return->business_unit_id,
            'warehouse_id'      => $return->warehouse_id,
            'product_id'        => $productId,
            'unit_id'           => $unitId,
            'movement_type'     => $condition === 'good' ? 'sales_return_in' : 'sales_return_reject',
            'qty'               => $condition === 'good' ? $baseQty : 0,
            'transaction_qty'   => $qty,
            'conversion_factor' => $conversionFactor,
            'unit_cost'         => $hppUnit,
            'reference_type'    => 'sales_return',
            'reference_id'      => $return->id,
            'occurred_at'       => $return->return_date,
            'created_by'        => $userId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Bangun ulang qty + moving average dari seluruh mutasi item/gudang.
     * Dipakai setelah retur edit/hapus agar tidak meninggalkan avg_cost lama.
     */
    private function rebuildStock(int $entityId, int $warehouseId, int $productId): void
    {
        $movements = DB::table('stock_movements')
            ->where('entity_id', $entityId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $qty = 0.0;
        $avgCost = 0.0;

        foreach ($movements as $movement) {
            $movementQty = (float) $movement->qty;
            $unitCost = (float) ($movement->unit_cost ?? 0);

            if ($movementQty > 0) {
                $newQty = $qty + $movementQty;
                if ($newQty > 0) {
                    $avgCost = (($qty * $avgCost) + ($movementQty * $unitCost)) / $newQty;
                }
                $qty = $newQty;
            } elseif ($movementQty < 0) {
                $qty += $movementQty;
                if ($qty <= 0) {
                    $qty = 0.0;
                    $avgCost = 0.0;
                }
            }
        }

        $stock = DB::table('warehouses_stocks')
            ->where('entity_id', $entityId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                'qty' => round($qty, 6),
                'avg_cost' => round($avgCost, 6),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('warehouses_stocks')->insert([
                'entity_id' => $entityId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'qty' => round($qty, 6),
                'avg_cost' => round($avgCost, 6),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function generateReturnNo($businessUnitId): string
    {
        $prefix = 'RET-202610' . date('d');
        $count = SalesReturn::where('business_unit_id', $businessUnitId)
            ->whereDate('created_at', date('Y-m-d'))
            ->count() + 1;

        return $prefix . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
