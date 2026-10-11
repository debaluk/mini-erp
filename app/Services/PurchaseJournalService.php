<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseJournalService
{
    /**
     * Post or refresh the financial journal for a purchase invoice.
     * Stock receipt, moving-average cost, and purchase returns are intentionally
     * outside this service.
     */
    public function post(int $purchaseId, int $entityId, ?int $legacyReceiptId = null): int
    {
        if ($entityId <= 0) {
            throw new RuntimeException('Entitas pembelian tidak valid.');
        }

        return DB::transaction(function () use ($purchaseId, $entityId, $legacyReceiptId): int {
            $purchase = DB::table('purchases')
                ->where('entity_id', $entityId)
                ->where('id', $purchaseId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$purchase) {
                throw new RuntimeException('Faktur pembelian tidak ditemukan pada entitas ini.');
            }

            if (!in_array($purchase->source_type, ['po', 'direct'], true)) {
                throw new RuntimeException('Jenis sumber faktur pembelian tidak didukung untuk jurnal.');
            }

            $businessUnit = DB::table('business_units')
                ->where('entity_id', $entityId)
                ->where('id', $purchase->business_unit_id)
                ->where('is_active', 1)
                ->first();

            if (!$businessUnit) {
                throw new RuntimeException('Unit Bisnis faktur tidak aktif atau bukan milik entitas pengguna.');
            }

            $creditKey = match (strtolower((string) ($purchase->payment_method ?? 'credit'))) {
                'cash', 'tunai' => 'cash',
                'bank', 'transfer', 'qris' => 'bank',
                'credit' => 'payable',
                default => throw new RuntimeException('Metode pembayaran faktur pembelian tidak valid.'),
            };

            $mappingKeys = ['inventory', $creditKey];
            $mapped = DB::table('business_unit_account_mappings as m')
                ->join('chart_of_accounts as a', 'a.id', '=', 'm.account_id')
                ->where('m.entity_id', $entityId)
                ->where('m.business_unit_id', $purchase->business_unit_id)
                ->whereIn('m.mapping_key', $mappingKeys)
                ->where('a.entity_id', $entityId)
                ->where('a.is_active', 1)
                ->where('a.is_postable', 1)
                ->pluck('m.account_id', 'm.mapping_key')
                ->map(fn ($id) => (int) $id)
                ->all();

            $missing = array_values(array_filter($mappingKeys, fn ($key) => !isset($mapped[$key])));
            if ($missing) {
                throw new RuntimeException('Mapping akun pembelian belum lengkap atau tidak aktif: '.implode(', ', $missing).'.');
            }

            $journal = DB::table('journals')
                ->where('entity_id', $entityId)
                ->where('source_type', 'purchase_invoice')
                ->where('source_id', $purchase->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            // Migrate a legacy Non-PO receipt journal only when no invoice journal exists.
            if (!$journal && $purchase->source_type === 'direct' && $legacyReceiptId) {
                $legacy = DB::table('journals')
                    ->where('entity_id', $entityId)
                    ->where('source_type', 'receipt')
                    ->where('source_id', $legacyReceiptId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($legacy) {
                    DB::table('journals')->where('id', $legacy->id)->update([
                        'source_type' => 'purchase_invoice',
                        'source_id' => $purchase->id,
                        'updated_at' => now(),
                    ]);
                    $journal = DB::table('journals')->where('id', $legacy->id)->first();
                }
            }

            $journalDate = date('Y-m-d', strtotime($purchase->purchase_date));
            $description = 'Faktur pembelian '.$purchase->purchase_no;

            if (!$journal) {
                $journalId = DB::table('journals')->insertGetId([
                    'entity_id' => $entityId,
                    'business_unit_id' => $purchase->business_unit_id,
                    'journal_no' => 'JRN-INV-'.$purchase->id,
                    'journal_date' => $journalDate,
                    'source_type' => 'purchase_invoice',
                    'source_id' => $purchase->id,
                    'description' => $description,
                    'status' => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $journalId = (int) $journal->id;
                DB::table('journals')->where('id', $journalId)->update([
                    'entity_id' => $entityId,
                    'business_unit_id' => $purchase->business_unit_id,
                    'journal_date' => $journalDate,
                    'description' => $description,
                    'status' => 'posted',
                    'updated_at' => now(),
                ]);
                DB::table('journal_entries')->where('journal_id', $journalId)->delete();
            }

            $amount = round((float) $purchase->total, 2);
            if ($amount < 0) {
                throw new RuntimeException('Total faktur pembelian tidak boleh negatif.');
            }

            DB::table('journal_entries')->insert([
                [
                    'journal_id' => $journalId,
                    'account_id' => $mapped['inventory'],
                    'debit' => $amount,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'journal_id' => $journalId,
                    'account_id' => $mapped[$creditKey],
                    'debit' => 0,
                    'credit' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            return $journalId;
        });
    }
    /**
     * Remove journals for a purchase invoice being cancelled.
     * Legacy receipt journals are included for receipts belonging to this invoice.
     */
    public function cancel(int $purchaseId, int $entityId): void
    {
        if ($entityId <= 0) {
            throw new RuntimeException('Entitas pembelian tidak valid.');
        }

        DB::transaction(function () use ($purchaseId, $entityId): void {
            $purchase = DB::table('purchases')
                ->where('entity_id', $entityId)
                ->where('id', $purchaseId)
                ->lockForUpdate()
                ->first();

            if (!$purchase) {
                throw new RuntimeException('Faktur pembelian tidak ditemukan pada entitas ini.');
            }

            $receiptIds = DB::table('receipts')
                ->where('entity_id', $entityId)
                ->where('purchase_id', $purchaseId)
                ->pluck('id');

            $journalQuery = DB::table('journals')
                ->where('entity_id', $entityId)
                ->where(function ($query) use ($purchaseId, $receiptIds): void {
                    $query->where(function ($q) use ($purchaseId): void {
                        $q->where('source_type', 'purchase_invoice')
                            ->where('source_id', $purchaseId);
                    });

                    if ($receiptIds->isNotEmpty()) {
                        $query->orWhere(function ($q) use ($receiptIds): void {
                            $q->where('source_type', 'receipt')
                                ->whereIn('source_id', $receiptIds);
                        });
                    }
                });

            $journalIds = $journalQuery->lockForUpdate()->pluck('id');
            if ($journalIds->isNotEmpty()) {
                DB::table('journal_entries')->whereIn('journal_id', $journalIds)->delete();
                DB::table('journals')->whereIn('id', $journalIds)->delete();
            }
        });
    }

}
