<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockInventoryController extends Controller
{
    private function entityId(Request $request): int
    {
        return (int) ($request->user()->entity_id ?? 1);
    }

    private function movementDelta(object $movement): float
    {
        $qty = (float) $movement->qty;
        $incoming = ['opening', 'purchase_in', 'receipt_in', 'production_in', 'transfer_in', 'adjustment_in', 'return_in'];
        $outgoing = ['sale_out', 'purchase_return', 'production_out', 'transfer_out', 'adjustment_out', 'reject_out', 'return_out'];

        if (in_array($movement->movement_type, $incoming, true)) return abs($qty);
        if (in_array($movement->movement_type, $outgoing, true)) return -abs($qty);
        return $qty;
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId($request);
        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entityId)->where('is_active', 1)->orderBy('code')->get();
        $warehouses = DB::table('warehouses')
            ->where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get();

        $buMap = DB::table('warehouse_business_units as wbu')
            ->join('business_units as bu', function ($join) {
                $join->on('bu.id', '=', 'wbu.business_unit_id')
                    ->on('bu.entity_id', '=', 'wbu.entity_id');
            })
            ->where('wbu.entity_id', $entityId)
            ->select('wbu.warehouse_id', DB::raw("GROUP_CONCAT(bu.name ORDER BY bu.code SEPARATOR ', ') as business_unit_names"))
            ->groupBy('wbu.warehouse_id');

        $query = DB::table('warehouses_stocks as ws')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'ws.product_id')->on('p.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'ws.warehouse_id')->on('w.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoinSub($buMap, 'bm', fn ($join) => $join->on('bm.warehouse_id', '=', 'w.id'))
            ->where('ws.entity_id', $entityId)
            ->select(
                'p.id as product_id', 'p.code', 'p.sku', 'p.name as product_name',
                'u.code as unit_code', 'u.name as unit_name', 'w.id as warehouse_id',
                'w.name as warehouse_name', 'bm.business_unit_names', 'ws.qty'
            )
            ->orderBy('p.name')->orderBy('w.name');

        if ($request->filled('business_unit_id')) {
            $query->whereExists(function ($sub) use ($request, $entityId) {
                $sub->select(DB::raw(1))->from('warehouse_business_units as wbu')
                    ->whereColumn('wbu.warehouse_id', 'ws.warehouse_id')
                    ->where('wbu.entity_id', $entityId)
                    ->where('wbu.business_unit_id', $request->integer('business_unit_id'));
            });
        }
        if ($request->filled('warehouse_id')) $query->where('ws.warehouse_id', $request->integer('warehouse_id'));
        if ($request->filled('search')) {
            $search = '%' . trim($request->string('search')->toString()) . '%';
            $query->where(fn ($sub) => $sub->where('p.name', 'like', $search)
                ->orWhere('p.code', 'like', $search)->orWhere('p.sku', 'like', $search));
        }

        $rows = $query->paginate(15)->withQueryString();

        return view('inventori.persediaan.stok.index', compact('businessUnits', 'warehouses', 'rows'));
    }

    public function history(Request $request, int $product, int $warehouse)
    {
        $entityId = $this->entityId($request);
        $stock = DB::table('warehouses_stocks as ws')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'ws.product_id')->on('p.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'ws.warehouse_id')->on('w.entity_id', '=', 'ws.entity_id');
            })
            ->where('ws.entity_id', $entityId)->where('ws.product_id', $product)->where('ws.warehouse_id', $warehouse)
            ->select('p.id as product_id', 'p.code', 'p.sku', 'p.name as product_name', 'u.code as unit_code',
                'u.name as unit_name', 'w.id as warehouse_id', 'w.name as warehouse_name', 'ws.qty')
            ->first();
        abort_unless($stock, 404);

        $businessUnitNames = DB::table('warehouse_business_units as wbu')
            ->join('business_units as bu', function ($join) {
                $join->on('bu.id', '=', 'wbu.business_unit_id')->on('bu.entity_id', '=', 'wbu.entity_id');
            })
            ->where('wbu.entity_id', $entityId)->where('wbu.warehouse_id', $warehouse)
            ->orderBy('bu.code')->pluck('bu.name')->implode(', ');
        $stock->business_unit_names = $businessUnitNames ?: '-';

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $request->validate(['start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date']]);

        $movements = DB::table('stock_movements')
            ->where('entity_id', $entityId)->where('product_id', $product)->where('warehouse_id', $warehouse)
            ->whereDate('occurred_at', '<=', $endDate)
            ->orderBy('occurred_at')->orderBy('id')->get();

        $openingBalance = 0.0;
        $history = collect();
        $balance = 0.0;
        foreach ($movements as $movement) {
            $delta = $this->movementDelta($movement);
            if (substr((string) $movement->occurred_at, 0, 10) < $startDate) {
                $openingBalance += $delta;
                continue;
            }
            if (substr((string) $movement->occurred_at, 0, 10) > $endDate) continue;
            $balance += $delta;
            $history->push((object) [
                'date' => $movement->occurred_at,
                'reference' => $movement->reference_type && $movement->reference_id
                    ? strtoupper(str_replace('_', ' ', $movement->reference_type)) . ' #' . $movement->reference_id : '-',
                'description' => ucwords(str_replace('_', ' ', (string) $movement->movement_type)),
                'in' => $delta > 0 ? $delta : 0,
                'out' => $delta < 0 ? abs($delta) : 0,
                'balance' => $openingBalance + $balance,
                'unit_cost' => $movement->unit_cost,
            ]);
        }

        return view('inventori.persediaan.stok.history', compact('stock', 'startDate', 'endDate', 'openingBalance', 'history'));
    }
}
