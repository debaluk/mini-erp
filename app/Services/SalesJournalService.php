<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SalesJournalService
{
    public function post(int $saleId, ?int $entityId = null): int
    {
        $entityId ??= (int) (DB::table('entities')->value('id') ?? 1);

        return DB::transaction(function () use ($saleId, $entityId): int {
            $sale = DB::table('sales as s')
                ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
                ->join('business_units as bu', 'bu.id', '=', 's.business_unit_id')
                ->where('s.id', $saleId)
                ->where('s.entity_id', $entityId)
                ->lockForUpdate()
                ->select('s.*', 'c.name as customer_name', 'bu.business_type')
                ->first();

            if (!$sale) {
                throw new RuntimeException('Penjualan tidak ditemukan.');
            }

            $existing = DB::table('journals')
                ->where('entity_id', $entityId)
                ->where('source_type', 'sale')
                ->where('source_id', $sale->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status !== 'posted') {
                    DB::table('journals')->where('id', $existing->id)->update([
                        'status' => 'posted',
                        'updated_at' => now(),
                    ]);
                }

                return (int) $existing->id;
            }

            $mappingKeys = match ($sale->business_type) {
                'retail' => ['cash','bank','receivable','inventory','sales_merchandise','cogs_merchandise'],
                'production' => ['cash','bank','receivable','inventory','sales_finished_goods','cogs_finished_goods'],
                'service' => ['cash','bank','receivable','sales_service'],
                default => throw new RuntimeException('Jenis Unit Bisnis tidak didukung untuk posting penjualan.'),
            };

            $mapped = DB::table('business_unit_account_mappings as m')
                ->join('chart_of_accounts as a', 'a.id', '=', 'm.account_id')
                ->where('m.entity_id', $entityId)
                ->where('m.business_unit_id', $sale->business_unit_id)
                ->whereIn('m.mapping_key', $mappingKeys)
                ->where('a.entity_id', $entityId)
                ->where('a.is_active', true)
                ->where('a.is_postable', true)
                ->pluck('m.account_id', 'm.mapping_key')
                ->map(fn ($id) => (int) $id)
                ->all();

            $paymentMethod = DB::table('payments')
                ->where('sale_id', $sale->id)
                ->orderBy('id')
                ->value('method');

            $debitKey = match ($paymentMethod) {
                'cash' => 'cash',
                'transfer', 'qris' => 'bank',
                'credit' => 'receivable',
                default => throw new RuntimeException('Metode pembayaran penjualan tidak valid untuk posting jurnal.'),
            };

            $salesKey = match ($sale->business_type) {
                'retail' => 'sales_merchandise',
                'production' => 'sales_finished_goods',
                'service' => 'sales_service',
            };

            $required = [$debitKey, $salesKey];

            $hppTotal = (float) DB::table('sale_items')
                ->where('sale_id', $sale->id)
                ->sum('hpp_total');

            if (in_array($sale->business_type, ['retail', 'production'], true) && $hppTotal > 0) {
                $required[] = $sale->business_type === 'retail' ? 'cogs_merchandise' : 'cogs_finished_goods';
                $required[] = 'inventory';
            }

            $missing = array_values(array_filter($required, fn ($key) => !isset($mapped[$key])));
            if ($missing) {
                throw new RuntimeException('Mapping akun penjualan belum lengkap: '.implode(', ', $missing).'.');
            }

            $customer = trim((string) ($sale->customer_name ?? ''));
            $description = 'Penjualan #'.$sale->invoice_no.' an '.($customer !== '' ? $customer : 'Umum');

            $journalId = DB::table('journals')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $sale->business_unit_id,
                'journal_no' => 'JRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'journal_date' => date('Y-m-d', strtotime($sale->sale_date)),
                'source_type' => 'sale',
                'source_id' => $sale->id,
                'description' => $description,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $entries = [
                [
                    'journal_id' => $journalId,
                    'account_id' => $mapped[$debitKey],
                    'debit' => round((float) $sale->total, 2),
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'journal_id' => $journalId,
                    'account_id' => $mapped[$salesKey],
                    'debit' => 0,
                    'credit' => round((float) $sale->total, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            if (in_array($sale->business_type, ['retail', 'production'], true) && $hppTotal > 0) {
                $cogsKey = $sale->business_type === 'retail' ? 'cogs_merchandise' : 'cogs_finished_goods';

                $entries[] = [
                    'journal_id' => $journalId,
                    'account_id' => $mapped[$cogsKey],
                    'debit' => round($hppTotal, 2),
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $entries[] = [
                    'journal_id' => $journalId,
                    'account_id' => $mapped['inventory'],
                    'debit' => 0,
                    'credit' => round($hppTotal, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('journal_entries')->insert($entries);

            return (int) $journalId;
        });
    }
}
