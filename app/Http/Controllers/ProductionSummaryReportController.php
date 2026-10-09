<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Maatwebsite\\Excel\\Facades\\Excel;
use App\\Exports\\ProductionSummaryReportExport;

class ProductionSummaryReportController extends Controller
{
    private function rows(Request $request)
    {
        $entityId = (int) (DB::table('entities')->value('id') ?? 0);
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
            ->get(['wo.id', 'wo.wo_no', 'wo.wo_date', 'wo.batch_qty', 'wo.target_output_qty', 'wo.bom_id', 'wo.warehouse_id', 'wo.status', 'p.id as product_id', 'p.name as product_name']);

        return $workOrders->map(function ($wo) use ($entityId) {
            $estimatedMaterial = 0.0;
            $bomItems = DB::table('bom_items as bi')
                ->join('products as p', 'p.id', '=', 'bi.product_id')
                ->where('bi.bom_id', $wo->bom_id)
                ->get(['bi.product_id', 'bi.unit_id', 'bi.qty', 'p.base_unit_id']);

            foreach ($bomItems as $item) {
                $factor = 1.0;
                if ((int) $item->unit_id !== (int) $item->base_unit_id) {
                    $factor = (float) (DB::table('product_unit_conversions')
                        ->where('product_id', $item->product_id)->where('unit_id', $item->unit_id)
                        ->where('is_active', 1)->value('conversion_factor') ?? 0);
                }
                $avgCost = (float) (DB::table('warehouses_stocks')
                    ->where('entity_id', $entityId)->where('warehouse_id', $wo->warehouse_id)
                    ->where('product_id', $item->product_id)->value('avg_cost') ?? 0);
                $estimatedMaterial += (float) $item->qty * $factor * (float) $wo->batch_qty * $avgCost;
            }

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
            } else {
                $draft = DB::table('production_work_order_results as r')
                    ->join('production_work_order_result_lines as l', 'l.production_work_order_result_id', '=', 'r.id')
                    ->where('r.production_work_order_id', $wo->id)
                    ->where('r.status', 'draft')
                    ->selectRaw('COALESCE(SUM(l.good_qty),0) as good_qty, COALESCE(SUM(l.reject_qty),0) as reject_qty')
                    ->first();
                $goodQty = (float) ($draft->good_qty ?? 0);
                $rejectQty = (float) ($draft->reject_qty ?? 0);
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
                'status' => $production ? 'posted' : $wo->status,
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

    public function export(Request $request)
    {
        return Excel::download(
            new ProductionSummaryReportExport($this->rows($request)),
            'laporan-produksi-' . now()->format('Ymd-His') . '.xlsx'
        );
    }
}
