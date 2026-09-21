<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Service\ServiceCostEngine;

class HppController extends Controller
{
    private function entityId(): int
    {
        return (int) (auth()->user()->entity_id ?? DB::table('entities')->value('id') ?? 0);
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId();
        $entity = DB::table('entities')->where('id', $entityId)->first();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $businessUnitId = $request->integer('business_unit_id') ?: null;

        $movementIn = DB::table('stock_movements')
            ->where('entity_id', $entityId)
            ->where('qty', '>', 0)
            ->whereBetween('occurred_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->when($businessUnitId, fn ($q) => $q->where('business_unit_id', $businessUnitId))
            ->select('product_id', DB::raw('SUM(qty) as qty_in'), DB::raw('SUM(qty * unit_cost) as value_in'))
            ->groupBy('product_id');

        $movementOut = DB::table('stock_movements')
            ->where('entity_id', $entityId)
            ->where('qty', '<', 0)
            ->whereBetween('occurred_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->when($businessUnitId, fn ($q) => $q->where('business_unit_id', $businessUnitId))
            ->select('product_id', DB::raw('SUM(ABS(qty)) as qty_out'), DB::raw('SUM(ABS(qty) * unit_cost) as hpp_sales'))
            ->groupBy('product_id');

        $stockBalance = DB::table('warehouses_stocks as ws')
            ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
            ->where('ws.entity_id', $entityId)
            ->when($businessUnitId, fn ($q) => $q->where('w.business_unit_id', $businessUnitId))
            ->select(
                'ws.product_id',
                DB::raw('SUM(ws.qty) as balance_qty'),
                DB::raw('SUM(ws.qty * ws.avg_cost) as balance_value'),
                DB::raw('CASE WHEN SUM(ws.qty) > 0 THEN SUM(ws.qty * ws.avg_cost) / SUM(ws.qty) ELSE 0 END as hpp_unit')
            )
            ->groupBy('ws.product_id');

        $retailRows = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->leftJoinSub($movementIn, 'mi', 'mi.product_id', '=', 'p.id')
            ->leftJoinSub($movementOut, 'mo', 'mo.product_id', '=', 'p.id')
            ->leftJoinSub($stockBalance, 'sb', 'sb.product_id', '=', 'p.id')
            ->where('p.entity_id', $entityId)
            ->where('p.item_type', 'barang')
            ->select(
                'p.id',
                'p.code',
                'p.name',
                'u.code as unit',
                DB::raw('COALESCE(mi.qty_in,0) as qty_in'),
                DB::raw('COALESCE(mi.value_in,0) as value_in'),
                DB::raw('COALESCE(mo.qty_out,0) as qty_out'),
                DB::raw('COALESCE(mo.hpp_sales,0) as hpp_sales'),
                DB::raw('COALESCE(sb.balance_qty,0) as balance_qty'),
                DB::raw('COALESCE(sb.balance_value,0) as balance_value'),
                DB::raw('COALESCE(sb.hpp_unit,0) as hpp_unit')
            )
            ->where(function ($q) {
                $q->whereNotNull('mi.product_id')
                    ->orWhereNotNull('mo.product_id')
                    ->orWhereNotNull('sb.product_id');
            })
            ->orderBy('p.name')
            ->get();

        $productionMaterial = DB::table('production_material_usages')
            ->select('production_id', DB::raw('SUM(total_cost) as material_cost'))
            ->groupBy('production_id');

        $productionCosts = DB::table('production_costs')
            ->select(
                'production_id',
                DB::raw("SUM(CASE WHEN cost_group = 'labor' THEN amount ELSE 0 END) as labor_cost"),
                DB::raw("SUM(CASE WHEN cost_group IN ('overhead','other') THEN amount ELSE 0 END) as overhead_cost")
            )
            ->groupBy('production_id');

        $productionRows = DB::table('productions as pr')
            ->join('boms as b', 'b.id', '=', 'pr.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoinSub($productionMaterial, 'pm', 'pm.production_id', '=', 'pr.id')
            ->leftJoinSub($productionCosts, 'pc', 'pc.production_id', '=', 'pr.id')
            ->where('pr.entity_id', $entityId)
            ->whereBetween('pr.production_date', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->when($businessUnitId, fn ($q) => $q->where('pr.business_unit_id', $businessUnitId))
            ->select(
                'pr.id',
                'pr.production_no',
                'pr.production_date as date',
                'p.code',
                'p.name as product',
                'b.code as bom',
                'pr.good_output_qty as qty',
                DB::raw('COALESCE(pm.material_cost,0) as material_cost'),
                DB::raw('COALESCE(pc.labor_cost,0) as labor_cost'),
                DB::raw('COALESCE(pc.overhead_cost,0) as overhead_cost'),
                'pr.total_cost as total_cost',
                DB::raw('CASE WHEN pr.good_output_qty > 0 THEN pr.total_cost / pr.good_output_qty ELSE 0 END as unit_cost')
            )
            ->orderByDesc('pr.production_date')
            ->orderByDesc('pr.id')
            ->get();

        $serviceRows = app(ServiceCostEngine::class)->report($entityId, $businessUnitId, $startDate, $endDate);

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('erp.hpp.index', compact(
            'entity',
            'startDate',
            'endDate',
            'businessUnitId',
            'businessUnits',
            'retailRows',
            'productionRows',
            'serviceRows'
        ));
    }
}
