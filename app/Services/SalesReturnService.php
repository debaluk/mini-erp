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
     * Orchestrator Utama: Simpan/Edit Retur + Reversal Jurnal & Stok
     */
    public function processReturn(array $data, int $userId): SalesReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $isEdit = !empty($data['return_id']);

            // 1. Jika mode EDIT, pertahankan tanggal transaksi retur asli.
            // Koreksi hanya mengganti isi transaksi; tanggal tidak boleh bergeser
            // ke tanggal saat koreksi dilakukan.
            $existingReturn = null;
            if ($isEdit) {
                $existingReturn = SalesReturn::findOrFail($data['return_id']);
                $this->rollbackPreviousTransactions($existingReturn);
            }

            // 2. Generate Nomor Retur jika baru
            $sale = DB::table('sales')->where('id', $data['sale_id'])->first();
            abort_unless($sale, 404);
            $returnNo = $isEdit 
                ? $existingReturn->return_no 
                : $this->generateReturnNo($data['business_unit_id']);

            // 3. Simpan Header `sales_returns`
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
                    'total'            => 0.00, // Will be updated
                    'reason'           => $data['reason'] ?? null,
                    'status'           => 'posted',
                ]
            );

            // 4. Hapus Item Lama jika Edit
            if ($isEdit) {
                SalesReturnItem::where('sales_return_id', $returnHeader->id)->delete();
            }

            // 5. Processing Items & Snapshot HPP
            $totalReturnValue = 0;
            $totalHppValue = 0;

            foreach ($data['items'] as $itemData) {
                $saleItem = DB::table('sale_items')->where('id', $itemData['sale_item_id'])->first();
                abort_unless($saleItem, 404);

                $qty = (float)$itemData['qty'];
                $unitPrice = (float)$saleItem->unit_price;
                $returnValue = $qty * $unitPrice;
                
                // CRITICAL: HPP Snapshot dari sale_items.hpp_unit (Historical Cost)
                $hppUnit = (float)$saleItem->hpp_unit;
                $hppTotal = $qty * $hppUnit;

                $conversionFactor = (float)($saleItem->conversion_factor ?? 1.0);
                $baseQty = $qty * $conversionFactor;

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
                    'condition'         => $itemData['condition'], // good / damaged
                ]);

                // Record Mutasi Stok (IN)
                $this->createStockMovement($returnHeader, $saleItem->product_id, $saleItem->unit_id, $qty, $conversionFactor, $baseQty, $hppUnit, $itemData['condition'], $userId);

                $totalReturnValue += $returnValue;
                $totalHppValue += $hppTotal;
            }

            // Update Total Header
            $returnHeader->update(['total' => $totalReturnValue]);

            // 6. Post Auto-Journal ke GL Engine
            $this->journalService->post($returnHeader, $sale, $totalReturnValue, $totalHppValue, $data['items']);

            return $returnHeader;
        });
    }

    /**
     * Normalisasi tanggal retur dari input date/datetime-local menjadi DATETIME MySQL.
     */
    private function normalizeReturnDate(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new Exception('Tanggal retur wajib diisi.');
        }

        // Input dari <input type="datetime-local"> berbentuk YYYY-MM-DDTHH:MM.
        // Normalisasi eksplisit agar tidak pernah terjadi double time specification.
        $value = str_replace('T', ' ', $value);

        try {
            $date = new \DateTime($value);
        } catch (\Throwable $e) {
            throw new Exception('Tanggal dan waktu retur tidak valid. Silakan pilih ulang tanggal dan waktu retur.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Rollback Jurnal & Mutasi Stok Lama saat Edit
     */
    private function rollbackPreviousTransactions(SalesReturn $return)
    {
        // Reversal jurnal lama melalui Journal Service; jurnal lama tetap tersimpan sebagai audit trail.
        $this->journalService->reverse($return->id, auth()->id());

        // Hapus Mutasi Stok Lama
        DB::table('stock_movements')
            ->where('reference_type', 'sales_return')
            ->where('reference_id', $return->id)
            ->delete();
    }

    /**
     * Record Mutasi Stok
     */
    private function createStockMovement($return, $productId, $unitId, $qty, $conversionFactor, $baseQty, $hppUnit, $condition, $userId)
    {
        // Movement Type: return_in (Menambah Stok Kembali)
        DB::table('stock_movements')->insert([
            'entity_id'         => $return->entity_id,
            'business_unit_id'  => $return->business_unit_id,
            'warehouse_id'      => $return->warehouse_id,
            'product_id'        => $productId,
            'unit_id'           => $unitId,
            'movement_type'     => 'sales_return_in',
            'qty'               => $qty,
            'transaction_qty'   => $qty,
            'conversion_factor' => $conversionFactor,
            'unit_cost'         => $hppUnit,
            'reference_type'    => 'sales_return',
            'reference_id'     => $return->id,
            'occurred_at'       => $return->return_date,
            'created_by'        => $userId,
        ]);
    }

    /**
     * Auto Generate Nomor Retur
     */
    private function generateReturnNo($businessUnitId): string
    {
        $prefix = 'RET-202610' . date('d');
        $count = SalesReturn::where('business_unit_id', $businessUnitId)
            ->whereDate('created_at', date('Y-m-d'))
            ->count() + 1;

        return $prefix . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
