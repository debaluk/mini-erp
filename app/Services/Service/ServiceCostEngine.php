<?php

namespace App\Services\Service;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ServiceCostEngine
{
    /**
     * Record one direct cost against a service delivery.
     *
     * Direct costs are intentionally separate from inventory valuation.
     * Typical types: fuel, driver, toll, parking, loading, accommodation,
     * maintenance-direct, and other.
     */
    public function addCost(int $deliveryId, array $data): object
    {
        return DB::transaction(function () use ($deliveryId, $data) {
            $delivery = DB::table('deliveries')
                ->where('id', $deliveryId)
                ->lockForUpdate()
                ->first();

            if (!$delivery) {
                throw new RuntimeException('Delivery / order jasa tidak ditemukan.');
            }

            $entityId = (int) ($data['entity_id'] ?? $delivery->entity_id);
            $businessUnitId = (int) ($data['business_unit_id'] ?? $delivery->business_unit_id);

            if ($entityId !== (int) $delivery->entity_id || $businessUnitId !== (int) $delivery->business_unit_id) {
                throw new InvalidArgumentException('Entity / Business Unit biaya harus sama dengan order jasa.');
            }

            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Nilai direct cost harus lebih besar dari 0.');
            }

            $costType = trim((string) ($data['cost_type'] ?? 'other'));
            $description = trim((string) ($data['description'] ?? 'Direct cost jasa'));

            if ($description === '') {
                throw new InvalidArgumentException('Deskripsi direct cost wajib diisi.');
            }

            $id = DB::table('service_costs')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $businessUnitId,
                'delivery_id' => $deliveryId,
                'vehicle_id' => $data['vehicle_id'] ?? $delivery->vehicle_id,
                'driver_id' => $data['driver_id'] ?? $delivery->driver_id,
                'cost_date' => $data['cost_date'] ?? $delivery->delivery_date,
                'cost_type' => $costType,
                'description' => $description,
                'amount' => $amount,
                'source' => $data['source'] ?? 'manual',
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('service_costs')->where('id', $id)->first();
        });
    }

    public function setRevenue(int $deliveryId, float $amount): object
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Pendapatan jasa tidak boleh negatif.');
        }

        DB::table('deliveries')->where('id', $deliveryId)->update([
            'service_revenue' => round($amount, 2),
            'updated_at' => now(),
        ]);

        return $this->summary($deliveryId);
    }

    public function summary(int $deliveryId): object
    {
        $delivery = DB::table('deliveries')->where('id', $deliveryId)->first();

        if (!$delivery) {
            throw new RuntimeException('Delivery / order jasa tidak ditemukan.');
        }

        $costs = DB::table('service_costs')
            ->where('delivery_id', $deliveryId)
            ->select('cost_type', DB::raw('SUM(amount) as total'))
            ->groupBy('cost_type')
            ->orderBy('cost_type')
            ->get();

        $totalDirectCost = (float) DB::table('service_costs')
            ->where('delivery_id', $deliveryId)
            ->sum('amount');

        $revenue = (float) $delivery->service_revenue;
        $grossProfit = $revenue - $totalDirectCost;
        $margin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;

        return (object) [
            'delivery_id' => $delivery->id,
            'delivery_no' => $delivery->delivery_no,
            'delivery_date' => $delivery->delivery_date,
            'business_unit_id' => $delivery->business_unit_id,
            'revenue' => round($revenue, 2),
            'direct_cost' => round($totalDirectCost, 2),
            'gross_profit' => round($grossProfit, 2),
            'margin_percent' => round($margin, 2),
            'cost_breakdown' => $costs,
        ];
    }

    public function report(int $entityId, ?int $businessUnitId = null, ?string $start = null, ?string $end = null)
    {
        $q = DB::table('deliveries as d')
            ->leftJoin('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->leftJoin('drivers as dr', 'dr.id', '=', 'd.driver_id')
            ->leftJoinSub(
                DB::table('service_costs')
                    ->select('delivery_id', DB::raw('SUM(amount) as direct_cost'))
                    ->groupBy('delivery_id'),
                'sc',
                'sc.delivery_id',
                '=',
                'd.id'
            )
            ->where('d.entity_id', $entityId);

        if ($businessUnitId) {
            $q->where('d.business_unit_id', $businessUnitId);
        }
        if ($start) {
            $q->whereDate('d.delivery_date', '>=', $start);
        }
        if ($end) {
            $q->whereDate('d.delivery_date', '<=', $end);
        }

        return $q->select(
            'd.id',
            'd.delivery_no',
            'd.delivery_date',
            'd.business_unit_id',
            'd.destination',
            'd.distance_km',
            'd.service_revenue',
            DB::raw('COALESCE(sc.direct_cost, 0) as direct_cost'),
            DB::raw('(d.service_revenue - COALESCE(sc.direct_cost, 0)) as gross_profit'),
            DB::raw("CASE WHEN d.service_revenue > 0 THEN ((d.service_revenue - COALESCE(sc.direct_cost, 0)) / d.service_revenue) * 100 ELSE 0 END as margin_percent"),
            'v.plate_number',
            'dr.name as driver_name'
        )
        ->orderByDesc('d.delivery_date')
        ->orderByDesc('d.id')
        ->get();
    }
}
