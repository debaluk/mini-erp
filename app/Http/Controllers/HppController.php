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

        $retailBu = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('business_type', 'retail')
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        $retailRows = collect();

        if ($retailBu) {
            $movements = DB::table('stock_movements as sm')
                ->join('products as p', 'p.id', '=', 'sm.product_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
                ->where('sm.entity_id', $entityId)
                ->where('sm.business_unit_id', $retailBu->id)
                ->whereBetween('sm.occurred_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->select(
                    'sm.product_id',
                    'p.code',
                    'p.name',
                    'u.code as unit',
                    DB::raw("SUM(CASE WHEN sm.qty > 0 AND sm.movement_type IN ('purchase_in','production_in','adjustment_in','sale_return_in') THEN sm.qty ELSE 0 END) as qty_in"),
                    DB::raw("SUM(CASE WHEN sm.qty > 0 AND sm.movement_type IN ('purchase_in','production_in','adjustment_in','sale_return_in') THEN sm.qty * sm.unit_cost ELSE 0 END) as value_in"),
                    DB::raw("SUM(CASE WHEN sm.qty < 0 AND sm.movement_type = 'sale_out' THEN ABS(sm.qty) ELSE 0 END) as qty_out"),
                    DB::raw("SUM(CASE WHEN sm.qty < 0 AND sm.movement_type = 'sale_out' THEN ABS(sm.qty) * sm.unit_cost ELSE 0 END) as hpp_sales")
                )
                ->groupBy('sm.product_id','p.code','p.name','u.code')
                ->get()
                ->keyBy('product_id');

            $stockRows = DB::table('warehouses_stocks as ws')
                ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
                ->join('products as p', 'p.id', '=', 'ws.product_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
                ->where('ws.entity_id', $entityId)
                ->where('w.business_unit_id', $retailBu->id)
                ->select(
                    'ws.product_id',
                    DB::raw('SUM(ws.qty) as balance_qty'),
                    DB::raw('SUM(ws.qty * ws.avg_cost) as balance_value'),
                    DB::raw('CASE WHEN SUM(ws.qty) > 0 THEN SUM(ws.qty * ws.avg_cost) / SUM(ws.qty) ELSE 0 END as avg_cost'),
                    'p.code',
                    'p.name',
                    'u.code as unit'
                )
                ->groupBy('ws.product_id','p.code','p.name','u.code')
                ->get()
                ->keyBy('product_id');

            $ids = $movements->keys()->merge($stockRows->keys())->unique();

            $retailRows = $ids->map(function ($id) use ($movements, $stockRows) {
                $m = $movements->get($id);
                $s = $stockRows->get($id);

                $qtyIn = (float) ($m->qty_in ?? 0);
                $valueIn = (float) ($m->value_in ?? 0);
                $qtyOut = (float) ($m->qty_out ?? 0);
                $hppSales = (float) ($m->hpp_sales ?? 0);

                return (object) [
                    'code' => $m->code ?? $s->code ?? '-',
                    'name' => $m->name ?? $s->name ?? '-',
                    'unit' => $m->unit ?? $s->unit ?? '-',
                    'qty_in' => $qtyIn,
                    'value_in' => $valueIn,
                    'qty_out' => $qtyOut,
                    'hpp_unit' => $qtyOut > 0 ? $hppSales / $qtyOut : (float) ($s->avg_cost ?? 0),
                    'hpp_sales' => $hppSales,
                    'balance_qty' => (float) ($s->balance_qty ?? 0),
                    'balance_value' => (float) ($s->balance_value ?? 0),
                ];
            })->sortBy('name')->values();
        }

        $productionBu = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('business_type', 'production')
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        $productionRows = collect();

        if ($productionBu) {
            $productionRows = DB::table('productions as pr')
                ->join('boms as b', 'b.id', '=', 'pr.bom_id')
                ->join('products as p', 'p.id', '=', 'b.product_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
                ->leftJoin(DB::raw('(SELECT production_id, SUM(total_cost) total_material FROM production_material_usages GROUP BY production_id) mu'), 'mu.production_id', '=', 'pr.id')
                ->leftJoin(DB::raw('(SELECT production_id, SUM(CASE WHEN cost_group = "U" THEN amount ELSE 0 END) labor_cost, SUM(CASE WHEN cost_group <> "U" THEN amount ELSE 0 END) overhead_cost FROM production_costs GROUP BY production_id) pc'), 'pc.production_id', '=', 'pr.id')
                ->where('pr.entity_id', $entityId)
                ->where('pr.business_unit_id', $productionBu->id)
                ->whereBetween('pr.production_date', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->select(
                    'pr.production_no',
                    'pr.production_date',
                    'p.code as product_code',
                    'p.name as product',
                    'u.code as unit',
                    'b.code as bom',
                    'pr.good_output_qty as qty',
                    DB::raw('COALESCE(mu.total_material,0) as material_cost'),
                    DB::raw('COALESCE(pc.labor_cost,0) as labor_cost'),
                    DB::raw('COALESCE(pc.overhead_cost,0) as overhead_cost'),
                    'pr.total_cost'
                )
                ->orderByDesc('pr.production_date')
                ->orderByDesc('pr.id')
                ->get()
                ->map(function ($row) {
                    $qty = (float) $row->qty;
                    $total = (float) $row->total_cost;
                    $material = (float) $row->material_cost;
                    $labor = (float) $row->labor_cost;
                    $overhead = (float) $row->overhead_cost;

                    return (object) [
                        'production_no' => $row->production_no,
                        'date' => $row->production_date,
                        'product' => $row->product,
                        'unit' => $row->unit,
                        'bom' => $row->bom,
                        'qty' => $qty,
                        'material_cost' => $material,
                        'labor_cost' => $labor,
                        'freight_cost' => $overhead,
                        'total_cost' => $total,
                        'unit_cost' => $qty > 0 ? $total / $qty : 0,
                    ];
                });
        }

        return view('inventori.laporan.hpp', [
            'entity' => $entity,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'retailRows' => $retailRows,
            'productionRows' => $productionRows,
        ]);
    }
}
