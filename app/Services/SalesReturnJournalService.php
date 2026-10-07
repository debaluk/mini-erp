<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SalesReturnJournalService
{
    /**
     * Post / rebuild jurnal retur.
     *
     * Saat edit, header jurnal yang sama dipertahankan dan hanya
     * journal_entries yang diganti. Tidak pernah membuat jurnal reversal.
     */
    public function post(object $return, object $sale, float $totalRefund, float $totalHpp, array $itemsData): int
    {
        $businessType = (string) DB::table('business_units')
            ->where('id', $return->business_unit_id)
            ->where('entity_id', $return->entity_id)
            ->value('business_type');

        $mappingKeys = match ($businessType) {
            'retail' => [
                'receivable', 'cash', 'bank', 'sales_return_merchandise',
                'inventory', 'cogs_merchandise', 'inventory_damage_loss',
            ],
            'production' => [
                'receivable', 'cash', 'bank', 'sales_return_finished_goods',
                'inventory_finished_goods', 'cogs_finished_goods', 'inventory_damage_loss',
            ],
            default => throw new RuntimeException('Jenis Unit Bisnis tidak didukung untuk jurnal retur penjualan.'),
        };

        $mapped = DB::table('business_unit_account_mappings as m')
            ->join('chart_of_accounts as a', 'a.id', '=', 'm.account_id')
            ->where('m.entity_id', $return->entity_id)
            ->where('m.business_unit_id', $return->business_unit_id)
            ->whereIn('m.mapping_key', $mappingKeys)
            ->where('a.entity_id', $return->entity_id)
            ->where('a.is_active', true)
            ->where('a.is_postable', true)
            ->pluck('m.account_id', 'm.mapping_key')
            ->map(fn ($id) => (int) $id)
            ->all();

        $paymentMethod = DB::table('payments')
            ->where('sale_id', $sale->id)
            ->orderBy('id')
            ->value('method');

        $creditKey = match ($paymentMethod) {
            'cash' => 'cash',
            'transfer', 'qris' => 'bank',
            'credit' => 'receivable',
            default => $sale->due_date !== null ? 'receivable' : 'cash',
        };

        $returnKey = $businessType === 'retail'
            ? 'sales_return_merchandise'
            : 'sales_return_finished_goods';

        $inventoryKey = $businessType === 'retail'
            ? 'inventory'
            : 'inventory_finished_goods';

        $cogsKey = $businessType === 'retail'
            ? 'cogs_merchandise'
            : 'cogs_finished_goods';

        $hppGood = 0.0;
        $hppDamaged = 0.0;

        foreach ($itemsData as $item) {
            $saleItem = DB::table('sale_items')
                ->where('id', $item['sale_item_id'])
                ->where('sale_id', $sale->id)
                ->first();

            if (!$saleItem) {
                throw new RuntimeException('Item penjualan untuk retur tidak ditemukan.');
            }

            $value = (float) $item['qty'] * (float) $saleItem->hpp_unit;

            if (($item['condition'] ?? 'good') === 'damaged') {
                $hppDamaged += $value;
            } else {
                $hppGood += $value;
            }
        }

        $required = [$creditKey, $returnKey, $cogsKey];

        if ($hppGood > 0) {
            $required[] = $inventoryKey;
        }

        if ($hppDamaged > 0) {
            $required[] = 'inventory_damage_loss';
        }

        $missing = array_values(array_filter(
            array_unique($required),
            fn ($key) => !isset($mapped[$key])
        ));

        if ($missing) {
            throw new RuntimeException('Mapping akun retur penjualan belum lengkap: ' . implode(', ', $missing) . '.');
        }

        $journal = DB::table('journals')
            ->where('entity_id', $return->entity_id)
            ->where('source_type', 'sales_return')
            ->where('source_id', $return->id)
            ->lockForUpdate()
            ->first();

        if ($journal) {
            $journalId = (int) $journal->id;

            DB::table('journals')->where('id', $journalId)->update([
                'business_unit_id' => $return->business_unit_id,
                'journal_date' => date('Y-m-d', strtotime($return->return_date)),
                'status' => 'posted',
                'description' => 'Retur penjualan #' . $return->return_no . ' (Inv: ' . $sale->invoice_no . ')',
                'updated_at' => now(),
            ]);

            DB::table('journal_entries')->where('journal_id', $journalId)->delete();
        } else {
            $journalId = (int) DB::table('journals')->insertGetId([
                'entity_id' => $return->entity_id,
                'business_unit_id' => $return->business_unit_id,
                'journal_no' => 'JRN-RET-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
                'journal_date' => date('Y-m-d', strtotime($return->return_date)),
                'source_type' => 'sales_return',
                'source_id' => $return->id,
                'description' => 'Retur penjualan #' . $return->return_no . ' (Inv: ' . $sale->invoice_no . ')',
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $entries = [
            [
                'journal_id' => $journalId,
                'account_id' => $mapped[$returnKey],
                'debit' => round($totalRefund, 2),
                'credit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_id' => $journalId,
                'account_id' => $mapped[$creditKey],
                'debit' => 0,
                'credit' => round($totalRefund, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        if ($hppGood > 0) {
            $entries[] = [
                'journal_id' => $journalId,
                'account_id' => $mapped[$inventoryKey],
                'debit' => round($hppGood, 2),
                'credit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($hppDamaged > 0) {
            $entries[] = [
                'journal_id' => $journalId,
                'account_id' => $mapped['inventory_damage_loss'],
                'debit' => round($hppDamaged, 2),
                'credit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $entries[] = [
            'journal_id' => $journalId,
            'account_id' => $mapped[$cogsKey],
            'debit' => 0,
            'credit' => round($totalHpp, 2),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('journal_entries')->insert($entries);

        return $journalId;
    }
}
