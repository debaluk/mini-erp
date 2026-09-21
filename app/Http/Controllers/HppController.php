<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HppController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId();
        $entity = DB::table('entities')->where('id', $entityId)->first();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $businessUnitId = $request->integer('business_unit_id') ?: null;

        $retailRows = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->leftJoin('stock_movements as sm_in', function ($join) use ($entityId, $startDate, $endDate, $businessUnitId) {
                $join->on('sm_in.product_id', '=', 'p.id')
                    ->where('sm_in.entity_id', $entityId)
                    ->where('sm_in.qty', '>', 0)
                    ->whereBetween('sm_in.occurred_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
                if ($businessUnitId) {
                    $join->where('sm_in.business_unit_id', $businessUnitId);
                }
            })
            ->leftJoin('stock_movements as sm_out', function ($join) use ($entityId, $startDate, $endDate, $businessUnitId) {
                $join->on('sm_out.product_id', '=', 'p.id')
                    ->where('sm_out.entity_id', $entityId)
                    ->where('sm_out.qty', '<', 0)
                    ->whereBetween('sm_out.occurred_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
                if ($businessUnitId) {
                    $join->where('sm_out.business_unit_id', $businessUnitId);
                }
            })
            ->leftJoin('warehouses_stocks as ws', function ($join) use ($entityId, $businessUnitId) {
                $join->on('ws.product_id', '=', 'p.id')->where('ws.entity_id', $entityId);
                if ($businessUnitId) {
                    $join->whereIn('ws.warehouse_id', function ($q) use ($businessUnitId) {
                        $q->select('id')->from('warehouses')->where('business_unit_id', $businessUnitId);
                    });
                }
            })
            ->where('p.entity_id', $entityId)
            ->where('p.item_type', 'barang')
            ->select(
                'p.id',
                'p.code',
                'p.name',
                'u.code as unit',
                DB::raw('COALESCE(SUM(DISTINCT sm_in.qty),0) as qty_in'),
                DB::raw('COALESCE(SUM(DISTINCT sm_in.qty * sm_in.unit_cost),0) as value_in'),
                DB::raw('COALESCE(SUM(DISTINCT ABS(sm_out.qty)),0) as qty_out'),
                DB::raw('COALESCE(SUM(DISTINCT ABS(sm_out.qty) * sm_out.unit_cost),0) as hpp_sales'),
                DB::raw('COALESCE(SUM(DISTINCT ws.qty),0) as balance_qty'),
                DB::raw('COALESCE(SUM(DISTINCT ws.qty * ws.avg_cost),0) as balance_value'),
                DB::raw('COALESCE(AVG(ws.avg_cost),0) as hpp_unit')
            )
            ->groupBy('p.id','p.code','p.name','u.code')
            ->havingRaw('qty_in > 0 OR qty_out > 0 OR balance_qty > 0')
            ->orderBy('p.name')
            ->get();

        $productionRows = DB::table('productions as pr')
            ->join('boms as b', 'b.id', '=', 'pr.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('production_material_usages as pmu', 'pmu.production_id', '=', 'pr.id')
            ->leftJoin('production_costs as pc', 'pc.production_id', '=', 'pr.id')
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
                DB::raw('COALESCE(SUM(DISTINCT pmu.total_cost),0) as material_cost'),
                DB::raw("COALESCE(SUM(DISTINCT CASE WHEN pc.cost_group = 'labor' THEN pc.amount ELSE 0 END),0) as labor_cost"),
                DB::raw("COALESCE(SUM(DISTINCT CASE WHEN pc.cost_group IN ('overhead','other') THEN pc.amount ELSE 0 END),0) as overhead_cost"),
                'pr.total_cost as total_cost',
                DB::raw('CASE WHEN pr.good_output_qty > 0 THEN pr.total_cost / pr.good_output_qty ELSE 0 END as unit_cost')
            )
            ->groupBy('pr.id','pr.production_no','pr.production_date','p.code','p.name','b.code','pr.good_output_qty','pr.total_cost')
            ->orderByDesc('pr.production_date')
            ->orderByDesc('pr.id')
            ->get();

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
            'productionRows'
        ));
    }
}
