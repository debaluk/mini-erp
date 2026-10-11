<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductionSummaryReportExport;

class ProductionSummaryReportController extends Controller
{
    private function rows(Request $request)
    {
        $entityId = (int) (auth()->user()?->entity_id ?? 0);
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

        $workOrders = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'wo.business_unit_id')
            ->where('wo.entity_id', $entityId)
            ->where('bu.business_type', 'production')
            ->whereDate('wo.wo_date', '>=', $dateFrom)
            ->whereDate('wo.wo_date', '<=', $dateTo)
            ->orderByDesc('wo.wo_date')->orderByDesc('wo.id')
            ->get(['wo.id', 'wo.wo_no', 'wo.wo_date', 'wo.batch_qty', 'wo.target_output_qty', 'wo.estimated_material_cost', 'wo.bom_id', 'wo.warehouse_id', 'wo.status', 'p.id as product_id', 'p.name as product_name']);

        return $workOrders->map(function ($wo) use ($entityId) {
            // Snapshot saat SPK dibuat/terakhir diedit; jangan hitung ulang dari avg_cost stok terkini.
            $estimatedMaterial = (float) $wo->estimated_material_cost;

            $costs = DB::table('production_work_order_costs')
                ->where('production_work_order_id', $wo->id)->get(['cost_group', 'amount']);
            $estimatedLabor = (float) $costs->where('cost_group', 'U')->sum('amount');
            $estimatedTotal = $estimatedMaterial + (float) $costs->sum('amount');

            $production = DB::table('productions')
                ->where('production_work_order_id', $wo->id)
                ->where('status', 'posted')
                ->orderByDesc('id')->first(['id', 'good_output_qty', 'reject_qty']);

            $actualMaterial = 0.0;
            $actualLabor = 0.0;
            $hppUnit = null;
            $goodQty = 0.0;
            $rejectQty = 0.0;

            if ($production) {
                $actualMaterial = (float) DB::table('production_wo_material_usages as u')
                    ->join('production_material_usage_items as ui', 'ui.production_material_usage_id', '=', 'u.id')
                    ->where('u.production_work_order_id', $wo->id)->where('u.status', 'approved')
                    ->sum('ui.total_cost');
                $actualLabor = (float) DB::table('production_costs')->where('production_id', $production->id)->where('cost_group', 'U')->sum('amount');
                $output = DB::table('production_outputs')->where('production_id', $production->id)->where('output_type', 'good')->orderByDesc('id')->first(['unit_cost', 'qty']);
                $goodQty = (float) ($output->qty ?? $production->good_output_qty ?? 0);
                $hppUnit = $output ? (float) $output->unit_cost : null;
                $rejectQty = (float) ($production->reject_qty ?? 0);
            }

            return (object) [
                'date' => $wo->wo_date,
                'wo_no' => $wo->wo_no,
                'target' => (float) $wo->target_output_qty,
                'estimated_cost' => round($estimatedTotal, 2),
                'material_cost' => round($production ? $actualMaterial : $estimatedMaterial, 2),
                'labor_cost' => round($production ? $actualLabor : $estimatedLabor, 2),
                'production_qty' => $goodQty,
                'hpp_unit' => $hppUnit,
                'reject_qty' => $rejectQty,
                'status' => $production ? 'completed' : $wo->status,
                'cost_basis' => $production ? 'Realisasi' : 'Estimasi',
                'product_name' => $wo->product_name,
            ];
        });
    }

    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());
        $rows = $this->rows($request);
        return view('inventori.laporan.produksi', compact('rows', 'dateFrom', 'dateTo'));
    }


    public function detail(int $id)
    {
        $entityId = (int) (auth()->user()?->entity_id ?? 0);
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        $wo = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'wo.business_unit_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->where('wo.id', $id)->where('wo.entity_id', $entityId)->where('bu.business_type', 'production')
            ->first(['wo.id', 'wo.wo_no', 'wo.wo_date', 'wo.target_output_qty', 'wo.batch_qty', 'wo.estimated_material_cost', 'wo.status', 'p.name as product_name', 'w.name as warehouse_name']);
        abort_if(!$wo, 404);

        $estimatedCosts = DB::table('production_work_order_costs')
            ->where('production_work_order_id', $wo->id)->orderBy('id')
            ->get(['cost_group', 'description', 'amount', 'pay_type', 'unit_rate']);
        $estimatedLabor = (float) $estimatedCosts->where('cost_group', 'U')->sum('amount');
        $estimatedTotal = (float) $wo->estimated_material_cost + (float) $estimatedCosts->sum('amount');

        $production = DB::table('productions')->where('production_work_order_id', $wo->id)
            ->where('status', 'posted')->orderByDesc('id')->first(['id', 'good_output_qty', 'reject_qty']);
        $materialLines = collect();
        $laborLines = collect();
        $actualMaterial = 0.0;
        $actualLabor = 0.0;
        $goodQty = 0.0;
        $rejectQty = 0.0;
        $hppUnit = null;

        if ($production) {
            $materialLines = DB::table('production_wo_material_usages as u')
                ->join('production_material_usage_items as ui', 'ui.production_material_usage_id', '=', 'u.id')
                ->join('products as p', 'p.id', '=', 'ui.product_id')
                ->where('u.production_work_order_id', $wo->id)->where('u.status', 'approved')
                ->groupBy('ui.product_id', 'p.name')->orderBy('p.name')
                ->get([
                    'p.name as product_name',
                    DB::raw('SUM(ui.base_actual_qty) as actual_qty'),
                    DB::raw('SUM(ui.total_cost) as total_cost'),
                ]);
            $actualMaterial = (float) $materialLines->sum('total_cost');
            $laborLines = DB::table('production_costs')->where('production_id', $production->id)
                ->where('cost_group', 'U')->orderBy('id')->get();
            $actualLabor = (float) $laborLines->sum('amount');
            $output = DB::table('production_outputs')->where('production_id', $production->id)
                ->where('output_type', 'good')->orderByDesc('id')->first(['unit_cost', 'qty']);
            $goodQty = (float) ($output->qty ?? $production->good_output_qty ?? 0);
            $hppUnit = $output ? (float) $output->unit_cost : null;
            $rejectQty = (float) ($production->reject_qty ?? 0);
        }

        $materialCost = $production ? $actualMaterial : (float) $wo->estimated_material_cost;
        $laborCost = $production ? $actualLabor : $estimatedLabor;
        $comparisonTotal = $materialCost + $laborCost;
        $variance = $estimatedTotal - $comparisonTotal;

        return view('inventori.laporan.produksi-detail', compact(
            'wo', 'estimatedCosts', 'estimatedLabor', 'estimatedTotal', 'production',
            'materialLines', 'laborLines', 'actualMaterial', 'actualLabor',
            'goodQty', 'rejectQty', 'hppUnit', 'materialCost', 'laborCost', 'comparisonTotal', 'variance'
        ));
    }

    public function export(Request $request)
    {
        return Excel::download(
            new ProductionSummaryReportExport($this->rows($request)),
            'laporan-produksi-' . now()->format('Ymd-His') . '.xlsx'
        );
    }
}
