<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionJournalService
{
    /**
     * Satu pintu posting jurnal untuk proses produksi.
     * Caller harus menjalankan method ini di dalam transaksi database
     * yang sama dengan posting stok/produksi.
     *
     * Idempotensi dijaga oleh kombinasi entity + source_type + source_id.
     */
    public function post(
        int $entityId,
        int $businessUnitId,
        string $journalDate,
        string $sourceType,
        int $sourceId,
        string $description,
        array $entries
    ): int {
        if ($entityId <= 0 || $businessUnitId <= 0 || $sourceId <= 0 || trim($sourceType) === '') {
            throw new RuntimeException('Identitas sumber jurnal produksi tidak lengkap.');
        }

        $normalized = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($entries as $entry) {
            $accountId = (int) ($entry['account_id'] ?? 0);
            $debit = round((float) ($entry['debit'] ?? 0), 2);
            $credit = round((float) ($entry['credit'] ?? 0), 2);

            if ($accountId <= 0 || $debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw new RuntimeException('Baris jurnal produksi tidak valid.');
            }

            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }

            $normalized[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
            ];
            $debitTotal += $debit;
            $creditTotal += $credit;
        }

        if (count($normalized) < 2 || abs($debitTotal - $creditTotal) >= 0.01) {
            throw new RuntimeException('Jurnal produksi tidak seimbang atau tidak memiliki baris yang cukup.');
        }

        $existing = DB::table('journals')
            ->where('entity_id', $entityId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $now = now();
        $journalId = (int) DB::table('journals')->insertGetId([
            'entity_id' => $entityId,
            'business_unit_id' => $businessUnitId,
            'journal_no' => 'JRN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
            'journal_date' => $journalDate,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'status' => 'posted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rows = array_map(static fn (array $entry): array => [
            'journal_id' => $journalId,
            'account_id' => $entry['account_id'],
            'debit' => $entry['debit'],
            'credit' => $entry['credit'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $normalized);

        DB::table('journal_entries')->insert($rows);

        return $journalId;
    }
}
