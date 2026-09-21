<?php

namespace App\Services\Service;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ServiceCostEngine
{
    /**
     * Catat Beban Langsung (Direct Cost) untuk transaksi penjualan jasa.
     * Jenis beban tidak ditentukan oleh engine; akun biaya menjadi sumber klasifikasi.
     */
    public function addCost(int $saleId, array $data): object
    {
        return DB::transaction(function () use ($saleId, $data) {
            $sale = DB::table('sales')
                ->where('id', $saleId)
                ->lockForUpdate()
                ->first();

            if (!$sale) {
                throw new RuntimeException('Transaksi penjualan tidak ditemukan.');
            }

            $entityId = (int) ($data['entity_id'] ?? $sale->entity_id);
            $businessUnitId = (int) ($data['business_unit_id'] ?? $sale->business_unit_id);

            if ($entityId !== (int) $sale->entity_id || $businessUnitId !== (int) $sale->business_unit_id) {
                throw new InvalidArgumentException('Entity / Business Unit beban harus sama dengan penjualan.');
            }

            if ($businessUnitId !== (int) DB::table('business_units')
                ->where('id', $businessUnitId)
                ->where('business_type', 'service')
                ->value('id')) {
                throw new InvalidArgumentException('Beban Langsung hanya dapat dicatat untuk Business Unit Jasa.');
            }

            $accountId = (int) ($data['account_id'] ?? 0);
            $account = DB::table('chart_of_accounts')
                ->where('id', $accountId)
                ->where('entity_id', $entityId)
                ->where('is_active', true)
                ->where('is_postable', true)
                ->first();

            if (!$account) {
                throw new InvalidArgumentException('Akun biaya tidak valid atau bukan akun postable.');
            }

            if (!in_array($account->type, ['cogs', 'expense'], true)) {
                throw new InvalidArgumentException('Akun Beban Langsung harus bertipe COGS atau Expense.');
            }

            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Nilai Beban Langsung harus lebih besar dari 0.');
            }

            $description = trim((string) ($data['description'] ?? ''));
            if ($description === '') {
                throw new InvalidArgumentException('Keterangan Beban Langsung wajib diisi.');
            }

            $id = DB::table('service_costs')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $businessUnitId,
                'sale_id' => $saleId,
                'account_id' => $accountId,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'cost_date' => $data['cost_date'] ?? $sale->sale_date,
                'description' => $description,
                'amount' => $amount,
                'source' => $data['source'] ?? 'manual',
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('service_costs as sc')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'sc.account_id')
                ->where('sc.id', $id)
                ->select('sc.*', 'coa.code as account_code', 'coa.name as account_name')
                ->first();
        });
    }

    public function summary(int $saleId): object
    {
        $sale = DB::table('sales')->where('id', $saleId)->first();

        if (!$sale) {
            throw new RuntimeException('Transaksi penjualan tidak ditemukan.');
        }

        $costs = DB::table('service_costs as sc')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'sc.account_id')
            ->where('sc.sale_id', $saleId)
            ->select(
                'sc.id',
                'sc.account_id',
                'coa.code as account_code',
                'coa.name as account_name',
                'sc.description',
                'sc.amount',
                'sc.cost_date',
                'sc.vehicle_id',
                'sc.driver_id'
            )
            ->orderBy('sc.id')
            ->get();

        $directCost = (float) DB::table('service_costs')
            ->where('sale_id', $saleId)
            ->sum('amount');

        $revenue = (float) $sale->total;
        $grossProfit = $revenue - $directCost;
        $margin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;

        return (object) [
            'sale_id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'sale_date' => $sale->sale_date,
            'business_unit_id' => $sale->business_unit_id,
            'revenue' => round($revenue, 2),
            'direct_cost' => round($directCost, 2),
            'hpp_jasa' => round($directCost, 2),
            'gross_profit' => round($grossProfit, 2),
            'margin_percent' => round($margin, 2),
            'costs' => $costs,
        ];
    }

    public function report(int $entityId, ?int $businessUnitId = null, ?string $start = null, ?string $end = null)
    {
        $q = DB::table('sales as s')
            ->leftJoinSub(
                DB::table('service_costs')
                    ->select('sale_id', DB::raw('SUM(amount) as direct_cost'))
                    ->groupBy('sale_id'),
                'sc',
                'sc.sale_id',
                '=',
                's.id'
            )
            ->where('s.entity_id', $entityId)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('business_units as bu')
                    ->whereColumn('bu.id', 's.business_unit_id')
                    ->where('bu.business_type', 'service');
            });

        if ($businessUnitId) {
            $q->where('s.business_unit_id', $businessUnitId);
        }
        if ($start) {
            $q->whereDate('s.sale_date', '>=', $start);
        }
        if ($end) {
            $q->whereDate('s.sale_date', '<=', $end);
        }

        return $q->select(
            's.id as sale_id',
            's.invoice_no',
            's.sale_date',
            's.business_unit_id',
            's.total as revenue',
            DB::raw('COALESCE(sc.direct_cost, 0) as direct_cost'),
            DB::raw('(s.total - COALESCE(sc.direct_cost, 0)) as gross_profit'),
            DB::raw("CASE WHEN s.total > 0 THEN ((s.total - COALESCE(sc.direct_cost, 0)) / s.total) * 100 ELSE 0 END as margin_percent")
        )
        ->orderByDesc('s.sale_date')
        ->orderByDesc('s.id')
        ->get();
    }
}
