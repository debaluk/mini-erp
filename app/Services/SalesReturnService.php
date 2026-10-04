<?php

namespace App\Services;

use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Exception;

class SalesReturnService
{
    /**
     * Orchestrator Utama: Simpan/Edit Retur + Reversal Jurnal & Stok
     */
    public function processReturn(array $data, int $userId): SalesReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $isEdit = !empty($data['return_id']);

            // 1. Jika mode EDIT, eksekusi Reversal Jurnal & Mutasi Stok Lama
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
                    'return_date'      => $data['return_date'] . ' ' . date('H:i:s'),
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
            $this->postJournalGL($returnHeader, $sale, $totalReturnValue, $totalHppValue, $data['items']);

            return $returnHeader;
        });
    }

    /**
     * Rollback Jurnal & Mutasi Stok Lama saat Edit
     */
    private function rollbackPreviousTransactions(SalesReturn $return)
    {
        // Hapus Jurnal Lama
        $journals = Journal::where('source_type', 'sales_return')
            ->where('source_id', $return->id)
            ->get();

        foreach ($journals as $j) {
            JournalEntry::where('journal_id', $j->id)->delete();
            $j->delete();
        }

        // Hapus Mutasi Stok Lama
        StockMovement::where('reference_type', 'sales_return')
            ->where('reference_id', $return->id)
            ->delete();
    }

    /**
     * Record Mutasi Stok
     */
    private function createStockMovement($return, $productId, $unitId, $qty, $conversionFactor, $baseQty, $hppUnit, $condition, $userId)
    {
        // Movement Type: return_in (Menambah Stok Kembali)
        StockMovement::create([
            'entity_id'        => $return->entity_id,
            'business_unit_id' => $return->business_unit_id,
            'warehouse_id'     => $return->warehouse_id,
            'product_id'       => $productId,
            'unit_id'          => $unitId,
            'movement_type'    => 'sales_return_in',
            'qty'              => $qty,
            'transaction_qty'  => $qty,
            'conversion_factor'=> $conversionFactor,
            'unit_cost'        => $hppUnit,
            'reference_type'   => 'sales_return',
            'reference_id'     => $return->id,
            'occurred_at'      => $return->return_date,
            'created_by'       => $userId,
        ]);
    }

    /**
     * Post Auto-Journal berpasangan ke GL Engine
     */
    private function postJournalGL($return, $sale, $totalRefund, $totalHpp, array $itemsData)
    {
        $journalNo = 'JRN-RET-' . date('YmdHis') . '-' . $return->id;

        $journal = Journal::create([
            'entity_id'        => $return->entity_id,
            'business_unit_id' => $return->business_unit_id,
            'journal_no'       => $journalNo,
            'journal_date'     => date('Y-m-d', strtotime($return->return_date)),
            'source_type'      => 'sales_return',
            'source_id'        => $return->id,
            'description'      => "Jurnal Retur Penjualan No. {$return->return_no} (Inv: {$sale->invoice_no})",
            'status'           => 'posted',
        ]);

        // Fetch COA Mapping
        $accountRetur = ChartOfAccount::where('entity_id', $return->entity_id)->where('code', '4000201')->firstOrFail(); // Retur Penjualan
        $accountPiutang = ChartOfAccount::where('entity_id', $return->entity_id)->where('code', '1000301')->first(); // Piutang Usaha
        $accountKas = ChartOfAccount::where('entity_id', $return->entity_id)->where('code', '1000101')->first(); // Kas Kecil
        $accountPersediaan = ChartOfAccount::where('entity_id', $return->entity_id)->where('code', '1000401')->firstOrFail(); // Persediaan
        $accountHpp = ChartOfAccount::where('entity_id', $return->entity_id)->where('code', '5000101')->firstOrFail(); // HPP
        $accountKerusakan = ChartOfAccount::where('entity_id', $return->entity_id)->where('code', '5000901')->first(); // Beban Kerusakan

        // 1. (D) Retur Penjualan
        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $accountRetur->id,
            'debit'      => $totalRefund,
            'credit'     => 0.00,
        ]);

        // 2. (K) Piutang Usaha ATAU Kas Kecil (Tergantung jenis transaksi asal)
        $creditAccountId = ($sale->due_date != null) ? $accountPiutang->id : $accountKas->id;
        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $creditAccountId,
            'debit'      => 0.00,
            'credit'     => $totalRefund,
        ]);

        // 3. Hitung pemisahan HPP Barang Bagus vs Rusak
        $hppGood = 0;
        $hppDamaged = 0;

        foreach ($itemsData as $it) {
            $sItem = DB::table('sale_items')->where('id', $it['sale_item_id'])->first();
            abort_unless($sItem, 404);
            $val = (float)$it['qty'] * (float)$sItem->hpp_unit;
            if ($it['condition'] === 'damaged') {
                $hppDamaged += $val;
            } else {
                $hppGood += $val;
            }
        }

        // 4. (D) Persediaan Barang Dagangan (Untuk Barang Bagus)
        if ($hppGood > 0) {
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $accountPersediaan->id,
                'debit'      => $hppGood,
                'credit'     => 0.00,
            ]);
        }

        // 5. (D) Beban Kerusakan / Scrap (Untuk Barang Rusak)
        if ($hppDamaged > 0 && $accountKerusakan) {
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $accountKerusakan->id,
                'debit'      => $hppDamaged,
                'credit'     => 0.00,
            ]);
        }

        // 6. (K) HPP Barang Dagangan (Reversal total HPP)
        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $accountHpp->id,
            'debit'      => 0.00,
            'credit'     => $totalHpp,
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
