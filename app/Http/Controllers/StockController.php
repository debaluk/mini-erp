<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    private function entityId(Request $request): int
    {
        $entityId = (int) ($request->user()->entity_id ?? 0);
        abort_unless($entityId > 0, 403, 'Entitas pengguna tidak valid.');

        return $entityId;
    }

    /**
     * Saldo stok hanya ditampilkan bila item aktif dan item dipetakan ke
     * unit bisnis aktif yang juga memetakan gudang tersebut.
     */
    private function stockQuery(Request $request, int $entityId)
    {
        return DB::table('warehouses_stocks as ws')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'ws.product_id')
                    ->on('p.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'ws.warehouse_id')
                    ->on('w.entity_id', '=', 'ws.entity_id');
            })
            ->where('ws.entity_id', $entityId)
            ->where('p.is_active', 1)
            ->where('w.is_active', 1)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('product_business_units as pbu')
                    ->join('business_units as bu', function ($join) {
                        $join->on('bu.id', '=', 'pbu.business_unit_id')
                            ->on('bu.entity_id', '=', 'p.entity_id');
                    })
                    ->join('warehouse_business_units as wbu', function ($join) {
                        $join->on('wbu.business_unit_id', '=', 'bu.id')
                            ->on('wbu.entity_id', '=', 'bu.entity_id');
                    })
                    ->whereColumn('pbu.product_id', 'p.id')
                    ->whereColumn('wbu.warehouse_id', 'ws.warehouse_id')
                    ->whereColumn('wbu.entity_id', 'ws.entity_id')
                    ->where('bu.is_active', 1);
            })
            ->when($request->filled('warehouse_id'), function ($query) use ($request, $entityId) {
                $query->where('ws.warehouse_id', $request->integer('warehouse_id'))
                    ->whereExists(function ($sub) use ($request, $entityId) {
                        $sub->select(DB::raw(1))
                            ->from('warehouses as filter_w')
                            ->whereColumn('filter_w.id', 'ws.warehouse_id')
                            ->where('filter_w.entity_id', $entityId)
                            ->where('filter_w.is_active', 1)
                            ->whereExists(function ($mapped) {
                                $mapped->select(DB::raw(1))
                                    ->from('warehouse_business_units as filter_wbu')
                                    ->join('business_units as filter_bu', function ($join) {
                                        $join->on('filter_bu.id', '=', 'filter_wbu.business_unit_id')
                                            ->on('filter_bu.entity_id', '=', 'filter_wbu.entity_id');
                                    })
                                    ->whereColumn('filter_wbu.warehouse_id', 'filter_w.id')
                                    ->whereColumn('filter_wbu.entity_id', 'filter_w.entity_id')
                                    ->where('filter_bu.is_active', 1);
                            });
                    });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . trim((string) $request->query('search')) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('p.name', 'like', $term)
                        ->orWhere('p.code', 'like', $term)
                        ->orWhere('p.sku', 'like', $term);
                });
            })
            ->select(
                'ws.id',
                'p.id as product_id',
                'p.sku',
                'p.code',
                'p.name as product_name',
                'u.code as unit_code',
                'u.name as unit_name',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                'ws.qty',
                'ws.avg_cost',
                DB::raw('(ws.qty * ws.avg_cost) as stock_value')
            )
            ->orderBy('p.name')
            ->orderBy('w.name');
    }

    private function activeWarehouses(int $entityId)
    {
        return DB::table('warehouses as w')
            ->join('warehouse_business_units as wbu', function ($join) {
                $join->on('wbu.warehouse_id', '=', 'w.id')
                    ->on('wbu.entity_id', '=', 'w.entity_id');
            })
            ->join('business_units as bu', function ($join) {
                $join->on('bu.id', '=', 'wbu.business_unit_id')
                    ->on('bu.entity_id', '=', 'wbu.entity_id');
            })
            ->where('w.entity_id', $entityId)
            ->where('w.is_active', 1)
            ->where('bu.is_active', 1)
            ->orderBy('w.name')
            ->distinct()
            ->get(['w.id', 'w.name']);
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId($request);
        $warehouses = $this->activeWarehouses($entityId);
        $rows = $this->stockQuery($request, $entityId)->paginate(20)->withQueryString();

        return view('inventori.laporan.persediaan', compact('rows', 'warehouses'));
    }

    public function export(Request $request)
    {
        $entityId = $this->entityId($request);
        $rows = $this->stockQuery($request, $entityId)
            ->get([
                'p.code',
                'p.sku',
                'p.name as product_name',
                'u.code as unit_code',
                'u.name as unit_name',
                'w.name as warehouse_name',
                'ws.qty',
                'ws.avg_cost',
                DB::raw('(ws.qty * ws.avg_cost) as stock_value'),
            ]);

        return response()->json([
            'entity_name' => DB::table('entities')->where('id', $entityId)->value('name') ?? 'NAMA ENTITAS',
            'rows' => $rows,
        ]);
    }

    public function detail(Request $request, int $product, int $warehouse)
    {
        $entityId = $this->entityId($request);
        $stock = $this->stockQuery(new Request(), $entityId)
            ->where('p.id', $product)
            ->where('w.id', $warehouse)
            ->first();
        abort_unless($stock, 404);

        $movements = DB::table('stock_movements as sm')
            ->where('sm.entity_id', $entityId)
            ->where('sm.product_id', $product)
            ->where('sm.warehouse_id', $warehouse)
            ->orderBy('sm.occurred_at')
            ->orderBy('sm.id')
            ->get();

        $saldo = 0;
        $history = $movements->map(function ($movement) use (&$saldo) {
            $qty = (float) $movement->qty;
            $in = 0;
            $out = 0;

            if (in_array($movement->movement_type, ['opening', 'purchase_in', 'receipt_in', 'production_in', 'transfer_in', 'adjustment_in', 'return_in'], true)) {
                $in = $qty;
            } elseif (in_array($movement->movement_type, ['sale_out', 'purchase_return', 'production_out', 'transfer_out', 'adjustment_out', 'reject_out', 'return_out'], true)) {
                $out = $qty;
            } else {
                $in = $qty > 0 ? $qty : 0;
                $out = $qty < 0 ? abs($qty) : 0;
            }

            $saldo += $in - $out;

            return (object) [
                'date' => $movement->occurred_at,
                'reference' => $movement->reference_type && $movement->reference_id
                    ? strtoupper(str_replace('_', ' ', $movement->reference_type)) . ' #' . $movement->reference_id
                    : '-',
                'movement_type' => $movement->movement_type,
                'in' => $in,
                'out' => $out,
                'balance' => $saldo,
                'unit_cost' => $movement->unit_cost,
            ];
        });

        return view('inventori.laporan.kartu-stok', compact('stock', 'history'));
    }
}
